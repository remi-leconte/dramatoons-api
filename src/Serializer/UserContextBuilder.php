<?php

namespace App\Serializer;

use ApiPlatform\State\SerializerContextBuilderInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator(decorates: 'api_platform.serializer.context_builder')]
final class UserContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        private SerializerContextBuilderInterface $inner,
        private Security $security
    ) {}

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->inner->createFromRequest($request, $normalization, $extractedAttributes);

        if (isset($context['groups'])) {
            
            $currentUser = $this->security->getUser();

            // Si l'utilisateur connecté est MODO (sur n'importe quelle ressource)
            if ($this->security->isGranted('ROLE_MODO')) {
                $this->addGroupIfMissing($context, 'webtoon:write:modo'); // publish
                if (!$normalization) { // POST, PUT, PATCH
                    $this->addGroupIfMissing($context, 'user:patch:owner'); // email, password, login
                    if ($this->security->isGranted('ROLE_ADMIN')) {
                        $this->addGroupIfMissing($context, 'user:patch:admin'); // roles
                    }
                }
            }

            // Si un utilisateur est connecté (non-modo)
            if ($currentUser instanceof User) {
                $targetUser = $request->attributes->get('data');

                // Si la ressource actuelle est un User et que c'est lui-même
                if ($targetUser instanceof User && $currentUser->getUserIdentifier() === $targetUser->getUserIdentifier()) {
                    $this->addGroupIfMissing($context, 'user:read:owner');
                    if (!$normalization) { // POST, PUT, PATCH
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