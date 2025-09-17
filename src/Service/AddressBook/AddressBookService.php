<?php

declare(strict_types=1);

namespace App\Service\AddressBook;

use App\Entity\CustomerAddress;
use App\Repository\CustomerAddressRepository;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AddressBookService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomerAddressRepository $addressRepository,
        private readonly CustomerRepository $customerRepository,
        private readonly AddressValidationService $addressValidator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get customer addresses with filtering and pagination
     */
    public function getCustomerAddresses(int $customerId, array $filters = [], array $pagination = []): array
    {
        try {
            $customer = $this->customerRepository->find($customerId);
            if (!$customer) {
                throw new \Exception('Customer not found');
            }

            $queryBuilder = $this->addressRepository->createQueryBuilder('a')
                ->where('a.customer = :customer')
                ->setParameter('customer', $customer);

            // Apply filters
            if (!empty($filters['type'])) {
                if ($filters['type'] === 'sender') {
                    $queryBuilder->andWhere('(a.type = :sender OR a.type = :both)')
                        ->setParameter('sender', 'sender')
                        ->setParameter('both', 'both');
                } elseif ($filters['type'] === 'recipient') {
                    $queryBuilder->andWhere('(a.type = :recipient OR a.type = :both)')
                        ->setParameter('recipient', 'recipient')
                        ->setParameter('both', 'both');
                } elseif ($filters['type'] !== 'both') {
                    $queryBuilder->andWhere('a.type = :type')
                        ->setParameter('type', $filters['type']);
                }
            }

            if (!empty($filters['search'])) {
                $queryBuilder->andWhere('(a.name LIKE :search OR a.contactName LIKE :search OR a.address LIKE :search OR a.city LIKE :search)')
                    ->setParameter('search', '%' . $filters['search'] . '%');
            }

            if (!empty($filters['country'])) {
                $queryBuilder->andWhere('a.country = :country')
                    ->setParameter('country', $filters['country']);
            }

            if (!empty($filters['active_only'])) {
                $queryBuilder->andWhere('a.isActive = true');
            }

            // Apply sorting
            $sort = $pagination['sort'] ?? 'name';
            $order = $pagination['order'] ?? 'asc';
            $queryBuilder->orderBy('a.' . $sort, $order);

            // Apply pagination
            $page = $pagination['page'] ?? 1;
            $limit = $pagination['limit'] ?? 50;
            $offset = ($page - 1) * $limit;

            $queryBuilder->setFirstResult($offset)->setMaxResults($limit);

            $addresses = $queryBuilder->getQuery()->getResult();
            $total = $this->addressRepository->count(['customer' => $customer]);

            return [
                'data' => array_map([$this, 'formatAddress'], $addresses),
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get customer addresses', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);

            return [
                'data' => [],
                'pagination' => [
                    'page' => 1,
                    'limit' => 50,
                    'total' => 0,
                    'pages' => 0
                ]
            ];
        }
    }

    /**
     * Get single customer address
     */
    public function getCustomerAddress(int $customerId, int $addressId): ?array
    {
        try {
            $address = $this->addressRepository->findOneBy([
                'id' => $addressId,
                'customer' => $customerId
            ]);

            return $address ? $this->formatAddress($address) : null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get customer address', [
                'customer_id' => $customerId,
                'address_id' => $addressId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Create new address
     */
    public function createAddress(int $customerId, array $data): array
    {
        $this->entityManager->beginTransaction();

        try {
            $customer = $this->customerRepository->find($customerId);
            if (!$customer) {
                throw new \Exception('Customer not found');
            }

            $address = new CustomerAddress();
            $address->setCustomer($customer);
            $this->updateAddressData($address, $data);

            $this->entityManager->persist($address);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $this->formatAddress($address);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Failed to create address', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Update address
     */
    public function updateAddress(int $addressId, array $data): array
    {
        $this->entityManager->beginTransaction();

        try {
            $address = $this->addressRepository->find($addressId);
            if (!$address) {
                throw new \Exception('Address not found');
            }

            $this->updateAddressData($address, $data);

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $this->formatAddress($address);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Failed to update address', [
                'address_id' => $addressId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Delete address
     */
    public function deleteAddress(int $addressId): void
    {
        $this->entityManager->beginTransaction();

        try {
            $address = $this->addressRepository->find($addressId);
            if (!$address) {
                throw new \Exception('Address not found');
            }

            $this->entityManager->remove($address);
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Failed to delete address', [
                'address_id' => $addressId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Set default address for specific type
     */
    public function setDefaultAddress(int $customerId, int $addressId, string $type): void
    {
        $this->entityManager->beginTransaction();

        try {
            $customer = $this->customerRepository->find($customerId);
            if (!$customer) {
                throw new \Exception('Customer not found');
            }

            // Unset current default for this type
            $this->addressRepository->createQueryBuilder('a')
                ->update()
                ->set('a.isDefault', ':false')
                ->where('a.customer = :customer')
                ->andWhere('a.type = :type OR a.type = :both')
                ->setParameter('false', false)
                ->setParameter('customer', $customer)
                ->setParameter('type', $type)
                ->setParameter('both', 'both')
                ->getQuery()
                ->execute();

            // Set new default
            $address = $this->addressRepository->find($addressId);
            if (!$address || $address->getCustomer() !== $customer) {
                throw new \Exception('Address not found or not owned by customer');
            }

            $address->setIsDefault(true);
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Failed to set default address', [
                'customer_id' => $customerId,
                'address_id' => $addressId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Import multiple addresses
     */
    public function importAddresses(int $customerId, array $addresses, bool $overwriteExisting = false): array
    {
        $this->entityManager->beginTransaction();

        try {
            $customer = $this->customerRepository->find($customerId);
            if (!$customer) {
                throw new \Exception('Customer not found');
            }

            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($addresses as $index => $addressData) {
                try {
                    // Check if address already exists
                    $existing = $this->findExistingAddress($customer, $addressData);

                    if ($existing && !$overwriteExisting) {
                        $skipped++;
                        continue;
                    }

                    if ($existing && $overwriteExisting) {
                        $this->updateAddressData($existing, $addressData);
                    } else {
                        $address = new CustomerAddress();
                        $address->setCustomer($customer);
                        $this->updateAddressData($address, $addressData);
                        $this->entityManager->persist($address);
                    }

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Row {$index}: " . $e->getMessage();
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Failed to import addresses', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Export addresses
     */
    public function exportAddresses(int $customerId, string $format = 'json', ?string $type = null): array
    {
        try {
            $filters = [];
            if ($type) {
                $filters['type'] = $type;
            }

            $result = $this->getCustomerAddresses($customerId, $filters);
            $addresses = $result['data'];

            switch ($format) {
                case 'csv':
                    return $this->exportToCsv($addresses);
                case 'excel':
                    return $this->exportToExcel($addresses);
                default:
                    return [
                        'format' => 'json',
                        'data' => $addresses,
                        'count' => count($addresses)
                    ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to export addresses', [
                'customer_id' => $customerId,
                'format' => $format,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get address suggestions
     */
    public function getAddressSuggestions(string $query, string $country = 'PL'): array
    {
        return $this->addressValidator->getAddressSuggestions($query, $country);
    }

    // Private helper methods

    private function formatAddress(CustomerAddress $address): array
    {
        return [
            'id' => $address->getId(),
            'name' => $address->getName(),
            'contact_name' => $address->getContactName(),
            'email' => $address->getEmail(),
            'phone' => $address->getPhone(),
            'company_name' => $address->getCompanyName(),
            'address' => $address->getAddress(),
            'city' => $address->getCity(),
            'postal_code' => $address->getPostalCode(),
            'country' => $address->getCountry(),
            'type' => $address->getType(),
            'is_default' => $address->isDefault(),
            'is_active' => $address->isActive(),
            'created_at' => $address->getCreatedAt(),
            'updated_at' => $address->getUpdatedAt()
        ];
    }

    private function updateAddressData(CustomerAddress $address, array $data): void
    {
        $address->setName($data['name'] ?? '');
        $address->setContactName($data['contact_name'] ?? '');
        $address->setEmail($data['email'] ?? '');
        $address->setPhone($data['phone'] ?? '');
        $address->setCompanyName($data['company_name'] ?? null);
        $address->setAddress($data['address'] ?? '');
        $address->setCity($data['city'] ?? '');
        $address->setPostalCode($data['postal_code'] ?? '');
        $address->setCountry($data['country'] ?? 'Poland');
        $address->setType($data['type'] ?? 'both');
        $address->setIsActive($data['is_active'] ?? true);
        $address->setIsDefault($data['is_default'] ?? false);
    }

    private function findExistingAddress($customer, array $addressData): ?CustomerAddress
    {
        return $this->addressRepository->findOneBy([
            'customer' => $customer,
            'address' => $addressData['address'] ?? '',
            'postalCode' => $addressData['postal_code'] ?? ''
        ]);
    }

    private function exportToCsv(array $addresses): array
    {
        $csv = "Name,Email,Phone,Company,Address,City,Postal Code,Country,Type\n";

        foreach ($addresses as $address) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $address['name'],
                $address['email'],
                $address['phone'],
                $address['company'] ?? '',
                $address['address'],
                $address['city'],
                $address['postal_code'],
                $address['country'],
                $address['type']
            );
        }

        return [
            'format' => 'csv',
            'content' => $csv,
            'filename' => 'addresses_' . date('Y-m-d') . '.csv',
            'mime_type' => 'text/csv'
        ];
    }

    private function exportToExcel(array $addresses): array
    {
        // This would require a library like PhpSpreadsheet
        // For now, return CSV format
        return $this->exportToCsv($addresses);
    }
}