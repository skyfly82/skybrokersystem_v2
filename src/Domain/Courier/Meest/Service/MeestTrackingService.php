<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Service;

use App\Domain\Courier\Meest\Entity\MeestShipment;
use App\Domain\Courier\Meest\Repository\MeestShipmentRepository;
use Psr\Log\LoggerInterface;

/**
 * Service for managing MEEST tracking updates
 */
class MeestTrackingService
{
    public function __construct(
        private readonly MeestApiClient $apiClient,
        private readonly MeestShipmentRepository $shipmentRepository,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Update tracking status for all pending shipments
     */
    public function updatePendingShipments(\DateTimeImmutable $updatedBefore = null): int
    {
        $updatedBefore = $updatedBefore ?? new \DateTimeImmutable('-1 hour');
        $shipments = $this->shipmentRepository->findPendingTrackingUpdates($updatedBefore);

        $updateCount = 0;

        foreach ($shipments as $shipment) {
            try {
                if ($this->updateShipmentTracking($shipment)) {
                    $updateCount++;
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to update MEEST shipment tracking', [
                    'tracking_number' => $shipment->getTrackingNumber(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->logger->info('MEEST tracking batch update completed', [
            'total_shipments' => count($shipments),
            'updated_count' => $updateCount
        ]);

        return $updateCount;
    }

    /**
     * Update tracking for a specific shipment
     */
    public function updateShipmentTracking(MeestShipment $shipment): bool
    {
        try {
            $trackingResponse = $this->apiClient->getTracking($shipment->getTrackingNumber());

            // Check if status has changed
            if ($trackingResponse->status !== $shipment->getStatus()) {
                $oldStatus = $shipment->getStatus();
                $shipment->updateStatus($trackingResponse->status);

                // Update estimated delivery if provided
                if ($trackingResponse->estimatedDelivery) {
                    $shipment->setEstimatedDelivery($trackingResponse->estimatedDelivery);
                }

                // Update metadata with latest tracking events
                $metadata = $shipment->getMetadata() ?? [];
                $metadata['tracking_events'] = $trackingResponse->trackingEvents;
                $metadata['last_location'] = $trackingResponse->location;
                $shipment->setMetadata($metadata);

                $this->shipmentRepository->save($shipment);

                $this->logger->info('MEEST shipment status updated', [
                    'tracking_number' => $shipment->getTrackingNumber(),
                    'old_status' => $oldStatus->value,
                    'new_status' => $trackingResponse->status->value
                ]);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            $this->logger->error('Failed to update MEEST shipment tracking', [
                'tracking_number' => $shipment->getTrackingNumber(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get overdue shipments that need attention
     */
    public function getOverdueShipments(\DateTimeImmutable $overdueDate = null): array
    {
        return $this->shipmentRepository->findOverdueShipments($overdueDate);
    }

    /**
     * Get shipment statistics
     */
    public function getShipmentStatistics(\DateTimeImmutable $from = null): array
    {
        $from = $from ?? new \DateTimeImmutable('-30 days');

        $stats = $this->shipmentRepository->getStatistics($from);
        $costs = $this->shipmentRepository->getTotalCosts($from, new \DateTimeImmutable());

        return [
            'status_counts' => $stats,
            'total_costs' => $costs,
            'period' => [
                'from' => $from->format('Y-m-d'),
                'to' => (new \DateTimeImmutable())->format('Y-m-d')
            ]
        ];
    }

    /**
     * Generate labels for shipments without labels
     */
    public function generateMissingLabels(): int
    {
        $shipments = $this->shipmentRepository->createQueryBuilder('s')
            ->where('s.labelUrl IS NULL OR s.labelUrl = :empty')
            ->andWhere('s.status != :cancelled')
            ->setParameter('empty', '')
            ->setParameter('cancelled', \App\Domain\Courier\Meest\Enum\MeestTrackingStatus::CANCELLED)
            ->getQuery()
            ->getResult();

        $generatedCount = 0;

        foreach ($shipments as $shipment) {
            try {
                $labelUrl = $this->apiClient->generateLabel($shipment->getTrackingNumber());
                $shipment->setLabelUrl($labelUrl);
                $this->shipmentRepository->save($shipment);

                $generatedCount++;

                $this->logger->info('Label generated for MEEST shipment', [
                    'tracking_number' => $shipment->getTrackingNumber()
                ]);

            } catch (\Exception $e) {
                $this->logger->error('Failed to generate label for MEEST shipment', [
                    'tracking_number' => $shipment->getTrackingNumber(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $generatedCount;
    }
}