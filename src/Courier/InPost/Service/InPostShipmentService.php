<?php

declare(strict_types=1);

namespace App\Courier\InPost\Service;

use App\Service\InPostApiClient;
use App\Courier\InPost\DTO\InPostShipmentRequestDTO;
use App\Courier\InPost\DTO\InPostShipmentResponseDTO;
use App\Courier\InPost\Exception\InPostIntegrationException;
use App\Entity\Shipment;
use App\Repository\ShipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class InPostShipmentService
{
    public function __construct(
        private readonly InPostApiClient $apiClient,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create and send shipment to InPost
     */
    public function createShipment(InPostShipmentRequestDTO $request): InPostShipmentResponseDTO
    {
        try {
            $this->logger->info('Creating InPost shipment', [
                'receiver' => $request->getReceiver(),
                'service' => $request->getService(),
                'reference' => $request->getReference()
            ]);

            // Send to InPost API
            $response = $this->apiClient->createShipment($request->toArray());
            
            // Create response DTO
            $shipmentResponse = new InPostShipmentResponseDTO($response);
            
            // TODO: Save to database (requires Order entity)
            // For now, skip database operations until Order-Shipment relation is resolved
            // $shipment = $this->createShipmentEntity($request, $shipmentResponse);
            // $this->entityManager->persist($shipment);
            // $this->entityManager->flush();
            
            $this->logger->info('InPost shipment created successfully', [
                'shipment_id' => $shipmentResponse->getId(),
                'tracking_number' => $shipmentResponse->getTrackingNumber(),
                'status' => $shipmentResponse->getStatus()
            ]);
            
            return $shipmentResponse;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to create InPost shipment', [
                'error' => $e->getMessage(),
                'request' => $request->toArray()
            ]);
            
            throw new InPostIntegrationException(
                'Failed to create shipment: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get shipment label as PDF
     */
    public function getShipmentLabel(string $shipmentId): string
    {
        try {
            $this->logger->info('Retrieving shipment label', ['shipment_id' => $shipmentId]);
            
            $labelPdf = $this->apiClient->getShipmentLabel($shipmentId);
            
            $this->logger->info('Shipment label retrieved successfully', [
                'shipment_id' => $shipmentId,
                'size' => strlen($labelPdf)
            ]);
            
            return $labelPdf;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve shipment label', [
                'shipment_id' => $shipmentId,
                'error' => $e->getMessage()
            ]);
            
            throw new InPostIntegrationException(
                'Failed to retrieve label: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Update shipment status from tracking
     */
    public function updateShipmentStatus(string $trackingNumber): array
    {
        try {
            $this->logger->info('Updating shipment status', ['tracking_number' => $trackingNumber]);
            
            // Get tracking info from InPost
            $trackingData = $this->apiClient->trackShipment($trackingNumber);
            
            // Find shipment in database
            $shipment = $this->shipmentRepository->findOneBy(['trackingNumber' => $trackingNumber]);
            
            if ($shipment) {
                $oldStatus = $shipment->getStatus();
                $newStatus = $this->mapInPostStatus($trackingData['status'] ?? '');
                
                $shipment->setStatus($newStatus);
                $shipment->setTrackingData($trackingData);
                $shipment->setUpdatedAt(new \DateTimeImmutable());
                
                $this->entityManager->flush();
                
                $this->logger->info('Shipment status updated', [
                    'tracking_number' => $trackingNumber,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]);
                
                return [
                    'updated' => true,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'tracking_data' => $trackingData
                ];
            }
            
            $this->logger->warning('Shipment not found for tracking number', [
                'tracking_number' => $trackingNumber
            ]);
            
            return [
                'updated' => false,
                'message' => 'Shipment not found',
                'tracking_data' => $trackingData
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to update shipment status', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage()
            ]);
            
            throw new InPostIntegrationException(
                'Failed to update status: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get available pickup points for postal code
     */
    public function getPickupPoints(string $postCode, int $limit = 10): array
    {
        try {
            $points = $this->apiClient->getParcelLockers([
                'relative_post_code' => $postCode,
                'status' => 'Operating',
                'type' => 'parcel_locker',
                'limit' => $limit
            ]);
            
            return $points['items'] ?? [];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get pickup points', [
                'post_code' => $postCode,
                'error' => $e->getMessage()
            ]);
            
            throw new InPostIntegrationException(
                'Failed to get pickup points: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Process webhook notification
     */
    public function processWebhook(array $webhookData): array
    {
        try {
            $topic = $webhookData['topic'] ?? '';
            $eventId = $webhookData['event_id'] ?? '';
            
            $this->logger->info('Processing InPost webhook', [
                'topic' => $topic,
                'event_id' => $eventId
            ]);
            
            switch ($topic) {
                case 'Shipment.Tracking':
                    return $this->processTrackingWebhook($webhookData);
                    
                case 'Shipment.Status':
                    return $this->processStatusWebhook($webhookData);
                    
                default:
                    $this->logger->warning('Unknown webhook topic', ['topic' => $topic]);
                    return ['processed' => false, 'reason' => 'Unknown topic'];
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to process webhook', [
                'webhook_data' => $webhookData,
                'error' => $e->getMessage()
            ]);
            
            throw new InPostIntegrationException(
                'Failed to process webhook: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function createShipmentEntity(
        InPostShipmentRequestDTO $request, 
        InPostShipmentResponseDTO $response
    ): Shipment {
        $receiver = $request->getReceiver();
        $sender = $request->getSender() ?? [];
        $parcels = $request->getParcels();
        
        $shipment = new Shipment();
        
        // Basic shipment info
        $shipment->setCourierService('inpost');
        $shipment->setServiceType($request->getService());
        $shipment->setCourierShipmentId($response->getId());
        $shipment->setTrackingNumber($response->getTrackingNumber() ?? 'PENDING');
        $shipment->setStatus($this->mapInPostStatus($response->getStatus()));
        
        // Receiver data
        $shipment->setRecipientName($receiver['first_name'] . ' ' . ($receiver['last_name'] ?? ''));
        $shipment->setRecipientEmail($receiver['email'] ?? '');
        $shipment->setRecipientPhone($receiver['phone'] ?? '');
        $shipment->setRecipientAddress($receiver['address']['street'] ?? '');
        $shipment->setRecipientPostalCode($receiver['address']['post_code'] ?? '');
        $shipment->setRecipientCity($receiver['address']['city'] ?? '');
        $shipment->setRecipientCountry($receiver['address']['country_code'] ?? 'PL');
        
        // Sender data (use default if not provided)
        $shipment->setSenderName($sender['name'] ?? 'Sky Broker System');
        $shipment->setSenderEmail($sender['email'] ?? 'admin@skybroker.pl');
        $shipment->setSenderAddress($sender['address']['street'] ?? 'ul. Testowa 1');
        $shipment->setSenderPostalCode($sender['address']['post_code'] ?? '00-001');
        $shipment->setSenderCity($sender['address']['city'] ?? 'Warszawa');
        $shipment->setSenderCountry($sender['address']['country_code'] ?? 'PL');
        $shipment->setSenderPhone($sender['phone'] ?? null);
        
        // Parcel data
        if (!empty($parcels)) {
            $parcel = $parcels[0];
            $shipment->setTotalWeight((string) ($parcel['weight']['amount'] ?? 0.5));
        }
        
        // Additional metadata
        $shipment->setCourierMetadata([
            'custom_attributes' => $request->getCustomAttributes(),
            'comments' => $request->getComments(),
            'api_response' => $response->getRawResponse()
        ]);
        
        return $shipment;
    }

    private function mapInPostStatus(string $inPostStatus): string
    {
        return match ($inPostStatus) {
            'created', 'confirmed' => 'created',
            'dispatched_by_sender' => 'in_transit',
            'delivered_to_pok' => 'delivered_to_pickup_point',
            'collected_from_pok' => 'delivered',
            'returned_to_sender' => 'returned',
            'canceled' => 'cancelled',
            default => $inPostStatus
        };
    }

    private function processTrackingWebhook(array $webhookData): array
    {
        $trackingNumber = $webhookData['data']['tracking_number'] ?? '';
        
        if ($trackingNumber) {
            $result = $this->updateShipmentStatus($trackingNumber);
            return ['processed' => true, 'result' => $result];
        }
        
        return ['processed' => false, 'reason' => 'No tracking number'];
    }

    private function processStatusWebhook(array $webhookData): array
    {
        // Similar to tracking webhook, but for status changes
        return $this->processTrackingWebhook($webhookData);
    }
}