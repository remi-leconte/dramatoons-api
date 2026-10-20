<?php

namespace App\EventListener;

use App\Entity\Webtoon;
use App\Entity\WebtoonTitle;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsEntityListener(event: Events::postPersist, entity: Webtoon::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Webtoon::class)]
#[AsEntityListener(event: Events::postRemove, entity: Webtoon::class)]
#[AsEntityListener(event: Events::postPersist, entity: WebtoonTitle::class)]
#[AsEntityListener(event: Events::postUpdate, entity: WebtoonTitle::class)]
#[AsEntityListener(event: Events::postRemove, entity: WebtoonTitle::class)]
final class WebtoonCacheInvalidatorListener
{
    public function __construct(
        private readonly TagAwareCacheInterface $cache
    ) {}

    public function postPersist(): void
    {
        $this->invalidate();
    }

    public function postUpdate(): void
    {
        $this->invalidate();
    }

    public function postRemove(): void
    {
        $this->invalidate();
    }

    private function invalidate(): void
    {
        $this->cache->invalidateTags(['webtoons_list']);
    }
}