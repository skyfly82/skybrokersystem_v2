<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * Find transactions by customer
     *
     * @return Transaction[]
     */
    public function findByCustomer(Customer $customer, int $limit = 50): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pending transactions
     *
     * @return Transaction[]
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->setParameter('status', 'pending')
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find completed transactions in date range
     *
     * @return Transaction[]
     */
    public function findCompletedInDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->andWhere('t.completedAt >= :from')
            ->andWhere('t.completedAt <= :to')
            ->setParameter('status', 'completed')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('t.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate total revenue for period
     */
    public function getTotalRevenueForPeriod(\DateTimeInterface $from, \DateTimeInterface $to): float
    {
        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.amount)')
            ->andWhere('t.status = :status')
            ->andWhere('t.type = :type')
            ->andWhere('t.completedAt >= :from')
            ->andWhere('t.completedAt <= :to')
            ->setParameter('status', 'completed')
            ->setParameter('type', 'payment')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0.0);
    }

    /**
     * Get transaction statistics
     */
    public function getStatistics(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select([
                'COUNT(t.id) as total_transactions',
                'SUM(CASE WHEN t.status = \'completed\' THEN 1 ELSE 0 END) as completed_transactions',
                'SUM(CASE WHEN t.status = \'pending\' THEN 1 ELSE 0 END) as pending_transactions',
                'SUM(CASE WHEN t.status = \'failed\' THEN 1 ELSE 0 END) as failed_transactions',
                'SUM(CASE WHEN t.status = \'completed\' AND t.type = \'payment\' THEN t.amount ELSE 0 END) as total_revenue'
            ])
            ->andWhere('t.createdAt >= :from')
            ->andWhere('t.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Find transactions with filters and pagination
     *
     * @return Transaction[]
     */
    public function findWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.customer', 'c');

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('t.transactionId', ':search'),
                $qb->expr()->like('c.companyName', ':search'),
                $qb->expr()->like('t.description', ':search')
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('t.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['payment_method'])) {
            $qb->andWhere('t.paymentMethod = :payment_method')
               ->setParameter('payment_method', $filters['payment_method']);
        }

        if (!empty($filters['date_from'])) {
            $qb->andWhere('t.createdAt >= :date_from')
               ->setParameter('date_from', new \DateTime($filters['date_from']));
        }

        if (!empty($filters['date_to'])) {
            $qb->andWhere('t.createdAt <= :date_to')
               ->setParameter('date_to', new \DateTime($filters['date_to']));
        }

        $qb->orderBy('t.createdAt', 'DESC');

        if (!empty($filters['limit'])) {
            $qb->setMaxResults($filters['limit']);
        }

        if (!empty($filters['page']) && !empty($filters['limit'])) {
            $offset = ($filters['page'] - 1) * $filters['limit'];
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count transactions with filters
     */
    public function countWithFilters(array $filters): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->leftJoin('t.customer', 'c');

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('t.transactionId', ':search'),
                $qb->expr()->like('c.companyName', ':search'),
                $qb->expr()->like('t.description', ':search')
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('t.type = :type')
               ->setParameter('type', $filters['type']);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get payment method statistics
     */
    public function getPaymentMethodStatistics(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select([
                't.paymentMethod as payment_method',
                'COUNT(t.id) as transaction_count',
                'SUM(t.amount) as total_amount'
            ])
            ->andWhere('t.status = :status')
            ->andWhere('t.type = :type')
            ->andWhere('t.createdAt >= :from')
            ->andWhere('t.createdAt <= :to')
            ->groupBy('t.paymentMethod')
            ->orderBy('total_amount', 'DESC')
            ->setParameter('status', 'completed')
            ->setParameter('type', 'payment')
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        return $qb->getQuery()->getResult();
    }
}