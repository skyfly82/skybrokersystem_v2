<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Customer;
use App\Entity\CustomerUser;
use App\Entity\Invitation;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Customer entity
 */
class CustomerTest extends TestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        $this->customer = new Customer();
    }

    public function testCustomerInitialization(): void
    {
        $this->assertNull($this->customer->getId());
        $this->assertEquals('business', $this->customer->getType());
        $this->assertEquals('active', $this->customer->getStatus());
        $this->assertInstanceOf(\DateTime::class, $this->customer->getCreatedAt());
        $this->assertNull($this->customer->getUpdatedAt());
        $this->assertCount(0, $this->customer->getCustomerUsers());
        $this->assertCount(0, $this->customer->getInvitations());
    }

    public function testCompanyNameGetterSetter(): void
    {
        $companyName = 'Test Company Ltd.';
        $this->customer->setCompanyName($companyName);

        $this->assertEquals($companyName, $this->customer->getCompanyName());
    }

    public function testVatNumberGetterSetter(): void
    {
        $vatNumber = 'PL1234567890';
        $this->customer->setVatNumber($vatNumber);

        $this->assertEquals($vatNumber, $this->customer->getVatNumber());
    }

    public function testVatNumberCanBeNull(): void
    {
        $this->assertNull($this->customer->getVatNumber());

        $this->customer->setVatNumber(null);
        $this->assertNull($this->customer->getVatNumber());
    }

    public function testRegonGetterSetter(): void
    {
        $regon = '123456789';
        $this->customer->setRegon($regon);

        $this->assertEquals($regon, $this->customer->getRegon());
    }

    public function testAddressFields(): void
    {
        $address = 'ul. Testowa 123';
        $postalCode = '00-001';
        $city = 'Warszawa';
        $country = 'Poland';

        $this->customer
            ->setAddress($address)
            ->setPostalCode($postalCode)
            ->setCity($city)
            ->setCountry($country);

        $this->assertEquals($address, $this->customer->getAddress());
        $this->assertEquals($postalCode, $this->customer->getPostalCode());
        $this->assertEquals($city, $this->customer->getCity());
        $this->assertEquals($country, $this->customer->getCountry());
    }

    public function testContactFields(): void
    {
        $phone = '+48123456789';
        $email = 'test@example.com';

        $this->customer
            ->setPhone($phone)
            ->setEmail($email);

        $this->assertEquals($phone, $this->customer->getPhone());
        $this->assertEquals($email, $this->customer->getEmail());
    }

    public function testTypeGetterSetter(): void
    {
        $this->assertEquals('business', $this->customer->getType());

        $this->customer->setType('individual');
        $this->assertEquals('individual', $this->customer->getType());
    }

    public function testStatusGetterSetter(): void
    {
        $this->assertEquals('active', $this->customer->getStatus());

        $this->customer->setStatus('inactive');
        $this->assertEquals('inactive', $this->customer->getStatus());

        $this->customer->setStatus('suspended');
        $this->assertEquals('suspended', $this->customer->getStatus());
    }

    public function testCreatedAtGetterSetter(): void
    {
        $originalCreatedAt = $this->customer->getCreatedAt();
        $this->assertInstanceOf(\DateTime::class, $originalCreatedAt);

        $newCreatedAt = new \DateTime('2023-01-01 10:00:00');
        $this->customer->setCreatedAt($newCreatedAt);

        $this->assertEquals($newCreatedAt, $this->customer->getCreatedAt());
    }

    public function testUpdatedAtGetterSetter(): void
    {
        $this->assertNull($this->customer->getUpdatedAt());

        $updatedAt = new \DateTime();
        $this->customer->setUpdatedAt($updatedAt);

        $this->assertEquals($updatedAt, $this->customer->getUpdatedAt());
    }

    public function testIsIndividual(): void
    {
        $this->assertFalse($this->customer->isIndividual());

        $this->customer->setType('individual');
        $this->assertTrue($this->customer->isIndividual());
    }

    public function testIsBusiness(): void
    {
        $this->assertTrue($this->customer->isBusiness());

        $this->customer->setType('individual');
        $this->assertFalse($this->customer->isBusiness());
    }

    public function testAddCustomerUser(): void
    {
        $customerUser = $this->createMock(CustomerUser::class);
        $customerUser->expects($this->once())
            ->method('setCustomer')
            ->with($this->customer);

        $this->customer->addCustomerUser($customerUser);

        $this->assertCount(1, $this->customer->getCustomerUsers());
        $this->assertTrue($this->customer->getCustomerUsers()->contains($customerUser));
    }

    public function testAddCustomerUserDoesNotAddDuplicates(): void
    {
        $customerUser = $this->createMock(CustomerUser::class);
        $customerUser->expects($this->once())
            ->method('setCustomer')
            ->with($this->customer);

        $this->customer->addCustomerUser($customerUser);
        $this->customer->addCustomerUser($customerUser); // Add same user again

        $this->assertCount(1, $this->customer->getCustomerUsers());
    }

    public function testRemoveCustomerUser(): void
    {
        $customerUser = $this->createMock(CustomerUser::class);
        $customerUser->expects($this->exactly(2))
            ->method('setCustomer')
            ->with($this->callback(function ($arg) {
                static $callCount = 0;
                $callCount++;

                if ($callCount === 1) {
                    return $arg === $this->customer;
                }
                return $arg === null;
            }));
        $customerUser->expects($this->once())
            ->method('getCustomer')
            ->willReturn($this->customer);

        $this->customer->addCustomerUser($customerUser);
        $this->assertCount(1, $this->customer->getCustomerUsers());

        $this->customer->removeCustomerUser($customerUser);
        $this->assertCount(0, $this->customer->getCustomerUsers());
    }

    public function testRemoveCustomerUserOnlyRemovesIfBelongsToCustomer(): void
    {
        $customerUser = $this->createMock(CustomerUser::class);
        $otherCustomer = $this->createMock(Customer::class);

        $customerUser->expects($this->once())
            ->method('getCustomer')
            ->willReturn($otherCustomer);
        $customerUser->expects($this->never())
            ->method('setCustomer')
            ->with(null);

        $this->customer->getCustomerUsers()->add($customerUser);
        $this->customer->removeCustomerUser($customerUser);
    }

    public function testAddInvitation(): void
    {
        $invitation = $this->createMock(Invitation::class);
        $invitation->expects($this->once())
            ->method('setCustomer')
            ->with($this->customer);

        $this->customer->addInvitation($invitation);

        $this->assertCount(1, $this->customer->getInvitations());
        $this->assertTrue($this->customer->getInvitations()->contains($invitation));
    }

    public function testRemoveInvitation(): void
    {
        $invitation = $this->createMock(Invitation::class);
        $invitation->expects($this->exactly(2))
            ->method('setCustomer')
            ->with($this->callback(function ($arg) {
                static $callCount = 0;
                $callCount++;

                if ($callCount === 1) {
                    return $arg === $this->customer;
                }
                return $arg === null;
            }));
        $invitation->expects($this->once())
            ->method('getCustomer')
            ->willReturn($this->customer);

        $this->customer->addInvitation($invitation);
        $this->assertCount(1, $this->customer->getInvitations());

        $this->customer->removeInvitation($invitation);
        $this->assertCount(0, $this->customer->getInvitations());
    }

    public function testFluentInterface(): void
    {
        $result = $this->customer
            ->setCompanyName('Test Company')
            ->setVatNumber('123456789')
            ->setType('individual')
            ->setStatus('inactive');

        $this->assertSame($this->customer, $result);
        $this->assertEquals('Test Company', $this->customer->getCompanyName());
        $this->assertEquals('123456789', $this->customer->getVatNumber());
        $this->assertEquals('individual', $this->customer->getType());
        $this->assertEquals('inactive', $this->customer->getStatus());
    }
}