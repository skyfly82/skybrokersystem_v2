<?php

declare(strict_types=1);

namespace App\Courier\DHL\Config;

class DHLConfiguration
{
    public const SUPPORTED_COUNTRIES = [
        'PL' => 'Poland',
        'DE' => 'Germany',
        'FR' => 'France',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'CZ' => 'Czech Republic',
        'SK' => 'Slovakia',
        'AT' => 'Austria',
        'IT' => 'Italy',
        'ES' => 'Spain',
        'NL' => 'Netherlands',
        'BE' => 'Belgium',
    ];

    public const PRODUCT_CODES = [
        'N' => 'Domestic Express',
        'U' => 'Express Worldwide',
        'P' => 'Express 10:30',
        'Q' => 'Express 12:00',
        'T' => 'Express 9:00',
        'W' => 'Economy Select',
        'X' => 'Express Envelope',
    ];

    public const DOMESTIC_SERVICES = ['N'];
    public const INTERNATIONAL_SERVICES = ['U', 'P', 'Q', 'T', 'W', 'X'];

    public const MAX_PACKAGE_WEIGHT = 70; // kg
    public const MAX_PACKAGE_DIMENSIONS = [
        'length' => 120, // cm
        'width' => 80,   // cm
        'height' => 80,  // cm
    ];

    public const VOLUMETRIC_WEIGHT_DIVISOR = 5000; // cubic cm per kg

    public const DEFAULT_CURRENCY = 'PLN';
    public const SUPPORTED_CURRENCIES = ['PLN', 'EUR', 'USD', 'GBP'];

    public const API_RATE_LIMITS = [
        'tracking' => 250, // requests per minute
        'shipping' => 100, // requests per minute
    ];

    /**
     * Get available services for country pair
     */
    public static function getAvailableServices(string $originCountry, string $destinationCountry): array
    {
        if ($originCountry === $destinationCountry) {
            return array_intersect_key(
                self::PRODUCT_CODES,
                array_flip(self::DOMESTIC_SERVICES)
            );
        }

        return array_intersect_key(
            self::PRODUCT_CODES,
            array_flip(self::INTERNATIONAL_SERVICES)
        );
    }

    /**
     * Check if country is supported
     */
    public static function isCountrySupported(string $countryCode): bool
    {
        return array_key_exists($countryCode, self::SUPPORTED_COUNTRIES);
    }

    /**
     * Get delivery estimates in business days
     */
    public static function getDeliveryEstimate(string $originCountry, string $destinationCountry, string $serviceCode): ?int
    {
        // Simplified estimates - in real implementation this would come from DHL API
        if ($originCountry === $destinationCountry) {
            return match ($serviceCode) {
                'N' => 1, // Next business day
                default => 1,
            };
        }

        return match ($serviceCode) {
            'U' => 3, // Express Worldwide
            'P' => 1, // Express 10:30
            'Q' => 1, // Express 12:00
            'T' => 1, // Express 9:00
            'W' => 5, // Economy Select
            'X' => 2, // Express Envelope
            default => 3,
        };
    }

    /**
     * Get service requirements
     */
    public static function getServiceRequirements(string $serviceCode): array
    {
        return match ($serviceCode) {
            'P', 'Q', 'T' => [
                'requires_signature' => true,
                'business_day_delivery' => true,
                'time_guarantee' => true,
            ],
            'U' => [
                'requires_signature' => true,
                'business_day_delivery' => true,
                'time_guarantee' => false,
            ],
            'W' => [
                'requires_signature' => false,
                'business_day_delivery' => false,
                'time_guarantee' => false,
            ],
            'X' => [
                'requires_signature' => true,
                'business_day_delivery' => true,
                'time_guarantee' => true,
                'document_only' => true,
            ],
            default => [
                'requires_signature' => true,
                'business_day_delivery' => true,
                'time_guarantee' => false,
            ],
        };
    }

    /**
     * Validate package dimensions
     */
    public static function validateDimensions(float $length, float $width, float $height): bool
    {
        return $length <= self::MAX_PACKAGE_DIMENSIONS['length'] &&
               $width <= self::MAX_PACKAGE_DIMENSIONS['width'] &&
               $height <= self::MAX_PACKAGE_DIMENSIONS['height'];
    }

    /**
     * Calculate volumetric weight
     */
    public static function calculateVolumetricWeight(float $length, float $width, float $height): float
    {
        return ($length * $width * $height) / self::VOLUMETRIC_WEIGHT_DIVISOR;
    }

    /**
     * Get billable weight (higher of actual or volumetric)
     */
    public static function getBillableWeight(float $actualWeight, float $length, float $width, float $height): float
    {
        $volumetricWeight = self::calculateVolumetricWeight($length, $width, $height);
        return max($actualWeight, $volumetricWeight);
    }
}