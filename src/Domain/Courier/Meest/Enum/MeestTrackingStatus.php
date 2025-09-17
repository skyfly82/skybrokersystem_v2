<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Enum;

/**
 * MEEST tracking statuses based on API documentation
 */
enum MeestTrackingStatus: string
{
    case CREATED = 'created';
    case ACCEPTED = 'accepted';
    case IN_TRANSIT = 'in_transit';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case DELIVERY_ATTEMPT = 'delivery_attempt';
    case EXCEPTION = 'exception';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';
    case PENDING_PICKUP = 'pending_pickup';
    case AT_SORTING_FACILITY = 'at_sorting_facility';
    case CUSTOMS_CLEARANCE = 'customs_clearance';
    case CUSTOMS_CLEARED = 'customs_cleared';
    case CUSTOMS_HELD = 'customs_held';
    case ARRIVED_AT_LOCAL_HUB = 'arrived_at_local_hub';

    public function getDescription(): string
    {
        return match ($this) {
            self::CREATED => 'Shipment created',
            self::ACCEPTED => 'Shipment accepted by courier',
            self::IN_TRANSIT => 'In transit',
            self::OUT_FOR_DELIVERY => 'Out for delivery',
            self::DELIVERED => 'Delivered',
            self::DELIVERY_ATTEMPT => 'Delivery attempt made',
            self::EXCEPTION => 'Exception occurred',
            self::RETURNED => 'Returned to sender',
            self::CANCELLED => 'Shipment cancelled',
            self::PENDING_PICKUP => 'Pending pickup',
            self::AT_SORTING_FACILITY => 'At sorting facility',
            self::CUSTOMS_CLEARANCE => 'In customs clearance',
            self::CUSTOMS_CLEARED => 'Customs cleared',
            self::CUSTOMS_HELD => 'Held at customs',
            self::ARRIVED_AT_LOCAL_HUB => 'Arrived at local HUB',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::DELIVERED,
            self::RETURNED,
            self::CANCELLED
        ]);
    }

    public function isInProgress(): bool
    {
        return in_array($this, [
            self::CREATED,
            self::ACCEPTED,
            self::IN_TRANSIT,
            self::OUT_FOR_DELIVERY,
            self::PENDING_PICKUP,
            self::AT_SORTING_FACILITY,
            self::CUSTOMS_CLEARANCE,
            self::CUSTOMS_CLEARED,
            self::ARRIVED_AT_LOCAL_HUB
        ]);
    }

    public function hasIssue(): bool
    {
        return in_array($this, [
            self::DELIVERY_ATTEMPT,
            self::EXCEPTION,
            self::CUSTOMS_HELD
        ]);
    }

    /**
     * Map MEEST API status to internal enum
     */
    public static function fromApiStatus(string $apiStatus): ?self
    {
        // Mapping based on MEEST API responses
        return match (strtolower($apiStatus)) {
            'created', 'new' => self::CREATED,
            'accepted', 'processed' => self::ACCEPTED,
            'in_transit', 'transit', 'shipped' => self::IN_TRANSIT,
            'out_for_delivery', 'on_delivery' => self::OUT_FOR_DELIVERY,
            'delivered', 'complete' => self::DELIVERED,
            'delivery_attempt', 'attempted' => self::DELIVERY_ATTEMPT,
            'exception', 'error' => self::EXCEPTION,
            'returned', 'return' => self::RETURNED,
            'cancelled', 'canceled' => self::CANCELLED,
            'pending_pickup', 'pickup' => self::PENDING_PICKUP,
            'sorting', 'at_facility' => self::AT_SORTING_FACILITY,
            'customs', 'customs_processing' => self::CUSTOMS_CLEARANCE,
            'customs_cleared', 'cleared' => self::CUSTOMS_CLEARED,
            'customs_held', 'held' => self::CUSTOMS_HELD,
            'arrived_at_local_hub', 'local_hub', '606' => self::ARRIVED_AT_LOCAL_HUB,
            default => null,
        };
    }
}