<?php

declare(strict_types=1);

namespace App\Domain\InPost\DTO;

class LockerDetailsDTO
{
    public string $name;
    public string $address;
    public float $latitude;
    public float $longitude;
    public string $status;
    public array $availableSizes = [];
    public bool $paymentAvailable = false;
    public array $openingHours = [];
    public string $type; // 'paczkomat' | 'pop'
    public ?string $description = null;
    public array $functions = [];
    public ?string $partner = null;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->name = $data['name'] ?? '';
        $dto->address = $data['address']['line1'] ?? '';
        $dto->latitude = $data['location']['latitude'] ?? 0.0;
        $dto->longitude = $data['location']['longitude'] ?? 0.0;
        $dto->status = $data['status'] ?? 'inactive';
        $dto->availableSizes = $data['available_sizes'] ?? [];
        $dto->paymentAvailable = $data['payment_available'] ?? false;
        $dto->openingHours = $data['opening_hours'] ?? [];
        $dto->type = $data['type'] ?? 'paczkomat';
        $dto->description = $data['description'] ?? null;
        $dto->functions = $data['functions'] ?? [];
        $dto->partner = $data['partner'] ?? null;

        return $dto;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => $this->status,
            'availableSizes' => $this->availableSizes,
            'paymentAvailable' => $this->paymentAvailable,
            'openingHours' => $this->openingHours,
            'type' => $this->type,
            'description' => $this->description,
            'functions' => $this->functions,
            'partner' => $this->partner,
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'operating';
    }

    public function supportsSize(string $size): bool
    {
        return in_array($size, $this->availableSizes, true);
    }

    public function supportsPayment(): bool
    {
        return $this->paymentAvailable;
    }

    public function getDistance(float $lat, float $lon): float
    {
        // Calculate distance using Haversine formula
        $earthRadius = 6371; // Earth's radius in kilometers

        $deltaLat = deg2rad($this->latitude - $lat);
        $deltaLon = deg2rad($this->longitude - $lon);

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
            cos(deg2rad($lat)) * cos(deg2rad($this->latitude)) *
            sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}