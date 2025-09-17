<?php

declare(strict_types=1);

namespace App\Controller;

use App\Courier\InPost\Service\InPostWorkflowService;
use App\Repository\ShipmentRepository;
use App\Service\CourierSecretsService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/webhooks/inpost', name: 'inpost_webhook_', methods: ['POST'])]
class InPostWebhookController extends AbstractController
{
    public function __construct(
        private readonly InPostWorkflowService $workflowService,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly CourierSecretsService $courierSecretsService,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/status-update', name: 'status_update')]
    public function statusUpdate(Request $request): JsonResponse
    {
        try {
            // Validate webhook signature
            if (!$this->validateWebhookSignature($request)) {
                $this->logger->warning('Invalid InPost webhook signature', [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                ]);
                
                return new JsonResponse(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
            }

            $payload = json_decode($request->getContent(), true);
            
            if (!$payload) {
                return new JsonResponse(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
            }

            $this->logger->info('Received InPost webhook', [
                'event_type' => $payload['event_type'] ?? 'unknown',
                'tracking_number' => $payload['tracking_number'] ?? null,
            ]);

            $processed = $this->processWebhookEvent($payload);
            
            return new JsonResponse([
                'status' => 'success',
                'processed' => $processed,
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('InPost webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->getContent(),
            ]);
            
            return new JsonResponse([
                'error' => 'Webhook processing failed'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/parcel-delivered', name: 'parcel_delivered')]
    public function parcelDelivered(Request $request): JsonResponse
    {
        try {
            if (!$this->validateWebhookSignature($request)) {
                return new JsonResponse(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
            }

            $payload = json_decode($request->getContent(), true);
            $trackingNumber = $payload['tracking_number'] ?? null;
            
            if (!$trackingNumber) {
                return new JsonResponse(['error' => 'Missing tracking number'], Response::HTTP_BAD_REQUEST);
            }

            // Update shipment status to delivered
            $shipment = $this->shipmentRepository->findByTrackingNumber($trackingNumber);
            if ($shipment) {
                $this->workflowService->updateShipmentTracking($trackingNumber);
                
                $this->logger->info('InPost parcel delivered notification processed', [
                    'tracking_number' => $trackingNumber,
                    'delivered_at' => $payload['delivered_at'] ?? null,
                ]);
            }

            return new JsonResponse(['status' => 'success']);
            
        } catch (\Exception $e) {
            $this->logger->error('InPost delivery webhook failed', [
                'error' => $e->getMessage(),
                'payload' => $request->getContent(),
            ]);
            
            return new JsonResponse(['error' => 'Processing failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/parcel-pickup', name: 'parcel_pickup')]
    public function parcelPickup(Request $request): JsonResponse
    {
        try {
            if (!$this->validateWebhookSignature($request)) {
                return new JsonResponse(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
            }

            $payload = json_decode($request->getContent(), true);
            $trackingNumber = $payload['tracking_number'] ?? null;
            
            if (!$trackingNumber) {
                return new JsonResponse(['error' => 'Missing tracking number'], Response::HTTP_BAD_REQUEST);
            }

            // Update shipment tracking
            $this->workflowService->updateShipmentTracking($trackingNumber);
            
            $this->logger->info('InPost parcel pickup notification processed', [
                'tracking_number' => $trackingNumber,
                'pickup_location' => $payload['pickup_location'] ?? null,
                'picked_up_at' => $payload['picked_up_at'] ?? null,
            ]);

            return new JsonResponse(['status' => 'success']);
            
        } catch (\Exception $e) {
            $this->logger->error('InPost pickup webhook failed', [
                'error' => $e->getMessage(),
                'payload' => $request->getContent(),
            ]);
            
            return new JsonResponse(['error' => 'Processing failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/parcel-ready', name: 'parcel_ready')]
    public function parcelReady(Request $request): JsonResponse
    {
        try {
            if (!$this->validateWebhookSignature($request)) {
                return new JsonResponse(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
            }

            $payload = json_decode($request->getContent(), true);
            $trackingNumber = $payload['tracking_number'] ?? null;
            
            if (!$trackingNumber) {
                return new JsonResponse(['error' => 'Missing tracking number'], Response::HTTP_BAD_REQUEST);
            }

            // Update shipment status and send notification to customer
            $this->workflowService->updateShipmentTracking($trackingNumber);
            
            // Here you would typically trigger a customer notification
            // This could be done through an event system
            
            $this->logger->info('InPost parcel ready for pickup', [
                'tracking_number' => $trackingNumber,
                'paczkomat_code' => $payload['paczkomat_code'] ?? null,
                'open_code' => $payload['open_code'] ?? null,
            ]);

            return new JsonResponse(['status' => 'success']);
            
        } catch (\Exception $e) {
            $this->logger->error('InPost ready webhook failed', [
                'error' => $e->getMessage(),
                'payload' => $request->getContent(),
            ]);
            
            return new JsonResponse(['error' => 'Processing failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/parcel-expired', name: 'parcel_expired')]
    public function parcelExpired(Request $request): JsonResponse
    {
        try {
            if (!$this->validateWebhookSignature($request)) {
                return new JsonResponse(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
            }

            $payload = json_decode($request->getContent(), true);
            $trackingNumber = $payload['tracking_number'] ?? null;
            
            if (!$trackingNumber) {
                return new JsonResponse(['error' => 'Missing tracking number'], Response::HTTP_BAD_REQUEST);
            }

            // Update shipment status - parcel will be returned to sender
            $this->workflowService->updateShipmentTracking($trackingNumber);
            
            $this->logger->warning('InPost parcel expired in Paczkomat', [
                'tracking_number' => $trackingNumber,
                'paczkomat_code' => $payload['paczkomat_code'] ?? null,
                'expired_at' => $payload['expired_at'] ?? null,
            ]);

            return new JsonResponse(['status' => 'success']);
            
        } catch (\Exception $e) {
            $this->logger->error('InPost expired webhook failed', [
                'error' => $e->getMessage(),
                'payload' => $request->getContent(),
            ]);
            
            return new JsonResponse(['error' => 'Processing failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function validateWebhookSignature(Request $request): bool
    {
        $signature = $request->headers->get('X-InPost-Signature');
        
        if (!$signature) {
            return false;
        }

        $webhookToken = $this->courierSecretsService->getWebhookToken('inpost');
        
        if (!$webhookToken) {
            $this->logger->error('InPost webhook token not configured');
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $webhookToken);
        
        return hash_equals($expectedSignature, $signature);
    }

    private function processWebhookEvent(array $payload): bool
    {
        $eventType = $payload['event_type'] ?? null;
        $trackingNumber = $payload['tracking_number'] ?? null;
        
        if (!$eventType || !$trackingNumber) {
            return false;
        }

        switch ($eventType) {
            case 'status_changed':
                return $this->handleStatusChanged($payload);
                
            case 'parcel_delivered':
                return $this->handleParcelDelivered($payload);
                
            case 'parcel_picked_up':
                return $this->handleParcelPickedUp($payload);
                
            case 'parcel_ready_to_pickup':
                return $this->handleParcelReady($payload);
                
            case 'parcel_expired':
                return $this->handleParcelExpired($payload);
                
            default:
                $this->logger->warning('Unknown InPost webhook event type', [
                    'event_type' => $eventType,
                    'tracking_number' => $trackingNumber,
                ]);
                return false;
        }
    }

    private function handleStatusChanged(array $payload): bool
    {
        $trackingNumber = $payload['tracking_number'];
        $newStatus = $payload['new_status'] ?? null;
        $oldStatus = $payload['old_status'] ?? null;
        
        if (!$newStatus) {
            return false;
        }

        $updated = $this->workflowService->updateShipmentTracking($trackingNumber);
        
        if ($updated) {
            $this->logger->info('InPost status changed', [
                'tracking_number' => $trackingNumber,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
        }
        
        return $updated;
    }

    private function handleParcelDelivered(array $payload): bool
    {
        $trackingNumber = $payload['tracking_number'];
        
        $updated = $this->workflowService->updateShipmentTracking($trackingNumber);
        
        if ($updated) {
            // Trigger delivery confirmation email/SMS
            // This would be handled by an event system in a real application
            
            $this->logger->info('InPost parcel delivered', [
                'tracking_number' => $trackingNumber,
                'delivered_at' => $payload['delivered_at'] ?? null,
                'recipient_name' => $payload['recipient_name'] ?? null,
            ]);
        }
        
        return $updated;
    }

    private function handleParcelPickedUp(array $payload): bool
    {
        $trackingNumber = $payload['tracking_number'];
        
        $updated = $this->workflowService->updateShipmentTracking($trackingNumber);
        
        if ($updated) {
            $this->logger->info('InPost parcel picked up', [
                'tracking_number' => $trackingNumber,
                'picked_up_at' => $payload['picked_up_at'] ?? null,
                'pickup_location' => $payload['pickup_location'] ?? null,
            ]);
        }
        
        return $updated;
    }

    private function handleParcelReady(array $payload): bool
    {
        $trackingNumber = $payload['tracking_number'];
        
        $updated = $this->workflowService->updateShipmentTracking($trackingNumber);
        
        if ($updated) {
            // Send pickup notification to customer
            // This would trigger SMS/email with pickup code
            
            $this->logger->info('InPost parcel ready for pickup', [
                'tracking_number' => $trackingNumber,
                'paczkomat_code' => $payload['paczkomat_code'] ?? null,
                'open_code' => $payload['open_code'] ?? null,
                'expires_at' => $payload['expires_at'] ?? null,
            ]);
        }
        
        return $updated;
    }

    private function handleParcelExpired(array $payload): bool
    {
        $trackingNumber = $payload['tracking_number'];
        
        $updated = $this->workflowService->updateShipmentTracking($trackingNumber);
        
        if ($updated) {
            // Send expiration notification
            // Parcel will be returned to sender
            
            $this->logger->warning('InPost parcel expired', [
                'tracking_number' => $trackingNumber,
                'expired_at' => $payload['expired_at'] ?? null,
                'paczkomat_code' => $payload['paczkomat_code'] ?? null,
            ]);
        }
        
        return $updated;
    }
}