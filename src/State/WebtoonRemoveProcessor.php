<?php

namespace App\State;

use App\Entity\Webtoon;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @implements ProcessorInterface<Webtoon, void>
 */
final class WebtoonRemoveProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private TagAwareCacheInterface $cache
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $result = $this->removeProcessor->process($data, $operation, $uriVariables, $context);

        $this->cache->invalidateTags(['webtoons_list']);

        return $result;
    }
}