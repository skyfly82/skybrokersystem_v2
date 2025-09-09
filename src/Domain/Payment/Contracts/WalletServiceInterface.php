<?php

declare(strict_types=1);

namespace App\Domain\Payment\Contracts;

use App\Domain\Payment\DTO\WalletPaymentRequestDTO;
use App\Domain\Payment\DTO\WalletPaymentResponseDTO;
use App\Domain\Payment\DTO\WalletStatusDTO;
use App\Domain\Payment\DTO\WalletTopUpRequestDTO;
use App\Domain\Payment\DTO\WalletTopUpResponseDTO;
use App\Domain\Payment\DTO\WalletTransferRequestDTO;
use App\Domain\Payment\DTO\WalletTransferResponseDTO;
use App\Domain\Payment\Entity\Wallet;
use App\Domain\Payment\Entity\WalletTransaction;
use App\Entity\User;

interface WalletServiceInterface
{
    /**
     * Create a new wallet for a user
     */
    public function createWallet(
        User $user,
        string $currency = 'PLN',
        ?string $dailyTransactionLimit = null,
        ?string $monthlyTransactionLimit = null,
        ?string $lowBalanceThreshold = null,
        ?array $metadata = null
    ): Wallet;

    /**
     * Process a payment from wallet
     */
    public function processPayment(User $user, WalletPaymentRequestDTO $request): WalletPaymentResponseDTO;

    /**
     * Transfer funds between wallets
     */
    public function transferFunds(User $fromUser, WalletTransferRequestDTO $request): WalletTransferResponseDTO;

    /**
     * Top up wallet with external payment
     */
    public function topUpWallet(User $user, WalletTopUpRequestDTO $request): WalletTopUpResponseDTO;

    /**
     * Get wallet status and balance information
     */
    public function getWalletStatus(User $user): WalletStatusDTO;

    /**
     * Get wallet transaction by transaction ID
     */
    public function getTransaction(string $transactionId): WalletTransaction;

    /**
     * Reverse a completed transaction
     */
    public function reverseTransaction(
        string $transactionId,
        string $amount,
        ?string $reason = null
    ): WalletTransaction;

    /**
     * Freeze a wallet
     */
    public function freezeWallet(User $user, string $reason): Wallet;

    /**
     * Unfreeze a wallet
     */
    public function unfreezeWallet(User $user): Wallet;

    /**
     * Suspend a wallet
     */
    public function suspendWallet(User $user, string $reason): Wallet;

    /**
     * Close a wallet
     */
    public function closeWallet(User $user, string $reason): Wallet;

    /**
     * Update wallet transaction limits
     */
    public function updateTransactionLimits(
        User $user,
        ?string $dailyLimit = null,
        ?string $monthlyLimit = null
    ): Wallet;

    /**
     * Update low balance threshold and notification settings
     */
    public function updateLowBalanceSettings(
        User $user,
        string $threshold,
        bool $resetNotification = false
    ): Wallet;

    /**
     * Check if user can make a payment of specified amount
     */
    public function canMakePayment(User $user, string $amount, string $currency = 'PLN'): bool;

    /**
     * Validate payment amount and currency
     */
    public function validateAmount(string $amount, string $currency = 'PLN'): bool;

    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies(): array;

    /**
     * Get minimum amount for currency
     */
    public function getMinimumAmount(string $currency = 'PLN'): string;

    /**
     * Get maximum amount for currency
     */
    public function getMaximumAmount(string $currency = 'PLN'): string;

    /**
     * Check if wallet service is enabled
     */
    public function isEnabled(): bool;
}