<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'player_evidence_selection', options: ['engine' => 'InnoDB'])]
class PlayerEvidenceSelection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'evidenceSelections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PlayerAnomaly $anomaly;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private EvidenceDocument $document;

    #[ORM\Column(type: 'text')]
    private string $selectedText;

    #[ORM\Column]
    private int $position;

    public function __construct(PlayerAnomaly $anomaly, EvidenceDocument $document, string $selectedText, int $position)
    {
        $this->anomaly = $anomaly;
        $this->anomaly->addEvidenceSelection($this);
        $this->document = $document;
        $this->selectedText = $selectedText;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnomaly(): PlayerAnomaly
    {
        return $this->anomaly;
    }

    public function getDocument(): EvidenceDocument
    {
        return $this->document;
    }

    public function getSelectedText(): string
    {
        return $this->selectedText;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
