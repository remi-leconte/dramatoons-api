<?php

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class AuthenticationSuccessListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success')]
    public function onAuthenticationSuccessResponse(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $user->setLastLogin(new \DateTimeImmutable());
        $this->entityManager->flush();

        $data['user'] = [
            'id' => $user->getId(),
            'login' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'searchParameters' => [
                'sortBy' => $user->getSearchSortBy(),
                'sortOrder' => $user->getSearchSortOrder(),
                'status' => $user->getSearchStatus(),
                'itemsPerPage' => $user->getSearchItemsPerPage()
            ]
        ];

        $event->setData($data);
    }
}