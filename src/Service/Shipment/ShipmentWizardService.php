<?php

declare(strict_types=1);

namespace App\Service\Shipment;

use App\Entity\Shipment;
use App\Entity\Order;
use App\Repository\ShipmentRepository;
use App\Repository\OrderRepository;
use App\Repository\CustomerRepository;
use App\Service\Shipment\PricingCalculatorService;
use App\Service\InPost\InPostService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ShipmentWizardService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly OrderRepository $orderRepository,
        private readonly CustomerRepository $customerRepository,
        private readonly PricingCalculatorService $pricingCalculator,
        private readonly InPostService $inPostService,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * Get available package types for step 1
     */
    public function getAvailablePackageTypes(): array
    {
        return [
            [
                'id' => 'envelope',
                'name' => 'Envelope',
                'description' => 'Small, flat items like documents',
                'max_weight_kg' => 0.5,
                'max_dimensions' => ['length' => 35, 'width' => 25, 'height' => 2],
                'icon' => 'envelope',
                'example_items' => ['Documents', 'Photos', 'Cards']
            ],
            [
                'id' => 'small_package',
                'name' => 'Small Package',
                'description' => 'Small items that fit in a small box',
                'max_weight_kg' => 2.0,
                'max_dimensions' => ['length' => 20, 'width' => 15, 'height' => 10],
                'icon' => 'box-small',
                'example_items' => ['Books', 'Electronics', 'Clothing']
            ],
            [
                'id' => 'medium_package',
                'name' => 'Medium Package',
                'description' => 'Standard packages for most items',
                'max_weight_kg' => 10.0,
                'max_dimensions' => ['length' => 40, 'width' => 30, 'height' => 20],
                'icon' => 'box-medium',
                'example_items' => ['Shoes', 'Multiple books', 'Small appliances']
            ],
            [
                'id' => 'large_package',
                'name' => 'Large Package',
                'description' => 'Large items requiring special handling',
                'max_weight_kg' => 25.0,
                'max_dimensions' => ['length' => 60, 'width' => 40, 'height' => 30],
                'icon' => 'box-large',
                'example_items' => ['Large electronics', 'Multiple items', 'Bulky goods']
            ],
            [
                'id' => 'custom',
                'name' => 'Custom Dimensions',
                'description' => 'Specify exact dimensions and weight',
                'max_weight_kg' => 50.0,
                'max_dimensions' => ['length' => 100, 'width' => 60, 'height' => 50],
                'icon' => 'rulers',
                'example_items' => ['Irregular items', 'Multiple packages', 'Special items']
            ]
        ];
    }

    /**
     * Get recent shipment templates for quick selection
     */
    public function getRecentShipmentTemplates(int $customerId): array
    {
        $recentShipments = $this->shipmentRepository->findBy(
            ['order.customer' => $customerId],
            ['createdAt' => 'DESC'],
            5
        );

        return array_map(function (Shipment $shipment) {
            return [
                'id' => $shipment->getId(),
                'template_name' => sprintf(
                    '%s → %s (%s)',
                    $shipment->getSenderCity(),
                    $shipment->getRecipientCity(),
                    $shipment->getCourierService()
                ),
                'package_type' => $this->determinePackageType($shipment),
                'weight_kg' => $shipment->getTotalWeight(),
                'dimensions' => $this->extractDimensions($shipment),
                'sender' => [
                    'name' => $shipment->getSenderName(),
                    'address' => $shipment->getSenderAddress(),
                    'city' => $shipment->getSenderCity(),
                    'postal_code' => $shipment->getSenderPostalCode(),
                    'country' => $shipment->getSenderCountry(),
                    'phone' => $shipment->getSenderPhone(),
                    'email' => $shipment->getSenderEmail()
                ],
                'recipient' => [
                    'name' => $shipment->getRecipientName(),
                    'address' => $shipment->getRecipientAddress(),
                    'city' => $shipment->getRecipientCity(),
                    'postal_code' => $shipment->getRecipientPostalCode(),
                    'country' => $shipment->getRecipientCountry(),
                    'phone' => $shipment->getRecipientPhone(),
                    'email' => $shipment->getRecipientEmail()
                ]
            ];
        }, $recentShipments);
    }

    /**
     * Validate step 1 data (package type and dimensions)
     */
    public function validateStep1(array $data): array
    {
        $errors = [];

        // Validate package type
        if (empty($data['package_type'])) {
            $errors['package_type'] = 'Package type is required';
        }

        // Validate weight
        if (empty($data['weight_kg']) || !is_numeric($data['weight_kg'])) {
            $errors['weight_kg'] = 'Valid weight is required';
        } elseif ((float)$data['weight_kg'] <= 0) {
            $errors['weight_kg'] = 'Weight must be greater than 0';
        } elseif ((float)$data['weight_kg'] > 50) {
            $errors['weight_kg'] = 'Weight cannot exceed 50kg';
        }

        // Validate dimensions
        if ($data['package_type'] === 'custom') {
            $requiredDimensions = ['length', 'width', 'height'];
            foreach ($requiredDimensions as $dimension) {
                if (empty($data['dimensions'][$dimension]) || !is_numeric($data['dimensions'][$dimension])) {
                    $errors["dimensions.{$dimension}"] = ucfirst($dimension) . ' is required';
                } elseif ((int)$data['dimensions'][$dimension] <= 0) {
                    $errors["dimensions.{$dimension}"] = ucfirst($dimension) . ' must be greater than 0';
                }
            }
        }

        // Validate items
        if (empty($data['items']) || !is_array($data['items'])) {
            $errors['items'] = 'At least one item is required';
        } else {
            foreach ($data['items'] as $index => $item) {
                if (empty($item['name'])) {
                    $errors["items.{$index}.name"] = 'Item name is required';
                }
                if (empty($item['quantity']) || !is_numeric($item['quantity']) || (int)$item['quantity'] <= 0) {
                    $errors["items.{$index}.quantity"] = 'Valid quantity is required';
                }
                if (empty($item['value']) || !is_numeric($item['value']) || (float)$item['value'] < 0) {
                    $errors["items.{$index}.value"] = 'Valid item value is required';
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate step 2 data (addresses)
     */
    public function validateStep2(array $data): array
    {
        $errors = [];

        // Validate sender address
        $senderErrors = $this->validateAddress($data['sender'] ?? [], 'sender');
        $errors = array_merge($errors, $senderErrors);

        // Validate recipient address
        $recipientErrors = $this->validateAddress($data['recipient'] ?? [], 'recipient');
        $errors = array_merge($errors, $recipientErrors);

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate step 3 data (additional services)
     */
    public function validateStep3(array $data): array
    {
        $errors = [];

        // Validate COD amount if enabled
        if (!empty($data['cod_enabled'])) {
            if (empty($data['cod_amount']) || !is_numeric($data['cod_amount'])) {
                $errors['cod_amount'] = 'COD amount is required when COD is enabled';
            } elseif ((float)$data['cod_amount'] <= 0) {
                $errors['cod_amount'] = 'COD amount must be greater than 0';
            }
        }

        // Validate insurance amount if enabled
        if (!empty($data['insurance_enabled'])) {
            if (empty($data['insurance_amount']) || !is_numeric($data['insurance_amount'])) {
                $errors['insurance_amount'] = 'Insurance amount is required when insurance is enabled';
            } elseif ((float)$data['insurance_amount'] <= 0) {
                $errors['insurance_amount'] = 'Insurance amount must be greater than 0';
            }
        }

        // Validate delivery instructions length
        if (!empty($data['delivery_instructions']) && strlen($data['delivery_instructions']) > 500) {
            $errors['delivery_instructions'] = 'Delivery instructions cannot exceed 500 characters';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get available services for step 3
     */
    public function getAvailableServices(array $wizardData): array
    {
        return [
            'cod' => [
                'id' => 'cod',
                'name' => 'Cash on Delivery',
                'description' => 'Collect payment upon delivery',
                'available' => true,
                'price_modifier' => 2.5,
                'currency' => 'PLN'
            ],
            'insurance' => [
                'id' => 'insurance',
                'name' => 'Insurance',
                'description' => 'Protect your shipment value',
                'available' => true,
                'price_modifier' => 1.5,
                'currency' => 'PLN'
            ],
            'priority' => [
                'id' => 'priority',
                'name' => 'Priority Delivery',
                'description' => 'Faster delivery time',
                'available' => true,
                'price_modifier' => 5.0,
                'currency' => 'PLN'
            ],
            'saturday_delivery' => [
                'id' => 'saturday_delivery',
                'name' => 'Saturday Delivery',
                'description' => 'Delivery on Saturday',
                'available' => true,
                'price_modifier' => 3.0,
                'currency' => 'PLN'
            ],
            'sms_notification' => [
                'id' => 'sms_notification',
                'name' => 'SMS Notifications',
                'description' => 'SMS updates on delivery status',
                'available' => true,
                'price_modifier' => 1.0,
                'currency' => 'PLN'
            ]
        ];
    }

    /**
     * Get available couriers with pricing for step 4
     */
    public function getAvailableCouriers(array $wizardData): array
    {
        try {
            // Prepare data for pricing calculation
            $pricingData = $this->preparePricingData($wizardData);

            // Get courier comparison
            $courierOptions = [];

            // InPost
            try {
                $inpostPricing = $this->pricingCalculator->calculateForCourier('inpost', $pricingData);
                $courierOptions[] = [
                    'courier_code' => 'inpost',
                    'courier_name' => 'InPost',
                    'logo_url' => '/images/couriers/inpost.png',
                    'service_type' => 'parcel_locker',
                    'delivery_time' => '1-2 days',
                    'price' => $inpostPricing['total_price'],
                    'base_price' => $inpostPricing['base_price'],
                    'additional_services_price' => $inpostPricing['services_price'],
                    'currency' => 'PLN',
                    'recommended' => true,
                    'features' => ['24/7 pickup', 'Secure lockers', 'SMS notifications'],
                    'service_points' => $this->getInPostPoints($wizardData['step2']['recipient'])
                ];
            } catch (\Exception $e) {
                // InPost not available
            }

            // DHL
            try {
                $dhlPricing = $this->pricingCalculator->calculateForCourier('dhl', $pricingData);
                $courierOptions[] = [
                    'courier_code' => 'dhl',
                    'courier_name' => 'DHL',
                    'logo_url' => '/images/couriers/dhl.png',
                    'service_type' => 'home_delivery',
                    'delivery_time' => '1-3 days',
                    'price' => $dhlPricing['total_price'],
                    'base_price' => $dhlPricing['base_price'],
                    'additional_services_price' => $dhlPricing['services_price'],
                    'currency' => 'PLN',
                    'recommended' => false,
                    'features' => ['Door-to-door delivery', 'Tracking', 'Insurance included']
                ];
            } catch (\Exception $e) {
                // DHL not available
            }

            // Sort by price
            usort($courierOptions, fn($a, $b) => $a['price'] <=> $b['price']);

            return $courierOptions;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create shipment from wizard data
     */
    public function createShipment(int $customerId, array $wizardData, array $step4Data): Shipment
    {
        $this->entityManager->beginTransaction();

        try {
            $customer = $this->customerRepository->find($customerId);
            if (!$customer) {
                throw new \Exception('Customer not found');
            }

            // Create order first
            $order = new Order();
            $order->setCustomer($customer);
            $order->setStatus('pending');
            $order->setTotalAmount($step4Data['selected_price']);
            $order->setCurrency($step4Data['currency'] ?? 'PLN');

            $this->entityManager->persist($order);
            $this->entityManager->flush();

            // Create shipment
            $shipment = new Shipment();
            $shipment->setOrder($order);
            $shipment->setTrackingNumber($this->generateTrackingNumber());
            $shipment->setCourierService($step4Data['selected_courier']);

            // Set addresses
            $this->setShipmentAddresses($shipment, $wizardData['step2']);

            // Set package details
            $this->setShipmentPackageDetails($shipment, $wizardData['step1']);

            // Set additional services
            $this->setShipmentServices($shipment, $wizardData['step3']);

            // Set pricing
            $shipment->setShippingCost($step4Data['selected_price']);

            $this->entityManager->persist($shipment);
            $this->entityManager->flush();

            $this->entityManager->commit();

            return $shipment;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Get shipment details
     */
    public function getShipmentDetails(int $customerId, int $shipmentId): ?array
    {
        $shipment = $this->shipmentRepository->findOneBy([
            'id' => $shipmentId,
            'order.customer' => $customerId
        ]);

        if (!$shipment) {
            return null;
        }

        return [
            'id' => $shipment->getId(),
            'tracking_number' => $shipment->getTrackingNumber(),
            'status' => $shipment->getStatus(),
            'courier_service' => $shipment->getCourierService(),
            'created_at' => $shipment->getCreatedAt(),
            'sender' => [
                'name' => $shipment->getSenderName(),
                'address' => $shipment->getFullSenderAddress()
            ],
            'recipient' => [
                'name' => $shipment->getRecipientName(),
                'address' => $shipment->getFullRecipientAddress()
            ],
            'total_cost' => $shipment->getShippingCost(),
            'currency' => $shipment->getCurrency()
        ];
    }

    // Private helper methods

    private function validateAddress(array $address, string $prefix): array
    {
        $errors = [];
        $requiredFields = ['name', 'address', 'city', 'postal_code', 'country', 'phone', 'email'];

        foreach ($requiredFields as $field) {
            if (empty($address[$field])) {
                $errors["{$prefix}.{$field}"] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }

        // Validate email
        if (!empty($address['email']) && !filter_var($address['email'], FILTER_VALIDATE_EMAIL)) {
            $errors["{$prefix}.email"] = 'Valid email address is required';
        }

        // Validate postal code format (basic validation)
        if (!empty($address['postal_code']) && !preg_match('/^\d{2}-\d{3}$/', $address['postal_code'])) {
            $errors["{$prefix}.postal_code"] = 'Postal code must be in format XX-XXX';
        }

        return $errors;
    }

    private function determinePackageType(Shipment $shipment): string
    {
        $weight = (float)$shipment->getTotalWeight();

        if ($weight <= 0.5) return 'envelope';
        if ($weight <= 2.0) return 'small_package';
        if ($weight <= 10.0) return 'medium_package';
        if ($weight <= 25.0) return 'large_package';

        return 'custom';
    }

    private function extractDimensions(Shipment $shipment): array
    {
        // Extract from courier metadata if available
        $metadata = $shipment->getCourierMetadata();
        if ($metadata && isset($metadata['dimensions'])) {
            return $metadata['dimensions'];
        }

        // Default dimensions based on package type
        return ['length' => 30, 'width' => 20, 'height' => 10];
    }

    private function preparePricingData(array $wizardData): array
    {
        return [
            'weight_kg' => $wizardData['step1']['weight_kg'],
            'dimensions' => $wizardData['step1']['dimensions'] ?? ['length' => 30, 'width' => 20, 'height' => 10],
            'sender_postal_code' => $wizardData['step2']['sender']['postal_code'],
            'recipient_postal_code' => $wizardData['step2']['recipient']['postal_code'],
            'additional_services' => $wizardData['step3'] ?? [],
            'currency' => 'PLN'
        ];
    }

    private function getInPostPoints(array $recipientAddress): array
    {
        try {
            return $this->inPostService->getNearbyPoints(
                $recipientAddress['address'] . ', ' . $recipientAddress['city'],
                5000,
                'parcel_locker'
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    private function generateTrackingNumber(): string
    {
        return 'SKY' . date('Ymd') . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function setShipmentAddresses(Shipment $shipment, array $addressData): void
    {
        // Sender
        $sender = $addressData['sender'];
        $shipment->setSenderName($sender['name']);
        $shipment->setSenderEmail($sender['email']);
        $shipment->setSenderAddress($sender['address']);
        $shipment->setSenderPostalCode($sender['postal_code']);
        $shipment->setSenderCity($sender['city']);
        $shipment->setSenderCountry($sender['country']);
        $shipment->setSenderPhone($sender['phone'] ?? null);

        // Recipient
        $recipient = $addressData['recipient'];
        $shipment->setRecipientName($recipient['name']);
        $shipment->setRecipientEmail($recipient['email']);
        $shipment->setRecipientAddress($recipient['address']);
        $shipment->setRecipientPostalCode($recipient['postal_code']);
        $shipment->setRecipientCity($recipient['city']);
        $shipment->setRecipientCountry($recipient['country']);
        $shipment->setRecipientPhone($recipient['phone']);
    }

    private function setShipmentPackageDetails(Shipment $shipment, array $packageData): void
    {
        $shipment->setTotalWeight($packageData['weight_kg']);

        $totalValue = 0;
        foreach ($packageData['items'] as $item) {
            $totalValue += (float)$item['value'] * (int)$item['quantity'];
        }
        $shipment->setTotalValue((string)$totalValue);

        // Store package details in metadata
        $metadata = [
            'package_type' => $packageData['package_type'],
            'dimensions' => $packageData['dimensions'] ?? null,
            'items' => $packageData['items']
        ];
        $shipment->setCourierMetadata($metadata);
    }

    private function setShipmentServices(Shipment $shipment, array $servicesData): void
    {
        if (!empty($servicesData['cod_enabled']) && !empty($servicesData['cod_amount'])) {
            $shipment->setCodAmount($servicesData['cod_amount']);
        }

        if (!empty($servicesData['insurance_enabled']) && !empty($servicesData['insurance_amount'])) {
            $shipment->setInsuranceAmount($servicesData['insurance_amount']);
        }

        if (!empty($servicesData['delivery_instructions'])) {
            $shipment->setSpecialInstructions($servicesData['delivery_instructions']);
        }
    }
}