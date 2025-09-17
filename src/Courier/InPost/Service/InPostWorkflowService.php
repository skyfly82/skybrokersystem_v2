<?php

declare(strict_types=1);

namespace App\Courier\InPost\Service;

use App\Courier\InPost\Contracts\InPostServiceInterface;
use App\Courier\InPost\DTO\InPostShipmentRequestDTO;
use App\Courier\InPost\DTO\InPostShipmentResponseDTO;
use App\Courier\InPost\Exception\InPostIntegrationException;
use App\Courier\InPost\Enum\ParcelSize;
use App\Entity\Order;
use App\Entity\Shipment;
use App\Entity\ShipmentItem;
use App\Entity\ShipmentTracking;
use App\Repository\ShipmentRepository;
use App\Repository\ShipmentTrackingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class InPostWorkflowService
{
    public function __construct(
        private readonly InPostServiceInterface $inPostService,
        private readonly EntityManagerInterface $entityManager,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly ShipmentTrackingRepository $trackingRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create InPost shipment from order
     */
    public function createShipmentFromOrder(
        Order $order,
        array $shippingOptions = []
    ): Shipment {
        $this->validateOrderForShipment($order);

        try {
            // Build InPost shipment request
            $request = $this->buildShipmentRequestFromOrder($order, $shippingOptions);
            
            // Create shipment via InPost API
            $response = $this->inPostService->createShipment($request);
            
            // Create local shipment entity
            $shipment = $this->createShipmentEntity($order, $request, $response);
            
            // Create shipment items
            $this->createShipmentItems($shipment, $order);
            
            // Save to database
            $this->entityManager->persist($shipment);
            $this->entityManager->flush();
            
            // Create initial tracking event
            $this->createInitialTrackingEvent($shipment);
            
            // Update order status
            $order->setStatus('shipped');
            $this->entityManager->flush();
            
            $this->logger->info('InPost shipment created successfully', [
                'order_id' => $order->getId(),
                'tracking_number' => $shipment->getTrackingNumber(),
                'shipment_id' => $shipment->getId(),
            ]);
            
            return $shipment;
            
        } catch (InPostIntegrationException $e) {
            $this->logger->error('Failed to create InPost shipment', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
                'error_details' => $e->getErrorDetails(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Update shipment tracking information
     */
    public function updateShipmentTracking(string $trackingNumber): bool
    {
        try {
            $shipment = $this->shipmentRepository->findByTrackingNumber($trackingNumber);
            if (!$shipment) {
                $this->logger->warning('Shipment not found for tracking update', [
                    'tracking_number' => $trackingNumber,
                ]);
                return false;
            }

            // Get latest tracking details from InPost
            $trackingDetails = $this->inPostService->getTrackingDetails($trackingNumber);
            
            // Update shipment status
            $oldStatus = $shipment->getStatus();
            $shipment->setStatus($trackingDetails->status);
            
            if ($trackingDetails->deliveredAt) {
                $shipment->setDeliveredAt(new \DateTimeImmutable($trackingDetails->deliveredAt->format('c')));
                $shipment->getOrder()->setStatus('delivered');
            }
            
            if ($trackingDetails->estimatedDelivery) {
                $shipment->setEstimatedDeliveryAt(new \DateTimeImmutable($trackingDetails->estimatedDelivery->format('c')));
            }

            // Create tracking events for new events only
            foreach ($trackingDetails->events as $eventData) {
                if (!empty($eventData['event_id']) && 
                    !$this->trackingRepository->eventExists($shipment, $eventData['event_id'])) {
                    
                    $trackingEvent = ShipmentTracking::createFromArray($eventData);
                    $trackingEvent->setShipment($shipment);
                    $shipment->addTrackingEvent($trackingEvent);
                }
            }
            
            $this->entityManager->flush();
            
            $this->logger->info('Shipment tracking updated', [
                'tracking_number' => $trackingNumber,
                'old_status' => $oldStatus,
                'new_status' => $shipment->getStatus(),
                'events_added' => count($trackingDetails->events),
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to update shipment tracking', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Cancel InPost shipment
     */
    public function cancelShipment(string $trackingNumber): bool
    {
        try {
            $shipment = $this->shipmentRepository->findByTrackingNumber($trackingNumber);
            if (!$shipment) {
                throw new InPostIntegrationException("Shipment not found: {$trackingNumber}");
            }

            if (!$shipment->canBeCanceled()) {
                throw new InPostIntegrationException("Shipment cannot be canceled in current status: {$shipment->getStatus()}");
            }

            // Cancel via InPost API
            $canceled = $this->inPostService->cancelShipment($trackingNumber);
            
            if ($canceled) {
                // Update local shipment
                $shipment->setStatus('canceled');
                $shipment->getOrder()->setStatus('canceled');
                
                // Add tracking event
                $cancelEvent = new ShipmentTracking();
                $cancelEvent->setShipment($shipment);
                $cancelEvent->setStatus('canceled');
                $cancelEvent->setDescription('Shipment canceled by user');
                $cancelEvent->setEventDate(new \DateTimeImmutable());
                $shipment->addTrackingEvent($cancelEvent);
                
                $this->entityManager->flush();
                
                $this->logger->info('Shipment canceled successfully', [
                    'tracking_number' => $trackingNumber,
                ]);
            }
            
            return $canceled;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel shipment', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Generate and store shipping label
     */
    public function generateShippingLabel(string $trackingNumber, string $format = 'pdf'): ?string
    {
        try {
            $shipment = $this->shipmentRepository->findByTrackingNumber($trackingNumber);
            if (!$shipment) {
                return null;
            }

            // Generate label via InPost API
            $labelData = $this->inPostService->generateLabel($trackingNumber, $format);
            
            // In a real implementation, you would save the label to storage
            // For now, we'll just store the base64 data in a temporary location
            $labelPath = $this->storeLabelData($labelData, $trackingNumber, $format);
            
            // Update shipment with label URL
            $shipment->setLabelUrl($labelPath);
            $this->entityManager->flush();
            
            $this->logger->info('Shipping label generated', [
                'tracking_number' => $trackingNumber,
                'format' => $format,
                'label_path' => $labelPath,
            ]);
            
            return $labelPath;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate shipping label', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Bulk update tracking for all active InPost shipments
     */
    public function updateAllActiveShipments(): array
    {
        $activeShipments = $this->shipmentRepository->findShipmentsForStatusSync('inpost');
        $results = [
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];
        
        foreach ($activeShipments as $shipment) {
            try {
                if ($this->updateShipmentTracking($shipment->getTrackingNumber())) {
                    $results['updated']++;
                } else {
                    $results['failed']++;
                }
                
                // Add small delay to respect API rate limits
                usleep(200000); // 200ms delay
                
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'tracking_number' => $shipment->getTrackingNumber(),
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        $this->logger->info('Bulk tracking update completed', $results);
        
        return $results;
    }

    private function validateOrderForShipment(Order $order): void
    {
        if (!$order->canBeShipped()) {
            throw new InPostIntegrationException("Order cannot be shipped in current status: {$order->getStatus()}");
        }

        if ($order->getItems()->isEmpty()) {
            throw new InPostIntegrationException('Order has no items to ship');
        }

        $customer = $order->getCustomer();
        if (!$customer->getAddress() || !$customer->getPostalCode() || !$customer->getCity()) {
            throw new InPostIntegrationException('Customer address is incomplete');
        }
    }

    private function buildShipmentRequestFromOrder(Order $order, array $options): InPostShipmentRequestDTO
    {
        $customer = $order->getCustomer();
        
        $request = new InPostShipmentRequestDTO();
        
        // Sender information (business/company sending the package)
        $request->senderName = $options['sender_name'] ?? 'SkyBroker System';
        $request->senderEmail = $options['sender_email'] ?? 'noreply@skybroker.com';
        $request->senderAddress = $options['sender_address'] ?? 'ul. Przykładowa 1';
        $request->senderPostalCode = $options['sender_postal_code'] ?? '00-001';
        $request->senderPhone = $options['sender_phone'] ?? null;
        
        // Recipient information
        $request->recipientName = $customer->getCompanyName() ?? 'Unknown Customer';
        $request->recipientEmail = $customer->getEmail() ?? '';
        $request->recipientAddress = $customer->getAddress() ?? '';
        $request->recipientPostalCode = $customer->getPostalCode() ?? '';
        $request->recipientPhone = $customer->getPhone() ?? '';
        
        // Ensure phone number is in Polish format
        if ($request->recipientPhone && !str_starts_with($request->recipientPhone, '+48')) {
            $request->recipientPhone = '+48' . ltrim($request->recipientPhone, '0');
        }
        
        // Package details
        $totalWeight = 0;
        $totalValue = 0;
        $maxWidth = 0;
        $maxHeight = 0;
        $maxLength = 0;
        
        foreach ($order->getItems() as $item) {
            if ($item->getWeight()) {
                $totalWeight += (float) $item->getWeight() * $item->getQuantity();
            }
            $totalValue += (float) $item->getTotalPrice();
            
            if ($dimensions = $item->getDimensions()) {
                $maxWidth = max($maxWidth, $dimensions['width']);
                $maxHeight = max($maxHeight, $dimensions['height']);
                $maxLength = max($maxLength, $dimensions['length']);
            }
        }
        
        $request->weight = max($totalWeight, 0.1); // Minimum weight
        $request->width = $maxWidth > 0 ? $maxWidth : null;
        $request->height = $maxHeight > 0 ? $maxHeight : null;
        $request->length = $maxLength > 0 ? $maxLength : null;
        
        // Determine parcel size
        $request->parcelSize = $options['parcel_size'] ?? 
            ParcelSize::getRecommendedSize($request->weight, [
                'width' => $request->width,
                'height' => $request->height,
                'length' => $request->length,
            ])->value;
        
        // Delivery options
        $request->deliveryMethod = $options['delivery_method'] ?? 'paczkomaty';
        $request->targetPaczkomat = $options['target_paczkomat'] ?? null;
        $request->serviceType = $options['service_type'] ?? 'standard';
        
        // Additional services
        $request->codAmount = isset($options['cod_amount']) ? (float) $options['cod_amount'] : null;
        $request->insuranceAmount = isset($options['insurance_amount']) ? (float) $options['insurance_amount'] : null;
        $request->customerReference = $order->getOrderNumber();
        $request->specialInstructions = $options['special_instructions'] ?? null;
        
        return $request;
    }

    private function createShipmentEntity(
        Order $order,
        InPostShipmentRequestDTO $request,
        InPostShipmentResponseDTO $response
    ): Shipment {
        $shipment = new Shipment();
        $shipment->setOrder($order);
        $shipment->setTrackingNumber($response->trackingNumber);
        $shipment->setCourierShipmentId($response->shipmentId);
        $shipment->setCourierService('inpost');
        $shipment->setStatus($response->status);
        
        // Sender details
        $shipment->setSenderName($request->senderName);
        $shipment->setSenderEmail($request->senderEmail);
        $shipment->setSenderAddress($request->senderAddress);
        $shipment->setSenderPostalCode($request->senderPostalCode);
        $shipment->setSenderCity('Warsaw'); // Default, should be configurable
        $shipment->setSenderPhone($request->senderPhone);
        
        // Recipient details
        $shipment->setRecipientName($request->recipientName);
        $shipment->setRecipientEmail($request->recipientEmail);
        $shipment->setRecipientAddress($request->recipientAddress);
        $shipment->setRecipientPostalCode($request->recipientPostalCode);
        $shipment->setRecipientCity($order->getCustomer()->getCity() ?? 'Unknown');
        $shipment->setRecipientPhone($request->recipientPhone);
        
        // Package details
        $shipment->setTotalWeight((string) $request->weight);
        $shipment->setTotalValue($order->getTotalAmount());
        $shipment->setServiceType($request->serviceType);
        $shipment->setSpecialInstructions($request->specialInstructions);
        $shipment->setCodAmount($request->codAmount ? (string) $request->codAmount : null);
        $shipment->setInsuranceAmount($request->insuranceAmount ? (string) $request->insuranceAmount : null);
        
        // InPost-specific metadata
        $metadata = [
            'parcel_size' => $request->parcelSize,
            'delivery_method' => $request->deliveryMethod,
            'target_paczkomat' => $request->targetPaczkomat,
            'total_amount' => $response->totalAmount,
            'parcel_dimensions' => $response->parcelDimensions,
            'additional_services' => $response->additionalServices,
        ];
        
        if ($response->paczkomatCode) {
            $metadata['paczkomat_code'] = $response->paczkomatCode;
            $metadata['paczkomat_name'] = $response->paczkomatName;
            $metadata['paczkomat_address'] = $response->paczkomatAddress;
            $metadata['open_code'] = $response->openCode;
        }
        
        $shipment->setCourierMetadata($metadata);
        
        if ($response->estimatedDelivery) {
            $shipment->setEstimatedDeliveryAt(new \DateTimeImmutable($response->estimatedDelivery->format('c')));
        }
        
        return $shipment;
    }

    private function createShipmentItems(Shipment $shipment, Order $order): void
    {
        foreach ($order->getItems() as $orderItem) {
            $shipmentItem = ShipmentItem::fromOrderItem($orderItem);
            $shipment->addItem($shipmentItem);
        }
    }

    private function createInitialTrackingEvent(Shipment $shipment): void
    {
        $trackingEvent = new ShipmentTracking();
        $trackingEvent->setShipment($shipment);
        $trackingEvent->setStatus('created');
        $trackingEvent->setDescription('Shipment created in InPost system');
        $trackingEvent->setEventDate(new \DateTimeImmutable());
        $trackingEvent->setCourierEventId('initial_' . $shipment->getTrackingNumber());
        
        $shipment->addTrackingEvent($trackingEvent);
    }

    private function storeLabelData(string $base64Data, string $trackingNumber, string $format): string
    {
        // In a real implementation, this would save to cloud storage (S3, etc.)
        // For now, we'll create a temporary storage path
        $labelDir = sys_get_temp_dir() . '/inpost_labels';
        if (!is_dir($labelDir)) {
            mkdir($labelDir, 0755, true);
        }
        
        $filename = "label_{$trackingNumber}.{$format}";
        $filepath = $labelDir . '/' . $filename;
        
        file_put_contents($filepath, base64_decode($base64Data));
        
        return $filepath;
    }
}