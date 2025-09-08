<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\SecretCategory;
use App\Service\SecretsManagerService;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'secrets:store',
    description: 'Store a secret value in the secrets management system',
)]
class SecretsStoreCommand extends Command
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
            ->addArgument('value', InputArgument::OPTIONAL, 'Secret value (will prompt if not provided)')
            ->addOption('description', 'd', InputOption::VALUE_OPTIONAL, 'Secret description')
            ->addOption('expires-in', 'e', InputOption::VALUE_OPTIONAL, 'Expiration time (e.g., "30 days", "6 months")')
            ->addOption('generate', 'g', InputOption::VALUE_NONE, 'Generate a random secret value')
            ->addOption('generate-api-key', null, InputOption::VALUE_OPTIONAL, 'Generate API key with optional prefix')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force update if secret already exists')
            ->setHelp('
This command allows you to store secrets in the encrypted secrets management system.

Examples:
  # Store a secret interactively
  <info>php bin/console secrets:store courier_api_keys inpost_api_key</info>
  
  # Store a secret with value
  <info>php bin/console secrets:store courier_api_keys inpost_api_key "your-api-key-here"</info>
  
  # Generate a random secret
  <info>php bin/console secrets:store internal_tokens jwt_secret --generate</info>
  
  # Generate an API key with prefix
  <info>php bin/console secrets:store courier_api_keys dhl_api_key --generate-api-key="dhl_"</info>
  
  # Store with expiration
  <info>php bin/console secrets:store payment_keys stripe_key "sk_test_..." --expires-in="1 year"</info>

Available categories: ' . implode(', ', array_map(fn($c) => $c->value, SecretCategory::cases()))
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $category = $input->getArgument('category');
        $name = $input->getArgument('name');
        $value = $input->getArgument('value');
        $description = $input->getOption('description');
        $expiresIn = $input->getOption('expires-in');
        $generate = $input->getOption('generate');
        $generateApiKey = $input->getOption('generate-api-key');
        $force = $input->getOption('force');

        // Validate category
        if (!$this->isValidCategory($category)) {
            $io->error(sprintf('Invalid category "%s". Available categories: %s', 
                $category, 
                implode(', ', array_map(fn($c) => $c->value, SecretCategory::cases()))
            ));
            return Command::FAILURE;
        }

        // Check if secret already exists
        $existingValue = $this->secretsManager->getSecret($category, $name);
        if ($existingValue && !$force) {
            $io->warning('Secret already exists. Use --force to update it.');
            return Command::FAILURE;
        }

        // Handle value generation or input
        if ($generate) {
            $value = bin2hex(random_bytes(32));
            $io->success('Generated random secret value');
        } elseif ($generateApiKey !== null) {
            $prefix = $generateApiKey ?: '';
            $value = $prefix . bin2hex(random_bytes(32));
            $io->success(sprintf('Generated API key%s', $prefix ? " with prefix '$prefix'" : ''));
        } elseif (!$value) {
            $value = $io->askHidden('Enter secret value');
            if (!$value) {
                $io->error('Secret value cannot be empty');
                return Command::FAILURE;
            }
        }

        // Parse expiration date
        $expiresAt = null;
        if ($expiresIn) {
            try {
                $expiresAt = new DateTimeImmutable("+$expiresIn");
                $io->note(sprintf('Secret will expire on: %s', $expiresAt->format('Y-m-d H:i:s')));
            } catch (\Exception $e) {
                $io->error(sprintf('Invalid expiration format: %s', $e->getMessage()));
                return Command::FAILURE;
            }
        }

        try {
            $secret = $this->secretsManager->storeSecret(
                $category,
                $name,
                $value,
                $description,
                $expiresAt
            );

            $io->success(sprintf(
                'Secret "%s.%s" stored successfully (version %d)',
                $category,
                $name,
                $secret->getVersion()
            ));

            if ($existingValue) {
                $io->note('Previous version has been deactivated');
            }

            return Command::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function isValidCategory(string $category): bool
    {
        foreach (SecretCategory::cases() as $validCategory) {
            if ($validCategory->value === $category) {
                return true;
            }
        }
        return false;
    }
}