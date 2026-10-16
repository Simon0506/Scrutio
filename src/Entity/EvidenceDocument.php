<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'evidence_document', options: ['engine' => 'InnoDB'])]
class EvidenceDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    private CaseFile $caseFile;

    #[ORM\Column(length: 160)]
    private string $title;

    #[ORM\Column(length: 32)]
    private string $documentType;

    #[ORM\Column(length: 64)]
    private string $templateKey;

    #[ORM\Column(type: 'json')]
    private array $content;

    #[ORM\Column]
    private int $displayOrder;

    /** @var Collection<int, DocumentMetadata> */
    #[ORM\OneToMany(mappedBy: 'document', targetEntity: DocumentMetadata::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $metadata;

    public function __construct(
        string $title,
        string $documentType,
        string $templateKey,
        array $content,
        int $displayOrder = 1,
    ) {
        $this->title = $title;
        $this->documentType = $documentType;
        $this->templateKey = $templateKey;
        $this->content = $content;
        $this->displayOrder = $displayOrder;
        $this->metadata = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCaseFile(): CaseFile
    {
        return $this->caseFile;
    }

    public function setCaseFile(CaseFile $caseFile): void
    {
        $this->caseFile = $caseFile;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function getTemplateKey(): string
    {
        return $this->templateKey;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    /** @return Collection<int, DocumentMetadata> */
    public function getMetadata(): Collection
    {
        return $this->metadata;
    }

    public function addMetadata(DocumentMetadata $metadata): void
    {
        if (!$this->metadata->contains($metadata)) {
            $this->metadata->add($metadata);
            $metadata->setDocument($this);
        }
    }
}
