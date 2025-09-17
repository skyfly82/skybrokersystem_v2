<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Enum;

/**
 * MEEST supported countries with their currency codes
 */
enum MeestCountry: string
{
    case GERMANY = 'DE';
    case CZECH_REPUBLIC = 'CZ';
    case SLOVAKIA = 'SK';
    case HUNGARY = 'HU';
    case ROMANIA = 'RO';
    case LITHUANIA = 'LT';
    case LATVIA = 'LV';
    case ESTONIA = 'EE';
    case UKRAINE = 'UA';
    case BULGARIA = 'BG';
    case POLAND = 'PL';

    public function getName(): string
    {
        return match ($this) {
            self::GERMANY => 'Germany',
            self::CZECH_REPUBLIC => 'Czech Republic',
            self::SLOVAKIA => 'Slovakia',
            self::HUNGARY => 'Hungary',
            self::ROMANIA => 'Romania',
            self::LITHUANIA => 'Lithuania',
            self::LATVIA => 'Latvia',
            self::ESTONIA => 'Estonia',
            self::UKRAINE => 'Ukraine',
            self::BULGARIA => 'Bulgaria',
            self::POLAND => 'Poland',
        };
    }

    public function getCurrency(): string
    {
        return match ($this) {
            self::GERMANY => 'EUR',
            self::CZECH_REPUBLIC => 'CZK',
            self::SLOVAKIA => 'EUR',
            self::HUNGARY => 'HUF',
            self::ROMANIA => 'RON',
            self::LITHUANIA => 'EUR',
            self::LATVIA => 'EUR',
            self::ESTONIA => 'EUR',
            self::UKRAINE => 'UAH',
            self::BULGARIA => 'BGN',
            self::POLAND => 'PLN',
        };
    }

    public function isEuCountry(): bool
    {
        return in_array($this, [
            self::GERMANY,
            self::CZECH_REPUBLIC,
            self::SLOVAKIA,
            self::HUNGARY,
            self::ROMANIA,
            self::LITHUANIA,
            self::LATVIA,
            self::ESTONIA,
            self::BULGARIA,
            self::POLAND,
        ]);
    }

    public static function isSupported(string $countryCode): bool
    {
        return self::tryFrom($countryCode) !== null;
    }

    public static function getSupportedCountries(): array
    {
        return array_column(self::cases(), 'value');
    }
}