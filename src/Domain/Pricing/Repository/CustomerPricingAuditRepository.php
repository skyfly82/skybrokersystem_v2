<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Repository;

use App\Domain\Pricing\Entity\CustomerPricingAudit;
use App\Domain\Pricing\Entity\CustomerPricing;
use App\Entity\SystemUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerPricingAudit>
 */
class CustomerPricingAuditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerPricingAudit::class);
    }

    /**
     * Find audit logs for customer pricing
     *
     * @return CustomerPricingAudit[]
     */
    public function findByCustomerPricing(CustomerPricing $customerPricing, int $limit = 50): array
    {
        return $this->createQueryBuilder('cpa')
            ->where('cpa.customerPricing = :customerPricing')
            ->setParameter('customerPricing', $customerPricing)
            ->orderBy('cpa.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find audit logs by action
     *
     * @return CustomerPricingAudit[]
     */
    public function findByAction(string $action, int $limit = 100): array
    {
        return $this->createQueryBuilder('cpa')
            ->where('cpa.action = :action')
            ->setParameter('action', $action)
            ->orderBy('cpa.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find audit logs by user
     *
     * @return CustomerPricingAudit[]
     */
    public function findByUser(SystemUser $user, int $limit = 100): array
    {
        return $this->createQueryBuilder('cpa')
            ->where('cpa.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('cpa.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find audit logs in date range
     *
     * @return CustomerPricingAudit[]
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate, int $limit = 200): array
    {
        return $this->createQueryBuilder('cpa')
            ->where('cpa.createdAt >= :startDate')
            ->andWhere('cpa.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('cpa.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function save(CustomerPricingAudit $auditLog, bool $flush = false): void
    {
        $this->getEntityManager()->persist($auditLog);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}