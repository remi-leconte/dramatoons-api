<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\UserRepository;
use App\Service\UserMailer;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[Post(
    uriTemplate: '/users/resend-verification',
    processor: UserResendVerification::class, // 💡 Il s'appelle lui-même comme processeur
    denormalizationContext: ['groups' => ['user:resend']],
    normalizationContext: ['groups' => ['user:read']],
    openapi: new \ApiPlatform\OpenApi\Model\Operation(
        tags: ['User'],
        summary: 'Renvoie l\'e-mail de validation d\'adresse e-mail.',
        description: 'Reçoit l\'e-mail du front, cherche l\'utilisateur, génère un nouveau token et renvoie l\'e-mail.'
    )
)]
final class UserResendVerification implements ProcessorInterface
{
    #[Groups(['user:resend'])]
    #[Assert\NotBlank(message: 'L\'adresse email est obligatoire.')]
    #[Assert\Email(message: 'L\'adresse email n\'est pas valide.')]
    private ?string $email = null;

    // 💡 Les services sont mis à null par défaut pour que API Platform puisse l'utiliser en DTO
    public function __construct(
        private ?UserRepository $userRepository = null,
        private ?UserMailer $userMailer = null
    ) {}

    /**
     * @param UserResendVerification $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // 1. Chercher l'utilisateur avec l'email reçu
        $user = $this->userRepository->findOneBy(['email' => $data->getEmail()]);

        // 2. Sécurité : On ne lève pas de NotFoundException pour éviter le User Enumeration
        if (!$user) {
            return $data; 
        }

        // 3. Vérifier si l'utilisateur n'est pas déjà validé
        if ($user->isVerified()) {
            throw new BadRequestHttpException("Cette adresse email est déjà validée.");
        }

        // 4. Déclencher le renvoi via le service
        $this->userMailer->sendVerificationEmail($user);

        return $data;
    }

    // --- Getters et Setters ---

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }
}