<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Customer;
use App\Entity\CustomerAddress;
use App\Repository\CustomerAddressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for CustomerAddressRepository
 */
class CustomerAddressRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CustomerAddressRepository $addressRepository;
    private Customer $customer;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->addressRepository = $this->entityManager
            ->getRepository(CustomerAddress::class);

        // Clean up database before each test
        $this->entityManager->createQuery('DELETE FROM App\Entity\CustomerAddress')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Customer')->execute();
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Create test customer
        $this->customer = new Customer();
        $this->customer->setCompanyName('Test Company');
        $this->entityManager->persist($this->customer);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        // Clean up after tests
        if (isset($this->entityManager) && $this->entityManager->isOpen()) {
            $this->entityManager->createQuery('DELETE FROM App\Entity\CustomerAddress')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\Customer')->execute();
            $this->entityManager->flush();
            $this->entityManager->clear();
            $this->entityManager->close();
        }

        // Unset the property instead of setting it to null to avoid type errors
        unset($this->entityManager);

        parent::tearDown();
    }

    public function testSaveAndRemove(): void
    {
        $address = $this->createBasicAddress('Test Address');

        // Test save
        $this->addressRepository->save($address, true);
        $this->assertNotNull($address->getId());

        $savedAddress = $this->addressRepository->find($address->getId());
        $this->assertNotNull($savedAddress);
        $this->assertEquals('Test Address', $savedAddress->getName());

        // Test remove
        $addressId = $address->getId();
        $this->addressRepository->remove($address, true);
        $removedAddress = $this->addressRepository->find($addressId);
        $this->assertNull($removedAddress);
    }

    public function testFindCustomerAddresses(): void
    {
        $address1 = $this->createBasicAddress('Home Address', 'both');
        $address2 = $this->createBasicAddress('Office Address', 'sender');
        $address3 = $this->createBasicAddress('Warehouse Address', 'recipient');

        $this->addressRepository->save($address1, false);
        $this->addressRepository->save($address2, false);
        $this->addressRepository->save($address3, false);
        $this->entityManager->flush();

        // Test without filters
        $addresses = $this->addressRepository->findCustomerAddresses($this->customer->getId());
        $this->assertCount(3, $addresses);

        // Test type filter for sender
        $addresses = $this->addressRepository->findCustomerAddresses(
            $this->customer->getId(),
            ['type' => 'sender']
        );
        $this->assertCount(2, $addresses); // 'both' and 'sender'

        // Test type filter for recipient
        $addresses = $this->addressRepository->findCustomerAddresses(
            $this->customer->getId(),
            ['type' => 'recipient']
        );
        $this->assertCount(2, $addresses); // 'both' and 'recipient'

        // Test type filter for both
        $addresses = $this->addressRepository->findCustomerAddresses(
            $this->customer->getId(),
            ['type' => 'both']
        );
        $this->assertCount(1, $addresses); // only 'both'
    }

    public function testFindCustomerAddressesWithSearch(): void
    {
        $address1 = $this->createBasicAddress('Home Address');
        $address1->setContactName('John Doe');
        $address1->setCity('Warsaw');

        $address2 = $this->createBasicAddress('Office Address');
        $address2->setContactName('Jane Smith');
        $address2->setCity('Krakow');

        $this->addressRepository->save($address1, false);
        $this->addressRepository->save($address2, false);
        $this->entityManager->flush();

        // Test search by name
        $addresses = $this->addressRepository->findCustomerAddresses(
            $this->customer->getId(),
            ['search' => 'Home']
        );
        $this->assertCount(1, $addresses);
        $this->assertEquals($address1->getId(), $addresses[0]->getId());

        // Test search by contact name
        $addresses = $this->addressRepository->findCustomerAddresses(
            $this->customer->getId(),
            ['search' => 'John']
        );
        $this->assertCount(1, $addresses);
        $this->assertEquals($address1->getId(), $addresses[0]->getId());

        // Test search by city
        $addresses = $this->addressRepository->findCustomerAddresses(
            $this->customer->getId(),
            ['search' => 'Warsaw']
        );
        $this->assertCount(1, $addresses);
        $this->assertEquals($address1->getId(), $addresses[0]->getId());
    }

    public function testFindCustomerAddressesWithCountryFilter(): void
    {
        $address1 = $this->createBasicAddress('Poland Address');
        $address1->setCountry('Poland');

        $address2 = $this->createBasicAddress('Germany Address');
        $address2->setCountry('Germany');

        $this->addressRepository->save($address1, false);
        $this->addressRepository->save($address2, false);
        $this->entityManager->flush();

        $addresses = $this->addressRepository->findCustomerAddresses(
            $this->customer->getId(),
            ['country' => 'Poland']
        );
        $this->assertCount(1, $addresses);
        $this->assertEquals($address1->getId(), $addresses[0]->getId());
    }

    public function testFindCustomerAddressesActiveOnly(): void
    {
        $address1 = $this->createBasicAddress('Active Address');
        $address1->setIsActive(true);

        $address2 = $this->createBasicAddress('Inactive Address');
        $address2->setIsActive(false);

        $this->addressRepository->save($address1, false);
        $this->addressRepository->save($address2, false);
        $this->entityManager->flush();

        $addresses = $this->addressRepository->findCustomerAddresses(
            $this->customer->getId(),
            ['active_only' => true]
        );
        $this->assertCount(1, $addresses);
        $this->assertEquals($address1->getId(), $addresses[0]->getId());
    }

    public function testFindDefaultAddresses(): void
    {
        $address1 = $this->createBasicAddress('Default Sender', 'sender');
        $address1->setIsDefault(true);

        $address2 = $this->createBasicAddress('Default Recipient', 'recipient');
        $address2->setIsDefault(true);

        $address3 = $this->createBasicAddress('Non-default Address', 'both');
        $address3->setIsDefault(false);

        $this->addressRepository->save($address1, false);
        $this->addressRepository->save($address2, false);
        $this->addressRepository->save($address3, false);
        $this->entityManager->flush();

        // Test without type filter
        $addresses = $this->addressRepository->findDefaultAddresses($this->customer->getId());
        $this->assertCount(2, $addresses);

        // Test with sender type filter
        $addresses = $this->addressRepository->findDefaultAddresses($this->customer->getId(), 'sender');
        $this->assertCount(1, $addresses);
        $this->assertEquals($address1->getId(), $addresses[0]->getId());

        // Test with recipient type filter
        $addresses = $this->addressRepository->findDefaultAddresses($this->customer->getId(), 'recipient');
        $this->assertCount(1, $addresses);
        $this->assertEquals($address2->getId(), $addresses[0]->getId());
    }

    public function testFindMostUsedAddresses(): void
    {
        $address1 = $this->createBasicAddress('High Usage Address');
        $address1->setUsageCount(10);
        $address1->setLastUsedAt(new \DateTimeImmutable('-1 day'));

        $address2 = $this->createBasicAddress('Medium Usage Address');
        $address2->setUsageCount(5);
        $address2->setLastUsedAt(new \DateTimeImmutable('-2 days'));

        $address3 = $this->createBasicAddress('Unused Address');
        $address3->setUsageCount(0);

        $this->addressRepository->save($address1, false);
        $this->addressRepository->save($address2, false);
        $this->addressRepository->save($address3, false);
        $this->entityManager->flush();

        $addresses = $this->addressRepository->findMostUsedAddresses($this->customer->getId(), 5);
        $this->assertCount(2, $addresses); // Only addresses with usage > 0

        // Should be ordered by usage count DESC
        $this->assertEquals($address1->getId(), $addresses[0]->getId());
        $this->assertEquals($address2->getId(), $addresses[1]->getId());
    }

    public function testClearDefaultStatus(): void
    {
        $address1 = $this->createBasicAddress('Address 1', 'sender');
        $address1->setIsDefault(true);

        $address2 = $this->createBasicAddress('Address 2', 'both');
        $address2->setIsDefault(true);

        $address3 = $this->createBasicAddress('Address 3', 'recipient');
        $address3->setIsDefault(true);

        $this->addressRepository->save($address1, false);
        $this->addressRepository->save($address2, false);
        $this->addressRepository->save($address3, false);
        $this->entityManager->flush();

        // Clear default status for sender type
        $this->addressRepository->clearDefaultStatus($this->customer->getId(), 'sender');
        $this->entityManager->refresh($address1);
        $this->entityManager->refresh($address2);
        $this->entityManager->refresh($address3);

        $this->assertFalse($address1->isDefault()); // sender -> cleared
        $this->assertFalse($address2->isDefault()); // both -> cleared (matches sender)
        $this->assertTrue($address3->isDefault());  // recipient -> not cleared
    }

    public function testFindByPostalCode(): void
    {
        $address1 = $this->createBasicAddress('Address 1');
        $address1->setPostalCode('00-001')->setCountry('Poland')->setIsValidated(true);

        $address2 = $this->createBasicAddress('Address 2');
        $address2->setPostalCode('00-001')->setCountry('Germany')->setIsValidated(true);

        $address3 = $this->createBasicAddress('Address 3');
        $address3->setPostalCode('00-001')->setCountry('Poland')->setIsValidated(false);

        $this->addressRepository->save($address1, false);
        $this->addressRepository->save($address2, false);
        $this->addressRepository->save($address3, false);
        $this->entityManager->flush();

        // Test without country filter
        $addresses = $this->addressRepository->findByPostalCode('00-001');
        $this->assertCount(2, $addresses); // Only validated ones

        // Test with country filter
        $addresses = $this->addressRepository->findByPostalCode('00-001', 'Poland');
        $this->assertCount(1, $addresses);
        $this->assertEquals($address1->getId(), $addresses[0]->getId());
    }

    public function testFindDuplicateAddress(): void
    {
        $address1 = $this->createBasicAddress('Original Address');
        $address1->setAddress('ul. Testowa 123')
                 ->setPostalCode('00-001')
                 ->setCity('Warsaw')
                 ->setCountry('Poland')
                 ->setContactName('John Doe');

        $this->addressRepository->save($address1, true);

        $addressData = [
            'address' => 'ul. Testowa 123',
            'postal_code' => '00-001',
            'city' => 'Warsaw',
            'country' => 'Poland',
            'contact_name' => 'John Doe'
        ];

        // Should find the duplicate
        $duplicate = $this->addressRepository->findDuplicateAddress($this->customer->getId(), $addressData);
        $this->assertNotNull($duplicate);
        $this->assertEquals($address1->getId(), $duplicate->getId());

        // Should not find duplicate with different data
        $addressData['city'] = 'Krakow';
        $duplicate = $this->addressRepository->findDuplicateAddress($this->customer->getId(), $addressData);
        $this->assertNull($duplicate);
    }

    public function testGetAddressUsageStats(): void
    {
        $address1 = $this->createBasicAddress('Address 1');
        $address1->setIsActive(true)->setIsDefault(true)->setUsageCount(10);

        $address2 = $this->createBasicAddress('Address 2');
        $address2->setIsActive(false)->setIsDefault(false)->setUsageCount(5);

        $address3 = $this->createBasicAddress('Address 3');
        $address3->setIsActive(true)->setIsDefault(false)->setUsageCount(8);

        $this->addressRepository->save($address1, false);
        $this->addressRepository->save($address2, false);
        $this->addressRepository->save($address3, false);
        $this->entityManager->flush();

        $stats = $this->addressRepository->getAddressUsageStats($this->customer->getId());

        $this->assertEquals(3, $stats['total_addresses']);
        $this->assertEquals(2, $stats['active_addresses']);
        $this->assertEquals(1, $stats['default_addresses']);
        $this->assertEquals(23, $stats['total_usage']); // 10 + 5 + 8
        $this->assertEquals(7.666666666666667, $stats['avg_usage']); // 23/3
        $this->assertEquals(10, $stats['max_usage']);
    }

    public function testFindRecentlyUsed(): void
    {
        $address1 = $this->createBasicAddress('Recent Address');
        $address1->setLastUsedAt(new \DateTimeImmutable('-5 days'));

        $address2 = $this->createBasicAddress('Old Address');
        $address2->setLastUsedAt(new \DateTimeImmutable('-60 days'));

        $address3 = $this->createBasicAddress('Inactive Address');
        $address3->setLastUsedAt(new \DateTimeImmutable('-5 days'))->setIsActive(false);

        $this->addressRepository->save($address1, false);
        $this->addressRepository->save($address2, false);
        $this->addressRepository->save($address3, false);
        $this->entityManager->flush();

        $addresses = $this->addressRepository->findRecentlyUsed($this->customer->getId(), 30);
        $this->assertCount(1, $addresses); // Only recent and active
        $this->assertEquals($address1->getId(), $addresses[0]->getId());
    }

    public function testIncrementUsageCount(): void
    {
        $address = $this->createBasicAddress('Test Address');
        $address->setUsageCount(5);
        $this->addressRepository->save($address, true);

        $originalCount = $address->getUsageCount();
        $this->addressRepository->incrementUsageCount($address->getId());

        // Refresh entity from database
        $this->entityManager->refresh($address);

        $this->assertEquals($originalCount + 1, $address->getUsageCount());
        $this->assertInstanceOf(\DateTimeImmutable::class, $address->getLastUsedAt());
    }

    public function testFindUnusedAddresses(): void
    {
        $address1 = $this->createBasicAddress('Unused Address 1');
        $address1->setUsageCount(0)->setIsDefault(false);

        $address2 = $this->createBasicAddress('Unused Address 2');
        $address2->setUsageCount(0)->setIsDefault(false);

        $address3 = $this->createBasicAddress('Used Address');
        $address3->setUsageCount(5)->setIsDefault(false);

        $address4 = $this->createBasicAddress('Default Unused Address');
        $address4->setUsageCount(0)->setIsDefault(true);

        $this->addressRepository->save($address1, false);
        $this->addressRepository->save($address2, false);
        $this->addressRepository->save($address3, false);
        $this->addressRepository->save($address4, false);
        $this->entityManager->flush();

        // Since all addresses were just created (createdAt = now), they won't be considered "old"
        // Test with default parameter (365 days) - should return no results since all addresses are new
        $addresses = $this->addressRepository->findUnusedAddresses($this->customer->getId());
        $this->assertCount(0, $addresses);

        // Test with 0 days - still no results because the method looks for addresses
        // created BEFORE the cutoff date, and with 0 days the cutoff is "now"
        $addresses = $this->addressRepository->findUnusedAddresses($this->customer->getId(), 0);
        $this->assertCount(0, $addresses);

        // Test with negative days (future cutoff) - this should find unused addresses
        // since the cutoff date will be in the future
        $addresses = $this->addressRepository->findUnusedAddresses($this->customer->getId(), -1);
        $this->assertCount(2, $addresses); // address1 and address2 (unused and not default)
    }

    public function testGetAddressesForExport(): void
    {
        $address1 = $this->createBasicAddress('Z Address', 'sender');
        $address2 = $this->createBasicAddress('A Address', 'recipient');
        $address3 = $this->createBasicAddress('M Address', 'both');
        $address4 = $this->createBasicAddress('Inactive Address', 'both');
        $address4->setIsActive(false);

        $this->addressRepository->save($address1, false);
        $this->addressRepository->save($address2, false);
        $this->addressRepository->save($address3, false);
        $this->addressRepository->save($address4, false);
        $this->entityManager->flush();

        // Test without type filter
        $addresses = $this->addressRepository->getAddressesForExport($this->customer->getId());
        $this->assertCount(3, $addresses); // Only active ones

        // Should be ordered by name ASC
        $this->assertEquals('A Address', $addresses[0]->getName());
        $this->assertEquals('M Address', $addresses[1]->getName());
        $this->assertEquals('Z Address', $addresses[2]->getName());

        // Test with type filter
        $addresses = $this->addressRepository->getAddressesForExport($this->customer->getId(), 'sender');
        $this->assertCount(2, $addresses); // 'sender' and 'both'
    }

    private function createBasicAddress(string $name, string $type = 'both'): CustomerAddress
    {
        $address = new CustomerAddress();
        $address->setCustomer($this->customer)
                ->setName($name)
                ->setType($type)
                ->setContactName('Test Contact')
                ->setEmail('test@example.com')
                ->setPhone('+48123456789')
                ->setAddress('ul. Testowa 123')
                ->setPostalCode('00-001')
                ->setCity('Warsaw')
                ->setCountry('Poland');

        return $address;
    }
}