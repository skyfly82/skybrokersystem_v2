<?php

declare(strict_types=1);

namespace App\Domain\InPost\Enum;

enum ParcelSize: string
{
    case SMALL = 'small';
    case MEDIUM = 'medium';
    case LARGE = 'large';
    case XLARGE = 'xlarge';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::SMALL => 'Mała (8x38x64cm)',
            self::MEDIUM => 'Średnia (19x38x64cm)',
            self::LARGE => 'Duża (39x38x64cm)',
            self::XLARGE => 'Bardzo duża (41x38x64cm)',
        };
    }

    public function getDimensions(): array
    {
        return match ($this) {
            self::SMALL => ['width' => 8, 'height' => 38, 'length' => 64],
            self::MEDIUM => ['width' => 19, 'height' => 38, 'length' => 64],
            self::LARGE => ['width' => 39, 'height' => 38, 'length' => 64],
            self::XLARGE => ['width' => 41, 'height' => 38, 'length' => 64],
        };
    }

    public function getMaxWeight(): float
    {
        return match ($this) {
            self::SMALL => 1.0,
            self::MEDIUM => 5.0,
            self::LARGE => 15.0,
            self::XLARGE => 25.0,
        };
    }

    public function getVolume(): float
    {
        $dimensions = $this->getDimensions();
        return $dimensions['width'] * $dimensions['height'] * $dimensions['length'];
    }

    public static function getRecommendedSize(float $weight, ?array $dimensions = null): self
    {
        // First check by weight
        if ($weight <= 1.0) {
            $sizeByWeight = self::SMALL;
        } elseif ($weight <= 5.0) {
            $sizeByWeight = self::MEDIUM;
        } elseif ($weight <= 15.0) {
            $sizeByWeight = self::LARGE;
        } else {
            $sizeByWeight = self::XLARGE;
        }

        // If no dimensions provided, return size by weight
        if (!$dimensions || !isset($dimensions['width'], $dimensions['height'], $dimensions['length'])) {
            return $sizeByWeight;
        }

        // Check if dimensions fit in the recommended size
        $width = $dimensions['width'];
        $height = $dimensions['height'];
        $length = $dimensions['length'];

        // Try each size from smallest to largest
        foreach ([self::SMALL, self::MEDIUM, self::LARGE, self::XLARGE] as $size) {
            $sizeDimensions = $size->getDimensions();
            
            if ($width <= $sizeDimensions['width'] && 
                $height <= $sizeDimensions['height'] && 
                $length <= $sizeDimensions['length']) {
                // Return the larger of weight-based and dimension-based recommendation
                return $size->value >= $sizeByWeight->value ? $size : $sizeByWeight;
            }
        }

        // If dimensions don't fit any size, return XLARGE
        return self::XLARGE;
    }

    public function canFitDimensions(float $width, float $height, float $length): bool
    {
        $dimensions = $this->getDimensions();
        
        return $width <= $dimensions['width'] && 
               $height <= $dimensions['height'] && 
               $length <= $dimensions['length'];
    }
}