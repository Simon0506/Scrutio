<?php

namespace App\Tests\Entity;

use App\Entity\ContentPack;
use App\Entity\PackPurchase;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class PackPurchaseTest extends TestCase
{
    public function testPurchaseSnapshotsPackPriceAndCurrency(): void
    {
        $pack = new ContentPack('Pack test', 'pack-test', 'Description.', 499);
        $purchase = new PackPurchase(new User('analyste@example.test'), $pack, 'stripe');

        self::assertSame(499, $purchase->getAmountInCents());
        self::assertSame('EUR', $purchase->getCurrency());
        self::assertSame(PackPurchase::STATUS_PENDING, $purchase->getStatus());
    }

    public function testPaidTransitionIsIdempotentForSamePayment(): void
    {
        $purchase = new PackPurchase(
            new User('analyste@example.test'),
            new ContentPack('Pack test', 'pack-test-paid', 'Description.', 499),
            'stripe',
        );

        $purchase->markPaid('pi_test_123');
        $purchase->markPaid('pi_test_123');

        self::assertSame(PackPurchase::STATUS_PAID, $purchase->getStatus());
        self::assertSame('pi_test_123', $purchase->getProviderPaymentId());
    }

    public function testPaidTransitionRejectsDifferentPaymentReference(): void
    {
        $purchase = new PackPurchase(
            new User('analyste@example.test'),
            new ContentPack('Pack test', 'pack-test-conflict', 'Description.', 499),
            'stripe',
        );
        $purchase->markPaid('pi_test_123');

        $this->expectException(\LogicException::class);
        $purchase->markPaid('pi_other_456');
    }
}
