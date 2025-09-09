<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTO;

class PayNowPaymentResponseDTO
{
    private string $paymentId;
    private string $redirectUrl;
    private string $status;
    private string $externalId;
    private array $rawResponse;

    public function __construct(array $response)
    {
        $this->paymentId = $response['paymentId'] ?? '';
        $this->redirectUrl = $response['redirectUrl'] ?? '';
        $this->status = $response['status'] ?? 'NEW';
        $this->externalId = $response['externalId'] ?? '';
        $this->rawResponse = $response;
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }

    public function isSuccessful(): bool
    {
        return !empty($this->paymentId) && !empty($this->redirectUrl);
    }

    public function toArray(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'redirect_url' => $this->redirectUrl,
            'status' => $this->status,
            'external_id' => $this->externalId,
            'raw_response' => $this->rawResponse,
        ];
    }
}