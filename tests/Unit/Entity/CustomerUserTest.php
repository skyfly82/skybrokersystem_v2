<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Customer;
use App\Entity\CustomerUser;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CustomerUser entity
 */
class CustomerUserTest extends TestCase
{
    private CustomerUser $customerUser;

    protected function setUp(): void
    {
        $this->customerUser = new CustomerUser();
    }

    public function testCustomerUserInitialization(): void
    {
        $this->assertNull($this->customerUser->getId());
        $this->assertEquals('employee', $this->customerUser->getCustomerRole());
        $this->assertEquals('active', $this->customerUser->getStatus());
        $this->assertEquals(['ROLE_CUSTOMER_USER'], $this->customerUser->getRoles());
        $this->assertInstanceOf(\DateTime::class, $this->customerUser->getCreatedAt());
        $this->assertNull($this->customerUser->getUpdatedAt());
        $this->assertNull($this->customerUser->getLastLoginAt());
        $this->assertNull($this->customerUser->getEmailVerifiedAt());
        $this->assertNull($this->customerUser->getCustomer());
    }

    public function testEmailGetterSetter(): void
    {
        $email = 'test@example.com';
        $this->customerUser->setEmail($email);

        $this->assertEquals($email, $this->customerUser->getEmail());
    }

    public function testGetUserIdentifier(): void
    {
        $email = 'user@example.com';
        $this->customerUser->setEmail($email);

        $this->assertEquals($email, $this->customerUser->getUserIdentifier());
    }

    public function testGetUserIdentifierWithNullEmail(): void
    {
        $this->assertEquals('', $this->customerUser->getUserIdentifier());
    }

    public function testRolesGetterSetter(): void
    {
        $roles = ['ROLE_CUSTOMER_ADMIN'];
        $this->customerUser->setRoles($roles);

        $roles = $this->customerUser->getRoles();
        $this->assertContains('ROLE_CUSTOMER_USER', $roles);
        $this->assertContains('ROLE_CUSTOMER_ADMIN', $roles);
        $this->assertEquals(2, count($roles));
    }

    public function testRolesAlwaysIncludeCustomerUser(): void
    {
        $this->customerUser->setRoles(['ROLE_ADMIN']);

        $roles = $this->customerUser->getRoles();
        $this->assertContains('ROLE_CUSTOMER_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
    }

    public function testPasswordGetterSetter(): void
    {
        $password = 'hashed_password_123';
        $this->customerUser->setPassword($password);

        $this->assertEquals($password, $this->customerUser->getPassword());
    }

    public function testEraseCredentials(): void
    {
        // This method should not throw any errors
        $this->customerUser->eraseCredentials();
        $this->assertTrue(true); // If we get here, the method didn't throw
    }

    public function testFirstNameGetterSetter(): void
    {
        $firstName = 'John';
        $this->customerUser->setFirstName($firstName);

        $this->assertEquals($firstName, $this->customerUser->getFirstName());
    }

    public function testLastNameGetterSetter(): void
    {
        $lastName = 'Doe';
        $this->customerUser->setLastName($lastName);

        $this->assertEquals($lastName, $this->customerUser->getLastName());
    }

    public function testGetFullName(): void
    {
        $this->customerUser
            ->setFirstName('John')
            ->setLastName('Doe');

        $this->assertEquals('John Doe', $this->customerUser->getFullName());
    }

    public function testPhoneGetterSetter(): void
    {
        $phone = '+48123456789';
        $this->customerUser->setPhone($phone);

        $this->assertEquals($phone, $this->customerUser->getPhone());
    }

    public function testCustomerRoleGetterSetter(): void
    {
        $this->assertEquals('employee', $this->customerUser->getCustomerRole());

        $this->customerUser->setCustomerRole('manager');
        $this->assertEquals('manager', $this->customerUser->getCustomerRole());

        $this->customerUser->setCustomerRole('owner');
        $this->assertEquals('owner', $this->customerUser->getCustomerRole());

        $this->customerUser->setCustomerRole('viewer');
        $this->assertEquals('viewer', $this->customerUser->getCustomerRole());
    }

    public function testStatusGetterSetter(): void
    {
        $this->assertEquals('active', $this->customerUser->getStatus());

        $this->customerUser->setStatus('inactive');
        $this->assertEquals('inactive', $this->customerUser->getStatus());

        $this->customerUser->setStatus('pending');
        $this->assertEquals('pending', $this->customerUser->getStatus());
    }

    public function testCreatedAtGetterSetter(): void
    {
        $originalCreatedAt = $this->customerUser->getCreatedAt();
        $this->assertInstanceOf(\DateTime::class, $originalCreatedAt);

        $newCreatedAt = new \DateTime('2023-01-01 10:00:00');
        $this->customerUser->setCreatedAt($newCreatedAt);

        $this->assertEquals($newCreatedAt, $this->customerUser->getCreatedAt());
    }

    public function testUpdatedAtGetterSetter(): void
    {
        $this->assertNull($this->customerUser->getUpdatedAt());

        $updatedAt = new \DateTime();
        $this->customerUser->setUpdatedAt($updatedAt);

        $this->assertEquals($updatedAt, $this->customerUser->getUpdatedAt());
    }

    public function testLastLoginAtGetterSetter(): void
    {
        $this->assertNull($this->customerUser->getLastLoginAt());

        $lastLoginAt = new \DateTime();
        $this->customerUser->setLastLoginAt($lastLoginAt);

        $this->assertEquals($lastLoginAt, $this->customerUser->getLastLoginAt());
    }

    public function testEmailVerifiedAtGetterSetter(): void
    {
        $this->assertNull($this->customerUser->getEmailVerifiedAt());

        $emailVerifiedAt = new \DateTime();
        $this->customerUser->setEmailVerifiedAt($emailVerifiedAt);

        $this->assertEquals($emailVerifiedAt, $this->customerUser->getEmailVerifiedAt());
    }

    public function testCustomerGetterSetter(): void
    {
        $customer = $this->createMock(Customer::class);
        $this->customerUser->setCustomer($customer);

        $this->assertEquals($customer, $this->customerUser->getCustomer());
    }

    public function testIsOwner(): void
    {
        $this->assertFalse($this->customerUser->isOwner());

        $this->customerUser->setCustomerRole('owner');
        $this->assertTrue($this->customerUser->isOwner());

        $this->customerUser->setCustomerRole('manager');
        $this->assertFalse($this->customerUser->isOwner());
    }

    public function testIsManager(): void
    {
        $this->assertFalse($this->customerUser->isManager());

        $this->customerUser->setCustomerRole('manager');
        $this->assertTrue($this->customerUser->isManager());

        $this->customerUser->setCustomerRole('owner');
        $this->assertTrue($this->customerUser->isManager()); // Owner is also a manager

        $this->customerUser->setCustomerRole('employee');
        $this->assertFalse($this->customerUser->isManager());

        $this->customerUser->setCustomerRole('viewer');
        $this->assertFalse($this->customerUser->isManager());
    }

    public function testCanManageUsers(): void
    {
        $this->assertFalse($this->customerUser->canManageUsers());

        $this->customerUser->setCustomerRole('manager');
        $this->assertTrue($this->customerUser->canManageUsers());

        $this->customerUser->setCustomerRole('owner');
        $this->assertTrue($this->customerUser->canManageUsers());

        $this->customerUser->setCustomerRole('employee');
        $this->assertFalse($this->customerUser->canManageUsers());
    }

    public function testIsEmailVerified(): void
    {
        $this->assertFalse($this->customerUser->isEmailVerified());

        $this->customerUser->setEmailVerifiedAt(new \DateTime());
        $this->assertTrue($this->customerUser->isEmailVerified());
    }

    public function testFluentInterface(): void
    {
        $customer = $this->createMock(Customer::class);
        $result = $this->customerUser
            ->setEmail('test@example.com')
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setPhone('+48123456789')
            ->setCustomerRole('manager')
            ->setStatus('active')
            ->setCustomer($customer);

        $this->assertSame($this->customerUser, $result);
        $this->assertEquals('test@example.com', $this->customerUser->getEmail());
        $this->assertEquals('John', $this->customerUser->getFirstName());
        $this->assertEquals('Doe', $this->customerUser->getLastName());
        $this->assertEquals('+48123456789', $this->customerUser->getPhone());
        $this->assertEquals('manager', $this->customerUser->getCustomerRole());
        $this->assertEquals('active', $this->customerUser->getStatus());
        $this->assertEquals($customer, $this->customerUser->getCustomer());
    }

    public function testSecurityInterface(): void
    {
        $this->assertInstanceOf(\Symfony\Component\Security\Core\User\UserInterface::class, $this->customerUser);
        $this->assertInstanceOf(\Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface::class, $this->customerUser);
    }
}