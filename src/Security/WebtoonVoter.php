<?php

namespace App\Security;

use App\Entity\User;
use App\Entity\Webtoon;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droit de modifier/supprimer un webtoon, ses titres (webtoonTitle) et sa couverture :
 *  - modérateur/administrateur : toujours ;
 *  - créateur : seulement si le webtoon est dépublié et que personne d'autre ne le suit.
 *
 * @extends Voter<string, Webtoon>
 */
final class WebtoonVoter extends Voter
{
    public const EDIT = 'WEBTOON_EDIT';

    public function __construct(
        private AccessDecisionManagerInterface $accessDecisionManager
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Un sujet null (ex. : titre envoyé sans webtoon) est pris en charge pour être refusé
        return self::EDIT === $attribute && (null === $subject || $subject instanceof Webtoon);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$subject instanceof Webtoon) {
            return false;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_MODO'])) {
            return true;
        }

        return $subject->getCreator()?->getId() === $user->getId()
            && !$subject->isPublish()
            && !$subject->isFollowedByOtherThanCreator();
    }
}
