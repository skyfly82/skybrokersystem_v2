<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exception;

use Throwable;

class CreditException extends \Exception
{
    public const ERROR_ACCOUNT_NOT_FOUND = 'CREDIT_ACCOUNT_NOT_FOUND';
    public const ERROR_ACCOUNT_INACTIVE = 'CREDIT_ACCOUNT_INACTIVE';
    public const ERROR_ACCOUNT_SUSPENDED = 'CREDIT_ACCOUNT_SUSPENDED';
    public const ERROR_ACCOUNT_CLOSED = 'CREDIT_ACCOUNT_CLOSED';
    public const ERROR_INSUFFICIENT_CREDIT = 'INSUFFICIENT_CREDIT';
    public const ERROR_CREDIT_LIMIT_EXCEEDED = 'CREDIT_LIMIT_EXCEEDED';
    public const ERROR_OVERDRAFT_LIMIT_EXCEEDED = 'OVERDRAFT_LIMIT_EXCEEDED';
    public const ERROR_INVALID_AMOUNT = 'INVALID_AMOUNT';
    public const ERROR_INVALID_CURRENCY = 'INVALID_CURRENCY';
    public const ERROR_TRANSACTION_NOT_FOUND = 'CREDIT_TRANSACTION_NOT_FOUND';
    public const ERROR_TRANSACTION_ALREADY_PROCESSED = 'TRANSACTION_ALREADY_PROCESSED';
    public const ERROR_AUTHORIZATION_FAILED = 'AUTHORIZATION_FAILED';
    public const ERROR_SETTLEMENT_FAILED = 'SETTLEMENT_FAILED';
    public const ERROR_REFUND_NOT_ALLOWED = 'REFUND_NOT_ALLOWED';
    public const ERROR_ACCOUNT_REVIEW_REQUIRED = 'ACCOUNT_REVIEW_REQUIRED';
    public const ERROR_PAYMENT_OVERDUE = 'PAYMENT_OVERDUE';
    public const ERROR_INVALID_PAYMENT_TERMS = 'INVALID_PAYMENT_TERMS';
    public const ERROR_GENERAL = 'CREDIT_GENERAL_ERROR';

    private string $errorCode;
    private ?array $errorDetails = null;

    public function __construct(
        string $message,
        string $errorCode = self::ERROR_GENERAL,
        ?array $errorDetails = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->errorDetails = $errorDetails;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrorDetails(): ?array
    {
        return $this->errorDetails;
    }

    public static function accountNotFound(string $identifier): self
    {
        return new self(
            sprintf('Credit account not found: %s', $identifier),
            self::ERROR_ACCOUNT_NOT_FOUND,
            ['identifier' => $identifier]
        );
    }

    public static function accountInactive(string $accountNumber, string $status): self
    {
        return new self(
            sprintf('Credit account %s is not active (status: %s)', $accountNumber, $status),
            self::ERROR_ACCOUNT_INACTIVE,
            ['account_number' => $accountNumber, 'status' => $status]
        );
    }

    public static function accountSuspended(string $accountNumber, ?string $reason = null): self
    {
        $message = sprintf('Credit account %s is suspended', $accountNumber);
        if ($reason) {
            $message .= sprintf(': %s', $reason);
        }

        return new self(
            $message,
            self::ERROR_ACCOUNT_SUSPENDED,
            ['account_number' => $accountNumber, 'reason' => $reason]
        );
    }

    public static function accountClosed(string $accountNumber, ?\DateTimeInterface $closedAt = null): self
    {
        $message = sprintf('Credit account %s is closed', $accountNumber);
        if ($closedAt) {
            $message .= sprintf(' (closed on %s)', $closedAt->format('Y-m-d H:i:s'));
        }

        return new self(
            $message,
            self::ERROR_ACCOUNT_CLOSED,
            [
                'account_number' => $accountNumber,
                'closed_at' => $closedAt?->format('Y-m-d H:i:s')
            ]
        );
    }

    public static function insufficientCredit(
        string $requestedAmount,
        string $availableCredit,
        string $currency = 'PLN'
    ): self {
        return new self(
            sprintf(
                'Insufficient credit: requested %s %s, available %s %s',
                $requestedAmount,
                $currency,
                $availableCredit,
                $currency
            ),
            self::ERROR_INSUFFICIENT_CREDIT,
            [
                'requested_amount' => $requestedAmount,
                'available_credit' => $availableCredit,
                'currency' => $currency
            ]
        );
    }

    public static function creditLimitExceeded(
        string $requestedAmount,
        string $creditLimit,
        string $usedCredit,
        string $currency = 'PLN'
    ): self {
        return new self(
            sprintf(
                'Credit limit exceeded: requested %s %s would exceed limit of %s %s (currently used: %s %s)',
                $requestedAmount,
                $currency,
                $creditLimit,
                $currency,
                $usedCredit,
                $currency
            ),
            self::ERROR_CREDIT_LIMIT_EXCEEDED,
            [
                'requested_amount' => $requestedAmount,
                'credit_limit' => $creditLimit,
                'used_credit' => $usedCredit,
                'currency' => $currency
            ]
        );
    }

    public static function overdraftLimitExceeded(
        string $requestedAmount,
        string $overdraftLimit,
        string $currentOverdraft,
        string $currency = 'PLN'
    ): self {
        return new self(
            sprintf(
                'Overdraft limit exceeded: requested %s %s would exceed overdraft limit of %s %s (current overdraft: %s %s)',
                $requestedAmount,
                $currency,
                $overdraftLimit,
                $currency,
                $currentOverdraft,
                $currency
            ),
            self::ERROR_OVERDRAFT_LIMIT_EXCEEDED,
            [
                'requested_amount' => $requestedAmount,
                'overdraft_limit' => $overdraftLimit,
                'current_overdraft' => $currentOverdraft,
                'currency' => $currency
            ]
        );
    }

    public static function invalidAmount(string $amount, string $currency = 'PLN'): self
    {
        return new self(
            sprintf('Invalid credit amount: %s %s', $amount, $currency),
            self::ERROR_INVALID_AMOUNT,
            ['amount' => $amount, 'currency' => $currency]
        );
    }

    public static function invalidCurrency(string $currency, array $supportedCurrencies = []): self
    {
        $message = sprintf('Unsupported currency for credit operations: %s', $currency);
        if (!empty($supportedCurrencies)) {
            $message .= sprintf(' (supported: %s)', implode(', ', $supportedCurrencies));
        }

        return new self(
            $message,
            self::ERROR_INVALID_CURRENCY,
            ['currency' => $currency, 'supported_currencies' => $supportedCurrencies]
        );
    }

    public static function transactionNotFound(string $transactionId): self
    {
        return new self(
            sprintf('Credit transaction not found: %s', $transactionId),
            self::ERROR_TRANSACTION_NOT_FOUND,
            ['transaction_id' => $transactionId]
        );
    }

    public static function transactionAlreadyProcessed(string $transactionId, string $status): self
    {
        return new self(
            sprintf('Credit transaction %s already processed with status: %s', $transactionId, $status),
            self::ERROR_TRANSACTION_ALREADY_PROCESSED,
            ['transaction_id' => $transactionId, 'status' => $status]
        );
    }

    public static function authorizationFailed(string $reason, ?array $details = null): self
    {
        return new self(
            sprintf('Credit authorization failed: %s', $reason),
            self::ERROR_AUTHORIZATION_FAILED,
            array_merge(['reason' => $reason], $details ?? [])
        );
    }

    public static function settlementFailed(string $transactionId, string $reason): self
    {
        return new self(
            sprintf('Credit settlement failed for transaction %s: %s', $transactionId, $reason),
            self::ERROR_SETTLEMENT_FAILED,
            ['transaction_id' => $transactionId, 'reason' => $reason]
        );
    }

    public static function refundNotAllowed(string $transactionId, string $reason): self
    {
        return new self(
            sprintf('Credit refund not allowed for transaction %s: %s', $transactionId, $reason),
            self::ERROR_REFUND_NOT_ALLOWED,
            ['transaction_id' => $transactionId, 'reason' => $reason]
        );
    }

    public static function accountReviewRequired(string $accountNumber, string $reason): self
    {
        return new self(
            sprintf('Credit account %s requires review: %s', $accountNumber, $reason),
            self::ERROR_ACCOUNT_REVIEW_REQUIRED,
            ['account_number' => $accountNumber, 'reason' => $reason]
        );
    }

    public static function paymentOverdue(
        string $transactionId,
        \DateTimeInterface $dueDate,
        int $daysOverdue
    ): self {
        return new self(
            sprintf(
                'Payment overdue for transaction %s: due %s (%d days overdue)',
                $transactionId,
                $dueDate->format('Y-m-d'),
                $daysOverdue
            ),
            self::ERROR_PAYMENT_OVERDUE,
            [
                'transaction_id' => $transactionId,
                'due_date' => $dueDate->format('Y-m-d'),
                'days_overdue' => $daysOverdue
            ]
        );
    }

    public static function invalidPaymentTerms(int $termDays, array $allowedTerms = []): self
    {
        $message = sprintf('Invalid payment terms: %d days', $termDays);
        if (!empty($allowedTerms)) {
            $message .= sprintf(' (allowed: %s days)', implode(', ', $allowedTerms));
        }

        return new self(
            $message,
            self::ERROR_INVALID_PAYMENT_TERMS,
            ['term_days' => $termDays, 'allowed_terms' => $allowedTerms]
        );
    }
}