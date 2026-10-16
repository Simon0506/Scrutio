<?php

namespace App\Tests\Service;

use App\Entity\CaseFile;
use App\Entity\EvidenceDocument;
use App\Entity\InvestigationSession;
use App\Entity\PlayerAnomaly;
use App\Entity\PlayerEvidenceSelection;
use App\Entity\SolutionSchema;
use App\Service\ScoringService;
use PHPUnit\Framework\TestCase;

final class ScoringServiceTest extends TestCase
{
    public function testAuthenticCaseCanPassWithoutReportingAnyAnomaly(): void
    {
        $case = new CaseFile('Dossier authentique', 'dossier-authentique', 'Test.', 'Intermédiaire');
        $case->setSolution(new SolutionSchema([
            'expectedConclusion' => 'authentic',
            'expectedAnomalies' => [],
        ]));
        $investigation = new InvestigationSession($case, str_repeat('c', 48));
        $scoring = new ScoringService();

        $result = $scoring->evaluate($investigation, 'authentic');
        self::assertSame(20, $result['score']);
        self::assertSame(20, $result['maximum']);
        self::assertTrue($result['passed']);
        self::assertSame([], $result['submittedAnomalies']);
        self::assertFalse($scoring->evaluate($investigation, 'fraud_confirmed')['passed']);

        $document = new EvidenceDocument('Pièce cohérente', 'Courrier', 'letter', [], 1);
        $case->addDocument($document);
        $anomaly = new PlayerAnomaly($investigation, 'metadata_inconsistency', null);
        new PlayerEvidenceSelection($anomaly, $document, 'Date de numérisation', 1);

        $penalized = $scoring->evaluate($investigation, 'authentic');
        self::assertSame(10, $penalized['score']);
        self::assertSame(-10, $penalized['submittedAnomalies'][0]['points']);
    }

    public function testInconclusiveConclusionRequiresBothUnresolvedFindings(): void
    {
        [$investigation, $deathRecord, $codicil] = $this->createInvestigation();
        $anomaly = new PlayerAnomaly($investigation, 'cross_document_inconsistency', null);
        new PlayerEvidenceSelection($anomaly, $deathRecord, '4 mars 2025', 1);
        new PlayerEvidenceSelection($anomaly, $codicil, '12 mars 2025', 2);

        $solution = $investigation->getCaseFile()->getSolution()->getSchema();
        $solution['expectedConclusion'] = 'inconclusive';
        $investigation->getCaseFile()->setSolution(new SolutionSchema($solution));

        $result = (new ScoringService())->evaluate($investigation, 'inconclusive');

        self::assertSame(50, $result['score']);
        self::assertSame(50, $result['maximum']);
        self::assertSame(25, $result['minimumPassingScore']);
        self::assertTrue($result['passed']);
    }

    public function testCorrectConclusionAndFindingPassSingleFindingCaseWithoutJustification(): void
    {
        [$investigation, $deathRecord, $codicil] = $this->createInvestigation();
        $anomaly = new PlayerAnomaly($investigation, 'cross_document_inconsistency', null);
        new PlayerEvidenceSelection($anomaly, $deathRecord, '4 mars 2025', 1);
        new PlayerEvidenceSelection($anomaly, $codicil, '12 mars 2025', 2);

        $result = (new ScoringService())->evaluate($investigation, 'fraud_confirmed');

        self::assertSame(50, $result['score']);
        self::assertSame(50, $result['maximum']);
        self::assertSame(25, $result['minimumPassingScore']);
        self::assertTrue($result['passed']);
        self::assertTrue($result['conclusion']['correct']);
        self::assertTrue($result['submittedAnomalies'][0]['correct']);
        self::assertSame(30, $result['submittedAnomalies'][0]['points']);
    }

    public function testUnsupportedAnomalyIsPenalized(): void
    {
        [$investigation,, $codicil] = $this->createInvestigation();
        $anomaly = new PlayerAnomaly($investigation, 'cross_document_inconsistency', null);
        new PlayerEvidenceSelection($anomaly, $codicil, '12 mars 2025', 1);

        $result = (new ScoringService())->evaluate($investigation, 'fraud_confirmed');

        self::assertSame(10, $result['score']);
        self::assertSame(1, $result['falseAccusations']['count']);
        self::assertSame(10, $result['falseAccusations']['penalty']);
        self::assertFalse($result['passed']);
        self::assertFalse($result['submittedAnomalies'][0]['correct']);
        self::assertSame(-10, $result['submittedAnomalies'][0]['points']);
    }

    public function testIncorrectConclusionAndUnsubstantiatedReportScoreZero(): void
    {
        [$investigation,, $codicil] = $this->createInvestigation();
        $anomaly = new PlayerAnomaly($investigation, 'anachronism', null);
        new PlayerEvidenceSelection($anomaly, $codicil, 'texte sans incohérence', 1);

        $result = (new ScoringService())->evaluate($investigation, 'authentic');

        self::assertSame(0, $result['score']);
        self::assertFalse($result['conclusion']['correct']);
    }

    public function testThreeFindingCaseRequiresAtLeastFiftyFivePointsAndCorrectConclusion(): void
    {
        [$investigation, $documents] = $this->createThreeFindingInvestigation();
        $anomaly = new PlayerAnomaly($investigation, 'anachronism', null);
        new PlayerEvidenceSelection($anomaly, $documents[0], 'preuve 1', 1);

        $result = (new ScoringService())->evaluate($investigation, 'fraud_confirmed');

        self::assertSame(50, $result['score']);
        self::assertSame(110, $result['maximum']);
        self::assertSame(55, $result['minimumPassingScore']);
        self::assertFalse($result['passed']);
    }

    public function testThreeCorrectFindingsPassWithOneHundredTenPoints(): void
    {
        [$investigation, $documents] = $this->createThreeFindingInvestigation();
        foreach ($documents as $index => $document) {
            $anomaly = new PlayerAnomaly($investigation, 'anachronism', null);
            new PlayerEvidenceSelection($anomaly, $document, 'preuve ' . ($index + 1), 1);
        }

        $result = (new ScoringService())->evaluate($investigation, 'fraud_confirmed');

        self::assertSame(110, $result['score']);
        self::assertSame(110, $result['maximum']);
        self::assertSame(55, $result['minimumPassingScore']);
        self::assertTrue($result['passed']);
    }

    public function testOneReportedAnomalyCannotClaimMultipleExpectedFindings(): void
    {
        [$investigation, $documents] = $this->createThreeFindingInvestigation();
        $anomaly = new PlayerAnomaly($investigation, 'anachronism', null);
        foreach ($documents as $index => $document) {
            new PlayerEvidenceSelection($anomaly, $document, 'preuve ' . ($index + 1), $index + 1);
        }

        $result = (new ScoringService())->evaluate($investigation, 'fraud_confirmed');

        self::assertSame(1, $result['anomalies']['found']);
        self::assertSame(50, $result['score']);
        self::assertCount(1, $result['submittedAnomalies']);
        self::assertSame(30, $result['submittedAnomalies'][0]['points']);
    }

    private function createInvestigation(): array
    {
        $case = new CaseFile('Affaire test', 'affaire-test', 'Description de test.', 'Débutant');
        $firstDocument = new EvidenceDocument('Acte', 'Registre', 'registry', [], 1);
        $codicil = new EvidenceDocument('Codicille', 'Document', 'contract', [], 3);
        $case->addDocument($firstDocument);
        $case->addDocument($codicil);
        $case->setSolution(new SolutionSchema([
            'expectedConclusion' => 'fraud_confirmed',
            'expectedAnomalies' => [[
                'type' => 'anachronism',
                'minimumSelections' => 1,
                'documentOrders' => [1, 3],
                'expectedEvidence' => [
                    ['documentOrder' => 3, 'selectionKeywords' => ['12 mars 2025']],
                ],
                'explanation' => 'Le codicille est daté après le décès.',
                'acceptedVariants' => [[
                    'type' => 'cross_document_inconsistency',
                    'minimumSelections' => 2,
                    'minimumDistinctDocuments' => 2,
                    'expectedEvidence' => [
                        ['documentOrder' => 1, 'selectionKeywords' => ['4 mars 2025']],
                        ['documentOrder' => 3, 'selectionKeywords' => ['12 mars 2025']],
                    ],
                ]],
            ]],
        ]));

        return [new InvestigationSession($case, str_repeat('a', 48)), $firstDocument, $codicil];
    }

    private function createThreeFindingInvestigation(): array
    {
        $case = new CaseFile('Affaire test', 'affaire-test-3', 'Description de test.', 'Débutant');
        $documents = [];
        $expectedAnomalies = [];

        for ($index = 1; $index <= 3; ++$index) {
            $document = new EvidenceDocument('Document ' . $index, 'Pièce', 'registry', [], $index);
            $case->addDocument($document);
            $documents[] = $document;
            $expectedAnomalies[] = [
                'type' => 'anachronism',
                'expectedEvidence' => [[
                    'documentOrder' => $index,
                    'selectionKeywords' => ['preuve ' . $index],
                ]],
            ];
        }

        $case->setSolution(new SolutionSchema([
            'expectedConclusion' => 'fraud_confirmed',
            'expectedAnomalies' => $expectedAnomalies,
        ]));

        return [new InvestigationSession($case, str_repeat('b', 48)), $documents];
    }
}
