<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Service;

use App\Domain\Courier\Meest\Exception\MeestValidationException;
use Psr\Log\LoggerInterface;

/**
 * Business validation service for MEEST shipments
 */
class MeestBusinessValidator
{
    private const SUPPORTED_COUNTRIES = ['DE', 'CZ', 'SK', 'HU', 'RO', 'LT', 'LV', 'EE', 'UA', 'BG', 'PL'];

    private const CURRENCY_MAPPING = [
        'DE' => 'EUR',
        'CZ' => 'CZK',
        'SK' => 'EUR',
        'HU' => 'HUF',
        'RO' => 'RON',
        'LT' => 'EUR',
        'LV' => 'EUR',
        'EE' => 'EUR',
        'UA' => 'UAH',
        'BG' => 'BGN',
        'PL' => 'PLN'
    ];

    private const MAX_PARCEL_WEIGHT = 30.0; // kg
    private const MAX_PARCEL_DIMENSIONS = 120.0; // cm
    private const MAX_PARCEL_VALUE = 10000.0; // per currency
    private const MIN_PARCEL_VALUE = 0.01;

    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Validate complete shipment data according to MEEST business rules
     */
    public function validateShipmentData(array $data): void
    {
        $this->logger->info('Validating MEEST shipment data', ['data_keys' => array_keys($data)]);

        $violations = [];

        // Core structure validation
        $violations = array_merge($violations, $this->validateStructure($data));

        // Address validations
        if (isset($data['sender'])) {
            $violations = array_merge($violations, $this->validateAddress($data['sender'], 'sender'));
        }

        if (isset($data['recipient'])) {
            $violations = array_merge($violations, $this->validateAddress($data['recipient'], 'recipient'));
        }

        // Parcel validations
        if (isset($data['parcel'])) {
            $violations = array_merge($violations, $this->validateParcel($data['parcel']));
        }

        // Cross-field validations
        if (isset($data['recipient']['country']) && isset($data['parcel']['value']['localCurrency'])) {
            $violations = array_merge($violations, $this->validateCurrencyConsistency(
                $data['recipient']['country'],
                $data['parcel']['value']['localCurrency']
            ));
        }

        // Business logic validations
        $violations = array_merge($violations, $this->validateBusinessRules($data));

        if (!empty($violations)) {
            $this->logger->warning('MEEST shipment validation failed', ['violations' => $violations]);
            throw MeestValidationException::withErrors($violations);
        }

        $this->logger->info('MEEST shipment validation passed');
    }

    /**
     * Validate basic structure requirements
     */
    private function validateStructure(array $data): array
    {
        $violations = [];
        $required = ['sender', 'recipient', 'parcel'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || !is_array($data[$field])) {
                $violations[] = "Field '{$field}' is required and must be an object";
            }
        }

        // Validate parcel value structure for MEEST
        if (isset($data['parcel'])) {
            if (!isset($data['parcel']['value']['localTotalValue'])) {
                $violations[] = 'parcel.value.localTotalValue is required for MEEST shipments';
            }

            if (!isset($data['parcel']['value']['localCurrency'])) {
                $violations[] = 'parcel.value.localCurrency is required for MEEST shipments';
            }

            // Validate items if present
            if (isset($data['parcel']['items']) && is_array($data['parcel']['items'])) {
                foreach ($data['parcel']['items'] as $index => $item) {
                    if (!isset($item['value']['value']) || !is_numeric($item['value']['value'])) {
                        $violations[] = "parcel.items[{$index}].value.value is required and must be numeric";
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * Validate address data
     */
    private function validateAddress(array $address, string $type): array
    {
        $violations = [];
        $required = ['first_name', 'last_name', 'phone', 'email', 'country', 'city', 'address', 'postal_code'];

        foreach ($required as $field) {
            if (!isset($address[$field]) || empty(trim($address[$field]))) {
                $violations[] = "{$type}.{$field} is required and cannot be empty";
            }
        }

        // Specific validations
        if (isset($address['email']) && !filter_var($address['email'], FILTER_VALIDATE_EMAIL)) {
            $violations[] = "{$type}.email must be a valid email address";
        }

        if (isset($address['country'])) {
            $country = strtoupper(trim($address['country']));
            if (strlen($country) !== 2) {
                $violations[] = "{$type}.country must be a 2-letter ISO country code";
            } elseif (!in_array($country, self::SUPPORTED_COUNTRIES)) {
                $violations[] = "{$type}.country '{$country}' is not supported by MEEST. Supported countries: " . implode(', ', self::SUPPORTED_COUNTRIES);
            }
        }

        if (isset($address['phone'])) {
            $phone = preg_replace('/[^\d+]/', '', $address['phone']);
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                $violations[] = "{$type}.phone must be between 10 and 15 digits";
            }
        }

        if (isset($address['postal_code'])) {
            $postalCode = trim($address['postal_code']);
            if (strlen($postalCode) < 3 || strlen($postalCode) > 10) {
                $violations[] = "{$type}.postal_code must be between 3 and 10 characters";
            }
        }

        return $violations;
    }

    /**
     * Validate parcel data
     */
    private function validateParcel(array $parcel): array
    {
        $violations = [];

        // Weight validation
        if (isset($parcel['weight'])) {
            $weight = (float) $parcel['weight'];
            if ($weight <= 0) {
                $violations[] = 'parcel.weight must be greater than 0';
            } elseif ($weight > self::MAX_PARCEL_WEIGHT) {
                $violations[] = "parcel.weight cannot exceed " . self::MAX_PARCEL_WEIGHT . " kg";
            }
        }

        // Dimensions validation
        $dimensions = ['length', 'width', 'height'];
        foreach ($dimensions as $dimension) {
            if (isset($parcel[$dimension])) {
                $value = (float) $parcel[$dimension];
                if ($value <= 0) {
                    $violations[] = "parcel.{$dimension} must be greater than 0";
                } elseif ($value > self::MAX_PARCEL_DIMENSIONS) {
                    $violations[] = "parcel.{$dimension} cannot exceed " . self::MAX_PARCEL_DIMENSIONS . " cm";
                }
            }
        }

        // Value validation
        if (isset($parcel['value']['localTotalValue'])) {
            $value = (float) $parcel['value']['localTotalValue'];
            if ($value < self::MIN_PARCEL_VALUE) {
                $violations[] = "parcel.value.localTotalValue must be at least " . self::MIN_PARCEL_VALUE;
            } elseif ($value > self::MAX_PARCEL_VALUE) {
                $violations[] = "parcel.value.localTotalValue cannot exceed " . self::MAX_PARCEL_VALUE;
            }
        }

        // Currency validation
        if (isset($parcel['value']['localCurrency'])) {
            $currency = strtoupper(trim($parcel['value']['localCurrency']));
            if (strlen($currency) !== 3) {
                $violations[] = 'parcel.value.localCurrency must be a 3-letter ISO currency code';
            }
        }

        // Contents validation
        if (isset($parcel['contents'])) {
            $contents = trim($parcel['contents']);
            if (empty($contents)) {
                $violations[] = 'parcel.contents cannot be empty';
            } elseif (strlen($contents) > 500) {
                $violations[] = 'parcel.contents cannot exceed 500 characters';
            }
        }

        return $violations;
    }

    /**
     * Validate currency consistency with destination country
     */
    private function validateCurrencyConsistency(string $country, string $currency): array
    {
        $violations = [];
        $country = strtoupper(trim($country));
        $currency = strtoupper(trim($currency));

        if (isset(self::CURRENCY_MAPPING[$country])) {
            $expectedCurrency = self::CURRENCY_MAPPING[$country];
            if ($currency !== $expectedCurrency) {
                $violations[] = "Currency '{$currency}' is not valid for destination country '{$country}'. Expected: {$expectedCurrency}";
            }
        }

        return $violations;
    }

    /**
     * Additional business rule validations
     */
    private function validateBusinessRules(array $data): array
    {
        $violations = [];

        // Validate shipment type restrictions
        if (isset($data['shipment_type'])) {
            $shipmentType = $data['shipment_type'];

            if ($shipmentType === 'return' && !isset($data['original_tracking_number'])) {
                $violations[] = 'original_tracking_number is required for return shipments';
            }
        }

        // Validate special delivery options
        if (isset($data['delivery_date'])) {
            try {
                $deliveryDate = new \DateTimeImmutable($data['delivery_date']);
                $now = new \DateTimeImmutable();

                if ($deliveryDate <= $now) {
                    $violations[] = 'delivery_date must be in the future';
                }

                $maxAdvanceDate = $now->modify('+30 days');
                if ($deliveryDate > $maxAdvanceDate) {
                    $violations[] = 'delivery_date cannot be more than 30 days in advance';
                }
            } catch (\Exception) {
                $violations[] = 'delivery_date must be a valid date in ISO 8601 format';
            }
        }

        // Validate reference length
        if (isset($data['reference']) && strlen($data['reference']) > 100) {
            $violations[] = 'reference cannot exceed 100 characters';
        }

        // Validate special instructions
        if (isset($data['special_instructions']) && strlen($data['special_instructions']) > 500) {
            $violations[] = 'special_instructions cannot exceed 500 characters';
        }

        // Cross-validation: high-value shipments require signature
        if (isset($data['parcel']['value']['localTotalValue'])) {
            $value = (float) $data['parcel']['value']['localTotalValue'];
            if ($value > 1000 && !($data['require_signature'] ?? false)) {
                $violations[] = 'Shipments with value over 1000 require signature confirmation';
            }
        }

        return $violations;
    }

    /**
     * Quick validation for critical fields only
     */
    public function validateCriticalFields(array $data): void
    {
        $violations = [];

        if (!isset($data['recipient']['country'])) {
            $violations[] = 'recipient.country is required';
        } elseif (!in_array(strtoupper($data['recipient']['country']), self::SUPPORTED_COUNTRIES)) {
            $violations[] = 'Destination country is not supported by MEEST';
        }

        if (!isset($data['parcel']['value']['localTotalValue']) ||
            (float) $data['parcel']['value']['localTotalValue'] <= 0) {
            $violations[] = 'parcel.value.localTotalValue is required and must be greater than 0';
        }

        if (!empty($violations)) {
            throw MeestValidationException::withErrors($violations);
        }
    }

    /**
     * Get supported countries
     */
    public function getSupportedCountries(): array
    {
        return self::SUPPORTED_COUNTRIES;
    }

    /**
     * Get currency for country
     */
    public function getCurrencyForCountry(string $country): ?string
    {
        return self::CURRENCY_MAPPING[strtoupper($country)] ?? null;
    }

    /**
     * Check if country is supported
     */
    public function isCountrySupported(string $country): bool
    {
        return in_array(strtoupper($country), self::SUPPORTED_COUNTRIES);
    }
}