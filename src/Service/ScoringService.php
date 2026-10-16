<?php

namespace App\Service;

use App\Entity\InvestigationSession;
use App\Entity\PlayerAnomaly;

final class ScoringService
{
    private const CONCLUSION_POINTS = 20;
    private const POINTS_PER_ANOMALY = 30;
    private const FALSE_ACCUSATION_PENALTY = 10;

    public function evaluate(InvestigationSession $investigation, string $conclusion): array
    {
        $solution = $investigation->getCaseFile()->getSolution()?->getSchema() ?? [];
        $expectedConclusion = $solution['expectedConclusion'] ?? null;
        $expectedAnomalies = is_array($solution['expectedAnomalies'] ?? null)
            ? $solution['expectedAnomalies']
            : [];

        $matchedExpected = [];
        $falseAccusationCount = 0;
        $submittedAnomalies = [];

        foreach ($investigation->getAnomalies() as $anomaly) {
            $matchedExpectedIndex = null;

            foreach ($expectedAnomalies as $index => $expectedAnomaly) {
                if (!isset($matchedExpected[$index]) && $this->matchesExpectedAnomaly($anomaly, $expectedAnomaly)) {
                    $matchedExpectedIndex = $index;
                    $matchedExpected[$index] = true;
                    break;
                }
            }

            $isCorrect = $matchedExpectedIndex !== null;
            if (!$isCorrect) {
                ++$falseAccusationCount;
            }

            $submittedAnomalies[] = [
                'type' => $anomaly->getAnomalyType(),
                'correct' => $isCorrect,
                'points' => $isCorrect ? self::POINTS_PER_ANOMALY : -self::FALSE_ACCUSATION_PENALTY,
                'explanation' => $isCorrect && is_string($expectedAnomalies[$matchedExpectedIndex]['explanation'] ?? null)
                    ? $expectedAnomalies[$matchedExpectedIndex]['explanation']
                    : null,
                'evidence' => array_map(static fn($selection): array => [
                    'documentTitle' => $selection->getDocument()->getTitle(),
                    'selectedText' => $selection->getSelectedText(),
                ], $anomaly->getEvidenceSelections()->toArray()),
            ];
        }

        $expectedCount = count($expectedAnomalies);
        $foundCount = count($matchedExpected);
        $conclusionIsCorrect = is_string($expectedConclusion) && $conclusion === $expectedConclusion;
        $conclusionPoints = $conclusionIsCorrect ? self::CONCLUSION_POINTS : 0;
        $anomalyPoints = $foundCount * self::POINTS_PER_ANOMALY;
        $maximumScore = self::CONCLUSION_POINTS + $expectedCount * self::POINTS_PER_ANOMALY;
        $minimumPassingScore = (int) ceil($maximumScore / 2);
        $falseAccusationPenalty = $falseAccusationCount * self::FALSE_ACCUSATION_PENALTY;
        $score = max(0, $conclusionPoints + $anomalyPoints - $falseAccusationPenalty);
        $passed = $conclusionIsCorrect && $score >= $minimumPassingScore;

        return [
            'score' => $score,
            'maximum' => $maximumScore,
            'minimumPassingScore' => $minimumPassingScore,
            'passed' => $passed,
            'conclusion' => [
                'earned' => $conclusionPoints,
                'possible' => self::CONCLUSION_POINTS,
                'correct' => $conclusionIsCorrect,
            ],
            'anomalies' => [
                'earned' => $anomalyPoints,
                'possible' => $expectedCount * self::POINTS_PER_ANOMALY,
                'found' => $foundCount,
                'expected' => $expectedCount,
            ],
            'falseAccusations' => [
                'count' => $falseAccusationCount,
                'penalty' => $falseAccusationPenalty,
            ],
            'submittedAnomalies' => $submittedAnomalies,
        ];
    }

    private function normalize(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return strtolower($ascii === false ? $value : $ascii);
    }

    private function matchesExpectedAnomaly(PlayerAnomaly $anomaly, array $expectedAnomaly): bool
    {
        if ($this->matchesExpectedAnomalyCriteria($anomaly, $expectedAnomaly)) {
            return true;
        }

        foreach ($expectedAnomaly['acceptedVariants'] ?? [] as $variant) {
            if (is_array($variant) && $this->matchesExpectedAnomalyCriteria($anomaly, array_replace($expectedAnomaly, $variant))) {
                return true;
            }
        }

        return false;
    }

    private function matchesExpectedAnomalyCriteria(PlayerAnomaly $anomaly, array $expectedAnomaly): bool
    {
        if (($expectedAnomaly['type'] ?? null) !== $anomaly->getAnomalyType()) {
            return false;
        }

        $selections = array_values($anomaly->getEvidenceSelections()->toArray());
        $minimumSelections = max(1, (int) ($expectedAnomaly['minimumSelections'] ?? 1));
        if (count($selections) < $minimumSelections) {
            return false;
        }

        $minimumDistinctDocuments = max(1, (int) ($expectedAnomaly['minimumDistinctDocuments'] ?? 1));
        $distinctDocumentIds = array_unique(array_map(
            static fn($selection): int => $selection->getDocument()->getId() ?? spl_object_id($selection->getDocument()),
            $selections,
        ));
        if (count($distinctDocumentIds) < $minimumDistinctDocuments) {
            return false;
        }

        $expectedEvidence = $expectedAnomaly['expectedEvidence'] ?? [];
        if (!is_array($expectedEvidence) || $expectedEvidence === []) {
            $expectedEvidence = array_map(
                static fn(int $documentOrder): array => ['documentOrder' => $documentOrder],
                $expectedAnomaly['documentOrders'] ?? [],
            );
        }

        $availableSelections = $selections;
        foreach ($expectedEvidence as $expectedSelection) {
            $matchIndex = null;
            foreach ($availableSelections as $index => $selection) {
                if ($selection->getDocument()->getDisplayOrder() !== ($expectedSelection['documentOrder'] ?? null)) {
                    continue;
                }

                $keywords = $expectedSelection['selectionKeywords'] ?? [];
                if (!is_array($keywords) || $keywords === []) {
                    $matchIndex = $index;
                    break;
                }

                $selectedText = $this->normalize($selection->getSelectedText());
                foreach ($keywords as $keyword) {
                    if (is_string($keyword) && str_contains($selectedText, $this->normalize($keyword))) {
                        $matchIndex = $index;
                        break 2;
                    }
                }
            }

            if ($matchIndex === null) {
                return false;
            }

            unset($availableSelections[$matchIndex]);
        }

        return true;
    }
}
