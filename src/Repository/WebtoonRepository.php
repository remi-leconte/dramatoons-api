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

    public function findFilteredIdsForUser(User $user, array $params): array
    {
        $qb = $this->createQueryBuilder('w')->select('DISTINCT w.id');

        if (!empty($params['id'])) {
            $qb->andWhere('w.id = :id')
               ->setParameter('id', $params['id']);
        }

        if ($params['mode'] === 'bibliotheque') {
            $qb->innerJoin('w.readers', 'wu', 'WITH', 'wu.reader = :user')
                ->setParameter('user', $user);

            if (!empty($params['status']) && is_array($params['status'])) {
                $qb->andWhere('wu.state IN (:includedStatuses)')
                   ->setParameter('includedStatuses', $params['status']);
            }

            if (!empty($params['exclude_status']) && is_array($params['exclude_status'])) {
                $qb->andWhere(
                    $qb->expr()->orX(
                        'wu.state NOT IN (:excludedStatuses)',
                        'wu.state IS NULL'
                    )
                )->setParameter('excludedStatuses', $params['exclude_status']);
            }

            $qb->andWhere(
                $qb->expr()->orX(
                    'wu.state IS NOT NULL',
                    'wu.bookmark IS NOT NULL and wu.bookmark != 0',
                    'wu.rate IS NOT NULL'
                )
            );
        } elseif ($params['mode'] === 'catalogue' || $params['mode'] === 'search') {
            $qb->andWhere('w.publish = :publish')
               ->setParameter('publish', true);

            $hasStatusFilters = (!empty($params['status']) && is_array($params['status'])) 
                             || (!empty($params['exclude_status']) && is_array($params['exclude_status']));

            if ($hasStatusFilters) {
                $qb->innerJoin('w.readers', 'wu', 'WITH', 'wu.reader = :user')
                   ->setParameter('user', $user);

                if (!empty($params['status']) && is_array($params['status'])) {
                    $qb->andWhere('wu.state IN (:includedStatuses)')
                       ->setParameter('includedStatuses', $params['status']);
                }

                if (!empty($params['exclude_status']) && is_array($params['exclude_status'])) {
                    $qb->andWhere('wu.state NOT IN (:excludedStatuses)')
                       ->setParameter('excludedStatuses', $params['exclude_status']);
                }
            }
        } elseif ($params['mode'] === 'admin') {
            if (!$params['publish']) {
                $qb->andWhere('w.publish = :publish')
                   ->setParameter('publish', false);
            }
        }

        if (!empty($params['title'])) {
            $qb->leftJoin('w.title', 'main_title')
               ->leftJoin('w.secondaryTitles', 'sec_titles')
               ->andWhere(
                   $qb->expr()->orX(
                       'main_title.title LIKE :searchTitle',
                       'sec_titles.title LIKE :searchTitle'
                   )
               )
               ->setParameter('searchTitle', '%' . $params['title'] . '%');
        }

        if (!empty($params['statusWebtoon'])) {
            $qb->andWhere('w.status LIKE :status')
                ->setParameter('status', $params['statusWebtoon']);
        }

        match ($params['sortBy']) {
            'id' => $qb->orderBy('w.id', $params['sortOrder']),
            'title' => $qb->leftJoin('w.title', 'sort_title')->orderBy('sort_title.title', $params['sortOrder']),
            'status' => $qb->orderBy('w.status', $params['sortOrder']),
            'publish' => $qb->orderBy('w.publish', $params['sortOrder']),
            'updated' => $qb->orderBy('w.updated', $params['sortOrder']),
            'rating' => $qb->orderBy('w.averageRating', $params['sortOrder']),
            'user_rating' => $qb->leftJoin('w.readers', 'wu_user', 'WITH', 'wu_user.reader = :user')
                                ->addSelect('wu_user.rate AS HIDDEN user_rate')
                                ->setParameter('user', $user)
                                ->orderBy('user_rate', $params['sortOrder']),
            default => $qb->orderBy('w.created', $params['sortOrder']),
        };

        $countQb = clone $qb;
        $totalItems = (int) $countQb->select('COUNT(DISTINCT w.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        if ($params['limit'] !== null) {
            $qb->setMaxResults($params['limit'])
               ->setFirstResult(($params['page'] - 1) * $params['limit']);
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