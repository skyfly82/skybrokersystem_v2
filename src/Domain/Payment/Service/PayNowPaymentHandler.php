<?php

declare(strict_types=1);

namespace App\Domain\Payment\Service;

use App\Domain\Payment\Contracts\PayNowServiceInterface;
use App\Domain\Payment\DTO\PayNowPaymentRequestDTO;
use App\Domain\Payment\DTO\PayNowRefundRequestDTO;
use App\Domain\Payment\Entity\Payment;
use App\Domain\Payment\Exception\PayNowIntegrationException;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PayNowPaymentHandler
{
    private PayNowServiceInterface $payNowService;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(
        PayNowServiceInterface $payNowService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->payNowService = $payNowService;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Create and process PayNow payment
     */
    public function createPayment(
        User $user,
        string $amount,
        string $currency = 'PLN',
        string $description = '',
        ?array $metadata = null
    ): Payment {
        $this->logger->info('Creating PayNow payment', [
            'user_id' => $user->getId(),
            'amount' => $amount,
            'currency' => $currency,
        ]);

        // Create Payment entity
        $payment = new Payment();
        $payment->setUser($user)
            ->setPaymentMethod(Payment::METHOD_PAYNOW)
            ->setAmount($amount)
            ->setCurrency($currency)
            ->setDescription($description ?: sprintf('Payment for user #%d', $user->getId()))
            ->setMetadata($metadata);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        try {
            // Prepare PayNow payment request
            $paymentRequest = new PayNowPaymentRequestDTO([
                'external_id' => $payment->getPaymentId(),
                'amount' => $amount,
                'currency' => $currency,
                'description' => $payment->getDescription(),
                'continue_url' => $this->generateContinueUrl($payment->getPaymentId()),
                'notify_url' => $this->generateNotifyUrl($payment->getPaymentId()),
                'buyer_email' => $user->getEmail(),
                'buyer_first_name' => $user->getFirstName() ?? null,
                'buyer_last_name' => $user->getLastName() ?? null,
            ]);

            // Initialize payment with PayNow
            $payNowResponse = $this->payNowService->initializePayment($paymentRequest);

            // Update payment with PayNow response data
            $payment->setExternalTransactionId($payNowResponse->getPaymentId())
                ->setStatus(Payment::STATUS_PROCESSING)
                ->setGatewayResponse($payNowResponse->getRawResponse());

            // Add redirect URL to metadata
            $updatedMetadata = $metadata ?? [];
            $updatedMetadata['paynow_redirect_url'] = $payNowResponse->getRedirectUrl();
            $payment->setMetadata($updatedMetadata);

            $this->entityManager->flush();

            $this->logger->info('PayNow payment created successfully', [
                'payment_id' => $payment->getPaymentId(),
                'paynow_payment_id' => $payNowResponse->getPaymentId(),
                'redirect_url' => $payNowResponse->getRedirectUrl(),
            ]);

            return $payment;

        } catch (PayNowIntegrationException $e) {
            $this->logger->error('Failed to create PayNow payment', [
                'payment_id' => $payment->getPaymentId(),
                'error' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
                'error_details' => $e->getErrorDetails(),
            ]);

            // Update payment status to failed
            $payment->setStatus(Payment::STATUS_FAILED)
                ->setGatewayResponse([
                    'error' => $e->getMessage(),
                    'error_code' => $e->getErrorCode(),
                    'error_details' => $e->getErrorDetails(),
                ]);

            $this->entityManager->flush();

            throw $e;
        }
    }

    /**
     * Update payment status based on PayNow status
     */
    public function updatePaymentStatus(Payment $payment): Payment
    {
        if (!$payment->isPayNow() || !$payment->getExternalTransactionId()) {
            throw new \InvalidArgumentException('Payment is not a PayNow payment or missing external transaction ID');
        }

        $this->logger->info('Updating PayNow payment status', [
            'payment_id' => $payment->getPaymentId(),
            'paynow_payment_id' => $payment->getExternalTransactionId(),
        ]);

        try {
            $statusResponse = $this->payNowService->getPaymentStatus($payment->getExternalTransactionId());

            // Map PayNow status to our payment status
            $newStatus = $this->mapPayNowStatusToPaymentStatus($statusResponse->getStatus());
            $oldStatus = $payment->getStatus();

            if ($newStatus !== $oldStatus) {
                $payment->setStatus($newStatus);

                // Update gateway response with latest data
                $payment->setGatewayResponse(array_merge(
                    $payment->getGatewayResponse() ?? [],
                    $statusResponse->getRawResponse()
                ));

                $this->entityManager->flush();

                $this->logger->info('Payment status updated', [
                    'payment_id' => $payment->getPaymentId(),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'paynow_status' => $statusResponse->getStatus(),
                ]);
            }

            return $payment;

        } catch (PayNowIntegrationException $e) {
            $this->logger->error('Failed to update PayNow payment status', [
                'payment_id' => $payment->getPaymentId(),
                'paynow_payment_id' => $payment->getExternalTransactionId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process refund for PayNow payment
     */
    public function refundPayment(Payment $payment, string $amount, ?string $reason = null): void
    {
        if (!$payment->isPayNow() || !$payment->getExternalTransactionId()) {
            throw new \InvalidArgumentException('Payment is not a PayNow payment or missing external transaction ID');
        }

        if (!$payment->isCompleted()) {
            throw PayNowIntegrationException::refundNotAllowed(
                $payment->getPaymentId(),
                'Payment must be completed to process refund'
            );
        }

        $this->logger->info('Processing PayNow payment refund', [
            'payment_id' => $payment->getPaymentId(),
            'paynow_payment_id' => $payment->getExternalTransactionId(),
            'refund_amount' => $amount,
        ]);

        try {
            $refundRequest = new PayNowRefundRequestDTO([
                'payment_id' => $payment->getExternalTransactionId(),
                'amount' => $amount,
                'reason' => $reason,
                'external_refund_id' => 'REF_' . $payment->getPaymentId() . '_' . time(),
            ]);

            $refundResponse = $this->payNowService->refundPayment($refundRequest);

            // Update payment status to refunded if full refund
            if ((float)$amount >= (float)$payment->getAmount()) {
                $payment->setStatus(Payment::STATUS_REFUNDED);
            }

            // Add refund information to metadata
            $metadata = $payment->getMetadata() ?? [];
            $metadata['refunds'][] = [
                'refund_id' => $refundResponse->getRefundId(),
                'external_refund_id' => $refundResponse->getExternalRefundId(),
                'amount' => $amount,
                'reason' => $reason,
                'processed_at' => (new \DateTimeImmutable())->format('c'),
                'raw_response' => $refundResponse->getRawResponse(),
            ];
            $payment->setMetadata($metadata);

            $this->entityManager->flush();

            $this->logger->info('PayNow payment refund processed successfully', [
                'payment_id' => $payment->getPaymentId(),
                'refund_id' => $refundResponse->getRefundId(),
                'refund_amount' => $amount,
            ]);

        } catch (PayNowIntegrationException $e) {
            $this->logger->error('Failed to process PayNow payment refund', [
                'payment_id' => $payment->getPaymentId(),
                'paynow_payment_id' => $payment->getExternalTransactionId(),
                'refund_amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle PayNow webhook notification
     */
    public function handleWebhookNotification(array $webhookData, string $signature, string $timestamp): ?Payment
    {
        // Verify webhook signature
        $payload = json_encode($webhookData);
        if (!$this->payNowService->verifyWebhookSignature($payload, $signature, $timestamp)) {
            $this->logger->warning('Invalid PayNow webhook signature', [
                'webhook_data' => $webhookData,
            ]);
            throw PayNowIntegrationException::invalidWebhookSignature();
        }

        $externalId = $webhookData['externalId'] ?? null;
        if (!$externalId) {
            $this->logger->warning('PayNow webhook missing external ID', [
                'webhook_data' => $webhookData,
            ]);
            return null;
        }

        // Find payment by external ID
        $payment = $this->entityManager->getRepository(Payment::class)
            ->findOneBy(['paymentId' => $externalId]);

        if (!$payment) {
            $this->logger->warning('Payment not found for PayNow webhook', [
                'external_id' => $externalId,
                'webhook_data' => $webhookData,
            ]);
            return null;
        }

        $this->logger->info('Processing PayNow webhook notification', [
            'payment_id' => $payment->getPaymentId(),
            'paynow_payment_id' => $webhookData['paymentId'] ?? 'unknown',
            'webhook_status' => $webhookData['status'] ?? 'unknown',
        ]);

        try {
            $statusResponse = $this->payNowService->processWebhookNotification($webhookData);

            // Update payment status
            $newStatus = $this->mapPayNowStatusToPaymentStatus($statusResponse->getStatus());
            $oldStatus = $payment->getStatus();

            if ($newStatus !== $oldStatus) {
                $payment->setStatus($newStatus);

                // Update gateway response with webhook data
                $payment->setGatewayResponse(array_merge(
                    $payment->getGatewayResponse() ?? [],
                    $statusResponse->getRawResponse()
                ));

                $this->entityManager->flush();

                $this->logger->info('Payment status updated from webhook', [
                    'payment_id' => $payment->getPaymentId(),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'webhook_status' => $statusResponse->getStatus(),
                ]);
            }

            return $payment;

        } catch (PayNowIntegrationException $e) {
            $this->logger->error('Failed to process PayNow webhook', [
                'payment_id' => $payment->getPaymentId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function mapPayNowStatusToPaymentStatus(string $payNowStatus): string
    {
        return match ($payNowStatus) {
            'NEW', 'PENDING' => Payment::STATUS_PROCESSING,
            'CONFIRMED' => Payment::STATUS_COMPLETED,
            'REJECTED', 'EXPIRED', 'ERROR' => Payment::STATUS_FAILED,
            default => Payment::STATUS_PENDING,
        };
    }

    private function generateContinueUrl(string $paymentId): string
    {
        return $this->urlGenerator->generate('payment_success', [
            'paymentId' => $paymentId,
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function generateNotifyUrl(string $paymentId): string
    {
        return $this->urlGenerator->generate('paynow_webhook', [
            'paymentId' => $paymentId,
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}