<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\WebtoonUserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: WebtoonUserRepository::class)]
#[ApiResource]
class WebtoonUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['webtoon:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'readWebtoons')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    private ?User $reader = null;

    #[ORM\ManyToOne(inversedBy: 'readers')]
    #[ORM\JoinColumn(name: 'webtoon_id', nullable: false)]
    private ?Webtoon $webtoon = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['webtoon:read'])]
    private ?float $rate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['webtoon:read'])]
    private ?string $state = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['webtoon:read'])]
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
