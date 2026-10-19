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
        if (empty($filters['mode'])) {
            return [];
        }
        
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = (int) ($filters['itemsPerPage'] ?? 10); // admin

        $queryParams = [
            'mode' => ($filters['mode'] === 'admin' && $this->security->isGranted('ROLE_ADMIN')) ? 'admin' : (in_array($filters['mode'], ['catalogue', 'bibliotheque', 'search'], true) ? $filters['mode'] : 'bibliotheque'), // admin / catalogue / bibliotheque / search
            'title' => $filters['title'] ?? '', // admin / bibliothèque / catalogue
            'status' => $filters['status'] ?? [], // bibliothèque / catalogue
            'exclude_status' => $filters['exclude_status'] ?? [], // bibliothèque / catalogue
            'statusWebtoon' => ($filters['statusWebtoon'] ?? '' && in_array($filters['statusWebtoon'], ['completed', 'ongoing'], true) ? $filters['statusWebtoon'] : ''), // admin
            'id' => isset($filters['id']) ? (int) $filters['id'] : null, // admin
            'publish' => isset($filters['publish']) ? filter_var($filters['publish'], FILTER_VALIDATE_BOOLEAN) : true, // admin
            'page' => $page,
            'limit' => $limit, // admin (GET) / bibliothèque / catalogue
            'sortBy' => $filters['sortBy'] ?? 'added',
            'sortOrder' => $filters['sortOrder'] ?? 'DESC'
        ];
        
        $cacheKey = sprintf('webtoons_u%d_%s', $user->getId(), md5(json_encode($queryParams)));

        $cachedData = $this->cache->get($cacheKey, function (ItemInterface $item) use ($user, $queryParams) {
            $item->expiresAfter(new \DateInterval('P10D'));
            $item->tag(['webtoons_list', 'user_' . $user->getId()]);

            return $this->webtoonRepository->findFilteredIdsForUser($user, $queryParams);
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