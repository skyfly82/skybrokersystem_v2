<?php

declare(strict_types=1);

namespace App\Domain\Payment\Entity;

use App\Entity\User;
use App\Entity\SystemUser;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: \App\Domain\Payment\Repository\PaymentRepository::class)]
#[ORM\Table(name: 'v2_payments')]
#[ORM\Index(columns: ['status'], name: 'idx_payment_status')]
#[ORM\Index(columns: ['payment_method'], name: 'idx_payment_method')]
#[ORM\Index(columns: ['created_at'], name: 'idx_payment_created')]
class Payment
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    public const METHOD_PAYNOW = 'paynow';
    public const METHOD_CREDIT = 'credit';
    public const METHOD_WALLET = 'wallet';
    public const METHOD_SIMULATOR = 'simulator';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SystemUser::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 100, unique: true)]
    private string $paymentId;

    #[ORM\Column(length: 50)]
    private string $paymentMethod;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $amount;

    #[ORM\Column(length: 3)]
    private string $currency = 'PLN';

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalTransactionId = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $gatewayResponse = null;

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
        $this->paymentId = $this->generatePaymentId();
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

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
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

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
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
            self::STATUS_FAILED, self::STATUS_CANCELLED => $this->failedAt = new \DateTimeImmutable(),
            default => null
        };

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

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getGatewayResponse(): ?array
    {
        return $this->gatewayResponse;
    }

    public function setGatewayResponse(?array $gatewayResponse): self
    {
        $this->gatewayResponse = $gatewayResponse;
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
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_CANCELLED]);
    }

    public function canBeProcessed(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    // Payment method checking
    public function isPayNow(): bool
    {
        return $this->paymentMethod === self::METHOD_PAYNOW;
    }

    public function isCredit(): bool
    {
        return $this->paymentMethod === self::METHOD_CREDIT;
    }

    public function isWallet(): bool
    {
        return $this->paymentMethod === self::METHOD_WALLET;
    }

    public function isSimulator(): bool
    {
        return $this->paymentMethod === self::METHOD_SIMULATOR;
    }

    private function generatePaymentId(): string
    {
        return 'PAY_' . strtoupper(bin2hex(random_bytes(8))) . '_' . time();
    }
}