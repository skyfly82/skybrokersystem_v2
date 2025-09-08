<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Secret;
use App\Entity\SecretAuditLog;
use App\Enum\SecretAction;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SecretAuditLog>
 */
class SecretAuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecretAuditLog::class);
    }

    public function save(SecretAuditLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SecretAuditLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function logAction(
        Secret $secret,
        SecretAction $action,
        ?string $userIdentifier = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $metadata = null,
        ?string $details = null
    ): SecretAuditLog {
        $log = new SecretAuditLog();
        $log->setSecret($secret)
            ->setAction($action->value)
            ->setUserIdentifier($userIdentifier)
            ->setIpAddress($ipAddress)
            ->setUserAgent($userAgent)
            ->setMetadata($metadata)
            ->setDetails($details);

        $this->save($log, true);

        return $log;
    }

    public function findBySecret(Secret $secret, int $limit = 50): array
    {
        return $this->createQueryBuilder('al')
            ->andWhere('al.secret = :secret')
            ->setParameter('secret', $secret)
            ->orderBy('al.performedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByAction(SecretAction $action, int $limit = 100): array
    {
        return $this->createQueryBuilder('al')
            ->andWhere('al.action = :action')
            ->setParameter('action', $action->value)
            ->orderBy('al.performedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByUser(string $userIdentifier, int $limit = 100): array
    {
        return $this->createQueryBuilder('al')
            ->andWhere('al.userIdentifier = :user')
            ->setParameter('user', $userIdentifier)
            ->orderBy('al.performedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findRecentActivity(int $hours = 24, int $limit = 100): array
    {
        $since = (new DateTimeImmutable())->modify("-{$hours} hours");

        return $this->createQueryBuilder('al')
            ->leftJoin('al.secret', 's')
            ->addSelect('s')
            ->andWhere('al.performedAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('al.performedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findSecurityEvents(int $days = 7): array
    {
        $since = (new DateTimeImmutable())->modify("-{$days} days");

        return $this->createQueryBuilder('al')
            ->leftJoin('al.secret', 's')
            ->addSelect('s')
            ->andWhere('al.performedAt >= :since')
            ->andWhere('al.action IN (:securityActions)')
            ->setParameter('since', $since)
            ->setParameter('securityActions', [
                SecretAction::ACCESS_DENIED->value,
                SecretAction::DELETED->value,
                SecretAction::DEACTIVATED->value,
            ])
            ->orderBy('al.performedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getActionStats(int $days = 30): array
    {
        $since = (new DateTimeImmutable())->modify("-{$days} days");

        $result = $this->createQueryBuilder('al')
            ->select('al.action, COUNT(al.id) as count')
            ->andWhere('al.performedAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('al.action')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['action']] = (int) $row['count'];
        }

        return $stats;
    }

    public function findBySearchCriteria(array $criteria): QueryBuilder
    {
        $qb = $this->createQueryBuilder('al')
            ->leftJoin('al.secret', 's')
            ->addSelect('s');

        if (isset($criteria['secret_id'])) {
            $qb->andWhere('al.secret = :secret')
                ->setParameter('secret', $criteria['secret_id']);
        }

        if (isset($criteria['category'])) {
            $qb->andWhere('s.category = :category')
                ->setParameter('category', $criteria['category']);
        }

        if (isset($criteria['action'])) {
            $qb->andWhere('al.action = :action')
                ->setParameter('action', $criteria['action']);
        }

        if (isset($criteria['user_identifier'])) {
            $qb->andWhere('al.userIdentifier = :user')
                ->setParameter('user', $criteria['user_identifier']);
        }

        if (isset($criteria['from_date'])) {
            $qb->andWhere('al.performedAt >= :fromDate')
                ->setParameter('fromDate', $criteria['from_date']);
        }

        if (isset($criteria['to_date'])) {
            $qb->andWhere('al.performedAt <= :toDate')
                ->setParameter('toDate', $criteria['to_date']);
        }

        if (isset($criteria['ip_address'])) {
            $qb->andWhere('al.ipAddress = :ip')
                ->setParameter('ip', $criteria['ip_address']);
        }

        return $qb->orderBy('al.performedAt', 'DESC');
    }

    public function cleanupOldLogs(int $daysToKeep = 365): int
    {
        $cutoffDate = (new DateTimeImmutable())->modify("-{$daysToKeep} days");

        return $this->createQueryBuilder('al')
            ->delete()
            ->andWhere('al.performedAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();
    }
}