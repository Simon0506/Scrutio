<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'solution_schema', options: ['engine' => 'InnoDB'])]
class SolutionSchema
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'solution')]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private CaseFile $caseFile;

    #[ORM\Column(name: 'solution_data', type: 'json')]
    private array $schema;

    public function __construct(array $schema)
    {
        $this->schema = $schema;
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

    public function getSchema(): array
    {
        return $this->schema;
    }
}
