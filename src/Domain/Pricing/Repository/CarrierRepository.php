<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Repository;

use App\Domain\Pricing\Entity\Carrier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Carrier>
 */
class CarrierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Carrier::class);
    }

    /**
     * Find carrier by code
     */
    public function findByCode(string $code): ?Carrier
    {
        return $this->findOneBy(['code' => strtoupper($code)]);
    }

    /**
     * Find active carriers ordered by sort order
     *
     * @return Carrier[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find carriers that support specific zone
     *
     * @return Carrier[]
     */
    public function findByZoneSupport(string $zoneCode): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.supportedZones LIKE :zone')
            ->andWhere('c.isActive = true')
            ->setParameter('zone', '%"' . $zoneCode . '"%')
            ->orderBy('c.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find carriers with API integration
     *
     * @return Carrier[]
     */
    public function findWithApiIntegration(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.apiEndpoint IS NOT NULL')
            ->andWhere('c.apiConfig IS NOT NULL')
            ->andWhere('c.isActive = true')
            ->orderBy('c.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find carriers that can handle specific weight
     *
     * @return Carrier[]
     */
    public function findByWeightCapacity(float $weightKg): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.maxWeightKg IS NULL OR c.maxWeightKg >= :weight')
            ->andWhere('c.isActive = true')
            ->setParameter('weight', $weightKg)
            ->orderBy('c.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find carriers that have pricing tables for specific zone
     *
     * @return Carrier[]
     */
    public function findCarriersForZone(\App\Domain\Pricing\Entity\PricingZone $zone): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.pricingTables', 'pt')
            ->where('pt.zone = :zone')
            ->andWhere('c.isActive = true')
            ->andWhere('pt.isActive = true')
            ->setParameter('zone', $zone)
            ->orderBy('c.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get carrier statistics
     */
    public function getCarrierStatistics(): array
    {
        return $this->createQueryBuilder('c')
            ->select([
                'c.id', 
                'c.code', 
                'c.name', 
                'COUNT(DISTINCT pt.id) as pricingTableCount',
                'COUNT(DISTINCT ads.id) as additionalServiceCount'
            ])
            ->leftJoin('c.pricingTables', 'pt')
            ->leftJoin('c.additionalServices', 'ads')
            ->where('c.isActive = true')
            ->groupBy('c.id')
            ->orderBy('c.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(Carrier $carrier, bool $flush = false): void
    {
        $this->getEntityManager()->persist($carrier);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Carrier $carrier, bool $flush = false): void
    {
        $this->getEntityManager()->remove($carrier);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}