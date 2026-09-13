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

        if (!$user instanceof User) {
            return [];
        }

        $paginationEnabled = filter_var($context['filters']['pagination'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $page = max(1, (int) ($context['filters']['page'] ?? 1));
        
        $userId = $user->getId();
        $searchStatus = $user->getSearchStatus() ?? '';
        $sortBy = $user->getSearchSortBy() ?? 'added';
        $sortOrder = $user->getSearchSortOrder() ?? 'DESC';

        $limit = !$paginationEnabled ? null : ($user->getSearchItemsPerPage() ?? 20);

        $cacheKey = sprintf('webtoons_u%s_p%d_l%s_st%s_sb%s_so%s', $userId, $page, $limit ?? 'all', $searchStatus, $sortBy, $sortOrder);

        $cachedData = $this->cache->get($cacheKey, function (ItemInterface $item) use ($user, $page, $limit) {
            $item->tag(['webtoons_list', 'user_' . $user->getId()]);

            return $this->webtoonRepository->findFilteredIdsForUser($user, $page, $limit);
        });

        $webtoons = [];
        if (!empty($cachedData['ids'])) {
            $webtoons = $this->webtoonRepository->findByIdsWithUserProgress($cachedData['ids'], $user);
        }

        if (!$paginationEnabled) {
            return $webtoons;
        }

        return new TraversablePaginator(
            new \ArrayIterator($webtoons),
            (float) $page,
            (float) $limit,
            (float) $cachedData['totalItems']
        );
    }
}