<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Customer;
use App\Entity\CustomerAddress;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CustomerAddress entity
 */
class CustomerAddressTest extends TestCase
{
    private CustomerAddress $customerAddress;
    private Customer $customer;

    protected function setUp(): void
    {
        $this->customerAddress = new CustomerAddress();
        $this->customer = $this->createMock(Customer::class);
    }

    public function testCustomerAddressInitialization(): void
    {
        $this->assertNull($this->customerAddress->getId());
        $this->assertEquals('both', $this->customerAddress->getType());
        $this->assertEquals('Poland', $this->customerAddress->getCountry());
        $this->assertFalse($this->customerAddress->isDefault());
        $this->assertTrue($this->customerAddress->isActive());
        $this->assertFalse($this->customerAddress->isValidated());
        $this->assertNull($this->customerAddress->getValidationData());
        $this->assertEquals(0, $this->customerAddress->getUsageCount());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->customerAddress->getCreatedAt());
        $this->assertNull($this->customerAddress->getUpdatedAt());
        $this->assertNull($this->customerAddress->getLastUsedAt());
    }

    public function testCustomerGetterSetter(): void
    {
        $this->customerAddress->setCustomer($this->customer);
        $this->assertEquals($this->customer, $this->customerAddress->getCustomer());
    }

    public function testNameGetterSetter(): void
    {
        $name = 'Home Address';
        $this->customerAddress->setName($name);
        $this->assertEquals($name, $this->customerAddress->getName());
    }

    public function testTypeGetterSetter(): void
    {
        $this->assertEquals('both', $this->customerAddress->getType());

        $this->customerAddress->setType('sender');
        $this->assertEquals('sender', $this->customerAddress->getType());

        $this->customerAddress->setType('recipient');
        $this->assertEquals('recipient', $this->customerAddress->getType());
    }

    public function testContactInfoGetterSetter(): void
    {
        $contactName = 'John Doe';
        $companyName = 'Test Company';
        $email = 'test@example.com';
        $phone = '+48123456789';

        $this->customerAddress
            ->setContactName($contactName)
            ->setCompanyName($companyName)
            ->setEmail($email)
            ->setPhone($phone);

        $this->assertEquals($contactName, $this->customerAddress->getContactName());
        $this->assertEquals($companyName, $this->customerAddress->getCompanyName());
        $this->assertEquals($email, $this->customerAddress->getEmail());
        $this->assertEquals($phone, $this->customerAddress->getPhone());
    }

    public function testAddressFieldsGetterSetter(): void
    {
        $address = 'ul. Testowa 123';
        $postalCode = '00-001';
        $city = 'Warszawa';
        $country = 'Poland';
        $additionalInfo = 'Apartment 4B';

        $this->customerAddress
            ->setAddress($address)
            ->setPostalCode($postalCode)
            ->setCity($city)
            ->setCountry($country)
            ->setAdditionalInfo($additionalInfo);

        $this->assertEquals($address, $this->customerAddress->getAddress());
        $this->assertEquals($postalCode, $this->customerAddress->getPostalCode());
        $this->assertEquals($city, $this->customerAddress->getCity());
        $this->assertEquals($country, $this->customerAddress->getCountry());
        $this->assertEquals($additionalInfo, $this->customerAddress->getAdditionalInfo());
    }

    public function testIsDefaultGetterSetter(): void
    {
        $this->assertFalse($this->customerAddress->isDefault());

        $this->customerAddress->setIsDefault(true);
        $this->assertTrue($this->customerAddress->isDefault());
    }

    public function testIsActiveGetterSetter(): void
    {
        $this->assertTrue($this->customerAddress->isActive());

        $this->customerAddress->setIsActive(false);
        $this->assertFalse($this->customerAddress->isActive());
    }

    public function testIsValidatedGetterSetter(): void
    {
        $this->assertFalse($this->customerAddress->isValidated());

        $this->customerAddress->setIsValidated(true);
        $this->assertTrue($this->customerAddress->isValidated());
    }

    public function testValidationDataGetterSetter(): void
    {
        $this->assertNull($this->customerAddress->getValidationData());

        $validationData = ['status' => 'valid', 'message' => 'Address verified'];
        $this->customerAddress->setValidationData($validationData);

        $this->assertEquals($validationData, $this->customerAddress->getValidationData());
    }

    public function testUsageCountGetterSetter(): void
    {
        $this->assertEquals(0, $this->customerAddress->getUsageCount());

        $this->customerAddress->setUsageCount(5);
        $this->assertEquals(5, $this->customerAddress->getUsageCount());
    }

    public function testIncrementUsageCount(): void
    {
        $originalCount = $this->customerAddress->getUsageCount();
        $this->customerAddress->incrementUsageCount();

        $this->assertEquals($originalCount + 1, $this->customerAddress->getUsageCount());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->customerAddress->getLastUsedAt());
    }

    public function testUpdatedAtGetterSetter(): void
    {
        $this->assertNull($this->customerAddress->getUpdatedAt());

        $updatedAt = new \DateTimeImmutable();
        $this->customerAddress->setUpdatedAt($updatedAt);

        $this->assertEquals($updatedAt, $this->customerAddress->getUpdatedAt());
    }

    public function testCanBeUsedAsSender(): void
    {
        $this->customerAddress->setType('both');
        $this->assertTrue($this->customerAddress->canBeUsedAsSender());

        $this->customerAddress->setType('sender');
        $this->assertTrue($this->customerAddress->canBeUsedAsSender());

        $this->customerAddress->setType('recipient');
        $this->assertFalse($this->customerAddress->canBeUsedAsSender());
    }

    public function testCanBeUsedAsRecipient(): void
    {
        $this->customerAddress->setType('both');
        $this->assertTrue($this->customerAddress->canBeUsedAsRecipient());

        $this->customerAddress->setType('recipient');
        $this->assertTrue($this->customerAddress->canBeUsedAsRecipient());

        $this->customerAddress->setType('sender');
        $this->assertFalse($this->customerAddress->canBeUsedAsRecipient());
    }

    public function testGetFullAddress(): void
    {
        $this->customerAddress
            ->setAddress('ul. Testowa 123')
            ->setPostalCode('00-001')
            ->setCity('Warszawa')
            ->setCountry('Poland');

        $expected = 'ul. Testowa 123, 00-001, Warszawa, Poland';
        $this->assertEquals($expected, $this->customerAddress->getFullAddress());
    }

    public function testGetFullAddressFiltersEmptyValues(): void
    {
        $this->customerAddress
            ->setAddress('ul. Testowa 123')
            ->setPostalCode('00-001')
            ->setCity('Warszawa')
            ->setCountry('');

        $expected = 'ul. Testowa 123, 00-001, Warszawa';
        $this->assertEquals($expected, $this->customerAddress->getFullAddress());
    }

    public function testGetFormattedContactInfo(): void
    {
        $this->customerAddress->setContactName('John Doe');

        $this->assertEquals('John Doe', $this->customerAddress->getFormattedContactInfo());

        $this->customerAddress->setCompanyName('Test Company');
        $this->assertEquals('Test Company (John Doe)', $this->customerAddress->getFormattedContactInfo());
    }

    public function testToShipmentArrayAsSender(): void
    {
        $this->customerAddress
            ->setContactName('John Doe')
            ->setEmail('john@example.com')
            ->setPhone('+48123456789')
            ->setAddress('ul. Testowa 123')
            ->setPostalCode('00-001')
            ->setCity('Warszawa')
            ->setCountry('Poland')
            ->setCompanyName('Test Company');

        $expected = [
            'sender_name' => 'John Doe',
            'sender_email' => 'john@example.com',
            'sender_phone' => '+48123456789',
            'sender_address' => 'ul. Testowa 123',
            'sender_postal_code' => '00-001',
            'sender_city' => 'Warszawa',
            'sender_country' => 'Poland',
            'sender_company' => 'Test Company',
        ];

        $this->assertEquals($expected, $this->customerAddress->toShipmentArray('sender'));
    }

    public function testToShipmentArrayAsRecipient(): void
    {
        $this->customerAddress
            ->setContactName('Jane Smith')
            ->setEmail('jane@example.com')
            ->setPhone('+48987654321')
            ->setAddress('ul. Odbiorcza 456')
            ->setPostalCode('01-234')
            ->setCity('Kraków')
            ->setCountry('Poland')
            ->setCompanyName('Recipient Corp');

        $expected = [
            'recipient_name' => 'Jane Smith',
            'recipient_email' => 'jane@example.com',
            'recipient_phone' => '+48987654321',
            'recipient_address' => 'ul. Odbiorcza 456',
            'recipient_postal_code' => '01-234',
            'recipient_city' => 'Kraków',
            'recipient_country' => 'Poland',
            'recipient_company' => 'Recipient Corp',
        ];

        $this->assertEquals($expected, $this->customerAddress->toShipmentArray('recipient'));
    }

    public function testUpdateFromArray(): void
    {
        $data = [
            'name' => 'Updated Address',
            'type' => 'sender',
            'contact_name' => 'Updated Contact',
            'company_name' => 'Updated Company',
            'email' => 'updated@example.com',
            'phone' => '+48111222333',
            'address' => 'ul. Updated 789',
            'postal_code' => '12-345',
            'city' => 'Gdańsk',
            'country' => 'Poland',
            'additional_info' => 'Floor 2',
            'is_active' => false,
        ];

        $this->customerAddress->updateFromArray($data);

        $this->assertEquals('Updated Address', $this->customerAddress->getName());
        $this->assertEquals('sender', $this->customerAddress->getType());
        $this->assertEquals('Updated Contact', $this->customerAddress->getContactName());
        $this->assertEquals('Updated Company', $this->customerAddress->getCompanyName());
        $this->assertEquals('updated@example.com', $this->customerAddress->getEmail());
        $this->assertEquals('+48111222333', $this->customerAddress->getPhone());
        $this->assertEquals('ul. Updated 789', $this->customerAddress->getAddress());
        $this->assertEquals('12-345', $this->customerAddress->getPostalCode());
        $this->assertEquals('Gdańsk', $this->customerAddress->getCity());
        $this->assertEquals('Poland', $this->customerAddress->getCountry());
        $this->assertEquals('Floor 2', $this->customerAddress->getAdditionalInfo());
        $this->assertFalse($this->customerAddress->isActive());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->customerAddress->getUpdatedAt());
    }

    public function testToArray(): void
    {
        $this->customerAddress
            ->setCustomer($this->customer)
            ->setName('Test Address')
            ->setType('both')
            ->setContactName('John Doe')
            ->setCompanyName('Test Co')
            ->setEmail('test@example.com')
            ->setPhone('+48123456789')
            ->setAddress('ul. Testowa 123')
            ->setPostalCode('00-001')
            ->setCity('Warszawa')
            ->setCountry('Poland')
            ->setAdditionalInfo('Apt 4B')
            ->setIsDefault(true)
            ->setIsActive(true)
            ->setIsValidated(false)
            ->setUsageCount(5);

        $this->customer->method('getId')->willReturn(1);

        $result = $this->customerAddress->toArray();

        $this->assertIsArray($result);
        $this->assertEquals('Test Address', $result['name']);
        $this->assertEquals('both', $result['type']);
        $this->assertEquals('John Doe', $result['contact_name']);
        $this->assertEquals('Test Co', $result['company_name']);
        $this->assertEquals('test@example.com', $result['email']);
        $this->assertEquals('+48123456789', $result['phone']);
        $this->assertEquals('ul. Testowa 123', $result['address']);
        $this->assertEquals('00-001', $result['postal_code']);
        $this->assertEquals('Warszawa', $result['city']);
        $this->assertEquals('Poland', $result['country']);
        $this->assertEquals('Apt 4B', $result['additional_info']);
        $this->assertTrue($result['is_default']);
        $this->assertTrue($result['is_active']);
        $this->assertFalse($result['is_validated']);
        $this->assertEquals(5, $result['usage_count']);
        $this->assertArrayHasKey('full_address', $result);
        $this->assertArrayHasKey('formatted_contact', $result);
        $this->assertArrayHasKey('created_at', $result);
    }

    public function testFluentInterface(): void
    {
        $result = $this->customerAddress
            ->setCustomer($this->customer)
            ->setName('Test')
            ->setType('sender')
            ->setContactName('John')
            ->setEmail('test@example.com');

        $this->assertSame($this->customerAddress, $result);
    }
}