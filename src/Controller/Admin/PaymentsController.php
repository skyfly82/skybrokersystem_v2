<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/payments')]
#[IsGranted('ROLE_SYSTEM_USER')]
class PaymentsController extends AbstractController
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly CustomerRepository $customerRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'admin_payments', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $type = $request->query->get('type', '');
        $paymentMethod = $request->query->get('payment_method', '');
        $dateFrom = $request->query->get('date_from', '');
        $dateTo = $request->query->get('date_to', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 25;

        $filters = [
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'payment_method' => $paymentMethod,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'page' => $page,
            'limit' => $limit,
        ];

        $transactions = $this->transactionRepository->findWithFilters($filters);
        $totalTransactions = $this->transactionRepository->countWithFilters($filters);

        return $this->render('admin/payments/index.html.twig', [
            'transactions' => $transactions,
            'total_transactions' => $totalTransactions,
            'current_page' => $page,
            'total_pages' => ceil($totalTransactions / $limit),
            'filters' => $filters,
            'statistics' => $this->getPaymentStatistics(),
        ]);
    }

    #[Route('/api', name: 'admin_payments_api', methods: ['GET'])]
    public function getPaymentsApi(Request $request): JsonResponse
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $type = $request->query->get('type', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 25);

        $filters = [
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'page' => $page,
            'limit' => $limit,
        ];

        $transactions = $this->transactionRepository->findWithFilters($filters);
        $totalTransactions = $this->transactionRepository->countWithFilters($filters);

        $transactionsData = [];
        foreach ($transactions as $transaction) {
            $transactionsData[] = [
                'id' => $transaction->getId(),
                'transaction_id' => $transaction->getTransactionId(),
                'customer_name' => $transaction->getCustomer()?->getCompanyName(),
                'amount' => $transaction->getAmount(),
                'currency' => $transaction->getCurrency(),
                'type' => $transaction->getType(),
                'status' => $transaction->getStatus(),
                'payment_method' => $transaction->getPaymentMethod(),
                'created_at' => $transaction->getCreatedAt()?->format('Y-m-d H:i:s'),
                'completed_at' => $transaction->getCompletedAt()?->format('Y-m-d H:i:s'),
                'description' => $transaction->getDescription(),
            ];
        }

        return $this->json([
            'transactions' => $transactionsData,
            'total' => $totalTransactions,
            'page' => $page,
            'total_pages' => ceil($totalTransactions / $limit),
        ]);
    }

    #[Route('/{id}', name: 'admin_payment_show', methods: ['GET'])]
    public function show(Transaction $transaction): Response
    {
        return $this->render('admin/payments/show.html.twig', [
            'transaction' => $transaction,
            'customer' => $transaction->getCustomer(),
            'order' => $transaction->getOrder(),
        ]);
    }

    #[Route('/{id}/refund', name: 'admin_payment_refund', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function refund(Transaction $transaction, Request $request): JsonResponse
    {
        if ($transaction->getStatus() !== 'completed') {
            return $this->json(['error' => 'Only completed transactions can be refunded'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $refundAmount = $data['amount'] ?? $transaction->getAmount();
        $reason = $data['reason'] ?? '';

        // Create refund transaction
        $refund = new Transaction();
        $refund->setCustomer($transaction->getCustomer());
        $refund->setOrder($transaction->getOrder());
        $refund->setAmount($refundAmount);
        $refund->setCurrency($transaction->getCurrency());
        $refund->setType('refund');
        $refund->setStatus('pending');
        $refund->setPaymentMethod($transaction->getPaymentMethod());
        $refund->setDescription('Refund for transaction: ' . $transaction->getTransactionId() . ($reason ? ' - ' . $reason : ''));

        $this->entityManager->persist($refund);
        $this->entityManager->flush();

        // Here you would integrate with payment providers to process the actual refund
        // For now, we'll mark it as completed
        $refund->setStatus('completed');
        $refund->setCompletedAt(new \DateTime());
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Refund processed successfully',
            'refund_id' => $refund->getTransactionId(),
        ]);
    }

    #[Route('/analytics/revenue', name: 'admin_payments_revenue_analytics', methods: ['GET'])]
    public function revenueAnalyticsAction(Request $request): JsonResponse
    {
        $period = $request->query->get('period', '30days');
        $chartData = $this->getRevenueAnalytics($period);

        return $this->json($chartData);
    }

    #[Route('/analytics/methods', name: 'admin_payments_methods_analytics', methods: ['GET'])]
    public function getPaymentMethodsAnalytics(Request $request): JsonResponse
    {
        $period = $request->query->get('period', '30days');
        $chartData = $this->getPaymentMethodAnalytics($period);

        return $this->json($chartData);
    }

    #[Route('/export', name: 'admin_payments_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'csv');
        $filters = [
            'search' => $request->query->get('search', ''),
            'status' => $request->query->get('status', ''),
            'type' => $request->query->get('type', ''),
            'limit' => 10000,
        ];

        $transactions = $this->transactionRepository->findWithFilters($filters);

        if ($format === 'csv') {
            return $this->exportToCsv($transactions);
        }

        return $this->json(['error' => 'Unsupported export format'], 400);
    }

    #[Route('/reconcile', name: 'admin_payments_reconcile', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reconcilePayments(Request $request): JsonResponse
    {
        // Get pending transactions
        $pendingTransactions = $this->transactionRepository->findPending();
        $reconciledCount = 0;
        $errors = [];

        foreach ($pendingTransactions as $transaction) {
            try {
                // Here you would check with payment providers for actual status
                // For demo purposes, we'll mark old pending transactions as failed
                $createdAgo = $transaction->getCreatedAt()->diff(new \DateTime());
                if ($createdAgo->days >= 1) {
                    $transaction->setStatus('failed');
                    $transaction->markAsFailed('Transaction timeout - auto-reconciled');
                    $reconciledCount++;
                }
            } catch (\Exception $e) {
                $errors[] = 'Error reconciling transaction ' . $transaction->getTransactionId() . ': ' . $e->getMessage();
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => sprintf('%d transactions reconciled', $reconciledCount),
            'reconciled_count' => $reconciledCount,
            'errors' => $errors,
        ]);
    }

    private function getPaymentStatistics(): array
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');
        $startOfMonth = new \DateTime('first day of this month');
        $now = new \DateTime();

        $stats = $this->transactionRepository->getStatistics($startOfMonth, $now);

        return [
            'total_transactions' => (int) $stats['total_transactions'],
            'completed_transactions' => (int) $stats['completed_transactions'],
            'pending_transactions' => (int) $stats['pending_transactions'],
            'failed_transactions' => (int) $stats['failed_transactions'],
            'total_revenue' => (float) $stats['total_revenue'],
            'today_revenue' => $this->transactionRepository->getTotalRevenueForPeriod($today, $tomorrow),
        ];
    }

    private function getRevenueAnalytics(string $period): array
    {
        $days = match ($period) {
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            default => 30,
        };

        $data = [];
        $labels = [];
        $totalRevenue = 0;

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = new \DateTime("-{$i} days");
            $nextDate = clone $date;
            $nextDate->add(new \DateInterval('P1D'));

            $dayRevenue = $this->transactionRepository->getTotalRevenueForPeriod($date, $nextDate);
            $totalRevenue += $dayRevenue;

            $labels[] = $date->format('M j');
            $data[] = (float) $dayRevenue;
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'total' => $totalRevenue,
            'average' => count($data) > 0 ? $totalRevenue / count($data) : 0,
        ];
    }

    private function getPaymentMethodAnalytics(string $period): array
    {
        $days = match ($period) {
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            default => 30,
        };

        $from = new \DateTime("-{$days} days");
        $to = new \DateTime();

        $methodStats = $this->transactionRepository->getPaymentMethodStatistics($from, $to);

        $labels = [];
        $data = [];
        $colors = [
            'paynow' => '#3B82F6',
            'stripe' => '#8B5CF6',
            'credit' => '#10B981',
            'wallet' => '#F59E0B',
        ];

        foreach ($methodStats as $stat) {
            $labels[] = ucfirst($stat['payment_method']);
            $data[] = (float) $stat['total_amount'];
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'backgroundColor' => array_values($colors),
        ];
    }

    private function exportToCsv(array $transactions): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="payments_export.csv"');

        $output = fopen('php://output', 'w');

        // CSV Headers
        fputcsv($output, [
            'Transaction ID', 'Customer', 'Amount', 'Currency', 'Type', 'Status',
            'Payment Method', 'Description', 'Created At', 'Completed At'
        ]);

        foreach ($transactions as $transaction) {
            fputcsv($output, [
                $transaction->getTransactionId(),
                $transaction->getCustomer()?->getCompanyName(),
                $transaction->getAmount(),
                $transaction->getCurrency(),
                $transaction->getType(),
                $transaction->getStatus(),
                $transaction->getPaymentMethod(),
                $transaction->getDescription(),
                $transaction->getCreatedAt()?->format('Y-m-d H:i:s'),
                $transaction->getCompletedAt()?->format('Y-m-d H:i:s'),
            ]);
        }

        fclose($output);

        return $response;
    }
}