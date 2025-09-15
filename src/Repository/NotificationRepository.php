<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\CustomerUser;
use App\Entity\Notification;
use App\Entity\SystemUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Find notifications for customer user
     *
     * @return Notification[]
     */
    public function findForCustomerUser(CustomerUser $customerUser, int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.customerUser = :customerUser')
            ->setParameter('customerUser', $customerUser)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find notifications for system user
     *
     * @return Notification[]
     */
    public function findForSystemUser(SystemUser $systemUser, int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.systemUser = :systemUser')
            ->setParameter('systemUser', $systemUser)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pending notifications to send
     *
     * @return Notification[]
     */
    public function findPendingToSend(): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.status = :status')
            ->andWhere('n.scheduledAt <= :now OR n.scheduledAt IS NULL')
            ->setParameter('status', 'pending')
            ->setParameter('now', new \DateTime())
            ->orderBy('n.priority', 'DESC')
            ->addOrderBy('n.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find unread notifications for customer user
     *
     * @return Notification[]
     */
    public function findUnreadForCustomerUser(CustomerUser $customerUser): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.customerUser = :customerUser')
            ->andWhere('n.read = :read')
            ->setParameter('customerUser', $customerUser)
            ->setParameter('read', false)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find unread notifications for system user
     *
     * @return Notification[]
     */
    public function findUnreadForSystemUser(SystemUser $systemUser): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.systemUser = :systemUser')
            ->andWhere('n.read = :read')
            ->setParameter('systemUser', $systemUser)
            ->setParameter('read', false)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count unread notifications for customer user
     */
    public function countUnreadForCustomerUser(CustomerUser $customerUser): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.customerUser = :customerUser')
            ->andWhere('n.read = :read')
            ->setParameter('customerUser', $customerUser)
            ->setParameter('read', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count unread notifications for system user
     */
    public function countUnreadForSystemUser(SystemUser $systemUser): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.systemUser = :systemUser')
            ->andWhere('n.read = :read')
            ->setParameter('systemUser', $systemUser)
            ->setParameter('read', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get notification statistics
     */
    public function getStatistics(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $qb = $this->createQueryBuilder('n')
            ->select([
                'COUNT(n.id) as total_notifications',
                'SUM(CASE WHEN n.status = \'sent\' THEN 1 ELSE 0 END) as sent_notifications',
                'SUM(CASE WHEN n.status = \'pending\' THEN 1 ELSE 0 END) as pending_notifications',
                'SUM(CASE WHEN n.status = \'failed\' THEN 1 ELSE 0 END) as failed_notifications',
                'SUM(CASE WHEN n.type = \'email\' THEN 1 ELSE 0 END) as email_notifications',
                'SUM(CASE WHEN n.type = \'sms\' THEN 1 ELSE 0 END) as sms_notifications'
            ])
            ->andWhere('n.createdAt >= :from')
            ->andWhere('n.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Find notifications with filters and pagination
     *
     * @return Notification[]
     */
    public function findWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('n')
            ->leftJoin('n.customer', 'c')
            ->leftJoin('n.customerUser', 'cu')
            ->leftJoin('n.systemUser', 'su');

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('n.subject', ':search'),
                $qb->expr()->like('n.message', ':search'),
                $qb->expr()->like('c.companyName', ':search'),
                $qb->expr()->like('cu.firstName', ':search'),
                $qb->expr()->like('su.firstName', ':search')
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('n.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('n.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $qb->andWhere('n.priority = :priority')
               ->setParameter('priority', $filters['priority']);
        }

        $qb->orderBy('n.createdAt', 'DESC');

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
     * Count notifications with filters
     */
    public function countWithFilters(array $filters): int
    {
        $qb = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->leftJoin('n.customer', 'c')
            ->leftJoin('n.customerUser', 'cu')
            ->leftJoin('n.systemUser', 'su');

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('n.subject', ':search'),
                $qb->expr()->like('n.message', ':search'),
                $qb->expr()->like('c.companyName', ':search'),
                $qb->expr()->like('cu.firstName', ':search'),
                $qb->expr()->like('su.firstName', ':search')
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('n.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('n.status = :status')
               ->setParameter('status', $filters['status']);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}