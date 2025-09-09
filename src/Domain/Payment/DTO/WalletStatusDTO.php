<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTO;

class WalletStatusDTO
{
    public function __construct(
        private readonly string $walletNumber,
        private readonly string $status,
        private readonly string $balance,
        private readonly string $availableBalance,
        private readonly string $reservedBalance,
        private readonly string $currency,
        private readonly string $lowBalanceThreshold,
        private readonly bool $isLowBalance,
        private readonly bool $lowBalanceNotificationSent,
        private readonly ?string $dailyTransactionLimit,
        private readonly ?string $monthlyTransactionLimit,
        private readonly ?string $dailySpent,
        private readonly ?string $monthlySpent,
        private readonly ?string $freezeReason = null,
        private readonly ?\DateTimeInterface $lastTransactionAt = null,
        private readonly ?\DateTimeInterface $createdAt = null,
        private readonly ?array $securitySettings = null,
        private readonly ?array $metadata = null
    ) {
    }

    public function getWalletNumber(): string
    {
        return $this->walletNumber;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getBalance(): string
    {
        return $this->balance;
    }

    public function getAvailableBalance(): string
    {
        return $this->availableBalance;
    }

    public function getReservedBalance(): string
    {
        return $this->reservedBalance;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getLowBalanceThreshold(): string
    {
        return $this->lowBalanceThreshold;
    }

    public function isLowBalance(): bool
    {
        return $this->isLowBalance;
    }

    public function isLowBalanceNotificationSent(): bool
    {
        return $this->lowBalanceNotificationSent;
    }

    public function getDailyTransactionLimit(): ?string
    {
        return $this->dailyTransactionLimit;
    }

    public function getMonthlyTransactionLimit(): ?string
    {
        return $this->monthlyTransactionLimit;
    }

    public function getDailySpent(): ?string
    {
        return $this->dailySpent;
    }

    public function getMonthlySpent(): ?string
    {
        return $this->monthlySpent;
    }

    public function getFreezeReason(): ?string
    {
        return $this->freezeReason;
    }

    public function getLastTransactionAt(): ?\DateTimeInterface
    {
        return $this->lastTransactionAt;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getSecuritySettings(): ?array
    {
        return $this->securitySettings;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isFrozen(): bool
    {
        return $this->status === 'frozen';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function hasReservedFunds(): bool
    {
        return (float)$this->reservedBalance > 0;
    }

    public function getRemainingDailyLimit(): ?string
    {
        if ($this->dailyTransactionLimit === null || $this->dailySpent === null) {
            return null;
        }

        $remaining = (float)$this->dailyTransactionLimit - (float)$this->dailySpent;
        return number_format(max(0, $remaining), 2, '.', '');
    }

    public function getRemainingMonthlyLimit(): ?string
    {
        if ($this->monthlyTransactionLimit === null || $this->monthlySpent === null) {
            return null;
        }

        $remaining = (float)$this->monthlyTransactionLimit - (float)$this->monthlySpent;
        return number_format(max(0, $remaining), 2, '.', '');
    }
}