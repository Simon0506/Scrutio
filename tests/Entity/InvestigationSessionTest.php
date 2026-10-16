<?php

namespace App\Tests\Entity;

use App\Entity\CaseFile;
use App\Entity\InvestigationSession;
use App\Entity\PlayerAnomaly;
use PHPUnit\Framework\TestCase;

final class InvestigationSessionTest extends TestCase
{
    public function testRemovingAnomalyUpdatesTheSessionCollection(): void
    {
        $case = new CaseFile('Affaire test', 'affaire-session-test', 'Description.', 'Débutant');
        $investigation = new InvestigationSession($case, str_repeat('a', 48));
        $anomaly = new PlayerAnomaly($investigation, 'anachronism', null);

        self::assertCount(1, $investigation->getAnomalies());

        $investigation->removeAnomaly($anomaly);

        self::assertCount(0, $investigation->getAnomalies());
    }

    public function testRemovingAnomalyFromAnotherSessionDoesNothing(): void
    {
        $case = new CaseFile('Affaire test', 'affaire-session-test-2', 'Description.', 'Débutant');
        $otherCase = new CaseFile('Autre affaire', 'autre-affaire-session-test', 'Description.', 'Débutant');
        $investigation = new InvestigationSession($case, str_repeat('a', 48));
        $otherInvestigation = new InvestigationSession($otherCase, str_repeat('b', 48));
        $foreignAnomaly = new PlayerAnomaly($otherInvestigation, 'anachronism', null);

        $investigation->removeAnomaly($foreignAnomaly);

        self::assertCount(0, $investigation->getAnomalies());
        self::assertCount(1, $otherInvestigation->getAnomalies());
    }
}
