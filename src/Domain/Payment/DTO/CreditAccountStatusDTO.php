<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTO;

class CreditAccountStatusDTO
{
    private string $accountNumber;
    private string $accountType;
    private string $status;
    private string $creditLimit;
    private string $availableCredit;
    private string $usedCredit;
    private string $overdraftLimit;
    private string $currency;
    private int $paymentTermDays;
    private string $outstandingBalance;
    private int $overdueTransactions;
    private bool $needsReview;
    private ?\DateTimeImmutable $nextReviewDate;
    private ?array $metadata;

    public function __construct(
        string $accountNumber,
        string $accountType,
        string $status,
        string $creditLimit,
        string $availableCredit,
        string $usedCredit,
        string $overdraftLimit,
        string $currency,
        int $paymentTermDays,
        string $outstandingBalance,
        int $overdueTransactions,
        bool $needsReview,
        ?\DateTimeImmutable $nextReviewDate = null,
        ?array $metadata = null
    ) {
        $this->accountNumber = $accountNumber;
        $this->accountType = $accountType;
        $this->status = $status;
        $this->creditLimit = $creditLimit;
        $this->availableCredit = $availableCredit;
        $this->usedCredit = $usedCredit;
        $this->overdraftLimit = $overdraftLimit;
        $this->currency = $currency;
        $this->paymentTermDays = $paymentTermDays;
        $this->outstandingBalance = $outstandingBalance;
        $this->overdueTransactions = $overdueTransactions;
        $this->needsReview = $needsReview;
        $this->nextReviewDate = $nextReviewDate;
        $this->metadata = $metadata;
    }

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    public function getAccountType(): string
    {
        return $this->accountType;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreditLimit(): string
    {
        return $this->creditLimit;
    }

    public function getAvailableCredit(): string
    {
        return $this->availableCredit;
    }

    public function getUsedCredit(): string
    {
        return $this->usedCredit;
    }

    public function getOverdraftLimit(): string
    {
        return $this->overdraftLimit;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getPaymentTermDays(): int
    {
        return $this->paymentTermDays;
    }

    public function getOutstandingBalance(): string
    {
        return $this->outstandingBalance;
    }

    public function getOverdueTransactions(): int
    {
        return $this->overdueTransactions;
    }

    public function needsReview(): bool
    {
        return $this->needsReview;
    }

    public function getNextReviewDate(): ?\DateTimeImmutable
    {
        return $this->nextReviewDate;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getCreditUtilization(): float
    {
        $limit = (float)$this->creditLimit;
        if ($limit === 0.0) {
            return 0.0;
        }

        return ((float)$this->usedCredit / $limit) * 100;
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

    public function toArray(): array
    {
        return [
            'account_number' => $this->accountNumber,
            'account_type' => $this->accountType,
            'status' => $this->status,
            'credit_limit' => $this->creditLimit,
            'available_credit' => $this->availableCredit,
            'used_credit' => $this->usedCredit,
            'overdraft_limit' => $this->overdraftLimit,
            'currency' => $this->currency,
            'payment_term_days' => $this->paymentTermDays,
            'outstanding_balance' => $this->outstandingBalance,
            'overdue_transactions' => $this->overdueTransactions,
            'needs_review' => $this->needsReview,
            'next_review_date' => $this->nextReviewDate?->format('Y-m-d'),
            'credit_utilization_percent' => round($this->getCreditUtilization(), 2),
            'is_overdrafted' => $this->isOverdrafted(),
            'overdraft_amount' => $this->getOverdraftAmount(),
            'metadata' => $this->metadata
        ];
    }
}