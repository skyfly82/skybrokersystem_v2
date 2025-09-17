<?php

declare(strict_types=1);

namespace App\Controller\Api\Customer;

use App\Entity\Shipment;
use App\Service\Shipment\ShipmentService;
use App\Service\Shipment\PricingCalculatorService;
use App\Service\Shipment\ShipmentValidatorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Customer Shipment Management API Controller
 * Handles the complete shipment lifecycle for authenticated customers
 */
#[Route('/api/v1/customer/shipments', name: 'api_customer_shipments_')]
#[IsGranted('ROLE_CUSTOMER_USER')]
class ShipmentController extends AbstractController
{
    public function __construct(
        private readonly ShipmentService $shipmentService,
        private readonly PricingCalculatorService $pricingCalculator,
        private readonly ShipmentValidatorService $validator,
        private readonly ValidatorInterface $symfonyValidator
    ) {
    }

    /**
     * List customer shipments with filtering and pagination
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function listShipments(Request $request): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();

        $filters = [
            'status' => $request->query->get('status'),
            'courier_service' => $request->query->get('courier_service'),
            'date_from' => $request->query->get('date_from'),
            'date_to' => $request->query->get('date_to'),
            'tracking_number' => $request->query->get('tracking_number'),
        ];

        $pagination = [
            'page' => $request->query->getInt('page', 1),
            'limit' => $request->query->getInt('limit', 20),
            'sort' => $request->query->get('sort', 'created_at'),
            'order' => $request->query->get('order', 'desc'),
        ];

        $result = $this->shipmentService->getCustomerShipments($customerId, $filters, $pagination);

        return $this->json([
            'success' => true,
            'data' => $result['data'],
            'pagination' => $result['pagination'],
            'filters_applied' => array_filter($filters)
        ]);
    }

    /**
     * Get detailed shipment information
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getShipment(int $id): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();
        $shipment = $this->shipmentService->getCustomerShipment($customerId, $id);

        if (!$shipment) {
            return $this->json(['success' => false, 'message' => 'Shipment not found'], 404);
        }

        return $this->json([
            'success' => true,
            'data' => $this->shipmentService->formatShipmentDetails($shipment)
        ]);
    }

    /**
     * Create new shipment (4-step workflow)
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function createShipment(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $customerId = $this->getUser()->getCustomer()->getId();

        // Validate shipment data
        $validationResult = $this->validator->validateShipmentData($data);
        if (!$validationResult['valid']) {
            return $this->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validationResult['errors']
            ], 400);
        }

        try {
            $shipment = $this->shipmentService->createShipment($customerId, $data);

            return $this->json([
                'success' => true,
                'message' => 'Shipment created successfully',
                'data' => [
                    'id' => $shipment->getId(),
                    'tracking_number' => $shipment->getTrackingNumber(),
                    'status' => $shipment->getStatus(),
                    'courier_service' => $shipment->getCourierService()
                ]
            ], 201);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to create shipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate pricing for shipment
     */
    #[Route('/calculate-price', name: 'calculate_price', methods: ['POST'])]
    public function calculatePrice(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $pricing = $this->pricingCalculator->calculateShipmentPricing($data);

            return $this->json([
                'success' => true,
                'data' => $pricing
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to calculate pricing: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update shipment details (before dispatch)
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateShipment(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $customerId = $this->getUser()->getCustomer()->getId();

        $shipment = $this->shipmentService->getCustomerShipment($customerId, $id);
        if (!$shipment) {
            return $this->json(['success' => false, 'message' => 'Shipment not found'], 404);
        }

        if (!$shipment->canBeCanceled()) {
            return $this->json([
                'success' => false,
                'message' => 'Shipment cannot be modified in current status'
            ], 400);
        }

        try {
            $updatedShipment = $this->shipmentService->updateShipment($shipment, $data);

            return $this->json([
                'success' => true,
                'message' => 'Shipment updated successfully',
                'data' => $this->shipmentService->formatShipmentDetails($updatedShipment)
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to update shipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel shipment
     */
    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancelShipment(int $id, Request $request): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();
        $shipment = $this->shipmentService->getCustomerShipment($customerId, $id);

        if (!$shipment) {
            return $this->json(['success' => false, 'message' => 'Shipment not found'], 404);
        }

        if (!$shipment->canBeCanceled()) {
            return $this->json([
                'success' => false,
                'message' => 'Shipment cannot be canceled in current status'
            ], 400);
        }

        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? 'Customer request';

        try {
            $this->shipmentService->cancelShipment($shipment, $reason);

            return $this->json([
                'success' => true,
                'message' => 'Shipment canceled successfully'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to cancel shipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get shipment tracking information
     */
    #[Route('/{id}/tracking', name: 'tracking', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getShipmentTracking(int $id): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();
        $shipment = $this->shipmentService->getCustomerShipment($customerId, $id);

        if (!$shipment) {
            return $this->json(['success' => false, 'message' => 'Shipment not found'], 404);
        }

        try {
            $tracking = $this->shipmentService->getTrackingInformation($shipment);

            return $this->json([
                'success' => true,
                'data' => $tracking
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get tracking information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download shipment label
     */
    #[Route('/{id}/label', name: 'label', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function downloadLabel(int $id): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();
        $shipment = $this->shipmentService->getCustomerShipment($customerId, $id);

        if (!$shipment) {
            return $this->json(['success' => false, 'message' => 'Shipment not found'], 404);
        }

        try {
            $labelData = $this->shipmentService->getShipmentLabel($shipment);

            return $this->json([
                'success' => true,
                'data' => [
                    'label_url' => $labelData['url'],
                    'expires_at' => $labelData['expires_at']
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get label: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk operations on shipments
     */
    #[Route('/bulk', name: 'bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $customerId = $this->getUser()->getCustomer()->getId();

        $action = $data['action'] ?? null;
        $shipmentIds = $data['shipment_ids'] ?? [];

        if (!$action || empty($shipmentIds)) {
            return $this->json([
                'success' => false,
                'message' => 'Action and shipment IDs are required'
            ], 400);
        }

        try {
            $result = $this->shipmentService->performBulkAction($customerId, $action, $shipmentIds, $data);

            return $this->json([
                'success' => true,
                'message' => "Bulk {$action} completed",
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Bulk operation failed: ' . $e->getMessage()
            ], 500);
        }
    }
}