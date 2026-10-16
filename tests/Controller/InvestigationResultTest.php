<?php

namespace App\Tests\Controller;

use App\Entity\CaseFile;
use App\Entity\CaseSubmission;
use App\Entity\InvestigationSession;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Twig\Environment;

final class InvestigationResultTest extends KernelTestCase
{
    private bool $temporaryDatabaseUrl = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (!isset($_ENV['DATABASE_URL']) && !isset($_SERVER['DATABASE_URL']) && getenv('DATABASE_URL') === false) {
            $this->temporaryDatabaseUrl = true;
            $_ENV['DATABASE_URL'] = 'sqlite:///:memory:';
        }
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            if ($this->temporaryDatabaseUrl) {
                unset($_ENV['DATABASE_URL']);
            }
        }
    }

    public function testUnlockedNextCaseHasAPrimaryLinkAndRegistryAction(): void
    {
        $html = $this->renderResult(true, ['slug' => 'next-case', 'title' => 'Affaire suivante', 'unlocked' => true]);

        self::assertStringContainsString('class="primary-action" href="/affaires/next-case"', $html);
        self::assertStringContainsString('class="secondary-action" href="/affaires"', $html);
        self::assertStringContainsString('Recommencer l’expertise', $html);
        self::assertStringContainsString('VALIDÉE', $html);
        self::assertStringNotContainsString('id="next-expertise-requirement"', $html);
    }

    public function testLockedNextCaseIsDisabledInsteadOfLinked(): void
    {
        $html = $this->renderResult(false, ['slug' => 'next-case', 'title' => 'Affaire suivante', 'unlocked' => false]);

        self::assertStringContainsString('disabled aria-describedby="next-expertise-requirement"', $html);
        self::assertStringContainsString('Validez cette expertise pour accéder à la suivante.', $html);
        self::assertStringContainsString('NON VALIDÉE', $html);
        self::assertStringNotContainsString('href="/affaires/next-case"', $html);
    }

    public function testLastCaseHasNoNextAction(): void
    {
        $html = $this->renderResult(true, null);

        self::assertStringNotContainsString('Expertise suivante', $html);
        self::assertStringContainsString('Recommencer l’expertise', $html);
        self::assertStringContainsString('class="secondary-action" href="/affaires"', $html);
    }

    private function renderResult(bool $passed, ?array $nextCase): string
    {
        self::bootKernel();
        $container = static::getContainer();
        $request = Request::create('/enquete/' . str_repeat('a', 48) . '/resultat');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $container->get(RequestStack::class)->push($request);

        $case = new CaseFile('Affaire test', 'test-case', 'Test.', 'Initiation');
        $investigation = new InvestigationSession($case, str_repeat('a', 48));
        $score = [
            'passed' => $passed,
            'score' => $passed ? 100 : 0,
            'maximum' => 100,
            'minimumPassingScore' => 50,
            'conclusion' => ['correct' => $passed, 'earned' => $passed ? 40 : 0, 'possible' => 40],
            'anomalies' => ['earned' => $passed ? 60 : 0, 'possible' => 60, 'found' => $passed ? 1 : 0, 'expected' => 1],
            'falseAccusations' => ['count' => 0, 'penalty' => 0],
            'submittedAnomalies' => [],
        ];
        $submission = new CaseSubmission($investigation, 'authentic', $score['score'], $score);

        return $container->get(Environment::class)->load('investigation/result.html.twig')->renderBlock('body', [
            'case' => $case,
            'investigation' => $investigation,
            'submission' => $submission,
            'score' => $score,
            'bestScore' => $score['score'],
            'conclusionLabels' => ['authentic' => 'Authentique'],
            'nextCase' => $nextCase,
            'expectedConclusion' => 'Authentique',
            'officialFindings' => [],
            'metadataCaution' => null,
        ]);
    }
}
