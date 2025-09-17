<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Command;

use App\Domain\Courier\Meest\Service\MeestBackgroundUpdateService;
use App\Domain\Courier\Meest\Service\MeestMLPredictionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'meest:ai-tracking-update',
    description: 'Run AI-powered tracking updates for MEEST shipments'
)]
class MeestAITrackingUpdateCommand extends Command
{
    public function __construct(
        private readonly MeestBackgroundUpdateService $backgroundUpdateService,
        private readonly MeestMLPredictionService $mlPredictionService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run AI-powered tracking updates for MEEST shipments')
            ->setHelp('This command runs background updates with AI prioritization, ML predictions, and automated notifications')
            ->addOption(
                'train-models',
                't',
                InputOption::VALUE_NONE,
                'Train ML models before running updates'
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Run in dry-run mode without making actual updates'
            )
            ->addOption(
                'priority-threshold',
                'p',
                InputOption::VALUE_REQUIRED,
                'Minimum priority threshold for updates (0.0-1.0)',
                '0.3'
            )
            ->addOption(
                'max-shipments',
                'm',
                InputOption::VALUE_REQUIRED,
                'Maximum number of shipments to process',
                '500'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🤖 MEEST AI-Powered Tracking Updates');

        try {
            // Train ML models if requested
            if ($input->getOption('train-models')) {
                $io->section('🧠 Training ML Models');
                $this->trainMLModels($io);
            }

            // Run AI-powered tracking updates
            $io->section('🔄 Processing Tracking Updates');
            $results = $this->runTrackingUpdates($input, $io);

            // Display results
            $this->displayResults($results, $io);

            $io->success('AI-powered tracking updates completed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('AI tracking updates failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function trainMLModels(SymfonyStyle $io): void
    {
        $io->info('Starting ML model training...');

        try {
            $trainingResults = $this->mlPredictionService->trainModels();

            $io->createTable()
                ->setHeaders(['Metric', 'Value'])
                ->setRows([
                    ['Models Trained', implode(', ', $trainingResults['models'])],
                    ['Training Samples', $trainingResults['training_samples']],
                    ['Model Version', $trainingResults['model_version']],
                    ['Avg Accuracy', round(array_sum($trainingResults['accuracy_scores']) / count($trainingResults['accuracy_scores']), 3)]
                ])
                ->render();

            $io->success('ML models trained successfully!');

        } catch (\Exception $e) {
            $io->error('ML model training failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function runTrackingUpdates(InputInterface $input, SymfonyStyle $io): array
    {
        $dryRun = $input->getOption('dry-run');
        $priorityThreshold = (float) $input->getOption('priority-threshold');
        $maxShipments = (int) $input->getOption('max-shipments');

        if ($dryRun) {
            $io->warning('Running in DRY-RUN mode - no actual updates will be made');
        }

        $io->info("Priority threshold: {$priorityThreshold}");
        $io->info("Max shipments: {$maxShipments}");

        // Get prioritized shipments
        $io->text('📊 Analyzing shipments with AI prioritization...');
        $prioritizedShipments = $this->backgroundUpdateService->getPrioritizedShipments();

        // Filter by priority threshold and limit
        $filteredShipments = array_filter(
            $prioritizedShipments,
            fn($item) => $item['priority'] >= $priorityThreshold
        );
        $filteredShipments = array_slice($filteredShipments, 0, $maxShipments);

        $io->info(sprintf(
            'Found %d shipments total, %d above priority threshold %.1f, processing %d',
            count($prioritizedShipments),
            count($filteredShipments),
            $priorityThreshold,
            min(count($filteredShipments), $maxShipments)
        ));

        if (empty($filteredShipments)) {
            $io->warning('No shipments found matching criteria');
            return ['total_processed' => 0];
        }

        // Display top priority shipments
        $this->displayPriorityTable($filteredShipments, $io);

        if (!$dryRun) {
            // Process updates
            $io->text('🚀 Processing updates...');
            $progressBar = $io->createProgressBar(count($filteredShipments));
            $progressBar->start();

            $results = $this->backgroundUpdateService->processTrackingUpdates();

            $progressBar->finish();
            $io->newLine(2);

            return $results;
        } else {
            // Simulate results for dry run
            return [
                'total_processed' => count($filteredShipments),
                'successful_updates' => count($filteredShipments),
                'failed_updates' => 0,
                'high_priority_updates' => count(array_filter($filteredShipments, fn($item) => $item['priority'] > 0.7)),
                'ml_predictions_generated' => count($filteredShipments),
                'webhooks_sent' => 0,
                'processing_time' => 0.5
            ];
        }
    }

    private function displayPriorityTable(array $shipments, SymfonyStyle $io): void
    {
        $io->text('📋 Top Priority Shipments:');

        $tableRows = [];
        $topShipments = array_slice($shipments, 0, 10); // Show top 10

        foreach ($topShipments as $item) {
            $shipment = $item['shipment'];
            $priority = $item['priority'];
            $lastUpdateHours = $item['last_update_hours'];
            $statusChangeProbability = $item['predicted_status_change'];

            $priorityColor = $priority > 0.8 ? 'red' : ($priority > 0.6 ? 'yellow' : 'green');
            $priorityText = sprintf('<fg=%s>%.2f</fg>', $priorityColor, $priority);

            $tableRows[] = [
                substr($shipment->getTrackingNumber(), -8), // Last 8 chars
                $shipment->getStatus()->value,
                $priorityText,
                $lastUpdateHours . 'h',
                sprintf('%.0f%%', $statusChangeProbability * 100)
            ];
        }

        $io->createTable()
            ->setHeaders(['Tracking#', 'Status', 'Priority', 'Last Update', 'Change Prob.'])
            ->setRows($tableRows)
            ->render();
    }

    private function displayResults(array $results, SymfonyStyle $io): void
    {
        $io->section('📈 Update Results');

        // Summary table
        $io->createTable()
            ->setHeaders(['Metric', 'Count', 'Percentage'])
            ->setRows([
                [
                    'Total Processed',
                    $results['total_processed'],
                    '100%'
                ],
                [
                    'Successful Updates',
                    $results['successful_updates'],
                    $this->calculatePercentage($results['successful_updates'], $results['total_processed'])
                ],
                [
                    'Failed Updates',
                    $results['failed_updates'],
                    $this->calculatePercentage($results['failed_updates'], $results['total_processed'])
                ],
                [
                    'High Priority Updates',
                    $results['high_priority_updates'],
                    $this->calculatePercentage($results['high_priority_updates'], $results['total_processed'])
                ],
                [
                    'ML Predictions Generated',
                    $results['ml_predictions_generated'],
                    $this->calculatePercentage($results['ml_predictions_generated'], $results['total_processed'])
                ],
                [
                    'Webhooks Sent',
                    $results['webhooks_sent'],
                    $this->calculatePercentage($results['webhooks_sent'], $results['total_processed'])
                ]
            ])
            ->render();

        // Performance metrics
        $io->text([
            '',
            sprintf('⏱️  Processing time: %.2f seconds', $results['processing_time']),
            sprintf('🚀 Average time per shipment: %.3f seconds',
                $results['total_processed'] > 0 ? $results['processing_time'] / $results['total_processed'] : 0
            )
        ]);

        // Success rate indicator
        $successRate = $this->calculateSuccessRate($results);
        $successColor = $successRate > 95 ? 'green' : ($successRate > 85 ? 'yellow' : 'red');

        $io->text([
            '',
            sprintf('<fg=%s>✅ Success Rate: %.1f%%</fg>', $successColor, $successRate)
        ]);

        // Recommendations based on results
        $this->displayRecommendations($results, $io);
    }

    private function displayRecommendations(array $results, SymfonyStyle $io): void
    {
        $recommendations = [];

        // Analyze results and generate recommendations
        $successRate = $this->calculateSuccessRate($results);
        $highPriorityRate = $this->calculatePercentageFloat($results['high_priority_updates'], $results['total_processed']);
        $mlPredictionRate = $this->calculatePercentageFloat($results['ml_predictions_generated'], $results['total_processed']);

        if ($successRate < 90) {
            $recommendations[] = '⚠️  Consider investigating failed updates - success rate is below 90%';
        }

        if ($highPriorityRate > 50) {
            $recommendations[] = '🔥 High percentage of high-priority updates - consider increasing update frequency';
        }

        if ($mlPredictionRate < 30) {
            $recommendations[] = '🧠 Low ML prediction generation - consider lowering priority threshold';
        }

        if ($results['processing_time'] > 300) { // 5 minutes
            $recommendations[] = '⏰ Processing time is high - consider optimizing batch size or API calls';
        }

        if (!empty($recommendations)) {
            $io->section('💡 Recommendations');
            foreach ($recommendations as $recommendation) {
                $io->text($recommendation);
            }
        }
    }

    private function calculatePercentage(int $part, int $total): string
    {
        if ($total === 0) return '0%';
        return sprintf('%.1f%%', ($part / $total) * 100);
    }

    private function calculatePercentageFloat(int $part, int $total): float
    {
        if ($total === 0) return 0.0;
        return ($part / $total) * 100;
    }

    private function calculateSuccessRate(array $results): float
    {
        if ($results['total_processed'] === 0) return 0.0;
        return ($results['successful_updates'] / $results['total_processed']) * 100;
    }
}