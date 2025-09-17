<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration Tests for MeestTrackingController
 *
 * Tests cover AI-powered tracking endpoints including:
 * - GET /v2/api/tracking - Enhanced tracking with AI predictions
 * - POST /v2/api/tracking/batch - Batch tracking operations
 * - POST /v2/api/tracking/predict - AI predictions
 * - GET /v2/api/tracking/analytics - Analytics and patterns
 * - POST /v2/api/tracking/webhook - Webhook handling
 * - GET /v2/api/tracking/test - Test scenarios
 *
 * Real-world scenarios from correspondence:
 * - Test tracking number (BLP68A82A025DBC2PLTEST01)
 * - AI-powered delay risk assessment
 * - Status transition predictions
 * - Route optimization suggestions
 */
class MeestTrackingControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Test enhanced tracking for test package BLP68A82A025DBC2PLTEST01
     */
    public function testGetEnhancedTrackingForTestPackage(): void
    {
        $trackingNumber = 'BLP68A82A025DBC2PLTEST01';

        $this->client->request('GET', '/v2/api/tracking', [
            'trackingNumber' => $trackingNumber
        ]);

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('meta', $responseData);

        $trackingData = $responseData['data'];
        $this->assertEquals($trackingNumber, $trackingData['trackingNumber']);
        $this->assertArrayHasKey('statusCode', $trackingData);
        $this->assertArrayHasKey('statusText', $trackingData);
        $this->assertArrayHasKey('predictions', $trackingData);
        $this->assertArrayHasKey('delayRisk', $trackingData);
        $this->assertArrayHasKey('suggestedActions', $trackingData);
        $this->assertArrayHasKey('patterns', $trackingData);
        $this->assertArrayHasKey('confidence', $trackingData);
        $this->assertArrayHasKey('anomalies', $trackingData);
        $this->assertArrayHasKey('smartInsights', $trackingData);

        // Verify AI predictions structure
        $this->assertIsArray($trackingData['predictions']);
        foreach ($trackingData['predictions'] as $prediction) {
            $this->assertArrayHasKey('status', $prediction);
            $this->assertArrayHasKey('statusText', $prediction);
            $this->assertArrayHasKey('probability', $prediction);
            $this->assertArrayHasKey('estimatedTimeHours', $prediction);
            $this->assertArrayHasKey('confidence', $prediction);
        }

        // Verify delay risk structure
        $delayRisk = $trackingData['delayRisk'];
        $this->assertArrayHasKey('total', $delayRisk);
        $this->assertArrayHasKey('level', $delayRisk);
        $this->assertArrayHasKey('factors', $delayRisk);
        $this->assertArrayHasKey('recommendations', $delayRisk);

        // Verify meta information
        $meta = $responseData['meta'];
        $this->assertTrue($meta['ai_powered']);
        $this->assertEquals('v2.1', $meta['prediction_model']);
        $this->assertEquals(0.7, $meta['confidence_threshold']);
    }

    /**
     * Test enhanced tracking without tracking number
     */
    public function testGetEnhancedTrackingMissingTrackingNumber(): void
    {
        $this->client->request('GET', '/v2/api/tracking');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Tracking number is required', $responseData['error']);
    }

    /**
     * Test batch tracking with multiple tracking numbers
     */
    public function testGetBatchTrackingSuccess(): void
    {
        $trackingNumbers = [
            'BLP68A82A025DBC2PLTEST01',
            'BLP68A82A025DBC2PLTEST02',
            'BLP68A82A025DBC2PLTEST03'
        ];

        $this->client->request(
            'POST',
            '/v2/api/tracking/batch',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['trackingNumbers' => $trackingNumbers])
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('errors', $responseData);
        $this->assertArrayHasKey('summary', $responseData);

        $summary = $responseData['summary'];
        $this->assertEquals(3, $summary['total_requested']);
        $this->assertArrayHasKey('successful', $summary);
        $this->assertArrayHasKey('failed', $summary);

        // Verify that some tracking numbers were processed
        $this->assertIsArray($responseData['data']);
        $this->assertIsArray($responseData['errors']);
    }

    /**
     * Test batch tracking with too many tracking numbers
     */
    public function testGetBatchTrackingTooMany(): void
    {
        $trackingNumbers = array_fill(0, 51, 'TEST123456789'); // 51 numbers, limit is 50

        $this->client->request(
            'POST',
            '/v2/api/tracking/batch',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['trackingNumbers' => $trackingNumbers])
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Maximum 50 tracking numbers allowed per batch', $responseData['error']);
    }

    /**
     * Test batch tracking with empty array
     */
    public function testGetBatchTrackingEmpty(): void
    {
        $this->client->request(
            'POST',
            '/v2/api/tracking/batch',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['trackingNumbers' => []])
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('No tracking numbers provided', $responseData['error']);
    }

    /**
     * Test AI predictions endpoint
     */
    public function testGetPredictionsSuccess(): void
    {
        $requestData = [
            'trackingNumber' => 'BLP68A82A025DBC2PLTEST01',
            'currentStatus' => 'in_transit',
            'location' => 'Warsaw Distribution Center',
            'lastUpdate' => (new \DateTimeImmutable())->format('c')
        ];

        $this->client->request(
            'POST',
            '/v2/api/tracking/predict',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];

        $this->assertEquals('BLP68A82A025DBC2PLTEST01', $data['trackingNumber']);
        $this->assertArrayHasKey('predictions', $data);
        $this->assertArrayHasKey('delayRisk', $data);
        $this->assertEquals('v2.1', $data['modelVersion']);
        $this->assertArrayHasKey('predictedAt', $data);

        // Verify predictions structure
        $this->assertIsArray($data['predictions']);
        foreach ($data['predictions'] as $prediction) {
            $this->assertArrayHasKey('status', $prediction);
            $this->assertArrayHasKey('probability', $prediction);
        }

        // Verify delay risk structure
        $this->assertArrayHasKey('total', $data['delayRisk']);
        $this->assertArrayHasKey('level', $data['delayRisk']);
    }

    /**
     * Test predictions without tracking number
     */
    public function testGetPredictionsMissingTrackingNumber(): void
    {
        $requestData = [
            'currentStatus' => 'in_transit',
            'location' => 'Warsaw'
        ];

        $this->client->request(
            'POST',
            '/v2/api/tracking/predict',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Tracking number is required', $responseData['error']);
    }

    /**
     * Test analytics endpoint
     */
    public function testGetAnalyticsDefault(): void
    {
        $this->client->request('GET', '/v2/api/tracking/analytics');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $this->assertArrayHasKey('data', $responseData);
        $analytics = $responseData['data'];

        $this->assertEquals('30d', $analytics['period']);
        $this->assertArrayHasKey('from_date', $analytics);
        $this->assertArrayHasKey('to_date', $analytics);

        $this->assertArrayHasKey('summary', $analytics);
        $summary = $analytics['summary'];
        $this->assertArrayHasKey('total_shipments', $summary);
        $this->assertArrayHasKey('delivered', $summary);
        $this->assertArrayHasKey('in_transit', $summary);
        $this->assertArrayHasKey('delayed', $summary);
        $this->assertArrayHasKey('exceptions', $summary);

        $this->assertArrayHasKey('delivery_performance', $analytics);
        $performance = $analytics['delivery_performance'];
        $this->assertArrayHasKey('on_time_rate', $performance);
        $this->assertArrayHasKey('average_delivery_time', $performance);
        $this->assertArrayHasKey('delay_rate', $performance);

        $this->assertArrayHasKey('status_distribution', $analytics);
        $this->assertArrayHasKey('predictions_accuracy', $analytics);
        $this->assertArrayHasKey('common_delays', $analytics);
    }

    /**
     * Test analytics with different periods
     */
    public function testGetAnalyticsWithPeriods(): void
    {
        $periods = ['7d', '30d', '90d'];

        foreach ($periods as $period) {
            $this->client->request('GET', '/v2/api/tracking/analytics', [
                'period' => $period
            ]);

            $response = $this->client->getResponse();
            $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

            $responseData = json_decode($response->getContent(), true);
            $this->assertTrue($responseData['success']);

            $analytics = $responseData['data'];
            $this->assertEquals($period, $analytics['period']);
        }
    }

    /**
     * Test webhook endpoint
     */
    public function testWebhookHandling(): void
    {
        $webhookData = [
            'trackingNumber' => 'BLP68A82A025DBC2PLTEST01',
            'status' => 'in_transit',
            'location' => 'Warsaw Distribution Center',
            'timestamp' => (new \DateTimeImmutable())->format('c'),
            'metadata' => [
                'carrier' => 'MEEST',
                'facility_id' => 'WAR001'
            ]
        ];

        $this->client->request(
            'POST',
            '/v2/api/tracking/webhook',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($webhookData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Webhook processed successfully', $responseData['message']);
    }

    /**
     * Test webhook with invalid JSON
     */
    public function testWebhookInvalidJson(): void
    {
        $this->client->request(
            'POST',
            '/v2/api/tracking/webhook',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid json{'
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Webhook processing failed', $responseData['error']);
    }

    /**
     * Test tracking test endpoint with normal scenario
     */
    public function testTrackingTestNormalScenario(): void
    {
        $this->client->request('GET', '/v2/api/tracking/test', [
            'scenario' => 'normal'
        ]);

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('meta', $responseData);

        $data = $responseData['data'];
        $this->assertEquals('BLP68A82A025DBC2PLTEST01', $data['trackingNumber']);
        $this->assertEquals('606', $data['statusCode']);
        $this->assertEquals('Arrived at local HUB', $data['statusText']);

        $this->assertArrayHasKey('predictions', $data);
        $this->assertArrayHasKey('delayRisk', $data);
        $this->assertArrayHasKey('suggestedActions', $data);

        // Verify meta
        $meta = $responseData['meta'];
        $this->assertEquals('normal', $meta['test_scenario']);
        $this->assertTrue($meta['ai_powered']);
        $this->assertEquals('v2.1-test', $meta['prediction_model']);
    }

    /**
     * Test tracking test endpoint with delayed scenario
     */
    public function testTrackingTestDelayedScenario(): void
    {
        $this->client->request('GET', '/v2/api/tracking/test', [
            'scenario' => 'delayed'
        ]);

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $data = $responseData['data'];
        $this->assertEquals('BLP68A82A025DBC2PLTEST01', $data['trackingNumber']);
        $this->assertEquals('300', $data['statusCode']);
        $this->assertEquals('In transit - delayed', $data['statusText']);

        // Delayed scenario should have high delay risk
        $this->assertEquals(0.8, $data['delayRisk']['total']);
        $this->assertEquals('high', $data['delayRisk']['level']);
    }

    /**
     * Test tracking test endpoint with exception scenario
     */
    public function testTrackingTestExceptionScenario(): void
    {
        $this->client->request('GET', '/v2/api/tracking/test', [
            'scenario' => 'exception'
        ]);

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $data = $responseData['data'];
        $this->assertEquals('BLP68A82A025DBC2PLTEST01', $data['trackingNumber']);
        $this->assertEquals('999', $data['statusCode']);
        $this->assertEquals('Exception - customs hold', $data['statusText']);

        // Exception scenario should have very high delay risk
        $this->assertEquals(0.9, $data['delayRisk']['total']);
        $this->assertEquals('high', $data['delayRisk']['level']);
    }

    /**
     * Test tracking test endpoint with delivered scenario
     */
    public function testTrackingTestDeliveredScenario(): void
    {
        $this->client->request('GET', '/v2/api/tracking/test', [
            'scenario' => 'delivered'
        ]);

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $data = $responseData['data'];
        $this->assertEquals('BLP68A82A025DBC2PLTEST01', $data['trackingNumber']);
        $this->assertEquals('500', $data['statusCode']);
        $this->assertEquals('Delivered', $data['statusText']);

        // Delivered scenario should have no delay risk
        $this->assertEquals(0.0, $data['delayRisk']['total']);
        $this->assertEquals('low', $data['delayRisk']['level']);
    }

    /**
     * Test tracking test endpoint with default scenario
     */
    public function testTrackingTestDefaultScenario(): void
    {
        $this->client->request('GET', '/v2/api/tracking/test');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $meta = $responseData['meta'];
        $this->assertEquals('normal', $meta['test_scenario']); // Default should be 'normal'
    }

    /**
     * Test tracking test endpoint with invalid scenario
     */
    public function testTrackingTestInvalidScenario(): void
    {
        $this->client->request('GET', '/v2/api/tracking/test', [
            'scenario' => 'invalid_scenario'
        ]);

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        // Invalid scenario should default to 'normal'
        $meta = $responseData['meta'];
        $this->assertEquals('invalid_scenario', $meta['test_scenario']);
    }

    /**
     * Test enhanced tracking response structure validation
     */
    public function testEnhancedTrackingResponseStructure(): void
    {
        $this->client->request('GET', '/v2/api/tracking', [
            'trackingNumber' => 'BLP68A82A025DBC2PLTEST01'
        ]);

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);

        // Test required response structure
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('meta', $responseData);

        $data = $responseData['data'];

        // Test required data fields
        $requiredFields = [
            'trackingNumber', 'statusCode', 'statusText', 'statusDate',
            'predictions', 'delayRisk', 'confidence', 'anomalies', 'smartInsights'
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $data, "Missing required field: {$field}");
        }

        // Test optional data fields
        $optionalFields = [
            'lastMileTrackingNumber', 'country', 'city', 'eta',
            'pickupDate', 'recipientSurname', 'suggestedActions', 'patterns'
        ];

        foreach ($optionalFields as $field) {
            if (isset($data[$field])) {
                $this->assertNotNull($data[$field], "Optional field {$field} should not be null if present");
            }
        }
    }

    /**
     * Test batch tracking performance with many requests
     */
    public function testBatchTrackingPerformance(): void
    {
        // Test with maximum allowed batch size
        $trackingNumbers = array_fill(0, 50, 'BLP68A82A025DBC2PLTEST01');

        $startTime = microtime(true);

        $this->client->request(
            'POST',
            '/v2/api/tracking/batch',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['trackingNumbers' => $trackingNumbers])
        );

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        // Should complete within reasonable time (adjust as needed)
        $this->assertLessThan(30000, $executionTime, 'Batch tracking should complete within 30 seconds');

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals(50, $responseData['summary']['total_requested']);
    }
}