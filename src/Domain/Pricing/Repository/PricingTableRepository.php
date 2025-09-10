<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Repository;

use App\Domain\Pricing\Entity\PricingTable;
use App\Domain\Pricing\Entity\Carrier;
use App\Domain\Pricing\Entity\PricingZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PricingTable>
 */
class PricingTableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PricingTable::class);
    }

    /**
     * Find pricing table by carrier, zone and service type
     */
    public function findByCarrierZoneService(Carrier $carrier, PricingZone $zone, string $serviceType): ?PricingTable
    {
        return $this->createQueryBuilder('pt')
            ->where('pt.carrier = :carrier')
            ->andWhere('pt.zone = :zone')
            ->andWhere('pt.serviceType = :serviceType')
            ->andWhere('pt.isActive = true')
            ->andWhere('pt.effectiveFrom <= CURRENT_TIMESTAMP()')
            ->andWhere('pt.effectiveUntil IS NULL OR pt.effectiveUntil >= CURRENT_TIMESTAMP()')
            ->setParameter('carrier', $carrier)
            ->setParameter('zone', $zone)
            ->setParameter('serviceType', $serviceType)
            ->orderBy('pt.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find pricing table by carrier, zone code and service type
     */
    public function findByCarrierZoneAndService(Carrier $carrier, string $zoneCode, string $serviceType): ?PricingTable
    {
        return $this->createQueryBuilder('pt')
            ->join('pt.zone', 'z')
            ->where('pt.carrier = :carrier')
            ->andWhere('z.code = :zoneCode')
            ->andWhere('pt.serviceType = :serviceType')
            ->andWhere('pt.isActive = true')
            ->andWhere('pt.effectiveFrom <= CURRENT_TIMESTAMP()')
            ->andWhere('pt.effectiveUntil IS NULL OR pt.effectiveUntil >= CURRENT_TIMESTAMP()')
            ->setParameter('carrier', $carrier)
            ->setParameter('zoneCode', $zoneCode)
            ->setParameter('serviceType', $serviceType)
            ->orderBy('pt.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all active pricing tables for carrier
     *
     * @return PricingTable[]
     */
    public function findActiveByCarrier(Carrier $carrier): array
    {
        return $this->createQueryBuilder('pt')
            ->where('pt.carrier = :carrier')
            ->andWhere('pt.isActive = true')
            ->andWhere('pt.effectiveFrom <= CURRENT_TIMESTAMP()')
            ->andWhere('pt.effectiveUntil IS NULL OR pt.effectiveUntil >= CURRENT_TIMESTAMP()')
            ->setParameter('carrier', $carrier)
            ->orderBy('pt.zone', 'ASC')
            ->addOrderBy('pt.serviceType', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pricing tables by zone
     *
     * @return PricingTable[]
     */
    public function findActiveByZone(PricingZone $zone): array
    {
        return $this->createQueryBuilder('pt')
            ->where('pt.zone = :zone')
            ->andWhere('pt.isActive = true')
            ->andWhere('pt.effectiveFrom <= CURRENT_TIMESTAMP()')
            ->andWhere('pt.effectiveUntil IS NULL OR pt.effectiveUntil >= CURRENT_TIMESTAMP()')
            ->setParameter('zone', $zone)
            ->orderBy('pt.carrier', 'ASC')
            ->addOrderBy('pt.serviceType', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pricing tables expiring soon
     *
     * @return PricingTable[]
     */
    public function findExpiringSoon(int $daysAhead = 30): array
    {
        $futureDate = new \DateTimeImmutable(sprintf('+%d days', $daysAhead));
        
        return $this->createQueryBuilder('pt')
            ->where('pt.effectiveUntil IS NOT NULL')
            ->andWhere('pt.effectiveUntil <= :futureDate')
            ->andWhere('pt.effectiveUntil >= CURRENT_TIMESTAMP()')
            ->andWhere('pt.isActive = true')
            ->setParameter('futureDate', $futureDate)
            ->orderBy('pt.effectiveUntil', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get pricing table statistics
     */
    public function getPricingStatistics(): array
    {
        return $this->createQueryBuilder('pt')
            ->select([
                'c.code as carrierCode',
                'c.name as carrierName', 
                'z.code as zoneCode',
                'z.name as zoneName',
                'pt.serviceType',
                'COUNT(pr.id) as rulesCount',
                'MIN(pr.weightFrom) as minWeight',
                'MAX(pr.weightTo) as maxWeight'
            ])
            ->join('pt.carrier', 'c')
            ->join('pt.zone', 'z')
            ->leftJoin('pt.pricingRules', 'pr')
            ->where('pt.isActive = true')
            ->groupBy('pt.id')
            ->getQuery()
            ->getResult();
    }

    public function save(PricingTable $pricingTable, bool $flush = false): void
    {
        $this->getEntityManager()->persist($pricingTable);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PricingTable $pricingTable, bool $flush = false): void
    {
        $this->getEntityManager()->remove($pricingTable);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}