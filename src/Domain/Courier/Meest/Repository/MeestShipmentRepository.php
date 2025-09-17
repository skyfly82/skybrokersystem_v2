<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Repository;

use App\Domain\Courier\Meest\Entity\MeestShipment;
use App\Domain\Courier\Meest\Enum\MeestTrackingStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for MeestShipment entity
 */
class MeestShipmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MeestShipment::class);
    }

    public function save(MeestShipment $shipment, bool $flush = true): void
    {
        $this->getEntityManager()->persist($shipment);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Save multiple shipments in batch
     *
     * @param MeestShipment[] $shipments
     */
    public function saveBatch(array $shipments): void
    {
        $em = $this->getEntityManager();

        foreach ($shipments as $shipment) {
            $em->persist($shipment);
        }

        $em->flush();
    }

    public function findByTrackingNumber(string $trackingNumber): ?MeestShipment
    {
        return $this->findOneBy(['trackingNumber' => $trackingNumber]);
    }

    public function findByShipmentId(string $shipmentId): ?MeestShipment
    {
        return $this->findOneBy(['shipmentId' => $shipmentId]);
    }

    public function findByReference(string $reference): array
    {
        return $this->findBy(['reference' => $reference]);
    }

    /**
     * Find shipments by status
     *
     * @return MeestShipment[]
     */
    public function findByStatus(MeestTrackingStatus $status): array
    {
        return $this->findBy(['status' => $status]);
    }

    /**
     * Find shipments that need tracking updates
     *
     * @return MeestShipment[]
     */
    public function findPendingTrackingUpdates(\DateTimeImmutable $updatedBefore = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.status IN (:statuses)')
            ->setParameter('statuses', [
                MeestTrackingStatus::CREATED,
                MeestTrackingStatus::ACCEPTED,
                MeestTrackingStatus::IN_TRANSIT,
                MeestTrackingStatus::OUT_FOR_DELIVERY,
                MeestTrackingStatus::PENDING_PICKUP,
                MeestTrackingStatus::AT_SORTING_FACILITY,
                MeestTrackingStatus::CUSTOMS_CLEARANCE,
                MeestTrackingStatus::CUSTOMS_CLEARED,
                MeestTrackingStatus::DELIVERY_ATTEMPT,
                MeestTrackingStatus::EXCEPTION,
                MeestTrackingStatus::CUSTOMS_HELD
            ]);

        if ($updatedBefore) {
            $qb->andWhere('s.updatedAt < :updatedBefore')
               ->setParameter('updatedBefore', $updatedBefore);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find overdue shipments
     *
     * @return MeestShipment[]
     */
    public function findOverdueShipments(\DateTimeImmutable $overdueDate = null): array
    {
        $overdueDate = $overdueDate ?? new \DateTimeImmutable('-7 days');

        return $this->createQueryBuilder('s')
            ->where('s.estimatedDelivery < :overdueDate')
            ->andWhere('s.status NOT IN (:terminalStatuses)')
            ->setParameter('overdueDate', $overdueDate)
            ->setParameter('terminalStatuses', [
                MeestTrackingStatus::DELIVERED,
                MeestTrackingStatus::RETURNED,
                MeestTrackingStatus::CANCELLED
            ])
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent shipments for a sender
     *
     * @return MeestShipment[]
     */
    public function findRecentBySender(string $senderEmail, int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->where('JSON_EXTRACT(s.senderData, \'$.email\') = :senderEmail')
            ->setParameter('senderEmail', $senderEmail)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find shipments within date range
     *
     * @return MeestShipment[]
     */
    public function findByDateRange(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
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
    public function getStatistics(\DateTimeImmutable $from = null): array
    {
        $from = $from ?? new \DateTimeImmutable('-30 days');

        $qb = $this->createQueryBuilder('s')
            ->select('s.status, COUNT(s.id) as count')
            ->where('s.createdAt >= :from')
            ->setParameter('from', $from)
            ->groupBy('s.status');

        $results = $qb->getQuery()->getResult();

        $statistics = [];
        foreach ($results as $result) {
            $statistics[$result['status']->value] = (int) $result['count'];
        }

        return $statistics;
    }

    /**
     * Get total shipping costs within date range
     */
    public function getTotalCosts(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        string $currency = null
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->select('s.currency, SUM(s.totalCost) as total')
            ->where('s.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('s.currency');

        if ($currency) {
            $qb->andWhere('s.currency = :currency')
               ->setParameter('currency', $currency);
        }

        $results = $qb->getQuery()->getResult();

        $costs = [];
        foreach ($results as $result) {
            $costs[$result['currency']] = (float) $result['total'];
        }

        return $costs;
    }

    /**
     * Find shipments with failed label generation
     *
     * @return MeestShipment[]
     */
    public function findWithoutLabels(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.labelUrl IS NULL')
            ->andWhere('s.status != :cancelled')
            ->setParameter('cancelled', MeestTrackingStatus::CANCELLED)
            ->orderBy('s.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find shipments by multiple tracking numbers
     *
     * @param string[] $trackingNumbers
     * @return MeestShipment[]
     */
    public function findByTrackingNumbers(array $trackingNumbers): array
    {
        if (empty($trackingNumbers)) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->where('s.trackingNumber IN (:trackingNumbers)')
            ->setParameter('trackingNumbers', $trackingNumbers)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count shipments by status within date range
     */
    public function countByStatusAndDateRange(
        MeestTrackingStatus $status,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): int {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.status = :status')
            ->andWhere('s.createdAt BETWEEN :from AND :to')
            ->setParameter('status', $status)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find shipments with high value (above threshold)
     *
     * @return MeestShipment[]
     */
    public function findHighValueShipments(float $valueThreshold = 1000.0): array
    {
        return $this->createQueryBuilder('s')
            ->where('CAST(JSON_EXTRACT(s.parcelData, \'$.value\') AS DECIMAL(10,2)) > :threshold')
            ->setParameter('threshold', $valueThreshold)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find shipments by sender email with pagination
     *
     * @return MeestShipment[]
     */
    public function findBySenderEmailPaginated(
        string $senderEmail,
        int $page = 1,
        int $limit = 20
    ): array {
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('s')
            ->where('JSON_EXTRACT(s.senderData, \'$.email\') = :senderEmail')
            ->setParameter('senderEmail', $senderEmail)
            ->orderBy('s.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find shipments by recipient country
     *
     * @return MeestShipment[]
     */
    public function findByRecipientCountry(string $country): array
    {
        return $this->createQueryBuilder('s')
            ->where('JSON_EXTRACT(s.recipientData, \'$.country\') = :country')
            ->setParameter('country', strtoupper($country))
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get delivery performance statistics
     */
    public function getDeliveryPerformanceStats(\DateTimeImmutable $from): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id) as total')
            ->addSelect('COUNT(CASE WHEN s.status = :delivered THEN 1 END) as delivered')
            ->addSelect('COUNT(CASE WHEN s.estimatedDelivery < :now AND s.status != :delivered THEN 1 END) as overdue')
            ->addSelect('AVG(TIMESTAMPDIFF(DAY, s.createdAt, s.deliveredAt)) as avg_delivery_days')
            ->where('s.createdAt >= :from')
            ->setParameter('from', $from)
            ->setParameter('delivered', MeestTrackingStatus::DELIVERED)
            ->setParameter('now', new \DateTimeImmutable());

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total_shipments' => (int) $result['total'],
            'delivered_shipments' => (int) $result['delivered'],
            'overdue_shipments' => (int) $result['overdue'],
            'delivery_rate' => $result['total'] > 0 ? round(($result['delivered'] / $result['total']) * 100, 2) : 0,
            'average_delivery_days' => $result['avg_delivery_days'] ? round((float) $result['avg_delivery_days'], 1) : null
        ];
    }

    /**
     * Find duplicate tracking numbers (should not happen but good for debugging)
     */
    public function findDuplicateTrackingNumbers(): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.trackingNumber, COUNT(s.id) as count')
            ->groupBy('s.trackingNumber')
            ->having('COUNT(s.id) > 1')
            ->getQuery()
            ->getResult();
    }

    /**
     * Bulk update status for multiple shipments
     */
    public function bulkUpdateStatus(array $trackingNumbers, MeestTrackingStatus $status): int
    {
        if (empty($trackingNumbers)) {
            return 0;
        }

        return $this->createQueryBuilder('s')
            ->update()
            ->set('s.status', ':status')
            ->set('s.updatedAt', ':updatedAt')
            ->where('s.trackingNumber IN (:trackingNumbers)')
            ->setParameter('status', $status)
            ->setParameter('updatedAt', new \DateTimeImmutable())
            ->setParameter('trackingNumbers', $trackingNumbers)
            ->getQuery()
            ->execute();
    }
}