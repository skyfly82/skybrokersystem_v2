<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SecretsManagerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'secrets:audit',
    description: 'Show audit logs and statistics for secrets',
)]
class SecretsAuditCommand extends Command
{
    public function __construct(
        private readonly SecretsManagerService $secretsManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('category', 'c', InputOption::VALUE_OPTIONAL, 'Filter by category')
            ->addOption('name', 'n', InputOption::VALUE_OPTIONAL, 'Filter by secret name (requires category)')
            ->addOption('security-events', 's', InputOption::VALUE_NONE, 'Show only security events')
            ->addOption('stats', null, InputOption::VALUE_NONE, 'Show statistics only')
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Number of days to look back (default: 7)', 7)
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of results (default: 50)', 50)
            ->setHelp('
This command shows audit logs and statistics for the secrets management system.

Examples:
  # Show recent audit logs
  <info>php bin/console secrets:audit</info>
  
  # Show logs for specific secret
  <info>php bin/console secrets:audit --category=courier_api_keys --name=inpost_api_key</info>
  
  # Show only security events
  <info>php bin/console secrets:audit --security-events</info>
  
  # Show statistics only
  <info>php bin/console secrets:audit --stats</info>
  
  # Show last 30 days
  <info>php bin/console secrets:audit --days=30</info>
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $category = $input->getOption('category');
        $name = $input->getOption('name');
        $securityEvents = $input->getOption('security-events');
        $statsOnly = $input->getOption('stats');
        $days = (int) $input->getOption('days');
        $limit = (int) $input->getOption('limit');

        if ($statsOnly) {
            return $this->showStatistics($io);
        }

        if ($securityEvents) {
            return $this->showSecurityEvents($io, $days);
        }

        if ($category && $name) {
            return $this->showSecretAuditLogs($io, $category, $name, $limit);
        }

        return $this->showRecentActivity($io, $days, $limit);
    }

    private function showStatistics(SymfonyStyle $io): int
    {
        $io->title('Secrets Management Statistics');

        try {
            $stats = $this->secretsManager->getSecretsStatistics();

            // Overall statistics
            $io->section('Overview');
            $io->table(
                ['Metric', 'Count'],
                [
                    ['Total Secrets', $stats['total_secrets']],
                    ['Active Secrets', $stats['total_active']],
                    ['Expired Secrets', $stats['expired_count']],
                    ['Need Rotation', $stats['rotation_needed_count']],
                ]
            );

            // Categories breakdown
            if (!empty($stats['categories'])) {
                $io->section('By Category');
                $categoryRows = [];
                foreach ($stats['categories'] as $category => $data) {
                    $categoryRows[] = [
                        $category,
                        $data['total'],
                        $data['active'],
                        $data['inactive'],
                    ];
                }

                $io->table(
                    ['Category', 'Total', 'Active', 'Inactive'],
                    $categoryRows
                );
            }

            // Recent actions
            if (!empty($stats['actions'])) {
                $io->section('Recent Actions (Last 30 Days)');
                $actionRows = [];
                foreach ($stats['actions'] as $action => $count) {
                    $actionRows[] = [ucfirst(str_replace('_', ' ', $action)), $count];
                }

                $io->table(['Action', 'Count'], $actionRows);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to retrieve statistics: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function showSecurityEvents(SymfonyStyle $io, int $days): int
    {
        $io->title(sprintf('Security Events (Last %d Days)', $days));

        try {
            $events = $this->secretsManager->getRecentSecurityEvents($days);

            if (empty($events)) {
                $io->success('No security events found');
                return Command::SUCCESS;
            }

            $rows = [];
            foreach ($events as $event) {
                $secret = $event->getSecret();
                $rows[] = [
                    $event->getPerformedAt()->format('Y-m-d H:i:s'),
                    sprintf('%s.%s', $secret->getCategory(), $secret->getName()),
                    $event->getAction(),
                    $event->getUserIdentifier() ?? 'System',
                    $event->getIpAddress() ?? 'N/A',
                    $event->getDetails() ?? '',
                ];
            }

            $io->table(
                ['Timestamp', 'Secret', 'Action', 'User', 'IP', 'Details'],
                $rows
            );

            if (count($events) >= 100) { // Assuming repository limits to 100
                $io->note('Showing latest 100 security events. Use --days for different time range.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to retrieve security events: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function showSecretAuditLogs(SymfonyStyle $io, string $category, string $name, int $limit): int
    {
        $io->title(sprintf('Audit Logs for %s.%s', $category, $name));

        try {
            $logs = $this->secretsManager->getSecretAuditLogs($category, $name, $limit);

            if (empty($logs)) {
                $io->info('No audit logs found for this secret');
                return Command::SUCCESS;
            }

            $rows = [];
            foreach ($logs as $log) {
                $rows[] = [
                    $log->getPerformedAt()->format('Y-m-d H:i:s'),
                    $log->getAction(),
                    $log->getUserIdentifier() ?? 'System',
                    $log->getIpAddress() ?? 'N/A',
                    $this->truncateString($log->getUserAgent() ?? '', 30),
                    $this->truncateString($log->getDetails() ?? '', 50),
                ];
            }

            $io->table(
                ['Timestamp', 'Action', 'User', 'IP', 'User Agent', 'Details'],
                $rows
            );

            if (count($logs) >= $limit) {
                $io->note(sprintf('Showing latest %d log entries. Increase --limit for more.', $limit));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to retrieve audit logs: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function showRecentActivity(SymfonyStyle $io, int $days, int $limit): int
    {
        $io->title(sprintf('Recent Activity (Last %d Days)', $days));

        try {
            // Note: We would need to add a method to get recent activity from all secrets
            // For now, we'll show security events as an example
            $events = $this->secretsManager->getRecentSecurityEvents($days);

            if (empty($events)) {
                $io->info('No recent activity found');
                return Command::SUCCESS;
            }

            $io->section('Recent Security Events');
            $rows = [];
            foreach (array_slice($events, 0, $limit) as $event) {
                $secret = $event->getSecret();
                $rows[] = [
                    $event->getPerformedAt()->format('Y-m-d H:i:s'),
                    sprintf('%s.%s', $secret->getCategory(), $secret->getName()),
                    $event->getAction(),
                    $event->getUserIdentifier() ?? 'System',
                    $this->truncateString($event->getDetails() ?? '', 50),
                ];
            }

            $io->table(
                ['Timestamp', 'Secret', 'Action', 'User', 'Details'],
                $rows
            );

            $io->note('Use --security-events for all security events or --stats for statistics');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to retrieve recent activity: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function truncateString(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3) . '...';
    }
}