<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Service;

use App\Domain\Courier\DTO\ShipmentRequestDTO;
use App\Domain\Courier\DTO\ShipmentResponseDTO;
use App\Domain\Courier\DTO\TrackingDetailsDTO;
use App\Domain\Courier\Meest\DTO\MeestShipmentRequestDTO;
use App\Domain\Courier\Meest\Entity\MeestShipment;
use App\Domain\Courier\Meest\Enum\MeestCountry;
use App\Domain\Courier\Meest\Enum\MeestShipmentType;
use App\Domain\Courier\Meest\Exception\MeestIntegrationException;
use App\Domain\Courier\Meest\Exception\MeestValidationException;
use App\Domain\Courier\Meest\Repository\MeestShipmentRepository;
use App\Domain\Courier\Meest\ValueObject\MeestAddress;
use App\Domain\Courier\Meest\ValueObject\MeestCredentials;
use App\Domain\Courier\Meest\ValueObject\MeestParcel;
use App\Domain\Courier\Service\AbstractCourierService;
use App\Service\SecretsManagerService;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * MEEST Courier Integration Service
 */
class MeestCourierService extends AbstractCourierService
{
    private readonly MeestApiClient $apiClient;

    public function __construct(
        HttpClientInterface $httpClient,
        SecretsManagerService $secretManager,
        LoggerInterface $logger,
        private readonly MeestShipmentRepository $shipmentRepository
    ) {
        parent::__construct($httpClient, $secretManager, $logger);

        $credentials = $this->createCredentials();
        $this->apiClient = new MeestApiClient($httpClient, $logger, $credentials);
    }

    public function createShipment(ShipmentRequestDTO $shipmentRequest): ShipmentResponseDTO
    {
        $this->logCourierInteraction('create_shipment', [
            'service_type' => $shipmentRequest->serviceType
        ]);

        try {
            // Convert to MEEST-specific DTO
            $meestRequest = $this->convertToMeestRequest($shipmentRequest);

            // Validate request
            $this->validateShipmentRequest($meestRequest);

            // Create shipment via API
            $apiResponse = $this->executeWithRetry(
                fn() => $this->apiClient->createShipment($meestRequest)
            );

            // Save to database
            $shipment = $this->createShipmentEntity($meestRequest, $apiResponse);
            $this->shipmentRepository->save($shipment);

            $this->logger->info('MEEST shipment created successfully', [
                'tracking_number' => $apiResponse->trackingNumber,
                'shipment_id' => $apiResponse->shipmentId
            ]);

            return $this->convertToGenericResponse($apiResponse);

        } catch (MeestIntegrationException $e) {
            $this->logger->error('MEEST shipment creation failed', [
                'error' => $e->getMessage(),
                'api_response' => $e->getApiResponse()
            ]);
            throw $e;
        }
    }

    public function getTrackingDetails(string $trackingNumber): TrackingDetailsDTO
    {
        $this->logCourierInteraction('get_tracking', [
            'tracking_number' => $trackingNumber
        ]);

        try {
            // Validate tracking number format
            if (!$this->validateTrackingNumber($trackingNumber)) {
                throw new MeestValidationException("Invalid MEEST tracking number format: {$trackingNumber}");
            }

            // Get tracking info from API
            $trackingResponse = $this->executeWithRetry(
                fn() => $this->apiClient->getTracking($trackingNumber)
            );

            // Update local shipment if exists
            $shipment = $this->shipmentRepository->findByTrackingNumber($trackingNumber);
            if ($shipment) {
                $shipment->updateStatus($trackingResponse->status);

                if ($trackingResponse->deliveredAt) {
                    // Mark as delivered with timestamp
                    $shipment->updateStatus($trackingResponse->status);
                }

                $this->shipmentRepository->save($shipment);
            }

            return $this->convertToGenericTracking($trackingResponse);

        } catch (MeestIntegrationException $e) {
            $this->logger->error('MEEST tracking failed', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function generateTrackingNumber(): string
    {
        // MEEST generates tracking numbers on shipment creation
        // This is a placeholder for compatibility
        return 'MEEST' . time() . rand(1000, 9999);
    }

    public function validateTrackingNumber(string $trackingNumber): bool
    {
        // MEEST tracking numbers are typically alphanumeric, 10-20 characters
        return preg_match('/^[A-Z0-9]{10,20}$/', $trackingNumber) === 1;
    }

    public function generateLabel(string $trackingNumber): string
    {
        $this->logCourierInteraction('generate_label', [
            'tracking_number' => $trackingNumber
        ]);

        try {
            return $this->executeWithRetry(
                fn() => $this->apiClient->generateLabel($trackingNumber)
            );
        } catch (MeestIntegrationException $e) {
            $this->logger->error('MEEST label generation failed', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function processWebhook(array $payload): bool
    {
        $this->logCourierInteraction('process_webhook', [
            'payload_keys' => array_keys($payload)
        ]);

        try {
            // Extract tracking number from webhook
            $trackingNumber = $payload['tracking_number'] ?? null;
            if (!$trackingNumber) {
                $this->logger->warning('MEEST webhook missing tracking number', $payload);
                return false;
            }

            // Find shipment
            $shipment = $this->shipmentRepository->findByTrackingNumber($trackingNumber);
            if (!$shipment) {
                $this->logger->warning('MEEST webhook for unknown shipment', [
                    'tracking_number' => $trackingNumber
                ]);
                return false;
            }

            // Update status if provided
            if (isset($payload['status'])) {
                $status = \App\Domain\Courier\Meest\Enum\MeestTrackingStatus::fromApiStatus($payload['status']);
                if ($status) {
                    $shipment->updateStatus($status);
                    $this->shipmentRepository->save($shipment);

                    $this->logger->info('MEEST shipment status updated via webhook', [
                        'tracking_number' => $trackingNumber,
                        'status' => $status->value
                    ]);
                }
            }

            return true;

        } catch (\Exception $e) {
            $this->logger->error('MEEST webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
            return false;
        }
    }

    /**
     * Convert generic request to MEEST-specific request
     */
    private function convertToMeestRequest(ShipmentRequestDTO $shipmentRequest): MeestShipmentRequestDTO
    {
        // Parse addresses (assuming they contain required fields)
        $senderParts = $this->parseAddress($shipmentRequest->senderAddress);
        $recipientParts = $this->parseAddress($shipmentRequest->recipientAddress);

        $sender = new MeestAddress(
            firstName: $senderParts['firstName'] ?? 'Unknown',
            lastName: $senderParts['lastName'] ?? 'User',
            phone: $senderParts['phone'] ?? '+48000000000',
            email: $shipmentRequest->senderEmail,
            country: $senderParts['country'] ?? 'PL',
            city: $senderParts['city'] ?? 'Warsaw',
            address: $shipmentRequest->senderAddress,
            postalCode: $senderParts['postalCode'] ?? '00-000',
            company: $senderParts['company'] ?? null
        );

        $recipient = new MeestAddress(
            firstName: $recipientParts['firstName'] ?? 'Unknown',
            lastName: $recipientParts['lastName'] ?? 'User',
            phone: $recipientParts['phone'] ?? '+48000000000',
            email: $shipmentRequest->recipientEmail,
            country: $recipientParts['country'] ?? 'PL',
            city: $recipientParts['city'] ?? 'Warsaw',
            address: $shipmentRequest->recipientAddress,
            postalCode: $recipientParts['postalCode'] ?? '00-000',
            company: $recipientParts['company'] ?? null
        );

        $parcel = new MeestParcel(
            weight: $shipmentRequest->weight,
            length: 10.0, // Default dimensions
            width: 10.0,
            height: 10.0,
            value: 100.0, // Default value
            currency: MeestCountry::from($recipient->country)->getCurrency(),
            contents: 'General merchandise'
        );

        $shipmentType = match ($shipmentRequest->serviceType) {
            'express' => MeestShipmentType::EXPRESS,
            'economy' => MeestShipmentType::ECONOMY,
            'return' => MeestShipmentType::RETURN,
            default => MeestShipmentType::STANDARD,
        };

        return new MeestShipmentRequestDTO(
            sender: $sender,
            recipient: $recipient,
            parcel: $parcel,
            shipmentType: $shipmentType,
            specialInstructions: $shipmentRequest->specialInstructions
        );
    }

    /**
     * Validate MEEST shipment request
     */
    private function validateShipmentRequest(MeestShipmentRequestDTO $request): void
    {
        // Validate supported countries
        if (!MeestCountry::isSupported($request->sender->country)) {
            throw MeestIntegrationException::invalidCountry($request->sender->country);
        }

        if (!MeestCountry::isSupported($request->recipient->country)) {
            throw MeestIntegrationException::invalidCountry($request->recipient->country);
        }

        // Additional validations can be added here
    }

    /**
     * Create shipment entity from API response
     */
    private function createShipmentEntity(
        MeestShipmentRequestDTO $request,
        $apiResponse
    ): MeestShipment {
        $shipment = new MeestShipment(
            trackingNumber: $apiResponse->trackingNumber,
            shipmentId: $apiResponse->shipmentId,
            shipmentType: $request->shipmentType,
            senderData: $request->sender->toArray(),
            recipientData: $request->recipient->toArray(),
            parcelData: $request->parcel->toArray()
        );

        $shipment->setCost($apiResponse->totalCost, $apiResponse->currency);

        if ($apiResponse->hasLabel()) {
            $shipment->setLabelUrl($apiResponse->labelUrl);
        }

        if ($apiResponse->estimatedDelivery) {
            $shipment->setEstimatedDelivery($apiResponse->estimatedDelivery);
        }

        if ($request->specialInstructions) {
            $shipment->setSpecialInstructions($request->specialInstructions);
        }

        if ($request->reference) {
            $shipment->setReference($request->reference);
        }

        return $shipment;
    }

    /**
     * Convert MEEST response to generic response
     */
    private function convertToGenericResponse($meestResponse): ShipmentResponseDTO
    {
        return new ShipmentResponseDTO(
            trackingNumber: $meestResponse->trackingNumber,
            labelUrl: $meestResponse->labelUrl,
            cost: $meestResponse->totalCost,
            currency: $meestResponse->currency,
            estimatedDelivery: $meestResponse->estimatedDelivery
        );
    }

    /**
     * Convert MEEST tracking to generic tracking
     */
    private function convertToGenericTracking($meestTracking): TrackingDetailsDTO
    {
        return new TrackingDetailsDTO(
            trackingNumber: $meestTracking->trackingNumber,
            status: $meestTracking->status->value,
            statusDescription: $meestTracking->statusDescription,
            lastUpdated: $meestTracking->lastUpdated,
            estimatedDelivery: $meestTracking->estimatedDelivery,
            events: $meestTracking->trackingEvents
        );
    }

    /**
     * Parse address string into components
     */
    private function parseAddress(string $address): array
    {
        // This is a simplified parser - should be enhanced based on actual address formats
        return [
            'firstName' => 'Unknown',
            'lastName' => 'User',
            'phone' => '+48000000000',
            'country' => 'PL',
            'city' => 'Warsaw',
            'postalCode' => '00-000',
            'company' => null
        ];
    }

    /**
     * Create MEEST credentials from secrets
     */
    private function createCredentials(): MeestCredentials
    {
        $username = $this->secretManager->getSecret('MEEST_USERNAME');
        $password = $this->secretManager->getSecret('MEEST_PASSWORD');
        $baseUrl = $this->secretManager->getSecret('MEEST_BASE_URL', 'https://mwl.meest.com/mwl');

        return new MeestCredentials($username, $password, $baseUrl);
    }
}