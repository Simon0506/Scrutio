<?php

namespace App\Controller;

use App\Entity\ContentPack;
use App\Entity\PackPurchase;
use App\Entity\User;
use App\Service\StripeCheckoutService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PackPurchaseController extends AbstractController
{
    #[Route('/packs/{slug}/acheter/carte', name: 'pack_purchase_stripe', methods: ['POST'])]
    public function checkout(
        string $slug,
        Request $request,
        EntityManagerInterface $entityManager,
        StripeCheckoutService $stripeCheckout,
        UrlGeneratorInterface $urlGenerator,
        LoggerInterface $logger,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('buy-pack-' . $slug, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $pack = $entityManager->getRepository(ContentPack::class)->findOneBy(['slug' => $slug]);
        if (!$pack instanceof ContentPack) {
            throw $this->createNotFoundException();
        }

        if ($currentUser->hasPackAccess($pack)) {
            return $this->redirectToRoute('case_index');
        }

        $purchase = $entityManager->getRepository(PackPurchase::class)->findOneBy(
            ['user' => $currentUser, 'pack' => $pack, 'provider' => 'stripe', 'status' => PackPurchase::STATUS_PENDING],
            ['createdAt' => 'DESC'],
        );
        if (!$purchase instanceof PackPurchase) {
            $purchase = new PackPurchase($currentUser, $pack, 'stripe');
            $entityManager->persist($purchase);
            $entityManager->flush();
        }

        $successUrl = $urlGenerator->generate(
            'pack_purchase_return',
            ['id' => $purchase->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ) . '?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $urlGenerator->generate(
            'pack_purchase_cancelled',
            ['id' => $purchase->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        try {
            $session = $stripeCheckout->createCheckoutSession($purchase, $successUrl, $cancelUrl);
            $purchase->setProviderCheckoutId($session->id);
            $entityManager->flush();

            return new RedirectResponse($session->url, Response::HTTP_SEE_OTHER);
        } catch (\Throwable $exception) {
            $logger->error('Stripe checkout creation failed.', [
                'purchase_id' => $purchase->getId(),
                'exception' => $exception,
            ]);
            $this->addFlash('payment_error', 'Le paiement par carte est momentanément indisponible. Aucun débit n’a été effectué.');

            return $this->redirectToRoute('case_index');
        }
    }

    #[Route('/achats/{id}/retour', name: 'pack_purchase_return', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function returned(int $id, EntityManagerInterface $entityManager): Response
    {
        $purchase = $this->findOwnedPurchase($id, $entityManager);

        return $this->render('pack/payment_result.html.twig', [
            'purchase' => $purchase,
            'cancelled' => false,
        ]);
    }

    #[Route('/achats/{id}/annulation', name: 'pack_purchase_cancelled', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function cancelled(int $id, EntityManagerInterface $entityManager): Response
    {
        $purchase = $this->findOwnedPurchase($id, $entityManager);

        return $this->render('pack/payment_result.html.twig', [
            'purchase' => $purchase,
            'cancelled' => true,
        ]);
    }

    private function findOwnedPurchase(int $id, EntityManagerInterface $entityManager): PackPurchase
    {
        $currentUser = $this->getUser();
        $purchase = $entityManager->getRepository(PackPurchase::class)->find($id);

        if (
            !$currentUser instanceof User
            || !$purchase instanceof PackPurchase
            || $purchase->getUser()->getId() !== $currentUser->getId()
        ) {
            throw $this->createNotFoundException();
        }

        return $purchase;
    }
}
