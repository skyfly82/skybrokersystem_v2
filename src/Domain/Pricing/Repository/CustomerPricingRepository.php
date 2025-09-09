<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Repository;

use App\Domain\Pricing\Entity\CustomerPricing;
use App\Domain\Pricing\Entity\PricingTable;
use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerPricing>
 */
class CustomerPricingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerPricing::class);
    }

    /**
     * Find active pricing for customer and base pricing table
     */
    public function findActiveByCustomerAndTable(Customer $customer, PricingTable $basePricingTable): ?CustomerPricing
    {
        return $this->createQueryBuilder('cp')
            ->where('cp.customer = :customer')
            ->andWhere('cp.basePricingTable = :basePricingTable')
            ->andWhere('cp.isActive = true')
            ->andWhere('cp.effectiveFrom <= CURRENT_TIMESTAMP()')
            ->andWhere('cp.effectiveUntil IS NULL OR cp.effectiveUntil >= CURRENT_TIMESTAMP()')
            ->setParameter('customer', $customer)
            ->setParameter('basePricingTable', $basePricingTable)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all active pricing for customer
     *
     * @return CustomerPricing[]
     */
    public function findActiveByCustomer(Customer $customer): array
    {
        return $this->createQueryBuilder('cp')
            ->where('cp.customer = :customer')
            ->andWhere('cp.isActive = true')
            ->andWhere('cp.effectiveFrom <= CURRENT_TIMESTAMP()')
            ->andWhere('cp.effectiveUntil IS NULL OR cp.effectiveUntil >= CURRENT_TIMESTAMP()')
            ->setParameter('customer', $customer)
            ->orderBy('cp.priorityLevel', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pricing expiring soon
     *
     * @return CustomerPricing[]
     */
    public function findExpiringSoon(int $daysAhead = 30): array
    {
        $futureDate = new \DateTimeImmutable(sprintf('+%d days', $daysAhead));
        
        return $this->createQueryBuilder('cp')
            ->where('cp.effectiveUntil IS NOT NULL')
            ->andWhere('cp.effectiveUntil <= :futureDate')
            ->andWhere('cp.effectiveUntil >= CURRENT_TIMESTAMP()')
            ->andWhere('cp.isActive = true')
            ->setParameter('futureDate', $futureDate)
            ->orderBy('cp.effectiveUntil', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(CustomerPricing $customerPricing, bool $flush = false): void
    {
        $this->getEntityManager()->persist($customerPricing);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CustomerPricing $customerPricing, bool $flush = false): void
    {
        $this->getEntityManager()->remove($customerPricing);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}