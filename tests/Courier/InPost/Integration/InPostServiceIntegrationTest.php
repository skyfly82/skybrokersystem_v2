<?php

declare(strict_types=1);

namespace App\Tests\Courier\InPost\Integration;

use App\Courier\InPost\DTO\InPostShipmentRequestDTO;
use App\Courier\InPost\Service\InPostService;
use App\Domain\Pricing\DTO\ShipmentDTO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Psr\Log\LoggerInterface;
use App\Service\SecretsManagerService;
use App\Service\CourierSecretsService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class InPostServiceIntegrationTest extends TestCase
{
    private InPostService $inPostService;
    private MockHttpClient $mockHttpClient;

    protected function setUp(): void
    {
        $this->mockHttpClient = new MockHttpClient();
        
        // Set up mock dependencies
        $mockCourierSecretsService = $this->createMock(CourierSecretsService::class);
        $mockCourierSecretsService->method('getInpostApiKey')
            ->willReturn('mock-api-key');

        $this->inPostService = new InPostService(
            $this->mockHttpClient,
            $this->createMock(SecretsManagerService::class),
            $mockCourierSecretsService,
            $this->createMock(LoggerInterface::class),
            $this->createMock(EventDispatcherInterface::class)
        );
    }

    public function testCreateShipmentSuccessfully()
    {
        // Arrange
        $mockResponse = new MockResponse(json_encode([
            'tracking_number' => 'TEST1234567890',
            'id' => '12345',
            'status' => 'created',
            'calculated_charge_amount' => 25.50
        ]));

        $this->mockHttpClient->setResponseFactory($mockResponse);

        $shipmentRequest = InPostShipmentRequestDTO::fromArray([
            'senderName' => 'Test Sender',
            'senderEmail' => 'sender@example.com',
            'senderPhone' => '+48500500500',
            'senderAddress' => 'Nadawcza 1',
            'senderPostalCode' => '00-001',
            'recipientName' => 'Test Recipient',
            'recipientEmail' => 'recipient@example.com',
            'recipientPhone' => '+48600600600',
            'recipientAddress' => 'Odbiorcza 2',
            'recipientPostalCode' => '00-002',
            'weight' => 0.5,
            'length' => 30,
            'width' => 7,
            'height' => 20,
            'targetPaczkomat' => 'WAW01A',
            'deliveryMethod' => 'paczkomat',
            'parcelSize' => 'small'
        ]);

        // Act
        $response = $this->inPostService->createInPostShipment($shipmentRequest);

        // Assert
        $this->assertEquals('TEST1234567890', $response->trackingNumber);
        $this->assertEquals('12345', $response->shipmentId);
        $this->assertEquals(25.50, $response->totalAmount);
    }

    public function testTrackingDetailsRetrieval()
    {
        // Arrange
        $mockResponse = new MockResponse(json_encode([
            'status' => 'in_transit',
            'tracking' => [
                [
                    'datetime' => '2023-09-16T10:00:00Z',
                    'status' => 'in_transit',
                    'description' => 'Shipment in transit',
                    'location' => 'Warsaw Sorting Center'
                ]
            ],
            'receiver' => [
                'name' => 'John Doe',
                'phone' => '+48600600600'
            ]
        ]));

        $this->mockHttpClient->setResponseFactory($mockResponse);

        // Act
        $trackingDetails = $this->inPostService->getTrackingDetails('TEST1234567890');

        // Assert
        $this->assertEquals('in_transit', $trackingDetails->status);
        $this->assertEquals('Warsaw Sorting Center', $trackingDetails->currentLocation);
    }

    public function testWebhookProcessing()
    {
        // Arrange
        $webhookPayload = [
            'tracking_number' => 'TEST1234567890',
            'status' => 'delivered',
            'location' => 'Recipient Address',
            'timestamp' => '2023-09-16T14:30:00Z'
        ];

        // Act
        $result = $this->inPostService->processWebhook($webhookPayload);

        // Assert
        $this->assertTrue($result);
    }

    public function testNearbyPaczkomatFinder()
    {
        // Arrange
        $mockResponse = new MockResponse(json_encode([
            'items' => [
                [
                    'code' => 'WAW01A',
                    'name' => 'Paczkomat Centrum',
                    'latitude' => 52.2297,
                    'longitude' => 21.0122
                ]
            ]
        ]));

        $this->mockHttpClient->setResponseFactory($mockResponse);

        // Act
        $paczkomaty = $this->inPostService->findNearbyPaczkomaty(52.2297, 21.0122);

        // Assert
        $this->assertCount(1, $paczkomaty);
        $this->assertEquals('WAW01A', $paczkomaty[0]->code);
    }
}