<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Payment\Service\PaymentHandler;
use App\Domain\Payment\Service\CreditPaymentHandler;
use App\Domain\Payment\Service\WalletPaymentHandler;
use App\Domain\Payment\Entity\Payment;
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
    name: 'payment:test-system',
    description: 'Test all payment systems (Credit, Wallet, PayNow, Simulator)',
    aliases: ['payment:test']
)]
class PaymentSystemTestCommand extends Command
{
    private PaymentHandler $paymentHandler;
    private CreditPaymentHandler $creditHandler;
    private WalletPaymentHandler $walletHandler;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        PaymentHandler $paymentHandler,
        CreditPaymentHandler $creditHandler,
        WalletPaymentHandler $walletHandler,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->paymentHandler = $paymentHandler;
        $this->creditHandler = $creditHandler;
        $this->walletHandler = $walletHandler;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this
            ->addOption('method', 'm', InputOption::VALUE_OPTIONAL, 'Test specific payment method', 'all')
            ->addOption('amount', 'a', InputOption::VALUE_OPTIONAL, 'Payment amount', '25.00')
            ->addOption('currency', 'c', InputOption::VALUE_OPTIONAL, 'Currency', 'PLN')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without making actual changes')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $method = $input->getOption('method');
        $amount = $input->getOption('amount');
        $currency = $input->getOption('currency');
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->warning('DRY RUN MODE: No actual payments will be created');
        }

        $io->title('Payment System Test Suite');
        
        // Find test user
        $user = $this->entityManager->getRepository(SystemUser::class)->find(1);
        if (!$user) {
            $io->error('Test user with ID 1 not found');
            return Command::FAILURE;
        }

        $io->section("Test Configuration");
        $io->table(
            ['Setting', 'Value'],
            [
                ['Test User', sprintf('#%d - %s', $user->getId(), $user->getEmail())],
                ['Amount', $amount . ' ' . $currency],
                ['Method', $method],
                ['Dry Run', $dryRun ? 'Yes' : 'No'],
            ]
        );

        $results = [];
        $methods = $method === 'all' ? ['credit', 'wallet', 'simulator'] : [$method];

        foreach ($methods as $paymentMethod) {
            $io->section("Testing {$paymentMethod} Payment");
            
            try {
                $result = $this->testPaymentMethod($paymentMethod, $user, $amount, $currency, $dryRun, $io);
                $results[$paymentMethod] = $result;
                
                if ($result['success']) {
                    $io->success("{$paymentMethod} payment test passed");
                } else {
                    $io->error("{$paymentMethod} payment test failed: " . $result['error']);
                }
                
            } catch (\Exception $e) {
                $results[$paymentMethod] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'payment_id' => null
                ];
                $io->error("{$paymentMethod} payment test failed: " . $e->getMessage());
            }
        }

        // Summary
        $io->section('Test Results Summary');
        $summaryRows = [];
        foreach ($results as $methodName => $result) {
            $summaryRows[] = [
                ucfirst($methodName),
                $result['success'] ? '✅ PASSED' : '❌ FAILED',
                $result['payment_id'] ?? 'N/A',
                $result['error'] ?? 'None'
            ];
        }
        
        $io->table(
            ['Payment Method', 'Status', 'Payment ID', 'Error'],
            $summaryRows
        );

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $totalCount = count($results);
        
        if ($successCount === $totalCount) {
            $io->success("All payment methods tested successfully ({$successCount}/{$totalCount})");
            return Command::SUCCESS;
        } else {
            $io->warning("Some payment methods failed ({$successCount}/{$totalCount} passed)");
            return Command::FAILURE;
        }
    }

    private function testPaymentMethod(string $method, SystemUser $user, string $amount, string $currency, bool $dryRun, SymfonyStyle $io): array
    {
        $io->info("Creating {$method} payment for {$amount} {$currency}");

        if ($dryRun) {
            return [
                'success' => true,
                'payment_id' => 'DRY_RUN_' . strtoupper($method) . '_' . time(),
                'message' => 'Dry run - no actual payment created'
            ];
        }

        switch ($method) {
            case 'credit':
                return $this->testCreditPayment($user, $amount, $currency, $io);
                
            case 'wallet':
                return $this->testWalletPayment($user, $amount, $currency, $io);
                
            case 'simulator':
                return $this->testSimulatorPayment($user, $amount, $currency, $io);
                
            default:
                throw new \InvalidArgumentException("Unsupported payment method: {$method}");
        }
    }

    private function testCreditPayment(SystemUser $user, string $amount, string $currency, SymfonyStyle $io): array
    {
        try {
            $io->text('Creating credit payment...');
            $result = $this->creditHandler->createPayment($user, $amount, $currency, 'Test credit payment from command');
            
            $paymentId = $result['payment_id'];
            $io->text("Payment created with ID: {$paymentId}");
            
            // Test status check
            $io->text('Checking payment status...');
            $status = $this->paymentHandler->getPaymentStatus($paymentId);
            
            if (!$status) {
                return [
                    'success' => false,
                    'error' => 'Could not retrieve payment status',
                    'payment_id' => $paymentId
                ];
            }

            $io->text("Current status: {$status['status']}");

            // Try to settle the payment
            if ($status['status'] === 'processing') {
                $io->text('Settling credit payment...');
                $settleResult = $this->creditHandler->settlePayment($paymentId);
                $io->text("Payment settled successfully");
            }

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'status' => $status['status'],
                'message' => 'Credit payment test completed successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_id' => null
            ];
        }
    }

    private function testWalletPayment(SystemUser $user, string $amount, string $currency, SymfonyStyle $io): array
    {
        try {
            $io->text('Creating wallet payment...');
            
            // For now, wallet payment is not fully implemented
            // This is a placeholder that demonstrates the structure
            $io->text('Wallet payment system is not yet fully implemented');
            
            return [
                'success' => false,
                'error' => 'Wallet payment system not yet implemented',
                'payment_id' => null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_id' => null
            ];
        }
    }

    private function testSimulatorPayment(SystemUser $user, string $amount, string $currency, SymfonyStyle $io): array
    {
        try {
            $io->text('Creating simulator payment...');
            
            // Create a mock payment for simulator
            $payment = new Payment();
            $payment->setUser($user)
                ->setPaymentMethod(Payment::METHOD_SIMULATOR)
                ->setAmount($amount)
                ->setCurrency($currency)
                ->setDescription('Test simulator payment from command')
                ->setStatus(Payment::STATUS_PROCESSING);

            $this->entityManager->persist($payment);
            $this->entityManager->flush();

            $paymentId = $payment->getPaymentId();
            $io->text("Simulator payment created with ID: {$paymentId}");

            // Simulate completion
            $payment->setStatus(Payment::STATUS_COMPLETED);
            $payment->setGatewayResponse([
                'simulator' => true,
                'completed_at' => (new \DateTimeImmutable())->format('c'),
                'amount' => $amount,
                'currency' => $currency
            ]);

            $this->entityManager->flush();
            $io->text("Simulator payment completed successfully");

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'status' => 'completed',
                'message' => 'Simulator payment test completed successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_id' => null
            ];
        }
    }
}