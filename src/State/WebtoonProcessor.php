<?php

namespace App\State;

use App\Entity\Webtoon;
use App\Entity\User;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @implements ProcessorInterface<Webtoon, void>
 */
final class WebtoonProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private TagAwareCacheInterface $cache
    ) {
    }

    /**
     * @param Webtoon $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Webtoon) {
            $user = $this->security->getUser();

            if ($user instanceof User && $data->getCreator() === null) {
                $data->setCreator($user);
            }

            if ($this->security->isGranted('ROLE_ADMIN')) {
                $data->setPublish(true);
            } 
            elseif ($this->security->isGranted('ROLE_MODO')) {
                $isCreator = $data->getCreator() === $user;
                
                if ($isCreator) {
                    $data->setPublish(true);
                } else {
                    $data->setPublish(false);
                }
            } 
            else {
                $data->setPublish(false);
            }
        }

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        
        $this->cache->invalidateTags(['webtoons_list']);

        return $result;
    }
}