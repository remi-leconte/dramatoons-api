<?php

namespace App\Tests\Factory;

use App\Entity\WebtoonUser;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<WebtoonUser>
 */
final class WebtoonUserFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return WebtoonUser::class;
    }

    protected function defaults(): array
    {
        return [
            'reader' => UserFactory::new(),
            'webtoon' => WebtoonFactory::new(),
            'state' => 'reading',
        ];
    }

    protected function initialize(): static
    {
        // Les droits (WebtoonVoter) lisent la collection readers du webtoon déjà chargé
        return $this->afterInstantiate(function (WebtoonUser $suivi): void {
            $suivi->getWebtoon()?->addReader($suivi);
        });
    }
}
