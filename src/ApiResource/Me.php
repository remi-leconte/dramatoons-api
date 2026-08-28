<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\WebtoonUserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource]
#[Get(
    uriTemplate: '/me',
    provider: Me::class,
    normalizationContext: ['groups' => ['user:read']],
    openapi: new \ApiPlatform\OpenApi\Model\Operation(
        tags: ['User'],
        summary: 'Récupère les informations de l\'utilisateur connecté.',
        description: 'Renvoie le profil de l\'utilisateur authentifié via le token JWT.'
    )
)]
final class Me implements ProviderInterface
{
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[Groups(['user:read'])]
    private ?string $email = null;

    #[Groups(['user:read'])]
    private ?int $total = null;

    #[Groups(['user:read'])]
    private ?string $username = null;

    public function __construct(
        private readonly Security $security,
        private readonly WebtoonUserRepository $webtoonUserRepository
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|null
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        if (!$user) {
            return null;
        }

        $me = new self($this->security, $this->webtoonUserRepository);
        $me->id = $user->getId();
        $me->email = $user->getEmail();
        $me->total = $this->webtoonUserRepository->countBookmark($user);
        $me->username = method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : $user->getEmail();

        return $me;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }
}