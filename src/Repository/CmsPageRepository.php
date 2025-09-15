<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CmsPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CmsPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CmsPage::class);
    }

    public function save(CmsPage $page, bool $flush = false): void
    {
        $this->getEntityManager()->persist($page);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findPublishedBySlug(string $slug): ?CmsPage
    {
        return $this->createQueryBuilder('p')
            ->where('p.slug = :slug')
            ->andWhere('p.status = :status')
            ->setParameter('slug', $slug)
            ->setParameter('status', 'published')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find page by slug (regardless of status)
     */
    public function findBySlug(string $slug): ?CmsPage
    {
        return $this->createQueryBuilder('p')
            ->where('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find pages with filters and pagination
     *
     * @return CmsPage[]
     */
    public function findWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('p');

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('p.title', ':search'),
                $qb->expr()->like('p.content', ':search'),
                $qb->expr()->like('p.author', ':search')
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('p.type = :type')
               ->setParameter('type', $filters['type']);
        }

        $qb->orderBy('p.createdAt', 'DESC');

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
     * Count pages with filters
     */
    public function countWithFilters(array $filters): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)');

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('p.title', ':search'),
                $qb->expr()->like('p.content', ':search'),
                $qb->expr()->like('p.author', ':search')
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('p.type = :type')
               ->setParameter('type', $filters['type']);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}

