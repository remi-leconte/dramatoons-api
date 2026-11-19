<?php

namespace App\Tests\Api\Utilisateur;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Api\ApiTestCase;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Compte : inscription (POST /users), connexion (POST /login) et session (POST /token/refresh)')]
final class CompteTest extends ApiTestCase
{
    /**
     * @param array<string, mixed> $donnees
     */
    private function inscription(array $donnees): void
    {
        $this->post(null, '/users', $donnees + ['login' => 'nouveau', 'email' => 'nouveau@dramatoons.test', 'password' => 'secret']);
    }

    private function utilisateur(string $login): ?User
    {
        return static::getContainer()->get(UserRepository::class)->findOneBy(['login' => $login]);
    }

    #[TestDox('Un visiteur crée un compte : rôle utilisateur, compte actif, email non vérifié')]
    public function testInscription(): void
    {
        $this->inscription([]);

        $this->assertResponseStatusCodeSame(201);
        $utilisateur = $this->utilisateur('nouveau');
        $this->assertSame('nouveau@dramatoons.test', $utilisateur->getEmail());
        $this->assertSame('ROLE_USER', $utilisateur->getRole());
        $this->assertTrue($utilisateur->isPublish());
        $this->assertFalse($utilisateur->isVerified());
    }

    #[TestDox('À l’inscription, les rôles, l’état du compte et la vérification envoyés sont ignorés')]
    public function testInscriptionChampsProteges(): void
    {
        $this->inscription(['roles' => ['ROLE_ADMIN'], 'publish' => false, 'verified' => true]);

        $this->assertResponseStatusCodeSame(201);
        $utilisateur = $this->utilisateur('nouveau');
        $this->assertSame(['ROLE_USER'], $utilisateur->getRoles());
        $this->assertTrue($utilisateur->isPublish());
        $this->assertFalse($utilisateur->isVerified());
    }

    #[TestDox('Un compte créé par un administrateur connecté est lui aussi un utilisateur actif, au email non vérifié')]
    public function testInscriptionParUnAdministrateur(): void
    {
        $this->post(UserFactory::new()->administrateur()->create(), '/users', [
            'login' => 'nouveau', 'email' => 'nouveau@dramatoons.test', 'password' => 'secret',
            'roles' => ['ROLE_ADMIN'], 'publish' => false, 'verified' => true,
        ]);

        $this->assertResponseStatusCodeSame(201);
        $utilisateur = $this->utilisateur('nouveau');
        $this->assertSame(['ROLE_USER'], $utilisateur->getRoles());
        $this->assertTrue($utilisateur->isPublish());
        $this->assertFalse($utilisateur->isVerified());
    }

    #[TestDox('Le login, l’email et le mot de passe sont obligatoires : 422')]
    public function testInscriptionChampsObligatoires(): void
    {
        foreach (['login', 'email', 'password'] as $champ) {
            $this->inscription([$champ => '']);
            $this->assertResponseStatusCodeSame(422);
        }
    }

    #[TestDox('Le login et l’email sont uniques : 422')]
    public function testInscriptionLoginEtEmailUniques(): void
    {
        UserFactory::createOne(['login' => 'pris', 'email' => 'pris@dramatoons.test']);

        $this->inscription(['login' => 'pris']);
        $this->assertResponseStatusCodeSame(422);

        $this->inscription(['email' => 'pris@dramatoons.test']);
        $this->assertResponseStatusCodeSame(422);
    }

    #[TestDox('On se connecte avec son login et son mot de passe ; la date de dernière connexion est enregistrée')]
    public function testConnexionParLogin(): void
    {
        UserFactory::new()->avecMotDePasse('secret')->create(['login' => 'lecteur', 'lastLogin' => null]);

        $reponse = $this->connexion('lecteur', 'secret');

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $reponse->toArray());
        $this->assertArrayHasKey('refresh_token', $reponse->toArray());
        $this->assertNotNull($this->utilisateur('lecteur')->getLastLogin());
    }

    #[TestDox('On ne se connecte pas avec son email, ni avec un mauvais mot de passe : 401')]
    public function testConnexionRefusee(): void
    {
        UserFactory::new()->avecMotDePasse('secret')->create(['login' => 'lecteur', 'email' => 'lecteur@dramatoons.test']);

        $this->connexion('lecteur@dramatoons.test', 'secret');
        $this->assertResponseStatusCodeSame(401);

        $this->connexion('lecteur', 'mauvais');
        $this->assertResponseStatusCodeSame(401);
    }

    #[TestDox('Un compte désactivé ne peut pas se connecter : 401 « Votre compte a été désactivé. »')]
    public function testCompteDesactive(): void
    {
        UserFactory::new()->avecMotDePasse('secret')->create(['login' => 'lecteur', 'publish' => false]);

        $this->connexion('lecteur', 'secret');

        $this->assertResponseStatusCodeSame(401);
        $this->assertJsonContains(['message' => 'Votre compte a été désactivé.']);
    }

    #[TestDox('Un email non vérifié n’empêche ni de se connecter ni d’utiliser le site')]
    public function testEmailNonVerifie(): void
    {
        $utilisateur = UserFactory::new()->avecMotDePasse('secret')->create(['login' => 'lecteur', 'verified' => false]);

        $this->connexion('lecteur', 'secret');
        $this->assertResponseIsSuccessful();

        $this->listing($utilisateur, 'catalogue');
        $this->post($utilisateur, '/webtoons', ['title' => ['title' => 'Mon webtoon'], 'status' => 'ongoing']);
        $this->assertResponseStatusCodeSame(201);
    }

    #[TestDox('La session dure 1 h ; chaque renouvellement donne un nouveau jeton de renouvellement, valable 30 jours à partir de ce renouvellement')]
    public function testDureeDeSession(): void
    {
        UserFactory::new()->avecMotDePasse('secret')->create(['login' => 'lecteur']);
        $connexion = $this->connexion('lecteur', 'secret')->toArray();

        $jeton = json_decode(base64_decode(strtr(explode('.', $connexion['token'])[1], '-_', '+/')), true);
        $this->assertSame(3600, $jeton['exp'] - $jeton['iat']);

        // Jeton vieilli de 25 jours : le renouvellement doit repartir pour 30 jours, pas garder l'échéance de la connexion
        $jetons = static::getContainer()->get('doctrine')->getRepository(RefreshToken::class);
        $jetons->findOneBy(['refreshToken' => $connexion['refresh_token']])->setValid(new \DateTime('+5 days'));
        static::getContainer()->get('doctrine')->getManager()->flush();

        $renouvellement = $this->client()->request('POST', '/token/refresh', ['json' => ['refresh_token' => $connexion['refresh_token']]])->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $renouvellement);
        $this->assertNotSame($connexion['refresh_token'], $renouvellement['refresh_token']);

        $validite = static::getContainer()->get('doctrine')->getRepository(RefreshToken::class)
            ->findOneBy(['refreshToken' => $renouvellement['refresh_token']])->getValid();
        $this->assertEqualsWithDelta((new \DateTimeImmutable('+30 days'))->getTimestamp(), $validite->getTimestamp(), 60);
    }

    #[TestDox('Un jeton de renouvellement ne sert qu’une fois : 401 à la seconde utilisation')]
    public function testJetonDeRenouvellementAUsageUnique(): void
    {
        UserFactory::new()->avecMotDePasse('secret')->create(['login' => 'lecteur']);
        $jeton = $this->connexion('lecteur', 'secret')->toArray()['refresh_token'];

        $this->client()->request('POST', '/token/refresh', ['json' => ['refresh_token' => $jeton]]);
        $this->assertResponseIsSuccessful();

        $this->client()->request('POST', '/token/refresh', ['json' => ['refresh_token' => $jeton]]);
        $this->assertResponseStatusCodeSame(401);
    }

    #[TestDox('Un compte désactivé ne peut plus renouveler sa session : 401')]
    public function testRenouvellementCompteDesactive(): void
    {
        UserFactory::new()->avecMotDePasse('secret')->create(['login' => 'lecteur']);
        $jeton = $this->connexion('lecteur', 'secret')->toArray()['refresh_token'];
        $this->utilisateur('lecteur')->setPublish(false);
        static::getContainer()->get('doctrine')->getManager()->flush();

        $this->client()->request('POST', '/token/refresh', ['json' => ['refresh_token' => $jeton]]);

        $this->assertResponseStatusCodeSame(401);
    }
}
