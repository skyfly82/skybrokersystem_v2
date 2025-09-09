<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTO;

class PayNowStatusResponseDTO
{
    public const STATUS_NEW = 'NEW';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_CONFIRMED = 'CONFIRMED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_ERROR = 'ERROR';

    private string $paymentId;
    private string $externalId;
    private string $status;
    private int $amount;
    private string $currency;
    private ?\DateTimeInterface $modifiedAt = null;
    private ?array $paymentMethod = null;
    private array $rawResponse;

    public function __construct(array $response)
    {
        $this->paymentId = $response['paymentId'] ?? '';
        $this->externalId = $response['externalId'] ?? '';
        $this->status = $response['status'] ?? self::STATUS_NEW;
        $this->amount = $response['amount'] ?? 0;
        $this->currency = $response['currency'] ?? 'PLN';
        $this->paymentMethod = $response['paymentMethod'] ?? null;
        $this->rawResponse = $response;

        if (isset($response['modifiedAt'])) {
            $this->modifiedAt = new \DateTimeImmutable($response['modifiedAt']);
        }
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
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

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getModifiedAt(): ?\DateTimeInterface
    {
        return $this->modifiedAt;
    }

    public function getPaymentMethod(): ?array
    {
        return $this->paymentMethod;
    }

    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isFailed(): bool
    {
        return in_array($this->status, [self::STATUS_REJECTED, self::STATUS_EXPIRED, self::STATUS_ERROR]);
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_PENDING]);
    }

    public function toArray(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'external_id' => $this->externalId,
            'status' => $this->status,
            'amount' => $this->amount,
            'amount_formatted' => $this->getAmountAsString(),
            'currency' => $this->currency,
            'modified_at' => $this->modifiedAt?->format('c'),
            'payment_method' => $this->paymentMethod,
            'raw_response' => $this->rawResponse,
        ];
    }
}