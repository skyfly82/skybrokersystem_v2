<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exception;

use Throwable;

class PayNowIntegrationException extends \Exception
{
    public const ERROR_API_CONNECTION = 'API_CONNECTION_ERROR';
    public const ERROR_INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    public const ERROR_INVALID_AMOUNT = 'INVALID_AMOUNT';
    public const ERROR_INVALID_CURRENCY = 'INVALID_CURRENCY';
    public const ERROR_PAYMENT_NOT_FOUND = 'PAYMENT_NOT_FOUND';
    public const ERROR_PAYMENT_ALREADY_PROCESSED = 'PAYMENT_ALREADY_PROCESSED';
    public const ERROR_REFUND_NOT_ALLOWED = 'REFUND_NOT_ALLOWED';
    public const ERROR_INVALID_WEBHOOK_SIGNATURE = 'INVALID_WEBHOOK_SIGNATURE';
    public const ERROR_GENERAL = 'GENERAL_ERROR';

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

    public static function apiConnectionError(string $message, ?Throwable $previous = null): self
    {
        return new self($message, self::ERROR_API_CONNECTION, null, $previous);
    }

    public static function invalidCredentials(string $message = 'Invalid PayNow API credentials'): self
    {
        return new self($message, self::ERROR_INVALID_CREDENTIALS);
    }

    public static function invalidAmount(string $amount, string $currency = 'PLN'): self
    {
        return new self(
            sprintf('Invalid payment amount: %s %s', $amount, $currency),
            self::ERROR_INVALID_AMOUNT,
            ['amount' => $amount, 'currency' => $currency]
        );
    }

    public static function invalidCurrency(string $currency): self
    {
        return new self(
            sprintf('Unsupported currency: %s', $currency),
            self::ERROR_INVALID_CURRENCY,
            ['currency' => $currency]
        );
    }

    public static function paymentNotFound(string $paymentId): self
    {
        return new self(
            sprintf('Payment not found: %s', $paymentId),
            self::ERROR_PAYMENT_NOT_FOUND,
            ['payment_id' => $paymentId]
        );
    }

    public static function paymentAlreadyProcessed(string $paymentId, string $status): self
    {
        return new self(
            sprintf('Payment %s already processed with status: %s', $paymentId, $status),
            self::ERROR_PAYMENT_ALREADY_PROCESSED,
            ['payment_id' => $paymentId, 'status' => $status]
        );
    }

    public static function refundNotAllowed(string $paymentId, string $reason): self
    {
        return new self(
            sprintf('Refund not allowed for payment %s: %s', $paymentId, $reason),
            self::ERROR_REFUND_NOT_ALLOWED,
            ['payment_id' => $paymentId, 'reason' => $reason]
        );
    }

    public static function invalidWebhookSignature(): self
    {
        return new self(
            'Invalid webhook signature',
            self::ERROR_INVALID_WEBHOOK_SIGNATURE
        );
    }
}