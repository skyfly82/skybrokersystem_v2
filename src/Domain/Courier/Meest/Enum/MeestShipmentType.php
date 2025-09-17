<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Enum;

/**
 * MEEST shipment types
 */
enum MeestShipmentType: string
{
    case STANDARD = 'standard';
    case RETURN = 'return';
    case EXPRESS = 'express';
    case ECONOMY = 'economy';

    public function getDescription(): string
    {
        return match ($this) {
            self::STANDARD => 'Standard delivery',
            self::RETURN => 'Return shipment',
            self::EXPRESS => 'Express delivery',
            self::ECONOMY => 'Economy delivery',
        };
    }

    public function getApiEndpoint(): string
    {
        return match ($this) {
            self::STANDARD, self::EXPRESS, self::ECONOMY => '/v2/api/parcels',
            self::RETURN => '/v2/api/parcels/return',
        };
    }

    public function isReturnShipment(): bool
    {
        return $this === self::RETURN;
    }
}