<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CustomerBalance;
use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Customer Balance Repository
 * Provides specialized queries for customer balance management
 */
class CustomerBalanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerBalance::class);
    }

    public function save(CustomerBalance $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CustomerBalance $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find balance by customer
     */
    public function findByCustomer(Customer $customer): ?CustomerBalance
    {
        return $this->findOneBy(['customer' => $customer]);
    }

    /**
     * Find balance by customer ID
     */
    public function findByCustomerId(int $customerId): ?CustomerBalance
    {
        return $this->createQueryBuilder('b')
            ->where('b.customer = :customerId')
            ->setParameter('customerId', $customerId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find customers with low balance for auto top-up
     */
    public function findCustomersNeedingAutoTopUp(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.autoTopUpEnabled = true')
            ->andWhere('b.autoTopUpTrigger IS NOT NULL')
            ->andWhere('b.autoTopUpAmount IS NOT NULL')
            ->andWhere('b.currentBalance <= b.autoTopUpTrigger')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find customers with negative balance (in credit)
     */
    public function findCustomersInCredit(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.currentBalance < 0')
            ->orderBy('b.currentBalance', 'asc')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find customers exceeding credit limit
     */
    public function findCustomersOverCreditLimit(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.currentBalance < 0')
            ->andWhere('ABS(b.currentBalance) > b.creditLimit')
            ->orderBy('b.currentBalance', 'asc')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get balance statistics
     */
    public function getBalanceStatistics(): array
    {
        $result = $this->createQueryBuilder('b')
            ->select('
                COUNT(b.id) as total_customers,
                SUM(CASE WHEN b.currentBalance > 0 THEN 1 ELSE 0 END) as customers_with_positive_balance,
                SUM(CASE WHEN b.currentBalance < 0 THEN 1 ELSE 0 END) as customers_in_credit,
                SUM(CASE WHEN b.currentBalance = 0 THEN 1 ELSE 0 END) as customers_zero_balance,
                SUM(b.currentBalance) as total_balance,
                AVG(b.currentBalance) as avg_balance,
                SUM(b.totalSpent) as total_spent,
                SUM(b.totalTopUps) as total_topups,
                SUM(CASE WHEN b.autoTopUpEnabled = true THEN 1 ELSE 0 END) as auto_topup_enabled_count
            ')
            ->getQuery()
            ->getSingleResult();

        return [
            'total_customers' => (int) $result['total_customers'],
            'customers_with_positive_balance' => (int) $result['customers_with_positive_balance'],
            'customers_in_credit' => (int) $result['customers_in_credit'],
            'customers_zero_balance' => (int) $result['customers_zero_balance'],
            'total_balance' => (float) ($result['total_balance'] ?? 0),
            'average_balance' => (float) ($result['avg_balance'] ?? 0),
            'total_spent' => (float) ($result['total_spent'] ?? 0),
            'total_topups' => (float) ($result['total_topups'] ?? 0),
            'auto_topup_enabled_count' => (int) $result['auto_topup_enabled_count'],
        ];
    }

    /**
     * Find customers by balance range
     */
    public function findByBalanceRange(float $minBalance, float $maxBalance): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.currentBalance >= :minBalance')
            ->andWhere('b.currentBalance <= :maxBalance')
            ->orderBy('b.currentBalance', 'desc')
            ->setParameter('minBalance', $minBalance)
            ->setParameter('maxBalance', $maxBalance)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find top spenders
     */
    public function findTopSpenders(int $limit = 10, int $days = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->orderBy('b.totalSpent', 'desc')
            ->setMaxResults($limit);

        if ($days) {
            $fromDate = new \DateTime("-{$days} days");
            $qb->andWhere('b.lastTransactionAt >= :fromDate')
               ->setParameter('fromDate', $fromDate);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find customers who haven't topped up recently
     */
    public function findInactiveCustomers(int $days = 90): array
    {
        $cutoffDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('b')
            ->where('b.lastTopUpAt < :cutoffDate OR b.lastTopUpAt IS NULL')
            ->andWhere('b.currentBalance < 50') // Low balance threshold
            ->orderBy('b.lastTopUpAt', 'asc')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * Update balance atomically
     */
    public function updateBalance(int $customerId, float $amount, string $operation = 'add'): bool
    {
        $operator = $operation === 'add' ? '+' : '-';

        $query = $this->createQueryBuilder('b')
            ->update()
            ->set('b.currentBalance', "b.currentBalance {$operator} :amount")
            ->set('b.updatedAt', ':now')
            ->set('b.lastTransactionAt', ':now')
            ->where('b.customer = :customerId')
            ->setParameter('amount', abs($amount))
            ->setParameter('customerId', $customerId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery();

        $affectedRows = $query->execute();
        return $affectedRows > 0;
    }

    /**
     * Reserve funds atomically
     */
    public function reserveFunds(int $customerId, float $amount): bool
    {
        // First check if sufficient funds are available
        $balance = $this->findByCustomerId($customerId);
        if (!$balance || !$balance->hasSufficientFunds($amount)) {
            return false;
        }

        $query = $this->createQueryBuilder('b')
            ->update()
            ->set('b.reservedAmount', 'b.reservedAmount + :amount')
            ->set('b.updatedAt', ':now')
            ->where('b.customer = :customerId')
            ->setParameter('amount', $amount)
            ->setParameter('customerId', $customerId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery();

        $affectedRows = $query->execute();
        return $affectedRows > 0;
    }

    /**
     * Release reserved funds
     */
    public function releaseReservedFunds(int $customerId, float $amount): bool
    {
        $query = $this->createQueryBuilder('b')
            ->update()
            ->set('b.reservedAmount', 'CASE WHEN (b.reservedAmount - :amount) > 0 THEN (b.reservedAmount - :amount) ELSE 0 END')
            ->set('b.updatedAt', ':now')
            ->where('b.customer = :customerId')
            ->setParameter('amount', $amount)
            ->setParameter('customerId', $customerId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery();

        $affectedRows = $query->execute();
        return $affectedRows > 0;
    }

    /**
     * Update spending totals
     */
    public function updateSpendingTotal(int $customerId, float $amount): bool
    {
        $query = $this->createQueryBuilder('b')
            ->update()
            ->set('b.totalSpent', 'b.totalSpent + :amount')
            ->set('b.updatedAt', ':now')
            ->set('b.lastTransactionAt', ':now')
            ->where('b.customer = :customerId')
            ->setParameter('amount', $amount)
            ->setParameter('customerId', $customerId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery();

        $affectedRows = $query->execute();
        return $affectedRows > 0;
    }

    /**
     * Update top-up totals
     */
    public function updateTopUpTotal(int $customerId, float $amount): bool
    {
        $query = $this->createQueryBuilder('b')
            ->update()
            ->set('b.totalTopUps', 'b.totalTopUps + :amount')
            ->set('b.lastTopUpAt', ':now')
            ->set('b.updatedAt', ':now')
            ->set('b.lastTransactionAt', ':now')
            ->where('b.customer = :customerId')
            ->setParameter('amount', $amount)
            ->setParameter('customerId', $customerId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery();

        $affectedRows = $query->execute();
        return $affectedRows > 0;
    }

    /**
     * Find or create balance for customer
     */
    public function findOrCreateForCustomer(Customer $customer): CustomerBalance
    {
        $balance = $this->findByCustomer($customer);

        if (!$balance) {
            $balance = new CustomerBalance();
            $balance->setCustomer($customer);
            $this->save($balance, true);
        }

        return $balance;
    }

    /**
     * Get monthly balance trends for analytics
     */
    public function getMonthlyBalanceTrends(int $months = 12): array
    {
        $fromDate = new \DateTime("-{$months} months");

        return $this->createQueryBuilder('b')
            ->select('
                DATE_FORMAT(b.updatedAt, "%Y-%m") as month,
                AVG(b.currentBalance) as avg_balance,
                SUM(b.totalSpent) as monthly_spending,
                SUM(b.totalTopUps) as monthly_topups,
                COUNT(b.id) as active_customers
            ')
            ->where('b.updatedAt >= :fromDate')
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get balance distribution for analytics
     */
    public function getBalanceDistribution(): array
    {
        return $this->createQueryBuilder('b')
            ->select('
                SUM(CASE WHEN b.currentBalance < 0 THEN 1 ELSE 0 END) as negative_balance,
                SUM(CASE WHEN b.currentBalance >= 0 AND b.currentBalance < 50 THEN 1 ELSE 0 END) as low_balance,
                SUM(CASE WHEN b.currentBalance >= 50 AND b.currentBalance < 200 THEN 1 ELSE 0 END) as medium_balance,
                SUM(CASE WHEN b.currentBalance >= 200 AND b.currentBalance < 500 THEN 1 ELSE 0 END) as good_balance,
                SUM(CASE WHEN b.currentBalance >= 500 THEN 1 ELSE 0 END) as high_balance
            ')
            ->getQuery()
            ->getSingleResult();
    }
}