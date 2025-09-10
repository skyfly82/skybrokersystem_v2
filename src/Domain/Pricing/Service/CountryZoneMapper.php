<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Service;

use App\Domain\Pricing\Entity\PricingZone;
use Psr\Log\LoggerInterface;

/**
 * Service for mapping countries to geographical zones
 * 
 * Provides mapping of ISO country codes to pricing zones
 * with support for EU, Europe, and worldwide classifications.
 */
class CountryZoneMapper
{
    private const EU_WEST_COUNTRIES = [
        'DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'PT', 'IE', 'LU', 'FI', 'SE', 'DK',
        'GB', 'UK' // Brexit considered for logistics
    ];

    private const EU_EAST_COUNTRIES = [
        'CZ', 'SK', 'HU', 'SI', 'HR', 'BG', 'RO', 'EE', 'LV', 'LT'
    ];

    private const EUROPE_NON_EU_COUNTRIES = [
        'NO', 'CH', 'IS', 'LI', 'AD', 'MC', 'SM', 'VA', 'MT', 'CY',
        'RS', 'ME', 'BA', 'MK', 'AL', 'XK', 'MD', 'UA', 'BY', 'RU',
        'TR', 'GE', 'AM', 'AZ'
    ];

    private const SPECIAL_ZONES = [
        // Countries with specific handling
        'PL' => PricingZone::ZONE_DOMESTIC,
        'RU' => PricingZone::ZONE_WORLD, // Due to sanctions/logistics complexity
        'BY' => PricingZone::ZONE_WORLD, // Due to sanctions/logistics complexity
    ];

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get zone by country ISO code
     */
    public function getZoneByCountry(string $countryCode): ?string
    {
        $normalizedCountryCode = strtoupper(trim($countryCode));

        try {
            // Check special zones first
            if (isset(self::SPECIAL_ZONES[$normalizedCountryCode])) {
                $zone = self::SPECIAL_ZONES[$normalizedCountryCode];
                $this->logger->info('Special zone mapping applied', [
                    'country' => $normalizedCountryCode,
                    'zone' => $zone
                ]);
                return $zone;
            }

            // Check EU West
            if (in_array($normalizedCountryCode, self::EU_WEST_COUNTRIES)) {
                $this->logger->info('EU West zone mapping', [
                    'country' => $normalizedCountryCode,
                    'zone' => PricingZone::ZONE_EU_WEST
                ]);
                return PricingZone::ZONE_EU_WEST;
            }

            // Check EU East
            if (in_array($normalizedCountryCode, self::EU_EAST_COUNTRIES)) {
                $this->logger->info('EU East zone mapping', [
                    'country' => $normalizedCountryCode,
                    'zone' => PricingZone::ZONE_EU_EAST
                ]);
                return PricingZone::ZONE_EU_EAST;
            }

            // Check Europe (non-EU)
            if (in_array($normalizedCountryCode, self::EUROPE_NON_EU_COUNTRIES)) {
                $this->logger->info('Europe zone mapping', [
                    'country' => $normalizedCountryCode,
                    'zone' => PricingZone::ZONE_EUROPE
                ]);
                return PricingZone::ZONE_EUROPE;
            }

            // Default to World zone
            $this->logger->info('World zone mapping (default)', [
                'country' => $normalizedCountryCode,
                'zone' => PricingZone::ZONE_WORLD
            ]);
            return PricingZone::ZONE_WORLD;

        } catch (\Exception $e) {
            $this->logger->error('Error mapping country to zone', [
                'country' => $normalizedCountryCode,
                'error' => $e->getMessage()
            ]);
            return PricingZone::ZONE_WORLD;
        }
    }

    /**
     * Get country information including zone and region details
     */
    public function getCountryInfo(string $countryCode): array
    {
        $normalizedCountryCode = strtoupper(trim($countryCode));
        $zone = $this->getZoneByCountry($normalizedCountryCode);

        return [
            'country_code' => $normalizedCountryCode,
            'zone_code' => $zone,
            'is_eu' => $this->isEuCountry($normalizedCountryCode),
            'is_europe' => $this->isEuropeanCountry($normalizedCountryCode),
            'is_domestic' => $normalizedCountryCode === 'PL',
            'region' => $this->getRegion($normalizedCountryCode),
            'continent' => $this->getContinent($normalizedCountryCode),
            'country_name' => $this->getCountryName($normalizedCountryCode)
        ];
    }

    /**
     * Check if country is in European Union
     */
    public function isEuCountry(string $countryCode): bool
    {
        $normalizedCountryCode = strtoupper(trim($countryCode));
        
        return in_array($normalizedCountryCode, self::EU_WEST_COUNTRIES) ||
               in_array($normalizedCountryCode, self::EU_EAST_COUNTRIES);
    }

    /**
     * Check if country is in Europe
     */
    public function isEuropeanCountry(string $countryCode): bool
    {
        $normalizedCountryCode = strtoupper(trim($countryCode));
        
        return $normalizedCountryCode === 'PL' ||
               $this->isEuCountry($normalizedCountryCode) ||
               in_array($normalizedCountryCode, self::EUROPE_NON_EU_COUNTRIES);
    }

    /**
     * Get all countries for a specific zone
     */
    public function getCountriesForZone(string $zoneCode): array
    {
        switch ($zoneCode) {
            case PricingZone::ZONE_LOCAL:
            case PricingZone::ZONE_DOMESTIC:
                return ['PL'];

            case PricingZone::ZONE_EU_WEST:
                return self::EU_WEST_COUNTRIES;

            case PricingZone::ZONE_EU_EAST:
                return self::EU_EAST_COUNTRIES;

            case PricingZone::ZONE_EUROPE:
                return self::EUROPE_NON_EU_COUNTRIES;

            case PricingZone::ZONE_WORLD:
                // Return some example world countries
                return [
                    'US', 'CA', 'AU', 'JP', 'CN', 'IN', 'BR', 'AR', 'ZA', 'EG',
                    'MX', 'KR', 'TH', 'VN', 'ID', 'MY', 'SG', 'PH', 'NZ', 'IL'
                ];

            default:
                return [];
        }
    }

    /**
     * Get zone priority for routing (lower number = higher priority)
     */
    public function getZonePriority(string $zoneCode): int
    {
        return match ($zoneCode) {
            PricingZone::ZONE_LOCAL => 1,
            PricingZone::ZONE_DOMESTIC => 2,
            PricingZone::ZONE_EU_WEST => 3,
            PricingZone::ZONE_EU_EAST => 4,
            PricingZone::ZONE_EUROPE => 5,
            PricingZone::ZONE_WORLD => 6,
            default => 10
        };
    }

    /**
     * Get region for country
     */
    private function getRegion(string $countryCode): string
    {
        if ($countryCode === 'PL') {
            return 'Central Europe';
        }

        if (in_array($countryCode, self::EU_WEST_COUNTRIES)) {
            return 'Western Europe';
        }

        if (in_array($countryCode, self::EU_EAST_COUNTRIES)) {
            return 'Eastern Europe';
        }

        if (in_array($countryCode, self::EUROPE_NON_EU_COUNTRIES)) {
            return 'Europe (Non-EU)';
        }

        return 'International';
    }

    /**
     * Get continent for country
     */
    private function getContinent(string $countryCode): string
    {
        // European countries
        if ($this->isEuropeanCountry($countryCode)) {
            return 'Europe';
        }

        // Basic continent mapping for common countries
        $continentMap = [
            // North America
            'US' => 'North America', 'CA' => 'North America', 'MX' => 'North America',
            
            // Asia
            'CN' => 'Asia', 'JP' => 'Asia', 'IN' => 'Asia', 'KR' => 'Asia', 'TH' => 'Asia',
            'VN' => 'Asia', 'ID' => 'Asia', 'MY' => 'Asia', 'SG' => 'Asia', 'PH' => 'Asia',
            
            // Oceania
            'AU' => 'Oceania', 'NZ' => 'Oceania',
            
            // South America
            'BR' => 'South America', 'AR' => 'South America',
            
            // Africa
            'ZA' => 'Africa', 'EG' => 'Africa',
            
            // Middle East
            'IL' => 'Middle East', 'AE' => 'Middle East', 'SA' => 'Middle East',
        ];

        return $continentMap[$countryCode] ?? 'Unknown';
    }

    /**
     * Get country name
     */
    private function getCountryName(string $countryCode): string
    {
        $countryNames = [
            'PL' => 'Poland',
            'DE' => 'Germany',
            'FR' => 'France',
            'GB' => 'United Kingdom',
            'UK' => 'United Kingdom',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'AT' => 'Austria',
            'CZ' => 'Czech Republic',
            'SK' => 'Slovakia',
            'HU' => 'Hungary',
            'US' => 'United States',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'JP' => 'Japan',
            'CN' => 'China',
            'RU' => 'Russia',
            'UA' => 'Ukraine',
        ];

        return $countryNames[$countryCode] ?? $countryCode;
    }

    /**
     * Get shipping difficulty score (1-10, higher = more complex)
     */
    public function getShippingDifficulty(string $countryCode): int
    {
        $normalizedCountryCode = strtoupper(trim($countryCode));

        // Domestic shipping
        if ($normalizedCountryCode === 'PL') {
            return 1;
        }

        // EU West - easy shipping
        if (in_array($normalizedCountryCode, self::EU_WEST_COUNTRIES)) {
            return 2;
        }

        // EU East - moderate shipping
        if (in_array($normalizedCountryCode, self::EU_EAST_COUNTRIES)) {
            return 3;
        }

        // Europe Non-EU - more complex
        if (in_array($normalizedCountryCode, self::EUROPE_NON_EU_COUNTRIES)) {
            return 6;
        }

        // Special difficult countries
        $difficultCountries = ['RU', 'BY', 'CN', 'IN', 'BR'];
        if (in_array($normalizedCountryCode, $difficultCountries)) {
            return 9;
        }

        // World - complex shipping
        return 7;
    }
}