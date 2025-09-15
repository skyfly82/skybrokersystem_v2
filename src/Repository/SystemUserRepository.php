<?php

namespace App\Repository;

use App\Entity\SystemUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SystemUser>
 */
class SystemUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemUser::class);
    }

    /**
     * Find system users with filters and pagination
     *
     * @return SystemUser[]
     */
    public function findWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('u');

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('u.firstName', ':search'),
                $qb->expr()->like('u.lastName', ':search'),
                $qb->expr()->like('u.email', ':search')
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['department'])) {
            $qb->andWhere('u.department = :department')
               ->setParameter('department', $filters['department']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('u.status = :status')
               ->setParameter('status', $filters['status']);
        }

        $qb->orderBy('u.createdAt', 'DESC');

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
     * Count system users with filters
     */
    public function countWithFilters(array $filters): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)');

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('u.firstName', ':search'),
                $qb->expr()->like('u.lastName', ':search'),
                $qb->expr()->like('u.email', ':search')
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['department'])) {
            $qb->andWhere('u.department = :department')
               ->setParameter('department', $filters['department']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('u.status = :status')
               ->setParameter('status', $filters['status']);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
