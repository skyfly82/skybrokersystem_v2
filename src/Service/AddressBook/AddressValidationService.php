<?php

declare(strict_types=1);

namespace App\Service\AddressBook;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AddressValidationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Validate address data
     */
    public function validateAddress(array $addressData): array
    {
        $errors = [];

        // Validate required fields
        $requiredFields = ['name', 'address', 'city', 'postal_code', 'country', 'phone', 'email'];
        foreach ($requiredFields as $field) {
            if (empty($addressData[$field])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }

        // Validate email format
        if (!empty($addressData['email']) && !filter_var($addressData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email address is required';
        }

        // Validate postal code format
        if (!empty($addressData['postal_code'])) {
            $country = $addressData['country'] ?? 'Poland';
            if (!$this->validatePostalCode($addressData['postal_code'], $country)) {
                $errors['postal_code'] = 'Invalid postal code format for ' . $country;
            }
        }

        // Validate phone number format
        if (!empty($addressData['phone'])) {
            if (!$this->validatePhoneNumber($addressData['phone'])) {
                $errors['phone'] = 'Invalid phone number format';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate address for specific courier service
     */
    public function validateAddressForCourier(array $addressData, ?string $courierService = null): array
    {
        try {
            $baseValidation = $this->validateAddress($addressData);

            if (!$baseValidation['valid']) {
                return $baseValidation;
            }

            $result = [
                'valid' => true,
                'courier_specific' => [],
                'suggestions' => []
            ];

            // Courier-specific validations
            switch ($courierService) {
                case 'inpost':
                    $result['courier_specific']['inpost'] = $this->validateForInPost($addressData);
                    break;
                case 'dhl':
                    $result['courier_specific']['dhl'] = $this->validateForDHL($addressData);
                    break;
            }

            // Get address suggestions if available
            if (!empty($addressData['address']) && !empty($addressData['city'])) {
                $suggestions = $this->getAddressSuggestions(
                    $addressData['address'] . ', ' . $addressData['city'],
                    $addressData['country'] ?? 'PL'
                );
                $result['suggestions'] = array_slice($suggestions, 0, 5);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Address validation failed', [
                'error' => $e->getMessage(),
                'address_data' => $addressData
            ]);

            return [
                'valid' => false,
                'error' => 'Validation service temporarily unavailable'
            ];
        }
    }

    /**
     * Get address suggestions based on partial input
     */
    public function getAddressSuggestions(string $query, string $country = 'PL'): array
    {
        try {
            // Use a geocoding service or address validation API
            // For demo purposes, return some sample suggestions
            return $this->getSampleSuggestions($query, $country);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get address suggestions', [
                'query' => $query,
                'country' => $country,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Validate postal code format by country
     */
    private function validatePostalCode(string $postalCode, string $country): bool
    {
        $patterns = [
            'Poland' => '/^\d{2}-\d{3}$/',
            'Germany' => '/^\d{5}$/',
            'Czech Republic' => '/^\d{3} \d{2}$/',
            'Slovakia' => '/^\d{3} \d{2}$/',
            'Austria' => '/^\d{4}$/',
            'Hungary' => '/^\d{4}$/',
            'Lithuania' => '/^LT-\d{5}$/',
            'Latvia' => '/^LV-\d{4}$/',
            'Estonia' => '/^\d{5}$/'
        ];

        $pattern = $patterns[$country] ?? $patterns['Poland'];
        return preg_match($pattern, $postalCode) === 1;
    }

    /**
     * Validate phone number format
     */
    private function validatePhoneNumber(string $phone): bool
    {
        // Remove all non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        // Check if it starts with + and has appropriate length
        if (preg_match('/^\+\d{10,15}$/', $cleaned)) {
            return true;
        }

        // Check Polish mobile format
        if (preg_match('/^\d{9}$/', $cleaned)) {
            return true;
        }

        return false;
    }

    /**
     * Validate address for InPost courier
     */
    private function validateForInPost(array $addressData): array
    {
        $result = [
            'valid' => true,
            'warnings' => [],
            'restrictions' => []
        ];

        $country = $addressData['country'] ?? 'Poland';

        // InPost operates mainly in Poland and selected EU countries
        $supportedCountries = ['Poland', 'Germany', 'Czech Republic', 'Slovakia', 'Austria'];

        if (!in_array($country, $supportedCountries, true)) {
            $result['valid'] = false;
            $result['restrictions'][] = 'InPost does not deliver to ' . $country;
        }

        // Check for restricted areas (simplified)
        $city = strtolower($addressData['city'] ?? '');
        $restrictedAreas = ['hel', 'ustka']; // Some remote areas

        if (in_array($city, $restrictedAreas, true)) {
            $result['warnings'][] = 'Limited service availability in this area';
        }

        return $result;
    }

    /**
     * Validate address for DHL courier
     */
    private function validateForDHL(array $addressData): array
    {
        $result = [
            'valid' => true,
            'warnings' => [],
            'restrictions' => []
        ];

        $country = $addressData['country'] ?? 'Poland';

        // DHL has broader coverage but different service levels
        if (!in_array($country, ['Poland', 'Germany', 'Czech Republic', 'Slovakia', 'Austria', 'Hungary'], true)) {
            $result['warnings'][] = 'International shipping rates apply';
        }

        return $result;
    }

    /**
     * Get sample address suggestions
     */
    private function getSampleSuggestions(string $query, string $country): array
    {
        // In real implementation, this would call an address validation API
        $suggestions = [];

        if (stripos($query, 'Warsaw') !== false || stripos($query, 'Warszawa') !== false) {
            $suggestions = [
                [
                    'id' => 1,
                    'street' => 'ul. Marszałkowska 1',
                    'city' => 'Warsaw',
                    'postal_code' => '00-624',
                    'country' => 'Poland',
                    'formatted' => 'ul. Marszałkowska 1, 00-624 Warsaw, Poland'
                ],
                [
                    'id' => 2,
                    'street' => 'ul. Nowy Świat 10',
                    'city' => 'Warsaw',
                    'postal_code' => '00-497',
                    'country' => 'Poland',
                    'formatted' => 'ul. Nowy Świat 10, 00-497 Warsaw, Poland'
                ]
            ];
        } elseif (stripos($query, 'Krakow') !== false || stripos($query, 'Kraków') !== false) {
            $suggestions = [
                [
                    'id' => 3,
                    'street' => 'ul. Floriańska 1',
                    'city' => 'Krakow',
                    'postal_code' => '31-019',
                    'country' => 'Poland',
                    'formatted' => 'ul. Floriańska 1, 31-019 Krakow, Poland'
                ],
                [
                    'id' => 4,
                    'street' => 'Rynek Główny 1',
                    'city' => 'Krakow',
                    'postal_code' => '31-042',
                    'country' => 'Poland',
                    'formatted' => 'Rynek Główny 1, 31-042 Krakow, Poland'
                ]
            ];
        } elseif (stripos($query, 'Gdansk') !== false || stripos($query, 'Gdańsk') !== false) {
            $suggestions = [
                [
                    'id' => 5,
                    'street' => 'ul. Długa 1',
                    'city' => 'Gdansk',
                    'postal_code' => '80-827',
                    'country' => 'Poland',
                    'formatted' => 'ul. Długa 1, 80-827 Gdansk, Poland'
                ]
            ];
        }

        return $suggestions;
    }
}