<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\UserRepository;
use App\Service\UserMailer;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[Post(
    uriTemplate: '/users/forgot-password',
    processor: ForgotPassword::class,
    denormalizationContext: ['groups' => ['user:forgot']],
    normalizationContext: ['groups' => ['user:read']],
    openapi: new \ApiPlatform\OpenApi\Model\Operation(
        tags: ['User'],
        summary: 'Demande de réinitialisation de mot de passe.',
        description: 'Reçoit l\'e-mail du front, cherche l\'utilisateur, génère un token et envoie l\'e-mail de récupération.'
    )
)]
final class ForgotPassword implements ProcessorInterface
{
    #[Groups(['user:forgot'])]
    #[Assert\NotBlank(message: 'L\'adresse email est obligatoire.')]
    #[Assert\Email(message: 'L\'adresse email n\'est pas valide.')]
    private ?string $email = null;

    public function __construct(
        private ?UserRepository $userRepository = null,
        private ?UserMailer $userMailer = null
    ) {}

    /**
     * @param ForgotPassword $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->userRepository->findOneBy(['email' => $data->getEmail()]);

        if (!$user) {
            return $data;
        }

        $this->userMailer->sendForgotPasswordEmail($user);

        return $data;
    }


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