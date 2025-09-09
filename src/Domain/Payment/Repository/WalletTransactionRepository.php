<?php

declare(strict_types=1);

namespace App\Domain\Payment\Repository;

use App\Domain\Payment\Entity\Wallet;
use App\Domain\Payment\Entity\WalletTransaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WalletTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WalletTransaction::class);
    }

    public function save(WalletTransaction $transaction, bool $flush = false): void
    {
        $this->getEntityManager()->persist($transaction);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WalletTransaction $transaction, bool $flush = false): void
    {
        $this->getEntityManager()->remove($transaction);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByTransactionId(string $transactionId): ?WalletTransaction
    {
        return $this->createQueryBuilder('wt')
            ->andWhere('wt.transactionId = :transactionId')
            ->setParameter('transactionId', $transactionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByWallet(Wallet $wallet, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('wt')
            ->andWhere('wt.wallet = :wallet')
            ->setParameter('wallet', $wallet)
            ->orderBy('wt.createdAt', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByWalletAndStatus(Wallet $wallet, string $status): array
    {
        return $this->createQueryBuilder('wt')
            ->andWhere('wt.wallet = :wallet')
            ->andWhere('wt.status = :status')
            ->setParameter('wallet', $wallet)
            ->setParameter('status', $status)
            ->orderBy('wt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByWalletAndType(Wallet $wallet, string $transactionType): array
    {
        return $this->createQueryBuilder('wt')
            ->andWhere('wt.wallet = :wallet')
            ->andWhere('wt.transactionType = :transactionType')
            ->setParameter('wallet', $wallet)
            ->setParameter('transactionType', $transactionType)
            ->orderBy('wt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByWalletAndCategory(Wallet $wallet, string $category): array
    {
        return $this->createQueryBuilder('wt')
            ->andWhere('wt.wallet = :wallet')
            ->andWhere('wt.category = :category')
            ->setParameter('wallet', $wallet)
            ->setParameter('category', $category)
            ->orderBy('wt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByWalletAndDateRange(
        Wallet $wallet,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('wt')
            ->andWhere('wt.wallet = :wallet')
            ->andWhere('wt.createdAt >= :startDate')
            ->andWhere('wt.createdAt <= :endDate')
            ->setParameter('wallet', $wallet)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('wt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('wt')
            ->andWhere('wt.status = :status')
            ->setParameter('status', $status)
            ->orderBy('wt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByTransactionType(string $transactionType): array
    {
        return $this->createQueryBuilder('wt')
            ->andWhere('wt.transactionType = :transactionType')
            ->setParameter('transactionType', $transactionType)
            ->orderBy('wt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingTransactions(): array
    {
        return $this->createQueryBuilder('wt')
            ->andWhere('wt.status = :status')
            ->setParameter('status', WalletTransaction::STATUS_PENDING)
            ->orderBy('wt.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findProcessingTransactions(): array
    {
        return $this->createQueryBuilder('wt')
            ->andWhere('wt.status = :status')
            ->setParameter('status', WalletTransaction::STATUS_PROCESSING)
            ->orderBy('wt.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findFailedTransactions(\DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('wt')
            ->andWhere('wt.status IN (:statuses)')
            ->setParameter('statuses', [WalletTransaction::STATUS_FAILED, WalletTransaction::STATUS_CANCELLED])
            ->orderBy('wt.createdAt', 'DESC');

        if ($since !== null) {
            $qb->andWhere('wt.createdAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    public function findTransactionsByUser(User $user, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('wt')
            ->join('wt.wallet', 'w')
            ->andWhere('w.user = :user')
            ->setParameter('user', $user)
            ->orderBy('wt.createdAt', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findTransfersBetweenWallets(Wallet $sourceWallet, Wallet $destinationWallet): array
    {
        return $this->createQueryBuilder('wt')
            ->andWhere('(wt.sourceWallet = :sourceWallet AND wt.destinationWallet = :destinationWallet)')
            ->orWhere('(wt.sourceWallet = :destinationWallet AND wt.destinationWallet = :sourceWallet)')
            ->setParameter('sourceWallet', $sourceWallet)
            ->setParameter('destinationWallet', $destinationWallet)
            ->orderBy('wt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getDailyTransactionAmount(Wallet $wallet, \DateTimeInterface $date): string
    {
        $startOfDay = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
        $endOfDay = $startOfDay->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('wt')
            ->select('SUM(CAST(wt.amount AS DECIMAL(15,2)))')
            ->andWhere('wt.wallet = :wallet')
            ->andWhere('wt.status = :status')
            ->andWhere('wt.transactionType IN (:debitTypes)')
            ->andWhere('wt.createdAt >= :startOfDay')
            ->andWhere('wt.createdAt <= :endOfDay')
            ->setParameter('wallet', $wallet)
            ->setParameter('status', WalletTransaction::STATUS_COMPLETED)
            ->setParameter('debitTypes', [
                WalletTransaction::TYPE_DEBIT,
                WalletTransaction::TYPE_TRANSFER_OUT,
                WalletTransaction::TYPE_WITHDRAWAL
            ])
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->getQuery()
            ->getSingleScalarResult();

        return number_format($result ?? 0, 2, '.', '');
    }

    public function getMonthlyTransactionAmount(Wallet $wallet, \DateTimeInterface $date): string
    {
        $startOfMonth = \DateTimeImmutable::createFromInterface($date)->modify('first day of this month')->setTime(0, 0, 0);
        $endOfMonth = $startOfMonth->modify('last day of this month')->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('wt')
            ->select('SUM(CAST(wt.amount AS DECIMAL(15,2)))')
            ->andWhere('wt.wallet = :wallet')
            ->andWhere('wt.status = :status')
            ->andWhere('wt.transactionType IN (:debitTypes)')
            ->andWhere('wt.createdAt >= :startOfMonth')
            ->andWhere('wt.createdAt <= :endOfMonth')
            ->setParameter('wallet', $wallet)
            ->setParameter('status', WalletTransaction::STATUS_COMPLETED)
            ->setParameter('debitTypes', [
                WalletTransaction::TYPE_DEBIT,
                WalletTransaction::TYPE_TRANSFER_OUT,
                WalletTransaction::TYPE_WITHDRAWAL
            ])
            ->setParameter('startOfMonth', $startOfMonth)
            ->setParameter('endOfMonth', $endOfMonth)
            ->getQuery()
            ->getSingleScalarResult();

        return number_format($result ?? 0, 2, '.', '');
    }

    public function getTotalTransactionVolume(
        ?Wallet $wallet = null,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null,
        ?string $currency = null
    ): string {
        $qb = $this->createQueryBuilder('wt')
            ->select('SUM(CAST(wt.amount AS DECIMAL(15,2)))')
            ->andWhere('wt.status = :status')
            ->setParameter('status', WalletTransaction::STATUS_COMPLETED);

        if ($wallet !== null) {
            $qb->andWhere('wt.wallet = :wallet')
               ->setParameter('wallet', $wallet);
        }

        if ($startDate !== null) {
            $qb->andWhere('wt.createdAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('wt.createdAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        if ($currency !== null) {
            $qb->andWhere('wt.currency = :currency')
               ->setParameter('currency', $currency);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        return number_format($result ?? 0, 2, '.', '');
    }

    public function countTransactionsByStatus(): array
    {
        $results = $this->createQueryBuilder('wt')
            ->select('wt.status, COUNT(wt.id) as count')
            ->groupBy('wt.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['status']] = (int)$result['count'];
        }

        return $counts;
    }

    public function countTransactionsByType(): array
    {
        $results = $this->createQueryBuilder('wt')
            ->select('wt.transactionType, COUNT(wt.id) as count')
            ->groupBy('wt.transactionType')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['transactionType']] = (int)$result['count'];
        }

        return $counts;
    }

    public function countTransactionsByCategory(): array
    {
        $results = $this->createQueryBuilder('wt')
            ->select('wt.category, COUNT(wt.id) as count')
            ->groupBy('wt.category')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['category']] = (int)$result['count'];
        }

        return $counts;
    }

    public function getTransactionStatistics(\DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('wt');

        if ($startDate !== null) {
            $qb->andWhere('wt.createdAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('wt.createdAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $totalTransactions = $qb->select('COUNT(wt.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $completedTransactions = (clone $qb)->select('COUNT(wt.id)')
            ->andWhere('wt.status = :status')
            ->setParameter('status', WalletTransaction::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        $totalVolume = $this->getTotalTransactionVolume(null, $startDate, $endDate);

        $statusCounts = $this->countTransactionsByStatus();
        $typeCounts = $this->countTransactionsByType();
        $categoryCounts = $this->countTransactionsByCategory();

        // Average transaction amount
        $avgAmount = (clone $qb)->select('AVG(CAST(wt.amount AS DECIMAL(15,2)))')
            ->andWhere('wt.status = :status')
            ->setParameter('status', WalletTransaction::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total_transactions' => (int)$totalTransactions,
            'completed_transactions' => (int)$completedTransactions,
            'total_volume' => $totalVolume,
            'average_amount' => number_format($avgAmount ?? 0, 2, '.', ''),
            'status_breakdown' => $statusCounts,
            'type_breakdown' => $typeCounts,
            'category_breakdown' => $categoryCounts
        ];
    }

    public function findReversibleTransactions(Wallet $wallet): array
    {
        return $this->createQueryBuilder('wt')
            ->andWhere('wt.wallet = :wallet')
            ->andWhere('wt.status = :status')
            ->andWhere('wt.transactionType NOT IN (:excludedTypes)')
            ->setParameter('wallet', $wallet)
            ->setParameter('status', WalletTransaction::STATUS_COMPLETED)
            ->setParameter('excludedTypes', [
                WalletTransaction::TYPE_REVERSAL,
                WalletTransaction::TYPE_ADJUSTMENT
            ])
            ->orderBy('wt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findTransactionHistory(
        User $user,
        ?string $transactionType = null,
        ?string $status = null,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null,
        ?int $limit = null,
        int $offset = 0
    ): array {
        $qb = $this->createQueryBuilder('wt')
            ->join('wt.wallet', 'w')
            ->andWhere('w.user = :user')
            ->setParameter('user', $user);

        if ($transactionType !== null) {
            $qb->andWhere('wt.transactionType = :transactionType')
               ->setParameter('transactionType', $transactionType);
        }

        if ($status !== null) {
            $qb->andWhere('wt.status = :status')
               ->setParameter('status', $status);
        }

        if ($startDate !== null) {
            $qb->andWhere('wt.createdAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('wt.createdAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $qb->orderBy('wt.createdAt', 'DESC')
           ->setFirstResult($offset);

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function getWalletBalance(Wallet $wallet): string
    {
        $creditSum = $this->createQueryBuilder('wt')
            ->select('SUM(CAST(wt.amount AS DECIMAL(15,2)))')
            ->andWhere('wt.wallet = :wallet')
            ->andWhere('wt.status = :status')
            ->andWhere('wt.transactionType IN (:creditTypes)')
            ->setParameter('wallet', $wallet)
            ->setParameter('status', WalletTransaction::STATUS_COMPLETED)
            ->setParameter('creditTypes', [
                WalletTransaction::TYPE_CREDIT,
                WalletTransaction::TYPE_TRANSFER_IN,
                WalletTransaction::TYPE_TOP_UP,
                WalletTransaction::TYPE_REFUND
            ])
            ->getQuery()
            ->getSingleScalarResult();

        $debitSum = $this->createQueryBuilder('wt')
            ->select('SUM(CAST(wt.amount AS DECIMAL(15,2)))')
            ->andWhere('wt.wallet = :wallet')
            ->andWhere('wt.status = :status')
            ->andWhere('wt.transactionType IN (:debitTypes)')
            ->setParameter('wallet', $wallet)
            ->setParameter('status', WalletTransaction::STATUS_COMPLETED)
            ->setParameter('debitTypes', [
                WalletTransaction::TYPE_DEBIT,
                WalletTransaction::TYPE_TRANSFER_OUT,
                WalletTransaction::TYPE_WITHDRAWAL,
                WalletTransaction::TYPE_FEE
            ])
            ->getQuery()
            ->getSingleScalarResult();

        $balance = (float)($creditSum ?? 0) - (float)($debitSum ?? 0);
        return number_format(max(0, $balance), 2, '.', '');
    }
}