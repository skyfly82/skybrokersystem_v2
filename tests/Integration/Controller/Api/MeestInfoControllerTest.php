<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration Tests for MeestInfoController
 *
 * Tests cover information and validation endpoints including:
 * - GET /v2/api/meest/info/countries - Supported countries
 * - POST /v2/api/meest/info/validate - Shipment validation
 * - GET /v2/api/meest/info/statistics - Shipping statistics
 * - GET /v2/api/meest/info/requirements - Validation rules
 * - GET /v2/api/meest/info/countries/{code}/check - Country support check
 * - GET /v2/api/meest/info/health - Health check
 *
 * Business validation scenarios:
 * - Country support validation
 * - Currency mapping
 * - Value requirements (localTotalValue, items.value.value)
 * - API health monitoring
 */
class MeestInfoControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Test getting supported countries
     */
    public function testGetSupportedCountries(): void
    {
        $this->client->request('GET', '/v2/api/meest/info/countries');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];

        $this->assertArrayHasKey('supported_countries', $data);
        $this->assertArrayHasKey('total_count', $data);

        $countries = $data['supported_countries'];
        $this->assertIsArray($countries);
        $this->assertGreaterThan(0, count($countries));

        // Verify structure of country entries
        foreach ($countries as $country) {
            $this->assertArrayHasKey('code', $country);
            $this->assertArrayHasKey('currency', $country);
            $this->assertIsString($country['code']);
            $this->assertIsString($country['currency']);
            $this->assertEquals(2, strlen($country['code'])); // ISO country codes are 2 characters
        }

        $this->assertEquals(count($countries), $data['total_count']);
    }

    /**
     * Test shipment validation with valid data
     */
    public function testValidateShipmentValid(): void
    {
        $validShipmentData = [
            'sender' => [
                'first_name' => 'Jan',
                'last_name' => 'Kowalski',
                'phone' => '+48123456789',
                'email' => 'jan@example.com',
                'country' => 'PL',
                'city' => 'Warsaw',
                'address' => 'ul. Testowa 1',
                'postal_code' => '00-001'
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
                    'localTotalValue' => 100.0,
                    'localCurrency' => 'USD'
                ],
                'contents' => 'Documents',
                'items' => [
                    [
                        'description' => 'Test item',
                        'quantity' => 1,
                        'value' => [
                            'value' => 100.0
                        ]
                    ]
                ]
            ]
        ];

        $this->client->request(
            'POST',
            '/v2/api/meest/info/validate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($validShipmentData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Validation passed', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];

        $this->assertTrue($data['valid']);
        $this->assertEquals('US', $data['destination_country']);
        $this->assertEquals('USD', $data['estimated_currency']);
    }

    /**
     * Test shipment validation with invalid data
     */
    public function testValidateShipmentInvalid(): void
    {
        $invalidShipmentData = [
            'sender' => [
                'first_name' => 'Test'
                // Missing required fields
            ],
            'recipient' => [
                // Empty recipient
            ],
            'parcel' => [
                'weight' => -1 // Invalid weight
            ]
        ];

        $this->client->request(
            'POST',
            '/v2/api/meest/info/validate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($invalidShipmentData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('validation_failed', $responseData['error']);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertFalse($responseData['data']['valid']);
    }

    /**
     * Test shipment validation with invalid JSON
     */
    public function testValidateShipmentInvalidJson(): void
    {
        $this->client->request(
            'POST',
            '/v2/api/meest/info/validate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid json{'
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('invalid_json', $responseData['error']);
        $this->assertStringContains('Invalid JSON', $responseData['message']);
    }

    /**
     * Test getting statistics
     */
    public function testGetStatistics(): void
    {
        $this->client->request('GET', '/v2/api/meest/info/statistics');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];

        $this->assertArrayHasKey('period', $data);
        $this->assertArrayHasKey('shipment_counts', $data);
        $this->assertArrayHasKey('delivery_performance', $data);
        $this->assertArrayHasKey('total_costs', $data);

        // Verify period structure
        $period = $data['period'];
        $this->assertArrayHasKey('from', $period);
        $this->assertArrayHasKey('to', $period);
        $this->assertArrayHasKey('days', $period);
        $this->assertEquals(30, $period['days']); // Default period
    }

    /**
     * Test getting statistics with custom period
     */
    public function testGetStatisticsCustomPeriod(): void
    {
        $this->client->request('GET', '/v2/api/meest/info/statistics', [
            'days' => 7
        ]);

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $period = $responseData['data']['period'];
        $this->assertEquals(7, $period['days']);
    }

    /**
     * Test getting statistics with invalid period (should be clamped)
     */
    public function testGetStatisticsInvalidPeriod(): void
    {
        // Test with negative days
        $this->client->request('GET', '/v2/api/meest/info/statistics', [
            'days' => -5
        ]);

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $period = $responseData['data']['period'];
        $this->assertEquals(1, $period['days']); // Should be clamped to minimum 1

        // Test with too many days
        $this->client->request('GET', '/v2/api/meest/info/statistics', [
            'days' => 500
        ]);

        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $period = $responseData['data']['period'];
        $this->assertEquals(365, $period['days']); // Should be clamped to maximum 365
    }

    /**
     * Test getting validation requirements
     */
    public function testGetRequirements(): void
    {
        $this->client->request('GET', '/v2/api/meest/info/requirements');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];

        $this->assertArrayHasKey('required_fields', $data);
        $this->assertArrayHasKey('optional_fields', $data);
        $this->assertArrayHasKey('limits', $data);
        $this->assertArrayHasKey('business_rules', $data);
        $this->assertArrayHasKey('supported_countries', $data);
        $this->assertArrayHasKey('supported_shipment_types', $data);

        // Verify required fields structure
        $requiredFields = $data['required_fields'];
        $this->assertArrayHasKey('sender', $requiredFields);
        $this->assertArrayHasKey('recipient', $requiredFields);
        $this->assertArrayHasKey('parcel', $requiredFields);

        // Verify sender required fields
        $senderFields = $requiredFields['sender'];
        $expectedSenderFields = ['first_name', 'last_name', 'phone', 'email', 'country', 'city', 'address', 'postal_code'];
        foreach ($expectedSenderFields as $field) {
            $this->assertContains($field, $senderFields, "Missing required sender field: {$field}");
        }

        // Verify parcel value requirements (important for MEEST)
        $parcelFields = $requiredFields['parcel'];
        $this->assertArrayHasKey('value', $parcelFields);
        $valueFields = $parcelFields['value'];
        $this->assertContains('localTotalValue', $valueFields);
        $this->assertContains('localCurrency', $valueFields);

        // Verify limits
        $limits = $data['limits'];
        $this->assertArrayHasKey('max_weight', $limits);
        $this->assertArrayHasKey('max_dimensions', $limits);
        $this->assertArrayHasKey('max_value', $limits);
        $this->assertArrayHasKey('min_value', $limits);

        // Verify business rules
        $businessRules = $data['business_rules'];
        $this->assertIsArray($businessRules);
        $this->assertGreaterThan(0, count($businessRules));

        // Verify supported shipment types
        $shipmentTypes = $data['supported_shipment_types'];
        $expectedTypes = ['standard', 'express', 'economy', 'return'];
        foreach ($expectedTypes as $type) {
            $this->assertContains($type, $shipmentTypes, "Missing supported shipment type: {$type}");
        }
    }

    /**
     * Test checking supported country (positive case)
     */
    public function testCheckCountrySupported(): void
    {
        $this->client->request('GET', '/v2/api/meest/info/countries/PL/check');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];

        $this->assertEquals('PL', $data['country_code']);
        $this->assertTrue($data['supported']);
        $this->assertArrayHasKey('currency', $data);
        $this->assertIsString($data['currency']);
    }

    /**
     * Test checking unsupported country
     */
    public function testCheckCountryUnsupported(): void
    {
        // Use a country code that's unlikely to be supported
        $this->client->request('GET', '/v2/api/meest/info/countries/XX/check');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $data = $responseData['data'];
        $this->assertEquals('XX', $data['country_code']);
        $this->assertFalse($data['supported']);
        $this->assertArrayHasKey('supported_countries', $data);
        $this->assertIsArray($data['supported_countries']);
    }

    /**
     * Test checking country with lowercase code (should be normalized)
     */
    public function testCheckCountryLowercase(): void
    {
        $this->client->request('GET', '/v2/api/meest/info/countries/pl/check');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $data = $responseData['data'];

        // Should be normalized to uppercase
        $this->assertEquals('PL', $data['country_code']);
    }

    /**
     * Test health check endpoint
     */
    public function testHealthCheck(): void
    {
        $this->client->request('GET', '/v2/api/meest/info/health');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];

        $this->assertEquals('healthy', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertEquals('connected', $data['database']);
        $this->assertArrayHasKey('recent_shipments', $data);
        $this->assertArrayHasKey('supported_countries_count', $data);

        // Verify timestamp format
        $timestamp = $data['timestamp'];
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $timestamp);

        // Verify numeric values
        $this->assertIsInt($data['recent_shipments']);
        $this->assertIsInt($data['supported_countries_count']);
        $this->assertGreaterThan(0, $data['supported_countries_count']);
    }

    /**
     * Test validation of parcel value requirements (MEEST specific)
     */
    public function testValidateParcelValueRequirements(): void
    {
        // Test with missing localTotalValue
        $shipmentDataMissingValue = [
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
                    // Missing localTotalValue
                    'localCurrency' => 'USD'
                ],
                'contents' => 'Test'
            ]
        ];

        $this->client->request(
            'POST',
            '/v2/api/meest/info/validate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($shipmentDataMissingValue)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('validation_failed', $responseData['error']);
    }

    /**
     * Test validation of items value requirements (MEEST specific)
     */
    public function testValidateItemsValueRequirements(): void
    {
        // Test with missing items.value.value
        $shipmentDataMissingItemValue = [
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
                            // Missing value.value
                        ]
                    ]
                ]
            ]
        ];

        $this->client->request(
            'POST',
            '/v2/api/meest/info/validate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($shipmentDataMissingItemValue)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('validation_failed', $responseData['error']);
    }

    /**
     * Test getting requirements for specific business rules
     */
    public function testRequirementsBusinessRules(): void
    {
        $this->client->request('GET', '/v2/api/meest/info/requirements');

        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $businessRules = $responseData['data']['business_rules'];

        // Verify specific business rules mentioned in requirements
        $expectedRules = [
            'High value shipments (>1000) require signature',
            'Return shipments require original_tracking_number',
            'Currency must match destination country',
            'Items must have individual values specified'
        ];

        foreach ($expectedRules as $rule) {
            $this->assertContains($rule, $businessRules, "Missing business rule: {$rule}");
        }
    }

    /**
     * Test currency validation for different countries
     */
    public function testCurrencyValidationForCountries(): void
    {
        $testCountries = ['PL', 'US', 'DE', 'GB'];

        foreach ($testCountries as $country) {
            $this->client->request('GET', "/v2/api/meest/info/countries/{$country}/check");

            $response = $this->client->getResponse();
            if ($response->getStatusCode() === Response::HTTP_OK) {
                $responseData = json_decode($response->getContent(), true);
                $data = $responseData['data'];

                if ($data['supported']) {
                    $this->assertArrayHasKey('currency', $data);
                    $this->assertIsString($data['currency']);
                    $this->assertEquals(3, strlen($data['currency'])); // Currency codes are 3 characters
                }
            }
        }
    }

    /**
     * Test complete integration flow: countries -> validation -> requirements
     */
    public function testCompleteIntegrationFlow(): void
    {
        // 1. Get supported countries
        $this->client->request('GET', '/v2/api/meest/info/countries');
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $countriesData = json_decode($response->getContent(), true);
        $countries = $countriesData['data']['supported_countries'];
        $this->assertNotEmpty($countries);

        // 2. Pick first supported country and check it
        $firstCountry = $countries[0];
        $this->client->request('GET', "/v2/api/meest/info/countries/{$firstCountry['code']}/check");
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $checkData = json_decode($response->getContent(), true);
        $this->assertTrue($checkData['data']['supported']);
        $this->assertEquals($firstCountry['currency'], $checkData['data']['currency']);

        // 3. Get requirements
        $this->client->request('GET', '/v2/api/meest/info/requirements');
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $requirementsData = json_decode($response->getContent(), true);
        $supportedCountries = $requirementsData['data']['supported_countries'];
        $this->assertContains($firstCountry['code'], $supportedCountries);

        // 4. Validate a shipment using the information gathered
        $validShipment = [
            'sender' => [
                'first_name' => 'Test',
                'last_name' => 'Sender',
                'phone' => '+48123456789',
                'email' => 'test@example.com',
                'country' => $firstCountry['code'],
                'city' => 'Test City',
                'address' => 'Test Address',
                'postal_code' => '00-001'
            ],
            'recipient' => [
                'first_name' => 'Test',
                'last_name' => 'Recipient',
                'phone' => '+1234567890',
                'email' => 'recipient@example.com',
                'country' => $firstCountry['code'],
                'city' => 'Test City',
                'address' => 'Test Address',
                'postal_code' => '00-001'
            ],
            'parcel' => [
                'weight' => 1.0,
                'value' => [
                    'localTotalValue' => 100.0,
                    'localCurrency' => $firstCountry['currency']
                ],
                'contents' => 'Test items'
            ]
        ];

        $this->client->request(
            'POST',
            '/v2/api/meest/info/validate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($validShipment)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $validationData = json_decode($response->getContent(), true);
        $this->assertTrue($validationData['success']);
        $this->assertTrue($validationData['data']['valid']);
    }
}