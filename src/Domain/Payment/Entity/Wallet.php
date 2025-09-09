<?php

declare(strict_types=1);

namespace App\Domain\Payment\Entity;

use App\Entity\User;
use App\Entity\SystemUser;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: \App\Domain\Payment\Repository\WalletRepository::class)]
#[ORM\Table(name: 'v2_wallets')]
#[ORM\Index(columns: ['status'], name: 'idx_wallet_status')]
#[ORM\Index(columns: ['currency'], name: 'idx_wallet_currency')]
#[ORM\Index(columns: ['created_at'], name: 'idx_wallet_created')]
class Wallet
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FROZEN = 'frozen';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CLOSED = 'closed';

    public const FREEZE_REASON_SECURITY = 'security_concern';
    public const FREEZE_REASON_MAINTENANCE = 'maintenance';
    public const FREEZE_REASON_COMPLIANCE = 'compliance_check';
    public const FREEZE_REASON_MANUAL = 'manual_freeze';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SystemUser::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 100, unique: true)]
    private string $walletNumber;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $balance = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $reservedBalance = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $availableBalance = '0.00';

    #[ORM\Column(length: 3)]
    private string $currency = 'PLN';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $dailyTransactionLimit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $monthlyTransactionLimit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $lowBalanceThreshold = '10.00';

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $lowBalanceNotificationSent = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $freezeReason = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $securitySettings = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $frozenAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $suspendedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastTransactionAt = null;

    public function __construct()
    {
        $this->walletNumber = $this->generateWalletNumber();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->availableBalance = $this->balance;
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

    public function getWalletNumber(): string
    {
        return $this->walletNumber;
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
            self::STATUS_FROZEN => $this->frozenAt = new \DateTimeImmutable(),
            self::STATUS_SUSPENDED => $this->suspendedAt = new \DateTimeImmutable(),
            self::STATUS_CLOSED => $this->closedAt = new \DateTimeImmutable(),
            default => null
        };

        return $this;
    }

    public function getBalance(): string
    {
        return $this->balance;
    }

    public function setBalance(string $balance): self
    {
        $this->balance = $balance;
        $this->recalculateAvailableBalance();
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getReservedBalance(): string
    {
        return $this->reservedBalance;
    }

    public function setReservedBalance(string $reservedBalance): self
    {
        $this->reservedBalance = $reservedBalance;
        $this->recalculateAvailableBalance();
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getAvailableBalance(): string
    {
        return $this->availableBalance;
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

    public function getDailyTransactionLimit(): ?string
    {
        return $this->dailyTransactionLimit;
    }

    public function setDailyTransactionLimit(?string $dailyTransactionLimit): self
    {
        $this->dailyTransactionLimit = $dailyTransactionLimit;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getMonthlyTransactionLimit(): ?string
    {
        return $this->monthlyTransactionLimit;
    }

    public function setMonthlyTransactionLimit(?string $monthlyTransactionLimit): self
    {
        $this->monthlyTransactionLimit = $monthlyTransactionLimit;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getLowBalanceThreshold(): string
    {
        return $this->lowBalanceThreshold;
    }

    public function setLowBalanceThreshold(string $lowBalanceThreshold): self
    {
        $this->lowBalanceThreshold = $lowBalanceThreshold;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isLowBalanceNotificationSent(): bool
    {
        return $this->lowBalanceNotificationSent;
    }

    public function setLowBalanceNotificationSent(bool $lowBalanceNotificationSent): self
    {
        $this->lowBalanceNotificationSent = $lowBalanceNotificationSent;
        return $this;
    }

    public function getFreezeReason(): ?string
    {
        return $this->freezeReason;
    }

    public function setFreezeReason(?string $freezeReason): self
    {
        $this->freezeReason = $freezeReason;
        return $this;
    }

    public function getSecuritySettings(): ?array
    {
        return $this->securitySettings;
    }

    public function setSecuritySettings(?array $securitySettings): self
    {
        $this->securitySettings = $securitySettings;
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

    public function getFrozenAt(): ?\DateTimeImmutable
    {
        return $this->frozenAt;
    }

    public function getSuspendedAt(): ?\DateTimeImmutable
    {
        return $this->suspendedAt;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function getLastTransactionAt(): ?\DateTimeImmutable
    {
        return $this->lastTransactionAt;
    }

    public function setLastTransactionAt(?\DateTimeImmutable $lastTransactionAt): self
    {
        $this->lastTransactionAt = $lastTransactionAt;
        return $this;
    }

    // Status checking methods
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isFrozen(): bool
    {
        return $this->status === self::STATUS_FROZEN;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function canMakeTransaction(string $amount): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $requestedAmount = (float)$amount;
        $available = (float)$this->availableBalance;

        return $requestedAmount > 0 && $requestedAmount <= $available;
    }

    public function isLowBalance(): bool
    {
        return (float)$this->availableBalance <= (float)$this->lowBalanceThreshold;
    }

    public function hasReservedFunds(): bool
    {
        return (float)$this->reservedBalance > 0;
    }

    public function reserveFunds(string $amount): bool
    {
        if (!$this->canMakeTransaction($amount)) {
            return false;
        }

        $reserveAmount = (float)$amount;
        $currentReserved = (float)$this->reservedBalance;

        $this->setReservedBalance(number_format($currentReserved + $reserveAmount, 2, '.', ''));

        return true;
    }

    public function releaseReservedFunds(string $amount): void
    {
        $releaseAmount = (float)$amount;
        $currentReserved = (float)$this->reservedBalance;
        
        $newReserved = max(0, $currentReserved - $releaseAmount);
        $this->setReservedBalance(number_format($newReserved, 2, '.', ''));
    }

    public function debitBalance(string $amount): bool
    {
        $debitAmount = (float)$amount;
        $currentBalance = (float)$this->balance;

        if ($debitAmount > $currentBalance) {
            return false;
        }

        $this->setBalance(number_format($currentBalance - $debitAmount, 2, '.', ''));
        $this->setLastTransactionAt(new \DateTimeImmutable());

        // Reset low balance notification if balance is now above threshold
        if (!$this->isLowBalance() && $this->lowBalanceNotificationSent) {
            $this->setLowBalanceNotificationSent(false);
        }

        return true;
    }

    public function creditBalance(string $amount): void
    {
        $creditAmount = (float)$amount;
        $currentBalance = (float)$this->balance;

        $this->setBalance(number_format($currentBalance + $creditAmount, 2, '.', ''));
        $this->setLastTransactionAt(new \DateTimeImmutable());

        // Reset low balance notification if balance is now above threshold
        if (!$this->isLowBalance() && $this->lowBalanceNotificationSent) {
            $this->setLowBalanceNotificationSent(false);
        }
    }

    public function freezeWallet(string $reason): void
    {
        $this->setStatus(self::STATUS_FROZEN);
        $this->setFreezeReason($reason);
    }

    public function unfreezeWallet(): void
    {
        $this->setStatus(self::STATUS_ACTIVE);
        $this->setFreezeReason(null);
        $this->frozenAt = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function checkDailyLimit(string $amount, string $totalDailySpent): bool
    {
        if ($this->dailyTransactionLimit === null) {
            return true;
        }

        $requestedAmount = (float)$amount;
        $currentDailySpent = (float)$totalDailySpent;
        $dailyLimit = (float)$this->dailyTransactionLimit;

        return ($currentDailySpent + $requestedAmount) <= $dailyLimit;
    }

    public function checkMonthlyLimit(string $amount, string $totalMonthlySpent): bool
    {
        if ($this->monthlyTransactionLimit === null) {
            return true;
        }

        $requestedAmount = (float)$amount;
        $currentMonthlySpent = (float)$totalMonthlySpent;
        $monthlyLimit = (float)$this->monthlyTransactionLimit;

        return ($currentMonthlySpent + $requestedAmount) <= $monthlyLimit;
    }

    public function getDaysSinceLastTransaction(): int
    {
        if ($this->lastTransactionAt === null) {
            return $this->createdAt->diff(new \DateTimeImmutable())->days;
        }

        return $this->lastTransactionAt->diff(new \DateTimeImmutable())->days;
    }

    public function isInactive(int $inactiveDays = 365): bool
    {
        return $this->getDaysSinceLastTransaction() >= $inactiveDays;
    }

    private function recalculateAvailableBalance(): void
    {
        $balance = (float)$this->balance;
        $reserved = (float)$this->reservedBalance;
        $available = max(0, $balance - $reserved);
        
        $this->availableBalance = number_format($available, 2, '.', '');
    }

    private function generateWalletNumber(): string
    {
        return 'WAL_' . strtoupper(bin2hex(random_bytes(8))) . '_' . time();
    }
}