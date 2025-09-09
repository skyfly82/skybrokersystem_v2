<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTO;

class WalletTopUpRequestDTO
{
    public function __construct(
        private readonly string $walletNumber,
        private readonly string $amount,
        private readonly string $currency,
        private readonly string $paymentMethod,
        private readonly ?string $description = null,
        private readonly ?string $externalReference = null,
        private readonly ?string $returnUrl = null,
        private readonly ?string $notificationUrl = null,
        private readonly ?\DateTimeInterface $expiresAt = null,
        private readonly ?array $metadata = null
    ) {
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function getReturnUrl(): ?string
    {
        return $this->returnUrl;
    }

    public function getNotificationUrl(): ?string
    {
        return $this->notificationUrl;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }
}