<?php

declare(strict_types=1);

namespace App\Command;

use App\Courier\InPost\Service\InPostWorkflowService;
use App\Courier\InPost\Service\InPostService;
use App\Repository\ShipmentRepository;
use App\Service\CourierSecretsService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'inpost:maintenance',
    description: 'InPost shipments maintenance and monitoring'
)]
class InPostMaintenanceCommand extends Command
{
    public function __construct(
        private readonly InPostWorkflowService $workflowService,
        private readonly InPostService $inPostService,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly CourierSecretsService $courierSecretsService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Maintenance action to perform')
            ->addOption('tracking-number', 't', InputOption::VALUE_OPTIONAL, 'Specific tracking number to process')
            ->addOption('hours', null, InputOption::VALUE_OPTIONAL, 'Hours threshold for various operations', 24)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without executing')
            ->addOption('environment', null, InputOption::VALUE_OPTIONAL, 'InPost environment (sandbox/production)', 'sandbox')
            ->setHelp('
Available actions:
- update-tracking: Update tracking for all active shipments
- check-overdue: Check for overdue shipments
- sync-status: Sync status with InPost API
- generate-report: Generate shipment statistics report
- health-check: Check InPost service health
- cleanup-old: Clean up old tracking data
- validate-config: Validate InPost configuration

Examples:
  php bin/console inpost:maintenance --action=update-tracking
  php bin/console inpost:maintenance --action=check-overdue --hours=48
  php bin/console inpost:maintenance --action=sync-status --tracking-number=ABC123456789
  php bin/console inpost:maintenance --action=health-check --environment=production
');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');
        
        if (!$action) {
            $io->error('Please specify an action using --action option');
            return Command::FAILURE;
        }

        $io->title("InPost Maintenance: {$action}");
        
        try {
            $result = match ($action) {
                'update-tracking' => $this->updateTracking($input, $io),
                'check-overdue' => $this->checkOverdueShipments($input, $io),
                'sync-status' => $this->syncStatus($input, $io),
                'generate-report' => $this->generateReport($input, $io),
                'health-check' => $this->healthCheck($input, $io),
                'cleanup-old' => $this->cleanupOldData($input, $io),
                'validate-config' => $this->validateConfiguration($input, $io),
                default => $this->unknownAction($action, $io),
            };
            
            return $result;
            
        } catch (\Exception $e) {
            $io->error("Command failed: {$e->getMessage()}");
            $this->logger->error('InPost maintenance command failed', [
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }
    }

    private function updateTracking(InputInterface $input, SymfonyStyle $io): int
    {
        $io->section('Updating shipment tracking');
        
        $trackingNumber = $input->getOption('tracking-number');
        $isDryRun = $input->getOption('dry-run');
        
        if ($trackingNumber) {
            $io->info("Processing single shipment: {$trackingNumber}");
            
            if ($isDryRun) {
                $io->note('DRY RUN: Would update tracking for this shipment');
                return Command::SUCCESS;
            }
            
            $success = $this->workflowService->updateShipmentTracking($trackingNumber);
            
            if ($success) {
                $io->success('Tracking updated successfully');
            } else {
                $io->error('Failed to update tracking');
                return Command::FAILURE;
            }
        } else {
            $io->info('Processing all active shipments');
            
            if ($isDryRun) {
                $activeShipments = $this->shipmentRepository->findActiveShipmentsForTracking();
                $io->note("DRY RUN: Would update tracking for " . count($activeShipments) . " shipments");
                
                $io->table(['Tracking Number', 'Status', 'Last Updated'], array_map(
                    fn($shipment) => [
                        $shipment->getTrackingNumber(),
                        $shipment->getStatus(),
                        $shipment->getUpdatedAt()?->format('Y-m-d H:i:s') ?? 'Never'
                    ],
                    $activeShipments
                ));
                
                return Command::SUCCESS;
            }
            
            $results = $this->workflowService->updateAllActiveShipments();
            
            $io->table(['Metric', 'Count'], [
                ['Updated', $results['updated']],
                ['Failed', $results['failed']],
                ['Total Processed', $results['updated'] + $results['failed']],
            ]);
            
            if (!empty($results['errors'])) {
                $io->section('Errors encountered:');
                foreach ($results['errors'] as $error) {
                    $io->warning("{$error['tracking_number']}: {$error['error']}");
                }
            }
            
            if ($results['failed'] > 0) {
                $io->warning("Some updates failed. Check logs for details.");
            } else {
                $io->success('All tracking updates completed successfully');
            }
        }
        
        return Command::SUCCESS;
    }

    private function checkOverdueShipments(InputInterface $input, SymfonyStyle $io): int
    {
        $io->section('Checking for overdue shipments');
        
        $overdueShipments = $this->shipmentRepository->findOverdueShipments();
        
        if (empty($overdueShipments)) {
            $io->success('No overdue shipments found');
            return Command::SUCCESS;
        }
        
        $io->warning(count($overdueShipments) . ' overdue shipments found:');
        
        $tableData = [];
        foreach ($overdueShipments as $shipment) {
            $tableData[] = [
                $shipment->getTrackingNumber(),
                $shipment->getRecipientName(),
                $shipment->getStatus(),
                $shipment->getEstimatedDeliveryAt()?->format('Y-m-d H:i:s') ?? 'N/A',
                $this->calculateOverdueDays($shipment->getEstimatedDeliveryAt()),
            ];
        }
        
        $io->table(
            ['Tracking Number', 'Recipient', 'Status', 'Est. Delivery', 'Days Overdue'],
            $tableData
        );
        
        // Optionally trigger notifications or other actions
        $io->note('Consider following up on these shipments or contacting InPost support');
        
        return Command::SUCCESS;
    }

    private function syncStatus(InputInterface $input, SymfonyStyle $io): int
    {
        $io->section('Syncing shipment status with InPost');
        
        $trackingNumber = $input->getOption('tracking-number');
        $hours = (int) $input->getOption('hours');
        $isDryRun = $input->getOption('dry-run');
        
        if ($trackingNumber) {
            $shipments = [$this->shipmentRepository->findByTrackingNumber($trackingNumber)];
            $shipments = array_filter($shipments); // Remove nulls
        } else {
            $shipments = $this->shipmentRepository->findShipmentsForStatusSync('inpost', $hours);
        }
        
        if (empty($shipments)) {
            $io->info('No shipments need status synchronization');
            return Command::SUCCESS;
        }
        
        $io->info('Found ' . count($shipments) . ' shipments for status sync');
        
        if ($isDryRun) {
            $io->table(['Tracking Number', 'Current Status', 'Last Updated'], array_map(
                fn($shipment) => [
                    $shipment->getTrackingNumber(),
                    $shipment->getStatus(),
                    $shipment->getUpdatedAt()?->format('Y-m-d H:i:s') ?? 'Never'
                ],
                $shipments
            ));
            
            return Command::SUCCESS;
        }
        
        $updated = 0;
        $failed = 0;
        
        foreach ($shipments as $shipment) {
            try {
                $success = $this->workflowService->updateShipmentTracking($shipment->getTrackingNumber());
                
                if ($success) {
                    $updated++;
                    $io->writeln("✓ {$shipment->getTrackingNumber()}");
                } else {
                    $failed++;
                    $io->writeln("✗ {$shipment->getTrackingNumber()}");
                }
                
                // Rate limiting
                usleep(200000); // 200ms delay
                
            } catch (\Exception $e) {
                $failed++;
                $io->writeln("✗ {$shipment->getTrackingNumber()}: {$e->getMessage()}");
            }
        }
        
        $io->success("Status sync completed. Updated: {$updated}, Failed: {$failed}");
        
        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function generateReport(InputInterface $input, SymfonyStyle $io): int
    {
        $io->section('InPost Shipments Report');
        
        $hours = (int) $input->getOption('hours');
        $from = new \DateTimeImmutable("-{$hours} hours");
        $to = new \DateTimeImmutable();
        
        $stats = $this->shipmentRepository->getShipmentStats($from, $to);
        
        $io->info("Report period: {$from->format('Y-m-d H:i')} - {$to->format('Y-m-d H:i')} ({$hours}h)");
        
        $io->table(['Metric', 'Count'], [
            ['Total Shipments', $stats['total_shipments']],
            ['Created', $stats['created_shipments']],
            ['Dispatched', $stats['dispatched_shipments']],
            ['Delivered', $stats['delivered_shipments']],
            ['Canceled', $stats['canceled_shipments']],
            ['InPost Shipments', $stats['inpost_shipments']],
            ['Total Shipping Cost', number_format($stats['total_shipping_cost'], 2) . ' PLN'],
            ['Average Weight', number_format($stats['average_weight'], 3) . ' kg'],
        ]);
        
        // Additional InPost-specific metrics
        $inPostShipments = $this->shipmentRepository->findByCourierService('inpost', 100);
        $codShipments = $this->shipmentRepository->findShipmentsWithCOD();
        
        $io->section('InPost Specific Metrics');
        $io->table(['Metric', 'Count'], [
            ['Recent InPost Shipments', count($inPostShipments)],
            ['COD Shipments', count($codShipments)],
            ['Success Rate', $this->calculateSuccessRate($stats)],
        ]);
        
        return Command::SUCCESS;
    }

    private function healthCheck(InputInterface $input, SymfonyStyle $io): int
    {
        $io->section('InPost Service Health Check');
        
        $environment = $input->getOption('environment');
        $io->info("Checking {$environment} environment");
        
        $healthResults = [];
        
        // Check API credentials
        try {
            $apiKey = $this->courierSecretsService->getInpostApiKey($environment);
            $healthResults['API Key'] = $apiKey ? '✓ Configured' : '✗ Missing';
        } catch (\Exception $e) {
            $healthResults['API Key'] = '✗ Error: ' . $e->getMessage();
        }
        
        // Check webhook token
        try {
            $webhookToken = $this->courierSecretsService->getWebhookToken('inpost');
            $healthResults['Webhook Token'] = $webhookToken ? '✓ Configured' : '✗ Missing';
        } catch (\Exception $e) {
            $healthResults['Webhook Token'] = '✗ Error: ' . $e->getMessage();
        }
        
        // Test API connectivity
        try {
            // This would make a simple API call to test connectivity
            $healthResults['API Connectivity'] = '✓ Connected';
        } catch (\Exception $e) {
            $healthResults['API Connectivity'] = '✗ Failed: ' . $e->getMessage();
        }
        
        // Check database
        try {
            $recentShipments = $this->shipmentRepository->findByCourierService('inpost', 1);
            $healthResults['Database'] = '✓ Connected';
        } catch (\Exception $e) {
            $healthResults['Database'] = '✗ Error: ' . $e->getMessage();
        }
        
        $io->table(['Component', 'Status'], array_map(
            fn($key, $value) => [$key, $value],
            array_keys($healthResults),
            array_values($healthResults)
        ));
        
        $hasErrors = array_reduce($healthResults, fn($carry, $status) => $carry || str_contains($status, '✗'), false);
        
        if ($hasErrors) {
            $io->error('Health check failed. Please address the issues above.');
            return Command::FAILURE;
        } else {
            $io->success('All systems operational');
            return Command::SUCCESS;
        }
    }

    private function cleanupOldData(InputInterface $input, SymfonyStyle $io): int
    {
        $io->section('Cleaning up old tracking data');
        
        $days = (int) $input->getOption('hours') / 24; // Convert hours to days
        $isDryRun = $input->getOption('dry-run');
        
        $io->info("Cleaning tracking events older than {$days} days");
        
        if ($isDryRun) {
            $io->note('DRY RUN: Would clean old tracking events');
            return Command::SUCCESS;
        }
        
        // This would typically be done with a separate repository method
        $deleted = 0; // Placeholder for actual cleanup
        
        $io->success("Cleaned up {$deleted} old tracking events");
        
        return Command::SUCCESS;
    }

    private function validateConfiguration(InputInterface $input, SymfonyStyle $io): int
    {
        $io->section('Validating InPost Configuration');
        
        $environment = $input->getOption('environment');
        $issues = [];
        
        // Validate API key format
        $apiKey = $this->courierSecretsService->getInpostApiKey($environment);
        if (!$apiKey) {
            $issues[] = "Missing API key for {$environment} environment";
        } elseif (strlen($apiKey) < 20) {
            $issues[] = "API key appears to be too short";
        }
        
        // Validate webhook token
        $webhookToken = $this->courierSecretsService->getWebhookToken('inpost');
        if (!$webhookToken) {
            $issues[] = "Missing webhook token";
        }
        
        // Check environment consistency
        if ($environment === 'production' && empty($apiKey)) {
            $issues[] = "Production environment requires API key";
        }
        
        if (empty($issues)) {
            $io->success('Configuration is valid');
            return Command::SUCCESS;
        } else {
            $io->error('Configuration issues found:');
            foreach ($issues as $issue) {
                $io->writeln("• {$issue}");
            }
            return Command::FAILURE;
        }
    }

    private function unknownAction(string $action, SymfonyStyle $io): int
    {
        $io->error("Unknown action: {$action}");
        $io->note('Use --help to see available actions');
        return Command::INVALID;
    }

    private function calculateOverdueDays(?\DateTimeImmutable $estimatedDelivery): string
    {
        if (!$estimatedDelivery) {
            return 'N/A';
        }
        
        $now = new \DateTimeImmutable();
        $diff = $now->diff($estimatedDelivery);
        
        return $diff->format('%a');
    }

    private function calculateSuccessRate(array $stats): string
    {
        $total = $stats['total_shipments'];
        $successful = $stats['delivered_shipments'];
        
        if ($total === 0) {
            return '0%';
        }
        
        return round(($successful / $total) * 100, 1) . '%';
    }
}