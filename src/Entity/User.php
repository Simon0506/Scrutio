<?php

namespace App\Entity;

use App\Entity\PlayerProfile;
use App\Entity\ContentPack;
use App\Entity\PackAccessGrant;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_user', options: ['engine' => 'InnoDB'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'json')]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column(length: 255)]
    private string $password;

    #[ORM\Column(options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: PlayerProfile::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?PlayerProfile $playerProfile = null;

    /** @var Collection<int, PackAccessGrant> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: PackAccessGrant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $packAccessGrants;

    public function __construct(string $email)
    {
        $this->email = strtolower(trim($email));
        $this->packAccessGrants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): void
    {
        $this->isVerified = $isVerified;
    }

    public function eraseCredentials(): void {}

    public function getPlayerProfile(): ?PlayerProfile
    {
        return $this->playerProfile;
    }

    public function setPlayerProfile(PlayerProfile $playerProfile): void
    {
        $this->playerProfile = $playerProfile;
    }

    public function grantPackAccess(ContentPack $pack): void
    {
        if (!$this->hasPackAccess($pack)) {
            new PackAccessGrant($this, $pack);
        }
    }

    public function hasPackAccess(ContentPack $pack): bool
    {
        foreach ($this->packAccessGrants as $grant) {
            $grantedPack = $grant->getPack();
            if ($grantedPack === $pack || ($pack->getId() !== null && $grantedPack->getId() === $pack->getId())) {
                return true;
            }
        }

        return false;
    }

    public function addPackAccessGrant(PackAccessGrant $grant): void
    {
        if (!$this->packAccessGrants->contains($grant)) {
            $this->packAccessGrants->add($grant);
        }
    }
}
