<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CustomerBalanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Customer Balance Entity
 * Tracks customer account balance and credit limits
 */
#[ORM\Entity(repositoryClass: CustomerBalanceRepository::class)]
#[ORM\Table(name: 'v2_customer_balances')]
#[ORM\UniqueConstraint(name: 'UNIQ_CUSTOMER_BALANCE', fields: ['customer'])]
class CustomerBalance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $currentBalance = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $creditLimit = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $availableCredit = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $reservedAmount = '0.00'; // Amount reserved for pending shipments

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $totalSpent = '0.00'; // Lifetime spending

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $totalTopUps = '0.00'; // Lifetime top-ups

    #[ORM\Column(length: 3)]
    private string $currency = 'PLN';

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $autoTopUpEnabled = false;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $autoTopUpTrigger = null; // Balance threshold for auto top-up

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $autoTopUpAmount = null; // Amount to top up automatically

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $autoTopUpMethod = null; // Payment method for auto top-up

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastTopUpAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastTransactionAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): static
    {
        $this->customer = $customer;
        return $this;
    }

    public function getCurrentBalance(): string
    {
        return $this->currentBalance;
    }

    public function setCurrentBalance(string $currentBalance): static
    {
        $this->currentBalance = $currentBalance;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreditLimit(): string
    {
        return $this->creditLimit;
    }

    public function setCreditLimit(string $creditLimit): static
    {
        $this->creditLimit = $creditLimit;
        $this->updateAvailableCredit();
        return $this;
    }

    public function getAvailableCredit(): string
    {
        return $this->availableCredit;
    }

    public function setAvailableCredit(string $availableCredit): static
    {
        $this->availableCredit = $availableCredit;
        return $this;
    }

    public function getReservedAmount(): string
    {
        return $this->reservedAmount;
    }

    public function setReservedAmount(string $reservedAmount): static
    {
        $this->reservedAmount = $reservedAmount;
        $this->updateAvailableCredit();
        return $this;
    }

    public function getTotalSpent(): string
    {
        return $this->totalSpent;
    }

    public function setTotalSpent(string $totalSpent): static
    {
        $this->totalSpent = $totalSpent;
        return $this;
    }

    public function getTotalTopUps(): string
    {
        return $this->totalTopUps;
    }

    public function setTotalTopUps(string $totalTopUps): static
    {
        $this->totalTopUps = $totalTopUps;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function isAutoTopUpEnabled(): bool
    {
        return $this->autoTopUpEnabled;
    }

    public function setAutoTopUpEnabled(bool $autoTopUpEnabled): static
    {
        $this->autoTopUpEnabled = $autoTopUpEnabled;
        return $this;
    }

    public function getAutoTopUpTrigger(): ?string
    {
        return $this->autoTopUpTrigger;
    }

    public function setAutoTopUpTrigger(?string $autoTopUpTrigger): static
    {
        $this->autoTopUpTrigger = $autoTopUpTrigger;
        return $this;
    }

    public function getAutoTopUpAmount(): ?string
    {
        return $this->autoTopUpAmount;
    }

    public function setAutoTopUpAmount(?string $autoTopUpAmount): static
    {
        $this->autoTopUpAmount = $autoTopUpAmount;
        return $this;
    }

    public function getAutoTopUpMethod(): ?string
    {
        return $this->autoTopUpMethod;
    }

    public function setAutoTopUpMethod(?string $autoTopUpMethod): static
    {
        $this->autoTopUpMethod = $autoTopUpMethod;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastTopUpAt(): ?\DateTimeImmutable
    {
        return $this->lastTopUpAt;
    }

    public function setLastTopUpAt(?\DateTimeImmutable $lastTopUpAt): static
    {
        $this->lastTopUpAt = $lastTopUpAt;
        return $this;
    }

    public function getLastTransactionAt(): ?\DateTimeImmutable
    {
        return $this->lastTransactionAt;
    }

    public function setLastTransactionAt(?\DateTimeImmutable $lastTransactionAt): static
    {
        $this->lastTransactionAt = $lastTransactionAt;
        return $this;
    }

    // Business logic methods

    public function addFunds(float $amount): static
    {
        $this->currentBalance = bcadd($this->currentBalance, (string) $amount, 2);
        $this->totalTopUps = bcadd($this->totalTopUps, (string) $amount, 2);
        $this->lastTopUpAt = new \DateTimeImmutable();
        $this->lastTransactionAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->updateAvailableCredit();

        return $this;
    }

    public function deductFunds(float $amount): static
    {
        if (!$this->hasSufficientFunds($amount)) {
            throw new \InvalidArgumentException('Insufficient funds');
        }

        $this->currentBalance = bcsub($this->currentBalance, (string) $amount, 2);
        $this->totalSpent = bcadd($this->totalSpent, (string) $amount, 2);
        $this->lastTransactionAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->updateAvailableCredit();

        return $this;
    }

    public function reserveFunds(float $amount): static
    {
        if (!$this->hasSufficientFunds($amount)) {
            throw new \InvalidArgumentException('Insufficient funds to reserve');
        }

        $this->reservedAmount = bcadd($this->reservedAmount, (string) $amount, 2);
        $this->updatedAt = new \DateTimeImmutable();
        $this->updateAvailableCredit();

        return $this;
    }

    public function releaseReservedFunds(float $amount): static
    {
        $maxRelease = min((float) $this->reservedAmount, $amount);
        $this->reservedAmount = bcsub($this->reservedAmount, (string) $maxRelease, 2);
        $this->updatedAt = new \DateTimeImmutable();
        $this->updateAvailableCredit();

        return $this;
    }

    public function finalizeReservedTransaction(float $reservedAmount, float $actualAmount): static
    {
        // Release the reserved amount
        $this->releaseReservedFunds($reservedAmount);

        // Deduct the actual amount
        $this->deductFunds($actualAmount);

        // If actual amount is less than reserved, the difference stays in balance
        if ($actualAmount < $reservedAmount) {
            $difference = $reservedAmount - $actualAmount;
            // Difference automatically stays in balance since we only deducted actual amount
        }

        return $this;
    }

    public function hasSufficientFunds(float $amount): bool
    {
        $availableFunds = (float) $this->getAvailableFunds();
        return $availableFunds >= $amount;
    }

    public function getAvailableFunds(): string
    {
        $availableBalance = bcsub($this->currentBalance, $this->reservedAmount, 2);
        $totalAvailable = bcadd($availableBalance, $this->availableCredit, 2);

        return max('0.00', $totalAvailable);
    }

    public function getTotalAvailableFunds(): string
    {
        return $this->getAvailableFunds();
    }

    public function needsAutoTopUp(): bool
    {
        if (!$this->autoTopUpEnabled || !$this->autoTopUpTrigger) {
            return false;
        }

        return bccomp($this->currentBalance, $this->autoTopUpTrigger, 2) <= 0;
    }

    public function updateAvailableCredit(): static
    {
        // Available credit = credit limit - current negative balance (if any)
        if (bccomp($this->currentBalance, '0.00', 2) >= 0) {
            $this->availableCredit = $this->creditLimit;
        } else {
            // If balance is negative, reduce available credit
            $usedCredit = abs((float) $this->currentBalance);
            $this->availableCredit = bcsub($this->creditLimit, (string) $usedCredit, 2);
            $this->availableCredit = max('0.00', $this->availableCredit);
        }

        return $this;
    }

    public function isInCredit(): bool
    {
        return bccomp($this->currentBalance, '0.00', 2) < 0;
    }

    public function getCreditUsed(): string
    {
        if (!$this->isInCredit()) {
            return '0.00';
        }

        return number_format(abs((float) $this->currentBalance), 2, '.', '');
    }

    public function getBalanceStatus(): string
    {
        $balance = (float) $this->currentBalance;

        if ($balance > 100) {
            return 'healthy';
        } elseif ($balance > 0) {
            return 'low';
        } elseif ($balance >= -((float) $this->creditLimit)) {
            return 'credit';
        } else {
            return 'overlimit';
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer->getId(),
            'current_balance' => (float) $this->currentBalance,
            'credit_limit' => (float) $this->creditLimit,
            'available_credit' => (float) $this->availableCredit,
            'reserved_amount' => (float) $this->reservedAmount,
            'available_funds' => (float) $this->getAvailableFunds(),
            'total_spent' => (float) $this->totalSpent,
            'total_topups' => (float) $this->totalTopUps,
            'currency' => $this->currency,
            'balance_status' => $this->getBalanceStatus(),
            'is_in_credit' => $this->isInCredit(),
            'credit_used' => (float) $this->getCreditUsed(),
            'auto_topup_enabled' => $this->autoTopUpEnabled,
            'auto_topup_trigger' => $this->autoTopUpTrigger ? (float) $this->autoTopUpTrigger : null,
            'auto_topup_amount' => $this->autoTopUpAmount ? (float) $this->autoTopUpAmount : null,
            'auto_topup_method' => $this->autoTopUpMethod,
            'needs_auto_topup' => $this->needsAutoTopUp(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'last_topup_at' => $this->lastTopUpAt?->format('Y-m-d H:i:s'),
            'last_transaction_at' => $this->lastTransactionAt?->format('Y-m-d H:i:s'),
        ];
    }
}