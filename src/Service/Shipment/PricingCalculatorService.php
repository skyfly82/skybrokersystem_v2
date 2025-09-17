<?php

declare(strict_types=1);

namespace App\Service\Shipment;

use App\Service\InPostApiClient;
use App\Service\CourierServiceRegistry;
use App\Repository\CourierServiceRepository;

/**
 * Real-time Pricing Calculator Service
 * Calculates shipping costs across multiple courier services with real-time rates
 */
class PricingCalculatorService
{
    public function __construct(
        private readonly InPostApiClient $inPostClient,
        private readonly CourierServiceRegistry $courierRegistry,
        private readonly CourierServiceRepository $courierServiceRepository
    ) {
    }

    /**
     * Calculate pricing for shipment across all available services
     */
    public function calculateShipmentPricing(array $shipmentData): array
    {
        $this->validatePricingData($shipmentData);

        $pricing = [
            'shipment_details' => $this->extractShipmentDetails($shipmentData),
            'pricing_options' => [],
            'best_price' => null,
            'fastest_delivery' => null,
            'calculated_at' => new \DateTime(),
            'currency' => 'PLN'
        ];

        // Get pricing from all available courier services
        $availableServices = $this->getAvailableCourierServices();

        foreach ($availableServices as $service) {
            try {
                $servicePrice = $this->calculateServicePrice($service, $shipmentData);
                if ($servicePrice) {
                    $pricing['pricing_options'][] = $servicePrice;
                }
            } catch (\Exception $e) {
                // Log error but continue with other services
                error_log("Pricing calculation failed for {$service['name']}: " . $e->getMessage());
            }
        }

        // Sort and identify best options
        if (!empty($pricing['pricing_options'])) {
            $pricing = $this->identifyBestOptions($pricing);
        }

        return $pricing;
    }

    /**
     * Calculate pricing for multiple shipments (bulk pricing)
     */
    public function calculateBulkPricing(array $shipmentsData): array
    {
        $bulkPricing = [
            'shipments' => [],
            'summary' => [
                'total_shipments' => count($shipmentsData),
                'total_cost_estimate' => 0,
                'average_cost_per_shipment' => 0,
                'bulk_discount_available' => false,
                'bulk_discount_percentage' => 0
            ],
            'calculated_at' => new \DateTime()
        ];

        foreach ($shipmentsData as $index => $shipmentData) {
            try {
                $pricing = $this->calculateShipmentPricing($shipmentData);
                $bulkPricing['shipments'][$index] = $pricing;

                // Add to total cost
                if (isset($pricing['best_price']['price'])) {
                    $bulkPricing['summary']['total_cost_estimate'] += $pricing['best_price']['price'];
                }
            } catch (\Exception $e) {
                $bulkPricing['shipments'][$index] = [
                    'error' => 'Pricing calculation failed: ' . $e->getMessage()
                ];
            }
        }

        // Calculate averages and check for bulk discounts
        if ($bulkPricing['summary']['total_cost_estimate'] > 0) {
            $bulkPricing['summary']['average_cost_per_shipment'] =
                $bulkPricing['summary']['total_cost_estimate'] / $bulkPricing['summary']['total_shipments'];

            // Apply bulk discount logic
            $bulkPricing = $this->applyBulkDiscounts($bulkPricing);
        }

        return $bulkPricing;
    }

    /**
     * Compare pricing between specific courier services
     */
    public function comparePricing(array $shipmentData, array $courierCodes): array
    {
        $comparison = [
            'shipment_details' => $this->extractShipmentDetails($shipmentData),
            'comparison' => [],
            'recommendation' => null,
            'calculated_at' => new \DateTime()
        ];

        foreach ($courierCodes as $courierCode) {
            $service = $this->courierServiceRepository->findByCode($courierCode);
            if ($service && $service->isActive()) {
                try {
                    $pricing = $this->calculateServicePrice($service->toArray(), $shipmentData);
                    if ($pricing) {
                        $comparison['comparison'][] = $pricing;
                    }
                } catch (\Exception $e) {
                    $comparison['comparison'][] = [
                        'service_code' => $courierCode,
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        // Generate recommendation
        $comparison['recommendation'] = $this->generateRecommendation($comparison['comparison']);

        return $comparison;
    }

    /**
     * Get best price option for quick checkout
     */
    public function getBestPriceOption(array $shipmentData): array
    {
        $pricing = $this->calculateShipmentPricing($shipmentData);

        if (empty($pricing['pricing_options'])) {
            throw new \Exception('No pricing options available for this shipment');
        }

        return $pricing['best_price'] ?? $pricing['pricing_options'][0];
    }

    /**
     * Validate carrier-specific requirements
     */
    public function validateCarrierRequirements(string $carrierCode, array $shipmentData): array
    {
        $service = $this->courierServiceRepository->findByCode($carrierCode);
        if (!$service) {
            throw new \Exception("Courier service '{$carrierCode}' not found");
        }

        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'requirements_met' => []
        ];

        // Carrier-specific validation
        switch ($carrierCode) {
            case 'inpost':
                $validation = $this->validateInPostRequirements($shipmentData, $validation);
                break;
            case 'dhl':
                $validation = $this->validateDHLRequirements($shipmentData, $validation);
                break;
            default:
                $validation = $this->validateGenericRequirements($shipmentData, $validation);
        }

        $validation['valid'] = empty($validation['errors']);

        return $validation;
    }

    /**
     * Calculate service-specific pricing
     */
    private function calculateServicePrice(array $service, array $shipmentData): ?array
    {
        switch ($service['code']) {
            case 'inpost':
                return $this->calculateInPostPricing($shipmentData);
            case 'dhl':
                return $this->calculateDHLPricing($shipmentData);
            default:
                return $this->calculateGenericPricing($service, $shipmentData);
        }
    }

    /**
     * Calculate InPost pricing using API
     */
    private function calculateInPostPricing(array $shipmentData): array
    {
        // InPost pricing calculation
        $dimensions = $shipmentData['dimensions'] ?? [];
        $weight = (float) ($shipmentData['weight'] ?? 0);
        $serviceType = $shipmentData['service_type'] ?? 'parcel_locker';

        // Base pricing logic for InPost
        $basePrice = match ($serviceType) {
            'parcel_locker' => $this->calculateInPostParcelLockerPrice($weight, $dimensions),
            'courier' => $this->calculateInPostCourierPrice($weight, $dimensions),
            default => $this->calculateInPostParcelLockerPrice($weight, $dimensions)
        };

        // Add service fees
        $codFee = isset($shipmentData['cod_amount']) && $shipmentData['cod_amount'] > 0 ? 3.00 : 0;
        $insuranceFee = $this->calculateInsuranceFee($shipmentData['insurance_amount'] ?? 0);

        $totalPrice = $basePrice + $codFee + $insuranceFee;

        return [
            'service_code' => 'inpost',
            'service_name' => 'InPost',
            'service_type' => $serviceType,
            'price' => round($totalPrice, 2),
            'base_price' => round($basePrice, 2),
            'additional_fees' => [
                'cod_fee' => $codFee,
                'insurance_fee' => $insuranceFee
            ],
            'estimated_delivery_days' => $serviceType === 'parcel_locker' ? 1 : 2,
            'features' => [
                '24/7_pickup' => $serviceType === 'parcel_locker',
                'tracking' => true,
                'insurance_included' => true,
                'cod_available' => true
            ],
            'restrictions' => [
                'max_weight' => 25,
                'max_dimensions' => ['length' => 64, 'width' => 38, 'height' => 39]
            ]
        ];
    }

    /**
     * Calculate DHL pricing
     */
    private function calculateDHLPricing(array $shipmentData): array
    {
        // DHL pricing would integrate with DHL API
        // For now, using simplified calculation
        $weight = (float) ($shipmentData['weight'] ?? 0);
        $isInternational = $this->isInternationalShipment($shipmentData);

        $basePrice = $isInternational ? 35.00 : 20.00;
        $weightPrice = max(0, $weight - 1) * ($isInternational ? 8.00 : 4.00);

        $totalPrice = $basePrice + $weightPrice;

        return [
            'service_code' => 'dhl',
            'service_name' => 'DHL Express',
            'service_type' => 'express',
            'price' => round($totalPrice, 2),
            'base_price' => round($basePrice, 2),
            'additional_fees' => [
                'weight_fee' => round($weightPrice, 2)
            ],
            'estimated_delivery_days' => $isInternational ? 3 : 1,
            'features' => [
                'express_delivery' => true,
                'tracking' => true,
                'insurance_included' => true,
                'signature_required' => true
            ],
            'restrictions' => [
                'max_weight' => 70,
                'max_dimensions' => ['length' => 120, 'width' => 80, 'height' => 80]
            ]
        ];
    }

    /**
     * Calculate generic courier pricing
     */
    private function calculateGenericPricing(array $service, array $shipmentData): array
    {
        $weight = (float) ($shipmentData['weight'] ?? 0);
        $basePrice = $service['base_price'] ?? 15.00;
        $pricePerKg = $service['price_per_kg'] ?? 2.00;

        $totalPrice = $basePrice + (max(0, $weight - 1) * $pricePerKg);

        return [
            'service_code' => $service['code'],
            'service_name' => $service['name'],
            'service_type' => 'standard',
            'price' => round($totalPrice, 2),
            'base_price' => round($basePrice, 2),
            'estimated_delivery_days' => $service['estimated_delivery_days'] ?? 2,
            'features' => $service['features'] ?? [],
            'restrictions' => $service['restrictions'] ?? []
        ];
    }

    /**
     * Calculate InPost parcel locker pricing
     */
    private function calculateInPostParcelLockerPrice(float $weight, array $dimensions): float
    {
        // InPost size-based pricing
        $size = $this->determineInPostPackageSize($weight, $dimensions);

        return match ($size) {
            'A' => 9.99,
            'B' => 11.99,
            'C' => 13.99,
            default => 15.99
        };
    }

    /**
     * Calculate InPost courier pricing
     */
    private function calculateInPostCourierPrice(float $weight, array $dimensions): float
    {
        $basePrice = 15.99;
        $weightSurcharge = max(0, $weight - 5) * 2.00;

        return $basePrice + $weightSurcharge;
    }

    /**
     * Determine InPost package size category
     */
    private function determineInPostPackageSize(float $weight, array $dimensions): string
    {
        $length = $dimensions['length'] ?? 0;
        $width = $dimensions['width'] ?? 0;
        $height = $dimensions['height'] ?? 0;

        // Size A: up to 8 x 38 x 64 cm
        if ($length <= 8 && $width <= 38 && $height <= 64 && $weight <= 1) {
            return 'A';
        }

        // Size B: up to 19 x 38 x 64 cm
        if ($length <= 19 && $width <= 38 && $height <= 64 && $weight <= 5) {
            return 'B';
        }

        // Size C: up to 39 x 38 x 64 cm
        if ($length <= 39 && $width <= 38 && $height <= 64 && $weight <= 25) {
            return 'C';
        }

        return 'oversized';
    }

    /**
     * Calculate insurance fee based on declared value
     */
    private function calculateInsuranceFee(float $declaredValue): float
    {
        if ($declaredValue <= 500) {
            return 0; // Free insurance up to 500 PLN
        }

        return round(($declaredValue - 500) * 0.005, 2); // 0.5% of value above 500 PLN
    }

    /**
     * Check if shipment is international
     */
    private function isInternationalShipment(array $shipmentData): bool
    {
        $senderCountry = $shipmentData['sender']['country'] ?? 'PL';
        $recipientCountry = $shipmentData['recipient']['country'] ?? 'PL';

        return $senderCountry !== $recipientCountry;
    }

    /**
     * Identify best pricing options
     */
    private function identifyBestOptions(array $pricing): array
    {
        $options = $pricing['pricing_options'];

        // Sort by price (ascending)
        usort($options, fn($a, $b) => $a['price'] <=> $b['price']);
        $pricing['best_price'] = $options[0] ?? null;

        // Sort by delivery time (ascending)
        usort($options, fn($a, $b) => $a['estimated_delivery_days'] <=> $b['estimated_delivery_days']);
        $pricing['fastest_delivery'] = $options[0] ?? null;

        // Restore original pricing options order
        $pricing['pricing_options'] = $options;

        return $pricing;
    }

    /**
     * Apply bulk discounts for large shipments
     */
    private function applyBulkDiscounts(array $bulkPricing): array
    {
        $shipmentCount = $bulkPricing['summary']['total_shipments'];
        $discountPercentage = 0;

        // Bulk discount tiers
        if ($shipmentCount >= 100) {
            $discountPercentage = 15;
        } elseif ($shipmentCount >= 50) {
            $discountPercentage = 10;
        } elseif ($shipmentCount >= 20) {
            $discountPercentage = 5;
        }

        if ($discountPercentage > 0) {
            $originalTotal = $bulkPricing['summary']['total_cost_estimate'];
            $discountAmount = $originalTotal * ($discountPercentage / 100);

            $bulkPricing['summary']['bulk_discount_available'] = true;
            $bulkPricing['summary']['bulk_discount_percentage'] = $discountPercentage;
            $bulkPricing['summary']['discount_amount'] = round($discountAmount, 2);
            $bulkPricing['summary']['total_cost_after_discount'] = round($originalTotal - $discountAmount, 2);
        }

        return $bulkPricing;
    }

    /**
     * Generate pricing recommendation
     */
    private function generateRecommendation(array $pricingOptions): ?array
    {
        if (empty($pricingOptions)) {
            return null;
        }

        // Score each option based on price, delivery time, and features
        $scoredOptions = array_map(function ($option) {
            if (isset($option['error'])) {
                return null;
            }

            $priceScore = 100 - min(100, ($option['price'] / 50) * 100); // Lower price = higher score
            $speedScore = 100 - min(100, ($option['estimated_delivery_days'] / 7) * 100); // Faster = higher score
            $featureScore = count($option['features']) * 10; // More features = higher score

            $totalScore = ($priceScore * 0.5) + ($speedScore * 0.3) + ($featureScore * 0.2);

            return array_merge($option, ['recommendation_score' => round($totalScore, 2)]);
        }, $pricingOptions);

        $scoredOptions = array_filter($scoredOptions); // Remove null entries

        if (empty($scoredOptions)) {
            return null;
        }

        // Sort by score (descending)
        usort($scoredOptions, fn($a, $b) => $b['recommendation_score'] <=> $a['recommendation_score']);

        return $scoredOptions[0];
    }

    /**
     * Get available courier services
     */
    private function getAvailableCourierServices(): array
    {
        $services = $this->courierServiceRepository->findBy(['isActive' => true]);

        return array_map(fn($service) => $service->toArray(), $services);
    }

    /**
     * Validate pricing calculation data
     */
    private function validatePricingData(array $data): void
    {
        $required = ['sender', 'recipient', 'weight'];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!is_numeric($data['weight']) || $data['weight'] <= 0) {
            throw new \InvalidArgumentException("Invalid weight value");
        }
    }

    /**
     * Extract shipment details for pricing summary
     */
    private function extractShipmentDetails(array $shipmentData): array
    {
        return [
            'weight' => (float) ($shipmentData['weight'] ?? 0),
            'dimensions' => $shipmentData['dimensions'] ?? null,
            'from_country' => $shipmentData['sender']['country'] ?? 'PL',
            'to_country' => $shipmentData['recipient']['country'] ?? 'PL',
            'service_type' => $shipmentData['service_type'] ?? 'standard',
            'cod_amount' => $shipmentData['cod_amount'] ?? null,
            'insurance_amount' => $shipmentData['insurance_amount'] ?? null
        ];
    }

    /**
     * Validate InPost-specific requirements
     */
    private function validateInPostRequirements(array $shipmentData, array $validation): array
    {
        $weight = (float) ($shipmentData['weight'] ?? 0);
        $dimensions = $shipmentData['dimensions'] ?? [];

        if ($weight > 25) {
            $validation['errors'][] = 'InPost maximum weight is 25kg';
        }

        if (isset($dimensions['length']) && $dimensions['length'] > 64) {
            $validation['errors'][] = 'InPost maximum length is 64cm';
        }

        return $validation;
    }

    /**
     * Validate DHL-specific requirements
     */
    private function validateDHLRequirements(array $shipmentData, array $validation): array
    {
        $weight = (float) ($shipmentData['weight'] ?? 0);

        if ($weight > 70) {
            $validation['errors'][] = 'DHL maximum weight is 70kg';
        }

        return $validation;
    }

    /**
     * Validate generic courier requirements
     */
    private function validateGenericRequirements(array $shipmentData, array $validation): array
    {
        // Basic validation for all couriers
        $weight = (float) ($shipmentData['weight'] ?? 0);

        if ($weight > 100) {
            $validation['errors'][] = 'Maximum weight exceeded for most courier services';
        }

        return $validation;
    }
}