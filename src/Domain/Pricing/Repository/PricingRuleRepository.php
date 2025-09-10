<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Repository;

use App\Domain\Pricing\Entity\PricingRule;
use App\Domain\Pricing\Entity\PricingTable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PricingRule>
 */
class PricingRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PricingRule::class);
    }

    /**
     * Find pricing rule for specific weight
     */
    public function findByWeight(PricingTable $pricingTable, float $weight): ?PricingRule
    {
        return $this->createQueryBuilder('pr')
            ->where('pr.pricingTable = :pricingTable')
            ->andWhere('pr.weightFrom <= :weight')
            ->andWhere('pr.weightTo IS NULL OR pr.weightTo >= :weight')
            ->andWhere('pr.isActive = true')
            ->setParameter('pricingTable', $pricingTable)
            ->setParameter('weight', $weight)
            ->orderBy('pr.weightFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find pricing rule by table and weight (alias for findByWeight)
     */
    public function findByTableAndWeight(PricingTable $pricingTable, float $weight): ?PricingRule
    {
        return $this->findByWeight($pricingTable, $weight);
    }

    /**
     * Find all rules for pricing table
     *
     * @return PricingRule[]
     */
    public function findByPricingTable(PricingTable $pricingTable): array
    {
        return $this->createQueryBuilder('pr')
            ->where('pr.pricingTable = :pricingTable')
            ->andWhere('pr.isActive = true')
            ->setParameter('pricingTable', $pricingTable)
            ->orderBy('pr.weightFrom', 'ASC')
            ->addOrderBy('pr.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find overlapping weight ranges
     *
     * @return PricingRule[]
     */
    public function findOverlappingRules(PricingTable $pricingTable, float $weightFrom, ?float $weightTo = null): array
    {
        $qb = $this->createQueryBuilder('pr')
            ->where('pr.pricingTable = :pricingTable')
            ->andWhere('pr.isActive = true')
            ->setParameter('pricingTable', $pricingTable);

        if ($weightTo !== null) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->andX(
                        'pr.weightFrom <= :weightFrom',
                        'pr.weightTo IS NULL OR pr.weightTo >= :weightFrom'
                    ),
                    $qb->expr()->andX(
                        'pr.weightFrom <= :weightTo',
                        'pr.weightTo IS NULL OR pr.weightTo >= :weightTo'
                    ),
                    $qb->expr()->andX(
                        'pr.weightFrom >= :weightFrom',
                        'pr.weightTo IS NOT NULL AND pr.weightTo <= :weightTo'
                    )
                )
            )
            ->setParameter('weightFrom', $weightFrom)
            ->setParameter('weightTo', $weightTo);
        } else {
            $qb->andWhere('pr.weightFrom <= :weightFrom')
               ->andWhere('pr.weightTo IS NULL OR pr.weightTo >= :weightFrom')
               ->setParameter('weightFrom', $weightFrom);
        }

        return $qb->getQuery()->getResult();
    }

    public function save(PricingRule $pricingRule, bool $flush = false): void
    {
        $this->getEntityManager()->persist($pricingRule);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PricingRule $pricingRule, bool $flush = false): void
    {
        $this->getEntityManager()->remove($pricingRule);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}