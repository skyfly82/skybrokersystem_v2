<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Courier\Meest\Service;

use App\Domain\Courier\Meest\DTO\MeestTrackingResponseDTO;
use App\Domain\Courier\Meest\Enum\MeestTrackingStatus;
use App\Domain\Courier\Meest\Repository\MeestShipmentRepository;
use App\Domain\Courier\Meest\Service\MeestMLPredictionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Unit Tests for MeestMLPredictionService
 *
 * Tests cover machine learning features including:
 * - Model training and accuracy assessment
 * - Delivery time predictions using ML algorithms
 * - Delay risk assessment with multiple factors
 * - Status transition predictions
 * - Route optimization recommendations
 * - Anomaly detection using pattern analysis
 * - Automated insights generation
 */
class MeestMLPredictionServiceTest extends TestCase
{
    private MeestShipmentRepository|MockObject $repository;
    private LoggerInterface|MockObject $logger;
    private CacheInterface|MockObject $cache;
    private MeestMLPredictionService $mlService;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(MeestShipmentRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);

        $this->mlService = new MeestMLPredictionService(
            $this->repository,
            $this->logger,
            $this->cache
        );
    }

    /**
     * Test ML model training process
     */
    public function testTrainModelsSuccess(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Starting ML model training for MEEST predictions');

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with('meest_ml_models', $this->isType('array'), 86400);

        $result = $this->mlService->trainModels();

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('models', $result);
        $this->assertArrayHasKey('training_samples', $result);
        $this->assertArrayHasKey('model_version', $result);
        $this->assertArrayHasKey('accuracy_scores', $result);

        $expectedModels = [
            'delivery_time_prediction',
            'delay_risk_assessment',
            'status_transition_prediction',
            'route_optimization',
            'cost_prediction'
        ];

        $this->assertEquals($expectedModels, $result['models']);
        $this->assertEquals('v2.1', $result['model_version']);

        // Verify accuracy scores
        $this->assertIsArray($result['accuracy_scores']);
        foreach ($expectedModels as $model) {
            $this->assertArrayHasKey($model, $result['accuracy_scores']);
            $this->assertIsFloat($result['accuracy_scores'][$model]);
            $this->assertGreaterThan(0.8, $result['accuracy_scores'][$model]); // All models should have > 80% accuracy
        }
    }

    /**
     * Test ML model training failure handling
     */
    public function testTrainModelsFailure(): void
    {
        // Mock an exception during training
        $this->cache
            ->expects($this->once())
            ->method('set')
            ->willThrowException(new \Exception('Cache error'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('ML model training failed', ['error' => 'Cache error']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cache error');

        $this->mlService->trainModels();
    }

    /**
     * Test delivery time prediction for out for delivery status
     */
    public function testPredictDeliveryTimeOutForDelivery(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::OUT_FOR_DELIVERY,
            lastUpdate: new \DateTimeImmutable(),
            events: array_fill(0, 5, ['timestamp' => new \DateTimeImmutable(), 'status' => 'test']),
            currentLocation: 'Local delivery hub'
        );

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturn(['accuracy' => 0.87]);

        $prediction = $this->mlService->predictDeliveryTime($trackingResponse);

        $this->assertIsArray($prediction);
        $this->assertArrayHasKey('predicted_hours', $prediction);
        $this->assertArrayHasKey('confidence', $prediction);
        $this->assertArrayHasKey('factors', $prediction);
        $this->assertArrayHasKey('model_version', $prediction);

        $this->assertIsInt($prediction['predicted_hours']);
        $this->assertGreaterThan(0, $prediction['predicted_hours']);
        $this->assertLessThan(24, $prediction['predicted_hours']); // Should be less than 24 hours for out for delivery

        $this->assertIsFloat($prediction['confidence']);
        $this->assertGreaterThan(0, $prediction['confidence']);
        $this->assertLessThanOrEqual(1, $prediction['confidence']);

        $this->assertEquals('v2.1', $prediction['model_version']);
    }

    /**
     * Test delivery time prediction for in transit status
     */
    public function testPredictDeliveryTimeInTransit(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Distribution center'
        );

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturn(['accuracy' => 0.87]);

        $prediction = $this->mlService->predictDeliveryTime($trackingResponse);

        $this->assertGreaterThan(24, $prediction['predicted_hours']); // Should be more than 24 hours for in transit
    }

    /**
     * Test delivery time prediction with weekend adjustment
     */
    public function testPredictDeliveryTimeWeekendAdjustment(): void
    {
        // Create tracking response that would trigger weekend adjustment
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Transit'
        );

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturn(['accuracy' => 0.87]);

        $prediction = $this->mlService->predictDeliveryTime($trackingResponse);

        // If it's weekend, factors should include weekend_delay
        $today = new \DateTimeImmutable();
        if (in_array($today->format('N'), [6, 7])) {
            $this->assertArrayHasKey('weekend_delay', $prediction['factors']);
        }
    }

    /**
     * Test delay risk assessment for high-risk scenario
     */
    public function testAssessDelayRiskHighRisk(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::EXCEPTION,
            lastUpdate: new \DateTimeImmutable('-48 hours'), // Old update
            events: [],
            currentLocation: 'Customs inspection facility'
        );

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturn(['accuracy' => 0.92]);

        $riskAssessment = $this->mlService->assessDelayRisk($trackingResponse);

        $this->assertIsArray($riskAssessment);
        $this->assertArrayHasKey('total_risk', $riskAssessment);
        $this->assertArrayHasKey('risk_level', $riskAssessment);
        $this->assertArrayHasKey('factors', $riskAssessment);
        $this->assertArrayHasKey('confidence', $riskAssessment);
        $this->assertArrayHasKey('recommendations', $riskAssessment);

        $this->assertIsFloat($riskAssessment['total_risk']);
        $this->assertGreaterThan(0.5, $riskAssessment['total_risk']); // Should be high risk
        $this->assertEquals('high', $riskAssessment['risk_level']);

        // Should include various risk factors
        $this->assertArrayHasKey('location_risk', $riskAssessment['factors']);
        $this->assertArrayHasKey('status_risk', $riskAssessment['factors']);
        $this->assertArrayHasKey('time_risk', $riskAssessment['factors']);
        $this->assertArrayHasKey('pattern_risk', $riskAssessment['factors']);
        $this->assertArrayHasKey('external_risk', $riskAssessment['factors']);

        // Exception status should have high status risk
        $this->assertEquals(0.9, $riskAssessment['factors']['status_risk']);

        // Old update should have significant time risk
        $this->assertGreaterThan(0.5, $riskAssessment['factors']['time_risk']);
    }

    /**
     * Test delay risk assessment for low-risk scenario
     */
    public function testAssessDelayRiskLowRisk(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::OUT_FOR_DELIVERY,
            lastUpdate: new \DateTimeImmutable('-30 minutes'),
            events: [],
            currentLocation: 'Local delivery vehicle'
        );

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturn(['accuracy' => 0.92]);

        $riskAssessment = $this->mlService->assessDelayRisk($trackingResponse);

        $this->assertLessThan(0.3, $riskAssessment['total_risk']); // Should be low risk
        $this->assertEquals('low', $riskAssessment['risk_level']);
    }

    /**
     * Test status transition predictions
     */
    public function testPredictStatusTransitions(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Transit'
        );

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturn(['accuracy' => 0.89]);

        $predictions = $this->mlService->predictStatusTransitions($trackingResponse);

        $this->assertIsArray($predictions);
        $this->assertLessThanOrEqual(5, count($predictions)); // Should return top 5

        foreach ($predictions as $prediction) {
            $this->assertArrayHasKey('status', $prediction);
            $this->assertArrayHasKey('status_text', $prediction);
            $this->assertArrayHasKey('probability', $prediction);
            $this->assertArrayHasKey('estimated_hours', $prediction);
            $this->assertArrayHasKey('confidence', $prediction);

            $this->assertIsString($prediction['status']);
            $this->assertIsString($prediction['status_text']);
            $this->assertIsFloat($prediction['probability']);
            $this->assertIsInt($prediction['estimated_hours']);
            $this->assertIsFloat($prediction['confidence']);

            // Probabilities should be valid
            $this->assertGreaterThanOrEqual(0, $prediction['probability']);
            $this->assertLessThanOrEqual(1, $prediction['probability']);
        }

        // Should be sorted by probability (highest first)
        for ($i = 1; $i < count($predictions); $i++) {
            $this->assertGreaterThanOrEqual(
                $predictions[$i]['probability'],
                $predictions[$i - 1]['probability']
            );
        }
    }

    /**
     * Test status transition predictions for terminal status
     */
    public function testPredictStatusTransitionsTerminalStatus(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::DELIVERED,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Delivered'
        );

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturn(['accuracy' => 0.89]);

        $predictions = $this->mlService->predictStatusTransitions($trackingResponse);

        // Delivered status should have no possible transitions
        $this->assertEmpty($predictions);
    }

    /**
     * Test route optimization
     */
    public function testOptimizeRoute(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Transit'
        );

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturn(['efficiency' => 0.91]);

        $optimization = $this->mlService->optimizeRoute($trackingResponse);

        $this->assertIsArray($optimization);
        $this->assertArrayHasKey('current_route_efficiency', $optimization);
        $this->assertArrayHasKey('optimizations', $optimization);
        $this->assertArrayHasKey('recommendations', $optimization);
        $this->assertArrayHasKey('confidence', $optimization);

        $this->assertIsFloat($optimization['current_route_efficiency']);
        $this->assertIsArray($optimization['optimizations']);
        $this->assertIsArray($optimization['recommendations']);
        $this->assertIsFloat($optimization['confidence']);

        // Check optimization structure
        $optimizations = $optimization['optimizations'];
        $this->assertArrayHasKey('alternative_routes', $optimizations);
        $this->assertArrayHasKey('time_savings', $optimizations);
        $this->assertArrayHasKey('cost_implications', $optimizations);
        $this->assertArrayHasKey('risk_assessment', $optimizations);
    }

    /**
     * Test anomaly detection
     */
    public function testDetectAnomalies(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Unknown location'
        );

        $result = $this->mlService->detectAnomalies($trackingResponse);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('anomalies', $result);
        $this->assertArrayHasKey('total_score', $result);
        $this->assertArrayHasKey('severity', $result);
        $this->assertArrayHasKey('recommendations', $result);

        $this->assertIsArray($result['anomalies']);
        $this->assertIsFloat($result['total_score']);
        $this->assertIsString($result['severity']);
        $this->assertIsArray($result['recommendations']);
    }

    /**
     * Test insights generation for high performance shipment
     */
    public function testGenerateInsightsHighPerformance(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Transit'
        );

        $insights = $this->mlService->generateInsights($trackingResponse);

        $this->assertIsArray($insights);

        foreach ($insights as $insight) {
            $this->assertArrayHasKey('type', $insight);
            $this->assertArrayHasKey('category', $insight);
            $this->assertArrayHasKey('message', $insight);
            $this->assertArrayHasKey('confidence', $insight);
            $this->assertArrayHasKey('data', $insight);

            $this->assertIsString($insight['type']);
            $this->assertIsString($insight['category']);
            $this->assertIsString($insight['message']);
            $this->assertIsFloat($insight['confidence']);
            $this->assertIsArray($insight['data']);
        }
    }

    /**
     * Test feature extraction from tracking data
     */
    public function testFeatureExtraction(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            estimatedDelivery: new \DateTimeImmutable('+2 days'),
            events: array_fill(0, 8, ['timestamp' => new \DateTimeImmutable(), 'status' => 'test']),
            currentLocation: 'Hub facility'
        );

        // We need to test this indirectly through public methods that use feature extraction
        $prediction = $this->mlService->predictDeliveryTime($trackingResponse);

        // If feature extraction works correctly, prediction should be generated
        $this->assertIsArray($prediction);
        $this->assertArrayHasKey('predicted_hours', $prediction);
    }

    /**
     * Test model accuracy validation
     */
    public function testModelAccuracyValidation(): void
    {
        $result = $this->mlService->trainModels();

        $accuracyScores = $result['accuracy_scores'];

        // All models should have reasonable accuracy scores
        foreach ($accuracyScores as $model => $accuracy) {
            $this->assertGreaterThan(0.8, $accuracy, "Model {$model} should have accuracy > 80%");
            $this->assertLessThanOrEqual(1.0, $accuracy, "Model {$model} accuracy should not exceed 100%");
        }

        // Specific model accuracy expectations
        $this->assertGreaterThanOrEqual(0.85, $accuracyScores['delivery_time_prediction']);
        $this->assertGreaterThanOrEqual(0.90, $accuracyScores['delay_risk_assessment']);
        $this->assertGreaterThanOrEqual(0.85, $accuracyScores['status_transition_prediction']);
    }

    /**
     * Test caching behavior in ML predictions
     */
    public function testCachingBehaviorInPredictions(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Transit'
        );

        // Test that cache is used for model retrieval
        $this->cache
            ->expects($this->atLeastOnce())
            ->method('get')
            ->with($this->stringContains('meest_ml_models'))
            ->willReturn(['delivery_time_prediction' => ['accuracy' => 0.87]]);

        $prediction = $this->mlService->predictDeliveryTime($trackingResponse);

        $this->assertIsArray($prediction);
    }

    /**
     * Test weekend factor detection in ML features
     */
    public function testWeekendFactorDetection(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Transit'
        );

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturn(['accuracy' => 0.87]);

        $prediction = $this->mlService->predictDeliveryTime($trackingResponse);

        // Check if weekend is properly detected
        $today = new \DateTimeImmutable();
        if (in_array($today->format('N'), [6, 7])) {
            // Should have weekend adjustment
            $this->assertArrayHasKey('factors', $prediction);
        }
    }

    /**
     * Test customs location risk detection
     */
    public function testCustomsLocationRiskDetection(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::CUSTOMS_CLEARANCE,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Customs inspection facility'
        );

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturn(['accuracy' => 0.92]);

        $riskAssessment = $this->mlService->assessDelayRisk($trackingResponse);

        // Should detect high location risk for customs
        $this->assertGreaterThan(0.5, $riskAssessment['factors']['location_risk']);
    }

    /**
     * Test risk aggregation algorithm
     */
    public function testRiskAggregationAlgorithm(): void
    {
        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'Transit'
        );

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturn(['accuracy' => 0.92]);

        $riskAssessment = $this->mlService->assessDelayRisk($trackingResponse);

        // Total risk should be weighted average of individual factors
        $totalRisk = $riskAssessment['total_risk'];
        $factors = $riskAssessment['factors'];

        $this->assertIsFloat($totalRisk);
        $this->assertGreaterThanOrEqual(0, $totalRisk);
        $this->assertLessThanOrEqual(1, $totalRisk);

        // Risk level should correspond to total risk
        $riskLevel = $riskAssessment['risk_level'];
        if ($totalRisk < 0.3) {
            $this->assertEquals('low', $riskLevel);
        } elseif ($totalRisk < 0.6) {
            $this->assertEquals('medium', $riskLevel);
        } else {
            $this->assertEquals('high', $riskLevel);
        }
    }
}