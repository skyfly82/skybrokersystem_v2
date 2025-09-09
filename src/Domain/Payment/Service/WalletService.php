<?php

declare(strict_types=1);

namespace App\Domain\Payment\Service;

use App\Domain\Payment\Contracts\WalletServiceInterface;
use App\Domain\Payment\DTO\WalletPaymentRequestDTO;
use App\Domain\Payment\DTO\WalletPaymentResponseDTO;
use App\Domain\Payment\DTO\WalletStatusDTO;
use App\Domain\Payment\DTO\WalletTopUpRequestDTO;
use App\Domain\Payment\DTO\WalletTopUpResponseDTO;
use App\Domain\Payment\DTO\WalletTransferRequestDTO;
use App\Domain\Payment\DTO\WalletTransferResponseDTO;
use App\Domain\Payment\Entity\Wallet;
use App\Domain\Payment\Entity\WalletTransaction;
use App\Domain\Payment\Exception\WalletException;
use App\Domain\Payment\Repository\WalletRepository;
use App\Domain\Payment\Repository\WalletTransactionRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class WalletService implements WalletServiceInterface
{
    private const SUPPORTED_CURRENCIES = ['PLN', 'EUR', 'USD', 'GBP'];
    private const MIN_AMOUNTS = [
        'PLN' => '0.01',
        'EUR' => '0.01',
        'USD' => '0.01',
        'GBP' => '0.01'
    ];
    private const MAX_AMOUNTS = [
        'PLN' => '100000.00',
        'EUR' => '20000.00',
        'USD' => '25000.00',
        'GBP' => '20000.00'
    ];

    private const DEFAULT_DAILY_LIMIT = [
        'PLN' => '10000.00',
        'EUR' => '2000.00',
        'USD' => '2500.00',
        'GBP' => '2000.00'
    ];

    private const DEFAULT_MONTHLY_LIMIT = [
        'PLN' => '50000.00',
        'EUR' => '10000.00',
        'USD' => '12500.00',
        'GBP' => '10000.00'
    ];

    private const DEFAULT_LOW_BALANCE_THRESHOLD = '10.00';

    public function __construct(
        private readonly WalletRepository $walletRepository,
        private readonly WalletTransactionRepository $walletTransactionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly bool $enabled = true
    ) {
    }

    public function createWallet(
        User $user,
        string $currency = 'PLN',
        ?string $dailyTransactionLimit = null,
        ?string $monthlyTransactionLimit = null,
        ?string $lowBalanceThreshold = null,
        ?array $metadata = null
    ): Wallet {
        $this->logger->info('Creating wallet', [
            'user_id' => $user->getId(),
            'currency' => $currency
        ]);

        if (!$this->isEnabled()) {
            throw WalletException::paymentFailed('Wallet service is disabled');
        }

        if (!in_array($currency, self::SUPPORTED_CURRENCIES)) {
            throw WalletException::invalidCurrency($currency, self::SUPPORTED_CURRENCIES);
        }

        // Check if user already has a wallet
        $existingWallet = $this->walletRepository->findByUser($user);
        if ($existingWallet !== null) {
            throw WalletException::walletAlreadyExists($user->getId());
        }

        $wallet = new Wallet();
        $wallet->setUser($user)
               ->setCurrency($currency)
               ->setDailyTransactionLimit($dailyTransactionLimit ?? self::DEFAULT_DAILY_LIMIT[$currency])
               ->setMonthlyTransactionLimit($monthlyTransactionLimit ?? self::DEFAULT_MONTHLY_LIMIT[$currency])
               ->setLowBalanceThreshold($lowBalanceThreshold ?? self::DEFAULT_LOW_BALANCE_THRESHOLD)
               ->setMetadata($metadata);

        try {
            $this->walletRepository->save($wallet, true);

            $this->logger->info('Wallet created successfully', [
                'wallet_number' => $wallet->getWalletNumber(),
                'user_id' => $user->getId()
            ]);

            return $wallet;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create wallet', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            throw WalletException::paymentFailed(
                'Failed to create wallet',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function processPayment(User $user, WalletPaymentRequestDTO $request): WalletPaymentResponseDTO
    {
        $this->logger->info('Processing wallet payment', [
            'user_id' => $user->getId(),
            'payment_id' => $request->getPaymentId(),
            'amount' => $request->getAmount(),
            'currency' => $request->getCurrency()
        ]);

        if (!$this->isEnabled()) {
            return WalletPaymentResponseDTO::failure(
                $request->getPaymentId(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletException::ERROR_PAYMENT_FAILED,
                'Wallet service is disabled'
            );
        }

        if (!$this->validateAmount($request->getAmount(), $request->getCurrency())) {
            return WalletPaymentResponseDTO::failure(
                $request->getPaymentId(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletException::ERROR_INVALID_AMOUNT,
                'Invalid payment amount'
            );
        }

        $wallet = $this->walletRepository->findActiveByUser($user);
        if ($wallet === null) {
            return WalletPaymentResponseDTO::failure(
                $request->getPaymentId(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletException::ERROR_WALLET_NOT_FOUND,
                'Active wallet not found'
            );
        }

        if (!$wallet->canMakeTransaction($request->getAmount())) {
            return WalletPaymentResponseDTO::failure(
                $request->getPaymentId(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletException::ERROR_INSUFFICIENT_BALANCE,
                sprintf(
                    'Insufficient balance: requested %s, available %s',
                    $request->getAmount(),
                    $wallet->getAvailableBalance()
                )
            );
        }

        // Check daily and monthly limits
        $today = new \DateTimeImmutable();
        $dailySpent = $this->walletTransactionRepository->getDailyTransactionAmount($wallet, $today);
        $monthlySpent = $this->walletTransactionRepository->getMonthlyTransactionAmount($wallet, $today);

        if (!$wallet->checkDailyLimit($request->getAmount(), $dailySpent)) {
            return WalletPaymentResponseDTO::failure(
                $request->getPaymentId(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletException::ERROR_DAILY_LIMIT_EXCEEDED,
                'Daily transaction limit exceeded'
            );
        }

        if (!$wallet->checkMonthlyLimit($request->getAmount(), $monthlySpent)) {
            return WalletPaymentResponseDTO::failure(
                $request->getPaymentId(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletException::ERROR_MONTHLY_LIMIT_EXCEEDED,
                'Monthly transaction limit exceeded'
            );
        }

        try {
            $this->entityManager->beginTransaction();

            // Record balance before transaction
            $balanceBefore = $wallet->getBalance();

            // Create transaction
            $transaction = new WalletTransaction();
            $transaction->setWallet($wallet)
                       ->setTransactionType(WalletTransaction::TYPE_DEBIT)
                       ->setCategory(WalletTransaction::CATEGORY_PAYMENT)
                       ->setAmount($request->getAmount())
                       ->setCurrency($request->getCurrency())
                       ->setDescription($request->getDescription() ?? 'Payment transaction')
                       ->setExternalReference($request->getExternalReference())
                       ->setBalanceBefore($balanceBefore)
                       ->setMetadata($request->getMetadata())
                       ->setStatus(WalletTransaction::STATUS_PROCESSING);

            // Debit wallet balance
            if (!$wallet->debitBalance($request->getAmount())) {
                $this->entityManager->rollback();
                return WalletPaymentResponseDTO::failure(
                    $request->getPaymentId(),
                    $request->getAmount(),
                    $request->getCurrency(),
                    WalletException::ERROR_PAYMENT_FAILED,
                    'Failed to debit wallet balance'
                );
            }

            $transaction->setBalanceAfter($wallet->getBalance())
                       ->setStatus(WalletTransaction::STATUS_COMPLETED);

            $this->walletTransactionRepository->save($transaction, false);
            $this->walletRepository->save($wallet, true);

            $this->entityManager->commit();

            $this->logger->info('Wallet payment processed successfully', [
                'transaction_id' => $transaction->getTransactionId(),
                'payment_id' => $request->getPaymentId(),
                'wallet_number' => $wallet->getWalletNumber()
            ]);

            return WalletPaymentResponseDTO::success(
                $transaction->getTransactionId(),
                $request->getPaymentId(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletTransaction::STATUS_COMPLETED,
                $wallet->getAvailableBalance(),
                new \DateTimeImmutable(),
                $request->getMetadata()
            );

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Wallet payment failed', [
                'payment_id' => $request->getPaymentId(),
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return WalletPaymentResponseDTO::failure(
                $request->getPaymentId(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletException::ERROR_PAYMENT_FAILED,
                'Payment processing failed: ' . $e->getMessage()
            );
        }
    }

    public function transferFunds(User $fromUser, WalletTransferRequestDTO $request): WalletTransferResponseDTO
    {
        $this->logger->info('Processing wallet transfer', [
            'from_user_id' => $fromUser->getId(),
            'source_wallet' => $request->getSourceWalletNumber(),
            'destination_wallet' => $request->getDestinationWalletNumber(),
            'amount' => $request->getAmount()
        ]);

        if (!$this->isEnabled()) {
            return WalletTransferResponseDTO::failure(
                $request->getSourceWalletNumber(),
                $request->getDestinationWalletNumber(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletException::ERROR_TRANSFER_FAILED,
                'Wallet service is disabled'
            );
        }

        if (!$this->validateAmount($request->getAmount(), $request->getCurrency())) {
            return WalletTransferResponseDTO::failure(
                $request->getSourceWalletNumber(),
                $request->getDestinationWalletNumber(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletException::ERROR_INVALID_AMOUNT,
                'Invalid transfer amount'
            );
        }

        if ($request->getSourceWalletNumber() === $request->getDestinationWalletNumber()) {
            return WalletTransferResponseDTO::failure(
                $request->getSourceWalletNumber(),
                $request->getDestinationWalletNumber(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletException::ERROR_SAME_WALLET_TRANSFER,
                'Cannot transfer to the same wallet'
            );
        }

        $sourceWallet = $this->walletRepository->findByWalletNumber($request->getSourceWalletNumber());
        if ($sourceWallet === null || $sourceWallet->getUser()->getId() !== $fromUser->getId()) {
            return WalletTransferResponseDTO::failure(
                $request->getSourceWalletNumber(),
                $request->getDestinationWalletNumber(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletException::ERROR_WALLET_NOT_FOUND,
                'Source wallet not found or not owned by user'
            );
        }

        $destinationWallet = $this->walletRepository->findByWalletNumber($request->getDestinationWalletNumber());
        if ($destinationWallet === null) {
            return WalletTransferResponseDTO::failure(
                $request->getSourceWalletNumber(),
                $request->getDestinationWalletNumber(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletException::ERROR_WALLET_NOT_FOUND,
                'Destination wallet not found'
            );
        }

        if (!$sourceWallet->isActive()) {
            return WalletTransferResponseDTO::failure(
                $request->getSourceWalletNumber(),
                $request->getDestinationWalletNumber(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletException::ERROR_WALLET_INACTIVE,
                'Source wallet is not active'
            );
        }

        if (!$destinationWallet->isActive()) {
            return WalletTransferResponseDTO::failure(
                $request->getSourceWalletNumber(),
                $request->getDestinationWalletNumber(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletException::ERROR_WALLET_INACTIVE,
                'Destination wallet is not active'
            );
        }

        if (!$sourceWallet->canMakeTransaction($request->getAmount())) {
            return WalletTransferResponseDTO::failure(
                $request->getSourceWalletNumber(),
                $request->getDestinationWalletNumber(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletException::ERROR_INSUFFICIENT_BALANCE,
                'Insufficient balance in source wallet'
            );
        }

        try {
            $this->entityManager->beginTransaction();

            $sourceBalanceBefore = $sourceWallet->getBalance();
            $destinationBalanceBefore = $destinationWallet->getBalance();

            // Create outbound transaction
            $outboundTransaction = new WalletTransaction();
            $outboundTransaction->setWallet($sourceWallet)
                              ->setDestinationWallet($destinationWallet)
                              ->setTransactionType(WalletTransaction::TYPE_TRANSFER_OUT)
                              ->setCategory(WalletTransaction::CATEGORY_TRANSFER)
                              ->setAmount($request->getAmount())
                              ->setCurrency($request->getCurrency())
                              ->setDescription($request->getDescription() ?? 'Transfer to ' . $request->getDestinationWalletNumber())
                              ->setExternalReference($request->getExternalReference())
                              ->setBalanceBefore($sourceBalanceBefore)
                              ->setMetadata($request->getMetadata())
                              ->setStatus(WalletTransaction::STATUS_PROCESSING);

            // Create inbound transaction
            $inboundTransaction = new WalletTransaction();
            $inboundTransaction->setWallet($destinationWallet)
                             ->setSourceWallet($sourceWallet)
                             ->setTransactionType(WalletTransaction::TYPE_TRANSFER_IN)
                             ->setCategory(WalletTransaction::CATEGORY_TRANSFER)
                             ->setAmount($request->getAmount())
                             ->setCurrency($request->getCurrency())
                             ->setDescription($request->getDescription() ?? 'Transfer from ' . $request->getSourceWalletNumber())
                             ->setExternalReference($request->getExternalReference())
                             ->setBalanceBefore($destinationBalanceBefore)
                             ->setMetadata($request->getMetadata())
                             ->setStatus(WalletTransaction::STATUS_PROCESSING);

            // Process transfer
            if (!$sourceWallet->debitBalance($request->getAmount())) {
                $this->entityManager->rollback();
                return WalletTransferResponseDTO::failure(
                    $request->getSourceWalletNumber(),
                    $request->getDestinationWalletNumber(),
                    $request->getAmount(),
                    $request->getCurrency(),
                    WalletException::ERROR_TRANSFER_FAILED,
                    'Failed to debit source wallet'
                );
            }

            $destinationWallet->creditBalance($request->getAmount());

            $outboundTransaction->setBalanceAfter($sourceWallet->getBalance())
                              ->setStatus(WalletTransaction::STATUS_COMPLETED);

            $inboundTransaction->setBalanceAfter($destinationWallet->getBalance())
                             ->setStatus(WalletTransaction::STATUS_COMPLETED);

            $this->walletTransactionRepository->save($outboundTransaction, false);
            $this->walletTransactionRepository->save($inboundTransaction, false);
            $this->walletRepository->save($sourceWallet, false);
            $this->walletRepository->save($destinationWallet, true);

            $this->entityManager->commit();

            $this->logger->info('Wallet transfer completed successfully', [
                'source_transaction_id' => $outboundTransaction->getTransactionId(),
                'destination_transaction_id' => $inboundTransaction->getTransactionId(),
                'source_wallet' => $request->getSourceWalletNumber(),
                'destination_wallet' => $request->getDestinationWalletNumber()
            ]);

            return WalletTransferResponseDTO::success(
                $outboundTransaction->getTransactionId(),
                $inboundTransaction->getTransactionId(),
                $request->getSourceWalletNumber(),
                $request->getDestinationWalletNumber(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletTransaction::STATUS_COMPLETED,
                $sourceWallet->getAvailableBalance(),
                $destinationWallet->getAvailableBalance(),
                new \DateTimeImmutable(),
                $request->getMetadata()
            );

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Wallet transfer failed', [
                'source_wallet' => $request->getSourceWalletNumber(),
                'destination_wallet' => $request->getDestinationWalletNumber(),
                'error' => $e->getMessage()
            ]);

            return WalletTransferResponseDTO::failure(
                $request->getSourceWalletNumber(),
                $request->getDestinationWalletNumber(),
                $request->getAmount(),
                $request->getCurrency(),
                WalletException::ERROR_TRANSFER_FAILED,
                'Transfer processing failed: ' . $e->getMessage()
            );
        }
    }

    public function topUpWallet(User $user, WalletTopUpRequestDTO $request): WalletTopUpResponseDTO
    {
        $this->logger->info('Processing wallet top-up', [
            'user_id' => $user->getId(),
            'wallet_number' => $request->getWalletNumber(),
            'amount' => $request->getAmount(),
            'payment_method' => $request->getPaymentMethod()
        ]);

        if (!$this->isEnabled()) {
            return WalletTopUpResponseDTO::failure(
                $request->getWalletNumber(),
                $request->getAmount(),
                $request->getCurrency(),
                $request->getPaymentMethod(),
                WalletException::ERROR_TOP_UP_FAILED,
                'Wallet service is disabled'
            );
        }

        if (!$this->validateAmount($request->getAmount(), $request->getCurrency())) {
            return WalletTopUpResponseDTO::failure(
                $request->getWalletNumber(),
                $request->getAmount(),
                $request->getCurrency(),
                $request->getPaymentMethod(),
                WalletException::ERROR_INVALID_AMOUNT,
                'Invalid top-up amount'
            );
        }

        $wallet = $this->walletRepository->findByWalletNumber($request->getWalletNumber());
        if ($wallet === null || $wallet->getUser()->getId() !== $user->getId()) {
            return WalletTopUpResponseDTO::failure(
                $request->getWalletNumber(),
                $request->getAmount(),
                $request->getCurrency(),
                $request->getPaymentMethod(),
                WalletException::ERROR_WALLET_NOT_FOUND,
                'Wallet not found or not owned by user'
            );
        }

        if (!$wallet->isActive()) {
            return WalletTopUpResponseDTO::failure(
                $request->getWalletNumber(),
                $request->getAmount(),
                $request->getCurrency(),
                $request->getPaymentMethod(),
                WalletException::ERROR_WALLET_INACTIVE,
                'Wallet is not active'
            );
        }

        // This is a placeholder for top-up logic
        // In a real implementation, this would integrate with PayNow or other payment gateways
        try {
            // Create top-up record with pending status
            // The actual payment processing would be handled by payment gateway integration
            
            $this->logger->info('Wallet top-up initiated', [
                'wallet_number' => $request->getWalletNumber(),
                'amount' => $request->getAmount()
            ]);

            return WalletTopUpResponseDTO::success(
                'TUP_' . strtoupper(bin2hex(random_bytes(8))) . '_' . time(),
                $request->getWalletNumber(),
                $request->getAmount(),
                $request->getCurrency(),
                $request->getPaymentMethod(),
                'pending',
                null, // Payment URL would be provided by payment gateway
                $request->getExpiresAt(),
                new \DateTimeImmutable(),
                $request->getMetadata()
            );

        } catch (\Exception $e) {
            $this->logger->error('Wallet top-up failed', [
                'wallet_number' => $request->getWalletNumber(),
                'error' => $e->getMessage()
            ]);

            return WalletTopUpResponseDTO::failure(
                $request->getWalletNumber(),
                $request->getAmount(),
                $request->getCurrency(),
                $request->getPaymentMethod(),
                WalletException::ERROR_TOP_UP_FAILED,
                'Top-up initiation failed: ' . $e->getMessage()
            );
        }
    }

    public function getWalletStatus(User $user): WalletStatusDTO
    {
        $wallet = $this->walletRepository->findByUser($user);
        if ($wallet === null) {
            throw WalletException::walletNotFound('User ID: ' . $user->getId());
        }

        $today = new \DateTimeImmutable();
        $dailySpent = $this->walletTransactionRepository->getDailyTransactionAmount($wallet, $today);
        $monthlySpent = $this->walletTransactionRepository->getMonthlyTransactionAmount($wallet, $today);

        return new WalletStatusDTO(
            $wallet->getWalletNumber(),
            $wallet->getStatus(),
            $wallet->getBalance(),
            $wallet->getAvailableBalance(),
            $wallet->getReservedBalance(),
            $wallet->getCurrency(),
            $wallet->getLowBalanceThreshold(),
            $wallet->isLowBalance(),
            $wallet->isLowBalanceNotificationSent(),
            $wallet->getDailyTransactionLimit(),
            $wallet->getMonthlyTransactionLimit(),
            $dailySpent,
            $monthlySpent,
            $wallet->getFreezeReason(),
            $wallet->getLastTransactionAt(),
            $wallet->getCreatedAt(),
            $wallet->getSecuritySettings(),
            $wallet->getMetadata()
        );
    }

    public function getTransaction(string $transactionId): WalletTransaction
    {
        $transaction = $this->walletTransactionRepository->findByTransactionId($transactionId);
        if ($transaction === null) {
            throw WalletException::transactionNotFound($transactionId);
        }

        return $transaction;
    }

    public function reverseTransaction(
        string $transactionId,
        string $amount,
        ?string $reason = null
    ): WalletTransaction {
        $this->logger->info('Reversing wallet transaction', [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'reason' => $reason
        ]);

        $originalTransaction = $this->walletTransactionRepository->findByTransactionId($transactionId);
        if ($originalTransaction === null) {
            throw WalletException::transactionNotFound($transactionId);
        }

        if (!$originalTransaction->canBeReversed()) {
            throw WalletException::reversalFailed(
                'Transaction cannot be reversed',
                ['status' => $originalTransaction->getStatus()]
            );
        }

        if (!$this->validateAmount($amount, $originalTransaction->getCurrency())) {
            throw WalletException::invalidAmount($amount, $originalTransaction->getCurrency());
        }

        if ((float)$amount > (float)$originalTransaction->getAmount()) {
            throw WalletException::reversalFailed('Reversal amount cannot exceed original transaction amount');
        }

        try {
            $this->entityManager->beginTransaction();

            $wallet = $originalTransaction->getWallet();
            $balanceBefore = $wallet->getBalance();

            // Create reversal transaction
            $reversalTransaction = new WalletTransaction();
            $reversalTransaction->setWallet($wallet)
                              ->setTransactionType(WalletTransaction::TYPE_REVERSAL)
                              ->setCategory($originalTransaction->getCategory())
                              ->setAmount($amount)
                              ->setCurrency($originalTransaction->getCurrency())
                              ->setDescription(
                                  sprintf('Reversal for transaction %s: %s', $transactionId, $reason ?? 'No reason provided')
                              )
                              ->setOriginalTransactionId($transactionId)
                              ->setBalanceBefore($balanceBefore)
                              ->setStatus(WalletTransaction::STATUS_COMPLETED);

            // Process reversal based on original transaction type
            if ($originalTransaction->isDebit() || $originalTransaction->transactionType === WalletTransaction::TYPE_TRANSFER_OUT) {
                // Credit back to wallet
                $wallet->creditBalance($amount);
            } elseif ($originalTransaction->isCredit() || $originalTransaction->transactionType === WalletTransaction::TYPE_TRANSFER_IN) {
                // Debit from wallet
                if (!$wallet->debitBalance($amount)) {
                    $this->entityManager->rollback();
                    throw WalletException::reversalFailed('Insufficient balance for reversal');
                }
            }

            $reversalTransaction->setBalanceAfter($wallet->getBalance());

            // Mark original transaction as reversed
            $originalTransaction->setStatus(WalletTransaction::STATUS_REVERSED);

            $this->walletTransactionRepository->save($reversalTransaction, false);
            $this->walletTransactionRepository->save($originalTransaction, false);
            $this->walletRepository->save($wallet, true);

            $this->entityManager->commit();

            $this->logger->info('Wallet transaction reversed successfully', [
                'reversal_transaction_id' => $reversalTransaction->getTransactionId(),
                'original_transaction_id' => $transactionId,
                'amount' => $amount
            ]);

            return $reversalTransaction;

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Wallet transaction reversal failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            throw WalletException::reversalFailed(
                'Reversal processing failed: ' . $e->getMessage()
            );
        }
    }

    public function freezeWallet(User $user, string $reason): Wallet
    {
        $this->logger->info('Freezing wallet', [
            'user_id' => $user->getId(),
            'reason' => $reason
        ]);

        $wallet = $this->walletRepository->findByUser($user);
        if ($wallet === null) {
            throw WalletException::walletNotFound('User ID: ' . $user->getId());
        }

        if ($wallet->isFrozen()) {
            throw WalletException::freezeFailed($wallet->getWalletNumber(), 'Wallet is already frozen');
        }

        $wallet->freezeWallet($reason);
        $this->walletRepository->save($wallet, true);

        $this->logger->info('Wallet frozen successfully', [
            'wallet_number' => $wallet->getWalletNumber(),
            'reason' => $reason
        ]);

        return $wallet;
    }

    public function unfreezeWallet(User $user): Wallet
    {
        $this->logger->info('Unfreezing wallet', [
            'user_id' => $user->getId()
        ]);

        $wallet = $this->walletRepository->findByUser($user);
        if ($wallet === null) {
            throw WalletException::walletNotFound('User ID: ' . $user->getId());
        }

        if (!$wallet->isFrozen()) {
            throw WalletException::unfreezeFailed($wallet->getWalletNumber(), 'Wallet is not frozen');
        }

        $wallet->unfreezeWallet();
        $this->walletRepository->save($wallet, true);

        $this->logger->info('Wallet unfrozen successfully', [
            'wallet_number' => $wallet->getWalletNumber()
        ]);

        return $wallet;
    }

    public function suspendWallet(User $user, string $reason): Wallet
    {
        $this->logger->info('Suspending wallet', [
            'user_id' => $user->getId(),
            'reason' => $reason
        ]);

        $wallet = $this->walletRepository->findByUser($user);
        if ($wallet === null) {
            throw WalletException::walletNotFound('User ID: ' . $user->getId());
        }

        if ($wallet->isSuspended()) {
            throw WalletException::walletSuspended($wallet->getWalletNumber());
        }

        $wallet->setStatus(Wallet::STATUS_SUSPENDED);
        
        $metadata = $wallet->getMetadata() ?? [];
        $metadata['suspension_reason'] = $reason;
        $metadata['suspended_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $wallet->setMetadata($metadata);

        $this->walletRepository->save($wallet, true);

        $this->logger->info('Wallet suspended successfully', [
            'wallet_number' => $wallet->getWalletNumber(),
            'reason' => $reason
        ]);

        return $wallet;
    }

    public function closeWallet(User $user, string $reason): Wallet
    {
        $this->logger->info('Closing wallet', [
            'user_id' => $user->getId(),
            'reason' => $reason
        ]);

        $wallet = $this->walletRepository->findByUser($user);
        if ($wallet === null) {
            throw WalletException::walletNotFound('User ID: ' . $user->getId());
        }

        if ($wallet->isClosed()) {
            throw WalletException::walletClosed($wallet->getWalletNumber());
        }

        // Check if wallet has balance
        if ((float)$wallet->getBalance() > 0) {
            throw WalletException::paymentFailed(
                'Cannot close wallet with remaining balance',
                [
                    'balance' => $wallet->getBalance(),
                    'currency' => $wallet->getCurrency()
                ]
            );
        }

        $wallet->setStatus(Wallet::STATUS_CLOSED);
        
        $metadata = $wallet->getMetadata() ?? [];
        $metadata['closure_reason'] = $reason;
        $metadata['closed_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $wallet->setMetadata($metadata);

        $this->walletRepository->save($wallet, true);

        $this->logger->info('Wallet closed successfully', [
            'wallet_number' => $wallet->getWalletNumber(),
            'reason' => $reason
        ]);

        return $wallet;
    }

    public function updateTransactionLimits(
        User $user,
        ?string $dailyLimit = null,
        ?string $monthlyLimit = null
    ): Wallet {
        $wallet = $this->walletRepository->findByUser($user);
        if ($wallet === null) {
            throw WalletException::walletNotFound('User ID: ' . $user->getId());
        }

        if ($dailyLimit !== null) {
            if (!$this->validateAmount($dailyLimit, $wallet->getCurrency())) {
                throw WalletException::invalidAmount($dailyLimit, $wallet->getCurrency());
            }
            $wallet->setDailyTransactionLimit($dailyLimit);
        }

        if ($monthlyLimit !== null) {
            if (!$this->validateAmount($monthlyLimit, $wallet->getCurrency())) {
                throw WalletException::invalidAmount($monthlyLimit, $wallet->getCurrency());
            }
            $wallet->setMonthlyTransactionLimit($monthlyLimit);
        }

        $this->walletRepository->save($wallet, true);

        $this->logger->info('Wallet transaction limits updated', [
            'wallet_number' => $wallet->getWalletNumber(),
            'daily_limit' => $dailyLimit,
            'monthly_limit' => $monthlyLimit
        ]);

        return $wallet;
    }

    public function updateLowBalanceSettings(
        User $user,
        string $threshold,
        bool $resetNotification = false
    ): Wallet {
        $wallet = $this->walletRepository->findByUser($user);
        if ($wallet === null) {
            throw WalletException::walletNotFound('User ID: ' . $user->getId());
        }

        if (!$this->validateAmount($threshold, $wallet->getCurrency())) {
            throw WalletException::invalidAmount($threshold, $wallet->getCurrency());
        }

        $wallet->setLowBalanceThreshold($threshold);
        
        if ($resetNotification) {
            $wallet->setLowBalanceNotificationSent(false);
        }

        $this->walletRepository->save($wallet, true);

        $this->logger->info('Wallet low balance settings updated', [
            'wallet_number' => $wallet->getWalletNumber(),
            'threshold' => $threshold,
            'reset_notification' => $resetNotification
        ]);

        return $wallet;
    }

    public function canMakePayment(User $user, string $amount, string $currency = 'PLN'): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        if (!$this->validateAmount($amount, $currency)) {
            return false;
        }

        $wallet = $this->walletRepository->findActiveByUser($user);
        if ($wallet === null) {
            return false;
        }

        return $wallet->canMakeTransaction($amount);
    }

    public function validateAmount(string $amount, string $currency = 'PLN'): bool
    {
        if (!in_array($currency, self::SUPPORTED_CURRENCIES)) {
            return false;
        }

        $numericAmount = (float)$amount;
        $minAmount = (float)(self::MIN_AMOUNTS[$currency] ?? '0.01');
        $maxAmount = (float)(self::MAX_AMOUNTS[$currency] ?? '100000.00');

        return $numericAmount >= $minAmount && $numericAmount <= $maxAmount && $numericAmount > 0;
    }

    public function getSupportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    public function getMinimumAmount(string $currency = 'PLN'): string
    {
        return self::MIN_AMOUNTS[$currency] ?? '0.01';
    }

    public function getMaximumAmount(string $currency = 'PLN'): string
    {
        return self::MAX_AMOUNTS[$currency] ?? '100000.00';
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}