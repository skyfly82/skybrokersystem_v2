<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Service;

use App\Domain\Pricing\Entity\PricingZone;
use App\Domain\Pricing\Repository\PricingZoneRepository;
use App\Domain\Pricing\Repository\CarrierRepository;
use App\Domain\Pricing\Service\PostalCodeMapper;
use App\Domain\Pricing\Service\CountryZoneMapper;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for geographical zone mapping and location-based operations
 * 
 * Provides comprehensive geographical zone determination, carrier availability,
 * distance calculation, and delivery time estimation for the courier system.
 */
class GeographicalZoneService
{
    private const DEFAULT_ZONE = PricingZone::ZONE_DOMESTIC;
    private const CACHE_TTL = 3600; // 1 hour
    
    public function __construct(
        private readonly PricingZoneRepository $pricingZoneRepository,
        private readonly CarrierRepository $carrierRepository,
        private readonly PostalCodeMapper $postalCodeMapper,
        private readonly CountryZoneMapper $countryZoneMapper,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get geographical zone by postal code
     */
    public function getZoneByPostalCode(string $postalCode, ?string $countryCode = 'PL'): ?PricingZone
    {
        try {
            $normalizedPostalCode = $this->normalizePostalCode($postalCode);
            
            // First try postal code mapping
            $zoneCode = $this->postalCodeMapper->getZoneByPostalCode($normalizedPostalCode, $countryCode);
            
            if ($zoneCode) {
                $zone = $this->pricingZoneRepository->findOneBy(['code' => $zoneCode, 'isActive' => true]);
                if ($zone) {
                    $this->logger->info('Zone found by postal code', [
                        'postal_code' => $postalCode,
                        'country' => $countryCode,
                        'zone' => $zoneCode
                    ]);
                    return $zone;
                }
            }

            // Fallback to country-based mapping
            if ($countryCode && $countryCode !== 'PL') {
                return $this->getZoneByCountry($countryCode);
            }

            // Default zone for Poland
            return $this->getDefaultDomesticZone();

        } catch (\Exception $e) {
            $this->logger->error('Error determining zone by postal code', [
                'postal_code' => $postalCode,
                'country' => $countryCode,
                'error' => $e->getMessage()
            ]);
            return $this->getDefaultDomesticZone();
        }
    }

    /**
     * Get geographical zone by country code
     */
    public function getZoneByCountry(string $countryCode): ?PricingZone
    {
        try {
            $normalizedCountryCode = strtoupper($countryCode);
            
            // Use country zone mapper to determine zone
            $zoneCode = $this->countryZoneMapper->getZoneByCountry($normalizedCountryCode);
            
            if ($zoneCode) {
                $zone = $this->pricingZoneRepository->findOneBy(['code' => $zoneCode, 'isActive' => true]);
                if ($zone) {
                    $this->logger->info('Zone found by country', [
                        'country' => $countryCode,
                        'zone' => $zoneCode
                    ]);
                    return $zone;
                }
            }

            // Default to world zone for unknown countries
            return $this->pricingZoneRepository->findOneBy(['code' => PricingZone::ZONE_WORLD, 'isActive' => true]);

        } catch (\Exception $e) {
            $this->logger->error('Error determining zone by country', [
                'country' => $countryCode,
                'error' => $e->getMessage()
            ]);
            return $this->pricingZoneRepository->findOneBy(['code' => PricingZone::ZONE_WORLD, 'isActive' => true]);
        }
    }

    /**
     * Get available carriers for a specific zone
     */
    public function getAvailableCarriers(string $zoneCode): array
    {
        try {
            $zone = $this->pricingZoneRepository->findOneBy(['code' => $zoneCode, 'isActive' => true]);
            if (!$zone) {
                return [];
            }

            // Get carriers that have pricing tables for this zone
            $carriers = $this->carrierRepository->findCarriersForZone($zone);
            
            $this->logger->info('Available carriers retrieved', [
                'zone' => $zoneCode,
                'carriers_count' => count($carriers)
            ]);

            return $carriers;

        } catch (\Exception $e) {
            $this->logger->error('Error getting available carriers', [
                'zone' => $zoneCode,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Calculate distance between two points (using Haversine formula)
     */
    public function calculateDistance(
        float $fromLat, 
        float $fromLng, 
        float $toLat, 
        float $toLng
    ): float {
        $earthRadius = 6371; // Earth radius in kilometers

        $dLat = deg2rad($toLat - $fromLat);
        $dLng = deg2rad($toLng - $fromLng);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        $this->logger->debug('Distance calculated', [
            'from' => ['lat' => $fromLat, 'lng' => $fromLng],
            'to' => ['lat' => $toLat, 'lng' => $toLng],
            'distance_km' => round($distance, 2)
        ]);

        return $distance;
    }

    /**
     * Get estimated delivery time for zone and carrier
     */
    public function getDeliveryTime(string $zoneCode, string $carrierCode): ?array
    {
        try {
            // Static delivery time mapping (can be moved to database)
            $deliveryTimes = [
                PricingZone::ZONE_LOCAL => [
                    'inpost' => ['min' => 1, 'max' => 2, 'unit' => 'days'],
                    'dhl' => ['min' => 1, 'max' => 2, 'unit' => 'days'],
                    'default' => ['min' => 1, 'max' => 3, 'unit' => 'days']
                ],
                PricingZone::ZONE_DOMESTIC => [
                    'inpost' => ['min' => 1, 'max' => 3, 'unit' => 'days'],
                    'dhl' => ['min' => 1, 'max' => 3, 'unit' => 'days'],
                    'default' => ['min' => 2, 'max' => 5, 'unit' => 'days']
                ],
                PricingZone::ZONE_EU_WEST => [
                    'dhl' => ['min' => 2, 'max' => 5, 'unit' => 'days'],
                    'default' => ['min' => 3, 'max' => 7, 'unit' => 'days']
                ],
                PricingZone::ZONE_EU_EAST => [
                    'dhl' => ['min' => 2, 'max' => 4, 'unit' => 'days'],
                    'default' => ['min' => 3, 'max' => 6, 'unit' => 'days']
                ],
                PricingZone::ZONE_EUROPE => [
                    'dhl' => ['min' => 3, 'max' => 7, 'unit' => 'days'],
                    'default' => ['min' => 5, 'max' => 10, 'unit' => 'days']
                ],
                PricingZone::ZONE_WORLD => [
                    'dhl' => ['min' => 5, 'max' => 14, 'unit' => 'days'],
                    'default' => ['min' => 7, 'max' => 21, 'unit' => 'days']
                ]
            ];

            $zoneDeliveryTimes = $deliveryTimes[$zoneCode] ?? null;
            if (!$zoneDeliveryTimes) {
                return null;
            }

            $deliveryTime = $zoneDeliveryTimes[$carrierCode] ?? $zoneDeliveryTimes['default'];
            
            $this->logger->info('Delivery time estimated', [
                'zone' => $zoneCode,
                'carrier' => $carrierCode,
                'delivery_time' => $deliveryTime
            ]);

            return $deliveryTime;

        } catch (\Exception $e) {
            $this->logger->error('Error getting delivery time', [
                'zone' => $zoneCode,
                'carrier' => $carrierCode,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get zone by location coordinates
     */
    public function getZoneByCoordinates(float $lat, float $lng): ?PricingZone
    {
        // This would typically use a geographic service or database
        // For now, simplified logic based on Poland's approximate boundaries
        if ($this->isInPoland($lat, $lng)) {
            // Could further determine if it's a local zone (major cities)
            if ($this->isLocalZoneCoordinates($lat, $lng)) {
                return $this->pricingZoneRepository->findOneBy(['code' => PricingZone::ZONE_LOCAL, 'isActive' => true]);
            }
            return $this->pricingZoneRepository->findOneBy(['code' => PricingZone::ZONE_DOMESTIC, 'isActive' => true]);
        }

        // For international locations, default to world zone
        return $this->pricingZoneRepository->findOneBy(['code' => PricingZone::ZONE_WORLD, 'isActive' => true]);
    }

    /**
     * Get all active zones ordered by sort order
     */
    public function getAllActiveZones(): array
    {
        return $this->pricingZoneRepository->findBy(
            ['isActive' => true],
            ['sortOrder' => 'ASC', 'name' => 'ASC']
        );
    }

    /**
     * Check if zone supports a specific carrier
     */
    public function doesZoneSupportCarrier(string $zoneCode, string $carrierCode): bool
    {
        $availableCarriers = $this->getAvailableCarriers($zoneCode);
        
        foreach ($availableCarriers as $carrier) {
            if ($carrier->getCode() === $carrierCode) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize postal code format
     */
    private function normalizePostalCode(string $postalCode): string
    {
        // Remove spaces, dashes, and convert to uppercase
        return strtoupper(str_replace([' ', '-'], '', $postalCode));
    }

    /**
     * Get default domestic zone
     */
    private function getDefaultDomesticZone(): ?PricingZone
    {
        return $this->pricingZoneRepository->findOneBy(['code' => self::DEFAULT_ZONE, 'isActive' => true]);
    }

    /**
     * Check if coordinates are within Poland boundaries
     */
    private function isInPoland(float $lat, float $lng): bool
    {
        // Simplified Poland boundary check
        return ($lat >= 49.0 && $lat <= 54.9) && ($lng >= 14.1 && $lng <= 24.2);
    }

    /**
     * Check if coordinates are in local zone (major cities)
     */
    private function isLocalZoneCoordinates(float $lat, float $lng): bool
    {
        $localZones = [
            // Warsaw
            ['lat' => 52.2297, 'lng' => 21.0122, 'radius' => 50],
            // Krakow
            ['lat' => 50.0647, 'lng' => 19.9450, 'radius' => 30],
            // Gdansk
            ['lat' => 54.3520, 'lng' => 18.6466, 'radius' => 25],
            // Wroclaw
            ['lat' => 51.1079, 'lng' => 17.0385, 'radius' => 25],
            // Poznan
            ['lat' => 52.4064, 'lng' => 16.9252, 'radius' => 25],
        ];

        foreach ($localZones as $zone) {
            $distance = $this->calculateDistance($lat, $lng, $zone['lat'], $zone['lng']);
            if ($distance <= $zone['radius']) {
                return true;
            }
        }

        return false;
    }
}