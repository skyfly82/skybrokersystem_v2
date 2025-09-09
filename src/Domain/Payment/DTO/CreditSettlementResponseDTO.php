<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTO;

class CreditSettlementResponseDTO
{
    private bool $success;
    private string $transactionId;
    private string $status;
    private string $settledAmount;
    private string $currency;
    private ?\DateTimeImmutable $settledAt;
    private ?string $errorCode;
    private ?string $errorMessage;
    private ?array $metadata;

    public function __construct(
        bool $success,
        string $transactionId,
        string $status,
        string $settledAmount,
        string $currency,
        ?\DateTimeImmutable $settledAt = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?array $metadata = null
    ) {
        $this->success = $success;
        $this->transactionId = $transactionId;
        $this->status = $status;
        $this->settledAmount = $settledAmount;
        $this->currency = $currency;
        $this->settledAt = $settledAt;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
        $this->metadata = $metadata;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getSettledAmount(): string
    {
        return $this->settledAmount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getSettledAt(): ?\DateTimeImmutable
    {
        return $this->settledAt;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'transaction_id' => $this->transactionId,
            'status' => $this->status,
            'settled_amount' => $this->settledAmount,
            'currency' => $this->currency,
            'settled_at' => $this->settledAt?->format('Y-m-d H:i:s'),
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'metadata' => $this->metadata
        ];
    }

    public static function success(
        string $transactionId,
        string $status,
        string $settledAmount,
        string $currency,
        ?\DateTimeImmutable $settledAt = null,
        ?array $metadata = null
    ): self {
        return new self(
            true,
            $transactionId,
            $status,
            $settledAmount,
            $currency,
            $settledAt,
            null,
            null,
            $metadata
        );
    }

    public static function failure(
        string $transactionId,
        string $errorCode,
        string $errorMessage,
        ?array $metadata = null
    ): self {
        return new self(
            false,
            $transactionId,
            'failed',
            '0.00',
            'PLN',
            null,
            $errorCode,
            $errorMessage,
            $metadata
        );
    }
}