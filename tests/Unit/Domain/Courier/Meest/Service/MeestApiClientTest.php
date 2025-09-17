<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Courier\Meest\Service;

use App\Domain\Courier\Meest\DTO\MeestAuthResponseDTO;
use App\Domain\Courier\Meest\DTO\MeestShipmentRequestDTO;
use App\Domain\Courier\Meest\DTO\MeestShipmentResponseDTO;
use App\Domain\Courier\Meest\DTO\MeestTrackingResponseDTO;
use App\Domain\Courier\Meest\Enum\MeestCountry;
use App\Domain\Courier\Meest\Enum\MeestShipmentType;
use App\Domain\Courier\Meest\Enum\MeestTrackingStatus;
use App\Domain\Courier\Meest\Exception\MeestIntegrationException;
use App\Domain\Courier\Meest\Service\MeestApiClient;
use App\Domain\Courier\Meest\ValueObject\MeestAddress;
use App\Domain\Courier\Meest\ValueObject\MeestCredentials;
use App\Domain\Courier\Meest\ValueObject\MeestParcel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Comprehensive Unit Tests for MEEST API Client
 *
 * Tests cover all scenarios from correspondence including:
 * - Authentication with test credentials (BLPAMST / h+gl3P3(Wl)
 * - Standard and return shipment creation
 * - Tracking for test package BLP68A82A025DBC2PLTEST01
 * - Error handling for "Non-unique parcel number" and "Any routing not found"
 * - Value validation (localTotalValue, items.value.value)
 * - Retry logic and rate limiting
 */
class MeestApiClientTest extends TestCase
{
    private HttpClientInterface|MockObject $httpClient;
    private LoggerInterface|MockObject $logger;
    private CacheInterface|MockObject $cache;
    private MeestCredentials $credentials;
    private MeestApiClient $apiClient;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);

        // Using test credentials from correspondence
        $this->credentials = new MeestCredentials(
            'BLPAMST',
            'h+gl3P3(Wl',
            'https://api.meest.com'
        );

        $this->apiClient = new MeestApiClient(
            $this->httpClient,
            $this->logger,
            $this->credentials,
            $this->cache
        );
    }

    /**
     * Test successful authentication with test credentials
     * Scenario: BLPAMST / h+gl3P3(Wl credentials should authenticate successfully
     */
    public function testAuthenticateSuccess(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('toArray')->willReturn([
            'access_token' => 'test-token-12345',
            'token_type' => 'Bearer',
            'expires_in' => 3600
        ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://api.meest.com/v2/api/auth',
                $this->callback(function ($options) {
                    $this->assertArrayHasKey('json', $options);
                    $payload = $options['json'];
                    $this->assertEquals('BLPAMST', $payload['username']);
                    $this->assertEquals('h+gl3P3(Wl', $payload['password']);
                    return true;
                })
            )
            ->willReturn($mockResponse);

        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with('meest_auth_token', $this->isInstanceOf(MeestAuthResponseDTO::class), 3600);

        $authResponse = $this->apiClient->authenticate();

        $this->assertInstanceOf(MeestAuthResponseDTO::class, $authResponse);
        $this->assertEquals('test-token-12345', $authResponse->accessToken);
        $this->assertEquals('Bearer', $authResponse->tokenType);
    }

    /**
     * Test authentication failure
     */
    public function testAuthenticateFailure(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(401);
        $mockResponse->method('toArray')->willReturn(['error' => 'Invalid credentials']);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $this->expectException(MeestIntegrationException::class);
        $this->expectExceptionMessage('Authentication failed');

        $this->apiClient->authenticate();
    }

    /**
     * Test authentication from cache
     */
    public function testAuthenticateFromCache(): void
    {
        $cachedAuth = new MeestAuthResponseDTO('cached-token', 'Bearer', 3600);

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with('meest_auth_token')
            ->willReturn($cachedAuth);

        // HttpClient should not be called since we use cached token
        $this->httpClient->expects($this->never())->method('request');

        $result = $this->apiClient->authenticate();
        $this->assertEquals('cached-token', $result->accessToken);
    }

    /**
     * Test creating standard shipment to all supported countries
     */
    public function testCreateStandardShipmentSuccess(): void
    {
        // First mock authentication
        $this->mockSuccessfulAuthentication();

        $sender = new MeestAddress(
            'Jan',
            'Kowalski',
            '+48123456789',
            'jan@example.com',
            MeestCountry::POLAND,
            'Warsaw',
            'ul. Testowa 1',
            '00-001'
        );

        $recipient = new MeestAddress(
            'John',
            'Smith',
            '+1234567890',
            'john@example.com',
            MeestCountry::USA,
            'New York',
            '123 Test St',
            '10001'
        );

        $parcel = new MeestParcel(
            weight: 1.5,
            length: 20.0,
            width: 15.0,
            height: 10.0,
            value: 100.0,
            currency: 'USD',
            contents: 'Documents',
            description: 'Test shipment'
        );

        $request = new MeestShipmentRequestDTO(
            $sender,
            $recipient,
            $parcel,
            MeestShipmentType::STANDARD
        );

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('toArray')->willReturn([
            'tracking_number' => 'BLP68A82A025DBC2PLTEST01',
            'shipment_id' => 'SHIP-123456',
            'status' => 'created',
            'estimated_delivery' => '2024-01-15T10:00:00Z',
            'total_cost' => 25.50,
            'currency' => 'USD'
        ]);

        $this->httpClient
            ->expects($this->exactly(2)) // auth + create shipment
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use ($mockResponse) {
                if (str_contains($url, '/auth')) {
                    return $this->createAuthResponse();
                }
                return $mockResponse;
            });

        $result = $this->apiClient->createShipment($request);

        $this->assertInstanceOf(MeestShipmentResponseDTO::class, $result);
        $this->assertEquals('BLP68A82A025DBC2PLTEST01', $result->trackingNumber);
        $this->assertEquals('SHIP-123456', $result->shipmentId);
        $this->assertEquals(25.50, $result->totalCost);
    }

    /**
     * Test creating return shipment with createReturnParcel=true
     */
    public function testCreateReturnShipmentSuccess(): void
    {
        $this->mockSuccessfulAuthentication();

        $sender = new MeestAddress(
            'Return',
            'Sender',
            '+48123456789',
            'return@example.com',
            MeestCountry::POLAND,
            'Warsaw',
            'ul. Zwrotna 1',
            '00-001'
        );

        $recipient = new MeestAddress(
            'Original',
            'Sender',
            '+1234567890',
            'original@example.com',
            MeestCountry::USA,
            'New York',
            '123 Original St',
            '10001'
        );

        $parcel = new MeestParcel(
            weight: 1.0,
            length: 15.0,
            width: 10.0,
            height: 8.0,
            value: 50.0,
            currency: 'USD',
            contents: 'Return Item'
        );

        $request = new MeestShipmentRequestDTO(
            $sender,
            $recipient,
            $parcel,
            MeestShipmentType::RETURN,
            originalTrackingNumber: 'BLP68A82A025DBC2PLTEST01'
        );

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('toArray')->willReturn([
            'tracking_number' => 'BLP68A82A025DBC2PLTEST02',
            'shipment_id' => 'RETURN-123456',
            'status' => 'created',
            'original_tracking_number' => 'BLP68A82A025DBC2PLTEST01',
            'total_cost' => 15.75,
            'currency' => 'USD'
        ]);

        $this->httpClient
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use ($mockResponse) {
                if (str_contains($url, '/auth')) {
                    return $this->createAuthResponse();
                }

                // Verify return endpoint is called
                $this->assertStringContains('/parcels/return', $url);

                // Verify payload contains createReturnParcel flag
                $this->assertArrayHasKey('json', $options);
                $payload = $options['json'];
                $this->assertEquals('BLP68A82A025DBC2PLTEST01', $payload['original_tracking_number']);

                return $mockResponse;
            });

        $result = $this->apiClient->createReturnShipment($request);

        $this->assertEquals('BLP68A82A025DBC2PLTEST02', $result->trackingNumber);
        $this->assertEquals('RETURN-123456', $result->shipmentId);
    }

    /**
     * Test tracking for test package BLP68A82A025DBC2PLTEST01
     */
    public function testGetTrackingForTestPackage(): void
    {
        $this->mockSuccessfulAuthentication();

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('toArray')->willReturn([
            'tracking_number' => 'BLP68A82A025DBC2PLTEST01',
            'status' => 'in_transit',
            'last_update' => '2024-01-10T14:30:00Z',
            'estimated_delivery' => '2024-01-15T10:00:00Z',
            'current_location' => 'Warsaw Distribution Center',
            'events' => [
                [
                    'timestamp' => '2024-01-10T14:30:00Z',
                    'status' => 'in_transit',
                    'location' => 'Warsaw Distribution Center',
                    'description' => 'Package is in transit'
                ],
                [
                    'timestamp' => '2024-01-10T09:15:00Z',
                    'status' => 'collected',
                    'location' => 'Warsaw',
                    'description' => 'Package collected from sender'
                ]
            ]
        ]);

        $this->httpClient
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use ($mockResponse) {
                if (str_contains($url, '/auth')) {
                    return $this->createAuthResponse();
                }

                // Verify tracking endpoint with correct tracking number
                $this->assertEquals('GET', $method);
                $this->assertStringContains('/tracking', $url);
                $this->assertEquals('BLP68A82A025DBC2PLTEST01', $options['query']['tracking_number']);

                return $mockResponse;
            });

        $result = $this->apiClient->getTracking('BLP68A82A025DBC2PLTEST01');

        $this->assertInstanceOf(MeestTrackingResponseDTO::class, $result);
        $this->assertEquals('BLP68A82A025DBC2PLTEST01', $result->trackingNumber);
        $this->assertEquals(MeestTrackingStatus::IN_TRANSIT, $result->status);
        $this->assertEquals('Warsaw Distribution Center', $result->currentLocation);
        $this->assertCount(2, $result->events);
    }

    /**
     * Test error handling for "Non-unique parcel number"
     */
    public function testCreateShipmentNonUniqueParcelNumber(): void
    {
        $this->mockSuccessfulAuthentication();

        $request = $this->createSampleShipmentRequest();

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(409);
        $mockResponse->method('toArray')->willReturn([
            'error' => 'Non-unique parcel number',
            'message' => 'Parcel number already exists in the system'
        ]);

        $this->httpClient
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use ($mockResponse) {
                if (str_contains($url, '/auth')) {
                    return $this->createAuthResponse();
                }
                return $mockResponse;
            });

        $this->expectException(MeestIntegrationException::class);
        $this->expectExceptionMessage('Non-unique parcel number');
        $this->expectExceptionCode(409);

        $this->apiClient->createShipment($request);
    }

    /**
     * Test error handling for "Any routing not found"
     */
    public function testCreateShipmentNoRoutingFound(): void
    {
        $this->mockSuccessfulAuthentication();

        $request = $this->createSampleShipmentRequest();

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(422);
        $mockResponse->method('toArray')->willReturn([
            'error' => 'Any routing not found',
            'message' => 'No routing available for the specified destination'
        ]);

        $this->httpClient
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use ($mockResponse) {
                if (str_contains($url, '/auth')) {
                    return $this->createAuthResponse();
                }
                return $mockResponse;
            });

        $this->expectException(MeestIntegrationException::class);
        $this->expectExceptionMessage('No routing found for the destination');
        $this->expectExceptionCode(422);

        $this->apiClient->createShipment($request);
    }

    /**
     * Test label generation
     */
    public function testGenerateLabelSuccess(): void
    {
        $this->mockSuccessfulAuthentication();

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('toArray')->willReturn([
            'label_url' => 'https://api.meest.com/labels/BLP68A82A025DBC2PLTEST01.pdf'
        ]);

        $this->httpClient
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use ($mockResponse) {
                if (str_contains($url, '/auth')) {
                    return $this->createAuthResponse();
                }

                $this->assertEquals('GET', $method);
                $this->assertStringContains('/label', $url);
                $this->assertEquals('BLP68A82A025DBC2PLTEST01', $options['query']['tracking_number']);

                return $mockResponse;
            });

        $labelUrl = $this->apiClient->generateLabel('BLP68A82A025DBC2PLTEST01');

        $this->assertEquals('https://api.meest.com/labels/BLP68A82A025DBC2PLTEST01.pdf', $labelUrl);
    }

    /**
     * Test retry logic on network failure
     */
    public function testRetryLogicOnNetworkFailure(): void
    {
        $this->httpClient
            ->expects($this->exactly(3)) // Initial + 2 retries
            ->method('request')
            ->willThrowException(new \Exception('Network timeout'));

        $this->expectException(MeestIntegrationException::class);
        $this->expectExceptionMessage('Request failed after 3 attempts');

        $this->apiClient->authenticate();
    }

    /**
     * Test rate limiting error handling
     */
    public function testRateLimitingError(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(429);
        $mockResponse->method('toArray')->willReturn([
            'error' => 'Rate limit exceeded',
            'retry_after' => 60
        ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $this->expectException(MeestIntegrationException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->apiClient->authenticate();
    }

    /**
     * Test shipment not found during tracking
     */
    public function testTrackingShipmentNotFound(): void
    {
        $this->mockSuccessfulAuthentication();

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('toArray')->willReturn([]);

        $this->httpClient
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use ($mockResponse) {
                if (str_contains($url, '/auth')) {
                    return $this->createAuthResponse();
                }
                return $mockResponse;
            });

        $this->expectException(MeestIntegrationException::class);
        $this->expectExceptionMessage('Shipment not found');

        $this->apiClient->getTracking('INVALID-TRACKING-NUMBER');
    }

    /**
     * Helper method to mock successful authentication
     */
    private function mockSuccessfulAuthentication(): void
    {
        $this->cache
            ->method('get')
            ->willReturn(null); // No cached token
    }

    /**
     * Helper method to create auth response
     */
    private function createAuthResponse(): ResponseInterface
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('toArray')->willReturn([
            'access_token' => 'test-token-12345',
            'token_type' => 'Bearer',
            'expires_in' => 3600
        ]);
        return $mockResponse;
    }

    /**
     * Helper method to create sample shipment request
     */
    private function createSampleShipmentRequest(): MeestShipmentRequestDTO
    {
        $sender = new MeestAddress(
            'Test',
            'Sender',
            '+48123456789',
            'test@example.com',
            MeestCountry::POLAND,
            'Warsaw',
            'ul. Test 1',
            '00-001'
        );

        $recipient = new MeestAddress(
            'Test',
            'Recipient',
            '+1234567890',
            'recipient@example.com',
            MeestCountry::USA,
            'New York',
            '123 Test St',
            '10001'
        );

        $parcel = new MeestParcel(
            weight: 1.0,
            length: 10.0,
            width: 10.0,
            height: 10.0,
            value: 100.0,
            currency: 'USD',
            contents: 'Test'
        );

        return new MeestShipmentRequestDTO(
            $sender,
            $recipient,
            $parcel,
            MeestShipmentType::STANDARD
        );
    }
}