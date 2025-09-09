<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTO;

class WalletTransferRequestDTO
{
    public function __construct(
        private readonly string $sourceWalletNumber,
        private readonly string $destinationWalletNumber,
        private readonly string $amount,
        private readonly string $currency,
        private readonly ?string $description = null,
        private readonly ?string $externalReference = null,
        private readonly ?array $metadata = null
    ) {
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