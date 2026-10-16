<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'document_metadata', options: ['engine' => 'InnoDB'])]
class DocumentMetadata
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'metadata')]
    #[ORM\JoinColumn(nullable: false)]
    private EvidenceDocument $document;

    #[ORM\Column(length: 100)]
    private string $metadataKey;

    #[ORM\Column(type: 'text')]
    private string $metadataValue;

    #[ORM\Column]
    private int $displayOrder;

    public function __construct(string $metadataKey, string $metadataValue, int $displayOrder = 1)
    {
        $this->metadataKey = $metadataKey;
        $this->metadataValue = $metadataValue;
        $this->displayOrder = $displayOrder;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocument(): EvidenceDocument
    {
        return $this->document;
    }

    public function setDocument(EvidenceDocument $document): void
    {
        $this->document = $document;
    }

    public function getMetadataKey(): string
    {
        return $this->metadataKey;
    }

    public function getMetadataValue(): string
    {
        return $this->metadataValue;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }
}
