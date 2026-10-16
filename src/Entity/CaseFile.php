<?php

namespace App\Entity;

use App\Entity\ContentPack;
use App\Entity\EvidenceDocument;
use App\Entity\SolutionSchema;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'case_file', options: ['engine' => 'InnoDB'])]
class CaseFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private string $title;

    #[ORM\Column(length: 180, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'text')]
    private string $synopsis;

    #[ORM\Column(length: 24)]
    private string $difficulty;

    #[ORM\ManyToOne(inversedBy: 'cases')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?ContentPack $pack = null;

    #[ORM\Column]
    private int $displayOrder;

    /** @var Collection<int, EvidenceDocument> */
    #[ORM\OneToMany(mappedBy: 'caseFile', targetEntity: EvidenceDocument::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $documents;

    #[ORM\OneToOne(mappedBy: 'caseFile', targetEntity: SolutionSchema::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?SolutionSchema $solution = null;

    public function __construct(
        string $title,
        string $slug,
        string $synopsis,
        string $difficulty,
        int $displayOrder = 1,
        ?ContentPack $pack = null,
    ) {
        $this->title = $title;
        $this->slug = $slug;
        $this->synopsis = $synopsis;
        $this->difficulty = $difficulty;
        $this->displayOrder = $displayOrder;
        $this->documents = new ArrayCollection();
        $this->setPack($pack);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getSynopsis(): string
    {
        return $this->synopsis;
    }

    public function getDifficulty(): string
    {
        return $this->difficulty;
    }

    public function getPack(): ?ContentPack
    {
        return $this->pack;
    }

    public function setPack(?ContentPack $pack): void
    {
        if ($this->pack === $pack) {
            return;
        }

        $previousPack = $this->pack;
        $this->pack = $pack;
        $previousPack?->removeCase($this);
        $pack?->addCase($this);
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    /** @return Collection<int, EvidenceDocument> */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(EvidenceDocument $document): void
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setCaseFile($this);
        }
    }

    public function getSolution(): ?SolutionSchema
    {
        return $this->solution;
    }

    public function setSolution(?SolutionSchema $solution): void
    {
        $this->solution = $solution;
        $solution?->setCaseFile($this);
    }
}
