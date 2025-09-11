<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

#[Route('/api/v1/customer')]
class CustomerOrdersController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    #[Route('/orders', name: 'api_customer_orders_list', methods: ['GET'])]
    public function getOrders(Request $request): JsonResponse
    {
        try {
            // Mock data for now - replace with actual database query
            $orders = [
                [
                    'id' => 'ORD-2025-0012',
                    'orderNumber' => 'ORD-2025-0012',
                    'status' => 'processing',
                    'amount' => 199.99,
                    'currency' => 'PLN',
                    'createdAt' => '2025-09-01 10:22:00',
                    'carrier' => 'InPost',
                    'trackingNumber' => 'INP240901001',
                    'recipient' => [
                        'name' => 'Jan Kowalski',
                        'address' => 'ul. Przykładowa 123, 00-001 Warszawa'
                    ]
                ],
                [
                    'id' => 'ORD-2025-0011',
                    'orderNumber' => 'ORD-2025-0011',
                    'status' => 'shipped',
                    'amount' => 89.50,
                    'currency' => 'PLN',
                    'createdAt' => '2025-08-30 14:12:00',
                    'carrier' => 'DHL',
                    'trackingNumber' => 'DHL240830002',
                    'recipient' => [
                        'name' => 'Anna Nowak',
                        'address' => 'ul. Krakowska 45, 30-001 Kraków'
                    ]
                ],
                [
                    'id' => 'ORD-2025-0010',
                    'orderNumber' => 'ORD-2025-0010',
                    'status' => 'delivered',
                    'amount' => 349.00,
                    'currency' => 'PLN',
                    'createdAt' => '2025-08-27 09:05:00',
                    'carrier' => 'InPost',
                    'trackingNumber' => 'INP240827001',
                    'recipient' => [
                        'name' => 'Piotr Wiśniewski',
                        'address' => 'ul. Gdańska 78, 80-001 Gdańsk'
                    ]
                ]
            ];

            $this->logger->info('Customer orders retrieved', [
                'customer_id' => $this->getUser()?->getId(),
                'orders_count' => count($orders)
            ]);

            return $this->json([
                'success' => true,
                'data' => $orders
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve customer orders', [
                'error' => $e->getMessage(),
                'customer_id' => $this->getUser()?->getId()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve orders'
            ], 500);
        }
    }

    #[Route('/orders/{id}', name: 'api_customer_order_details', methods: ['GET'])]
    public function getOrderDetails(string $id): JsonResponse
    {
        try {
            // Mock detailed order data
            $order = [
                'id' => $id,
                'orderNumber' => $id,
                'status' => 'processing',
                'amount' => 199.99,
                'currency' => 'PLN',
                'createdAt' => '2025-09-01 10:22:00',
                'carrier' => 'InPost',
                'trackingNumber' => 'INP240901001',
                'recipient' => [
                    'name' => 'Jan Kowalski',
                    'phone' => '+48 123 456 789',
                    'email' => 'jan.kowalski@example.com',
                    'address' => 'ul. Przykładowa 123, 00-001 Warszawa'
                ],
                'items' => [
                    [
                        'description' => 'Przesyłka standardowa',
                        'weight' => '2.5 kg',
                        'dimensions' => '30x20x10 cm',
                        'price' => 18.45
                    ]
                ],
                'pricing' => [
                    'basePrice' => 15.00,
                    'taxAmount' => 3.45,
                    'totalPrice' => 18.45,
                    'currency' => 'PLN'
                ],
                'timeline' => [
                    [
                        'date' => '2025-09-01 10:22:00',
                        'status' => 'created',
                        'description' => 'Zamówienie zostało utworzone'
                    ],
                    [
                        'date' => '2025-09-01 14:30:00',
                        'status' => 'processing',
                        'description' => 'Zamówienie w trakcie przetwarzania'
                    ]
                ]
            ];

            return $this->json([
                'success' => true,
                'data' => $order
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve order details', [
                'error' => $e->getMessage(),
                'order_id' => $id
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Order not found'
            ], 404);
        }
    }

    #[Route('/orders/{id}/cancel', name: 'api_customer_cancel_order', methods: ['POST'])]
    public function cancelOrder(string $id): JsonResponse
    {
        try {
            // Mock cancellation logic
            $this->logger->info('Order cancellation requested', [
                'order_id' => $id,
                'customer_id' => $this->getUser()?->getId()
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Order cancellation requested'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel order', [
                'error' => $e->getMessage(),
                'order_id' => $id
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to cancel order'
            ], 500);
        }
    }
}