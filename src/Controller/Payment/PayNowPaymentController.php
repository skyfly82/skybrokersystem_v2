<?php

declare(strict_types=1);

namespace App\Controller\Payment;

use App\Domain\Payment\Service\PayNowPaymentHandler;
use App\Domain\Payment\Exception\PayNowIntegrationException;
use App\Domain\Payment\Repository\PaymentRepository;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/payment', name: 'payment_')]
#[IsGranted('ROLE_USER')]
class PayNowPaymentController extends AbstractController
{
    private PayNowPaymentHandler $paymentHandler;
    private PaymentRepository $paymentRepository;
    private LoggerInterface $logger;
    private ValidatorInterface $validator;

    public function __construct(
        PayNowPaymentHandler $paymentHandler,
        PaymentRepository $paymentRepository,
        LoggerInterface $logger,
        ValidatorInterface $validator
    ) {
        $this->paymentHandler = $paymentHandler;
        $this->paymentRepository = $paymentRepository;
        $this->logger = $logger;
        $this->validator = $validator;
    }

    #[Route('/paynow/create', name: 'paynow_create', methods: ['POST'])]
    public function createPayNowPayment(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $this->logger->info('Creating PayNow payment', [
            'user_id' => $user->getId(),
            'user_email' => $user->getEmail(),
        ]);

        try {
            $data = json_decode($request->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse([
                    'error' => 'Invalid JSON',
                    'details' => json_last_error_msg(),
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate required fields
            $requiredFields = ['amount', 'currency', 'description'];
            $missingFields = [];

            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                return new JsonResponse([
                    'error' => 'Missing required fields',
                    'missing_fields' => $missingFields,
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate amount format
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $data['amount'])) {
                return new JsonResponse([
                    'error' => 'Invalid amount format',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate currency
            $supportedCurrencies = ['PLN', 'EUR', 'USD', 'GBP'];
            if (!in_array($data['currency'], $supportedCurrencies)) {
                return new JsonResponse([
                    'error' => 'Unsupported currency',
                    'supported_currencies' => $supportedCurrencies,
                ], Response::HTTP_BAD_REQUEST);
            }

            // Create payment
            $payment = $this->paymentHandler->createPayment(
                $user,
                $data['amount'],
                $data['currency'],
                $data['description'],
                $data['metadata'] ?? null
            );

            return new JsonResponse([
                'status' => 'success',
                'payment_id' => $payment->getPaymentId(),
                'amount' => $payment->getAmount(),
                'currency' => $payment->getCurrency(),
                'status_payment' => $payment->getStatus(),
                'redirect_url' => $payment->getMetadata()['paynow_redirect_url'] ?? null,
                'external_transaction_id' => $payment->getExternalTransactionId(),
                'created_at' => $payment->getCreatedAt()->format('c'),
            ], Response::HTTP_CREATED);

        } catch (PayNowIntegrationException $e) {
            $this->logger->error('Failed to create PayNow payment', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
                'error_details' => $e->getErrorDetails(),
            ]);

            $statusCode = match ($e->getErrorCode()) {
                PayNowIntegrationException::ERROR_INVALID_AMOUNT,
                PayNowIntegrationException::ERROR_INVALID_CURRENCY => Response::HTTP_BAD_REQUEST,
                PayNowIntegrationException::ERROR_INVALID_CREDENTIALS => Response::HTTP_UNAUTHORIZED,
                default => Response::HTTP_INTERNAL_SERVER_ERROR,
            };

            return new JsonResponse([
                'error' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
                'error_details' => $e->getErrorDetails(),
            ], $statusCode);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error creating PayNow payment', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'error' => 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/paynow/{paymentId}/refund', name: 'paynow_refund', methods: ['POST'])]
    public function refundPayNowPayment(Request $request, string $paymentId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->logger->info('Processing PayNow refund', [
            'user_id' => $user->getId(),
            'payment_id' => $paymentId,
        ]);

        try {
            $payment = $this->paymentRepository->findByPaymentId($paymentId);

            if (!$payment) {
                return new JsonResponse([
                    'error' => 'Payment not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Check if user owns the payment
            if ($payment->getUser()->getId() !== $user->getId()) {
                return new JsonResponse([
                    'error' => 'Access denied',
                ], Response::HTTP_FORBIDDEN);
            }

            if (!$payment->isPayNow()) {
                return new JsonResponse([
                    'error' => 'Not a PayNow payment',
                ], Response::HTTP_BAD_REQUEST);
            }

            $data = json_decode($request->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse([
                    'error' => 'Invalid JSON',
                    'details' => json_last_error_msg(),
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate refund amount
            $refundAmount = $data['amount'] ?? $payment->getAmount();

            if (!preg_match('/^\d+(\.\d{1,2})?$/', $refundAmount)) {
                return new JsonResponse([
                    'error' => 'Invalid refund amount format',
                ], Response::HTTP_BAD_REQUEST);
            }

            if ((float)$refundAmount > (float)$payment->getAmount()) {
                return new JsonResponse([
                    'error' => 'Refund amount cannot exceed payment amount',
                    'payment_amount' => $payment->getAmount(),
                    'requested_refund' => $refundAmount,
                ], Response::HTTP_BAD_REQUEST);
            }

            // Process refund
            $this->paymentHandler->refundPayment(
                $payment,
                $refundAmount,
                $data['reason'] ?? null
            );

            return new JsonResponse([
                'status' => 'success',
                'payment_id' => $payment->getPaymentId(),
                'refund_amount' => $refundAmount,
                'payment_status' => $payment->getStatus(),
                'processed_at' => (new \DateTimeImmutable())->format('c'),
            ]);

        } catch (PayNowIntegrationException $e) {
            $this->logger->error('Failed to process PayNow refund', [
                'user_id' => $user->getId(),
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ]);

            $statusCode = match ($e->getErrorCode()) {
                PayNowIntegrationException::ERROR_PAYMENT_NOT_FOUND => Response::HTTP_NOT_FOUND,
                PayNowIntegrationException::ERROR_REFUND_NOT_ALLOWED => Response::HTTP_BAD_REQUEST,
                default => Response::HTTP_INTERNAL_SERVER_ERROR,
            };

            return new JsonResponse([
                'error' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], $statusCode);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error processing PayNow refund', [
                'user_id' => $user->getId(),
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'error' => 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/success/{paymentId}', name: 'success', methods: ['GET'])]
    public function paymentSuccess(string $paymentId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $payment = $this->paymentRepository->findByPaymentId($paymentId);

        if (!$payment) {
            return new JsonResponse([
                'error' => 'Payment not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if user owns the payment
        if ($payment->getUser()->getId() !== $user->getId()) {
            return new JsonResponse([
                'error' => 'Access denied',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            // Update payment status from PayNow if it's a PayNow payment
            if ($payment->isPayNow()) {
                $payment = $this->paymentHandler->updatePaymentStatus($payment);
            }

            return new JsonResponse([
                'status' => 'success',
                'payment_id' => $payment->getPaymentId(),
                'payment_status' => $payment->getStatus(),
                'amount' => $payment->getAmount(),
                'currency' => $payment->getCurrency(),
                'description' => $payment->getDescription(),
                'created_at' => $payment->getCreatedAt()->format('c'),
                'completed_at' => $payment->getCompletedAt()?->format('c'),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in payment success callback', [
                'user_id' => $user->getId(),
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'error' => 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}