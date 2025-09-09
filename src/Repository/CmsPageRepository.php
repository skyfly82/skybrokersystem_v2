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
}

