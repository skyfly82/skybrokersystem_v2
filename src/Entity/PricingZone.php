<?php

declare(strict_types=1);

namespace App\Entity;

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
#[ORM\Entity]
#[ORM\Table(name: 'v2_pricing_zones')]
#[ORM\Index(name: 'IDX_ZONE_TYPE', columns: ['zone_type'])]
#[ORM\Index(name: 'IDX_ZONE_ACTIVE', columns: ['is_active'])]
#[ORM\Index(name: 'IDX_ZONE_SORT', columns: ['sort_order'])]
class PricingZone
{
    public const ZONE_TYPE_LOCAL = 'local';
    public const ZONE_TYPE_NATIONAL = 'national'; 
    public const ZONE_TYPE_INTERNATIONAL = 'international';

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

    #[ORM\Column(type: Types::STRING, enumType: self::class)]
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

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, PricingTable>
     */
    #[ORM\OneToMany(mappedBy: 'zone', targetEntity: PricingTable::class)]
    private Collection $pricingTables;

    public function __construct()
    {
        $this->pricingTables = new ArrayCollection();
        $this->createdAt = new \DateTime();
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

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime();
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