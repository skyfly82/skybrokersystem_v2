<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Customer;
use App\Entity\CustomerBalance;
use App\Repository\CustomerBalanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for CustomerBalanceRepository
 */
class CustomerBalanceRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private CustomerBalanceRepository $balanceRepository;
    private Customer $customer;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->balanceRepository = $this->entityManager
            ->getRepository(CustomerBalance::class);

        // Clean up database before each test
        $this->entityManager->createQuery('DELETE FROM App\Entity\CustomerBalance')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Customer')->execute();

        // Create test customer
        $this->customer = new Customer();
        $this->customer->setCompanyName('Test Company');
        $this->entityManager->persist($this->customer);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up after tests
        $this->entityManager->createQuery('DELETE FROM App\Entity\CustomerBalance')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Customer')->execute();
        $this->entityManager->close();
        $this->entityManager = null;
    }

    public function testSaveAndRemove(): void
    {
        $balance = $this->createBasicBalance();

        // Test save
        $this->balanceRepository->save($balance, true);
        $this->assertNotNull($balance->getId());

        $balanceId = $balance->getId();
        $savedBalance = $this->balanceRepository->find($balanceId);
        $this->assertNotNull($savedBalance);
        $this->assertEquals($this->customer->getId(), $savedBalance->getCustomer()->getId());

        // Test remove
        $this->balanceRepository->remove($balance, true);
        $removedBalance = $this->balanceRepository->find($balanceId);
        $this->assertNull($removedBalance);
    }

    public function testFindByCustomer(): void
    {
        $balance = $this->createBasicBalance();
        $this->balanceRepository->save($balance, true);

        $foundBalance = $this->balanceRepository->findByCustomer($this->customer);
        $this->assertNotNull($foundBalance);
        $this->assertEquals($balance->getId(), $foundBalance->getId());
    }

    public function testFindByCustomerId(): void
    {
        $balance = $this->createBasicBalance();
        $this->balanceRepository->save($balance, true);

        $foundBalance = $this->balanceRepository->findByCustomerId($this->customer->getId());
        $this->assertNotNull($foundBalance);
        $this->assertEquals($balance->getId(), $foundBalance->getId());
    }

    public function testFindCustomersNeedingAutoTopUp(): void
    {
        // Customer 1 - needs auto top-up
        $balance1 = $this->createBasicBalance();
        $balance1->setAutoTopUpEnabled(true)
                 ->setAutoTopUpTrigger('50.00')
                 ->setAutoTopUpAmount('100.00')
                 ->setCurrentBalance('30.00');

        // Customer 2 - auto top-up disabled
        $customer2 = new Customer();
        $customer2->setCompanyName('Customer 2');
        $this->entityManager->persist($customer2);

        $balance2 = new CustomerBalance();
        $balance2->setCustomer($customer2)
                 ->setAutoTopUpEnabled(false)
                 ->setCurrentBalance('30.00');

        // Customer 3 - balance above trigger
        $customer3 = new Customer();
        $customer3->setCompanyName('Customer 3');
        $this->entityManager->persist($customer3);

        $balance3 = new CustomerBalance();
        $balance3->setCustomer($customer3)
                 ->setAutoTopUpEnabled(true)
                 ->setAutoTopUpTrigger('50.00')
                 ->setAutoTopUpAmount('100.00')
                 ->setCurrentBalance('60.00');

        $this->balanceRepository->save($balance1, false);
        $this->balanceRepository->save($balance2, false);
        $this->balanceRepository->save($balance3, false);
        $this->entityManager->flush();

        $results = $this->balanceRepository->findCustomersNeedingAutoTopUp();
        $this->assertCount(1, $results);
        $this->assertEquals($balance1->getId(), $results[0]->getId());
    }

    public function testFindCustomersInCredit(): void
    {
        // Customer 1 - positive balance
        $balance1 = $this->createBasicBalance();
        $balance1->setCurrentBalance('100.00');

        // Customer 2 - in credit (most negative)
        $customer2 = new Customer();
        $customer2->setCompanyName('Customer 2');
        $this->entityManager->persist($customer2);

        $balance2 = new CustomerBalance();
        $balance2->setCustomer($customer2)->setCurrentBalance('-100.00');

        // Customer 3 - in credit (less negative)
        $customer3 = new Customer();
        $customer3->setCompanyName('Customer 3');
        $this->entityManager->persist($customer3);

        $balance3 = new CustomerBalance();
        $balance3->setCustomer($customer3)->setCurrentBalance('-50.00');

        $this->balanceRepository->save($balance1, false);
        $this->balanceRepository->save($balance2, false);
        $this->balanceRepository->save($balance3, false);
        $this->entityManager->flush();

        $results = $this->balanceRepository->findCustomersInCredit();
        $this->assertCount(2, $results);

        // Should be ordered by balance ASC (most negative first)
        $this->assertEquals($balance2->getId(), $results[0]->getId());
        $this->assertEquals($balance3->getId(), $results[1]->getId());
    }

    public function testFindCustomersOverCreditLimit(): void
    {
        // Customer 1 - within credit limit
        $balance1 = $this->createBasicBalance();
        $balance1->setCurrentBalance('-50.00')->setCreditLimit('100.00');

        // Customer 2 - over credit limit
        $customer2 = new Customer();
        $customer2->setCompanyName('Customer 2');
        $this->entityManager->persist($customer2);

        $balance2 = new CustomerBalance();
        $balance2->setCustomer($customer2)
                 ->setCurrentBalance('-150.00')
                 ->setCreditLimit('100.00');

        // Customer 3 - positive balance
        $customer3 = new Customer();
        $customer3->setCompanyName('Customer 3');
        $this->entityManager->persist($customer3);

        $balance3 = new CustomerBalance();
        $balance3->setCustomer($customer3)->setCurrentBalance('50.00');

        $this->balanceRepository->save($balance1, false);
        $this->balanceRepository->save($balance2, false);
        $this->balanceRepository->save($balance3, false);
        $this->entityManager->flush();

        $results = $this->balanceRepository->findCustomersOverCreditLimit();
        $this->assertCount(1, $results);
        $this->assertEquals($balance2->getId(), $results[0]->getId());
    }

    public function testGetBalanceStatistics(): void
    {
        // Customer 1 - positive balance
        $balance1 = $this->createBasicBalance();
        $balance1->setCurrentBalance('100.00')
                 ->setTotalSpent('500.00')
                 ->setTotalTopUps('600.00')
                 ->setAutoTopUpEnabled(true);

        // Customer 2 - zero balance
        $customer2 = new Customer();
        $customer2->setCompanyName('Customer 2');
        $this->entityManager->persist($customer2);

        $balance2 = new CustomerBalance();
        $balance2->setCustomer($customer2)
                 ->setCurrentBalance('0.00')
                 ->setTotalSpent('200.00')
                 ->setTotalTopUps('200.00')
                 ->setAutoTopUpEnabled(false);

        // Customer 3 - negative balance
        $customer3 = new Customer();
        $customer3->setCompanyName('Customer 3');
        $this->entityManager->persist($customer3);

        $balance3 = new CustomerBalance();
        $balance3->setCustomer($customer3)
                 ->setCurrentBalance('-50.00')
                 ->setTotalSpent('300.00')
                 ->setTotalTopUps('250.00')
                 ->setAutoTopUpEnabled(true);

        $this->balanceRepository->save($balance1, false);
        $this->balanceRepository->save($balance2, false);
        $this->balanceRepository->save($balance3, false);
        $this->entityManager->flush();

        $stats = $this->balanceRepository->getBalanceStatistics();

        $this->assertEquals(3, $stats['total_customers']);
        $this->assertEquals(1, $stats['customers_with_positive_balance']);
        $this->assertEquals(1, $stats['customers_in_credit']);
        $this->assertEquals(1, $stats['customers_zero_balance']);
        $this->assertEquals(50.0, $stats['total_balance']); // 100 + 0 + (-50)
        $this->assertEquals(16.666666666666668, $stats['average_balance']); // 50/3
        $this->assertEquals(1000.0, $stats['total_spent']); // 500 + 200 + 300
        $this->assertEquals(1050.0, $stats['total_topups']); // 600 + 200 + 250
        $this->assertEquals(2, $stats['auto_topup_enabled_count']);
    }

    public function testFindByBalanceRange(): void
    {
        $balance1 = $this->createBasicBalance();
        $balance1->setCurrentBalance('150.00');

        $customer2 = new Customer();
        $customer2->setCompanyName('Customer 2');
        $this->entityManager->persist($customer2);

        $balance2 = new CustomerBalance();
        $balance2->setCustomer($customer2)->setCurrentBalance('75.00');

        $customer3 = new Customer();
        $customer3->setCompanyName('Customer 3');
        $this->entityManager->persist($customer3);

        $balance3 = new CustomerBalance();
        $balance3->setCustomer($customer3)->setCurrentBalance('25.00');

        $this->balanceRepository->save($balance1, false);
        $this->balanceRepository->save($balance2, false);
        $this->balanceRepository->save($balance3, false);
        $this->entityManager->flush();

        $results = $this->balanceRepository->findByBalanceRange(50.0, 100.0);
        $this->assertCount(1, $results);
        $this->assertEquals($balance2->getId(), $results[0]->getId());
    }

    public function testFindTopSpenders(): void
    {
        $balance1 = $this->createBasicBalance();
        $balance1->setTotalSpent('500.00')
                 ->setLastTransactionAt(new \DateTimeImmutable('-5 days'));

        $customer2 = new Customer();
        $customer2->setCompanyName('Customer 2');
        $this->entityManager->persist($customer2);

        $balance2 = new CustomerBalance();
        $balance2->setCustomer($customer2)
                 ->setTotalSpent('800.00')
                 ->setLastTransactionAt(new \DateTimeImmutable('-10 days'));

        $customer3 = new Customer();
        $customer3->setCompanyName('Customer 3');
        $this->entityManager->persist($customer3);

        $balance3 = new CustomerBalance();
        $balance3->setCustomer($customer3)
                 ->setTotalSpent('300.00')
                 ->setLastTransactionAt(new \DateTimeImmutable('-60 days'));

        $this->balanceRepository->save($balance1, false);
        $this->balanceRepository->save($balance2, false);
        $this->balanceRepository->save($balance3, false);
        $this->entityManager->flush();

        // Test without date filter
        $results = $this->balanceRepository->findTopSpenders(5);
        $this->assertCount(3, $results);
        $this->assertEquals($balance2->getId(), $results[0]->getId()); // Highest spender first

        // Test with date filter (30 days)
        $results = $this->balanceRepository->findTopSpenders(5, 30);
        $this->assertCount(2, $results); // Only recent transactions
        $this->assertEquals($balance2->getId(), $results[0]->getId());
        $this->assertEquals($balance1->getId(), $results[1]->getId());
    }

    public function testFindInactiveCustomers(): void
    {
        $balance1 = $this->createBasicBalance();
        $balance1->setCurrentBalance('30.00') // Low balance
                 ->setLastTopUpAt(new \DateTimeImmutable('-100 days')); // Old top-up

        $customer2 = new Customer();
        $customer2->setCompanyName('Customer 2');
        $this->entityManager->persist($customer2);

        $balance2 = new CustomerBalance();
        $balance2->setCustomer($customer2)
                 ->setCurrentBalance('30.00') // Low balance
                 ->setLastTopUpAt(new \DateTimeImmutable('-5 days')); // Recent top-up

        $customer3 = new Customer();
        $customer3->setCompanyName('Customer 3');
        $this->entityManager->persist($customer3);

        $balance3 = new CustomerBalance();
        $balance3->setCustomer($customer3)
                 ->setCurrentBalance('100.00') // High balance
                 ->setLastTopUpAt(new \DateTimeImmutable('-100 days')); // Old top-up

        $this->balanceRepository->save($balance1, false);
        $this->balanceRepository->save($balance2, false);
        $this->balanceRepository->save($balance3, false);
        $this->entityManager->flush();

        $results = $this->balanceRepository->findInactiveCustomers(90);
        $this->assertCount(1, $results); // Only old top-up with low balance
        $this->assertEquals($balance1->getId(), $results[0]->getId());
    }

    public function testUpdateBalance(): void
    {
        $balance = $this->createBasicBalance();
        $balance->setCurrentBalance('100.00');
        $this->balanceRepository->save($balance, true);

        // Test add operation
        $result = $this->balanceRepository->updateBalance($this->customer->getId(), 50.0, 'add');
        $this->assertTrue($result);

        $this->entityManager->refresh($balance);
        $this->assertEquals('150', $balance->getCurrentBalance());

        // Test subtract operation
        $result = $this->balanceRepository->updateBalance($this->customer->getId(), 25.0, 'subtract');
        $this->assertTrue($result);

        $this->entityManager->refresh($balance);
        $this->assertEquals('125', $balance->getCurrentBalance());
    }

    public function testReserveFunds(): void
    {
        $balance = $this->createBasicBalance();
        $balance->setCurrentBalance('100.00');
        $this->balanceRepository->save($balance, true);

        // Test successful reservation
        $result = $this->balanceRepository->reserveFunds($this->customer->getId(), 30.0);
        $this->assertTrue($result);

        $this->entityManager->refresh($balance);
        $this->assertEquals('30', $balance->getReservedAmount());

        // Test insufficient funds
        $result = $this->balanceRepository->reserveFunds($this->customer->getId(), 200.0);
        $this->assertFalse($result);
    }

    public function testReleaseReservedFunds(): void
    {
        $balance = $this->createBasicBalance();
        $balance->setReservedAmount('50.00');
        $this->balanceRepository->save($balance, true);

        $result = $this->balanceRepository->releaseReservedFunds($this->customer->getId(), 30.0);
        $this->assertTrue($result);

        $this->entityManager->refresh($balance);
        $this->assertEquals('20', $balance->getReservedAmount());

        // Test releasing more than reserved (should not go below 0)
        $result = $this->balanceRepository->releaseReservedFunds($this->customer->getId(), 50.0);
        $this->assertTrue($result);

        $this->entityManager->refresh($balance);
        $this->assertEquals('0', $balance->getReservedAmount());
    }

    public function testUpdateSpendingTotal(): void
    {
        $balance = $this->createBasicBalance();
        $balance->setTotalSpent('100.00');
        $this->balanceRepository->save($balance, true);

        $result = $this->balanceRepository->updateSpendingTotal($this->customer->getId(), 50.0);
        $this->assertTrue($result);

        $this->entityManager->refresh($balance);
        $this->assertEquals('150', $balance->getTotalSpent());
        $this->assertInstanceOf(\DateTimeImmutable::class, $balance->getLastTransactionAt());
    }

    public function testUpdateTopUpTotal(): void
    {
        $balance = $this->createBasicBalance();
        $balance->setTotalTopUps('200.00');
        $this->balanceRepository->save($balance, true);

        $result = $this->balanceRepository->updateTopUpTotal($this->customer->getId(), 100.0);
        $this->assertTrue($result);

        $this->entityManager->refresh($balance);
        $this->assertEquals('300', $balance->getTotalTopUps());
        $this->assertInstanceOf(\DateTimeImmutable::class, $balance->getLastTopUpAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $balance->getLastTransactionAt());
    }

    public function testFindOrCreateForCustomer(): void
    {
        // Test create new balance
        $balance = $this->balanceRepository->findOrCreateForCustomer($this->customer);
        $this->assertNotNull($balance);
        $this->assertEquals($this->customer->getId(), $balance->getCustomer()->getId());

        $balanceId = $balance->getId();

        // Test find existing balance
        $existingBalance = $this->balanceRepository->findOrCreateForCustomer($this->customer);
        $this->assertEquals($balanceId, $existingBalance->getId());
    }

    public function testGetBalanceDistribution(): void
    {
        // Create customers with different balance ranges
        $balance1 = $this->createBasicBalance();
        $balance1->setCurrentBalance('-50.00'); // negative

        $customer2 = new Customer();
        $customer2->setCompanyName('Customer 2');
        $this->entityManager->persist($customer2);

        $balance2 = new CustomerBalance();
        $balance2->setCustomer($customer2)->setCurrentBalance('25.00'); // low

        $customer3 = new Customer();
        $customer3->setCompanyName('Customer 3');
        $this->entityManager->persist($customer3);

        $balance3 = new CustomerBalance();
        $balance3->setCustomer($customer3)->setCurrentBalance('100.00'); // medium

        $customer4 = new Customer();
        $customer4->setCompanyName('Customer 4');
        $this->entityManager->persist($customer4);

        $balance4 = new CustomerBalance();
        $balance4->setCustomer($customer4)->setCurrentBalance('300.00'); // good

        $customer5 = new Customer();
        $customer5->setCompanyName('Customer 5');
        $this->entityManager->persist($customer5);

        $balance5 = new CustomerBalance();
        $balance5->setCustomer($customer5)->setCurrentBalance('600.00'); // high

        $this->balanceRepository->save($balance1, false);
        $this->balanceRepository->save($balance2, false);
        $this->balanceRepository->save($balance3, false);
        $this->balanceRepository->save($balance4, false);
        $this->balanceRepository->save($balance5, false);
        $this->entityManager->flush();

        $distribution = $this->balanceRepository->getBalanceDistribution();

        $this->assertEquals(1, $distribution['negative_balance']); // < 0
        $this->assertEquals(1, $distribution['low_balance']); // 0-50
        $this->assertEquals(1, $distribution['medium_balance']); // 50-200
        $this->assertEquals(1, $distribution['good_balance']); // 200-500
        $this->assertEquals(1, $distribution['high_balance']); // >= 500
    }

    private function createBasicBalance(): CustomerBalance
    {
        $balance = new CustomerBalance();
        $balance->setCustomer($this->customer);

        return $balance;
    }
}