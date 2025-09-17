<?php

declare(strict_types=1);

namespace App\Courier\InPost\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class InPostShipmentRequestDTO
{
    public string $senderName;
    public string $senderEmail;
    public string $senderPhone;
    public string $senderAddress;
    public string $senderPostalCode;
    public string $recipientName;
    public string $recipientEmail;
    public string $recipientPhone;
    public string $recipientAddress;
    public string $recipientPostalCode;
    public float $weight;
    public float $length;
    public float $width;
    public float $height;
    public ?string $targetPaczkomat;
    public string $deliveryMethod;
    public ?float $codAmount;
    public string $codCurrency = 'PLN';
    public ?float $insuranceAmount;
    public ?string $customerReference;
    public string $parcelSize;

    public function __construct(
        string $senderName,
        string $senderEmail,
        string $senderPhone,
        string $senderAddress,
        string $senderPostalCode,
        string $recipientName,
        string $recipientEmail,
        string $recipientPhone,
        string $recipientAddress,
        string $recipientPostalCode,
        float $weight,
        float $length,
        float $width,
        float $height,
        string $deliveryMethod,
        string $parcelSize,
        ?string $targetPaczkomat = null,
        ?float $codAmount = null,
        string $codCurrency = 'PLN',
        ?float $insuranceAmount = null,
        ?string $customerReference = null
    ) {
        $this->senderName = $senderName;
        $this->senderEmail = $senderEmail;
        $this->senderPhone = $senderPhone;
        $this->senderAddress = $senderAddress;
        $this->senderPostalCode = $senderPostalCode;
        $this->recipientName = $recipientName;
        $this->recipientEmail = $recipientEmail;
        $this->recipientPhone = $recipientPhone;
        $this->recipientAddress = $recipientAddress;
        $this->recipientPostalCode = $recipientPostalCode;
        $this->weight = $weight;
        $this->length = $length;
        $this->width = $width;
        $this->height = $height;
        $this->targetPaczkomat = $targetPaczkomat;
        $this->deliveryMethod = $deliveryMethod;
        $this->codAmount = $codAmount;
        $this->codCurrency = $codCurrency;
        $this->insuranceAmount = $insuranceAmount;
        $this->customerReference = $customerReference;
        $this->parcelSize = $parcelSize;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['senderName'] ?? '',
            $data['senderEmail'] ?? '',
            $data['senderPhone'] ?? '',
            $data['senderAddress'] ?? '',
            $data['senderPostalCode'] ?? '',
            $data['recipientName'] ?? '',
            $data['recipientEmail'] ?? '',
            $data['recipientPhone'] ?? '',
            $data['recipientAddress'] ?? '',
            $data['recipientPostalCode'] ?? '',
            (float) ($data['weight'] ?? 0.0),
            (float) ($data['length'] ?? 0.0),
            (float) ($data['width'] ?? 0.0),
            (float) ($data['height'] ?? 0.0),
            $data['deliveryMethod'] ?? 'paczkomat',
            $data['parcelSize'] ?? 'small',
            $data['targetPaczkomat'] ?? null,
            isset($data['codAmount']) ? (float) $data['codAmount'] : null,
            $data['codCurrency'] ?? 'PLN',
            isset($data['insuranceAmount']) ? (float) $data['insuranceAmount'] : null,
            $data['customerReference'] ?? null
        );
    }

    public function isPackzomatDelivery(): bool
    {
        return $this->deliveryMethod === 'paczkomat' && $this->targetPaczkomat !== null;
    }

    public function hasCashOnDelivery(): bool
    {
        return $this->codAmount !== null && $this->codAmount > 0;
    }

    public function hasInsurance(): bool
    {
        return $this->insuranceAmount !== null && $this->insuranceAmount > 0;
    }

    public function toArray(): array
    {
        return [
            'senderName' => $this->senderName,
            'senderEmail' => $this->senderEmail,
            'senderPhone' => $this->senderPhone,
            'senderAddress' => $this->senderAddress,
            'senderPostalCode' => $this->senderPostalCode,
            'recipientName' => $this->recipientName,
            'recipientEmail' => $this->recipientEmail,
            'recipientPhone' => $this->recipientPhone,
            'recipientAddress' => $this->recipientAddress,
            'recipientPostalCode' => $this->recipientPostalCode,
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'targetPaczkomat' => $this->targetPaczkomat,
            'deliveryMethod' => $this->deliveryMethod,
            'codAmount' => $this->codAmount,
            'codCurrency' => $this->codCurrency,
            'insuranceAmount' => $this->insuranceAmount,
            'customerReference' => $this->customerReference,
            'parcelSize' => $this->parcelSize,
        ];
    }
}