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

        $filters = $context['filters'] ?? [];

        $paginationEnabled = filter_var($filters['pagination'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $page = max(1, (int) ($filters['page'] ?? 1));

        $id = $filters['id'] ?? null;
        $title = $filters['title'] ?? '';
        $status = $filters['status'] ?? null;
        $requestedAdmin = filter_var($filters['admin'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $inAdmin = $requestedAdmin && $this->security->isGranted('ROLE_ADMIN');
        
        $publish = isset($filters['publish']) 
            ? filter_var($filters['publish'], FILTER_VALIDATE_BOOLEAN) 
            : true;

        $orderQuery = $filters['order'] ?? [];

        if (!empty($orderQuery) && is_array($orderQuery)) {
            $sortBy = (string) array_key_first($orderQuery);
            $sortOrder = (string) ($orderQuery[$sortBy] ?? 'DESC');
        } else {
            $sortBy = $user->getSearchSortBy() ?? 'added';
            $sortOrder = $user->getSearchSortOrder() ?? 'DESC';
        }

        $userId = $user->getId();
        $searchStatus = $user->getSearchStatus() ?? '';

        $limit = !$paginationEnabled ? null : (int) ($filters['itemsPerPage'] ?? $user->getSearchItemsPerPage() ?? 20);

        $cacheKey = sprintf(
            'webtoons_u%s_pa%d_l%s_id%s_st%s_wst%s_sb%s_so%s_t%s_a%d_pu%d',
            $userId,
            $page,
            $limit ?? 'all',
            $id ?? 'none',
            $searchStatus,
            $status ?? 'none',
            $sortBy,
            $sortOrder,
            md5($title),
            (int) $inAdmin,
            (int) $publish
        );

        $cachedData = $this->cache->get($cacheKey, function (ItemInterface $item) use ($user, $title, $page, $limit, $inAdmin, $publish, $id, $status, $sortBy, $sortOrder) {
            $item->expiresAfter(new \DateInterval('P10D'));
            $item->tag(['webtoons_list', 'user_' . $user->getId()]);

            return $this->webtoonRepository->findFilteredIdsForUser(
                user: $user,
                searchTitle: $title,
                page: $page,
                limit: $limit,
                inAdmin: $inAdmin,
                publish: $publish,
                id: $id,
                status: $status,
                sortBy: $sortBy,
                sortOrder: $sortOrder
            );
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