<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Webtoon;
use App\Repository\WebtoonUserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class WebtoonProvider implements ProviderInterface
{
    private ProviderInterface $collectionProvider;
    private WebtoonUserRepository $webtoonUserRepository;
    private Security $security;

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        ProviderInterface $collectionProvider,
        WebtoonUserRepository $webtoonUserRepository,
        Security $security
    ) {
        $this->collectionProvider = $collectionProvider;
        $this->webtoonUserRepository = $webtoonUserRepository;
        $this->security = $security;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // On laisse le provider d'origine faire tout le travail (sécurité, filtres, SQL)
        $webtoons = $this->collectionProvider->provide($operation, $uriVariables, $context);

        // Si l'utilisateur n'est pas connecté, inutile de chercher des jointures
        $user = $this->security->getUser();
        if (!$user) {
            return $webtoons;
        }

        // On extrait les IDs de la page courante de manière sécurisée
        $webtoonIds = [];
        foreach ($webtoons as $webtoon) {
            $webtoonIds[] = $webtoon->getId();
        }

        if (empty($webtoonIds)) {
            return $webtoons;
        }

        // On récupère les lignes de la table Webtoon_user pour cet utilisateur
        $progressions = $this->webtoonUserRepository->findBy([
            'reader' => $user,
            'webtoon' => $webtoonIds
        ]);

        $progressByWebtoon = [];
        foreach ($progressions as $progress) {
            $progressByWebtoon[$progress->getWebtoon()->getId()] = $progress;
        }

        // On mappe les données sur notre propriété virtuelle
        foreach ($webtoons as $webtoon) {
            if (isset($progressByWebtoon[$webtoon->getId()])) {
                $webtoon->setUserProgress($progressByWebtoon[$webtoon->getId()]);
            }
        }

        return $webtoons;
    }
}