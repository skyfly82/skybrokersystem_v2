<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Customer dashboard data provider
 */
class CustomerDashboardDataProvider implements DashboardDataProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {}

    public function getDashboardData(): array
    {
        try {
            return [
                'overview' => $this->getCustomerOverview(),
                'recent_orders' => $this->getRecentCustomerOrders(),
                'billing_summary' => $this->getBillingSummary(),
                'quick_actions' => $this->getCustomerQuickActions()
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get customer dashboard data', [
                'error' => $e->getMessage()
            ]);
            return $this->getEmptyDashboardData();
        }
    }

    public function getRealtimeUpdates(int $lastUpdate = 0): array
    {
        return [
            'order_updates' => $this->getOrderUpdatesSince($lastUpdate),
            'shipment_tracking' => $this->getShipmentUpdates(),
            'notifications' => $this->getCustomerNotifications()
        ];
    }

    private function getCustomerOverview(): array
    {
        return [
            'total_orders' => 47,
            'orders_this_month' => 12,
            'pending_orders' => 3,
            'in_transit' => 2,
            'delivered_this_month' => 9,
            'total_spent' => 2840.75,
            'spent_this_month' => 487.30,
            'avg_shipping_cost' => 23.45,
            'favorite_carrier' => 'InPost'
        ];
    }

    private function getRecentCustomerOrders(): array
    {
        return [
            [
                'id' => 'ORD-2025-089',
                'tracking_number' => 'INP123456789',
                'status' => 'in_transit',
                'created_at' => '2025-09-10 14:30:00',
                'total' => 45.30,
                'carrier' => 'InPost',
                'destination' => 'Warszawa',
                'estimated_delivery' => '2025-09-12'
            ],
            [
                'id' => 'ORD-2025-087',
                'tracking_number' => 'DHL987654321',
                'status' => 'delivered',
                'created_at' => '2025-09-08 10:15:00',
                'delivered_at' => '2025-09-09 16:45:00',
                'total' => 67.80,
                'carrier' => 'DHL',
                'destination' => 'Kraków'
            ],
            [
                'id' => 'ORD-2025-085',
                'tracking_number' => 'UPS456789123',
                'status' => 'pending',
                'created_at' => '2025-09-11 09:00:00',
                'total' => 89.20,
                'carrier' => 'UPS',
                'destination' => 'Gdańsk',
                'estimated_delivery' => '2025-09-13'
            ]
        ];
    }

    private function getBillingSummary(): array
    {
        return [
            'current_balance' => -156.75,
            'pending_invoices' => 2,
            'overdue_amount' => 89.50,
            'credit_limit' => 5000.00,
            'available_credit' => 4843.25,
            'last_payment' => [
                'amount' => 299.99,
                'date' => '2025-09-11',
                'method' => 'bank_transfer'
            ],
            'next_due_date' => '2025-09-24'
        ];
    }

    private function getCustomerQuickActions(): array
    {
        return [
            [
                'title' => 'Create New Order',
                'url' => '/dashboard/orders/create',
                'icon' => 'plus',
                'primary' => true
            ],
            [
                'title' => 'Track Shipments',
                'url' => '/dashboard/tracking',
                'icon' => 'location',
                'count' => 5
            ],
            [
                'title' => 'View Invoices',
                'url' => '/dashboard/billing',
                'icon' => 'receipt',
                'count' => 2,
                'urgent' => true
            ],
            [
                'title' => 'Calculate Pricing',
                'url' => '/dashboard/pricing-calculator',
                'icon' => 'calculator'
            ]
        ];
    }

    private function getOrderUpdatesSince(int $lastUpdate): array
    {
        return [
            [
                'order_id' => 'ORD-2025-089',
                'status' => 'in_transit',
                'location' => 'Sortownia Warszawa',
                'updated_at' => time() - 900
            ]
        ];
    }

    private function getShipmentUpdates(): array
    {
        return [
            [
                'tracking_number' => 'INP123456789',
                'status' => 'Przesyłka w drodze do odbiorcy',
                'location' => 'Centrum logistyczne Warszawa',
                'estimated_delivery' => '2025-09-12 15:00:00',
                'updated_at' => time() - 1200
            ]
        ];
    }

    private function getCustomerNotifications(): array
    {
        return [
            [
                'type' => 'delivery',
                'title' => 'Przesyłka dostarczona',
                'message' => 'Twoja przesyłka ORD-2025-087 została dostarczona',
                'timestamp' => time() - 3600,
                'read' => false
            ],
            [
                'type' => 'billing',
                'title' => 'Nowa faktura',
                'message' => 'Otrzymałeś nową fakturę INV-2025-003',
                'timestamp' => time() - 7200,
                'read' => false
            ]
        ];
    }

    private function getEmptyDashboardData(): array
    {
        return [
            'overview' => [],
            'recent_orders' => [],
            'billing_summary' => [],
            'quick_actions' => []
        ];
    }
}