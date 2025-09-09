<?php

declare(strict_types=1);

namespace App\Domain\Payment\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: \App\Domain\Payment\Repository\WalletTopUpRepository::class)]
#[ORM\Table(name: 'v2_wallet_top_ups')]
#[ORM\Index(columns: ['wallet_id'], name: 'idx_wallet_top_up_wallet')]
#[ORM\Index(columns: ['payment_id'], name: 'idx_wallet_top_up_payment')]
#[ORM\Index(columns: ['status'], name: 'idx_wallet_top_up_status')]
#[ORM\Index(columns: ['payment_method'], name: 'idx_wallet_top_up_method')]
#[ORM\Index(columns: ['created_at'], name: 'idx_wallet_top_up_created')]
class WalletTopUp
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const PAYMENT_METHOD_PAYNOW = 'paynow';
    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';
    public const PAYMENT_METHOD_CREDIT_CARD = 'credit_card';
    public const PAYMENT_METHOD_BLIK = 'blik';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Wallet::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Wallet $wallet;

    #[ORM\ManyToOne(targetEntity: Payment::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Payment $payment = null;

    #[ORM\ManyToOne(targetEntity: WalletTransaction::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?WalletTransaction $walletTransaction = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $topUpId;

    #[ORM\Column(length: 50)]
    private string $paymentMethod;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $amount;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $feeAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $netAmount = null;

    #[ORM\Column(length: 3)]
    private string $currency = 'PLN';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalTransactionId = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $paymentUrl = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $paymentGatewayData = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $failedAt = null;

    public function __construct()
    {
        $this->topUpId = $this->generateTopUpId();
        $this->createdAt = new \DateTimeImmutable();
        // Default expiry: 1 hour from creation
        $this->expiresAt = new \DateTimeImmutable('+1 hour');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWallet(): Wallet
    {
        return $this->wallet;
    }

    public function setWallet(Wallet $wallet): self
    {
        $this->wallet = $wallet;
        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): self
    {
        $this->payment = $payment;
        return $this;
    }

    public function getWalletTransaction(): ?WalletTransaction
    {
        return $this->walletTransaction;
    }

    public function setWalletTransaction(?WalletTransaction $walletTransaction): self
    {
        $this->walletTransaction = $walletTransaction;
        return $this;
    }

    public function getTopUpId(): string
    {
        return $this->topUpId;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $oldStatus = $this->status;
        $this->status = $status;

        // Set timestamps based on status change
        match ($status) {
            self::STATUS_PROCESSING => $this->processedAt = new \DateTimeImmutable(),
            self::STATUS_COMPLETED => $this->completedAt = new \DateTimeImmutable(),
            self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_EXPIRED => $this->failedAt = new \DateTimeImmutable(),
            default => null
        };

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        $this->calculateNetAmount();
        return $this;
    }

    public function getFeeAmount(): ?string
    {
        return $this->feeAmount;
    }

    public function setFeeAmount(?string $feeAmount): self
    {
        $this->feeAmount = $feeAmount;
        $this->calculateNetAmount();
        return $this;
    }

    public function getNetAmount(): ?string
    {
        return $this->netAmount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getExternalTransactionId(): ?string
    {
        return $this->externalTransactionId;
    }

    public function setExternalTransactionId(?string $externalTransactionId): self
    {
        $this->externalTransactionId = $externalTransactionId;
        return $this;
    }

    public function getPaymentUrl(): ?string
    {
        return $this->paymentUrl;
    }

    public function setPaymentUrl(?string $paymentUrl): self
    {
        $this->paymentUrl = $paymentUrl;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getPaymentGatewayData(): ?array
    {
        return $this->paymentGatewayData;
    }

    public function setPaymentGatewayData(?array $paymentGatewayData): self
    {
        $this->paymentGatewayData = $paymentGatewayData;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getFailedAt(): ?\DateTimeImmutable
    {
        return $this->failedAt;
    }

    // Status checking methods
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_EXPIRED]);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        if ($this->expiresAt === null || $this->isCompleted() || $this->isFailed()) {
            return false;
        }

        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function canBeProcessed(): bool
    {
        return $this->status === self::STATUS_PENDING && !$this->isExpired();
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]) && !$this->isExpired();
    }

    // Payment method checking
    public function isPayNow(): bool
    {
        return $this->paymentMethod === self::PAYMENT_METHOD_PAYNOW;
    }

    public function isBankTransfer(): bool
    {
        return $this->paymentMethod === self::PAYMENT_METHOD_BANK_TRANSFER;
    }

    public function isCreditCard(): bool
    {
        return $this->paymentMethod === self::PAYMENT_METHOD_CREDIT_CARD;
    }

    public function isBlik(): bool
    {
        return $this->paymentMethod === self::PAYMENT_METHOD_BLIK;
    }

    public function requiresPaymentUrl(): bool
    {
        return in_array($this->paymentMethod, [
            self::PAYMENT_METHOD_PAYNOW,
            self::PAYMENT_METHOD_CREDIT_CARD
        ]);
    }

    public function getTimeUntilExpiry(): ?int
    {
        if ($this->expiresAt === null || $this->isExpired()) {
            return null;
        }

        $now = new \DateTimeImmutable();
        return $this->expiresAt->getTimestamp() - $now->getTimestamp();
    }

    public function getProcessingTime(): ?int
    {
        if ($this->processedAt === null) {
            return null;
        }

        return $this->processedAt->getTimestamp() - $this->createdAt->getTimestamp();
    }

    public function getCompletionTime(): ?int
    {
        if ($this->completedAt === null) {
            return null;
        }

        return $this->completedAt->getTimestamp() - $this->createdAt->getTimestamp();
    }

    public function getTotalAmount(): string
    {
        $total = (float)$this->amount + (float)($this->feeAmount ?? '0.00');
        return number_format($total, 2, '.', '');
    }

    public function extendExpiry(int $minutes = 60): self
    {
        if (!$this->canBeProcessed()) {
            return $this;
        }

        $this->expiresAt = new \DateTimeImmutable(sprintf('+%d minutes', $minutes));
        return $this;
    }

    public function markAsExpired(): self
    {
        if ($this->canBeCancelled()) {
            $this->setStatus(self::STATUS_EXPIRED);
        }
        return $this;
    }

    private function calculateNetAmount(): void
    {
        $amount = (float)$this->amount;
        $fee = (float)($this->feeAmount ?? '0.00');
        $this->netAmount = number_format($amount - $fee, 2, '.', '');
    }

    private function generateTopUpId(): string
    {
        return 'TUP_' . strtoupper(bin2hex(random_bytes(8))) . '_' . time();
    }
}