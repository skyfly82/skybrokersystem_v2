<?php

declare(strict_types=1);

namespace App\Domain\Payment\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: \App\Domain\Payment\Repository\WalletTransactionRepository::class)]
#[ORM\Table(name: 'v2_wallet_transactions')]
#[ORM\Index(columns: ['wallet_id'], name: 'idx_wallet_transaction_wallet')]
#[ORM\Index(columns: ['payment_id'], name: 'idx_wallet_transaction_payment')]
#[ORM\Index(columns: ['transaction_type'], name: 'idx_wallet_transaction_type')]
#[ORM\Index(columns: ['status'], name: 'idx_wallet_transaction_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_wallet_transaction_created')]
#[ORM\Index(columns: ['source_wallet_id'], name: 'idx_wallet_transaction_source')]
#[ORM\Index(columns: ['destination_wallet_id'], name: 'idx_wallet_transaction_destination')]
class WalletTransaction
{
    public const TYPE_DEBIT = 'debit';
    public const TYPE_CREDIT = 'credit';
    public const TYPE_TRANSFER_OUT = 'transfer_out';
    public const TYPE_TRANSFER_IN = 'transfer_in';
    public const TYPE_TOP_UP = 'top_up';
    public const TYPE_WITHDRAWAL = 'withdrawal';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_FEE = 'fee';
    public const TYPE_REVERSAL = 'reversal';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REVERSED = 'reversed';

    public const CATEGORY_PAYMENT = 'payment';
    public const CATEGORY_TOP_UP = 'top_up';
    public const CATEGORY_TRANSFER = 'transfer';
    public const CATEGORY_WITHDRAWAL = 'withdrawal';
    public const CATEGORY_REFUND = 'refund';
    public const CATEGORY_ADJUSTMENT = 'adjustment';
    public const CATEGORY_FEE = 'fee';

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

    #[ORM\ManyToOne(targetEntity: Wallet::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Wallet $sourceWallet = null;

    #[ORM\ManyToOne(targetEntity: Wallet::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Wallet $destinationWallet = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $transactionId;

    #[ORM\Column(length: 50)]
    private string $transactionType;

    #[ORM\Column(length: 50)]
    private string $category;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $amount;

    #[ORM\Column(length: 3)]
    private string $currency = 'PLN';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $feeAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $balanceBefore = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $balanceAfter = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalReference = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalTransactionId = null;

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
        $this->transactionId = $this->generateTransactionId();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getSourceWallet(): ?Wallet
    {
        return $this->sourceWallet;
    }

    public function setSourceWallet(?Wallet $sourceWallet): self
    {
        $this->sourceWallet = $sourceWallet;
        return $this;
    }

    public function getDestinationWallet(): ?Wallet
    {
        return $this->destinationWallet;
    }

    public function setDestinationWallet(?Wallet $destinationWallet): self
    {
        $this->destinationWallet = $destinationWallet;
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

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
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

    public function getFeeAmount(): ?string
    {
        return $this->feeAmount;
    }

    public function setFeeAmount(?string $feeAmount): self
    {
        $this->feeAmount = $feeAmount;
        return $this;
    }

    public function getBalanceBefore(): ?string
    {
        return $this->balanceBefore;
    }

    public function setBalanceBefore(?string $balanceBefore): self
    {
        $this->balanceBefore = $balanceBefore;
        return $this;
    }

    public function getBalanceAfter(): ?string
    {
        return $this->balanceAfter;
    }

    public function setBalanceAfter(?string $balanceAfter): self
    {
        $this->balanceAfter = $balanceAfter;
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

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function setExternalReference(?string $externalReference): self
    {
        $this->externalReference = $externalReference;
        return $this;
    }

    public function getOriginalTransactionId(): ?string
    {
        return $this->originalTransactionId;
    }

    public function setOriginalTransactionId(?string $originalTransactionId): self
    {
        $this->originalTransactionId = $originalTransactionId;
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
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_CANCELLED]);
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    public function canBeProcessed(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    public function canBeReversed(): bool
    {
        return $this->status === self::STATUS_COMPLETED && 
               !in_array($this->transactionType, [self::TYPE_REVERSAL, self::TYPE_ADJUSTMENT]);
    }

    // Transaction type checking
    public function isDebit(): bool
    {
        return $this->transactionType === self::TYPE_DEBIT;
    }

    public function isCredit(): bool
    {
        return $this->transactionType === self::TYPE_CREDIT;
    }

    public function isTransfer(): bool
    {
        return in_array($this->transactionType, [self::TYPE_TRANSFER_OUT, self::TYPE_TRANSFER_IN]);
    }

    public function isTopUp(): bool
    {
        return $this->transactionType === self::TYPE_TOP_UP;
    }

    public function isWithdrawal(): bool
    {
        return $this->transactionType === self::TYPE_WITHDRAWAL;
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

    public function isReversal(): bool
    {
        return $this->transactionType === self::TYPE_REVERSAL;
    }

    // Category checking
    public function isPaymentCategory(): bool
    {
        return $this->category === self::CATEGORY_PAYMENT;
    }

    public function isTopUpCategory(): bool
    {
        return $this->category === self::CATEGORY_TOP_UP;
    }

    public function isTransferCategory(): bool
    {
        return $this->category === self::CATEGORY_TRANSFER;
    }

    // Utility methods
    public function getTotalAmount(): string
    {
        $total = (float)$this->amount + (float)($this->feeAmount ?? '0.00');
        return number_format($total, 2, '.', '');
    }

    public function getNetAmount(): string
    {
        if ($this->isCredit() || $this->transactionType === self::TYPE_TRANSFER_IN) {
            return $this->amount;
        }
        
        $net = (float)$this->amount - (float)($this->feeAmount ?? '0.00');
        return number_format(max(0, $net), 2, '.', '');
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

    private function generateTransactionId(): string
    {
        return 'WLT_' . strtoupper(bin2hex(random_bytes(8))) . '_' . time();
    }
}