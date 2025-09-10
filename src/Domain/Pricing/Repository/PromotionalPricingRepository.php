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
            ->orderBy('pp.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active promotions by carrier and optional customer/promo codes
     *
     * @return PromotionalPricing[]
     */
    public function findActivePromotions(string $carrierCode, ?int $customerId = null, ?array $promoCodes = null): array
    {
        $qb = $this->createQueryBuilder('pp')
            ->leftJoin('pp.customerPricing', 'cp')
            ->leftJoin('cp.basePricingTable', 'pt')  
            ->leftJoin('pt.carrier', 'c')
            ->where('pp.isActive = true')
            ->andWhere('pp.validFrom <= CURRENT_TIMESTAMP()')
            ->andWhere('pp.validUntil >= CURRENT_TIMESTAMP()');
            
        // Add carrier filter - either global promotions or carrier-specific ones
        $qb->andWhere(
            $qb->expr()->orX(
                'pp.customerPricing IS NULL', // Global promotions
                'c.code = :carrierCode' // Carrier-specific promotions
            )
        )
        ->setParameter('carrierCode', $carrierCode);

        if ($customerId !== null) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'pp.customerPricing IS NULL', // Global promotions available to all
                    'cp.customer = :customerId' // Customer-specific promotions
                )
            )
            ->setParameter('customerId', $customerId);
        }

        if ($promoCodes !== null && !empty($promoCodes)) {
            $qb->andWhere('pp.promoCode IN (:promoCodes)')
               ->setParameter('promoCodes', $promoCodes);
        }

        return $qb->orderBy('pp.id', 'DESC')
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
            ->orderBy('pp.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all active promotions at given date
     *
     * @return PromotionalPricing[]
     */
    public function findActivePromotionsByDate(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('pp')
            ->where('pp.isActive = true')
            ->andWhere('pp.validFrom <= :date OR pp.validFrom IS NULL')
            ->andWhere('pp.validUntil >= :date OR pp.validUntil IS NULL')
            ->setParameter('date', $date)
            ->orderBy('pp.id', 'DESC')
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