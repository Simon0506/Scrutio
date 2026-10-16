<?php

namespace App\Entity;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'player_profile', options: ['engine' => 'InnoDB'])]
class PlayerProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'playerProfile')]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 80)]
    private string $displayName;

    #[ORM\Column]
    private int $reputation = 0;

    #[ORM\Column]
    private int $casesCompleted = 0;

    #[ORM\Column]
    private int $casesPassed = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(User $user, string $displayName)
    {
        $this->user = $user;
        $this->user->setPlayerProfile($this);
        $this->displayName = trim($displayName);
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getReputation(): int
    {
        return $this->reputation;
    }

    public function getCasesCompleted(): int
    {
        return $this->casesCompleted;
    }

    public function getCasesPassed(): int
    {
        return $this->casesPassed;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
