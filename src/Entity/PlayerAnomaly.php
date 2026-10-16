<?php

namespace App\Entity;

use App\Entity\InvestigationSession;
use App\Entity\PlayerEvidenceSelection;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'player_anomaly', options: ['engine' => 'InnoDB'])]
class PlayerAnomaly
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'anomalies')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private InvestigationSession $investigationSession;

    #[ORM\Column(length: 48)]
    private string $anomalyType;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $justification;

    /** @var Collection<int, PlayerEvidenceSelection> */
    #[ORM\OneToMany(mappedBy: 'anomaly', targetEntity: PlayerEvidenceSelection::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $evidenceSelections;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(
        InvestigationSession $investigationSession,
        string $anomalyType,
        ?string $justification,
    ) {
        $this->investigationSession = $investigationSession;
        $this->investigationSession->addAnomaly($this);
        $this->anomalyType = $anomalyType;
        $this->justification = $justification;
        $this->evidenceSelections = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvestigationSession(): InvestigationSession
    {
        return $this->investigationSession;
    }

    public function getJustification(): ?string
    {
        return $this->justification;
    }

    public function getAnomalyType(): string
    {
        return $this->anomalyType;
    }

    /** @return Collection<int, PlayerEvidenceSelection> */
    public function getEvidenceSelections(): Collection
    {
        return $this->evidenceSelections;
    }

    public function addEvidenceSelection(PlayerEvidenceSelection $selection): void
    {
        if (!$this->evidenceSelections->contains($selection)) {
            $this->evidenceSelections->add($selection);
        }
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
