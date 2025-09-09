<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTO;

class CreditAuthorizationResponseDTO
{
    private bool $success;
    private string $transactionId;
    private string $paymentId;
    private string $amount;
    private string $currency;
    private string $status;
    private ?\DateTimeImmutable $dueDate;
    private ?string $errorCode;
    private ?string $errorMessage;
    private ?array $metadata;

    public function __construct(
        bool $success,
        string $transactionId,
        string $paymentId,
        string $amount,
        string $currency,
        string $status,
        ?\DateTimeImmutable $dueDate = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?array $metadata = null
    ) {
        $this->success = $success;
        $this->transactionId = $transactionId;
        $this->paymentId = $paymentId;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->status = $status;
        $this->dueDate = $dueDate;
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

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
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
            'payment_id' => $this->paymentId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'due_date' => $this->dueDate?->format('Y-m-d H:i:s'),
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'metadata' => $this->metadata
        ];
    }

    public static function success(
        string $transactionId,
        string $paymentId,
        string $amount,
        string $currency,
        string $status,
        ?\DateTimeImmutable $dueDate = null,
        ?array $metadata = null
    ): self {
        return new self(
            true,
            $transactionId,
            $paymentId,
            $amount,
            $currency,
            $status,
            $dueDate,
            null,
            null,
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
            false,
            '',
            $paymentId,
            $amount,
            $currency,
            'failed',
            null,
            $errorCode,
            $errorMessage,
            $metadata
        );
    }
}