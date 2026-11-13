<?php

namespace App\Security;

use App\Entity\User;
use App\Entity\Webtoon;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur un webtoon, ses titres (webtoonTitle) et sa couverture.
 *
 * @extends Voter<string, Webtoon|null>
 */
final class WebtoonVoter extends Voter
{
    public const VIEW = 'WEBTOON_VIEW';
    public const EDIT = 'WEBTOON_EDIT';

    public function __construct(
        private AccessDecisionManagerInterface $accessDecisionManager
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Un sujet null (ex. : titre envoyé sans webtoon) est pris en charge pour être refusé
        return in_array($attribute, [self::VIEW, self::EDIT], true) && (null === $subject || $subject instanceof Webtoon);
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

        $isCreator = $subject->getCreator()?->getId() === $user->getId();

        if (self::VIEW === $attribute) {
            return $subject->isPublish() || $isCreator || $subject->isFollowedBy($user);
        }

        return $isCreator && !$subject->isPublish() && !$subject->isFollowedByOtherThanCreator();
    }
}
