<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * System dashboard data provider
 */
class SystemDashboardDataProvider implements DashboardDataProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {}

    public function getDashboardData(): array
    {
        try {
            return [
                'overview' => $this->getOverviewMetrics(),
                'recent_orders' => $this->getRecentOrders(),
                'system_stats' => $this->getSystemStats(),
                'quick_actions' => $this->getQuickActions()
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get system dashboard data', [
                'error' => $e->getMessage()
            ]);
            return $this->getEmptyDashboardData();
        }
    }

    public function getRealtimeUpdates(int $lastUpdate = 0): array
    {
        return [
            'new_orders' => $this->getNewOrdersSince($lastUpdate),
            'status_changes' => $this->getStatusChangesSince($lastUpdate),
            'system_alerts' => $this->getSystemAlerts()
        ];
    }

    private function getOverviewMetrics(): array
    {
        return [
            'total_orders' => 1247,
            'pending_orders' => 89,
            'completed_today' => 156,
            'total_customers' => 342,
            'active_customers' => 189,
            'revenue_today' => 12450.75,
            'revenue_month' => 345600.20,
            'avg_delivery_time' => 2.3
        ];
    }

    private function getRecentOrders(): array
    {
        return [
            [
                'id' => 'ORD-2025-001',
                'customer' => 'ABC Company Sp. z o.o.',
                'status' => 'pending',
                'created_at' => '2025-09-11 10:30:00',
                'total' => 125.50,
                'carrier' => 'InPost'
            ],
            [
                'id' => 'ORD-2025-002',
                'customer' => 'XYZ Trading',
                'status' => 'in_transit',
                'created_at' => '2025-09-11 09:15:00',
                'total' => 89.30,
                'carrier' => 'DHL'
            ],
            [
                'id' => 'ORD-2025-003',
                'customer' => 'Tech Solutions Ltd.',
                'status' => 'delivered',
                'created_at' => '2025-09-11 08:45:00',
                'total' => 234.75,
                'carrier' => 'UPS'
            ]
        ];
    }

    private function getSystemStats(): array
    {
        return [
            'server_status' => 'healthy',
            'api_calls_today' => 15420,
            'error_rate' => 0.05,
            'avg_response_time' => 120,
            'active_sessions' => 45,
            'storage_used' => 78.5
        ];
    }

    private function getQuickActions(): array
    {
        return [
            [
                'title' => 'View Pending Orders',
                'url' => '/dashboard/orders?status=pending',
                'icon' => 'clock',
                'count' => 89
            ],
            [
                'title' => 'Customer Support',
                'url' => '/dashboard/support',
                'icon' => 'support',
                'count' => 12
            ],
            [
                'title' => 'System Reports',
                'url' => '/dashboard/reports',
                'icon' => 'chart',
                'count' => null
            ]
        ];
    }

    private function getNewOrdersSince(int $lastUpdate): array
    {
        // Mock implementation - in production, query database
        return [
            [
                'id' => 'ORD-2025-004',
                'customer' => 'New Customer Ltd.',
                'status' => 'pending',
                'created_at' => time(),
                'total' => 67.20
            ]
        ];
    }

    private function getStatusChangesSince(int $lastUpdate): array
    {
        return [
            [
                'order_id' => 'ORD-2025-002',
                'old_status' => 'pending',
                'new_status' => 'in_transit',
                'updated_at' => time() - 300
            ]
        ];
    }

    private function getSystemAlerts(): array
    {
        return [
            [
                'type' => 'info',
                'message' => 'System backup completed successfully',
                'timestamp' => time() - 1800
            ]
        ];
    }

    private function getEmptyDashboardData(): array
    {
        return [
            'overview' => [],
            'recent_orders' => [],
            'system_stats' => [],
            'quick_actions' => []
        ];
    }
}