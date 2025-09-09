<?php

declare(strict_types=1);

namespace App\Domain\Payment\Service;

use App\Domain\Payment\DTO\PayNowStatusResponseDTO;
use App\Domain\Payment\Entity\Payment;
use App\Domain\Payment\Exception\PaymentHandlerException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Main payment handler that coordinates different payment methods
 */
class PaymentHandler
{
    private PayNowPaymentHandler $payNowHandler;
    private CreditPaymentHandler $creditHandler;
    private WalletPaymentHandler $walletHandler;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        PayNowPaymentHandler $payNowHandler,
        CreditPaymentHandler $creditHandler,
        WalletPaymentHandler $walletHandler,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->payNowHandler = $payNowHandler;
        $this->creditHandler = $creditHandler;
        $this->walletHandler = $walletHandler;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Update payment status from webhook notification
     */
    public function updatePaymentStatusFromWebhook(PayNowStatusResponseDTO $statusResponse): ?Payment
    {
        $this->logger->info('Processing payment status update from webhook', [
            'payment_id' => $statusResponse->getPaymentId(),
            'external_id' => $statusResponse->getExternalId(),
            'status' => $statusResponse->getStatus(),
        ]);

        try {
            // Find payment by external ID (our payment ID)
            $payment = $this->findPaymentByExternalId($statusResponse->getExternalId());
            
            if (!$payment) {
                $this->logger->warning('Payment not found for webhook status update', [
                    'external_id' => $statusResponse->getExternalId(),
                    'paynow_payment_id' => $statusResponse->getPaymentId(),
                ]);
                return null;
            }

            // Route to appropriate handler based on payment method
            switch ($payment->getPaymentMethod()) {
                case Payment::METHOD_PAYNOW:
                    return $this->updatePayNowPaymentStatus($payment, $statusResponse);
                
                case Payment::METHOD_CREDIT:
                    return $this->updateCreditPaymentStatus($payment, $statusResponse);
                
                case Payment::METHOD_WALLET:
                    return $this->updateWalletPaymentStatus($payment, $statusResponse);
                
                case Payment::METHOD_SIMULATOR:
                    return $this->updateSimulatorPaymentStatus($payment, $statusResponse);
                
                default:
                    throw PaymentHandlerException::unsupportedPaymentMethod(
                        $payment->getPaymentMethod(),
                        $payment->getPaymentId()
                    );
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to update payment status from webhook', [
                'payment_id' => $statusResponse->getPaymentId(),
                'external_id' => $statusResponse->getExternalId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw PaymentHandlerException::webhookProcessingFailed(
                $statusResponse->getExternalId(),
                $e->getMessage(),
                $e
            );
        }
    }

    /**
     * Find payment by various identifiers
     */
    public function findPayment(string $identifier): ?Payment
    {
        $payment = $this->entityManager->getRepository(Payment::class)
            ->findOneBy(['paymentId' => $identifier]);

        if (!$payment) {
            $payment = $this->entityManager->getRepository(Payment::class)
                ->findOneBy(['externalTransactionId' => $identifier]);
        }

        if (!$payment) {
            $payment = $this->entityManager->getRepository(Payment::class)
                ->find((int)$identifier);
        }

        return $payment;
    }

    /**
     * Get payment status for any payment method
     */
    public function getPaymentStatus(string $paymentId): ?array
    {
        $payment = $this->findPayment($paymentId);
        
        if (!$payment) {
            return null;
        }

        switch ($payment->getPaymentMethod()) {
            case Payment::METHOD_PAYNOW:
                return $this->getPayNowPaymentStatus($payment);
            
            case Payment::METHOD_CREDIT:
                return $this->getCreditPaymentStatus($payment);
            
            case Payment::METHOD_WALLET:
                return $this->getWalletPaymentStatus($payment);
            
            case Payment::METHOD_SIMULATOR:
                return $this->getSimulatorPaymentStatus($payment);
            
            default:
                return [
                    'payment_id' => $payment->getPaymentId(),
                    'status' => $payment->getStatus(),
                    'method' => $payment->getPaymentMethod(),
                    'amount' => $payment->getAmount(),
                    'currency' => $payment->getCurrency(),
                    'created_at' => $payment->getCreatedAt()->format('c'),
                ];
        }
    }

    private function findPaymentByExternalId(string $externalId): ?Payment
    {
        return $this->entityManager->getRepository(Payment::class)
            ->findOneBy(['paymentId' => $externalId]);
    }

    private function updatePayNowPaymentStatus(Payment $payment, PayNowStatusResponseDTO $statusResponse): Payment
    {
        $this->logger->info('Updating PayNow payment status', [
            'payment_id' => $payment->getPaymentId(),
            'old_status' => $payment->getStatus(),
            'new_paynow_status' => $statusResponse->getStatus(),
        ]);

        // Map PayNow status to our payment status
        $newStatus = $this->mapPayNowStatusToPaymentStatus($statusResponse->getStatus());
        $oldStatus = $payment->getStatus();

        if ($newStatus !== $oldStatus) {
            $payment->setStatus($newStatus);

            // Update gateway response with webhook data
            $payment->setGatewayResponse(array_merge(
                $payment->getGatewayResponse() ?? [],
                $statusResponse->getRawResponse()
            ));

            // Set external transaction ID if not set
            if (!$payment->getExternalTransactionId() && $statusResponse->getPaymentId()) {
                $payment->setExternalTransactionId($statusResponse->getPaymentId());
            }

            $this->entityManager->flush();

            $this->logger->info('PayNow payment status updated', [
                'payment_id' => $payment->getPaymentId(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
        }

        return $payment;
    }

    private function updateCreditPaymentStatus(Payment $payment, PayNowStatusResponseDTO $statusResponse): Payment
    {
        $this->logger->info('Credit payment status update requested but not yet implemented', [
            'payment_id' => $payment->getPaymentId(),
        ]);

        // Credit payments are typically handled internally, webhooks might not be applicable
        // This is a placeholder for future implementation
        return $payment;
    }

    private function updateWalletPaymentStatus(Payment $payment, PayNowStatusResponseDTO $statusResponse): Payment
    {
        $this->logger->info('Wallet payment status update requested but not yet implemented', [
            'payment_id' => $payment->getPaymentId(),
        ]);

        // Wallet payments are typically handled internally, webhooks might not be applicable
        // This is a placeholder for future implementation
        return $payment;
    }

    private function updateSimulatorPaymentStatus(Payment $payment, PayNowStatusResponseDTO $statusResponse): Payment
    {
        $this->logger->info('Updating simulator payment status', [
            'payment_id' => $payment->getPaymentId(),
            'old_status' => $payment->getStatus(),
        ]);

        // For simulator, we can directly map the status
        $newStatus = $this->mapPayNowStatusToPaymentStatus($statusResponse->getStatus());
        $oldStatus = $payment->getStatus();

        if ($newStatus !== $oldStatus) {
            $payment->setStatus($newStatus);
            $payment->setGatewayResponse($statusResponse->getRawResponse());
            
            $this->entityManager->flush();

            $this->logger->info('Simulator payment status updated', [
                'payment_id' => $payment->getPaymentId(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
        }

        return $payment;
    }

    private function getPayNowPaymentStatus(Payment $payment): array
    {
        try {
            $updatedPayment = $this->payNowHandler->updatePaymentStatus($payment);
            
            return [
                'payment_id' => $updatedPayment->getPaymentId(),
                'status' => $updatedPayment->getStatus(),
                'method' => $updatedPayment->getPaymentMethod(),
                'amount' => $updatedPayment->getAmount(),
                'currency' => $updatedPayment->getCurrency(),
                'external_transaction_id' => $updatedPayment->getExternalTransactionId(),
                'gateway_response' => $updatedPayment->getGatewayResponse(),
                'created_at' => $updatedPayment->getCreatedAt()->format('c'),
                'processed_at' => $updatedPayment->getProcessedAt()?->format('c'),
                'completed_at' => $updatedPayment->getCompletedAt()?->format('c'),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get PayNow payment status', [
                'payment_id' => $payment->getPaymentId(),
                'error' => $e->getMessage(),
            ]);

            return [
                'payment_id' => $payment->getPaymentId(),
                'status' => $payment->getStatus(),
                'error' => 'Failed to fetch current status from PayNow',
            ];
        }
    }

    private function getCreditPaymentStatus(Payment $payment): array
    {
        return [
            'payment_id' => $payment->getPaymentId(),
            'status' => $payment->getStatus(),
            'method' => $payment->getPaymentMethod(),
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'created_at' => $payment->getCreatedAt()->format('c'),
            'processed_at' => $payment->getProcessedAt()?->format('c'),
            'completed_at' => $payment->getCompletedAt()?->format('c'),
            'metadata' => $payment->getMetadata(),
        ];
    }

    private function getWalletPaymentStatus(Payment $payment): array
    {
        return [
            'payment_id' => $payment->getPaymentId(),
            'status' => $payment->getStatus(),
            'method' => $payment->getPaymentMethod(),
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'created_at' => $payment->getCreatedAt()->format('c'),
            'processed_at' => $payment->getProcessedAt()?->format('c'),
            'completed_at' => $payment->getCompletedAt()?->format('c'),
            'metadata' => $payment->getMetadata(),
        ];
    }

    private function getSimulatorPaymentStatus(Payment $payment): array
    {
        return [
            'payment_id' => $payment->getPaymentId(),
            'status' => $payment->getStatus(),
            'method' => $payment->getPaymentMethod(),
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'created_at' => $payment->getCreatedAt()->format('c'),
            'processed_at' => $payment->getProcessedAt()?->format('c'),
            'completed_at' => $payment->getCompletedAt()?->format('c'),
            'gateway_response' => $payment->getGatewayResponse(),
        ];
    }

    private function mapPayNowStatusToPaymentStatus(string $payNowStatus): string
    {
        return match ($payNowStatus) {
            PayNowStatusResponseDTO::STATUS_NEW, PayNowStatusResponseDTO::STATUS_PENDING => Payment::STATUS_PROCESSING,
            PayNowStatusResponseDTO::STATUS_CONFIRMED => Payment::STATUS_COMPLETED,
            PayNowStatusResponseDTO::STATUS_REJECTED, PayNowStatusResponseDTO::STATUS_EXPIRED, PayNowStatusResponseDTO::STATUS_ERROR => Payment::STATUS_FAILED,
            default => Payment::STATUS_PENDING,
        };
    }
}