<?php

declare(strict_types=1);

namespace App\Service\Shipment;

use App\Entity\Shipment;
use App\Entity\Order;
use App\Entity\Customer;
use App\Repository\ShipmentRepository;
use App\Repository\OrderRepository;
use App\Repository\CustomerRepository;
use App\Service\InPostApiClient;
use App\Service\Notification\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Comprehensive Shipment Management Service
 * Handles the complete shipment lifecycle from creation to delivery
 */
class ShipmentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly OrderRepository $orderRepository,
        private readonly CustomerRepository $customerRepository,
        private readonly InPostApiClient $inPostClient,
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get customer shipments with filtering and pagination
     */
    public function getCustomerShipments(int $customerId, array $filters, array $pagination): array
    {
        $qb = $this->shipmentRepository->createQueryBuilder('s')
            ->join('s.order', 'o')
            ->join('o.customer', 'c')
            ->where('c.id = :customerId')
            ->setParameter('customerId', $customerId);

        // Apply filters
        if (!empty($filters['status'])) {
            $qb->andWhere('s.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['courier_service'])) {
            $qb->andWhere('s.courierService = :courierService')
               ->setParameter('courierService', $filters['courier_service']);
        }

        if (!empty($filters['date_from'])) {
            $qb->andWhere('s.createdAt >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($filters['date_from']));
        }

        if (!empty($filters['date_to'])) {
            $qb->andWhere('s.createdAt <= :dateTo')
               ->setParameter('dateTo', new \DateTime($filters['date_to'] . ' 23:59:59'));
        }

        if (!empty($filters['tracking_number'])) {
            $qb->andWhere('s.trackingNumber LIKE :trackingNumber')
               ->setParameter('trackingNumber', '%' . $filters['tracking_number'] . '%');
        }

        // Apply sorting
        $sortField = $pagination['sort'] ?? 'createdAt';
        $sortOrder = $pagination['order'] ?? 'desc';
        $qb->orderBy("s.{$sortField}", $sortOrder);

        // Count total records
        $totalQuery = clone $qb;
        $total = $totalQuery->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();

        // Apply pagination
        $limit = $pagination['limit'] ?? 20;
        $page = $pagination['page'] ?? 1;
        $offset = ($page - 1) * $limit;

        $qb->setMaxResults($limit)->setFirstResult($offset);

        $shipments = $qb->getQuery()->getResult();

        return [
            'data' => array_map([$this, 'formatShipmentSummary'], $shipments),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => (int) $total,
                'total_pages' => ceil($total / $limit),
                'has_next' => $page < ceil($total / $limit),
                'has_prev' => $page > 1
            ]
        ];
    }

    /**
     * Get single customer shipment
     */
    public function getCustomerShipment(int $customerId, int $shipmentId): ?Shipment
    {
        return $this->shipmentRepository->createQueryBuilder('s')
            ->join('s.order', 'o')
            ->join('o.customer', 'c')
            ->where('c.id = :customerId')
            ->andWhere('s.id = :shipmentId')
            ->setParameter('customerId', $customerId)
            ->setParameter('shipmentId', $shipmentId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Create new shipment following the 4-step workflow
     */
    public function createShipment(int $customerId, array $data): Shipment
    {
        $this->entityManager->beginTransaction();

        try {
            // 1. Create or find order
            $order = $this->createOrFindOrder($customerId, $data);

            // 2. Create shipment entity
            $shipment = $this->createShipmentEntity($order, $data);

            // 3. Integrate with courier service
            $courierResponse = $this->integrateWithCourier($shipment, $data);

            // 4. Finalize shipment with courier data
            $this->finalizeShipment($shipment, $courierResponse);

            $this->entityManager->flush();
            $this->entityManager->commit();

            // Send notifications
            $this->notificationService->sendShipmentCreatedNotification($shipment);

            $this->logger->info('Shipment created successfully', [
                'shipment_id' => $shipment->getId(),
                'tracking_number' => $shipment->getTrackingNumber(),
                'customer_id' => $customerId
            ]);

            return $shipment;

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Failed to create shipment', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Update existing shipment
     */
    public function updateShipment(Shipment $shipment, array $data): Shipment
    {
        if (!$shipment->canBeCanceled()) {
            throw new \InvalidArgumentException('Shipment cannot be modified in current status');
        }

        $this->entityManager->beginTransaction();

        try {
            // Update shipment details
            $this->updateShipmentEntity($shipment, $data);

            // Update with courier service if needed
            if ($this->needsCourierUpdate($shipment, $data)) {
                $this->updateWithCourier($shipment, $data);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Shipment updated successfully', [
                'shipment_id' => $shipment->getId(),
                'tracking_number' => $shipment->getTrackingNumber()
            ]);

            return $shipment;

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Failed to update shipment', [
                'shipment_id' => $shipment->getId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Cancel shipment
     */
    public function cancelShipment(Shipment $shipment, string $reason): void
    {
        if (!$shipment->canBeCanceled()) {
            throw new \InvalidArgumentException('Shipment cannot be canceled in current status');
        }

        $this->entityManager->beginTransaction();

        try {
            // Cancel with courier service first
            $this->cancelWithCourier($shipment, $reason);

            // Update shipment status
            $shipment->setStatus('canceled');

            $this->entityManager->flush();
            $this->entityManager->commit();

            // Send notifications
            $this->notificationService->sendShipmentCanceledNotification($shipment, $reason);

            $this->logger->info('Shipment canceled successfully', [
                'shipment_id' => $shipment->getId(),
                'tracking_number' => $shipment->getTrackingNumber(),
                'reason' => $reason
            ]);

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Failed to cancel shipment', [
                'shipment_id' => $shipment->getId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get shipment tracking information
     */
    public function getTrackingInformation(Shipment $shipment): array
    {
        try {
            // Get latest tracking from courier
            $courierTracking = $this->getTrackingFromCourier($shipment);

            // Get internal tracking events
            $internalEvents = $shipment->getTrackingEvents()->toArray();

            // Merge and format tracking data
            return $this->formatTrackingData($shipment, $courierTracking, $internalEvents);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get tracking information', [
                'shipment_id' => $shipment->getId(),
                'error' => $e->getMessage()
            ]);

            // Return basic tracking info if courier fails
            return $this->getBasicTrackingInfo($shipment);
        }
    }

    /**
     * Get shipment label
     */
    public function getShipmentLabel(Shipment $shipment): array
    {
        try {
            if ($shipment->getLabelUrl()) {
                return [
                    'url' => $shipment->getLabelUrl(),
                    'expires_at' => (new \DateTime())->add(new \DateInterval('PT24H'))->format('c')
                ];
            }

            // Generate new label from courier
            $labelData = $this->generateLabelFromCourier($shipment);

            // Update shipment with label URL
            $shipment->setLabelUrl($labelData['url']);
            $this->entityManager->flush();

            return $labelData;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get shipment label', [
                'shipment_id' => $shipment->getId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Perform bulk operations on shipments
     */
    public function performBulkAction(int $customerId, string $action, array $shipmentIds, array $data): array
    {
        $results = [
            'successful' => [],
            'failed' => [],
            'total' => count($shipmentIds)
        ];

        foreach ($shipmentIds as $shipmentId) {
            try {
                $shipment = $this->getCustomerShipment($customerId, $shipmentId);

                if (!$shipment) {
                    $results['failed'][] = [
                        'shipment_id' => $shipmentId,
                        'error' => 'Shipment not found'
                    ];
                    continue;
                }

                switch ($action) {
                    case 'cancel':
                        $reason = $data['reason'] ?? 'Bulk cancellation';
                        $this->cancelShipment($shipment, $reason);
                        break;

                    case 'download_labels':
                        $labelData = $this->getShipmentLabel($shipment);
                        $results['successful'][] = [
                            'shipment_id' => $shipmentId,
                            'label_url' => $labelData['url']
                        ];
                        continue 2; // Skip the default success entry

                    default:
                        throw new \InvalidArgumentException("Unknown action: {$action}");
                }

                $results['successful'][] = [
                    'shipment_id' => $shipmentId,
                    'tracking_number' => $shipment->getTrackingNumber()
                ];

            } catch (\Exception $e) {
                $results['failed'][] = [
                    'shipment_id' => $shipmentId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Format shipment details for API response
     */
    public function formatShipmentDetails(Shipment $shipment): array
    {
        return [
            'id' => $shipment->getId(),
            'tracking_number' => $shipment->getTrackingNumber(),
            'courier_shipment_id' => $shipment->getCourierShipmentId(),
            'courier_service' => $shipment->getCourierService(),
            'status' => $shipment->getStatus(),
            'service_type' => $shipment->getServiceType(),

            // Sender information
            'sender' => [
                'name' => $shipment->getSenderName(),
                'email' => $shipment->getSenderEmail(),
                'phone' => $shipment->getSenderPhone(),
                'address' => $shipment->getSenderAddress(),
                'postal_code' => $shipment->getSenderPostalCode(),
                'city' => $shipment->getSenderCity(),
                'country' => $shipment->getSenderCountry(),
                'full_address' => $shipment->getFullSenderAddress()
            ],

            // Recipient information
            'recipient' => [
                'name' => $shipment->getRecipientName(),
                'email' => $shipment->getRecipientEmail(),
                'phone' => $shipment->getRecipientPhone(),
                'address' => $shipment->getRecipientAddress(),
                'postal_code' => $shipment->getRecipientPostalCode(),
                'city' => $shipment->getRecipientCity(),
                'country' => $shipment->getRecipientCountry(),
                'full_address' => $shipment->getFullRecipientAddress()
            ],

            // Package information
            'package' => [
                'total_weight' => (float) $shipment->getTotalWeight(),
                'total_value' => (float) $shipment->getTotalValue(),
                'currency' => $shipment->getCurrency(),
                'items_count' => $shipment->getItems()->count(),
                'items' => array_map(function ($item) {
                    return [
                        'name' => $item->getName(),
                        'quantity' => $item->getQuantity(),
                        'weight' => (float) $item->getWeight(),
                        'value' => (float) $item->getValue()
                    ];
                }, $shipment->getItems()->toArray())
            ],

            // Cost information
            'costs' => [
                'shipping_cost' => $shipment->getShippingCost() ? (float) $shipment->getShippingCost() : null,
                'cod_amount' => $shipment->getCodAmount() ? (float) $shipment->getCodAmount() : null,
                'insurance_amount' => $shipment->getInsuranceAmount() ? (float) $shipment->getInsuranceAmount() : null,
                'has_cod' => $shipment->hasCashOnDelivery(),
                'has_insurance' => $shipment->hasInsurance()
            ],

            // Delivery information
            'delivery' => [
                'estimated_delivery_at' => $shipment->getEstimatedDeliveryAt()?->format('Y-m-d H:i:s'),
                'dispatched_at' => $shipment->getDispatchedAt()?->format('Y-m-d H:i:s'),
                'delivered_at' => $shipment->getDeliveredAt()?->format('Y-m-d H:i:s'),
                'can_be_canceled' => $shipment->canBeCanceled()
            ],

            // Additional information
            'special_instructions' => $shipment->getSpecialInstructions(),
            'label_url' => $shipment->getLabelUrl(),
            'courier_metadata' => $shipment->getCourierMetadata(),

            // Timestamps
            'created_at' => $shipment->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $shipment->getUpdatedAt()?->format('Y-m-d H:i:s'),

            // Latest tracking event
            'latest_tracking_event' => $this->formatLatestTrackingEvent($shipment)
        ];
    }

    /**
     * Format shipment summary for list views
     */
    public function formatShipmentSummary(Shipment $shipment): array
    {
        return [
            'id' => $shipment->getId(),
            'tracking_number' => $shipment->getTrackingNumber(),
            'courier_service' => $shipment->getCourierService(),
            'status' => $shipment->getStatus(),
            'recipient_name' => $shipment->getRecipientName(),
            'recipient_city' => $shipment->getRecipientCity(),
            'shipping_cost' => $shipment->getShippingCost() ? (float) $shipment->getShippingCost() : null,
            'created_at' => $shipment->getCreatedAt()->format('Y-m-d H:i:s'),
            'estimated_delivery_at' => $shipment->getEstimatedDeliveryAt()?->format('Y-m-d H:i:s'),
            'can_be_canceled' => $shipment->canBeCanceled()
        ];
    }

    // Private helper methods

    private function createOrFindOrder(int $customerId, array $data): Order
    {
        // Implementation to create or find existing order
        $customer = $this->customerRepository->find($customerId);
        if (!$customer) {
            throw new \InvalidArgumentException('Customer not found');
        }

        $order = new Order();
        $order->setCustomer($customer);
        $order->setStatus('processing');
        $order->setTotalAmount($data['total_amount'] ?? '0.00');
        $order->setCurrency($data['currency'] ?? 'PLN');

        $this->entityManager->persist($order);

        return $order;
    }

    private function createShipmentEntity(Order $order, array $data): Shipment
    {
        $shipment = new Shipment();
        $shipment->setOrder($order);
        $shipment->setTrackingNumber($this->generateTrackingNumber());
        $shipment->setCourierService($data['courier_service'] ?? 'inpost');

        // Set sender information
        $shipment->setSenderName($data['sender']['name']);
        $shipment->setSenderEmail($data['sender']['email']);
        $shipment->setSenderAddress($data['sender']['address']);
        $shipment->setSenderPostalCode($data['sender']['postal_code']);
        $shipment->setSenderCity($data['sender']['city']);
        $shipment->setSenderCountry($data['sender']['country'] ?? 'Poland');
        $shipment->setSenderPhone($data['sender']['phone'] ?? null);

        // Set recipient information
        $shipment->setRecipientName($data['recipient']['name']);
        $shipment->setRecipientEmail($data['recipient']['email']);
        $shipment->setRecipientAddress($data['recipient']['address']);
        $shipment->setRecipientPostalCode($data['recipient']['postal_code']);
        $shipment->setRecipientCity($data['recipient']['city']);
        $shipment->setRecipientCountry($data['recipient']['country'] ?? 'Poland');
        $shipment->setRecipientPhone($data['recipient']['phone']);

        // Set package information
        $shipment->setTotalWeight($data['weight'] ?? '0.000');
        $shipment->setTotalValue($data['value'] ?? '0.00');
        $shipment->setServiceType($data['service_type'] ?? 'standard');
        $shipment->setSpecialInstructions($data['special_instructions'] ?? null);

        // Set optional services
        if (isset($data['cod_amount'])) {
            $shipment->setCodAmount($data['cod_amount']);
        }

        if (isset($data['insurance_amount'])) {
            $shipment->setInsuranceAmount($data['insurance_amount']);
        }

        $this->entityManager->persist($shipment);

        return $shipment;
    }

    private function integrateWithCourier(Shipment $shipment, array $data): array
    {
        // Implementation for courier integration
        switch ($shipment->getCourierService()) {
            case 'inpost':
                return $this->integrateWithInPost($shipment, $data);
            default:
                throw new \InvalidArgumentException('Unsupported courier service');
        }
    }

    private function integrateWithInPost(Shipment $shipment, array $data): array
    {
        $shipmentData = [
            'receiver' => [
                'name' => $shipment->getRecipientName(),
                'email' => $shipment->getRecipientEmail(),
                'phone' => $shipment->getRecipientPhone(),
                'address' => [
                    'street' => $shipment->getRecipientAddress(),
                    'city' => $shipment->getRecipientCity(),
                    'post_code' => $shipment->getRecipientPostalCode(),
                    'country_code' => 'PL'
                ]
            ],
            'sender' => [
                'name' => $shipment->getSenderName(),
                'email' => $shipment->getSenderEmail(),
                'phone' => $shipment->getSenderPhone() ?? '',
                'address' => [
                    'street' => $shipment->getSenderAddress(),
                    'city' => $shipment->getSenderCity(),
                    'post_code' => $shipment->getSenderPostalCode(),
                    'country_code' => 'PL'
                ]
            ],
            'parcels' => [
                [
                    'template' => 'small',
                    'weight' => [
                        'amount' => (float) $shipment->getTotalWeight(),
                        'unit' => 'kg'
                    ]
                ]
            ],
            'service' => 'inpost_locker_standard'
        ];

        return $this->inPostClient->createShipment($shipmentData);
    }

    private function finalizeShipment(Shipment $shipment, array $courierResponse): void
    {
        $shipment->setCourierShipmentId($courierResponse['id'] ?? null);
        $shipment->setCourierMetadata($courierResponse);
        $shipment->setStatus('confirmed');

        if (isset($courierResponse['tracking_number'])) {
            $shipment->setTrackingNumber($courierResponse['tracking_number']);
        }
    }

    private function generateTrackingNumber(): string
    {
        return 'SKY' . date('Ymd') . strtoupper(substr(uniqid(), -6));
    }

    private function updateShipmentEntity(Shipment $shipment, array $data): void
    {
        // Update only allowed fields based on current status
        if (isset($data['special_instructions'])) {
            $shipment->setSpecialInstructions($data['special_instructions']);
        }

        // Add more updateable fields as needed
    }

    private function needsCourierUpdate(Shipment $shipment, array $data): bool
    {
        // Determine if courier service needs to be updated
        return false; // Simplified for now
    }

    private function updateWithCourier(Shipment $shipment, array $data): void
    {
        // Update shipment with courier service
        // Implementation depends on courier capabilities
    }

    private function cancelWithCourier(Shipment $shipment, string $reason): void
    {
        // Cancel shipment with courier service
        if ($shipment->getCourierShipmentId()) {
            // Implementation for courier cancellation
        }
    }

    private function getTrackingFromCourier(Shipment $shipment): array
    {
        // Get tracking information from courier service
        switch ($shipment->getCourierService()) {
            case 'inpost':
                return $this->inPostClient->trackShipment($shipment->getTrackingNumber());
            default:
                return [];
        }
    }

    private function formatTrackingData(Shipment $shipment, array $courierTracking, array $internalEvents): array
    {
        // Format tracking data for API response
        return [
            'tracking_number' => $shipment->getTrackingNumber(),
            'status' => $shipment->getStatus(),
            'courier_tracking' => $courierTracking,
            'internal_events' => $internalEvents,
            'last_updated' => new \DateTime()
        ];
    }

    private function getBasicTrackingInfo(Shipment $shipment): array
    {
        return [
            'tracking_number' => $shipment->getTrackingNumber(),
            'status' => $shipment->getStatus(),
            'created_at' => $shipment->getCreatedAt()->format('Y-m-d H:i:s'),
            'error' => 'Unable to fetch detailed tracking information'
        ];
    }

    private function generateLabelFromCourier(Shipment $shipment): array
    {
        // Generate label from courier service
        switch ($shipment->getCourierService()) {
            case 'inpost':
                $labelContent = $this->inPostClient->getShipmentLabel($shipment->getCourierShipmentId());
                // Save label and return URL
                return [
                    'url' => '/labels/' . $shipment->getId() . '.pdf',
                    'expires_at' => (new \DateTime())->add(new \DateInterval('PT24H'))->format('c')
                ];
            default:
                throw new \RuntimeException('Label generation not supported for this courier');
        }
    }

    private function formatLatestTrackingEvent(Shipment $shipment): ?array
    {
        $latestEvent = $shipment->getLatestTrackingEvent();
        if (!$latestEvent) {
            return null;
        }

        return [
            'status' => $latestEvent->getStatus(),
            'description' => $latestEvent->getDescription(),
            'location' => $latestEvent->getLocation(),
            'event_date' => $latestEvent->getEventDate()->format('Y-m-d H:i:s')
        ];
    }
}