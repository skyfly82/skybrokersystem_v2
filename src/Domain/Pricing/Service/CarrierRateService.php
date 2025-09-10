<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Service;

use App\Domain\Pricing\DTO\PriceCalculationRequestDTO;
use App\Domain\Pricing\DTO\PriceCalculationResponseDTO;
use App\Domain\Pricing\Entity\Carrier;
use App\Domain\Pricing\Entity\AdditionalService;
use App\Domain\Pricing\Repository\PricingTableRepository;
use App\Domain\Pricing\Repository\PricingRuleRepository;
use App\Domain\Pricing\Repository\AdditionalServicePriceRepository;
use App\Domain\Pricing\Repository\PricingZoneRepository;
use App\Domain\Pricing\Exception\PricingException;
use Psr\Log\LoggerInterface;

/**
 * Service for calculating carrier-specific rates and pricing
 */
class CarrierRateService
{
    public function __construct(
        private readonly PricingTableRepository $pricingTableRepository,
        private readonly PricingRuleRepository $pricingRuleRepository,
        private readonly AdditionalServicePriceRepository $additionalServicePriceRepository,
        private readonly PricingZoneRepository $pricingZoneRepository,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Calculate base price for carrier
     */
    public function calculateBasePrice(
        PriceCalculationRequestDTO $request,
        Carrier $carrier
    ): PriceCalculationResponseDTO {
        // Find pricing table for carrier, zone and service type
        $pricingTable = $this->pricingTableRepository->findByCarrierZoneAndService(
            $carrier,
            $request->zoneCode,
            $request->serviceType
        );

        if ($pricingTable === null) {
            throw PricingException::pricingTableNotFound(
                $carrier->getCode(),
                $request->zoneCode,
                $request->serviceType
            );
        }

        // Get chargeable weight (higher of actual weight or volumetric weight)
        $chargeableWeight = $request->getChargeableWeight($this->getVolumetricDivisor($carrier));

        // Find applicable pricing rule
        $pricingRule = $this->pricingRuleRepository->findByTableAndWeight($pricingTable, $chargeableWeight);

        if ($pricingRule === null) {
            throw PricingException::noPricingRuleFound($chargeableWeight);
        }

        // Calculate base price using the pricing rule
        $basePrice = $this->calculatePriceFromRule($pricingRule, $chargeableWeight);

        // Get zone information
        $zone = $this->pricingZoneRepository->findByCode($request->zoneCode);
        $zoneName = $zone?->getName() ?? $request->zoneCode;

        // Create response
        $response = new PriceCalculationResponseDTO(
            $carrier->getCode(),
            $carrier->getName(),
            $request->zoneCode,
            $zoneName,
            $request->serviceType,
            $request->weightKg,
            $chargeableWeight,
            $request->dimensionsCm,
            number_format($basePrice, 2, '.', ''),
            $request->currency
        );

        // Add price breakdown
        $response->addPriceBreakdownItem('Base rate', number_format($basePrice, 2, '.', ''));
        
        if ($chargeableWeight > $request->weightKg) {
            $response->addPriceBreakdownItem(
                'Volumetric weight applied',
                sprintf('%.3f kg (%.1f cm³ / %d)', 
                    $chargeableWeight, 
                    $request->getVolumetricWeight($this->getVolumetricDivisor($carrier)) * $this->getVolumetricDivisor($carrier),
                    $this->getVolumetricDivisor($carrier)
                )
            );
        }

        $this->logger->debug('Base price calculated', [
            'carrier_code' => $carrier->getCode(),
            'zone_code' => $request->zoneCode,
            'service_type' => $request->serviceType,
            'actual_weight' => $request->weightKg,
            'chargeable_weight' => $chargeableWeight,
            'base_price' => $basePrice,
            'pricing_rule_id' => $pricingRule->getId(),
        ]);

        return $response;
    }

    /**
     * Calculate additional service price
     */
    public function calculateAdditionalServicePrice(
        AdditionalService $service,
        PriceCalculationRequestDTO $request
    ): string {
        $servicePrice = $this->additionalServicePriceRepository->findByServiceAndZone(
            $service,
            $request->zoneCode
        );

        if ($servicePrice === null) {
            throw PricingException::additionalServiceNotFound($service->getCode());
        }

        $price = $this->calculateServicePriceFromRule($servicePrice, $request);

        $this->logger->debug('Additional service price calculated', [
            'service_code' => $service->getCode(),
            'zone_code' => $request->zoneCode,
            'price' => $price,
            'calculation_method' => $servicePrice->getCalculationMethod(),
        ]);

        return number_format($price, 2, '.', '');
    }

    /**
     * Get volumetric divisor for carrier
     */
    private function getVolumetricDivisor(Carrier $carrier): int
    {
        // Different carriers may use different volumetric divisors
        return match ($carrier->getCode()) {
            Carrier::CARRIER_INPOST => 5000,
            Carrier::CARRIER_DHL => 5000,
            Carrier::CARRIER_UPS => 5000,
            Carrier::CARRIER_DPD => 4000,
            Carrier::CARRIER_MEEST => 6000,
            default => 5000,
        };
    }

    /**
     * Calculate price from pricing rule
     */
    private function calculatePriceFromRule($pricingRule, float $weight): float
    {
        $calculationMethod = $pricingRule->getCalculationMethod();
        
        return match ($calculationMethod) {
            'flat_rate' => (float)$pricingRule->getFlatRate(),
            'fixed' => (float)$pricingRule->getFlatRate(),
            'per_kg' => $weight * ($pricingRule->getPricePerKg() ?? 0),
            'per_kg_step' => $this->calculateTieredPrice($pricingRule, $weight),
            'tiered' => $this->calculateTieredPrice($pricingRule, $weight),
            default => throw PricingException::invalidPricingConfiguration(
                "Unknown calculation method: {$calculationMethod}"
            ),
        };
    }

    /**
     * Calculate tiered pricing
     */
    private function calculateTieredPrice($pricingRule, float $weight): float
    {
        $baseRate = (float)$pricingRule->getFlatRate();
        $baseWeight = (float)$pricingRule->getMinWeight();
        $additionalRate = (float)$pricingRule->getPricePerKg();

        if ($weight <= $baseWeight) {
            return $baseRate;
        }

        $additionalWeight = $weight - $baseWeight;
        return $baseRate + ($additionalWeight * $additionalRate);
    }

    /**
     * Calculate service price from additional service price rule
     */
    private function calculateServicePriceFromRule($servicePrice, PriceCalculationRequestDTO $request): float
    {
        $calculationMethod = $servicePrice->getCalculationMethod();
        
        return match ($calculationMethod) {
            'flat_rate' => (float)$servicePrice->getFlatRate(),
            'percentage' => $this->calculatePercentageServicePrice($servicePrice, $request),
            'per_kg' => $request->getChargeableWeight() * (float)$servicePrice->getPricePerKg(),
            'cod_percentage' => $this->calculateCodServicePrice($servicePrice, $request),
            default => throw PricingException::invalidPricingConfiguration(
                "Unknown service calculation method: {$calculationMethod}"
            ),
        };
    }

    /**
     * Calculate percentage-based service price
     */
    private function calculatePercentageServicePrice($servicePrice, PriceCalculationRequestDTO $request): float
    {
        // For percentage calculation, we need base shipment value
        // This would typically come from the request or be calculated separately
        $baseValue = $this->getBaseShipmentValue($request);
        $percentage = (float)$servicePrice->getPercentageRate();
        
        return ($baseValue * $percentage) / 100;
    }

    /**
     * Calculate COD (Cash on Delivery) service price
     */
    private function calculateCodServicePrice($servicePrice, PriceCalculationRequestDTO $request): float
    {
        // COD service typically charges a percentage of declared value plus flat fee
        $declaredValue = $this->getDeclaredValue($request);
        $percentage = (float)$servicePrice->getPercentageRate();
        $flatFee = (float)$servicePrice->getFlatRate();
        
        return $flatFee + (($declaredValue * $percentage) / 100);
    }

    /**
     * Get base shipment value for percentage calculations
     */
    private function getBaseShipmentValue(PriceCalculationRequestDTO $request): float
    {
        // This would typically come from additional request data
        // For now, return a default value or extract from custom parameters
        return $request->customerId !== null ? 100.0 : 50.0;
    }

    /**
     * Get declared value for COD calculations
     */
    private function getDeclaredValue(PriceCalculationRequestDTO $request): float
    {
        // This would typically come from request parameters
        // For now, return a default value
        return 500.0;
    }
}