<?php

declare(strict_types=1);

namespace App\Domain\Payment\Service;

use App\Domain\Payment\Contracts\CreditServiceInterface;
use App\Domain\Payment\DTO\CreditAuthorizationRequestDTO;
use App\Domain\Payment\DTO\CreditAuthorizationResponseDTO;
use App\Domain\Payment\DTO\CreditSettlementRequestDTO;
use App\Domain\Payment\DTO\CreditSettlementResponseDTO;
use App\Domain\Payment\Entity\Payment;
use App\Domain\Payment\Exception\CreditException;
use App\Domain\Payment\Repository\PaymentRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;

class CreditPaymentHandler
{
    public function __construct(
        private readonly CreditServiceInterface $creditService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly RouterInterface $router
    ) {
    }

    /**
     * Create and authorize a credit payment
     *
     * @throws CreditException
     */
    public function createPayment(
        User $user,
        string $amount,
        string $currency,
        string $description,
        ?string $externalReference = null,
        ?int $paymentTermDays = null,
        ?array $metadata = null
    ): array {
        $this->logger->info('Creating credit payment', [
            'user_id' => $user->getId(),
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description
        ]);

        try {
            $this->entityManager->beginTransaction();

            // Create Payment entity
            $payment = new Payment();
            $payment->setUser($user)
                   ->setPaymentMethod(Payment::METHOD_CREDIT)
                   ->setAmount($amount)
                   ->setCurrency($currency)
                   ->setDescription($description)
                   ->setExternalTransactionId($externalReference)
                   ->setMetadata($metadata)
                   ->setStatus(Payment::STATUS_PENDING);

            $this->entityManager->persist($payment);
            $this->entityManager->flush();

            // Authorize credit payment
            $authRequest = new CreditAuthorizationRequestDTO([
                'payment_id' => $payment->getPaymentId(),
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description,
                'external_reference' => $externalReference,
                'payment_term_days' => $paymentTermDays,
                'metadata' => $metadata
            ]);

            $authResponse = $this->creditService->authorizePayment($user, $authRequest);

            if (!$authResponse->isSuccess()) {
                // Authorization failed - update payment status
                $payment->setStatus(Payment::STATUS_FAILED);
                $payment->setGatewayResponse([
                    'error_code' => $authResponse->getErrorCode(),
                    'error_message' => $authResponse->getErrorMessage(),
                    'response_data' => $authResponse->toArray()
                ]);

                $this->entityManager->flush();
                $this->entityManager->commit();

                $this->logger->warning('Credit payment authorization failed', [
                    'payment_id' => $payment->getPaymentId(),
                    'error_code' => $authResponse->getErrorCode(),
                    'error_message' => $authResponse->getErrorMessage()
                ]);

                return [
                    'success' => false,
                    'payment_id' => $payment->getPaymentId(),
                    'transaction_id' => null,
                    'status' => Payment::STATUS_FAILED,
                    'error_code' => $authResponse->getErrorCode(),
                    'error_message' => $authResponse->getErrorMessage(),
                    'due_date' => null,
                    'metadata' => $authResponse->getMetadata()
                ];
            }

            // Authorization successful - update payment
            $payment->setStatus(Payment::STATUS_PROCESSING);
            $payment->setExternalTransactionId($authResponse->getTransactionId());
            $payment->setGatewayResponse($authResponse->toArray());

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Credit payment created and authorized successfully', [
                'payment_id' => $payment->getPaymentId(),
                'transaction_id' => $authResponse->getTransactionId(),
                'due_date' => $authResponse->getDueDate()?->format('Y-m-d')
            ]);

            return [
                'success' => true,
                'payment_id' => $payment->getPaymentId(),
                'transaction_id' => $authResponse->getTransactionId(),
                'status' => Payment::STATUS_PROCESSING,
                'due_date' => $authResponse->getDueDate(),
                'amount' => $amount,
                'currency' => $currency,
                'metadata' => $authResponse->getMetadata()
            ];

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Failed to create credit payment', [
                'user_id' => $user->getId(),
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            if ($e instanceof CreditException) {
                throw $e;
            }

            throw CreditException::authorizationFailed(
                'Failed to create credit payment',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Settle an authorized credit payment
     *
     * @throws CreditException
     */
    public function settlePayment(
        string $paymentId,
        ?string $settleAmount = null,
        ?string $notes = null,
        bool $forceSettle = false
    ): array {
        $this->logger->info('Settling credit payment', [
            'payment_id' => $paymentId,
            'settle_amount' => $settleAmount,
            'force_settle' => $forceSettle
        ]);

        $paymentRepository = $this->entityManager->getRepository(Payment::class);
        $payment = $paymentRepository->findByPaymentId($paymentId);

        if ($payment === null) {
            throw CreditException::transactionNotFound($paymentId);
        }

        if (!$payment->isCredit()) {
            throw CreditException::settlementFailed(
                $paymentId,
                'Payment is not a credit payment'
            );
        }

        if (!$payment->isProcessing()) {
            throw CreditException::transactionAlreadyProcessed($paymentId, $payment->getStatus());
        }

        $transactionId = $payment->getExternalTransactionId();
        if (!$transactionId) {
            throw CreditException::settlementFailed(
                $paymentId,
                'No credit transaction ID found for payment'
            );
        }

        try {
            $this->entityManager->beginTransaction();

            // Settle the credit transaction
            $settlementRequest = new CreditSettlementRequestDTO([
                'transaction_id' => $transactionId,
                'settle_amount' => $settleAmount,
                'notes' => $notes,
                'force_settle' => $forceSettle
            ]);

            $settlementResponse = $this->creditService->settlePayment($settlementRequest);

            if (!$settlementResponse->isSuccess()) {
                $payment->setStatus(Payment::STATUS_FAILED);
                $gatewayResponse = $payment->getGatewayResponse() ?? [];
                $gatewayResponse['settlement_error'] = [
                    'error_code' => $settlementResponse->getErrorCode(),
                    'error_message' => $settlementResponse->getErrorMessage(),
                    'attempted_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
                ];
                $payment->setGatewayResponse($gatewayResponse);

                $this->entityManager->flush();
                $this->entityManager->commit();

                $this->logger->warning('Credit payment settlement failed', [
                    'payment_id' => $paymentId,
                    'transaction_id' => $transactionId,
                    'error_code' => $settlementResponse->getErrorCode(),
                    'error_message' => $settlementResponse->getErrorMessage()
                ]);

                return [
                    'success' => false,
                    'payment_id' => $paymentId,
                    'transaction_id' => $transactionId,
                    'status' => Payment::STATUS_FAILED,
                    'error_code' => $settlementResponse->getErrorCode(),
                    'error_message' => $settlementResponse->getErrorMessage(),
                    'settled_amount' => '0.00',
                    'settled_at' => null
                ];
            }

            // Settlement successful
            $payment->setStatus(Payment::STATUS_COMPLETED);
            $gatewayResponse = $payment->getGatewayResponse() ?? [];
            $gatewayResponse['settlement_response'] = $settlementResponse->toArray();
            $payment->setGatewayResponse($gatewayResponse);

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Credit payment settled successfully', [
                'payment_id' => $paymentId,
                'transaction_id' => $transactionId,
                'settled_amount' => $settlementResponse->getSettledAmount(),
                'settled_at' => $settlementResponse->getSettledAt()?->format('Y-m-d H:i:s')
            ]);

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'transaction_id' => $transactionId,
                'status' => Payment::STATUS_COMPLETED,
                'settled_amount' => $settlementResponse->getSettledAmount(),
                'currency' => $settlementResponse->getCurrency(),
                'settled_at' => $settlementResponse->getSettledAt(),
                'metadata' => $settlementResponse->getMetadata()
            ];

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Failed to settle credit payment', [
                'payment_id' => $paymentId,
                'transaction_id' => $transactionId ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            if ($e instanceof CreditException) {
                throw $e;
            }

            throw CreditException::settlementFailed(
                $paymentId,
                'Settlement processing failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Cancel an authorized credit payment
     *
     * @throws CreditException
     */
    public function cancelPayment(string $paymentId, ?string $reason = null): array
    {
        $this->logger->info('Cancelling credit payment', [
            'payment_id' => $paymentId,
            'reason' => $reason
        ]);

        $paymentRepository = $this->entityManager->getRepository(Payment::class);
        $payment = $paymentRepository->findByPaymentId($paymentId);

        if ($payment === null) {
            throw CreditException::transactionNotFound($paymentId);
        }

        if (!$payment->isCredit()) {
            throw CreditException::authorizationFailed(
                $paymentId,
                'Payment is not a credit payment'
            );
        }

        if (!$payment->canBeCancelled()) {
            throw CreditException::transactionAlreadyProcessed($paymentId, $payment->getStatus());
        }

        $transactionId = $payment->getExternalTransactionId();
        if (!$transactionId) {
            throw CreditException::authorizationFailed(
                $paymentId,
                'No credit transaction ID found for payment'
            );
        }

        try {
            $this->entityManager->beginTransaction();

            // Cancel the credit authorization
            $cancelled = $this->creditService->cancelAuthorization($transactionId, $reason);

            if (!$cancelled) {
                $this->entityManager->rollback();
                
                throw CreditException::authorizationFailed(
                    'Failed to cancel credit authorization',
                    ['payment_id' => $paymentId, 'transaction_id' => $transactionId]
                );
            }

            // Update payment status
            $payment->setStatus(Payment::STATUS_CANCELLED);
            $gatewayResponse = $payment->getGatewayResponse() ?? [];
            $gatewayResponse['cancellation'] = [
                'reason' => $reason,
                'cancelled_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
            ];
            $payment->setGatewayResponse($gatewayResponse);

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Credit payment cancelled successfully', [
                'payment_id' => $paymentId,
                'transaction_id' => $transactionId,
                'reason' => $reason
            ]);

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'transaction_id' => $transactionId,
                'status' => Payment::STATUS_CANCELLED,
                'cancelled_at' => new \DateTimeImmutable(),
                'reason' => $reason
            ];

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Failed to cancel credit payment', [
                'payment_id' => $paymentId,
                'transaction_id' => $transactionId ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            if ($e instanceof CreditException) {
                throw $e;
            }

            throw CreditException::authorizationFailed(
                'Cancellation failed',
                ['payment_id' => $paymentId, 'error' => $e->getMessage()]
            );
        }
    }

    /**
     * Process a credit refund for a completed payment
     *
     * @throws CreditException
     */
    public function refundPayment(
        string $paymentId,
        string $refundAmount,
        ?string $reason = null
    ): array {
        $this->logger->info('Processing credit refund', [
            'payment_id' => $paymentId,
            'refund_amount' => $refundAmount,
            'reason' => $reason
        ]);

        $paymentRepository = $this->entityManager->getRepository(Payment::class);
        $payment = $paymentRepository->findByPaymentId($paymentId);

        if ($payment === null) {
            throw CreditException::transactionNotFound($paymentId);
        }

        if (!$payment->isCredit()) {
            throw CreditException::refundNotAllowed(
                $paymentId,
                'Payment is not a credit payment'
            );
        }

        if (!$payment->isCompleted()) {
            throw CreditException::refundNotAllowed(
                $paymentId,
                'Only completed payments can be refunded'
            );
        }

        $transactionId = $payment->getExternalTransactionId();
        if (!$transactionId) {
            throw CreditException::refundNotAllowed(
                $paymentId,
                'No credit transaction ID found for payment'
            );
        }

        try {
            $this->entityManager->beginTransaction();

            // Process the credit refund
            $refundTransaction = $this->creditService->processRefund($transactionId, $refundAmount, $reason);

            // Update payment with refund information
            $gatewayResponse = $payment->getGatewayResponse() ?? [];
            $gatewayResponse['refunds'] = $gatewayResponse['refunds'] ?? [];
            $gatewayResponse['refunds'][] = [
                'refund_transaction_id' => $refundTransaction->getTransactionId(),
                'refund_amount' => $refundAmount,
                'reason' => $reason,
                'refunded_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
            ];
            $payment->setGatewayResponse($gatewayResponse);

            // Check if payment should be marked as refunded
            $totalRefunded = array_sum(array_column($gatewayResponse['refunds'], 'refund_amount'));
            if ($totalRefunded >= (float)$payment->getAmount()) {
                $payment->setStatus(Payment::STATUS_REFUNDED);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Credit refund processed successfully', [
                'payment_id' => $paymentId,
                'refund_transaction_id' => $refundTransaction->getTransactionId(),
                'refund_amount' => $refundAmount
            ]);

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'original_transaction_id' => $transactionId,
                'refund_transaction_id' => $refundTransaction->getTransactionId(),
                'refund_amount' => $refundAmount,
                'currency' => $refundTransaction->getCurrency(),
                'refunded_at' => $refundTransaction->getCreatedAt(),
                'reason' => $reason,
                'payment_status' => $payment->getStatus()
            ];

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Failed to process credit refund', [
                'payment_id' => $paymentId,
                'transaction_id' => $transactionId ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            if ($e instanceof CreditException) {
                throw $e;
            }

            throw CreditException::refundNotAllowed(
                $paymentId,
                'Refund processing failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get credit payment status
     */
    public function getPaymentStatus(string $paymentId): array
    {
        $paymentRepository = $this->entityManager->getRepository(Payment::class);
        $payment = $paymentRepository->findByPaymentId($paymentId);

        if ($payment === null) {
            throw CreditException::transactionNotFound($paymentId);
        }

        if (!$payment->isCredit()) {
            throw CreditException::authorizationFailed(
                $paymentId,
                'Payment is not a credit payment'
            );
        }

        $transactionId = $payment->getExternalTransactionId();
        $creditTransaction = null;

        if ($transactionId) {
            try {
                $creditTransaction = $this->creditService->getTransaction($transactionId);
            } catch (CreditException $e) {
                // Transaction not found in credit service, continue with payment data only
                $this->logger->warning('Credit transaction not found', [
                    'payment_id' => $paymentId,
                    'transaction_id' => $transactionId
                ]);
            }
        }

        return [
            'payment_id' => $payment->getPaymentId(),
            'transaction_id' => $transactionId,
            'status' => $payment->getStatus(),
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'description' => $payment->getDescription(),
            'created_at' => $payment->getCreatedAt(),
            'completed_at' => $payment->getCompletedAt(),
            'failed_at' => $payment->getFailedAt(),
            'credit_transaction' => $creditTransaction ? [
                'transaction_id' => $creditTransaction->getTransactionId(),
                'transaction_type' => $creditTransaction->getTransactionType(),
                'status' => $creditTransaction->getStatus(),
                'due_date' => $creditTransaction->getDueDate(),
                'authorized_at' => $creditTransaction->getAuthorizedAt(),
                'settled_at' => $creditTransaction->getSettledAt(),
                'is_overdue' => $creditTransaction->isOverdue(),
                'days_overdue' => $creditTransaction->getDaysOverdue()
            ] : null,
            'gateway_response' => $payment->getGatewayResponse(),
            'metadata' => $payment->getMetadata()
        ];
    }
}