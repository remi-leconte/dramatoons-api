<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\State\WebtoonUserProcessor;
use App\Repository\WebtoonUserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: WebtoonUserRepository::class)]
#[UniqueEntity(
    fields: ['reader', 'webtoon'],
    message: 'Vous suivez déjà ce webtoon.',
    errorPath: 'webtoon'
)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            security: "is_granted('ROLE_USER')",
            denormalizationContext: ['groups' => ['webtoon_user:write']],
            normalizationContext: ['groups' => ['webtoon:read'], 'skip_null_values' => false],
            processor: WebtoonUserProcessor::class
        ),
        new Put(denormalizationContext: ['groups' => ['webtoon_user:write']]),
        new Patch(
            denormalizationContext: ['groups' => ['webtoon_user:write']],
            processor: WebtoonUserProcessor::class
        ),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['webtoon_user:read', 'webtoon:read'], 'skip_null_values' => false]
)]
final class WebtoonUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['webtoon:read', 'webtoon_user:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'readWebtoons')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    private ?User $reader = null;

    #[ORM\ManyToOne(inversedBy: 'readers')]
    #[ORM\JoinColumn(name: 'webtoon_id', nullable: false)]
    #[Groups(['webtoon_user:read', 'webtoon_user:write'])]
    private ?Webtoon $webtoon = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['webtoon:read', 'webtoon_user:read', 'webtoon_user:write'])]
    private ?float $rate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['webtoon:read', 'webtoon_user:read', 'webtoon_user:write'])]
    private ?string $state = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['webtoon:read', 'webtoon_user:read', 'webtoon_user:write'])]
    private ?int $bookmark = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReader(): ?User
    {
        return $this->reader;
    }

    public function setReader(?User $reader): static
    {
        $this->reader = $reader;

        return $this;
    }

    public function getWebtoon(): ?Webtoon
    {
        return $this->webtoon;
    }

    public function setWebtoon(?Webtoon $webtoon): static
    {
        $this->webtoon = $webtoon;

        return $this;
    }

    public function getRate(): ?float
    {
        return $this->rate;
    }

    public function setRate(?float $rate): static
    {
        $this->rate = $rate;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getBookmark(): ?int
    {
        return $this->bookmark;
    }

    public function setBookmark(?int $bookmark): static
    {
        $this->bookmark = $bookmark;

        return $this;
    }
}
