<?php 

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\WebtoonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[Post(
    uriTemplate: '/webtoons/{id}/cover',
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
    #[Assert\NotNull(message: 'Le fichier est obligatoire.')]
    #[Assert\File(
        maxSize: '1M',
        mimeTypes: ['image/jpeg'],
        mimeTypesMessage: 'Veuillez uploader une image JPG valide.'
    )]
    private ?UploadedFile $file = null;

    public function __construct(
        private RequestStack $requestStack,
        private WebtoonRepository $webtoonRepository,
        private EntityManagerInterface $em
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

        $request = $this->requestStack->getCurrentRequest();
        $uploadedFile = $request?->files->get('file');

        if ($uploadedFile instanceof UploadedFile) {
            $webtoon->setImageFile($uploadedFile);
            $this->em->flush();
        }

        return $webtoon;
    }

    public function getFile(): ?UploadedFile
    {
        return $this->file;
    }

    public function setFile(?UploadedFile $file): self
    {
        $this->file = $file;
        return $this;
    }
}