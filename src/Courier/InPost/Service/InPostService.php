<?php

declare(strict_types=1);

namespace App\Courier\InPost\Service;

use App\Domain\Courier\Service\AbstractCourierService;
use App\Domain\Courier\Contracts\CourierIntegrationInterface;
use App\Courier\InPost\Contracts\InPostServiceInterface;
use App\Courier\InPost\DTO\InPostShipmentRequestDTO;
use App\Courier\InPost\DTO\InPostShipmentResponseDTO;
use App\Courier\InPost\DTO\LockerDetailsDTO;
use App\Domain\Courier\DTO\ShipmentRequestDTO;
use App\Domain\Courier\DTO\ShipmentResponseDTO;
use App\Domain\Courier\DTO\TrackingDetailsDTO;
use App\Courier\InPost\Exception\InPostIntegrationException;
use App\Courier\InPost\Enum\InPostStatus;
use App\Courier\InPost\Enum\ParcelSize;
use App\Service\CourierSecretsService;
use App\Service\SecretsManagerService;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class InPostService extends AbstractCourierService implements CourierIntegrationInterface, InPostServiceInterface
{
    private const SANDBOX_BASE_URL = 'https://api-shipx-pl.easypack24.net/v1';
    private const PRODUCTION_BASE_URL = 'https://api-shipx-pl.easypack24.net/v1';
    
    private const GEOWIDGET_SANDBOX_URL = 'https://geowidget.easypack24.net';
    private const GEOWIDGET_PRODUCTION_URL = 'https://geowidget.easypack24.net';

    private string $baseUrl;
    private string $geowidgetUrl;
    private bool $isProduction;

    public function __construct(
        HttpClientInterface $httpClient,
        SecretsManagerService $secretManager,
        private readonly CourierSecretsService $courierSecretsService,
        LoggerInterface $logger,
        private readonly EventDispatcherInterface $eventDispatcher,
        string $environment = 'sandbox'
    ) {
        parent::__construct($httpClient, $secretManager, $logger);
        
        $this->isProduction = $environment === 'production';
        $this->baseUrl = $this->isProduction ? self::PRODUCTION_BASE_URL : self::SANDBOX_BASE_URL;
        $this->geowidgetUrl = $this->isProduction ? self::GEOWIDGET_PRODUCTION_URL : self::GEOWIDGET_SANDBOX_URL;
    }

    // Adapter method for CourierIntegrationInterface compatibility
    public function createShipment(ShipmentRequestDTO $shipmentRequest): ShipmentResponseDTO
    {
        if ($shipmentRequest instanceof InPostShipmentRequestDTO) {
            return $this->createInPostShipment($shipmentRequest);
        }

        // Convert generic shipment request to InPost-specific
        $inPostRequest = $this->convertToInPostRequest($shipmentRequest);
        $inPostResponse = $this->createInPostShipment($inPostRequest);
        
        // Convert back to generic response
        return $this->convertToGenericResponse($inPostResponse);
    }

    public function createInPostShipment(InPostShipmentRequestDTO $request): InPostShipmentResponseDTO
    {
        $this->validateShipmentRequest($request);

        $payload = $this->buildShipmentPayload($request);
        
        return $this->executeWithRetry(function () use ($payload, $request) {
            $response = $this->makeApiRequest('POST', '/shipments', $payload);
            $data = $response->toArray();
            
            $this->logCourierInteraction('createShipment', [
                'tracking_number' => $data['tracking_number'] ?? null,
                'status' => $data['status'] ?? null
            ]);

            return InPostShipmentResponseDTO::fromArray([
                'trackingNumber' => $data['tracking_number'] ?? '',
                'shipmentId' => (string) ($data['id'] ?? ''),
                'status' => $data['status'] ?? 'created',
                'labelUrl' => null, // Will be generated separately
                'estimatedDelivery' => null,
                'paczkomatCode' => $request->targetPaczkomat,
                'totalAmount' => $data['calculated_charge_amount'] ?? null,
                'codAmount' => $request->codAmount,
                'parcelDimensions' => [
                    'width' => $request->width,
                    'height' => $request->height,
                    'length' => $request->length,
                    'weight' => $request->weight,
                ],
            ]);
        });
    }

    public function getTrackingDetails(string $trackingNumber): TrackingDetailsDTO
    {
        return $this->executeWithRetry(function () use ($trackingNumber) {
            $response = $this->makeApiRequest('GET', "/shipments/{$trackingNumber}");
            $data = $response->toArray();

            $status = InPostStatus::tryFrom($data['status'] ?? '') ?? InPostStatus::ERROR;
            
            return TrackingDetailsDTO::fromArray([
                'trackingNumber' => $trackingNumber,
                'status' => $status->value,
                'statusDescription' => $status->getDisplayName(),
                'estimatedDelivery' => $data['estimated_delivery_date'] ?? null,
                'deliveredAt' => $data['delivered_at'] ?? null,
                'events' => $this->formatTrackingEvents($data['tracking'] ?? []),
                'currentLocation' => $this->getCurrentLocation($data),
                'recipientName' => $data['receiver']['name'] ?? null,
                'recipientPhone' => $data['receiver']['phone'] ?? null,
                'deliveryMethod' => $data['service'] ?? null,
                'lockerName' => $data['custom_attributes']['target_point'] ?? null,
                'lockerAddress' => null, // Would need separate API call
            ]);
        });
    }

    public function findNearbyPaczkomaty(
        float $latitude,
        float $longitude,
        int $radiusKm = 5,
        ?string $parcelSize = null
    ): array {
        return $this->executeWithRetry(function () use ($latitude, $longitude, $radiusKm, $parcelSize) {
            $params = [
                'relative_point' => "{$latitude},{$longitude}",
                'max_distance' => $radiusKm * 1000, // Convert to meters
                'max_results' => 50,
                'type' => 'paczkomat',
            ];

            if ($parcelSize) {
                $params['functions'] = 'parcel_collect_' . strtolower($parcelSize);
            }

            $response = $this->makeGeowidgetRequest('GET', '/v1/points', $params);
            $data = $response->toArray();

            $paczkomaty = [];
            foreach ($data['items'] ?? [] as $item) {
                $paczkomaty[] = LockerDetailsDTO::fromArray($item);
            }

            return $paczkomaty;
        });
    }

    public function getPaczkomatDetails(string $paczkomatCode): LockerDetailsDTO
    {
        return $this->executeWithRetry(function () use ($paczkomatCode) {
            $response = $this->makeGeowidgetRequest('GET', "/v1/points/{$paczkomatCode}");
            $data = $response->toArray();
            
            return LockerDetailsDTO::fromArray($data);
        });
    }

    public function validatePolishPostalCode(string $postalCode): bool
    {
        return (bool) preg_match('/^[0-9]{2}-[0-9]{3}$/', $postalCode);
    }

    public function validatePaczkomatCode(string $code): bool
    {
        return (bool) preg_match('/^[A-Z]{3}[0-9]{2,4}[A-Z]?$/', $code);
    }

    // Generic label generation for CourierIntegrationInterface
    public function generateLabel(string $trackingNumber): string
    {
        return $this->generateInPostLabel($trackingNumber, 'pdf');
    }

    // InPost-specific label generation with format option
    public function generateInPostLabel(string $trackingNumber, string $format = 'pdf'): string
    {
        if (!in_array($format, ['pdf', 'zpl'], true)) {
            throw new InPostIntegrationException("Unsupported label format: {$format}");
        }

        return $this->executeWithRetry(function () use ($trackingNumber, $format) {
            $response = $this->makeApiRequest('GET', "/shipments/{$trackingNumber}/label", [
                'format' => $format,
                'type' => 'normal',
            ]);

            // InPost API returns label content directly
            $labelContent = $response->getContent();
            
            $this->logCourierInteraction('generateLabel', [
                'tracking_number' => $trackingNumber,
                'format' => $format,
                'size' => strlen($labelContent),
            ]);

            return base64_encode($labelContent);
        });
    }

    public function cancelShipment(string $trackingNumber): bool
    {
        return $this->executeWithRetry(function () use ($trackingNumber) {
            // First check if shipment can be canceled
            $shipmentDetails = $this->getTrackingDetails($trackingNumber);
            $status = InPostStatus::tryFrom($shipmentDetails->status);
            
            if ($status && ($status->isDelivered() || $status->isFinalStatus())) {
                throw InPostIntegrationException::shipmentAlreadyDispatched($trackingNumber);
            }

            $response = $this->makeApiRequest('DELETE', "/shipments/{$trackingNumber}");
            
            $this->logCourierInteraction('cancelShipment', [
                'tracking_number' => $trackingNumber,
                'success' => true,
            ]);

            return $response->getStatusCode() === 200;
        });
    }

    public function getDeliveryStatus(string $trackingNumber): array
    {
        $trackingDetails = $this->getTrackingDetails($trackingNumber);
        $status = InPostStatus::tryFrom($trackingDetails->status);
        
        return [
            'status' => $trackingDetails->status,
            'status_description' => $trackingDetails->statusDescription,
            'is_delivered' => $status?->isDelivered() ?? false,
            'is_in_transit' => $status?->isInTransit() ?? false,
            'is_awaiting_pickup' => $status?->isAwaitingPickup() ?? false,
            'is_final' => $status?->isFinalStatus() ?? false,
            'delivered_at' => $trackingDetails->deliveredAt?->format('Y-m-d H:i:s'),
            'estimated_delivery' => $trackingDetails->estimatedDelivery?->format('Y-m-d H:i:s'),
        ];
    }

    public function createBulkShipments(array $requests): array
    {
        // InPost API doesn't support true bulk operations, so we'll process sequentially
        $results = [];
        $errors = [];

        foreach ($requests as $index => $request) {
            try {
                $results[] = $this->createShipment($request);
            } catch (InPostIntegrationException $e) {
                $errors[$index] = $e;
                // Continue processing other requests
            }
        }

        if (!empty($errors)) {
            $this->logger->warning('Bulk shipment creation had errors', [
                'total_requests' => count($requests),
                'successful' => count($results),
                'failed' => count($errors),
            ]);
        }

        return $results;
    }

    public function getShipmentCost(InPostShipmentRequestDTO $request): float
    {
        return $this->executeWithRetry(function () use ($request) {
            $payload = $this->buildShipmentPayload($request);
            
            $response = $this->makeApiRequest('POST', '/shipments/calculate', $payload);
            $data = $response->toArray();
            
            return (float) ($data['calculated_charge_amount'] ?? 0.0);
        });
    }

    // Implementation of parent abstract methods
    public function generateTrackingNumber(): string
    {
        // InPost generates tracking numbers, so this is not needed
        return '';
    }

    public function validateTrackingNumber(string $trackingNumber): bool
    {
        // InPost tracking numbers are typically 24 characters
        return strlen($trackingNumber) === 24 && ctype_alnum($trackingNumber);
    }

    public function processWebhook(array $payload): bool
    {
        // Enhanced webhook processing for status updates
        $trackingNumber = $payload['tracking_number'] ?? null;
        $status = $payload['status'] ?? null;
        $location = $payload['location'] ?? null;
        $timestamp = $payload['timestamp'] ?? null;

        if (!$trackingNumber || !$status) {
            $this->logger->error('Invalid InPost webhook payload', $payload);
            return false;
        }

        $this->logCourierInteraction('processWebhook', [
            'tracking_number' => $trackingNumber,
            'new_status' => $status,
            'location' => $location,
        ]);

        // Create tracking event for this status update
        $trackingEvent = [
            'tracking_number' => $trackingNumber,
            'status' => $status,
            'location' => $location,
            'timestamp' => $timestamp ?? date('Y-m-d H:i:s'),
            'payload' => $payload, // Store full payload for potential future reference
        ];

        try {
            // Dispatch event for further processing
            // Note: Event dispatching would require proper event class implementation
            // $this->eventDispatcher->dispatch(
            //     new ShipmentStatusUpdateEvent($trackingEvent),
            //     ShipmentStatusUpdateEvent::NAME
            // );

            // For now, just log the event
            $this->logger->info('Webhook processed successfully', $trackingEvent);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to process webhook', [
                'error' => $e->getMessage(),
                'tracking_number' => $trackingNumber,
            ]);
            return false;
        }
    }

    /**
     * Enhanced tracking event creation with more detailed information
     */
    private function createTrackingEventFromWebhook(array $payload): TrackingEventDTO
    {
        return TrackingEventDTO::fromArray([
            'tracking_number' => $payload['tracking_number'] ?? '',
            'status' => $payload['status'] ?? '',
            'description' => $this->getEventDescription($payload['status'] ?? ''),
            'location' => $payload['location'] ?? null,
            'timestamp' => new \DateTimeImmutable($payload['timestamp'] ?? 'now'),
            'additional_data' => json_encode($payload),
        ]);
    }

    /**
     * Translate raw status to human-readable description
     */
    private function getEventDescription(string $status): string
    {
        $descriptions = [
            'created' => 'Shipment created and awaiting processing',
            'in_transit' => 'Shipment is in transit',
            'delivered' => 'Shipment successfully delivered',
            'returned' => 'Shipment returned to sender',
            // Add more translations as needed
        ];

        return $descriptions[$status] ?? 'Unknown status';
    }

    private function validateShipmentRequest(InPostShipmentRequestDTO $request): void
    {
        // Validate postal codes
        if (!$this->validatePolishPostalCode($request->senderPostalCode)) {
            throw InPostIntegrationException::invalidPostalCode($request->senderPostalCode);
        }

        if (!$this->validatePolishPostalCode($request->recipientPostalCode)) {
            throw InPostIntegrationException::invalidPostalCode($request->recipientPostalCode);
        }

        // Validate phone number
        if (!preg_match('/^\+48[0-9]{9}$/', $request->recipientPhone)) {
            throw InPostIntegrationException::invalidPhoneNumber($request->recipientPhone);
        }

        // Validate Paczkomat code if provided
        if ($request->targetPaczkomat && !$this->validatePaczkomatCode($request->targetPaczkomat)) {
            throw InPostIntegrationException::invalidPaczkomat($request->targetPaczkomat);
        }

        // Validate parcel dimensions
        if ($request->width && $request->height && $request->length) {
            $parcelSize = ParcelSize::from($request->parcelSize);
            if (!$parcelSize->canFitDimensions($request->width, $request->height, $request->length)) {
                throw InPostIntegrationException::parcelTooLarge(
                    ['width' => $request->width, 'height' => $request->height, 'length' => $request->length],
                    $request->parcelSize
                );
            }
        }
    }

    private function buildShipmentPayload(InPostShipmentRequestDTO $request): array
    {
        $payload = [
            'receiver' => [
                'name' => $request->recipientName,
                'email' => $request->recipientEmail,
                'phone' => $request->recipientPhone,
                'address' => [
                    'line1' => $request->recipientAddress,
                    'post_code' => $request->recipientPostalCode,
                ],
            ],
            'sender' => [
                'name' => $request->senderName,
                'email' => $request->senderEmail,
                'phone' => $request->senderPhone,
                'address' => [
                    'line1' => $request->senderAddress,
                    'post_code' => $request->senderPostalCode,
                ],
            ],
            'parcels' => [
                [
                    'dimensions' => [
                        'length' => $request->length,
                        'width' => $request->width,
                        'height' => $request->height,
                    ],
                    'weight' => [
                        'amount' => $request->weight,
                        'unit' => 'kg',
                    ],
                ],
            ],
            'service' => $request->deliveryMethod,
            'custom_attributes' => [],
        ];

        // Add Paczkomat-specific data
        if ($request->isPackzomatDelivery() && $request->targetPaczkomat) {
            $payload['custom_attributes']['target_point'] = $request->targetPaczkomat;
        }

        // Add COD if specified
        if ($request->hasCashOnDelivery()) {
            $payload['cod'] = [
                'amount' => $request->codAmount,
                'currency' => $request->codCurrency,
            ];
        }

        // Add insurance if specified
        if ($request->hasInsurance()) {
            $payload['insurance'] = [
                'amount' => $request->insuranceAmount,
                'currency' => 'PLN',
            ];
        }

        // Add customer reference
        if ($request->customerReference) {
            $payload['reference'] = $request->customerReference;
        }

        return $payload;
    }

    private function makeApiRequest(string $method, string $endpoint, array $data = []): ResponseInterface
    {
        $apiKey = $this->courierSecretsService->getInpostApiKey($this->isProduction ? 'production' : 'sandbox');
        
        if (!$apiKey) {
            throw InPostIntegrationException::authenticationFailed();
        }

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
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

        $response = $this->httpClient->request($method, $this->baseUrl . $endpoint, $options);

        if ($response->getStatusCode() >= 400) {
            $this->handleApiError($response);
        }

        return $response;
    }

    private function makeGeowidgetRequest(string $method, string $endpoint, array $params = []): ResponseInterface
    {
        $options = [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];

        if (!empty($params)) {
            $options['query'] = $params;
        }

        $response = $this->httpClient->request($method, $this->geowidgetUrl . $endpoint, $options);

        if ($response->getStatusCode() >= 400) {
            throw new InPostIntegrationException(
                'Geowidget API request failed: ' . $response->getContent(false),
                null,
                [],
                $response->getStatusCode()
            );
        }

        return $response;
    }

    private function handleApiError(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);
        
        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            $message = $data['message'] ?? $data['error'] ?? 'Unknown API error';
            $errorCode = $data['error_code'] ?? null;
        } catch (\JsonException) {
            $message = 'API request failed with status ' . $statusCode;
            $errorCode = null;
        }

        // Handle specific error codes
        switch ($statusCode) {
            case 401:
                throw InPostIntegrationException::authenticationFailed();
            case 429:
                $retryAfter = (int) $response->getHeaders()['retry-after'][0] ?? 60;
                throw InPostIntegrationException::apiRateLimit($retryAfter);
            default:
                throw new InPostIntegrationException($message, $errorCode, [], $statusCode);
        }
    }

    private function formatTrackingEvents(array $events): array
    {
        $formatted = [];
        
        foreach ($events as $event) {
            $formatted[] = [
                'date' => $event['datetime'] ?? null,
                'status' => $event['status'] ?? '',
                'description' => $event['description'] ?? '',
                'location' => $event['location'] ?? null,
            ];
        }

        return $formatted;
    }

    private function getCurrentLocation(array $shipmentData): ?string
    {
        $tracking = $shipmentData['tracking'] ?? [];
        
        if (empty($tracking)) {
            return null;
        }

        // Get the latest tracking event
        $latestEvent = end($tracking);
        
        return $latestEvent['location'] ?? null;
    }

    private function convertToInPostRequest(ShipmentRequestDTO $request): InPostShipmentRequestDTO
    {
        // Convert generic request to InPost-specific DTO
        // This is a simplified conversion - you might need to extend based on actual requirements
        return InPostShipmentRequestDTO::fromArray([
            'senderName' => $request->senderName,
            'senderEmail' => $request->senderEmail,
            'senderPhone' => $request->senderPhone ?? '',
            'senderAddress' => $request->senderAddress,
            'senderPostalCode' => $request->senderPostalCode,
            'recipientName' => $request->recipientName,
            'recipientEmail' => $request->recipientEmail,
            'recipientPhone' => $request->recipientPhone,
            'recipientAddress' => $request->recipientAddress,
            'recipientPostalCode' => $request->recipientPostalCode,
            'weight' => $request->weight,
            'length' => $request->length ?? 0,
            'width' => $request->width ?? 0,
            'height' => $request->height ?? 0,
            'targetPaczkomat' => $request->deliveryMethod === 'paczkomat' ? ($request->paczkomatCode ?? '') : null,
            'deliveryMethod' => $request->deliveryMethod,
            'codAmount' => $request->codAmount,
            'customerReference' => $request->reference ?? null,
            'parcelSize' => 'small', // Default size - should be calculated based on dimensions
        ]);
    }

    private function convertToGenericResponse(InPostShipmentResponseDTO $inPostResponse): ShipmentResponseDTO
    {
        // Convert InPost response to generic courier response
        return ShipmentResponseDTO::fromArray([
            'trackingNumber' => $inPostResponse->trackingNumber,
            'shipmentId' => $inPostResponse->shipmentId,
            'status' => $inPostResponse->status,
            'labelUrl' => $inPostResponse->labelUrl,
            'totalCost' => $inPostResponse->totalAmount,
            'estimatedDelivery' => $inPostResponse->estimatedDelivery,
            'courierReference' => $inPostResponse->shipmentId,
        ]);
    }
}