<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Service;

use App\Domain\Pricing\Entity\PricingZone;
use Psr\Log\LoggerInterface;

/**
 * Service for mapping postal codes to geographical zones
 * 
 * Handles Polish postal code ranges and special city mappings
 * for accurate zone determination in the courier system.
 */
class PostalCodeMapper
{
    private const LOCAL_POSTAL_CODES = [
        // Warsaw area
        '/^0[0-9]-\d{3}$/' => PricingZone::ZONE_LOCAL,
        '/^[01][0-9]-\d{3}$/' => PricingZone::ZONE_LOCAL,
        
        // Krakow area
        '/^3[0-4]-\d{3}$/' => PricingZone::ZONE_LOCAL,
        
        // Gdansk area  
        '/^8[0-2]-\d{3}$/' => PricingZone::ZONE_LOCAL,
        
        // Wroclaw area
        '/^5[0-4]-\d{3}$/' => PricingZone::ZONE_LOCAL,
        
        // Poznan area
        '/^6[0-2]-\d{3}$/' => PricingZone::ZONE_LOCAL,
    ];

    private const CITY_POSTAL_CODES = [
        // Major cities postal code mappings
        'WARSZAWA' => [
            'patterns' => ['/^0[0-9]\d{3}$/'],
            'zone' => PricingZone::ZONE_LOCAL
        ],
        'KRAKOW' => [
            'patterns' => ['/^3[0-4]\d{3}$/'],
            'zone' => PricingZone::ZONE_LOCAL
        ],
        'GDANSK' => [
            'patterns' => ['/^8[0-2]\d{3}$/'],
            'zone' => PricingZone::ZONE_LOCAL
        ],
        'WROCLAW' => [
            'patterns' => ['/^5[0-4]\d{3}$/'],
            'zone' => PricingZone::ZONE_LOCAL
        ],
        'POZNAN' => [
            'patterns' => ['/^6[0-2]\d{3}$/'],
            'zone' => PricingZone::ZONE_LOCAL
        ],
        'LODZ' => [
            'patterns' => ['/^9[0-5]\d{3}$/'],
            'zone' => PricingZone::ZONE_LOCAL
        ],
        'SZCZECIN' => [
            'patterns' => ['/^7[0-1]\d{3}$/'],
            'zone' => PricingZone::ZONE_LOCAL
        ],
        'LUBLIN' => [
            'patterns' => ['/^2[0-2]\d{3}$/'],
            'zone' => PricingZone::ZONE_LOCAL
        ]
    ];

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get zone by postal code and country
     */
    public function getZoneByPostalCode(string $postalCode, ?string $countryCode = 'PL'): ?string
    {
        $normalizedPostalCode = $this->normalizePostalCode($postalCode);
        
        // Handle Polish postal codes
        if ($countryCode === 'PL') {
            return $this->getPolishZoneByPostalCode($normalizedPostalCode);
        }

        // For international postal codes, return null to fall back to country-based mapping
        return null;
    }

    /**
     * Get zone for Polish postal codes
     */
    private function getPolishZoneByPostalCode(string $postalCode): ?string
    {
        try {
            // Format: NNNNN (5 digits, no dash)
            if (!preg_match('/^\d{5}$/', $postalCode)) {
                $this->logger->warning('Invalid Polish postal code format', ['postal_code' => $postalCode]);
                return PricingZone::ZONE_DOMESTIC;
            }

            // Check local zones first (major cities)
            $localZone = $this->checkLocalZones($postalCode);
            if ($localZone) {
                return $localZone;
            }

            // All other Polish postal codes are domestic
            return PricingZone::ZONE_DOMESTIC;

        } catch (\Exception $e) {
            $this->logger->error('Error processing Polish postal code', [
                'postal_code' => $postalCode,
                'error' => $e->getMessage()
            ]);
            return PricingZone::ZONE_DOMESTIC;
        }
    }

    /**
     * Check if postal code belongs to local zones
     */
    private function checkLocalZones(string $postalCode): ?string
    {
        $localZoneRanges = [
            // Warsaw metropolitan area (00-xxx to 05-xxx)
            ['start' => '00000', 'end' => '05999', 'zone' => PricingZone::ZONE_LOCAL],
            
            // Krakow metropolitan area (30-xxx to 34-xxx)
            ['start' => '30000', 'end' => '34999', 'zone' => PricingZone::ZONE_LOCAL],
            
            // Gdansk metropolitan area (80-xxx to 84-xxx)
            ['start' => '80000', 'end' => '84999', 'zone' => PricingZone::ZONE_LOCAL],
            
            // Wroclaw metropolitan area (50-xxx to 54-xxx)
            ['start' => '50000', 'end' => '54999', 'zone' => PricingZone::ZONE_LOCAL],
            
            // Poznan metropolitan area (60-xxx to 62-xxx)
            ['start' => '60000', 'end' => '62999', 'zone' => PricingZone::ZONE_LOCAL],
            
            // Lodz metropolitan area (90-xxx to 95-xxx)
            ['start' => '90000', 'end' => '95999', 'zone' => PricingZone::ZONE_LOCAL],
        ];

        foreach ($localZoneRanges as $range) {
            if ($postalCode >= $range['start'] && $postalCode <= $range['end']) {
                $this->logger->info('Local zone detected', [
                    'postal_code' => $postalCode,
                    'zone' => $range['zone']
                ]);
                return $range['zone'];
            }
        }

        return null;
    }

    /**
     * Get detailed postal code information
     */
    public function getPostalCodeInfo(string $postalCode, ?string $countryCode = 'PL'): array
    {
        $normalizedPostalCode = $this->normalizePostalCode($postalCode);
        $zone = $this->getZoneByPostalCode($normalizedPostalCode, $countryCode);
        
        $info = [
            'original_code' => $postalCode,
            'normalized_code' => $normalizedPostalCode,
            'country_code' => $countryCode,
            'zone_code' => $zone,
            'is_local' => $zone === PricingZone::ZONE_LOCAL,
            'is_domestic' => $zone === PricingZone::ZONE_DOMESTIC,
            'city_info' => null
        ];

        // Add city information for Polish postal codes
        if ($countryCode === 'PL') {
            $info['city_info'] = $this->getCityInfoByPostalCode($normalizedPostalCode);
        }

        return $info;
    }

    /**
     * Get city information by postal code
     */
    private function getCityInfoByPostalCode(string $postalCode): ?array
    {
        $cityMappings = [
            // Warsaw
            ['start' => '00000', 'end' => '05999', 'city' => 'Warszawa', 'voivodeship' => 'mazowieckie'],
            
            // Krakow
            ['start' => '30000', 'end' => '34999', 'city' => 'Kraków', 'voivodeship' => 'małopolskie'],
            
            // Gdansk
            ['start' => '80000', 'end' => '84999', 'city' => 'Gdańsk', 'voivodeship' => 'pomorskie'],
            
            // Wroclaw
            ['start' => '50000', 'end' => '54999', 'city' => 'Wrocław', 'voivodeship' => 'dolnośląskie'],
            
            // Poznan
            ['start' => '60000', 'end' => '62999', 'city' => 'Poznań', 'voivodeship' => 'wielkopolskie'],
            
            // Lodz
            ['start' => '90000', 'end' => '95999', 'city' => 'Łódź', 'voivodeship' => 'łódzkie'],
        ];

        foreach ($cityMappings as $mapping) {
            if ($postalCode >= $mapping['start'] && $postalCode <= $mapping['end']) {
                return [
                    'city' => $mapping['city'],
                    'voivodeship' => $mapping['voivodeship'],
                    'is_major_city' => true
                ];
            }
        }

        return [
            'city' => 'Inne',
            'voivodeship' => $this->getVoivodeshipByPostalCode($postalCode),
            'is_major_city' => false
        ];
    }

    /**
     * Get voivodeship by postal code
     */
    private function getVoivodeshipByPostalCode(string $postalCode): string
    {
        $voivodeshipMappings = [
            ['start' => '00000', 'end' => '09999', 'voivodeship' => 'mazowieckie'],
            ['start' => '10000', 'end' => '19999', 'voivodeship' => 'mazowieckie'],
            ['start' => '20000', 'end' => '24999', 'voivodeship' => 'lubelskie'],
            ['start' => '25000', 'end' => '29999', 'voivodeship' => 'świętokrzyskie'],
            ['start' => '30000', 'end' => '39999', 'voivodeship' => 'małopolskie'],
            ['start' => '40000', 'end' => '49999', 'voivodeship' => 'śląskie'],
            ['start' => '50000', 'end' => '59999', 'voivodeship' => 'dolnośląskie'],
            ['start' => '60000', 'end' => '69999', 'voivodeship' => 'wielkopolskie'],
            ['start' => '70000', 'end' => '79999', 'voivodeship' => 'zachodniopomorskie'],
            ['start' => '80000', 'end' => '89999', 'voivodeship' => 'pomorskie'],
            ['start' => '90000', 'end' => '99999', 'voivodeship' => 'łódzkie'],
        ];

        foreach ($voivodeshipMappings as $mapping) {
            if ($postalCode >= $mapping['start'] && $postalCode <= $mapping['end']) {
                return $mapping['voivodeship'];
            }
        }

        return 'nieznane';
    }

    /**
     * Validate postal code format
     */
    public function isValidPostalCode(string $postalCode, ?string $countryCode = 'PL'): bool
    {
        $normalizedPostalCode = $this->normalizePostalCode($postalCode);

        switch ($countryCode) {
            case 'PL':
                return preg_match('/^\d{5}$/', $normalizedPostalCode) === 1;
            case 'DE':
                return preg_match('/^\d{5}$/', $normalizedPostalCode) === 1;
            case 'UK':
            case 'GB':
                return preg_match('/^[A-Z]{1,2}[0-9R][0-9A-Z]?[0-9][ABD-HJLNP-UW-Z]{2}$/', $normalizedPostalCode) === 1;
            case 'FR':
                return preg_match('/^\d{5}$/', $normalizedPostalCode) === 1;
            case 'US':
                return preg_match('/^\d{5}(\d{4})?$/', $normalizedPostalCode) === 1;
            default:
                // Basic validation for other countries
                return strlen($normalizedPostalCode) >= 3 && strlen($normalizedPostalCode) <= 10;
        }
    }

    /**
     * Normalize postal code by removing spaces and dashes
     */
    private function normalizePostalCode(string $postalCode): string
    {
        return strtoupper(str_replace([' ', '-'], '', trim($postalCode)));
    }

    /**
     * Get all local zone postal code ranges
     */
    public function getLocalZoneRanges(): array
    {
        return [
            'warszawa' => ['00000', '05999'],
            'krakow' => ['30000', '34999'],
            'gdansk' => ['80000', '84999'],
            'wroclaw' => ['50000', '54999'],
            'poznan' => ['60000', '62999'],
            'lodz' => ['90000', '95999'],
        ];
    }
}