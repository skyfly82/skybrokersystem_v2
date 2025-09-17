<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Repository\ShipmentRepository;
use App\Repository\OrderRepository;
use App\Repository\TransactionRepository;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Customer Dashboard Service
 * Provides comprehensive dashboard data and analytics for customers
 */
class CustomerDashboardService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly OrderRepository $orderRepository,
        private readonly TransactionRepository $transactionRepository,
        private readonly InvoiceRepository $invoiceRepository
    ) {
    }

    /**
     * Get comprehensive dashboard statistics
     */
    public function getComprehensiveStats(int $customerId, int $periodDays): array
    {
        $fromDate = new \DateTime("-{$periodDays} days");
        $toDate = new \DateTime();

        return [
            'shipments' => $this->getShipmentStatistics($customerId, $periodDays),
            'financial' => $this->getFinancialStatistics($customerId, $periodDays),
            'performance' => $this->getPerformanceMetrics($customerId, $periodDays),
            'courier_breakdown' => $this->getCourierServiceUsage($customerId, $periodDays),
            'trends' => $this->getTrendAnalysis($customerId, $periodDays),
            'period' => [
                'from' => $fromDate->format('Y-m-d'),
                'to' => $toDate->format('Y-m-d'),
                'days' => $periodDays
            ]
        ];
    }

    /**
     * Get shipment statistics
     */
    public function getShipmentStatistics(int $customerId, int $periodDays): array
    {
        $fromDate = new \DateTime("-{$periodDays} days");

        $qb = $this->shipmentRepository->createQueryBuilder('s')
            ->join('s.order', 'o')
            ->join('o.customer', 'c')
            ->where('c.id = :customerId')
            ->andWhere('s.createdAt >= :fromDate')
            ->setParameter('customerId', $customerId)
            ->setParameter('fromDate', $fromDate);

        // Total shipments
        $totalShipments = (clone $qb)->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();

        // Shipments by status
        $statusCounts = (clone $qb)
            ->select('s.status, COUNT(s.id) as count')
            ->groupBy('s.status')
            ->getQuery()
            ->getResult();

        // Average delivery time for delivered shipments - fetch data and calculate in PHP
        $deliveredShipments = (clone $qb)
            ->select('s.createdAt, s.deliveredAt')
            ->andWhere('s.status = :delivered')
            ->andWhere('s.deliveredAt IS NOT NULL')
            ->setParameter('delivered', 'delivered')
            ->getQuery()
            ->getResult();

        $avgDeliveryTime = null;
        if (!empty($deliveredShipments)) {
            $totalHours = 0;
            $count = 0;
            foreach ($deliveredShipments as $shipment) {
                if ($shipment['deliveredAt'] && $shipment['createdAt']) {
                    $created = new \DateTime($shipment['createdAt']);
                    $delivered = new \DateTime($shipment['deliveredAt']);
                    $diff = $delivered->getTimestamp() - $created->getTimestamp();
                    $totalHours += $diff / 3600; // Convert seconds to hours
                    $count++;
                }
            }
            $avgDeliveryTime = $count > 0 ? $totalHours / $count : 0;
        }

        // Success rate (delivered + dispatched vs total)
        $successfulShipments = (clone $qb)
            ->select('COUNT(s.id)')
            ->andWhere('s.status IN (:successStatuses)')
            ->setParameter('successStatuses', ['delivered', 'dispatched'])
            ->getQuery()
            ->getSingleScalarResult();

        $successRate = $totalShipments > 0 ? ($successfulShipments / $totalShipments) * 100 : 0;

        return [
            'total' => (int) $totalShipments,
            'by_status' => array_column($statusCounts, 'count', 'status'),
            'success_rate' => round($successRate, 2),
            'average_delivery_hours' => $avgDeliveryTime ? round((float) $avgDeliveryTime, 1) : null,
            'volume_trend' => $this->getShipmentVolumeTrend($customerId, $periodDays)
        ];
    }

    /**
     * Get financial statistics
     */
    public function getFinancialStatistics(int $customerId, int $periodDays): array
    {
        $fromDate = new \DateTime("-{$periodDays} days");

        // Total shipping costs
        $totalShippingCosts = $this->shipmentRepository->createQueryBuilder('s')
            ->select('SUM(s.shippingCost)')
            ->join('s.order', 'o')
            ->join('o.customer', 'c')
            ->where('c.id = :customerId')
            ->andWhere('s.createdAt >= :fromDate')
            ->andWhere('s.shippingCost IS NOT NULL')
            ->setParameter('customerId', $customerId)
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getSingleScalarResult();

        // Total invoice amounts - disabled until Invoice-Customer relationship is established
        $totalInvoiceAmount = 0;

        // Pending invoices - disabled until Invoice-Customer relationship is established
        $pendingInvoiceAmount = 0;

        // Average cost per shipment
        $avgCostPerShipment = $this->shipmentRepository->createQueryBuilder('s')
            ->select('AVG(s.shippingCost)')
            ->join('s.order', 'o')
            ->join('o.customer', 'c')
            ->where('c.id = :customerId')
            ->andWhere('s.createdAt >= :fromDate')
            ->andWhere('s.shippingCost IS NOT NULL')
            ->setParameter('customerId', $customerId)
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total_shipping_costs' => round((float) ($totalShippingCosts ?? 0), 2),
            'total_invoice_amount' => round((float) ($totalInvoiceAmount ?? 0), 2),
            'pending_invoice_amount' => round((float) ($pendingInvoiceAmount ?? 0), 2),
            'average_cost_per_shipment' => round((float) ($avgCostPerShipment ?? 0), 2),
            'currency' => 'PLN'
        ];
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(int $customerId, int $periodDays): array
    {
        $fromDate = new \DateTime("-{$periodDays} days");

        // On-time delivery rate
        $onTimeDeliveries = $this->shipmentRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->join('s.order', 'o')
            ->join('o.customer', 'c')
            ->where('c.id = :customerId')
            ->andWhere('s.deliveredAt <= s.estimatedDeliveryAt')
            ->andWhere('s.status = :delivered')
            ->andWhere('s.createdAt >= :fromDate')
            ->setParameter('customerId', $customerId)
            ->setParameter('delivered', 'delivered')
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getSingleScalarResult();

        $totalDelivered = $this->shipmentRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->join('s.order', 'o')
            ->join('o.customer', 'c')
            ->where('c.id = :customerId')
            ->andWhere('s.status = :delivered')
            ->andWhere('s.createdAt >= :fromDate')
            ->setParameter('customerId', $customerId)
            ->setParameter('delivered', 'delivered')
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getSingleScalarResult();

        $onTimeRate = $totalDelivered > 0 ? ($onTimeDeliveries / $totalDelivered) * 100 : 0;

        // Cancellation rate
        $canceledShipments = $this->shipmentRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->join('s.order', 'o')
            ->join('o.customer', 'c')
            ->where('c.id = :customerId')
            ->andWhere('s.status = :canceled')
            ->andWhere('s.createdAt >= :fromDate')
            ->setParameter('customerId', $customerId)
            ->setParameter('canceled', 'canceled')
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getSingleScalarResult();

        $totalShipments = $this->shipmentRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->join('s.order', 'o')
            ->join('o.customer', 'c')
            ->where('c.id = :customerId')
            ->andWhere('s.createdAt >= :fromDate')
            ->setParameter('customerId', $customerId)
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getSingleScalarResult();

        $cancellationRate = $totalShipments > 0 ? ($canceledShipments / $totalShipments) * 100 : 0;

        return [
            'on_time_delivery_rate' => round($onTimeRate, 2),
            'cancellation_rate' => round($cancellationRate, 2),
            'total_delivered' => (int) $totalDelivered,
            'total_canceled' => (int) $canceledShipments
        ];
    }

    /**
     * Get courier service usage statistics
     */
    public function getCourierServiceUsage(int $customerId, int $periodDays): array
    {
        $fromDate = new \DateTime("-{$periodDays} days");

        $courierStats = $this->shipmentRepository->createQueryBuilder('s')
            ->select('s.courierService, COUNT(s.id) as shipment_count, SUM(s.shippingCost) as total_cost, AVG(s.shippingCost) as avg_cost')
            ->join('s.order', 'o')
            ->join('o.customer', 'c')
            ->where('c.id = :customerId')
            ->andWhere('s.createdAt >= :fromDate')
            ->groupBy('s.courierService')
            ->orderBy('shipment_count', 'DESC')
            ->setParameter('customerId', $customerId)
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getResult();

        $totalShipments = array_sum(array_column($courierStats, 'shipment_count'));

        return array_map(function ($stat) use ($totalShipments) {
            return [
                'courier_service' => $stat['courierService'],
                'shipment_count' => (int) $stat['shipment_count'],
                'percentage' => $totalShipments > 0 ? round(($stat['shipment_count'] / $totalShipments) * 100, 2) : 0,
                'total_cost' => round((float) ($stat['total_cost'] ?? 0), 2),
                'average_cost' => round((float) ($stat['avg_cost'] ?? 0), 2)
            ];
        }, $courierStats);
    }

    /**
     * Get recent activity feed
     */
    public function getRecentActivity(int $customerId, int $limit, int $offset): array
    {
        // This would typically combine data from multiple sources
        // For now, focusing on shipment-related activities

        $activities = [];

        // Recent shipments
        $recentShipments = $this->shipmentRepository->createQueryBuilder('s')
            ->join('s.order', 'o')
            ->join('o.customer', 'c')
            ->where('c.id = :customerId')
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->setParameter('customerId', $customerId)
            ->getQuery()
            ->getResult();

        foreach ($recentShipments as $shipment) {
            $activities[] = [
                'type' => 'shipment_created',
                'title' => 'New shipment created',
                'description' => "Shipment {$shipment->getTrackingNumber()} to {$shipment->getRecipientCity()}",
                'date' => $shipment->getCreatedAt()->format('Y-m-d H:i:s'),
                'metadata' => [
                    'shipment_id' => $shipment->getId(),
                    'tracking_number' => $shipment->getTrackingNumber(),
                    'status' => $shipment->getStatus()
                ]
            ];
        }

        // Sort by date
        usort($activities, fn($a, $b) => $b['date'] <=> $a['date']);

        return array_slice($activities, 0, $limit);
    }

    /**
     * Get revenue analytics with time-based grouping
     */
    public function getRevenueAnalytics(int $customerId, int $periodDays, string $groupBy): array
    {
        $fromDate = new \DateTime("-{$periodDays} days");

        // Fetch raw data and group in PHP for cross-database compatibility
        $shipments = $this->shipmentRepository->createQueryBuilder('s')
            ->select('s.createdAt, s.shippingCost')
            ->join('s.order', 'o')
            ->join('o.customer', 'c')
            ->where('c.id = :customerId')
            ->andWhere('s.createdAt >= :fromDate')
            ->andWhere('s.shippingCost IS NOT NULL')
            ->orderBy('s.createdAt', 'ASC')
            ->setParameter('customerId', $customerId)
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getResult();

        $revenue = [];
        foreach ($shipments as $shipment) {
            $date = new \DateTime($shipment['createdAt']);
            $period = match ($groupBy) {
                'day' => $date->format('Y-m-d'),
                'week' => $date->format('Y-W'),
                'month' => $date->format('Y-m'),
                default => $date->format('Y-m-d')
            };

            if (!isset($revenue[$period])) {
                $revenue[$period] = ['period' => $period, 'revenue' => 0, 'shipments' => 0];
            }
            $revenue[$period]['revenue'] += (float) $shipment['shippingCost'];
            $revenue[$period]['shipments']++;
        }

        $revenue = array_values($revenue);

        return [
            'chart_data' => $revenue,
            'total_revenue' => array_sum(array_column($revenue, 'revenue')),
            'total_shipments' => array_sum(array_column($revenue, 'shipments')),
            'group_by' => $groupBy
        ];
    }

    /**
     * Get performance comparison between periods
     */
    public function getPerformanceComparison(int $customerId, int $currentPeriod, int $comparePeriod): array
    {
        $currentStats = $this->getComprehensiveStats($customerId, $currentPeriod);

        $compareFromDate = new \DateTime("-" . ($currentPeriod + $comparePeriod) . " days");
        $compareToDate = new \DateTime("-{$currentPeriod} days");

        // Calculate comparison stats for the previous period
        $previousStats = $this->getStatsForPeriod($customerId, $compareFromDate, $compareToDate);

        return [
            'current' => $currentStats,
            'previous' => $previousStats,
            'comparison' => $this->calculatePercentageChanges($currentStats, $previousStats)
        ];
    }

    /**
     * Get trend analysis
     */
    private function getTrendAnalysis(int $customerId, int $periodDays): array
    {
        // Calculate daily shipment volume for trend analysis
        $dailyVolume = $this->getShipmentVolumeTrend($customerId, $periodDays);

        // Simple trend calculation (positive/negative/stable)
        $values = array_column($dailyVolume, 'count');
        if (count($values) < 2) {
            $trend = 'stable';
        } else {
            $firstHalf = array_slice($values, 0, intval(count($values) / 2));
            $secondHalf = array_slice($values, intval(count($values) / 2));

            $firstAvg = array_sum($firstHalf) / count($firstHalf);
            $secondAvg = array_sum($secondHalf) / count($secondHalf);

            $change = (($secondAvg - $firstAvg) / max($firstAvg, 1)) * 100;

            if ($change > 10) {
                $trend = 'increasing';
            } elseif ($change < -10) {
                $trend = 'decreasing';
            } else {
                $trend = 'stable';
            }
        }

        return [
            'shipment_volume' => $trend,
            'trend_percentage' => round($change ?? 0, 2)
        ];
    }

    /**
     * Get shipment volume trend by day
     */
    private function getShipmentVolumeTrend(int $customerId, int $periodDays): array
    {
        $fromDate = new \DateTime("-{$periodDays} days");

        // Fetch raw data and group by date in PHP for cross-database compatibility
        $shipments = $this->shipmentRepository->createQueryBuilder('s')
            ->select('s.createdAt')
            ->join('s.order', 'o')
            ->join('o.customer', 'c')
            ->where('c.id = :customerId')
            ->andWhere('s.createdAt >= :fromDate')
            ->orderBy('s.createdAt', 'ASC')
            ->setParameter('customerId', $customerId)
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getResult();

        $trends = [];
        foreach ($shipments as $shipment) {
            $date = (new \DateTime($shipment['createdAt']))->format('Y-m-d');
            if (!isset($trends[$date])) {
                $trends[$date] = ['date' => $date, 'count' => 0];
            }
            $trends[$date]['count']++;
        }

        return array_values($trends);
    }

    /**
     * Get statistics for a specific period
     */
    private function getStatsForPeriod(int $customerId, \DateTime $fromDate, \DateTime $toDate): array
    {
        // Implementation would be similar to getComprehensiveStats but with custom date range
        // This is a simplified version
        return [
            'shipments' => ['total' => 0],
            'financial' => ['total_shipping_costs' => 0]
        ];
    }

    /**
     * Calculate percentage changes between current and previous periods
     */
    private function calculatePercentageChanges(array $current, array $previous): array
    {
        $changes = [];

        // Shipment volume change
        $currentShipments = $current['shipments']['total'] ?? 0;
        $previousShipments = $previous['shipments']['total'] ?? 0;
        $changes['shipments'] = $this->calculatePercentageChange($currentShipments, $previousShipments);

        // Revenue change
        $currentRevenue = $current['financial']['total_shipping_costs'] ?? 0;
        $previousRevenue = $previous['financial']['total_shipping_costs'] ?? 0;
        $changes['revenue'] = $this->calculatePercentageChange($currentRevenue, $previousRevenue);

        return $changes;
    }

    /**
     * Calculate percentage change between two values
     */
    private function calculatePercentageChange(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }
}