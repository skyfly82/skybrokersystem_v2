<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CourierService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CourierService>
 */
class CourierServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourierService::class);
    }

    /**
     * Find all active courier services
     *
     * @return CourierService[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('cs')
            ->andWhere('cs.active = :active')
            ->setParameter('active', true)
            ->orderBy('cs.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find courier service by code
     */
    public function findByCode(string $code): ?CourierService
    {
        return $this->createQueryBuilder('cs')
            ->andWhere('cs.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find domestic courier services
     *
     * @return CourierService[]
     */
    public function findDomestic(): array
    {
        return $this->createQueryBuilder('cs')
            ->andWhere('cs.active = :active')
            ->andWhere('cs.domestic = :domestic')
            ->setParameter('active', true)
            ->setParameter('domestic', true)
            ->orderBy('cs.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find international courier services
     *
     * @return CourierService[]
     */
    public function findInternational(): array
    {
        return $this->createQueryBuilder('cs')
            ->andWhere('cs.active = :active')
            ->andWhere('cs.international = :international')
            ->setParameter('active', true)
            ->setParameter('international', true)
            ->orderBy('cs.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}