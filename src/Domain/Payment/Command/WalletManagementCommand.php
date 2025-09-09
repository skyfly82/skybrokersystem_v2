<?php

declare(strict_types=1);

namespace App\Domain\Payment\Command;

use App\Domain\Payment\Contracts\WalletServiceInterface;
use App\Domain\Payment\Exception\WalletException;
use App\Domain\Payment\Repository\WalletRepository;
use App\Domain\Payment\Repository\WalletTransactionRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'wallet:manage',
    description: 'Manage wallet operations (create, freeze, unfreeze, suspend, close, adjust balance)'
)]
class WalletManagementCommand extends Command
{
    public function __construct(
        private readonly WalletServiceInterface $walletService,
        private readonly WalletRepository $walletRepository,
        private readonly WalletTransactionRepository $walletTransactionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform (create, freeze, unfreeze, suspend, close, adjust, status, stats)')
            ->addOption('user-id', 'u', InputOption::VALUE_OPTIONAL, 'User ID')
            ->addOption('wallet-number', 'w', InputOption::VALUE_OPTIONAL, 'Wallet number')
            ->addOption('amount', 'a', InputOption::VALUE_OPTIONAL, 'Amount for balance adjustment')
            ->addOption('currency', 'c', InputOption::VALUE_OPTIONAL, 'Currency', 'PLN')
            ->addOption('reason', 'r', InputOption::VALUE_OPTIONAL, 'Reason for action')
            ->addOption('daily-limit', null, InputOption::VALUE_OPTIONAL, 'Daily transaction limit')
            ->addOption('monthly-limit', null, InputOption::VALUE_OPTIONAL, 'Monthly transaction limit')
            ->addOption('low-balance-threshold', null, InputOption::VALUE_OPTIONAL, 'Low balance threshold')
            ->addOption('adjustment-type', null, InputOption::VALUE_OPTIONAL, 'Adjustment type (credit, debit)', 'credit')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force action without confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        try {
            return match ($action) {
                'create' => $this->createWallet($input, $io),
                'freeze' => $this->freezeWallet($input, $io),
                'unfreeze' => $this->unfreezeWallet($input, $io),
                'suspend' => $this->suspendWallet($input, $io),
                'close' => $this->closeWallet($input, $io),
                'adjust' => $this->adjustBalance($input, $io),
                'status' => $this->getWalletStatus($input, $io),
                'stats' => $this->getWalletStats($input, $io),
                'list' => $this->listWallets($input, $io),
                default => $this->showHelp($io)
            };
        } catch (\Exception $e) {
            $io->error('Command failed: ' . $e->getMessage());
            $this->logger->error('Wallet management command failed', [
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            return Command::FAILURE;
        }
    }

    private function createWallet(InputInterface $input, SymfonyStyle $io): int
    {
        $userId = $input->getOption('user-id');
        if (!$userId) {
            $io->error('User ID is required for wallet creation');
            return Command::FAILURE;
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            $io->error('User not found');
            return Command::FAILURE;
        }

        $currency = $input->getOption('currency');
        $dailyLimit = $input->getOption('daily-limit');
        $monthlyLimit = $input->getOption('monthly-limit');
        $lowBalanceThreshold = $input->getOption('low-balance-threshold');

        try {
            $wallet = $this->walletService->createWallet(
                $user,
                $currency,
                $dailyLimit,
                $monthlyLimit,
                $lowBalanceThreshold
            );

            $io->success(sprintf(
                'Wallet created successfully: %s for user %d',
                $wallet->getWalletNumber(),
                $user->getId()
            ));

            $this->displayWalletInfo($io, $wallet);

            return Command::SUCCESS;
        } catch (WalletException $e) {
            $io->error('Failed to create wallet: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function freezeWallet(InputInterface $input, SymfonyStyle $io): int
    {
        $wallet = $this->getWalletFromInput($input, $io);
        if (!$wallet) {
            return Command::FAILURE;
        }

        $reason = $input->getOption('reason') ?? 'Manual freeze via command';

        if (!$input->getOption('force')) {
            if (!$io->confirm(sprintf('Are you sure you want to freeze wallet %s?', $wallet->getWalletNumber()))) {
                $io->note('Operation cancelled');
                return Command::SUCCESS;
            }
        }

        try {
            $wallet = $this->walletService->freezeWallet($wallet->getUser(), $reason);
            $io->success(sprintf('Wallet %s frozen successfully', $wallet->getWalletNumber()));
            return Command::SUCCESS;
        } catch (WalletException $e) {
            $io->error('Failed to freeze wallet: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function unfreezeWallet(InputInterface $input, SymfonyStyle $io): int
    {
        $wallet = $this->getWalletFromInput($input, $io);
        if (!$wallet) {
            return Command::FAILURE;
        }

        if (!$input->getOption('force')) {
            if (!$io->confirm(sprintf('Are you sure you want to unfreeze wallet %s?', $wallet->getWalletNumber()))) {
                $io->note('Operation cancelled');
                return Command::SUCCESS;
            }
        }

        try {
            $wallet = $this->walletService->unfreezeWallet($wallet->getUser());
            $io->success(sprintf('Wallet %s unfrozen successfully', $wallet->getWalletNumber()));
            return Command::SUCCESS;
        } catch (WalletException $e) {
            $io->error('Failed to unfreeze wallet: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function suspendWallet(InputInterface $input, SymfonyStyle $io): int
    {
        $wallet = $this->getWalletFromInput($input, $io);
        if (!$wallet) {
            return Command::FAILURE;
        }

        $reason = $input->getOption('reason') ?? 'Manual suspension via command';

        if (!$input->getOption('force')) {
            if (!$io->confirm(sprintf('Are you sure you want to suspend wallet %s?', $wallet->getWalletNumber()))) {
                $io->note('Operation cancelled');
                return Command::SUCCESS;
            }
        }

        try {
            $wallet = $this->walletService->suspendWallet($wallet->getUser(), $reason);
            $io->success(sprintf('Wallet %s suspended successfully', $wallet->getWalletNumber()));
            return Command::SUCCESS;
        } catch (WalletException $e) {
            $io->error('Failed to suspend wallet: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function closeWallet(InputInterface $input, SymfonyStyle $io): int
    {
        $wallet = $this->getWalletFromInput($input, $io);
        if (!$wallet) {
            return Command::FAILURE;
        }

        $reason = $input->getOption('reason') ?? 'Manual closure via command';

        $io->warning([
            'CAUTION: Closing a wallet is irreversible!',
            'The wallet must have zero balance to be closed.'
        ]);

        if (!$input->getOption('force')) {
            if (!$io->confirm(sprintf('Are you sure you want to PERMANENTLY close wallet %s?', $wallet->getWalletNumber()))) {
                $io->note('Operation cancelled');
                return Command::SUCCESS;
            }
        }

        try {
            $wallet = $this->walletService->closeWallet($wallet->getUser(), $reason);
            $io->success(sprintf('Wallet %s closed successfully', $wallet->getWalletNumber()));
            return Command::SUCCESS;
        } catch (WalletException $e) {
            $io->error('Failed to close wallet: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function adjustBalance(InputInterface $input, SymfonyStyle $io): int
    {
        $wallet = $this->getWalletFromInput($input, $io);
        if (!$wallet) {
            return Command::FAILURE;
        }

        $amount = $input->getOption('amount');
        if (!$amount) {
            $io->error('Amount is required for balance adjustment');
            return Command::FAILURE;
        }

        $adjustmentType = $input->getOption('adjustment-type');
        $reason = $input->getOption('reason') ?? 'Manual balance adjustment via command';

        if (!$this->walletService->validateAmount($amount, $wallet->getCurrency())) {
            $io->error('Invalid adjustment amount');
            return Command::FAILURE;
        }

        $io->warning([
            'CAUTION: This will directly adjust the wallet balance!',
            sprintf('Wallet: %s', $wallet->getWalletNumber()),
            sprintf('Current Balance: %s %s', $wallet->getBalance(), $wallet->getCurrency()),
            sprintf('Adjustment Type: %s', $adjustmentType),
            sprintf('Adjustment Amount: %s %s', $amount, $wallet->getCurrency()),
            sprintf('Reason: %s', $reason)
        ]);

        if (!$input->getOption('force')) {
            if (!$io->confirm('Proceed with balance adjustment?')) {
                $io->note('Operation cancelled');
                return Command::SUCCESS;
            }
        }

        try {
            $this->entityManager->beginTransaction();

            $balanceBefore = $wallet->getBalance();

            if ($adjustmentType === 'credit') {
                $wallet->creditBalance($amount);
            } else {
                if (!$wallet->debitBalance($amount)) {
                    $io->error('Insufficient balance for debit adjustment');
                    return Command::FAILURE;
                }
            }

            // Record the adjustment as a transaction
            $transaction = new \App\Domain\Payment\Entity\WalletTransaction();
            $transaction->setWallet($wallet)
                       ->setTransactionType(\App\Domain\Payment\Entity\WalletTransaction::TYPE_ADJUSTMENT)
                       ->setCategory(\App\Domain\Payment\Entity\WalletTransaction::CATEGORY_ADJUSTMENT)
                       ->setAmount($amount)
                       ->setCurrency($wallet->getCurrency())
                       ->setDescription($reason)
                       ->setBalanceBefore($balanceBefore)
                       ->setBalanceAfter($wallet->getBalance())
                       ->setStatus(\App\Domain\Payment\Entity\WalletTransaction::STATUS_COMPLETED);

            $this->walletTransactionRepository->save($transaction, false);
            $this->walletRepository->save($wallet, true);

            $this->entityManager->commit();

            $io->success([
                'Balance adjustment completed successfully',
                sprintf('Transaction ID: %s', $transaction->getTransactionId()),
                sprintf('New Balance: %s %s', $wallet->getBalance(), $wallet->getCurrency())
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $io->error('Failed to adjust balance: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getWalletStatus(InputInterface $input, SymfonyStyle $io): int
    {
        $wallet = $this->getWalletFromInput($input, $io);
        if (!$wallet) {
            return Command::FAILURE;
        }

        try {
            $status = $this->walletService->getWalletStatus($wallet->getUser());
            $this->displayWalletStatus($io, $status);
            return Command::SUCCESS;
        } catch (WalletException $e) {
            $io->error('Failed to get wallet status: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getWalletStats(InputInterface $input, SymfonyStyle $io): int
    {
        $stats = $this->walletRepository->getWalletStatistics();
        $this->displayWalletStatistics($io, $stats);
        return Command::SUCCESS;
    }

    private function listWallets(InputInterface $input, SymfonyStyle $io): int
    {
        $userId = $input->getOption('user-id');
        
        if ($userId) {
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if (!$user) {
                $io->error('User not found');
                return Command::FAILURE;
            }
            
            $wallet = $this->walletRepository->findByUser($user);
            if ($wallet) {
                $this->displayWalletInfo($io, $wallet);
            } else {
                $io->info('No wallet found for this user');
            }
        } else {
            // List all wallets (limited)
            $wallets = $this->walletRepository->findBy([], ['createdAt' => 'DESC'], 10);
            
            if (empty($wallets)) {
                $io->info('No wallets found');
                return Command::SUCCESS;
            }

            $table = new Table($output);
            $table->setHeaders(['Wallet Number', 'User ID', 'Status', 'Balance', 'Currency', 'Created']);

            foreach ($wallets as $wallet) {
                $table->addRow([
                    $wallet->getWalletNumber(),
                    $wallet->getUser()->getId(),
                    $wallet->getStatus(),
                    $wallet->getBalance(),
                    $wallet->getCurrency(),
                    $wallet->getCreatedAt()->format('Y-m-d H:i:s')
                ]);
            }

            $table->render();
        }

        return Command::SUCCESS;
    }

    private function showHelp(SymfonyStyle $io): int
    {
        $io->title('Wallet Management Command Help');
        
        $io->section('Available Actions:');
        $io->listing([
            'create - Create a new wallet for a user',
            'freeze - Freeze a wallet',
            'unfreeze - Unfreeze a wallet',
            'suspend - Suspend a wallet',
            'close - Close a wallet (irreversible)',
            'adjust - Adjust wallet balance (credit/debit)',
            'status - Show wallet status and details',
            'stats - Show system-wide wallet statistics',
            'list - List wallets'
        ]);

        $io->section('Examples:');
        $io->text([
            'Create wallet: <comment>php bin/console wallet:manage create --user-id=123 --currency=PLN</comment>',
            'Freeze wallet: <comment>php bin/console wallet:manage freeze --wallet-number=WAL_ABC123 --reason="Security concern"</comment>',
            'Adjust balance: <comment>php bin/console wallet:manage adjust --wallet-number=WAL_ABC123 --amount=100.00 --adjustment-type=credit</comment>',
            'Show status: <comment>php bin/console wallet:manage status --user-id=123</comment>'
        ]);

        return Command::SUCCESS;
    }

    private function getWalletFromInput(InputInterface $input, SymfonyStyle $io)
    {
        $walletNumber = $input->getOption('wallet-number');
        $userId = $input->getOption('user-id');

        if ($walletNumber) {
            $wallet = $this->walletRepository->findByWalletNumber($walletNumber);
            if (!$wallet) {
                $io->error('Wallet not found');
                return null;
            }
            return $wallet;
        } elseif ($userId) {
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if (!$user) {
                $io->error('User not found');
                return null;
            }
            
            $wallet = $this->walletRepository->findByUser($user);
            if (!$wallet) {
                $io->error('User does not have a wallet');
                return null;
            }
            return $wallet;
        } else {
            $io->error('Either --wallet-number or --user-id must be provided');
            return null;
        }
    }

    private function displayWalletInfo(SymfonyStyle $io, $wallet): void
    {
        $io->definitionList(
            ['Wallet Number' => $wallet->getWalletNumber()],
            ['User ID' => $wallet->getUser()->getId()],
            ['Status' => $wallet->getStatus()],
            ['Balance' => $wallet->getBalance() . ' ' . $wallet->getCurrency()],
            ['Available Balance' => $wallet->getAvailableBalance() . ' ' . $wallet->getCurrency()],
            ['Reserved Balance' => $wallet->getReservedBalance() . ' ' . $wallet->getCurrency()],
            ['Currency' => $wallet->getCurrency()],
            ['Daily Limit' => ($wallet->getDailyTransactionLimit() ?? 'None') . ' ' . $wallet->getCurrency()],
            ['Monthly Limit' => ($wallet->getMonthlyTransactionLimit() ?? 'None') . ' ' . $wallet->getCurrency()],
            ['Low Balance Threshold' => $wallet->getLowBalanceThreshold() . ' ' . $wallet->getCurrency()],
            ['Created At' => $wallet->getCreatedAt()->format('Y-m-d H:i:s')],
            ['Last Transaction' => $wallet->getLastTransactionAt()?->format('Y-m-d H:i:s') ?? 'Never']
        );

        if ($wallet->isFrozen()) {
            $io->warning('Wallet is FROZEN - Reason: ' . ($wallet->getFreezeReason() ?? 'Not specified'));
        }
    }

    private function displayWalletStatus(SymfonyStyle $io, $status): void
    {
        $io->definitionList(
            ['Wallet Number' => $status->getWalletNumber()],
            ['Status' => $status->getStatus()],
            ['Balance' => $status->getBalance() . ' ' . $status->getCurrency()],
            ['Available Balance' => $status->getAvailableBalance() . ' ' . $status->getCurrency()],
            ['Reserved Balance' => $status->getReservedBalance() . ' ' . $status->getCurrency()],
            ['Low Balance' => $status->isLowBalance() ? 'YES' : 'NO'],
            ['Daily Limit' => ($status->getDailyTransactionLimit() ?? 'None')],
            ['Monthly Limit' => ($status->getMonthlyTransactionLimit() ?? 'None')],
            ['Daily Spent' => ($status->getDailySpent() ?? '0.00')],
            ['Monthly Spent' => ($status->getMonthlySpent() ?? '0.00')],
            ['Daily Remaining' => ($status->getRemainingDailyLimit() ?? 'Unlimited')],
            ['Monthly Remaining' => ($status->getRemainingMonthlyLimit() ?? 'Unlimited')]
        );
    }

    private function displayWalletStatistics(SymfonyStyle $io, array $stats): void
    {
        $io->title('Wallet Statistics');

        $io->definitionList(
            ['Total Wallets' => $stats['total_wallets']],
            ['Active Wallets' => $stats['active_wallets']],
            ['Frozen Wallets' => $stats['frozen_wallets']],
            ['Suspended Wallets' => $stats['suspended_wallets']],
            ['Closed Wallets' => $stats['closed_wallets']],
            ['Low Balance Wallets' => $stats['low_balance_wallets']],
            ['Total Balance' => $stats['total_balance'] . ' PLN'],
            ['Available Balance' => $stats['total_available_balance'] . ' PLN'],
            ['Reserved Balance' => $stats['total_reserved_balance'] . ' PLN']
        );

        if (!empty($stats['currency_distribution'])) {
            $io->section('Currency Distribution');
            foreach ($stats['currency_distribution'] as $currency => $count) {
                $io->writeln(sprintf('%s: %d wallets', $currency, $count));
            }
        }
    }
}