<?php

declare(strict_types=1);

namespace App\Domain\Pricing\DTO;

use App\Domain\Pricing\Entity\Carrier;

/**
 * Carrier DTO for API responses
 */
class CarrierDTO
{
    public int $id;
    public string $code;
    public string $name;
    public ?string $logoUrl;
    public array $supportedZones;
    public ?float $maxWeightKg;
    public ?array $maxDimensionsCm;
    public bool $hasApiIntegration;
    public bool $isActive;
    public int $sortOrder;

    public static function fromEntity(Carrier $carrier): self
    {
        $dto = new self();
        $dto->id = $carrier->getId();
        $dto->code = $carrier->getCode();
        $dto->name = $carrier->getName();
        $dto->logoUrl = $carrier->getLogoUrl();
        $dto->supportedZones = $carrier->getSupportedZones();
        $dto->maxWeightKg = $carrier->getMaxWeightKg();
        $dto->maxDimensionsCm = $carrier->getMaxDimensionsCm();
        $dto->hasApiIntegration = $carrier->hasApiIntegration();
        $dto->isActive = $carrier->isActive();
        $dto->sortOrder = $carrier->getSortOrder();

        return $dto;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'logo_url' => $this->logoUrl,
            'supported_zones' => $this->supportedZones,
            'max_weight_kg' => $this->maxWeightKg,
            'max_dimensions_cm' => $this->maxDimensionsCm,
            'has_api_integration' => $this->hasApiIntegration,
            'is_active' => $this->isActive,
            'sort_order' => $this->sortOrder,
        ];
    }
}