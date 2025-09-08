<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Secret;
use App\Enum\SecretCategory;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Secret>
 */
class SecretRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Secret::class);
    }

    public function save(Secret $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Secret $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findActiveByName(string $category, string $name): ?Secret
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.category = :category')
            ->andWhere('s.name = :name')
            ->andWhere('s.isActive = :active')
            ->andWhere('s.expiresAt IS NULL OR s.expiresAt > :now')
            ->setParameter('category', $category)
            ->setParameter('name', $name)
            ->setParameter('active', true)
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.category = :category')
            ->setParameter('category', $category)
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('s.version', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByCategoryName(string $categoryName): ?Secret
    {
        [$category, $name] = explode('.', $categoryName, 2);
        return $this->findActiveByName($category, $name);
    }

    public function findExpiredSecrets(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.expiresAt IS NOT NULL')
            ->andWhere('s.expiresAt <= :now')
            ->andWhere('s.isActive = :active')
            ->setParameter('now', new DateTimeImmutable())
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    public function findSecretsForRotation(int $daysBeforeExpiry = 30): array
    {
        $rotationDate = (new DateTimeImmutable())->modify("+{$daysBeforeExpiry} days");

        return $this->createQueryBuilder('s')
            ->andWhere('s.expiresAt IS NOT NULL')
            ->andWhere('s.expiresAt <= :rotationDate')
            ->andWhere('s.isActive = :active')
            ->setParameter('rotationDate', $rotationDate)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    public function findVersionsBySecret(string $category, string $name): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.category = :category')
            ->andWhere('s.name = :name')
            ->setParameter('category', $category)
            ->setParameter('name', $name)
            ->orderBy('s.version', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getNextVersion(string $category, string $name): int
    {
        $result = $this->createQueryBuilder('s')
            ->select('MAX(s.version) as maxVersion')
            ->andWhere('s.category = :category')
            ->andWhere('s.name = :name')
            ->setParameter('category', $category)
            ->setParameter('name', $name)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }

    public function findAllActiveSecrets(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.isActive = :active')
            ->andWhere('s.expiresAt IS NULL OR s.expiresAt > :now')
            ->setParameter('active', true)
            ->setParameter('now', new DateTimeImmutable())
            ->orderBy('s.category', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySearchCriteria(array $criteria): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s');

        if (isset($criteria['category'])) {
            $qb->andWhere('s.category = :category')
                ->setParameter('category', $criteria['category']);
        }

        if (isset($criteria['name'])) {
            $qb->andWhere('s.name LIKE :name')
                ->setParameter('name', '%' . $criteria['name'] . '%');
        }

        if (isset($criteria['is_active'])) {
            $qb->andWhere('s.isActive = :active')
                ->setParameter('active', $criteria['is_active']);
        }

        if (isset($criteria['expires_soon'])) {
            $daysAhead = $criteria['expires_soon'];
            $futureDate = (new DateTimeImmutable())->modify("+{$daysAhead} days");
            $qb->andWhere('s.expiresAt IS NOT NULL')
                ->andWhere('s.expiresAt <= :futureDate')
                ->setParameter('futureDate', $futureDate);
        }

        return $qb->orderBy('s.category', 'ASC')->addOrderBy('s.name', 'ASC');
    }

    public function updateLastAccessed(Secret $secret): void
    {
        $secret->setLastAccessedAt(new DateTimeImmutable());
        $this->save($secret, true);
    }

    public function getCategoriesStats(): array
    {
        $result = $this->createQueryBuilder('s')
            ->select('s.category, COUNT(s.id) as total, SUM(CASE WHEN s.isActive = true THEN 1 ELSE 0 END) as active')
            ->groupBy('s.category')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['category']] = [
                'total' => (int) $row['total'],
                'active' => (int) $row['active'],
                'inactive' => (int) $row['total'] - (int) $row['active'],
            ];
        }

        return $stats;
    }
}