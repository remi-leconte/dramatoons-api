<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Suppression d'un compte. Ses suivis (webtoonUser) sont supprimés en cascade avec lui.
 * Pour chacun des webtoons qu'il a créés :
 *  - dépublié et suivi par personne d'autre que lui : supprimé ;
 *  - publié, ou dépublié mais suivi par quelqu'un d'autre : attribué au compte « Utilisateur supprimé ».
 */
final class UserDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private UserRepository $userRepository,
        private EntityManagerInterface $em
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof User) {
            if ($data->getLogin() === User::DELETED_USER_LOGIN) {
                throw new AccessDeniedHttpException(sprintf('Le compte « %s » ne peut pas être supprimé.', User::DELETED_USER_LOGIN));
            }

            $deletedUser = null;
            // Toutes ces opérations sont exécutées dans le même flush que la suppression du compte
            foreach ($data->getCreatedWebtoons()->toArray() as $webtoon) {
                if (!$webtoon->isPublish() && !$webtoon->isFollowedByOtherThanCreator()) {
                    $this->em->remove($webtoon);
                    continue;
                }

                $deletedUser ??= $this->userRepository->findOneBy(['login' => User::DELETED_USER_LOGIN])
                    ?? throw new \LogicException(sprintf('Compte « %s » introuvable : lancer app:create-deleted-user.', User::DELETED_USER_LOGIN));
                $webtoon->setCreator($deletedUser);
            }
        }

        return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }
}
