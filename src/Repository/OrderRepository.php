<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function save(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find orders by customer with optional status filter
     */
    public function findByCustomer(Customer $customer, ?string $status = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($status) {
            $qb->andWhere('o.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find orders by status
     */
    public function findByStatus(string $status, int $limit = 100): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->setParameter('status', $status)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find orders that need to be shipped
     */
    public function findPendingShipment(): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status IN (:statuses)')
            ->setParameter('statuses', ['confirmed', 'processing'])
            ->andWhere('o.confirmedAt IS NOT NULL')
            ->orderBy('o.confirmedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find orders created in date range
     */
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get order statistics for customer
     */
    public function getCustomerOrderStats(Customer $customer): array
    {
        $result = $this->createQueryBuilder('o')
            ->select('
                COUNT(o.id) as total_orders,
                SUM(CASE WHEN o.status = \'pending\' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN o.status = \'confirmed\' THEN 1 ELSE 0 END) as confirmed_orders,
                SUM(CASE WHEN o.status = \'shipped\' THEN 1 ELSE 0 END) as shipped_orders,
                SUM(CASE WHEN o.status = \'delivered\' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(CASE WHEN o.status = \'canceled\' THEN 1 ELSE 0 END) as canceled_orders,
                SUM(o.totalAmount) as total_value,
                AVG(o.totalAmount) as average_order_value
            ')
            ->where('o.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleResult();

        return [
            'total_orders' => (int) $result['total_orders'],
            'pending_orders' => (int) $result['pending_orders'],
            'confirmed_orders' => (int) $result['confirmed_orders'],
            'shipped_orders' => (int) $result['shipped_orders'],
            'delivered_orders' => (int) $result['delivered_orders'],
            'canceled_orders' => (int) $result['canceled_orders'],
            'total_value' => (float) ($result['total_value'] ?? 0),
            'average_order_value' => (float) ($result['average_order_value'] ?? 0),
        ];
    }

    /**
     * Find orders with shipments ready for tracking update
     */
    public function findOrdersWithActiveShipments(): array
    {
        return $this->createQueryBuilder('o')
            ->join('o.shipments', 's')
            ->where('o.status IN (:statuses)')
            ->andWhere('s.status NOT IN (:finalStatuses)')
            ->setParameter('statuses', ['shipped', 'processing'])
            ->setParameter('finalStatuses', ['delivered', 'canceled', 'returned'])
            ->orderBy('o.shippedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get monthly order statistics
     */
    public function getMonthlyStats(int $year, int $month): array
    {
        $startDate = new \DateTimeImmutable("{$year}-{$month}-01");
        $endDate = $startDate->modify('last day of this month')->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('o')
            ->select('
                DATE(o.createdAt) as order_date,
                COUNT(o.id) as daily_count,
                SUM(o.totalAmount) as daily_value
            ')
            ->where('o.createdAt BETWEEN :start AND :end')
            ->groupBy('order_date')
            ->orderBy('order_date', 'ASC')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Count orders for a specific period
     */
    public function countForPeriod(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.createdAt >= :from')
            ->andWhere('o.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Search orders by multiple criteria
     */
    public function searchOrders(array $criteria = []): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.customer', 'c');

        if (!empty($criteria['orderNumber'])) {
            $qb->andWhere('o.orderNumber LIKE :orderNumber')
               ->setParameter('orderNumber', '%' . $criteria['orderNumber'] . '%');
        }

        if (!empty($criteria['customerName'])) {
            $qb->andWhere('c.companyName LIKE :customerName')
               ->setParameter('customerName', '%' . $criteria['customerName'] . '%');
        }

        if (!empty($criteria['status'])) {
            $qb->andWhere('o.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        if (!empty($criteria['dateFrom'])) {
            $qb->andWhere('o.createdAt >= :dateFrom')
               ->setParameter('dateFrom', $criteria['dateFrom']);
        }

        if (!empty($criteria['dateTo'])) {
            $qb->andWhere('o.createdAt <= :dateTo')
               ->setParameter('dateTo', $criteria['dateTo']);
        }

        if (isset($criteria['minAmount'])) {
            $qb->andWhere('o.totalAmount >= :minAmount')
               ->setParameter('minAmount', $criteria['minAmount']);
        }

        if (isset($criteria['maxAmount'])) {
            $qb->andWhere('o.totalAmount <= :maxAmount')
               ->setParameter('maxAmount', $criteria['maxAmount']);
        }

        return $qb->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($criteria['limit'] ?? 100)
            ->getQuery()
            ->getResult();
    }
}