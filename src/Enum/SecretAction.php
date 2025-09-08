<?php

declare(strict_types=1);

namespace App\Enum;

enum SecretAction: string
{
    case CREATED = 'created';
    case RETRIEVED = 'retrieved';
    case UPDATED = 'updated';
    case ROTATED = 'rotated';
    case DEACTIVATED = 'deactivated';
    case ACTIVATED = 'activated';
    case DELETED = 'deleted';
    case ACCESS_DENIED = 'access_denied';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::CREATED => 'Created',
            self::RETRIEVED => 'Retrieved',
            self::UPDATED => 'Updated',
            self::ROTATED => 'Rotated',
            self::DEACTIVATED => 'Deactivated',
            self::ACTIVATED => 'Activated',
            self::DELETED => 'Deleted',
            self::ACCESS_DENIED => 'Access Denied',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::CREATED => 'Secret was created',
            self::RETRIEVED => 'Secret was accessed/retrieved',
            self::UPDATED => 'Secret value was updated',
            self::ROTATED => 'Secret was rotated with new value',
            self::DEACTIVATED => 'Secret was deactivated',
            self::ACTIVATED => 'Secret was activated',
            self::DELETED => 'Secret was permanently deleted',
            self::ACCESS_DENIED => 'Access to secret was denied',
        };
    }

    public function getSeverityLevel(): string
    {
        return match ($this) {
            self::CREATED, self::UPDATED, self::ROTATED => 'high',
            self::DEACTIVATED, self::ACTIVATED, self::DELETED => 'critical',
            self::ACCESS_DENIED => 'warning',
            self::RETRIEVED => 'low',
        };
    }
}