<?php

namespace App\Tests\Api\Webtoon;

use App\Entity\User;
use App\Entity\Webtoon;
use App\Tests\Api\ApiTestCase;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\WebtoonFactory;
use App\Tests\Factory\WebtoonUserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Modification (PATCH) et suppression (DELETE) d’un webtoon')]
final class WebtoonModificationTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function operations(): iterable
    {
        yield 'modification' => ['PATCH'];
        yield 'suppression' => ['DELETE'];
    }

    private function agir(string $operation, ?User $utilisateur, Webtoon $webtoon): void
    {
        if ('PATCH' === $operation) {
            $this->patch($utilisateur, '/webtoons/'.$webtoon->getId(), ['status' => 'completed']);
        } else {
            $this->client($utilisateur)->request('DELETE', '/webtoons/'.$webtoon->getId());
        }
    }

    private function assertAutorise(string $operation): void
    {
        $this->assertResponseStatusCodeSame('PATCH' === $operation ? 200 : 204);
    }

    #[DataProvider('operations')]
    #[TestDox('$operation par un visiteur non connecté : 401')]
    public function testVisiteurNonConnecte(string $operation): void
    {
        $this->agir($operation, null, WebtoonFactory::createOne());

        $this->assertResponseStatusCodeSame(401);
    }

    #[DataProvider('operations')]
    #[TestDox('$operation par le créateur d’un webtoon dépublié que personne ne suit : autorisée')]
    public function testCreateurWebtoonDepublieNonSuivi(string $operation): void
    {
        $createur = UserFactory::createOne();

        $this->agir($operation, $createur, WebtoonFactory::createOne(['creator' => $createur]));

        $this->assertAutorise($operation);
    }

    #[DataProvider('operations')]
    #[TestDox('$operation par le créateur d’un webtoon dépublié qu’il est seul à suivre : autorisée')]
    public function testCreateurWebtoonDepublieSuiviParLuiSeul(string $operation): void
    {
        $createur = UserFactory::createOne();
        $webtoon = WebtoonFactory::createOne(['creator' => $createur]);
        WebtoonUserFactory::createOne(['webtoon' => $webtoon, 'reader' => $createur]);

        $this->agir($operation, $createur, $webtoon);

        $this->assertAutorise($operation);
    }

    #[DataProvider('operations')]
    #[TestDox('$operation par le créateur d’un webtoon publié : 403')]
    public function testCreateurWebtoonPublie(string $operation): void
    {
        $createur = UserFactory::createOne();

        $this->agir($operation, $createur, WebtoonFactory::new()->publie()->create(['creator' => $createur]));

        $this->assertResponseStatusCodeSame(403);
    }

    #[DataProvider('operations')]
    #[TestDox('$operation par le créateur d’un webtoon dépublié suivi par un autre utilisateur : 403')]
    public function testCreateurWebtoonDepublieSuiviParUnAutre(string $operation): void
    {
        $createur = UserFactory::createOne();
        $webtoon = WebtoonFactory::createOne(['creator' => $createur]);
        WebtoonUserFactory::createOne(['webtoon' => $webtoon]);

        $this->agir($operation, $createur, $webtoon);

        $this->assertResponseStatusCodeSame(403);
    }

    #[DataProvider('operations')]
    #[TestDox('$operation par un utilisateur qui n’est pas le créateur d’un webtoon publié : 403')]
    public function testAutreUtilisateur(string $operation): void
    {
        $this->agir($operation, UserFactory::createOne(), WebtoonFactory::new()->publie()->create());

        $this->assertResponseStatusCodeSame(403);
    }

    #[DataProvider('operations')]
    #[TestDox('$operation par un lecteur qui suit un webtoon dépublié dont il n’est pas le créateur : 403')]
    public function testLecteurWebtoonDepublieSuivi(string $operation): void
    {
        $suivi = WebtoonUserFactory::createOne();

        $this->agir($operation, $suivi->getReader(), $suivi->getWebtoon());

        $this->assertResponseStatusCodeSame(403);
    }

    #[DataProvider('operations')]
    #[TestDox('$operation par un modérateur d’un webtoon publié et suivi qu’il n’a pas créé : autorisée')]
    public function testModerateur(string $operation): void
    {
        $webtoon = WebtoonFactory::new()->publie()->create();
        WebtoonUserFactory::createOne(['webtoon' => $webtoon]);

        $this->agir($operation, UserFactory::new()->moderateur()->create(), $webtoon);

        $this->assertAutorise($operation);
    }

    #[DataProvider('operations')]
    #[TestDox('$operation par un administrateur d’un webtoon dépublié suivi par un autre utilisateur : autorisée')]
    public function testAdministrateur(string $operation): void
    {
        $webtoon = WebtoonFactory::createOne();
        WebtoonUserFactory::createOne(['webtoon' => $webtoon]);

        $this->agir($operation, UserFactory::new()->administrateur()->create(), $webtoon);

        $this->assertAutorise($operation);
    }

    #[TestDox('La suppression d’un webtoon supprime ses titres et ses suivis')]
    public function testSuppressionSupprimeTitresEtSuivis(): void
    {
        $webtoon = WebtoonFactory::createOne();
        WebtoonUserFactory::createOne(['webtoon' => $webtoon]);
        $id = $webtoon->getId();

        $this->client(UserFactory::new()->moderateur()->create())->request('DELETE', '/webtoons/'.$id);

        $this->assertResponseStatusCodeSame(204);
        $this->assertSame(0, WebtoonUserFactory::repository()->count(['webtoon' => $id]));
        $this->assertNull(WebtoonFactory::repository()->find($id));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function suivisMinimaux(): iterable
    {
        yield 'pas intéressé' => [['state' => 'break']];
        yield 'chapitre seul' => [['state' => null, 'bookmark' => 2]];
        yield 'note seule' => [['state' => null, 'rate' => 5.0]];
    }

    /**
     * @param array<string, mixed> $suivi
     */
    #[DataProvider('suivisMinimaux')]
    #[TestDox('Le créateur est bloqué dès qu’un autre utilisateur suit son webtoon dépublié, même avec un suivi minimal ($_dataName) : 403')]
    public function testCreateurBloqueParUnSuiviMinimal(array $suivi): void
    {
        $createur = UserFactory::createOne();
        $webtoon = WebtoonFactory::createOne(['creator' => $createur]);
        WebtoonUserFactory::createOne(['webtoon' => $webtoon] + $suivi);

        $this->agir('PATCH', $createur, $webtoon);

        $this->assertResponseStatusCodeSame(403);
    }
}
