<?php

declare(strict_types=1);

namespace App\Tests\Performance;

use App\Domain\Courier\Meest\Entity\MeestShipment;
use App\Domain\Courier\Meest\Enum\MeestShipmentType;
use App\Domain\Courier\Meest\Enum\MeestTrackingStatus;
use App\Domain\Courier\Meest\Repository\MeestShipmentRepository;
use App\Domain\Courier\Meest\Service\MeestTrackingService;
use App\Tests\Fixtures\MeestApiResponseFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Performance Tests for MEEST Batch Operations
 *
 * Tests cover performance scenarios including:
 * - Batch tracking API performance with 50 tracking numbers
 * - Bulk shipment creation performance
 * - Batch status updates performance
 * - Database query optimization validation
 * - Memory usage monitoring
 * - Response time benchmarks
 * - Concurrent request handling
 * - Large dataset processing
 */
class MeestBatchOperationsPerformanceTest extends WebTestCase
{
    private $client;
    private Stopwatch $stopwatch;
    private MeestShipmentRepository $repository;
    private MeestTrackingService $trackingService;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->stopwatch = new Stopwatch();
        $this->repository = static::getContainer()->get(MeestShipmentRepository::class);
        $this->trackingService = static::getContainer()->get(MeestTrackingService::class);
    }

    /**
     * Test batch tracking API performance with maximum allowed requests (50)
     */
    public function testBatchTrackingPerformanceMaxSize(): void
    {
        $trackingNumbers = array_fill(0, 50, 'BLP68A82A025DBC2PLTEST01');

        $memoryBefore = memory_get_usage(true);
        $this->stopwatch->start('batch_tracking_max');

        $this->client->request(
            'POST',
            '/v2/api/tracking/batch',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['trackingNumbers' => $trackingNumbers])
        );

        $event = $this->stopwatch->stop('batch_tracking_max');
        $memoryAfter = memory_get_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals(50, $responseData['summary']['total_requested']);

        // Performance assertions
        $executionTime = $event->getDuration(); // milliseconds
        $this->assertLessThan(30000, $executionTime, 'Batch tracking should complete within 30 seconds');
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Memory usage should be under 50MB'); // 50MB limit

        // Log performance metrics
        echo "\nBatch Tracking Performance (50 requests):\n";
        echo "Execution time: {$executionTime}ms\n";
        echo "Memory used: " . round($memoryUsed / 1024 / 1024, 2) . "MB\n";
        echo "Average time per request: " . round($executionTime / 50, 2) . "ms\n";
    }

    /**
     * Test batch tracking with incremental sizes
     */
    public function testBatchTrackingPerformanceIncremental(): void
    {
        $batchSizes = [5, 10, 25, 50];
        $results = [];

        foreach ($batchSizes as $size) {
            $trackingNumbers = array_fill(0, $size, 'BLP68A82A025DBC2PLTEST01');

            $memoryBefore = memory_get_usage(true);
            $this->stopwatch->start("batch_tracking_{$size}");

            $this->client->request(
                'POST',
                '/v2/api/tracking/batch',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['trackingNumbers' => $trackingNumbers])
            );

            $event = $this->stopwatch->stop("batch_tracking_{$size}");
            $memoryAfter = memory_get_usage(true);

            $response = $this->client->getResponse();
            $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

            $executionTime = $event->getDuration();
            $memoryUsed = $memoryAfter - $memoryBefore;
            $avgTimePerRequest = $executionTime / $size;

            $results[$size] = [
                'execution_time' => $executionTime,
                'memory_used' => $memoryUsed,
                'avg_time_per_request' => $avgTimePerRequest
            ];

            // Performance assertions based on batch size
            $expectedMaxTime = $size * 600; // 600ms per request max
            $this->assertLessThan($expectedMaxTime, $executionTime,
                "Batch size {$size} should complete within {$expectedMaxTime}ms");
        }

        // Verify performance scaling is reasonable
        $this->assertLessThan(
            $results[50]['avg_time_per_request'] * 2,
            $results[5]['avg_time_per_request'],
            'Small batches should not be disproportionately slower'
        );

        echo "\nBatch Tracking Performance Scaling:\n";
        foreach ($results as $size => $metrics) {
            echo "Size {$size}: {$metrics['execution_time']}ms total, " .
                 round($metrics['avg_time_per_request'], 2) . "ms avg\n";
        }
    }

    /**
     * Test bulk shipment creation performance
     */
    public function testBulkShipmentCreationPerformance(): void
    {
        $shipmentCount = 20;
        $baseShipmentData = MeestApiResponseFixtures::getCreateShipmentRequest();

        $memoryBefore = memory_get_usage(true);
        $this->stopwatch->start('bulk_shipment_creation');

        for ($i = 1; $i <= $shipmentCount; $i++) {
            $shipmentData = $baseShipmentData;
            $shipmentData['reference'] = "BULK-TEST-{$i}";
            $shipmentData['recipient']['email'] = "recipient{$i}@example.com";

            $this->client->request(
                'POST',
                '/v2/api/meest/parcels',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($shipmentData)
            );

            $response = $this->client->getResponse();
            $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        }

        $event = $this->stopwatch->stop('bulk_shipment_creation');
        $memoryAfter = memory_get_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;

        $executionTime = $event->getDuration();
        $avgTimePerShipment = $executionTime / $shipmentCount;

        // Performance assertions
        $this->assertLessThan(60000, $executionTime, 'Bulk creation should complete within 60 seconds');
        $this->assertLessThan(3000, $avgTimePerShipment, 'Average shipment creation should be under 3 seconds');

        echo "\nBulk Shipment Creation Performance ({$shipmentCount} shipments):\n";
        echo "Total time: {$executionTime}ms\n";
        echo "Average per shipment: " . round($avgTimePerShipment, 2) . "ms\n";
        echo "Memory used: " . round($memoryUsed / 1024 / 1024, 2) . "MB\n";
    }

    /**
     * Test batch status updates performance
     */
    public function testBatchStatusUpdatesPerformance(): void
    {
        // Create test shipments
        $shipmentCount = 100;
        $testShipments = [];

        for ($i = 1; $i <= $shipmentCount; $i++) {
            $shipment = new MeestShipment(
                trackingNumber: "PERF{$i}23456789",
                shipmentId: "PERF-SHIP-{$i}",
                shipmentType: MeestShipmentType::STANDARD,
                senderData: ['name' => 'Test Sender'],
                recipientData: ['name' => 'Test Recipient'],
                parcelData: ['weight' => 1.0]
            );

            $this->repository->save($shipment);
            $testShipments[] = $shipment;
        }

        // Test batch status updates
        $memoryBefore = memory_get_usage(true);
        $this->stopwatch->start('batch_status_updates');

        $updatedCount = $this->trackingService->updatePendingShipments();

        $event = $this->stopwatch->stop('batch_status_updates');
        $memoryAfter = memory_get_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;

        $executionTime = $event->getDuration();

        // Performance assertions
        $this->assertLessThan(30000, $executionTime, 'Batch updates should complete within 30 seconds');
        $this->assertGreaterThanOrEqual(0, $updatedCount, 'Should process updates without errors');

        echo "\nBatch Status Updates Performance ({$shipmentCount} shipments):\n";
        echo "Execution time: {$executionTime}ms\n";
        echo "Updated count: {$updatedCount}\n";
        echo "Memory used: " . round($memoryUsed / 1024 / 1024, 2) . "MB\n";

        // Cleanup
        foreach ($testShipments as $shipment) {
            $this->repository->remove($shipment);
        }
    }

    /**
     * Test database query performance for large datasets
     */
    public function testDatabaseQueryPerformance(): void
    {
        // Create test data
        $shipmentCount = 500;
        $testShipments = [];

        $this->stopwatch->start('data_setup');

        for ($i = 1; $i <= $shipmentCount; $i++) {
            $shipment = new MeestShipment(
                trackingNumber: "DB{$i}456789012",
                shipmentId: "DB-SHIP-{$i}",
                shipmentType: MeestShipmentType::STANDARD,
                senderData: ['name' => 'Test Sender'],
                recipientData: ['name' => 'Test Recipient'],
                parcelData: ['weight' => 1.0]
            );

            $shipment->updateStatus(
                $i % 5 === 0 ? MeestTrackingStatus::DELIVERED : MeestTrackingStatus::IN_TRANSIT
            );

            $this->repository->save($shipment);
            $testShipments[] = $shipment;
        }

        $this->stopwatch->stop('data_setup');

        // Test various query scenarios
        $queryTests = [
            'find_by_status' => function() {
                return $this->repository->findBy(['status' => MeestTrackingStatus::IN_TRANSIT]);
            },
            'find_by_date_range' => function() {
                return $this->repository->findByDateRange(
                    new \DateTimeImmutable('-1 day'),
                    new \DateTimeImmutable()
                );
            },
            'get_statistics' => function() {
                return $this->repository->getStatistics(new \DateTimeImmutable('-30 days'));
            },
            'find_pending_updates' => function() {
                return $this->repository->findPendingTrackingUpdates(new \DateTimeImmutable('-1 hour'));
            }
        ];

        $queryResults = [];

        foreach ($queryTests as $testName => $queryFunction) {
            $this->stopwatch->start("query_{$testName}");
            $memoryBefore = memory_get_usage(true);

            $result = $queryFunction();

            $event = $this->stopwatch->stop("query_{$testName}");
            $memoryAfter = memory_get_usage(true);

            $queryResults[$testName] = [
                'execution_time' => $event->getDuration(),
                'memory_used' => $memoryAfter - $memoryBefore,
                'result_count' => is_array($result) ? count($result) : (is_countable($result) ? count($result) : 1)
            ];

            // Performance assertion - queries should complete within reasonable time
            $this->assertLessThan(5000, $event->getDuration(),
                "Query {$testName} should complete within 5 seconds");
        }

        echo "\nDatabase Query Performance ({$shipmentCount} records):\n";
        foreach ($queryResults as $testName => $metrics) {
            echo "{$testName}: {$metrics['execution_time']}ms, " .
                 "Results: {$metrics['result_count']}, " .
                 "Memory: " . round($metrics['memory_used'] / 1024, 2) . "KB\n";
        }

        // Cleanup
        foreach ($testShipments as $shipment) {
            $this->repository->remove($shipment);
        }
    }

    /**
     * Test concurrent batch requests simulation
     */
    public function testConcurrentBatchRequestsSimulation(): void
    {
        $concurrentRequests = 5;
        $batchSize = 10;
        $trackingNumbers = array_fill(0, $batchSize, 'BLP68A82A025DBC2PLTEST01');

        $memoryBefore = memory_get_usage(true);
        $this->stopwatch->start('concurrent_batch_requests');

        $responses = [];

        // Simulate concurrent requests (sequential in test environment)
        for ($i = 1; $i <= $concurrentRequests; $i++) {
            $requestStart = microtime(true);

            $this->client->request(
                'POST',
                '/v2/api/tracking/batch',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['trackingNumbers' => $trackingNumbers])
            );

            $requestEnd = microtime(true);
            $response = $this->client->getResponse();

            $responses[] = [
                'status_code' => $response->getStatusCode(),
                'execution_time' => ($requestEnd - $requestStart) * 1000,
                'success' => json_decode($response->getContent(), true)['success'] ?? false
            ];

            $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        }

        $event = $this->stopwatch->stop('concurrent_batch_requests');
        $memoryAfter = memory_get_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;

        // Calculate statistics
        $totalTime = $event->getDuration();
        $avgResponseTime = array_sum(array_column($responses, 'execution_time')) / count($responses);
        $maxResponseTime = max(array_column($responses, 'execution_time'));
        $minResponseTime = min(array_column($responses, 'execution_time'));

        // Performance assertions
        $this->assertLessThan(45000, $totalTime, 'Concurrent requests should complete within 45 seconds');
        $this->assertLessThan(10000, $avgResponseTime, 'Average response time should be under 10 seconds');

        // All requests should succeed
        foreach ($responses as $response) {
            $this->assertTrue($response['success'], 'All concurrent requests should succeed');
        }

        echo "\nConcurrent Batch Requests Performance ({$concurrentRequests} requests x {$batchSize} items):\n";
        echo "Total time: {$totalTime}ms\n";
        echo "Average response time: " . round($avgResponseTime, 2) . "ms\n";
        echo "Min response time: " . round($minResponseTime, 2) . "ms\n";
        echo "Max response time: " . round($maxResponseTime, 2) . "ms\n";
        echo "Memory used: " . round($memoryUsed / 1024 / 1024, 2) . "MB\n";
    }

    /**
     * Test memory usage monitoring for large operations
     */
    public function testMemoryUsageMonitoring(): void
    {
        $memoryLimit = 128 * 1024 * 1024; // 128MB
        $initialMemory = memory_get_usage(true);

        // Test creating many shipments in memory
        $shipmentCount = 1000;
        $shipments = [];

        $this->stopwatch->start('memory_test');

        for ($i = 1; $i <= $shipmentCount; $i++) {
            $shipment = new MeestShipment(
                trackingNumber: "MEM{$i}456789012",
                shipmentId: "MEM-SHIP-{$i}",
                shipmentType: MeestShipmentType::STANDARD,
                senderData: ['name' => 'Test Sender'],
                recipientData: ['name' => 'Test Recipient'],
                parcelData: ['weight' => 1.0]
            );

            $shipments[] = $shipment;

            // Check memory usage every 100 items
            if ($i % 100 === 0) {
                $currentMemory = memory_get_usage(true);
                $memoryUsed = $currentMemory - $initialMemory;

                $this->assertLessThan($memoryLimit, $currentMemory,
                    "Memory usage should stay under limit at {$i} items");

                echo "Memory at {$i} items: " . round($memoryUsed / 1024 / 1024, 2) . "MB\n";
            }
        }

        $event = $this->stopwatch->stop('memory_test');
        $finalMemory = memory_get_usage(true);
        $totalMemoryUsed = $finalMemory - $initialMemory;

        // Performance assertions
        $this->assertLessThan($memoryLimit, $finalMemory, 'Total memory usage should stay within limits');

        $avgMemoryPerShipment = $totalMemoryUsed / $shipmentCount;
        $this->assertLessThan(1024, $avgMemoryPerShipment, 'Average memory per shipment should be under 1KB');

        echo "\nMemory Usage Test ({$shipmentCount} shipments in memory):\n";
        echo "Total memory used: " . round($totalMemoryUsed / 1024 / 1024, 2) . "MB\n";
        echo "Average per shipment: " . round($avgMemoryPerShipment, 2) . " bytes\n";
        echo "Execution time: {$event->getDuration()}ms\n";

        // Clean up memory
        unset($shipments);
        gc_collect_cycles();
    }

    /**
     * Test performance benchmarks for different operations
     */
    public function testPerformanceBenchmarks(): void
    {
        $benchmarks = [];

        // Benchmark 1: Single tracking request
        $this->stopwatch->start('single_tracking');
        $this->client->request('GET', '/v2/api/tracking', [
            'trackingNumber' => 'BLP68A82A025DBC2PLTEST01'
        ]);
        $event = $this->stopwatch->stop('single_tracking');
        $benchmarks['single_tracking'] = $event->getDuration();

        // Benchmark 2: Analytics request
        $this->stopwatch->start('analytics');
        $this->client->request('GET', '/v2/api/tracking/analytics');
        $event = $this->stopwatch->stop('analytics');
        $benchmarks['analytics'] = $event->getDuration();

        // Benchmark 3: Validation request
        $this->stopwatch->start('validation');
        $this->client->request(
            'POST',
            '/v2/api/meest/info/validate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(MeestApiResponseFixtures::getCreateShipmentRequest())
        );
        $event = $this->stopwatch->stop('validation');
        $benchmarks['validation'] = $event->getDuration();

        // Benchmark 4: Statistics request
        $this->stopwatch->start('statistics');
        $this->client->request('GET', '/v2/api/meest/info/statistics');
        $event = $this->stopwatch->stop('statistics');
        $benchmarks['statistics'] = $event->getDuration();

        // Performance assertions
        $this->assertLessThan(2000, $benchmarks['single_tracking'], 'Single tracking should be under 2s');
        $this->assertLessThan(3000, $benchmarks['analytics'], 'Analytics should be under 3s');
        $this->assertLessThan(1000, $benchmarks['validation'], 'Validation should be under 1s');
        $this->assertLessThan(2000, $benchmarks['statistics'], 'Statistics should be under 2s');

        echo "\nPerformance Benchmarks:\n";
        foreach ($benchmarks as $operation => $time) {
            echo "{$operation}: {$time}ms\n";
        }
    }

    protected function tearDown(): void
    {
        // Clean up any remaining test data
        $testPatterns = ['PERF%', 'DB%', 'MEM%', 'BULK-TEST-%'];

        foreach ($testPatterns as $pattern) {
            $shipments = $this->repository->createQueryBuilder('s')
                ->where('s.trackingNumber LIKE :pattern OR s.reference LIKE :pattern')
                ->setParameter('pattern', $pattern)
                ->getQuery()
                ->getResult();

            foreach ($shipments as $shipment) {
                $this->repository->remove($shipment);
            }
        }

        parent::tearDown();
    }
}