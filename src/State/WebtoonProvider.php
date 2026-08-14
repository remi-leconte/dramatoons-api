<?php

namespace App\State;

use ApiPlatform\State\Pagination\TraversablePaginator;
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

            return $this->webtoonRepository->findFilteredIdsForUser($user, $page, $limit);
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

        return new TraversablePaginator(
            new \ArrayIterator($webtoons),
            (float) $page,
            (float) $limit,
            (float) $cachedData['totalItems']
        );
    }
}