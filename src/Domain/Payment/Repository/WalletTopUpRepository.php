<?php

declare(strict_types=1);

namespace App\Domain\Payment\Repository;

use App\Domain\Payment\Entity\Wallet;
use App\Domain\Payment\Entity\WalletTopUp;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WalletTopUpRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WalletTopUp::class);
    }

    public function save(WalletTopUp $topUp, bool $flush = false): void
    {
        $this->getEntityManager()->persist($topUp);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WalletTopUp $topUp, bool $flush = false): void
    {
        $this->getEntityManager()->remove($topUp);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByTopUpId(string $topUpId): ?WalletTopUp
    {
        return $this->createQueryBuilder('wtu')
            ->andWhere('wtu.topUpId = :topUpId')
            ->setParameter('topUpId', $topUpId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByWallet(Wallet $wallet, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('wtu')
            ->andWhere('wtu.wallet = :wallet')
            ->setParameter('wallet', $wallet)
            ->orderBy('wtu.createdAt', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByWalletAndStatus(Wallet $wallet, string $status): array
    {
        return $this->createQueryBuilder('wtu')
            ->andWhere('wtu.wallet = :wallet')
            ->andWhere('wtu.status = :status')
            ->setParameter('wallet', $wallet)
            ->setParameter('status', $status)
            ->orderBy('wtu.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByUser(User $user, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('wtu')
            ->join('wtu.wallet', 'w')
            ->andWhere('w.user = :user')
            ->setParameter('user', $user)
            ->orderBy('wtu.createdAt', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('wtu')
            ->andWhere('wtu.status = :status')
            ->setParameter('status', $status)
            ->orderBy('wtu.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByPaymentMethod(string $paymentMethod): array
    {
        return $this->createQueryBuilder('wtu')
            ->andWhere('wtu.paymentMethod = :paymentMethod')
            ->setParameter('paymentMethod', $paymentMethod)
            ->orderBy('wtu.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingTopUps(): array
    {
        return $this->createQueryBuilder('wtu')
            ->andWhere('wtu.status = :status')
            ->setParameter('status', WalletTopUp::STATUS_PENDING)
            ->orderBy('wtu.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findProcessingTopUps(): array
    {
        return $this->createQueryBuilder('wtu')
            ->andWhere('wtu.status = :status')
            ->setParameter('status', WalletTopUp::STATUS_PROCESSING)
            ->orderBy('wtu.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findExpiredTopUps(): array
    {
        return $this->createQueryBuilder('wtu')
            ->andWhere('wtu.status IN (:statuses)')
            ->andWhere('wtu.expiresAt < :now')
            ->setParameter('statuses', [WalletTopUp::STATUS_PENDING, WalletTopUp::STATUS_PROCESSING])
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('wtu.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findExpiringTopUps(int $minutesUntilExpiry = 15): array
    {
        $expiryTime = new \DateTimeImmutable(sprintf('+%d minutes', $minutesUntilExpiry));

        return $this->createQueryBuilder('wtu')
            ->andWhere('wtu.status IN (:statuses)')
            ->andWhere('wtu.expiresAt <= :expiryTime')
            ->andWhere('wtu.expiresAt > :now')
            ->setParameter('statuses', [WalletTopUp::STATUS_PENDING, WalletTopUp::STATUS_PROCESSING])
            ->setParameter('expiryTime', $expiryTime)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('wtu.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findCompletedTopUps(\DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('wtu')
            ->andWhere('wtu.status = :status')
            ->setParameter('status', WalletTopUp::STATUS_COMPLETED)
            ->orderBy('wtu.completedAt', 'DESC');

        if ($since !== null) {
            $qb->andWhere('wtu.completedAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    public function findFailedTopUps(\DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('wtu')
            ->andWhere('wtu.status IN (:statuses)')
            ->setParameter('statuses', [
                WalletTopUp::STATUS_FAILED,
                WalletTopUp::STATUS_CANCELLED,
                WalletTopUp::STATUS_EXPIRED
            ])
            ->orderBy('wtu.failedAt', 'DESC');

        if ($since !== null) {
            $qb->andWhere('wtu.failedAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByDateRange(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $status = null
    ): array {
        $qb = $this->createQueryBuilder('wtu')
            ->andWhere('wtu.createdAt >= :startDate')
            ->andWhere('wtu.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('wtu.createdAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('wtu.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByExternalTransactionId(string $externalTransactionId): ?WalletTopUp
    {
        return $this->createQueryBuilder('wtu')
            ->andWhere('wtu.externalTransactionId = :externalTransactionId')
            ->setParameter('externalTransactionId', $externalTransactionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getTotalTopUpAmount(
        ?Wallet $wallet = null,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null,
        ?string $currency = null
    ): string {
        $qb = $this->createQueryBuilder('wtu')
            ->select('SUM(CAST(wtu.amount AS DECIMAL(10,2)))')
            ->andWhere('wtu.status = :status')
            ->setParameter('status', WalletTopUp::STATUS_COMPLETED);

        if ($wallet !== null) {
            $qb->andWhere('wtu.wallet = :wallet')
               ->setParameter('wallet', $wallet);
        }

        if ($startDate !== null) {
            $qb->andWhere('wtu.completedAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('wtu.completedAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        if ($currency !== null) {
            $qb->andWhere('wtu.currency = :currency')
               ->setParameter('currency', $currency);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        return number_format($result ?? 0, 2, '.', '');
    }

    public function getTotalFeeAmount(
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null,
        ?string $currency = null
    ): string {
        $qb = $this->createQueryBuilder('wtu')
            ->select('SUM(CAST(wtu.feeAmount AS DECIMAL(10,2)))')
            ->andWhere('wtu.status = :status')
            ->andWhere('wtu.feeAmount IS NOT NULL')
            ->setParameter('status', WalletTopUp::STATUS_COMPLETED);

        if ($startDate !== null) {
            $qb->andWhere('wtu.completedAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('wtu.completedAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        if ($currency !== null) {
            $qb->andWhere('wtu.currency = :currency')
               ->setParameter('currency', $currency);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        return number_format($result ?? 0, 2, '.', '');
    }

    public function countTopUpsByStatus(): array
    {
        $results = $this->createQueryBuilder('wtu')
            ->select('wtu.status, COUNT(wtu.id) as count')
            ->groupBy('wtu.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['status']] = (int)$result['count'];
        }

        return $counts;
    }

    public function countTopUpsByPaymentMethod(): array
    {
        $results = $this->createQueryBuilder('wtu')
            ->select('wtu.paymentMethod, COUNT(wtu.id) as count')
            ->groupBy('wtu.paymentMethod')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['paymentMethod']] = (int)$result['count'];
        }

        return $counts;
    }

    public function getTopUpStatistics(\DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('wtu');

        if ($startDate !== null) {
            $qb->andWhere('wtu.createdAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('wtu.createdAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $totalTopUps = $qb->select('COUNT(wtu.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $completedTopUps = (clone $qb)->select('COUNT(wtu.id)')
            ->andWhere('wtu.status = :status')
            ->setParameter('status', WalletTopUp::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        $totalAmount = $this->getTotalTopUpAmount(null, $startDate, $endDate);
        $totalFees = $this->getTotalFeeAmount($startDate, $endDate);

        $statusCounts = $this->countTopUpsByStatus();
        $methodCounts = $this->countTopUpsByPaymentMethod();

        // Average top-up amount
        $avgAmount = (clone $qb)->select('AVG(CAST(wtu.amount AS DECIMAL(10,2)))')
            ->andWhere('wtu.status = :status')
            ->setParameter('status', WalletTopUp::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        // Success rate
        $successRate = $totalTopUps > 0 ? ($completedTopUps / $totalTopUps) * 100 : 0;

        return [
            'total_top_ups' => (int)$totalTopUps,
            'completed_top_ups' => (int)$completedTopUps,
            'success_rate' => number_format($successRate, 2, '.', ''),
            'total_amount' => $totalAmount,
            'total_fees' => $totalFees,
            'average_amount' => number_format($avgAmount ?? 0, 2, '.', ''),
            'status_breakdown' => $statusCounts,
            'payment_method_breakdown' => $methodCounts
        ];
    }

    public function findRecentTopUpsByUser(User $user, int $days = 30): array
    {
        $cutoffDate = new \DateTimeImmutable(sprintf('-%d days', $days));

        return $this->createQueryBuilder('wtu')
            ->join('wtu.wallet', 'w')
            ->andWhere('w.user = :user')
            ->andWhere('wtu.createdAt >= :cutoffDate')
            ->setParameter('user', $user)
            ->setParameter('cutoffDate', $cutoffDate)
            ->orderBy('wtu.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getDailyTopUpAmount(User $user, \DateTimeInterface $date): string
    {
        $startOfDay = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
        $endOfDay = $startOfDay->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('wtu')
            ->select('SUM(CAST(wtu.amount AS DECIMAL(10,2)))')
            ->join('wtu.wallet', 'w')
            ->andWhere('w.user = :user')
            ->andWhere('wtu.status = :status')
            ->andWhere('wtu.createdAt >= :startOfDay')
            ->andWhere('wtu.createdAt <= :endOfDay')
            ->setParameter('user', $user)
            ->setParameter('status', WalletTopUp::STATUS_COMPLETED)
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->getQuery()
            ->getSingleScalarResult();

        return number_format($result ?? 0, 2, '.', '');
    }

    public function getMonthlyTopUpAmount(User $user, \DateTimeInterface $date): string
    {
        $startOfMonth = \DateTimeImmutable::createFromInterface($date)->modify('first day of this month')->setTime(0, 0, 0);
        $endOfMonth = $startOfMonth->modify('last day of this month')->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('wtu')
            ->select('SUM(CAST(wtu.amount AS DECIMAL(10,2)))')
            ->join('wtu.wallet', 'w')
            ->andWhere('w.user = :user')
            ->andWhere('wtu.status = :status')
            ->andWhere('wtu.createdAt >= :startOfMonth')
            ->andWhere('wtu.createdAt <= :endOfMonth')
            ->setParameter('user', $user)
            ->setParameter('status', WalletTopUp::STATUS_COMPLETED)
            ->setParameter('startOfMonth', $startOfMonth)
            ->setParameter('endOfMonth', $endOfMonth)
            ->getQuery()
            ->getSingleScalarResult();

        return number_format($result ?? 0, 2, '.', '');
    }
}