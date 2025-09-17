<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for CustomerRepository
 */
class CustomerRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CustomerRepository $customerRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->customerRepository = $this->entityManager
            ->getRepository(Customer::class);

        // Clean up database before each test
        $this->entityManager->createQuery('DELETE FROM App\Entity\Customer')->execute();
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    protected function tearDown(): void
    {
        // Clean up after tests
        if (isset($this->entityManager) && $this->entityManager->isOpen()) {
            $this->entityManager->createQuery('DELETE FROM App\Entity\Customer')->execute();
            $this->entityManager->flush();
            $this->entityManager->clear();
            $this->entityManager->close();
        }

        // Unset the property instead of setting it to null to avoid type errors
        unset($this->entityManager);

        parent::tearDown();
    }

    public function testFindWithFiltersSearchByCompanyName(): void
    {
        // Create test customers
        $customer1 = $this->createCustomer('ACME Corporation', 'active', 'business');
        $customer2 = $this->createCustomer('Beta Solutions', 'active', 'business');
        $customer3 = $this->createCustomer('Gamma Industries', 'inactive', 'business');

        $this->entityManager->flush();

        // Test search by company name
        $results = $this->customerRepository->findWithFilters(['search' => 'ACME']);
        $this->assertCount(1, $results);
        $this->assertEquals($customer1->getId(), $results[0]->getId());

        // Test partial search
        $results = $this->customerRepository->findWithFilters(['search' => 'Corp']);
        $this->assertCount(1, $results);
        $this->assertEquals($customer1->getId(), $results[0]->getId());

        // Test case insensitive search
        $results = $this->customerRepository->findWithFilters(['search' => 'acme']);
        $this->assertCount(1, $results);
        $this->assertEquals($customer1->getId(), $results[0]->getId());
    }

    public function testFindWithFiltersSearchByEmail(): void
    {
        $customer1 = $this->createCustomer('Company One', 'active', 'business');
        $customer1->setEmail('test@acme.com');

        $customer2 = $this->createCustomer('Company Two', 'active', 'business');
        $customer2->setEmail('info@beta.com');

        $this->entityManager->flush();

        $results = $this->customerRepository->findWithFilters(['search' => 'acme.com']);
        $this->assertCount(1, $results);
        $this->assertEquals($customer1->getId(), $results[0]->getId());
    }

    public function testFindWithFiltersSearchByPhone(): void
    {
        $customer1 = $this->createCustomer('Company One', 'active', 'business');
        $customer1->setPhone('+48123456789');

        $customer2 = $this->createCustomer('Company Two', 'active', 'business');
        $customer2->setPhone('+48987654321');

        $this->entityManager->flush();

        $results = $this->customerRepository->findWithFilters(['search' => '123456']);
        $this->assertCount(1, $results);
        $this->assertEquals($customer1->getId(), $results[0]->getId());
    }

    public function testFindWithFiltersSearchByVatNumber(): void
    {
        $customer1 = $this->createCustomer('Company One', 'active', 'business');
        $customer1->setVatNumber('PL1234567890');

        $customer2 = $this->createCustomer('Company Two', 'active', 'business');
        $customer2->setVatNumber('PL0987654321');

        $this->entityManager->flush();

        $results = $this->customerRepository->findWithFilters(['search' => '123456']);
        $this->assertCount(1, $results);
        $this->assertEquals($customer1->getId(), $results[0]->getId());
    }

    public function testFindWithFiltersStatus(): void
    {
        $customer1 = $this->createCustomer('Company One', 'active', 'business');
        $customer2 = $this->createCustomer('Company Two', 'inactive', 'business');
        $customer3 = $this->createCustomer('Company Three', 'suspended', 'business');

        $this->entityManager->flush();

        // Test active status filter
        $results = $this->customerRepository->findWithFilters(['status' => 'active']);
        $this->assertCount(1, $results);
        $this->assertEquals($customer1->getId(), $results[0]->getId());

        // Test inactive status filter
        $results = $this->customerRepository->findWithFilters(['status' => 'inactive']);
        $this->assertCount(1, $results);
        $this->assertEquals($customer2->getId(), $results[0]->getId());

        // Test suspended status filter
        $results = $this->customerRepository->findWithFilters(['status' => 'suspended']);
        $this->assertCount(1, $results);
        $this->assertEquals($customer3->getId(), $results[0]->getId());
    }

    public function testFindWithFiltersType(): void
    {
        $customer1 = $this->createCustomer('ACME Corp', 'active', 'business');
        $customer2 = $this->createCustomer('John Doe', 'active', 'individual');
        $customer3 = $this->createCustomer('Beta Corp', 'active', 'business');

        $this->entityManager->flush();

        // Test business type filter
        $results = $this->customerRepository->findWithFilters(['type' => 'business']);
        $this->assertCount(2, $results);

        // Test individual type filter
        $results = $this->customerRepository->findWithFilters(['type' => 'individual']);
        $this->assertCount(1, $results);
        $this->assertEquals($customer2->getId(), $results[0]->getId());
    }

    public function testFindWithFiltersMultipleFilters(): void
    {
        $customer1 = $this->createCustomer('ACME Corp', 'active', 'business');
        $customer2 = $this->createCustomer('ACME Ltd', 'inactive', 'business');
        $customer3 = $this->createCustomer('Beta Corp', 'active', 'business');

        $this->entityManager->flush();

        // Test combining search and status filters
        $results = $this->customerRepository->findWithFilters([
            'search' => 'ACME',
            'status' => 'active'
        ]);
        $this->assertCount(1, $results);
        $this->assertEquals($customer1->getId(), $results[0]->getId());

        // Test combining all filters
        $results = $this->customerRepository->findWithFilters([
            'search' => 'ACME',
            'status' => 'inactive',
            'type' => 'business'
        ]);
        $this->assertCount(1, $results);
        $this->assertEquals($customer2->getId(), $results[0]->getId());
    }

    public function testFindWithFiltersOrderByCreatedAt(): void
    {
        $customer1 = $this->createCustomer('Company A', 'active', 'business');
        // Set explicit timestamp for first customer (older)
        $customer1->setCreatedAt(new \DateTime('2023-01-01 10:00:00'));
        $this->entityManager->flush();

        $customer2 = $this->createCustomer('Company B', 'active', 'business');
        // Set explicit timestamp for second customer (newer)
        $customer2->setCreatedAt(new \DateTime('2023-01-01 11:00:00'));
        $this->entityManager->flush();

        $results = $this->customerRepository->findWithFilters([]);
        $this->assertCount(2, $results);

        // Should be ordered by createdAt DESC (newest first)
        // Compare by company name instead of ID to avoid auto-increment issues
        $this->assertEquals('Company B', $results[0]->getCompanyName());
        $this->assertEquals('Company A', $results[1]->getCompanyName());
    }

    public function testFindWithFiltersPagination(): void
    {
        // Create 5 customers
        for ($i = 1; $i <= 5; $i++) {
            $this->createCustomer("Company $i", 'active', 'business');
        }
        $this->entityManager->flush();

        // Test limit only
        $results = $this->customerRepository->findWithFilters(['limit' => 3]);
        $this->assertCount(3, $results);

        // Test page and limit
        $results = $this->customerRepository->findWithFilters(['page' => 1, 'limit' => 2]);
        $this->assertCount(2, $results);

        $results = $this->customerRepository->findWithFilters(['page' => 2, 'limit' => 2]);
        $this->assertCount(2, $results);

        $results = $this->customerRepository->findWithFilters(['page' => 3, 'limit' => 2]);
        $this->assertCount(1, $results);
    }

    public function testCountWithFilters(): void
    {
        $customer1 = $this->createCustomer('ACME Corp', 'active', 'business');
        $customer2 = $this->createCustomer('Beta Corp', 'inactive', 'business');
        $customer3 = $this->createCustomer('John Doe', 'active', 'individual');

        $this->entityManager->flush();

        // Test count all
        $count = $this->customerRepository->countWithFilters([]);
        $this->assertEquals(3, $count);

        // Test count with status filter
        $count = $this->customerRepository->countWithFilters(['status' => 'active']);
        $this->assertEquals(2, $count);

        // Test count with type filter
        $count = $this->customerRepository->countWithFilters(['type' => 'business']);
        $this->assertEquals(2, $count);

        // Test count with search filter
        $count = $this->customerRepository->countWithFilters(['search' => 'ACME']);
        $this->assertEquals(1, $count);

        // Test count with multiple filters
        $count = $this->customerRepository->countWithFilters([
            'status' => 'active',
            'type' => 'business'
        ]);
        $this->assertEquals(1, $count);
    }

    public function testCountWithFiltersEmptyResult(): void
    {
        $this->createCustomer('ACME Corp', 'active', 'business');
        $this->entityManager->flush();

        $count = $this->customerRepository->countWithFilters(['search' => 'NonExistent']);
        $this->assertEquals(0, $count);
    }

    public function testFindWithFiltersEmptyResult(): void
    {
        $this->createCustomer('ACME Corp', 'active', 'business');
        $this->entityManager->flush();

        $results = $this->customerRepository->findWithFilters(['search' => 'NonExistent']);
        $this->assertCount(0, $results);
    }

    public function testFindWithFiltersNoFilters(): void
    {
        $customer1 = $this->createCustomer('Company A', 'active', 'business');
        $customer2 = $this->createCustomer('Company B', 'inactive', 'individual');

        $this->entityManager->flush();

        $results = $this->customerRepository->findWithFilters([]);
        $this->assertCount(2, $results);
    }

    private function createCustomer(string $companyName, string $status, string $type): Customer
    {
        $customer = new Customer();
        $customer->setCompanyName($companyName)
                 ->setStatus($status)
                 ->setType($type);

        $this->entityManager->persist($customer);

        return $customer;
    }
}