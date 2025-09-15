<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Shipment;
use App\Repository\ShipmentRepository;
use App\Repository\ShipmentTrackingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/shipments')]
#[IsGranted('ROLE_SYSTEM_USER')]
class ShipmentsController extends AbstractController
{
    public function __construct(
        private readonly ShipmentRepository $shipmentRepository,
        private readonly ShipmentTrackingRepository $trackingRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'admin_shipments', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $courier = $request->query->get('courier', '');
        $dateFrom = $request->query->get('date_from', '');
        $dateTo = $request->query->get('date_to', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 25;

        $filters = [
            'search' => $search,
            'status' => $status,
            'courier' => $courier,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'page' => $page,
            'limit' => $limit,
        ];

        $shipments = $this->shipmentRepository->findWithFilters($filters);
        $totalShipments = $this->shipmentRepository->countWithFilters($filters);

        return $this->render('admin/shipments/index.html.twig', [
            'shipments' => $shipments,
            'total_shipments' => $totalShipments,
            'current_page' => $page,
            'total_pages' => ceil($totalShipments / $limit),
            'filters' => $filters,
            'statistics' => $this->getShipmentStatistics(),
        ]);
    }

    #[Route('/api', name: 'admin_shipments_api', methods: ['GET'])]
    public function getShipmentsApi(Request $request): JsonResponse
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $courier = $request->query->get('courier', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 25);

        $filters = [
            'search' => $search,
            'status' => $status,
            'courier' => $courier,
            'page' => $page,
            'limit' => $limit,
        ];

        $shipments = $this->shipmentRepository->findWithFilters($filters);
        $totalShipments = $this->shipmentRepository->countWithFilters($filters);

        $shipmentsData = [];
        foreach ($shipments as $shipment) {
            $shipmentsData[] = [
                'id' => $shipment->getId(),
                'tracking_number' => $shipment->getTrackingNumber(),
                'status' => $shipment->getStatus(),
                'courier_service' => $shipment->getCourierService(),
                'recipient_name' => $shipment->getRecipientName(),
                'recipient_city' => $shipment->getRecipientCity(),
                'sender_name' => $shipment->getSenderName(),
                'created_at' => $shipment->getCreatedAt()?->format('Y-m-d H:i:s'),
                'delivered_at' => $shipment->getDeliveredAt()?->format('Y-m-d H:i:s'),
                'total_cost' => $shipment->getTotalCost(),
            ];
        }

        return $this->json([
            'shipments' => $shipmentsData,
            'total' => $totalShipments,
            'page' => $page,
            'total_pages' => ceil($totalShipments / $limit),
        ]);
    }

    #[Route('/{id}', name: 'admin_shipment_show', methods: ['GET'])]
    public function show(Shipment $shipment): Response
    {
        $trackingHistory = $this->trackingRepository->findBy(
            ['shipment' => $shipment],
            ['createdAt' => 'DESC']
        );

        return $this->render('admin/shipments/show.html.twig', [
            'shipment' => $shipment,
            'tracking_history' => $trackingHistory,
            'items' => $shipment->getShipmentItems(),
        ]);
    }

    #[Route('/{id}/tracking', name: 'admin_shipment_tracking', methods: ['GET'])]
    public function getTracking(Shipment $shipment): JsonResponse
    {
        $trackingHistory = $this->trackingRepository->findBy(
            ['shipment' => $shipment],
            ['createdAt' => 'DESC']
        );

        $trackingData = [];
        foreach ($trackingHistory as $tracking) {
            $trackingData[] = [
                'id' => $tracking->getId(),
                'status' => $tracking->getStatus(),
                'location' => $tracking->getLocation(),
                'description' => $tracking->getDescription(),
                'timestamp' => $tracking->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json([
            'tracking_number' => $shipment->getTrackingNumber(),
            'current_status' => $shipment->getStatus(),
            'tracking_history' => $trackingData,
        ]);
    }

    #[Route('/{id}/status', name: 'admin_shipment_update_status', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateStatus(Shipment $shipment, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;
        $note = $data['note'] ?? '';

        $validStatuses = ['pending', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned'];

        if (!in_array($newStatus, $validStatuses)) {
            return $this->json(['error' => 'Invalid status'], 400);
        }

        $oldStatus = $shipment->getStatus();
        $shipment->setStatus($newStatus);
        $shipment->setUpdatedAt(new \DateTime());

        if ($newStatus === 'delivered' && $oldStatus !== 'delivered') {
            $shipment->setDeliveredAt(new \DateTime());
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Shipment status updated successfully',
            'status' => $shipment->getStatus(),
        ]);
    }

    #[Route('/bulk/action', name: 'admin_shipments_bulk_action', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function bulkAction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? null;
        $shipmentIds = $data['shipment_ids'] ?? [];

        if (!$action || empty($shipmentIds)) {
            return $this->json(['error' => 'Invalid action or no shipments selected'], 400);
        }

        $shipments = $this->shipmentRepository->findBy(['id' => $shipmentIds]);

        $count = 0;
        foreach ($shipments as $shipment) {
            switch ($action) {
                case 'mark_picked_up':
                    if ($shipment->getStatus() === 'pending') {
                        $shipment->setStatus('picked_up');
                        $count++;
                    }
                    break;
                case 'mark_in_transit':
                    if (in_array($shipment->getStatus(), ['pending', 'picked_up'])) {
                        $shipment->setStatus('in_transit');
                        $count++;
                    }
                    break;
                case 'mark_delivered':
                    if (in_array($shipment->getStatus(), ['in_transit', 'out_for_delivery'])) {
                        $shipment->setStatus('delivered');
                        $shipment->setDeliveredAt(new \DateTime());
                        $count++;
                    }
                    break;
            }
            $shipment->setUpdatedAt(new \DateTime());
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => sprintf('%d shipments updated successfully', $count),
            'updated_count' => $count,
        ]);
    }

    #[Route('/analytics/chart', name: 'admin_shipments_analytics', methods: ['GET'])]
    public function getAnalyticsChart(Request $request): JsonResponse
    {
        $period = $request->query->get('period', '30days');
        $chartData = $this->getShipmentAnalytics($period);

        return $this->json($chartData);
    }

    #[Route('/export', name: 'admin_shipments_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'csv');
        $filters = [
            'search' => $request->query->get('search', ''),
            'status' => $request->query->get('status', ''),
            'courier' => $request->query->get('courier', ''),
            'limit' => 10000,
        ];

        $shipments = $this->shipmentRepository->findWithFilters($filters);

        if ($format === 'csv') {
            return $this->exportToCsv($shipments);
        }

        return $this->json(['error' => 'Unsupported export format'], 400);
    }

    private function getShipmentStatistics(): array
    {
        $total = $this->shipmentRepository->count([]);
        $pending = $this->shipmentRepository->count(['status' => 'pending']);
        $inTransit = $this->shipmentRepository->count(['status' => 'in_transit']);
        $delivered = $this->shipmentRepository->count(['status' => 'delivered']);
        $failed = $this->shipmentRepository->count(['status' => 'failed']);

        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');
        $deliveredToday = $this->shipmentRepository->countDeliveredInRange($today, $tomorrow);

        return [
            'total' => $total,
            'pending' => $pending,
            'in_transit' => $inTransit,
            'delivered' => $delivered,
            'failed' => $failed,
            'delivered_today' => $deliveredToday,
        ];
    }

    private function getShipmentAnalytics(string $period): array
    {
        $days = match ($period) {
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            default => 30,
        };

        $data = [];
        $labels = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = new \DateTime("-{$i} days");
            $nextDate = clone $date;
            $nextDate->add(new \DateInterval('P1D'));

            $deliveredCount = $this->shipmentRepository->countDeliveredInRange($date, $nextDate);
            $createdCount = $this->shipmentRepository->countCreatedInRange($date, $nextDate);

            $labels[] = $date->format('M j');
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'delivered' => $deliveredCount,
                'created' => $createdCount,
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Delivered',
                    'data' => array_column($data, 'delivered'),
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                ],
                [
                    'label' => 'Created',
                    'data' => array_column($data, 'created'),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
            ],
        ];
    }

    private function exportToCsv(array $shipments): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="shipments_export.csv"');

        $output = fopen('php://output', 'w');

        // CSV Headers
        fputcsv($output, [
            'ID', 'Tracking Number', 'Status', 'Courier', 'Sender', 'Recipient',
            'From City', 'To City', 'Cost', 'Created At', 'Delivered At'
        ]);

        foreach ($shipments as $shipment) {
            fputcsv($output, [
                $shipment->getId(),
                $shipment->getTrackingNumber(),
                $shipment->getStatus(),
                $shipment->getCourierService(),
                $shipment->getSenderName(),
                $shipment->getRecipientName(),
                $shipment->getSenderCity(),
                $shipment->getRecipientCity(),
                $shipment->getTotalCost(),
                $shipment->getCreatedAt()?->format('Y-m-d H:i:s'),
                $shipment->getDeliveredAt()?->format('Y-m-d H:i:s'),
            ]);
        }

        fclose($output);

        return $response;
    }
}