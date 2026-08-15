<?php 

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Webtoon;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

final class WebtoonPublishExtension implements QueryCollectionExtensionInterface
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (Webtoon::class !== $resourceClass) {
            return;
        }

        // RÈGLE 1 : Les Admin peuvent voir tous les Webtoons
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $user = $this->security->getUser();

        // RÈGLE 2 : Si l'utilisateur est connecté (et non-admin)
        if ($user) {
            // Il voit les Webtoons publiés ou ses propres Webtoons dépubliés
            $queryBuilder->andWhere(
                $queryBuilder->expr()->orX(
                    sprintf('%s.publish = :publish', $rootAlias),
                    sprintf('%s.creator = :current_user', $rootAlias)
                )
            );
            $queryBuilder->setParameter('publish', true);
            $queryBuilder->setParameter('current_user', $user);
        } else {
            // RÈGLE 3 : Les utilisateurs non connectés ne voient que les Webtoons publiés
            $queryBuilder->andWhere(sprintf('%s.publish = :publish', $rootAlias))
                         ->setParameter('publish', true);
        }
    }
}