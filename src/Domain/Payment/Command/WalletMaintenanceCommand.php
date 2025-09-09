<?php

declare(strict_types=1);

namespace App\Domain\Payment\Command;

use App\Domain\Payment\Repository\WalletRepository;
use App\Domain\Payment\Repository\WalletTopUpRepository;
use App\Domain\Payment\Entity\WalletTopUp;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'wallet:maintenance',
    description: 'Perform wallet maintenance tasks (expire top-ups, check low balances, clean up)'
)]
class WalletMaintenanceCommand extends Command
{
    public function __construct(
        private readonly WalletRepository $walletRepository,
        private readonly WalletTopUpRepository $walletTopUpRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('expire-topups', null, InputOption::VALUE_NONE, 'Mark expired top-ups as expired')
            ->addOption('check-low-balances', null, InputOption::VALUE_NONE, 'Check for wallets with low balances')
            ->addOption('cleanup-inactive', null, InputOption::VALUE_OPTIONAL, 'Clean up inactive wallets (days)', 365)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without making changes')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of records to process', 1000);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');

        $io->title('Wallet Maintenance');

        if ($isDryRun) {
            $io->note('Running in DRY RUN mode - no changes will be made');
        }

        $totalProcessed = 0;

        // Expire top-ups
        if ($input->getOption('expire-topups')) {
            $processed = $this->expireTopUps($io, $isDryRun, (int)$input->getOption('limit'));
            $totalProcessed += $processed;
        }

        // Check low balances
        if ($input->getOption('check-low-balances')) {
            $processed = $this->checkLowBalances($io, $isDryRun, (int)$input->getOption('limit'));
            $totalProcessed += $processed;
        }

        // Clean up inactive wallets
        $inactiveDays = $input->getOption('cleanup-inactive');
        if ($inactiveDays !== null) {
            $processed = $this->cleanupInactiveWallets($io, $isDryRun, (int)$inactiveDays, (int)$input->getOption('limit'));
            $totalProcessed += $processed;
        }

        // If no specific options provided, run all tasks
        if (!$input->getOption('expire-topups') && 
            !$input->getOption('check-low-balances') && 
            $input->getOption('cleanup-inactive') === null) {
            
            $io->section('Running all maintenance tasks...');
            
            $totalProcessed += $this->expireTopUps($io, $isDryRun, (int)$input->getOption('limit'));
            $totalProcessed += $this->checkLowBalances($io, $isDryRun, (int)$input->getOption('limit'));
            $totalProcessed += $this->cleanupInactiveWallets($io, $isDryRun, 365, (int)$input->getOption('limit'));
        }

        $io->success(sprintf('Wallet maintenance completed. Total records processed: %d', $totalProcessed));

        return Command::SUCCESS;
    }

    private function expireTopUps(SymfonyStyle $io, bool $isDryRun, int $limit): int
    {
        $io->section('Expiring Top-Ups');

        try {
            $expiredTopUps = $this->walletTopUpRepository->findExpiredTopUps();
            $processed = 0;

            foreach (array_slice($expiredTopUps, 0, $limit) as $topUp) {
                /** @var WalletTopUp $topUp */
                if ($isDryRun) {
                    $io->writeln(sprintf(
                        'Would expire top-up: %s (Amount: %s %s, Expired: %s)',
                        $topUp->getTopUpId(),
                        $topUp->getAmount(),
                        $topUp->getCurrency(),
                        $topUp->getExpiresAt()?->format('Y-m-d H:i:s')
                    ));
                } else {
                    $topUp->markAsExpired();
                    $this->walletTopUpRepository->save($topUp, false);
                    
                    $this->logger->info('Top-up marked as expired', [
                        'top_up_id' => $topUp->getTopUpId(),
                        'wallet_number' => $topUp->getWallet()->getWalletNumber()
                    ]);
                }
                $processed++;
            }

            if (!$isDryRun && $processed > 0) {
                $this->entityManager->flush();
            }

            $io->info(sprintf('Processed %d expired top-ups', $processed));
            return $processed;

        } catch (\Exception $e) {
            $io->error('Failed to expire top-ups: ' . $e->getMessage());
            $this->logger->error('Failed to expire top-ups', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private function checkLowBalances(SymfonyStyle $io, bool $isDryRun, int $limit): int
    {
        $io->section('Checking Low Balances');

        try {
            $lowBalanceWallets = $this->walletRepository->findWalletsWithLowBalance();
            $processed = 0;

            foreach (array_slice($lowBalanceWallets, 0, $limit) as $wallet) {
                if ($isDryRun) {
                    $io->writeln(sprintf(
                        'Would send low balance notification: %s (Balance: %s %s, Threshold: %s %s)',
                        $wallet->getWalletNumber(),
                        $wallet->getAvailableBalance(),
                        $wallet->getCurrency(),
                        $wallet->getLowBalanceThreshold(),
                        $wallet->getCurrency()
                    ));
                } else {
                    // Mark notification as sent to prevent repeated notifications
                    $wallet->setLowBalanceNotificationSent(true);
                    $this->walletRepository->save($wallet, false);
                    
                    // Here you would typically trigger a notification event
                    // For example: $this->eventDispatcher->dispatch(new LowBalanceNotificationEvent($wallet));
                    
                    $this->logger->info('Low balance notification triggered', [
                        'wallet_number' => $wallet->getWalletNumber(),
                        'user_id' => $wallet->getUser()->getId(),
                        'balance' => $wallet->getAvailableBalance(),
                        'threshold' => $wallet->getLowBalanceThreshold()
                    ]);
                }
                $processed++;
            }

            if (!$isDryRun && $processed > 0) {
                $this->entityManager->flush();
            }

            $io->info(sprintf('Processed %d low balance wallets', $processed));
            return $processed;

        } catch (\Exception $e) {
            $io->error('Failed to check low balances: ' . $e->getMessage());
            $this->logger->error('Failed to check low balances', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private function cleanupInactiveWallets(SymfonyStyle $io, bool $isDryRun, int $inactiveDays, int $limit): int
    {
        $io->section(sprintf('Cleaning Up Inactive Wallets (%d+ days)', $inactiveDays));

        try {
            $inactiveWallets = $this->walletRepository->findInactiveWallets($inactiveDays);
            $processed = 0;

            foreach (array_slice($inactiveWallets, 0, $limit) as $wallet) {
                $daysSinceLastTransaction = $wallet->getDaysSinceLastTransaction();
                
                if ($isDryRun) {
                    $io->writeln(sprintf(
                        'Would process inactive wallet: %s (Days inactive: %d, Balance: %s %s)',
                        $wallet->getWalletNumber(),
                        $daysSinceLastTransaction,
                        $wallet->getBalance(),
                        $wallet->getCurrency()
                    ));
                } else {
                    // For now, just log the inactive wallet
                    // In a real implementation, you might want to:
                    // - Send inactivity notifications
                    // - Freeze wallets with zero balance
                    // - Archive old wallet data
                    
                    $this->logger->info('Inactive wallet detected', [
                        'wallet_number' => $wallet->getWalletNumber(),
                        'user_id' => $wallet->getUser()->getId(),
                        'days_inactive' => $daysSinceLastTransaction,
                        'balance' => $wallet->getBalance(),
                        'last_transaction_at' => $wallet->getLastTransactionAt()?->format('Y-m-d H:i:s')
                    ]);
                }
                $processed++;
            }

            $io->info(sprintf('Processed %d inactive wallets', $processed));
            return $processed;

        } catch (\Exception $e) {
            $io->error('Failed to cleanup inactive wallets: ' . $e->getMessage());
            $this->logger->error('Failed to cleanup inactive wallets', ['error' => $e->getMessage()]);
            return 0;
        }
    }
}