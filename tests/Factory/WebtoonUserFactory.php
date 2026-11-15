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
}
