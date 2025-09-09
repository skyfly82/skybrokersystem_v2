<?php

declare(strict_types=1);

namespace App\Domain\Payment\Service;

use App\Domain\Payment\Contracts\PayNowServiceInterface;
use App\Domain\Payment\DTO\PayNowPaymentRequestDTO;
use App\Domain\Payment\DTO\PayNowPaymentResponseDTO;
use App\Domain\Payment\DTO\PayNowRefundRequestDTO;
use App\Domain\Payment\DTO\PayNowRefundResponseDTO;
use App\Domain\Payment\DTO\PayNowStatusResponseDTO;
use App\Domain\Payment\Exception\PayNowIntegrationException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class PayNowService implements PayNowServiceInterface
{
    private const SUPPORTED_CURRENCIES = ['PLN', 'EUR', 'USD', 'GBP'];
    private const CURRENCY_LIMITS = [
        'PLN' => ['min' => '1.00', 'max' => '100000.00'],
        'EUR' => ['min' => '1.00', 'max' => '25000.00'],
        'USD' => ['min' => '1.00', 'max' => '30000.00'],
        'GBP' => ['min' => '1.00', 'max' => '20000.00'],
    ];

    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $environment;
    private string $apiKey;
    private string $signatureKey;
    private string $apiUrl;
    private bool $enabled;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $environment = 'sandbox',
        string $apiKey = '',
        string $signatureKey = '',
        bool $enabled = true
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->environment = $environment;
        $this->apiKey = $apiKey;
        $this->signatureKey = $signatureKey;
        $this->enabled = $enabled;

        // Set API URL based on environment
        $this->apiUrl = $environment === 'production'
            ? 'https://api.paynow.pl/v1'
            : 'https://api.sandbox.paynow.pl/v1';
    }

    public function initializePayment(PayNowPaymentRequestDTO $request): PayNowPaymentResponseDTO
    {
        if (!$this->isEnabled()) {
            throw PayNowIntegrationException::apiConnectionError('PayNow integration is disabled');
        }

        $this->logger->info('Initializing PayNow payment', [
            'external_id' => $request->getExternalId(),
            'amount' => $request->getAmount(),
            'currency' => $request->getCurrency(),
        ]);

        // Validate request data
        $this->validatePaymentRequest($request);

        try {
            $payload = $request->toArray();
            $signature = $this->generateSignature($payload);

            $response = $this->httpClient->request('POST', $this->apiUrl . '/payments', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Api-Key' => $this->apiKey,
                    'Signature' => $signature,
                    'Idempotency-Key' => $request->getExternalId(),
                ],
                'json' => $payload,
                'timeout' => 30,
            ]);

            $responseData = $response->toArray();

            if ($response->getStatusCode() !== 201) {
                throw PayNowIntegrationException::apiConnectionError(
                    'Failed to initialize payment: ' . ($responseData['message'] ?? 'Unknown error')
                );
            }

            $paymentResponse = new PayNowPaymentResponseDTO($responseData);

            $this->logger->info('PayNow payment initialized successfully', [
                'external_id' => $request->getExternalId(),
                'payment_id' => $paymentResponse->getPaymentId(),
                'status' => $paymentResponse->getStatus(),
            ]);

            return $paymentResponse;

        } catch (TransportExceptionInterface | ClientExceptionInterface | ServerExceptionInterface $e) {
            $this->logger->error('PayNow API request failed', [
                'external_id' => $request->getExternalId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw PayNowIntegrationException::apiConnectionError(
                'Failed to connect to PayNow API: ' . $e->getMessage(),
                $e
            );
        }
    }

    public function getPaymentStatus(string $paymentId): PayNowStatusResponseDTO
    {
        if (!$this->isEnabled()) {
            throw PayNowIntegrationException::apiConnectionError('PayNow integration is disabled');
        }

        $this->logger->info('Getting PayNow payment status', ['payment_id' => $paymentId]);

        try {
            $response = $this->httpClient->request('GET', $this->apiUrl . '/payments/' . $paymentId, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Api-Key' => $this->apiKey,
                ],
                'timeout' => 30,
            ]);

            $responseData = $response->toArray();

            if ($response->getStatusCode() !== 200) {
                if ($response->getStatusCode() === 404) {
                    throw PayNowIntegrationException::paymentNotFound($paymentId);
                }

                throw PayNowIntegrationException::apiConnectionError(
                    'Failed to get payment status: ' . ($responseData['message'] ?? 'Unknown error')
                );
            }

            $statusResponse = new PayNowStatusResponseDTO($responseData);

            $this->logger->info('PayNow payment status retrieved', [
                'payment_id' => $paymentId,
                'status' => $statusResponse->getStatus(),
            ]);

            return $statusResponse;

        } catch (TransportExceptionInterface | ClientExceptionInterface | ServerExceptionInterface $e) {
            $this->logger->error('PayNow API request failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            throw PayNowIntegrationException::apiConnectionError(
                'Failed to connect to PayNow API: ' . $e->getMessage(),
                $e
            );
        }
    }

    public function refundPayment(PayNowRefundRequestDTO $request): PayNowRefundResponseDTO
    {
        if (!$this->isEnabled()) {
            throw PayNowIntegrationException::apiConnectionError('PayNow integration is disabled');
        }

        $this->logger->info('Processing PayNow refund', [
            'payment_id' => $request->getPaymentId(),
            'amount' => $request->getAmount(),
            'external_refund_id' => $request->getExternalRefundId(),
        ]);

        try {
            $payload = $request->toArray();
            $signature = $this->generateSignature($payload);

            $response = $this->httpClient->request('POST', $this->apiUrl . '/payments/' . $request->getPaymentId() . '/refunds', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Api-Key' => $this->apiKey,
                    'Signature' => $signature,
                    'Idempotency-Key' => $request->getExternalRefundId(),
                ],
                'json' => $payload,
                'timeout' => 30,
            ]);

            $responseData = $response->toArray();

            if ($response->getStatusCode() !== 201) {
                throw PayNowIntegrationException::refundNotAllowed(
                    $request->getPaymentId(),
                    $responseData['message'] ?? 'Unknown error'
                );
            }

            $refundResponse = new PayNowRefundResponseDTO($responseData);

            $this->logger->info('PayNow refund processed successfully', [
                'payment_id' => $request->getPaymentId(),
                'refund_id' => $refundResponse->getRefundId(),
            ]);

            return $refundResponse;

        } catch (TransportExceptionInterface | ClientExceptionInterface | ServerExceptionInterface $e) {
            $this->logger->error('PayNow refund API request failed', [
                'payment_id' => $request->getPaymentId(),
                'error' => $e->getMessage(),
            ]);

            throw PayNowIntegrationException::apiConnectionError(
                'Failed to connect to PayNow API: ' . $e->getMessage(),
                $e
            );
        }
    }

    public function verifyWebhookSignature(string $payload, string $signature, string $timestamp): bool
    {
        if (!$this->signatureKey) {
            $this->logger->warning('PayNow signature key not configured, skipping webhook verification');
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $timestamp . $payload, $this->signatureKey);

        return hash_equals($expectedSignature, $signature);
    }

    public function processWebhookNotification(array $webhookData): PayNowStatusResponseDTO
    {
        $this->logger->info('Processing PayNow webhook notification', [
            'payment_id' => $webhookData['paymentId'] ?? 'unknown',
            'status' => $webhookData['status'] ?? 'unknown',
        ]);

        return new PayNowStatusResponseDTO($webhookData);
    }

    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->apiKey);
    }

    public function validateAmount(string $amount, string $currency = 'PLN'): bool
    {
        if (!in_array($currency, self::SUPPORTED_CURRENCIES)) {
            return false;
        }

        $numericAmount = (float)$amount;
        $limits = self::CURRENCY_LIMITS[$currency];

        return $numericAmount >= (float)$limits['min'] && $numericAmount <= (float)$limits['max'];
    }

    public function getSupportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    public function getMinimumAmount(string $currency = 'PLN'): string
    {
        return self::CURRENCY_LIMITS[$currency]['min'] ?? '1.00';
    }

    public function getMaximumAmount(string $currency = 'PLN'): string
    {
        return self::CURRENCY_LIMITS[$currency]['max'] ?? '100000.00';
    }

    private function validatePaymentRequest(PayNowPaymentRequestDTO $request): void
    {
        if (!$this->validateAmount($request->getAmount(), $request->getCurrency())) {
            throw PayNowIntegrationException::invalidAmount(
                $request->getAmount(),
                $request->getCurrency()
            );
        }

        if (!in_array($request->getCurrency(), $this->getSupportedCurrencies())) {
            throw PayNowIntegrationException::invalidCurrency($request->getCurrency());
        }
    }

    private function generateSignature(array $payload): string
    {
        if (!$this->signatureKey) {
            return '';
        }

        $dataToSign = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return base64_encode(hash_hmac('sha256', $dataToSign, $this->signatureKey, true));
    }
}