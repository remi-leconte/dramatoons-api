<?php


namespace App\Entity;

use App\State\UserProcessor;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: "Cette adresse email est déjà utilisée.")]
#[UniqueEntity(fields: ['login'], message: "Ce nom d'utilisateur est déjà utilisé.")]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(normalizationContext: ['groups' => ['user:read']]),
        new Get(normalizationContext: ['groups' => ['user:read']]),
        new Post(denormalizationContext: ['groups' => ['user:write']],
            normalizationContext: ['groups' => ['user:read']],
            processor: UserProcessor::class),
        new Patch(denormalizationContext: ['groups' => []], // Ne semble pas utile, à vérifier en mode prod
            normalizationContext: ['groups' => ['user:read']],
            security: "is_granted('ROLE_ADMIN') or object == user",
            securityMessage: "Seul un administrateur ou l'utilisateur propriétaire de ce compte peut le modifier.",
            processor: UserProcessor::class),
        new Delete(security: "is_granted('ROLE_ADMIN') or object == user",
            securityMessage: "Seul un administrateur ou l'utilisateur propriétaire de ce compte peut le supprimer.")
    ]
)]
final class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read:owner'])] 
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "L'adresse email n'est pas valide.")]
    #[Groups(['user:read:owner', 'user:write', 'user:patch:owner'])] 
    private ?string $email = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['user:read', 'user:patch:admin'])]
    private ?array $roles = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le mot de passe est obligatoire.")]
    #[Groups(['user:write','user:patch:owner'])] // Symfony sécurise déjà le fait de ne pouvoir modifier que le mot de passe de son propre compte
    private ?string $password = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['user:read', 'user:write','user:patch:owner'])]
    #[Assert\NotBlank(message: "Le login est obligatoire.")]
    private ?string $login = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['user:read'])]
    private ?\DateTimeInterface $created = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['user:read'])]
    private ?\DateTimeInterface $updated = null;

    #[ORM\Column(name: 'resetTokenExpiration', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $resetTokenExpiration = null;

    #[ORM\Column(name: 'resetToken', length: 255, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(name: 'searchSortBy', length: 255, nullable: true)]
    #[Groups(['user:read:owner', 'user:write:owner'])]
    private ?string $searchSortBy = null;

    #[ORM\Column(name: 'searchSortOrder', length: 255, nullable: true)]
    #[Groups(['user:read:owner', 'user:write:owner'])]
    private ?string $searchSortOrder = null;

    #[ORM\Column(name: 'searchStatus', length: 255, nullable: true)]
    #[Groups(['user:read:owner', 'user:write:owner'])]
    private ?string $searchStatus = null;

    #[ORM\Column(name: 'searchItemsPerPage', nullable: true)]
    #[Groups(['user:read:owner', 'user:write:owner'])]
    private ?int $searchItemsPerPage = null;

    #[ORM\Column(name: 'rememberToken', length: 64, nullable: true)]
    #[Groups(['user:read:owner', 'user:write:owner'])]
    private ?string $rememberToken = null;

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => true])]
    private ?bool $publish = true;

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => false])]
    #[Groups(['user:read:owner'])]
    private ?bool $verified = null;

    /**
     * @var Collection<int, Webtoon>
     */
    #[ORM\OneToMany(targetEntity: Webtoon::class, mappedBy: 'creator')]
    private Collection $createdWebtoons;

    /**
     * @var Collection<int, WebtoonUser>
     */
    #[ORM\OneToMany(mappedBy: 'reader', targetEntity: WebtoonUser::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $readWebtoons;

    public function __construct()
    {
        $this->createdWebtoons = new ArrayCollection();
        $this->readWebtoons = new ArrayCollection();

        $this->roles = [];
        $this->created = new \DateTimeImmutable();
        $this->updated = new \DateTimeImmutable();
    }
    
    #[ORM\PreUpdate] // modification de la date de modification à chaque update
    public function setUpdatedValue(): void
    {
        $this->updated = new \DateTimeImmutable();
    }

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->login;
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    public function setLogin(string $login): static
    {
        $this->login = $login;

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

    public function getResetTokenExpiration(): ?\DateTimeInterface
    {
        return $this->resetTokenExpiration;
    }

    public function setResetTokenExpiration(?\DateTimeInterface $resetTokenExpiration): static
    {
        $this->resetTokenExpiration = $resetTokenExpiration;

        return $this;
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;

        return $this;
    }

    public function getSearchSortBy(): ?string
    {
        return $this->searchSortBy;
    }

    public function setSearchSortBy(?string $searchSortBy): static
    {
        $this->searchSortBy = $searchSortBy;

        return $this;
    }

    public function getSearchSortOrder(): ?string
    {
        return $this->searchSortOrder;
    }

    public function setSearchSortOrder(?string $searchSortOrder): static
    {
        $this->searchSortOrder = $searchSortOrder;

        return $this;
    }

    public function getSearchStatus(): ?string
    {
        return $this->searchStatus;
    }

    public function setSearchStatus(?string $searchStatus): static
    {
        $this->searchStatus = $searchStatus;

        return $this;
    }

    public function getSearchItemsPerPage(): ?int
    {
        return $this->searchItemsPerPage;
    }

    public function setSearchItemsPerPage(?int $searchItemsPerPage): static
    {
        $this->searchItemsPerPage = $searchItemsPerPage;

        return $this;
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function setRememberToken(?string $rememberToken): static
    {
        $this->rememberToken = $rememberToken;

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

    public function isVerified(): ?bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): static
    {
        $this->verified = $verified;

        return $this;
    }

    /**
     * @return Collection<int, Webtoon>
     */
    public function getCreatedWebtoons(): Collection
    {
        return $this->createdWebtoons;
    }

    public function addCreatedWebtoon(Webtoon $createdWebtoon): static
    {
        if (!$this->createdWebtoons->contains($createdWebtoon)) {
            $this->createdWebtoons->add($createdWebtoon);
            $createdWebtoon->setCreator($this);
        }

        return $this;
    }

    public function removeCreatedWebtoon(Webtoon $createdWebtoon): static
    {
        if ($this->createdWebtoons->removeElement($createdWebtoon)) {
            // set the owning side to null (unless already changed)
            if ($createdWebtoon->getCreator() === $this) {
                $createdWebtoon->setCreator(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, WebtoonUser>
     */
    public function getReadWebtoons(): Collection
    {
        return $this->readWebtoons;
    }

    public function addReadWebtoon(WebtoonUser $readWebtoon): static
    {
        if (!$this->readWebtoons->contains($readWebtoon)) {
            $this->readWebtoons->add($readWebtoon);
            $readWebtoon->setReader($this);
        }

        return $this;
    }

    public function removeReadWebtoon(WebtoonUser $readWebtoon): static
    {
        if ($this->readWebtoons->removeElement($readWebtoon)) {
            // set the owning side to null (unless already changed)
            if ($readWebtoon->getReader() === $this) {
                $readWebtoon->setReader(null);
            }
        }

        return $this;
    }
}
