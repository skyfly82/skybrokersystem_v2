<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Geographic zones for courier pricing differentiation
 * 
 * Defines geographic zones used to categorize shipping destinations
 * and apply different pricing structures based on location.
 */
#[ORM\Entity(repositoryClass: \App\Domain\Pricing\Repository\PricingZoneRepository::class)]
#[ORM\Table(name: 'v2_pricing_zones')]
#[ORM\Index(name: 'IDX_ZONE_TYPE', columns: ['zone_type'])]
#[ORM\Index(name: 'IDX_ZONE_ACTIVE', columns: ['is_active'])]
#[ORM\Index(name: 'IDX_ZONE_SORT', columns: ['sort_order'])]
class PricingZone
{
    public const ZONE_TYPE_LOCAL = 'local';
    public const ZONE_TYPE_NATIONAL = 'national'; 
    public const ZONE_TYPE_INTERNATIONAL = 'international';

    // Geographic Zone Constants
    public const ZONE_LOCAL = 'LOCAL';
    public const ZONE_DOMESTIC = 'DOMESTIC';
    public const ZONE_EU_WEST = 'EU_WEST';
    public const ZONE_EU_EAST = 'EU_EAST';
    public const ZONE_EUROPE = 'EUROPE';
    public const ZONE_WORLD = 'WORLD';

    public const AVAILABLE_ZONES = [
        self::ZONE_LOCAL,
        self::ZONE_DOMESTIC,
        self::ZONE_EU_WEST,
        self::ZONE_EU_EAST,
        self::ZONE_EUROPE,
        self::ZONE_WORLD,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    #[Assert\Regex(pattern: '/^[A-Z_]+$/', message: 'Zone code must contain only uppercase letters and underscores')]
    private string $code;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::ZONE_TYPE_LOCAL, self::ZONE_TYPE_NATIONAL, self::ZONE_TYPE_INTERNATIONAL])]
    private string $zoneType;

    /**
     * List of ISO country codes in this zone
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $countries = null;

    /**
     * Postal code patterns for automatic zone detection
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $postalCodePatterns = null;

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
    #[ORM\OneToMany(mappedBy: 'zone', targetEntity: PricingTable::class)]
    private Collection $pricingTables;

    public function __construct(string $code, string $name, string $zoneType)
    {
        $this->pricingTables = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->code = strtoupper($code);
        $this->name = $name;
        $this->setZoneType($zoneType);
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
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getZoneType(): string
    {
        return $this->zoneType;
    }

    public function setZoneType(string $zoneType): static
    {
        if (!in_array($zoneType, [self::ZONE_TYPE_LOCAL, self::ZONE_TYPE_NATIONAL, self::ZONE_TYPE_INTERNATIONAL])) {
            throw new \InvalidArgumentException('Invalid zone type');
        }
        $this->zoneType = $zoneType;
        return $this;
    }

    public function getCountries(): ?array
    {
        return $this->countries;
    }

    public function setCountries(?array $countries): static
    {
        $this->countries = $countries;
        return $this;
    }

    public function hasCountry(string $countryCode): bool
    {
        return $this->countries && in_array(strtoupper($countryCode), $this->countries);
    }

    public function getPostalCodePatterns(): ?array
    {
        return $this->postalCodePatterns;
    }

    public function setPostalCodePatterns(?array $patterns): static
    {
        $this->postalCodePatterns = $patterns;
        return $this;
    }

    public function matchesPostalCode(string $postalCode): bool
    {
        if (!$this->postalCodePatterns) {
            return false;
        }

        foreach ($this->postalCodePatterns as $pattern) {
            if (preg_match($pattern, $postalCode)) {
                return true;
            }
        }

        return false;
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
            $pricingTable->setZone($this);
        }

        return $this;
    }

    public function removePricingTable(PricingTable $pricingTable): static
    {
        if ($this->pricingTables->removeElement($pricingTable)) {
            if ($pricingTable->getZone() === $this) {
                $pricingTable->setZone(null);
            }
        }

        return $this;
    }

    public function isLocal(): bool
    {
        return $this->zoneType === self::ZONE_TYPE_LOCAL;
    }

    public function isNational(): bool
    {
        return $this->zoneType === self::ZONE_TYPE_NATIONAL;
    }

    public function isInternational(): bool
    {
        return $this->zoneType === self::ZONE_TYPE_INTERNATIONAL;
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->name, $this->code);
    }
}