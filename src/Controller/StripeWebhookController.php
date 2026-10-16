<?php

namespace App\Controller;

use App\Entity\PackPurchase;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StripeWebhookController
{
    public function __construct(
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')]
        private readonly string $webhookSecret,
    ) {}

    #[Route('/webhooks/stripe', name: 'stripe_webhook', methods: ['POST'])]
    public function __invoke(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->webhookSecret === '') {
            return new JsonResponse(['error' => 'Webhook is not configured.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $signature = $request->headers->get('Stripe-Signature');
        if (!is_string($signature)) {
            return new JsonResponse(['error' => 'Missing Stripe signature.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), $signature, $this->webhookSecret);
        } catch (\UnexpectedValueException | \Stripe\Exception\SignatureVerificationException) {
            return new JsonResponse(['error' => 'Invalid Stripe webhook.'], Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $session = $event->data->object;
        $metadata = $session->metadata;
        $purchaseId = filter_var($metadata->purchase_id ?? null, FILTER_VALIDATE_INT);
        $purchase = is_int($purchaseId) ? $entityManager->getRepository(PackPurchase::class)->find($purchaseId) : null;

        if (!$purchase instanceof PackPurchase || $purchase->getProvider() !== 'stripe') {
            return new JsonResponse(['error' => 'Unknown purchase.'], Response::HTTP_BAD_REQUEST);
        }

        $sessionId = $session->id ?? null;
        $clientUserId = $session->client_reference_id ?? null;
        $metadataPackId = filter_var($metadata->pack_id ?? null, FILTER_VALIDATE_INT);
        $currency = strtolower((string) ($session->currency ?? ''));
        $paymentReference = $session->payment_intent ?? null;

        if (
            !is_string($sessionId)
            || ($purchase->getProviderCheckoutId() !== null && $purchase->getProviderCheckoutId() !== $sessionId)
            || $clientUserId !== (string) $purchase->getUser()->getId()
            || $metadataPackId !== $purchase->getPack()->getId()
            || ($session->amount_total ?? null) !== $purchase->getAmountInCents()
            || $currency !== strtolower($purchase->getCurrency())
        ) {
            return new JsonResponse(['error' => 'Checkout does not match the purchase.'], Response::HTTP_BAD_REQUEST);
        }

        if (($session->payment_status ?? null) !== 'paid') {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        if (!is_string($paymentReference) || $paymentReference === '') {
            $paymentReference = $sessionId;
        }

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            if ($purchase->getStatus() !== PackPurchase::STATUS_PAID) {
                if ($purchase->getProviderCheckoutId() === null) {
                    $purchase->setProviderCheckoutId($sessionId);
                }
                $purchase->markPaid($paymentReference);
                $purchase->getUser()->grantPackAccess($purchase->getPack());
                $entityManager->flush();
            } elseif ($purchase->getProviderPaymentId() !== $paymentReference) {
                throw new \LogicException('A paid purchase received a conflicting payment reference.');
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
