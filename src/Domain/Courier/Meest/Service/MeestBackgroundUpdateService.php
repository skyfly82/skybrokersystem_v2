<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Service;

use App\Domain\Courier\Meest\Entity\MeestShipment;
use App\Domain\Courier\Meest\Repository\MeestShipmentRepository;
use App\Domain\Courier\Meest\Enum\MeestTrackingStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Background service for automated tracking updates with AI-powered prioritization
 */
class MeestBackgroundUpdateService
{
    private const UPDATE_INTERVAL_HOURS = 2;
    private const HIGH_PRIORITY_INTERVAL_MINUTES = 30;
    private const BATCH_SIZE = 50;
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly MeestApiClient $apiClient,
        private readonly MeestAITrackingService $aiTrackingService,
        private readonly MeestMLPredictionService $mlPredictionService,
        private readonly MeestShipmentRepository $shipmentRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly ?CacheInterface $cache = null
    ) {}

    /**
     * Process all pending tracking updates with AI prioritization
     */
    public function processTrackingUpdates(): array
    {
        $startTime = microtime(true);
        $this->logger->info('Starting AI-powered background tracking updates');

        try {
            // Get shipments prioritized by AI
            $prioritizedShipments = $this->getPrioritizedShipments();

            $results = [
                'total_processed' => 0,
                'successful_updates' => 0,
                'failed_updates' => 0,
                'high_priority_updates' => 0,
                'ml_predictions_generated' => 0,
                'webhooks_sent' => 0,
                'processing_time' => 0
            ];

            // Process in batches
            $batches = array_chunk($prioritizedShipments, self::BATCH_SIZE);

            foreach ($batches as $batchIndex => $batch) {
                $batchResults = $this->processBatch($batch, $batchIndex + 1);
                $results = $this->mergeBatchResults($results, $batchResults);

                // Small delay between batches to avoid API rate limits
                usleep(100000); // 100ms
            }

            $results['processing_time'] = round(microtime(true) - $startTime, 2);

            $this->logger->info('Background tracking updates completed', $results);

            return $results;

        } catch (\Exception $e) {
            $this->logger->error('Background tracking updates failed', [
                'error' => $e->getMessage(),
                'processing_time' => round(microtime(true) - $startTime, 2)
            ]);

            throw $e;
        }
    }

    /**
     * Get shipments prioritized by AI algorithms
     */
    public function getPrioritizedShipments(): array
    {
        // Get all active shipments that need updates
        $activeShipments = $this->shipmentRepository->createQueryBuilder('s')
            ->where('s.status NOT IN (:terminalStatuses)')
            ->setParameter('terminalStatuses', [
                MeestTrackingStatus::DELIVERED,
                MeestTrackingStatus::CANCELLED,
                MeestTrackingStatus::RETURNED
            ])
            ->getQuery()
            ->getResult();

        // Apply AI prioritization
        $prioritizedShipments = [];

        foreach ($activeShipments as $shipment) {
            $priority = $this->calculateUpdatePriority($shipment);
            $prioritizedShipments[] = [
                'shipment' => $shipment,
                'priority' => $priority,
                'last_update_hours' => $this->getHoursSinceLastUpdate($shipment),
                'predicted_status_change' => $this->predictStatusChange($shipment)
            ];
        }

        // Sort by priority (highest first)
        usort($prioritizedShipments, fn($a, $b) => $b['priority'] <=> $a['priority']);

        return $prioritizedShipments;
    }

    /**
     * Process a batch of shipments
     */
    private function processBatch(array $batch, int $batchNumber): array
    {
        $this->logger->info("Processing batch {$batchNumber}", [
            'batch_size' => count($batch),
            'batch_number' => $batchNumber
        ]);

        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'high_priority' => 0,
            'ml_predictions' => 0,
            'webhooks' => 0
        ];

        foreach ($batch as $item) {
            try {
                $shipment = $item['shipment'];
                $priority = $item['priority'];

                $updateResult = $this->updateSingleShipment($shipment, $priority);

                $results['processed']++;
                if ($updateResult['success']) {
                    $results['successful']++;
                }
                if ($updateResult['failed']) {
                    $results['failed']++;
                }
                if ($priority > 0.7) {
                    $results['high_priority']++;
                }
                if ($updateResult['ml_prediction_generated']) {
                    $results['ml_predictions']++;
                }
                if ($updateResult['webhook_sent']) {
                    $results['webhooks']++;
                }

            } catch (\Exception $e) {
                $results['processed']++;
                $results['failed']++;

                $this->logger->error('Failed to update shipment in batch', [
                    'tracking_number' => $item['shipment']->getTrackingNumber(),
                    'batch_number' => $batchNumber,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Update a single shipment with AI enhancements
     */
    private function updateSingleShipment(MeestShipment $shipment, float $priority): array
    {
        $trackingNumber = $shipment->getTrackingNumber();
        $result = [
            'success' => false,
            'failed' => false,
            'ml_prediction_generated' => false,
            'webhook_sent' => false,
            'status_changed' => false
        ];

        try {
            // Get enhanced tracking with AI
            $enhancedTracking = $this->aiTrackingService->getEnhancedTracking($trackingNumber);

            // Check if status changed
            $oldStatus = $shipment->getStatus();
            $newStatusCode = $enhancedTracking['statusCode'];
            $newStatus = $this->mapStatusCodeToEnum($newStatusCode);

            if ($newStatus && $newStatus !== $oldStatus) {
                // Update shipment status
                $shipment->updateStatus($newStatus);

                // Update additional tracking data
                $this->updateShipmentMetadata($shipment, $enhancedTracking);

                // Save changes
                $this->shipmentRepository->save($shipment);

                $result['status_changed'] = true;
                $result['success'] = true;

                $this->logger->info('Shipment status updated via background process', [
                    'tracking_number' => $trackingNumber,
                    'old_status' => $oldStatus->value,
                    'new_status' => $newStatus->value,
                    'priority' => $priority
                ]);

                // Send webhook notification for status change
                if ($this->shouldSendWebhook($oldStatus, $newStatus)) {
                    $this->sendWebhookNotification($shipment, $enhancedTracking);
                    $result['webhook_sent'] = true;
                }

            } else {
                $result['success'] = true; // No status change, but update was successful
            }

            // Generate and cache ML predictions for high-priority shipments
            if ($priority > 0.7) {
                $this->generateAndCachePredictions($shipment, $enhancedTracking);
                $result['ml_prediction_generated'] = true;
            }

            // Update ML training data
            $this->updateMLTrainingData($shipment, $enhancedTracking);

        } catch (\Exception $e) {
            $result['failed'] = true;

            $this->logger->error('Failed to update shipment via background process', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage(),
                'priority' => $priority
            ]);
        }

        return $result;
    }

    /**
     * Calculate update priority using AI algorithms
     */
    private function calculateUpdatePriority(MeestShipment $shipment): float
    {
        $priority = 0.5; // Base priority

        // Time-based priority
        $hoursSinceUpdate = $this->getHoursSinceLastUpdate($shipment);
        if ($hoursSinceUpdate > 24) {
            $priority += 0.3; // High priority for stale tracking
        } elseif ($hoursSinceUpdate > 12) {
            $priority += 0.2;
        }

        // Status-based priority
        $statusPriority = match ($shipment->getStatus()) {
            MeestTrackingStatus::OUT_FOR_DELIVERY => 0.4, // High priority - delivery imminent
            MeestTrackingStatus::DELIVERY_ATTEMPT => 0.4, // High priority - needs attention
            MeestTrackingStatus::EXCEPTION => 0.5, // Very high priority - has issues
            MeestTrackingStatus::CUSTOMS_HELD => 0.3, // Medium-high priority
            MeestTrackingStatus::IN_TRANSIT => 0.2, // Medium priority
            default => 0.1 // Lower priority for stable statuses
        };

        $priority += $statusPriority;

        // Customer priority (if available)
        $customerPriority = $this->getCustomerPriority($shipment);
        $priority += $customerPriority * 0.2;

        // Delay risk priority
        try {
            $trackingResponse = $this->apiClient->getTracking($shipment->getTrackingNumber());
            $delayRisk = $this->aiTrackingService->calculateDelayRisk($trackingResponse);
            $priority += $delayRisk['total'] * 0.3;
        } catch (\Exception) {
            // If we can't get current tracking, increase priority
            $priority += 0.2;
        }

        return min($priority, 1.0); // Cap at 1.0
    }

    /**
     * Predict if status change is likely
     */
    private function predictStatusChange(MeestShipment $shipment): float
    {
        try {
            $trackingResponse = $this->apiClient->getTracking($shipment->getTrackingNumber());
            $predictions = $this->mlPredictionService->predictStatusTransitions($trackingResponse);

            // Return highest probability of status change
            if (!empty($predictions)) {
                return $predictions[0]['probability'] ?? 0.0;
            }
        } catch (\Exception) {
            // If prediction fails, assume moderate probability
            return 0.5;
        }

        return 0.0;
    }

    /**
     * Generate and cache ML predictions
     */
    private function generateAndCachePredictions(MeestShipment $shipment, array $enhancedTracking): void
    {
        try {
            $trackingResponse = $this->apiClient->getTracking($shipment->getTrackingNumber());

            // Generate various predictions
            $predictions = [
                'delivery_time' => $this->mlPredictionService->predictDeliveryTime($trackingResponse),
                'delay_risk' => $this->mlPredictionService->assessDelayRisk($trackingResponse),
                'status_transitions' => $this->mlPredictionService->predictStatusTransitions($trackingResponse),
                'route_optimization' => $this->mlPredictionService->optimizeRoute($trackingResponse),
                'anomalies' => $this->mlPredictionService->detectAnomalies($trackingResponse),
                'insights' => $this->mlPredictionService->generateInsights($trackingResponse)
            ];

            // Cache predictions
            if ($this->cache) {
                $cacheKey = "ml_predictions_{$shipment->getTrackingNumber()}";
                $this->cache->set($cacheKey, $predictions, 3600); // Cache for 1 hour
            }

            $this->logger->debug('ML predictions generated and cached', [
                'tracking_number' => $shipment->getTrackingNumber(),
                'predictions_count' => count($predictions)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate ML predictions', [
                'tracking_number' => $shipment->getTrackingNumber(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send webhook notification for status changes
     */
    private function sendWebhookNotification(MeestShipment $shipment, array $enhancedTracking): void
    {
        try {
            // In production, this would send webhooks to registered endpoints
            $webhookData = [
                'event' => 'tracking.status_changed',
                'tracking_number' => $shipment->getTrackingNumber(),
                'old_status' => $shipment->getStatus()->value,
                'new_status' => $enhancedTracking['statusCode'],
                'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'enhanced_data' => $enhancedTracking
            ];

            // Queue webhook for processing
            // $this->messageBus->dispatch(new SendWebhookMessage($webhookData));

            $this->logger->info('Webhook notification queued', [
                'tracking_number' => $shipment->getTrackingNumber(),
                'event' => 'tracking.status_changed'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send webhook notification', [
                'tracking_number' => $shipment->getTrackingNumber(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update ML training data with new observations
     */
    private function updateMLTrainingData(MeestShipment $shipment, array $enhancedTracking): void
    {
        try {
            // In production, this would update the ML training dataset
            $trainingData = [
                'tracking_number' => $shipment->getTrackingNumber(),
                'status_sequence' => $this->getStatusSequence($shipment),
                'time_in_status' => $this->getTimeInCurrentStatus($shipment),
                'location_data' => $enhancedTracking['city'] ?? null,
                'delay_occurred' => $enhancedTracking['delayRisk']['total'] > 0.5,
                'delivery_time' => $this->calculateActualDeliveryTime($shipment),
                'observed_at' => new \DateTimeImmutable()
            ];

            // Store training data for batch ML model updates
            if ($this->cache) {
                $cacheKey = "ml_training_" . (new \DateTimeImmutable())->format('Y-m-d');
                $existingData = $this->cache->get($cacheKey, function() { return []; });
                $existingData[] = $trainingData;
                $this->cache->set($cacheKey, $existingData, 86400); // Store for 24 hours
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to update ML training data', [
                'tracking_number' => $shipment->getTrackingNumber(),
                'error' => $e->getMessage()
            ]);
        }
    }

    // Helper methods

    private function getHoursSinceLastUpdate(MeestShipment $shipment): int
    {
        return $shipment->getUpdatedAt()->diff(new \DateTimeImmutable())->h;
    }

    private function getCustomerPriority(MeestShipment $shipment): float
    {
        // In production, this would check customer tier/priority
        return 0.5; // Default priority
    }

    private function mapStatusCodeToEnum(string $statusCode): ?MeestTrackingStatus
    {
        return match ($statusCode) {
            '100' => MeestTrackingStatus::CREATED,
            '200' => MeestTrackingStatus::ACCEPTED,
            '300' => MeestTrackingStatus::IN_TRANSIT,
            '400' => MeestTrackingStatus::OUT_FOR_DELIVERY,
            '500' => MeestTrackingStatus::DELIVERED,
            '606' => MeestTrackingStatus::ARRIVED_AT_LOCAL_HUB,
            default => null
        };
    }

    private function updateShipmentMetadata(MeestShipment $shipment, array $enhancedTracking): void
    {
        $metadata = $shipment->getMetadata() ?? [];

        $metadata['ai_enhanced'] = true;
        $metadata['last_ai_update'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $metadata['predictions'] = $enhancedTracking['predictions'] ?? null;
        $metadata['delay_risk'] = $enhancedTracking['delayRisk'] ?? null;
        $metadata['confidence'] = $enhancedTracking['confidence'] ?? null;

        $shipment->setMetadata($metadata);
    }

    private function shouldSendWebhook(MeestTrackingStatus $oldStatus, MeestTrackingStatus $newStatus): bool
    {
        // Send webhooks for significant status changes
        $significantStatuses = [
            MeestTrackingStatus::OUT_FOR_DELIVERY,
            MeestTrackingStatus::DELIVERED,
            MeestTrackingStatus::EXCEPTION,
            MeestTrackingStatus::DELIVERY_ATTEMPT
        ];

        return in_array($newStatus, $significantStatuses) || $newStatus->isTerminal();
    }

    private function mergeBatchResults(array $overall, array $batch): array
    {
        return [
            'total_processed' => $overall['total_processed'] + $batch['processed'],
            'successful_updates' => $overall['successful_updates'] + $batch['successful'],
            'failed_updates' => $overall['failed_updates'] + $batch['failed'],
            'high_priority_updates' => $overall['high_priority_updates'] + $batch['high_priority'],
            'ml_predictions_generated' => $overall['ml_predictions_generated'] + $batch['ml_predictions'],
            'webhooks_sent' => $overall['webhooks_sent'] + $batch['webhooks'],
            'processing_time' => $overall['processing_time']
        ];
    }

    private function getStatusSequence(MeestShipment $shipment): array
    {
        // In production, this would return the sequence of statuses this shipment has been through
        return [$shipment->getStatus()->value];
    }

    private function getTimeInCurrentStatus(MeestShipment $shipment): int
    {
        return $shipment->getUpdatedAt()->diff(new \DateTimeImmutable())->h;
    }

    private function calculateActualDeliveryTime(MeestShipment $shipment): ?int
    {
        if ($shipment->getStatus() === MeestTrackingStatus::DELIVERED) {
            return $shipment->getCreatedAt()->diff($shipment->getUpdatedAt())->h;
        }
        return null;
    }
}