<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exception;

/**
 * Exception thrown by PaymentHandler when payment processing fails
 */
class PaymentHandlerException extends \Exception
{
    private ?string $paymentId = null;
    private ?string $paymentMethod = null;
    private ?array $context = null;

    public static function unsupportedPaymentMethod(string $paymentMethod, string $paymentId): self
    {
        $exception = new self(
            sprintf('Unsupported payment method "%s" for payment "%s"', $paymentMethod, $paymentId)
        );
        $exception->paymentMethod = $paymentMethod;
        $exception->paymentId = $paymentId;

        return $exception;
    }

    public static function webhookProcessingFailed(string $paymentId, string $reason, ?\Throwable $previous = null): self
    {
        $exception = new self(
            sprintf('Failed to process webhook for payment "%s": %s', $paymentId, $reason),
            0,
            $previous
        );
        $exception->paymentId = $paymentId;

        return $exception;
    }

    public static function paymentNotFound(string $identifier): self
    {
        $exception = new self(
            sprintf('Payment not found with identifier "%s"', $identifier)
        );
        $exception->paymentId = $identifier;

        return $exception;
    }

    public static function statusUpdateFailed(string $paymentId, string $paymentMethod, string $reason, ?\Throwable $previous = null): self
    {
        $exception = new self(
            sprintf('Failed to update status for %s payment "%s": %s', $paymentMethod, $paymentId, $reason),
            0,
            $previous
        );
        $exception->paymentId = $paymentId;
        $exception->paymentMethod = $paymentMethod;

        return $exception;
    }

    public static function invalidWebhookData(string $reason, ?array $webhookData = null): self
    {
        $exception = new self(
            sprintf('Invalid webhook data: %s', $reason)
        );
        $exception->context = $webhookData;

        return $exception;
    }

    public function getPaymentId(): ?string
    {
        return $this->paymentId;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'payment_id' => $this->paymentId,
            'payment_method' => $this->paymentMethod,
            'context' => $this->context,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }
}