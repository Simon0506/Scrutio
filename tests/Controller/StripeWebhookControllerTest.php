<?php

namespace App\Tests\Controller;

use App\Controller\StripeWebhookController;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class StripeWebhookControllerTest extends TestCase
{
    private const WEBHOOK_SECRET = 'whsec_test_scrutio';

    public function testRejectsWebhookWithInvalidSignature(): void
    {
        $request = Request::create(
            '/webhooks/stripe',
            'POST',
            server: ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=invalid'],
            content: '{}',
        );
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $response = (new StripeWebhookController(self::WEBHOOK_SECRET))($request, $entityManager);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testAcceptsValidSignatureForAnUnrelatedEventWithoutProcessingIt(): void
    {
        $payload = json_encode([
            'id' => 'evt_test_unrelated',
            'object' => 'event',
            'api_version' => '2025-10-29.clover',
            'created' => time(),
            'data' => ['object' => ['object' => 'customer']],
            'livemode' => false,
            'pending_webhooks' => 1,
            'request' => ['id' => null, 'idempotency_key' => null],
            'type' => 'customer.created',
        ], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, self::WEBHOOK_SECRET);
        $request = Request::create(
            '/webhooks/stripe',
            'POST',
            server: ['HTTP_STRIPE_SIGNATURE' => sprintf('t=%d,v1=%s', $timestamp, $signature)],
            content: $payload,
        );
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $response = (new StripeWebhookController(self::WEBHOOK_SECRET))($request, $entityManager);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
