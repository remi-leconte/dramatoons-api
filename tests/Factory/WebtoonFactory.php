<?php

namespace App\Tests\Factory;

use App\Entity\Webtoon;
use App\Entity\WebtoonTitle;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Webtoon>
 */
final class WebtoonFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Webtoon::class;
    }

    protected function defaults(): array
    {
        return [
            'creator' => UserFactory::new(),
            'status' => 'ongoing',
            'publish' => false,
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (Webtoon $webtoon): void {
            if (null === $webtoon->getTitle()) {
                $titre = (new WebtoonTitle())->setTitle('Webtoon '.self::faker()->unique()->numberBetween(1, 1_000_000));
                $titre->setWebtoon($webtoon);
                $webtoon->addSecondaryTitle($titre);
                $webtoon->setTitle($titre);
            }
        });
    }

    public function publie(): self
    {
        return $this->with(['publish' => true]);
    }
}
