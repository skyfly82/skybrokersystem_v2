<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Courier\InPost\Service\InPostShipmentService;
use App\Courier\InPost\DTO\InPostShipmentRequestDTO;
use App\Courier\InPost\Exception\InPostIntegrationException;
use App\Service\CourierSecretsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

#[Route('/api/inpost', name: 'api_inpost_')]
class InPostController extends AbstractController
{
    public function __construct(
        private readonly InPostShipmentService $shipmentService,
        private readonly CourierSecretsService $secretsService,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create new shipment
     */
    #[Route('/shipments', name: 'create_shipment', methods: ['POST'])]
    public function createShipment(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
            }
            
            // Validate required fields
            $requiredFields = ['receiver', 'parcels', 'service'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return $this->json(['error' => "Missing required field: {$field}"], Response::HTTP_BAD_REQUEST);
                }
            }
            
            // Create DTO
            $shipmentRequest = new InPostShipmentRequestDTO($data);
            
            // Validate DTO
            $errors = $this->validator->validate($shipmentRequest);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }
            
            // Create shipment
            $response = $this->shipmentService->createShipment($shipmentRequest);
            
            return $this->json([
                'success' => true,
                'data' => [
                    'id' => $response->getId(),
                    'tracking_number' => $response->getTrackingNumber(),
                    'status' => $response->getStatus(),
                    'reference' => $response->getReference(),
                    'created_at' => $response->getCreatedAt()
                ]
            ], Response::HTTP_CREATED);
            
        } catch (InPostIntegrationException $e) {
            $this->logger->error('InPost integration error in createShipment', [
                'error' => $e->getMessage(),
                'data' => $data ?? null
            ]);
            
            return $this->json([
                'success' => false,
                'error' => 'Failed to create shipment',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
            
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in createShipment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->json([
                'success' => false,
                'error' => 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get shipment label
     */
    #[Route('/shipments/{shipmentId}/label', name: 'get_label', methods: ['GET'])]
    public function getShipmentLabel(string $shipmentId): Response
    {
        try {
            $labelPdf = $this->shipmentService->getShipmentLabel($shipmentId);
            
            return new Response($labelPdf, Response::HTTP_OK, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="inpost-label-' . $shipmentId . '.pdf"',
                'Content-Length' => strlen($labelPdf)
            ]);
            
        } catch (InPostIntegrationException $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to get label',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
            
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in getShipmentLabel', [
                'shipment_id' => $shipmentId,
                'error' => $e->getMessage()
            ]);
            
            return $this->json([
                'success' => false,
                'error' => 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update shipment status
     */
    #[Route('/shipments/{trackingNumber}/status', name: 'update_status', methods: ['PUT'])]
    public function updateShipmentStatus(string $trackingNumber): JsonResponse
    {
        try {
            $result = $this->shipmentService->updateShipmentStatus($trackingNumber);
            
            return $this->json([
                'success' => true,
                'data' => $result
            ]);
            
        } catch (InPostIntegrationException $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to update status',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
            
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in updateShipmentStatus', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage()
            ]);
            
            return $this->json([
                'success' => false,
                'error' => 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get pickup points
     */
    #[Route('/pickup-points', name: 'pickup_points', methods: ['GET'])]
    public function getPickupPoints(Request $request): JsonResponse
    {
        try {
            $postCode = $request->query->get('post_code');
            $limit = (int) $request->query->get('limit', 10);
            
            if (!$postCode) {
                return $this->json(['error' => 'Missing post_code parameter'], Response::HTTP_BAD_REQUEST);
            }
            
            $points = $this->shipmentService->getPickupPoints($postCode, $limit);
            
            return $this->json([
                'success' => true,
                'data' => $points,
                'count' => count($points)
            ]);
            
        } catch (InPostIntegrationException $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to get pickup points',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
            
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in getPickupPoints', [
                'error' => $e->getMessage()
            ]);
            
            return $this->json([
                'success' => false,
                'error' => 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Webhook endpoint for InPost notifications
     */
    #[Route('/webhook', name: 'webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        try {
            // Verify webhook signature if configured
            $signature = $request->headers->get('x-inpost-signature');
            $webhookToken = $this->secretsService->getWebhookToken('inpost');
            
            if ($webhookToken && $signature) {
                $expectedSignature = hash_hmac('sha256', $request->getContent(), $webhookToken);
                
                if (!hash_equals($expectedSignature, $signature)) {
                    $this->logger->warning('Invalid webhook signature', [
                        'expected' => $expectedSignature,
                        'received' => $signature
                    ]);
                    
                    return $this->json(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
                }
            }
            
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
            }
            
            // Add webhook headers to data
            $data['headers'] = [
                'topic' => $request->headers->get('x-inpost-topic'),
                'event_id' => $request->headers->get('x-inpost-event-id'),
                'timestamp' => $request->headers->get('x-inpost-timestamp'),
                'api_version' => $request->headers->get('x-inpost-api-version')
            ];
            
            $result = $this->shipmentService->processWebhook($data);
            
            return $this->json([
                'success' => true,
                'data' => $result
            ]);
            
        } catch (InPostIntegrationException $e) {
            $this->logger->error('InPost webhook processing error', [
                'error' => $e->getMessage(),
                'payload' => $request->getContent()
            ]);
            
            return $this->json([
                'success' => false,
                'error' => 'Webhook processing failed',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
            
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in webhook', [
                'error' => $e->getMessage(),
                'payload' => $request->getContent()
            ]);
            
            return $this->json([
                'success' => false,
                'error' => 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}