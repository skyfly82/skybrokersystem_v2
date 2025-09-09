<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Payment\Service\PayNowPaymentHandler;
use App\Domain\Payment\Repository\PaymentRepository;
use App\Domain\Payment\Exception\PayNowIntegrationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'paynow:update-status',
    description: 'Update status of pending PayNow payments',
    aliases: ['paynow:status']
)]
class PayNowUpdateStatusCommand extends Command
{
    private PayNowPaymentHandler $paymentHandler;
    private PaymentRepository $paymentRepository;
    private LoggerInterface $logger;

    public function __construct(
        PayNowPaymentHandler $paymentHandler,
        PaymentRepository $paymentRepository,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->paymentHandler = $paymentHandler;
        $this->paymentRepository = $paymentRepository;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Maximum number of payments to process', 50)
            ->addOption('payment-id', 'p', InputOption::VALUE_OPTIONAL, 'Update specific payment by ID')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Show what would be updated without making changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');
        $paymentId = $input->getOption('payment-id');
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->warning('DRY RUN MODE: No actual updates will be made');
        }

        $io->title('PayNow Payment Status Update');

        try {
            if ($paymentId) {
                return $this->updateSinglePayment($io, $paymentId, $dryRun);
            } else {
                return $this->updatePendingPayments($io, $limit, $dryRun);
            }
        } catch (\Exception $e) {
            $this->logger->error('PayNow status update command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $io->error('Command failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function updateSinglePayment(SymfonyStyle $io, string $paymentId, bool $dryRun): int
    {
        $io->section('Updating single payment');

        $payment = $this->paymentRepository->findByPaymentId($paymentId);

        if (!$payment) {
            $io->error(sprintf('Payment not found: %s', $paymentId));
            return Command::FAILURE;
        }

        if (!$payment->isPayNow()) {
            $io->error(sprintf('Payment %s is not a PayNow payment', $paymentId));
            return Command::FAILURE;
        }

        $io->info(sprintf('Found payment: %s (Status: %s)', $paymentId, $payment->getStatus()));

        if ($dryRun) {
            $io->note('Dry run: Would update payment status');
            return Command::SUCCESS;
        }

        try {
            $updatedPayment = $this->paymentHandler->updatePaymentStatus($payment);
            
            $io->success(sprintf(
                'Payment %s updated successfully: %s -> %s',
                $paymentId,
                $payment->getStatus(),
                $updatedPayment->getStatus()
            ));

            return Command::SUCCESS;

        } catch (PayNowIntegrationException $e) {
            $this->logger->error('Failed to update single payment', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ]);

            $io->error(sprintf('Failed to update payment %s: %s', $paymentId, $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function updatePendingPayments(SymfonyStyle $io, int $limit, bool $dryRun): int
    {
        $io->section('Updating pending PayNow payments');

        $pendingPayments = $this->paymentRepository->findPendingPayNowPayments($limit);

        if (empty($pendingPayments)) {
            $io->success('No pending PayNow payments found');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d pending PayNow payments', count($pendingPayments)));

        if ($dryRun) {
            $io->table(
                ['Payment ID', 'Status', 'Amount', 'Currency', 'Created At'],
                array_map(function ($payment) {
                    return [
                        $payment->getPaymentId(),
                        $payment->getStatus(),
                        $payment->getAmount(),
                        $payment->getCurrency(),
                        $payment->getCreatedAt()->format('Y-m-d H:i:s'),
                    ];
                }, $pendingPayments)
            );

            $io->note('Dry run: Would update these payment statuses');
            return Command::SUCCESS;
        }

        $updated = 0;
        $failed = 0;
        $progressBar = $io->createProgressBar(count($pendingPayments));

        foreach ($pendingPayments as $payment) {
            $progressBar->advance();

            try {
                $oldStatus = $payment->getStatus();
                $updatedPayment = $this->paymentHandler->updatePaymentStatus($payment);
                
                if ($oldStatus !== $updatedPayment->getStatus()) {
                    $updated++;
                    $io->writeln('');
                    $io->info(sprintf(
                        'Payment %s: %s -> %s',
                        $payment->getPaymentId(),
                        $oldStatus,
                        $updatedPayment->getStatus()
                    ));
                }

            } catch (PayNowIntegrationException $e) {
                $failed++;
                $this->logger->error('Failed to update payment status', [
                    'payment_id' => $payment->getPaymentId(),
                    'error' => $e->getMessage(),
                    'error_code' => $e->getErrorCode(),
                ]);

                $io->writeln('');
                $io->warning(sprintf(
                    'Failed to update payment %s: %s',
                    $payment->getPaymentId(),
                    $e->getMessage()
                ));
            }
        }

        $progressBar->finish();
        $io->writeln('');

        $io->success(sprintf(
            'Status update completed: %d updated, %d failed, %d processed',
            $updated,
            $failed,
            count($pendingPayments)
        ));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}