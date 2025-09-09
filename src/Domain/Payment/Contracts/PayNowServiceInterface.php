<?php

declare(strict_types=1);

namespace App\Domain\Payment\Contracts;

use App\Domain\Payment\DTO\PayNowPaymentRequestDTO;
use App\Domain\Payment\DTO\PayNowPaymentResponseDTO;
use App\Domain\Payment\DTO\PayNowRefundRequestDTO;
use App\Domain\Payment\DTO\PayNowRefundResponseDTO;
use App\Domain\Payment\DTO\PayNowStatusResponseDTO;
use App\Domain\Payment\Exception\PayNowIntegrationException;

interface PayNowServiceInterface
{
    /**
     * Initialize payment in PayNow system
     *
     * @throws PayNowIntegrationException
     */
    public function initializePayment(PayNowPaymentRequestDTO $request): PayNowPaymentResponseDTO;

    /**
     * Get payment status from PayNow
     *
     * @throws PayNowIntegrationException
     */
    public function getPaymentStatus(string $paymentId): PayNowStatusResponseDTO;

    /**
     * Process refund for completed payment
     *
     * @throws PayNowIntegrationException
     */
    public function refundPayment(PayNowRefundRequestDTO $request): PayNowRefundResponseDTO;

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $timestamp): bool;

    /**
     * Process webhook notification
     *
     * @throws PayNowIntegrationException
     */
    public function processWebhookNotification(array $webhookData): PayNowStatusResponseDTO;

    /**
     * Check if PayNow integration is enabled
     */
    public function isEnabled(): bool;

    /**
     * Validate payment amount
     */
    public function validateAmount(string $amount, string $currency = 'PLN'): bool;

    /**
     * Get supported currencies
     *
     * @return string[]
     */
    public function getSupportedCurrencies(): array;

    /**
     * Get minimum payment amount for currency
     */
    public function getMinimumAmount(string $currency = 'PLN'): string;

    /**
     * Get maximum payment amount for currency
     */
    public function getMaximumAmount(string $currency = 'PLN'): string;
}