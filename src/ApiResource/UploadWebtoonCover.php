<?php 

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\WebtoonRepository;
use App\Security\WebtoonVoter;
use Doctrine\ORM\EntityManagerInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[ApiResource]
#[Post(
    uriTemplate: '/webtoons/{id}/cover',
    security: "is_granted('ROLE_USER')",
    processor: UploadWebtoonCover::class,
    deserialize: false,
    openapi: new \ApiPlatform\OpenApi\Model\Operation(
        tags: ['Webtoon'],
        summary: 'Upload de la couverture d\'un webtoon',
        description: 'Reçoit un fichier image multipart/form-data, le sauvegarde sur le disque et met à jour le nom en BDD.',
        requestBody: new \ApiPlatform\OpenApi\Model\RequestBody(
            content: new \ArrayObject([
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'file' => [
                                'type' => 'string',
                                'format' => 'binary',
                                'description' => 'Fichier image à uploader'
                            ]
                        ]
                    ]
                ]
            ])
        )
    )
)]
final class UploadWebtoonCover implements ProcessorInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private WebtoonRepository $webtoonRepository,
        private EntityManagerInterface $em,
        private TagAwareCacheInterface $cache,
        private Security $security,
        private ValidatorInterface $validator
    ) {}

    /**
     * @param UploadWebtoonCover $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $webtoonId = $uriVariables['id'] ?? null;
        $webtoon = $this->webtoonRepository->find($webtoonId);

        if (!$webtoon) {
            throw new NotFoundHttpException('Webtoon non trouvé.');
        }

        // La couverture suit les droits du webtoon (WebtoonVoter)
        if (!$this->security->isGranted(WebtoonVoter::EDIT, $webtoon)) {
            throw new AccessDeniedHttpException('Impossible de modifier la couverture de ce webtoon.');
        }

        $request = $this->requestStack->getCurrentRequest();
        $uploadedFile = $request?->files->get('file');

        // deserialize: false : le fichier ne passe pas par la validation d'API Platform
        $violations = $this->validator->validate($uploadedFile, [
            new Assert\NotNull(message: 'Le fichier est obligatoire.'),
            new Assert\File(
                maxSize: '1M',
                mimeTypes: ['image/jpeg'],
                maxSizeMessage: 'L\'image ne doit pas dépasser 1 Mo.',
                mimeTypesMessage: 'L\'image doit être au format JPEG.',
                uploadIniSizeErrorMessage: 'L\'image ne doit pas dépasser 1 Mo.',
            ),
        ]);
        if (0 !== \count($violations)) {
            throw new ValidationException($violations);
        }

        $webtoon->setImageFile($uploadedFile);
        $this->em->flush();
        $this->cache->invalidateTags(['webtoons_list']);

        return $webtoon;
    }
}