<?php

declare(strict_types=1);

namespace App\Service\Shipment;

use Psr\Log\LoggerInterface;

class ShipmentValidatorService
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Validate complete shipment data
     */
    public function validateShipmentData(array $data): array
    {
        $errors = [];

        // Validate package details
        $packageValidation = $this->validatePackageData($data);
        if (!$packageValidation['valid']) {
            $errors = array_merge($errors, $packageValidation['errors']);
        }

        // Validate addresses
        $addressValidation = $this->validateAddressData($data);
        if (!$addressValidation['valid']) {
            $errors = array_merge($errors, $addressValidation['errors']);
        }

        // Validate services
        $servicesValidation = $this->validateServicesData($data);
        if (!$servicesValidation['valid']) {
            $errors = array_merge($errors, $servicesValidation['errors']);
        }

        // Validate courier selection
        $courierValidation = $this->validateCourierSelection($data);
        if (!$courierValidation['valid']) {
            $errors = array_merge($errors, $courierValidation['errors']);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate package data
     */
    private function validatePackageData(array $data): array
    {
        $errors = [];

        // Validate weight
        if (empty($data['weight_kg']) || !is_numeric($data['weight_kg'])) {
            $errors['weight_kg'] = 'Valid weight is required';
        } elseif ((float)$data['weight_kg'] <= 0) {
            $errors['weight_kg'] = 'Weight must be greater than 0';
        } elseif ((float)$data['weight_kg'] > 50) {
            $errors['weight_kg'] = 'Weight cannot exceed 50kg';
        }

        // Validate dimensions if provided
        if (!empty($data['dimensions'])) {
            $requiredDimensions = ['length', 'width', 'height'];
            foreach ($requiredDimensions as $dimension) {
                if (empty($data['dimensions'][$dimension]) || !is_numeric($data['dimensions'][$dimension])) {
                    $errors["dimensions.{$dimension}"] = ucfirst($dimension) . ' is required';
                } elseif ((int)$data['dimensions'][$dimension] <= 0) {
                    $errors["dimensions.{$dimension}"] = ucfirst($dimension) . ' must be greater than 0';
                }
            }
        }

        // Validate items
        if (empty($data['items']) || !is_array($data['items'])) {
            $errors['items'] = 'At least one item is required';
        } else {
            foreach ($data['items'] as $index => $item) {
                if (empty($item['name'])) {
                    $errors["items.{$index}.name"] = 'Item name is required';
                }
                if (empty($item['quantity']) || !is_numeric($item['quantity']) || (int)$item['quantity'] <= 0) {
                    $errors["items.{$index}.quantity"] = 'Valid quantity is required';
                }
                if (!isset($item['value']) || !is_numeric($item['value']) || (float)$item['value'] < 0) {
                    $errors["items.{$index}.value"] = 'Valid item value is required';
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate address data
     */
    private function validateAddressData(array $data): array
    {
        $errors = [];

        // Validate sender address
        $senderErrors = $this->validateAddress($data['sender'] ?? [], 'sender');
        $errors = array_merge($errors, $senderErrors);

        // Validate recipient address
        $recipientErrors = $this->validateAddress($data['recipient'] ?? [], 'recipient');
        $errors = array_merge($errors, $recipientErrors);

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate single address
     */
    private function validateAddress(array $address, string $prefix): array
    {
        $errors = [];
        $requiredFields = ['name', 'address', 'city', 'postal_code', 'country', 'phone', 'email'];

        foreach ($requiredFields as $field) {
            if (empty($address[$field])) {
                $errors["{$prefix}.{$field}"] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }

        // Validate email
        if (!empty($address['email']) && !filter_var($address['email'], FILTER_VALIDATE_EMAIL)) {
            $errors["{$prefix}.email"] = 'Valid email address is required';
        }

        // Validate postal code format (basic validation)
        if (!empty($address['postal_code']) && !preg_match('/^\d{2}-\d{3}$/', $address['postal_code'])) {
            $errors["{$prefix}.postal_code"] = 'Postal code must be in format XX-XXX';
        }

        return $errors;
    }

    /**
     * Validate services data
     */
    private function validateServicesData(array $data): array
    {
        $errors = [];

        // Validate COD if enabled
        if (!empty($data['cod_enabled'])) {
            if (empty($data['cod_amount']) || !is_numeric($data['cod_amount'])) {
                $errors['cod_amount'] = 'COD amount is required when COD is enabled';
            } elseif ((float)$data['cod_amount'] <= 0) {
                $errors['cod_amount'] = 'COD amount must be greater than 0';
            } elseif ((float)$data['cod_amount'] > 50000) {
                $errors['cod_amount'] = 'COD amount cannot exceed 50,000 PLN';
            }
        }

        // Validate insurance if enabled
        if (!empty($data['insurance_enabled'])) {
            if (empty($data['insurance_amount']) || !is_numeric($data['insurance_amount'])) {
                $errors['insurance_amount'] = 'Insurance amount is required when insurance is enabled';
            } elseif ((float)$data['insurance_amount'] <= 0) {
                $errors['insurance_amount'] = 'Insurance amount must be greater than 0';
            } elseif ((float)$data['insurance_amount'] > 100000) {
                $errors['insurance_amount'] = 'Insurance amount cannot exceed 100,000 PLN';
            }
        }

        // Validate delivery instructions length
        if (!empty($data['delivery_instructions']) && strlen($data['delivery_instructions']) > 500) {
            $errors['delivery_instructions'] = 'Delivery instructions cannot exceed 500 characters';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate courier selection
     */
    private function validateCourierSelection(array $data): array
    {
        $errors = [];

        if (empty($data['courier_service'])) {
            $errors['courier_service'] = 'Courier service selection is required';
        }

        if (empty($data['payment_method'])) {
            $errors['payment_method'] = 'Payment method is required';
        }

        // Validate InPost specific requirements
        if (!empty($data['courier_service']) && $data['courier_service'] === 'inpost') {
            if (empty($data['inpost_point'])) {
                $errors['inpost_point'] = 'InPost point selection is required';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate weight limits for courier
     */
    public function validateWeightLimits(string $courierService, float $weight): array
    {
        $limits = [
            'inpost' => [
                'min' => 0.1,
                'max' => 25.0
            ],
            'dhl' => [
                'min' => 0.1,
                'max' => 70.0
            ]
        ];

        $limit = $limits[$courierService] ?? $limits['inpost'];

        $errors = [];
        if ($weight < $limit['min']) {
            $errors['weight'] = "Minimum weight for {$courierService} is {$limit['min']}kg";
        } elseif ($weight > $limit['max']) {
            $errors['weight'] = "Maximum weight for {$courierService} is {$limit['max']}kg";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate dimensions for courier
     */
    public function validateDimensions(string $courierService, array $dimensions): array
    {
        $limits = [
            'inpost' => [
                'max_length' => 60,
                'max_width' => 40,
                'max_height' => 40,
                'max_sum' => 120
            ],
            'dhl' => [
                'max_length' => 120,
                'max_width' => 80,
                'max_height' => 80,
                'max_sum' => 300
            ]
        ];

        $limit = $limits[$courierService] ?? $limits['inpost'];

        $errors = [];

        if (!empty($dimensions['length']) && $dimensions['length'] > $limit['max_length']) {
            $errors['length'] = "Maximum length for {$courierService} is {$limit['max_length']}cm";
        }

        if (!empty($dimensions['width']) && $dimensions['width'] > $limit['max_width']) {
            $errors['width'] = "Maximum width for {$courierService} is {$limit['max_width']}cm";
        }

        if (!empty($dimensions['height']) && $dimensions['height'] > $limit['max_height']) {
            $errors['height'] = "Maximum height for {$courierService} is {$limit['max_height']}cm";
        }

        // Check total dimensions
        $sum = ($dimensions['length'] ?? 0) + ($dimensions['width'] ?? 0) + ($dimensions['height'] ?? 0);
        if ($sum > $limit['max_sum']) {
            $errors['dimensions_sum'] = "Total dimensions for {$courierService} cannot exceed {$limit['max_sum']}cm";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate value limits
     */
    public function validateValueLimits(float $value, array $services = []): array
    {
        $errors = [];

        // Maximum value without insurance
        if ($value > 5000 && !in_array('insurance', $services, true)) {
            $errors['value'] = 'Items with value over 5,000 PLN require insurance';
        }

        // Maximum insurable value
        if ($value > 100000) {
            $errors['value'] = 'Maximum insurable value is 100,000 PLN';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}