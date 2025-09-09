<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTO;

class WalletTransferResponseDTO
{
    public function __construct(
        private readonly string $sourceTransactionId,
        private readonly string $destinationTransactionId,
        private readonly string $sourceWalletNumber,
        private readonly string $destinationWalletNumber,
        private readonly string $amount,
        private readonly string $currency,
        private readonly string $status,
        private readonly bool $success,
        private readonly ?string $errorCode = null,
        private readonly ?string $errorMessage = null,
        private readonly ?string $sourceRemainingBalance = null,
        private readonly ?string $destinationNewBalance = null,
        private readonly ?\DateTimeInterface $processedAt = null,
        private readonly ?array $metadata = null
    ) {
    }

    public function getSourceTransactionId(): string
    {
        return $this->sourceTransactionId;
    }

    public function getDestinationTransactionId(): string
    {
        return $this->destinationTransactionId;
    }

    public function getSourceWalletNumber(): string
    {
        return $this->sourceWalletNumber;
    }

    public function getDestinationWalletNumber(): string
    {
        return $this->destinationWalletNumber;
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

    public function getSourceRemainingBalance(): ?string
    {
        return $this->sourceRemainingBalance;
    }

    public function getDestinationNewBalance(): ?string
    {
        return $this->destinationNewBalance;
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
        string $sourceTransactionId,
        string $destinationTransactionId,
        string $sourceWalletNumber,
        string $destinationWalletNumber,
        string $amount,
        string $currency,
        string $status,
        string $sourceRemainingBalance,
        string $destinationNewBalance,
        ?\DateTimeInterface $processedAt = null,
        ?array $metadata = null
    ): self {
        return new self(
            $sourceTransactionId,
            $destinationTransactionId,
            $sourceWalletNumber,
            $destinationWalletNumber,
            $amount,
            $currency,
            $status,
            true,
            null,
            null,
            $sourceRemainingBalance,
            $destinationNewBalance,
            $processedAt,
            $metadata
        );
    }

    public static function failure(
        string $sourceWalletNumber,
        string $destinationWalletNumber,
        string $amount,
        string $currency,
        string $errorCode,
        string $errorMessage,
        ?array $metadata = null
    ): self {
        return new self(
            '',
            '',
            $sourceWalletNumber,
            $destinationWalletNumber,
            $amount,
            $currency,
            'failed',
            false,
            $errorCode,
            $errorMessage,
            null,
            null,
            null,
            $metadata
        );
    }
}