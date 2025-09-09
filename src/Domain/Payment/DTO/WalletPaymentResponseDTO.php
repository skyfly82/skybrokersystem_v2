<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTO;

class WalletPaymentResponseDTO
{
    public function __construct(
        private readonly string $transactionId,
        private readonly string $paymentId,
        private readonly string $amount,
        private readonly string $currency,
        private readonly string $status,
        private readonly bool $success,
        private readonly ?string $errorCode = null,
        private readonly ?string $errorMessage = null,
        private readonly ?string $remainingBalance = null,
        private readonly ?\DateTimeInterface $processedAt = null,
        private readonly ?array $metadata = null
    ) {
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getRemainingBalance(): ?string
    {
        return $this->remainingBalance;
    }

    public function getProcessedAt(): ?\DateTimeInterface
    {
        return $this->processedAt;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public static function success(
        string $transactionId,
        string $paymentId,
        string $amount,
        string $currency,
        string $status,
        string $remainingBalance,
        ?\DateTimeInterface $processedAt = null,
        ?array $metadata = null
    ): self {
        return new self(
            $transactionId,
            $paymentId,
            $amount,
            $currency,
            $status,
            true,
            null,
            null,
            $remainingBalance,
            $processedAt,
            $metadata
        );
    }

    public static function failure(
        string $paymentId,
        string $amount,
        string $currency,
        string $errorCode,
        string $errorMessage,
        ?array $metadata = null
    ): self {
        return new self(
            '',
            $paymentId,
            $amount,
            $currency,
            'failed',
            false,
            $errorCode,
            $errorMessage,
            null,
            null,
            $metadata
        );
    }
}