<?php

declare(strict_types=1);

namespace App\Domain\Pricing\DTO;

use App\Domain\Pricing\Entity\PricingZone;

/**
 * Pricing zone DTO for API responses
 */
class PricingZoneDTO
{
    public int $id;
    public string $code;
    public string $name;
    public ?string $description;
    public string $zoneType;
    public ?array $countries;
    public bool $isActive;
    public int $sortOrder;

    public static function fromEntity(PricingZone $zone): self
    {
        $dto = new self();
        $dto->id = $zone->getId();
        $dto->code = $zone->getCode();
        $dto->name = $zone->getName();
        $dto->description = $zone->getDescription();
        $dto->zoneType = $zone->getZoneType();
        $dto->countries = $zone->getCountries();
        $dto->isActive = $zone->isActive();
        $dto->sortOrder = $zone->getSortOrder();

        return $dto;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'zone_type' => $this->zoneType,
            'countries' => $this->countries,
            'is_active' => $this->isActive,
            'sort_order' => $this->sortOrder,
        ];
    }
}