<?php

namespace App\Tests\Api\Utilisateur;

use App\Repository\UserRepository;
use App\Tests\Api\ApiTestCase;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Profil (PATCH /users/{id}) : login, email et mot de passe')]
final class ProfilTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function roles(): iterable
    {
        yield 'utilisateur' => ['ROLE_USER'];
        yield 'modérateur' => ['ROLE_MODO'];
        yield 'administrateur' => ['ROLE_ADMIN'];
    }

    #[DataProvider('roles')]
    #[TestDox('Un $_dataName modifie son login, son email et son mot de passe')]
    public function testModificationDuProfil(string $role): void
    {
        $utilisateur = UserFactory::new()->avecMotDePasse('ancien')->create(['roles' => [$role]]);

        $this->patch($utilisateur, '/users/'.$utilisateur->getId(), ['login' => 'nouveau-login', 'email' => 'nouveau@dramatoons.test', 'password' => 'nouveau']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['login' => 'nouveau-login', 'email' => 'nouveau@dramatoons.test']);
        $this->connexion('nouveau-login', 'nouveau');
        $this->assertResponseIsSuccessful();
    }

    #[TestDox('Une modification sans mot de passe garde l’ancien')]
    public function testModificationSansMotDePasse(): void
    {
        $utilisateur = UserFactory::new()->avecMotDePasse('ancien')->create();

        $this->patch($utilisateur, '/users/'.$utilisateur->getId(), ['login' => 'nouveau-login']);

        $this->assertResponseIsSuccessful();
        $this->connexion('nouveau-login', 'ancien');
        $this->assertResponseIsSuccessful();
    }

    #[TestDox('Après un changement de login, l’ancienne session n’est plus valable et ne peut pas être renouvelée : 401')]
    public function testDeconnexionApresChangementDeLogin(): void
    {
        $utilisateur = UserFactory::new()->avecMotDePasse('secret')->create();
        $session = $this->connexion($utilisateur->getLogin(), 'secret')->toArray();

        $this->client()->request('PATCH', '/users/'.$utilisateur->getId(), [
            'json' => ['login' => 'nouveau-login'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'auth_bearer' => $session['token'],
        ]);
        $this->assertResponseIsSuccessful();

        $this->client()->request('GET', '/me', ['auth_bearer' => $session['token']]);
        $this->assertResponseStatusCodeSame(401);

        $this->client()->request('POST', '/token/refresh', ['json' => ['refresh_token' => $session['refresh_token']]]);
        $this->assertResponseStatusCodeSame(401);
    }

    #[TestDox('Le login et l’email restent uniques à la modification : 422')]
    public function testUnicite(): void
    {
        $autre = UserFactory::createOne();
        $utilisateur = UserFactory::createOne();

        $this->patch($utilisateur, '/users/'.$utilisateur->getId(), ['login' => $autre->getLogin()]);
        $this->assertResponseStatusCodeSame(422);

        $this->patch($utilisateur, '/users/'.$utilisateur->getId(), ['email' => $autre->getEmail()]);
        $this->assertResponseStatusCodeSame(422);
    }

    #[TestDox('Un utilisateur ne peut changer ni son rôle, ni l’état de son compte, ni la vérification de son email')]
    public function testChampsProteges(): void
    {
        $utilisateur = UserFactory::createOne(['verified' => false]);

        $this->patch($utilisateur, '/users/'.$utilisateur->getId(), ['roles' => ['ROLE_ADMIN'], 'publish' => false, 'verified' => true]);

        $enBase = static::getContainer()->get(UserRepository::class)->find($utilisateur->getId());
        $this->assertSame('ROLE_USER', $enBase->getRole());
        $this->assertTrue($enBase->isPublish());
        $this->assertFalse($enBase->isVerified());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rolesSansDroitSurLesComptes(): iterable
    {
        yield 'utilisateur' => ['ROLE_USER'];
        yield 'modérateur' => ['ROLE_MODO'];
    }

    #[DataProvider('rolesSansDroitSurLesComptes')]
    #[TestDox('Un $_dataName ne peut pas modifier le compte d’un autre : 403')]
    public function testModificationDuCompteDUnAutre(string $role): void
    {
        $autre = UserFactory::createOne();

        $this->patch(UserFactory::createOne(['roles' => [$role]]), '/users/'.$autre->getId(), ['login' => 'pirate']);

        $this->assertResponseStatusCodeSame(403);
    }

    #[TestDox('Un visiteur non connecté ne peut modifier aucun compte : 401')]
    public function testVisiteurNonConnecte(): void
    {
        $this->patch(null, '/users/'.UserFactory::createOne()->getId(), ['login' => 'pirate']);

        $this->assertResponseStatusCodeSame(401);
    }
}
