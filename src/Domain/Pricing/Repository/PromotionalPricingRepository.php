<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Repository;

use App\Domain\Pricing\Entity\PromotionalPricing;
use App\Domain\Pricing\Entity\CustomerPricing;
use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PromotionalPricing>
 */
class PromotionalPricingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PromotionalPricing::class);
    }

    /**
     * Find active promotions for customer
     *
     * @return PromotionalPricing[]
     */
    public function findActiveByCustomer(Customer $customer): array
    {
        return $this->createQueryBuilder('pp')
            ->join('pp.customerPricing', 'cp')
            ->where('cp.customer = :customer')
            ->andWhere('pp.isActive = true')
            ->andWhere('pp.validFrom <= CURRENT_TIMESTAMP()')
            ->andWhere('pp.validUntil >= CURRENT_TIMESTAMP()')
            ->setParameter('customer', $customer)
            ->orderBy('pp.priorityLevel', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find promotion by code
     */
    public function findByCode(string $promoCode): ?PromotionalPricing
    {
        return $this->createQueryBuilder('pp')
            ->where('pp.promoCode = :promoCode')
            ->andWhere('pp.isActive = true')
            ->andWhere('pp.validFrom <= CURRENT_TIMESTAMP()')
            ->andWhere('pp.validUntil >= CURRENT_TIMESTAMP()')
            ->setParameter('promoCode', $promoCode)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active global promotions (not tied to specific customer pricing)
     *
     * @return PromotionalPricing[]
     */
    public function findActiveGlobal(): array
    {
        return $this->createQueryBuilder('pp')
            ->where('pp.customerPricing IS NULL')
            ->andWhere('pp.isActive = true')
            ->andWhere('pp.validFrom <= CURRENT_TIMESTAMP()')
            ->andWhere('pp.validUntil >= CURRENT_TIMESTAMP()')
            ->orderBy('pp.priorityLevel', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(PromotionalPricing $promotionalPricing, bool $flush = false): void
    {
        $this->getEntityManager()->persist($promotionalPricing);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PromotionalPricing $promotionalPricing, bool $flush = false): void
    {
        $this->getEntityManager()->remove($promotionalPricing);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}