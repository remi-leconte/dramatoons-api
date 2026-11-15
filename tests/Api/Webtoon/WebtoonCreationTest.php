<?php

namespace App\Tests\Api\Webtoon;

use App\Repository\WebtoonTitleRepository;
use App\Tests\Api\ApiTestCase;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\WebtoonFactory;
use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Création d’un webtoon (POST /webtoons), titre principal et créateur')]
final class WebtoonCreationTest extends ApiTestCase
{
    #[TestDox('Un visiteur non connecté ne peut pas créer de webtoon : 401')]
    public function testVisiteurNonConnecte(): void
    {
        $this->post(null, '/webtoons', ['title' => ['title' => 'Mon webtoon'], 'status' => 'ongoing']);

        $this->assertResponseStatusCodeSame(401);
    }

    #[TestDox('Un utilisateur crée un webtoon : il en est le créateur, et le webtoon est dépublié')]
    public function testUtilisateurCreeWebtoonDepublie(): void
    {
        $utilisateur = UserFactory::createOne();

        $reponse = $this->post($utilisateur, '/webtoons', ['title' => ['title' => 'Mon webtoon'], 'status' => 'ongoing']);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            'publish' => false,
            'creator' => ['id' => $utilisateur->getId()],
            'title' => ['title' => 'Mon webtoon'],
        ]);
        $titres = static::getContainer()->get(WebtoonTitleRepository::class)->findBy(['webtoon' => $reponse->toArray()['id']]);
        $this->assertSame(['Mon webtoon'], array_map(static fn ($titre) => $titre->getTitle(), $titres));
    }

    #[TestDox('Un utilisateur qui envoie publish à true crée quand même un webtoon dépublié')]
    public function testUtilisateurNePeutPasCreerWebtoonPublie(): void
    {
        $this->post(UserFactory::createOne(), '/webtoons', ['title' => ['title' => 'Mon webtoon'], 'status' => 'ongoing', 'publish' => true]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains(['publish' => false]);
    }

    #[TestDox('Un modérateur peut créer un webtoon directement publié, ou dépublié par défaut')]
    public function testModerateurCreeWebtoonPublie(): void
    {
        $moderateur = UserFactory::new()->moderateur()->create();

        $this->post($moderateur, '/webtoons', ['title' => ['title' => 'Mon webtoon publié'], 'status' => 'ongoing', 'publish' => true]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains(['publish' => true]);

        $this->post($moderateur, '/webtoons', ['title' => ['title' => 'Mon webtoon dépublié'], 'status' => 'ongoing']);
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains(['publish' => false]);
    }

    #[TestDox('Personne ne peut créer un webtoon au nom d’un autre utilisateur')]
    public function testCreateurImpose(): void
    {
        $utilisateur = UserFactory::createOne();
        $autre = UserFactory::createOne();

        $this->post($utilisateur, '/webtoons', ['title' => ['title' => 'Mon webtoon'], 'status' => 'ongoing', 'creator' => '/users/'.$autre->getId()]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains(['creator' => ['id' => $utilisateur->getId()]]);
    }

    #[TestDox('Le titre principal ne peut pas être vide ni fait uniquement d’espaces, ni à la création ni à la modification : 422')]
    public function testTitrePrincipalObligatoire(): void
    {
        $createur = UserFactory::createOne();

        foreach (['', '   ', "\u{00A0}", "\u{3000}"] as $vide) {
            $this->post($createur, '/webtoons', ['title' => ['title' => $vide], 'status' => 'ongoing']);
            $this->assertResponseStatusCodeSame(422);
            $this->assertJsonContains(['violations' => [['propertyPath' => 'title.title']]]);
        }

        $webtoon = WebtoonFactory::createOne(['creator' => $createur]);
        $titre = $webtoon->getTitle()->getTitle();
        $this->patch($createur, '/webtoons/'.$webtoon->getId(), ['title' => ['title' => '']]);
        $this->assertResponseStatusCodeSame(422);
        $this->client($createur)->request('GET', '/webtoons/'.$webtoon->getId());
        $this->assertJsonContains(['title' => ['title' => $titre]]);
    }

    #[TestDox('Un webtoon sans titre est refusé, à la création comme à la modification : 422')]
    public function testSansTitre(): void
    {
        $createur = UserFactory::createOne();

        $this->post($createur, '/webtoons', ['status' => 'ongoing']);
        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'title']]]);

        $webtoon = WebtoonFactory::createOne(['creator' => $createur]);
        $this->patch($createur, '/webtoons/'.$webtoon->getId(), ['title' => null]);
        $this->assertResponseStatusCodeSame(422);
    }

    #[TestDox('Le titre principal peut être modifié par PATCH /webtoons')]
    public function testModificationDuTitrePrincipal(): void
    {
        $createur = UserFactory::createOne();
        $webtoon = WebtoonFactory::createOne(['creator' => $createur]);

        $this->patch($createur, '/webtoons/'.$webtoon->getId(), ['title' => ['title' => 'Nouveau Titre']]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['title' => ['title' => 'Nouveau Titre']]);
    }

    #[TestDox('Le créateur d’un webtoon ne peut pas être changé, même par un modérateur')]
    public function testCreateurNonModifiable(): void
    {
        $webtoon = WebtoonFactory::createOne();
        $createur = $webtoon->getCreator();

        $this->patch(UserFactory::new()->moderateur()->create(), '/webtoons/'.$webtoon->getId(), ['creator' => '/users/'.UserFactory::createOne()->getId()]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['creator' => ['id' => $createur->getId()]]);
        $this->assertSame($createur->getId(), WebtoonFactory::repository()->find($webtoon->getId())?->getCreator()?->getId());
    }

    #[TestDox('Le titre principal modifié par /webtoon_titles est celui du webtoon')]
    public function testTitrePrincipalModifieParWebtoonTitles(): void
    {
        $createur = UserFactory::createOne();
        $webtoon = WebtoonFactory::createOne(['creator' => $createur]);

        $this->patch($createur, '/webtoon_titles/'.$webtoon->getTitle()->getId(), ['title' => 'Titre Corrigé']);
        $this->assertResponseIsSuccessful();

        $this->client($createur)->request('GET', '/webtoons/'.$webtoon->getId());
        $this->assertJsonContains(['title' => ['title' => 'Titre Corrigé']]);
    }

    #[TestDox('Le créateur ne peut pas changer le créateur de son webtoon dépublié : valeur ignorée')]
    public function testCreateurNeChangePasLeCreateur(): void
    {
        $createur = UserFactory::createOne();
        $webtoon = WebtoonFactory::createOne(['creator' => $createur]);

        $this->patch($createur, '/webtoons/'.$webtoon->getId(), ['creator' => '/users/'.UserFactory::createOne()->getId()]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['creator' => ['id' => $createur->getId()]]);
    }
}
