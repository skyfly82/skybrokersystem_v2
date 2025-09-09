<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exception;

class WalletException extends \Exception
{
    public const ERROR_WALLET_NOT_FOUND = 'wallet_not_found';
    public const ERROR_WALLET_INACTIVE = 'wallet_inactive';
    public const ERROR_WALLET_FROZEN = 'wallet_frozen';
    public const ERROR_WALLET_SUSPENDED = 'wallet_suspended';
    public const ERROR_WALLET_CLOSED = 'wallet_closed';
    public const ERROR_INSUFFICIENT_BALANCE = 'insufficient_balance';
    public const ERROR_INVALID_AMOUNT = 'invalid_amount';
    public const ERROR_INVALID_CURRENCY = 'invalid_currency';
    public const ERROR_TRANSACTION_NOT_FOUND = 'transaction_not_found';
    public const ERROR_TRANSACTION_ALREADY_PROCESSED = 'transaction_already_processed';
    public const ERROR_PAYMENT_FAILED = 'payment_failed';
    public const ERROR_TRANSFER_FAILED = 'transfer_failed';
    public const ERROR_TOP_UP_FAILED = 'top_up_failed';
    public const ERROR_REVERSAL_FAILED = 'reversal_failed';
    public const ERROR_DAILY_LIMIT_EXCEEDED = 'daily_limit_exceeded';
    public const ERROR_MONTHLY_LIMIT_EXCEEDED = 'monthly_limit_exceeded';
    public const ERROR_SAME_WALLET_TRANSFER = 'same_wallet_transfer';
    public const ERROR_WALLET_ALREADY_EXISTS = 'wallet_already_exists';
    public const ERROR_FREEZE_FAILED = 'freeze_failed';
    public const ERROR_UNFREEZE_FAILED = 'unfreeze_failed';
    public const ERROR_LOW_BALANCE_THRESHOLD = 'low_balance_threshold';
    public const ERROR_INVALID_TRANSACTION_TYPE = 'invalid_transaction_type';

    private string $errorCode;
    private array $errorData;

    public function __construct(
        string $message,
        string $errorCode,
        array $errorData = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->errorData = $errorData;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrorData(): array
    {
        return $this->errorData;
    }

    // Static factory methods for common exceptions
    public static function walletNotFound(string $identifier): self
    {
        return new self(
            sprintf('Wallet not found: %s', $identifier),
            self::ERROR_WALLET_NOT_FOUND,
            ['identifier' => $identifier]
        );
    }

    public static function walletInactive(string $walletNumber, string $status): self
    {
        return new self(
            sprintf('Wallet %s is inactive (status: %s)', $walletNumber, $status),
            self::ERROR_WALLET_INACTIVE,
            ['wallet_number' => $walletNumber, 'status' => $status]
        );
    }

    public static function walletFrozen(string $walletNumber, ?string $reason = null): self
    {
        return new self(
            sprintf('Wallet %s is frozen%s', $walletNumber, $reason ? ': ' . $reason : ''),
            self::ERROR_WALLET_FROZEN,
            ['wallet_number' => $walletNumber, 'reason' => $reason]
        );
    }

    public static function walletSuspended(string $walletNumber, ?\DateTimeInterface $suspendedAt = null): self
    {
        return new self(
            sprintf(
                'Wallet %s is suspended%s',
                $walletNumber,
                $suspendedAt ? ' since ' . $suspendedAt->format('Y-m-d H:i:s') : ''
            ),
            self::ERROR_WALLET_SUSPENDED,
            [
                'wallet_number' => $walletNumber,
                'suspended_at' => $suspendedAt?->format('Y-m-d H:i:s')
            ]
        );
    }

    public static function walletClosed(string $walletNumber, ?\DateTimeInterface $closedAt = null): self
    {
        return new self(
            sprintf(
                'Wallet %s is closed%s',
                $walletNumber,
                $closedAt ? ' since ' . $closedAt->format('Y-m-d H:i:s') : ''
            ),
            self::ERROR_WALLET_CLOSED,
            [
                'wallet_number' => $walletNumber,
                'closed_at' => $closedAt?->format('Y-m-d H:i:s')
            ]
        );
    }

    public static function insufficientBalance(string $walletNumber, string $requested, string $available, string $currency): self
    {
        return new self(
            sprintf(
                'Insufficient balance in wallet %s: requested %s %s, available %s %s',
                $walletNumber,
                $requested,
                $currency,
                $available,
                $currency
            ),
            self::ERROR_INSUFFICIENT_BALANCE,
            [
                'wallet_number' => $walletNumber,
                'requested_amount' => $requested,
                'available_amount' => $available,
                'currency' => $currency
            ]
        );
    }

    public static function invalidAmount(string $amount, string $currency): self
    {
        return new self(
            sprintf('Invalid amount: %s %s', $amount, $currency),
            self::ERROR_INVALID_AMOUNT,
            ['amount' => $amount, 'currency' => $currency]
        );
    }

    public static function invalidCurrency(string $currency, array $supportedCurrencies): self
    {
        return new self(
            sprintf(
                'Invalid currency: %s. Supported currencies: %s',
                $currency,
                implode(', ', $supportedCurrencies)
            ),
            self::ERROR_INVALID_CURRENCY,
            ['currency' => $currency, 'supported_currencies' => $supportedCurrencies]
        );
    }

    public static function transactionNotFound(string $transactionId): self
    {
        return new self(
            sprintf('Wallet transaction not found: %s', $transactionId),
            self::ERROR_TRANSACTION_NOT_FOUND,
            ['transaction_id' => $transactionId]
        );
    }

    public static function transactionAlreadyProcessed(string $transactionId, string $status): self
    {
        return new self(
            sprintf('Transaction %s already processed (status: %s)', $transactionId, $status),
            self::ERROR_TRANSACTION_ALREADY_PROCESSED,
            ['transaction_id' => $transactionId, 'status' => $status]
        );
    }

    public static function paymentFailed(string $reason, array $data = []): self
    {
        return new self(
            sprintf('Wallet payment failed: %s', $reason),
            self::ERROR_PAYMENT_FAILED,
            array_merge(['reason' => $reason], $data)
        );
    }

    public static function transferFailed(string $reason, array $data = []): self
    {
        return new self(
            sprintf('Wallet transfer failed: %s', $reason),
            self::ERROR_TRANSFER_FAILED,
            array_merge(['reason' => $reason], $data)
        );
    }

    public static function topUpFailed(string $reason, array $data = []): self
    {
        return new self(
            sprintf('Wallet top-up failed: %s', $reason),
            self::ERROR_TOP_UP_FAILED,
            array_merge(['reason' => $reason], $data)
        );
    }

    public static function reversalFailed(string $reason, array $data = []): self
    {
        return new self(
            sprintf('Transaction reversal failed: %s', $reason),
            self::ERROR_REVERSAL_FAILED,
            array_merge(['reason' => $reason], $data)
        );
    }

    public static function dailyLimitExceeded(
        string $walletNumber,
        string $requestedAmount,
        string $dailySpent,
        string $dailyLimit,
        string $currency
    ): self {
        return new self(
            sprintf(
                'Daily transaction limit exceeded for wallet %s: requested %s %s, daily spent %s %s, limit %s %s',
                $walletNumber,
                $requestedAmount,
                $currency,
                $dailySpent,
                $currency,
                $dailyLimit,
                $currency
            ),
            self::ERROR_DAILY_LIMIT_EXCEEDED,
            [
                'wallet_number' => $walletNumber,
                'requested_amount' => $requestedAmount,
                'daily_spent' => $dailySpent,
                'daily_limit' => $dailyLimit,
                'currency' => $currency
            ]
        );
    }

    public static function monthlyLimitExceeded(
        string $walletNumber,
        string $requestedAmount,
        string $monthlySpent,
        string $monthlyLimit,
        string $currency
    ): self {
        return new self(
            sprintf(
                'Monthly transaction limit exceeded for wallet %s: requested %s %s, monthly spent %s %s, limit %s %s',
                $walletNumber,
                $requestedAmount,
                $currency,
                $monthlySpent,
                $currency,
                $monthlyLimit,
                $currency
            ),
            self::ERROR_MONTHLY_LIMIT_EXCEEDED,
            [
                'wallet_number' => $walletNumber,
                'requested_amount' => $requestedAmount,
                'monthly_spent' => $monthlySpent,
                'monthly_limit' => $monthlyLimit,
                'currency' => $currency
            ]
        );
    }

    public static function sameWalletTransfer(string $walletNumber): self
    {
        return new self(
            sprintf('Cannot transfer to the same wallet: %s', $walletNumber),
            self::ERROR_SAME_WALLET_TRANSFER,
            ['wallet_number' => $walletNumber]
        );
    }

    public static function walletAlreadyExists(int $userId): self
    {
        return new self(
            sprintf('User %d already has a wallet', $userId),
            self::ERROR_WALLET_ALREADY_EXISTS,
            ['user_id' => $userId]
        );
    }

    public static function freezeFailed(string $walletNumber, string $reason): self
    {
        return new self(
            sprintf('Failed to freeze wallet %s: %s', $walletNumber, $reason),
            self::ERROR_FREEZE_FAILED,
            ['wallet_number' => $walletNumber, 'reason' => $reason]
        );
    }

    public static function unfreezeFailed(string $walletNumber, string $reason): self
    {
        return new self(
            sprintf('Failed to unfreeze wallet %s: %s', $walletNumber, $reason),
            self::ERROR_UNFREEZE_FAILED,
            ['wallet_number' => $walletNumber, 'reason' => $reason]
        );
    }

    public static function lowBalanceThreshold(string $walletNumber, string $threshold, string $currency): self
    {
        return new self(
            sprintf(
                'Wallet %s balance is below low balance threshold of %s %s',
                $walletNumber,
                $threshold,
                $currency
            ),
            self::ERROR_LOW_BALANCE_THRESHOLD,
            [
                'wallet_number' => $walletNumber,
                'threshold' => $threshold,
                'currency' => $currency
            ]
        );
    }

    public static function invalidTransactionType(string $transactionType, array $validTypes): self
    {
        return new self(
            sprintf(
                'Invalid transaction type: %s. Valid types: %s',
                $transactionType,
                implode(', ', $validTypes)
            ),
            self::ERROR_INVALID_TRANSACTION_TYPE,
            ['transaction_type' => $transactionType, 'valid_types' => $validTypes]
        );
    }
}