<?php

namespace App\State;

use App\Entity\Webtoon;
use App\Entity\User;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<Webtoon, void>
 */
final class WebtoonProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security
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
                // On vérifie s'il est bien le créateur du webtoon
                $isCreator = $data->getCreator() === $user;
                
                if ($isCreator) {
                    $data->setPublish(true);
                } else {
                    $data->setPublish(false); // Sécurité
                }
            } 
            else {
                $data->setPublish(false);
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}