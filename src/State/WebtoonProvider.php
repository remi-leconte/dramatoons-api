<?php

namespace App\State;

use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\WebtoonRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;


final class WebtoonProvider implements ProviderInterface
{
    public function __construct(
        private WebtoonRepository $webtoonRepository,
        private Security $security,
        private TagAwareCacheInterface $cache
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        $page = max(1, (int) ($context['filters']['page'] ?? 1));
        
        $userId = $user ? $user->getId() : 'anon';
        $searchStatus = $user ? ($user->getSearchStatus() ?? null) : null;
        $sortBy = $user ? ($user->getSearchSortBy() ?? 'added') : 'default';
        $sortOrder = $user ? ($user->getSearchSortOrder() ?? 'DESC') : 'DESC';
        $limit = $user ? ($user->getSearchItemsPerPage() ?? 20) : 20;

        $cacheKey = sprintf('webtoons_u%s_p%d_l%d_st%s_sb%s_so%s', $userId, $page, $limit, md5($searchStatus), $sortBy, $sortOrder);

        $cachedData = $this->cache->get($cacheKey, function (ItemInterface $item) use ($user, $page, $limit) {
            $item->tag(['webtoons_list', 'user_' . ($user ? $user->getId() : 'anon')]);

            return $this->webtoonRepository->findFilteredIdsForUser($user, $page, $limit);
        });

        $webtoons = [];
        if (!empty($cachedData['ids'])) {
            $webtoons = $this->webtoonRepository->findByIdsWithUserProgress($cachedData['ids'], $user);
        }

        return new TraversablePaginator(
            new \ArrayIterator($webtoons),
            (float) $page,
            (float) $limit,
            (float) $cachedData['totalItems']
        );
    }
}