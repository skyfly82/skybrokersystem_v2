<?php

declare(strict_types=1);

namespace App\Courier\DHL\Service;

use App\Domain\Courier\Service\AbstractCourierService;
use App\Domain\Courier\Contracts\CourierIntegrationInterface;
use App\Domain\Courier\DTO\ShipmentRequestDTO;
use App\Domain\Courier\DTO\ShipmentResponseDTO;
use App\Domain\Courier\DTO\TrackingDetailsDTO;
use App\Courier\DHL\Exception\DHLIntegrationException;
use App\Service\CourierSecretsService;
use App\Service\SecretsManagerService;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class DHLService extends AbstractCourierService implements CourierIntegrationInterface
{
    private const SANDBOX_BASE_URL = 'https://api-eu.dhl.com/track';
    private const PRODUCTION_BASE_URL = 'https://api-eu.dhl.com/track';

    private const SHIPPING_SANDBOX_URL = 'https://express-api.dhl.com/mydhlapi/test';
    private const SHIPPING_PRODUCTION_URL = 'https://express-api.dhl.com/mydhlapi';

    private string $baseUrl;
    private string $shippingUrl;
    private bool $isProduction;

    public function __construct(
        HttpClientInterface $httpClient,
        SecretsManagerService $secretManager,
        private readonly CourierSecretsService $courierSecretsService,
        LoggerInterface $logger,
        string $environment = 'sandbox'
    ) {
        parent::__construct($httpClient, $secretManager, $logger);

        $this->isProduction = $environment === 'production';
        $this->baseUrl = $this->isProduction ? self::PRODUCTION_BASE_URL : self::SANDBOX_BASE_URL;
        $this->shippingUrl = $this->isProduction ? self::SHIPPING_PRODUCTION_URL : self::SHIPPING_SANDBOX_URL;
    }

    public function createShipment(ShipmentRequestDTO $shipmentRequest): ShipmentResponseDTO
    {
        $this->validateShipmentRequest($shipmentRequest);

        $payload = $this->buildShipmentPayload($shipmentRequest);

        return $this->executeWithRetry(function () use ($payload) {
            $response = $this->makeShippingApiRequest('POST', '/shipments', $payload);
            $data = $response->toArray();

            $this->logCourierInteraction('createShipment', [
                'shipment_id' => $data['shipmentTrackingNumber'] ?? null,
                'status' => 'created'
            ]);

            return ShipmentResponseDTO::fromArray([
                'trackingNumber' => $data['shipmentTrackingNumber'] ?? '',
                'shipmentId' => $data['shipmentId'] ?? '',
                'status' => 'created',
                'labelUrl' => $data['documents'][0]['content'] ?? null,
                'totalCost' => $data['shipmentCharges'][0]['price'] ?? null,
                'estimatedDelivery' => $data['estimatedDeliveryDate'] ?? null,
                'courierReference' => $data['shipmentId'] ?? '',
            ]);
        });
    }

    public function getTrackingDetails(string $trackingNumber): TrackingDetailsDTO
    {
        return $this->executeWithRetry(function () use ($trackingNumber) {
            $response = $this->makeTrackingApiRequest('GET', "/shipments/{$trackingNumber}");
            $data = $response->toArray();

            $shipment = $data['shipments'][0] ?? null;
            if (!$shipment) {
                throw DHLIntegrationException::trackingNotFound($trackingNumber);
            }

            $events = [];
            foreach ($shipment['events'] ?? [] as $event) {
                $events[] = [
                    'date' => $event['timestamp'] ?? null,
                    'status' => $event['statusCode'] ?? '',
                    'description' => $event['description'] ?? '',
                    'location' => $event['location']['address']['addressLocality'] ?? null,
                ];
            }

            return TrackingDetailsDTO::fromArray([
                'trackingNumber' => $trackingNumber,
                'status' => $shipment['status']['statusCode'] ?? 'unknown',
                'statusDescription' => $shipment['status']['description'] ?? '',
                'estimatedDelivery' => $shipment['estimatedTimeOfDelivery'] ?? null,
                'deliveredAt' => $shipment['status']['statusCode'] === 'delivered' ?
                    ($shipment['status']['timestamp'] ?? null) : null,
                'events' => $events,
                'currentLocation' => $this->getCurrentLocation($shipment),
                'recipientName' => null,
                'recipientPhone' => null,
                'deliveryMethod' => $shipment['service'] ?? null,
            ]);
        });
    }

    public function generateLabel(string $trackingNumber): string
    {
        return $this->executeWithRetry(function () use ($trackingNumber) {
            $response = $this->makeShippingApiRequest('GET', "/shipments/{$trackingNumber}/proof-of-delivery");

            $this->logCourierInteraction('generateLabel', [
                'tracking_number' => $trackingNumber,
                'format' => 'pdf',
            ]);

            return base64_encode($response->getContent());
        });
    }

    public function generateTrackingNumber(): string
    {
        // DHL generates tracking numbers, so this is not needed
        return '';
    }

    public function validateTrackingNumber(string $trackingNumber): bool
    {
        // DHL tracking numbers are typically 10-11 digits
        return preg_match('/^[0-9]{10,11}$/', $trackingNumber) === 1;
    }

    public function processWebhook(array $payload): bool
    {
        $trackingNumber = $payload['trackingNumber'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$trackingNumber || !$status) {
            $this->logger->error('Invalid DHL webhook payload', $payload);
            return false;
        }

        $this->logCourierInteraction('processWebhook', [
            'tracking_number' => $trackingNumber,
            'new_status' => $status,
        ]);

        return true;
    }

    private function validateShipmentRequest(ShipmentRequestDTO $request): void
    {
        if (empty($request->senderAddress) || empty($request->recipientAddress)) {
            throw DHLIntegrationException::invalidAddress('Address fields cannot be empty');
        }

        if ($request->weight <= 0 || $request->weight > 70) {
            throw DHLIntegrationException::invalidWeight($request->weight);
        }
    }

    private function buildShipmentPayload(ShipmentRequestDTO $request): array
    {
        return [
            'plannedShippingDateAndTime' => date('Y-m-d\TH:i:s \G\M\T\TP'),
            'pickup' => [
                'isRequested' => false,
            ],
            'productCode' => 'N',
            'accounts' => [
                [
                    'typeCode' => 'shipper',
                    'number' => $this->courierSecretsService->getDhlAccountNumber(),
                ]
            ],
            'customerDetails' => [
                'shipperDetails' => [
                    'postalAddress' => [
                        'postalCode' => $request->senderPostalCode,
                        'cityName' => $request->senderCity ?? '',
                        'countryCode' => $request->senderCountryCode ?? 'PL',
                        'addressLine1' => $request->senderAddress,
                    ],
                    'contactInformation' => [
                        'email' => $request->senderEmail,
                        'phone' => $request->senderPhone ?? '',
                        'companyName' => $request->senderName,
                        'fullName' => $request->senderName,
                    ],
                ],
                'receiverDetails' => [
                    'postalAddress' => [
                        'postalCode' => $request->recipientPostalCode,
                        'cityName' => $request->recipientCity ?? '',
                        'countryCode' => $request->recipientCountryCode ?? 'PL',
                        'addressLine1' => $request->recipientAddress,
                    ],
                    'contactInformation' => [
                        'email' => $request->recipientEmail,
                        'phone' => $request->recipientPhone,
                        'companyName' => $request->recipientName,
                        'fullName' => $request->recipientName,
                    ],
                ],
            ],
            'content' => [
                'packages' => [
                    [
                        'typeCode' => '2BP',
                        'weight' => $request->weight,
                        'dimensions' => [
                            'length' => $request->length ?? 10,
                            'width' => $request->width ?? 10,
                            'height' => $request->height ?? 10,
                        ],
                    ],
                ],
                'isCustomsDeclarable' => false,
                'description' => 'Package',
            ],
        ];
    }

    private function makeShippingApiRequest(string $method, string $endpoint, array $data = []): ResponseInterface
    {
        $apiKey = $this->courierSecretsService->getDhlApiKey($this->isProduction ? 'production' : 'sandbox');

        if (!$apiKey) {
            throw DHLIntegrationException::authenticationFailed();
        }

        $options = [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($apiKey),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        if (!empty($data)) {
            if ($method === 'GET') {
                $options['query'] = $data;
            } else {
                $options['json'] = $data;
            }
        }

        $response = $this->httpClient->request($method, $this->shippingUrl . $endpoint, $options);

        if ($response->getStatusCode() >= 400) {
            $this->handleApiError($response);
        }

        return $response;
    }

    private function makeTrackingApiRequest(string $method, string $endpoint, array $params = []): ResponseInterface
    {
        $apiKey = $this->courierSecretsService->getDhlTrackingApiKey($this->isProduction ? 'production' : 'sandbox');

        if (!$apiKey) {
            throw DHLIntegrationException::authenticationFailed();
        }

        $options = [
            'headers' => [
                'DHL-API-Key' => $apiKey,
                'Accept' => 'application/json',
            ],
        ];

        if (!empty($params)) {
            $options['query'] = $params;
        }

        $response = $this->httpClient->request($method, $this->baseUrl . $endpoint, $options);

        if ($response->getStatusCode() >= 400) {
            $this->handleApiError($response);
        }

        return $response;
    }

    private function handleApiError(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            $message = $data['detail'] ?? $data['message'] ?? 'Unknown API error';
        } catch (\JsonException) {
            $message = 'API request failed with status ' . $statusCode;
        }

        switch ($statusCode) {
            case 401:
                throw DHLIntegrationException::authenticationFailed();
            case 429:
                throw DHLIntegrationException::apiRateLimit();
            default:
                throw new DHLIntegrationException($message, [], $statusCode);
        }
    }

    private function getCurrentLocation(array $shipmentData): ?string
    {
        $events = $shipmentData['events'] ?? [];

        if (empty($events)) {
            return null;
        }

        $latestEvent = end($events);

        return $latestEvent['location']['address']['addressLocality'] ?? null;
    }
}