<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTO;

class PayNowRefundResponseDTO
{
    private string $refundId;
    private string $externalRefundId;
    private string $status;
    private int $amount;
    private array $rawResponse;

    public function __construct(array $response)
    {
        $this->refundId = $response['refundId'] ?? '';
        $this->externalRefundId = $response['externalRefundId'] ?? '';
        $this->status = $response['status'] ?? 'NEW';
        $this->amount = $response['amount'] ?? 0;
        $this->rawResponse = $response;
    }

    public function getRefundId(): string
    {
        return $this->refundId;
    }

    public function getExternalRefundId(): string
    {
        return $this->externalRefundId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getAmountAsString(): string
    {
        return number_format($this->amount / 100, 2, '.', '');
    }

    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }

    public function isSuccessful(): bool
    {
        return !empty($this->refundId);
    }

    public function toArray(): array
    {
        return [
            'refund_id' => $this->refundId,
            'external_refund_id' => $this->externalRefundId,
            'status' => $this->status,
            'amount' => $this->amount,
            'amount_formatted' => $this->getAmountAsString(),
            'raw_response' => $this->rawResponse,
        ];
    }
}