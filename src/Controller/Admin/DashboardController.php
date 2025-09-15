<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Repository\ShipmentRepository;
use App\Repository\TransactionRepository;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_SYSTEM_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly OrderRepository $orderRepository,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly TransactionRepository $transactionRepository,
        private readonly NotificationRepository $notificationRepository
    ) {}

    #[Route('/dashboard', name: 'admin_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $statistics = $this->getDashboardStatistics();

        return $this->render('admin/dashboard.html.twig', [
            'statistics' => $statistics,
            'recent_activity' => $this->getRecentActivity(),
        ]);
    }

    #[Route('/dashboard/api/statistics', name: 'admin_dashboard_statistics', methods: ['GET'])]
    public function getStatisticsApi(): JsonResponse
    {
        return $this->json($this->getDashboardStatistics());
    }

    #[Route('/dashboard/api/recent-activity', name: 'admin_dashboard_recent_activity', methods: ['GET'])]
    public function getRecentActivityApi(): JsonResponse
    {
        return $this->json($this->getRecentActivity());
    }

    #[Route('/dashboard/api/revenue-chart', name: 'admin_dashboard_revenue_chart', methods: ['GET'])]
    public function getRevenueChartApi(): JsonResponse
    {
        $data = $this->getRevenueChartData();
        return $this->json($data);
    }

    private function getDashboardStatistics(): array
    {
        $now = new \DateTime();
        $startOfMonth = new \DateTime('first day of this month');
        $startOfWeek = new \DateTime('monday this week');
        $yesterday = new \DateTime('yesterday');

        // Get basic counts
        $totalCustomers = $this->customerRepository->count([]);
        $totalOrders = $this->orderRepository->count([]);
        $totalShipments = $this->shipmentRepository->count([]);

        // Get period-based statistics
        $ordersThisMonth = $this->orderRepository->countForPeriod($startOfMonth, $now);
        $ordersThisWeek = $this->orderRepository->countForPeriod($startOfWeek, $now);
        $ordersToday = $this->orderRepository->countForPeriod(new \DateTime('today'), $now);

        $revenueThisMonth = $this->transactionRepository->getTotalRevenueForPeriod($startOfMonth, $now);
        $revenueThisWeek = $this->transactionRepository->getTotalRevenueForPeriod($startOfWeek, $now);
        $revenueToday = $this->transactionRepository->getTotalRevenueForPeriod(new \DateTime('today'), $now);

        // Calculate growth rates
        $previousMonth = new \DateTime('first day of last month');
        $endOfPreviousMonth = new \DateTime('last day of last month');
        $ordersPreviousMonth = $this->orderRepository->countForPeriod($previousMonth, $endOfPreviousMonth);
        $revenuePreviousMonth = $this->transactionRepository->getTotalRevenueForPeriod($previousMonth, $endOfPreviousMonth);

        $orderGrowth = $ordersPreviousMonth > 0 ?
            round((($ordersThisMonth - $ordersPreviousMonth) / $ordersPreviousMonth) * 100, 1) : 0;

        $revenueGrowth = $revenuePreviousMonth > 0 ?
            round((($revenueThisMonth - $revenuePreviousMonth) / $revenuePreviousMonth) * 100, 1) : 0;

        return [
            'overview' => [
                'total_customers' => $totalCustomers,
                'total_orders' => $totalOrders,
                'total_shipments' => $totalShipments,
                'total_revenue' => $revenueThisMonth,
            ],
            'periods' => [
                'this_month' => [
                    'orders' => $ordersThisMonth,
                    'revenue' => $revenueThisMonth,
                    'order_growth' => $orderGrowth,
                    'revenue_growth' => $revenueGrowth,
                ],
                'this_week' => [
                    'orders' => $ordersThisWeek,
                    'revenue' => $revenueThisWeek,
                ],
                'today' => [
                    'orders' => $ordersToday,
                    'revenue' => $revenueToday,
                ],
            ],
            'shipments' => $this->getShipmentStatistics(),
            'notifications' => $this->getNotificationStatistics(),
        ];
    }

    private function getShipmentStatistics(): array
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        return [
            'pending' => $this->shipmentRepository->count(['status' => 'pending']),
            'in_transit' => $this->shipmentRepository->count(['status' => 'in_transit']),
            'delivered' => $this->shipmentRepository->countDeliveredToday(),
            'failed' => $this->shipmentRepository->count(['status' => 'failed']),
        ];
    }

    private function getNotificationStatistics(): array
    {
        $startOfDay = new \DateTime('today');
        $endOfDay = new \DateTime('tomorrow');

        $stats = $this->notificationRepository->getStatistics($startOfDay, $endOfDay);

        return [
            'total_today' => (int) $stats['total_notifications'],
            'sent_today' => (int) $stats['sent_notifications'],
            'pending' => (int) $stats['pending_notifications'],
            'failed' => (int) $stats['failed_notifications'],
        ];
    }

    private function getRecentActivity(): array
    {
        // Get recent orders
        $recentOrders = $this->orderRepository->findBy([], ['createdAt' => 'DESC'], 10);

        // Get recent transactions
        $recentTransactions = $this->transactionRepository->findBy([], ['createdAt' => 'DESC'], 10);

        // Get recent shipments
        $recentShipments = $this->shipmentRepository->findBy([], ['createdAt' => 'DESC'], 10);

        $activities = [];

        // Add orders to activity feed
        foreach ($recentOrders as $order) {
            $activities[] = [
                'type' => 'order',
                'title' => 'New Order #' . $order->getOrderNumber(),
                'description' => 'Order created by customer',
                'timestamp' => $order->getCreatedAt(),
                'icon' => 'shopping-bag',
                'color' => 'blue',
            ];
        }

        // Add transactions to activity feed
        foreach ($recentTransactions as $transaction) {
            $activities[] = [
                'type' => 'transaction',
                'title' => 'Transaction ' . $transaction->getTransactionId(),
                'description' => ucfirst($transaction->getType()) . ' - ' . $transaction->getAmountFormatted(),
                'timestamp' => $transaction->getCreatedAt(),
                'icon' => 'credit-card',
                'color' => $transaction->isCompleted() ? 'green' : ($transaction->isFailed() ? 'red' : 'yellow'),
            ];
        }

        // Add shipments to activity feed
        foreach ($recentShipments as $shipment) {
            $activities[] = [
                'type' => 'shipment',
                'title' => 'Shipment #' . $shipment->getTrackingNumber(),
                'description' => 'Status: ' . ucfirst($shipment->getStatus()),
                'timestamp' => $shipment->getCreatedAt(),
                'icon' => 'truck',
                'color' => 'purple',
            ];
        }

        // Sort by timestamp (newest first)
        usort($activities, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        return array_slice($activities, 0, 20);
    }

    private function getRevenueChartData(): array
    {
        $data = [];
        $labels = [];

        // Get last 30 days of revenue data
        for ($i = 29; $i >= 0; $i--) {
            $date = new \DateTime("-{$i} days");
            $nextDate = clone $date;
            $nextDate->add(new \DateInterval('P1D'));

            $revenue = $this->transactionRepository->getTotalRevenueForPeriod($date, $nextDate);

            $labels[] = $date->format('M j');
            $data[] = (float) $revenue;
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'total' => array_sum($data),
            'average' => count($data) > 0 ? array_sum($data) / count($data) : 0,
        ];
    }
}