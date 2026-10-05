<?php

namespace App\Entity;

use App\State\WebtoonProvider;
use App\State\WebtoonProcessor;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use App\Repository\WebtoonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Serializer\Attribute\Groups;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: WebtoonRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['webtoon:read']],
            provider: WebtoonProvider::class),
        new Get(normalizationContext: ['groups' => ['webtoon:read']]),
        new Post(
            denormalizationContext: ['groups' => ['webtoon:write']],
            normalizationContext: ['groups' => ['webtoon:read']],
            processor: WebtoonProcessor::class),
        new Patch(denormalizationContext: ['groups' => ['webtoon:write']],
            normalizationContext: ['groups' => ['webtoon:read']],
            security: "is_granted('ROLE_MODO') or object.getCreator() == user",
            securityMessage: "Seul un modérateur ou l'utilisateur propriétaire de ce webtoon peut le modifier.",
            processor: WebtoonProcessor::class),
        new Delete(
            normalizationContext: ['groups' => ['webtoon:read']],
            security: "is_granted('ROLE_MODO') or (object.getCreator() == user and not object.hasOtherInteractions())",
            securityMessage: "Vous ne pouvez pas supprimer ce Webtoon car d'autres utilisateurs ont déjà interagi avec.")
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'title.title' => 'partial',
    'status' => 'partial',
    'publish' => 'partial'
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'status',
    'publish',
    'updated'
])]
final class Webtoon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['webtoon:read'])]
    private ?int $id = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'main_title_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['webtoon:read', 'webtoon:write'])]
    private ?WebtoonTitle $title = null;

    /**
     * @var Collection<int, WebtoonTitle>
     */
    #[ORM\OneToMany(targetEntity: WebtoonTitle::class, mappedBy: 'webtoon', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['webtoon:read'])]
    private Collection $secondaryTitles;

    #[ORM\ManyToOne(inversedBy: 'createdWebtoons')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['webtoon:read', 'webtoon:write'])]
    private ?User $creator = null; // tous

    #[ORM\Column(length: 255)]
    #[Groups(['webtoon:read', 'webtoon:write'])]
    private ?string $slug = null; // tous

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['webtoon:read', 'webtoon:write'])]
    private ?string $status = null; // tous

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['webtoon:read'])]
    private ?\DateTimeInterface $created = null; // tous lecture

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['webtoon:read'])]
    private ?\DateTimeInterface $updated = null; // tous lecture

    #[ORM\Column]
    #[Groups(['webtoon:read', 'webtoon:write:modo'])]
    private ?bool $publish = false;

    #[ORM\Column(length: 255)]
    #[Groups(['webtoon:read', 'webtoon:write'])]
    private ?string $image = 'defaut.jpg'; // tous

    #[Vich\UploadableField(mapping: 'webtoon_covers', fileNameProperty: 'image')]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $comment = null; // ignore

    #[ORM\Column(name: 'lastVerification', type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['webtoon:read'])]
    private ?\DateTimeInterface $lastVerification = null; // tous lecture

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['webtoon:read'])]
    private ?float $averageRating = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['webtoon:read'])]
    private int $readersCount = 0;
    /**
     * @var Collection<int, WebtoonUser>
     */
    #[ORM\OneToMany(targetEntity: WebtoonUser::class, mappedBy: 'webtoon', cascade: ['remove'], orphanRemoval: true)]
    private Collection $readers;

    #[Groups(['webtoon:read'])]
    private ?WebtoonUser $userProgress = null;

    public function __construct()
    {
        $this->readers = new ArrayCollection();
        $this->secondaryTitles = new ArrayCollection();

        $this->created = new \DateTimeImmutable();
        $this->updated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?WebtoonTitle
    {
        return $this->title;
    }

    public function setTitle(?WebtoonTitle $title): static
    {
        $this->title = $title;
        if ($title !== null && $title->getTitle() !== null) {
            $this->slug = (new AsciiSlugger())->slug($title->getTitle())->lower()->toString();
        }

        return $this;
    }

    /**
     * @return Collection<int, WebtoonTitle>
     */
    public function getSecondaryTitles(): Collection
    {
        if ($this->title === null) {
            return $this->secondaryTitles;
        }

        return $this->secondaryTitles->filter(
            fn(WebtoonTitle $t) => $t->getId() !== $this->title->getId()
        );
    }

    public function addSecondaryTitle(WebtoonTitle $secondaryTitle): static
    {
        if (!$this->secondaryTitles->contains($secondaryTitle)) {
            $this->secondaryTitles->add($secondaryTitle);
            $secondaryTitle->setWebtoon($this);
        }

        return $this;
    }

    public function removeSecondaryTitle(WebtoonTitle $secondaryTitle): static
    {
        if ($this->secondaryTitles->removeElement($secondaryTitle)) {
            if ($secondaryTitle->getWebtoon() === $this) {
                $secondaryTitle->setWebtoon(null);
            }
        }

        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): static
    {
        $this->creator = $creator;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(\DateTimeInterface $created): static
    {
        $this->created = $created;

        return $this;
    }

    public function getUpdated(): ?\DateTimeInterface
    {
        return $this->updated;
    }

    public function setUpdated(\DateTimeInterface $updated): static
    {
        $this->updated = $updated;

        return $this;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updated = new \DateTimeImmutable();
    }

    public function isPublish(): ?bool
    {
        return $this->publish;
    }

    public function setPublish(bool $publish): static
    {
        $this->publish = $publish;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile = null): static
    {
        $this->imageFile = $imageFile;
        if (null !== $imageFile) {
            $this->updated = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getLastVerification(): ?\DateTimeInterface
    {
        return $this->lastVerification;
    }

    public function setLastVerification(?\DateTimeInterface $lastVerification): static
    {
        $this->lastVerification = $lastVerification;

        return $this;
    }

    public function getAverageRating(): ?float
    {
        return $this->averageRating;
    }

    public function setAverageRating(?float $averageRating): static
    {
        $this->averageRating = $averageRating;

        return $this;
    }

    public function getReadersCount(): int
    {
        return $this->readersCount;
    }

    public function setReadersCount(int $readersCount): static
    {
        $this->readersCount = $readersCount;

        return $this;
    }

    /**
     * @return Collection<int, WebtoonUser>
     */
    public function getReaders(): Collection
    {
        return $this->readers;
    }

    public function addReader(WebtoonUser $reader): static
    {
        if (!$this->readers->contains($reader)) {
            $this->readers->add($reader);
            $reader->setWebtoon($this);
        }

        return $this;
    }

    public function removeReader(WebtoonUser $reader): static
    {
        if ($this->readers->removeElement($reader)) {
            // set the owning side to null (unless already changed)
            if ($reader->getWebtoon() === $this) {
                $reader->setWebtoon(null);
            }
        }

        return $this;
    }
    
    public function getUserProgress(): ?WebtoonUser
    {
        return $this->userProgress;
    }

    public function setUserProgress(?WebtoonUser $userProgress): self
    {
        $this->userProgress = $userProgress;
        return $this;
    }

    public function hasOtherInteractions(): bool
    {
        foreach ($this->readers as $reader) {
            if ($reader->getReader() !== $this->creator) {
                return true;
            }
        }

        return false;
    }
}