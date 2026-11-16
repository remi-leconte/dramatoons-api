<?php

namespace App\Tests\Api\Utilisateur;

use App\Repository\UserRepository;
use App\Tests\Api\ApiTestCase;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\WebtoonFactory;
use App\Tests\Factory\WebtoonUserFactory;
use DoctrineMigrations\Version20261116180000;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\TestDox;
use Psr\Log\NullLogger;

#[TestDox('Administration des utilisateurs : réservée aux administrateurs')]
final class AdministrationTest extends ApiTestCase
{
    #[TestDox('La liste des utilisateurs : 401 pour un visiteur, 403 pour un utilisateur ou un modérateur, accessible à un administrateur')]
    public function testListeReserveeAuxAdministrateurs(): void
    {
        $this->client()->request('GET', '/users');
        $this->assertResponseStatusCodeSame(401);

        $this->client(UserFactory::createOne())->request('GET', '/users');
        $this->assertResponseStatusCodeSame(403);

        $this->client(UserFactory::new()->moderateur()->create())->request('GET', '/users');
        $this->assertResponseStatusCodeSame(403);

        $this->client(UserFactory::new()->administrateur()->create())->request('GET', '/users');
        $this->assertResponseIsSuccessful();
    }

    #[TestDox('Un administrateur filtre et trie la liste des utilisateurs')]
    public function testFiltreEtTri(): void
    {
        foreach (['zorro-filtre', 'alice-filtre', 'bob-filtre', 'autre'] as $login) {
            UserFactory::createOne(['login' => $login]);
        }

        $reponse = $this->client(UserFactory::new()->administrateur()->create())->request('GET', '/users', [
            'query' => ['login' => 'filtre', 'order' => ['login' => 'asc']],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(['alice-filtre', 'bob-filtre', 'zorro-filtre'], array_column($reponse->toArray()['member'], 'login'));
    }

    #[TestDox('Un administrateur ou un modérateur voit les suivis d’un utilisateur, un utilisateur non : 403')]
    public function testSuivisDUnUtilisateur(): void
    {
        $suivi = WebtoonUserFactory::createOne(['webtoon' => WebtoonFactory::new()->publie()]);
        WebtoonUserFactory::createOne();
        $parametres = ['query' => ['reader' => '/users/'.$suivi->getReader()->getId()]];

        foreach ([UserFactory::new()->administrateur()->create(), UserFactory::new()->moderateur()->create()] as $lecteur) {
            $reponse = $this->client($lecteur)->request('GET', '/webtoon_users', $parametres);
            $this->assertResponseIsSuccessful();
            $this->assertSame([$suivi->getId()], array_column($reponse->toArray()['member'], 'id'));
        }

        $this->client(UserFactory::createOne())->request('GET', '/webtoon_users', $parametres);
        $this->assertResponseStatusCodeSame(403);
    }

    #[TestDox('Un administrateur modifie le login, l’email et le rôle d’un utilisateur')]
    public function testModificationParUnAdministrateur(): void
    {
        $utilisateur = UserFactory::createOne();
        $administrateur = UserFactory::new()->administrateur()->create();

        foreach (['ROLE_MODO', 'ROLE_ADMIN', 'ROLE_USER'] as $role) {
            $this->patch($administrateur, '/users/'.$utilisateur->getId(), ['login' => 'login-'.$role, 'email' => $role.'@dramatoons.test', 'roles' => [$role]]);
            $this->assertResponseIsSuccessful();
            $this->assertJsonContains(['login' => 'login-'.$role, 'email' => $role.'@dramatoons.test', 'role' => $role]);
        }
    }

    #[TestDox('Un utilisateur n’a qu’un seul rôle, parmi utilisateur, modérateur et administrateur : 422 sinon')]
    public function testUnSeulRoleValide(): void
    {
        $utilisateur = UserFactory::createOne();
        $administrateur = UserFactory::new()->administrateur()->create();

        foreach ([['ROLE_MODO', 'ROLE_ADMIN'], ['ROLE_INCONNU'], [null], []] as $roles) {
            $this->patch($administrateur, '/users/'.$utilisateur->getId(), ['roles' => $roles]);
            $this->assertResponseStatusCodeSame(422, 'Rôles refusés : '.json_encode($roles));
        }
    }

    #[TestDox('La migration des rôles donne à chaque compte un seul rôle, le plus haut qu’il avait : un compte créé sans rôle peut de nouveau modifier son profil')]
    public function testMigrationDesRoles(): void
    {
        $attendus = [];
        foreach ([[[], 'ROLE_USER'], [['ROLE_USER', 'ROLE_MODO'], 'ROLE_MODO'], [['ROLE_ADMIN', 'ROLE_MODO'], 'ROLE_ADMIN'], [['ROLE_INCONNU'], 'ROLE_USER'], [['ROLE_MODO'], 'ROLE_MODO']] as [$roles, $role]) {
            $attendus[UserFactory::createOne(['roles' => $roles])->getId()] = [$role];
        }

        require_once \dirname(__DIR__, 3).'/migrations/Version20261116180000.php';
        $connexion = static::getContainer()->get(Connection::class);
        $migration = new Version20261116180000($connexion, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $requete) {
            $connexion->executeStatement($requete->getStatement());
        }

        foreach ($attendus as $id => $roles) {
            $this->assertSame($roles, json_decode($connexion->fetchOne('SELECT roles FROM user WHERE id = ?', [$id]), true));
        }
        $sansRole = UserFactory::find(array_key_first($attendus));
        $this->patch($sansRole, '/users/'.$sansRole->getId(), ['email' => 'migre@dramatoons.test']);
        $this->assertResponseIsSuccessful();
    }

    #[TestDox('Un administrateur désactive puis réactive un compte : la connexion est refusée puis acceptée')]
    public function testActivationDUnCompte(): void
    {
        $utilisateur = UserFactory::new()->avecMotDePasse('secret')->create();
        $administrateur = UserFactory::new()->administrateur()->create();

        $this->patch($administrateur, '/users/'.$utilisateur->getId(), ['publish' => false]);
        $this->assertResponseIsSuccessful();
        $this->connexion($utilisateur->getLogin(), 'secret');
        $this->assertResponseStatusCodeSame(401);

        $this->patch($administrateur, '/users/'.$utilisateur->getId(), ['publish' => true]);
        $this->assertResponseIsSuccessful();
        $this->connexion($utilisateur->getLogin(), 'secret');
        $this->assertResponseIsSuccessful();
    }

    #[TestDox('Un administrateur ne peut pas modifier la vérification de l’email, la dernière connexion ni les dates d’un compte')]
    public function testChampsNonModifiablesParUnAdministrateur(): void
    {
        $utilisateur = UserFactory::createOne(['verified' => false, 'lastLogin' => null]);

        $this->patch(UserFactory::new()->administrateur()->create(), '/users/'.$utilisateur->getId(), [
            'verified' => true,
            'lastLogin' => '2020-01-01T00:00:00+00:00',
            'created' => '2020-01-01T00:00:00+00:00',
        ]);

        $enBase = static::getContainer()->get(UserRepository::class)->find($utilisateur->getId());
        $this->assertFalse($enBase->isVerified());
        $this->assertNull($enBase->getLastLogin());
        $this->assertNotSame('2020', $enBase->getCreated()->format('Y'));
    }

    #[TestDox('Un administrateur ne peut pas changer directement le mot de passe d’un autre utilisateur')]
    public function testMotDePasseDUnAutre(): void
    {
        $utilisateur = UserFactory::new()->avecMotDePasse('secret')->create();

        $this->patch(UserFactory::new()->administrateur()->create(), '/users/'.$utilisateur->getId(), ['password' => 'change']);

        $this->connexion($utilisateur->getLogin(), 'secret');
        $this->assertResponseIsSuccessful();
    }

    #[TestDox('Un administrateur envoie à un utilisateur l’email de mot de passe oublié ou un nouveau lien de vérification')]
    public function testEnvoiDEmailsParUnAdministrateur(): void
    {
        $utilisateur = UserFactory::createOne(['verified' => false]);
        $administrateur = UserFactory::new()->administrateur()->create();

        $this->post($administrateur, '/users/forgot-password', ['email' => $utilisateur->getEmail()]);
        $this->assertResponseIsSuccessful();
        $this->jetonDuMail('/reset-password', $utilisateur->getEmail());

        $this->post($administrateur, '/users/resend-verification', ['email' => $utilisateur->getEmail()]);
        $this->assertResponseIsSuccessful();
        $this->jetonDuMail('/verify', $utilisateur->getEmail());
    }
}
