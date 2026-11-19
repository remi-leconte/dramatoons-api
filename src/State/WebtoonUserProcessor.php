<?php

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\WebtoonUser;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class WebtoonUserProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private Security $security,
        private ValidatorInterface $validator,
        private TagAwareCacheInterface $cache
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        
        if ($operation instanceof Delete) {
            $this->removeProcessor->process($data, $operation, $uriVariables, $context);
            $this->cache->invalidateTags(['user_' . $data->getReader()->getId()]);

            return null;
        }

        if ($operation instanceof Post && $user instanceof User) {
            $data->setReader($user);
        }

        $violations = $this->validator->validate($data);
        if (0 !== \count($violations)) {
            throw new ValidationException($violations);
        }

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        $this->cache->invalidateTags(['user_' . $user->getId()]);

        return $result;
    }
}