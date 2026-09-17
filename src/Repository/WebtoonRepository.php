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

    public function findFilteredIdsForUser(User $user, ?string $searchTitle, int $page, ?int $limit): array
    {
        $qb = $this->createQueryBuilder('w')
            ->select('w.id');

        // Ne récupérer que les webtoons publiés OU créés par l'utilisateur connecté
        $qb->andWhere(
            $qb->expr()->orX(
                'w.publish = :publish',
                'w.creator = :current_user'
            )
        )
        ->setParameter('publish', true)
        ->setParameter('current_user', $user);

        if (!empty($searchTitle)) {
            $qb->andWhere('w.title LIKE :searchTitle')
               ->setParameter('searchTitle', '%' . $searchTitle . '%');
        }

        $searchStatus = $user->getSearchStatus() ?? null;
        if (!empty($searchStatus)) {
            $qb->join('w.readers', 'wu_status', 'WITH', 'wu_status.reader = :user')
               ->andWhere('wu_status.state = :searchStatus')
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

        $countQb = clone $qb;
        $totalItems = (int) $countQb->select('COUNT(DISTINCT w.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        if ($limit !== null) {
            $qb->setMaxResults($limit)
               ->setFirstResult(($page - 1) * $limit);
        }

        $ids = $qb->getQuery()->getSingleColumnResult();

        return ['ids' => $ids, 'totalItems' => $totalItems];
    }

    public function findByIdsWithUserProgress(array $ids, User $user): array
    {
        if (empty($ids)) {
            return [];
        }

        $fetched = $this->createQueryBuilder('w')
            ->where('w.id IN (:ids)') // ne respectera pas forcement l'ordre lors de la récupération
            ->leftJoin('w.readers', 'wu', 'WITH', 'wu.reader = :user')
            ->addSelect('wu')
            ->setParameter('ids', $ids)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        // Rétablissement de l'ordre exact demandé par $ids & récupération de la progression
        $indexed = [];
        /** @var Webtoon $w */
        foreach ($fetched as $w) {
            $progress = $w->getReaders()->first() ?: null;
            if ($progress) {
                $w->setUserProgress($progress);
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