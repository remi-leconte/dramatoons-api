<?php

namespace App\State;

use App\Entity\Webtoon;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @implements ProcessorInterface<Webtoon, void>
 */
final class WebtoonRemoveProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private EntityManagerInterface $entityManager,
        private TagAwareCacheInterface $cache,
        private Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Webtoon) {
            $isModoOrAdmin = $this->security->isGranted('ROLE_MODO') || $this->security->isGranted('ROLE_ADMIN');

            if (!$isModoOrAdmin && $data->hasOtherInteractions()) {
                throw new AccessDeniedException("Impossible de supprimer un webtoon avec lequel d'autres utilisateurs ont interagi.");
            }

            // Supprime toutes les associations WebtoonUser liées
            foreach ($data->getReaders() as $reader) {
                $this->entityManager->remove($reader);
            }
            $this->entityManager->flush();
        }

        $result = $this->removeProcessor->process($data, $operation, $uriVariables, $context);

        $this->cache->invalidateTags(['webtoons_list']);

        return $result;
    }
}