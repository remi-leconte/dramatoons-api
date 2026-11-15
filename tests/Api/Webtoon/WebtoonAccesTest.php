<?php

namespace App\Tests\Api\Webtoon;

use App\Tests\Api\ApiTestCase;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\WebtoonFactory;
use App\Tests\Factory\WebtoonUserFactory;
use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Accès à un webtoon (GET /webtoons/{id})')]
final class WebtoonAccesTest extends ApiTestCase
{
    #[TestDox('Un visiteur non connecté ne voit aucun webtoon, même publié : 401')]
    public function testVisiteurNonConnecte(): void
    {
        $webtoon = WebtoonFactory::new()->publie()->create();

        $this->client()->request('GET', '/webtoons/'.$webtoon->getId());

        $this->assertResponseStatusCodeSame(401);
    }

    #[TestDox('Un utilisateur voit un webtoon publié')]
    public function testUtilisateurVoitWebtoonPublie(): void
    {
        $webtoon = WebtoonFactory::new()->publie()->create();

        $this->client(UserFactory::createOne())->request('GET', '/webtoons/'.$webtoon->getId());

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['id' => $webtoon->getId(), 'slug' => $webtoon->getSlug()]);
    }

    #[TestDox('Un utilisateur ne voit pas un webtoon dépublié qu’il n’a pas créé et ne suit pas : 403')]
    public function testUtilisateurNeVoitPasWebtoonDepublie(): void
    {
        $webtoon = WebtoonFactory::createOne();

        $this->client(UserFactory::createOne())->request('GET', '/webtoons/'.$webtoon->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    #[TestDox('Le créateur voit son webtoon dépublié')]
    public function testCreateurVoitSonWebtoonDepublie(): void
    {
        $createur = UserFactory::createOne();
        $webtoon = WebtoonFactory::createOne(['creator' => $createur]);

        $this->client($createur)->request('GET', '/webtoons/'.$webtoon->getId());

        $this->assertResponseIsSuccessful();
    }

    #[TestDox('Un lecteur qui suit un webtoon dépublié le voit')]
    public function testLecteurVoitWebtoonDepublieSuivi(): void
    {
        $suivi = WebtoonUserFactory::createOne();

        $this->client($suivi->getReader())->request('GET', '/webtoons/'.$suivi->getWebtoon()->getId());

        $this->assertResponseIsSuccessful();
    }

    #[TestDox('Un modérateur voit un webtoon dépublié qu’il n’a pas créé')]
    public function testModerateurVoitWebtoonDepublie(): void
    {
        $webtoon = WebtoonFactory::createOne();

        $this->client(UserFactory::new()->moderateur()->create())->request('GET', '/webtoons/'.$webtoon->getId());

        $this->assertResponseIsSuccessful();
    }

    #[TestDox('Le détail d’un webtoon montre ses statistiques mais aucun suivi d’un autre utilisateur')]
    public function testDetailSansSuiviDesAutres(): void
    {
        $webtoon = WebtoonFactory::new()->publie()->create();
        WebtoonUserFactory::createOne(['webtoon' => $webtoon, 'rate' => 8.0]);

        $reponse = $this->client(UserFactory::createOne())->request('GET', '/webtoons/'.$webtoon->getId());

        $this->assertResponseIsSuccessful();
        $donnees = $reponse->toArray();
        $this->assertSame(8, (int) $donnees['averageRating']);
        $this->assertSame(1, $donnees['readersCount']);
        $this->assertArrayNotHasKey('readers', $donnees);
        $this->assertNull($donnees['userProgress'] ?? null);
    }
}
