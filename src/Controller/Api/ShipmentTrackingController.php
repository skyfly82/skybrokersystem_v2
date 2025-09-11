<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

#[Route('/api/v1')]
class ShipmentTrackingController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    #[Route('/shipments/track/{trackingNumber}', name: 'api_shipment_track', methods: ['GET'])]
    public function trackShipment(string $trackingNumber): JsonResponse
    {
        try {
            // Determine carrier from tracking number format
            $carrier = 'Unknown';
            if (str_starts_with($trackingNumber, 'INP')) {
                $carrier = 'InPost';
            } elseif (str_starts_with($trackingNumber, 'DHL')) {
                $carrier = 'DHL';
            } elseif (str_starts_with($trackingNumber, 'UPS')) {
                $carrier = 'UPS';
            }

            // Mock tracking data - in production, integrate with carrier APIs
            $trackingData = [
                'trackingNumber' => $trackingNumber,
                'carrier' => $carrier,
                'status' => 'in_transit',
                'statusLabel' => 'W transporcie',
                'destination' => 'Warszawa, ul. Przykładowa 123',
                'estimatedDelivery' => '2025-09-12',
                'currentLocation' => 'Sortownia Warszawa',
                'weight' => '2.5 kg',
                'dimensions' => '30x20x10 cm',
                'timeline' => [
                    [
                        'date' => '2025-09-10 14:30:00',
                        'location' => 'Sortownia Warszawa',
                        'status' => 'in_transit',
                        'statusLabel' => 'W transporcie',
                        'description' => 'Przesyłka w drodze do odbiorcy'
                    ],
                    [
                        'date' => '2025-09-10 08:15:00',
                        'location' => 'Sortownia Łódź',
                        'status' => 'sorted',
                        'statusLabel' => 'Posortowano',
                        'description' => 'Przesyłka została posortowana'
                    ],
                    [
                        'date' => '2025-09-09 16:45:00',
                        'location' => 'Oddział nadania',
                        'status' => 'picked_up',
                        'statusLabel' => 'Nadano',
                        'description' => 'Przesyłka została nadana'
                    ]
                ]
            ];

            $this->logger->info('Shipment tracking requested', [
                'tracking_number' => $trackingNumber,
                'carrier' => $carrier
            ]);

            return $this->json([
                'success' => true,
                'data' => $trackingData
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to track shipment', [
                'error' => $e->getMessage(),
                'tracking_number' => $trackingNumber
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Shipment not found or tracking unavailable'
            ], 404);
        }
    }

    #[Route('/customer/shipments', name: 'api_customer_shipments', methods: ['GET'])]
    public function getCustomerShipments(Request $request): JsonResponse
    {
        try {
            // Mock customer shipments data
            $shipments = [
                [
                    'id' => 1,
                    'trackingNumber' => 'INP240901001',
                    'carrier' => 'InPost',
                    'status' => 'in_transit',
                    'statusLabel' => 'W transporcie',
                    'destination' => 'Warszawa',
                    'createdAt' => '2025-09-01',
                    'estimatedDelivery' => '2025-09-12',
                    'weight' => '2.5 kg',
                    'currentLocation' => 'Sortownia Warszawa'
                ],
                [
                    'id' => 2,
                    'trackingNumber' => 'DHL240830002',
                    'carrier' => 'DHL',
                    'status' => 'delivered',
                    'statusLabel' => 'Dostarczona',
                    'destination' => 'Kraków',
                    'createdAt' => '2025-08-30',
                    'deliveredAt' => '2025-08-31',
                    'weight' => '1.8 kg',
                    'currentLocation' => 'Dostarczona'
                ],
                [
                    'id' => 3,
                    'trackingNumber' => 'INP240828003',
                    'carrier' => 'InPost',
                    'status' => 'processing',
                    'statusLabel' => 'Przetwarzana',
                    'destination' => 'Gdańsk',
                    'createdAt' => '2025-08-28',
                    'estimatedDelivery' => '2025-09-13',
                    'weight' => '0.5 kg',
                    'currentLocation' => 'Oddział nadania'
                ]
            ];

            $this->logger->info('Customer shipments retrieved', [
                'customer_id' => $this->getUser()?->getId(),
                'shipments_count' => count($shipments)
            ]);

            return $this->json([
                'success' => true,
                'data' => $shipments
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve customer shipments', [
                'error' => $e->getMessage(),
                'customer_id' => $this->getUser()?->getId()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve shipments'
            ], 500);
        }
    }

    #[Route('/shipments/batch-track', name: 'api_batch_track_shipments', methods: ['POST'])]
    public function batchTrackShipments(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $trackingNumbers = $data['trackingNumbers'] ?? [];

            if (empty($trackingNumbers)) {
                return $this->json([
                    'success' => false,
                    'error' => 'No tracking numbers provided'
                ], 400);
            }

            $results = [];
            foreach ($trackingNumbers as $trackingNumber) {
                // Mock tracking for each number
                $carrier = str_starts_with($trackingNumber, 'INP') ? 'InPost' : 'DHL';
                $results[] = [
                    'trackingNumber' => $trackingNumber,
                    'carrier' => $carrier,
                    'status' => 'in_transit',
                    'currentLocation' => 'Sortownia Warszawa',
                    'lastUpdate' => date('Y-m-d H:i:s')
                ];
            }

            return $this->json([
                'success' => true,
                'data' => $results
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to batch track shipments', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to track shipments'
            ], 500);
        }
    }
}