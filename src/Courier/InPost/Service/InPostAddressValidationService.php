<?php

declare(strict_types=1);

namespace App\Courier\InPost\Service;

use App\Courier\InPost\DTO\LockerDetailsDTO;
use App\Courier\InPost\Exception\InPostIntegrationException;
use App\Courier\InPost\Config\InPostConfiguration;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class InPostAddressValidationService
{
    private string $geowidgetUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        string $environment = 'sandbox'
    ) {
        $this->geowidgetUrl = InPostConfiguration::getGeowidgetUrl($environment);
    }

    /**
     * Validate Polish address and postal code
     */
    public function validateAddress(string $address, string $postalCode, string $city): array
    {
        if (!InPostConfiguration::validatePolishPostalCode($postalCode)) {
            throw InPostIntegrationException::invalidPostalCode($postalCode);
        }

        try {
            // Use geocoding to validate address
            $response = $this->httpClient->request('GET', $this->geowidgetUrl . '/v1/geocode', [
                'query' => [
                    'q' => "{$address}, {$postalCode} {$city}, Poland",
                    'limit' => 1,
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();
            
            if (empty($data['features'])) {
                return [
                    'valid' => false,
                    'error' => 'Address not found',
                    'suggestions' => [],
                ];
            }

            $feature = $data['features'][0];
            $properties = $feature['properties'] ?? [];
            $geometry = $feature['geometry'] ?? [];
            
            return [
                'valid' => true,
                'normalized_address' => $properties['label'] ?? $address,
                'postal_code' => $properties['postcode'] ?? $postalCode,
                'city' => $properties['city'] ?? $city,
                'latitude' => $geometry['coordinates'][1] ?? null,
                'longitude' => $geometry['coordinates'][0] ?? null,
                'confidence' => $properties['confidence'] ?? 0,
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Address validation failed', [
                'address' => $address,
                'postal_code' => $postalCode,
                'city' => $city,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'valid' => false,
                'error' => 'Address validation service unavailable',
                'suggestions' => [],
            ];
        }
    }

    /**
     * Find nearest Paczkomaty to address
     */
    public function findNearestPaczkomaty(
        string $address,
        string $postalCode,
        string $city,
        int $maxResults = 10,
        int $maxDistanceKm = 5,
        ?string $parcelSize = null
    ): array {
        // First validate and geocode the address
        $addressData = $this->validateAddress($address, $postalCode, $city);
        
        if (!$addressData['valid'] || !$addressData['latitude'] || !$addressData['longitude']) {
            throw new InPostIntegrationException('Could not geocode address for Paczkomat search');
        }

        return $this->findPaczkomatyByCoordinates(
            $addressData['latitude'],
            $addressData['longitude'],
            $maxResults,
            $maxDistanceKm,
            $parcelSize
        );
    }

    /**
     * Find Paczkomaty by coordinates
     */
    public function findPaczkomatyByCoordinates(
        float $latitude,
        float $longitude,
        int $maxResults = 10,
        int $maxDistanceKm = 5,
        ?string $parcelSize = null
    ): array {
        try {
            $params = [
                'relative_point' => "{$latitude},{$longitude}",
                'max_distance' => $maxDistanceKm * 1000, // Convert to meters
                'max_results' => min($maxResults, 50), // API limit
                'type' => 'paczkomat',
                'status' => 'operating',
            ];

            // Filter by parcel size if specified
            if ($parcelSize && InPostConfiguration::isValidParcelSize($parcelSize)) {
                $params['functions'] = "parcel_collect_{$parcelSize}";
            }

            $response = $this->httpClient->request('GET', $this->geowidgetUrl . '/v1/points', [
                'query' => $params,
                'timeout' => 15,
            ]);

            $data = $response->toArray();
            $paczkomaty = [];

            foreach ($data['items'] ?? [] as $item) {
                $locker = LockerDetailsDTO::fromArray($item);
                
                // Calculate distance
                $distance = $this->calculateDistance(
                    $latitude,
                    $longitude,
                    $locker->latitude,
                    $locker->longitude
                );
                
                $paczkomaty[] = [
                    'code' => $locker->name,
                    'name' => $locker->name,
                    'address' => $locker->address,
                    'latitude' => $locker->latitude,
                    'longitude' => $locker->longitude,
                    'distance_km' => round($distance, 2),
                    'status' => $locker->status,
                    'available_sizes' => $locker->availableSizes,
                    'payment_available' => $locker->paymentAvailable,
                    'opening_hours' => $locker->openingHours,
                    'functions' => $locker->functions,
                    'supports_parcel_size' => $parcelSize ? $locker->supportsSize($parcelSize) : true,
                ];
            }

            // Sort by distance
            usort($paczkomaty, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);

            $this->logger->info('Found nearby Paczkomaty', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'max_distance_km' => $maxDistanceKm,
                'parcel_size' => $parcelSize,
                'results_count' => count($paczkomaty),
            ]);

            return $paczkomaty;
            
        } catch (\Exception $e) {
            $this->logger->error('Paczkomat search failed', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage(),
            ]);
            
            throw new InPostIntegrationException(
                'Failed to find nearby Paczkomaty: ' . $e->getMessage(),
                null,
                [],
                0,
                $e
            );
        }
    }

    /**
     * Get detailed information about specific Paczkomat
     */
    public function getPaczkomatDetails(string $paczkomatCode): array
    {
        if (!InPostConfiguration::validatePaczkomatCode($paczkomatCode)) {
            throw InPostIntegrationException::invalidPaczkomat($paczkomatCode);
        }

        try {
            $response = $this->httpClient->request('GET', $this->geowidgetUrl . "/v1/points/{$paczkomatCode}", [
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 404) {
                throw InPostIntegrationException::invalidPaczkomat($paczkomatCode);
            }

            $data = $response->toArray();
            $locker = LockerDetailsDTO::fromArray($data);

            if (!$locker->isActive()) {
                throw InPostIntegrationException::paczkomatNotAvailable($paczkomatCode);
            }

            return [
                'code' => $locker->name,
                'name' => $locker->name,
                'address' => $locker->address,
                'latitude' => $locker->latitude,
                'longitude' => $locker->longitude,
                'status' => $locker->status,
                'available_sizes' => $locker->availableSizes,
                'payment_available' => $locker->paymentAvailable,
                'opening_hours' => $locker->openingHours,
                'type' => $locker->type,
                'description' => $locker->description,
                'functions' => $locker->functions,
                'partner' => $locker->partner,
                'is_active' => $locker->isActive(),
            ];
            
        } catch (InPostIntegrationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Paczkomat details fetch failed', [
                'paczkomat_code' => $paczkomatCode,
                'error' => $e->getMessage(),
            ]);
            
            throw new InPostIntegrationException(
                'Failed to get Paczkomat details: ' . $e->getMessage(),
                null,
                [],
                0,
                $e
            );
        }
    }

    /**
     * Validate if Paczkomat supports specific parcel size
     */
    public function validatePaczkomatForParcel(string $paczkomatCode, string $parcelSize, float $weight): array
    {
        $details = $this->getPaczkomatDetails($paczkomatCode);
        
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'paczkomat_details' => $details,
        ];

        // Check if Paczkomat is active
        if (!$details['is_active']) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Paczkomat is not currently active';
        }

        // Check parcel size support
        if (!in_array($parcelSize, $details['available_sizes'], true)) {
            $validation['valid'] = false;
            $validation['errors'][] = "Paczkomat does not support {$parcelSize} parcels";
        }

        // Check weight limits
        $sizeConstraints = InPostConfiguration::getParcelConstraints($parcelSize);
        if ($sizeConstraints && $weight > $sizeConstraints['max_weight']) {
            $validation['valid'] = false;
            $validation['errors'][] = "Parcel weight ({$weight}kg) exceeds maximum for {$parcelSize} size ({$sizeConstraints['max_weight']}kg)";
        }

        // Add warnings for potentially problematic situations
        if ($details['type'] !== 'paczkomat') {
            $validation['warnings'][] = 'This is not a standard Paczkomat location';
        }

        if (!empty($details['partner'])) {
            $validation['warnings'][] = "This is a partner location: {$details['partner']}";
        }

        return $validation;
    }

    /**
     * Get Paczkomat pickup instructions
     */
    public function getPaczkomatPickupInstructions(string $paczkomatCode): array
    {
        $details = $this->getPaczkomatDetails($paczkomatCode);
        
        return [
            'paczkomat_code' => $paczkomatCode,
            'name' => $details['name'],
            'address' => $details['address'],
            'opening_hours' => $details['opening_hours'],
            'instructions' => [
                'pl' => [
                    'Znajdź Paczkomat o kodzie ' . $paczkomatCode,
                    'Wprowadź kod odbioru na ekranie dotykowym',
                    'Alternatywnie, użyj aplikacji InPost Mobile',
                    'Odbierz przesyłkę z otwartej skrytki',
                    'Zamknij skrytkę po odbiorze',
                ],
                'en' => [
                    'Find Paczkomat with code ' . $paczkomatCode,
                    'Enter pickup code on the touchscreen',
                    'Alternatively, use InPost Mobile app',
                    'Collect your parcel from the opened compartment',
                    'Close the compartment after pickup',
                ],
            ],
            'payment_available' => $details['payment_available'],
            'mobile_app_qr' => $this->generateMobileAppQR($paczkomatCode),
        ];
    }

    /**
     * Calculate distance between two points using Haversine formula
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Generate QR code URL for InPost Mobile app
     */
    private function generateMobileAppQR(string $paczkomatCode): string
    {
        // This would generate a QR code that opens InPost Mobile app
        // with the specific Paczkomat pre-selected
        return "https://inpost.pl/app?paczkomat={$paczkomatCode}";
    }

    /**
     * Batch validate multiple addresses
     */
    public function validateMultipleAddresses(array $addresses): array
    {
        $results = [];
        
        foreach ($addresses as $index => $addressData) {
            try {
                $result = $this->validateAddress(
                    $addressData['address'] ?? '',
                    $addressData['postal_code'] ?? '',
                    $addressData['city'] ?? ''
                );
                $results[$index] = $result;
            } catch (\Exception $e) {
                $results[$index] = [
                    'valid' => false,
                    'error' => $e->getMessage(),
                    'suggestions' => [],
                ];
            }
        }
        
        return $results;
    }

    /**
     * Get coverage area information for InPost services
     */
    public function getCoverageInfo(string $postalCode): array
    {
        if (!InPostConfiguration::validatePolishPostalCode($postalCode)) {
            return [
                'covered' => false,
                'error' => 'Invalid postal code format',
            ];
        }

        try {
            // Check if there are any Paczkomaty in the area
            $response = $this->httpClient->request('GET', $this->geowidgetUrl . '/v1/points', [
                'query' => [
                    'post_code' => $postalCode,
                    'type' => 'paczkomat',
                    'status' => 'operating',
                    'max_results' => 1,
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();
            $hasPaczkomaty = !empty($data['items']);

            // Check courier service availability (simplified - in reality would use different endpoint)
            $courierAvailable = true; // InPost courier serves most of Poland

            return [
                'covered' => $hasPaczkomaty || $courierAvailable,
                'postal_code' => $postalCode,
                'services' => [
                    'paczkomaty' => $hasPaczkomaty,
                    'courier' => $courierAvailable,
                    'poczta' => true, // InPost Poczta serves all of Poland
                ],
                'paczkomat_count' => count($data['items'] ?? []),
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Coverage check failed', [
                'postal_code' => $postalCode,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'covered' => true, // Default to covered if check fails
                'error' => 'Coverage check unavailable',
                'services' => [
                    'paczkomaty' => true,
                    'courier' => true,
                    'poczta' => true,
                ],
            ];
        }
    }
}