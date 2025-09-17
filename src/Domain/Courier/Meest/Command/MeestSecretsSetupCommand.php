<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Command;

use App\Service\SecretsManagerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'meest:secrets:setup',
    description: 'Setup MEEST API credentials and configuration'
)]
class MeestSecretsSetupCommand extends Command
{
    public function __construct(
        private readonly SecretsManagerService $secretsManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('environment', 'e', InputOption::VALUE_REQUIRED, 'Environment (test|prod)', 'test')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force update existing secrets');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $environment = $input->getOption('environment');
        $force = $input->getOption('force');

        $io->title('MEEST API Secrets Setup');

        try {
            $this->setupMeestSecrets($io, $environment, $force);
            $io->success('MEEST secrets have been configured successfully');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Failed to setup MEEST secrets: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function setupMeestSecrets(SymfonyStyle $io, string $environment, bool $force): void
    {
        $secrets = [
            'MEEST_USERNAME' => 'MEEST API Username',
            'MEEST_PASSWORD' => 'MEEST API Password',
            'MEEST_BASE_URL' => 'MEEST API Base URL'
        ];

        $defaultValues = [
            'MEEST_BASE_URL' => $environment === 'prod'
                ? 'https://mwl.meest.com/mwl'
                : 'https://mwl-stage.meest.com/mwl'
        ];

        $io->section("Setting up MEEST secrets for {$environment} environment");

        foreach ($secrets as $secretKey => $description) {
            $this->setupSecret($io, $secretKey, $description, $force, $defaultValues[$secretKey] ?? null);
        }

        // Verify the setup
        $this->verifySetup($io);
    }

    private function setupSecret(
        SymfonyStyle $io,
        string $secretKey,
        string $description,
        bool $force,
        ?string $defaultValue = null
    ): void {
        $existingValue = null;

        try {
            $existingValue = $this->secretsManager->getSecret($secretKey);
        } catch (\Exception) {
            // Secret doesn't exist
        }

        if ($existingValue && !$force) {
            $io->text("✓ {$secretKey} already exists (use --force to update)");
            return;
        }

        $helper = $this->getHelper('question');

        $questionText = $description;
        if ($defaultValue) {
            $questionText .= " (default: {$defaultValue})";
        }
        if ($existingValue && $force) {
            $questionText .= " (current: " . substr($existingValue, 0, 10) . "...)";
        }

        $question = new Question($questionText . ': ', $defaultValue);

        if ($secretKey === 'MEEST_PASSWORD') {
            $question->setHidden(true);
            $question->setHiddenFallback(false);
        }

        $value = $helper->ask($this->input ?? null, $this->output ?? null, $question);

        if (empty($value)) {
            if ($defaultValue) {
                $value = $defaultValue;
            } else {
                throw new \InvalidArgumentException("Value for {$secretKey} cannot be empty");
            }
        }

        $this->secretsManager->storeSecret($secretKey, $value, 'MEEST API configuration');
        $io->text("✓ {$secretKey} configured");
    }

    private function verifySetup(SymfonyStyle $io): void
    {
        $io->section('Verifying Configuration');

        $requiredSecrets = ['MEEST_USERNAME', 'MEEST_PASSWORD', 'MEEST_BASE_URL'];
        $allConfigured = true;

        foreach ($requiredSecrets as $secret) {
            try {
                $value = $this->secretsManager->getSecret($secret);
                if (empty($value)) {
                    $io->error("✗ {$secret} is empty");
                    $allConfigured = false;
                } else {
                    $displayValue = $secret === 'MEEST_PASSWORD'
                        ? str_repeat('*', 8)
                        : $value;
                    $io->text("✓ {$secret}: {$displayValue}");
                }
            } catch (\Exception $e) {
                $io->error("✗ {$secret} not found: {$e->getMessage()}");
                $allConfigured = false;
            }
        }

        if ($allConfigured) {
            $io->success('All MEEST secrets are properly configured');

            $io->section('Next Steps');
            $io->listing([
                'Test authentication: php bin/console meest:test auth',
                'Create test shipment: php bin/console meest:test create',
                'Update tracking status: php bin/console meest:tracking:update',
                'View statistics: php bin/console meest:tracking:update --stats'
            ]);
        } else {
            throw new \RuntimeException('Configuration verification failed');
        }
    }
}