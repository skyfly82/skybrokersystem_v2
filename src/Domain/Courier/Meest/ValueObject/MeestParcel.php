<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\ValueObject;

use App\Domain\Courier\Meest\Exception\MeestValidationException;

/**
 * Value Object representing MEEST parcel details
 */
readonly class MeestParcel
{
    public function __construct(
        public float $weight,
        public float $length,
        public float $width,
        public float $height,
        public float $value,
        public string $currency,
        public string $contents,
        public ?string $description = null
    ) {
        $this->validateParcel();
    }

    private function validateParcel(): void
    {
        if ($this->weight <= 0) {
            throw new MeestValidationException('Weight must be greater than 0');
        }

        if ($this->length <= 0 || $this->width <= 0 || $this->height <= 0) {
            throw new MeestValidationException('Dimensions must be greater than 0');
        }

        if ($this->value <= 0) {
            throw new MeestValidationException('Value must be greater than 0');
        }

        if (strlen($this->currency) !== 3) {
            throw new MeestValidationException('Currency must be a valid 3-letter ISO code');
        }

        if (empty($this->contents)) {
            throw new MeestValidationException('Contents description cannot be empty');
        }
    }

    public function getVolumetricWeight(): float
    {
        // Standard volumetric weight calculation (L x W x H / 5000)
        return ($this->length * $this->width * $this->height) / 5000;
    }

    public function getBillableWeight(): float
    {
        return max($this->weight, $this->getVolumetricWeight());
    }

    public function toArray(): array
    {
        return [
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'value' => $this->value,
            'currency' => $this->currency,
            'contents' => $this->contents,
            'description' => $this->description
        ];
    }
}