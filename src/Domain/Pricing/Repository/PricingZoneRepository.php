<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Repository;

use App\Domain\Pricing\Entity\PricingZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PricingZone>
 */
class PricingZoneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PricingZone::class);
    }

    /**
     * Find zone by code
     */
    public function findByCode(string $code): ?PricingZone
    {
        return $this->findOneBy(['code' => strtoupper($code)]);
    }

    /**
     * Find active zones ordered by sort order
     *
     * @return PricingZone[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('z')
            ->where('z.isActive = true')
            ->orderBy('z.sortOrder', 'ASC')
            ->addOrderBy('z.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find zones by type
     *
     * @return PricingZone[]
     */
    public function findByType(string $zoneType): array
    {
        return $this->createQueryBuilder('z')
            ->where('z.zoneType = :zoneType')
            ->andWhere('z.isActive = true')
            ->setParameter('zoneType', $zoneType)
            ->orderBy('z.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find zone by country code
     */
    public function findByCountry(string $countryCode): ?PricingZone
    {
        return $this->createQueryBuilder('z')
            ->where('JSON_CONTAINS(z.countries, :country) = 1')
            ->andWhere('z.isActive = true')
            ->setParameter('country', json_encode(strtoupper($countryCode)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find zone by postal code
     */
    public function findByPostalCode(string $postalCode): ?PricingZone
    {
        $zones = $this->createQueryBuilder('z')
            ->where('z.postalCodePatterns IS NOT NULL')
            ->andWhere('z.isActive = true')
            ->getQuery()
            ->getResult();

        foreach ($zones as $zone) {
            if ($zone->matchesPostalCode($postalCode)) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * Get zones with their pricing table counts
     */
    public function getZoneStatistics(): array
    {
        return $this->createQueryBuilder('z')
            ->select(['z.id', 'z.code', 'z.name', 'z.zoneType', 'COUNT(pt.id) as pricingTableCount'])
            ->leftJoin('z.pricingTables', 'pt')
            ->where('z.isActive = true')
            ->groupBy('z.id')
            ->orderBy('z.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(PricingZone $zone, bool $flush = false): void
    {
        $this->getEntityManager()->persist($zone);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PricingZone $zone, bool $flush = false): void
    {
        $this->getEntityManager()->remove($zone);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}