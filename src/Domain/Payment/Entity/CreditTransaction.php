<?php

declare(strict_types=1);

namespace App\Domain\Payment\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: \App\Domain\Payment\Repository\CreditTransactionRepository::class)]
#[ORM\Table(name: 'v2_credit_transactions')]
#[ORM\Index(columns: ['credit_account_id'], name: 'idx_credit_transaction_account')]
#[ORM\Index(columns: ['payment_id'], name: 'idx_credit_transaction_payment')]
#[ORM\Index(columns: ['transaction_type'], name: 'idx_credit_transaction_type')]
#[ORM\Index(columns: ['status'], name: 'idx_credit_transaction_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_credit_transaction_created')]
#[ORM\Index(columns: ['due_date'], name: 'idx_credit_transaction_due_date')]
class CreditTransaction
{
    public const TYPE_AUTHORIZATION = 'authorization';
    public const TYPE_CHARGE = 'charge';
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_FEE = 'fee';
    public const TYPE_INTEREST = 'interest';

    public const STATUS_PENDING = 'pending';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_OVERDUE = 'overdue';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CreditAccount::class)]
    #[ORM\JoinColumn(nullable: false)]
    private CreditAccount $creditAccount;

    #[ORM\ManyToOne(targetEntity: Payment::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Payment $payment = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $transactionId;

    #[ORM\Column(length: 50)]
    private string $transactionType;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $amount;

    #[ORM\Column(length: 3)]
    private string $currency = 'PLN';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalReference = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $authorizedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $settledAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $failedAt = null;

    public function __construct()
    {
        $this->transactionId = $this->generateTransactionId();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreditAccount(): CreditAccount
    {
        return $this->creditAccount;
    }

    public function setCreditAccount(CreditAccount $creditAccount): self
    {
        $this->creditAccount = $creditAccount;
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

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getTransactionType(): string
    {
        return $this->transactionType;
    }

    public function setTransactionType(string $transactionType): self
    {
        $this->transactionType = $transactionType;
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
            self::STATUS_AUTHORIZED => $this->authorizedAt = new \DateTimeImmutable(),
            self::STATUS_SETTLED => $this->settledAt = new \DateTimeImmutable(),
            self::STATUS_FAILED, self::STATUS_CANCELLED => $this->failedAt = new \DateTimeImmutable(),
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function setExternalReference(?string $externalReference): self
    {
        $this->externalReference = $externalReference;
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

    public function getAuthorizedAt(): ?\DateTimeImmutable
    {
        return $this->authorizedAt;
    }

    public function getSettledAt(): ?\DateTimeImmutable
    {
        return $this->settledAt;
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

    public function isAuthorized(): bool
    {
        return $this->status === self::STATUS_AUTHORIZED;
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_SETTLED;
    }

    public function isFailed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_CANCELLED]);
    }

    public function isOverdue(): bool
    {
        if ($this->status === self::STATUS_OVERDUE) {
            return true;
        }

        if ($this->dueDate === null || $this->isSettled() || $this->isFailed()) {
            return false;
        }

        return $this->dueDate < new \DateTimeImmutable();
    }

    public function canBeSettled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_AUTHORIZED]);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_AUTHORIZED]);
    }

    // Transaction type checking
    public function isAuthorization(): bool
    {
        return $this->transactionType === self::TYPE_AUTHORIZATION;
    }

    public function isCharge(): bool
    {
        return $this->transactionType === self::TYPE_CHARGE;
    }

    public function isPayment(): bool
    {
        return $this->transactionType === self::TYPE_PAYMENT;
    }

    public function isRefund(): bool
    {
        return $this->transactionType === self::TYPE_REFUND;
    }

    public function isAdjustment(): bool
    {
        return $this->transactionType === self::TYPE_ADJUSTMENT;
    }

    public function isFee(): bool
    {
        return $this->transactionType === self::TYPE_FEE;
    }

    public function isInterest(): bool
    {
        return $this->transactionType === self::TYPE_INTEREST;
    }

    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        $now = new \DateTimeImmutable();
        return $now->diff($this->dueDate)->days;
    }

    private function generateTransactionId(): string
    {
        return 'CRT_' . strtoupper(bin2hex(random_bytes(8))) . '_' . time();
    }
}