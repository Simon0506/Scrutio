<?php

namespace App\Entity;

use App\Entity\CaseSubmission;
use App\Entity\PlayerAnomaly;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'investigation_session', options: ['engine' => 'InnoDB'])]
class InvestigationSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private CaseFile $caseFile;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $player = null;

    #[ORM\Column(length: 48, unique: true)]
    private string $publicToken;

    #[ORM\Column(length: 24)]
    private string $status = 'in_progress';

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $startedAt;

    /** @var Collection<int, PlayerAnomaly> */
    #[ORM\OneToMany(mappedBy: 'investigationSession', targetEntity: PlayerAnomaly::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $anomalies;

    #[ORM\OneToOne(mappedBy: 'investigationSession', targetEntity: CaseSubmission::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?CaseSubmission $submission = null;

    public function __construct(CaseFile $caseFile, string $publicToken, ?User $player = null)
    {
        $this->caseFile = $caseFile;
        $this->publicToken = $publicToken;
        $this->player = $player;
        $this->startedAt = new DateTimeImmutable();
        $this->anomalies = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCaseFile(): CaseFile
    {
        return $this->caseFile;
    }

    public function getPlayer(): ?User
    {
        return $this->player;
    }

    public function assignPlayer(User $player): void
    {
        if ($this->player !== null && $this->player->getId() !== $player->getId()) {
            throw new \LogicException('An investigation session cannot be reassigned to another player.');
        }

        $this->player = $player;
    }

    public function getPublicToken(): string
    {
        return $this->publicToken;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    /** @return Collection<int, PlayerAnomaly> */
    public function getAnomalies(): Collection
    {
        return $this->anomalies;
    }

    public function getSubmission(): ?CaseSubmission
    {
        return $this->submission;
    }

    public function setSubmission(CaseSubmission $submission): void
    {
        $this->submission = $submission;
        $this->status = 'submitted';
    }

    public function addAnomaly(PlayerAnomaly $anomaly): void
    {
        if (!$this->anomalies->contains($anomaly)) {
            $this->anomalies->add($anomaly);
        }
    }

    public function removeAnomaly(PlayerAnomaly $anomaly): void
    {
        if ($anomaly->getInvestigationSession() === $this) {
            $this->anomalies->removeElement($anomaly);
        }
    }
}
