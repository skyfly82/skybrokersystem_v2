<?php

declare(strict_types=1);

namespace App\Domain\Payment\Repository;

use App\Domain\Payment\Entity\Wallet;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WalletRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wallet::class);
    }

    public function save(Wallet $wallet, bool $flush = false): void
    {
        $this->getEntityManager()->persist($wallet);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Wallet $wallet, bool $flush = false): void
    {
        $this->getEntityManager()->remove($wallet);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByUser(User $user): ?Wallet
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveByUser(User $user): ?Wallet
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.user = :user')
            ->andWhere('w.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', Wallet::STATUS_ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByWalletNumber(string $walletNumber): ?Wallet
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.walletNumber = :walletNumber')
            ->setParameter('walletNumber', $walletNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.status = :status')
            ->setParameter('status', $status)
            ->orderBy('w.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByStatusAndCurrency(string $status, string $currency): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.status = :status')
            ->andWhere('w.currency = :currency')
            ->setParameter('status', $status)
            ->setParameter('currency', $currency)
            ->orderBy('w.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findWalletsWithLowBalance(): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.status = :status')
            ->andWhere('CAST(w.availableBalance AS DECIMAL(15,2)) <= CAST(w.lowBalanceThreshold AS DECIMAL(15,2))')
            ->andWhere('w.lowBalanceNotificationSent = :notificationSent')
            ->setParameter('status', Wallet::STATUS_ACTIVE)
            ->setParameter('notificationSent', false)
            ->orderBy('w.availableBalance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findInactiveWallets(int $inactiveDays = 365): array
    {
        $cutoffDate = new \DateTimeImmutable(sprintf('-%d days', $inactiveDays));

        return $this->createQueryBuilder('w')
            ->andWhere('w.status = :status')
            ->andWhere('(w.lastTransactionAt IS NULL AND w.createdAt < :cutoffDate) OR w.lastTransactionAt < :cutoffDate')
            ->setParameter('status', Wallet::STATUS_ACTIVE)
            ->setParameter('cutoffDate', $cutoffDate)
            ->orderBy('w.lastTransactionAt', 'ASC NULLS FIRST')
            ->getQuery()
            ->getResult();
    }

    public function findWalletsWithReservedFunds(): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('CAST(w.reservedBalance AS DECIMAL(15,2)) > 0')
            ->orderBy('w.reservedBalance', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findFrozenWallets(?\DateTimeInterface $frozenSince = null): array
    {
        $qb = $this->createQueryBuilder('w')
            ->andWhere('w.status = :status')
            ->setParameter('status', Wallet::STATUS_FROZEN)
            ->orderBy('w.frozenAt', 'DESC');

        if ($frozenSince !== null) {
            $qb->andWhere('w.frozenAt >= :frozenSince')
               ->setParameter('frozenSince', $frozenSince);
        }

        return $qb->getQuery()->getResult();
    }

    public function getTotalBalanceByStatus(string $status, ?string $currency = null): string
    {
        $qb = $this->createQueryBuilder('w')
            ->select('SUM(CAST(w.balance AS DECIMAL(15,2)))')
            ->andWhere('w.status = :status')
            ->setParameter('status', $status);

        if ($currency !== null) {
            $qb->andWhere('w.currency = :currency')
               ->setParameter('currency', $currency);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        return number_format($result ?? 0, 2, '.', '');
    }

    public function getTotalAvailableBalanceByStatus(string $status, ?string $currency = null): string
    {
        $qb = $this->createQueryBuilder('w')
            ->select('SUM(CAST(w.availableBalance AS DECIMAL(15,2)))')
            ->andWhere('w.status = :status')
            ->setParameter('status', $status);

        if ($currency !== null) {
            $qb->andWhere('w.currency = :currency')
               ->setParameter('currency', $currency);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        return number_format($result ?? 0, 2, '.', '');
    }

    public function getTotalReservedBalance(?string $currency = null): string
    {
        $qb = $this->createQueryBuilder('w')
            ->select('SUM(CAST(w.reservedBalance AS DECIMAL(15,2)))');

        if ($currency !== null) {
            $qb->andWhere('w.currency = :currency')
               ->setParameter('currency', $currency);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        return number_format($result ?? 0, 2, '.', '');
    }

    public function countWalletsByStatus(): array
    {
        $results = $this->createQueryBuilder('w')
            ->select('w.status, COUNT(w.id) as count')
            ->groupBy('w.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['status']] = (int)$result['count'];
        }

        return $counts;
    }

    public function countWalletsByCurrency(): array
    {
        $results = $this->createQueryBuilder('w')
            ->select('w.currency, COUNT(w.id) as count')
            ->groupBy('w.currency')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['currency']] = (int)$result['count'];
        }

        return $counts;
    }

    public function findWalletsCreatedAfter(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('w.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findWalletsWithBalanceAbove(string $amount, ?string $currency = null): array
    {
        $qb = $this->createQueryBuilder('w')
            ->andWhere('CAST(w.balance AS DECIMAL(15,2)) > :amount')
            ->setParameter('amount', $amount)
            ->orderBy('w.balance', 'DESC');

        if ($currency !== null) {
            $qb->andWhere('w.currency = :currency')
               ->setParameter('currency', $currency);
        }

        return $qb->getQuery()->getResult();
    }

    public function findWalletsWithBalanceBelow(string $amount, ?string $currency = null): array
    {
        $qb = $this->createQueryBuilder('w')
            ->andWhere('CAST(w.balance AS DECIMAL(15,2)) < :amount')
            ->andWhere('w.status = :status')
            ->setParameter('amount', $amount)
            ->setParameter('status', Wallet::STATUS_ACTIVE)
            ->orderBy('w.balance', 'ASC');

        if ($currency !== null) {
            $qb->andWhere('w.currency = :currency')
               ->setParameter('currency', $currency);
        }

        return $qb->getQuery()->getResult();
    }

    public function findWalletsWithRecentActivity(int $days = 30): array
    {
        $cutoffDate = new \DateTimeImmutable(sprintf('-%d days', $days));

        return $this->createQueryBuilder('w')
            ->andWhere('w.lastTransactionAt >= :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->orderBy('w.lastTransactionAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getWalletStatistics(): array
    {
        $activeWallets = $this->count(['status' => Wallet::STATUS_ACTIVE]);
        $frozenWallets = $this->count(['status' => Wallet::STATUS_FROZEN]);
        $suspendedWallets = $this->count(['status' => Wallet::STATUS_SUSPENDED]);
        $closedWallets = $this->count(['status' => Wallet::STATUS_CLOSED]);

        $totalBalance = $this->getTotalBalanceByStatus(Wallet::STATUS_ACTIVE);
        $totalAvailableBalance = $this->getTotalAvailableBalanceByStatus(Wallet::STATUS_ACTIVE);
        $totalReservedBalance = $this->getTotalReservedBalance();

        $lowBalanceCount = $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.status = :status')
            ->andWhere('CAST(w.availableBalance AS DECIMAL(15,2)) <= CAST(w.lowBalanceThreshold AS DECIMAL(15,2))')
            ->setParameter('status', Wallet::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total_wallets' => $activeWallets + $frozenWallets + $suspendedWallets,
            'active_wallets' => $activeWallets,
            'frozen_wallets' => $frozenWallets,
            'suspended_wallets' => $suspendedWallets,
            'closed_wallets' => $closedWallets,
            'low_balance_wallets' => (int)$lowBalanceCount,
            'total_balance' => $totalBalance,
            'total_available_balance' => $totalAvailableBalance,
            'total_reserved_balance' => $totalReservedBalance,
            'currency_distribution' => $this->countWalletsByCurrency()
        ];
    }
}