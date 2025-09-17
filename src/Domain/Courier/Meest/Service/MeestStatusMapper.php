<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Service;

use App\Domain\Courier\Meest\Enum\MeestTrackingStatus;

/**
 * Service for mapping MEEST API statuses to internal statuses
 */
class MeestStatusMapper
{
    /**
     * Status mapping from MEEST API to internal status
     */
    private const STATUS_MAPPING = [
        // MEEST API status => Internal MeestTrackingStatus
        'created' => MeestTrackingStatus::CREATED,
        'new' => MeestTrackingStatus::CREATED,
        'registered' => MeestTrackingStatus::CREATED,

        'accepted' => MeestTrackingStatus::ACCEPTED,
        'processed' => MeestTrackingStatus::ACCEPTED,
        'confirmed' => MeestTrackingStatus::ACCEPTED,

        'in_transit' => MeestTrackingStatus::IN_TRANSIT,
        'transit' => MeestTrackingStatus::IN_TRANSIT,
        'shipped' => MeestTrackingStatus::IN_TRANSIT,
        'on_route' => MeestTrackingStatus::IN_TRANSIT,

        'out_for_delivery' => MeestTrackingStatus::OUT_FOR_DELIVERY,
        'on_delivery' => MeestTrackingStatus::OUT_FOR_DELIVERY,
        'delivering' => MeestTrackingStatus::OUT_FOR_DELIVERY,

        'delivered' => MeestTrackingStatus::DELIVERED,
        'complete' => MeestTrackingStatus::DELIVERED,
        'completed' => MeestTrackingStatus::DELIVERED,
        'success' => MeestTrackingStatus::DELIVERED,

        'delivery_attempt' => MeestTrackingStatus::DELIVERY_ATTEMPT,
        'attempted' => MeestTrackingStatus::DELIVERY_ATTEMPT,
        'failed_delivery' => MeestTrackingStatus::DELIVERY_ATTEMPT,

        'exception' => MeestTrackingStatus::EXCEPTION,
        'error' => MeestTrackingStatus::EXCEPTION,
        'problem' => MeestTrackingStatus::EXCEPTION,
        'issue' => MeestTrackingStatus::EXCEPTION,

        'returned' => MeestTrackingStatus::RETURNED,
        'return' => MeestTrackingStatus::RETURNED,
        'return_to_sender' => MeestTrackingStatus::RETURNED,

        'cancelled' => MeestTrackingStatus::CANCELLED,
        'canceled' => MeestTrackingStatus::CANCELLED,
        'void' => MeestTrackingStatus::CANCELLED,

        'pending_pickup' => MeestTrackingStatus::PENDING_PICKUP,
        'pickup' => MeestTrackingStatus::PENDING_PICKUP,
        'ready_for_pickup' => MeestTrackingStatus::PENDING_PICKUP,

        'sorting' => MeestTrackingStatus::AT_SORTING_FACILITY,
        'at_facility' => MeestTrackingStatus::AT_SORTING_FACILITY,
        'at_sorting_facility' => MeestTrackingStatus::AT_SORTING_FACILITY,
        'hub' => MeestTrackingStatus::AT_SORTING_FACILITY,

        'customs' => MeestTrackingStatus::CUSTOMS_CLEARANCE,
        'customs_processing' => MeestTrackingStatus::CUSTOMS_CLEARANCE,
        'customs_inspection' => MeestTrackingStatus::CUSTOMS_CLEARANCE,

        'customs_cleared' => MeestTrackingStatus::CUSTOMS_CLEARED,
        'cleared' => MeestTrackingStatus::CUSTOMS_CLEARED,
        'customs_released' => MeestTrackingStatus::CUSTOMS_CLEARED,

        'customs_held' => MeestTrackingStatus::CUSTOMS_HELD,
        'held' => MeestTrackingStatus::CUSTOMS_HELD,
        'customs_detained' => MeestTrackingStatus::CUSTOMS_HELD,
        'customs_issue' => MeestTrackingStatus::CUSTOMS_HELD,

        'arrived_at_local_hub' => MeestTrackingStatus::ARRIVED_AT_LOCAL_HUB,
        'local_hub' => MeestTrackingStatus::ARRIVED_AT_LOCAL_HUB,
        '606' => MeestTrackingStatus::ARRIVED_AT_LOCAL_HUB,
        'at_local_hub' => MeestTrackingStatus::ARRIVED_AT_LOCAL_HUB,
    ];

    /**
     * Get status priority mapping for conflicting statuses
     */
    private function getStatusPriorityMap(): array
    {
        return [
            MeestTrackingStatus::DELIVERED->value => 100,
            MeestTrackingStatus::RETURNED->value => 95,
            MeestTrackingStatus::CANCELLED->value => 90,
            MeestTrackingStatus::EXCEPTION->value => 85,
            MeestTrackingStatus::CUSTOMS_HELD->value => 80,
            MeestTrackingStatus::DELIVERY_ATTEMPT->value => 75,
            MeestTrackingStatus::OUT_FOR_DELIVERY->value => 70,
            MeestTrackingStatus::CUSTOMS_CLEARED->value => 65,
            MeestTrackingStatus::CUSTOMS_CLEARANCE->value => 60,
            MeestTrackingStatus::IN_TRANSIT->value => 55,
            MeestTrackingStatus::AT_SORTING_FACILITY->value => 50,
            MeestTrackingStatus::ARRIVED_AT_LOCAL_HUB->value => 48,
            MeestTrackingStatus::PENDING_PICKUP->value => 45,
            MeestTrackingStatus::ACCEPTED->value => 40,
            MeestTrackingStatus::CREATED->value => 35,
        ];
    }

    /**
     * Map MEEST API status to internal status
     */
    public function mapApiStatus(string $apiStatus): ?MeestTrackingStatus
    {
        $normalizedStatus = strtolower(trim($apiStatus));

        return self::STATUS_MAPPING[$normalizedStatus] ?? null;
    }

    /**
     * Map multiple API statuses and return the most significant one
     */
    public function mapMultipleStatuses(array $apiStatuses): ?MeestTrackingStatus
    {
        $mappedStatuses = [];

        foreach ($apiStatuses as $apiStatus) {
            $mapped = $this->mapApiStatus($apiStatus);
            if ($mapped) {
                $mappedStatuses[] = $mapped;
            }
        }

        if (empty($mappedStatuses)) {
            return null;
        }

        // Return the status with highest priority
        $priorityMap = $this->getStatusPriorityMap();
        usort($mappedStatuses, fn($a, $b) =>
            ($priorityMap[$b->value] ?? 0) - ($priorityMap[$a->value] ?? 0)
        );

        return $mappedStatuses[0];
    }

    /**
     * Get status priority for sorting
     */
    public function getStatusPriority(MeestTrackingStatus $status): int
    {
        $priorityMap = $this->getStatusPriorityMap();
        return $priorityMap[$status->value] ?? 0;
    }

    /**
     * Check if status transition is valid
     */
    public function isValidTransition(
        MeestTrackingStatus $fromStatus,
        MeestTrackingStatus $toStatus
    ): bool {
        // Terminal statuses cannot transition to non-terminal
        if ($fromStatus->isTerminal() && !$toStatus->isTerminal()) {
            return false;
        }

        // Can't go backwards in priority (with some exceptions)
        $fromPriority = $this->getStatusPriority($fromStatus);
        $toPriority = $this->getStatusPriority($toStatus);

        // Allow transition to exception states
        if ($toStatus->hasIssue()) {
            return true;
        }

        // Allow transition to terminal states
        if ($toStatus->isTerminal()) {
            return true;
        }

        // Generally, can only move to higher priority status
        return $toPriority >= $fromPriority;
    }

    /**
     * Get all supported API statuses
     */
    public function getSupportedApiStatuses(): array
    {
        return array_keys(self::STATUS_MAPPING);
    }

    /**
     * Get status mapping for a specific internal status
     */
    public function getApiStatusesForInternalStatus(MeestTrackingStatus $status): array
    {
        return array_keys(
            array_filter(
                self::STATUS_MAPPING,
                fn($mappedStatus) => $mappedStatus === $status
            )
        );
    }

    /**
     * Normalize API status string
     */
    public function normalizeApiStatus(string $apiStatus): string
    {
        return strtolower(trim(str_replace(['-', '_', ' '], '_', $apiStatus)));
    }

    /**
     * Get human-readable status description for API status
     */
    public function getStatusDescription(string $apiStatus): string
    {
        $mapped = $this->mapApiStatus($apiStatus);

        return $mapped ? $mapped->getDescription() : "Unknown status: {$apiStatus}";
    }

    /**
     * Check if API status indicates a delivery issue
     */
    public function isIssueStatus(string $apiStatus): bool
    {
        $mapped = $this->mapApiStatus($apiStatus);

        return $mapped ? $mapped->hasIssue() : false;
    }

    /**
     * Check if API status indicates completion
     */
    public function isTerminalStatus(string $apiStatus): bool
    {
        $mapped = $this->mapApiStatus($apiStatus);

        return $mapped ? $mapped->isTerminal() : false;
    }

    /**
     * Get suggested next statuses for current status
     */
    public function getSuggestedNextStatuses(MeestTrackingStatus $currentStatus): array
    {
        $allStatuses = MeestTrackingStatus::cases();
        $validNext = [];

        foreach ($allStatuses as $status) {
            if ($this->isValidTransition($currentStatus, $status)) {
                $validNext[] = $status;
            }
        }

        return $validNext;
    }
}