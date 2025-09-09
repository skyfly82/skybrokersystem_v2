<?php

declare(strict_types=1);

namespace App\Controller\Payment;

use App\Domain\Payment\Service\PayNowPaymentHandler;
use App\Domain\Payment\Exception\PayNowIntegrationException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/payment/paynow', name: 'paynow_')]
class PayNowWebhookController extends AbstractController
{
    private PayNowPaymentHandler $paymentHandler;
    private LoggerInterface $logger;

    public function __construct(
        PayNowPaymentHandler $paymentHandler,
        LoggerInterface $logger
    ) {
        $this->paymentHandler = $paymentHandler;
        $this->logger = $logger;
    }

    #[Route('/webhook/{paymentId}', name: 'webhook', methods: ['POST'])]
    public function webhook(Request $request, string $paymentId): JsonResponse
    {
        $this->logger->info('Received PayNow webhook', [
            'payment_id' => $paymentId,
            'method' => $request->getMethod(),
            'content_type' => $request->headers->get('content-type'),
        ]);

        try {
            // Get webhook payload
            $payload = $request->getContent();
            $webhookData = json_decode($payload, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Invalid JSON in PayNow webhook', [
                    'payment_id' => $paymentId,
                    'json_error' => json_last_error_msg(),
                    'payload' => $payload,
                ]);

                return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            // Get webhook signature and timestamp from headers
            $signature = $request->headers->get('X-PayNow-Signature', '');
            $timestamp = $request->headers->get('X-PayNow-Timestamp', (string) time());

            if (empty($signature)) {
                $this->logger->warning('Missing PayNow webhook signature', [
                    'payment_id' => $paymentId,
                ]);
            }

            // Process webhook notification
            $payment = $this->paymentHandler->handleWebhookNotification(
                $webhookData,
                $signature,
                $timestamp
            );

            if (!$payment) {
                $this->logger->warning('Payment not found for PayNow webhook', [
                    'payment_id' => $paymentId,
                    'webhook_data' => $webhookData,
                ]);

                return new JsonResponse(['error' => 'Payment not found'], Response::HTTP_NOT_FOUND);
            }

            $this->logger->info('PayNow webhook processed successfully', [
                'payment_id' => $payment->getPaymentId(),
                'status' => $payment->getStatus(),
            ]);

            return new JsonResponse([
                'status' => 'success',
                'payment_id' => $payment->getPaymentId(),
                'payment_status' => $payment->getStatus(),
            ]);

        } catch (PayNowIntegrationException $e) {
            $this->logger->error('PayNow webhook processing failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            $statusCode = match ($e->getErrorCode()) {
                PayNowIntegrationException::ERROR_INVALID_WEBHOOK_SIGNATURE => Response::HTTP_UNAUTHORIZED,
                PayNowIntegrationException::ERROR_PAYMENT_NOT_FOUND => Response::HTTP_NOT_FOUND,
                default => Response::HTTP_INTERNAL_SERVER_ERROR,
            };

            return new JsonResponse([
                'error' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], $statusCode);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error processing PayNow webhook', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'error' => 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/status/{paymentId}', name: 'status', methods: ['GET'])]
    public function checkStatus(string $paymentId): JsonResponse
    {
        $this->logger->info('Checking PayNow payment status', [
            'payment_id' => $paymentId,
        ]);

        try {
            $entityManager = $this->container->get('doctrine')->getManager();
            $paymentRepository = $entityManager->getRepository(\App\Domain\Payment\Entity\Payment::class);
            $payment = $paymentRepository->findByPaymentId($paymentId);

            if (!$payment) {
                return new JsonResponse([
                    'error' => 'Payment not found',
                ], Response::HTTP_NOT_FOUND);
            }

            if (!$payment->isPayNow()) {
                return new JsonResponse([
                    'error' => 'Not a PayNow payment',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Update payment status from PayNow
            $updatedPayment = $this->paymentHandler->updatePaymentStatus($payment);

            return new JsonResponse([
                'payment_id' => $updatedPayment->getPaymentId(),
                'status' => $updatedPayment->getStatus(),
                'amount' => $updatedPayment->getAmount(),
                'currency' => $updatedPayment->getCurrency(),
                'external_transaction_id' => $updatedPayment->getExternalTransactionId(),
                'created_at' => $updatedPayment->getCreatedAt()->format('c'),
                'completed_at' => $updatedPayment->getCompletedAt()?->format('c'),
                'failed_at' => $updatedPayment->getFailedAt()?->format('c'),
            ]);

        } catch (PayNowIntegrationException $e) {
            $this->logger->error('Failed to check PayNow payment status', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ]);

            return new JsonResponse([
                'error' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error checking PayNow payment status', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'error' => 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}