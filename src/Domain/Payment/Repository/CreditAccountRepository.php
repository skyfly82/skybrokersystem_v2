<?php

declare(strict_types=1);

namespace App\Domain\Payment\Repository;

use App\Domain\Payment\Entity\CreditAccount;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<CreditAccount>
 */
class CreditAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CreditAccount::class);
    }

    public function save(CreditAccount $creditAccount, bool $flush = false): void
    {
        $this->getEntityManager()->persist($creditAccount);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CreditAccount $creditAccount, bool $flush = false): void
    {
        $this->getEntityManager()->remove($creditAccount);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByUser(User $user): ?CreditAccount
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function findByAccountNumber(string $accountNumber): ?CreditAccount
    {
        return $this->findOneBy(['accountNumber' => $accountNumber]);
    }

    public function findActiveByUser(User $user): ?CreditAccount
    {
        return $this->findOneBy([
            'user' => $user,
            'status' => CreditAccount::STATUS_ACTIVE
        ]);
    }

    /**
     * @return CreditAccount[]
     */
    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status]);
    }

    /**
     * @return CreditAccount[]
     */
    public function findAccountsNeedingReview(): array
    {
        return $this->createQueryBuilder('ca')
            ->where('ca.nextReviewDate <= :now')
            ->andWhere('ca.status = :activeStatus')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('activeStatus', CreditAccount::STATUS_ACTIVE)
            ->orderBy('ca.nextReviewDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return CreditAccount[]
     */
    public function findOverdraftedAccounts(): array
    {
        return $this->createQueryBuilder('ca')
            ->where('CAST(ca.usedCredit AS DECIMAL(10,2)) > CAST(ca.creditLimit AS DECIMAL(10,2))')
            ->andWhere('ca.status = :activeStatus')
            ->setParameter('activeStatus', CreditAccount::STATUS_ACTIVE)
            ->orderBy('ca.usedCredit', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return CreditAccount[]
     */
    public function findAccountsWithLowCredit(float $thresholdPercentage = 0.1): array
    {
        return $this->createQueryBuilder('ca')
            ->where('CAST(ca.availableCredit AS DECIMAL(10,2)) <= (CAST(ca.creditLimit AS DECIMAL(10,2)) * :threshold)')
            ->andWhere('ca.status = :activeStatus')
            ->setParameter('threshold', $thresholdPercentage)
            ->setParameter('activeStatus', CreditAccount::STATUS_ACTIVE)
            ->orderBy('ca.availableCredit', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalCreditLimitByStatus(string $status): string
    {
        $result = $this->createQueryBuilder('ca')
            ->select('SUM(CAST(ca.creditLimit AS DECIMAL(10,2))) as total')
            ->where('ca.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (string)$result : '0.00';
    }

    public function getTotalUsedCreditByStatus(string $status): string
    {
        $result = $this->createQueryBuilder('ca')
            ->select('SUM(CAST(ca.usedCredit AS DECIMAL(10,2))) as total')
            ->where('ca.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (string)$result : '0.00';
    }

    /**
     * Get credit accounts statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('ca');
        
        $result = $qb
            ->select([
                'ca.status',
                'COUNT(ca.id) as account_count',
                'SUM(CAST(ca.creditLimit AS DECIMAL(10,2))) as total_credit_limit',
                'SUM(CAST(ca.usedCredit AS DECIMAL(10,2))) as total_used_credit',
                'SUM(CAST(ca.availableCredit AS DECIMAL(10,2))) as total_available_credit'
            ])
            ->groupBy('ca.status')
            ->getQuery()
            ->getResult();

        $statistics = [];
        foreach ($result as $row) {
            $statistics[$row['status']] = [
                'account_count' => (int)$row['account_count'],
                'total_credit_limit' => $row['total_credit_limit'] ?? '0.00',
                'total_used_credit' => $row['total_used_credit'] ?? '0.00',
                'total_available_credit' => $row['total_available_credit'] ?? '0.00'
            ];
        }

        return $statistics;
    }

    public function createFilterQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('ca');

        if (!empty($filters['status'])) {
            $qb->andWhere('ca.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['account_type'])) {
            $qb->andWhere('ca.accountType = :accountType')
               ->setParameter('accountType', $filters['account_type']);
        }

        if (!empty($filters['currency'])) {
            $qb->andWhere('ca.currency = :currency')
               ->setParameter('currency', $filters['currency']);
        }

        if (!empty($filters['min_credit_limit'])) {
            $qb->andWhere('CAST(ca.creditLimit AS DECIMAL(10,2)) >= :minCreditLimit')
               ->setParameter('minCreditLimit', $filters['min_credit_limit']);
        }

        if (!empty($filters['max_credit_limit'])) {
            $qb->andWhere('CAST(ca.creditLimit AS DECIMAL(10,2)) <= :maxCreditLimit')
               ->setParameter('maxCreditLimit', $filters['max_credit_limit']);
        }

        if (!empty($filters['overdue_only']) && $filters['overdue_only']) {
            $qb->andWhere('CAST(ca.usedCredit AS DECIMAL(10,2)) > CAST(ca.creditLimit AS DECIMAL(10,2))');
        }

        if (!empty($filters['needs_review']) && $filters['needs_review']) {
            $qb->andWhere('ca.nextReviewDate <= :now')
               ->setParameter('now', new \DateTimeImmutable());
        }

        return $qb;
    }
}