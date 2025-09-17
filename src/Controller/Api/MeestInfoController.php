<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Courier\Meest\Service\MeestBusinessValidator;
use App\Domain\Courier\Meest\Repository\MeestShipmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API Controller for MEEST information and validation endpoints
 */
#[Route('/v2/api/meest/info', name: 'api_meest_info_')]
class MeestInfoController extends AbstractController
{
    public function __construct(
        private readonly MeestBusinessValidator $businessValidator,
        private readonly MeestShipmentRepository $repository
    ) {}

    /**
     * Get supported countries and their currencies
     */
    #[Route('/countries', name: 'supported_countries', methods: ['GET'])]
    public function getSupportedCountries(): JsonResponse
    {
        $countries = [];
        foreach ($this->businessValidator->getSupportedCountries() as $country) {
            $countries[] = [
                'code' => $country,
                'currency' => $this->businessValidator->getCurrencyForCountry($country)
            ];
        }

        return $this->json([
            'success' => true,
            'data' => [
                'supported_countries' => $countries,
                'total_count' => count($countries)
            ]
        ]);
    }

    /**
     * Validate shipment data without creating it
     */
    #[Route('/validate', name: 'validate_shipment', methods: ['POST'])]
    public function validateShipment(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json([
                    'success' => false,
                    'error' => 'invalid_json',
                    'message' => 'Invalid JSON: ' . json_last_error_msg()
                ], Response::HTTP_BAD_REQUEST);
            }

            // Perform validation
            $this->businessValidator->validateShipmentData($data);

            return $this->json([
                'success' => true,
                'message' => 'Validation passed',
                'data' => [
                    'valid' => true,
                    'destination_country' => $data['recipient']['country'] ?? null,
                    'estimated_currency' => isset($data['recipient']['country'])
                        ? $this->businessValidator->getCurrencyForCountry($data['recipient']['country'])
                        : null
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'validation_failed',
                'message' => $e->getMessage(),
                'data' => [
                    'valid' => false
                ]
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get shipping statistics
     */
    #[Route('/statistics', name: 'get_statistics', methods: ['GET'])]
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $days = min(365, max(1, (int) $request->query->get('days', 30)));
            $from = new \DateTimeImmutable("-{$days} days");

            $statistics = $this->repository->getStatistics($from);
            $deliveryStats = $this->repository->getDeliveryPerformanceStats($from);
            $costs = $this->repository->getTotalCosts($from, new \DateTimeImmutable());

            return $this->json([
                'success' => true,
                'data' => [
                    'period' => [
                        'from' => $from->format('c'),
                        'to' => (new \DateTimeImmutable())->format('c'),
                        'days' => $days
                    ],
                    'shipment_counts' => $statistics,
                    'delivery_performance' => $deliveryStats,
                    'total_costs' => $costs
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'statistics_error',
                'message' => 'Failed to retrieve statistics'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get validation rules and requirements
     */
    #[Route('/requirements', name: 'get_requirements', methods: ['GET'])]
    public function getRequirements(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => [
                'required_fields' => [
                    'sender' => [
                        'first_name', 'last_name', 'phone', 'email',
                        'country', 'city', 'address', 'postal_code'
                    ],
                    'recipient' => [
                        'first_name', 'last_name', 'phone', 'email',
                        'country', 'city', 'address', 'postal_code'
                    ],
                    'parcel' => [
                        'weight', 'length', 'width', 'height', 'contents',
                        'value' => ['localTotalValue', 'localCurrency']
                    ]
                ],
                'optional_fields' => [
                    'sender' => ['company', 'region1'],
                    'recipient' => ['company', 'region1'],
                    'parcel' => ['description'],
                    'shipment' => [
                        'special_instructions', 'reference', 'require_signature',
                        'saturday_delivery', 'delivery_date'
                    ]
                ],
                'limits' => [
                    'max_weight' => '30.0 kg',
                    'max_dimensions' => '120.0 cm per side',
                    'max_value' => '10000.00 per currency',
                    'min_value' => '0.01',
                    'max_reference_length' => 100,
                    'max_instructions_length' => 500,
                    'max_contents_length' => 500
                ],
                'business_rules' => [
                    'High value shipments (>1000) require signature',
                    'Return shipments require original_tracking_number',
                    'Delivery date must be within 30 days',
                    'Currency must match destination country',
                    'Items must have individual values specified'
                ],
                'supported_countries' => $this->businessValidator->getSupportedCountries(),
                'supported_shipment_types' => ['standard', 'express', 'economy', 'return']
            ]
        ]);
    }

    /**
     * Check if specific country is supported
     */
    #[Route('/countries/{countryCode}/check', name: 'check_country', methods: ['GET'])]
    public function checkCountry(string $countryCode): JsonResponse
    {
        $countryCode = strtoupper($countryCode);
        $isSupported = $this->businessValidator->isCountrySupported($countryCode);

        $response = [
            'success' => true,
            'data' => [
                'country_code' => $countryCode,
                'supported' => $isSupported
            ]
        ];

        if ($isSupported) {
            $response['data']['currency'] = $this->businessValidator->getCurrencyForCountry($countryCode);
        } else {
            $response['data']['supported_countries'] = $this->businessValidator->getSupportedCountries();
        }

        return $this->json($response);
    }

    /**
     * Get API health status
     */
    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        try {
            // Check database connectivity
            $recentCount = $this->repository->createQueryBuilder('s')
                ->select('COUNT(s.id)')
                ->where('s.createdAt >= :recent')
                ->setParameter('recent', new \DateTimeImmutable('-1 hour'))
                ->getQuery()
                ->getSingleScalarResult();

            return $this->json([
                'success' => true,
                'data' => [
                    'status' => 'healthy',
                    'timestamp' => (new \DateTimeImmutable())->format('c'),
                    'database' => 'connected',
                    'recent_shipments' => (int) $recentCount,
                    'supported_countries_count' => count($this->businessValidator->getSupportedCountries())
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'data' => [
                    'status' => 'unhealthy',
                    'timestamp' => (new \DateTimeImmutable())->format('c'),
                    'error' => 'Database connection failed'
                ]
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}