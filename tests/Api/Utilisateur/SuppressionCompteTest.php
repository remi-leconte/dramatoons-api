<?php

namespace App\Tests\Api\Utilisateur;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\WebtoonRepository;
use App\Repository\WebtoonUserRepository;
use App\Tests\Api\ApiTestCase;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\WebtoonFactory;
use App\Tests\Factory\WebtoonUserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Suppression d’un compte (DELETE /users/{id}) et compte « Utilisateur supprimé »')]
final class SuppressionCompteTest extends ApiTestCase
{
    private User $utilisateurSupprime;

    protected function setUp(): void
    {
        parent::setUp();
        $this->utilisateurSupprime = UserFactory::new()->compteUtilisateurSupprime()->create();
    }

    private function supprimer(?User $par, User $compte): void
    {
        $this->client($par)->request('DELETE', '/users/'.$compte->getId());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function quiSupprime(): iterable
    {
        yield 'l’utilisateur lui-même' => ['lui'];
        yield 'un administrateur' => ['admin'];
    }

    #[DataProvider('quiSupprime')]
    #[TestDox('Compte supprimé par $_dataName : ses suivis sont supprimés, ses webtoons dépubliés que personne d’autre ne suit aussi, les autres sont attribués à « Utilisateur supprimé »')]
    public function testSuppression(string $qui): void
    {
        $utilisateur = UserFactory::createOne();
        $autre = UserFactory::createOne();

        $suiviDUnWebtoonPublic = WebtoonUserFactory::createOne(['reader' => $utilisateur, 'webtoon' => WebtoonFactory::new()->publie()]);
        $nonSuivi = WebtoonFactory::createOne(['creator' => $utilisateur]);
        $suiviParLuiSeul = WebtoonFactory::createOne(['creator' => $utilisateur]);
        WebtoonUserFactory::createOne(['reader' => $utilisateur, 'webtoon' => $suiviParLuiSeul]);
        $publie = WebtoonFactory::new()->publie()->create(['creator' => $utilisateur]);
        $suiviParUnAutre = WebtoonFactory::createOne(['creator' => $utilisateur]);
        WebtoonUserFactory::createOne(['reader' => $autre, 'webtoon' => $suiviParUnAutre]);
        $ids = [
            'suivi' => $suiviDUnWebtoonPublic->getId(),
            'nonSuivi' => $nonSuivi->getId(),
            'suiviParLuiSeul' => $suiviParLuiSeul->getId(),
            'publie' => $publie->getId(),
            'suiviParUnAutre' => $suiviParUnAutre->getId(),
        ];

        $this->supprimer('lui' === $qui ? $utilisateur : UserFactory::new()->administrateur()->create(), $utilisateur);

        $this->assertResponseStatusCodeSame(204);
        $conteneur = static::getContainer();
        $this->assertNull($conteneur->get(UserRepository::class)->find($utilisateur->getId()));
        $this->assertNull($conteneur->get(WebtoonUserRepository::class)->find($ids['suivi']));
        $webtoons = $conteneur->get(WebtoonRepository::class);
        $this->assertNull($webtoons->find($ids['nonSuivi']));
        $this->assertNull($webtoons->find($ids['suiviParLuiSeul']));
        $this->assertSame($this->utilisateurSupprime->getId(), $webtoons->find($ids['publie'])->getCreator()->getId());
        $this->assertSame($this->utilisateurSupprime->getId(), $webtoons->find($ids['suiviParUnAutre'])->getCreator()->getId());
    }

    #[TestDox('Les webtoons attribués à « Utilisateur supprimé » restent visibles de leurs lecteurs')]
    public function testWebtoonAttribueResteVisible(): void
    {
        $utilisateur = UserFactory::createOne();
        $suivi = WebtoonUserFactory::createOne(['webtoon' => WebtoonFactory::new(['creator' => $utilisateur])]);

        $this->supprimer($utilisateur, $utilisateur);
        $this->assertResponseStatusCodeSame(204);

        $this->client($suivi->getReader())->request('GET', '/webtoons/'.$suivi->getWebtoon()->getId());
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['creator' => ['id' => $this->utilisateurSupprime->getId()]]);
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
    #[TestDox('Un $_dataName ne peut pas supprimer le compte d’un autre : 403')]
    public function testSuppressionDuCompteDUnAutre(string $role): void
    {
        $autre = UserFactory::createOne();

        $this->supprimer(UserFactory::createOne(['roles' => [$role]]), $autre);

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull(static::getContainer()->get(UserRepository::class)->find($autre->getId()));
    }

    #[TestDox('Un visiteur non connecté ne peut supprimer aucun compte : 401')]
    public function testVisiteurNonConnecte(): void
    {
        $this->supprimer(null, UserFactory::createOne());

        $this->assertResponseStatusCodeSame(401);
    }

    #[TestDox('Le compte « Utilisateur supprimé » ne peut pas être supprimé, même par un administrateur : 403')]
    public function testCompteUtilisateurSupprimeIndestructible(): void
    {
        $this->supprimer(UserFactory::new()->administrateur()->create(), $this->utilisateurSupprime);

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull(static::getContainer()->get(UserRepository::class)->find($this->utilisateurSupprime->getId()));
    }
}
