<?php

namespace App\Entity;

use App\Entity\ContentPack;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pack_purchase', options: ['engine' => 'InnoDB'])]
#[ORM\UniqueConstraint(name: 'uniq_pack_purchase_provider_checkout', columns: ['provider_checkout_id'])]
#[ORM\UniqueConstraint(name: 'uniq_pack_purchase_provider_payment', columns: ['provider_payment_id'])]
class PackPurchase
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ContentPack $pack;

    #[ORM\Column]
    private int $amountInCents;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(length: 16)]
    private string $provider;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerCheckoutId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerPaymentId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    public function __construct(User $user, ContentPack $pack, string $provider)
    {
        if (!in_array($provider, ['stripe', 'paypal'], true)) {
            throw new \InvalidArgumentException('Unsupported payment provider.');
        }

        $this->user = $user;
        $this->pack = $pack;
        $this->amountInCents = $pack->getPriceAmountInCents();
        $this->currency = $pack->getCurrency();
        $this->provider = $provider;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPack(): ContentPack
    {
        return $this->pack;
    }

    public function getAmountInCents(): int
    {
        return $this->amountInCents;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getProviderCheckoutId(): ?string
    {
        return $this->providerCheckoutId;
    }

    public function getProviderPaymentId(): ?string
    {
        return $this->providerPaymentId;
    }

    public function setProviderCheckoutId(string $providerCheckoutId): void
    {
        if ($this->status !== self::STATUS_PENDING || $providerCheckoutId === '') {
            throw new \LogicException('A checkout reference can only be set on a pending purchase.');
        }

        $this->providerCheckoutId = $providerCheckoutId;
    }

    public function markPaid(string $providerPaymentId): void
    {
        if ($providerPaymentId === '') {
            throw new \InvalidArgumentException('A provider payment reference is required.');
        }

        if ($this->status === self::STATUS_PAID && $this->providerPaymentId === $providerPaymentId) {
            return;
        }

        if ($this->status !== self::STATUS_PENDING) {
            throw new \LogicException('Only a pending purchase can be marked as paid.');
        }

        $this->providerPaymentId = $providerPaymentId;
        $this->status = self::STATUS_PAID;
        $this->paidAt = new \DateTimeImmutable();
    }

    public function markFailed(): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \LogicException('Only a pending purchase can be marked as failed.');
        }

        $this->status = self::STATUS_FAILED;
    }
}
