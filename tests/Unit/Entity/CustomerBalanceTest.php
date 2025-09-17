<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Customer;
use App\Entity\CustomerBalance;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CustomerBalance entity
 */
class CustomerBalanceTest extends TestCase
{
    private CustomerBalance $customerBalance;
    private Customer $customer;

    protected function setUp(): void
    {
        $this->customerBalance = new CustomerBalance();
        $this->customer = $this->createMock(Customer::class);
    }

    public function testCustomerBalanceInitialization(): void
    {
        $this->assertNull($this->customerBalance->getId());
        $this->assertEquals('0.00', $this->customerBalance->getCurrentBalance());
        $this->assertEquals('0.00', $this->customerBalance->getCreditLimit());
        $this->assertEquals('0.00', $this->customerBalance->getAvailableCredit());
        $this->assertEquals('0.00', $this->customerBalance->getReservedAmount());
        $this->assertEquals('0.00', $this->customerBalance->getTotalSpent());
        $this->assertEquals('0.00', $this->customerBalance->getTotalTopUps());
        $this->assertEquals('PLN', $this->customerBalance->getCurrency());
        $this->assertFalse($this->customerBalance->isAutoTopUpEnabled());
        $this->assertNull($this->customerBalance->getAutoTopUpTrigger());
        $this->assertNull($this->customerBalance->getAutoTopUpAmount());
        $this->assertNull($this->customerBalance->getAutoTopUpMethod());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->customerBalance->getCreatedAt());
        $this->assertNull($this->customerBalance->getUpdatedAt());
        $this->assertNull($this->customerBalance->getLastTopUpAt());
        $this->assertNull($this->customerBalance->getLastTransactionAt());
    }

    public function testCustomerGetterSetter(): void
    {
        $this->customerBalance->setCustomer($this->customer);
        $this->assertEquals($this->customer, $this->customerBalance->getCustomer());
    }

    public function testCurrentBalanceGetterSetter(): void
    {
        $balance = '100.50';
        $this->customerBalance->setCurrentBalance($balance);

        $this->assertEquals($balance, $this->customerBalance->getCurrentBalance());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->customerBalance->getUpdatedAt());
    }

    public function testCreditLimitGetterSetter(): void
    {
        $creditLimit = '500.00';
        $this->customerBalance->setCreditLimit($creditLimit);

        $this->assertEquals($creditLimit, $this->customerBalance->getCreditLimit());
        $this->assertEquals($creditLimit, $this->customerBalance->getAvailableCredit()); // Should update available credit
    }

    public function testReservedAmountGetterSetter(): void
    {
        $reserved = '25.75';
        $this->customerBalance->setReservedAmount($reserved);

        $this->assertEquals($reserved, $this->customerBalance->getReservedAmount());
    }

    public function testTotalSpentGetterSetter(): void
    {
        $spent = '1250.00';
        $this->customerBalance->setTotalSpent($spent);

        $this->assertEquals($spent, $this->customerBalance->getTotalSpent());
    }

    public function testTotalTopUpsGetterSetter(): void
    {
        $topUps = '800.00';
        $this->customerBalance->setTotalTopUps($topUps);

        $this->assertEquals($topUps, $this->customerBalance->getTotalTopUps());
    }

    public function testCurrencyGetterSetter(): void
    {
        $this->assertEquals('PLN', $this->customerBalance->getCurrency());

        $this->customerBalance->setCurrency('EUR');
        $this->assertEquals('EUR', $this->customerBalance->getCurrency());
    }

    public function testAutoTopUpEnabledGetterSetter(): void
    {
        $this->assertFalse($this->customerBalance->isAutoTopUpEnabled());

        $this->customerBalance->setAutoTopUpEnabled(true);
        $this->assertTrue($this->customerBalance->isAutoTopUpEnabled());
    }

    public function testAutoTopUpTriggerGetterSetter(): void
    {
        $trigger = '50.00';
        $this->customerBalance->setAutoTopUpTrigger($trigger);

        $this->assertEquals($trigger, $this->customerBalance->getAutoTopUpTrigger());
    }

    public function testAutoTopUpAmountGetterSetter(): void
    {
        $amount = '100.00';
        $this->customerBalance->setAutoTopUpAmount($amount);

        $this->assertEquals($amount, $this->customerBalance->getAutoTopUpAmount());
    }

    public function testAutoTopUpMethodGetterSetter(): void
    {
        $method = 'stripe';
        $this->customerBalance->setAutoTopUpMethod($method);

        $this->assertEquals($method, $this->customerBalance->getAutoTopUpMethod());
    }

    public function testLastTopUpAtGetterSetter(): void
    {
        $date = new \DateTimeImmutable();
        $this->customerBalance->setLastTopUpAt($date);

        $this->assertEquals($date, $this->customerBalance->getLastTopUpAt());
    }

    public function testLastTransactionAtGetterSetter(): void
    {
        $date = new \DateTimeImmutable();
        $this->customerBalance->setLastTransactionAt($date);

        $this->assertEquals($date, $this->customerBalance->getLastTransactionAt());
    }

    public function testAddFunds(): void
    {
        $amount = 100.0;
        $this->customerBalance->addFunds($amount);

        $this->assertEquals('100.00', $this->customerBalance->getCurrentBalance());
        $this->assertEquals('100.00', $this->customerBalance->getTotalTopUps());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->customerBalance->getLastTopUpAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->customerBalance->getLastTransactionAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->customerBalance->getUpdatedAt());
    }

    public function testDeductFunds(): void
    {
        $this->customerBalance->setCurrentBalance('100.00');
        $amount = 30.0;

        $this->customerBalance->deductFunds($amount);

        $this->assertEquals('70.00', $this->customerBalance->getCurrentBalance());
        $this->assertEquals('30.00', $this->customerBalance->getTotalSpent());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->customerBalance->getLastTransactionAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->customerBalance->getUpdatedAt());
    }

    public function testDeductFundsThrowsExceptionOnInsufficientFunds(): void
    {
        $this->customerBalance->setCurrentBalance('50.00');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient funds');

        $this->customerBalance->deductFunds(100.0);
    }

    public function testReserveFunds(): void
    {
        $this->customerBalance->setCurrentBalance('100.00');
        $amount = 25.0;

        $this->customerBalance->reserveFunds($amount);

        $this->assertEquals('25.00', $this->customerBalance->getReservedAmount());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->customerBalance->getUpdatedAt());
    }

    public function testReserveFundsThrowsExceptionOnInsufficientFunds(): void
    {
        $this->customerBalance->setCurrentBalance('30.00');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient funds to reserve');

        $this->customerBalance->reserveFunds(50.0);
    }

    public function testReleaseReservedFunds(): void
    {
        $this->customerBalance->setReservedAmount('50.00');

        $this->customerBalance->releaseReservedFunds(30.0);

        $this->assertEquals('20.00', $this->customerBalance->getReservedAmount());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->customerBalance->getUpdatedAt());
    }

    public function testReleaseReservedFundsLimitedByReservedAmount(): void
    {
        $this->customerBalance->setReservedAmount('20.00');

        $this->customerBalance->releaseReservedFunds(50.0); // Try to release more than reserved

        $this->assertEquals('0.00', $this->customerBalance->getReservedAmount()); // Should only release what's reserved
    }

    public function testFinalizeReservedTransaction(): void
    {
        $this->customerBalance
            ->setCurrentBalance('100.00')
            ->setReservedAmount('30.00');

        $reservedAmount = 30.0;
        $actualAmount = 25.0;

        $this->customerBalance->finalizeReservedTransaction($reservedAmount, $actualAmount);

        $this->assertEquals('75.00', $this->customerBalance->getCurrentBalance()); // 100 - 25
        $this->assertEquals('0.00', $this->customerBalance->getReservedAmount()); // Reserved amount released
        $this->assertEquals('25.00', $this->customerBalance->getTotalSpent());
    }

    public function testHasSufficientFunds(): void
    {
        $this->customerBalance->setCurrentBalance('100.00');

        $this->assertTrue($this->customerBalance->hasSufficientFunds(50.0));
        $this->assertTrue($this->customerBalance->hasSufficientFunds(100.0));
        $this->assertFalse($this->customerBalance->hasSufficientFunds(150.0));
    }

    public function testHasSufficientFundsWithCredit(): void
    {
        $this->customerBalance
            ->setCurrentBalance('50.00')
            ->setCreditLimit('100.00');

        $this->assertTrue($this->customerBalance->hasSufficientFunds(120.0)); // 50 balance + 100 credit
        $this->assertFalse($this->customerBalance->hasSufficientFunds(200.0)); // Exceeds total available
    }

    public function testGetAvailableFunds(): void
    {
        $this->customerBalance
            ->setCurrentBalance('100.00')
            ->setCreditLimit('50.00')
            ->setReservedAmount('20.00');

        // Available funds = (balance - reserved) + available credit
        // = (100 - 20) + 50 = 130
        $this->assertEquals('130.00', $this->customerBalance->getAvailableFunds());
    }

    public function testGetAvailableFundsWithNegativeBalance(): void
    {
        $this->customerBalance
            ->setCurrentBalance('-20.00')
            ->setCreditLimit('100.00')
            ->setReservedAmount('10.00');

        // Available funds with negative balance should account for credit usage
        $availableFunds = $this->customerBalance->getAvailableFunds();
        $this->assertGreaterThanOrEqual('0.00', $availableFunds);
    }

    public function testNeedsAutoTopUp(): void
    {
        $this->assertFalse($this->customerBalance->needsAutoTopUp()); // Disabled by default

        $this->customerBalance
            ->setAutoTopUpEnabled(true)
            ->setAutoTopUpTrigger('50.00')
            ->setCurrentBalance('30.00');

        $this->assertTrue($this->customerBalance->needsAutoTopUp());

        $this->customerBalance->setCurrentBalance('60.00');
        $this->assertFalse($this->customerBalance->needsAutoTopUp());
    }

    public function testUpdateAvailableCredit(): void
    {
        $this->customerBalance
            ->setCreditLimit('100.00')
            ->setCurrentBalance('50.00');

        $this->customerBalance->updateAvailableCredit();
        $this->assertEquals('100.00', $this->customerBalance->getAvailableCredit()); // Full credit available

        $this->customerBalance->setCurrentBalance('-20.00');
        $this->customerBalance->updateAvailableCredit();
        $this->assertEquals('80.00', $this->customerBalance->getAvailableCredit()); // Credit reduced by negative balance
    }

    public function testIsInCredit(): void
    {
        $this->customerBalance->setCurrentBalance('100.00');
        $this->assertFalse($this->customerBalance->isInCredit());

        $this->customerBalance->setCurrentBalance('-50.00');
        $this->assertTrue($this->customerBalance->isInCredit());
    }

    public function testGetCreditUsed(): void
    {
        $this->customerBalance->setCurrentBalance('100.00');
        $this->assertEquals('0.00', $this->customerBalance->getCreditUsed());

        $this->customerBalance->setCurrentBalance('-75.50');
        $this->assertEquals(75.5, $this->customerBalance->getCreditUsed());
    }

    public function testGetBalanceStatus(): void
    {
        $this->customerBalance->setCurrentBalance('150.00');
        $this->assertEquals('healthy', $this->customerBalance->getBalanceStatus());

        $this->customerBalance->setCurrentBalance('50.00');
        $this->assertEquals('low', $this->customerBalance->getBalanceStatus());

        $this->customerBalance
            ->setCurrentBalance('-30.00')
            ->setCreditLimit('100.00');
        $this->assertEquals('credit', $this->customerBalance->getBalanceStatus());

        $this->customerBalance->setCurrentBalance('-150.00'); // Exceeds credit limit
        $this->assertEquals('overlimit', $this->customerBalance->getBalanceStatus());
    }

    public function testToArray(): void
    {
        $this->customer->method('getId')->willReturn(1);

        $this->customerBalance
            ->setCustomer($this->customer)
            ->setCurrentBalance('100.00')
            ->setCreditLimit('200.00')
            ->setReservedAmount('25.00')
            ->setTotalSpent('500.00')
            ->setTotalTopUps('600.00')
            ->setCurrency('EUR')
            ->setAutoTopUpEnabled(true)
            ->setAutoTopUpTrigger('50.00')
            ->setAutoTopUpAmount('100.00')
            ->setAutoTopUpMethod('stripe');

        $result = $this->customerBalance->toArray();

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['customer_id']);
        $this->assertEquals(100.00, $result['current_balance']);
        $this->assertEquals(200.00, $result['credit_limit']);
        $this->assertEquals(200.00, $result['available_credit']);
        $this->assertEquals(25.00, $result['reserved_amount']);
        $this->assertEquals(275.00, $result['available_funds']); // (100-25) + 200
        $this->assertEquals(500.00, $result['total_spent']);
        $this->assertEquals(600.00, $result['total_topups']);
        $this->assertEquals('EUR', $result['currency']);
        $this->assertEquals('low', $result['balance_status']); // 100.00 is not > 100, so it's 'low'
        $this->assertFalse($result['is_in_credit']);
        $this->assertEquals(0.00, $result['credit_used']);
        $this->assertTrue($result['auto_topup_enabled']);
        $this->assertEquals(50.00, $result['auto_topup_trigger']);
        $this->assertEquals(100.00, $result['auto_topup_amount']);
        $this->assertEquals('stripe', $result['auto_topup_method']);
        $this->assertFalse($result['needs_auto_topup']); // Balance > trigger
        $this->assertArrayHasKey('created_at', $result);
    }

    public function testFluentInterface(): void
    {
        $result = $this->customerBalance
            ->setCustomer($this->customer)
            ->setCurrentBalance('100.00')
            ->setCreditLimit('200.00')
            ->setAutoTopUpEnabled(true);

        $this->assertSame($this->customerBalance, $result);
    }
}