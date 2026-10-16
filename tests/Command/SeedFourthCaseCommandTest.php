<?php

namespace App\Tests\Command;

use App\Command\SeedFourthCaseCommand;
use App\Entity\CaseFile;
use App\Entity\ContentPack;
use App\Entity\InvestigationSession;
use App\Entity\PlayerAnomaly;
use App\Entity\PlayerEvidenceSelection;
use App\Service\ScoringService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedFourthCaseCommandTest extends TestCase
{
    public function testScenarioContainsTenProDocumentsInPaidPackAndFiveSolvableFindings(): void
    {
        $case = $this->createCase();
        self::assertInstanceOf(ContentPack::class, $case->getPack());
        self::assertSame('les-dossiers-datelier', $case->getPack()->getSlug());
        self::assertSame(499, $case->getPack()->getPriceAmountInCents());
        self::assertSame('Pro', $case->getDifficulty());
        self::assertCount(10, $case->getDocuments());
        $solution = $case->getSolution()->getSchema();
        self::assertSame('fraud_confirmed', $solution['expectedConclusion']);
        self::assertCount(5, $solution['expectedAnomalies']);
        self::assertCount(4, array_unique(array_column($solution['expectedAnomalies'], 'type')));

        $investigation = new InvestigationSession($case, str_repeat('d', 48));
        foreach ($solution['expectedAnomalies'] as $finding) {
            $this->reportFinding($investigation, $finding);
        }

        $result = (new ScoringService())->evaluate($investigation, 'fraud_confirmed');
        self::assertSame(170, $result['score']);
        self::assertSame(170, $result['maximum']);
        self::assertSame(85, $result['minimumPassingScore']);
        self::assertSame(5, $result['anomalies']['found']);
        self::assertSame(0, $result['falseAccusations']['count']);
        self::assertTrue($result['passed']);
    }

    public function testEquivalentCrossDocumentAndChronologicalClassificationsAreAccepted(): void
    {
        $case = $this->createCase();
        $investigation = new InvestigationSession($case, str_repeat('e', 48));
        foreach ($case->getSolution()->getSchema()['expectedAnomalies'] as $finding) {
            $variant = $finding['acceptedVariants'][0] ?? [];
            $this->reportFinding($investigation, array_replace($finding, $variant));
        }

        self::assertSame(170, (new ScoringService())->evaluate($investigation, 'fraud_confirmed')['score']);
    }

    public function testScanningDateIsNotAValidFinding(): void
    {
        $case = $this->createCase();
        $investigation = new InvestigationSession($case, str_repeat('f', 48));
        $anomaly = new PlayerAnomaly($investigation, 'metadata_inconsistency', null);
        $document = $case->getDocuments()->first();
        new PlayerEvidenceSelection($anomaly, $document, $document->getMetadata()->first()->getMetadataValue(), 1);

        $result = (new ScoringService())->evaluate($investigation, 'fraud_confirmed');
        self::assertSame(-10, $result['submittedAnomalies'][0]['points']);
        self::assertSame(0, $result['anomalies']['found']);
        self::assertFalse($result['passed']);
    }

    private function createCase(): CaseFile
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $case = null;
        $packPersisted = false;
        $entityManager->expects(self::atLeast(2))->method('persist')->willReturnCallback(
            static function (object $entity) use (&$case, &$packPersisted): void {
                if ($entity instanceof ContentPack) {
                    $packPersisted = true;

                    return;
                }
                self::assertInstanceOf(CaseFile::class, $entity);
                $case = $entity;
            },
        );
        $entityManager->expects(self::once())->method('flush');
        $tester = new CommandTester(new SeedFourthCaseCommand($entityManager));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        self::assertTrue($packPersisted);
        if (!$case instanceof CaseFile) {
            throw new \LogicException('The command did not persist a case.');
        }

        return $case;
    }

    private function reportFinding(InvestigationSession $investigation, array $finding): void
    {
        $anomaly = new PlayerAnomaly($investigation, $finding['type'], null);
        foreach ($finding['expectedEvidence'] as $index => $evidence) {
            foreach ($investigation->getCaseFile()->getDocuments() as $document) {
                if ($document->getDisplayOrder() !== $evidence['documentOrder']) {
                    continue;
                }

                $content = $document->getContent();
                $passages = array_merge(
                    $content['clauses'] ?? $content['paragraphs'] ?? [],
                    [$content['documentDate'] ?? $content['date'] ?? ''],
                );
                if (isset($content['visualExcerpt'])) {
                    $passages[] = implode(' ', array_values($content['visualExcerpt']));
                }
                foreach ($document->getMetadata() as $metadata) {
                    $passages[] = $metadata->getMetadataValue();
                }
                $matchingPassages = array_filter($passages, static fn(string $passage): bool => str_contains($passage, $evidence['selectionKeywords'][0]));
                self::assertNotEmpty($matchingPassages, 'Every expected evidence must exist in a selectable passage.');
                new PlayerEvidenceSelection($anomaly, $document, array_values($matchingPassages)[0], $index + 1);
            }
        }
    }
}
