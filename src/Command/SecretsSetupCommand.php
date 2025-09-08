<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\CourierSecretsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'secrets:setup',
    description: 'Interactive setup wizard for courier and payment service secrets',
)]
class SecretsSetupCommand extends Command
{
    public function __construct(
        private readonly CourierSecretsService $courierSecrets
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('service', 's', InputOption::VALUE_OPTIONAL, 'Setup specific service (inpost, dhl, paynow, stripe, smtp)')
            ->addOption('environment', 'e', InputOption::VALUE_OPTIONAL, 'Environment (sandbox/production, test/live)', 'sandbox')
            ->addOption('skip-existing', null, InputOption::VALUE_NONE, 'Skip services that already have configured secrets')
            ->setHelp('
This command provides an interactive wizard to set up secrets for various services.

Examples:
  # Full interactive setup
  <info>php bin/console secrets:setup</info>
  
  # Setup specific service
  <info>php bin/console secrets:setup --service=inpost</info>
  
  # Setup for production environment
  <info>php bin/console secrets:setup --environment=production</info>
  
  # Skip already configured services
  <info>php bin/console secrets:setup --skip-existing</info>

Supported services: inpost, dhl, paynow, stripe, smtp
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $service = $input->getOption('service');
        $environment = $input->getOption('environment');
        $skipExisting = $input->getOption('skip-existing');

        $io->title('Sky - Secrets Setup Wizard');

        if ($service) {
            return $this->setupSpecificService($io, $service, $environment, $skipExisting);
        }

        return $this->setupAllServices($io, $environment, $skipExisting);
    }

    private function setupSpecificService(SymfonyStyle $io, string $service, string $environment, bool $skipExisting): int
    {
        switch ($service) {
            case 'inpost':
                return $this->setupInpost($io, $environment, $skipExisting);
            case 'dhl':
                return $this->setupDhl($io, $environment, $skipExisting);
            case 'paynow':
                return $this->setupPayNow($io, $environment, $skipExisting);
            case 'stripe':
                return $this->setupStripe($io, $environment === 'production' ? 'live' : 'test', $skipExisting);
            case 'smtp':
                return $this->setupSmtp($io, $skipExisting);
            default:
                $io->error(sprintf('Unknown service: %s', $service));
                return Command::FAILURE;
        }
    }

    private function setupAllServices(SymfonyStyle $io, string $environment, bool $skipExisting): int
    {
        $io->section('Setting up all services');

        $services = ['inpost', 'dhl', 'paynow', 'stripe', 'smtp'];
        $successful = 0;
        $skipped = 0;

        foreach ($services as $service) {
            try {
                $io->writeln(sprintf('<info>Setting up %s...</info>', $service));
                
                $result = $this->setupSpecificService($io, $service, $environment, $skipExisting);
                
                if ($result === Command::SUCCESS) {
                    $successful++;
                } else {
                    $io->warning(sprintf('Setup for %s completed with warnings or was skipped', $service));
                    $skipped++;
                }
            } catch (\Exception $e) {
                $io->error(sprintf('Failed to setup %s: %s', $service, $e->getMessage()));
            }
        }

        $io->success(sprintf(
            'Setup completed. Successful: %d, Skipped/Warning: %d',
            $successful,
            $skipped
        ));

        return Command::SUCCESS;
    }

    private function setupInpost(SymfonyStyle $io, string $environment, bool $skipExisting): int
    {
        $io->section(sprintf('InPost API Setup (%s)', $environment));

        if ($skipExisting && $this->courierSecrets->getInpostApiKey($environment)) {
            $io->note('InPost API key already configured, skipping...');
            return Command::SUCCESS;
        }

        $apiKey = $io->askHidden('Enter InPost API key');
        if (!$apiKey) {
            $io->warning('No API key provided, skipping InPost setup');
            return Command::SUCCESS;
        }

        try {
            $this->courierSecrets->setInpostApiKey($apiKey, $environment);
            $io->success(sprintf('InPost API key configured for %s environment', $environment));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to store InPost API key: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function setupDhl(SymfonyStyle $io, string $environment, bool $skipExisting): int
    {
        $io->section(sprintf('DHL API Setup (%s)', $environment));

        if ($skipExisting) {
            $credentials = $this->courierSecrets->getDhlCredentials($environment);
            if (!empty($credentials['username']) && !empty($credentials['password'])) {
                $io->note('DHL credentials already configured, skipping...');
                return Command::SUCCESS;
            }
        }

        $username = $io->ask('Enter DHL API username');
        if (!$username) {
            $io->warning('No username provided, skipping DHL setup');
            return Command::SUCCESS;
        }

        $password = $io->askHidden('Enter DHL API password');
        if (!$password) {
            $io->warning('No password provided, skipping DHL setup');
            return Command::SUCCESS;
        }

        $accountNumber = $io->ask('Enter DHL account number');
        if (!$accountNumber) {
            $io->warning('No account number provided, skipping DHL setup');
            return Command::SUCCESS;
        }

        try {
            $this->courierSecrets->setDhlCredentials($username, $password, $accountNumber, $environment);
            $io->success(sprintf('DHL credentials configured for %s environment', $environment));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to store DHL credentials: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function setupPayNow(SymfonyStyle $io, string $environment, bool $skipExisting): int
    {
        $io->section(sprintf('PayNow Setup (%s)', $environment));

        if ($skipExisting) {
            $credentials = $this->courierSecrets->getPayNowCredentials($environment);
            if (!empty($credentials['api_key']) && !empty($credentials['signature_key'])) {
                $io->note('PayNow credentials already configured, skipping...');
                return Command::SUCCESS;
            }
        }

        $apiKey = $io->askHidden('Enter PayNow API key');
        if (!$apiKey) {
            $io->warning('No API key provided, skipping PayNow setup');
            return Command::SUCCESS;
        }

        $signatureKey = $io->askHidden('Enter PayNow signature key');
        if (!$signatureKey) {
            $io->warning('No signature key provided, skipping PayNow setup');
            return Command::SUCCESS;
        }

        try {
            $this->courierSecrets->setPayNowCredentials($apiKey, $signatureKey, $environment);
            $io->success(sprintf('PayNow credentials configured for %s environment', $environment));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to store PayNow credentials: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function setupStripe(SymfonyStyle $io, string $environment, bool $skipExisting): int
    {
        $io->section(sprintf('Stripe Setup (%s)', $environment));

        if ($skipExisting) {
            $keys = $this->courierSecrets->getStripeKeys($environment);
            if (!empty($keys['publishable_key']) && !empty($keys['secret_key'])) {
                $io->note('Stripe keys already configured, skipping...');
                return Command::SUCCESS;
            }
        }

        $publishableKey = $io->ask('Enter Stripe publishable key');
        if (!$publishableKey) {
            $io->warning('No publishable key provided, skipping Stripe setup');
            return Command::SUCCESS;
        }

        $secretKey = $io->askHidden('Enter Stripe secret key');
        if (!$secretKey) {
            $io->warning('No secret key provided, skipping Stripe setup');
            return Command::SUCCESS;
        }

        $webhookSecret = $io->askHidden('Enter Stripe webhook secret (optional)');

        try {
            $this->courierSecrets->setStripeKeys($publishableKey, $secretKey, $webhookSecret ?: null, $environment);
            $io->success(sprintf('Stripe keys configured for %s environment', $environment));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to store Stripe keys: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function setupSmtp(SymfonyStyle $io, bool $skipExisting): int
    {
        $io->section('SMTP Server Setup');

        if ($skipExisting) {
            $credentials = $this->courierSecrets->getSmtpCredentials();
            if (!empty($credentials['username']) && !empty($credentials['password'])) {
                $io->note('SMTP credentials already configured, skipping...');
                return Command::SUCCESS;
            }
        }

        $host = $io->ask('Enter SMTP host');
        if (!$host) {
            $io->warning('No SMTP host provided, skipping SMTP setup');
            return Command::SUCCESS;
        }

        $port = $io->ask('Enter SMTP port', '587');
        $username = $io->ask('Enter SMTP username');
        if (!$username) {
            $io->warning('No SMTP username provided, skipping SMTP setup');
            return Command::SUCCESS;
        }

        $password = $io->askHidden('Enter SMTP password');
        if (!$password) {
            $io->warning('No SMTP password provided, skipping SMTP setup');
            return Command::SUCCESS;
        }

        $encryption = $io->choice('Select encryption', ['tls', 'ssl', 'none'], 'tls');

        try {
            $this->courierSecrets->setSmtpCredentials($host, (int) $port, $username, $password, $encryption);
            $io->success('SMTP credentials configured');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to store SMTP credentials: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
