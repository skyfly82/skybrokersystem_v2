<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Courier\Meest\Entity\MeestShipment;
use App\Domain\Courier\Meest\Exception\MeestIntegrationException;
use App\Domain\Courier\Meest\Exception\MeestValidationException;
use App\Domain\Courier\Meest\Repository\MeestShipmentRepository;
use App\Domain\Courier\Meest\Service\MeestLabelService;
use App\Domain\Courier\Meest\Service\MeestShipmentService;
use App\Domain\Courier\Meest\Service\MeestTrackingService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * REST API Controller for MEEST shipment operations
 */
#[Route('/v2/api/meest', name: 'api_meest_')]
class MeestShipmentController extends AbstractController
{
    public function __construct(
        private readonly MeestShipmentService $shipmentService,
        private readonly MeestTrackingService $trackingService,
        private readonly MeestLabelService $labelService,
        private readonly MeestShipmentRepository $repository,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Create a new MEEST shipment
     */
    #[Route('/parcels', name: 'create_shipment', methods: ['POST'])]
    public function createShipment(Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonRequestData($request);
            $this->validateCreateShipmentRequest($data);

            $shipment = $this->shipmentService->createShipment($data);

            return $this->json([
                'success' => true,
                'data' => $this->formatShipmentResponse($shipment),
                'message' => 'Shipment created successfully'
            ], Response::HTTP_CREATED);

        } catch (MeestValidationException $e) {
            return $this->json([
                'success' => false,
                'error' => 'validation_failed',
                'message' => $e->getMessage(),
                'details' => $e->getValidationErrors()
            ], Response::HTTP_BAD_REQUEST);

        } catch (MeestIntegrationException $e) {
            $this->logger->error('MEEST API integration error', [
                'error' => $e->getMessage(),
                'request_data' => $data ?? null
            ]);

            return $this->json([
                'success' => false,
                'error' => 'integration_error',
                'message' => 'Failed to create shipment due to courier API issues'
            ], Response::HTTP_BAD_GATEWAY);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error creating MEEST shipment', [
                'error' => $e->getMessage(),
                'request_data' => $data ?? null
            ]);

            return $this->json([
                'success' => false,
                'error' => 'internal_error',
                'message' => 'An unexpected error occurred'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a return shipment
     */
    #[Route('/parcels/return', name: 'create_return_shipment', methods: ['POST'])]
    public function createReturnShipment(Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonRequestData($request);
            $this->validateReturnShipmentRequest($data);

            $shipment = $this->shipmentService->createReturnShipment(
                $data['original_tracking_number'],
                $data
            );

            return $this->json([
                'success' => true,
                'data' => $this->formatShipmentResponse($shipment),
                'message' => 'Return shipment created successfully'
            ], Response::HTTP_CREATED);

        } catch (MeestValidationException $e) {
            return $this->json([
                'success' => false,
                'error' => 'validation_failed',
                'message' => $e->getMessage(),
                'details' => $e->getValidationErrors()
            ], Response::HTTP_BAD_REQUEST);

        } catch (MeestIntegrationException $e) {
            $this->logger->error('MEEST return shipment error', [
                'error' => $e->getMessage(),
                'request_data' => $data ?? null
            ]);

            return $this->json([
                'success' => false,
                'error' => 'integration_error',
                'message' => 'Failed to create return shipment'
            ], Response::HTTP_BAD_GATEWAY);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error creating return shipment', [
                'error' => $e->getMessage(),
                'request_data' => $data ?? null
            ]);

            return $this->json([
                'success' => false,
                'error' => 'internal_error',
                'message' => 'An unexpected error occurred'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get shipment tracking information
     */
    #[Route('/tracking/{trackingNumber}', name: 'get_tracking', methods: ['GET'])]
    public function getTracking(string $trackingNumber): JsonResponse
    {
        try {
            $trackingInfo = $this->trackingService->getTrackingInfo($trackingNumber);

            return $this->json([
                'success' => true,
                'data' => [
                    'tracking_number' => $trackingNumber,
                    'status' => $trackingInfo->status->value,
                    'status_description' => $trackingInfo->status->getDescription(),
                    'last_update' => $trackingInfo->lastUpdate?->format('c'),
                    'estimated_delivery' => $trackingInfo->estimatedDelivery?->format('c'),
                    'events' => $trackingInfo->events,
                    'location' => $trackingInfo->currentLocation
                ]
            ]);

        } catch (MeestIntegrationException $e) {
            if (str_contains($e->getMessage(), 'not found')) {
                return $this->json([
                    'success' => false,
                    'error' => 'not_found',
                    'message' => 'Tracking number not found'
                ], Response::HTTP_NOT_FOUND);
            }

            return $this->json([
                'success' => false,
                'error' => 'integration_error',
                'message' => 'Failed to retrieve tracking information'
            ], Response::HTTP_BAD_GATEWAY);

        } catch (\Exception $e) {
            $this->logger->error('Error retrieving tracking info', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'internal_error',
                'message' => 'An unexpected error occurred'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get shipment details
     */
    #[Route('/shipments/{trackingNumber}', name: 'get_shipment', methods: ['GET'])]
    public function getShipment(string $trackingNumber): JsonResponse
    {
        $shipment = $this->repository->findByTrackingNumber($trackingNumber);

        if (!$shipment) {
            return $this->json([
                'success' => false,
                'error' => 'not_found',
                'message' => 'Shipment not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $this->formatShipmentResponse($shipment)
        ]);
    }

    /**
     * Download shipping label
     */
    #[Route('/labels/{trackingNumber}', name: 'download_label', methods: ['GET'])]
    public function downloadLabel(string $trackingNumber): Response
    {
        $shipment = $this->repository->findByTrackingNumber($trackingNumber);

        if (!$shipment) {
            throw new NotFoundHttpException('Shipment not found');
        }

        $labelPath = $this->labelService->getLabelPath($trackingNumber);

        if (!$labelPath) {
            // Try to regenerate label if it doesn't exist
            try {
                $labelPath = $this->labelService->regenerateLabel($trackingNumber);
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => 'label_not_available',
                    'message' => 'Label is not available for this shipment'
                ], Response::HTTP_NOT_FOUND);
            }
        }

        $response = new BinaryFileResponse($labelPath);
        $response->setContentDisposition(
            'attachment',
            "meest_label_{$trackingNumber}.pdf"
        );

        return $response;
    }

    /**
     * List shipments with pagination and filters
     */
    #[Route('/shipments', name: 'list_shipments', methods: ['GET'])]
    public function listShipments(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $status = $request->query->get('status');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');

        try {
            $filters = [];

            if ($status) {
                $filters['status'] = $status;
            }

            if ($dateFrom) {
                $filters['date_from'] = new \DateTimeImmutable($dateFrom);
            }

            if ($dateTo) {
                $filters['date_to'] = new \DateTimeImmutable($dateTo);
            }

            $shipments = $this->repository->findByDateRange(
                $filters['date_from'] ?? new \DateTimeImmutable('-30 days'),
                $filters['date_to'] ?? new \DateTimeImmutable()
            );

            // Apply status filter if needed
            if ($status) {
                $shipments = array_filter($shipments, function (MeestShipment $shipment) use ($status) {
                    return $shipment->getStatus()->value === $status;
                });
            }

            // Apply pagination
            $offset = ($page - 1) * $limit;
            $paginatedShipments = array_slice($shipments, $offset, $limit);

            return $this->json([
                'success' => true,
                'data' => array_map([$this, 'formatShipmentResponse'], $paginatedShipments),
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => count($shipments),
                    'has_more' => count($shipments) > $offset + $limit
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error listing shipments', [
                'error' => $e->getMessage(),
                'filters' => $filters ?? []
            ]);

            return $this->json([
                'success' => false,
                'error' => 'internal_error',
                'message' => 'Failed to retrieve shipments'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get JSON data from request
     */
    private function getJsonRequestData(Request $request): array
    {
        $content = $request->getContent();

        if (empty($content)) {
            throw new BadRequestHttpException('Request body cannot be empty');
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestHttpException('Invalid JSON: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Validate create shipment request
     */
    private function validateCreateShipmentRequest(array $data): void
    {
        $requiredFields = ['sender', 'recipient', 'parcel'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new BadRequestHttpException("Missing required field: {$field}");
            }
        }

        // Validate parcel value requirements for MEEST
        if (!isset($data['parcel']['value']['localTotalValue'])) {
            throw new BadRequestHttpException('parcel.value.localTotalValue is required');
        }

        if (!isset($data['parcel']['value']['localCurrency'])) {
            throw new BadRequestHttpException('parcel.value.localCurrency is required');
        }

        // Validate items if present
        if (isset($data['parcel']['items'])) {
            foreach ($data['parcel']['items'] as $index => $item) {
                if (!isset($item['value']['value'])) {
                    throw new BadRequestHttpException("parcel.items.{$index}.value.value is required");
                }
            }
        }
    }

    /**
     * Validate return shipment request
     */
    private function validateReturnShipmentRequest(array $data): void
    {
        if (!isset($data['original_tracking_number'])) {
            throw new BadRequestHttpException('original_tracking_number is required for return shipments');
        }
    }

    /**
     * Format shipment response
     */
    private function formatShipmentResponse(MeestShipment $shipment): array
    {
        return [
            'id' => $shipment->getId(),
            'tracking_number' => $shipment->getTrackingNumber(),
            'shipment_id' => $shipment->getShipmentId(),
            'status' => $shipment->getStatus()->value,
            'status_description' => $shipment->getStatus()->getDescription(),
            'shipment_type' => $shipment->getShipmentType()->value,
            'total_cost' => $shipment->getTotalCost(),
            'currency' => $shipment->getCurrency(),
            'sender' => $shipment->getSenderData(),
            'recipient' => $shipment->getRecipientData(),
            'parcel' => $shipment->getParcelData(),
            'special_instructions' => $shipment->getSpecialInstructions(),
            'reference' => $shipment->getReference(),
            'has_label' => $shipment->hasLabel(),
            'estimated_delivery' => $shipment->getEstimatedDelivery()?->format('c'),
            'delivered_at' => $shipment->getDeliveredAt()?->format('c'),
            'created_at' => $shipment->getCreatedAt()->format('c'),
            'updated_at' => $shipment->getUpdatedAt()->format('c'),
            'metadata' => $shipment->getMetadata()
        ];
    }
}