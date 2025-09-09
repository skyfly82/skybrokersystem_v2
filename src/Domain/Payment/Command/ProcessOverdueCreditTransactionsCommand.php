<?php

declare(strict_types=1);

namespace App\Domain\Payment\Command;

use App\Domain\Payment\Contracts\CreditServiceInterface;
use App\Domain\Payment\Repository\CreditAccountRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'credit:process-overdue',
    description: 'Process overdue credit transactions and apply fees/interest'
)]
class ProcessOverdueCreditTransactionsCommand extends Command
{
    public function __construct(
        private readonly CreditServiceInterface $creditService,
        private readonly CreditAccountRepository $creditAccountRepository,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Process overdue credit transactions and apply fees/interest')
            ->setHelp('This command processes overdue credit transactions and applies appropriate fees and interest charges.')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be processed without making changes'
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Maximum number of transactions to process',
                100
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $limit = (int)$input->getOption('limit');

        $io->title('Credit Transactions Overdue Processing');

        if ($dryRun) {
            $io->note('Running in DRY-RUN mode - no changes will be made');
        }

        if (!$this->creditService->isEnabled()) {
            $io->error('Credit service is disabled');
            return Command::FAILURE;
        }

        try {
            // Get overdue transactions
            $overdueTransactions = $this->creditService->getOverdueTransactions();
            
            if (empty($overdueTransactions)) {
                $io->success('No overdue transactions found');
                return Command::SUCCESS;
            }

            $totalTransactions = count($overdueTransactions);
            $processedCount = 0;
            $errorCount = 0;
            $skippedCount = 0;

            $io->text(sprintf('Found %d overdue transactions to process', $totalTransactions));

            if ($limit > 0 && $totalTransactions > $limit) {
                $io->note(sprintf('Processing limited to %d transactions', $limit));
                $overdueTransactions = array_slice($overdueTransactions, 0, $limit);
            }

            $progressBar = $io->createProgressBar(count($overdueTransactions));
            $progressBar->start();

            foreach ($overdueTransactions as $transaction) {
                try {
                    if ($dryRun) {
                        // Just analyze what would happen
                        $io->text(sprintf(
                            '  - Would process transaction %s (overdue by %d days, amount: %s %s)',
                            $transaction->getTransactionId(),
                            $transaction->getDaysOverdue(),
                            $transaction->getAmount(),
                            $transaction->getCurrency()
                        ));
                        $processedCount++;
                    } else {
                        // Actually process the overdue charges
                        $charges = $this->creditService->processOverdueCharges($transaction);
                        
                        if (!empty($charges)) {
                            $this->logger->info('Processed overdue transaction', [
                                'transaction_id' => $transaction->getTransactionId(),
                                'charges_created' => count($charges),
                                'days_overdue' => $transaction->getDaysOverdue()
                            ]);
                        }

                        $processedCount++;
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->logger->error('Failed to process overdue transaction', [
                        'transaction_id' => $transaction->getTransactionId(),
                        'error' => $e->getMessage()
                    ]);

                    if ($io->isVerbose()) {
                        $io->error(sprintf(
                            'Error processing transaction %s: %s',
                            $transaction->getTransactionId(),
                            $e->getMessage()
                        ));
                    }
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $io->newLine(2);

            // Show summary
            $io->section('Processing Summary');
            $io->table(
                ['Metric', 'Count'],
                [
                    ['Total Found', $totalTransactions],
                    ['Processed', $processedCount],
                    ['Errors', $errorCount],
                    ['Skipped', $skippedCount]
                ]
            );

            if ($dryRun) {
                $io->success('Dry run completed successfully');
                $io->note('Run without --dry-run option to actually process the transactions');
            } else {
                if ($errorCount > 0) {
                    $io->warning(sprintf(
                        'Processing completed with %d errors. Check logs for details.',
                        $errorCount
                    ));
                } else {
                    $io->success('All overdue transactions processed successfully');
                }
            }

            // Show additional statistics
            $this->showStatistics($io);

            return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Command execution failed: ' . $e->getMessage());
            $this->logger->error('Overdue processing command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }

    private function showStatistics(SymfonyStyle $io): void
    {
        try {
            $statistics = $this->creditAccountRepository->getStatistics();
            
            if (!empty($statistics)) {
                $io->section('Credit Account Statistics');
                
                $rows = [];
                foreach ($statistics as $status => $stats) {
                    $rows[] = [
                        ucfirst($status),
                        $stats['account_count'],
                        $stats['total_credit_limit'],
                        $stats['total_used_credit'],
                        $stats['total_available_credit']
                    ];
                }

                $io->table(
                    ['Status', 'Accounts', 'Total Limit', 'Used Credit', 'Available Credit'],
                    $rows
                );
            }

            // Show accounts needing review
            $reviewAccounts = $this->creditService->getAccountsNeedingReview();
            if (!empty($reviewAccounts)) {
                $io->section('Accounts Needing Review');
                $io->text(sprintf('%d accounts need credit review', count($reviewAccounts)));
            }

        } catch (\Exception $e) {
            $io->note('Could not retrieve statistics: ' . $e->getMessage());
        }
    }
}