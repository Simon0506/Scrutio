<?php

namespace App\Entity;

use App\Entity\InvestigationSession;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'case_submission', options: ['engine' => 'InnoDB'])]
class CaseSubmission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'submission')]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private InvestigationSession $investigationSession;

    #[ORM\Column(length: 32)]
    private string $conclusion;

    #[ORM\Column(type: 'text')]
    private string $justification = '';

    #[ORM\Column]
    private int $score;

    #[ORM\Column(type: 'json')]
    private array $scoreBreakdown;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $submittedAt;

    public function __construct(
        InvestigationSession $investigationSession,
        string $conclusion,
        int $score,
        array $scoreBreakdown,
    ) {
        $this->investigationSession = $investigationSession;
        $this->investigationSession->setSubmission($this);
        $this->conclusion = $conclusion;
        $this->score = $score;
        $this->scoreBreakdown = $scoreBreakdown;
        $this->submittedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvestigationSession(): InvestigationSession
    {
        return $this->investigationSession;
    }

    public function getConclusion(): string
    {
        return $this->conclusion;
    }

    public function getJustification(): string
    {
        return $this->justification;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function getScoreBreakdown(): array
    {
        return $this->scoreBreakdown;
    }

    public function getSubmittedAt(): DateTimeImmutable
    {
        return $this->submittedAt;
    }
}
