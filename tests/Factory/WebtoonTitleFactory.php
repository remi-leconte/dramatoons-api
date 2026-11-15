<?php

namespace App\Tests\Factory;

use App\Entity\WebtoonTitle;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Titre secondaire d'un webtoon.
 *
 * @extends PersistentObjectFactory<WebtoonTitle>
 */
final class WebtoonTitleFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return WebtoonTitle::class;
    }

    protected function defaults(): array
    {
        return [
            'title' => 'Titre secondaire '.self::faker()->unique()->numberBetween(1, 1_000_000),
            'webtoon' => WebtoonFactory::new(),
        ];
    }
}
