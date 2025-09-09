<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Contracts;

use App\Domain\Pricing\DTO\PriceCalculationRequestDTO;
use App\Domain\Pricing\DTO\PriceCalculationResponseDTO;
use App\Domain\Pricing\DTO\PriceComparisonRequestDTO;
use App\Domain\Pricing\DTO\PriceComparisonResponseDTO;
use App\Domain\Pricing\DTO\BulkPriceCalculationRequestDTO;
use App\Domain\Pricing\DTO\BulkPriceCalculationResponseDTO;

/**
 * Main interface for pricing calculation services
 */
interface PricingCalculatorInterface
{
    /**
     * Calculate price for a single carrier
     */
    public function calculatePrice(PriceCalculationRequestDTO $request): PriceCalculationResponseDTO;

    /**
     * Compare prices across all available carriers
     */
    public function compareAllCarriers(PriceComparisonRequestDTO $request): PriceComparisonResponseDTO;

    /**
     * Get the best price option from all available carriers
     */
    public function getBestPrice(PriceComparisonRequestDTO $request): PriceCalculationResponseDTO;

    /**
     * Calculate prices for multiple shipments (bulk calculation)
     */
    public function calculateBulk(BulkPriceCalculationRequestDTO $request): BulkPriceCalculationResponseDTO;

    /**
     * Apply promotional discounts to an existing calculation
     */
    public function applyPromotions(
        PriceCalculationResponseDTO $response,
        ?int $customerId = null,
        ?array $promotionCodes = null
    ): PriceCalculationResponseDTO;

    /**
     * Calculate additional services pricing
     */
    public function calculateAdditionalServices(
        string $carrierCode,
        array $servicesCodes,
        PriceCalculationRequestDTO $request
    ): array;

    /**
     * Get available carriers for specific zone and weight
     */
    public function getAvailableCarriers(string $zoneCode, float $weightKg, array $dimensionsCm): array;

    /**
     * Validate if carrier can handle the shipment requirements
     */
    public function canCarrierHandle(string $carrierCode, PriceCalculationRequestDTO $request): bool;
}