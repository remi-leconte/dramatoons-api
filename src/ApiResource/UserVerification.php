<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[Post(
    uriTemplate: '/users/verify',
    processor: UserVerification::class, 
    denormalizationContext: ['groups' => ['user:verify']],
    normalizationContext: ['groups' => ['user:read']],
    openapi: new \ApiPlatform\OpenApi\Model\Operation(
        tags: ['User'],
        summary: 'Valide le compte d\'un utilisateur via son token d\'activation',
        description: 'Reçoit le token du front, cherche l\'utilisateur, valide son email et supprime le token.'
    )
)]
class UserVerification implements ProcessorInterface
{
    #[Groups(['user:verify'])]
    #[Assert\NotBlank(message: 'Le token est obligatoire.')]
    private ?string $token = null;

    public function __construct(
        private ?UserRepository $userRepository = null,
        private ?EntityManagerInterface $entityManager = null
    ) {}

    /**
     * @param UserVerification $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->userRepository->findOneBy(['resetToken' => $data->getToken()]);

        if (!$user) {
            throw new NotFoundHttpException("Ce jeton de vérification est invalide.");
        }

        $now = new \DateTimeImmutable();
        if ($user->getResetTokenExpiration() < $now) {
            throw new BadRequestHttpException("Ce jeton de vérification a expiré.");
        }

        $user->setVerified(true);
        $user->setResetToken(null);
        $user->setResetTokenExpiration(null);

        $this->entityManager->flush();

        return $user;
    }

    // --- Getters et Setters ---

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): self
    {
        $this->token = $token;
        return $this;
    }
}