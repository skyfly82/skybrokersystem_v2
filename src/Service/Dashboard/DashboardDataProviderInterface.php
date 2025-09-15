<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

/**
 * Interface for dashboard data providers
 */
interface DashboardDataProviderInterface
{
    /**
     * Get dashboard data for the current user
     */
    public function getDashboardData(): array;

    /**
     * Get realtime updates since last timestamp
     */
    public function getRealtimeUpdates(int $lastUpdate = 0): array;
}