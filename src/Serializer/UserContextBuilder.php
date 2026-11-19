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

            if ($this->security->isGranted('ROLE_MODO')) {
                $this->addGroupIfMissing($context, 'webtoon:write:modo'); // publish
            }

            $targetUser = $request->attributes->get('data');

            // user:read:owner contient des champs que personne ne doit écrire (roles, verified, lastLogin…) :
            // il ne sert qu'en lecture, et chaque profil a son propre groupe d'écriture
            if ($this->security->isGranted('ROLE_ADMIN')) {
                if ($normalization) {
                    $this->addGroupIfMissing($context, 'user:read:owner');
                } elseif ($targetUser instanceof User) {
                    // Seulement sur un compte existant : un compte créé, même par un admin, est un utilisateur actif
                    $this->addGroupIfMissing($context, 'user:patch:admin');
                }
            }

            if ($currentUser instanceof User) {
                if ($targetUser instanceof User && $currentUser->getUserIdentifier() === $targetUser->getUserIdentifier()) {
                    $this->addGroupIfMissing($context, $normalization ? 'user:read:owner' : 'user:patch:owner');
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