<?php

declare(strict_types=1);

namespace App\Domain\Payment\Repository;

use App\Domain\Payment\Entity\Payment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * Find payment by payment ID
     */
    public function findByPaymentId(string $paymentId): ?Payment
    {
        return $this->findOneBy(['paymentId' => $paymentId]);
    }

    /**
     * Find payment by external transaction ID (PayNow payment ID)
     */
    public function findByExternalTransactionId(string $externalTransactionId): ?Payment
    {
        return $this->findOneBy(['externalTransactionId' => $externalTransactionId]);
    }

    /**
     * Find payments by user
     *
     * @return Payment[]
     */
    public function findByUser(User $user, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find payments by status
     *
     * @return Payment[]
     */
    public function findByStatus(string $status, int $limit = 100, int $offset = 0): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find payments by payment method
     *
     * @return Payment[]
     */
    public function findByPaymentMethod(string $paymentMethod, int $limit = 100, int $offset = 0): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.paymentMethod = :paymentMethod')
            ->setParameter('paymentMethod', $paymentMethod)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find PayNow payments
     *
     * @return Payment[]
     */
    public function findPayNowPayments(int $limit = 100, int $offset = 0): array
    {
        return $this->findByPaymentMethod(Payment::METHOD_PAYNOW, $limit, $offset);
    }

    /**
     * Find pending PayNow payments (for status checking)
     *
     * @return Payment[]
     */
    public function findPendingPayNowPayments(int $limit = 50): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.paymentMethod = :paymentMethod')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('paymentMethod', Payment::METHOD_PAYNOW)
            ->setParameter('statuses', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])
            ->orderBy('p.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find payments by date range
     *
     * @return Payment[]
     */
    public function findByDateRange(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $paymentMethod = null,
        int $limit = 100,
        int $offset = 0
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->where('p.createdAt >= :startDate')
            ->andWhere('p.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($paymentMethod) {
            $qb->andWhere('p.paymentMethod = :paymentMethod')
                ->setParameter('paymentMethod', $paymentMethod);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStatistics(?string $paymentMethod = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select([
                'p.status',
                'COUNT(p.id) as count',
                'SUM(p.amount) as total_amount',
                'AVG(p.amount) as avg_amount',
            ])
            ->groupBy('p.status');

        if ($paymentMethod) {
            $qb->andWhere('p.paymentMethod = :paymentMethod')
                ->setParameter('paymentMethod', $paymentMethod);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get PayNow payment statistics
     */
    public function getPayNowStatistics(): array
    {
        return $this->getPaymentStatistics(Payment::METHOD_PAYNOW);
    }

    /**
     * Count payments by user
     */
    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get total amount by user and status
     */
    public function getTotalAmountByUserAndStatus(User $user, string $status): string
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.user = :user')
            ->andWhere('p.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();

        return (string) ($result ?? '0.00');
    }

    public function save(Payment $payment, bool $flush = false): void
    {
        $this->getEntityManager()->persist($payment);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Payment $payment, bool $flush = false): void
    {
        $this->getEntityManager()->remove($payment);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}