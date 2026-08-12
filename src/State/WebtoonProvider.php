<?php

namespace App\State;

use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Entity\Webtoon;
use App\Repository\WebtoonRepository;
use App\Repository\WebtoonUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class WebtoonProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private WebtoonRepository $webtoonRepository,
        private WebtoonUserRepository $webtoonUserRepository,
        private Security $security,
        #[Autowire(service: 'cache.redis_tag_aware')]
        private TagAwareCacheInterface $cache
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        $page = max(1, (int) ($context['filters']['page'] ?? 1));
        
        $userId = $user ? $user->getId() : 'anon';
        $searchStatus = $user ? implode('-', $user->getSearchStatus() ?? []) : '';
        $sortBy = $user ? ($user->getSearchSortBy() ?? 'added') : 'default';
        $sortOrder = $user ? ($user->getSearchSortOrder() ?? 'DESC') : 'DESC';
        $limit = $user ? ($user->getSearchItemsPerPage() ?? 20) : 20;

        $cacheKey = sprintf('webtoons_u%s_p%d_l%d_st%s_sb%s_so%s', $userId, $page, $limit, md5($searchStatus), $sortBy, $sortOrder);

        $cachedData = $this->cache->get($cacheKey, function (ItemInterface $item) use ($user, $page, $limit) {
            $item->expiresAfter(3600);
            $item->tag(['webtoons_list', 'user_' . ($user ? $user->getId() : 'anon')]);

            $qb = $this->em->createQueryBuilder()
                ->select('w')
                ->from(Webtoon::class, 'w');

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
        });

        // On réhydrate les entités Doctrine et on reconstruit le Paginator
        $webtoons = [];
        if (!empty($cachedData['ids'])) {
            $fetched = $this->webtoonRepository->findBy(['id' => $cachedData['ids']]);
            
            // On préserve l'ordre initial du tri
            $indexed = [];
            foreach ($fetched as $w) {
                $indexed[$w->getId()] = $w;
            }
            foreach ($cachedData['ids'] as $id) {
                if (isset($indexed[$id])) {
                    $webtoons[] = $indexed[$id];
                }
            }

            if ($user) {
                $progressions = $this->webtoonUserRepository->findBy([
                    'reader' => $user,
                    'webtoon' => $cachedData['ids']
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

        return new \ApiPlatform\State\Pagination\ArrayPaginator(
            $webtoons,
            ($page - 1) * $limit,
            $limit
        );
    }
}