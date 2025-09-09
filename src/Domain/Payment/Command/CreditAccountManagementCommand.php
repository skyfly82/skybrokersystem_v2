<?php

declare(strict_types=1);

namespace App\Domain\Payment\Command;

use App\Domain\Payment\Contracts\CreditServiceInterface;
use App\Domain\Payment\Repository\CreditAccountRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'credit:account-management',
    description: 'Manage credit accounts (create, update, suspend, close)'
)]
class CreditAccountManagementCommand extends Command
{
    public function __construct(
        private readonly CreditServiceInterface $creditService,
        private readonly CreditAccountRepository $creditAccountRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Manage credit accounts')
            ->setHelp('This command allows you to manage credit accounts: create, update limits, suspend, reactivate, or close accounts.')
            ->addArgument(
                'action',
                InputArgument::REQUIRED,
                'Action to perform: create, update-limit, suspend, reactivate, close, status, list'
            )
            ->addOption(
                'user-id',
                'u',
                InputOption::VALUE_REQUIRED,
                'User ID for the credit account'
            )
            ->addOption(
                'account-type',
                't',
                InputOption::VALUE_REQUIRED,
                'Account type (individual or business)',
                'individual'
            )
            ->addOption(
                'credit-limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Credit limit amount'
            )
            ->addOption(
                'payment-terms',
                'p',
                InputOption::VALUE_REQUIRED,
                'Payment terms in days (15, 30, 45, 60, 90)',
                '30'
            )
            ->addOption(
                'currency',
                'c',
                InputOption::VALUE_REQUIRED,
                'Currency code',
                'PLN'
            )
            ->addOption(
                'reason',
                'r',
                InputOption::VALUE_REQUIRED,
                'Reason for the action'
            )
            ->addOption(
                'status',
                's',
                InputOption::VALUE_REQUIRED,
                'Filter accounts by status (for list action)'
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Limit number of results (for list action)',
                10
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        $io->title('Credit Account Management');

        if (!$this->creditService->isEnabled()) {
            $io->error('Credit service is disabled');
            return Command::FAILURE;
        }

        try {
            return match ($action) {
                'create' => $this->createAccount($input, $io),
                'update-limit' => $this->updateCreditLimit($input, $io),
                'suspend' => $this->suspendAccount($input, $io),
                'reactivate' => $this->reactivateAccount($input, $io),
                'close' => $this->closeAccount($input, $io),
                'status' => $this->showAccountStatus($input, $io),
                'list' => $this->listAccounts($input, $io),
                default => $this->showHelp($io)
            };
        } catch (\Exception $e) {
            $io->error('Command execution failed: ' . $e->getMessage());
            $this->logger->error('Credit account management command failed', [
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }

    private function createAccount(InputInterface $input, SymfonyStyle $io): int
    {
        $userId = $input->getOption('user-id');
        $accountType = $input->getOption('account-type');
        $creditLimit = $input->getOption('credit-limit');
        $paymentTerms = (int)$input->getOption('payment-terms');
        $currency = $input->getOption('currency');

        if (!$userId || !$creditLimit) {
            $io->error('User ID and credit limit are required for creating an account');
            return Command::FAILURE;
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            $io->error(sprintf('User with ID %s not found', $userId));
            return Command::FAILURE;
        }

        try {
            $account = $this->creditService->createCreditAccount(
                $user,
                $accountType,
                $creditLimit,
                $paymentTerms,
                $currency
            );

            $io->success(sprintf(
                'Credit account created successfully: %s',
                $account->getAccountNumber()
            ));

            $this->displayAccountInfo($io, $account);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to create credit account: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function updateCreditLimit(InputInterface $input, SymfonyStyle $io): int
    {
        $userId = $input->getOption('user-id');
        $creditLimit = $input->getOption('credit-limit');
        $reason = $input->getOption('reason');

        if (!$userId || !$creditLimit) {
            $io->error('User ID and new credit limit are required');
            return Command::FAILURE;
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            $io->error(sprintf('User with ID %s not found', $userId));
            return Command::FAILURE;
        }

        try {
            $account = $this->creditService->updateCreditLimit($user, $creditLimit, $reason);

            $io->success(sprintf(
                'Credit limit updated to %s %s for account %s',
                $creditLimit,
                $account->getCurrency(),
                $account->getAccountNumber()
            ));

            $this->displayAccountInfo($io, $account);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to update credit limit: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function suspendAccount(InputInterface $input, SymfonyStyle $io): int
    {
        $userId = $input->getOption('user-id');
        $reason = $input->getOption('reason') ?? 'Suspended via command';

        if (!$userId) {
            $io->error('User ID is required');
            return Command::FAILURE;
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            $io->error(sprintf('User with ID %s not found', $userId));
            return Command::FAILURE;
        }

        try {
            $account = $this->creditService->suspendAccount($user, $reason);

            $io->success(sprintf(
                'Account %s suspended successfully',
                $account->getAccountNumber()
            ));

            $this->displayAccountInfo($io, $account);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to suspend account: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function reactivateAccount(InputInterface $input, SymfonyStyle $io): int
    {
        $userId = $input->getOption('user-id');

        if (!$userId) {
            $io->error('User ID is required');
            return Command::FAILURE;
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            $io->error(sprintf('User with ID %s not found', $userId));
            return Command::FAILURE;
        }

        try {
            $account = $this->creditService->reactivateAccount($user);

            $io->success(sprintf(
                'Account %s reactivated successfully',
                $account->getAccountNumber()
            ));

            $this->displayAccountInfo($io, $account);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to reactivate account: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function closeAccount(InputInterface $input, SymfonyStyle $io): int
    {
        $userId = $input->getOption('user-id');
        $reason = $input->getOption('reason') ?? 'Closed via command';

        if (!$userId) {
            $io->error('User ID is required');
            return Command::FAILURE;
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            $io->error(sprintf('User with ID %s not found', $userId));
            return Command::FAILURE;
        }

        try {
            $account = $this->creditService->closeAccount($user, $reason);

            $io->success(sprintf(
                'Account %s closed successfully',
                $account->getAccountNumber()
            ));

            $this->displayAccountInfo($io, $account);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to close account: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function showAccountStatus(InputInterface $input, SymfonyStyle $io): int
    {
        $userId = $input->getOption('user-id');

        if (!$userId) {
            $io->error('User ID is required');
            return Command::FAILURE;
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            $io->error(sprintf('User with ID %s not found', $userId));
            return Command::FAILURE;
        }

        try {
            $statusDTO = $this->creditService->getCreditAccountStatus($user);

            $io->section('Credit Account Status');
            
            $io->table(
                ['Field', 'Value'],
                [
                    ['Account Number', $statusDTO->getAccountNumber()],
                    ['Account Type', ucfirst($statusDTO->getAccountType())],
                    ['Status', ucfirst($statusDTO->getStatus())],
                    ['Credit Limit', $statusDTO->getCreditLimit() . ' ' . $statusDTO->getCurrency()],
                    ['Used Credit', $statusDTO->getUsedCredit() . ' ' . $statusDTO->getCurrency()],
                    ['Available Credit', $statusDTO->getAvailableCredit() . ' ' . $statusDTO->getCurrency()],
                    ['Overdraft Limit', $statusDTO->getOverdraftLimit() . ' ' . $statusDTO->getCurrency()],
                    ['Payment Terms', $statusDTO->getPaymentTermDays() . ' days'],
                    ['Outstanding Balance', $statusDTO->getOutstandingBalance() . ' ' . $statusDTO->getCurrency()],
                    ['Overdue Transactions', $statusDTO->getOverdueTransactions()],
                    ['Needs Review', $statusDTO->needsReview() ? 'Yes' : 'No'],
                    ['Credit Utilization', round($statusDTO->getCreditUtilization(), 2) . '%'],
                    ['Is Overdrafted', $statusDTO->isOverdrafted() ? 'Yes' : 'No'],
                    ['Overdraft Amount', $statusDTO->getOverdraftAmount() . ' ' . $statusDTO->getCurrency()]
                ]
            );

            if ($statusDTO->getNextReviewDate()) {
                $io->text(sprintf(
                    'Next Review Date: %s',
                    $statusDTO->getNextReviewDate()->format('Y-m-d')
                ));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to get account status: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function listAccounts(InputInterface $input, SymfonyStyle $io): int
    {
        $status = $input->getOption('status');
        $limit = (int)$input->getOption('limit');

        $filters = [];
        if ($status) {
            $filters['status'] = $status;
        }

        $queryBuilder = $this->creditAccountRepository->createFilterQueryBuilder($filters);
        $queryBuilder->setMaxResults($limit)
                    ->orderBy('ca.createdAt', 'DESC');

        $accounts = $queryBuilder->getQuery()->getResult();

        if (empty($accounts)) {
            $io->info('No credit accounts found');
            return Command::SUCCESS;
        }

        $io->section('Credit Accounts');

        $rows = [];
        foreach ($accounts as $account) {
            $rows[] = [
                $account->getAccountNumber(),
                $account->getUser()->getId(),
                ucfirst($account->getAccountType()),
                ucfirst($account->getStatus()),
                $account->getCreditLimit() . ' ' . $account->getCurrency(),
                $account->getUsedCredit() . ' ' . $account->getCurrency(),
                $account->getCreatedAt()->format('Y-m-d'),
                $account->needsReview() ? 'Yes' : 'No'
            ];
        }

        $io->table(
            ['Account Number', 'User ID', 'Type', 'Status', 'Limit', 'Used', 'Created', 'Needs Review'],
            $rows
        );

        $io->text(sprintf('Showing %d of matching accounts', count($accounts)));

        return Command::SUCCESS;
    }

    private function showHelp(SymfonyStyle $io): int
    {
        $io->section('Available Actions');
        $io->text([
            'create        - Create a new credit account',
            'update-limit  - Update credit limit for an account',
            'suspend       - Suspend a credit account',
            'reactivate    - Reactivate a suspended account',
            'close         - Close a credit account',
            'status        - Show account status',
            'list          - List credit accounts'
        ]);

        $io->section('Examples');
        $io->text([
            'Create account:    credit:account-management create --user-id=1 --credit-limit=5000',
            'Update limit:      credit:account-management update-limit --user-id=1 --credit-limit=10000 --reason="Increased limit"',
            'Suspend account:   credit:account-management suspend --user-id=1 --reason="Risk assessment"',
            'Show status:       credit:account-management status --user-id=1',
            'List active:       credit:account-management list --status=active --limit=20'
        ]);

        return Command::SUCCESS;
    }

    private function displayAccountInfo(SymfonyStyle $io, $account): void
    {
        $io->table(
            ['Field', 'Value'],
            [
                ['Account Number', $account->getAccountNumber()],
                ['User ID', $account->getUser()->getId()],
                ['Account Type', ucfirst($account->getAccountType())],
                ['Status', ucfirst($account->getStatus())],
                ['Credit Limit', $account->getCreditLimit() . ' ' . $account->getCurrency()],
                ['Available Credit', $account->getAvailableCredit() . ' ' . $account->getCurrency()],
                ['Payment Terms', $account->getPaymentTermDays() . ' days'],
                ['Created At', $account->getCreatedAt()->format('Y-m-d H:i:s')],
                ['Updated At', $account->getUpdatedAt()->format('Y-m-d H:i:s')]
            ]
        );
    }
}