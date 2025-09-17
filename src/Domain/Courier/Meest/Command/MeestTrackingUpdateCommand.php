<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Command;

use App\Domain\Courier\Meest\Service\MeestTrackingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'meest:tracking:update',
    description: 'Update tracking status for MEEST shipments'
)]
class MeestTrackingUpdateCommand extends Command
{
    public function __construct(
        private readonly MeestTrackingService $trackingService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('hours', null, InputOption::VALUE_REQUIRED, 'Update shipments not updated in last X hours', 1)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be updated without making changes')
            ->addOption('labels', null, InputOption::VALUE_NONE, 'Generate missing labels')
            ->addOption('stats', null, InputOption::VALUE_NONE, 'Show shipment statistics');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hours = (int) $input->getOption('hours');
        $dryRun = $input->getOption('dry-run');
        $generateLabels = $input->getOption('labels');
        $showStats = $input->getOption('stats');

        $io->title('MEEST Tracking Update');

        try {
            if ($showStats) {
                $this->showStatistics($io);
            }

            if ($generateLabels) {
                $this->generateMissingLabels($io, $dryRun);
            }

            $this->updateTrackingStatus($io, $hours, $dryRun);

            $io->success('Tracking update completed successfully');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Tracking update failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function updateTrackingStatus(SymfonyStyle $io, int $hours, bool $dryRun): void
    {
        $updatedBefore = new \DateTimeImmutable("-{$hours} hours");

        $io->section('Updating Tracking Status');
        $io->text("Looking for shipments not updated since: {$updatedBefore->format('Y-m-d H:i:s')}");

        if ($dryRun) {
            $io->warning('DRY RUN - No changes will be made');
        }

        if (!$dryRun) {
            $updateCount = $this->trackingService->updatePendingShipments($updatedBefore);

            $io->table(['Metric', 'Value'], [
                ['Shipments Updated', $updateCount],
                ['Update Time', (new \DateTimeImmutable())->format('Y-m-d H:i:s')]
            ]);
        } else {
            $io->text('Use without --dry-run to perform actual updates');
        }
    }

    private function generateMissingLabels(SymfonyStyle $io, bool $dryRun): void
    {
        $io->section('Generating Missing Labels');

        if ($dryRun) {
            $io->warning('DRY RUN - No labels will be generated');
            return;
        }

        $generatedCount = $this->trackingService->generateMissingLabels();

        $io->table(['Metric', 'Value'], [
            ['Labels Generated', $generatedCount],
            ['Generation Time', (new \DateTimeImmutable())->format('Y-m-d H:i:s')]
        ]);
    }

    private function showStatistics(SymfonyStyle $io): void
    {
        $io->section('MEEST Shipment Statistics');

        // Last 30 days statistics
        $stats30 = $this->trackingService->getShipmentStatistics(new \DateTimeImmutable('-30 days'));

        $io->text('<info>Last 30 Days:</info>');
        if (!empty($stats30['status_counts'])) {
            $statusData = [];
            foreach ($stats30['status_counts'] as $status => $count) {
                $statusData[] = [ucfirst(str_replace('_', ' ', $status)), $count];
            }
            $io->table(['Status', 'Count'], $statusData);
        } else {
            $io->text('No shipments found in the last 30 days');
        }

        if (!empty($stats30['total_costs'])) {
            $io->text('<info>Total Costs (Last 30 Days):</info>');
            $costData = [];
            foreach ($stats30['total_costs'] as $currency => $total) {
                $costData[] = [$currency, number_format($total, 2)];
            }
            $io->table(['Currency', 'Total'], $costData);
        }

        // Overdue shipments
        $overdueShipments = $this->trackingService->getOverdueShipments();
        if (!empty($overdueShipments)) {
            $io->section('Overdue Shipments');
            $overdueData = [];
            foreach ($overdueShipments as $shipment) {
                $overdueData[] = [
                    $shipment->getTrackingNumber(),
                    $shipment->getStatus()->value,
                    $shipment->getEstimatedDelivery()?->format('Y-m-d') ?? 'N/A',
                    $shipment->getCreatedAt()->format('Y-m-d')
                ];
            }
            $io->table(['Tracking Number', 'Status', 'Expected Delivery', 'Created'], $overdueData);
        } else {
            $io->text('<info>No overdue shipments found</info>');
        }

        // Last 7 days statistics
        $stats7 = $this->trackingService->getShipmentStatistics(new \DateTimeImmutable('-7 days'));

        $io->text('<info>Last 7 Days Summary:</info>');
        $totalLast7 = array_sum($stats7['status_counts'] ?? []);
        $totalLast30 = array_sum($stats30['status_counts'] ?? []);

        $io->table(['Period', 'Total Shipments'], [
            ['Last 7 days', $totalLast7],
            ['Last 30 days', $totalLast30],
            ['Daily average (7d)', $totalLast7 > 0 ? round($totalLast7 / 7, 1) : 0],
            ['Daily average (30d)', $totalLast30 > 0 ? round($totalLast30 / 30, 1) : 0]
        ]);
    }
}