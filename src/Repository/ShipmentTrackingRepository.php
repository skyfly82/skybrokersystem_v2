<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShipmentTracking;
use App\Entity\Shipment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ShipmentTrackingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShipmentTracking::class);
    }

    public function save(ShipmentTracking $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ShipmentTracking $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find tracking events for shipment ordered by date
     */
    public function findByShipmentOrderedByDate(Shipment $shipment): array
    {
        return $this->createQueryBuilder('st')
            ->where('st.shipment = :shipment')
            ->setParameter('shipment', $shipment)
            ->orderBy('st.eventDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find latest tracking event for shipment
     */
    public function findLatestForShipment(Shipment $shipment): ?ShipmentTracking
    {
        return $this->createQueryBuilder('st')
            ->where('st.shipment = :shipment')
            ->setParameter('shipment', $shipment)
            ->orderBy('st.eventDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if tracking event already exists (prevent duplicates)
     */
    public function eventExists(Shipment $shipment, string $courierEventId): bool
    {
        return $this->createQueryBuilder('st')
            ->select('COUNT(st.id)')
            ->where('st.shipment = :shipment')
            ->andWhere('st.courierEventId = :courierEventId')
            ->setParameter('shipment', $shipment)
            ->setParameter('courierEventId', $courierEventId)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Find tracking events by status
     */
    public function findByStatus(string $status, int $limit = 100): array
    {
        return $this->createQueryBuilder('st')
            ->where('st.status = :status')
            ->setParameter('status', $status)
            ->orderBy('st.eventDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent tracking events
     */
    public function findRecent(int $hours = 24, int $limit = 100): array
    {
        $since = new \DateTimeImmutable("-{$hours} hours");

        return $this->createQueryBuilder('st')
            ->where('st.eventDate >= :since')
            ->setParameter('since', $since)
            ->orderBy('st.eventDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get tracking statistics
     */
    public function getTrackingStats(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('st')
            ->select('
                st.status,
                COUNT(st.id) as event_count
            ')
            ->groupBy('st.status');

        if ($from && $to) {
            $qb->where('st.eventDate BETWEEN :from AND :to')
               ->setParameter('from', $from)
               ->setParameter('to', $to);
        }

        $results = $qb->getQuery()->getResult();
        
        $stats = [];
        foreach ($results as $result) {
            $stats[$result['status']] = (int) $result['event_count'];
        }

        return $stats;
    }

    /**
     * Remove old tracking events (data cleanup)
     */
    public function removeOldEvents(int $daysOld = 365): int
    {
        $cutoffDate = new \DateTimeImmutable("-{$daysOld} days");

        return $this->createQueryBuilder('st')
            ->delete()
            ->where('st.eventDate < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();
    }
}