<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Payment\Contracts\CreditServiceInterface;
use App\Domain\Payment\Service\CreditPaymentHandler;
use App\Domain\Payment\Exception\CreditException;
use App\Entity\User;
use App\Entity\SystemUser;
use App\Entity\CustomerUser;
use App\Repository\SystemUserRepository;
use App\Repository\CustomerUserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/payments/credit', name: 'api_credit_payment_')]
class CreditPaymentController extends AbstractController
{
    public function __construct(
        private readonly CreditServiceInterface $creditService,
        private readonly CreditPaymentHandler $creditPaymentHandler,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
        private readonly SystemUserRepository $systemUserRepository,
        private readonly CustomerUserRepository $customerUserRepository
    ) {
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function createPayment(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid JSON payload'
                ], 400);
            }

            // Get user from request - first try system user, then customer user
            $user = $this->getUserById($data['user_id'] ?? 1, $data['user_type'] ?? 'system');

            $result = $this->creditPaymentHandler->createPayment(
                $user,
                $data['amount'] ?? '0.00',
                $data['currency'] ?? 'PLN',
                $data['description'] ?? 'Credit payment',
                $data['external_reference'] ?? null,
                $data['payment_term_days'] ?? null,
                $data['metadata'] ?? null
            );

            return $this->json($result);

        } catch (CreditException $e) {
            $this->logger->warning('Credit payment creation failed', [
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getMessage(),
                'error_details' => $e->getErrorDetails()
            ]);

            return $this->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getMessage(),
                'error_details' => $e->getErrorDetails()
            ], 400);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in credit payment creation', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    #[Route('/settle/{paymentId}', name: 'settle', methods: ['POST'])]
    public function settlePayment(string $paymentId, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true) ?? [];

            $result = $this->creditPaymentHandler->settlePayment(
                $paymentId,
                $data['settle_amount'] ?? null,
                $data['notes'] ?? null,
                $data['force_settle'] ?? false
            );

            return $this->json($result);

        } catch (CreditException $e) {
            $this->logger->warning('Credit payment settlement failed', [
                'payment_id' => $paymentId,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getMessage(),
                'error_details' => $e->getErrorDetails()
            ], 400);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in credit payment settlement', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    #[Route('/cancel/{paymentId}', name: 'cancel', methods: ['POST'])]
    public function cancelPayment(string $paymentId, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true) ?? [];

            $result = $this->creditPaymentHandler->cancelPayment(
                $paymentId,
                $data['reason'] ?? null
            );

            return $this->json($result);

        } catch (CreditException $e) {
            $this->logger->warning('Credit payment cancellation failed', [
                'payment_id' => $paymentId,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getMessage(),
                'error_details' => $e->getErrorDetails()
            ], 400);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in credit payment cancellation', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    #[Route('/refund/{paymentId}', name: 'refund', methods: ['POST'])]
    public function refundPayment(string $paymentId, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data || !isset($data['refund_amount'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Refund amount is required'
                ], 400);
            }

            $result = $this->creditPaymentHandler->refundPayment(
                $paymentId,
                $data['refund_amount'],
                $data['reason'] ?? null
            );

            return $this->json($result);

        } catch (CreditException $e) {
            $this->logger->warning('Credit payment refund failed', [
                'payment_id' => $paymentId,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getMessage(),
                'error_details' => $e->getErrorDetails()
            ], 400);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in credit payment refund', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    #[Route('/status/{paymentId}', name: 'status', methods: ['GET'])]
    public function getPaymentStatus(string $paymentId): JsonResponse
    {
        try {
            $result = $this->creditPaymentHandler->getPaymentStatus($paymentId);
            return $this->json($result);

        } catch (CreditException $e) {
            $this->logger->warning('Failed to get credit payment status', [
                'payment_id' => $paymentId,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getMessage(),
                'error_details' => $e->getErrorDetails()
            ], 404);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error getting credit payment status', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    #[Route('/account/status', name: 'account_status', methods: ['GET'])]
    public function getCreditAccountStatus(Request $request): JsonResponse
    {
        try {
            $userId = (int)$request->query->get('user_id', 1);
            $userType = $request->query->get('user_type', 'system');
            $user = $this->getUserById($userId, $userType);

            $statusDTO = $this->creditService->getCreditAccountStatus($user);
            
            return $this->json([
                'success' => true,
                'account_status' => $statusDTO->toArray()
            ]);

        } catch (CreditException $e) {
            $this->logger->warning('Failed to get credit account status', [
                'user_id' => $request->query->get('user_id'),
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getMessage(),
                'error_details' => $e->getErrorDetails()
            ], 404);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error getting credit account status', [
                'user_id' => $request->query->get('user_id'),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    #[Route('/account/create', name: 'account_create', methods: ['POST'])]
    public function createCreditAccount(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid JSON payload'
                ], 400);
            }

            $user = $this->getUserById($data['user_id'] ?? 1, $data['user_type'] ?? 'system');

            $account = $this->creditService->createCreditAccount(
                $user,
                $data['account_type'] ?? 'individual',
                $data['credit_limit'] ?? '1000.00',
                $data['payment_term_days'] ?? 30,
                $data['currency'] ?? 'PLN',
                $data['metadata'] ?? null
            );

            return $this->json([
                'success' => true,
                'account' => [
                    'account_number' => $account->getAccountNumber(),
                    'account_type' => $account->getAccountType(),
                    'status' => $account->getStatus(),
                    'credit_limit' => $account->getCreditLimit(),
                    'currency' => $account->getCurrency(),
                    'payment_term_days' => $account->getPaymentTermDays(),
                    'created_at' => $account->getCreatedAt()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (CreditException $e) {
            $this->logger->warning('Credit account creation failed', [
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getMessage(),
                'error_details' => $e->getErrorDetails()
            ]);

            return $this->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getMessage(),
                'error_details' => $e->getErrorDetails()
            ], 400);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in credit account creation', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    #[Route('/info', name: 'info', methods: ['GET'])]
    public function getServiceInfo(): JsonResponse
    {
        return $this->json([
            'service' => 'Credit Payment Service',
            'enabled' => $this->creditService->isEnabled(),
            'supported_currencies' => $this->creditService->getSupportedCurrencies(),
            'allowed_payment_terms' => $this->creditService->getAllowedPaymentTerms(),
            'minimum_amounts' => array_map(
                fn($currency) => [
                    'currency' => $currency,
                    'min_amount' => $this->creditService->getMinimumAmount($currency),
                    'max_amount' => $this->creditService->getMaximumAmount($currency)
                ],
                $this->creditService->getSupportedCurrencies()
            ),
            'version' => '1.0.0',
            'documentation' => '/api/doc'
        ]);
    }

    /**
     * Get user by ID and type from database
     */
    private function getUserById(int $userId, string $userType): User
    {
        $user = match ($userType) {
            'system' => $this->systemUserRepository->find($userId),
            'customer' => $this->customerUserRepository->find($userId),
            default => null
        };

        if (!$user) {
            throw new \RuntimeException(sprintf('User with ID %d and type %s not found', $userId, $userType));
        }

        return $user;
    }
}