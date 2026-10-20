<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use App\Repository\WebtoonTitleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WebtoonTitleRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_USER')"),
        new Patch(security: "is_granted('ROLE_MODO') or object.getWebtoon().getCreator() == user"),
        new Delete(security: "is_granted('ROLE_MODO') or object.getWebtoon().getCreator() == user"),
    ],
    normalizationContext: ['groups' => ['webtoon_title:read', 'webtoon:read']],
    denormalizationContext: ['groups' => ['webtoon_title:write']],
    forceEager: false
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'title' => 'partial',
    'webtoon' => 'exact'
])]
class WebtoonTitle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['webtoon:read', 'webtoon_title:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire.")]
    #[Assert\Length(max: 255, maxMessage: "Le titre ne peut pas dépasser 255 caractères.")]
    #[Groups(['webtoon:read', 'webtoon:write', 'webtoon_title:read', 'webtoon_title:write'])]
    private ?string $title = null;

    #[ORM\ManyToOne(inversedBy: 'secondaryTitles')]
    #[ORM\JoinColumn(name: 'webtoon_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['webtoon_title:read', 'webtoon_title:write'])]
    private ?Webtoon $webtoon = null;

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

    public function getWebtoon(): ?Webtoon
    {
        return $this->webtoon;
    }

    public function setWebtoon(?Webtoon $webtoon): static
    {
        $this->webtoon = $webtoon;

        return $this;
    }
}