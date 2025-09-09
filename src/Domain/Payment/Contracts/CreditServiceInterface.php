<?php

declare(strict_types=1);

namespace App\Domain\Payment\Contracts;

use App\Domain\Payment\DTO\CreditAccountStatusDTO;
use App\Domain\Payment\DTO\CreditAuthorizationRequestDTO;
use App\Domain\Payment\DTO\CreditAuthorizationResponseDTO;
use App\Domain\Payment\DTO\CreditSettlementRequestDTO;
use App\Domain\Payment\DTO\CreditSettlementResponseDTO;
use App\Domain\Payment\Entity\CreditAccount;
use App\Domain\Payment\Entity\CreditTransaction;
use App\Domain\Payment\Exception\CreditException;
use App\Entity\User;

interface CreditServiceInterface
{
    /**
     * Create a credit account for a user
     *
     * @throws CreditException
     */
    public function createCreditAccount(
        User $user,
        string $accountType,
        string $creditLimit,
        int $paymentTermDays,
        string $currency = 'PLN',
        ?array $metadata = null
    ): CreditAccount;

    /**
     * Authorize a credit payment (hold credit limit)
     *
     * @throws CreditException
     */
    public function authorizePayment(
        User $user,
        CreditAuthorizationRequestDTO $request
    ): CreditAuthorizationResponseDTO;

    /**
     * Settle (finalize) an authorized credit payment
     *
     * @throws CreditException
     */
    public function settlePayment(CreditSettlementRequestDTO $request): CreditSettlementResponseDTO;

    /**
     * Cancel an authorized payment and release the credit hold
     *
     * @throws CreditException
     */
    public function cancelAuthorization(string $transactionId, ?string $reason = null): bool;

    /**
     * Process a credit refund
     *
     * @throws CreditException
     */
    public function processRefund(
        string $transactionId,
        string $refundAmount,
        ?string $reason = null
    ): CreditTransaction;

    /**
     * Get credit account status for a user
     *
     * @throws CreditException
     */
    public function getCreditAccountStatus(User $user): CreditAccountStatusDTO;

    /**
     * Get credit transaction by ID
     *
     * @throws CreditException
     */
    public function getTransaction(string $transactionId): CreditTransaction;

    /**
     * Update credit limit for an account
     *
     * @throws CreditException
     */
    public function updateCreditLimit(
        User $user,
        string $newCreditLimit,
        ?string $reason = null
    ): CreditAccount;

    /**
     * Suspend a credit account
     *
     * @throws CreditException
     */
    public function suspendAccount(User $user, string $reason): CreditAccount;

    /**
     * Reactivate a suspended credit account
     *
     * @throws CreditException
     */
    public function reactivateAccount(User $user): CreditAccount;

    /**
     * Close a credit account
     *
     * @throws CreditException
     */
    public function closeAccount(User $user, string $reason): CreditAccount;

    /**
     * Check if user can make a payment for the specified amount
     */
    public function canMakePayment(User $user, string $amount, string $currency = 'PLN'): bool;

    /**
     * Validate payment amount
     */
    public function validateAmount(string $amount, string $currency = 'PLN'): bool;

    /**
     * Get supported currencies for credit operations
     *
     * @return string[]
     */
    public function getSupportedCurrencies(): array;

    /**
     * Get minimum payment amount for currency
     */
    public function getMinimumAmount(string $currency = 'PLN'): string;

    /**
     * Get maximum payment amount for currency
     */
    public function getMaximumAmount(string $currency = 'PLN'): string;

    /**
     * Get all overdue transactions for a user
     *
     * @return CreditTransaction[]
     */
    public function getOverdueTransactions(?User $user = null): array;

    /**
     * Process overdue transaction fees and interest
     *
     * @throws CreditException
     */
    public function processOverdueCharges(CreditTransaction $transaction): array;

    /**
     * Mark transactions as overdue
     */
    public function markOverdueTransactions(): int;

    /**
     * Check if credit service is enabled
     */
    public function isEnabled(): bool;

    /**
     * Get allowed payment terms
     *
     * @return int[]
     */
    public function getAllowedPaymentTerms(): array;

    /**
     * Schedule credit account review
     */
    public function scheduleAccountReview(User $user, ?\DateTimeInterface $reviewDate = null): void;

    /**
     * Get accounts that need review
     *
     * @return CreditAccount[]
     */
    public function getAccountsNeedingReview(): array;

    /**
     * Record a manual payment/adjustment
     *
     * @throws CreditException
     */
    public function recordPayment(
        User $user,
        string $amount,
        string $transactionType,
        ?string $description = null,
        ?array $metadata = null
    ): CreditTransaction;
}