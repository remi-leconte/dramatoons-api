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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[Post(
    uriTemplate: '/users/reset-password',
    processor: ResetPassword::class,
    denormalizationContext: ['groups' => ['user:reset_password']],
    normalizationContext: ['groups' => ['user:read']],
    openapi: new \ApiPlatform\OpenApi\Model\Operation(
        tags: ['User'],
        summary: 'Valide le changement de mot de passe via le token.',
        description: 'Reçoit le token et le nouveau mot de passe, vérifie la validité, et met à jour l\'utilisateur.'
    )
)]
final class ResetPassword implements ProcessorInterface
{
    #[Groups(['user:reset_password'])]
    #[Assert\NotBlank(message: 'Le token est obligatoire.')]
    private ?string $token = null;

    #[Groups(['user:reset_password'])]
    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire.')]
    #[Assert\Length(min: 6, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.')]
    private ?string $password = null;

    public function __construct(
        private ?UserRepository $userRepository = null,
        private ?UserPasswordHasherInterface $passwordHasher = null,
        private ?EntityManagerInterface $entityManager = null
    ) {}

    /**
     * @param ResetPassword $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->userRepository->findOneBy(['resetToken' => $data->getToken()]);

        if (!$user) {
            throw new NotFoundHttpException("Ce jeton de réinitialisation est invalide.");
        }

        $now = new \DateTimeImmutable();
        if ($user->getResetTokenExpiration() < $now) {
            throw new BadRequestHttpException("Ce jeton de réinitialisation a expiré.");
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $data->getPassword());
        $user->setPassword($hashedPassword);
        $user->setResetToken(null);
        $user->setResetTokenExpiration(null);

        $this->entityManager->flush();

        return $data;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;
        return $this;
    }
}