<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTO;

class WalletTopUpResponseDTO
{
    public function __construct(
        private readonly string $topUpId,
        private readonly string $walletNumber,
        private readonly string $amount,
        private readonly string $currency,
        private readonly string $paymentMethod,
        private readonly string $status,
        private readonly bool $success,
        private readonly ?string $paymentUrl = null,
        private readonly ?string $errorCode = null,
        private readonly ?string $errorMessage = null,
        private readonly ?\DateTimeInterface $expiresAt = null,
        private readonly ?\DateTimeInterface $processedAt = null,
        private readonly ?array $metadata = null
    ) {
    }

    public function getTopUpId(): string
    {
        return $this->topUpId;
    }

    public function getWalletNumber(): string
    {
        return $this->walletNumber;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getPaymentUrl(): ?string
    {
        return $this->paymentUrl;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
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
        string $topUpId,
        string $walletNumber,
        string $amount,
        string $currency,
        string $paymentMethod,
        string $status,
        ?string $paymentUrl = null,
        ?\DateTimeInterface $expiresAt = null,
        ?\DateTimeInterface $processedAt = null,
        ?array $metadata = null
    ): self {
        return new self(
            $topUpId,
            $walletNumber,
            $amount,
            $currency,
            $paymentMethod,
            $status,
            true,
            $paymentUrl,
            null,
            null,
            $expiresAt,
            $processedAt,
            $metadata
        );
    }

    public static function failure(
        string $walletNumber,
        string $amount,
        string $currency,
        string $paymentMethod,
        string $errorCode,
        string $errorMessage,
        ?array $metadata = null
    ): self {
        return new self(
            '',
            $walletNumber,
            $amount,
            $currency,
            $paymentMethod,
            'failed',
            false,
            null,
            $errorCode,
            $errorMessage,
            null,
            null,
            $metadata
        );
    }
}