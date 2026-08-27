<?php

namespace App\Repository;

use App\Entity\Webtoon;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
        $qb = $this->createQueryBuilder('w')
            ->select('w.id');

        if ($user instanceof User) {
            $searchStatus = array_filter($user->getSearchStatus() ?? []);
            if (!empty($searchStatus)) {
                $qb->join('w.readers', 'wu_status', 'WITH', 'wu_status.reader = :user')
                ->andWhere('wu_status.state IN (:searchStatus)')
                ->setParameter('searchStatus', $searchStatus)
                ->setParameter('user', $user);
            }

            $sortOrder = in_array(strtoupper($user->getSearchSortOrder() ?? ''), ['ASC', 'DESC'], true) ? $user->getSearchSortOrder() : 'DESC';
            $sortBy = $user->getSearchSortBy() ?? 'added';

            match ($sortBy) {
                'title' => $qb->orderBy('w.title', $sortOrder),
                'rating' => $qb->orderBy('w.averageRating', $sortOrder),
                'user_rating' => $qb->leftJoin('w.readers', 'wu_user', 'WITH', 'wu_user.reader = :user')
                                ->addSelect('wu_user.rate AS HIDDEN user_rate')
                                ->setParameter('user', $user)
                                ->orderBy('user_rate', $sortOrder),
                default => $qb->orderBy('w.created', $sortOrder),
            };
        } else {
            $qb->orderBy('w.created', 'DESC');
        }

        $countQb = clone $qb;
        $totalItems = (int) $countQb->select('COUNT(DISTINCT w.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $ids = $qb->setMaxResults($limit)
                ->setFirstResult(($page - 1) * $limit)
                ->getQuery()
                ->getSingleColumnResult();

        return ['ids' => $ids, 'totalItems' => $totalItems];
    }

    public function findByIdsWithUserProgress(array $ids, ?User $user): array
    {
        if (empty($ids)) {
            return [];
        }

        $qb = $this->createQueryBuilder('w')
            ->where('w.id IN (:ids)') // ne respectera pas forcement l'ordre lors de la récupération
            ->setParameter('ids', $ids);

        if ($user instanceof User) {
            $qb->leftJoin('w.readers', 'wu', 'WITH', 'wu.reader = :user')
            ->addSelect('wu')
            ->setParameter('user', $user);
        }

        /** @var Webtoon[] $fetched */
        $fetched = $qb->getQuery()->getResult();

        // Rétablissement de l'ordre exact demandé par $ids & récupération de la progression
        $indexed = [];
        foreach ($fetched as $w) {
            if ($user instanceof User) {
                $progress = $w->getReaders()->first() ?: null;
                if ($progress) {
                    $w->setUserProgress($progress);
                }
            }
            $indexed[$w->getId()] = $w;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($indexed[$id])) {
                $ordered[] = $indexed[$id];
            }
        }

        return $ordered;
    }
}