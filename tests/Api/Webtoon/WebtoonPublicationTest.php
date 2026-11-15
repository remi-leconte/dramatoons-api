<?php

namespace App\Tests\Api\Webtoon;

use App\Tests\Api\ApiTestCase;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\WebtoonFactory;
use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Publication et dépublication d’un webtoon (champ publish)')]
final class WebtoonPublicationTest extends ApiTestCase
{
    #[TestDox('Le créateur d’un webtoon dépublié ne peut pas le publier : le champ publish est ignoré')]
    public function testCreateurNePeutPasPublier(): void
    {
        $createur = UserFactory::createOne();
        $webtoon = WebtoonFactory::createOne(['creator' => $createur]);

        $this->patch($createur, '/webtoons/'.$webtoon->getId(), ['publish' => true]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['publish' => false]);
    }

    #[TestDox('Un modérateur publie un webtoon')]
    public function testModerateurPublie(): void
    {
        $webtoon = WebtoonFactory::createOne();

        $this->patch(UserFactory::new()->moderateur()->create(), '/webtoons/'.$webtoon->getId(), ['publish' => true]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['publish' => true]);
    }

    #[TestDox('Un modérateur dépublie un webtoon')]
    public function testModerateurDepublie(): void
    {
        $webtoon = WebtoonFactory::new()->publie()->create();

        $this->patch(UserFactory::new()->moderateur()->create(), '/webtoons/'.$webtoon->getId(), ['publish' => false]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['publish' => false]);
    }

    #[TestDox('Un administrateur publie un webtoon')]
    public function testAdministrateurPublie(): void
    {
        $webtoon = WebtoonFactory::createOne();

        $this->patch(UserFactory::new()->administrateur()->create(), '/webtoons/'.$webtoon->getId(), ['publish' => true]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['publish' => true]);
    }

    #[TestDox('Le créateur d’un webtoon publié ne peut pas le dépublier : 403')]
    public function testCreateurNePeutPasDepublier(): void
    {
        $createur = UserFactory::createOne();
        $webtoon = WebtoonFactory::new()->publie()->create(['creator' => $createur]);

        $this->patch($createur, '/webtoons/'.$webtoon->getId(), ['publish' => false]);

        $this->assertResponseStatusCodeSame(403);
    }
}
