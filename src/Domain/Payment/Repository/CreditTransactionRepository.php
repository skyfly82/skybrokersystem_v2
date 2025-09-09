<?php

declare(strict_types=1);

namespace App\Domain\Payment\Repository;

use App\Domain\Payment\Entity\CreditAccount;
use App\Domain\Payment\Entity\CreditTransaction;
use App\Domain\Payment\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<CreditTransaction>
 */
class CreditTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CreditTransaction::class);
    }

    public function save(CreditTransaction $creditTransaction, bool $flush = false): void
    {
        $this->getEntityManager()->persist($creditTransaction);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CreditTransaction $creditTransaction, bool $flush = false): void
    {
        $this->getEntityManager()->remove($creditTransaction);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByTransactionId(string $transactionId): ?CreditTransaction
    {
        return $this->findOneBy(['transactionId' => $transactionId]);
    }

    public function findByPayment(Payment $payment): ?CreditTransaction
    {
        return $this->findOneBy(['payment' => $payment]);
    }

    /**
     * @return CreditTransaction[]
     */
    public function findByCreditAccount(CreditAccount $creditAccount): array
    {
        return $this->findBy(
            ['creditAccount' => $creditAccount],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * @return CreditTransaction[]
     */
    public function findOutstandingCharges(CreditAccount $creditAccount): array
    {
        return $this->createQueryBuilder('ct')
            ->where('ct.creditAccount = :creditAccount')
            ->andWhere('ct.transactionType = :chargeType')
            ->andWhere('ct.status IN (:outstandingStatuses)')
            ->setParameter('creditAccount', $creditAccount)
            ->setParameter('chargeType', CreditTransaction::TYPE_CHARGE)
            ->setParameter('outstandingStatuses', [
                CreditTransaction::STATUS_AUTHORIZED,
                CreditTransaction::STATUS_SETTLED
            ])
            ->orderBy('ct.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return CreditTransaction[]
     */
    public function findOverdueTransactions(?CreditAccount $creditAccount = null): array
    {
        $qb = $this->createQueryBuilder('ct')
            ->where('ct.dueDate < :now')
            ->andWhere('ct.status NOT IN (:excludedStatuses)')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('excludedStatuses', [
                CreditTransaction::STATUS_SETTLED,
                CreditTransaction::STATUS_FAILED,
                CreditTransaction::STATUS_CANCELLED,
                CreditTransaction::STATUS_REFUNDED
            ]);

        if ($creditAccount !== null) {
            $qb->andWhere('ct.creditAccount = :creditAccount')
               ->setParameter('creditAccount', $creditAccount);
        }

        return $qb->orderBy('ct.dueDate', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * @return CreditTransaction[]
     */
    public function findPendingAuthorizations(CreditAccount $creditAccount): array
    {
        return $this->findBy([
            'creditAccount' => $creditAccount,
            'transactionType' => CreditTransaction::TYPE_AUTHORIZATION,
            'status' => CreditTransaction::STATUS_PENDING
        ], ['createdAt' => 'DESC']);
    }

    /**
     * @return CreditTransaction[]
     */
    public function findByStatusAndType(string $status, string $type): array
    {
        return $this->findBy([
            'status' => $status,
            'transactionType' => $type
        ], ['createdAt' => 'DESC']);
    }

    public function getTotalAmountByAccountAndType(
        CreditAccount $creditAccount,
        string $transactionType,
        ?string $status = null
    ): string {
        $qb = $this->createQueryBuilder('ct')
            ->select('SUM(ct.amount) as total')
            ->where('ct.creditAccount = :creditAccount')
            ->andWhere('ct.transactionType = :transactionType')
            ->setParameter('creditAccount', $creditAccount)
            ->setParameter('transactionType', $transactionType);

        if ($status !== null) {
            $qb->andWhere('ct.status = :status')
               ->setParameter('status', $status);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result !== null ? (string)$result : '0.00';
    }

    public function getOutstandingBalance(CreditAccount $creditAccount): string
    {
        $charges = $this->getTotalAmountByAccountAndType(
            $creditAccount,
            CreditTransaction::TYPE_CHARGE,
            CreditTransaction::STATUS_SETTLED
        );

        $fees = $this->getTotalAmountByAccountAndType(
            $creditAccount,
            CreditTransaction::TYPE_FEE,
            CreditTransaction::STATUS_SETTLED
        );

        $interest = $this->getTotalAmountByAccountAndType(
            $creditAccount,
            CreditTransaction::TYPE_INTEREST,
            CreditTransaction::STATUS_SETTLED
        );

        $payments = $this->getTotalAmountByAccountAndType(
            $creditAccount,
            CreditTransaction::TYPE_PAYMENT,
            CreditTransaction::STATUS_SETTLED
        );

        $refunds = $this->getTotalAmountByAccountAndType(
            $creditAccount,
            CreditTransaction::TYPE_REFUND,
            CreditTransaction::STATUS_SETTLED
        );

        $totalCharges = (float)$charges + (float)$fees + (float)$interest;
        $totalCredits = (float)$payments + (float)$refunds;
        $outstanding = $totalCharges - $totalCredits;

        return number_format(max(0, $outstanding), 2, '.', '');
    }

    /**
     * Get transaction statistics for a credit account
     */
    public function getAccountStatistics(CreditAccount $creditAccount): array
    {
        $qb = $this->createQueryBuilder('ct');
        
        $result = $qb
            ->select([
                'ct.transactionType',
                'ct.status',
                'COUNT(ct.id) as transaction_count',
                'SUM(ct.amount) as total_amount'
            ])
            ->where('ct.creditAccount = :creditAccount')
            ->setParameter('creditAccount', $creditAccount)
            ->groupBy('ct.transactionType', 'ct.status')
            ->getQuery()
            ->getResult();

        $statistics = [];
        foreach ($result as $row) {
            $type = $row['transactionType'];
            $status = $row['status'];
            
            if (!isset($statistics[$type])) {
                $statistics[$type] = [];
            }
            
            $statistics[$type][$status] = [
                'transaction_count' => (int)$row['transaction_count'],
                'total_amount' => $row['total_amount'] ?? '0.00'
            ];
        }

        return $statistics;
    }

    public function createFilterQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('ct');

        if (!empty($filters['credit_account_id'])) {
            $qb->andWhere('ct.creditAccount = :creditAccountId')
               ->setParameter('creditAccountId', $filters['credit_account_id']);
        }

        if (!empty($filters['transaction_type'])) {
            $qb->andWhere('ct.transactionType = :transactionType')
               ->setParameter('transactionType', $filters['transaction_type']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('ct.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['currency'])) {
            $qb->andWhere('ct.currency = :currency')
               ->setParameter('currency', $filters['currency']);
        }

        if (!empty($filters['min_amount'])) {
            $qb->andWhere('ct.amount >= :minAmount')
               ->setParameter('minAmount', $filters['min_amount']);
        }

        if (!empty($filters['max_amount'])) {
            $qb->andWhere('ct.amount <= :maxAmount')
               ->setParameter('maxAmount', $filters['max_amount']);
        }

        if (!empty($filters['from_date'])) {
            $qb->andWhere('ct.createdAt >= :fromDate')
               ->setParameter('fromDate', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $qb->andWhere('ct.createdAt <= :toDate')
               ->setParameter('toDate', $filters['to_date']);
        }

        if (!empty($filters['overdue_only']) && $filters['overdue_only']) {
            $qb->andWhere('ct.dueDate < :now')
               ->andWhere('ct.status NOT IN (:excludedStatuses)')
               ->setParameter('now', new \DateTimeImmutable())
               ->setParameter('excludedStatuses', [
                   CreditTransaction::STATUS_SETTLED,
                   CreditTransaction::STATUS_FAILED,
                   CreditTransaction::STATUS_CANCELLED,
                   CreditTransaction::STATUS_REFUNDED
               ]);
        }

        return $qb;
    }
}