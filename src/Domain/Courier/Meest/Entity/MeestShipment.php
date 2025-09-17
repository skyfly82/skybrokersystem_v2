<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Entity;

use App\Domain\Courier\Meest\Enum\MeestShipmentType;
use App\Domain\Courier\Meest\Enum\MeestTrackingStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * MEEST Shipment Entity
 */
#[ORM\Entity]
#[ORM\Table(name: 'meest_shipments')]
#[ORM\Index(columns: ['tracking_number'], name: 'idx_meest_tracking_number')]
#[ORM\Index(columns: ['status'], name: 'idx_meest_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_meest_created_at')]
class MeestShipment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private string $id;

    #[ORM\Column(length: 100, unique: true)]
    private string $trackingNumber;

    #[ORM\Column(length: 100)]
    private string $shipmentId;

    #[ORM\Column(length: 20, enumType: MeestShipmentType::class)]
    private MeestShipmentType $shipmentType;

    #[ORM\Column(length: 30, enumType: MeestTrackingStatus::class)]
    private MeestTrackingStatus $status;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalCost;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $labelUrl = null;

    #[ORM\Column(type: Types::JSON)]
    private array $senderData;

    #[ORM\Column(type: Types::JSON)]
    private array $recipientData;

    #[ORM\Column(type: Types::JSON)]
    private array $parcelData;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $specialInstructions = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $estimatedDelivery = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    public function __construct(
        string $trackingNumber,
        string $shipmentId,
        MeestShipmentType $shipmentType,
        array $senderData,
        array $recipientData,
        array $parcelData
    ) {
        $this->id = Uuid::v4()->toRfc4122();
        $this->trackingNumber = $trackingNumber;
        $this->shipmentId = $shipmentId;
        $this->shipmentType = $shipmentType;
        $this->status = MeestTrackingStatus::CREATED;
        $this->senderData = $senderData;
        $this->recipientData = $recipientData;
        $this->parcelData = $parcelData;
        $this->totalCost = '0.00';
        $this->currency = 'EUR';
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getTrackingNumber(): string
    {
        return $this->trackingNumber;
    }

    public function getShipmentId(): string
    {
        return $this->shipmentId;
    }

    public function getShipmentType(): MeestShipmentType
    {
        return $this->shipmentType;
    }

    public function getStatus(): MeestTrackingStatus
    {
        return $this->status;
    }

    public function getTotalCost(): string
    {
        return $this->totalCost;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getLabelUrl(): ?string
    {
        return $this->labelUrl;
    }

    public function getSenderData(): array
    {
        return $this->senderData;
    }

    public function getRecipientData(): array
    {
        return $this->recipientData;
    }

    public function getParcelData(): array
    {
        return $this->parcelData;
    }

    public function getSpecialInstructions(): ?string
    {
        return $this->specialInstructions;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getEstimatedDelivery(): ?\DateTimeImmutable
    {
        return $this->estimatedDelivery;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    // Setters
    public function updateStatus(MeestTrackingStatus $status): self
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        if ($status === MeestTrackingStatus::DELIVERED && !$this->deliveredAt) {
            $this->deliveredAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function setCost(string $totalCost, string $currency): self
    {
        $this->totalCost = $totalCost;
        $this->currency = $currency;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function setLabelUrl(string $labelUrl): self
    {
        $this->labelUrl = $labelUrl;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function setEstimatedDelivery(\DateTimeImmutable $estimatedDelivery): self
    {
        $this->estimatedDelivery = $estimatedDelivery;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function setSpecialInstructions(?string $specialInstructions): self
    {
        $this->specialInstructions = $specialInstructions;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    // Business methods
    public function isDelivered(): bool
    {
        return $this->status === MeestTrackingStatus::DELIVERED;
    }

    public function isInProgress(): bool
    {
        return $this->status->isInProgress();
    }

    public function hasIssue(): bool
    {
        return $this->status->hasIssue();
    }

    public function isReturnShipment(): bool
    {
        return $this->shipmentType->isReturnShipment();
    }

    public function hasLabel(): bool
    {
        return !empty($this->labelUrl);
    }
}