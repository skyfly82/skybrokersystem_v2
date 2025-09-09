<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Repository;

use App\Domain\Pricing\Entity\AdditionalService;
use App\Domain\Pricing\Entity\Carrier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdditionalService>
 */
class AdditionalServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdditionalService::class);
    }

    /**
     * Find service by code
     */
    public function findByCode(string $code): ?AdditionalService
    {
        return $this->findOneBy(['code' => strtoupper($code)]);
    }

    /**
     * Find active services for carrier
     *
     * @return AdditionalService[]
     */
    public function findActiveByCarrier(Carrier $carrier): array
    {
        return $this->createQueryBuilder('ads')
            ->where('ads.carrier = :carrier')
            ->andWhere('ads.isActive = true')
            ->setParameter('carrier', $carrier)
            ->orderBy('ads.sortOrder', 'ASC')
            ->addOrderBy('ads.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find services by type
     *
     * @return AdditionalService[]
     */
    public function findByType(string $serviceType): array
    {
        return $this->createQueryBuilder('ads')
            ->where('ads.serviceType = :serviceType')
            ->andWhere('ads.isActive = true')
            ->setParameter('serviceType', $serviceType)
            ->orderBy('ads.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(AdditionalService $service, bool $flush = false): void
    {
        $this->getEntityManager()->persist($service);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AdditionalService $service, bool $flush = false): void
    {
        $this->getEntityManager()->remove($service);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}