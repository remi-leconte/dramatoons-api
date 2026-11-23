<?php

namespace App\Tests\Api\Webtoon;

use App\Entity\User;
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

    #[TestDox('GET /me : total est le nombre de webtoons de la bibliothèque, celui que l’extension affiche')]
    public function testTotalDeMe(): void
    {
        $donnees = $this->jeuDeDonnees();

        $reponse = $this->client($donnees['lecteur'])->request('GET', '/me');

        $this->assertResponseIsSuccessful();
        $this->assertSame(count($donnees['bibliotheque']), $reponse->toArray()['total']);
        $this->assertSame(count($this->listing($donnees['lecteur'], 'ext')), $reponse->toArray()['total']);
    }

    #[TestDox('GET /me : total d’un modérateur ou d’un administrateur ne compte que sa propre bibliothèque')]
    public function testTotalDeMeModerateurEtAdministrateur(): void
    {
        $this->jeuDeDonnees();

        foreach ([UserFactory::new()->moderateur()->create(), UserFactory::new()->administrateur()->create()] as $utilisateur) {
            WebtoonUserFactory::createOne(['reader' => $utilisateur]);

            $reponse = $this->client($utilisateur)->request('GET', '/me');

            $this->assertResponseIsSuccessful();
            $this->assertSame(1, $reponse->toArray()['total']);
        }
    }

    #[TestDox('GET /me : total vaut 0 pour une bibliothèque vide, même si d’autres lecteurs suivent des webtoons')]
    public function testTotalDeMeBibliothequeVide(): void
    {
        $this->jeuDeDonnees();

        $reponse = $this->client(UserFactory::createOne())->request('GET', '/me');

        $this->assertResponseIsSuccessful();
        $this->assertSame(0, $reponse->toArray()['total']);
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

    #[TestDox('Administration : tri par identifiant, titre, statut, publication ou date de modification, croissant et décroissant (sortOrder en minuscules ou en majuscules), pour un modérateur et un administrateur')]
    public function testAdministrationTri(): void
    {
        // Ordres différents d'une colonne à l'autre, et différents de l'ordre de création (tri par défaut)
        foreach ([
            ['Delta', 'ongoing', true, '2026-01-03'],
            ['Alpha', 'completed', false, '2026-01-01'],
            ['Charlie', 'ongoing', false, '2026-01-04'],
            ['Bravo', 'completed', true, '2026-01-02'],
        ] as [$titre, $statut, $publie, $modification]) {
            $webtoon = WebtoonFactory::createOne([
                'status' => $statut,
                'publish' => $publie,
                'updated' => new \DateTimeImmutable($modification),
            ]);
            $webtoon->getTitle()->setTitle($titre);
        }
        static::getContainer()->get('doctrine')->getManager()->flush();

        $colonnes = [
            'id' => static fn (array $w): int => $w['id'],
            'title' => static fn (array $w): string => $w['title']['title'],
            'status' => static fn (array $w): string => $w['status'],
            'publish' => static fn (array $w): bool => $w['publish'],
            'updated' => static fn (array $w): string => $w['updated'],
        ];

        foreach ([UserFactory::new()->moderateur()->create(), UserFactory::new()->administrateur()->create()] as $utilisateur) {
            foreach ($colonnes as $colonne => $valeur) {
                foreach (['asc', 'desc', 'ASC', 'DESC'] as $ordre) {
                    $reponse = $this->client($utilisateur)->request('GET', '/webtoons', [
                        'query' => ['mode' => 'admin', 'pagination' => 'false', 'sortBy' => $colonne, 'sortOrder' => $ordre],
                    ]);
                    $this->assertResponseIsSuccessful();
                    $obtenues = array_map($valeur, $reponse->toArray()['member']);

                    $attendues = $obtenues;
                    'ASC' === strtoupper($ordre) ? sort($attendues) : rsort($attendues);
                    $this->assertCount(4, $obtenues);
                    if (in_array($colonne, ['id', 'title', 'updated'], true)) {
                        // Valeurs toutes différentes : sinon le tri serait vérifié sans rien prouver
                        $this->assertCount(4, array_unique($obtenues), $colonne);
                    }
                    $this->assertSame($attendues, $obtenues, sprintf('%s, sortBy=%s, sortOrder=%s', $utilisateur->getRoles()[0], $colonne, $ordre));
                }
            }
        }
    }

    #[TestDox('Administration : trié sur une colonne à valeurs égales (publication, statut), les pages se suivent sans répéter ni omettre de webtoon, les ex aequo étant rangés par identifiant')]
    public function testAdministrationTriPagine(): void
    {
        $ids = [];
        for ($i = 0; $i < 6; ++$i) {
            $ids[] = WebtoonFactory::createOne(['status' => 'ongoing', 'publish' => false])->getId();
        }
        // Ex aequo rangés par identifiant dans le sens du tri : décroissant ici, l'inverse de l'ordre de création
        rsort($ids);
        $moderateur = UserFactory::new()->moderateur()->create();

        foreach (['publish', 'status'] as $colonne) {
            $obtenus = [];
            foreach ([1, 2, 3] as $page) {
                $reponse = $this->client($moderateur)->request('GET', '/webtoons', [
                    'query' => ['mode' => 'admin', 'itemsPerPage' => '2', 'page' => (string) $page, 'sortBy' => $colonne, 'sortOrder' => 'desc'],
                ]);
                $this->assertResponseIsSuccessful();
                foreach ($reponse->toArray()['member'] as $webtoon) {
                    $obtenus[] = $webtoon['id'];
                }
            }

            $this->assertSame($ids, $obtenus, $colonne);
        }
    }

    /**
     * @return list<int>
     */
    private function bibliothequeParModification(User $lecteur): array
    {
        $reponse = $this->client($lecteur)->request('GET', '/webtoons', [
            'query' => ['mode' => 'bibliotheque', 'pagination' => 'false', 'sortBy' => 'user_updated', 'sortOrder' => 'desc'],
        ]);
        $this->assertResponseIsSuccessful();

        return array_map(static fn (array $webtoon): int => $webtoon['id'], $reponse->toArray()['member']);
    }

    #[TestDox('Bibliothèque, tri « Modification » (user_updated) : le suivi modifié en dernier passe en tête')]
    public function testTriModificationDuSuivi(): void
    {
        $lecteur = UserFactory::createOne();
        $ancien = WebtoonFactory::new()->publie()->create();
        $recent = WebtoonFactory::new()->publie()->create();
        $suiviAncien = WebtoonUserFactory::createOne(['reader' => $lecteur, 'webtoon' => $ancien, 'state' => 'reading', 'updated' => new \DateTimeImmutable('-2 days')]);
        WebtoonUserFactory::createOne(['reader' => $lecteur, 'webtoon' => $recent, 'state' => 'reading', 'updated' => new \DateTimeImmutable('-1 day')]);
        $this->assertSame([$recent->getId(), $ancien->getId()], $this->bibliothequeParModification($lecteur));

        $this->patch($lecteur, '/webtoon_users/'.$suiviAncien->getId(), ['rate' => 8]);
        $this->assertResponseIsSuccessful();

        $this->assertSame([$ancien->getId(), $recent->getId()], $this->bibliothequeParModification($lecteur));
    }

    #[TestDox('Bibliothèque, tri « Modification » : un suivi créé passe en tête ; sans date de suivi (webtoon créé sans suivi, ancien suivi), le webtoon est rangé par sa propre date de modification')]
    public function testTriModificationSansDateDeSuivi(): void
    {
        $lecteur = UserFactory::createOne();
        // Créés dans un ordre qui contredit l'ordre attendu : ni l'id ni la date de création ne suffisent à le donner
        $ancienSuivi = WebtoonFactory::new()->publie()->create(['updated' => new \DateTimeImmutable('-1 day')]);
        WebtoonUserFactory::createOne(['reader' => $lecteur, 'webtoon' => $ancienSuivi, 'state' => 'reading', 'updated' => null]);
        $creeSansSuivi = WebtoonFactory::createOne(['creator' => $lecteur, 'updated' => new \DateTimeImmutable('-3 days')]);
        $suivi = WebtoonFactory::new()->publie()->create(['updated' => new \DateTimeImmutable('-5 days')]);

        $this->post($lecteur, '/webtoon_users', ['webtoon' => '/webtoons/'.$suivi->getId(), 'state' => 'reading']);
        $this->assertResponseStatusCodeSame(201);

        $this->assertSame([$suivi->getId(), $ancienSuivi->getId(), $creeSansSuivi->getId()], $this->bibliothequeParModification($lecteur));
    }

    #[TestDox('Extension, tri user_updated : rangé comme la bibliothèque, par dernière modification du suivi')]
    public function testTriModificationExtension(): void
    {
        $lecteur = UserFactory::createOne();
        $premier = WebtoonFactory::new()->publie()->create();
        $second = WebtoonFactory::new()->publie()->create();
        WebtoonUserFactory::createOne(['reader' => $lecteur, 'webtoon' => $premier, 'state' => 'reading', 'updated' => new \DateTimeImmutable('-1 day')]);
        WebtoonUserFactory::createOne(['reader' => $lecteur, 'webtoon' => $second, 'state' => 'reading', 'updated' => new \DateTimeImmutable('-2 days')]);

        $reponse = $this->client($lecteur)->request('GET', '/webtoons', [
            'query' => ['mode' => 'ext', 'pagination' => 'false', 'sortBy' => 'user_updated', 'sortOrder' => 'desc'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame([$premier->getId(), $second->getId()], array_map(static fn (array $w): int => $w['id'], $reponse->toArray()['member']));
    }

    #[TestDox('Catalogue, tri user_updated : sans suivi joint, rangé comme added, par date de création du webtoon')]
    public function testTriModificationCatalogue(): void
    {
        $lecteur = UserFactory::createOne();
        $ancien = WebtoonFactory::new()->publie()->create(['created' => new \DateTimeImmutable('-2 days')]);
        $recent = WebtoonFactory::new()->publie()->create(['created' => new \DateTimeImmutable('-1 day')]);
        WebtoonUserFactory::createOne(['reader' => $lecteur, 'webtoon' => $ancien, 'state' => 'reading', 'updated' => new \DateTimeImmutable()]);
        WebtoonUserFactory::createOne(['reader' => $lecteur, 'webtoon' => $recent, 'state' => 'reading', 'updated' => new \DateTimeImmutable('-3 days')]);

        $reponse = $this->client($lecteur)->request('GET', '/webtoons', [
            'query' => ['mode' => 'catalogue', 'pagination' => 'false', 'sortBy' => 'user_updated', 'sortOrder' => 'desc'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame([$recent->getId(), $ancien->getId()], array_map(static fn (array $w): int => $w['id'], $reponse->toArray()['member']));
    }

    #[TestDox('Bibliothèque, tri « Modification » : seuls les suivis de l’utilisateur comptent, pas ceux des autres')]
    public function testTriModificationSuivisDesAutres(): void
    {
        $lecteur = UserFactory::createOne();
        $premier = WebtoonFactory::new()->publie()->create();
        $second = WebtoonFactory::new()->publie()->create();
        WebtoonUserFactory::createOne(['reader' => $lecteur, 'webtoon' => $premier, 'state' => 'reading', 'updated' => new \DateTimeImmutable('-1 day')]);
        WebtoonUserFactory::createOne(['reader' => $lecteur, 'webtoon' => $second, 'state' => 'reading', 'updated' => new \DateTimeImmutable('-2 days')]);
        WebtoonUserFactory::createOne(['webtoon' => $second, 'state' => 'pause', 'updated' => new \DateTimeImmutable()]);

        $this->assertSame([$premier->getId(), $second->getId()], $this->bibliothequeParModification($lecteur));
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
