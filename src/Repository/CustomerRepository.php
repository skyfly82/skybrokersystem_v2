<?php

namespace App\Repository;

use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    /**
     * Find customers with filters and pagination
     *
     * @return Customer[]
     */
    public function findWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('c');

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('c.companyName', ':search'),
                $qb->expr()->like('c.email', ':search'),
                $qb->expr()->like('c.phone', ':search'),
                $qb->expr()->like('c.vatNumber', ':search')
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('c.type = :type')
               ->setParameter('type', $filters['type']);
        }

        $qb->orderBy('c.createdAt', 'DESC');

        if (!empty($filters['limit'])) {
            $qb->setMaxResults($filters['limit']);
        }

        if (!empty($filters['page']) && !empty($filters['limit'])) {
            $offset = ($filters['page'] - 1) * $filters['limit'];
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count customers with filters
     */
    public function countWithFilters(array $filters): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)');

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('c.companyName', ':search'),
                $qb->expr()->like('c.email', ':search'),
                $qb->expr()->like('c.phone', ':search'),
                $qb->expr()->like('c.vatNumber', ':search')
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('c.type = :type')
               ->setParameter('type', $filters['type']);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
