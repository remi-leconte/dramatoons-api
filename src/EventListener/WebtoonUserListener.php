<?php

namespace App\EventListener;

use App\Entity\WebtoonUser;
use App\Repository\WebtoonUserRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, method: 'recalculate', entity: WebtoonUser::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'recalculate', entity: WebtoonUser::class)]
#[AsEntityListener(event: Events::postRemove, method: 'recalculate', entity: WebtoonUser::class)]
final class WebtoonUserListener
{
    public function __construct(
        private WebtoonUserRepository $webtoonUserRepository
    ) {}

    public function recalculate(WebtoonUser $webtoonUser): void
    {
        $webtoon = $webtoonUser->getWebtoon();
        if ($webtoon) {
            $this->webtoonUserRepository->updateWebtoonStats($webtoon);
        }
    }
}