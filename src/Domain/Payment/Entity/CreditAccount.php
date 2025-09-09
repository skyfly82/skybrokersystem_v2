<?php

declare(strict_types=1);

namespace App\Domain\Payment\Entity;

use App\Entity\User;
use App\Entity\SystemUser;
use App\Entity\CustomerUser;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: \App\Domain\Payment\Repository\CreditAccountRepository::class)]
#[ORM\Table(name: 'v2_credit_accounts')]
#[ORM\Index(columns: ['status'], name: 'idx_credit_account_status')]
#[ORM\Index(columns: ['account_type'], name: 'idx_credit_account_type')]
#[ORM\Index(columns: ['created_at'], name: 'idx_credit_account_created')]
class CreditAccount
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const TYPE_INDIVIDUAL = 'individual';
    public const TYPE_BUSINESS = 'business';

    public const TERM_NET_15 = 15;
    public const TERM_NET_30 = 30;
    public const TERM_NET_45 = 45;
    public const TERM_NET_60 = 60;
    public const TERM_NET_90 = 90;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SystemUser::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 100, unique: true)]
    private string $accountNumber;

    #[ORM\Column(length: 50)]
    private string $accountType;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_PENDING_APPROVAL;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $creditLimit;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $availableCredit;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $usedCredit = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $overdraftLimit = '0.00';

    #[ORM\Column(type: Types::SMALLINT)]
    private int $paymentTermDays = self::TERM_NET_30;

    #[ORM\Column(length: 3)]
    private string $currency = 'PLN';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $interestRate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $overdraftFee = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastCreditReviewDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $nextReviewDate = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $suspendedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    public function __construct()
    {
        $this->accountNumber = $this->generateAccountNumber();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    public function getAccountType(): string
    {
        return $this->accountType;
    }

    public function setAccountType(string $accountType): self
    {
        $this->accountType = $accountType;
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
        $this->updatedAt = new \DateTimeImmutable();

        // Set timestamps based on status change
        match ($status) {
            self::STATUS_ACTIVE => $this->approvedAt = new \DateTimeImmutable(),
            self::STATUS_SUSPENDED => $this->suspendedAt = new \DateTimeImmutable(),
            self::STATUS_CLOSED => $this->closedAt = new \DateTimeImmutable(),
            default => null
        };

        return $this;
    }

    public function getCreditLimit(): string
    {
        return $this->creditLimit;
    }

    public function setCreditLimit(string $creditLimit): self
    {
        $this->creditLimit = $creditLimit;
        $this->recalculateAvailableCredit();
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getAvailableCredit(): string
    {
        return $this->availableCredit;
    }

    public function getUsedCredit(): string
    {
        return $this->usedCredit;
    }

    public function setUsedCredit(string $usedCredit): self
    {
        $this->usedCredit = $usedCredit;
        $this->recalculateAvailableCredit();
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getOverdraftLimit(): string
    {
        return $this->overdraftLimit;
    }

    public function setOverdraftLimit(string $overdraftLimit): self
    {
        $this->overdraftLimit = $overdraftLimit;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getPaymentTermDays(): int
    {
        return $this->paymentTermDays;
    }

    public function setPaymentTermDays(int $paymentTermDays): self
    {
        $this->paymentTermDays = $paymentTermDays;
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getInterestRate(): ?string
    {
        return $this->interestRate;
    }

    public function setInterestRate(?string $interestRate): self
    {
        $this->interestRate = $interestRate;
        return $this;
    }

    public function getOverdraftFee(): ?string
    {
        return $this->overdraftFee;
    }

    public function setOverdraftFee(?string $overdraftFee): self
    {
        $this->overdraftFee = $overdraftFee;
        return $this;
    }

    public function getLastCreditReviewDate(): ?\DateTimeImmutable
    {
        return $this->lastCreditReviewDate;
    }

    public function setLastCreditReviewDate(?\DateTimeImmutable $lastCreditReviewDate): self
    {
        $this->lastCreditReviewDate = $lastCreditReviewDate;
        return $this;
    }

    public function getNextReviewDate(): ?\DateTimeImmutable
    {
        return $this->nextReviewDate;
    }

    public function setNextReviewDate(?\DateTimeImmutable $nextReviewDate): self
    {
        $this->nextReviewDate = $nextReviewDate;
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function getSuspendedAt(): ?\DateTimeImmutable
    {
        return $this->suspendedAt;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    // Status checking methods
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function canMakePayment(string $amount): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $requestedAmount = (float)$amount;
        $totalAvailable = (float)$this->availableCredit + (float)$this->overdraftLimit;

        return $requestedAmount <= $totalAvailable;
    }

    public function isOverdrafted(): bool
    {
        return (float)$this->usedCredit > (float)$this->creditLimit;
    }

    public function getOverdraftAmount(): string
    {
        $overdraft = (float)$this->usedCredit - (float)$this->creditLimit;
        return $overdraft > 0 ? number_format($overdraft, 2, '.', '') : '0.00';
    }

    public function authorizePayment(string $amount): bool
    {
        if (!$this->canMakePayment($amount)) {
            return false;
        }

        $newUsedCredit = (float)$this->usedCredit + (float)$amount;
        $this->setUsedCredit(number_format($newUsedCredit, 2, '.', ''));

        return true;
    }

    public function releaseAuthorization(string $amount): void
    {
        $newUsedCredit = (float)$this->usedCredit - (float)$amount;
        $this->setUsedCredit(number_format(max(0, $newUsedCredit), 2, '.', ''));
    }

    public function calculatePaymentDueDate(\DateTimeInterface $baseDate = null): \DateTimeImmutable
    {
        $baseDate = $baseDate ?? new \DateTimeImmutable();
        return $baseDate->modify(sprintf('+%d days', $this->paymentTermDays));
    }

    public function needsReview(): bool
    {
        if ($this->nextReviewDate === null) {
            return false;
        }

        return $this->nextReviewDate <= new \DateTimeImmutable();
    }

    private function recalculateAvailableCredit(): void
    {
        $available = (float)$this->creditLimit - (float)$this->usedCredit;
        $this->availableCredit = number_format(max(0, $available), 2, '.', '');
    }

    private function generateAccountNumber(): string
    {
        return 'CRD_' . strtoupper(bin2hex(random_bytes(6))) . '_' . time();
    }
}