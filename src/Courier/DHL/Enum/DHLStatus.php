<?php

declare(strict_types=1);

namespace App\Courier\DHL\Enum;

enum DHLStatus: string
{
    case PRE_TRANSIT = 'pre-transit';
    case IN_TRANSIT = 'transit';
    case OUT_FOR_DELIVERY = 'out-for-delivery';
    case DELIVERED = 'delivered';
    case EXCEPTION = 'exception';
    case UNKNOWN = 'unknown';
    case FAILURE = 'failure';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::PRE_TRANSIT => 'Przygotowywane do wysyłki',
            self::IN_TRANSIT => 'W transporcie',
            self::OUT_FOR_DELIVERY => 'W doręczeniu',
            self::DELIVERED => 'Doręczona',
            self::EXCEPTION => 'Problem z doręczeniem',
            self::UNKNOWN => 'Status nieznany',
            self::FAILURE => 'Niepowodzenie',
        };
    }

    public function isDelivered(): bool
    {
        return $this === self::DELIVERED;
    }

    public function isInTransit(): bool
    {
        return in_array($this, [self::IN_TRANSIT, self::OUT_FOR_DELIVERY], true);
    }

    public function isFinalStatus(): bool
    {
        return in_array($this, [self::DELIVERED, self::FAILURE], true);
    }

    public function hasException(): bool
    {
        return in_array($this, [self::EXCEPTION, self::FAILURE], true);
    }

    public static function fromDHLStatusCode(string $statusCode): self
    {
        return match (strtolower($statusCode)) {
            'pre-transit', 'manifested', 'picked-up' => self::PRE_TRANSIT,
            'transit', 'in-transit', 'processed' => self::IN_TRANSIT,
            'out-for-delivery', 'with-delivery-courier' => self::OUT_FOR_DELIVERY,
            'delivered', 'ok' => self::DELIVERED,
            'exception', 'delivery-exception' => self::EXCEPTION,
            'failure', 'cancelled' => self::FAILURE,
            default => self::UNKNOWN,
        };
    }
}