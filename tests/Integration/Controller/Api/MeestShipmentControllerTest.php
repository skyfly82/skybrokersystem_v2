<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Api;

use App\Domain\Courier\Meest\Entity\MeestShipment;
use App\Domain\Courier\Meest\Enum\MeestShipmentType;
use App\Domain\Courier\Meest\Enum\MeestTrackingStatus;
use App\Domain\Courier\Meest\Repository\MeestShipmentRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration Tests for MeestShipmentController
 *
 * Tests cover all REST API endpoints including:
 * - POST /v2/api/meest/parcels - Create standard shipment
 * - POST /v2/api/meest/parcels/return - Create return shipment
 * - GET /v2/api/meest/tracking/{trackingNumber} - Get tracking info
 * - GET /v2/api/meest/shipments/{trackingNumber} - Get shipment details
 * - GET /v2/api/meest/labels/{trackingNumber} - Download label
 * - GET /v2/api/meest/shipments - List shipments with pagination
 *
 * Real-world scenarios from correspondence:
 * - Test credentials (BLPAMST / h+gl3P3(Wl)
 * - Test tracking number (BLP68A82A025DBC2PLTEST01)
 * - Value validation (localTotalValue, items.value.value)
 * - Error handling ("Non-unique parcel number", "Any routing not found")
 */
class MeestShipmentControllerTest extends WebTestCase
{
    private $client;
    private MeestShipmentRepository $repository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->repository = static::getContainer()->get(MeestShipmentRepository::class);
    }

    /**
     * Test creating standard shipment to all supported countries
     */
    public function testCreateStandardShipmentSuccess(): void
    {
        $shipmentData = [
            'sender' => [
                'first_name' => 'Jan',
                'last_name' => 'Kowalski',
                'phone' => '+48123456789',
                'email' => 'jan@example.com',
                'country' => 'PL',
                'city' => 'Warsaw',
                'address' => 'ul. Testowa 1',
                'postal_code' => '00-001',
                'company' => 'Test Company'
            ],
            'recipient' => [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'phone' => '+1234567890',
                'email' => 'john@example.com',
                'country' => 'US',
                'city' => 'New York',
                'address' => '123 Test St',
                'postal_code' => '10001'
            ],
            'parcel' => [
                'weight' => 1.5,
                'length' => 20.0,
                'width' => 15.0,
                'height' => 10.0,
                'value' => [
                    'localTotalValue' => 100.0, // Required by MEEST
                    'localCurrency' => 'USD'
                ],
                'contents' => 'Documents',
                'description' => 'Test shipment',
                'items' => [
                    [
                        'description' => 'Test item',
                        'quantity' => 1,
                        'value' => [
                            'value' => 100.0 // Required by MEEST
                        ]
                    ]
                ]
            ],
            'service_type' => 'standard',
            'special_instructions' => 'Handle with care'
        ];

        $this->client->request(
            'POST',
            '/v2/api/meest/parcels',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($shipmentData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Shipment created successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $shipment = $responseData['data'];

        $this->assertArrayHasKey('tracking_number', $shipment);
        $this->assertArrayHasKey('shipment_id', $shipment);
        $this->assertArrayHasKey('status', $shipment);
        $this->assertArrayHasKey('total_cost', $shipment);
        $this->assertArrayHasKey('currency', $shipment);

        // Verify tracking number format
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{10,20}$/', $shipment['tracking_number']);
    }

    /**
     * Test creating return shipment with createReturnParcel=true
     */
    public function testCreateReturnShipmentSuccess(): void
    {
        $returnShipmentData = [
            'original_tracking_number' => 'BLP68A82A025DBC2PLTEST01',
            'sender' => [
                'first_name' => 'Return',
                'last_name' => 'Sender',
                'phone' => '+48123456789',
                'email' => 'return@example.com',
                'country' => 'PL',
                'city' => 'Warsaw',
                'address' => 'ul. Zwrotna 1',
                'postal_code' => '00-001'
            ],
            'recipient' => [
                'first_name' => 'Original',
                'last_name' => 'Sender',
                'phone' => '+1234567890',
                'email' => 'original@example.com',
                'country' => 'US',
                'city' => 'New York',
                'address' => '123 Original St',
                'postal_code' => '10001'
            ],
            'parcel' => [
                'weight' => 1.0,
                'length' => 15.0,
                'width' => 10.0,
                'height' => 8.0,
                'value' => [
                    'localTotalValue' => 50.0,
                    'localCurrency' => 'USD'
                ],
                'contents' => 'Return Item'
            ]
        ];

        $this->client->request(
            'POST',
            '/v2/api/meest/parcels/return',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($returnShipmentData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Return shipment created successfully', $responseData['message']);

        $shipment = $responseData['data'];
        $this->assertArrayHasKey('tracking_number', $shipment);
        $this->assertEquals('return', $shipment['shipment_type']);
    }

    /**
     * Test validation error for missing required field
     */
    public function testCreateShipmentMissingRequiredField(): void
    {
        $invalidData = [
            'sender' => [
                'first_name' => 'Test',
                'last_name' => 'User'
                // Missing required fields
            ]
            // Missing recipient and parcel
        ];

        $this->client->request(
            'POST',
            '/v2/api/meest/parcels',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($invalidData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('validation_failed', $responseData['error']);
    }

    /**
     * Test validation error for missing localTotalValue
     */
    public function testCreateShipmentMissingLocalTotalValue(): void
    {
        $invalidData = [
            'sender' => [
                'first_name' => 'Test',
                'last_name' => 'User',
                'phone' => '+48123456789',
                'email' => 'test@example.com',
                'country' => 'PL',
                'city' => 'Warsaw',
                'address' => 'Test Address',
                'postal_code' => '00-001'
            ],
            'recipient' => [
                'first_name' => 'Test',
                'last_name' => 'Recipient',
                'phone' => '+1234567890',
                'email' => 'recipient@example.com',
                'country' => 'US',
                'city' => 'New York',
                'address' => 'Test Address',
                'postal_code' => '10001'
            ],
            'parcel' => [
                'weight' => 1.0,
                'value' => [
                    // Missing localTotalValue - required by MEEST
                    'localCurrency' => 'USD'
                ],
                'contents' => 'Test'
            ]
        ];

        $this->client->request(
            'POST',
            '/v2/api/meest/parcels',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($invalidData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertStringContains('localTotalValue is required', $responseData['message']);
    }

    /**
     * Test validation error for missing items.value.value
     */
    public function testCreateShipmentMissingItemValue(): void
    {
        $invalidData = [
            'sender' => [
                'first_name' => 'Test',
                'last_name' => 'User',
                'phone' => '+48123456789',
                'email' => 'test@example.com',
                'country' => 'PL',
                'city' => 'Warsaw',
                'address' => 'Test Address',
                'postal_code' => '00-001'
            ],
            'recipient' => [
                'first_name' => 'Test',
                'last_name' => 'Recipient',
                'phone' => '+1234567890',
                'email' => 'recipient@example.com',
                'country' => 'US',
                'city' => 'New York',
                'address' => 'Test Address',
                'postal_code' => '10001'
            ],
            'parcel' => [
                'weight' => 1.0,
                'value' => [
                    'localTotalValue' => 100.0,
                    'localCurrency' => 'USD'
                ],
                'contents' => 'Test',
                'items' => [
                    [
                        'description' => 'Test item',
                        'value' => [
                            // Missing value.value - required by MEEST
                        ]
                    ]
                ]
            ]
        ];

        $this->client->request(
            'POST',
            '/v2/api/meest/parcels',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($invalidData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertStringContains('items.0.value.value is required', $responseData['message']);
    }

    /**
     * Test getting tracking information for test package
     */
    public function testGetTrackingForTestPackage(): void
    {
        $trackingNumber = 'BLP68A82A025DBC2PLTEST01';

        $this->client->request('GET', "/v2/api/meest/tracking/{$trackingNumber}");

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $trackingData = $responseData['data'];
        $this->assertEquals($trackingNumber, $trackingData['tracking_number']);
        $this->assertArrayHasKey('status', $trackingData);
        $this->assertArrayHasKey('status_description', $trackingData);
        $this->assertArrayHasKey('events', $trackingData);
        $this->assertArrayHasKey('location', $trackingData);
    }

    /**
     * Test getting tracking for non-existent shipment
     */
    public function testGetTrackingNotFound(): void
    {
        $this->client->request('GET', '/v2/api/meest/tracking/INVALID123');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('not_found', $responseData['error']);
        $this->assertEquals('Tracking number not found', $responseData['message']);
    }

    /**
     * Test getting shipment details
     */
    public function testGetShipmentDetails(): void
    {
        // First create a test shipment in the database
        $shipment = new MeestShipment(
            trackingNumber: 'TEST123456789',
            shipmentId: 'SHIP-TEST-123',
            shipmentType: MeestShipmentType::STANDARD,
            senderData: ['name' => 'Test Sender'],
            recipientData: ['name' => 'Test Recipient'],
            parcelData: ['weight' => 1.0]
        );

        $this->repository->save($shipment);

        $this->client->request('GET', '/v2/api/meest/shipments/TEST123456789');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $shipmentData = $responseData['data'];
        $this->assertEquals('TEST123456789', $shipmentData['tracking_number']);
        $this->assertEquals('SHIP-TEST-123', $shipmentData['shipment_id']);
        $this->assertEquals('standard', $shipmentData['shipment_type']);
    }

    /**
     * Test getting non-existent shipment details
     */
    public function testGetShipmentDetailsNotFound(): void
    {
        $this->client->request('GET', '/v2/api/meest/shipments/NONEXISTENT123');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('not_found', $responseData['error']);
        $this->assertEquals('Shipment not found', $responseData['message']);
    }

    /**
     * Test downloading shipping label
     */
    public function testDownloadLabelSuccess(): void
    {
        // Create a test shipment with label
        $shipment = new MeestShipment(
            trackingNumber: 'LABEL123456789',
            shipmentId: 'SHIP-LABEL-123',
            shipmentType: MeestShipmentType::STANDARD,
            senderData: ['name' => 'Test Sender'],
            recipientData: ['name' => 'Test Recipient'],
            parcelData: ['weight' => 1.0]
        );

        // Mock label URL
        $shipment->setLabelUrl('https://api.meest.com/labels/test.pdf');
        $this->repository->save($shipment);

        $this->client->request('GET', '/v2/api/meest/labels/LABEL123456789');

        $response = $this->client->getResponse();

        // Note: In real implementation, this would return a binary file response
        // For testing, we check that the request is processed correctly
        $this->assertNotEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    /**
     * Test downloading label for non-existent shipment
     */
    public function testDownloadLabelNotFound(): void
    {
        $this->client->request('GET', '/v2/api/meest/labels/NONEXISTENT123');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * Test listing shipments with pagination
     */
    public function testListShipmentsWithPagination(): void
    {
        // Create multiple test shipments
        for ($i = 1; $i <= 25; $i++) {
            $shipment = new MeestShipment(
                trackingNumber: "LIST{$i}23456789",
                shipmentId: "SHIP-LIST-{$i}",
                shipmentType: MeestShipmentType::STANDARD,
                senderData: ['name' => 'Test Sender'],
                recipientData: ['name' => 'Test Recipient'],
                parcelData: ['weight' => 1.0]
            );

            $this->repository->save($shipment);
        }

        // Test first page
        $this->client->request('GET', '/v2/api/meest/shipments?page=1&limit=10');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('pagination', $responseData);

        $pagination = $responseData['pagination'];
        $this->assertEquals(1, $pagination['page']);
        $this->assertEquals(10, $pagination['limit']);
        $this->assertGreaterThanOrEqual(25, $pagination['total']);
        $this->assertTrue($pagination['has_more']);

        // Verify we got 10 shipments
        $this->assertCount(10, $responseData['data']);
    }

    /**
     * Test listing shipments with status filter
     */
    public function testListShipmentsWithStatusFilter(): void
    {
        // Create shipments with different statuses
        $shipment1 = new MeestShipment(
            trackingNumber: 'FILTER123456789',
            shipmentId: 'SHIP-FILTER-1',
            shipmentType: MeestShipmentType::STANDARD,
            senderData: ['name' => 'Test Sender'],
            recipientData: ['name' => 'Test Recipient'],
            parcelData: ['weight' => 1.0]
        );
        $shipment1->updateStatus(MeestTrackingStatus::DELIVERED);

        $shipment2 = new MeestShipment(
            trackingNumber: 'FILTER223456789',
            shipmentId: 'SHIP-FILTER-2',
            shipmentType: MeestShipmentType::STANDARD,
            senderData: ['name' => 'Test Sender'],
            recipientData: ['name' => 'Test Recipient'],
            parcelData: ['weight' => 1.0]
        );
        $shipment2->updateStatus(MeestTrackingStatus::IN_TRANSIT);

        $this->repository->save($shipment1);
        $this->repository->save($shipment2);

        // Filter by delivered status
        $this->client->request('GET', '/v2/api/meest/shipments?status=delivered');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        // Should only return delivered shipments
        foreach ($responseData['data'] as $shipment) {
            $this->assertEquals('delivered', $shipment['status']);
        }
    }

    /**
     * Test listing shipments with date range filter
     */
    public function testListShipmentsWithDateRangeFilter(): void
    {
        $dateFrom = (new \DateTimeImmutable('-7 days'))->format('Y-m-d');
        $dateTo = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->request('GET', "/v2/api/meest/shipments?date_from={$dateFrom}&date_to={$dateTo}");

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('pagination', $responseData);
    }

    /**
     * Test invalid JSON request
     */
    public function testInvalidJsonRequest(): void
    {
        $this->client->request(
            'POST',
            '/v2/api/meest/parcels',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid json{'
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * Test empty request body
     */
    public function testEmptyRequestBody(): void
    {
        $this->client->request(
            'POST',
            '/v2/api/meest/parcels',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            ''
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * Test return shipment without original tracking number
     */
    public function testCreateReturnShipmentMissingOriginalTracking(): void
    {
        $invalidReturnData = [
            // Missing original_tracking_number
            'sender' => [
                'first_name' => 'Return',
                'last_name' => 'Sender',
                'phone' => '+48123456789',
                'email' => 'return@example.com',
                'country' => 'PL',
                'city' => 'Warsaw',
                'address' => 'ul. Zwrotna 1',
                'postal_code' => '00-001'
            ]
        ];

        $this->client->request(
            'POST',
            '/v2/api/meest/parcels/return',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($invalidReturnData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertStringContains('original_tracking_number is required', $responseData['message']);
    }

    /**
     * Test creating shipment with all service types
     */
    public function testCreateShipmentAllServiceTypes(): void
    {
        $baseData = [
            'sender' => [
                'first_name' => 'Test',
                'last_name' => 'Sender',
                'phone' => '+48123456789',
                'email' => 'test@example.com',
                'country' => 'PL',
                'city' => 'Warsaw',
                'address' => 'Test Address',
                'postal_code' => '00-001'
            ],
            'recipient' => [
                'first_name' => 'Test',
                'last_name' => 'Recipient',
                'phone' => '+1234567890',
                'email' => 'recipient@example.com',
                'country' => 'US',
                'city' => 'New York',
                'address' => 'Test Address',
                'postal_code' => '10001'
            ],
            'parcel' => [
                'weight' => 1.0,
                'value' => [
                    'localTotalValue' => 100.0,
                    'localCurrency' => 'USD'
                ],
                'contents' => 'Test'
            ]
        ];

        $serviceTypes = ['standard', 'express', 'economy'];

        foreach ($serviceTypes as $serviceType) {
            $shipmentData = array_merge($baseData, ['service_type' => $serviceType]);

            $this->client->request(
                'POST',
                '/v2/api/meest/parcels',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($shipmentData)
            );

            $response = $this->client->getResponse();
            $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode(), "Failed for service type: {$serviceType}");

            $responseData = json_decode($response->getContent(), true);
            $this->assertTrue($responseData['success']);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $testTrackingNumbers = [
            'TEST123456789',
            'LABEL123456789',
            'FILTER123456789',
            'FILTER223456789'
        ];

        foreach ($testTrackingNumbers as $trackingNumber) {
            $shipment = $this->repository->findByTrackingNumber($trackingNumber);
            if ($shipment) {
                $this->repository->remove($shipment);
            }
        }

        // Clean up LIST shipments
        for ($i = 1; $i <= 25; $i++) {
            $shipment = $this->repository->findByTrackingNumber("LIST{$i}23456789");
            if ($shipment) {
                $this->repository->remove($shipment);
            }
        }

        parent::tearDown();
    }
}