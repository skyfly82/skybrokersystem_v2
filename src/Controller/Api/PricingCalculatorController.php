<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Pricing\Contracts\PricingCalculatorInterface;
use App\Domain\Pricing\DTO\PriceCalculationRequestDTO;
use App\Domain\Pricing\DTO\PriceComparisonRequestDTO;
use App\Domain\Pricing\DTO\BulkPriceCalculationRequestDTO;
use App\Domain\Pricing\Exception\PricingException;
use App\Domain\Pricing\Exception\PricingCalculatorException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

/**
 * API Controller for pricing calculations
 */
#[Route('/api/v1/pricing', name: 'api_pricing_')]
class PricingCalculatorController extends AbstractController
{
    public function __construct(
        private readonly PricingCalculatorInterface $pricingCalculator,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Calculate price for a single carrier
     */
    #[Route('/calculate', name: 'calculate', methods: ['POST'])]
    public function calculatePrice(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json([
                    'error' => 'Invalid JSON format',
                    'code' => 'INVALID_JSON'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate required fields
            $requiredFields = ['carrier_code', 'zone_code', 'weight_kg', 'dimensions_cm'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->json([
                        'error' => "Missing required field: {$field}",
                        'code' => 'MISSING_FIELD'
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            $calculationRequest = new PriceCalculationRequestDTO(
                $data['carrier_code'],
                $data['zone_code'],
                (float)$data['weight_kg'],
                $data['dimensions_cm'],
                $data['service_type'] ?? 'standard',
                $data['currency'] ?? 'PLN'
            );

            $calculationRequest->additionalServices = $data['additional_services'] ?? [];
            $calculationRequest->customerId = $data['customer_id'] ?? null;

            $result = $this->pricingCalculator->calculatePrice($calculationRequest);

            $this->logger->info('Price calculation requested via API', [
                'carrier_code' => $data['carrier_code'],
                'zone_code' => $data['zone_code'],
                'weight_kg' => $data['weight_kg'],
                'total_price' => $result->totalPrice,
                'user_agent' => $request->headers->get('User-Agent'),
                'ip' => $request->getClientIp(),
            ]);

            return $this->json([
                'success' => true,
                'data' => $result->toArray()
            ], Response::HTTP_OK);

        } catch (PricingException $e) {
            $this->logger->warning('Pricing calculation failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'context' => $e->getContext(),
            ]);

            return $this->json([
                'error' => $e->getMessage(),
                'code' => 'PRICING_ERROR',
                'context' => $e->getContext()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error in price calculation', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'code' => 'INTERNAL_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Compare prices across all carriers
     */
    #[Route('/compare', name: 'compare', methods: ['POST'])]
    public function compareCarriers(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json([
                    'error' => 'Invalid JSON format',
                    'code' => 'INVALID_JSON'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate required fields
            $requiredFields = ['zone_code', 'weight_kg', 'dimensions_cm'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->json([
                        'error' => "Missing required field: {$field}",
                        'code' => 'MISSING_FIELD'
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            $comparisonRequest = new PriceComparisonRequestDTO(
                $data['zone_code'],
                (float)$data['weight_kg'],
                $data['dimensions_cm'],
                $data['service_type'] ?? 'standard',
                $data['currency'] ?? 'PLN'
            );

            $comparisonRequest->additionalServices = $data['additional_services'] ?? [];
            $comparisonRequest->customerId = $data['customer_id'] ?? null;
            $comparisonRequest->includeCarriers = $data['include_carriers'] ?? [];
            $comparisonRequest->excludeCarriers = $data['exclude_carriers'] ?? [];

            $result = $this->pricingCalculator->compareAllCarriers($comparisonRequest);

            $this->logger->info('Price comparison requested via API', [
                'zone_code' => $data['zone_code'],
                'weight_kg' => $data['weight_kg'],
                'available_carriers' => $result->availableCarriersCount,
                'best_price' => $result->getBestPrice()?->totalPrice,
                'user_agent' => $request->headers->get('User-Agent'),
                'ip' => $request->getClientIp(),
            ]);

            return $this->json([
                'success' => true,
                'data' => $result->toArray()
            ], Response::HTTP_OK);

        } catch (PricingException $e) {
            $this->logger->warning('Price comparison failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'context' => $e->getContext(),
            ]);

            return $this->json([
                'error' => $e->getMessage(),
                'code' => 'PRICING_ERROR',
                'context' => $e->getContext()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error in price comparison', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'code' => 'INTERNAL_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get the best price from all carriers
     */
    #[Route('/best-price', name: 'best_price', methods: ['POST'])]
    public function getBestPrice(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json([
                    'error' => 'Invalid JSON format',
                    'code' => 'INVALID_JSON'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate required fields
            $requiredFields = ['zone_code', 'weight_kg', 'dimensions_cm'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->json([
                        'error' => "Missing required field: {$field}",
                        'code' => 'MISSING_FIELD'
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            $comparisonRequest = new PriceComparisonRequestDTO(
                $data['zone_code'],
                (float)$data['weight_kg'],
                $data['dimensions_cm'],
                $data['service_type'] ?? 'standard',
                $data['currency'] ?? 'PLN'
            );

            $comparisonRequest->additionalServices = $data['additional_services'] ?? [];
            $comparisonRequest->customerId = $data['customer_id'] ?? null;
            $comparisonRequest->includeCarriers = $data['include_carriers'] ?? [];
            $comparisonRequest->excludeCarriers = $data['exclude_carriers'] ?? [];

            $result = $this->pricingCalculator->getBestPrice($comparisonRequest);

            $this->logger->info('Best price requested via API', [
                'zone_code' => $data['zone_code'],
                'weight_kg' => $data['weight_kg'],
                'best_carrier' => $result->carrierCode,
                'best_price' => $result->totalPrice,
                'user_agent' => $request->headers->get('User-Agent'),
                'ip' => $request->getClientIp(),
            ]);

            return $this->json([
                'success' => true,
                'data' => $result->toArray()
            ], Response::HTTP_OK);

        } catch (PricingException $e) {
            $this->logger->warning('Best price calculation failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'context' => $e->getContext(),
            ]);

            return $this->json([
                'error' => $e->getMessage(),
                'code' => 'PRICING_ERROR',
                'context' => $e->getContext()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error in best price calculation', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'code' => 'INTERNAL_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Calculate bulk pricing
     */
    #[Route('/bulk', name: 'bulk', methods: ['POST'])]
    public function calculateBulkPricing(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json([
                    'error' => 'Invalid JSON format',
                    'code' => 'INVALID_JSON'
                ], Response::HTTP_BAD_REQUEST);
            }

            if (!isset($data['requests']) || !is_array($data['requests'])) {
                return $this->json([
                    'error' => 'Missing or invalid "requests" field',
                    'code' => 'MISSING_REQUESTS'
                ], Response::HTTP_BAD_REQUEST);
            }

            $requests = [];
            foreach ($data['requests'] as $index => $requestData) {
                // Validate each request
                $requiredFields = ['carrier_code', 'zone_code', 'weight_kg', 'dimensions_cm'];
                foreach ($requiredFields as $field) {
                    if (!isset($requestData[$field])) {
                        return $this->json([
                            'error' => "Missing required field '{$field}' in request {$index}",
                            'code' => 'MISSING_FIELD'
                        ], Response::HTTP_BAD_REQUEST);
                    }
                }

                $calculationRequest = new PriceCalculationRequestDTO(
                    $requestData['carrier_code'],
                    $requestData['zone_code'],
                    (float)$requestData['weight_kg'],
                    $requestData['dimensions_cm'],
                    $requestData['service_type'] ?? 'standard',
                    $requestData['currency'] ?? 'PLN'
                );

                $calculationRequest->additionalServices = $requestData['additional_services'] ?? [];
                $calculationRequest->customerId = $requestData['customer_id'] ?? null;

                $requests[] = $calculationRequest;
            }

            $bulkRequest = new BulkPriceCalculationRequestDTO($requests, $data['currency'] ?? 'PLN');
            $bulkRequest->customerId = $data['customer_id'] ?? null;
            $bulkRequest->stopOnFirstError = $data['stop_on_first_error'] ?? false;

            // Set bulk discount if provided
            if (isset($data['bulk_discount'])) {
                $bulkRequest->setBulkDiscount(
                    $data['bulk_discount']['threshold'] ?? 10,
                    $data['bulk_discount']['percentage'] ?? 5.0
                );
            }

            $result = $this->pricingCalculator->calculateBulk($bulkRequest);

            $this->logger->info('Bulk pricing requested via API', [
                'total_requests' => count($requests),
                'successful' => $result->successfulCalculations,
                'failed' => $result->failedCalculations,
                'total_amount' => $result->totalAmount,
                'user_agent' => $request->headers->get('User-Agent'),
                'ip' => $request->getClientIp(),
            ]);

            $responseCode = $result->failedCalculations > 0 ? 
                Response::HTTP_PARTIAL_CONTENT : 
                Response::HTTP_OK;

            return $this->json([
                'success' => $result->successfulCalculations > 0,
                'data' => $result->toArray()
            ], $responseCode);

        } catch (PricingCalculatorException $e) {
            $this->logger->warning('Bulk pricing calculation failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'context' => $e->getContext(),
            ]);

            return $this->json([
                'error' => $e->getMessage(),
                'code' => 'PRICING_CALCULATOR_ERROR',
                'context' => $e->getContext()
            ], Response::HTTP_BAD_REQUEST);

        } catch (PricingException $e) {
            $this->logger->warning('Bulk pricing calculation failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'context' => $e->getContext(),
            ]);

            return $this->json([
                'error' => $e->getMessage(),
                'code' => 'PRICING_ERROR',
                'context' => $e->getContext()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error in bulk pricing calculation', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'code' => 'INTERNAL_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get available carriers for specific parameters
     */
    #[Route('/carriers/available', name: 'carriers_available', methods: ['GET'])]
    public function getAvailableCarriers(Request $request): JsonResponse
    {
        try {
            $zoneCode = $request->query->get('zone_code');
            $weightKg = $request->query->get('weight_kg');
            $length = $request->query->get('length');
            $width = $request->query->get('width');
            $height = $request->query->get('height');

            if (!$zoneCode || !$weightKg || !$length || !$width || !$height) {
                return $this->json([
                    'error' => 'Missing required parameters: zone_code, weight_kg, length, width, height',
                    'code' => 'MISSING_PARAMETERS'
                ], Response::HTTP_BAD_REQUEST);
            }

            $carriers = $this->pricingCalculator->getAvailableCarriers(
                $zoneCode,
                (float)$weightKg,
                [
                    'length' => (int)$length,
                    'width' => (int)$width,
                    'height' => (int)$height,
                ]
            );

            $carrierData = array_map(function ($carrier) {
                return [
                    'code' => $carrier->getCode(),
                    'name' => $carrier->getName(),
                    'logo_url' => $carrier->getLogoUrl(),
                    'max_weight_kg' => $carrier->getMaxWeightKgFloat(),
                    'max_dimensions_cm' => $carrier->getMaxDimensionsCm(),
                    'default_service_type' => $carrier->getDefaultServiceType(),
                ];
            }, $carriers);

            return $this->json([
                'success' => true,
                'data' => [
                    'carriers' => $carrierData,
                    'count' => count($carrierData),
                    'zone_code' => $zoneCode,
                    'parameters' => [
                        'weight_kg' => (float)$weightKg,
                        'dimensions_cm' => [
                            'length' => (int)$length,
                            'width' => (int)$width,
                            'height' => (int)$height,
                        ]
                    ]
                ]
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            $this->logger->error('Error fetching available carriers', [
                'error' => $e->getMessage(),
                'zone_code' => $request->query->get('zone_code'),
                'weight_kg' => $request->query->get('weight_kg'),
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'code' => 'INTERNAL_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate if carrier can handle specific shipment
     */
    #[Route('/carriers/{carrierCode}/validate', name: 'carrier_validate', methods: ['POST'])]
    public function validateCarrier(string $carrierCode, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json([
                    'error' => 'Invalid JSON format',
                    'code' => 'INVALID_JSON'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate required fields
            $requiredFields = ['zone_code', 'weight_kg', 'dimensions_cm'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->json([
                        'error' => "Missing required field: {$field}",
                        'code' => 'MISSING_FIELD'
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            $validationRequest = new PriceCalculationRequestDTO(
                $carrierCode,
                $data['zone_code'],
                (float)$data['weight_kg'],
                $data['dimensions_cm'],
                $data['service_type'] ?? 'standard',
                $data['currency'] ?? 'PLN'
            );

            $canHandle = $this->pricingCalculator->canCarrierHandle($carrierCode, $validationRequest);

            return $this->json([
                'success' => true,
                'data' => [
                    'carrier_code' => $carrierCode,
                    'can_handle' => $canHandle,
                    'parameters' => [
                        'zone_code' => $data['zone_code'],
                        'weight_kg' => $data['weight_kg'],
                        'dimensions_cm' => $data['dimensions_cm'],
                    ]
                ]
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            $this->logger->error('Error validating carrier', [
                'carrier_code' => $carrierCode,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'code' => 'INTERNAL_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}