<?php

declare(strict_types=1);

namespace App\Service\InPost;

use App\Service\InPostApiClient;
use Psr\Log\LoggerInterface;

class InPostService
{
    public function __construct(
        private readonly InPostApiClient $inPostApiClient,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get nearby InPost points
     */
    public function getNearbyPoints(string $address, int $radius = 5000, string $type = 'parcel_locker'): array
    {
        try {
            // Parse address to get coordinates (simplified version)
            $coordinates = $this->geocodeAddress($address);

            if (!$coordinates) {
                // Fallback to default Warsaw coordinates
                $coordinates = ['lat' => 52.2297, 'lng' => 21.0122];
            }

            $params = [
                'relative_point' => $coordinates['lat'] . ',' . $coordinates['lng'],
                'max_distance' => $radius,
                'max_results' => 20,
                'type' => $type,
                'functions' => 'parcel_collect,parcel_send'
            ];

            $response = $this->inPostApiClient->getParcelLockers($params);

            return $this->formatPoints($response['items'] ?? []);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get InPost points', [
                'address' => $address,
                'error' => $e->getMessage()
            ]);

            return $this->getFallbackPoints();
        }
    }

    /**
     * Create InPost shipment
     */
    public function createShipment(array $shipmentData): array
    {
        try {
            $inpostData = $this->formatShipmentData($shipmentData);
            $response = $this->inPostApiClient->createShipment($inpostData);

            return [
                'success' => true,
                'inpost_id' => $response['id'],
                'tracking_number' => $response['tracking_number'],
                'status' => $response['status'],
                'label_url' => $response['label_url'] ?? null
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to create InPost shipment', [
                'error' => $e->getMessage(),
                'data' => $shipmentData
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get shipment tracking information
     */
    public function getTrackingInfo(string $trackingNumber): array
    {
        try {
            $response = $this->inPostApiClient->trackShipment($trackingNumber);

            return [
                'tracking_number' => $trackingNumber,
                'status' => $response['status'],
                'events' => $this->formatTrackingEvents($response['tracking_details'] ?? []),
                'estimated_delivery' => $response['estimated_delivery_date'] ?? null,
                'pickup_point' => $response['pickup_point'] ?? null
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get InPost tracking', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage()
            ]);

            return [
                'tracking_number' => $trackingNumber,
                'status' => 'unknown',
                'events' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get shipment label
     */
    public function getShipmentLabel(string $shipmentId): array
    {
        try {
            $labelContent = $this->inPostApiClient->getShipmentLabel($shipmentId);

            return [
                'success' => true,
                'label_content' => base64_encode($labelContent),
                'content_type' => 'application/pdf',
                'filename' => "inpost_label_{$shipmentId}.pdf"
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get InPost label', [
                'shipment_id' => $shipmentId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate shipping price
     */
    public function calculatePrice(array $packageData): array
    {
        try {
            // InPost pricing is usually based on package size and destination
            $size = $this->determinePackageSize($packageData);
            $price = $this->getPriceForSize($size, $packageData['destination_country'] ?? 'PL');

            return [
                'success' => true,
                'base_price' => $price,
                'currency' => 'PLN',
                'service_type' => 'parcel_locker',
                'estimated_delivery_days' => $this->getEstimatedDeliveryDays($packageData)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to calculate InPost price', [
                'error' => $e->getMessage(),
                'package_data' => $packageData
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Private helper methods

    private function geocodeAddress(string $address): ?array
    {
        // Simplified geocoding - in real implementation, use proper geocoding service
        // For now, return some sample coordinates for major Polish cities
        $cityCoordinates = [
            'Warsaw' => ['lat' => 52.2297, 'lng' => 21.0122],
            'Krakow' => ['lat' => 50.0647, 'lng' => 19.9450],
            'Gdansk' => ['lat' => 54.3520, 'lng' => 18.6466],
            'Wroclaw' => ['lat' => 51.1079, 'lng' => 17.0385],
            'Poznan' => ['lat' => 52.4064, 'lng' => 16.9252]
        ];

        foreach ($cityCoordinates as $city => $coords) {
            if (stripos($address, $city) !== false) {
                return $coords;
            }
        }

        return null;
    }

    private function formatPoints(array $points): array
    {
        return array_map(function ($point) {
            return [
                'name' => $point['name'],
                'address' => $point['address']['line1'] . ', ' . $point['address']['city'],
                'city' => $point['address']['city'],
                'postal_code' => $point['address']['post_code'],
                'latitude' => $point['location']['latitude'],
                'longitude' => $point['location']['longitude'],
                'distance' => $point['distance'] ?? 0,
                'opening_hours' => $point['opening_hours'] ?? [],
                'type' => $point['type'],
                'status' => $point['status'],
                'payment_available' => $point['payment_available'] ?? false,
                'partner' => $point['partner'] ?? null
            ];
        }, $points);
    }

    private function getFallbackPoints(): array
    {
        // Return some sample points in case API fails
        return [
            [
                'name' => 'WAW01234',
                'address' => 'ul. Marszałkowska 1, Warsaw',
                'city' => 'Warsaw',
                'postal_code' => '00-624',
                'latitude' => 52.2297,
                'longitude' => 21.0122,
                'distance' => 500,
                'opening_hours' => ['Mon-Sun: 24h'],
                'type' => 'parcel_locker',
                'status' => 'Operating',
                'payment_available' => true,
                'partner' => null
            ],
            [
                'name' => 'WAW05678',
                'address' => 'ul. Żurawia 10, Warsaw',
                'city' => 'Warsaw',
                'postal_code' => '00-515',
                'latitude' => 52.2319,
                'longitude' => 21.0067,
                'distance' => 800,
                'opening_hours' => ['Mon-Sun: 24h'],
                'type' => 'parcel_locker',
                'status' => 'Operating',
                'payment_available' => true,
                'partner' => null
            ]
        ];
    }

    private function formatShipmentData(array $shipmentData): array
    {
        return [
            'receiver' => [
                'first_name' => $this->extractFirstName($shipmentData['recipient_name']),
                'last_name' => $this->extractLastName($shipmentData['recipient_name']),
                'email' => $shipmentData['recipient_email'],
                'phone' => $shipmentData['recipient_phone']
            ],
            'parcels' => [
                [
                    'template' => $this->determineTemplate($shipmentData),
                    'dimensions' => [
                        'length' => $shipmentData['dimensions']['length'] ?? 30,
                        'width' => $shipmentData['dimensions']['width'] ?? 20,
                        'height' => $shipmentData['dimensions']['height'] ?? 10,
                        'unit' => 'cm'
                    ],
                    'weight' => [
                        'amount' => $shipmentData['weight_kg'],
                        'unit' => 'kg'
                    ],
                    'reference' => $shipmentData['reference'] ?? null
                ]
            ],
            'service' => 'inpost_locker_standard',
            'reference' => $shipmentData['reference'] ?? null,
            'comments' => $shipmentData['comments'] ?? null
        ];
    }

    private function formatTrackingEvents(array $events): array
    {
        return array_map(function ($event) {
            return [
                'date' => $event['date'],
                'status' => $event['status'],
                'description' => $event['description'],
                'location' => $event['location'] ?? null
            ];
        }, $events);
    }

    private function determinePackageSize(array $packageData): string
    {
        $weight = $packageData['weight_kg'] ?? 0;
        $dimensions = $packageData['dimensions'] ?? [];

        if ($weight <= 1 && isset($dimensions['length']) && $dimensions['length'] <= 35) {
            return 'small';
        } elseif ($weight <= 5) {
            return 'medium';
        } else {
            return 'large';
        }
    }

    private function getPriceForSize(string $size, string $country): float
    {
        $prices = [
            'PL' => [
                'small' => 9.99,
                'medium' => 13.99,
                'large' => 17.99
            ],
            'default' => [
                'small' => 15.99,
                'medium' => 22.99,
                'large' => 29.99
            ]
        ];

        $countryPrices = $prices[$country] ?? $prices['default'];
        return $countryPrices[$size] ?? $countryPrices['medium'];
    }

    private function getEstimatedDeliveryDays(array $packageData): int
    {
        $country = $packageData['destination_country'] ?? 'PL';

        return match ($country) {
            'PL' => 1,
            'DE', 'CZ', 'SK', 'AT' => 2,
            default => 3
        };
    }

    private function determineTemplate(array $shipmentData): string
    {
        $weight = $shipmentData['weight_kg'] ?? 0;

        if ($weight <= 1) {
            return 'small';
        } elseif ($weight <= 5) {
            return 'medium';
        } else {
            return 'large';
        }
    }

    private function extractFirstName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName), 2);
        return $parts[0] ?? '';
    }

    private function extractLastName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName), 2);
        return $parts[1] ?? '';
    }
}