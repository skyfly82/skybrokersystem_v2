<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CustomerAddressRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Customer Address Entity for Address Book Management
 * Stores frequently used sender and recipient addresses
 */
#[ORM\Entity(repositoryClass: CustomerAddressRepository::class)]
#[ORM\Table(name: 'v2_customer_addresses')]
#[ORM\Index(columns: ['customer_id', 'type'], name: 'idx_customer_address_type')]
#[ORM\Index(columns: ['customer_id', 'is_default'], name: 'idx_customer_default')]
#[ORM\Index(columns: ['postal_code'], name: 'idx_postal_code')]
class CustomerAddress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column(length: 100)]
    private string $name; // Address nickname/label

    #[ORM\Column(length: 50)]
    private string $type = 'both'; // 'sender', 'recipient', 'both'

    #[ORM\Column(length: 255)]
    private string $contactName;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyName = null;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 20)]
    private string $phone;

    #[ORM\Column(type: Types::TEXT)]
    private string $address;

    #[ORM\Column(length: 20)]
    private string $postalCode;

    #[ORM\Column(length: 100)]
    private string $city;

    #[ORM\Column(length: 100)]
    private string $country = 'Poland';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $additionalInfo = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isDefault = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isValidated = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $validationData = null; // Store validation results from courier APIs

    #[ORM\Column(type: Types::INTEGER)]
    private int $usageCount = 0; // Track how often this address is used

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): static
    {
        $this->customer = $customer;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getContactName(): string
    {
        return $this->contactName;
    }

    public function setContactName(string $contactName): static
    {
        $this->contactName = $contactName;
        return $this;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): static
    {
        $this->companyName = $companyName;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): static
    {
        $this->postalCode = $postalCode;
        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;
        return $this;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;
        return $this;
    }

    public function getAdditionalInfo(): ?string
    {
        return $this->additionalInfo;
    }

    public function setAdditionalInfo(?string $additionalInfo): static
    {
        $this->additionalInfo = $additionalInfo;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;
        return $this;
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

    public function isValidated(): bool
    {
        return $this->isValidated;
    }

    public function setIsValidated(bool $isValidated): static
    {
        $this->isValidated = $isValidated;
        return $this;
    }

    public function getValidationData(): ?array
    {
        return $this->validationData;
    }

    public function setValidationData(?array $validationData): static
    {
        $this->validationData = $validationData;
        return $this;
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function setUsageCount(int $usageCount): static
    {
        $this->usageCount = $usageCount;
        return $this;
    }

    public function incrementUsageCount(): static
    {
        $this->usageCount++;
        $this->lastUsedAt = new \DateTimeImmutable();
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

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }

    // Utility methods

    public function canBeUsedAsSender(): bool
    {
        return in_array($this->type, ['sender', 'both']);
    }

    public function canBeUsedAsRecipient(): bool
    {
        return in_array($this->type, ['recipient', 'both']);
    }

    public function getFullAddress(): string
    {
        return implode(', ', array_filter([
            $this->address,
            $this->postalCode,
            $this->city,
            $this->country,
        ]));
    }

    public function getFormattedContactInfo(): string
    {
        $contact = $this->contactName;
        if ($this->companyName) {
            $contact = "{$this->companyName} ({$this->contactName})";
        }
        return $contact;
    }

    public function toShipmentArray(string $role = 'sender'): array
    {
        return [
            "{$role}_name" => $this->contactName,
            "{$role}_email" => $this->email,
            "{$role}_phone" => $this->phone,
            "{$role}_address" => $this->address,
            "{$role}_postal_code" => $this->postalCode,
            "{$role}_city" => $this->city,
            "{$role}_country" => $this->country,
            "{$role}_company" => $this->companyName,
        ];
    }

    public function updateFromArray(array $data): static
    {
        $this->name = $data['name'] ?? $this->name;
        $this->type = $data['type'] ?? $this->type;
        $this->contactName = $data['contact_name'] ?? $this->contactName;
        $this->companyName = $data['company_name'] ?? $this->companyName;
        $this->email = $data['email'] ?? $this->email;
        $this->phone = $data['phone'] ?? $this->phone;
        $this->address = $data['address'] ?? $this->address;
        $this->postalCode = $data['postal_code'] ?? $this->postalCode;
        $this->city = $data['city'] ?? $this->city;
        $this->country = $data['country'] ?? $this->country;
        $this->additionalInfo = $data['additional_info'] ?? $this->additionalInfo;
        $this->isActive = $data['is_active'] ?? $this->isActive;

        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'contact_name' => $this->contactName,
            'company_name' => $this->companyName,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'country' => $this->country,
            'additional_info' => $this->additionalInfo,
            'is_default' => $this->isDefault,
            'is_active' => $this->isActive,
            'is_validated' => $this->isValidated,
            'usage_count' => $this->usageCount,
            'full_address' => $this->getFullAddress(),
            'formatted_contact' => $this->getFormattedContactInfo(),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'last_used_at' => $this->lastUsedAt?->format('Y-m-d H:i:s'),
        ];
    }
}