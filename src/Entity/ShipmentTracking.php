<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShipmentTrackingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShipmentTrackingRepository::class)]
#[ORM\Table(name: 'v2_shipment_tracking')]
#[ORM\Index(columns: ['event_date'], name: 'idx_tracking_event_date')]
#[ORM\Index(columns: ['status'], name: 'idx_tracking_status')]
class ShipmentTracking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Shipment::class, inversedBy: 'trackingEvents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 100)]
    private string $status;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $eventDate;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $rawData = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $courierEventId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShipment(): ?Shipment
    {
        return $this->shipment;
    }

    public function setShipment(?Shipment $shipment): static
    {
        $this->shipment = $shipment;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getEventDate(): \DateTimeImmutable
    {
        return $this->eventDate;
    }

    public function setEventDate(\DateTimeImmutable $eventDate): static
    {
        $this->eventDate = $eventDate;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRawData(): ?array
    {
        return $this->rawData;
    }

    public function setRawData(?array $rawData): static
    {
        $this->rawData = $rawData;
        return $this;
    }

    public function getCourierEventId(): ?string
    {
        return $this->courierEventId;
    }

    public function setCourierEventId(?string $courierEventId): static
    {
        $this->courierEventId = $courierEventId;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isInTransit(): bool
    {
        return in_array($this->status, [
            'dispatched_by_sender',
            'collected_from_sender',
            'taken_by_courier',
            'adopted_at_source_branch',
            'sent_from_source_branch',
            'adopted_at_sorting_center',
            'sent_from_sorting_center',
            'adopted_at_target_branch',
            'sent_from_target_branch',
            'out_for_delivery',
        ], true);
    }

    public function isAwaitingPickup(): bool
    {
        return in_array($this->status, [
            'ready_to_pickup',
            'pickup_reminder_sent',
        ], true);
    }

    public function isError(): bool
    {
        return $this->status === 'error';
    }

    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    public static function createFromArray(array $data): self
    {
        $tracking = new self();
        $tracking->status = $data['status'] ?? '';
        $tracking->description = $data['description'] ?? '';
        $tracking->location = $data['location'] ?? null;
        $tracking->eventDate = isset($data['date']) 
            ? new \DateTimeImmutable($data['date']) 
            : new \DateTimeImmutable();
        $tracking->courierEventId = $data['event_id'] ?? null;
        $tracking->rawData = $data;
        
        return $tracking;
    }
}