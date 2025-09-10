<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Repository;

use App\Domain\Pricing\Entity\AdditionalServicePrice;
use App\Domain\Pricing\Entity\AdditionalService;
use App\Domain\Pricing\Entity\PricingTable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdditionalServicePrice>
 */
class AdditionalServicePriceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdditionalServicePrice::class);
    }

    /**
     * Find price for service and pricing table
     */
    public function findByServiceAndTable(AdditionalService $service, PricingTable $pricingTable): ?AdditionalServicePrice
    {
        return $this->createQueryBuilder('asp')
            ->where('asp.additionalService = :service')
            ->andWhere('asp.pricingTable = :pricingTable')
            ->andWhere('asp.isActive = true')
            ->setParameter('service', $service)
            ->setParameter('pricingTable', $pricingTable)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find price for service and zone code
     */
    public function findByServiceAndZone(AdditionalService $service, string $zoneCode): ?AdditionalServicePrice
    {
        return $this->createQueryBuilder('asp')
            ->join('asp.pricingTable', 'pt')
            ->join('pt.zone', 'z')
            ->where('asp.additionalService = :service')
            ->andWhere('z.code = :zoneCode')
            ->andWhere('asp.isActive = true')
            ->andWhere('pt.isActive = true')
            ->andWhere('pt.effectiveFrom <= CURRENT_TIMESTAMP()')
            ->andWhere('pt.effectiveUntil IS NULL OR pt.effectiveUntil >= CURRENT_TIMESTAMP()')
            ->setParameter('service', $service)
            ->setParameter('zoneCode', $zoneCode)
            ->orderBy('pt.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all prices for pricing table
     *
     * @return AdditionalServicePrice[]
     */
    public function findByPricingTable(PricingTable $pricingTable): array
    {
        return $this->createQueryBuilder('asp')
            ->where('asp.pricingTable = :pricingTable')
            ->andWhere('asp.isActive = true')
            ->setParameter('pricingTable', $pricingTable)
            ->join('asp.additionalService', 'ads')
            ->orderBy('ads.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(AdditionalServicePrice $price, bool $flush = false): void
    {
        $this->getEntityManager()->persist($price);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AdditionalServicePrice $price, bool $flush = false): void
    {
        $this->getEntityManager()->remove($price);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}