<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\AnalyticsEventRepository;
use App\Service\ReportingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/analytics', name: 'api_analytics_')]
class AnalyticsController extends AbstractController
{
    public function __construct(
        private readonly ReportingService $reporting,
        private readonly AnalyticsEventRepository $events
    ) {}

    #[Route('/summary', name: 'summary', methods: ['GET'])]
    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->parseRange($request);
        $eventSummary = $this->reporting->getEventSummary($from, $to);
        $endpointPerf = $this->reporting->getEndpointPerformance($from, $to, (int)($request->query->get('limit', 20)));
        $shipments = $this->reporting->getShipmentKpis($from, $to);
        return $this->json([
            'events' => $eventSummary,
            'endpoints' => $endpointPerf,
            'shipments' => $shipments,
        ]);
    }

    #[Route('/events', name: 'events', methods: ['GET'])]
    public function events(Request $request): JsonResponse
    {
        $limit = (int) ($request->query->get('limit', 100));
        $items = $this->events->findRecent($limit);
        $out = array_map(static function ($e) {
            return [
                'id' => $e->getId(),
                'type' => $e->getType(),
                'name' => $e->getName(),
                'endpoint' => $e->getEndpoint(),
                'method' => $e->getMethod(),
                'status' => $e->getStatusCode(),
                'duration_ms' => $e->getDurationMs(),
                'ip' => $e->getIp(),
                'created_at' => $e->getCreatedAt()->format(DATE_ATOM),
            ];
        }, $items);
        return $this->json($out);
    }

    private function parseRange(Request $request): array
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $fromDt = $from ? new \DateTimeImmutable($from) : null;
        $toDt = $to ? new \DateTimeImmutable($to) : null;
        return [$fromDt, $toDt];
    }
}

