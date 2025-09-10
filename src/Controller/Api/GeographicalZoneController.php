<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Pricing\Service\GeographicalZoneService;
use App\Domain\Pricing\Service\PostalCodeMapper;
use App\Domain\Pricing\Service\CountryZoneMapper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * API Controller for geographical zone operations
 * 
 * Provides endpoints for testing and using geographical zone mapping services
 * including postal code detection, country mapping, and zone information.
 */
#[Route('/api/geographical-zones', name: 'api_geographical_zones_')]
class GeographicalZoneController extends AbstractController
{
    public function __construct(
        private readonly GeographicalZoneService $geographicalZoneService,
        private readonly PostalCodeMapper $postalCodeMapper,
        private readonly CountryZoneMapper $countryZoneMapper,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * Get zone by postal code
     */
    #[Route('/postal-code/{postalCode}', name: 'by_postal_code', methods: ['GET'])]
    public function getZoneByPostalCode(
        string $postalCode, 
        Request $request
    ): JsonResponse {
        $countryCode = $request->query->get('country', 'PL');
        
        // Validate input
        $violations = $this->validator->validate($postalCode, [
            new Assert\NotBlank(),
            new Assert\Length(min: 3, max: 10)
        ]);

        if (count($violations) > 0) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid postal code format',
                'violations' => array_map(fn($v) => $v->getMessage(), iterator_to_array($violations))
            ], 400);
        }

        try {
            $zone = $this->geographicalZoneService->getZoneByPostalCode($postalCode, $countryCode);
            $postalInfo = $this->postalCodeMapper->getPostalCodeInfo($postalCode, $countryCode);
            $isValid = $this->postalCodeMapper->isValidPostalCode($postalCode, $countryCode);

            return $this->json([
                'success' => true,
                'data' => [
                    'postal_code' => $postalCode,
                    'country_code' => $countryCode,
                    'zone' => $zone ? [
                        'code' => $zone->getCode(),
                        'name' => $zone->getName(),
                        'type' => $zone->getZoneType(),
                        'description' => $zone->getDescription()
                    ] : null,
                    'postal_info' => $postalInfo,
                    'is_valid' => $isValid,
                    'available_carriers' => $zone ? $this->getCarriersForZone($zone->getCode()) : []
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error determining zone',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get zone by country code
     */
    #[Route('/country/{countryCode}', name: 'by_country', methods: ['GET'])]
    public function getZoneByCountry(string $countryCode): JsonResponse
    {
        $countryCode = strtoupper($countryCode);
        
        // Validate input
        $violations = $this->validator->validate($countryCode, [
            new Assert\NotBlank(),
            new Assert\Length(exactly: 2),
            new Assert\Regex(pattern: '/^[A-Z]{2}$/')
        ]);

        if (count($violations) > 0) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid country code format (should be 2-letter ISO code)',
                'violations' => array_map(fn($v) => $v->getMessage(), iterator_to_array($violations))
            ], 400);
        }

        try {
            $zone = $this->geographicalZoneService->getZoneByCountry($countryCode);
            $countryInfo = $this->countryZoneMapper->getCountryInfo($countryCode);
            $shippingDifficulty = $this->countryZoneMapper->getShippingDifficulty($countryCode);

            return $this->json([
                'success' => true,
                'data' => [
                    'country_code' => $countryCode,
                    'zone' => $zone ? [
                        'code' => $zone->getCode(),
                        'name' => $zone->getName(),
                        'type' => $zone->getZoneType(),
                        'description' => $zone->getDescription(),
                        'priority' => $this->countryZoneMapper->getZonePriority($zone->getCode())
                    ] : null,
                    'country_info' => $countryInfo,
                    'shipping_difficulty' => $shippingDifficulty,
                    'available_carriers' => $zone ? $this->getCarriersForZone($zone->getCode()) : []
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error determining zone',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get zone by coordinates
     */
    #[Route('/coordinates', name: 'by_coordinates', methods: ['GET'])]
    public function getZoneByCoordinates(Request $request): JsonResponse
    {
        $lat = (float) $request->query->get('lat');
        $lng = (float) $request->query->get('lng');

        // Validate coordinates
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid coordinates (lat: -90 to 90, lng: -180 to 180)'
            ], 400);
        }

        try {
            $zone = $this->geographicalZoneService->getZoneByCoordinates($lat, $lng);

            return $this->json([
                'success' => true,
                'data' => [
                    'coordinates' => [
                        'latitude' => $lat,
                        'longitude' => $lng
                    ],
                    'zone' => $zone ? [
                        'code' => $zone->getCode(),
                        'name' => $zone->getName(),
                        'type' => $zone->getZoneType(),
                        'description' => $zone->getDescription()
                    ] : null,
                    'available_carriers' => $zone ? $this->getCarriersForZone($zone->getCode()) : []
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error determining zone',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate distance between two points
     */
    #[Route('/distance', name: 'calculate_distance', methods: ['GET'])]
    public function calculateDistance(Request $request): JsonResponse
    {
        $fromLat = (float) $request->query->get('from_lat');
        $fromLng = (float) $request->query->get('from_lng');
        $toLat = (float) $request->query->get('to_lat');
        $toLng = (float) $request->query->get('to_lng');

        // Validate coordinates
        $coordinates = [$fromLat, $fromLng, $toLat, $toLng];
        foreach ($coordinates as $coord) {
            if ($coord < -180 || $coord > 180) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid coordinates'
                ], 400);
            }
        }

        try {
            $distance = $this->geographicalZoneService->calculateDistance($fromLat, $fromLng, $toLat, $toLng);

            return $this->json([
                'success' => true,
                'data' => [
                    'from' => ['latitude' => $fromLat, 'longitude' => $fromLng],
                    'to' => ['latitude' => $toLat, 'longitude' => $toLng],
                    'distance_km' => round($distance, 2),
                    'distance_miles' => round($distance * 0.621371, 2)
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error calculating distance',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get delivery time estimate
     */
    #[Route('/delivery-time/{zoneCode}/{carrierCode}', name: 'delivery_time', methods: ['GET'])]
    public function getDeliveryTime(string $zoneCode, string $carrierCode): JsonResponse
    {
        try {
            $deliveryTime = $this->geographicalZoneService->getDeliveryTime($zoneCode, $carrierCode);

            if (!$deliveryTime) {
                return $this->json([
                    'success' => false,
                    'error' => 'No delivery time estimate available for this zone/carrier combination'
                ], 404);
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'zone_code' => $zoneCode,
                    'carrier_code' => $carrierCode,
                    'delivery_time' => $deliveryTime,
                    'estimate_text' => sprintf('%d-%d %s', 
                        $deliveryTime['min'], 
                        $deliveryTime['max'], 
                        $deliveryTime['unit']
                    )
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error getting delivery time',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all available zones
     */
    #[Route('/zones', name: 'list_zones', methods: ['GET'])]
    public function listZones(): JsonResponse
    {
        try {
            $zones = $this->geographicalZoneService->getAllActiveZones();

            $zonesData = array_map(function($zone) {
                $countries = $zone->getCountries();
                return [
                    'code' => $zone->getCode(),
                    'name' => $zone->getName(),
                    'description' => $zone->getDescription(),
                    'type' => $zone->getZoneType(),
                    'countries_count' => $countries ? count($countries) : null,
                    'countries' => $countries ? array_slice($countries, 0, 10) : null, // First 10 countries
                    'has_more_countries' => $countries && count($countries) > 10,
                    'sort_order' => $zone->getSortOrder(),
                    'priority' => $this->countryZoneMapper->getZonePriority($zone->getCode()),
                    'available_carriers_count' => count($this->getCarriersForZone($zone->getCode()))
                ];
            }, $zones);

            return $this->json([
                'success' => true,
                'data' => [
                    'zones' => $zonesData,
                    'total_count' => count($zones)
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error retrieving zones',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate postal code
     */
    #[Route('/validate/postal-code', name: 'validate_postal_code', methods: ['POST'])]
    public function validatePostalCode(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $postalCode = $data['postal_code'] ?? '';
        $countryCode = $data['country_code'] ?? 'PL';

        if (empty($postalCode)) {
            return $this->json([
                'success' => false,
                'error' => 'Postal code is required'
            ], 400);
        }

        try {
            $isValid = $this->postalCodeMapper->isValidPostalCode($postalCode, $countryCode);
            $postalInfo = $this->postalCodeMapper->getPostalCodeInfo($postalCode, $countryCode);

            return $this->json([
                'success' => true,
                'data' => [
                    'is_valid' => $isValid,
                    'postal_info' => $postalInfo,
                    'normalized_code' => $postalInfo['normalized_code']
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error validating postal code',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get carriers available for a zone
     */
    private function getCarriersForZone(string $zoneCode): array
    {
        try {
            $carriers = $this->geographicalZoneService->getAvailableCarriers($zoneCode);
            return array_map(fn($carrier) => [
                'code' => $carrier->getCode(),
                'name' => $carrier->getName()
            ], $carriers);
        } catch (\Exception $e) {
            return [];
        }
    }
}