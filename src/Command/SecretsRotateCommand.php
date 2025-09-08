<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SecretsManagerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'secrets:rotate',
    description: 'Rotate a secret with a new value',
)]
class SecretsRotateCommand extends Command
{
    public function __construct(
        private readonly SecretsManagerService $secretsManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('category', InputArgument::REQUIRED, 'Secret category')
            ->addArgument('name', InputArgument::REQUIRED, 'Secret name')
            ->addArgument('new-value', InputArgument::OPTIONAL, 'New secret value (will prompt if not provided)')
            ->addOption('generate', 'g', InputOption::VALUE_NONE, 'Generate a random new value')
            ->addOption('generate-api-key', null, InputOption::VALUE_OPTIONAL, 'Generate API key with optional prefix')
            ->addOption('auto-rotate', 'a', InputOption::VALUE_NONE, 'Auto-rotate all secrets expiring soon')
            ->addOption('days-ahead', 'd', InputOption::VALUE_OPTIONAL, 'Days ahead to check for expiration (default: 30)', 30)
            ->setHelp('
This command rotates secrets by creating a new version and deactivating the old one.

Examples:
  # Rotate a secret interactively
  <info>php bin/console secrets:rotate courier_api_keys inpost_api_key</info>
  
  # Rotate with new value
  <info>php bin/console secrets:rotate courier_api_keys inpost_api_key "new-api-key-value"</info>
  
  # Generate new random value
  <info>php bin/console secrets:rotate internal_tokens jwt_secret --generate</info>
  
  # Generate new API key
  <info>php bin/console secrets:rotate courier_api_keys dhl_api_key --generate-api-key="dhl_"</info>
  
  # Auto-rotate all secrets expiring in next 30 days
  <info>php bin/console secrets:rotate --auto-rotate</info>
  
  # Auto-rotate secrets expiring in next 7 days
  <info>php bin/console secrets:rotate --auto-rotate --days-ahead=7</info>
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $autoRotate = $input->getOption('auto-rotate');

        if ($autoRotate) {
            return $this->handleAutoRotate($input, $output, $io);
        }

        return $this->handleSingleRotate($input, $output, $io);
    }

    private function handleSingleRotate(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $category = $input->getArgument('category');
        $name = $input->getArgument('name');
        $newValue = $input->getArgument('new-value');
        $generate = $input->getOption('generate');
        $generateApiKey = $input->getOption('generate-api-key');

        // Check if secret exists
        $currentValue = $this->secretsManager->getSecret($category, $name);
        if (!$currentValue) {
            $io->error(sprintf('Secret "%s.%s" not found or not accessible', $category, $name));
            return Command::FAILURE;
        }

        // Generate or get new value
        if ($generate) {
            $newValue = bin2hex(random_bytes(32));
            $io->success('Generated new random value');
        } elseif ($generateApiKey !== null) {
            $prefix = $generateApiKey ?: '';
            $newValue = $prefix . bin2hex(random_bytes(32));
            $io->success(sprintf('Generated new API key%s', $prefix ? " with prefix '$prefix'" : ''));
        } elseif (!$newValue) {
            $newValue = $io->askHidden('Enter new secret value');
            if (!$newValue) {
                $io->error('New secret value cannot be empty');
                return Command::FAILURE;
            }
        }

        // Confirm rotation
        if (!$io->confirm(sprintf('Are you sure you want to rotate secret "%s.%s"?', $category, $name))) {
            $io->info('Rotation cancelled');
            return Command::SUCCESS;
        }

        try {
            // Find the current secret to get its details
            $secrets = $this->secretsManager->getSecretsByCategory($category);
            $currentSecret = null;
            foreach ($secrets as $secret) {
                if ($secret->getName() === $name && $secret->isActive()) {
                    $currentSecret = $secret;
                    break;
                }
            }

            if (!$currentSecret) {
                $io->error('Current active secret not found');
                return Command::FAILURE;
            }

            $newSecret = $this->secretsManager->rotateSecret(
                $currentSecret,
                $newValue
            );

            $io->success(sprintf(
                'Secret "%s.%s" rotated successfully (new version %d)',
                $category,
                $name,
                $newSecret->getVersion()
            ));

            $io->note('Previous version has been deactivated');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to rotate secret: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function handleAutoRotate(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $daysAhead = (int) $input->getOption('days-ahead');

        $io->info(sprintf('Checking for secrets expiring in the next %d days...', $daysAhead));

        $secretsToRotate = $this->secretsManager->getSecretsForRotation($daysAhead);

        if (empty($secretsToRotate)) {
            $io->success('No secrets need rotation at this time');
            return Command::SUCCESS;
        }

        $io->table(
            ['Category', 'Name', 'Version', 'Expires At', 'Days Until Expiry'],
            array_map(function ($secret) {
                $daysUntil = $secret->getExpiresAt() 
                    ? $secret->getExpiresAt()->diff(new \DateTimeImmutable())->days 
                    : null;
                
                return [
                    $secret->getCategory(),
                    $secret->getName(),
                    $secret->getVersion(),
                    $secret->getExpiresAt()?->format('Y-m-d H:i:s') ?? 'Never',
                    $daysUntil ?? 'N/A'
                ];
            }, $secretsToRotate)
        );

        if (!$io->confirm(sprintf('Found %d secrets that need rotation. Proceed?', count($secretsToRotate)))) {
            $io->info('Auto-rotation cancelled');
            return Command::SUCCESS;
        }

        $rotatedCount = 0;
        $failedCount = 0;

        foreach ($secretsToRotate as $secret) {
            try {
                // Generate new random value for auto-rotation
                $newValue = bin2hex(random_bytes(32));
                
                $this->secretsManager->rotateSecret($secret, $newValue);
                
                $io->writeln(sprintf(
                    '<info>✓</info> Rotated %s.%s (v%d)',
                    $secret->getCategory(),
                    $secret->getName(),
                    $secret->getVersion()
                ));
                
                $rotatedCount++;
            } catch (\Exception $e) {
                $io->writeln(sprintf(
                    '<error>✗</error> Failed to rotate %s.%s: %s',
                    $secret->getCategory(),
                    $secret->getName(),
                    $e->getMessage()
                ));
                
                $failedCount++;
            }
        }

        $io->success(sprintf(
            'Auto-rotation completed. Rotated: %d, Failed: %d',
            $rotatedCount,
            $failedCount
        ));

        if ($failedCount > 0) {
            $io->warning('Some secrets failed to rotate. Check logs for details.');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}