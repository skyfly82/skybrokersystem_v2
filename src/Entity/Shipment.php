<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShipmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShipmentRepository::class)]
#[ORM\Table(name: 'v2_shipments')]
#[ORM\Index(columns: ['tracking_number'], name: 'idx_tracking_number')]
#[ORM\Index(columns: ['status'], name: 'idx_shipment_status')]
#[ORM\Index(columns: ['courier_service'], name: 'idx_courier_service')]
#[ORM\Index(columns: ['created_at'], name: 'idx_shipment_created')]
class Shipment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'shipments')]
    #[ORM\JoinColumn(nullable: false)]
    private Order $order;

    #[ORM\Column(length: 100, unique: true)]
    private string $trackingNumber;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $courierShipmentId = null;

    #[ORM\Column(length: 50)]
    private string $courierService; // 'inpost', 'dhl', etc.

    #[ORM\Column(length: 50)]
    private string $status = 'created';

    #[ORM\Column(length: 255)]
    private string $senderName;

    #[ORM\Column(length: 255)]
    private string $senderEmail;

    #[ORM\Column(type: Types::TEXT)]
    private string $senderAddress;

    #[ORM\Column(length: 20)]
    private string $senderPostalCode;

    #[ORM\Column(length: 100)]
    private string $senderCity;

    #[ORM\Column(length: 100)]
    private string $senderCountry = 'Poland';

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $senderPhone = null;

    #[ORM\Column(length: 255)]
    private string $recipientName;

    #[ORM\Column(length: 255)]
    private string $recipientEmail;

    #[ORM\Column(type: Types::TEXT)]
    private string $recipientAddress;

    #[ORM\Column(length: 20)]
    private string $recipientPostalCode;

    #[ORM\Column(length: 100)]
    private string $recipientCity;

    #[ORM\Column(length: 100)]
    private string $recipientCountry = 'Poland';

    #[ORM\Column(length: 20)]
    private string $recipientPhone;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 3)]
    private string $totalWeight = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalValue = '0.00';

    #[ORM\Column(length: 3)]
    private string $currency = 'PLN';

    #[ORM\Column(length: 50)]
    private string $serviceType = 'standard';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $specialInstructions = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $labelUrl = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $shippingCost = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $codAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $insuranceAmount = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $courierMetadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dispatchedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $estimatedDeliveryAt = null;

    #[ORM\OneToMany(targetEntity: ShipmentItem::class, mappedBy: 'shipment', cascade: ['persist', 'remove'])]
    private Collection $items;

    #[ORM\OneToMany(targetEntity: ShipmentTracking::class, mappedBy: 'shipment', cascade: ['persist', 'remove'])]
    private Collection $trackingEvents;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->trackingEvents = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): static
    {
        $this->order = $order;
        return $this;
    }

    public function getTrackingNumber(): string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(string $trackingNumber): static
    {
        $this->trackingNumber = $trackingNumber;
        return $this;
    }

    public function getCourierShipmentId(): ?string
    {
        return $this->courierShipmentId;
    }

    public function setCourierShipmentId(?string $courierShipmentId): static
    {
        $this->courierShipmentId = $courierShipmentId;
        return $this;
    }

    public function getCourierService(): string
    {
        return $this->courierService;
    }

    public function setCourierService(string $courierService): static
    {
        $this->courierService = $courierService;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $oldStatus = $this->status;
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        // Set status timestamps
        if ($status === 'dispatched' && $oldStatus !== 'dispatched') {
            $this->dispatchedAt = new \DateTimeImmutable();
        } elseif ($status === 'delivered' && $oldStatus !== 'delivered') {
            $this->deliveredAt = new \DateTimeImmutable();
        }

        return $this;
    }

    // Sender getters and setters
    public function getSenderName(): string
    {
        return $this->senderName;
    }

    public function setSenderName(string $senderName): static
    {
        $this->senderName = $senderName;
        return $this;
    }

    public function getSenderEmail(): string
    {
        return $this->senderEmail;
    }

    public function setSenderEmail(string $senderEmail): static
    {
        $this->senderEmail = $senderEmail;
        return $this;
    }

    public function getSenderAddress(): string
    {
        return $this->senderAddress;
    }

    public function setSenderAddress(string $senderAddress): static
    {
        $this->senderAddress = $senderAddress;
        return $this;
    }

    public function getSenderPostalCode(): string
    {
        return $this->senderPostalCode;
    }

    public function setSenderPostalCode(string $senderPostalCode): static
    {
        $this->senderPostalCode = $senderPostalCode;
        return $this;
    }

    public function getSenderCity(): string
    {
        return $this->senderCity;
    }

    public function setSenderCity(string $senderCity): static
    {
        $this->senderCity = $senderCity;
        return $this;
    }

    public function getSenderCountry(): string
    {
        return $this->senderCountry;
    }

    public function setSenderCountry(string $senderCountry): static
    {
        $this->senderCountry = $senderCountry;
        return $this;
    }

    public function getSenderPhone(): ?string
    {
        return $this->senderPhone;
    }

    public function setSenderPhone(?string $senderPhone): static
    {
        $this->senderPhone = $senderPhone;
        return $this;
    }

    // Recipient getters and setters
    public function getRecipientName(): string
    {
        return $this->recipientName;
    }

    public function setRecipientName(string $recipientName): static
    {
        $this->recipientName = $recipientName;
        return $this;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }

    public function setRecipientEmail(string $recipientEmail): static
    {
        $this->recipientEmail = $recipientEmail;
        return $this;
    }

    public function getRecipientAddress(): string
    {
        return $this->recipientAddress;
    }

    public function setRecipientAddress(string $recipientAddress): static
    {
        $this->recipientAddress = $recipientAddress;
        return $this;
    }

    public function getRecipientPostalCode(): string
    {
        return $this->recipientPostalCode;
    }

    public function setRecipientPostalCode(string $recipientPostalCode): static
    {
        $this->recipientPostalCode = $recipientPostalCode;
        return $this;
    }

    public function getRecipientCity(): string
    {
        return $this->recipientCity;
    }

    public function setRecipientCity(string $recipientCity): static
    {
        $this->recipientCity = $recipientCity;
        return $this;
    }

    public function getRecipientCountry(): string
    {
        return $this->recipientCountry;
    }

    public function setRecipientCountry(string $recipientCountry): static
    {
        $this->recipientCountry = $recipientCountry;
        return $this;
    }

    public function getRecipientPhone(): string
    {
        return $this->recipientPhone;
    }

    public function setRecipientPhone(string $recipientPhone): static
    {
        $this->recipientPhone = $recipientPhone;
        return $this;
    }

    // Other properties
    public function getTotalWeight(): string
    {
        return $this->totalWeight;
    }

    public function setTotalWeight(string $totalWeight): static
    {
        $this->totalWeight = $totalWeight;
        return $this;
    }

    public function getTotalValue(): string
    {
        return $this->totalValue;
    }

    public function setTotalValue(string $totalValue): static
    {
        $this->totalValue = $totalValue;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getServiceType(): string
    {
        return $this->serviceType;
    }

    public function setServiceType(string $serviceType): static
    {
        $this->serviceType = $serviceType;
        return $this;
    }

    public function getSpecialInstructions(): ?string
    {
        return $this->specialInstructions;
    }

    public function setSpecialInstructions(?string $specialInstructions): static
    {
        $this->specialInstructions = $specialInstructions;
        return $this;
    }

    public function getLabelUrl(): ?string
    {
        return $this->labelUrl;
    }

    public function setLabelUrl(?string $labelUrl): static
    {
        $this->labelUrl = $labelUrl;
        return $this;
    }

    public function getShippingCost(): ?string
    {
        return $this->shippingCost;
    }

    public function setShippingCost(?string $shippingCost): static
    {
        $this->shippingCost = $shippingCost;
        return $this;
    }

    public function getCodAmount(): ?string
    {
        return $this->codAmount;
    }

    public function setCodAmount(?string $codAmount): static
    {
        $this->codAmount = $codAmount;
        return $this;
    }

    public function getInsuranceAmount(): ?string
    {
        return $this->insuranceAmount;
    }

    public function setInsuranceAmount(?string $insuranceAmount): static
    {
        $this->insuranceAmount = $insuranceAmount;
        return $this;
    }

    public function getCourierMetadata(): ?array
    {
        return $this->courierMetadata;
    }

    public function setCourierMetadata(?array $courierMetadata): static
    {
        $this->courierMetadata = $courierMetadata;
        return $this;
    }

    // Timestamps
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDispatchedAt(): ?\DateTimeImmutable
    {
        return $this->dispatchedAt;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function getEstimatedDeliveryAt(): ?\DateTimeImmutable
    {
        return $this->estimatedDeliveryAt;
    }

    public function setEstimatedDeliveryAt(?\DateTimeImmutable $estimatedDeliveryAt): static
    {
        $this->estimatedDeliveryAt = $estimatedDeliveryAt;
        return $this;
    }

    // Collections
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ShipmentItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setShipment($this);
        }
        return $this;
    }

    public function removeItem(ShipmentItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getShipment() === $this) {
                $item->setShipment(null);
            }
        }
        return $this;
    }

    public function getTrackingEvents(): Collection
    {
        return $this->trackingEvents;
    }

    public function addTrackingEvent(ShipmentTracking $trackingEvent): static
    {
        if (!$this->trackingEvents->contains($trackingEvent)) {
            $this->trackingEvents->add($trackingEvent);
            $trackingEvent->setShipment($this);
        }
        return $this;
    }

    public function removeTrackingEvent(ShipmentTracking $trackingEvent): static
    {
        if ($this->trackingEvents->removeElement($trackingEvent)) {
            if ($trackingEvent->getShipment() === $this) {
                $trackingEvent->setShipment(null);
            }
        }
        return $this;
    }

    // Status checking methods
    public function isInPost(): bool
    {
        return $this->courierService === 'inpost';
    }

    public function isDhl(): bool
    {
        return $this->courierService === 'dhl';
    }

    public function isCreated(): bool
    {
        return $this->status === 'created';
    }

    public function isDispatched(): bool
    {
        return $this->status === 'dispatched';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    public function hasCashOnDelivery(): bool
    {
        return $this->codAmount !== null && (float) $this->codAmount > 0;
    }

    public function hasInsurance(): bool
    {
        return $this->insuranceAmount !== null && (float) $this->insuranceAmount > 0;
    }

    public function canBeCanceled(): bool
    {
        return in_array($this->status, ['created', 'confirmed'], true);
    }

    public function getFullSenderAddress(): string
    {
        return implode(', ', array_filter([
            $this->senderAddress,
            $this->senderPostalCode,
            $this->senderCity,
            $this->senderCountry,
        ]));
    }

    public function getFullRecipientAddress(): string
    {
        return implode(', ', array_filter([
            $this->recipientAddress,
            $this->recipientPostalCode,
            $this->recipientCity,
            $this->recipientCountry,
        ]));
    }

    public function getLatestTrackingEvent(): ?ShipmentTracking
    {
        if ($this->trackingEvents->isEmpty()) {
            return null;
        }

        $events = $this->trackingEvents->toArray();
        usort($events, fn($a, $b) => $b->getEventDate() <=> $a->getEventDate());

        return $events[0];
    }
}