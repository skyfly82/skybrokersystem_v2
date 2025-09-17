<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Service;

use App\Domain\Courier\Meest\DTO\MeestShipmentRequestDTO;
use App\Domain\Courier\Meest\DTO\MeestShipmentResponseDTO;
use App\Domain\Courier\Meest\Entity\MeestShipment;
use App\Domain\Courier\Meest\Enum\MeestShipmentType;
use App\Domain\Courier\Meest\Exception\MeestIntegrationException;
use App\Domain\Courier\Meest\Exception\MeestValidationException;
use App\Domain\Courier\Meest\Repository\MeestShipmentRepository;
use App\Domain\Courier\Meest\ValueObject\MeestAddress;
use App\Domain\Courier\Meest\ValueObject\MeestParcel;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * High-level business service for MEEST shipment operations
 */
class MeestShipmentService
{
    private const SUPPORTED_COUNTRIES = ['DE', 'CZ', 'SK', 'HU', 'RO', 'LT', 'LV', 'EE', 'UA', 'BG', 'PL'];
    private const CURRENCY_MAPPING = [
        'DE' => 'EUR',
        'CZ' => 'CZK',
        'SK' => 'EUR',
        'HU' => 'HUF',
        'RO' => 'RON',
        'LT' => 'EUR',
        'LV' => 'EUR',
        'EE' => 'EUR',
        'UA' => 'UAH',
        'BG' => 'BGN',
        'PL' => 'PLN'
    ];

    public function __construct(
        private readonly MeestApiClient $apiClient,
        private readonly MeestShipmentRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
        private readonly MeestTrackingNumberGenerator $trackingNumberGenerator,
        private readonly MeestLabelService $labelService,
        private readonly MeestBusinessValidator $businessValidator
    ) {}

    /**
     * Create a new shipment with validation and retry logic
     */
    public function createShipment(array $shipmentData): MeestShipment
    {
        $this->logger->info('Creating MEEST shipment', ['data' => $shipmentData]);

        try {
            // Step 1: Validate business rules
            $this->businessValidator->validateShipmentData($shipmentData);

            // Step 2: Create and validate DTO
            $requestDTO = $this->createShipmentRequestDTO($shipmentData);
            $this->validateDTO($requestDTO);

            // Step 3: Generate unique tracking number
            $trackingNumber = $this->trackingNumberGenerator->generate();
            $attempts = 0;
            $maxAttempts = 5;

            while ($attempts < $maxAttempts) {
                try {
                    // Step 4: Call API to create shipment
                    $responseDTO = $this->apiClient->createShipment($requestDTO);

                    // Step 5: Create entity and persist
                    $shipment = $this->createShipmentEntity($requestDTO, $responseDTO, $trackingNumber);
                    $this->repository->save($shipment);

                    // Step 6: Generate and store label asynchronously
                    $this->labelService->generateAndStoreLabel($shipment);

                    $this->logger->info('MEEST shipment created successfully', [
                        'tracking_number' => $shipment->getTrackingNumber(),
                        'shipment_id' => $shipment->getShipmentId()
                    ]);

                    return $shipment;

                } catch (MeestIntegrationException $e) {
                    if ($this->isRetryableError($e)) {
                        $attempts++;
                        $trackingNumber = $this->trackingNumberGenerator->generate();

                        $this->logger->warning('Retrying shipment creation', [
                            'attempt' => $attempts,
                            'error' => $e->getMessage(),
                            'new_tracking_number' => $trackingNumber
                        ]);

                        continue;
                    }

                    throw $e;
                }
            }

            throw new MeestIntegrationException(
                "Failed to create shipment after {$maxAttempts} attempts"
            );

        } catch (\Exception $e) {
            $this->logger->error('Failed to create MEEST shipment', [
                'error' => $e->getMessage(),
                'data' => $shipmentData
            ]);

            if ($e instanceof MeestValidationException || $e instanceof MeestIntegrationException) {
                throw $e;
            }

            throw new MeestIntegrationException('Unexpected error during shipment creation: ' . $e->getMessage());
        }
    }

    /**
     * Create return shipment
     */
    public function createReturnShipment(string $originalTrackingNumber, array $returnData): MeestShipment
    {
        $this->logger->info('Creating MEEST return shipment', [
            'original_tracking_number' => $originalTrackingNumber,
            'return_data' => $returnData
        ]);

        $originalShipment = $this->repository->findByTrackingNumber($originalTrackingNumber);
        if (!$originalShipment) {
            throw MeestValidationException::invalidValue('original_tracking_number', $originalTrackingNumber);
        }

        // Swap sender and recipient for return shipment
        $returnShipmentData = $this->prepareReturnShipmentData($originalShipment, $returnData);
        $returnShipmentData['shipment_type'] = MeestShipmentType::RETURN->value;
        $returnShipmentData['original_tracking_number'] = $originalTrackingNumber;

        return $this->createShipment($returnShipmentData);
    }


    /**
     * Create DTO from validated data
     */
    private function createShipmentRequestDTO(array $data): MeestShipmentRequestDTO
    {
        try {
            // Create sender address
            $sender = new MeestAddress(
                firstName: $data['sender']['first_name'],
                lastName: $data['sender']['last_name'],
                phone: $data['sender']['phone'],
                email: $data['sender']['email'],
                country: strtoupper($data['sender']['country']),
                city: $data['sender']['city'],
                address: $data['sender']['address'],
                postalCode: $data['sender']['postal_code'],
                company: $data['sender']['company'] ?? null
            );

            // Create recipient address with optional region1
            $recipient = new MeestAddress(
                firstName: $data['recipient']['first_name'],
                lastName: $data['recipient']['last_name'],
                phone: $data['recipient']['phone'],
                email: $data['recipient']['email'],
                country: strtoupper($data['recipient']['country']),
                city: $data['recipient']['city'],
                address: $data['recipient']['address'],
                postalCode: $data['recipient']['postal_code'],
                company: $data['recipient']['company'] ?? null
            );

            // Create parcel with items validation
            $parcel = new MeestParcel(
                weight: (float) $data['parcel']['weight'],
                length: (float) $data['parcel']['length'],
                width: (float) $data['parcel']['width'],
                height: (float) $data['parcel']['height'],
                value: (float) $data['parcel']['value']['localTotalValue'],
                currency: strtoupper($data['parcel']['value']['localCurrency']),
                contents: $data['parcel']['contents'],
                description: $data['parcel']['description'] ?? null
            );

            $shipmentType = MeestShipmentType::from($data['shipment_type'] ?? 'standard');

            return new MeestShipmentRequestDTO(
                sender: $sender,
                recipient: $recipient,
                parcel: $parcel,
                shipmentType: $shipmentType,
                specialInstructions: $data['special_instructions'] ?? null,
                reference: $data['reference'] ?? null,
                requireSignature: $data['require_signature'] ?? false,
                saturdayDelivery: $data['saturday_delivery'] ?? false,
                deliveryDate: $data['delivery_date'] ?? null
            );

        } catch (\Exception $e) {
            throw new MeestValidationException('Failed to create shipment request: ' . $e->getMessage());
        }
    }

    /**
     * Validate DTO using Symfony validator
     */
    private function validateDTO(MeestShipmentRequestDTO $dto): void
    {
        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            throw MeestValidationException::withErrors($errors);
        }
    }

    /**
     * Create shipment entity from DTO and response
     */
    private function createShipmentEntity(
        MeestShipmentRequestDTO $requestDTO,
        MeestShipmentResponseDTO $responseDTO,
        string $trackingNumber
    ): MeestShipment {
        $shipment = new MeestShipment(
            trackingNumber: $trackingNumber,
            shipmentId: $responseDTO->shipmentId,
            shipmentType: $requestDTO->shipmentType,
            senderData: $requestDTO->sender->toArray(),
            recipientData: $requestDTO->recipient->toArray(),
            parcelData: $requestDTO->parcel->toArray()
        );

        if ($responseDTO->totalCost) {
            $shipment->setCost($responseDTO->totalCost, $responseDTO->currency ?? $requestDTO->parcel->currency);
        }

        if ($responseDTO->estimatedDelivery) {
            $shipment->setEstimatedDelivery($responseDTO->estimatedDelivery);
        }

        if ($requestDTO->specialInstructions) {
            $shipment->setSpecialInstructions($requestDTO->specialInstructions);
        }

        if ($requestDTO->reference) {
            $shipment->setReference($requestDTO->reference);
        }

        // Store additional metadata
        $metadata = [
            'api_response' => $responseDTO->toArray(),
            'require_signature' => $requestDTO->requireSignature,
            'saturday_delivery' => $requestDTO->saturdayDelivery,
            'delivery_date' => $requestDTO->deliveryDate,
            'created_via' => 'api'
        ];
        $shipment->setMetadata($metadata);

        return $shipment;
    }

    /**
     * Prepare return shipment data
     */
    private function prepareReturnShipmentData(MeestShipment $originalShipment, array $returnData): array
    {
        $originalSender = $originalShipment->getSenderData();
        $originalRecipient = $originalShipment->getRecipientData();
        $originalParcel = $originalShipment->getParcelData();

        return [
            'sender' => $originalRecipient, // Swap: original recipient becomes sender
            'recipient' => $originalSender, // Swap: original sender becomes recipient
            'parcel' => array_merge($originalParcel, [
                'contents' => $returnData['contents'] ?? 'Return shipment',
                'description' => $returnData['description'] ?? 'Return of original shipment',
                'value' => $returnData['value'] ?? $originalParcel['value'],
            ]),
            'special_instructions' => $returnData['special_instructions'] ?? 'Return shipment',
            'reference' => $returnData['reference'] ?? 'RET-' . $originalShipment->getTrackingNumber()
        ];
    }

    /**
     * Check if error is retryable
     */
    private function isRetryableError(MeestIntegrationException $e): bool
    {
        $retryableMessages = [
            'Non-unique parcel number',
            'Duplicate tracking number',
            'Tracking number already exists'
        ];

        $message = $e->getMessage();
        foreach ($retryableMessages as $retryableMessage) {
            if (stripos($message, $retryableMessage) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if nested value exists in array
     */
    private function hasNestedValue(array $data, string $path): bool
    {
        $keys = explode('.', $path);
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return false;
            }
            $current = $current[$key];
        }

        return !empty($current);
    }
}