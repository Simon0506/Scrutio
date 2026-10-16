<?php

namespace App\Service;

use App\Entity\CaseSubmission;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class CaseResultSummaryService
{
    public function __construct(private readonly ScoringService $scoringService) {}

    public function forPlayer(?User $user, Request $request, EntityManagerInterface $entityManager): array
    {
        $query = $entityManager->createQueryBuilder()
            ->select('submission', 'attempt', 'caseFile')
            ->from(CaseSubmission::class, 'submission')
            ->join('submission.investigationSession', 'attempt')
            ->join('attempt.caseFile', 'caseFile');

        if ($user !== null) {
            $query->where('attempt.player = :player')->setParameter('player', $user);

            return $this->summarize($query->getQuery()->getResult());
        }

        $storedResults = $request->getSession()->get('scrutio.case_results', []);
        $summaries = is_array($storedResults) ? $storedResults : [];
        $tokens = $request->getSession()->get('scrutio.case_sessions', []);
        $tokens = is_array($tokens) ? array_values(array_filter($tokens, 'is_string')) : [];
        if ($tokens === []) {
            return $summaries;
        }

        $query->where('attempt.player IS NULL')
            ->andWhere('attempt.publicToken IN (:tokens)')->setParameter('tokens', $tokens);

        return $this->summarize($query->getQuery()->getResult(), $summaries);
    }

    /** @param iterable<CaseSubmission> $submissions */
    public function summarize(iterable $submissions, array $summaries = []): array
    {
        foreach ($submissions as $submission) {
            $investigation = $submission->getInvestigationSession();
            $score = $submission->getScoreBreakdown();
            if (isset($score['submittedAnomalies'])) {
                $score = $this->scoringService->evaluate($investigation, $submission->getConclusion());
            }

            $result = [
                'score' => $score['score'] ?? $submission->getScore(),
                'maximum' => $score['maximum'] ?? 100,
                'passed' => $score['passed'] ?? (($score['conclusion']['correct'] ?? false) && $submission->getScore() >= 50),
            ];
            $slug = $investigation->getCaseFile()->getSlug();
            $previous = $summaries[$slug] ?? null;
            if ($previous === null) {
                $summaries[$slug] = $result;
                continue;
            }

            $passed = $previous['passed'] || $result['passed'];
            if ($result['score'] > $previous['score']) {
                $summaries[$slug] = $result;
            }
            $summaries[$slug]['passed'] = $passed;
        }

        return $summaries;
    }
}
