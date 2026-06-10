<?php

namespace App\Entity;

use App\State\WebtoonProvider;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\WebtoonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: WebtoonRepository::class)]
#[ApiResource(
    operations: [
        // règles spécifique de la récupération de la collection dans src/Doctrine/WebtoonPublishExtension.php
        new GetCollection(
            normalizationContext: ['groups' => ['webtoon:read']],
            provider: WebtoonProvider::class),
        // new Get(normalizationContext: ['groups' => ['webtoon:read']]), // pas d'utilité pour l'instant
        new Post(denormalizationContext: ['groups' => ['webtoon:write']]),
        new Post(
            denormalizationContext: ['groups' => ['webtoon:write']],
            security: "is_granted('ROLE_MODO')",
            securityMessage: "Seuls les administrateurs et les modérateurs peuvent créer un Webtoon."
        ),
        new Put(
            denormalizationContext: ['groups' => ['webtoon:write', 'webtoon:write:owner']],
            security: "is_granted('ROLE_ADMIN') or (is_granted('ROLE_MODO') and object.getCreator() == user)",
            securityMessage: "Seul un administrateur ou le modérateur propriétaire de ce Webtoon peut le modifier."
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN') or (is_granted('ROLE_MODO') and object.getCreator() == user)",
            securityMessage: "Seul un administrateur ou le modérateur propriétaire de ce Webtoon peut le supprimer."
        )
    ]
)]
class Webtoon
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
    private ?User $creator = null; // personnes ? je ne vois pas l'utilité pour l'instant

    #[ORM\Column(length: 255)]
    #[Groups(['webtoon:read', 'webtoon:write'])]
    private ?string $slug = null; // tous

    #[ORM\Column(length: 255)]
    #[Groups(['webtoon:read', 'webtoon:write'])]
    private ?string $type = null; // tous mais il faudrait le renommé en etat

    #[ORM\Column]
    #[Groups(['webtoon:read', 'webtoon:write'])]
    private ?int $chapter = null; // tous

    #[ORM\Column]
    #[Groups(['webtoon:read'])]
    private ?\DateTime $created = null; // tous lecture

    #[ORM\Column]
    #[Groups(['webtoon:read'])]
    private ?\DateTime $updated = null; // tous lecture

    #[ORM\Column]
    #[Groups(['webtoon:read', 'webtoon:write:owner'])]
    private ?bool $publish = null; // uniquement le propriétaire et l'admin

    #[ORM\Column(length: 255)]
    #[Groups(['webtoon:read', 'webtoon:write'])]
    private ?string $image = null; // tous

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $comment = null; // ignore

    #[ORM\Column(name: 'lastVerification', type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['webtoon:read'])]
    private ?\DateTime $lastVerification = null; //tous lecture

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

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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

    public function getCreated(): ?\DateTime
    {
        return $this->created;
    }

    public function setCreated(\DateTime $created): static
    {
        $this->created = $created;

        return $this;
    }

    public function getUpdated(): ?\DateTime
    {
        return $this->updated;
    }

    public function setUpdated(\DateTime $updated): static
    {
        $this->updated = $updated;

        return $this;
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

    public function setImage(string $image): static
    {
        $this->image = $image;

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

    public function getLastVerification(): ?\DateTime
    {
        return $this->lastVerification;
    }

    public function setLastVerification(?\DateTime $lastVerification): static
    {
        $this->lastVerification = $lastVerification;

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
    
    #[Groups(['webtoon:read'])]
    public function getReadersCount(): int
    {
        $count = 0;
        foreach ($this->readers as $webtoonUser) {
            if (($webtoonUser->getState() !== null && $webtoonUser->getState() !== "break") || $webtoonUser->getRate() != null) {
                $count++;
            }
        }

        return $count;
    }

    #[Groups(['webtoon:read'])]
    public function getAverageRating(): ?float
    {
        $totalRates = 0;
        $count = 0;

        foreach ($this->readers as $webtoonUser) {
            if ($webtoonUser->getRate() !== null) {
                $totalRates += $webtoonUser->getRate();
                $count++;
            }
        }

        if ($count === 0) {
            return null;
        }

        return round($totalRates / $count, 1);
    }
}
