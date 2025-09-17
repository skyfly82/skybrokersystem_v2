<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Customer;

use App\Entity\Customer;
use App\Entity\CustomerAddress;
use App\Entity\CustomerUser;
use App\Repository\CustomerAddressRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for Customer Address Book API Controller
 */
class AddressBookControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private Customer $customer;
    private CustomerUser $customerUser;
    private CustomerAddressRepository $addressRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->addressRepository = $this->entityManager->getRepository(CustomerAddress::class);

        // Clean up database
        $this->entityManager->createQuery('DELETE FROM App\Entity\CustomerAddress')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\CustomerUser')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Customer')->execute();

        // Create test customer and user
        $this->createTestCustomerAndUser();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up after tests
        $this->entityManager->createQuery('DELETE FROM App\Entity\CustomerAddress')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\CustomerUser')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Customer')->execute();
        $this->entityManager->close();
        $this->entityManager = null;
    }

    public function testListAddressesRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/v1/customer/addresses');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testListAddressesWithAuthenticatedUser(): void
    {
        // Create test addresses
        $address1 = $this->createTestAddress('Home Address', 'both');
        $address2 = $this->createTestAddress('Office Address', 'sender');

        $this->loginAsCustomerUser();

        $this->client->request('GET', '/api/v1/customer/addresses');

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertCount(2, $response['data']);
        $this->assertArrayHasKey('pagination', $response);
    }

    public function testListAddressesWithTypeFilter(): void
    {
        $address1 = $this->createTestAddress('Home Address', 'both');
        $address2 = $this->createTestAddress('Office Address', 'sender');
        $address3 = $this->createTestAddress('Warehouse Address', 'recipient');

        $this->loginAsCustomerUser();

        // Test sender filter
        $this->client->request('GET', '/api/v1/customer/addresses?type=sender');
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $response['data']); // 'both' and 'sender'
    }

    public function testListAddressesWithTypeFilterRecipient(): void
    {
        $address1 = $this->createTestAddress('Home Address', 'both');
        $address2 = $this->createTestAddress('Office Address', 'sender');
        $address3 = $this->createTestAddress('Warehouse Address', 'recipient');

        $this->loginAsCustomerUser();

        // Test recipient filter
        $this->client->request('GET', '/api/v1/customer/addresses?type=recipient');
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $response['data']); // 'both' and 'recipient'
    }

    public function testListAddressesWithSearchFilter(): void
    {
        $address1 = $this->createTestAddress('Home Sweet Home', 'both');
        $address1->setContactName('John Doe');
        $this->addressRepository->save($address1, false);

        $address2 = $this->createTestAddress('Office Building', 'sender');
        $address2->setContactName('Jane Smith');
        $this->addressRepository->save($address2, false);

        $this->entityManager->flush();

        $this->loginAsCustomerUser();

        // Test search by name
        $this->client->request('GET', '/api/v1/customer/addresses?search=Home');
        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $response['data']);
    }

    public function testListAddressesWithSearchFilterByContact(): void
    {
        $address1 = $this->createTestAddress('Home Sweet Home', 'both');
        $address1->setContactName('John Doe');
        $this->addressRepository->save($address1, false);

        $address2 = $this->createTestAddress('Office Building', 'sender');
        $address2->setContactName('Jane Smith');
        $this->addressRepository->save($address2, false);

        $this->entityManager->flush();

        $this->loginAsCustomerUser();

        // Test search by contact name
        $this->client->request('GET', '/api/v1/customer/addresses?search=John');
        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $response['data']);
    }

    public function testGetAddressSuccess(): void
    {
        $address = $this->createTestAddress('Test Address', 'both');
        $this->loginAsCustomerUser();

        $this->client->request('GET', "/api/v1/customer/addresses/{$address->getId()}");

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals($address->getId(), $response['data']['id']);
        $this->assertEquals('Test Address', $response['data']['name']);
    }

    public function testGetAddressNotFound(): void
    {
        $this->loginAsCustomerUser();

        $this->client->request('GET', '/api/v1/customer/addresses/9999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Address not found', $response['message']);
    }

    public function testCreateAddressSuccess(): void
    {
        $this->loginAsCustomerUser();

        $addressData = [
            'name' => 'New Address',
            'type' => 'both',
            'contact_name' => 'John Doe',
            'company_name' => 'Test Company',
            'email' => 'john@example.com',
            'phone' => '+48123456789',
            'address' => 'ul. Testowa 123',
            'postal_code' => '00-001',
            'city' => 'Warsaw',
            'country' => 'Poland'
        ];

        $this->client->request(
            'POST',
            '/api/v1/customer/addresses',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($addressData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('Address created successfully', $response['message']);
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('New Address', $response['data']['name']);
    }

    public function testCreateAddressWithValidationErrors(): void
    {
        $this->loginAsCustomerUser();

        $invalidAddressData = [
            'name' => '', // Empty name should fail validation
            'type' => 'invalid_type',
            'contact_name' => '',
            'email' => 'invalid-email',
            'phone' => '',
            'address' => '',
            'postal_code' => '',
            'city' => '',
            'country' => ''
        ];

        $this->client->request(
            'POST',
            '/api/v1/customer/addresses',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($invalidAddressData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertFalse($response['success']);
        $this->assertEquals('Address validation failed', $response['message']);
        $this->assertArrayHasKey('errors', $response);
    }

    public function testUpdateAddressSuccess(): void
    {
        $address = $this->createTestAddress('Original Address', 'sender');
        $this->loginAsCustomerUser();

        $updateData = [
            'name' => 'Updated Address',
            'type' => 'both',
            'contact_name' => 'Jane Doe',
            'company_name' => 'Updated Company',
            'email' => 'jane@example.com',
            'phone' => '+48987654321',
            'address' => 'ul. Updated 456',
            'postal_code' => '01-234',
            'city' => 'Krakow',
            'country' => 'Poland'
        ];

        $this->client->request(
            'PUT',
            "/api/v1/customer/addresses/{$address->getId()}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('Address updated successfully', $response['message']);
        $this->assertEquals('Updated Address', $response['data']['name']);
        $this->assertEquals('both', $response['data']['type']);
    }

    public function testUpdateAddressNotFound(): void
    {
        $this->loginAsCustomerUser();

        $updateData = ['name' => 'Updated Address'];

        $this->client->request(
            'PUT',
            '/api/v1/customer/addresses/9999',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Address not found', $response['message']);
    }

    public function testDeleteAddressSuccess(): void
    {
        $address = $this->createTestAddress('Address To Delete', 'both');
        $addressId = $address->getId();
        $this->loginAsCustomerUser();

        $this->client->request('DELETE', "/api/v1/customer/addresses/{$addressId}");

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('Address deleted successfully', $response['message']);

        // Verify address is deleted
        $deletedAddress = $this->addressRepository->find($addressId);
        $this->assertNull($deletedAddress);
    }

    public function testDeleteAddressNotFound(): void
    {
        $this->loginAsCustomerUser();

        $this->client->request('DELETE', '/api/v1/customer/addresses/9999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Address not found', $response['message']);
    }

    public function testValidateAddress(): void
    {
        $this->loginAsCustomerUser();

        $addressData = [
            'address' => 'ul. Testowa 123',
            'postal_code' => '00-001',
            'city' => 'Warsaw',
            'country' => 'Poland',
            'courier_service' => 'inpost'
        ];

        $this->client->request(
            'POST',
            '/api/v1/customer/addresses/validate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($addressData)
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
    }

    public function testSuggestAddresses(): void
    {
        $this->loginAsCustomerUser();

        $suggestionData = [
            'address' => 'ul. Testowa',
            'country' => 'PL'
        ];

        $this->client->request(
            'POST',
            '/api/v1/customer/addresses/suggest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($suggestionData)
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
    }

    public function testSetDefaultAddress(): void
    {
        $address = $this->createTestAddress('Default Address', 'both');
        $this->loginAsCustomerUser();

        $defaultData = ['type' => 'sender'];

        $this->client->request(
            'POST',
            "/api/v1/customer/addresses/{$address->getId()}/set-default",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($defaultData)
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('Address set as default sender address', $response['message']);
    }

    public function testImportAddresses(): void
    {
        $this->loginAsCustomerUser();

        $importData = [
            'addresses' => [
                [
                    'name' => 'Imported Address 1',
                    'type' => 'sender',
                    'contact_name' => 'Import User 1',
                    'email' => 'import1@example.com',
                    'phone' => '+48111111111',
                    'address' => 'ul. Import 1',
                    'postal_code' => '00-001',
                    'city' => 'Warsaw',
                    'country' => 'Poland'
                ],
                [
                    'name' => 'Imported Address 2',
                    'type' => 'recipient',
                    'contact_name' => 'Import User 2',
                    'email' => 'import2@example.com',
                    'phone' => '+48222222222',
                    'address' => 'ul. Import 2',
                    'postal_code' => '01-234',
                    'city' => 'Krakow',
                    'country' => 'Poland'
                ]
            ],
            'overwrite_existing' => false
        ];

        $this->client->request(
            'POST',
            '/api/v1/customer/addresses/import',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($importData)
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('Addresses imported successfully', $response['message']);
        $this->assertArrayHasKey('data', $response);
    }

    public function testExportAddresses(): void
    {
        $this->createTestAddress('Export Address 1', 'sender');
        $this->createTestAddress('Export Address 2', 'recipient');

        $this->loginAsCustomerUser();

        $this->client->request('GET', '/api/v1/customer/addresses/export?format=json');

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
    }

    private function createTestCustomerAndUser(): void
    {
        $this->customer = new Customer();
        $this->customer->setCompanyName('Test Company')
                       ->setStatus('active')
                       ->setType('business');

        $this->customerUser = new CustomerUser();
        $this->customerUser->setEmail('test@example.com')
                          ->setFirstName('Test')
                          ->setLastName('User')
                          ->setPassword('$2y$13$hashedpassword') // This would be properly hashed in real scenario
                          ->setCustomer($this->customer)
                          ->setCustomerRole('owner')
                          ->setStatus('active')
                          ->setEmailVerifiedAt(new \DateTime());

        $this->entityManager->persist($this->customer);
        $this->entityManager->persist($this->customerUser);
        $this->entityManager->flush();
    }

    private function createTestAddress(string $name, string $type): CustomerAddress
    {
        $address = new CustomerAddress();
        $address->setCustomer($this->customer)
                ->setName($name)
                ->setType($type)
                ->setContactName('Test Contact')
                ->setEmail('contact@example.com')
                ->setPhone('+48123456789')
                ->setAddress('ul. Testowa 123')
                ->setPostalCode('00-001')
                ->setCity('Warsaw')
                ->setCountry('Poland');

        $this->addressRepository->save($address, true);

        return $address;
    }

    private function loginAsCustomerUser(): void
    {
        $this->client->loginUser($this->customerUser);
    }
}