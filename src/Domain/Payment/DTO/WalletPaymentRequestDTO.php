<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTO;

class WalletPaymentRequestDTO
{
    public function __construct(
        private readonly string $paymentId,
        private readonly string $amount,
        private readonly string $currency,
        private readonly ?string $description = null,
        private readonly ?string $externalReference = null,
        private readonly ?array $metadata = null
    ) {
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }
}