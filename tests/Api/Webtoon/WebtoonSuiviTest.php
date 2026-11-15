<?php

namespace App\Tests\Api\Webtoon;

use App\Tests\Api\ApiTestCase;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\WebtoonFactory;
use App\Tests\Factory\WebtoonUserFactory;
use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Suivis d’un webtoon (/webtoon_users) et statistiques')]
final class WebtoonSuiviTest extends ApiTestCase
{
    #[TestDox('Un visiteur non connecté ne peut pas suivre un webtoon : 401')]
    public function testVisiteurNonConnecte(): void
    {
        $this->post(null, '/webtoon_users', ['webtoon' => '/webtoons/'.WebtoonFactory::new()->publie()->create()->getId(), 'state' => 'reading']);

        $this->assertResponseStatusCodeSame(401);
    }

    #[TestDox('Un utilisateur suit un webtoon publié : le lecteur est toujours lui, même s’il en envoie un autre')]
    public function testSuivreWebtoonPublie(): void
    {
        $lecteur = UserFactory::createOne();
        $webtoon = WebtoonFactory::new()->publie()->create();

        $this->post($lecteur, '/webtoon_users', ['webtoon' => '/webtoons/'.$webtoon->getId(), 'state' => 'reading', 'reader' => '/users/'.UserFactory::createOne()->getId()]);

        $this->assertResponseStatusCodeSame(201);
        $suivi = WebtoonUserFactory::repository()->findOneBy(['webtoon' => $webtoon->getId()]);
        $this->assertSame($lecteur->getId(), $suivi?->getReader()?->getId());
    }

    #[TestDox('Un utilisateur ne peut pas suivre un webtoon dépublié qu’il ne voit pas : 403')]
    public function testSuivreWebtoonInvisible(): void
    {
        $this->post(UserFactory::createOne(), '/webtoon_users', ['webtoon' => '/webtoons/'.WebtoonFactory::createOne()->getId(), 'state' => 'reading']);

        $this->assertResponseStatusCodeSame(403);
    }

    #[TestDox('Le créateur suit son webtoon dépublié')]
    public function testCreateurSuitSonWebtoonDepublie(): void
    {
        $createur = UserFactory::createOne();

        $this->post($createur, '/webtoon_users', ['webtoon' => '/webtoons/'.WebtoonFactory::createOne(['creator' => $createur])->getId(), 'bookmark' => 1]);

        $this->assertResponseStatusCodeSame(201);
    }

    #[TestDox('Un suivi vide (ni note, ni état, ni chapitre) est refusé : 422')]
    public function testSuiviVide(): void
    {
        $this->post(UserFactory::createOne(), '/webtoon_users', ['webtoon' => '/webtoons/'.WebtoonFactory::new()->publie()->create()->getId()]);

        $this->assertResponseStatusCodeSame(422);
    }

    #[TestDox('Un suivi avec seulement le chapitre 0 est vide, donc refusé : 422')]
    public function testSuiviChapitreZero(): void
    {
        $this->post(UserFactory::createOne(), '/webtoon_users', ['webtoon' => '/webtoons/'.WebtoonFactory::new()->publie()->create()->getId(), 'bookmark' => 0]);

        $this->assertResponseStatusCodeSame(422);
    }

    #[TestDox('Un utilisateur ne peut suivre un webtoon qu’une fois : 422')]
    public function testSuiviEnDouble(): void
    {
        $suivi = WebtoonUserFactory::createOne(['webtoon' => WebtoonFactory::new()->publie()]);

        $this->post($suivi->getReader(), '/webtoon_users', ['webtoon' => '/webtoons/'.$suivi->getWebtoon()->getId(), 'state' => 'pause']);

        $this->assertResponseStatusCodeSame(422);
    }

    #[TestDox('Le lecteur modifie son suivi, mais ne peut pas le vider (le front le supprime) : 422')]
    public function testModifierEtViderSonSuivi(): void
    {
        $suivi = WebtoonUserFactory::createOne(['webtoon' => WebtoonFactory::new()->publie()]);

        $this->patch($suivi->getReader(), '/webtoon_users/'.$suivi->getId(), ['state' => 'completed', 'bookmark' => 12]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['state' => 'completed', 'bookmark' => 12]);

        $this->patch($suivi->getReader(), '/webtoon_users/'.$suivi->getId(), ['state' => null, 'bookmark' => 0, 'rate' => null]);
        $this->assertResponseStatusCodeSame(422);
    }

    #[TestDox('Le lecteur supprime son suivi')]
    public function testSupprimerSonSuivi(): void
    {
        $suivi = WebtoonUserFactory::createOne(['webtoon' => WebtoonFactory::new()->publie()]);

        $this->client($suivi->getReader())->request('DELETE', '/webtoon_users/'.$suivi->getId());

        $this->assertResponseStatusCodeSame(204);
    }

    #[TestDox('Un lecteur qui suit un webtoon dépublié dont il n’est pas le créateur peut encore modifier son suivi')]
    public function testModifierSuiviWebtoonDepublie(): void
    {
        $suivi = WebtoonUserFactory::createOne();

        $this->patch($suivi->getReader(), '/webtoon_users/'.$suivi->getId(), ['bookmark' => 5]);

        $this->assertResponseIsSuccessful();
    }

    #[TestDox('Personne d’autre que le lecteur ne modifie ni ne supprime son suivi, pas même un administrateur : 403')]
    public function testSuiviDUnAutre(): void
    {
        $suivi = WebtoonUserFactory::createOne(['webtoon' => WebtoonFactory::new()->publie()]);

        foreach ([UserFactory::createOne(), UserFactory::new()->moderateur()->create(), UserFactory::new()->administrateur()->create()] as $utilisateur) {
            $this->patch($utilisateur, '/webtoon_users/'.$suivi->getId(), ['bookmark' => 5]);
            $this->assertResponseStatusCodeSame(403);

            $this->client($utilisateur)->request('DELETE', '/webtoon_users/'.$suivi->getId());
            $this->assertResponseStatusCodeSame(403);
        }
    }

    #[TestDox('Un suivi n’est visible que de son lecteur et des modérateurs')]
    public function testVisibiliteDUnSuivi(): void
    {
        $suivi = WebtoonUserFactory::createOne(['webtoon' => WebtoonFactory::new()->publie()]);

        $this->client($suivi->getReader())->request('GET', '/webtoon_users/'.$suivi->getId());
        $this->assertResponseIsSuccessful();

        $this->client(UserFactory::createOne())->request('GET', '/webtoon_users/'.$suivi->getId());
        $this->assertResponseStatusCodeSame(403);

        $this->client(UserFactory::new()->moderateur()->create())->request('GET', '/webtoon_users/'.$suivi->getId());
        $this->assertResponseIsSuccessful();
    }

    #[TestDox('La liste de tous les suivis est réservée aux modérateurs')]
    public function testListeDesSuivis(): void
    {
        $this->client(UserFactory::createOne())->request('GET', '/webtoon_users');
        $this->assertResponseStatusCodeSame(403);

        $this->client(UserFactory::new()->moderateur()->create())->request('GET', '/webtoon_users');
        $this->assertResponseIsSuccessful();
    }

    #[TestDox('Statistiques : la note moyenne porte sur toutes les notes, le nombre de lecteurs exclut « Pas intéressé »')]
    public function testStatistiques(): void
    {
        $webtoon = WebtoonFactory::new()->publie()->create();
        $lecteur = UserFactory::createOne();
        $url = '/webtoons/'.$webtoon->getId();

        $this->post($lecteur, '/webtoon_users', ['webtoon' => $url, 'state' => 'reading', 'rate' => 6]);
        $this->post(UserFactory::createOne(), '/webtoon_users', ['webtoon' => $url, 'state' => 'completed', 'rate' => 9]);
        $this->post(UserFactory::createOne(), '/webtoon_users', ['webtoon' => $url, 'state' => 'break', 'rate' => 3]);

        $this->client($lecteur)->request('GET', $url);
        $this->assertJsonContains(['averageRating' => 6, 'readersCount' => 2]);
    }

    #[TestDox('Statistiques : recalculées quand un suivi est modifié ou supprimé')]
    public function testStatistiquesRecalculees(): void
    {
        $webtoon = WebtoonFactory::new()->publie()->create();
        $url = '/webtoons/'.$webtoon->getId();
        $premier = WebtoonUserFactory::createOne(['webtoon' => $webtoon, 'rate' => 4.0]);
        $second = WebtoonUserFactory::createOne(['webtoon' => $webtoon, 'rate' => 8.0]);

        $this->patch($premier->getReader(), '/webtoon_users/'.$premier->getId(), ['rate' => 10]);
        $this->client($premier->getReader())->request('GET', $url);
        $this->assertJsonContains(['averageRating' => 9, 'readersCount' => 2]);

        $this->client($second->getReader())->request('DELETE', '/webtoon_users/'.$second->getId());
        $this->client($premier->getReader())->request('GET', $url);
        $this->assertJsonContains(['averageRating' => 10, 'readersCount' => 1]);
    }

    #[TestDox('Statistiques : recalculées aussi pour un webtoon dépublié')]
    public function testStatistiquesWebtoonDepublie(): void
    {
        $suivi = WebtoonUserFactory::createOne(['rate' => 5.0]);

        $this->client($suivi->getReader())->request('GET', '/webtoons/'.$suivi->getWebtoon()->getId());

        $this->assertJsonContains(['averageRating' => 5, 'readersCount' => 1]);
    }

    #[TestDox('Un visiteur non connecté ne voit aucun suivi : 401')]
    public function testVisiteurNonConnecteNeVoitAucunSuivi(): void
    {
        $suivi = WebtoonUserFactory::createOne(['webtoon' => WebtoonFactory::new()->publie()]);

        $this->client()->request('GET', '/webtoon_users/'.$suivi->getId());
        $this->assertResponseStatusCodeSame(401);

        $this->client()->request('GET', '/webtoon_users');
        $this->assertResponseStatusCodeSame(401);
    }

    #[TestDox('Statistiques : un suivi avec seulement une note compte comme lecteur')]
    public function testSuiviAvecNoteSeuleCompteCommeLecteur(): void
    {
        $suivi = WebtoonUserFactory::createOne(['webtoon' => WebtoonFactory::new()->publie(), 'state' => null, 'rate' => 7.0]);

        $this->client($suivi->getReader())->request('GET', '/webtoons/'.$suivi->getWebtoon()->getId());

        $this->assertJsonContains(['averageRating' => 7, 'readersCount' => 1]);
    }

    #[TestDox('Un modérateur voit les suivis d’un webtoon avec le login des lecteurs, sans leurs autres informations')]
    public function testModerateurVoitLeLoginDesLecteurs(): void
    {
        $suivi = WebtoonUserFactory::createOne(['webtoon' => WebtoonFactory::new()->publie()]);

        $reponse = $this->client(UserFactory::new()->moderateur()->create())->request('GET', '/webtoon_users', ['query' => ['webtoon' => $suivi->getWebtoon()->getId()]]);

        $this->assertResponseIsSuccessful();
        $lecteur = $reponse->toArray()['member'][0]['reader'];
        $this->assertSame($suivi->getReader()->getLogin(), $lecteur['login']);
        $this->assertArrayNotHasKey('email', $lecteur);
    }
}
