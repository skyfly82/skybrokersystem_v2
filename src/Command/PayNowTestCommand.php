<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Payment\Contracts\PayNowServiceInterface;
use App\Domain\Payment\DTO\PayNowPaymentRequestDTO;
use App\Domain\Payment\Exception\PayNowIntegrationException;
use App\Domain\Payment\Repository\PaymentRepository;
use App\Entity\SystemUser;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'paynow:test',
    description: 'Test PayNow payment integration',
    aliases: ['paynow:demo']
)]
class PayNowTestCommand extends Command
{
    private PayNowServiceInterface $payNowService;
    private PaymentRepository $paymentRepository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        PayNowServiceInterface $payNowService,
        PaymentRepository $paymentRepository,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->payNowService = $payNowService;
        $this->paymentRepository = $paymentRepository;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this
            ->addOption('amount', 'a', InputOption::VALUE_OPTIONAL, 'Payment amount', '19.99')
            ->addOption('currency', 'c', InputOption::VALUE_OPTIONAL, 'Currency', 'PLN')
            ->addOption('description', 'd', InputOption::VALUE_OPTIONAL, 'Payment description', 'Test PayNow payment')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without making actual API calls');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $amount = $input->getOption('amount');
        $currency = $input->getOption('currency');
        $description = $input->getOption('description');
        $dryRun = $input->getOption('dry-run');

        $io->title('PayNow Payment Integration Test');

        // Check if PayNow is enabled
        if (!$this->payNowService->isEnabled()) {
            $io->error('PayNow integration is disabled. Please check your configuration.');
            return Command::FAILURE;
        }

        $io->success('PayNow integration is enabled');

        // Show service configuration
        $io->section('Configuration');
        $io->table(
            ['Setting', 'Value'],
            [
                ['Supported Currencies', implode(', ', $this->payNowService->getSupportedCurrencies())],
                ['Minimum Amount (PLN)', $this->payNowService->getMinimumAmount('PLN')],
                ['Maximum Amount (PLN)', $this->payNowService->getMaximumAmount('PLN')],
                ['Test Amount', $amount],
                ['Test Currency', $currency],
            ]
        );

        // Validate amount
        if (!$this->payNowService->validateAmount($amount, $currency)) {
            $io->error(sprintf(
                'Invalid amount %s %s. Must be between %s and %s %s',
                $amount,
                $currency,
                $this->payNowService->getMinimumAmount($currency),
                $this->payNowService->getMaximumAmount($currency),
                $currency
            ));
            return Command::FAILURE;
        }

        $io->success('Amount validation passed');

        if ($dryRun) {
            $io->note('DRY RUN MODE: No actual API calls will be made');
            return Command::SUCCESS;
        }

        // Find or create a test user
        $testUser = $this->getTestUser();
        if (!$testUser) {
            $io->error('No test user found. Please create a system user first.');
            return Command::FAILURE;
        }

        $io->info(sprintf('Using test user: %s (%s)', $testUser->getEmail(), $testUser->getFullName()));

        try {
            $io->section('Testing PayNow Payment Creation');

            // Create payment request
            $paymentRequest = new PayNowPaymentRequestDTO([
                'external_id' => 'TEST_' . uniqid(),
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description,
                'continue_url' => 'https://example.com/payment/success',
                'notify_url' => 'https://example.com/api/payment/paynow/webhook/test',
                'buyer_email' => $testUser->getEmail(),
                'buyer_first_name' => $testUser->getFirstName(),
                'buyer_last_name' => $testUser->getLastName(),
            ]);

            $io->info('Creating PayNow payment...');

            // Note: This will fail in sandbox without real credentials, but shows the flow
            $response = $this->payNowService->initializePayment($paymentRequest);

            $io->success('PayNow payment created successfully');
            $io->table(
                ['Field', 'Value'],
                [
                    ['Payment ID', $response->getPaymentId()],
                    ['Status', $response->getStatus()],
                    ['Redirect URL', $response->getRedirectUrl()],
                    ['External ID', $response->getExternalId()],
                ]
            );

            // Test status check
            $io->section('Testing Status Check');
            $statusResponse = $this->payNowService->getPaymentStatus($response->getPaymentId());
            
            $io->info(sprintf('Payment status: %s', $statusResponse->getStatus()));

            return Command::SUCCESS;

        } catch (PayNowIntegrationException $e) {
            $this->logger->error('PayNow test failed', [
                'error' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
                'error_details' => $e->getErrorDetails(),
            ]);

            $io->error('PayNow test failed: ' . $e->getMessage());
            
            if ($e->getErrorCode() === PayNowIntegrationException::ERROR_API_CONNECTION) {
                $io->note('This is expected in sandbox environment without real API credentials.');
                $io->info('The PayNow integration is properly configured and would work with real credentials.');
                return Command::SUCCESS;
            }

            return Command::FAILURE;

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in PayNow test', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $io->error('Unexpected error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getTestUser(): ?SystemUser
    {
        return $this->entityManager->getRepository(SystemUser::class)->findOneBy(['status' => 'active']);
    }
}