<?php

declare(strict_types=1);

namespace App\Domain\Pricing\DTO;

/**
 * Response DTO for price calculation
 */
class PriceCalculationResponseDTO
{
    public string $carrierCode;
    public string $carrierName;
    public string $zoneCode;
    public string $zoneName;
    public string $serviceType;
    public float $weightKg;
    public float $chargeableWeightKg;
    public array $dimensionsCm;
    public string $basePrice;
    public string $additionalServicesPrice;
    public string $subtotal;
    public string $taxAmount;
    public string $totalPrice;
    public string $currency;
    public ?float $taxRate;
    public array $additionalServices;
    public array $priceBreakdown;
    public bool $customerPricingApplied = false;
    public ?string $customerDiscount = null;
    public ?string $promotionalDiscount = null;

    public function __construct(
        string $carrierCode,
        string $carrierName,
        string $zoneCode,
        string $zoneName,
        string $serviceType,
        float $weightKg,
        float $chargeableWeightKg,
        array $dimensionsCm,
        string $basePrice,
        string $currency = 'PLN'
    ) {
        $this->carrierCode = $carrierCode;
        $this->carrierName = $carrierName;
        $this->zoneCode = $zoneCode;
        $this->zoneName = $zoneName;
        $this->serviceType = $serviceType;
        $this->weightKg = $weightKg;
        $this->chargeableWeightKg = $chargeableWeightKg;
        $this->dimensionsCm = $dimensionsCm;
        $this->basePrice = $basePrice;
        $this->currency = $currency;
        $this->additionalServicesPrice = '0.00';
        $this->subtotal = $basePrice;
        $this->taxAmount = '0.00';
        $this->totalPrice = $basePrice;
        $this->additionalServices = [];
        $this->priceBreakdown = [];
    }

    public function addAdditionalService(string $serviceCode, string $serviceName, string $price): void
    {
        $this->additionalServices[] = [
            'code' => $serviceCode,
            'name' => $serviceName,
            'price' => $price,
        ];
        
        $this->additionalServicesPrice = bcadd($this->additionalServicesPrice, $price, 2);
        $this->recalculateTotal();
    }

    public function setTax(float $taxRate): void
    {
        $this->taxRate = $taxRate;
        $this->taxAmount = bcmul($this->subtotal, (string)($taxRate / 100), 2);
        $this->recalculateTotal();
    }

    public function applyCustomerDiscount(string $discount, string $type = 'percentage'): void
    {
        $this->customerDiscount = $discount;
        $this->customerPricingApplied = true;
        
        if ($type === 'percentage') {
            $discountAmount = bcmul($this->basePrice, $discount, 2);
            $discountAmount = bcdiv($discountAmount, '100', 2);
            $this->basePrice = bcsub($this->basePrice, $discountAmount, 2);
        } else {
            $this->basePrice = bcsub($this->basePrice, $discount, 2);
        }
        
        $this->recalculateTotal();
    }

    public function applyPromotionalDiscount(string $discount, string $type = 'percentage'): void
    {
        $this->promotionalDiscount = $discount;
        
        if ($type === 'percentage') {
            $discountAmount = bcmul($this->subtotal, $discount, 2);
            $discountAmount = bcdiv($discountAmount, '100', 2);
            $this->subtotal = bcsub($this->subtotal, $discountAmount, 2);
        } else {
            $this->subtotal = bcsub($this->subtotal, $discount, 2);
        }
        
        $this->recalculateTotal();
    }

    private function recalculateTotal(): void
    {
        $this->subtotal = bcadd($this->basePrice, $this->additionalServicesPrice, 2);
        
        if ($this->taxRate !== null) {
            $this->taxAmount = bcmul($this->subtotal, (string)($this->taxRate / 100), 2);
        }
        
        $this->totalPrice = bcadd($this->subtotal, $this->taxAmount, 2);
    }

    public function addPriceBreakdownItem(string $description, string $amount): void
    {
        $this->priceBreakdown[] = [
            'description' => $description,
            'amount' => $amount,
        ];
    }

    public function toArray(): array
    {
        return [
            'carrier' => [
                'code' => $this->carrierCode,
                'name' => $this->carrierName,
            ],
            'zone' => [
                'code' => $this->zoneCode,
                'name' => $this->zoneName,
            ],
            'service_type' => $this->serviceType,
            'weight' => [
                'actual_kg' => $this->weightKg,
                'chargeable_kg' => $this->chargeableWeightKg,
            ],
            'dimensions_cm' => $this->dimensionsCm,
            'pricing' => [
                'base_price' => $this->basePrice,
                'additional_services_price' => $this->additionalServicesPrice,
                'subtotal' => $this->subtotal,
                'tax_amount' => $this->taxAmount,
                'total_price' => $this->totalPrice,
                'currency' => $this->currency,
                'tax_rate' => $this->taxRate,
            ],
            'additional_services' => $this->additionalServices,
            'discounts' => [
                'customer_pricing_applied' => $this->customerPricingApplied,
                'customer_discount' => $this->customerDiscount,
                'promotional_discount' => $this->promotionalDiscount,
            ],
            'price_breakdown' => $this->priceBreakdown,
        ];
    }
}