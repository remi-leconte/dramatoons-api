<?php

namespace App\Serializer;

use ApiPlatform\State\SerializerContextBuilderInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\MapDecoratorArgument;

#[AsDecorator(decorates: 'api_platform.serializer.context_builder')]
class UserContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        #[MapDecoratorArgument]
        private SerializerContextBuilderInterface $inner,
        private Security $security
    ) {}

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->inner->createFromRequest($request, $normalization, $extractedAttributes);

        if (isset($context['groups'])) {
            
            $currentUser = $this->security->getUser();

            // 1. Si l'utilisateur connecté est ADMIN (sur n'importe quelle ressource)
            if ($this->security->isGranted('ROLE_ADMIN')) {
                $this->addGroupIfMissing($context, 'user:read:owner');
                    if (!$normalization) {
                        $this->addGroupIfMissing($context, 'user:patch:owner');
                        $this->addGroupIfMissing($context, 'user:patch:admin');
                    }
                return $context;
            }

            // 2. Si un utilisateur est connecté (non-admin)
            if ($currentUser instanceof User) {
                $targetUser = $request->attributes->get('data');
                $isOwner = false;

                // Si la ressource actuelle est un User et que c'est lui-même
                if ($targetUser instanceof User && $currentUser->getUserIdentifier() === $targetUser->getUserIdentifier()) {
                    $isOwner = true;
                }

                if ($isOwner) {
                    $this->addGroupIfMissing($context, 'user:read:owner');
                    if (!$normalization) {
                        $this->addGroupIfMissing($context, 'user:patch:owner');
                    }
                }
            }
        }

        return $context;
    }

    private function addGroupIfMissing(array &$context, string $group): void
    {
        if (!in_array($group, $context['groups'], true)) {
            $context['groups'][] = $group;
        }
    }
}