<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\SystemUser;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Audit trail for customer pricing changes
 * 
 * Tracks all modifications to customer pricing agreements
 * for compliance and historical tracking purposes.
 */
#[ORM\Entity(repositoryClass: \App\Domain\Pricing\Repository\CustomerPricingAuditRepository::class)]
#[ORM\Table(name: 'v2_customer_pricing_audit')]
#[ORM\Index(name: 'IDX_PRICING_AUDIT_CUSTOMER_PRICING', columns: ['customer_pricing_id'])]
#[ORM\Index(name: 'IDX_PRICING_AUDIT_ACTION', columns: ['action'])]
#[ORM\Index(name: 'IDX_PRICING_AUDIT_DATE', columns: ['created_at'])]
#[ORM\Index(name: 'IDX_PRICING_AUDIT_USER', columns: ['created_by_id'])]
class CustomerPricingAudit
{
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_ACTIVATED = 'activated';
    public const ACTION_DEACTIVATED = 'deactivated';
    public const ACTION_EXPIRED = 'expired';
    public const ACTION_RENEWED = 'renewed';
    public const ACTION_DELETED = 'deleted';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CustomerPricing::class, inversedBy: 'auditLogs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?CustomerPricing $customerPricing = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::ACTION_CREATED,
        self::ACTION_UPDATED,
        self::ACTION_ACTIVATED,
        self::ACTION_DEACTIVATED,
        self::ACTION_EXPIRED,
        self::ACTION_RENEWED,
        self::ACTION_DELETED
    ])]
    private string $action;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Changed field names and their old values
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $oldValues = null;

    /**
     * Changed field names and their new values
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $newValues = null;

    /**
     * Additional metadata about the change
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    /**
     * User agent or system information
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    /**
     * IP address of the user making the change
     */
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\ManyToOne(targetEntity: SystemUser::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?SystemUser $createdBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerPricing(): ?CustomerPricing
    {
        return $this->customerPricing;
    }

    public function setCustomerPricing(?CustomerPricing $customerPricing): static
    {
        $this->customerPricing = $customerPricing;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
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

    public function getOldValues(): ?array
    {
        return $this->oldValues;
    }

    public function setOldValues(?array $oldValues): static
    {
        $this->oldValues = $oldValues;
        return $this;
    }

    public function getNewValues(): ?array
    {
        return $this->newValues;
    }

    public function setNewValues(?array $newValues): static
    {
        $this->newValues = $newValues;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function setMetadataValue(string $key, mixed $value): static
    {
        if ($this->metadata === null) {
            $this->metadata = [];
        }
        $this->metadata[$key] = $value;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
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

    public function getCreatedBy(): ?SystemUser
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?SystemUser $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * Get changed fields summary
     */
    public function getChangedFields(): array
    {
        $fields = [];
        
        if ($this->oldValues && $this->newValues) {
            foreach ($this->newValues as $field => $newValue) {
                $oldValue = $this->oldValues[$field] ?? null;
                if ($oldValue !== $newValue) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * Get summary of changes
     */
    public function getChangesSummary(): string
    {
        $changedFields = $this->getChangedFields();
        
        if (empty($changedFields)) {
            return $this->action;
        }

        return sprintf('%s: %s', $this->action, implode(', ', $changedFields));
    }

    /**
     * Check if specific field was changed
     */
    public function wasFieldChanged(string $fieldName): bool
    {
        return in_array($fieldName, $this->getChangedFields());
    }

    /**
     * Get old value for specific field
     */
    public function getOldValue(string $fieldName): mixed
    {
        return $this->oldValues[$fieldName] ?? null;
    }

    /**
     * Get new value for specific field
     */
    public function getNewValue(string $fieldName): mixed
    {
        return $this->newValues[$fieldName] ?? null;
    }

    public function isCreatedAction(): bool
    {
        return $this->action === self::ACTION_CREATED;
    }

    public function isUpdatedAction(): bool
    {
        return $this->action === self::ACTION_UPDATED;
    }

    public function isActivatedAction(): bool
    {
        return $this->action === self::ACTION_ACTIVATED;
    }

    public function isDeactivatedAction(): bool
    {
        return $this->action === self::ACTION_DEACTIVATED;
    }

    public function isExpiredAction(): bool
    {
        return $this->action === self::ACTION_EXPIRED;
    }

    public function isRenewedAction(): bool
    {
        return $this->action === self::ACTION_RENEWED;
    }

    public function isDeletedAction(): bool
    {
        return $this->action === self::ACTION_DELETED;
    }

    /**
     * Create audit log for creation
     */
    public static function createAuditLog(
        CustomerPricing $customerPricing,
        string $action,
        ?SystemUser $user = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): self {
        $audit = new self();
        $audit->setCustomerPricing($customerPricing);
        $audit->setAction($action);
        $audit->setCreatedBy($user);
        $audit->setDescription($description);
        $audit->setOldValues($oldValues);
        $audit->setNewValues($newValues);

        return $audit;
    }

    public function __toString(): string
    {
        return sprintf('%s - %s (%s)', 
            $this->customerPricing?->getContractName() ?? 'Unknown Contract',
            $this->action,
            $this->createdAt->format('Y-m-d H:i:s')
        );
    }
}