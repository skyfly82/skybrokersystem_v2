<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class PayNowPaymentRequestDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $externalId;

    #[Assert\NotBlank]
    #[Assert\Regex('/^\d+(\.\d{1,2})?$/')]
    private string $amount;

    #[Assert\NotBlank]
    #[Assert\Choice(['PLN', 'EUR', 'USD', 'GBP'])]
    private string $currency;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $description;

    #[Assert\NotBlank]
    #[Assert\Url]
    private string $continueUrl;

    #[Assert\NotBlank]
    #[Assert\Url]
    private string $notifyUrl;

    #[Assert\Email]
    private ?string $buyerEmail = null;

    private ?string $buyerFirstName = null;

    private ?string $buyerLastName = null;

    private ?string $buyerPhone = null;

    #[Assert\Choice(['ALL', 'PBL', 'CARD', 'BLIK'])]
    private ?string $paymentMethods = 'ALL';

    #[Assert\Type('integer')]
    #[Assert\GreaterThan(0)]
    private ?int $validityTime = null;

    private ?array $additionalData = null;

    public function __construct(array $data)
    {
        $this->externalId = $data['external_id'] ?? '';
        $this->amount = $data['amount'] ?? '';
        $this->currency = $data['currency'] ?? 'PLN';
        $this->description = $data['description'] ?? '';
        $this->continueUrl = $data['continue_url'] ?? '';
        $this->notifyUrl = $data['notify_url'] ?? '';
        $this->buyerEmail = $data['buyer_email'] ?? null;
        $this->buyerFirstName = $data['buyer_first_name'] ?? null;
        $this->buyerLastName = $data['buyer_last_name'] ?? null;
        $this->buyerPhone = $data['buyer_phone'] ?? null;
        $this->paymentMethods = $data['payment_methods'] ?? 'ALL';
        $this->validityTime = $data['validity_time'] ?? null;
        $this->additionalData = $data['additional_data'] ?? null;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): self
    {
        $this->externalId = $externalId;
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

    public function getContinueUrl(): string
    {
        return $this->continueUrl;
    }

    public function setContinueUrl(string $continueUrl): self
    {
        $this->continueUrl = $continueUrl;
        return $this;
    }

    public function getNotifyUrl(): string
    {
        return $this->notifyUrl;
    }

    public function setNotifyUrl(string $notifyUrl): self
    {
        $this->notifyUrl = $notifyUrl;
        return $this;
    }

    public function getBuyerEmail(): ?string
    {
        return $this->buyerEmail;
    }

    public function setBuyerEmail(?string $buyerEmail): self
    {
        $this->buyerEmail = $buyerEmail;
        return $this;
    }

    public function getBuyerFirstName(): ?string
    {
        return $this->buyerFirstName;
    }

    public function setBuyerFirstName(?string $buyerFirstName): self
    {
        $this->buyerFirstName = $buyerFirstName;
        return $this;
    }

    public function getBuyerLastName(): ?string
    {
        return $this->buyerLastName;
    }

    public function setBuyerLastName(?string $buyerLastName): self
    {
        $this->buyerLastName = $buyerLastName;
        return $this;
    }

    public function getBuyerPhone(): ?string
    {
        return $this->buyerPhone;
    }

    public function setBuyerPhone(?string $buyerPhone): self
    {
        $this->buyerPhone = $buyerPhone;
        return $this;
    }

    public function getPaymentMethods(): ?string
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(?string $paymentMethods): self
    {
        $this->paymentMethods = $paymentMethods;
        return $this;
    }

    public function getValidityTime(): ?int
    {
        return $this->validityTime;
    }

    public function setValidityTime(?int $validityTime): self
    {
        $this->validityTime = $validityTime;
        return $this;
    }

    public function getAdditionalData(): ?array
    {
        return $this->additionalData;
    }

    public function setAdditionalData(?array $additionalData): self
    {
        $this->additionalData = $additionalData;
        return $this;
    }

    public function toArray(): array
    {
        $data = [
            'externalId' => $this->externalId,
            'amount' => (int)((float)$this->amount * 100), // Convert to groszy/cents
            'currency' => $this->currency,
            'description' => $this->description,
            'continueUrl' => $this->continueUrl,
            'notifyUrl' => $this->notifyUrl,
        ];

        if ($this->buyerEmail !== null) {
            $data['buyer']['email'] = $this->buyerEmail;
        }

        if ($this->buyerFirstName !== null) {
            $data['buyer']['firstName'] = $this->buyerFirstName;
        }

        if ($this->buyerLastName !== null) {
            $data['buyer']['lastName'] = $this->buyerLastName;
        }

        if ($this->buyerPhone !== null) {
            $data['buyer']['phone'] = $this->buyerPhone;
        }

        if ($this->paymentMethods !== null) {
            $data['paymentMethods'] = explode(',', $this->paymentMethods);
        }

        if ($this->validityTime !== null) {
            $data['validityTime'] = $this->validityTime;
        }

        if ($this->additionalData !== null) {
            $data = array_merge($data, $this->additionalData);
        }

        return $data;
    }
}