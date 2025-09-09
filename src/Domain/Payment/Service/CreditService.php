<?php

declare(strict_types=1);

namespace App\Domain\Payment\Service;

use App\Domain\Payment\Contracts\CreditServiceInterface;
use App\Domain\Payment\DTO\CreditAccountStatusDTO;
use App\Domain\Payment\DTO\CreditAuthorizationRequestDTO;
use App\Domain\Payment\DTO\CreditAuthorizationResponseDTO;
use App\Domain\Payment\DTO\CreditSettlementRequestDTO;
use App\Domain\Payment\DTO\CreditSettlementResponseDTO;
use App\Domain\Payment\Entity\CreditAccount;
use App\Domain\Payment\Entity\CreditTransaction;
use App\Domain\Payment\Exception\CreditException;
use App\Domain\Payment\Repository\CreditAccountRepository;
use App\Domain\Payment\Repository\CreditTransactionRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CreditService implements CreditServiceInterface
{
    private const SUPPORTED_CURRENCIES = ['PLN', 'EUR', 'USD', 'GBP'];
    private const MIN_AMOUNTS = [
        'PLN' => '1.00',
        'EUR' => '0.50',
        'USD' => '0.50',
        'GBP' => '0.50'
    ];
    private const MAX_AMOUNTS = [
        'PLN' => '50000.00',
        'EUR' => '10000.00',
        'USD' => '10000.00',
        'GBP' => '10000.00'
    ];

    private const ALLOWED_PAYMENT_TERMS = [
        CreditAccount::TERM_NET_15,
        CreditAccount::TERM_NET_30,
        CreditAccount::TERM_NET_45,
        CreditAccount::TERM_NET_60,
        CreditAccount::TERM_NET_90
    ];

    private const DEFAULT_INTEREST_RATE = '2.50'; // 2.5% monthly
    private const DEFAULT_OVERDRAFT_FEE = '25.00';

    public function __construct(
        private readonly CreditAccountRepository $creditAccountRepository,
        private readonly CreditTransactionRepository $creditTransactionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly bool $enabled = true
    ) {
    }

    public function createCreditAccount(
        User $user,
        string $accountType,
        string $creditLimit,
        int $paymentTermDays,
        string $currency = 'PLN',
        ?array $metadata = null
    ): CreditAccount {
        $this->logger->info('Creating credit account', [
            'user_id' => $user->getId(),
            'account_type' => $accountType,
            'credit_limit' => $creditLimit,
            'payment_term_days' => $paymentTermDays,
            'currency' => $currency
        ]);

        if (!$this->isEnabled()) {
            throw CreditException::authorizationFailed('Credit service is disabled');
        }

        if (!in_array($currency, self::SUPPORTED_CURRENCIES)) {
            throw CreditException::invalidCurrency($currency, self::SUPPORTED_CURRENCIES);
        }

        if (!in_array($paymentTermDays, self::ALLOWED_PAYMENT_TERMS)) {
            throw CreditException::invalidPaymentTerms($paymentTermDays, self::ALLOWED_PAYMENT_TERMS);
        }

        if (!$this->validateAmount($creditLimit, $currency)) {
            throw CreditException::invalidAmount($creditLimit, $currency);
        }

        // Check if user already has a credit account
        $existingAccount = $this->creditAccountRepository->findByUser($user);
        if ($existingAccount !== null) {
            throw CreditException::authorizationFailed(
                'User already has a credit account',
                ['existing_account' => $existingAccount->getAccountNumber()]
            );
        }

        $account = new CreditAccount();
        $account->setUser($user)
                ->setAccountType($accountType)
                ->setCreditLimit($creditLimit)
                ->setPaymentTermDays($paymentTermDays)
                ->setCurrency($currency)
                ->setInterestRate(self::DEFAULT_INTEREST_RATE)
                ->setOverdraftFee(self::DEFAULT_OVERDRAFT_FEE)
                ->setMetadata($metadata);

        // Set available credit equal to credit limit initially
        $account->setUsedCredit('0.00');

        try {
            $this->creditAccountRepository->save($account, true);

            $this->logger->info('Credit account created successfully', [
                'account_number' => $account->getAccountNumber(),
                'user_id' => $user->getId()
            ]);

            return $account;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create credit account', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            throw CreditException::authorizationFailed(
                'Failed to create credit account',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function authorizePayment(
        User $user,
        CreditAuthorizationRequestDTO $request
    ): CreditAuthorizationResponseDTO {
        $this->logger->info('Authorizing credit payment', [
            'user_id' => $user->getId(),
            'payment_id' => $request->getPaymentId(),
            'amount' => $request->getAmount(),
            'currency' => $request->getCurrency()
        ]);

        if (!$this->isEnabled()) {
            return CreditAuthorizationResponseDTO::failure(
                $request->getPaymentId(),
                $request->getAmount(),
                $request->getCurrency(),
                CreditException::ERROR_AUTHORIZATION_FAILED,
                'Credit service is disabled'
            );
        }

        if (!$this->validateAmount($request->getAmount(), $request->getCurrency())) {
            return CreditAuthorizationResponseDTO::failure(
                $request->getPaymentId(),
                $request->getAmount(),
                $request->getCurrency(),
                CreditException::ERROR_INVALID_AMOUNT,
                'Invalid payment amount'
            );
        }

        $account = $this->creditAccountRepository->findActiveByUser($user);
        if ($account === null) {
            return CreditAuthorizationResponseDTO::failure(
                $request->getPaymentId(),
                $request->getAmount(),
                $request->getCurrency(),
                CreditException::ERROR_ACCOUNT_NOT_FOUND,
                'Active credit account not found'
            );
        }

        if (!$account->canMakePayment($request->getAmount())) {
            $availableCredit = $account->getAvailableCredit();
            $overdraftLimit = $account->getOverdraftLimit();
            $totalAvailable = (float)$availableCredit + (float)$overdraftLimit;

            return CreditAuthorizationResponseDTO::failure(
                $request->getPaymentId(),
                $request->getAmount(),
                $request->getCurrency(),
                CreditException::ERROR_INSUFFICIENT_CREDIT,
                sprintf(
                    'Insufficient credit: requested %s, available %s (including overdraft)',
                    $request->getAmount(),
                    number_format($totalAvailable, 2, '.', '')
                )
            );
        }

        try {
            $this->entityManager->beginTransaction();

            // Create authorization transaction
            $transaction = new CreditTransaction();
            $transaction->setCreditAccount($account)
                       ->setTransactionType(CreditTransaction::TYPE_AUTHORIZATION)
                       ->setAmount($request->getAmount())
                       ->setCurrency($request->getCurrency())
                       ->setDescription($request->getDescription())
                       ->setExternalReference($request->getExternalReference())
                       ->setMetadata($request->getMetadata());

            // Calculate due date
            $paymentTermDays = $request->getPaymentTermDays() ?? $account->getPaymentTermDays();
            $dueDate = $account->calculatePaymentDueDate();
            $transaction->setDueDate($dueDate);

            // Authorize payment (update account balance)
            if (!$account->authorizePayment($request->getAmount())) {
                $this->entityManager->rollback();

                return CreditAuthorizationResponseDTO::failure(
                    $request->getPaymentId(),
                    $request->getAmount(),
                    $request->getCurrency(),
                    CreditException::ERROR_AUTHORIZATION_FAILED,
                    'Failed to authorize payment'
                );
            }

            $transaction->setStatus(CreditTransaction::STATUS_AUTHORIZED);

            $this->creditTransactionRepository->save($transaction, false);
            $this->creditAccountRepository->save($account, true);

            $this->entityManager->commit();

            $this->logger->info('Credit payment authorized successfully', [
                'transaction_id' => $transaction->getTransactionId(),
                'payment_id' => $request->getPaymentId(),
                'account_number' => $account->getAccountNumber()
            ]);

            return CreditAuthorizationResponseDTO::success(
                $transaction->getTransactionId(),
                $request->getPaymentId(),
                $request->getAmount(),
                $request->getCurrency(),
                CreditTransaction::STATUS_AUTHORIZED,
                $dueDate,
                $request->getMetadata()
            );

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Credit authorization failed', [
                'payment_id' => $request->getPaymentId(),
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return CreditAuthorizationResponseDTO::failure(
                $request->getPaymentId(),
                $request->getAmount(),
                $request->getCurrency(),
                CreditException::ERROR_AUTHORIZATION_FAILED,
                'Authorization failed: ' . $e->getMessage()
            );
        }
    }

    public function settlePayment(CreditSettlementRequestDTO $request): CreditSettlementResponseDTO
    {
        $this->logger->info('Settling credit payment', [
            'transaction_id' => $request->getTransactionId(),
            'settle_amount' => $request->getSettleAmount()
        ]);

        if (!$this->isEnabled()) {
            return CreditSettlementResponseDTO::failure(
                $request->getTransactionId(),
                CreditException::ERROR_SETTLEMENT_FAILED,
                'Credit service is disabled'
            );
        }

        $transaction = $this->creditTransactionRepository->findByTransactionId($request->getTransactionId());
        if ($transaction === null) {
            return CreditSettlementResponseDTO::failure(
                $request->getTransactionId(),
                CreditException::ERROR_TRANSACTION_NOT_FOUND,
                'Transaction not found'
            );
        }

        if (!$transaction->canBeSettled()) {
            return CreditSettlementResponseDTO::failure(
                $request->getTransactionId(),
                CreditException::ERROR_TRANSACTION_ALREADY_PROCESSED,
                sprintf('Transaction cannot be settled (status: %s)', $transaction->getStatus())
            );
        }

        try {
            $this->entityManager->beginTransaction();

            // Determine settlement amount
            $settleAmount = $request->getSettleAmount() ?? $transaction->getAmount();
            
            // Validate settlement amount
            if (!$this->validateAmount($settleAmount, $transaction->getCurrency())) {
                $this->entityManager->rollback();
                return CreditSettlementResponseDTO::failure(
                    $request->getTransactionId(),
                    CreditException::ERROR_INVALID_AMOUNT,
                    'Invalid settlement amount'
                );
            }

            // Update transaction status
            $transaction->setStatus(CreditTransaction::STATUS_SETTLED);
            if ($request->getNotes()) {
                $metadata = $transaction->getMetadata() ?? [];
                $metadata['settlement_notes'] = $request->getNotes();
                $transaction->setMetadata($metadata);
            }

            // If partial settlement, adjust authorized amount
            if ((float)$settleAmount !== (float)$transaction->getAmount()) {
                $difference = (float)$transaction->getAmount() - (float)$settleAmount;
                $account = $transaction->getCreditAccount();
                $account->releaseAuthorization(number_format($difference, 2, '.', ''));
                
                $this->creditAccountRepository->save($account, false);
            }

            // Create charge transaction for settled amount
            $chargeTransaction = new CreditTransaction();
            $chargeTransaction->setCreditAccount($transaction->getCreditAccount())
                             ->setPayment($transaction->getPayment())
                             ->setTransactionType(CreditTransaction::TYPE_CHARGE)
                             ->setAmount($settleAmount)
                             ->setCurrency($transaction->getCurrency())
                             ->setDescription('Charge for settled payment: ' . $transaction->getDescription())
                             ->setDueDate($transaction->getDueDate())
                             ->setExternalReference($transaction->getExternalReference())
                             ->setStatus(CreditTransaction::STATUS_SETTLED)
                             ->setMetadata($transaction->getMetadata());

            $this->creditTransactionRepository->save($transaction, false);
            $this->creditTransactionRepository->save($chargeTransaction, true);

            $this->entityManager->commit();

            $this->logger->info('Credit payment settled successfully', [
                'transaction_id' => $transaction->getTransactionId(),
                'charge_transaction_id' => $chargeTransaction->getTransactionId(),
                'settled_amount' => $settleAmount
            ]);

            return CreditSettlementResponseDTO::success(
                $transaction->getTransactionId(),
                CreditTransaction::STATUS_SETTLED,
                $settleAmount,
                $transaction->getCurrency(),
                new \DateTimeImmutable(),
                $transaction->getMetadata()
            );

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Credit settlement failed', [
                'transaction_id' => $request->getTransactionId(),
                'error' => $e->getMessage()
            ]);

            return CreditSettlementResponseDTO::failure(
                $request->getTransactionId(),
                CreditException::ERROR_SETTLEMENT_FAILED,
                'Settlement failed: ' . $e->getMessage()
            );
        }
    }

    public function cancelAuthorization(string $transactionId, ?string $reason = null): bool
    {
        $this->logger->info('Cancelling credit authorization', [
            'transaction_id' => $transactionId,
            'reason' => $reason
        ]);

        $transaction = $this->creditTransactionRepository->findByTransactionId($transactionId);
        if ($transaction === null) {
            throw CreditException::transactionNotFound($transactionId);
        }

        if (!$transaction->canBeCancelled()) {
            throw CreditException::transactionAlreadyProcessed($transactionId, $transaction->getStatus());
        }

        try {
            $this->entityManager->beginTransaction();

            // Release the authorization hold
            $account = $transaction->getCreditAccount();
            $account->releaseAuthorization($transaction->getAmount());

            // Update transaction status
            $transaction->setStatus(CreditTransaction::STATUS_CANCELLED);
            if ($reason) {
                $metadata = $transaction->getMetadata() ?? [];
                $metadata['cancellation_reason'] = $reason;
                $transaction->setMetadata($metadata);
            }

            $this->creditAccountRepository->save($account, false);
            $this->creditTransactionRepository->save($transaction, true);

            $this->entityManager->commit();

            $this->logger->info('Credit authorization cancelled successfully', [
                'transaction_id' => $transactionId,
                'account_number' => $account->getAccountNumber()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Failed to cancel credit authorization', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            throw CreditException::authorizationFailed(
                'Failed to cancel authorization',
                ['transaction_id' => $transactionId, 'error' => $e->getMessage()]
            );
        }
    }

    public function processRefund(
        string $transactionId,
        string $refundAmount,
        ?string $reason = null
    ): CreditTransaction {
        $this->logger->info('Processing credit refund', [
            'transaction_id' => $transactionId,
            'refund_amount' => $refundAmount,
            'reason' => $reason
        ]);

        $originalTransaction = $this->creditTransactionRepository->findByTransactionId($transactionId);
        if ($originalTransaction === null) {
            throw CreditException::transactionNotFound($transactionId);
        }

        if (!$originalTransaction->isSettled() && !$originalTransaction->isCharge()) {
            throw CreditException::refundNotAllowed(
                $transactionId,
                'Only settled charges can be refunded'
            );
        }

        if (!$this->validateAmount($refundAmount, $originalTransaction->getCurrency())) {
            throw CreditException::invalidAmount($refundAmount, $originalTransaction->getCurrency());
        }

        if ((float)$refundAmount > (float)$originalTransaction->getAmount()) {
            throw CreditException::refundNotAllowed(
                $transactionId,
                'Refund amount cannot exceed original transaction amount'
            );
        }

        try {
            $this->entityManager->beginTransaction();

            // Create refund transaction
            $refundTransaction = new CreditTransaction();
            $refundTransaction->setCreditAccount($originalTransaction->getCreditAccount())
                             ->setPayment($originalTransaction->getPayment())
                             ->setTransactionType(CreditTransaction::TYPE_REFUND)
                             ->setAmount($refundAmount)
                             ->setCurrency($originalTransaction->getCurrency())
                             ->setDescription(
                                 sprintf('Refund for transaction %s: %s', $transactionId, $reason ?? 'No reason provided')
                             )
                             ->setExternalReference($originalTransaction->getExternalReference())
                             ->setStatus(CreditTransaction::STATUS_SETTLED);

            // Release credit (increase available credit)
            $account = $originalTransaction->getCreditAccount();
            $newUsedCredit = (float)$account->getUsedCredit() - (float)$refundAmount;
            $account->setUsedCredit(number_format(max(0, $newUsedCredit), 2, '.', ''));

            $this->creditTransactionRepository->save($refundTransaction, false);
            $this->creditAccountRepository->save($account, true);

            $this->entityManager->commit();

            $this->logger->info('Credit refund processed successfully', [
                'refund_transaction_id' => $refundTransaction->getTransactionId(),
                'original_transaction_id' => $transactionId,
                'refund_amount' => $refundAmount
            ]);

            return $refundTransaction;

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Credit refund failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            throw CreditException::settlementFailed(
                $transactionId,
                'Refund processing failed: ' . $e->getMessage()
            );
        }
    }

    public function getCreditAccountStatus(User $user): CreditAccountStatusDTO
    {
        $account = $this->creditAccountRepository->findByUser($user);
        if ($account === null) {
            throw CreditException::accountNotFound('User ID: ' . $user->getId());
        }

        // Get outstanding balance
        $outstandingBalance = $this->creditTransactionRepository->getOutstandingBalance($account);

        // Count overdue transactions
        $overdueTransactions = count($this->creditTransactionRepository->findOverdueTransactions($account));

        return new CreditAccountStatusDTO(
            $account->getAccountNumber(),
            $account->getAccountType(),
            $account->getStatus(),
            $account->getCreditLimit(),
            $account->getAvailableCredit(),
            $account->getUsedCredit(),
            $account->getOverdraftLimit(),
            $account->getCurrency(),
            $account->getPaymentTermDays(),
            $outstandingBalance,
            $overdueTransactions,
            $account->needsReview(),
            $account->getNextReviewDate(),
            $account->getMetadata()
        );
    }

    public function getTransaction(string $transactionId): CreditTransaction
    {
        $transaction = $this->creditTransactionRepository->findByTransactionId($transactionId);
        if ($transaction === null) {
            throw CreditException::transactionNotFound($transactionId);
        }

        return $transaction;
    }

    public function updateCreditLimit(
        User $user,
        string $newCreditLimit,
        ?string $reason = null
    ): CreditAccount {
        $this->logger->info('Updating credit limit', [
            'user_id' => $user->getId(),
            'new_credit_limit' => $newCreditLimit,
            'reason' => $reason
        ]);

        $account = $this->creditAccountRepository->findByUser($user);
        if ($account === null) {
            throw CreditException::accountNotFound('User ID: ' . $user->getId());
        }

        if (!$this->validateAmount($newCreditLimit, $account->getCurrency())) {
            throw CreditException::invalidAmount($newCreditLimit, $account->getCurrency());
        }

        $oldCreditLimit = $account->getCreditLimit();
        $account->setCreditLimit($newCreditLimit);

        if ($reason) {
            $metadata = $account->getMetadata() ?? [];
            $metadata['credit_limit_updates'] = $metadata['credit_limit_updates'] ?? [];
            $metadata['credit_limit_updates'][] = [
                'old_limit' => $oldCreditLimit,
                'new_limit' => $newCreditLimit,
                'reason' => $reason,
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
            ];
            $account->setMetadata($metadata);
        }

        $this->creditAccountRepository->save($account, true);

        $this->logger->info('Credit limit updated successfully', [
            'account_number' => $account->getAccountNumber(),
            'old_limit' => $oldCreditLimit,
            'new_limit' => $newCreditLimit
        ]);

        return $account;
    }

    public function suspendAccount(User $user, string $reason): CreditAccount
    {
        $this->logger->info('Suspending credit account', [
            'user_id' => $user->getId(),
            'reason' => $reason
        ]);

        $account = $this->creditAccountRepository->findByUser($user);
        if ($account === null) {
            throw CreditException::accountNotFound('User ID: ' . $user->getId());
        }

        if ($account->isSuspended()) {
            throw CreditException::accountSuspended($account->getAccountNumber(), 'Already suspended');
        }

        $account->setStatus(CreditAccount::STATUS_SUSPENDED);
        
        $metadata = $account->getMetadata() ?? [];
        $metadata['suspension_reason'] = $reason;
        $metadata['suspended_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $account->setMetadata($metadata);

        $this->creditAccountRepository->save($account, true);

        $this->logger->info('Credit account suspended successfully', [
            'account_number' => $account->getAccountNumber(),
            'reason' => $reason
        ]);

        return $account;
    }

    public function reactivateAccount(User $user): CreditAccount
    {
        $this->logger->info('Reactivating credit account', [
            'user_id' => $user->getId()
        ]);

        $account = $this->creditAccountRepository->findByUser($user);
        if ($account === null) {
            throw CreditException::accountNotFound('User ID: ' . $user->getId());
        }

        if (!$account->isSuspended()) {
            throw CreditException::authorizationFailed(
                'Account is not suspended',
                ['current_status' => $account->getStatus()]
            );
        }

        $account->setStatus(CreditAccount::STATUS_ACTIVE);
        
        $metadata = $account->getMetadata() ?? [];
        $metadata['reactivated_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $account->setMetadata($metadata);

        $this->creditAccountRepository->save($account, true);

        $this->logger->info('Credit account reactivated successfully', [
            'account_number' => $account->getAccountNumber()
        ]);

        return $account;
    }

    public function closeAccount(User $user, string $reason): CreditAccount
    {
        $this->logger->info('Closing credit account', [
            'user_id' => $user->getId(),
            'reason' => $reason
        ]);

        $account = $this->creditAccountRepository->findByUser($user);
        if ($account === null) {
            throw CreditException::accountNotFound('User ID: ' . $user->getId());
        }

        if ($account->isClosed()) {
            throw CreditException::accountClosed($account->getAccountNumber(), $account->getClosedAt());
        }

        // Check if there are outstanding charges
        $outstandingBalance = $this->creditTransactionRepository->getOutstandingBalance($account);
        if ((float)$outstandingBalance > 0) {
            throw CreditException::authorizationFailed(
                'Cannot close account with outstanding balance',
                [
                    'outstanding_balance' => $outstandingBalance,
                    'currency' => $account->getCurrency()
                ]
            );
        }

        $account->setStatus(CreditAccount::STATUS_CLOSED);
        
        $metadata = $account->getMetadata() ?? [];
        $metadata['closure_reason'] = $reason;
        $metadata['closed_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $account->setMetadata($metadata);

        $this->creditAccountRepository->save($account, true);

        $this->logger->info('Credit account closed successfully', [
            'account_number' => $account->getAccountNumber(),
            'reason' => $reason
        ]);

        return $account;
    }

    public function canMakePayment(User $user, string $amount, string $currency = 'PLN'): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        if (!$this->validateAmount($amount, $currency)) {
            return false;
        }

        $account = $this->creditAccountRepository->findActiveByUser($user);
        if ($account === null) {
            return false;
        }

        return $account->canMakePayment($amount);
    }

    public function validateAmount(string $amount, string $currency = 'PLN'): bool
    {
        if (!in_array($currency, self::SUPPORTED_CURRENCIES)) {
            return false;
        }

        $numericAmount = (float)$amount;
        $minAmount = (float)(self::MIN_AMOUNTS[$currency] ?? '1.00');
        $maxAmount = (float)(self::MAX_AMOUNTS[$currency] ?? '50000.00');

        return $numericAmount >= $minAmount && $numericAmount <= $maxAmount;
    }

    public function getSupportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    public function getMinimumAmount(string $currency = 'PLN'): string
    {
        return self::MIN_AMOUNTS[$currency] ?? '1.00';
    }

    public function getMaximumAmount(string $currency = 'PLN'): string
    {
        return self::MAX_AMOUNTS[$currency] ?? '50000.00';
    }

    public function getOverdueTransactions(?User $user = null): array
    {
        if ($user !== null) {
            $account = $this->creditAccountRepository->findByUser($user);
            if ($account === null) {
                return [];
            }
            return $this->creditTransactionRepository->findOverdueTransactions($account);
        }

        return $this->creditTransactionRepository->findOverdueTransactions();
    }

    public function processOverdueCharges(CreditTransaction $transaction): array
    {
        if (!$transaction->isOverdue()) {
            return [];
        }

        $this->logger->info('Processing overdue charges', [
            'transaction_id' => $transaction->getTransactionId()
        ]);

        $account = $transaction->getCreditAccount();
        $charges = [];

        try {
            $this->entityManager->beginTransaction();

            $daysOverdue = $transaction->getDaysOverdue();
            $transactionAmount = (float)$transaction->getAmount();

            // Calculate interest charge
            if ($account->getInterestRate() && $daysOverdue > 0) {
                $monthlyRate = (float)$account->getInterestRate() / 100;
                $dailyRate = $monthlyRate / 30;
                $interestAmount = $transactionAmount * $dailyRate * $daysOverdue;

                if ($interestAmount > 0) {
                    $interestTransaction = new CreditTransaction();
                    $interestTransaction->setCreditAccount($account)
                                      ->setTransactionType(CreditTransaction::TYPE_INTEREST)
                                      ->setAmount(number_format($interestAmount, 2, '.', ''))
                                      ->setCurrency($transaction->getCurrency())
                                      ->setDescription(
                                          sprintf('Interest charge for overdue transaction %s (%d days)', 
                                                $transaction->getTransactionId(), 
                                                $daysOverdue
                                          )
                                      )
                                      ->setStatus(CreditTransaction::STATUS_SETTLED)
                                      ->setDueDate(new \DateTimeImmutable('+1 day'));

                    $this->creditTransactionRepository->save($interestTransaction, false);
                    $charges[] = $interestTransaction;
                }
            }

            // Calculate overdraft fee if applicable
            if ($account->isOverdrafted() && $account->getOverdraftFee()) {
                $overdraftFee = (float)$account->getOverdraftFee();
                
                $feeTransaction = new CreditTransaction();
                $feeTransaction->setCreditAccount($account)
                              ->setTransactionType(CreditTransaction::TYPE_FEE)
                              ->setAmount(number_format($overdraftFee, 2, '.', ''))
                              ->setCurrency($account->getCurrency())
                              ->setDescription(
                                  sprintf('Overdraft fee for account %s', $account->getAccountNumber())
                              )
                              ->setStatus(CreditTransaction::STATUS_SETTLED)
                              ->setDueDate(new \DateTimeImmutable('+1 day'));

                $this->creditTransactionRepository->save($feeTransaction, false);
                $charges[] = $feeTransaction;
            }

            // Update transaction status to overdue
            $transaction->setStatus(CreditTransaction::STATUS_OVERDUE);
            $this->creditTransactionRepository->save($transaction, true);

            $this->entityManager->commit();

            $this->logger->info('Overdue charges processed successfully', [
                'transaction_id' => $transaction->getTransactionId(),
                'charges_count' => count($charges)
            ]);

            return $charges;

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Failed to process overdue charges', [
                'transaction_id' => $transaction->getTransactionId(),
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    public function markOverdueTransactions(): int
    {
        $this->logger->info('Marking overdue transactions');

        $overdueTransactions = $this->creditTransactionRepository->findOverdueTransactions();
        $markedCount = 0;

        foreach ($overdueTransactions as $transaction) {
            try {
                $this->processOverdueCharges($transaction);
                $markedCount++;
            } catch (\Exception $e) {
                $this->logger->error('Failed to process overdue transaction', [
                    'transaction_id' => $transaction->getTransactionId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->logger->info('Overdue transactions processing completed', [
            'processed_count' => $markedCount,
            'total_found' => count($overdueTransactions)
        ]);

        return $markedCount;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getAllowedPaymentTerms(): array
    {
        return self::ALLOWED_PAYMENT_TERMS;
    }

    public function scheduleAccountReview(User $user, ?\DateTimeInterface $reviewDate = null): void
    {
        $account = $this->creditAccountRepository->findByUser($user);
        if ($account === null) {
            throw CreditException::accountNotFound('User ID: ' . $user->getId());
        }

        $reviewDate = $reviewDate ?? new \DateTimeImmutable('+1 year');
        $account->setNextReviewDate(\DateTimeImmutable::createFromInterface($reviewDate));

        $this->creditAccountRepository->save($account, true);

        $this->logger->info('Account review scheduled', [
            'account_number' => $account->getAccountNumber(),
            'review_date' => $reviewDate->format('Y-m-d')
        ]);
    }

    public function getAccountsNeedingReview(): array
    {
        return $this->creditAccountRepository->findAccountsNeedingReview();
    }

    public function recordPayment(
        User $user,
        string $amount,
        string $transactionType,
        ?string $description = null,
        ?array $metadata = null
    ): CreditTransaction {
        $account = $this->creditAccountRepository->findByUser($user);
        if ($account === null) {
            throw CreditException::accountNotFound('User ID: ' . $user->getId());
        }

        if (!$this->validateAmount($amount, $account->getCurrency())) {
            throw CreditException::invalidAmount($amount, $account->getCurrency());
        }

        $transaction = new CreditTransaction();
        $transaction->setCreditAccount($account)
                   ->setTransactionType($transactionType)
                   ->setAmount($amount)
                   ->setCurrency($account->getCurrency())
                   ->setDescription($description ?? sprintf('%s transaction', ucfirst($transactionType)))
                   ->setStatus(CreditTransaction::STATUS_SETTLED)
                   ->setMetadata($metadata);

        // Adjust account balance for payments
        if ($transactionType === CreditTransaction::TYPE_PAYMENT) {
            $newUsedCredit = (float)$account->getUsedCredit() - (float)$amount;
            $account->setUsedCredit(number_format(max(0, $newUsedCredit), 2, '.', ''));
            $this->creditAccountRepository->save($account, false);
        }

        $this->creditTransactionRepository->save($transaction, true);

        $this->logger->info('Manual transaction recorded', [
            'transaction_id' => $transaction->getTransactionId(),
            'account_number' => $account->getAccountNumber(),
            'type' => $transactionType,
            'amount' => $amount
        ]);

        return $transaction;
    }
}