<?php

namespace App\Controller;

use App\Entity\CaseFile;
use App\Entity\User;
use App\Service\CaseAccessService;
use App\Service\CaseResultSummaryService;
use App\Service\StripeCheckoutService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CaseController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('case/home.html.twig');
    }

    #[Route('/affaires', name: 'case_index', methods: ['GET'])]
    public function index(
        EntityManagerInterface $entityManager,
        CaseAccessService $caseAccessService,
        StripeCheckoutService $stripeCheckout,
        Request $request,
        CaseResultSummaryService $resultSummary,
    ): Response {
        $cases = $entityManager->getRepository(CaseFile::class)->findBy([], ['displayOrder' => 'ASC']);
        usort(
            $cases,
            static fn(CaseFile $left, CaseFile $right): int => (($left->getPack()?->getDisplayOrder() ?? 0) <=> ($right->getPack()?->getDisplayOrder() ?? 0))
                ?: ($left->getDisplayOrder() <=> $right->getDisplayOrder())
        );
        $currentUser = $this->getUser();
        $user = $currentUser instanceof User ? $currentUser : null;

        $summaries = $resultSummary->forPlayer($user, $request, $entityManager);
        $journeys = [];
        foreach ($cases as $case) {
            $pack = $case->getPack();
            $key = $pack?->getSlug() ?? 'free';
            $journeys[$key] ??= [
                'slug' => $key,
                'title' => $pack?->getTitle() ?? 'Les premières expertises',
                'description' => $pack?->getDescription() ?? 'Trois affaires pour aiguiser votre regard, des premiers indices aux dossiers les plus exigeants.',
                'owned' => $caseAccessService->canAccess($user, $case),
                'free' => $pack === null,
                'priceAmountInCents' => $pack?->getPriceAmountInCents(),
                'currency' => $pack?->getCurrency(),
                'completed' => 0,
                'cases' => [],
            ];
            $unlocked = $caseAccessService->isUnlocked($case, $cases, $summaries);
            $caseCard = [
                'title' => $case->getTitle(),
                'slug' => $case->getSlug(),
                'synopsis' => $case->getSynopsis(),
                'difficulty' => $case->getDifficulty(),
                'documentCount' => $case->getDocuments()->count(),
                'packTitle' => $case->getPack()?->getTitle(),
                'result' => $summaries[$case->getSlug()] ?? null,
                'unlocked' => $journeys[$key]['owned'] && $unlocked,
                'previousTitle' => $journeys[$key]['cases'] !== [] ? $journeys[$key]['cases'][array_key_last($journeys[$key]['cases'])]['title'] : null,
            ];
            $journeys[$key]['cases'][] = $caseCard;
            $journeys[$key]['completed'] += ($caseCard['result']['passed'] ?? false) ? 1 : 0;
        }

        return $this->render('case/index.html.twig', [
            'journeys' => array_values($journeys),
            'stripeConfigured' => $stripeCheckout->isConfigured(),
        ]);
    }

    #[Route('/affaires/{slug}', name: 'case_show', methods: ['GET'])]
    public function show(string $slug, EntityManagerInterface $entityManager, CaseAccessService $caseAccessService, Request $request, CaseResultSummaryService $resultSummary): Response
    {
        $case = $entityManager->getRepository(CaseFile::class)->findOneBy(['slug' => $slug]);
        $currentUser = $this->getUser();
        $user = $currentUser instanceof User ? $currentUser : null;

        if (!$case instanceof CaseFile || !$caseAccessService->canAccess($user, $case)) {
            throw $this->createNotFoundException();
        }

        if (!$caseAccessService->isUnlocked(
            $case,
            $entityManager->getRepository(CaseFile::class)->findBy(['pack' => $case->getPack()]),
            $resultSummary->forPlayer($user, $request, $entityManager),
        )) {
            $this->addFlash('progression', 'Validez les expertises précédentes pour ouvrir cette affaire.');

            return $this->redirectToRoute('case_index');
        }

        $documents = $case->getDocuments()->toArray();
        usort($documents, static fn($left, $right): int => $left->getDisplayOrder() <=> $right->getDisplayOrder());

        $publicCase = [
            'title' => $case->getTitle(),
            'slug' => $case->getSlug(),
            'synopsis' => $case->getSynopsis(),
            'difficulty' => $case->getDifficulty(),
            'packTitle' => $case->getPack()?->getTitle(),
        ];
        $publicDocuments = array_map(static fn($document): array => [
            'title' => $document->getTitle(),
        ], $documents);

        return $this->render('case/show.html.twig', [
            'case' => $publicCase,
            'documents' => $publicDocuments,
        ]);
    }
}
