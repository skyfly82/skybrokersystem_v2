<?php

declare(strict_types=1);

namespace App\Domain\InPost\DTO;

use App\Domain\Courier\DTO\ShipmentRequestDTO;
use Symfony\Component\Validator\Constraints as Assert;

class InPostShipmentRequestDTO extends ShipmentRequestDTO
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['small', 'medium', 'large', 'xlarge'])]
    public string $parcelSize = 'medium';

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['paczkomaty', 'courier'])]
    public string $deliveryMethod = 'paczkomaty';

    // For Paczkomat delivery
    #[Assert\Regex(pattern: '/^[A-Z]{3}[0-9]{2,4}[A-Z]?$/')]
    public ?string $targetPaczkomat = null;

    // Polish-specific fields
    #[Assert\Regex(pattern: '/^[0-9]{2}-[0-9]{3}$/')]
    public string $senderPostalCode;

    #[Assert\Regex(pattern: '/^[0-9]{2}-[0-9]{3}$/')]
    public string $recipientPostalCode;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\+48[0-9]{9}$/')]
    public string $recipientPhone;

    // Optional sender phone
    #[Assert\Regex(pattern: '/^\+48[0-9]{9}$/')]
    public ?string $senderPhone = null;

    // COD (Cash on Delivery) support
    #[Assert\PositiveOrZero]
    public ?float $codAmount = null;

    #[Assert\Choice(choices: ['PLN'])]
    public string $codCurrency = 'PLN';

    // Insurance
    #[Assert\PositiveOrZero]
    public ?float $insuranceAmount = null;

    // Reference number for customer
    #[Assert\Length(max: 50)]
    public ?string $customerReference = null;

    // Package dimensions (cm)
    #[Assert\Positive]
    public ?float $width = null;

    #[Assert\Positive]
    public ?float $height = null;

    #[Assert\Positive]
    public ?float $length = null;

    public static function fromShipmentRequest(ShipmentRequestDTO $request): self
    {
        $dto = new self();
        
        // Copy basic shipment data
        $dto->senderName = $request->senderName;
        $dto->senderEmail = $request->senderEmail;
        $dto->senderAddress = $request->senderAddress;
        $dto->recipientName = $request->recipientName;
        $dto->recipientEmail = $request->recipientEmail;
        $dto->recipientAddress = $request->recipientAddress;
        $dto->weight = $request->weight;
        $dto->serviceType = $request->serviceType;
        $dto->specialInstructions = $request->specialInstructions;

        return $dto;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray() ?? [], [
            'parcelSize' => $this->parcelSize,
            'deliveryMethod' => $this->deliveryMethod,
            'targetPaczkomat' => $this->targetPaczkomat,
            'senderPostalCode' => $this->senderPostalCode,
            'recipientPostalCode' => $this->recipientPostalCode,
            'recipientPhone' => $this->recipientPhone,
            'senderPhone' => $this->senderPhone,
            'codAmount' => $this->codAmount,
            'codCurrency' => $this->codCurrency,
            'insuranceAmount' => $this->insuranceAmount,
            'customerReference' => $this->customerReference,
            'dimensions' => [
                'width' => $this->width,
                'height' => $this->height,
                'length' => $this->length,
            ],
        ]);
    }

    public function isPackzomatDelivery(): bool
    {
        return $this->deliveryMethod === 'paczkomaty';
    }

    public function hasCashOnDelivery(): bool
    {
        return $this->codAmount > 0;
    }

    public function hasInsurance(): bool
    {
        return $this->insuranceAmount > 0;
    }
}