<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AnalyticsEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AnalyticsEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsEvent::class);
    }

    public function save(AnalyticsEvent $event, bool $flush = false): void
    {
        $this->getEntityManager()->persist($event);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findRecent(int $limit = 100): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getSummary(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id) as total, e.type as type')
            ->groupBy('e.type');

        if ($from) {
            $qb->andWhere('e.createdAt >= :from')->setParameter('from', $from);
        }
        if ($to) {
            $qb->andWhere('e.createdAt <= :to')->setParameter('to', $to);
        }

        $rows = $qb->getQuery()->getArrayResult();
        $out = [];
        foreach ($rows as $row) {
            $out[$row['type']] = (int) $row['total'];
        }
        return $out;
    }

    public function getEndpointStats(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e.endpoint as endpoint, COUNT(e.id) as hits, AVG(e.durationMs) as avgDuration, MAX(e.durationMs) as maxDuration')
            ->where('e.endpoint IS NOT NULL')
            ->groupBy('e.endpoint')
            ->orderBy('hits', 'DESC')
            ->setMaxResults($limit);
        if ($from) {
            $qb->andWhere('e.createdAt >= :from')->setParameter('from', $from);
        }
        if ($to) {
            $qb->andWhere('e.createdAt <= :to')->setParameter('to', $to);
        }
        return $qb->getQuery()->getArrayResult();
    }
}

