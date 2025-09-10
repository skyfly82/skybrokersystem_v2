<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Service;

use App\Domain\Pricing\Contracts\PricingCalculatorInterface;
use App\Domain\Pricing\DTO\PriceCalculationRequestDTO;
use App\Domain\Pricing\DTO\PriceCalculationResponseDTO;
use App\Domain\Pricing\DTO\PriceComparisonRequestDTO;
use App\Domain\Pricing\DTO\PriceComparisonResponseDTO;
use App\Domain\Pricing\DTO\BulkPriceCalculationRequestDTO;
use App\Domain\Pricing\DTO\BulkPriceCalculationResponseDTO;
use App\Domain\Pricing\Repository\CarrierRepository;
use App\Domain\Pricing\Repository\PricingTableRepository;
use App\Domain\Pricing\Repository\PricingRuleRepository;
use App\Domain\Pricing\Repository\AdditionalServiceRepository;
use App\Domain\Pricing\Repository\AdditionalServicePriceRepository;
use App\Domain\Pricing\Repository\CustomerPricingRepository;
use App\Domain\Pricing\Repository\PromotionalPricingRepository;
use App\Domain\Pricing\Exception\PricingCalculatorException;
use App\Domain\Pricing\Exception\PricingException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Main pricing calculator service for courier shipments
 * 
 * This service handles:
 * - Single carrier price calculations
 * - Multi-carrier price comparisons
 * - Bulk pricing calculations
 * - Customer-specific pricing and discounts
 * - Promotional pricing application
 * - Additional services pricing
 */
class PricingCalculatorService implements PricingCalculatorInterface
{
    private const VOLUMETRIC_DIVISOR = 5000; // Standard volumetric divisor
    private const MAX_BULK_REQUESTS = 100;
    private const CALCULATION_TIMEOUT_SECONDS = 30.0;

    public function __construct(
        private readonly CarrierRepository $carrierRepository,
        private readonly PricingTableRepository $pricingTableRepository,
        private readonly PricingRuleRepository $pricingRuleRepository,
        private readonly AdditionalServiceRepository $additionalServiceRepository,
        private readonly AdditionalServicePriceRepository $additionalServicePriceRepository,
        private readonly CustomerPricingRepository $customerPricingRepository,
        private readonly PromotionalPricingRepository $promotionalPricingRepository,
        private readonly CarrierRateService $carrierRateService,
        private readonly PricingRuleEngine $pricingRuleEngine,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Calculate price for a single carrier
     */
    public function calculatePrice(PriceCalculationRequestDTO $request): PriceCalculationResponseDTO
    {
        $this->validateRequest($request);

        $startTime = microtime(true);
        
        try {
            $carrier = $this->getValidatedCarrier($request->carrierCode, $request);
            
            // Get base pricing
            $response = $this->calculateBasePrice($request, $carrier);
            
            // Add additional services
            if (!empty($request->additionalServices)) {
                $this->addAdditionalServices($response, $request, $carrier);
            }
            
            // Apply customer-specific pricing
            if ($request->customerId !== null) {
                $this->applyCustomerPricing($response, $request);
            }
            
            // Apply promotional discounts
            $this->applyPromotionalPricing($response, $request);
            
            // Calculate tax
            $this->calculateTax($response);
            
            $calculationTime = (microtime(true) - $startTime) * 1000;
            
            $this->logger->info('Price calculation completed', [
                'carrier_code' => $request->carrierCode,
                'zone_code' => $request->zoneCode,
                'weight_kg' => $request->weightKg,
                'total_price' => $response->totalPrice,
                'calculation_time_ms' => $calculationTime,
            ]);
            
            return $response;
            
        } catch (\Throwable $e) {
            $calculationTime = (microtime(true) - $startTime) * 1000;
            
            $this->logger->error('Price calculation failed', [
                'carrier_code' => $request->carrierCode,
                'zone_code' => $request->zoneCode,
                'weight_kg' => $request->weightKg,
                'error' => $e->getMessage(),
                'calculation_time_ms' => $calculationTime,
            ]);
            
            if ($e instanceof PricingException) {
                throw $e;
            }
            
            throw PricingCalculatorException::calculationError($e->getMessage(), $e);
        }
    }

    /**
     * Compare prices across all available carriers
     */
    public function compareAllCarriers(PriceComparisonRequestDTO $request): PriceComparisonResponseDTO
    {
        $this->validateComparisonRequest($request);

        $startTime = microtime(true);
        $response = new PriceComparisonResponseDTO($request);

        try {
            $availableCarriers = $this->getAvailableCarriers(
                $request->zoneCode,
                $request->weightKg,
                $request->dimensionsCm
            );

            $response->setTotalCarriersChecked(count($availableCarriers));

            foreach ($availableCarriers as $carrier) {
                if (!$request->shouldIncludeCarrier($carrier->getCode())) {
                    continue;
                }

                try {
                    $calculationRequest = $request->toPriceCalculationRequest($carrier->getCode());
                    $price = $this->calculatePrice($calculationRequest);
                    $response->addPrice($price);
                } catch (\Throwable $e) {
                    $response->addUnavailableCarrier($carrier->getCode(), $e->getMessage());
                    
                    $this->logger->warning('Carrier price calculation failed in comparison', [
                        'carrier_code' => $carrier->getCode(),
                        'zone_code' => $request->zoneCode,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($response->availableCarriersCount === 0) {
                throw PricingCalculatorException::allCarrierCalculationsFailed($request->zoneCode);
            }

            // Sort by price by default
            $response->sortPricesByTotal('asc');

            $calculationTime = (microtime(true) - $startTime) * 1000;
            
            $this->logger->info('Price comparison completed', [
                'zone_code' => $request->zoneCode,
                'total_carriers' => $response->totalCarriersChecked,
                'available_carriers' => $response->availableCarriersCount,
                'calculation_time_ms' => $calculationTime,
            ]);

            return $response;

        } catch (\Throwable $e) {
            $calculationTime = (microtime(true) - $startTime) * 1000;
            
            $this->logger->error('Price comparison failed', [
                'zone_code' => $request->zoneCode,
                'error' => $e->getMessage(),
                'calculation_time_ms' => $calculationTime,
            ]);

            if ($e instanceof PricingException) {
                throw $e;
            }

            throw PricingCalculatorException::calculationError($e->getMessage(), $e);
        }
    }

    /**
     * Get the best price option from all available carriers
     */
    public function getBestPrice(PriceComparisonRequestDTO $request): PriceCalculationResponseDTO
    {
        $comparison = $this->compareAllCarriers($request);
        $bestPrice = $comparison->getBestPrice();

        if ($bestPrice === null) {
            throw PricingCalculatorException::noCarriersAvailable($request->zoneCode);
        }

        return $bestPrice;
    }

    /**
     * Calculate prices for multiple shipments (bulk calculation)
     */
    public function calculateBulk(BulkPriceCalculationRequestDTO $request): BulkPriceCalculationResponseDTO
    {
        $this->validateBulkRequest($request);

        $startTime = microtime(true);
        $response = new BulkPriceCalculationResponseDTO($request);

        try {
            foreach ($request->requests as $index => $calculationRequest) {
                try {
                    $price = $this->calculatePrice($calculationRequest);
                    $response->addPrice($price);
                } catch (\Throwable $e) {
                    $response->addError($index, $e->getMessage());
                    
                    $this->logger->warning('Single calculation failed in bulk request', [
                        'request_index' => $index,
                        'carrier_code' => $calculationRequest->carrierCode,
                        'error' => $e->getMessage(),
                    ]);

                    if ($request->stopOnFirstError) {
                        break;
                    }
                }
            }

            if ($response->successfulCalculations === 0) {
                throw PricingCalculatorException::bulkCalculationCompleteFailure();
            }

            // Apply bulk discount if qualified
            if ($request->qualifiesForBulkDiscount()) {
                $response->applyBulkDiscount();
            }

            $calculationTime = (microtime(true) - $startTime) * 1000;
            $response->setCalculationTime($calculationTime);

            $this->logger->info('Bulk price calculation completed', [
                'total_requests' => $response->totalRequests,
                'successful' => $response->successfulCalculations,
                'failed' => $response->failedCalculations,
                'calculation_time_ms' => $calculationTime,
            ]);

            if ($response->failedCalculations > 0) {
                throw PricingCalculatorException::bulkCalculationPartialFailure(
                    $response->successfulCalculations,
                    $response->failedCalculations
                );
            }

            return $response;

        } catch (\Throwable $e) {
            $calculationTime = (microtime(true) - $startTime) * 1000;
            $response->setCalculationTime($calculationTime);
            
            $this->logger->error('Bulk price calculation failed', [
                'total_requests' => $request->getRequestCount(),
                'error' => $e->getMessage(),
                'calculation_time_ms' => $calculationTime,
            ]);

            if ($e instanceof PricingCalculatorException) {
                throw $e;
            }

            throw PricingCalculatorException::calculationError($e->getMessage(), $e);
        }
    }

    /**
     * Apply promotional discounts to an existing calculation
     */
    public function applyPromotions(
        PriceCalculationResponseDTO $response,
        ?int $customerId = null,
        ?array $promotionCodes = null
    ): PriceCalculationResponseDTO {
        try {
            $promotions = $this->promotionalPricingRepository->findActivePromotions(
                $response->carrierCode,
                $customerId,
                $promotionCodes
            );

            foreach ($promotions as $promotion) {
                if ($this->pricingRuleEngine->isPromotionApplicable($promotion, $response)) {
                    $discountAmount = $this->pricingRuleEngine->calculatePromotionDiscount($promotion, $response);
                    $response->applyPromotionalDiscount($discountAmount);
                    
                    $this->logger->info('Promotional discount applied', [
                        'promotion_id' => $promotion->getId(),
                        'discount_amount' => $discountAmount,
                        'carrier_code' => $response->carrierCode,
                    ]);
                }
            }

            return $response;

        } catch (\Throwable $e) {
            $this->logger->error('Failed to apply promotions', [
                'carrier_code' => $response->carrierCode,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            throw PricingCalculatorException::promotionApplicationFailed($e->getMessage(), $e);
        }
    }

    /**
     * Calculate additional services pricing
     */
    public function calculateAdditionalServices(
        string $carrierCode,
        array $servicesCodes,
        PriceCalculationRequestDTO $request
    ): array {
        $results = [];
        
        try {
            $carrier = $this->getValidatedCarrier($carrierCode, $request);
            
            foreach ($servicesCodes as $serviceCode) {
                try {
                    $service = $this->additionalServiceRepository->findByCarrierAndCode($carrier, $serviceCode);
                    
                    if ($service === null) {
                        throw PricingException::additionalServiceNotFound($serviceCode);
                    }

                    $price = $this->carrierRateService->calculateAdditionalServicePrice(
                        $service,
                        $request
                    );

                    $results[] = [
                        'code' => $serviceCode,
                        'name' => $service->getName(),
                        'price' => $price,
                        'currency' => $request->currency,
                    ];

                } catch (\Throwable $e) {
                    $this->logger->warning('Additional service calculation failed', [
                        'service_code' => $serviceCode,
                        'carrier_code' => $carrierCode,
                        'error' => $e->getMessage(),
                    ]);

                    throw PricingCalculatorException::additionalServiceCalculationFailed(
                        $serviceCode,
                        $e->getMessage()
                    );
                }
            }

            return $results;

        } catch (\Throwable $e) {
            if ($e instanceof PricingCalculatorException) {
                throw $e;
            }

            throw PricingCalculatorException::calculationError($e->getMessage(), $e);
        }
    }

    /**
     * Get available carriers for specific zone and weight
     */
    public function getAvailableCarriers(string $zoneCode, float $weightKg, array $dimensionsCm): array
    {
        $carriers = $this->carrierRepository->findByZoneSupport($zoneCode);
        
        return array_filter($carriers, function ($carrier) use ($weightKg, $dimensionsCm) {
            return $carrier->canHandleWeight($weightKg) &&
                   $carrier->canHandleDimensions(
                       $dimensionsCm['length'],
                       $dimensionsCm['width'],
                       $dimensionsCm['height']
                   );
        });
    }

    /**
     * Validate if carrier can handle the shipment requirements
     */
    public function canCarrierHandle(string $carrierCode, PriceCalculationRequestDTO $request): bool
    {
        try {
            $carrier = $this->carrierRepository->findByCode($carrierCode);
            
            if ($carrier === null || !$carrier->isActive()) {
                return false;
            }

            return $carrier->supportsZone($request->zoneCode) &&
                   $carrier->canHandleWeight($request->weightKg) &&
                   $carrier->canHandleDimensions(
                       $request->dimensionsCm['length'],
                       $request->dimensionsCm['width'],
                       $request->dimensionsCm['height']
                   );

        } catch (\Throwable $e) {
            $this->logger->warning('Carrier validation check failed', [
                'carrier_code' => $carrierCode,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Validate price calculation request
     */
    private function validateRequest(PriceCalculationRequestDTO $request): void
    {
        $violations = $this->validator->validate($request);
        
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            
            throw PricingException::invalidPricingConfiguration(implode(', ', $errors));
        }
    }

    /**
     * Validate price comparison request
     */
    private function validateComparisonRequest(PriceComparisonRequestDTO $request): void
    {
        $violations = $this->validator->validate($request);
        
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            
            throw PricingCalculatorException::comparisonRequestValidationFailed(implode(', ', $errors));
        }
    }

    /**
     * Validate bulk calculation request
     */
    private function validateBulkRequest(BulkPriceCalculationRequestDTO $request): void
    {
        if ($request->getRequestCount() === 0) {
            throw PricingCalculatorException::invalidBulkRequest('No requests provided');
        }

        if ($request->getRequestCount() > self::MAX_BULK_REQUESTS) {
            throw PricingCalculatorException::invalidBulkRequest(
                sprintf('Too many requests: %d (max: %d)', $request->getRequestCount(), self::MAX_BULK_REQUESTS)
            );
        }

        $violations = $this->validator->validate($request);
        
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            
            throw PricingCalculatorException::invalidBulkRequest(implode(', ', $errors));
        }
    }

    /**
     * Get and validate carrier for calculation
     */
    private function getValidatedCarrier(string $carrierCode, PriceCalculationRequestDTO $request): \App\Domain\Pricing\Entity\Carrier
    {
        $carrier = $this->carrierRepository->findByCode($carrierCode);
        
        if ($carrier === null) {
            throw PricingException::carrierNotFound($carrierCode);
        }

        if (!$carrier->isActive()) {
            throw PricingCalculatorException::carrierValidationFailed($carrierCode, 'Carrier is not active');
        }

        if (!$carrier->supportsZone($request->zoneCode)) {
            throw PricingException::carrierDoesNotSupportZone($carrierCode, $request->zoneCode);
        }

        if (!$carrier->canHandleWeight($request->weightKg)) {
            throw PricingException::weightExceedsLimit($request->weightKg, $carrier->getMaxWeightKgFloat() ?? 0);
        }

        if (!$carrier->canHandleDimensions(
            $request->dimensionsCm['length'],
            $request->dimensionsCm['width'],
            $request->dimensionsCm['height']
        )) {
            throw PricingException::dimensionsExceedLimit($request->dimensionsCm, $carrier->getMaxDimensionsCm() ?? []);
        }

        return $carrier;
    }

    /**
     * Calculate base price using carrier rate service
     */
    private function calculateBasePrice(
        PriceCalculationRequestDTO $request,
        \App\Domain\Pricing\Entity\Carrier $carrier
    ): PriceCalculationResponseDTO {
        return $this->carrierRateService->calculateBasePrice($request, $carrier);
    }

    /**
     * Add additional services to response
     */
    private function addAdditionalServices(
        PriceCalculationResponseDTO $response,
        PriceCalculationRequestDTO $request,
        \App\Domain\Pricing\Entity\Carrier $carrier
    ): void {
        foreach ($request->additionalServices as $serviceCode) {
            $service = $this->additionalServiceRepository->findByCarrierAndCode($carrier, $serviceCode);
            
            if ($service === null) {
                $this->logger->warning('Additional service not found', [
                    'service_code' => $serviceCode,
                    'carrier_code' => $carrier->getCode(),
                ]);
                continue;
            }

            $servicePrice = $this->carrierRateService->calculateAdditionalServicePrice($service, $request);
            $response->addAdditionalService($serviceCode, $service->getName(), $servicePrice);
        }
    }

    /**
     * Apply customer-specific pricing
     */
    private function applyCustomerPricing(
        PriceCalculationResponseDTO $response,
        PriceCalculationRequestDTO $request
    ): void {
        if ($request->customerId === null) {
            return;
        }
        
        $customerPricing = $this->customerPricingRepository->findActiveByCustomerAndCarrier(
            $request->customerId,
            $request->carrierCode
        );

        if ($customerPricing !== null) {
            $discount = $this->pricingRuleEngine->calculateCustomerDiscount($customerPricing, $response);
            
            if ($discount !== null) {
                $response->applyCustomerDiscount($discount);
                
                $this->logger->info('Customer pricing applied', [
                    'customer_id' => $request->customerId,
                    'carrier_code' => $request->carrierCode,
                    'discount' => $discount,
                ]);
            }
        }
    }

    /**
     * Apply promotional pricing
     */
    private function applyPromotionalPricing(
        PriceCalculationResponseDTO $response,
        PriceCalculationRequestDTO $request
    ): void {
        $promotions = $this->promotionalPricingRepository->findActivePromotions(
            $request->carrierCode,
            $request->customerId
        );

        foreach ($promotions as $promotion) {
            if ($this->pricingRuleEngine->isPromotionApplicable($promotion, $response)) {
                $discountAmount = $this->pricingRuleEngine->calculatePromotionDiscount($promotion, $response);
                $response->applyPromotionalDiscount($discountAmount);
                
                $this->logger->info('Promotion applied', [
                    'promotion_id' => $promotion->getId(),
                    'discount_amount' => $discountAmount,
                    'carrier_code' => $request->carrierCode,
                ]);
                
                break; // Apply only first applicable promotion
            }
        }
    }

    /**
     * Calculate tax for response
     */
    private function calculateTax(PriceCalculationResponseDTO $response): void
    {
        // Default VAT rate in Poland (can be configurable)
        $vatRate = 23.0;
        $response->setTax($vatRate);
    }
}