<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CustomerAddress;
use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Customer Address Repository
 * Provides specialized queries for customer address management
 */
class CustomerAddressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerAddress::class);
    }

    public function save(CustomerAddress $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CustomerAddress $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find customer addresses with filtering and pagination
     */
    public function findCustomerAddresses(int $customerId, array $filters = [], array $pagination = []): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.customer = :customerId')
            ->setParameter('customerId', $customerId);

        // Apply filters
        if (isset($filters['type']) && !empty($filters['type'])) {
            if ($filters['type'] === 'both') {
                $qb->andWhere('a.type = :type OR a.type = :typeBoth')
                   ->setParameter('type', $filters['type'])
                   ->setParameter('typeBoth', 'both');
            } else {
                $qb->andWhere('(a.type = :type OR a.type = :typeBoth)')
                   ->setParameter('type', $filters['type'])
                   ->setParameter('typeBoth', 'both');
            }
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $qb->andWhere('(a.name LIKE :search OR a.contactName LIKE :search OR a.city LIKE :search OR a.address LIKE :search)')
               ->setParameter('search', $searchTerm);
        }

        if (isset($filters['country']) && !empty($filters['country'])) {
            $qb->andWhere('a.country = :country')
               ->setParameter('country', $filters['country']);
        }

        if (isset($filters['active_only']) && $filters['active_only']) {
            $qb->andWhere('a.isActive = true');
        }

        // Apply sorting
        $sortField = $pagination['sort'] ?? 'name';
        $sortOrder = $pagination['order'] ?? 'asc';

        // Ensure valid sort field
        $validSortFields = ['name', 'contactName', 'city', 'usageCount', 'createdAt', 'lastUsedAt'];
        if (!in_array($sortField, $validSortFields)) {
            $sortField = 'name';
        }

        $qb->orderBy("a.{$sortField}", $sortOrder);

        // Add secondary sort by usage count for relevance
        if ($sortField !== 'usageCount') {
            $qb->addOrderBy('a.usageCount', 'desc');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find default addresses for a customer by type
     */
    public function findDefaultAddresses(int $customerId, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.customer = :customerId')
            ->andWhere('a.isDefault = true')
            ->andWhere('a.isActive = true')
            ->setParameter('customerId', $customerId);

        if ($type) {
            $qb->andWhere('(a.type = :type OR a.type = :typeBoth)')
               ->setParameter('type', $type)
               ->setParameter('typeBoth', 'both');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find most used addresses for quick access
     */
    public function findMostUsedAddresses(int $customerId, int $limit = 5, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.customer = :customerId')
            ->andWhere('a.isActive = true')
            ->andWhere('a.usageCount > 0')
            ->orderBy('a.usageCount', 'desc')
            ->addOrderBy('a.lastUsedAt', 'desc')
            ->setMaxResults($limit)
            ->setParameter('customerId', $customerId);

        if ($type) {
            $qb->andWhere('(a.type = :type OR a.type = :typeBoth)')
               ->setParameter('type', $type)
               ->setParameter('typeBoth', 'both');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Clear default status for specific type
     */
    public function clearDefaultStatus(int $customerId, string $type): void
    {
        $qb = $this->createQueryBuilder('a')
            ->update()
            ->set('a.isDefault', 'false')
            ->where('a.customer = :customerId')
            ->andWhere('(a.type = :type OR a.type = :typeBoth)')
            ->setParameter('customerId', $customerId)
            ->setParameter('type', $type)
            ->setParameter('typeBoth', 'both');

        $qb->getQuery()->execute();
    }

    /**
     * Find addresses by postal code for validation
     */
    public function findByPostalCode(string $postalCode, ?string $country = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.postalCode = :postalCode')
            ->andWhere('a.isValidated = true')
            ->setParameter('postalCode', $postalCode);

        if ($country) {
            $qb->andWhere('a.country = :country')
               ->setParameter('country', $country);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find duplicate addresses to prevent duplicates
     */
    public function findDuplicateAddress(int $customerId, array $addressData): ?CustomerAddress
    {
        return $this->createQueryBuilder('a')
            ->where('a.customer = :customerId')
            ->andWhere('a.address = :address')
            ->andWhere('a.postalCode = :postalCode')
            ->andWhere('a.city = :city')
            ->andWhere('a.country = :country')
            ->andWhere('a.contactName = :contactName')
            ->setParameter('customerId', $customerId)
            ->setParameter('address', $addressData['address'])
            ->setParameter('postalCode', $addressData['postal_code'])
            ->setParameter('city', $addressData['city'])
            ->setParameter('country', $addressData['country'])
            ->setParameter('contactName', $addressData['contact_name'])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get address usage statistics
     */
    public function getAddressUsageStats(int $customerId): array
    {
        return $this->createQueryBuilder('a')
            ->select('
                COUNT(a.id) as total_addresses,
                SUM(CASE WHEN a.isActive = true THEN 1 ELSE 0 END) as active_addresses,
                SUM(CASE WHEN a.isDefault = true THEN 1 ELSE 0 END) as default_addresses,
                SUM(a.usageCount) as total_usage,
                AVG(a.usageCount) as avg_usage,
                MAX(a.usageCount) as max_usage
            ')
            ->where('a.customer = :customerId')
            ->setParameter('customerId', $customerId)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Find recently used addresses
     */
    public function findRecentlyUsed(int $customerId, int $days = 30, int $limit = 10): array
    {
        $fromDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('a')
            ->where('a.customer = :customerId')
            ->andWhere('a.lastUsedAt >= :fromDate')
            ->andWhere('a.isActive = true')
            ->orderBy('a.lastUsedAt', 'desc')
            ->setMaxResults($limit)
            ->setParameter('customerId', $customerId)
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * Bulk update usage counts
     */
    public function incrementUsageCount(int $addressId): void
    {
        $this->createQueryBuilder('a')
            ->update()
            ->set('a.usageCount', 'a.usageCount + 1')
            ->set('a.lastUsedAt', ':now')
            ->where('a.id = :addressId')
            ->setParameter('addressId', $addressId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Clean up unused addresses (housekeeping)
     */
    public function findUnusedAddresses(int $customerId, int $daysUnused = 365): array
    {
        $cutoffDate = new \DateTime("-{$daysUnused} days");

        return $this->createQueryBuilder('a')
            ->where('a.customer = :customerId')
            ->andWhere('a.usageCount = 0')
            ->andWhere('a.createdAt < :cutoffDate')
            ->andWhere('a.isDefault = false')
            ->setParameter('customerId', $customerId)
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * Export addresses for customer
     */
    public function getAddressesForExport(int $customerId, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.customer = :customerId')
            ->andWhere('a.isActive = true')
            ->orderBy('a.name', 'asc')
            ->setParameter('customerId', $customerId);

        if ($type) {
            $qb->andWhere('(a.type = :type OR a.type = :typeBoth)')
               ->setParameter('type', $type)
               ->setParameter('typeBoth', 'both');
        }

        return $qb->getQuery()->getResult();
    }
}