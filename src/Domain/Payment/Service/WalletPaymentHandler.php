<?php

declare(strict_types=1);

namespace App\Domain\Payment\Service;

use App\Domain\Payment\Contracts\WalletServiceInterface;
use App\Domain\Payment\DTO\WalletPaymentRequestDTO;
use App\Domain\Payment\DTO\WalletPaymentResponseDTO;
use App\Domain\Payment\Entity\Payment;
use App\Domain\Payment\Exception\WalletException;
use App\Domain\Payment\Repository\PaymentRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class WalletPaymentHandler
{
    public function __construct(
        private readonly WalletServiceInterface $walletService,
        private readonly PaymentRepository $paymentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handlePayment(Payment $payment): bool
    {
        $this->logger->info('Handling wallet payment', [
            'payment_id' => $payment->getPaymentId(),
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency()
        ]);

        if (!$payment->canBeProcessed()) {
            $this->logger->warning('Payment cannot be processed', [
                'payment_id' => $payment->getPaymentId(),
                'status' => $payment->getStatus()
            ]);
            return false;
        }

        if (!$payment->isWallet()) {
            $this->logger->error('Payment is not a wallet payment', [
                'payment_id' => $payment->getPaymentId(),
                'payment_method' => $payment->getPaymentMethod()
            ]);
            return false;
        }

        try {
            $this->entityManager->beginTransaction();

            // Set payment status to processing
            $payment->setStatus(Payment::STATUS_PROCESSING);
            $this->paymentRepository->save($payment, false);

            // Create wallet payment request
            $walletPaymentRequest = new WalletPaymentRequestDTO(
                $payment->getPaymentId(),
                $payment->getAmount(),
                $payment->getCurrency(),
                $payment->getDescription(),
                $payment->getExternalTransactionId(),
                $payment->getMetadata()
            );

            // Process payment through wallet service
            $response = $this->walletService->processPayment(
                $payment->getUser(),
                $walletPaymentRequest
            );

            if ($response->isSuccess()) {
                // Update payment with successful response
                $payment->setStatus(Payment::STATUS_COMPLETED)
                       ->setExternalTransactionId($response->getTransactionId());

                // Store wallet response in gateway response
                $gatewayResponse = [
                    'transaction_id' => $response->getTransactionId(),
                    'remaining_balance' => $response->getRemainingBalance(),
                    'processed_at' => $response->getProcessedAt()?->format('Y-m-d H:i:s'),
                    'status' => $response->getStatus()
                ];

                if ($response->getMetadata()) {
                    $gatewayResponse['metadata'] = $response->getMetadata();
                }

                $payment->setGatewayResponse($gatewayResponse);

                $this->paymentRepository->save($payment, true);
                $this->entityManager->commit();

                $this->logger->info('Wallet payment completed successfully', [
                    'payment_id' => $payment->getPaymentId(),
                    'transaction_id' => $response->getTransactionId(),
                    'remaining_balance' => $response->getRemainingBalance()
                ]);

                return true;
            } else {
                // Handle payment failure
                $payment->setStatus(Payment::STATUS_FAILED);

                $gatewayResponse = [
                    'error_code' => $response->getErrorCode(),
                    'error_message' => $response->getErrorMessage(),
                    'failed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
                ];

                if ($response->getMetadata()) {
                    $gatewayResponse['metadata'] = $response->getMetadata();
                }

                $payment->setGatewayResponse($gatewayResponse);

                $this->paymentRepository->save($payment, true);
                $this->entityManager->commit();

                $this->logger->warning('Wallet payment failed', [
                    'payment_id' => $payment->getPaymentId(),
                    'error_code' => $response->getErrorCode(),
                    'error_message' => $response->getErrorMessage()
                ]);

                return false;
            }

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            // Update payment status to failed
            $payment->setStatus(Payment::STATUS_FAILED);
            $payment->setGatewayResponse([
                'error' => $e->getMessage(),
                'failed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
            ]);

            try {
                $this->paymentRepository->save($payment, true);
            } catch (\Exception $saveException) {
                $this->logger->error('Failed to save payment failure status', [
                    'payment_id' => $payment->getPaymentId(),
                    'save_error' => $saveException->getMessage()
                ]);
            }

            $this->logger->error('Wallet payment handler failed', [
                'payment_id' => $payment->getPaymentId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    public function canHandlePayment(Payment $payment): bool
    {
        return $payment->isWallet() && $this->walletService->isEnabled();
    }

    public function validatePayment(Payment $payment): array
    {
        $errors = [];

        if (!$payment->isWallet()) {
            $errors[] = 'Payment method is not wallet';
        }

        if (!$this->walletService->isEnabled()) {
            $errors[] = 'Wallet service is disabled';
        }

        if (!$this->walletService->validateAmount($payment->getAmount(), $payment->getCurrency())) {
            $errors[] = 'Invalid payment amount or currency';
        }

        if (!$this->walletService->canMakePayment(
            $payment->getUser(),
            $payment->getAmount(),
            $payment->getCurrency()
        )) {
            $errors[] = 'User cannot make this payment (insufficient balance or inactive wallet)';
        }

        return $errors;
    }

    public function getPaymentLimits(User $user): array
    {
        try {
            $walletStatus = $this->walletService->getWalletStatus($user);

            return [
                'min_amount' => $this->walletService->getMinimumAmount($walletStatus->getCurrency()),
                'max_amount' => $this->walletService->getMaximumAmount($walletStatus->getCurrency()),
                'available_balance' => $walletStatus->getAvailableBalance(),
                'daily_limit' => $walletStatus->getDailyTransactionLimit(),
                'monthly_limit' => $walletStatus->getMonthlyTransactionLimit(),
                'daily_remaining' => $walletStatus->getRemainingDailyLimit(),
                'monthly_remaining' => $walletStatus->getRemainingMonthlyLimit(),
                'currency' => $walletStatus->getCurrency(),
                'supported_currencies' => $this->walletService->getSupportedCurrencies()
            ];
        } catch (WalletException $e) {
            return [
                'error' => 'Unable to retrieve payment limits',
                'error_code' => $e->getErrorCode(),
                'supported_currencies' => $this->walletService->getSupportedCurrencies()
            ];
        }
    }

    public function refundPayment(Payment $payment, string $refundAmount, ?string $reason = null): bool
    {
        $this->logger->info('Processing wallet payment refund', [
            'payment_id' => $payment->getPaymentId(),
            'refund_amount' => $refundAmount,
            'reason' => $reason
        ]);

        if (!$payment->isCompleted()) {
            $this->logger->warning('Cannot refund non-completed payment', [
                'payment_id' => $payment->getPaymentId(),
                'status' => $payment->getStatus()
            ]);
            return false;
        }

        if (!$payment->isWallet()) {
            $this->logger->error('Payment is not a wallet payment', [
                'payment_id' => $payment->getPaymentId(),
                'payment_method' => $payment->getPaymentMethod()
            ]);
            return false;
        }

        $transactionId = $payment->getExternalTransactionId();
        if (!$transactionId) {
            $this->logger->error('No transaction ID found for refund', [
                'payment_id' => $payment->getPaymentId()
            ]);
            return false;
        }

        try {
            $this->entityManager->beginTransaction();

            // Create reversal transaction
            $reversalTransaction = $this->walletService->reverseTransaction(
                $transactionId,
                $refundAmount,
                $reason ?? 'Payment refund'
            );

            // Update payment status
            $payment->setStatus(Payment::STATUS_REFUNDED);

            // Update gateway response with refund information
            $gatewayResponse = $payment->getGatewayResponse() ?? [];
            $gatewayResponse['refund'] = [
                'refund_transaction_id' => $reversalTransaction->getTransactionId(),
                'refund_amount' => $refundAmount,
                'refund_reason' => $reason,
                'refunded_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
            ];
            $payment->setGatewayResponse($gatewayResponse);

            $this->paymentRepository->save($payment, true);
            $this->entityManager->commit();

            $this->logger->info('Wallet payment refund completed successfully', [
                'payment_id' => $payment->getPaymentId(),
                'refund_transaction_id' => $reversalTransaction->getTransactionId(),
                'refund_amount' => $refundAmount
            ]);

            return true;

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Wallet payment refund failed', [
                'payment_id' => $payment->getPaymentId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function getPaymentStatus(Payment $payment): array
    {
        if (!$payment->isWallet()) {
            return ['error' => 'Payment is not a wallet payment'];
        }

        $status = [
            'payment_id' => $payment->getPaymentId(),
            'status' => $payment->getStatus(),
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'created_at' => $payment->getCreatedAt()->format('Y-m-d H:i:s'),
            'processed_at' => $payment->getProcessedAt()?->format('Y-m-d H:i:s'),
            'completed_at' => $payment->getCompletedAt()?->format('Y-m-d H:i:s'),
            'failed_at' => $payment->getFailedAt()?->format('Y-m-d H:i:s')
        ];

        $gatewayResponse = $payment->getGatewayResponse();
        if ($gatewayResponse) {
            $status['transaction_id'] = $gatewayResponse['transaction_id'] ?? null;
            $status['remaining_balance'] = $gatewayResponse['remaining_balance'] ?? null;
            
            if (isset($gatewayResponse['error_code'])) {
                $status['error_code'] = $gatewayResponse['error_code'];
                $status['error_message'] = $gatewayResponse['error_message'] ?? null;
            }

            if (isset($gatewayResponse['refund'])) {
                $status['refund'] = $gatewayResponse['refund'];
            }
        }

        return $status;
    }
}