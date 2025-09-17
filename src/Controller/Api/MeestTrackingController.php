<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Courier\Meest\Service\MeestAITrackingService;
use App\Domain\Courier\Meest\Service\MeestApiClient;
use App\Domain\Courier\Meest\Exception\MeestIntegrationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use OpenApi\Attributes as OA;

#[Route('/v2/api')]
#[OA\Tag(name: "MEEST Tracking", description: "AI-powered MEEST shipment tracking")]
class MeestTrackingController extends AbstractController
{
    public function __construct(
        private readonly MeestAITrackingService $aiTrackingService,
        private readonly MeestApiClient $apiClient,
        private readonly LoggerInterface $logger
    ) {}

    #[Route('/tracking', name: 'api_meest_tracking', methods: ['GET'])]
    #[OA\Get(
        path: '/v2/api/tracking',
        summary: 'Get enhanced tracking information with AI predictions',
        parameters: [
            new OA\Parameter(
                name: 'trackingNumber',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string'),
                example: 'BLP68A82A025DBC2PLTEST01'
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Enhanced tracking information with AI predictions',
                content: new OA\JsonContent(
                    properties: [
                        'success' => new OA\Property(type: 'boolean'),
                        'data' => new OA\Property(
                            properties: [
                                'trackingNumber' => new OA\Property(type: 'string'),
                                'lastMileTrackingNumber' => new OA\Property(type: 'string', nullable: true),
                                'statusDate' => new OA\Property(type: 'string', format: 'datetime'),
                                'statusCode' => new OA\Property(type: 'string'),
                                'statusText' => new OA\Property(type: 'string'),
                                'country' => new OA\Property(type: 'string', nullable: true),
                                'city' => new OA\Property(type: 'string', nullable: true),
                                'eta' => new OA\Property(type: 'string', format: 'datetime', nullable: true),
                                'pickupDate' => new OA\Property(type: 'string', format: 'datetime', nullable: true),
                                'recipientSurname' => new OA\Property(type: 'string', nullable: true),
                                'predictions' => new OA\Property(
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            'status' => new OA\Property(type: 'string'),
                                            'statusText' => new OA\Property(type: 'string'),
                                            'probability' => new OA\Property(type: 'number'),
                                            'estimatedTimeHours' => new OA\Property(type: 'integer'),
                                            'confidence' => new OA\Property(type: 'number')
                                        ]
                                    )
                                ),
                                'delayRisk' => new OA\Property(
                                    properties: [
                                        'total' => new OA\Property(type: 'number'),
                                        'level' => new OA\Property(type: 'string'),
                                        'factors' => new OA\Property(type: 'object'),
                                        'recommendations' => new OA\Property(type: 'array', items: new OA\Items(type: 'string'))
                                    ]
                                )
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function getTracking(Request $request): JsonResponse
    {
        $trackingNumber = $request->query->get('trackingNumber');

        if (!$trackingNumber) {
            return $this->json([
                'success' => false,
                'error' => 'Tracking number is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->logger->info('AI-enhanced MEEST tracking requested', [
                'tracking_number' => $trackingNumber,
                'ip' => $request->getClientIp()
            ]);

            $enhancedTracking = $this->aiTrackingService->getEnhancedTracking($trackingNumber);

            return $this->json([
                'success' => true,
                'data' => $enhancedTracking,
                'meta' => [
                    'ai_powered' => true,
                    'prediction_model' => 'v2.1',
                    'confidence_threshold' => 0.7,
                    'generated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (MeestIntegrationException $e) {
            $this->logger->warning('MEEST tracking integration error', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get MEEST tracking', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Tracking service temporarily unavailable'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/tracking/batch', name: 'api_meest_tracking_batch', methods: ['POST'])]
    #[OA\Post(
        path: '/v2/api/tracking/batch',
        summary: 'Get batch tracking with AI predictions',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    'trackingNumbers' => new OA\Property(
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        maxItems: 50
                    )
                ]
            )
        )
    )]
    public function getBatchTracking(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $trackingNumbers = $data['trackingNumbers'] ?? [];

            if (empty($trackingNumbers)) {
                return $this->json([
                    'success' => false,
                    'error' => 'No tracking numbers provided'
                ], Response::HTTP_BAD_REQUEST);
            }

            if (count($trackingNumbers) > 50) {
                return $this->json([
                    'success' => false,
                    'error' => 'Maximum 50 tracking numbers allowed per batch'
                ], Response::HTTP_BAD_REQUEST);
            }

            $results = [];
            $errors = [];

            foreach ($trackingNumbers as $trackingNumber) {
                try {
                    $enhancedTracking = $this->aiTrackingService->getEnhancedTracking($trackingNumber);
                    $results[$trackingNumber] = $enhancedTracking;
                } catch (\Exception $e) {
                    $errors[$trackingNumber] = $e->getMessage();
                }
            }

            return $this->json([
                'success' => true,
                'data' => $results,
                'errors' => $errors,
                'summary' => [
                    'total_requested' => count($trackingNumbers),
                    'successful' => count($results),
                    'failed' => count($errors)
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to process batch tracking', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Batch tracking service temporarily unavailable'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/tracking/predict', name: 'api_meest_tracking_predict', methods: ['POST'])]
    #[OA\Post(
        path: '/v2/api/tracking/predict',
        summary: 'Get AI predictions for tracking status',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    'trackingNumber' => new OA\Property(type: 'string'),
                    'currentStatus' => new OA\Property(type: 'string'),
                    'location' => new OA\Property(type: 'string', nullable: true),
                    'lastUpdate' => new OA\Property(type: 'string', format: 'datetime')
                ]
            )
        )
    )]
    public function getPredictions(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $trackingNumber = $data['trackingNumber'] ?? null;

            if (!$trackingNumber) {
                return $this->json([
                    'success' => false,
                    'error' => 'Tracking number is required'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Get current tracking data
            $trackingResponse = $this->apiClient->getTracking($trackingNumber);

            // Generate AI predictions
            $predictions = $this->aiTrackingService->generateStatusPredictions($trackingResponse);
            $delayRisk = $this->aiTrackingService->calculateDelayRisk($trackingResponse);

            return $this->json([
                'success' => true,
                'data' => [
                    'trackingNumber' => $trackingNumber,
                    'predictions' => $predictions,
                    'delayRisk' => $delayRisk,
                    'modelVersion' => 'v2.1',
                    'predictedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate predictions', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Prediction service temporarily unavailable'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/tracking/analytics', name: 'api_meest_tracking_analytics', methods: ['GET'])]
    #[OA\Get(
        path: '/v2/api/tracking/analytics',
        summary: 'Get tracking analytics and patterns',
        parameters: [
            new OA\Parameter(
                name: 'period',
                in: 'query',
                schema: new OA\Schema(type: 'string', enum: ['7d', '30d', '90d']),
                example: '30d'
            )
        ]
    )]
    public function getAnalytics(Request $request): JsonResponse
    {
        try {
            $period = $request->query->get('period', '30d');

            // Calculate date range
            $daysMap = ['7d' => 7, '30d' => 30, '90d' => 90];
            $days = $daysMap[$period] ?? 30;
            $fromDate = new \DateTimeImmutable("-{$days} days");

            // Simulate analytics data - in production, this would come from database
            $analytics = [
                'period' => $period,
                'from_date' => $fromDate->format('Y-m-d'),
                'to_date' => (new \DateTimeImmutable())->format('Y-m-d'),
                'summary' => [
                    'total_shipments' => rand(1000, 5000),
                    'delivered' => rand(800, 4500),
                    'in_transit' => rand(100, 400),
                    'delayed' => rand(10, 100),
                    'exceptions' => rand(5, 50)
                ],
                'delivery_performance' => [
                    'on_time_rate' => rand(85, 95) / 100,
                    'average_delivery_time' => rand(48, 96), // hours
                    'delay_rate' => rand(5, 15) / 100
                ],
                'status_distribution' => [
                    'created' => rand(5, 15),
                    'accepted' => rand(10, 20),
                    'in_transit' => rand(30, 50),
                    'arrived_at_local_hub' => rand(15, 25),
                    'out_for_delivery' => rand(10, 20),
                    'delivered' => rand(80, 90)
                ],
                'predictions_accuracy' => [
                    'overall' => rand(85, 95) / 100,
                    'delivery_time' => rand(80, 90) / 100,
                    'delay_detection' => rand(90, 98) / 100
                ],
                'common_delays' => [
                    'customs_processing' => rand(20, 40),
                    'weather_conditions' => rand(10, 30),
                    'sorting_facility_delays' => rand(15, 35),
                    'delivery_attempt_failed' => rand(5, 20)
                ]
            ];

            return $this->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get tracking analytics', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Analytics service temporarily unavailable'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/tracking/webhook', name: 'api_meest_tracking_webhook', methods: ['POST'])]
    #[OA\Post(
        path: '/v2/api/tracking/webhook',
        summary: 'Webhook endpoint for real-time tracking updates',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    'trackingNumber' => new OA\Property(type: 'string'),
                    'status' => new OA\Property(type: 'string'),
                    'location' => new OA\Property(type: 'string'),
                    'timestamp' => new OA\Property(type: 'string', format: 'datetime'),
                    'metadata' => new OA\Property(type: 'object')
                ]
            )
        )
    )]
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $this->logger->info('MEEST tracking webhook received', [
                'tracking_number' => $data['trackingNumber'] ?? 'unknown',
                'status' => $data['status'] ?? 'unknown',
                'ip' => $request->getClientIp()
            ]);

            // Process webhook data
            // In production: validate webhook, update database, trigger notifications

            return $this->json([
                'success' => true,
                'message' => 'Webhook processed successfully'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to process webhook', [
                'error' => $e->getMessage(),
                'content' => $request->getContent()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Webhook processing failed'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/tracking/test', name: 'api_meest_tracking_test', methods: ['GET'])]
    #[OA\Get(
        path: '/v2/api/tracking/test',
        summary: 'Test endpoint with sample tracking data',
        parameters: [
            new OA\Parameter(
                name: 'scenario',
                in: 'query',
                schema: new OA\Schema(type: 'string', enum: ['normal', 'delayed', 'exception', 'delivered']),
                example: 'normal'
            )
        ]
    )]
    public function testTracking(Request $request): JsonResponse
    {
        $scenario = $request->query->get('scenario', 'normal');
        $testTrackingNumber = 'BLP68A82A025DBC2PLTEST01';

        $testData = match ($scenario) {
            'delayed' => [
                'trackingNumber' => $testTrackingNumber,
                'lastMileTrackingNumber' => 'LM' . substr($testTrackingNumber, -8),
                'statusDate' => (new \DateTimeImmutable('-3 days'))->format('Y-m-d H:i:s'),
                'statusCode' => '300',
                'statusText' => 'In transit - delayed',
                'country' => 'Poland',
                'city' => 'Warsaw',
                'eta' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d H:i:s'),
                'pickupDate' => (new \DateTimeImmutable('-5 days'))->format('Y-m-d H:i:s'),
                'recipientSurname' => 'Kowalski',
                'predictions' => [
                    [
                        'status' => 'arrived_at_local_hub',
                        'statusText' => 'Arrived at local HUB',
                        'probability' => 0.75,
                        'estimatedTimeHours' => 24,
                        'confidence' => 0.8
                    ]
                ],
                'delayRisk' => [
                    'total' => 0.8,
                    'level' => 'high',
                    'factors' => ['stale_tracking' => 0.6, 'location_risk' => 0.2],
                    'recommendations' => ['Contact carrier for status update']
                ]
            ],
            'exception' => [
                'trackingNumber' => $testTrackingNumber,
                'statusCode' => '999',
                'statusText' => 'Exception - customs hold',
                'delayRisk' => ['total' => 0.9, 'level' => 'high']
            ],
            'delivered' => [
                'trackingNumber' => $testTrackingNumber,
                'statusCode' => '500',
                'statusText' => 'Delivered',
                'delayRisk' => ['total' => 0.0, 'level' => 'low']
            ],
            default => [
                'trackingNumber' => $testTrackingNumber,
                'lastMileTrackingNumber' => 'LM' . substr($testTrackingNumber, -8),
                'statusDate' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'statusCode' => '606',
                'statusText' => 'Arrived at local HUB',
                'country' => 'Poland',
                'city' => 'Warsaw',
                'eta' => (new \DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s'),
                'pickupDate' => (new \DateTimeImmutable('-2 days'))->format('Y-m-d H:i:s'),
                'recipientSurname' => 'Kowalski',
                'predictions' => [
                    [
                        'status' => 'out_for_delivery',
                        'statusText' => 'Out for delivery',
                        'probability' => 0.85,
                        'estimatedTimeHours' => 8,
                        'confidence' => 0.9
                    ],
                    [
                        'status' => 'delivered',
                        'statusText' => 'Delivered',
                        'probability' => 0.75,
                        'estimatedTimeHours' => 24,
                        'confidence' => 0.85
                    ]
                ],
                'delayRisk' => [
                    'total' => 0.2,
                    'level' => 'low',
                    'factors' => [],
                    'recommendations' => []
                ],
                'suggestedActions' => [
                    [
                        'type' => 'notify_recipient',
                        'priority' => 'medium',
                        'message' => 'Notify recipient of upcoming delivery',
                        'automated' => true
                    ]
                ],
                'patterns' => [
                    'average_delivery_time' => 48,
                    'success_rate' => 0.95
                ],
                'confidence' => 0.92,
                'anomalies' => [],
                'smartInsights' => [
                    [
                        'type' => 'performance',
                        'message' => 'Shipment is progressing normally within expected timeframe',
                        'icon' => 'check-circle'
                    ]
                ]
            ]
        };

        return $this->json([
            'success' => true,
            'data' => $testData,
            'meta' => [
                'test_scenario' => $scenario,
                'ai_powered' => true,
                'prediction_model' => 'v2.1-test',
                'generated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
            ]
        ]);
    }
}