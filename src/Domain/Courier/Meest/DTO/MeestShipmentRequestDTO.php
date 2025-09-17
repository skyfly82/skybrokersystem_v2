<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\DTO;

use App\Domain\Courier\Meest\Enum\MeestShipmentType;
use App\Domain\Courier\Meest\ValueObject\MeestAddress;
use App\Domain\Courier\Meest\ValueObject\MeestParcel;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for MEEST shipment creation request
 */
class MeestShipmentRequestDTO
{
    public function __construct(
        #[Assert\NotNull]
        public MeestAddress $sender,

        #[Assert\NotNull]
        public MeestAddress $recipient,

        #[Assert\NotNull]
        public MeestParcel $parcel,

        #[Assert\NotNull]
        public MeestShipmentType $shipmentType = MeestShipmentType::STANDARD,

        #[Assert\Length(max: 500)]
        public ?string $specialInstructions = null,

        #[Assert\Length(max: 100)]
        public ?string $reference = null,

        public bool $requireSignature = false,

        public bool $saturdayDelivery = false,

        public ?string $deliveryDate = null,

        public ?string $originalTrackingNumber = null
    ) {}

    public function toApiPayload(): array
    {
        $payload = [
            'sender' => $this->sender->toArray(),
            'recipient' => $this->recipient->toArray(),
            'parcel' => $this->parcel->toArray(),
            'service_type' => $this->shipmentType->value,
            'options' => [
                'require_signature' => $this->requireSignature,
                'saturday_delivery' => $this->saturdayDelivery,
            ]
        ];

        if ($this->specialInstructions) {
            $payload['special_instructions'] = $this->specialInstructions;
        }

        if ($this->reference) {
            $payload['reference'] = $this->reference;
        }

        if ($this->deliveryDate) {
            $payload['delivery_date'] = $this->deliveryDate;
        }

        if ($this->originalTrackingNumber) {
            $payload['original_tracking_number'] = $this->originalTrackingNumber;
        }

        return $payload;
    }

    public function isReturnShipment(): bool
    {
        return $this->shipmentType->isReturnShipment();
    }

    public static function fromArray(array $data): self
    {
        $sender = new MeestAddress(
            firstName: $data['sender']['first_name'],
            lastName: $data['sender']['last_name'],
            phone: $data['sender']['phone'],
            email: $data['sender']['email'],
            country: $data['sender']['country'],
            city: $data['sender']['city'],
            address: $data['sender']['address'],
            postalCode: $data['sender']['postal_code'],
            company: $data['sender']['company'] ?? null
        );

        $recipient = new MeestAddress(
            firstName: $data['recipient']['first_name'],
            lastName: $data['recipient']['last_name'],
            phone: $data['recipient']['phone'],
            email: $data['recipient']['email'],
            country: $data['recipient']['country'],
            city: $data['recipient']['city'],
            address: $data['recipient']['address'],
            postalCode: $data['recipient']['postal_code'],
            company: $data['recipient']['company'] ?? null
        );

        $parcel = new MeestParcel(
            weight: (float) $data['parcel']['weight'],
            length: (float) $data['parcel']['length'],
            width: (float) $data['parcel']['width'],
            height: (float) $data['parcel']['height'],
            value: (float) $data['parcel']['value'],
            currency: $data['parcel']['currency'],
            contents: $data['parcel']['contents'],
            description: $data['parcel']['description'] ?? null
        );

        $shipmentType = MeestShipmentType::from($data['service_type'] ?? 'standard');

        return new self(
            sender: $sender,
            recipient: $recipient,
            parcel: $parcel,
            shipmentType: $shipmentType,
            specialInstructions: $data['special_instructions'] ?? null,
            reference: $data['reference'] ?? null,
            requireSignature: $data['options']['require_signature'] ?? false,
            saturdayDelivery: $data['options']['saturday_delivery'] ?? false,
            deliveryDate: $data['delivery_date'] ?? null,
            originalTrackingNumber: $data['original_tracking_number'] ?? null
        );
    }
}