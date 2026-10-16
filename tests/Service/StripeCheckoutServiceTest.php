<?php

namespace App\Tests\Service;

use App\Service\StripeCheckoutService;
use PHPUnit\Framework\TestCase;

final class StripeCheckoutServiceTest extends TestCase
{
    public function testCheckoutIsUnavailableUntilBothStripeSecretsAreConfigured(): void
    {
        self::assertFalse((new StripeCheckoutService('', ''))->isConfigured());
        self::assertFalse((new StripeCheckoutService('sk_test_value', ''))->isConfigured());
        self::assertFalse((new StripeCheckoutService('', 'whsec_test_value'))->isConfigured());
        self::assertTrue((new StripeCheckoutService('sk_test_value', 'whsec_test_value'))->isConfigured());
    }
}
