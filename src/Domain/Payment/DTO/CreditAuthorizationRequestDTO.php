<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreditAuthorizationRequestDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $paymentId;

    #[Assert\NotBlank]
    #[Assert\Regex('/^\d+(\.\d{1,2})?$/')]
    private string $amount;

    #[Assert\NotBlank]
    #[Assert\Choice(['PLN', 'EUR', 'USD', 'GBP'])]
    private string $currency;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $description;

    #[Assert\Length(max: 255)]
    private ?string $externalReference = null;

    #[Assert\Type('integer')]
    #[Assert\GreaterThan(0)]
    private ?int $paymentTermDays = null;

    private ?array $metadata = null;

    public function __construct(array $data)
    {
        $this->paymentId = $data['payment_id'] ?? '';
        $this->amount = $data['amount'] ?? '';
        $this->currency = $data['currency'] ?? 'PLN';
        $this->description = $data['description'] ?? '';
        $this->externalReference = $data['external_reference'] ?? null;
        $this->paymentTermDays = $data['payment_term_days'] ?? null;
        $this->metadata = $data['metadata'] ?? null;
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function setPaymentId(string $paymentId): self
    {
        $this->paymentId = $paymentId;
        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function setExternalReference(?string $externalReference): self
    {
        $this->externalReference = $externalReference;
        return $this;
    }

    public function getPaymentTermDays(): ?int
    {
        return $this->paymentTermDays;
    }

    public function setPaymentTermDays(?int $paymentTermDays): self
    {
        $this->paymentTermDays = $paymentTermDays;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }
}