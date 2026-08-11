<?php

namespace App\State;

use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Entity\Webtoon;
use App\Repository\WebtoonUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Bundle\SecurityBundle\Security;

final class WebtoonProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private WebtoonUserRepository $webtoonUserRepository,
        private Security $security
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        $qb = $this->em->createQueryBuilder()
            ->select('w')
            ->from(Webtoon::class, 'w');

        $limit = 20;

        if ($user instanceof User) {
            $searchStatus = array_filter($user->getSearchStatus() ?? []);
            if (!empty($searchStatus)) {
                $qb->join('w.readers', 'wu_status', 'WITH', 'wu_status.reader = :user')
                   ->andWhere('wu_status.state IN (:searchStatus)')
                   ->setParameter('searchStatus', $searchStatus)
                   ->setParameter('user', $user);
            }

            $sortOrder = strtoupper($user->getSearchSortOrder() ?? 'DESC');
            if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
                $sortOrder = 'DESC';
            }

            $sortBy = $user->getSearchSortBy() ?? 'added';

            switch ($sortBy) {
                case 'title':
                    $qb->orderBy('w.title', $sortOrder);
                    break;

                case 'rating':
                    $qb->leftJoin('w.readers', 'wu_avg')
                       ->addSelect('AVG(wu_avg.rate) AS HIDDEN avg_rate')
                       ->groupBy('w.id')
                       ->orderBy('avg_rate', $sortOrder);
                    break;

                case 'user_rating':
                    if (!$qb->getParameters()->contains('user')) {
                        $qb->setParameter('user', $user);
                    }
                    $qb->leftJoin('w.readers', 'wu_user', 'WITH', 'wu_user.reader = :user')
                       ->addSelect('wu_user.rate AS HIDDEN user_rate')
                       ->orderBy('user_rate', $sortOrder);
                    break;

                case 'added':
                default:
                    $qb->orderBy('w.updated', $sortOrder);
                    break;
            }

            if ($user->getSearchItemsPerPage() !== null) {
                $limit = $user->getSearchItemsPerPage();
            }
        } else {
            $qb->orderBy('w.created', 'DESC');
        }

        $page = (int) ($context['filters']['page'] ?? 1);
        if ($page < 1) {
            $page = 1;
        }

        $qb->setMaxResults($limit)
           ->setFirstResult(($page - 1) * $limit);

        // 1. On englobe la requête dans un Paginator Doctrine
        $doctrinePaginator = new DoctrinePaginator($qb);

        // 2. On transforme le Paginator Doctrine en Paginator API Platform pour générer 'hydra:view'
        $paginator = new Paginator($doctrinePaginator);

        // 3. Hydratation des informations utilisateur (userProgress)
        if ($user) {
            /** @var Webtoon[] $webtoons */
            $webtoons = iterator_to_array($paginator);
            if (!empty($webtoons)) {
                $webtoonIds = array_map(fn($w) => $w->getId(), $webtoons);

                $progressions = $this->webtoonUserRepository->findBy([
                    'reader' => $user,
                    'webtoon' => $webtoonIds
                ]);

                $progressByWebtoon = [];
                foreach ($progressions as $progress) {
                    $progressByWebtoon[$progress->getWebtoon()->getId()] = $progress;
                }

                foreach ($webtoons as $webtoon) {
                    if (isset($progressByWebtoon[$webtoon->getId()])) {
                        $webtoon->setUserProgress($progressByWebtoon[$webtoon->getId()]);
                    }
                }
            }
        }

        return $paginator;
    }
}