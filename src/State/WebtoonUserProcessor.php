<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\WebtoonUser;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class WebtoonUserProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private ValidatorInterface $validator
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof WebtoonUser) {
            $user = $this->security->getUser();
            
            if ($user instanceof User) {
                $data->setReader($user);
            }

            $violations = $this->validator->validate($data);
            if (0 !== \count($violations)) {
                throw new ValidationException($violations);
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}