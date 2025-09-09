<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Courier service providers with their capabilities and configurations
 */
#[ORM\Entity(repositoryClass: \App\Domain\Pricing\Repository\CarrierRepository::class)]
#[ORM\Table(name: 'v2_carriers')]
#[ORM\Index(name: 'IDX_CARRIER_ACTIVE', columns: ['is_active'])]
#[ORM\Index(name: 'IDX_CARRIER_SORT', columns: ['sort_order'])]
class Carrier
{
    public const CARRIER_INPOST = 'INPOST';
    public const CARRIER_DHL = 'DHL';
    public const CARRIER_UPS = 'UPS';
    public const CARRIER_DPD = 'DPD';
    public const CARRIER_MEEST = 'MEEST';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(pattern: '/^[A-Z_]+$/', message: 'Carrier code must contain only uppercase letters and underscores')]
    private string $code;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    private ?string $logoUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    private ?string $apiEndpoint = null;

    /**
     * API configuration parameters for integration
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $apiConfig = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $defaultServiceType = null;

    /**
     * List of supported zone codes
     */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull]
    private array $supportedZones = [];

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 3, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $maxWeightKg = null;

    /**
     * Maximum dimensions [length, width, height] in cm
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $maxDimensionsCm = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, PricingTable>
     */
    #[ORM\OneToMany(mappedBy: 'carrier', targetEntity: PricingTable::class)]
    private Collection $pricingTables;

    /**
     * @var Collection<int, AdditionalService>
     */
    #[ORM\OneToMany(mappedBy: 'carrier', targetEntity: AdditionalService::class)]
    private Collection $additionalServices;

    public function __construct(string $code, string $name)
    {
        $this->pricingTables = new ArrayCollection();
        $this->additionalServices = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->code = strtoupper($code);
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper($code);
        $this->updateTimestamp();
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        $this->updateTimestamp();
        return $this;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): static
    {
        $this->logoUrl = $logoUrl;
        $this->updateTimestamp();
        return $this;
    }

    public function getApiEndpoint(): ?string
    {
        return $this->apiEndpoint;
    }

    public function setApiEndpoint(?string $apiEndpoint): static
    {
        $this->apiEndpoint = $apiEndpoint;
        $this->updateTimestamp();
        return $this;
    }

    public function getApiConfig(): ?array
    {
        return $this->apiConfig;
    }

    public function setApiConfig(?array $apiConfig): static
    {
        $this->apiConfig = $apiConfig;
        $this->updateTimestamp();
        return $this;
    }

    public function getApiConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->apiConfig[$key] ?? $default;
    }

    public function setApiConfigValue(string $key, mixed $value): static
    {
        if ($this->apiConfig === null) {
            $this->apiConfig = [];
        }
        $this->apiConfig[$key] = $value;
        $this->updateTimestamp();
        return $this;
    }

    public function getDefaultServiceType(): ?string
    {
        return $this->defaultServiceType;
    }

    public function setDefaultServiceType(?string $defaultServiceType): static
    {
        $this->defaultServiceType = $defaultServiceType;
        $this->updateTimestamp();
        return $this;
    }

    public function getSupportedZones(): array
    {
        return $this->supportedZones;
    }

    public function setSupportedZones(array $supportedZones): static
    {
        $this->supportedZones = $supportedZones;
        $this->updateTimestamp();
        return $this;
    }

    public function supportsZone(string $zoneCode): bool
    {
        return in_array($zoneCode, $this->supportedZones);
    }

    public function addSupportedZone(string $zoneCode): static
    {
        if (!$this->supportsZone($zoneCode)) {
            $this->supportedZones[] = $zoneCode;
            $this->updateTimestamp();
        }
        return $this;
    }

    public function removeSupportedZone(string $zoneCode): static
    {
        $this->supportedZones = array_filter($this->supportedZones, fn($zone) => $zone !== $zoneCode);
        $this->updateTimestamp();
        return $this;
    }

    public function getMaxWeightKg(): ?string
    {
        return $this->maxWeightKg;
    }

    public function setMaxWeightKg(?string $maxWeightKg): static
    {
        $this->maxWeightKg = $maxWeightKg;
        $this->updateTimestamp();
        return $this;
    }

    public function getMaxWeightKgFloat(): ?float
    {
        return $this->maxWeightKg ? (float)$this->maxWeightKg : null;
    }

    public function canHandleWeight(float $weightKg): bool
    {
        return $this->maxWeightKg === null || $weightKg <= (float)$this->maxWeightKg;
    }

    public function getMaxDimensionsCm(): ?array
    {
        return $this->maxDimensionsCm;
    }

    public function setMaxDimensionsCm(?array $maxDimensionsCm): static
    {
        $this->maxDimensionsCm = $maxDimensionsCm;
        $this->updateTimestamp();
        return $this;
    }

    public function getMaxLengthCm(): ?int
    {
        return $this->maxDimensionsCm[0] ?? null;
    }

    public function getMaxWidthCm(): ?int
    {
        return $this->maxDimensionsCm[1] ?? null;
    }

    public function getMaxHeightCm(): ?int
    {
        return $this->maxDimensionsCm[2] ?? null;
    }

    public function canHandleDimensions(int $lengthCm, int $widthCm, int $heightCm): bool
    {
        if ($this->maxDimensionsCm === null) {
            return true;
        }

        return $lengthCm <= $this->getMaxLengthCm() &&
               $widthCm <= $this->getMaxWidthCm() &&
               $heightCm <= $this->getMaxHeightCm();
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        $this->updateTimestamp();
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
        $this->updateTimestamp();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return Collection<int, PricingTable>
     */
    public function getPricingTables(): Collection
    {
        return $this->pricingTables;
    }

    public function addPricingTable(PricingTable $pricingTable): static
    {
        if (!$this->pricingTables->contains($pricingTable)) {
            $this->pricingTables->add($pricingTable);
            $pricingTable->setCarrier($this);
        }

        return $this;
    }

    public function removePricingTable(PricingTable $pricingTable): static
    {
        if ($this->pricingTables->removeElement($pricingTable)) {
            if ($pricingTable->getCarrier() === $this) {
                $pricingTable->setCarrier(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AdditionalService>
     */
    public function getAdditionalServices(): Collection
    {
        return $this->additionalServices;
    }

    public function addAdditionalService(AdditionalService $additionalService): static
    {
        if (!$this->additionalServices->contains($additionalService)) {
            $this->additionalServices->add($additionalService);
            $additionalService->setCarrier($this);
        }

        return $this;
    }

    public function removeAdditionalService(AdditionalService $additionalService): static
    {
        if ($this->additionalServices->removeElement($additionalService)) {
            if ($additionalService->getCarrier() === $this) {
                $additionalService->setCarrier(null);
            }
        }

        return $this;
    }

    public function hasApiIntegration(): bool
    {
        return $this->apiEndpoint !== null && $this->apiConfig !== null;
    }

    public function isInPost(): bool
    {
        return $this->code === self::CARRIER_INPOST;
    }

    public function isDHL(): bool
    {
        return $this->code === self::CARRIER_DHL;
    }

    public function isUPS(): bool
    {
        return $this->code === self::CARRIER_UPS;
    }

    public function isDPD(): bool
    {
        return $this->code === self::CARRIER_DPD;
    }

    public function isMeest(): bool
    {
        return $this->code === self::CARRIER_MEEST;
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->name, $this->code);
    }
}