<?php

namespace App\Controller;

use App\Entity\CaseFile;
use App\Entity\CaseSubmission;
use App\Entity\DocumentMetadata;
use App\Entity\EvidenceDocument;
use App\Entity\InvestigationSession;
use App\Entity\PlayerAnomaly;
use App\Entity\PlayerEvidenceSelection;
use App\Entity\User;
use App\Service\CaseAccessService;
use App\Service\CaseResultSummaryService;
use App\Service\ScoringService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class InvestigationController extends AbstractController
{
    public function __construct(
        private readonly CaseAccessService $caseAccessService,
        private readonly CaseResultSummaryService $progressionSummary,
    ) {}

    private const ANOMALY_TYPES = [
        'anachronism',
        'cross_document_inconsistency',
        'graphic_forgery',
        'metadata_inconsistency',
    ];

    #[Route('/affaires/{slug}/ouvrir', name: 'investigation_start', methods: ['POST'])]
    public function start(string $slug, Request $request, EntityManagerInterface $entityManager): Response
    {
        $case = $entityManager->getRepository(CaseFile::class)->findOneBy(['slug' => $slug]);
        $currentUser = $this->getUser();
        $player = $currentUser instanceof User ? $currentUser : null;

        if (!$case instanceof CaseFile || !$this->caseAccessService->canAccess($player, $case)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCaseUnlocked($case, $player, $request, $entityManager)) {
            throw $this->createAccessDeniedException('Validez les expertises précédentes pour ouvrir cette affaire.');
        }

        if (!$this->isCsrfTokenValid('start-case-' . $slug, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $tokensByCase = $request->getSession()->get('scrutio.case_sessions', []);
        if (!is_array($tokensByCase)) {
            $tokensByCase = [];
        }
        $existingToken = $tokensByCase[$slug] ?? null;
        $investigation = is_string($existingToken)
            ? $entityManager->getRepository(InvestigationSession::class)->findOneBy([
                'publicToken' => $existingToken,
                'caseFile' => $case,
            ])
            : null;

        if (
            $investigation instanceof InvestigationSession
            && $investigation->getPlayer() instanceof User
            && $investigation->getPlayer()->getId() !== $player?->getId()
        ) {
            $investigation = null;
        }

        if (!$investigation instanceof InvestigationSession && $player instanceof User) {
            $investigation = $entityManager->getRepository(InvestigationSession::class)->findOneBy(
                ['caseFile' => $case, 'player' => $player, 'status' => 'in_progress'],
                ['startedAt' => 'DESC'],
            );
        }

        if (
            $investigation instanceof InvestigationSession
            && $investigation->getStatus() === 'in_progress'
            && $investigation->getPlayer() === null
            && $player instanceof User
        ) {
            $investigation->assignPlayer($player);
            $entityManager->flush();
        }

        if (!$investigation instanceof InvestigationSession && $player instanceof User) {
            $submittedInvestigation = $entityManager->getRepository(InvestigationSession::class)->findOneBy(
                ['caseFile' => $case, 'player' => $player, 'status' => 'submitted'],
                ['startedAt' => 'DESC'],
            );
            if ($submittedInvestigation instanceof InvestigationSession) {
                return $this->redirectToRoute('investigation_result', ['token' => $submittedInvestigation->getPublicToken()]);
            }
        }

        if ($investigation instanceof InvestigationSession && $investigation->getStatus() === 'submitted') {
            return $this->redirectToRoute('investigation_result', ['token' => $investigation->getPublicToken()]);
        }

        if (!$investigation instanceof InvestigationSession) {
            $investigation = new InvestigationSession($case, bin2hex(random_bytes(24)), $player);
            $entityManager->persist($investigation);
            $entityManager->flush();
        }

        $tokensByCase[$slug] = $investigation->getPublicToken();
        $request->getSession()->set('scrutio.case_sessions', $tokensByCase);

        return $this->redirectToRoute('investigation_show', ['token' => $investigation->getPublicToken()]);
    }

    #[Route('/enquete/{token}/recommencer', name: 'investigation_restart', requirements: ['token' => '[a-f0-9]{48}'], methods: ['POST'])]
    public function restart(string $token, Request $request, EntityManagerInterface $entityManager): Response
    {
        $previousInvestigation = $this->findInvestigation($token, $request, $entityManager, false);
        if ($previousInvestigation->getStatus() !== 'submitted') {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('restart-investigation-' . $token, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $investigation = new InvestigationSession(
            $previousInvestigation->getCaseFile(),
            bin2hex(random_bytes(24)),
            $previousInvestigation->getPlayer(),
        );
        $entityManager->persist($investigation);
        $entityManager->flush();

        $tokensByCase = $request->getSession()->get('scrutio.case_sessions', []);
        if (!is_array($tokensByCase)) {
            $tokensByCase = [];
        }
        $tokensByCase[$investigation->getCaseFile()->getSlug()] = $investigation->getPublicToken();
        $request->getSession()->set('scrutio.case_sessions', $tokensByCase);

        return $this->redirectToRoute('investigation_show', ['token' => $investigation->getPublicToken()]);
    }

    #[Route('/enquete/{token}', name: 'investigation_show', requirements: ['token' => '[a-f0-9]{48}'], methods: ['GET'])]
    public function show(string $token, Request $request, EntityManagerInterface $entityManager): Response
    {
        $investigation = $this->findInvestigation($token, $request, $entityManager);
        $documents = $investigation->getCaseFile()->getDocuments()->toArray();
        usort($documents, static fn(EvidenceDocument $left, EvidenceDocument $right): int => $left->getDisplayOrder() <=> $right->getDisplayOrder());

        $documentTemplates = [
            'registry' => 'documents/registry.html.twig',
            'contract' => 'documents/contract.html.twig',
            'letter' => 'documents/letter.html.twig',
        ];
        $publicDocuments = array_map(static fn(EvidenceDocument $document): array => [
            'id' => $document->getId(),
            'title' => $document->getTitle(),
            'type' => $document->getDocumentType(),
            'content' => $document->getContent(),
            'template' => $documentTemplates[$document->getTemplateKey()] ?? null,
        ], $documents);

        $publicAnomalies = [];
        foreach ($investigation->getAnomalies() as $anomaly) {
            $publicAnomalies[] = [
                'id' => $anomaly->getId(),
                'type' => $anomaly->getAnomalyType(),
                'evidenceSelections' => array_map(static fn(PlayerEvidenceSelection $selection): array => [
                    'documentTitle' => $selection->getDocument()->getTitle(),
                    'selectedText' => $selection->getSelectedText(),
                ], $anomaly->getEvidenceSelections()->toArray()),
            ];
        }

        return $this->render('investigation/show.html.twig', [
            'investigation' => $investigation,
            'case' => $investigation->getCaseFile(),
            'documents' => $publicDocuments,
            'anomalies' => $publicAnomalies,
            'anomalyTypes' => [
                'anachronism' => 'Anachronisme matériel',
                'cross_document_inconsistency' => 'Incohérence croisée',
                'missing_evidence' => 'Pièce manquante',
                'graphic_forgery' => 'Falsification graphique',
                'metadata_inconsistency' => 'Incohérence système / métadonnées',
            ],
            'anomalyDescriptions' => [
                'anachronism' => 'Un fait impossible au regard d’une date vérifiable, par exemple une signature attribuée à une personne après son décès. Une pièce peut suffire.',
                'cross_document_inconsistency' => 'Deux pièces donnent des informations incompatibles sur le même fait. Sélectionnez un passage probant dans chaque pièce.',
                'missing_evidence' => 'Une pièce est annoncée dans un document mais absente du dossier transmis. Sélectionnez sa mention et le bordereau ou inventaire qui confirme son absence. Cela ne prouve ni qu’elle n’existe pas, ni une fraude.',
                'graphic_forgery' => 'Une altération visible du document : ajout, rature, retouche ou signature matériellement incohérente. Une date suspecte ou une contradiction de texte ne suffit pas à elle seule.',
                'metadata_inconsistency' => 'Une donnée technique du fichier contredit le contexte établi. Une date de numérisation postérieure ne prouve pas que le document lui-même est faux.',
            ],
        ]);
    }

    #[Route('/enquete/{token}/rapport', name: 'investigation_submit', requirements: ['token' => '[a-f0-9]{48}'], methods: ['POST'])]
    public function submit(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        ScoringService $scoringService,
        CaseResultSummaryService $resultSummary,
    ): Response {
        $investigation = $this->findInvestigation($token, $request, $entityManager, false);

        if ($investigation->getStatus() === 'submitted') {
            return $this->redirectToRoute('investigation_result', ['token' => $token]);
        }

        if (!$this->isCsrfTokenValid('submit-report-' . $token, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $conclusion = $request->request->get('conclusion');
        $allowedConclusions = ['authentic', 'fraud_confirmed', 'inconclusive'];

        if (!is_string($conclusion) || !in_array($conclusion, $allowedConclusions, true)) {
            $this->addFlash('report_error', 'Choisissez une conclusion pour soumettre le rapport.');

            return $this->redirectToRoute('investigation_show', ['token' => $token]);
        }

        $scoreBreakdown = $scoringService->evaluate($investigation, $conclusion);
        $submission = new CaseSubmission(
            $investigation,
            $conclusion,
            $scoreBreakdown['score'],
            $scoreBreakdown,
        );

        $entityManager->persist($submission);
        $entityManager->flush();

        if (!$investigation->getPlayer() instanceof User) {
            $bestScores = $request->getSession()->get('scrutio.case_best_scores', []);
            if (!is_array($bestScores)) {
                $bestScores = [];
            }
            $caseSlug = $investigation->getCaseFile()->getSlug();
            $bestScores[$caseSlug] = max((int) ($bestScores[$caseSlug] ?? 0), $scoreBreakdown['score']);
            $request->getSession()->set('scrutio.case_best_scores', $bestScores);
            $previousResults = $request->getSession()->get('scrutio.case_results', []);
            $request->getSession()->set('scrutio.case_results', $resultSummary->summarize(
                [$submission],
                is_array($previousResults) ? $previousResults : [],
            ));
        }

        return $this->redirectToRoute('investigation_result', ['token' => $token]);
    }

    #[Route('/enquete/{token}/resultat', name: 'investigation_result', requirements: ['token' => '[a-f0-9]{48}'], methods: ['GET'])]
    public function result(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        ScoringService $scoringService,
    ): Response {
        $investigation = $this->findInvestigation($token, $request, $entityManager, false);
        $submission = $investigation->getSubmission();

        if ($investigation->getStatus() !== 'submitted' || !$submission instanceof CaseSubmission) {
            throw $this->createNotFoundException();
        }

        $score = $submission->getScoreBreakdown();
        if (isset($score['submittedAnomalies'])) {
            $score = $scoringService->evaluate($investigation, $submission->getConclusion());
        } else {
            if (!isset($score['maximum'])) {
                $score['maximum'] = 100;
                $score['minimumPassingScore'] = 50;
                $score['passed'] = ($score['conclusion']['correct'] ?? false) && $submission->getScore() >= 50;
            }

            $score['submittedAnomalies'] = $scoringService
                ->evaluate($investigation, $submission->getConclusion())['submittedAnomalies'];
        }
        $score['score'] ??= $submission->getScore();
        if (!$score['passed']) {
            foreach ($score['submittedAnomalies'] as &$anomaly) {
                $anomaly['explanation'] = null;
            }
            unset($anomaly);
        }
        $bestScore = $this->bestScoreFor($investigation, $request, $entityManager, $score['score']);

        $series = $entityManager->getRepository(CaseFile::class)->findBy(
            ['pack' => $investigation->getCaseFile()->getPack()],
            ['displayOrder' => 'ASC'],
        );
        $nextCase = $this->caseAccessService->nextCase($investigation->getCaseFile(), $series);
        $currentUser = $this->getUser();
        $player = $currentUser instanceof User ? $currentUser : null;
        $nextCaseData = $nextCase === null ? null : [
            'slug' => $nextCase->getSlug(),
            'title' => $nextCase->getTitle(),
            'unlocked' => $this->caseAccessService->canAccess($player, $nextCase)
                && $this->caseAccessService->isUnlocked(
                    $nextCase,
                    $series,
                    $this->progressionSummary->forPlayer($player, $request, $entityManager),
                ),
        ];

        $conclusionLabels = [
            'authentic' => 'Authentique',
            'fraud_confirmed' => 'Fraude avérée',
            'inconclusive' => 'Inconcluant',
        ];
        $viewData = [
            'case' => $investigation->getCaseFile(),
            'investigation' => $investigation,
            'submission' => $submission,
            'score' => $score,
            'bestScore' => $bestScore,
            'nextCase' => $nextCaseData,
            'conclusionLabels' => $conclusionLabels,
            'anomalyLabels' => [
                'anachronism' => 'Anachronisme matériel',
                'cross_document_inconsistency' => 'Incohérence croisée',
                'missing_evidence' => 'Pièce manquante',
                'graphic_forgery' => 'Falsification graphique',
                'metadata_inconsistency' => 'Incohérence système / métadonnées',
            ],
        ];

        if ($score['passed']) {
            $solution = $investigation->getCaseFile()->getSolution()?->getSchema() ?? [];
            $viewData += [
                'expectedConclusion' => $conclusionLabels[$solution['expectedConclusion'] ?? ''] ?? 'À déterminer',
                'officialFindings' => array_values(array_filter(array_map(
                    static fn(array $finding): ?string => isset($finding['explanation']) && is_string($finding['explanation'])
                        ? $finding['explanation']
                        : null,
                    is_array($solution['expectedAnomalies'] ?? null) ? $solution['expectedAnomalies'] : [],
                ))),
                'metadataCaution' => $solution['metadataCaution'] ?? null,
            ];
            $correctionNotes = is_array($solution['correctionNotes'] ?? null) ? $solution['correctionNotes'] : [];
            $viewData['officialFindings'] = array_merge(
                $viewData['officialFindings'],
                array_values(array_filter($correctionNotes, static fn($note): bool => is_string($note))),
            );
        }

        return $this->render('investigation/result.html.twig', $viewData);
    }

    private function bestScoreFor(
        InvestigationSession $investigation,
        Request $request,
        EntityManagerInterface $entityManager,
        int $currentScore,
    ): int {
        $player = $investigation->getPlayer();
        if ($player instanceof User) {
            $bestScore = $entityManager->createQueryBuilder()
                ->select('MAX(submission.score)')
                ->from(CaseSubmission::class, 'submission')
                ->join('submission.investigationSession', 'attempt')
                ->where('attempt.caseFile = :caseFile')
                ->andWhere('attempt.player = :player')
                ->setParameter('caseFile', $investigation->getCaseFile())
                ->setParameter('player', $player)
                ->getQuery()
                ->getSingleScalarResult();

            return max($currentScore, (int) ($bestScore ?? 0));
        }

        $bestScores = $request->getSession()->get('scrutio.case_best_scores', []);
        $bestForCase = is_array($bestScores)
            ? (int) ($bestScores[$investigation->getCaseFile()->getSlug()] ?? 0)
            : 0;

        return max($currentScore, $bestForCase);
    }

    #[Route('/enquete/{token}/documents/{documentId}/metadonnees', name: 'investigation_metadata', requirements: ['token' => '[a-f0-9]{48}', 'documentId' => '\\d+'], methods: ['GET'])]
    public function metadata(string $token, int $documentId, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $investigation = $this->findInvestigation($token, $request, $entityManager);
        $document = $this->findDocument($investigation, $documentId, $entityManager);
        $metadata = $document->getMetadata()->toArray();
        usort($metadata, static fn(DocumentMetadata $left, DocumentMetadata $right): int => $left->getDisplayOrder() <=> $right->getDisplayOrder());

        return $this->json(['metadata' => array_map(static fn(DocumentMetadata $item): array => [
            'key' => $item->getMetadataKey(),
            'value' => $item->getMetadataValue(),
        ], $metadata)]);
    }

    #[Route('/enquete/{token}/anomalies', name: 'investigation_add_anomaly', requirements: ['token' => '[a-f0-9]{48}'], methods: ['POST'])]
    public function addAnomaly(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): JsonResponse {
        $investigation = $this->findInvestigation($token, $request, $entityManager);

        if (!$this->isCsrfTokenValid('mark-anomaly-' . $token, (string) $request->headers->get('X-CSRF-Token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['error' => 'Corps JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $type = $payload['type'] ?? null;
        $evidencePayload = $payload['evidence'] ?? null;
        if (!is_string($type) || !in_array($type, self::ANOMALY_TYPES, true) || !is_array($evidencePayload)) {
            return $this->json(['error' => 'Type ou éléments de preuve invalides.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (count($evidencePayload) > 10) {
            return $this->json(['error' => 'Le repérage ne peut pas contenir plus de 10 éléments.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $requirements = $this->anomalyRequirements($type);
        if (count($evidencePayload) < $requirements['minimumSelections']) {
            return $this->json([
                'error' => sprintf('Ce type de repérage demande au moins %d élément(s) de preuve.', $requirements['minimumSelections']),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $evidence = [];
        $documentIds = [];
        $uniqueElements = [];
        foreach (array_values($evidencePayload) as $index => $selection) {
            if (!is_array($selection)) {
                return $this->json(['error' => 'Un élément de preuve est invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $documentId = filter_var($selection['documentId'] ?? null, FILTER_VALIDATE_INT);
            $selectedText = isset($selection['selectedText']) && is_string($selection['selectedText'])
                ? trim($selection['selectedText'])
                : '';

            if (!is_int($documentId) || $selectedText === '' || strlen($selectedText) > 1000) {
                return $this->json(['error' => 'Chaque élément doit désigner une pièce et un passage valides.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $elementKey = $documentId . ':' . $selectedText;
            if (isset($uniqueElements[$elementKey])) {
                return $this->json(['error' => 'Le même passage ne peut pas être ajouté deux fois.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $uniqueElements[$elementKey] = true;

            $document = $this->findDocument($investigation, $documentId, $entityManager);
            $documentIds[$documentId] = true;
            $evidence[] = ['document' => $document, 'selectedText' => $selectedText, 'position' => $index + 1];
        }

        if (count($documentIds) < $requirements['minimumDistinctDocuments']) {
            return $this->json([
                'error' => sprintf('Ce type de repérage demande des éléments provenant d’au moins %d documents distincts.', $requirements['minimumDistinctDocuments']),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $anomaly = new PlayerAnomaly($investigation, $type, null);
        foreach ($evidence as $selection) {
            new PlayerEvidenceSelection($anomaly, $selection['document'], $selection['selectedText'], $selection['position']);
        }
        $entityManager->persist($anomaly);
        $entityManager->flush();

        $anomalyId = $anomaly->getId();

        return $this->json([
            'id' => $anomalyId,
            'deleteUrl' => $this->generateUrl('investigation_remove_anomaly', [
                'token' => $token,
                'anomalyId' => $anomalyId,
            ]),
            'deleteCsrfToken' => $csrfTokenManager->getToken('remove-anomaly-' . $token . '-' . $anomalyId)->getValue(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/enquete/{token}/anomalies/{anomalyId}/supprimer', name: 'investigation_remove_anomaly', requirements: ['token' => '[a-f0-9]{48}', 'anomalyId' => '\\d+'], methods: ['POST'])]
    public function removeAnomaly(
        string $token,
        int $anomalyId,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $investigation = $this->findInvestigation($token, $request, $entityManager);

        if (!$this->isCsrfTokenValid(
            'remove-anomaly-' . $token . '-' . $anomalyId,
            (string) $request->headers->get('X-CSRF-Token'),
        )) {
            throw $this->createAccessDeniedException();
        }

        $anomaly = $entityManager->getRepository(PlayerAnomaly::class)->findOneBy([
            'id' => $anomalyId,
            'investigationSession' => $investigation,
        ]);
        if (!$anomaly instanceof PlayerAnomaly) {
            throw $this->createNotFoundException();
        }

        $investigation->removeAnomaly($anomaly);
        $entityManager->remove($anomaly);
        $entityManager->flush();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function anomalyRequirements(string $type): array
    {
        return match ($type) {
            'cross_document_inconsistency', 'missing_evidence' => ['minimumSelections' => 2, 'minimumDistinctDocuments' => 2],
            default => ['minimumSelections' => 1, 'minimumDistinctDocuments' => 1],
        };
    }

    private function findInvestigation(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        bool $requireInProgress = true,
    ): InvestigationSession {
        $investigation = $entityManager->getRepository(InvestigationSession::class)->findOneBy(['publicToken' => $token]);
        if (!$investigation instanceof InvestigationSession) {
            throw $this->createNotFoundException();
        }

        $tokensByCase = $request->getSession()->get('scrutio.case_sessions', []);
        $sessionOwnsToken = is_array($tokensByCase) && in_array($token, $tokensByCase, true);
        $currentUser = $this->getUser();
        $userOwnsInvestigation = $currentUser instanceof User
            && $investigation->getPlayer()?->getId() === $currentUser->getId();
        $investigationHasOwner = $investigation->getPlayer() instanceof User;

        if (
            ($investigationHasOwner && !$userOwnsInvestigation)
            || (!$investigationHasOwner && !$sessionOwnsToken)
        ) {
            throw $this->createNotFoundException();
        }

        $currentUser = $this->getUser();
        $user = $currentUser instanceof User ? $currentUser : null;
        if (!$this->caseAccessService->canAccess($user, $investigation->getCaseFile())) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCaseUnlocked($investigation->getCaseFile(), $user, $request, $entityManager)) {
            throw $this->createAccessDeniedException('Validez les expertises précédentes pour accéder à cette affaire.');
        }

        if (
            $requireInProgress && $investigation->getStatus() !== 'in_progress'
        ) {
            throw $this->createNotFoundException();
        }

        return $investigation;
    }

    private function isCaseUnlocked(CaseFile $case, ?User $user, Request $request, EntityManagerInterface $entityManager): bool
    {
        return $this->caseAccessService->isUnlocked(
            $case,
            $entityManager->getRepository(CaseFile::class)->findBy(['pack' => $case->getPack()]),
            $this->progressionSummary->forPlayer($user, $request, $entityManager),
        );
    }

    private function findDocument(InvestigationSession $investigation, int $documentId, EntityManagerInterface $entityManager): EvidenceDocument
    {
        $document = $entityManager->getRepository(EvidenceDocument::class)->find($documentId);

        if (!$document instanceof EvidenceDocument || $document->getCaseFile()->getId() !== $investigation->getCaseFile()->getId()) {
            throw $this->createNotFoundException();
        }

        return $document;
    }
}
