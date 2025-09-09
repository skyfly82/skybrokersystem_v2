<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AnalyticsEventRepository;
use App\Repository\ShipmentRepository;
use Psr\Cache\CacheItemPoolInterface;

class ReportingService
{
    public function __construct(
        private readonly ShipmentRepository $shipments,
        private readonly AnalyticsEventRepository $analytics,
        private readonly CacheItemPoolInterface $cache
    ) {
    }

    /**
     * Returns shipment KPIs with short cache.
     */
    public function getShipmentKpis(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $key = 'kpi.shipments.' . ($from?->format('Ymd') ?? 'all') . '.' . ($to?->format('Ymd') ?? 'all');
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }

        // Using custom ShipmentRepository method if exists, otherwise compute basic counts
        if (method_exists($this->shipments, 'getShipmentStats')) {
            $result = $this->shipments->getShipmentStats($from, $to);
        } else {
            $result = [
                'total_shipments' => count($this->shipments->findByDateRange($from ?? new \DateTimeImmutable('-365 days'), $to ?? new \DateTimeImmutable())),
            ];
        }

        $item->set($result);
        $item->expiresAfter(300); // 5 minutes
        $this->cache->save($item);
        return $result;
    }

    /**
     * Returns API endpoint performance stats with short cache.
     */
    public function getEndpointPerformance(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null, int $limit = 20): array
    {
        $key = 'kpi.endpoint.' . ($from?->format('Ymd') ?? 'all') . '.' . ($to?->format('Ymd') ?? 'all') . '.' . $limit;
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }
        $stats = $this->analytics->getEndpointStats($from, $to, $limit);
        $item->set($stats);
        $item->expiresAfter(300);
        $this->cache->save($item);
        return $stats;
    }

    public function getEventSummary(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        return $this->analytics->getSummary($from, $to);
    }
}
