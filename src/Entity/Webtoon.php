<?php

namespace App\Entity;

use App\State\WebtoonProvider;
use App\State\WebtoonProcessor;
use App\State\WebtoonRemoveProcessor;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
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
        // règles spécifique de la récupération de la collection dans src/Doctrine/WebtoonPublishExtension.php
        new GetCollection(
            normalizationContext: ['groups' => ['webtoon:read']],
            provider: WebtoonProvider::class),
        new Get(normalizationContext: ['groups' => ['webtoon:read']]),
        new Post(
            denormalizationContext: ['groups' => ['webtoon:write']],
            normalizationContext: ['groups' => ['webtoon:read']],
            security: "is_granted('ROLE_MODO')",
            securityMessage: "Seuls les administrateurs et les modérateurs peuvent créer un Webtoon.",
            processor: WebtoonProcessor::class),
        new Patch(denormalizationContext: ['groups' => ['webtoon:write']],
            normalizationContext: ['groups' => ['webtoon:read']],
            security: "is_granted('ROLE_ADMIN') or object == user",
            securityMessage: "Seul un administrateur ou l'utilisateur propriétaire de ce webtoon peut le modifier.",
            processor: WebtoonProcessor::class),
        new Delete(
            normalizationContext: ['groups' => ['webtoon:read']],
            security: "is_granted('ROLE_ADMIN') or (is_granted('ROLE_MODO') and object.getCreator() == user)",
            securityMessage: "Seul un administrateur ou le modérateur propriétaire de ce Webtoon peut le supprimer.",
            processor: WebtoonRemoveProcessor::class)
    ]
)]
final class Webtoon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['webtoon:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['webtoon:read', 'webtoon:write'])]
    private ?string $title = null; // tous

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

    #[ORM\Column]
    #[Groups(['webtoon:read', 'webtoon:write'])]
    private ?int $chapter = null; // tous

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['webtoon:read'])]
    private ?\DateTimeInterface $created = null; // tous lecture

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['webtoon:read'])]
    private ?\DateTimeInterface $updated = null; // tous lecture

    #[ORM\Column]
    #[Groups(['webtoon:read', 'webtoon:write:owner'])]
    private ?bool $publish = false; // uniquement le propriétaire et l'admin

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
    #[ORM\OneToMany(targetEntity: WebtoonUser::class, mappedBy: 'webtoon')]
    private Collection $readers; // lecture : uniquement les infos de l'utilisateur connecté

    #[Groups(['webtoon:read'])]
    private ?WebtoonUser $userProgress = null;

    public function __construct()
    {
        $this->readers = new ArrayCollection();

        $this->created = new \DateTimeImmutable();
        $this->updated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        $this->slug = (new AsciiSlugger())->slug($title)->lower()->toString();

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

    public function getChapter(): ?int
    {
        return $this->chapter;
    }

    public function setChapter(int $chapter): static
    {
        $this->chapter = $chapter;

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
}
