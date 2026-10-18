<?php

namespace App\Tests\Command;

use App\Command\SeedFifthCaseCommand;
use App\Entity\CaseFile;
use App\Entity\InvestigationSession;
use App\Entity\PlayerAnomaly;
use App\Entity\PlayerEvidenceSelection;
use App\Service\ScoringService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedFifthCaseCommandTest extends TestCase
{
    public function testDossierIsFreeProWithNineDocumentsAndInconclusiveOutcome(): void
    {
        $case = $this->createCase();
        self::assertNull($case->getPack());
        self::assertSame('Pro', $case->getDifficulty());
        self::assertCount(9, $case->getDocuments());

        $solution = $case->getSolution()->getSchema();
        self::assertSame('inconclusive', $solution['expectedConclusion']);
        self::assertCount(2, $solution['expectedAnomalies']);
        self::assertSame('cross_document_inconsistency', $solution['expectedAnomalies'][0]['type']);
        self::assertSame('missing_evidence', $solution['expectedAnomalies'][1]['type']);
        self::assertSame(1, $solution['expectedAnomalies'][1]['minimumSelections']);
        self::assertSame(1, $solution['expectedAnomalies'][1]['minimumDistinctDocuments']);
        self::assertSame(
            ['missing_evidence', 'cross_document_inconsistency'],
            array_column($solution['expectedAnomalies'][1]['acceptedVariants'], 'type'),
        );
    }

    public function testBothUnresolvedContradictionsSupportInconclusiveConclusion(): void
    {
        $case = $this->createCase();
        $investigation = new InvestigationSession($case, str_repeat('a', 48));
        foreach ($case->getSolution()->getSchema()['expectedAnomalies'] as $finding) {
            $this->reportFinding($investigation, $finding);
        }

        $result = (new ScoringService())->evaluate($investigation, 'inconclusive');

        self::assertSame(80, $result['score']);
        self::assertSame(80, $result['maximum']);
        self::assertSame(40, $result['minimumPassingScore']);
        self::assertSame(2, $result['anomalies']['found']);
        self::assertSame(0, $result['falseAccusations']['count']);
        self::assertTrue($result['passed']);
    }

    public function testSignatureVariationAloneIsNotAValidForgeryFinding(): void
    {
        $case = $this->createCase();
        $investigation = new InvestigationSession($case, str_repeat('b', 48));
        $anomaly = new PlayerAnomaly($investigation, 'graphic_forgery', null);
        $recognition = $this->documentAtOrder($case, 1);
        new PlayerEvidenceSelection($anomaly, $recognition, 'Signature attribuée à Paul Dervaux.', 1);

        $result = (new ScoringService())->evaluate($investigation, 'inconclusive');

        self::assertFalse($result['submittedAnomalies'][0]['correct']);
        self::assertSame(-10, $result['submittedAnomalies'][0]['points']);
        self::assertFalse($result['passed']);
    }

    public function testEitherSinglePassageCanSupportMissingEvidence(): void
    {
        foreach (
            [
                [4, 'avenant rectificatif signé en 2021'],
                [9, 'ne figure pas parmi les pièces remises'],
            ] as [$documentOrder, $keyword]
        ) {
            $case = $this->createCase();
            $investigation = new InvestigationSession($case, bin2hex(random_bytes(24)));
            $document = $this->documentAtOrder($case, $documentOrder);
            $content = $document->getContent();
            $passages = array_merge($content['clauses'] ?? $content['paragraphs'] ?? [], [$content['date'] ?? '', $content['documentDate'] ?? '']);
            $matches = array_filter($passages, static fn(string $passage): bool => str_contains($passage, $keyword));
            self::assertNotEmpty($matches);
            $anomaly = new PlayerAnomaly($investigation, 'missing_evidence', null);
            new PlayerEvidenceSelection($anomaly, $document, array_values($matches)[0], 1);

            $result = (new ScoringService())->evaluate($investigation, 'inconclusive');

            self::assertSame(1, $result['anomalies']['found']);
            self::assertTrue($result['submittedAnomalies'][0]['correct']);
        }
    }

    private function createCase(): CaseFile
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $case = null;
        $entityManager->expects(self::once())->method('persist')->willReturnCallback(
            static function (object $entity) use (&$case): void {
                self::assertInstanceOf(CaseFile::class, $entity);
                $case = $entity;
            },
        );
        $entityManager->expects(self::once())->method('flush');
        $tester = new CommandTester(new SeedFifthCaseCommand($entityManager));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        if (!$case instanceof CaseFile) {
            throw new \LogicException('The command did not persist a case.');
        }

        return $case;
    }

    private function reportFinding(InvestigationSession $investigation, array $finding): void
    {
        $anomaly = new PlayerAnomaly($investigation, $finding['type'], null);
        foreach ($finding['expectedEvidence'] as $index => $evidence) {
            $document = $this->documentAtOrder($investigation->getCaseFile(), $evidence['documentOrder']);
            $content = $document->getContent();
            $passages = array_merge($content['clauses'] ?? $content['paragraphs'] ?? [], [$content['date'] ?? '', $content['documentDate'] ?? '']);
            $matches = array_filter($passages, static fn(string $passage): bool => str_contains($passage, $evidence['selectionKeywords'][0]));
            self::assertNotEmpty($matches, 'Every expected passage must exist in the seeded evidence.');
            new PlayerEvidenceSelection($anomaly, $document, array_values($matches)[0], $index + 1);
        }
    }

    private function documentAtOrder(CaseFile $case, int $displayOrder): \App\Entity\EvidenceDocument
    {
        foreach ($case->getDocuments() as $document) {
            if ($document->getDisplayOrder() === $displayOrder) {
                return $document;
            }
        }

        throw new \LogicException(sprintf('Document order %d was not found.', $displayOrder));
    }
}
