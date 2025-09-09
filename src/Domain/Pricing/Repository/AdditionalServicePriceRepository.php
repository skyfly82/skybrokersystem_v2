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