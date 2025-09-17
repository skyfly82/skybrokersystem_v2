<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Courier\Meest\Service;

use App\Domain\Courier\Meest\DTO\MeestTrackingResponseDTO;
use App\Domain\Courier\Meest\Entity\MeestShipment;
use App\Domain\Courier\Meest\Enum\MeestShipmentType;
use App\Domain\Courier\Meest\Enum\MeestTrackingStatus;
use App\Domain\Courier\Meest\Exception\MeestIntegrationException;
use App\Domain\Courier\Meest\Repository\MeestShipmentRepository;
use App\Domain\Courier\Meest\Service\MeestApiClient;
use App\Domain\Courier\Meest\Service\MeestTrackingService;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit Tests for MeestTrackingService
 *
 * Tests cover tracking scenarios including:
 * - Batch tracking updates for pending shipments
 * - Individual shipment tracking updates
 * - Status change detection and logging
 * - Overdue shipment detection
 * - Statistics generation
 * - Missing label generation
 * - Error handling and recovery
 */
class MeestTrackingServiceTest extends TestCase
{
    private MeestApiClient|MockObject $apiClient;
    private MeestShipmentRepository|MockObject $repository;
    private LoggerInterface|MockObject $logger;
    private MeestTrackingService $trackingService;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(MeestApiClient::class);
        $this->repository = $this->createMock(MeestShipmentRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->trackingService = new MeestTrackingService(
            $this->apiClient,
            $this->repository,
            $this->logger
        );
    }

    /**
     * Test updating pending shipments successfully
     */
    public function testUpdatePendingShipmentsSuccess(): void
    {
        $shipment1 = $this->createMockShipment('BLP68A82A025DBC2PLTEST01', MeestTrackingStatus::CREATED);
        $shipment2 = $this->createMockShipment('BLP68A82A025DBC2PLTEST02', MeestTrackingStatus::IN_TRANSIT);

        $this->repository
            ->expects($this->once())
            ->method('findPendingTrackingUpdates')
            ->willReturn([$shipment1, $shipment2]);

        // Mock API responses for both shipments
        $trackingResponse1 = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            events: [
                [
                    'timestamp' => '2024-01-10T14:30:00Z',
                    'status' => 'in_transit',
                    'location' => 'Warsaw Distribution Center'
                ]
            ],
            currentLocation: 'Warsaw Distribution Center'
        );

        $trackingResponse2 = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST02',
            status: MeestTrackingStatus::IN_TRANSIT, // No status change
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'In Transit'
        );

        $this->apiClient
            ->expects($this->exactly(2))
            ->method('getTracking')
            ->willReturnMap([
                ['BLP68A82A025DBC2PLTEST01', $trackingResponse1],
                ['BLP68A82A025DBC2PLTEST02', $trackingResponse2]
            ]);

        // Shipment1 should be updated (status changed)
        $shipment1->expects($this->once())->method('updateStatus')->with(MeestTrackingStatus::IN_TRANSIT);
        $shipment1->expects($this->once())->method('setMetadata');

        // Shipment2 should not be updated (no status change)
        $shipment2->expects($this->never())->method('updateStatus');

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($shipment1);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('MEEST shipment status updated', $this->anything());

        $updateCount = $this->trackingService->updatePendingShipments();

        $this->assertEquals(1, $updateCount);
    }

    /**
     * Test updating pending shipments with API errors
     */
    public function testUpdatePendingShipmentsWithApiErrors(): void
    {
        $shipment1 = $this->createMockShipment('BLP68A82A025DBC2PLTEST01', MeestTrackingStatus::CREATED);
        $shipment2 = $this->createMockShipment('BLP68A82A025DBC2PLTEST02', MeestTrackingStatus::IN_TRANSIT);

        $this->repository
            ->expects($this->once())
            ->method('findPendingTrackingUpdates')
            ->willReturn([$shipment1, $shipment2]);

        // First API call fails, second succeeds
        $this->apiClient
            ->expects($this->exactly(2))
            ->method('getTracking')
            ->willReturnCallback(function ($trackingNumber) {
                if ($trackingNumber === 'BLP68A82A025DBC2PLTEST01') {
                    throw new MeestIntegrationException('API Error');
                }

                return new MeestTrackingResponseDTO(
                    trackingNumber: 'BLP68A82A025DBC2PLTEST02',
                    status: MeestTrackingStatus::DELIVERED,
                    lastUpdate: new \DateTimeImmutable(),
                    events: [],
                    currentLocation: 'Delivered'
                );
            });

        // First shipment should log error
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to update MEEST shipment tracking', $this->anything());

        // Second shipment should be updated
        $shipment2->expects($this->once())->method('updateStatus')->with(MeestTrackingStatus::DELIVERED);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($shipment2);

        $updateCount = $this->trackingService->updatePendingShipments();

        $this->assertEquals(1, $updateCount);
    }

    /**
     * Test individual shipment tracking update with status change
     */
    public function testUpdateShipmentTrackingWithStatusChange(): void
    {
        $shipment = $this->createMockShipment('BLP68A82A025DBC2PLTEST01', MeestTrackingStatus::CREATED);

        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT,
            lastUpdate: new \DateTimeImmutable(),
            estimatedDelivery: new \DateTimeImmutable('+2 days'),
            events: [
                [
                    'timestamp' => '2024-01-10T14:30:00Z',
                    'status' => 'in_transit',
                    'location' => 'Warsaw Distribution Center'
                ]
            ],
            currentLocation: 'Warsaw Distribution Center'
        );

        $this->apiClient
            ->expects($this->once())
            ->method('getTracking')
            ->with('BLP68A82A025DBC2PLTEST01')
            ->willReturn($trackingResponse);

        $shipment->expects($this->once())->method('updateStatus')->with(MeestTrackingStatus::IN_TRANSIT);
        $shipment->expects($this->once())->method('setEstimatedDelivery');
        $shipment->expects($this->once())->method('setMetadata');

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($shipment);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('MEEST shipment status updated', $this->anything());

        $result = $this->trackingService->updateShipmentTracking($shipment);

        $this->assertTrue($result);
    }

    /**
     * Test individual shipment tracking update without status change
     */
    public function testUpdateShipmentTrackingNoStatusChange(): void
    {
        $shipment = $this->createMockShipment('BLP68A82A025DBC2PLTEST01', MeestTrackingStatus::IN_TRANSIT);

        $trackingResponse = new MeestTrackingResponseDTO(
            trackingNumber: 'BLP68A82A025DBC2PLTEST01',
            status: MeestTrackingStatus::IN_TRANSIT, // Same status
            lastUpdate: new \DateTimeImmutable(),
            events: [],
            currentLocation: 'In Transit'
        );

        $this->apiClient
            ->expects($this->once())
            ->method('getTracking')
            ->willReturn($trackingResponse);

        // No update methods should be called
        $shipment->expects($this->never())->method('updateStatus');
        $shipment->expects($this->never())->method('setEstimatedDelivery');
        $shipment->expects($this->never())->method('setMetadata');

        $this->repository->expects($this->never())->method('save');
        $this->logger->expects($this->never())->method('info');

        $result = $this->trackingService->updateShipmentTracking($shipment);

        $this->assertFalse($result);
    }

    /**
     * Test individual shipment tracking update with API error
     */
    public function testUpdateShipmentTrackingApiError(): void
    {
        $shipment = $this->createMockShipment('BLP68A82A025DBC2PLTEST01', MeestTrackingStatus::CREATED);

        $this->apiClient
            ->expects($this->once())
            ->method('getTracking')
            ->willThrowException(new MeestIntegrationException('Shipment not found'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to update MEEST shipment tracking', $this->anything());

        $this->expectException(MeestIntegrationException::class);

        $this->trackingService->updateShipmentTracking($shipment);
    }

    /**
     * Test getting overdue shipments
     */
    public function testGetOverdueShipments(): void
    {
        $overdueDate = new \DateTimeImmutable('-7 days');
        $overdueShipments = [
            $this->createMockShipment('OVERDUE001', MeestTrackingStatus::IN_TRANSIT),
            $this->createMockShipment('OVERDUE002', MeestTrackingStatus::CREATED)
        ];

        $this->repository
            ->expects($this->once())
            ->method('findOverdueShipments')
            ->with($overdueDate)
            ->willReturn($overdueShipments);

        $result = $this->trackingService->getOverdueShipments($overdueDate);

        $this->assertCount(2, $result);
        $this->assertEquals($overdueShipments, $result);
    }

    /**
     * Test getting overdue shipments with default date
     */
    public function testGetOverdueShipmentsDefaultDate(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findOverdueShipments')
            ->with($this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturn([]);

        $result = $this->trackingService->getOverdueShipments();

        $this->assertIsArray($result);
    }

    /**
     * Test getting shipment statistics
     */
    public function testGetShipmentStatistics(): void
    {
        $from = new \DateTimeImmutable('-30 days');

        $statusStats = [
            'created' => 10,
            'in_transit' => 15,
            'delivered' => 25,
            'cancelled' => 2
        ];

        $costStats = [
            'total_cost' => 1250.75,
            'currency' => 'USD',
            'count' => 50
        ];

        $this->repository
            ->expects($this->once())
            ->method('getStatistics')
            ->with($from)
            ->willReturn($statusStats);

        $this->repository
            ->expects($this->once())
            ->method('getTotalCosts')
            ->with($from, $this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturn($costStats);

        $result = $this->trackingService->getShipmentStatistics($from);

        $this->assertArrayHasKey('status_counts', $result);
        $this->assertArrayHasKey('total_costs', $result);
        $this->assertArrayHasKey('period', $result);

        $this->assertEquals($statusStats, $result['status_counts']);
        $this->assertEquals($costStats, $result['total_costs']);
        $this->assertArrayHasKey('from', $result['period']);
        $this->assertArrayHasKey('to', $result['period']);
    }

    /**
     * Test getting shipment statistics with default date
     */
    public function testGetShipmentStatisticsDefaultDate(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getStatistics')
            ->with($this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturn([]);

        $this->repository
            ->expects($this->once())
            ->method('getTotalCosts')
            ->willReturn([]);

        $result = $this->trackingService->getShipmentStatistics();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status_counts', $result);
        $this->assertArrayHasKey('total_costs', $result);
        $this->assertArrayHasKey('period', $result);
    }

    /**
     * Test generating missing labels successfully
     */
    public function testGenerateMissingLabelsSuccess(): void
    {
        $shipment1 = $this->createMockShipment('BLP68A82A025DBC2PLTEST01', MeestTrackingStatus::CREATED);
        $shipment2 = $this->createMockShipment('BLP68A82A025DBC2PLTEST02', MeestTrackingStatus::IN_TRANSIT);

        // Mock query builder
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())->method('where')->willReturnSelf();
        $queryBuilder->expects($this->once())->method('andWhere')->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))->method('setParameter')->willReturnSelf();
        $queryBuilder->expects($this->once())->method('getQuery')->willReturn($query);

        $query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn([$shipment1, $shipment2]);

        // Mock label generation
        $this->apiClient
            ->expects($this->exactly(2))
            ->method('generateLabel')
            ->willReturnMap([
                ['BLP68A82A025DBC2PLTEST01', 'https://api.meest.com/labels/test01.pdf'],
                ['BLP68A82A025DBC2PLTEST02', 'https://api.meest.com/labels/test02.pdf']
            ]);

        // Expect both shipments to be updated
        $shipment1->expects($this->once())->method('setLabelUrl')->with('https://api.meest.com/labels/test01.pdf');
        $shipment2->expects($this->once())->method('setLabelUrl')->with('https://api.meest.com/labels/test02.pdf');

        $this->repository
            ->expects($this->exactly(2))
            ->method('save');

        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->with('Label generated for MEEST shipment', $this->anything());

        $generatedCount = $this->trackingService->generateMissingLabels();

        $this->assertEquals(2, $generatedCount);
    }

    /**
     * Test generating missing labels with some failures
     */
    public function testGenerateMissingLabelsWithFailures(): void
    {
        $shipment1 = $this->createMockShipment('BLP68A82A025DBC2PLTEST01', MeestTrackingStatus::CREATED);
        $shipment2 = $this->createMockShipment('BLP68A82A025DBC2PLTEST02', MeestTrackingStatus::IN_TRANSIT);

        // Mock query builder
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())->method('where')->willReturnSelf();
        $queryBuilder->expects($this->once())->method('andWhere')->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))->method('setParameter')->willReturnSelf();
        $queryBuilder->expects($this->once())->method('getQuery')->willReturn($query);

        $query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn([$shipment1, $shipment2]);

        // First label generation fails, second succeeds
        $this->apiClient
            ->expects($this->exactly(2))
            ->method('generateLabel')
            ->willReturnCallback(function ($trackingNumber) {
                if ($trackingNumber === 'BLP68A82A025DBC2PLTEST01') {
                    throw new MeestIntegrationException('Label generation failed');
                }
                return 'https://api.meest.com/labels/test02.pdf';
            });

        // Only second shipment should be updated
        $shipment1->expects($this->never())->method('setLabelUrl');
        $shipment2->expects($this->once())->method('setLabelUrl');

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($shipment2);

        // One error log and one success log
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to generate label for MEEST shipment', $this->anything());

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Label generated for MEEST shipment', $this->anything());

        $generatedCount = $this->trackingService->generateMissingLabels();

        $this->assertEquals(1, $generatedCount);
    }

    /**
     * Test custom update time for pending shipments
     */
    public function testUpdatePendingShipmentsCustomTime(): void
    {
        $customTime = new \DateTimeImmutable('-2 hours');

        $this->repository
            ->expects($this->once())
            ->method('findPendingTrackingUpdates')
            ->with($customTime)
            ->willReturn([]);

        $updateCount = $this->trackingService->updatePendingShipments($customTime);

        $this->assertEquals(0, $updateCount);
    }

    /**
     * Helper method to create mock shipment
     */
    private function createMockShipment(string $trackingNumber, MeestTrackingStatus $status): MockObject
    {
        $shipment = $this->createMock(MeestShipment::class);

        $shipment->method('getTrackingNumber')->willReturn($trackingNumber);
        $shipment->method('getStatus')->willReturn($status);
        $shipment->method('getMetadata')->willReturn([]);

        return $shipment;
    }
}