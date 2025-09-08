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
    name: 'inpost:setup-credentials',
    description: 'Set up InPost API credentials for sandbox and production'
)]
class InPostCredentialsSetupCommand extends Command
{
    public function __construct(
        private readonly CourierSecretsService $courierSecretsService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('sandbox-key', null, InputOption::VALUE_OPTIONAL, 'InPost sandbox API key')
            ->addOption('production-key', null, InputOption::VALUE_OPTIONAL, 'InPost production API key')
            ->addOption('generate-webhook-token', null, InputOption::VALUE_NONE, 'Generate webhook token for InPost')
            ->setHelp('
Set up InPost API credentials and webhook tokens.

Examples:
  php bin/console inpost:setup-credentials --sandbox-key=your_sandbox_key
  php bin/console inpost:setup-credentials --generate-webhook-token
  php bin/console inpost:setup-credentials --sandbox-key=key --production-key=prod_key

Note: For actual integration, you need to:
1. Register at https://manager.paczkomaty.pl/
2. Get API credentials from InPost
3. Configure webhook URLs in InPost dashboard
');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('InPost Credentials Setup');

        // Set up sandbox API key
        $sandboxKey = $input->getOption('sandbox-key');
        if ($sandboxKey) {
            $this->courierSecretsService->setInpostApiKey($sandboxKey, 'sandbox');
            $io->success('Sandbox API key configured successfully');
        } else {
            // Set placeholder for sandbox
            $this->courierSecretsService->setInpostApiKey('REPLACE_WITH_ACTUAL_INPOST_SANDBOX_API_KEY', 'sandbox');
            $io->warning('Sandbox API key set to placeholder. Replace with actual key from InPost.');
        }

        // Set up production API key
        $productionKey = $input->getOption('production-key');
        if ($productionKey) {
            $this->courierSecretsService->setInpostApiKey($productionKey, 'production');
            $io->success('Production API key configured successfully');
        } else {
            $io->note('Production API key not configured. Use --production-key option when ready.');
        }

        // Generate webhook token
        if ($input->getOption('generate-webhook-token')) {
            $webhookToken = $this->courierSecretsService->generateWebhookToken('inpost');
            $io->success("Webhook token generated: {$webhookToken}");
            $io->note('Configure this token in your InPost webhook settings.');
        }

        // Show current configuration
        $io->section('Current InPost Configuration');
        
        $sandboxConfigured = $this->courierSecretsService->getInpostApiKey('sandbox') !== null;
        $productionConfigured = $this->courierSecretsService->getInpostApiKey('production') !== null;
        $webhookConfigured = $this->courierSecretsService->getWebhookToken('inpost') !== null;
        
        $io->table(['Environment', 'Status'], [
            ['Sandbox API Key', $sandboxConfigured ? '✓ Configured' : '✗ Missing'],
            ['Production API Key', $productionConfigured ? '✓ Configured' : '✗ Missing'],
            ['Webhook Token', $webhookConfigured ? '✓ Configured' : '✗ Missing'],
        ]);

        if (!$sandboxKey && !$productionKey) {
            $io->section('Next Steps');
            $io->listing([
                'Register at InPost: https://manager.paczkomaty.pl/',
                'Get your API credentials from InPost dashboard',
                'Run: php bin/console inpost:setup-credentials --sandbox-key=YOUR_KEY',
                'Configure webhook URL in InPost: https://yourdomain.com/api/webhooks/inpost',
                'Run: php bin/console inpost:setup-credentials --generate-webhook-token',
            ]);
        }

        return Command::SUCCESS;
    }
}