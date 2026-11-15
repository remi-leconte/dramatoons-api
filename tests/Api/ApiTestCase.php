<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase as BaseApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Zenstruck\Foundry\Test\Factories;

abstract class ApiTestCase extends BaseApiTestCase
{
    use Factories;

    protected static ?bool $alwaysBootKernel = true;

    protected function client(?User $utilisateur = null): Client
    {
        $options = ['headers' => ['Accept' => 'application/ld+json']];

        if (null !== $utilisateur) {
            $options['auth_bearer'] = static::getContainer()->get(JWTTokenManagerInterface::class)->create($utilisateur);
        }

        return static::createClient([], $options);
    }
}
