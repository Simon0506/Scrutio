<?php

namespace App\Tests\Service;

use App\Entity\CaseFile;
use App\Entity\CaseSubmission;
use App\Entity\InvestigationSession;
use App\Service\CaseResultSummaryService;
use App\Service\ScoringService;
use PHPUnit\Framework\TestCase;

final class CaseResultSummaryServiceTest extends TestCase
{
    public function testUnplayedCaseHasNoResult(): void
    {
        self::assertSame([], $this->service()->summarize([]));
    }

    public function testKeepsBestScoreAndSuccessfulStatusAcrossRetries(): void
    {
        $submissions = [
            $this->submission('affaire', 80, true),
            $this->submission('affaire', 20, false),
            $this->submission('autre-affaire', 10, false),
        ];
        $summary = $this->service()->summarize($submissions);

        self::assertSame(['score' => 80, 'maximum' => 110, 'passed' => true], $summary['affaire']);
        self::assertFalse($summary['autre-affaire']['passed']);
        $reversed = $this->service()->summarize(array_reverse($submissions));
        ksort($summary);
        ksort($reversed);
        self::assertSame($summary, $reversed);
    }

    public function testPreservesAnonymousRecordWhenAnotherAttemptIsSubmitted(): void
    {
        $previous = ['affaire' => ['score' => 80, 'maximum' => 110, 'passed' => true]];
        $summary = $this->service()->summarize([$this->submission('affaire', 0, false)], $previous);

        self::assertSame($previous, $summary);
    }

    public function testLegacyResultUsesItsHistoricalMaximum(): void
    {
        $case = new CaseFile('Affaire', 'ancienne-affaire', 'Test.', 'Initiation');
        $attempt = new InvestigationSession($case, str_repeat('a', 48));
        $submission = new CaseSubmission($attempt, 'authentic', 60, ['conclusion' => ['correct' => true]]);

        self::assertSame(
            ['score' => 60, 'maximum' => 100, 'passed' => true],
            $this->service()->summarize([$submission])['ancienne-affaire'],
        );
    }

    private function service(): CaseResultSummaryService
    {
        return new CaseResultSummaryService(new ScoringService());
    }

    private function submission(string $slug, int $score, bool $passed): CaseSubmission
    {
        $case = new CaseFile('Affaire', $slug, 'Test.', 'Intermédiaire');
        $attempt = new InvestigationSession($case, bin2hex(random_bytes(24)));

        return new CaseSubmission($attempt, 'fraud_confirmed', $score, [
            'score' => $score,
            'maximum' => 110,
            'passed' => $passed,
        ]);
    }
}