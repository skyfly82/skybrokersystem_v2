<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShipmentItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShipmentItemRepository::class)]
#[ORM\Table(name: 'v2_shipment_items')]
class ShipmentItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Shipment::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Shipment $shipment = null;

    #[ORM\ManyToOne(targetEntity: OrderItem::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?OrderItem $orderItem = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $sku = null;

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 3)]
    private string $weight = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $width = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $height = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $length = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $value = '0.00';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

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

    public function getOrderItem(): ?OrderItem
    {
        return $this->orderItem;
    }

    public function setOrderItem(?OrderItem $orderItem): static
    {
        $this->orderItem = $orderItem;
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

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): static
    {
        $this->sku = $sku;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = max(1, $quantity);
        return $this;
    }

    public function getWeight(): string
    {
        return $this->weight;
    }

    public function setWeight(string $weight): static
    {
        $this->weight = $weight;
        return $this;
    }

    public function getWidth(): ?string
    {
        return $this->width;
    }

    public function setWidth(?string $width): static
    {
        $this->width = $width;
        return $this;
    }

    public function getHeight(): ?string
    {
        return $this->height;
    }

    public function setHeight(?string $height): static
    {
        $this->height = $height;
        return $this;
    }

    public function getLength(): ?string
    {
        return $this->length;
    }

    public function setLength(?string $length): static
    {
        $this->length = $length;
        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;
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

    public function getDimensions(): ?array
    {
        if (!$this->width || !$this->height || !$this->length) {
            return null;
        }

        return [
            'width' => (float) $this->width,
            'height' => (float) $this->height,
            'length' => (float) $this->length,
        ];
    }

    public function getVolume(): ?float
    {
        $dimensions = $this->getDimensions();
        if (!$dimensions) {
            return null;
        }

        return $dimensions['width'] * $dimensions['height'] * $dimensions['length'];
    }

    public function getTotalWeight(): float
    {
        return (float) $this->weight * $this->quantity;
    }

    public function getTotalValue(): float
    {
        return (float) $this->value * $this->quantity;
    }

    public function hasPhysicalDimensions(): bool
    {
        return $this->getDimensions() !== null;
    }

    public static function fromOrderItem(OrderItem $orderItem): self
    {
        $shipmentItem = new self();
        $shipmentItem->orderItem = $orderItem;
        $shipmentItem->name = $orderItem->getName();
        $shipmentItem->description = $orderItem->getDescription();
        $shipmentItem->sku = $orderItem->getSku();
        $shipmentItem->quantity = $orderItem->getQuantity();
        $shipmentItem->weight = $orderItem->getWeight() ?? '0.000';
        $shipmentItem->width = $orderItem->getWidth();
        $shipmentItem->height = $orderItem->getHeight();
        $shipmentItem->length = $orderItem->getLength();
        $shipmentItem->value = $orderItem->getTotalPrice();
        $shipmentItem->metadata = $orderItem->getMetadata();

        return $shipmentItem;
    }
}