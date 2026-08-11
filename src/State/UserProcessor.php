<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Service\UserMailer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private UserPasswordHasherInterface $passwordHasher,
        private UserMailer $userMailer
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $isNew = null === $data->getId();
        $previousData = $context['previous_data'] ?? null;
        $request = $context['request'] ?? null;
        $jsonData = [];

        if ($request) {
            $jsonData = json_decode($request->getContent(), true) ?? [];
        }

        // Gestion du mot de passe
        if (isset($jsonData['password']) && !empty($jsonData['password'])) {
            $hashedPassword = $this->passwordHasher->hashPassword(
                $data,
                $jsonData['password']
            );
            $data->setPassword($hashedPassword);
        } elseif (!$isNew && $previousData instanceof User) {
            $data->setPassword($previousData->getPassword());
        }

        // Vérification du changement d'email
        $emailChanged = false;
        if (!$isNew && $previousData instanceof User) {
            if ($previousData->getEmail() !== $data->getEmail()) {
                $emailChanged = true;
            }
        }

        // On prépare le drapeau avant la persistance
        $shouldSendEmail = $isNew || $emailChanged;

        // On persiste en BDD via le processeur d'API Platform
        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        // Si la BDD a accepté l'entité, on délègue l'envoi et la génération des tokens au service
        if ($shouldSendEmail) {
            $this->userMailer->sendVerificationEmail($data);
        }

        return $result;
    }
}