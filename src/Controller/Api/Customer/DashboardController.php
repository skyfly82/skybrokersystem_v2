<?php

declare(strict_types=1);

namespace App\Controller\Api\Customer;

use App\Service\Dashboard\CustomerDashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Customer Dashboard API Controller
 * Provides comprehensive dashboard statistics and metrics for authenticated customers
 */
#[Route('/api/v1/customer/dashboard', name: 'api_customer_dashboard_')]
#[IsGranted('ROLE_CUSTOMER_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly CustomerDashboardService $dashboardService
    ) {
    }

    /**
     * Get comprehensive dashboard statistics
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function getDashboardStats(Request $request): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();
        $period = $request->query->get('period', '30'); // days

        $stats = $this->dashboardService->getComprehensiveStats($customerId, (int) $period);

        return $this->json([
            'success' => true,
            'data' => $stats,
            'meta' => [
                'period_days' => $period,
                'generated_at' => new \DateTime(),
            ]
        ]);
    }

    /**
     * Get shipment statistics
     */
    #[Route('/shipments/stats', name: 'shipments_stats', methods: ['GET'])]
    public function getShipmentStats(Request $request): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();
        $period = $request->query->get('period', '30');

        $stats = $this->dashboardService->getShipmentStatistics($customerId, (int) $period);

        return $this->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get revenue and cost analytics
     */
    #[Route('/revenue/analytics', name: 'revenue_analytics', methods: ['GET'])]
    public function getRevenueAnalytics(Request $request): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();
        $period = $request->query->get('period', '30');
        $groupBy = $request->query->get('group_by', 'day'); // day, week, month

        $analytics = $this->dashboardService->getRevenueAnalytics($customerId, (int) $period, $groupBy);

        return $this->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Get recent activity feed
     */
    #[Route('/activity', name: 'activity', methods: ['GET'])]
    public function getRecentActivity(Request $request): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();
        $limit = $request->query->getInt('limit', 20);
        $offset = $request->query->getInt('offset', 0);

        $activities = $this->dashboardService->getRecentActivity($customerId, $limit, $offset);

        return $this->json([
            'success' => true,
            'data' => $activities,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => count($activities)
            ]
        ]);
    }

    /**
     * Get performance metrics comparison
     */
    #[Route('/performance', name: 'performance', methods: ['GET'])]
    public function getPerformanceMetrics(Request $request): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();
        $currentPeriod = $request->query->getInt('current_period', 30);
        $comparePeriod = $request->query->getInt('compare_period', 30);

        $metrics = $this->dashboardService->getPerformanceComparison(
            $customerId,
            $currentPeriod,
            $comparePeriod
        );

        return $this->json([
            'success' => true,
            'data' => $metrics
        ]);
    }

    /**
     * Get courier service usage statistics
     */
    #[Route('/couriers/usage', name: 'couriers_usage', methods: ['GET'])]
    public function getCourierUsage(Request $request): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();
        $period = $request->query->getInt('period', 30);

        $usage = $this->dashboardService->getCourierServiceUsage($customerId, $period);

        return $this->json([
            'success' => true,
            'data' => $usage
        ]);
    }
}