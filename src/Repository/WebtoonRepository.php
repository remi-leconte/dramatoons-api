<?php

namespace App\Repository;

use App\Entity\Webtoon;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;

/**
 * @extends ServiceEntityRepository<Webtoon>
 */
final class WebtoonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Webtoon::class);
    }

    public function findFilteredIdsForUser(?User $user, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('w');

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
        } else {
            $qb->orderBy('w.updated', 'DESC');
        }

        $qb->setMaxResults($limit)
           ->setFirstResult(($page - 1) * $limit);

        $doctrinePaginator = new DoctrinePaginator($qb);
        $totalItems = count($doctrinePaginator);

        $ids = [];
        foreach ($doctrinePaginator as $webtoon) {
            $ids[] = $webtoon->getId();
        }

        return [
            'ids' => $ids,
            'totalItems' => $totalItems
        ];
    }
}