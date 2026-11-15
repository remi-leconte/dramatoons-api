<?php

namespace App\Tests\Api\Webtoon;

use App\Tests\Api\ApiTestCase;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\WebtoonFactory;
use App\Tests\Factory\WebtoonTitleFactory;
use App\Tests\Factory\WebtoonUserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Titres d’un webtoon (/webtoon_titles) : mêmes droits que le webtoon')]
final class WebtoonTitreTest extends ApiTestCase
{
    #[TestDox('Le créateur ajoute un titre à son webtoon dépublié que personne ne suit')]
    public function testCreateurAjouteTitre(): void
    {
        $createur = UserFactory::createOne();
        $webtoon = WebtoonFactory::createOne(['creator' => $createur]);

        $this->post($createur, '/webtoon_titles', ['title' => 'Autre titre', 'webtoon' => '/webtoons/'.$webtoon->getId()]);

        $this->assertResponseStatusCodeSame(201);
    }

    #[TestDox('Le créateur ne peut pas ajouter de titre à son webtoon publié : 403')]
    public function testCreateurWebtoonPublie(): void
    {
        $createur = UserFactory::createOne();
        $webtoon = WebtoonFactory::new()->publie()->create(['creator' => $createur]);

        $this->post($createur, '/webtoon_titles', ['title' => 'Autre titre', 'webtoon' => '/webtoons/'.$webtoon->getId()]);

        $this->assertResponseStatusCodeSame(403);
    }

    #[TestDox('Le créateur ne peut pas ajouter de titre à son webtoon dépublié suivi par un autre utilisateur : 403')]
    public function testCreateurWebtoonSuiviParUnAutre(): void
    {
        $createur = UserFactory::createOne();
        $webtoon = WebtoonFactory::createOne(['creator' => $createur]);
        WebtoonUserFactory::createOne(['webtoon' => $webtoon]);

        $this->post($createur, '/webtoon_titles', ['title' => 'Autre titre', 'webtoon' => '/webtoons/'.$webtoon->getId()]);

        $this->assertResponseStatusCodeSame(403);
    }

    #[TestDox('Un utilisateur ne peut pas ajouter de titre au webtoon d’un autre : 403')]
    public function testAutreUtilisateurAjouteTitre(): void
    {
        $webtoon = WebtoonFactory::new()->publie()->create();

        $this->post(UserFactory::createOne(), '/webtoon_titles', ['title' => 'Autre titre', 'webtoon' => '/webtoons/'.$webtoon->getId()]);

        $this->assertResponseStatusCodeSame(403);
    }

    #[TestDox('Un modérateur ajoute un titre à un webtoon publié qu’il n’a pas créé')]
    public function testModerateurAjouteTitre(): void
    {
        $webtoon = WebtoonFactory::new()->publie()->create();

        $this->post(UserFactory::new()->moderateur()->create(), '/webtoon_titles', ['title' => 'Autre titre', 'webtoon' => '/webtoons/'.$webtoon->getId()]);

        $this->assertResponseStatusCodeSame(201);
    }

    #[TestDox('Un titre vide ou fait uniquement d’espaces est refusé : 422')]
    public function testTitreVide(): void
    {
        $createur = UserFactory::createOne();
        $webtoon = WebtoonFactory::createOne(['creator' => $createur]);

        foreach (['', '   '] as $titre) {
            $this->post($createur, '/webtoon_titles', ['title' => $titre, 'webtoon' => '/webtoons/'.$webtoon->getId()]);
            $this->assertResponseStatusCodeSame(422);
        }
    }

    #[TestDox('Le créateur modifie puis supprime un titre de son webtoon dépublié que personne ne suit')]
    public function testCreateurModifieEtSupprimeTitre(): void
    {
        $createur = UserFactory::createOne();
        $titre = WebtoonTitleFactory::createOne(['webtoon' => WebtoonFactory::new(['creator' => $createur])]);

        $this->patch($createur, '/webtoon_titles/'.$titre->getId(), ['title' => 'Titre corrigé']);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['title' => 'Titre corrigé']);

        $this->client($createur)->request('DELETE', '/webtoon_titles/'.$titre->getId());
        $this->assertResponseStatusCodeSame(204);
    }

    #[TestDox('Le créateur ne peut ni modifier ni supprimer un titre de son webtoon publié : 403')]
    public function testCreateurWebtoonPublieModifieTitre(): void
    {
        $createur = UserFactory::createOne();
        $titre = WebtoonTitleFactory::createOne(['webtoon' => WebtoonFactory::new()->publie()->with(['creator' => $createur])]);

        $this->patch($createur, '/webtoon_titles/'.$titre->getId(), ['title' => 'Titre corrigé']);
        $this->assertResponseStatusCodeSame(403);

        $this->client($createur)->request('DELETE', '/webtoon_titles/'.$titre->getId());
        $this->assertResponseStatusCodeSame(403);
    }

    #[TestDox('Un modérateur modifie puis supprime un titre d’un webtoon publié')]
    public function testModerateurModifieEtSupprimeTitre(): void
    {
        $moderateur = UserFactory::new()->moderateur()->create();
        $titre = WebtoonTitleFactory::createOne(['webtoon' => WebtoonFactory::new()->publie()]);

        $this->patch($moderateur, '/webtoon_titles/'.$titre->getId(), ['title' => 'Titre corrigé']);
        $this->assertResponseIsSuccessful();

        $this->client($moderateur)->request('DELETE', '/webtoon_titles/'.$titre->getId());
        $this->assertResponseStatusCodeSame(204);
    }

    #[TestDox('Un titre ne peut pas être déplacé vers un webtoon que l’utilisateur ne peut pas modifier : 403')]
    public function testDeplacementVersWebtoonNonModifiable(): void
    {
        $createur = UserFactory::createOne();
        $titre = WebtoonTitleFactory::createOne(['webtoon' => WebtoonFactory::new(['creator' => $createur])]);
        $autreWebtoon = WebtoonFactory::new()->publie()->create();

        $this->patch($createur, '/webtoon_titles/'.$titre->getId(), ['webtoon' => '/webtoons/'.$autreWebtoon->getId()]);

        $this->assertResponseStatusCodeSame(403);
    }

    #[TestDox('Un titre d’un webtoon invisible pour l’utilisateur ne lui est pas montré : 403')]
    public function testTitreWebtoonInvisible(): void
    {
        $titre = WebtoonTitleFactory::createOne();

        $this->client(UserFactory::createOne())->request('GET', '/webtoon_titles/'.$titre->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    #[TestDox('La liste de tous les titres est réservée aux modérateurs : 403 pour un utilisateur')]
    public function testListeDesTitres(): void
    {
        $this->client(UserFactory::createOne())->request('GET', '/webtoon_titles');
        $this->assertResponseStatusCodeSame(403);

        $this->client(UserFactory::new()->moderateur()->create())->request('GET', '/webtoon_titles');
        $this->assertResponseIsSuccessful();
    }

    #[TestDox('Un visiteur non connecté n’a accès à aucun titre : 401')]
    public function testVisiteurNonConnecte(): void
    {
        $titre = WebtoonTitleFactory::createOne(['webtoon' => WebtoonFactory::new()->publie()]);

        $this->client()->request('GET', '/webtoon_titles/'.$titre->getId());
        $this->assertResponseStatusCodeSame(401);

        $this->post(null, '/webtoon_titles', ['title' => 'Autre titre', 'webtoon' => '/webtoons/'.$titre->getWebtoon()->getId()]);
        $this->assertResponseStatusCodeSame(401);

        $this->patch(null, '/webtoon_titles/'.$titre->getId(), ['title' => 'Titre corrigé']);
        $this->assertResponseStatusCodeSame(401);

        $this->client()->request('DELETE', '/webtoon_titles/'.$titre->getId());
        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function utilisateursSansDroit(): iterable
    {
        yield 'créateur d’un webtoon suivi par un autre' => ['createur'];
        yield 'autre utilisateur' => ['autre'];
        yield 'lecteur qui suit le webtoon' => ['lecteur'];
    }

    #[DataProvider('utilisateursSansDroit')]
    #[TestDox('Ni modification ni suppression d’un titre par le $_dataName : 403')]
    public function testModificationSansDroit(string $qui): void
    {
        $createur = UserFactory::createOne();
        $titre = WebtoonTitleFactory::createOne(['webtoon' => WebtoonFactory::new(['creator' => $createur])]);
        $suivi = WebtoonUserFactory::createOne(['webtoon' => $titre->getWebtoon()]);
        $utilisateur = match ($qui) {
            'createur' => $createur,
            'lecteur' => $suivi->getReader(),
            default => UserFactory::createOne(),
        };

        $this->patch($utilisateur, '/webtoon_titles/'.$titre->getId(), ['title' => 'Titre corrigé']);
        $this->assertResponseStatusCodeSame(403);

        $this->client($utilisateur)->request('DELETE', '/webtoon_titles/'.$titre->getId());
        $this->assertResponseStatusCodeSame(403);
    }

    #[TestDox('Un administrateur modifie puis supprime un titre d’un webtoon dépublié suivi')]
    public function testAdministrateurModifieEtSupprimeTitre(): void
    {
        $administrateur = UserFactory::new()->administrateur()->create();
        $titre = WebtoonTitleFactory::createOne();
        WebtoonUserFactory::createOne(['webtoon' => $titre->getWebtoon()]);

        $this->patch($administrateur, '/webtoon_titles/'.$titre->getId(), ['title' => 'Titre corrigé']);
        $this->assertResponseIsSuccessful();

        $this->client($administrateur)->request('DELETE', '/webtoon_titles/'.$titre->getId());
        $this->assertResponseStatusCodeSame(204);
    }
}
