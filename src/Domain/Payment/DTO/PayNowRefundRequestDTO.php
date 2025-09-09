<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class PayNowRefundRequestDTO
{
    #[Assert\NotBlank]
    private string $paymentId;

    #[Assert\NotBlank]
    #[Assert\Regex('/^\d+(\.\d{1,2})?$/')]
    private string $amount;

    #[Assert\Length(max: 255)]
    private ?string $reason = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $externalRefundId;

    public function __construct(array $data)
    {
        $this->paymentId = $data['payment_id'] ?? '';
        $this->amount = $data['amount'] ?? '';
        $this->reason = $data['reason'] ?? null;
        $this->externalRefundId = $data['external_refund_id'] ?? '';
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

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    public function getExternalRefundId(): string
    {
        return $this->externalRefundId;
    }

    public function setExternalRefundId(string $externalRefundId): self
    {
        $this->externalRefundId = $externalRefundId;
        return $this;
    }

    public function toArray(): array
    {
        $data = [
            'paymentId' => $this->paymentId,
            'amount' => (int)((float)$this->amount * 100), // Convert to groszy/cents
            'externalRefundId' => $this->externalRefundId,
        ];

        if ($this->reason !== null) {
            $data['reason'] = $this->reason;
        }

        return $data;
    }
}