<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreditSettlementRequestDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $transactionId;

    #[Assert\Regex('/^\d+(\.\d{1,2})?$/')]
    private ?string $settleAmount = null;

    #[Assert\Length(max: 255)]
    private ?string $notes = null;

    #[Assert\Type('bool')]
    private bool $forceSettle = false;

    private ?array $metadata = null;

    public function __construct(array $data)
    {
        $this->transactionId = $data['transaction_id'] ?? '';
        $this->settleAmount = $data['settle_amount'] ?? null;
        $this->notes = $data['notes'] ?? null;
        $this->forceSettle = $data['force_settle'] ?? false;
        $this->metadata = $data['metadata'] ?? null;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function setTransactionId(string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function getSettleAmount(): ?string
    {
        return $this->settleAmount;
    }

    public function setSettleAmount(?string $settleAmount): self
    {
        $this->settleAmount = $settleAmount;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function isForceSettle(): bool
    {
        return $this->forceSettle;
    }

    public function setForceSettle(bool $forceSettle): self
    {
        $this->forceSettle = $forceSettle;
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