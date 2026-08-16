<?php

namespace App\Repository;

use App\Entity\WebtoonUser;
use App\Entity\Webtoon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebtoonUser>
 */
final class WebtoonUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebtoonUser::class);
    }

    public function updateWebtoonStats(Webtoon $webtoon): void
    {
        $stats = $this->createQueryBuilder('wu')
            ->select(
                'AVG(wu.rate) as avgRating',
                'SUM(CASE WHEN (wu.state IS NOT NULL AND wu.state != :breakState) OR wu.rate IS NOT NULL THEN 1 ELSE 0 END) as readersCount'
            )
            ->where('wu.webtoon = :webtoon')
            ->setParameter('webtoon', $webtoon)
            ->setParameter('breakState', 'break')
            ->getQuery()
            ->getSingleResult();

        $avg = $stats['avgRating'] !== null ? round((float) $stats['avgRating'], 1) : null;
        $count = (int) ($stats['readersCount'] ?? 0);

        $this->getEntityManager()->createQuery('
            UPDATE App\Entity\Webtoon w 
            SET w.averageRating = :avg, w.readersCount = :count 
            WHERE w.id = :id
        ')
        ->setParameter('avg', $avg)
        ->setParameter('count', $count)
        ->setParameter('id', $webtoon->getId())
        ->execute();
    }
}
