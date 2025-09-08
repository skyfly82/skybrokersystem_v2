<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\SecretCategory;
use App\Service\SecretsManagerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'secrets:list',
    description: 'List all secrets in the secrets management system',
)]
class SecretsListCommand extends Command
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
            ->addOption('show-values', 's', InputOption::VALUE_NONE, 'Show decrypted values (DANGEROUS)')
            ->addOption('show-expired', 'e', InputOption::VALUE_NONE, 'Include expired secrets')
            ->addOption('show-inactive', 'i', InputOption::VALUE_NONE, 'Include inactive secrets')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (table, json)', 'table')
            ->setHelp('
This command lists all secrets stored in the system.

Examples:
  # List all active secrets
  <info>php bin/console secrets:list</info>
  
  # List secrets in specific category
  <info>php bin/console secrets:list --category=courier_api_keys</info>
  
  # List with values (be careful!)
  <info>php bin/console secrets:list --show-values</info>
  
  # Export as JSON
  <info>php bin/console secrets:list --format=json</info>
  
  # Include expired and inactive
  <info>php bin/console secrets:list --show-expired --show-inactive</info>

Available categories: ' . implode(', ', array_map(fn($c) => $c->value, SecretCategory::cases()))
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $category = $input->getOption('category');
        $showValues = $input->getOption('show-values');
        $showExpired = $input->getOption('show-expired');
        $showInactive = $input->getOption('show-inactive');
        $format = $input->getOption('format');

        if ($showValues) {
            $confirm = $io->confirm('You are about to display secret values in plain text. Are you sure?', false);
            if (!$confirm) {
                $io->info('Operation cancelled');
                return Command::SUCCESS;
            }
        }

        // Get secrets based on filters
        if ($category) {
            $secrets = $this->secretsManager->getSecretsByCategory($category);
        } else {
            $secrets = $this->secretsManager->getAllActiveSecrets();
            
            if ($showInactive) {
                // This would need additional repository method to get all secrets
                $io->note('--show-inactive not fully implemented yet');
            }
        }

        // Filter expired secrets
        if (!$showExpired) {
            $secrets = array_filter($secrets, fn($secret) => !$secret->isExpired());
        }

        if (empty($secrets)) {
            $io->info('No secrets found matching the criteria');
            return Command::SUCCESS;
        }

        if ($format === 'json') {
            $this->outputJson($secrets, $showValues, $output);
        } else {
            $this->outputTable($secrets, $showValues, $io);
        }

        return Command::SUCCESS;
    }

    private function outputTable(array $secrets, bool $showValues, SymfonyStyle $io): void
    {
        $headers = ['Category', 'Name', 'Version', 'Status', 'Created', 'Expires'];
        if ($showValues) {
            $headers[] = 'Value';
        }
        $headers[] = 'Description';

        $rows = [];
        foreach ($secrets as $secret) {
            $row = [
                $secret->getCategory(),
                $secret->getName(),
                $secret->getVersion(),
                $this->getStatusString($secret),
                $secret->getCreatedAt()->format('Y-m-d H:i'),
                $secret->getExpiresAt()?->format('Y-m-d H:i') ?? 'Never',
            ];

            if ($showValues) {
                try {
                    $value = $this->secretsManager->getSecret($secret->getCategory(), $secret->getName());
                    $row[] = $this->maskValue($value);
                } catch (\Exception) {
                    $row[] = '[DECRYPT ERROR]';
                }
            }

            $row[] = $secret->getDescription() ?? '';
            $rows[] = $row;
        }

        $io->table($headers, $rows);

        if ($showValues) {
            $io->warning('Secret values were displayed above. Make sure to clear your terminal history!');
        }
    }

    private function outputJson(array $secrets, bool $showValues, OutputInterface $output): void
    {
        $data = [];
        foreach ($secrets as $secret) {
            $item = [
                'category' => $secret->getCategory(),
                'name' => $secret->getName(),
                'version' => $secret->getVersion(),
                'is_active' => $secret->isActive(),
                'is_expired' => $secret->isExpired(),
                'created_at' => $secret->getCreatedAt()->format('c'),
                'updated_at' => $secret->getUpdatedAt()->format('c'),
                'expires_at' => $secret->getExpiresAt()?->format('c'),
                'description' => $secret->getDescription(),
                'metadata' => $secret->getMetadata(),
            ];

            if ($showValues) {
                try {
                    $item['value'] = $this->secretsManager->getSecret($secret->getCategory(), $secret->getName());
                } catch (\Exception) {
                    $item['value'] = null;
                    $item['decrypt_error'] = true;
                }
            }

            $data[] = $item;
        }

        $output->writeln(json_encode($data, JSON_PRETTY_PRINT));
    }

    private function getStatusString($secret): string
    {
        if (!$secret->isActive()) {
            return '<fg=red>Inactive</>';
        }

        if ($secret->isExpired()) {
            return '<fg=red>Expired</>';
        }

        if ($secret->getExpiresAt() && $secret->getExpiresAt() <= (new \DateTimeImmutable())->modify('+30 days')) {
            return '<fg=yellow>Expiring Soon</>';
        }

        return '<fg=green>Active</>';
    }

    private function maskValue(?string $value): string
    {
        if (!$value) {
            return '';
        }

        $length = strlen($value);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4) . str_repeat('*', $length - 8) . substr($value, -4);
    }
}