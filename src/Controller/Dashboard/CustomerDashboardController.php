<?php

namespace App\Controller\Dashboard;

use App\Entity\CustomerUser;
use App\Repository\OrderRepository;
use App\Repository\InvoiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CustomerDashboardController extends AbstractController
{
    public function __construct(
        private OrderRepository $orderRepository,
        private InvoiceRepository $invoiceRepository
    ) {}

    #[Route('/api/dashboard/stats', name: 'api_dashboard_stats', methods: ['GET'])]
    #[IsGranted('ROLE_CUSTOMER')]
    public function getDashboardStats(): JsonResponse
    {
        /** @var CustomerUser $user */
        $user = $this->getUser();

        // Fetch total orders
        $totalOrders = $this->orderRepository->countByCustomer($user);
        $ordersInTransit = $this->orderRepository->countInTransitByCustomer($user);
        $totalInvoices = $this->invoiceRepository->countByCustomer($user);
        $totalSpent = $this->orderRepository->calculateTotalSpentByCustomer($user);
        $accountBalance = $user->getAccountBalance();

        // Monthly Activity Data
        $monthlyActivity = $this->orderRepository->getMonthlyOrderActivity($user);

        // Order Status Distribution
        $orderStatusDistribution = $this->orderRepository->getOrderStatusDistribution($user);

        return $this->json([
            'totalOrders' => $totalOrders,
            'ordersInTransit' => $ordersInTransit,
            'totalInvoices' => $totalInvoices,
            'totalSpent' => $totalSpent,
            'accountBalance' => $accountBalance,
            'monthlyActivity' => $monthlyActivity,
            'orderStatusDistribution' => $orderStatusDistribution
        ]);
    }

    #[Route('/api/dashboard/recent-orders', name: 'api_dashboard_recent_orders', methods: ['GET'])]
    #[IsGranted('ROLE_CUSTOMER')]
    public function getRecentOrders(): JsonResponse
    {
        /** @var CustomerUser $user */
        $user = $this->getUser();

        $recentOrders = $this->orderRepository->findRecentByCustomer($user, 5);

        $formattedOrders = array_map(function($order) {
            return [
                'id' => $order->getId(),
                'orderNumber' => $order->getOrderNumber(),
                'status' => $order->getStatus(),
                'total' => $order->getTotalAmount(),
                'date' => $order->getCreatedAt()->format('Y-m-d H:i')
            ];
        }, $recentOrders);

        return $this->json($formattedOrders);
    }
}