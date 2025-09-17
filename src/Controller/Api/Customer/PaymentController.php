<?php

declare(strict_types=1);

namespace App\Controller\Api\Customer;

use App\Service\Payment\CustomerPaymentService;
use App\Service\Payment\BalanceService;
use App\Service\Payment\InvoiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Customer Payment and Balance Management API Controller
 * Handles payments, invoices, balance management and billing for customers
 */
#[Route('/api/v1/customer/payments', name: 'api_customer_payments_')]
#[IsGranted('ROLE_CUSTOMER_USER')]
class PaymentController extends AbstractController
{
    public function __construct(
        private readonly CustomerPaymentService $paymentService,
        private readonly BalanceService $balanceService,
        private readonly InvoiceService $invoiceService
    ) {
    }

    /**
     * Get customer balance information
     */
    #[Route('/balance', name: 'balance', methods: ['GET'])]
    public function getBalance(): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();

        try {
            $balance = $this->balanceService->getCustomerBalance($customerId);

            return $this->json([
                'success' => true,
                'data' => $balance
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get balance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get balance history and transactions
     */
    #[Route('/balance/history', name: 'balance_history', methods: ['GET'])]
    public function getBalanceHistory(Request $request): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();

        $filters = [
            'date_from' => $request->query->get('date_from'),
            'date_to' => $request->query->get('date_to'),
            'type' => $request->query->get('type'), // 'credit', 'debit', 'all'
            'min_amount' => $request->query->get('min_amount'),
            'max_amount' => $request->query->get('max_amount')
        ];

        $pagination = [
            'page' => $request->query->getInt('page', 1),
            'limit' => $request->query->getInt('limit', 50),
            'sort' => $request->query->get('sort', 'created_at'),
            'order' => $request->query->get('order', 'desc')
        ];

        try {
            $result = $this->balanceService->getBalanceHistory($customerId, $filters, $pagination);

            return $this->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination']
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get balance history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Top up account balance
     */
    #[Route('/balance/topup', name: 'balance_topup', methods: ['POST'])]
    public function topUpBalance(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $customerId = $this->getUser()->getCustomer()->getId();

        $amount = (float) ($data['amount'] ?? 0);
        $paymentMethod = $data['payment_method'] ?? 'paynow';

        if ($amount <= 0) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid amount'
            ], 400);
        }

        try {
            $payment = $this->paymentService->createTopUpPayment($customerId, $amount, $paymentMethod);

            return $this->json([
                'success' => true,
                'message' => 'Top-up payment created',
                'data' => [
                    'payment_id' => $payment['id'],
                    'payment_url' => $payment['payment_url'],
                    'amount' => $amount,
                    'currency' => 'PLN'
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to create top-up payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List customer payments
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function listPayments(Request $request): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();

        $filters = [
            'status' => $request->query->get('status'),
            'method' => $request->query->get('method'),
            'date_from' => $request->query->get('date_from'),
            'date_to' => $request->query->get('date_to'),
            'type' => $request->query->get('type') // 'topup', 'shipment', 'invoice'
        ];

        $pagination = [
            'page' => $request->query->getInt('page', 1),
            'limit' => $request->query->getInt('limit', 20),
            'sort' => $request->query->get('sort', 'created_at'),
            'order' => $request->query->get('order', 'desc')
        ];

        try {
            $result = $this->paymentService->getCustomerPayments($customerId, $filters, $pagination);

            return $this->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination']
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get payments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment details
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getPayment(int $id): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();

        try {
            $payment = $this->paymentService->getCustomerPayment($customerId, $id);

            if (!$payment) {
                return $this->json(['success' => false, 'message' => 'Payment not found'], 404);
            }

            return $this->json([
                'success' => true,
                'data' => $payment
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List customer invoices
     */
    #[Route('/invoices', name: 'invoices', methods: ['GET'])]
    public function listInvoices(Request $request): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();

        $filters = [
            'status' => $request->query->get('status'),
            'date_from' => $request->query->get('date_from'),
            'date_to' => $request->query->get('date_to'),
            'min_amount' => $request->query->get('min_amount'),
            'max_amount' => $request->query->get('max_amount')
        ];

        $pagination = [
            'page' => $request->query->getInt('page', 1),
            'limit' => $request->query->getInt('limit', 20),
            'sort' => $request->query->get('sort', 'issue_date'),
            'order' => $request->query->get('order', 'desc')
        ];

        try {
            $result = $this->invoiceService->getCustomerInvoices($customerId, $filters, $pagination);

            return $this->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination']
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get invoices: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invoice details
     */
    #[Route('/invoices/{id}', name: 'invoice_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getInvoice(int $id): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();

        try {
            $invoice = $this->invoiceService->getCustomerInvoice($customerId, $id);

            if (!$invoice) {
                return $this->json(['success' => false, 'message' => 'Invoice not found'], 404);
            }

            return $this->json([
                'success' => true,
                'data' => $invoice
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Pay invoice
     */
    #[Route('/invoices/{id}/pay', name: 'invoice_pay', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function payInvoice(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $customerId = $this->getUser()->getCustomer()->getId();
        $paymentMethod = $data['payment_method'] ?? 'balance';

        try {
            $invoice = $this->invoiceService->getCustomerInvoice($customerId, $id);

            if (!$invoice) {
                return $this->json(['success' => false, 'message' => 'Invoice not found'], 404);
            }

            if ($invoice['status'] === 'paid') {
                return $this->json([
                    'success' => false,
                    'message' => 'Invoice is already paid'
                ], 400);
            }

            $payment = $this->paymentService->payInvoice($customerId, $id, $paymentMethod);

            return $this->json([
                'success' => true,
                'message' => 'Invoice payment initiated',
                'data' => $payment
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to pay invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download invoice PDF
     */
    #[Route('/invoices/{id}/download', name: 'invoice_download', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function downloadInvoice(int $id): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();

        try {
            $invoice = $this->invoiceService->getCustomerInvoice($customerId, $id);

            if (!$invoice) {
                return $this->json(['success' => false, 'message' => 'Invoice not found'], 404);
            }

            $downloadUrl = $this->invoiceService->generateDownloadUrl($id);

            return $this->json([
                'success' => true,
                'data' => [
                    'download_url' => $downloadUrl,
                    'expires_at' => (new \DateTime())->add(new \DateInterval('PT1H'))->format('Y-m-d H:i:s')
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to generate download URL: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment statistics and analytics
     */
    #[Route('/analytics', name: 'analytics', methods: ['GET'])]
    public function getPaymentAnalytics(Request $request): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();
        $period = $request->query->getInt('period', 30); // days

        try {
            $analytics = $this->paymentService->getPaymentAnalytics($customerId, $period);

            return $this->json([
                'success' => true,
                'data' => $analytics
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get payment analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Setup recurring payment for automatic top-ups
     */
    #[Route('/recurring/setup', name: 'recurring_setup', methods: ['POST'])]
    public function setupRecurringPayment(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $customerId = $this->getUser()->getCustomer()->getId();

        $triggerAmount = (float) ($data['trigger_amount'] ?? 0);
        $topUpAmount = (float) ($data['topup_amount'] ?? 0);
        $paymentMethod = $data['payment_method'] ?? 'paynow';

        if ($triggerAmount <= 0 || $topUpAmount <= 0) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid amounts'
            ], 400);
        }

        try {
            $recurringSetup = $this->paymentService->setupRecurringPayment(
                $customerId,
                $triggerAmount,
                $topUpAmount,
                $paymentMethod
            );

            return $this->json([
                'success' => true,
                'message' => 'Recurring payment setup successfully',
                'data' => $recurringSetup
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to setup recurring payment: ' . $e->getMessage()
            ], 500);
        }
    }
}