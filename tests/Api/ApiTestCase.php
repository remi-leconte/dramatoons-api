<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase as BaseApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
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

    /**
     * @param array<string, mixed> $donnees
     */
    protected function post(?User $utilisateur, string $url, array $donnees): ResponseInterface
    {
        return $this->client($utilisateur)->request('POST', $url, [
            'json' => $donnees,
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);
    }

    /**
     * @param array<string, mixed> $donnees
     */
    protected function patch(?User $utilisateur, string $url, array $donnees): ResponseInterface
    {
        return $this->client($utilisateur)->request('PATCH', $url, [
            'json' => $donnees,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
    }

    /**
     * Ids des webtoons renvoyés par le listing, dans l'ordre.
     *
     * @param array<string, string> $parametres
     *
     * @return list<int>
     */
    protected function listing(?User $utilisateur, string $mode, array $parametres = []): array
    {
        $reponse = $this->client($utilisateur)->request('GET', '/webtoons', [
            'query' => ['mode' => $mode, 'pagination' => 'false'] + $parametres,
        ]);
        $this->assertResponseIsSuccessful();

        return array_map(static fn (array $webtoon): int => $webtoon['id'], $reponse->toArray()['member']);
    }
}
