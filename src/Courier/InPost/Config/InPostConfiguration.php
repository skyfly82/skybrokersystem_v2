<?php

declare(strict_types=1);

namespace App\Courier\InPost\Config;

class InPostConfiguration
{
    // API Endpoints
    public const SANDBOX_API_BASE_URL = 'https://api-shipx-pl.easypack24.net/v1';
    public const PRODUCTION_API_BASE_URL = 'https://api-shipx-pl.easypack24.net/v1';
    public const GEOWIDGET_SANDBOX_URL = 'https://geowidget.easypack24.net';
    public const GEOWIDGET_PRODUCTION_URL = 'https://geowidget.easypack24.net';
    
    // Widget URLs for frontend integration
    public const MAP_WIDGET_SANDBOX_URL = 'https://sandbox-easy-geowidget.easypack24.net';
    public const MAP_WIDGET_PRODUCTION_URL = 'https://geowidget.easypack24.net';
    
    // Rate Limits
    public const API_RATE_LIMIT_PER_MINUTE = 60;
    public const API_RATE_LIMIT_PER_HOUR = 1000;
    public const API_TIMEOUT_SECONDS = 30;
    
    // Parcel Constraints
    public const MAX_WEIGHT_KG = 25.0;
    public const MIN_WEIGHT_KG = 0.001;
    
    public const PARCEL_DIMENSIONS = [
        'small' => ['width' => 8, 'height' => 38, 'length' => 64, 'max_weight' => 1.0],
        'medium' => ['width' => 19, 'height' => 38, 'length' => 64, 'max_weight' => 5.0],
        'large' => ['width' => 39, 'height' => 38, 'length' => 64, 'max_weight' => 15.0],
        'xlarge' => ['width' => 41, 'height' => 38, 'length' => 64, 'max_weight' => 25.0],
    ];
    
    // Service Types
    public const SERVICE_TYPES = [
        'paczkomaty' => 'InPost Paczkomaty',
        'courier' => 'InPost Kurier',
        'poczta' => 'InPost Poczta Polska',
    ];
    
    // Supported Countries
    public const SUPPORTED_COUNTRIES = ['PL', 'Poland'];
    
    // Currency
    public const DEFAULT_CURRENCY = 'PLN';
    
    // Delivery Time Estimates (in business days)
    public const DELIVERY_TIME_ESTIMATES = [
        'paczkomaty' => ['min' => 1, 'max' => 3],
        'courier' => ['min' => 1, 'max' => 2],
        'poczta' => ['min' => 2, 'max' => 5],
    ];
    
    // Tracking Status Mapping
    public const STATUS_MAPPING = [
        'created' => 'Utworzona',
        'offers_prepared' => 'Przygotowane oferty',
        'offer_selected' => 'Wybrana oferta',
        'confirmed' => 'Potwierdzona',
        'dispatched_by_sender' => 'Wysłana przez nadawcę',
        'collected_from_sender' => 'Odebrana od nadawcy',
        'taken_by_courier' => 'Odebrana przez kuriera',
        'adopted_at_source_branch' => 'Przyjęta w oddziale nadawczym',
        'sent_from_source_branch' => 'Wysłana z oddziału nadawczego',
        'adopted_at_sorting_center' => 'Przyjęta w centrum sortowania',
        'sent_from_sorting_center' => 'Wysłana z centrum sortowania',
        'adopted_at_target_branch' => 'Przyjęta w oddziale docelowym',
        'sent_from_target_branch' => 'Wysłana z oddziału docelowego',
        'out_for_delivery' => 'W dostawie',
        'ready_to_pickup' => 'Gotowa do odbioru',
        'pickup_reminder_sent' => 'Wysłane przypomnienie o odbiorze',
        'delivered' => 'Dostarczona',
        'returned_to_sender' => 'Zwrócona do nadawcy',
        'avizo' => 'Awizo',
        'canceled' => 'Anulowana',
        'error' => 'Błąd',
    ];
    
    // Webhook Event Types
    public const WEBHOOK_EVENTS = [
        'status_changed' => 'Status shipment changed',
        'parcel_delivered' => 'Parcel was delivered',
        'parcel_picked_up' => 'Parcel was picked up from paczkomat',
        'parcel_ready_to_pickup' => 'Parcel is ready to pickup',
        'parcel_expired' => 'Parcel expired in paczkomat',
    ];
    
    // Polish Postal Code Regex
    public const POLISH_POSTAL_CODE_REGEX = '/^[0-9]{2}-[0-9]{3}$/';
    
    // Polish Phone Number Regex
    public const POLISH_PHONE_REGEX = '/^\+48[0-9]{9}$/';
    
    // Paczkomat Code Regex
    public const PACZKOMAT_CODE_REGEX = '/^[A-Z]{3}[0-9]{2,4}[A-Z]?$/';
    
    // COD Limits
    public const COD_MIN_AMOUNT = 1.00;
    public const COD_MAX_AMOUNT = 5000.00;
    
    // Insurance Limits
    public const INSURANCE_MAX_AMOUNT = 20000.00;
    
    // Label Formats
    public const SUPPORTED_LABEL_FORMATS = ['pdf', 'zpl'];
    
    // Default Configuration Values
    public const DEFAULTS = [
        'environment' => 'sandbox',
        'parcel_size' => 'medium',
        'delivery_method' => 'paczkomaty',
        'service_type' => 'standard',
        'currency' => 'PLN',
        'sender_country' => 'PL',
        'recipient_country' => 'PL',
        'label_format' => 'pdf',
        'tracking_update_interval' => 3600, // seconds
        'webhook_timeout' => 30, // seconds
        'api_retry_attempts' => 3,
        'api_retry_delay' => 1000, // milliseconds
    ];
    
    // Validation Rules
    public const VALIDATION_RULES = [
        'sender_name' => ['required', 'max_length' => 100],
        'sender_email' => ['required', 'email'],
        'sender_address' => ['required', 'max_length' => 255],
        'sender_postal_code' => ['required', 'regex' => self::POLISH_POSTAL_CODE_REGEX],
        'recipient_name' => ['required', 'max_length' => 100],
        'recipient_email' => ['required', 'email'],
        'recipient_address' => ['required', 'max_length' => 255],
        'recipient_postal_code' => ['required', 'regex' => self::POLISH_POSTAL_CODE_REGEX],
        'recipient_phone' => ['required', 'regex' => self::POLISH_PHONE_REGEX],
        'weight' => ['required', 'min' => self::MIN_WEIGHT_KG, 'max' => self::MAX_WEIGHT_KG],
        'parcel_size' => ['required', 'in' => ['small', 'medium', 'large', 'xlarge']],
        'delivery_method' => ['required', 'in' => ['paczkomaty', 'courier']],
        'target_paczkomat' => ['regex' => self::PACZKOMAT_CODE_REGEX, 'required_if' => 'delivery_method:paczkomaty'],
        'cod_amount' => ['min' => self::COD_MIN_AMOUNT, 'max' => self::COD_MAX_AMOUNT],
        'insurance_amount' => ['max' => self::INSURANCE_MAX_AMOUNT],
    ];
    
    // Error Messages
    public const ERROR_MESSAGES = [
        'invalid_postal_code' => 'Invalid Polish postal code format. Use XX-XXX format.',
        'invalid_phone_number' => 'Invalid Polish phone number. Use +48XXXXXXXXX format.',
        'invalid_paczkomat_code' => 'Invalid Paczkomat code format.',
        'parcel_too_heavy' => 'Parcel exceeds maximum weight limit.',
        'parcel_too_large' => 'Parcel dimensions exceed size limits.',
        'invalid_cod_amount' => 'Cash on delivery amount is invalid.',
        'paczkomat_required' => 'Paczkomat code is required for Paczkomat delivery.',
        'unsupported_country' => 'Country is not supported for InPost delivery.',
        'api_rate_limit' => 'API rate limit exceeded. Please try again later.',
        'authentication_failed' => 'InPost API authentication failed.',
        'shipment_not_found' => 'Shipment not found in InPost system.',
        'shipment_cannot_be_canceled' => 'Shipment cannot be canceled in current state.',
    ];
    
    public static function getApiUrl(string $environment = 'sandbox'): string
    {
        return $environment === 'production' 
            ? self::PRODUCTION_API_BASE_URL 
            : self::SANDBOX_API_BASE_URL;
    }
    
    public static function getGeowidgetUrl(string $environment = 'sandbox'): string
    {
        return $environment === 'production'
            ? self::GEOWIDGET_PRODUCTION_URL
            : self::GEOWIDGET_SANDBOX_URL;
    }
    
    public static function getMapWidgetUrl(string $environment = 'sandbox'): string
    {
        return $environment === 'production'
            ? self::MAP_WIDGET_PRODUCTION_URL
            : self::MAP_WIDGET_SANDBOX_URL;
    }
    
    public static function getParcelConstraints(string $size): ?array
    {
        return self::PARCEL_DIMENSIONS[$size] ?? null;
    }
    
    public static function isValidParcelSize(string $size): bool
    {
        return array_key_exists($size, self::PARCEL_DIMENSIONS);
    }
    
    public static function isValidServiceType(string $serviceType): bool
    {
        return array_key_exists($serviceType, self::SERVICE_TYPES);
    }
    
    public static function isSupportedCountry(string $country): bool
    {
        return in_array(strtoupper($country), self::SUPPORTED_COUNTRIES, true);
    }
    
    public static function getStatusDisplayName(string $status): string
    {
        return self::STATUS_MAPPING[$status] ?? $status;
    }
    
    public static function getDeliveryTimeEstimate(string $serviceType): array
    {
        return self::DELIVERY_TIME_ESTIMATES[$serviceType] ?? ['min' => 1, 'max' => 5];
    }
    
    public static function validatePolishPostalCode(string $postalCode): bool
    {
        return (bool) preg_match(self::POLISH_POSTAL_CODE_REGEX, $postalCode);
    }
    
    public static function validatePolishPhoneNumber(string $phoneNumber): bool
    {
        return (bool) preg_match(self::POLISH_PHONE_REGEX, $phoneNumber);
    }
    
    public static function validatePaczkomatCode(string $code): bool
    {
        return (bool) preg_match(self::PACZKOMAT_CODE_REGEX, $code);
    }
    
    public static function formatPolishPhoneNumber(string $phoneNumber): string
    {
        // Remove any spaces, dashes, or other formatting
        $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);
        
        // If it starts with +48, return as is
        if (str_starts_with($cleaned, '+48')) {
            return $cleaned;
        }
        
        // If it starts with 48, add the +
        if (str_starts_with($cleaned, '48')) {
            return '+' . $cleaned;
        }
        
        // If it starts with 0, remove it and add +48
        if (str_starts_with($cleaned, '0')) {
            return '+48' . substr($cleaned, 1);
        }
        
        // If it's 9 digits, add +48
        if (strlen($cleaned) === 9) {
            return '+48' . $cleaned;
        }
        
        return $cleaned; // Return as is if format is unclear
    }
    
    public static function getWebhookEventDescription(string $eventType): string
    {
        return self::WEBHOOK_EVENTS[$eventType] ?? $eventType;
    }
    
    public static function getErrorMessage(string $errorKey): string
    {
        return self::ERROR_MESSAGES[$errorKey] ?? 'Unknown error occurred';
    }
}