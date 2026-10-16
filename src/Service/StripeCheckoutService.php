<?php

namespace App\Service;

use App\Entity\PackPurchase;
use Stripe\Checkout\Session;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class StripeCheckoutService
{
    public function __construct(
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private readonly string $secretKey,
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')]
        private readonly string $webhookSecret,
    ) {}

    public function isConfigured(): bool
    {
        return $this->secretKey !== '' && $this->webhookSecret !== '';
    }

    public function createCheckoutSession(PackPurchase $purchase, string $successUrl, string $cancelUrl): Session
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Stripe checkout and webhook are not configured.');
        }

        if ($purchase->getId() === null || $purchase->getUser()->getId() === null || $purchase->getPack()->getId() === null) {
            throw new \LogicException('A persisted purchase is required to create a Stripe checkout session.');
        }

        $session = (new StripeClient($this->secretKey))->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($purchase->getCurrency()),
                    'unit_amount' => $purchase->getAmountInCents(),
                    'product_data' => [
                        'name' => $purchase->getPack()->getTitle(),
                        'description' => $purchase->getPack()->getDescription(),
                    ],
                ],
                'quantity' => 1,
            ]],
            'client_reference_id' => (string) $purchase->getUser()->getId(),
            'metadata' => [
                'purchase_id' => (string) $purchase->getId(),
                'pack_id' => (string) $purchase->getPack()->getId(),
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ], [
            'idempotency_key' => 'scrutio-pack-checkout-v2-' . $purchase->getId(),
        ]);

        if (!is_string($session->id) || !is_string($session->url)) {
            throw new \RuntimeException('Stripe did not return a checkout URL.');
        }

        return $session;
    }
}
