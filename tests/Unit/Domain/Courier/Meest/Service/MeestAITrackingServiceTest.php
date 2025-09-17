<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Courier\Meest\Service;

use App\Domain\Courier\Meest\DTO\MeestTrackingResponseDTO;
use App\Domain\Courier\Meest\Enum\MeestTrackingStatus;
use App\Domain\Courier\Meest\Repository\MeestShipmentRepository;
use App\Domain\Courier\Meest\Service\MeestAITrackingService;
use App\Domain\Courier\Meest\Service\MeestApiClient;
use App\Domain\Courier\Meest\Service\MeestStatusMapper;
use App\Domain\Courier\Meest\Service\MeestTrackingService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Unit Tests for MeestAITrackingService
 *
 * Tests cover AI/ML features including:
 * - Enhanced tracking with AI predictions
 * - Status prediction algorithms
 * - Delay risk assessment using ML patterns
 * - Smart ETA calculations
 * - Anomaly detection
 * - Route optimization suggestions
 * - Performance analytics
 */
class MeestAITrackingServiceTest extends TestCase
{
    private MeestApiClient|MockObject $apiClient;
    private MeestTrackingService|MockObject $trackingService;
    private MeestStatusMapper|MockObject $statusMapper;
    private MeestShipmentRepository|MockObject $repository;
    private LoggerInterface|MockObject $logger;
    private CacheInterface|MockObject $cache;
    private MeestAITrackingService $aiTrackingService;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(MeestApiClient::class);
        $this->trackingService = $this->createMock(MeestTrackingService::class);
        $this->statusMapper = $this->createMock(MeestStatusMapper::class);
        $this->repository = $this->createMock(MeestShipmentRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);

        $this->aiTrackingService = new MeestAITrackingService(
            $this->apiClient,
            $this->trackingService,
            $this->statusMapper,
            $this->repository,
            $this->logger,
            $this->cache
        );
    }

    /**
     * Test enhanced tracking with AI predictions for test package
     */
    public function testGetEnhancedTrackingForTestPackage(): void
    {
        $trackingNumber = 'BLP68A82A025DBC2PLTEST01';

        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: $trackingNumber,
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable('2024-01-10T14:30:00Z'),
            estimatedDelivery: new \DateTimeImmutable('2024-01-15T10:00:00Z'),
            events: [
                [
                    'timestamp' => new \DateTimeImmutable('2024-01-10T14:30:00Z'),
                    'status' => 'in_transit',
                    'location' => 'Warsaw Distribution Center',
                    'description' => 'Package is in transit'
                ],
                [
                    'timestamp' => new \DateTimeImmutable('2024-01-10T09:15:00Z'),
                    'status' => 'collected',
                    'location' => 'Warsaw',
                    'description' => 'Package collected from sender'
                ]
            ],
            currentLocation: 'Warsaw Distribution Center'
        );

        $this->apiClient
            ->expects($this->once())
            ->method('getTracking')
            ->with($trackingNumber)
            ->willReturn($trackingResponse);

        $this->statusMapper
            ->expects($this->once())
            ->method('getSuggestedNextStatuses')
            ->with(MeestTrackingStatus::IN_TRANSIT)
            ->willReturn([
                MeestTrackingStatus::ARRIVED_AT_LOCAL_HUB,
                MeestTrackingStatus::OUT_FOR_DELIVERY,
                MeestTrackingStatus::DELIVERED
            ]);

        $result = $this->aiTrackingService->getEnhancedTracking($trackingNumber);

        $this->assertIsArray($result);
        $this->assertEquals($trackingNumber, $result['trackingNumber']);
        $this->assertEquals('606', $result['statusCode']); // Should map to appropriate code
        $this->assertArrayHasKey('predictions', $result);
        $this->assertArrayHasKey('delayRisk', $result);
        $this->assertArrayHasKey('suggestedActions', $result);
        $this->assertArrayHasKey('patterns', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('anomalies', $result);
        $this->assertArrayHasKey('smartInsights', $result);
        $this->assertArrayHasKey('eta', $result);
    }

    /**
     * Test AI status predictions generation
     */
    public function testGenerateStatusPredictions(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Warsaw Distribution Center'
        );

        $nextStatuses = [
            MeestTrackingStatus::ARRIVED_AT_LOCAL_HUB,
            MeestTrackingStatus::OUT_FOR_DELIVERY,
            MeestTrackingStatus::DELIVERED
        ];

        $this->statusMapper
            ->expects($this->once())
            ->method('getSuggestedNextStatuses')
            ->with(MeestTrackingStatus::IN_TRANSIT)
            ->willReturn($nextStatuses);

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                return $callback();
            });

        $predictions = $this->aiTrackingService->generateStatusPredictions($trackingResponse);

        $this->assertIsArray($predictions);
        $this->assertLessThanOrEqual(5, count($predictions)); // Top 5 predictions

        foreach ($predictions as $prediction) {
            $this->assertArrayHasKey('status', $prediction);
            $this->assertArrayHasKey('statusText', $prediction);
            $this->assertArrayHasKey('probability', $prediction);
            $this->assertArrayHasKey('estimatedTimeHours', $prediction);
            $this->assertArrayHasKey('confidence', $prediction);

            $this->assertIsFloat($prediction['probability']);
            $this->assertGreaterThanOrEqual(0, $prediction['probability']);
            $this->assertLessThanOrEqual(1, $prediction['probability']);
        }
    }

    /**
     * Test delay risk calculation for high-risk scenarios
     */
    public function testCalculateDelayRiskHighRisk(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::CUSTOMS_HELD,
            lastUpdate: new \DateTimeImmutable('-5 days'), // Stale tracking
            events: [],
            currentLocation: 'Customs inspection facility'
        );

        $delayRisk = $this->aiTrackingService->calculateDelayRisk($trackingResponse);

        $this->assertIsArray($delayRisk);
        $this->assertArrayHasKey('total', $delayRisk);
        $this->assertArrayHasKey('level', $delayRisk);
        $this->assertArrayHasKey('factors', $delayRisk);
        $this->assertArrayHasKey('recommendations', $delayRisk);

        $this->assertIsFloat($delayRisk['total']);
        $this->assertGreaterThan(0.5, $delayRisk['total']); // Should be high risk
        $this->assertEquals('high', $delayRisk['level']);

        $this->assertArrayHasKey('stale_tracking', $delayRisk['factors']);
        $this->assertArrayHasKey('status_issue', $delayRisk['factors']);
        $this->assertArrayHasKey('location_risk', $delayRisk['factors']);
    }

    /**
     * Test delay risk calculation for low-risk scenarios
     */
    public function testCalculateDelayRiskLowRisk(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::OUT_FOR_DELIVERY,
            lastUpdate: new \DateTimeImmutable('-1 hour'), // Recent update
            events: [],
            currentLocation: 'Local delivery hub'
        );

        $delayRisk = $this->aiTrackingService->calculateDelayRisk($trackingResponse);

        $this->assertIsArray($delayRisk);
        $this->assertLessThan(0.3, $delayRisk['total']); // Should be low risk
        $this->assertEquals('low', $delayRisk['level']);
    }

    /**
     * Test suggested actions generation for delivery attempt
     */
    public function testGenerateSuggestedActionsDeliveryAttempt(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::DELIVERY_ATTEMPT,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Local delivery hub'
        );

        $actions = $this->aiTrackingService->generateSuggestedActions($trackingResponse);

        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);

        // Should suggest contacting recipient
        $contactAction = array_filter($actions, function($action) {
            return $action['type'] === 'contact_recipient';
        });

        $this->assertNotEmpty($contactAction);
        $contactAction = reset($contactAction);
        $this->assertEquals('high', $contactAction['priority']);
        $this->assertFalse($contactAction['automated']);
    }

    /**
     * Test suggested actions for customs held status
     */
    public function testGenerateSuggestedActionsCustomsHeld(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::CUSTOMS_HELD,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Customs facility'
        );

        $actions = $this->aiTrackingService->generateSuggestedActions($trackingResponse);

        $this->assertIsArray($actions);

        // Should suggest customs assistance
        $customsAction = array_filter($actions, function($action) {
            return $action['type'] === 'customs_assistance';
        });

        $this->assertNotEmpty($customsAction);
        $customsAction = reset($customsAction);
        $this->assertEquals('high', $customsAction['priority']);
    }

    /**
     * Test suggested actions for out for delivery status
     */
    public function testGenerateSuggestedActionsOutForDelivery(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::OUT_FOR_DELIVERY,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Local delivery vehicle'
        );

        $actions = $this->aiTrackingService->generateSuggestedActions($trackingResponse);

        $this->assertIsArray($actions);

        // Should suggest notifying recipient
        $notifyAction = array_filter($actions, function($action) {
            return $action['type'] === 'notify_recipient';
        });

        $this->assertNotEmpty($notifyAction);
        $notifyAction = reset($notifyAction);
        $this->assertEquals('medium', $notifyAction['priority']);
        $this->assertTrue($notifyAction['automated']);
    }

    /**
     * Test suggested actions for stale tracking
     */
    public function testGenerateSuggestedActionsStaleTracking(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable('-4 days'), // Stale
            events: [],
            currentLocation: 'Unknown'
        );

        $actions = $this->aiTrackingService->generateSuggestedActions($trackingResponse);

        $this->assertIsArray($actions);

        // Should suggest requesting update
        $updateAction = array_filter($actions, function($action) {
            return $action['type'] === 'request_update';
        });

        $this->assertNotEmpty($updateAction);
        $updateAction = reset($updateAction);
        $this->assertEquals('medium', $updateAction['priority']);
        $this->assertTrue($updateAction['automated']);
    }

    /**
     * Test delivery patterns analysis
     */
    public function testAnalyzeDeliveryPatterns(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Warsaw Distribution Center'
        );

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                return $callback();
            });

        $patterns = $this->aiTrackingService->analyzeDeliveryPatterns($trackingResponse);

        $this->assertIsArray($patterns);

        if (isset($patterns['pattern']) && $patterns['pattern'] === 'insufficient_data') {
            $this->assertEquals(0, $patterns['confidence']);
        } else {
            $this->assertArrayHasKey('average_delivery_time', $patterns);
            $this->assertArrayHasKey('common_delays', $patterns);
            $this->assertArrayHasKey('best_delivery_days', $patterns);
            $this->assertArrayHasKey('route_efficiency', $patterns);
            $this->assertArrayHasKey('success_rate', $patterns);
        }
    }

    /**
     * Test smart ETA calculation with original ETA
     */
    public function testCalculateSmartETAWithOriginalETA(): void
    {
        $originalETA = new \DateTimeImmutable('+2 days');
        $patterns = ['route_efficiency' => 0.85];

        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            estimatedDelivery: $originalETA,
            events: [],
            currentLocation: 'Transit'
        );

        $smartETA = $this->aiTrackingService->calculateSmartETA($trackingResponse, $patterns);

        $this->assertIsString($smartETA);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $smartETA);
    }

    /**
     * Test smart ETA calculation without original ETA
     */
    public function testCalculateSmartETAWithoutOriginalETA(): void
    {
        $patterns = ['route_efficiency' => 0.85];

        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::OUT_FOR_DELIVERY,
            lastUpdate: new \DateTimeImmutable(),
            estimatedDelivery: null,
            events: [],
            currentLocation: 'Local delivery hub'
        );

        $smartETA = $this->aiTrackingService->calculateSmartETA($trackingResponse, $patterns);

        $this->assertIsString($smartETA);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $smartETA);

        // Should be relatively soon for out for delivery
        $etaTime = new \DateTimeImmutable($smartETA);
        $now = new \DateTimeImmutable();
        $diffHours = $etaTime->diff($now)->h;
        $this->assertLessThan(12, $diffHours);
    }

    /**
     * Test enhanced tracking with caching
     */
    public function testEnhancedTrackingWithCaching(): void
    {
        $trackingNumber = 'BLP68A82A025DBC2PLTEST01';

        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: $trackingNumber,
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Warsaw'
        );

        $this->apiClient
            ->expects($this->once())
            ->method('getTracking')
            ->willReturn($trackingResponse);

        $this->statusMapper
            ->expects($this->once())
            ->method('getSuggestedNextStatuses')
            ->willReturn([MeestTrackingStatus::DELIVERED]);

        // Mock cache behavior for predictions
        $this->cache
            ->expects($this->atLeastOnce())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                if (str_contains($key, 'predictions_')) {
                    return [
                        [
                            'status' => 'delivered',
                            'statusText' => 'Delivered',
                            'probability' => 0.8,
                            'estimatedTimeHours' => 12,
                            'confidence' => 0.85
                        ]
                    ];
                }
                return $callback();
            });

        $result = $this->aiTrackingService->getEnhancedTracking($trackingNumber);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('predictions', $result);
        $this->assertNotEmpty($result['predictions']);
    }

    /**
     * Test error handling in enhanced tracking
     */
    public function testEnhancedTrackingErrorHandling(): void
    {
        $trackingNumber = 'BLP68A82A025DBC2PLTEST01';

        $this->apiClient
            ->expects($this->once())
            ->method('getTracking')
            ->willThrowException(new \Exception('API Error'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to get enhanced tracking', [
                'tracking_number' => $trackingNumber,
                'error' => 'API Error'
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API Error');

        $this->aiTrackingService->getEnhancedTracking($trackingNumber);
    }

    /**
     * Test confidence score calculation
     */
    public function testConfidenceScoreCalculation(): void
    {
        // Recent update with many events should have high confidence
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable('-1 hour'),
            events: array_fill(0, 15, ['timestamp' => new \DateTimeImmutable(), 'status' => 'test']),
            currentLocation: 'Warsaw'
        );

        $this->apiClient
            ->expects($this->once())
            ->method('getTracking')
            ->willReturn($trackingResponse);

        $this->statusMapper
            ->expects($this->once())
            ->method('getSuggestedNextStatuses')
            ->willReturn([]);

        $result = $this->aiTrackingService->getEnhancedTracking('BLP68A82A025DBC2PLTEST01');

        $this->assertGreaterThan(0.7, $result['confidence']);
        $this->assertLessThanOrEqual(1.0, $result['confidence']);
    }

    /**
     * Test anomaly detection
     */
    public function testAnomalyDetection(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            events: [
                [
                    'timestamp' => new \DateTimeImmutable('-2 hours'),
                    'status' => 'delivered',
                    'description' => 'Delivered'
                ],
                [
                    'timestamp' => new \DateTimeImmutable('-1 hour'),
                    'status' => 'in_transit',
                    'description' => 'Back in transit' // Anomaly!
                ]
            ],
            currentLocation: 'Unknown location'
        );

        $this->statusMapper
            ->expects($this->atLeastOnce())
            ->method('isValidTransition')
            ->willReturn(false); // Invalid transition

        $this->apiClient
            ->expects($this->once())
            ->method('getTracking')
            ->willReturn($trackingResponse);

        $this->statusMapper
            ->expects($this->once())
            ->method('getSuggestedNextStatuses')
            ->willReturn([]);

        $result = $this->aiTrackingService->getEnhancedTracking('BLP68A82A025DBC2PLTEST01');

        $this->assertArrayHasKey('anomalies', $result);
        $this->assertIsArray($result['anomalies']);
    }

    /**
     * Test smart insights generation
     */
    public function testSmartInsightsGeneration(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Warsaw'
        );

        $this->apiClient
            ->expects($this->once())
            ->method('getTracking')
            ->willReturn($trackingResponse);

        $this->statusMapper
            ->expects($this->once())
            ->method('getSuggestedNextStatuses')
            ->willReturn([]);

        $result = $this->aiTrackingService->getEnhancedTracking('BLP68A82A025DBC2PLTEST01');

        $this->assertArrayHasKey('smartInsights', $result);
        $this->assertIsArray($result['smartInsights']);

        // Should have performance insight for in_transit status
        $performanceInsight = array_filter($result['smartInsights'], function($insight) {
            return $insight['type'] === 'performance';
        });

        $this->assertNotEmpty($performanceInsight);
    }

    /**
     * Test weekend risk factor detection
     */
    public function testWeekendRiskFactorDetection(): void
    {
        // Create a mock for weekend detection
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Transit'
        );

        $delayRisk = $this->aiTrackingService->calculateDelayRisk($trackingResponse);

        $this->assertIsArray($delayRisk);

        // If it's weekend, should have weekend_delay factor
        $today = new \DateTimeImmutable();
        if (in_array($today->format('N'), [6, 7])) {
            $this->assertArrayHasKey('weekend_delay', $delayRisk['factors']);
        }
    }
}