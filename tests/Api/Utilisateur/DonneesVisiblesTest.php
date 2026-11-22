<?php

namespace App\Tests\Api\Utilisateur;

use App\Entity\User;
use App\Tests\Api\ApiTestCase;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\WebtoonUserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Données visibles d’un compte (GET /users/{id}) selon qui le lit')]
final class DonneesVisiblesTest extends ApiTestCase
{
    private const PUBLIQUES = ['login', 'role', 'created'];
    private const PRIVEES = ['email', 'verified', 'publish', 'roles', 'updated', 'lastLogin'];

    /**
     * @return list<string>
     */
    private function champsLus(?User $lecteur, User $compte): array
    {
        $reponse = $this->client($lecteur)->request('GET', '/users/'.$compte->getId());
        $this->assertResponseIsSuccessful();

        return array_keys(array_filter($reponse->toArray(), static fn (string $cle): bool => !str_starts_with($cle, '@'), \ARRAY_FILTER_USE_KEY));
    }

    #[TestDox('Un visiteur non connecté ne lit aucun compte : 401')]
    public function testVisiteurNonConnecte(): void
    {
        $this->client()->request('GET', '/users/'.UserFactory::createOne()->getId());

        $this->assertResponseStatusCodeSame(401);
    }

    #[TestDox('GET /me : un visiteur non connecté reçoit 401, comme sur les autres endpoints')]
    public function testMeVisiteurNonConnecte(): void
    {
        $this->client()->request('GET', '/me');

        $this->assertResponseStatusCodeSame(401);
    }

    #[TestDox('GET /me : un utilisateur, un modérateur et un administrateur y lisent leur id, leur email et leur login')]
    public function testMeParRole(): void
    {
        foreach ([UserFactory::createOne(), UserFactory::new()->moderateur()->create(), UserFactory::new()->administrateur()->create()] as $utilisateur) {
            $reponse = $this->client($utilisateur)->request('GET', '/me');

            $this->assertResponseIsSuccessful();
            $donnees = $reponse->toArray();
            $this->assertSame($utilisateur->getId(), $donnees['id']);
            $this->assertSame($utilisateur->getEmail(), $donnees['email']);
            $this->assertSame($utilisateur->getUserIdentifier(), $donnees['username']);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function lecteursSansDroit(): iterable
    {
        yield 'un utilisateur' => ['ROLE_USER'];
        yield 'un modérateur' => ['ROLE_MODO'];
    }

    #[DataProvider('lecteursSansDroit')]
    #[TestDox('Lu par $_dataName, le compte d’un autre ne montre que le login, le rôle le plus haut et la date de création')]
    public function testCompteDUnAutre(string $role): void
    {
        $compte = UserFactory::new()->administrateur()->create(['lastLogin' => new \DateTimeImmutable()]);

        $champs = $this->champsLus(UserFactory::createOne(['roles' => [$role]]), $compte);

        $this->assertEqualsCanonicalizing(self::PUBLIQUES, $champs);
        $this->assertJsonContains(['role' => 'ROLE_ADMIN']);
    }

    #[TestDox('Un utilisateur lit toutes ses propres informations')]
    public function testSonPropreCompte(): void
    {
        $compte = UserFactory::createOne(['lastLogin' => new \DateTimeImmutable()]);

        $champs = $this->champsLus($compte, $compte);

        $this->assertEmpty(array_diff([...self::PUBLIQUES, ...self::PRIVEES], $champs));
    }

    #[TestDox('Un administrateur lit toutes les informations d’un autre compte')]
    public function testAdministrateur(): void
    {
        $compte = UserFactory::createOne(['lastLogin' => new \DateTimeImmutable()]);

        $champs = $this->champsLus(UserFactory::new()->administrateur()->create(), $compte);

        $this->assertEmpty(array_diff([...self::PUBLIQUES, ...self::PRIVEES], $champs));
    }

    #[TestDox('Le login d’un lecteur, vu dans les suivis d’un webtoon, ne s’accompagne d’aucune information privée')]
    public function testLecteurDansLesSuivis(): void
    {
        $suivi = WebtoonUserFactory::createOne();

        $reponse = $this->client(UserFactory::new()->moderateur()->create())->request('GET', '/webtoon_users', [
            'query' => ['webtoon' => '/webtoons/'.$suivi->getWebtoon()->getId()],
        ]);

        $this->assertResponseIsSuccessful();
        $lecteur = $reponse->toArray()['member'][0]['reader'];
        $this->assertSame($suivi->getReader()->getLogin(), $lecteur['login']);
        $this->assertEmpty(array_intersect(self::PRIVEES, array_keys($lecteur)));
    }
}
