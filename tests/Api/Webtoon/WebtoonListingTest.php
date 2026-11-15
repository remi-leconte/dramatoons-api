<?php

namespace App\Tests\Api\Webtoon;

use App\Tests\Api\ApiTestCase;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\WebtoonFactory;
use App\Tests\Factory\WebtoonTitleFactory;
use App\Tests\Factory\WebtoonUserFactory;
use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Listings de GET /webtoons : bibliothèque, extension, catalogue, recherche, administration')]
final class WebtoonListingTest extends ApiTestCase
{
    /**
     * @param list<int> $attendus
     * @param list<int> $obtenus
     */
    private function assertMemesWebtoons(array $attendus, array $obtenus): void
    {
        sort($attendus);
        sort($obtenus);
        $this->assertSame($attendus, $obtenus);
    }

    /**
     * Webtoons vus différemment selon l'utilisateur et le mode.
     *
     * @return array<string, mixed>
     */
    private function jeuDeDonnees(): array
    {
        $lecteur = UserFactory::createOne();
        $autre = UserFactory::createOne();

        $suiviEtat = WebtoonFactory::new()->publie()->create();
        WebtoonUserFactory::createOne(['reader' => $lecteur, 'webtoon' => $suiviEtat, 'state' => 'reading']);
        $suiviChapitre = WebtoonFactory::new()->publie()->create();
        WebtoonUserFactory::createOne(['reader' => $lecteur, 'webtoon' => $suiviChapitre, 'state' => null, 'bookmark' => 3]);
        $suiviNote = WebtoonFactory::new()->publie()->create();
        WebtoonUserFactory::createOne(['reader' => $lecteur, 'webtoon' => $suiviNote, 'state' => null, 'rate' => 7.0]);
        $pasInteresse = WebtoonFactory::new()->publie()->create();
        WebtoonUserFactory::createOne(['reader' => $lecteur, 'webtoon' => $pasInteresse, 'state' => 'break']);
        $suiviDepublie = WebtoonFactory::createOne(['creator' => $autre]);
        WebtoonUserFactory::createOne(['reader' => $lecteur, 'webtoon' => $suiviDepublie]);

        $creeDepublie = WebtoonFactory::createOne(['creator' => $lecteur]);
        $creePublie = WebtoonFactory::new()->publie()->create(['creator' => $lecteur]);
        $publieSuiviParUnAutre = WebtoonFactory::new()->publie()->create();
        WebtoonUserFactory::createOne(['reader' => $autre, 'webtoon' => $publieSuiviParUnAutre]);
        $depublieDunAutre = WebtoonFactory::createOne(['creator' => $autre]);

        return [
            'lecteur' => $lecteur,
            'bibliotheque' => [$suiviEtat->getId(), $suiviChapitre->getId(), $suiviNote->getId(), $pasInteresse->getId(), $suiviDepublie->getId(), $creeDepublie->getId()],
            'publies' => [$suiviEtat->getId(), $suiviChapitre->getId(), $suiviNote->getId(), $pasInteresse->getId(), $creePublie->getId(), $publieSuiviParUnAutre->getId()],
            'depublies' => [$suiviDepublie->getId(), $creeDepublie->getId(), $depublieDunAutre->getId()],
        ];
    }

    #[TestDox('Un visiteur non connecté ne reçoit aucun webtoon, quel que soit le mode')]
    public function testVisiteurNonConnecte(): void
    {
        WebtoonFactory::new()->publie()->create();

        foreach (['catalogue', 'search', 'bibliotheque', 'ext', 'admin'] as $mode) {
            $this->assertSame([], $this->listing(null, $mode), $mode);
        }
    }

    #[TestDox('Sans paramètre mode, la réponse est vide')]
    public function testSansMode(): void
    {
        WebtoonFactory::new()->publie()->create();

        $reponse = $this->client(UserFactory::createOne())->request('GET', '/webtoons');

        $this->assertResponseIsSuccessful();
        $this->assertSame([], $reponse->toArray()['member']);
    }

    #[TestDox('Bibliothèque : les webtoons suivis (état, chapitre ou note, même « Pas intéressé » ou dépubliés) et les webtoons dépubliés créés, rien d’autre')]
    public function testBibliotheque(): void
    {
        $donnees = $this->jeuDeDonnees();

        $this->assertMemesWebtoons($donnees['bibliotheque'], $this->listing($donnees['lecteur'], 'bibliotheque'));
    }

    #[TestDox('Extension : les mêmes webtoons que la bibliothèque')]
    public function testExtension(): void
    {
        $donnees = $this->jeuDeDonnees();

        $this->assertMemesWebtoons($donnees['bibliotheque'], $this->listing($donnees['lecteur'], 'ext'));
    }

    #[TestDox('Bibliothèque et extension d’un modérateur : seulement ses suivis et ses webtoons dépubliés, comme un utilisateur')]
    public function testBibliothequeModerateur(): void
    {
        $this->jeuDeDonnees();
        $moderateur = UserFactory::new()->moderateur()->create();
        $suivi = WebtoonUserFactory::createOne(['reader' => $moderateur]);

        $this->assertSame([$suivi->getWebtoon()->getId()], $this->listing($moderateur, 'bibliotheque'));
        $this->assertSame([$suivi->getWebtoon()->getId()], $this->listing($moderateur, 'ext'));
    }

    #[TestDox('Catalogue : seulement les webtoons publiés, pour un utilisateur comme pour un modérateur')]
    public function testCatalogue(): void
    {
        $donnees = $this->jeuDeDonnees();

        $this->assertMemesWebtoons($donnees['publies'], $this->listing($donnees['lecteur'], 'catalogue'));
        $this->assertMemesWebtoons($donnees['publies'], $this->listing(UserFactory::new()->moderateur()->create(), 'catalogue'));
    }

    #[TestDox('Recherche : seulement les webtoons publiés, pour un utilisateur comme pour un modérateur')]
    public function testRecherche(): void
    {
        $donnees = $this->jeuDeDonnees();

        $this->assertMemesWebtoons($donnees['publies'], $this->listing($donnees['lecteur'], 'search'));
        $this->assertMemesWebtoons($donnees['publies'], $this->listing(UserFactory::new()->moderateur()->create(), 'search'));
    }

    #[TestDox('Recherche par titre : trouve aussi un webtoon par son titre secondaire, jamais un webtoon dépublié')]
    public function testRechercheParTitre(): void
    {
        $lecteur = UserFactory::createOne();
        $publie = WebtoonFactory::new()->publie()->create();
        WebtoonTitleFactory::createOne(['title' => 'Nom alternatif introuvable', 'webtoon' => $publie]);
        WebtoonTitleFactory::createOne(['title' => 'Nom alternatif introuvable aussi', 'webtoon' => WebtoonFactory::createOne(['creator' => $lecteur])]);

        $this->assertSame([$publie->getId()], $this->listing($lecteur, 'search', ['title' => 'alternatif introuvable']));
    }

    #[TestDox('Administration : un modérateur et un administrateur voient tous les webtoons')]
    public function testAdministration(): void
    {
        $donnees = $this->jeuDeDonnees();
        $tous = [...$donnees['publies'], ...$donnees['depublies']];

        $this->assertMemesWebtoons($tous, $this->listing(UserFactory::new()->moderateur()->create(), 'admin'));
        $this->assertMemesWebtoons($tous, $this->listing(UserFactory::new()->administrateur()->create(), 'admin'));
    }

    #[TestDox('Administration avec publish=false : seulement les webtoons dépubliés')]
    public function testAdministrationDepublies(): void
    {
        $donnees = $this->jeuDeDonnees();

        $this->assertMemesWebtoons($donnees['depublies'], $this->listing(UserFactory::new()->moderateur()->create(), 'admin', ['publish' => 'false']));
    }

    #[TestDox('Administration demandée par un utilisateur : il ne reçoit que sa bibliothèque')]
    public function testAdministrationParUnUtilisateur(): void
    {
        $donnees = $this->jeuDeDonnees();

        $this->assertMemesWebtoons($donnees['bibliotheque'], $this->listing($donnees['lecteur'], 'admin'));
    }

    #[TestDox('Chaque webtoon porte le suivi de l’utilisateur connecté, jamais celui d’un autre')]
    public function testSuiviDeLUtilisateurSeulement(): void
    {
        $lecteur = UserFactory::createOne();
        $webtoon = WebtoonFactory::new()->publie()->create();
        WebtoonUserFactory::createOne(['reader' => $lecteur, 'webtoon' => $webtoon, 'state' => 'reading', 'rate' => 9.0]);
        WebtoonUserFactory::createOne(['webtoon' => $webtoon, 'state' => 'pause', 'rate' => 2.0]);

        $reponse = $this->client($lecteur)->request('GET', '/webtoons', ['query' => ['mode' => 'catalogue', 'pagination' => 'false']]);
        $suivi = $reponse->toArray()['member'][0]['userProgress'];
        $this->assertSame('reading', $suivi['state']);
        $this->assertEquals(9, $suivi['rate']);

        $reponse = $this->client(UserFactory::createOne())->request('GET', '/webtoons', ['query' => ['mode' => 'catalogue', 'pagination' => 'false']]);
        $this->assertNull($reponse->toArray()['member'][0]['userProgress'] ?? null);
    }

    #[TestDox('Cache : un webtoon publié par un modérateur apparaît aussitôt dans le catalogue')]
    public function testCacheInvalideALaPublication(): void
    {
        $lecteur = UserFactory::createOne();
        $webtoon = WebtoonFactory::createOne();
        $this->assertSame([], $this->listing($lecteur, 'catalogue'));

        $this->patch(UserFactory::new()->moderateur()->create(), '/webtoons/'.$webtoon->getId(), ['publish' => true]);

        $this->assertSame([$webtoon->getId()], $this->listing($lecteur, 'catalogue'));
    }

    #[TestDox('Cache : un titre secondaire ajouté par un modérateur est aussitôt trouvé par la recherche')]
    public function testCacheInvalideALAjoutDUnTitre(): void
    {
        $lecteur = UserFactory::createOne();
        $webtoon = WebtoonFactory::new()->publie()->create();
        $this->assertSame([], $this->listing($lecteur, 'search', ['title' => 'Nom alternatif tardif']));

        $this->post(UserFactory::new()->moderateur()->create(), '/webtoon_titles', ['title' => 'Nom alternatif tardif', 'webtoon' => '/webtoons/'.$webtoon->getId()]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame([$webtoon->getId()], $this->listing($lecteur, 'search', ['title' => 'Nom alternatif tardif']));
    }

    #[TestDox('Cache : un webtoon suivi apparaît aussitôt dans la bibliothèque')]
    public function testCacheInvalideAuSuivi(): void
    {
        $lecteur = UserFactory::createOne();
        $webtoon = WebtoonFactory::new()->publie()->create();
        $this->assertSame([], $this->listing($lecteur, 'bibliotheque'));

        $this->post($lecteur, '/webtoon_users', ['webtoon' => '/webtoons/'.$webtoon->getId(), 'state' => 'reading']);

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame([$webtoon->getId()], $this->listing($lecteur, 'bibliotheque'));
    }

    #[TestDox('Aucun listing ne montre les suivis des autres utilisateurs')]
    public function testListingsSansSuivisDesAutres(): void
    {
        $donnees = $this->jeuDeDonnees();

        foreach (['catalogue', 'search', 'bibliotheque', 'ext'] as $mode) {
            $reponse = $this->client($donnees['lecteur'])->request('GET', '/webtoons', ['query' => ['mode' => $mode, 'pagination' => 'false']]);
            foreach ($reponse->toArray()['member'] as $webtoon) {
                $this->assertArrayNotHasKey('readers', $webtoon, $mode);
            }
        }
    }
}
