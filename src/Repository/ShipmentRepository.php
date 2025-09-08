<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Shipment;
use App\Entity\Order;
use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ShipmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shipment::class);
    }

    public function save(Shipment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Shipment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find shipment by tracking number
     */
    public function findByTrackingNumber(string $trackingNumber): ?Shipment
    {
        return $this->createQueryBuilder('s')
            ->where('s.trackingNumber = :trackingNumber')
            ->setParameter('trackingNumber', $trackingNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find shipments by courier service
     */
    public function findByCourierService(string $courierService, int $limit = 100): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.courierService = :courierService')
            ->setParameter('courierService', $courierService)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find InPost shipments
     */
    public function findInPostShipments(int $limit = 100): array
    {
        return $this->findByCourierService('inpost', $limit);
    }

    /**
     * Find shipments by status
     */
    public function findByStatus(string $status, int $limit = 100): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->setParameter('status', $status)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active shipments that need tracking updates
     */
    public function findActiveShipmentsForTracking(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status NOT IN (:finalStatuses)')
            ->andWhere('s.dispatchedAt IS NOT NULL')
            ->setParameter('finalStatuses', ['delivered', 'canceled', 'returned_to_sender', 'error'])
            ->orderBy('s.updatedAt', 'ASC')
            ->setMaxResults(50) // Limit to avoid API rate limits
            ->getQuery()
            ->getResult();
    }

    /**
     * Find shipments for customer
     */
    public function findByCustomer(Customer $customer, int $limit = 50): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.order', 'o')
            ->where('o.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find shipments in date range
     */
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get shipment statistics
     */
    public function getShipmentStats(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('
                COUNT(s.id) as total_shipments,
                COUNT(CASE WHEN s.status = \'created\' THEN 1 END) as created_shipments,
                COUNT(CASE WHEN s.status = \'dispatched\' THEN 1 END) as dispatched_shipments,
                COUNT(CASE WHEN s.status = \'delivered\' THEN 1 END) as delivered_shipments,
                COUNT(CASE WHEN s.status = \'canceled\' THEN 1 END) as canceled_shipments,
                COUNT(CASE WHEN s.courierService = \'inpost\' THEN 1 END) as inpost_shipments,
                COUNT(CASE WHEN s.courierService = \'dhl\' THEN 1 END) as dhl_shipments,
                SUM(s.shippingCost) as total_shipping_cost,
                AVG(s.totalWeight) as average_weight
            ');

        if ($from && $to) {
            $qb->where('s.createdAt BETWEEN :from AND :to')
               ->setParameter('from', $from)
               ->setParameter('to', $to);
        }

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total_shipments' => (int) $result['total_shipments'],
            'created_shipments' => (int) $result['created_shipments'],
            'dispatched_shipments' => (int) $result['dispatched_shipments'],
            'delivered_shipments' => (int) $result['delivered_shipments'],
            'canceled_shipments' => (int) $result['canceled_shipments'],
            'inpost_shipments' => (int) $result['inpost_shipments'],
            'dhl_shipments' => (int) $result['dhl_shipments'],
            'total_shipping_cost' => (float) ($result['total_shipping_cost'] ?? 0),
            'average_weight' => (float) ($result['average_weight'] ?? 0),
        ];
    }

    /**
     * Find shipments with COD (Cash on Delivery)
     */
    public function findShipmentsWithCOD(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.codAmount IS NOT NULL')
            ->andWhere('s.codAmount > 0')
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find overdue shipments (estimated delivery passed)
     */
    public function findOverdueShipments(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.estimatedDeliveryAt < :now')
            ->andWhere('s.status NOT IN (:finalStatuses)')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('finalStatuses', ['delivered', 'canceled', 'returned_to_sender'])
            ->orderBy('s.estimatedDeliveryAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search shipments by multiple criteria
     */
    public function searchShipments(array $criteria = []): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.order', 'o')
            ->leftJoin('o.customer', 'c');

        if (!empty($criteria['trackingNumber'])) {
            $qb->andWhere('s.trackingNumber LIKE :trackingNumber')
               ->setParameter('trackingNumber', '%' . $criteria['trackingNumber'] . '%');
        }

        if (!empty($criteria['recipientName'])) {
            $qb->andWhere('s.recipientName LIKE :recipientName')
               ->setParameter('recipientName', '%' . $criteria['recipientName'] . '%');
        }

        if (!empty($criteria['courierService'])) {
            $qb->andWhere('s.courierService = :courierService')
               ->setParameter('courierService', $criteria['courierService']);
        }

        if (!empty($criteria['status'])) {
            $qb->andWhere('s.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        if (!empty($criteria['dateFrom'])) {
            $qb->andWhere('s.createdAt >= :dateFrom')
               ->setParameter('dateFrom', $criteria['dateFrom']);
        }

        if (!empty($criteria['dateTo'])) {
            $qb->andWhere('s.createdAt <= :dateTo')
               ->setParameter('dateTo', $criteria['dateTo']);
        }

        if (!empty($criteria['customerName'])) {
            $qb->andWhere('c.companyName LIKE :customerName')
               ->setParameter('customerName', '%' . $criteria['customerName'] . '%');
        }

        return $qb->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($criteria['limit'] ?? 100)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get shipments that need status synchronization from courier
     */
    public function findShipmentsForStatusSync(string $courierService, int $hoursOld = 1): array
    {
        $cutoffTime = new \DateTimeImmutable("-{$hoursOld} hours");

        return $this->createQueryBuilder('s')
            ->where('s.courierService = :courierService')
            ->andWhere('s.status NOT IN (:finalStatuses)')
            ->andWhere('s.updatedAt < :cutoffTime OR s.updatedAt IS NULL')
            ->setParameter('courierService', $courierService)
            ->setParameter('finalStatuses', ['delivered', 'canceled', 'returned_to_sender', 'error'])
            ->setParameter('cutoffTime', $cutoffTime)
            ->orderBy('s.updatedAt', 'ASC NULLS FIRST')
            ->setMaxResults(20) // Limit to respect API rate limits
            ->getQuery()
            ->getResult();
    }
}