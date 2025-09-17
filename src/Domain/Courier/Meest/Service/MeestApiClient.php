<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Service;

use App\Domain\Courier\Meest\DTO\MeestAuthResponseDTO;
use App\Domain\Courier\Meest\DTO\MeestShipmentRequestDTO;
use App\Domain\Courier\Meest\DTO\MeestShipmentResponseDTO;
use App\Domain\Courier\Meest\DTO\MeestTrackingResponseDTO;
use App\Domain\Courier\Meest\Exception\MeestIntegrationException;
use App\Domain\Courier\Meest\ValueObject\MeestCredentials;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * MEEST API Client
 */
class MeestApiClient
{
    private const TIMEOUT = 30;
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY = 1000; // milliseconds
    private const AUTH_CACHE_KEY = 'meest_auth_token';
    private const AUTH_CACHE_LIFETIME = 3600; // 1 hour

    private ?MeestAuthResponseDTO $currentAuth = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly MeestCredentials $credentials,
        private readonly ?CacheInterface $cache = null
    ) {
        // Try to load cached token on initialization
        if ($this->cache) {
            $cachedToken = $this->cache->get(self::AUTH_CACHE_KEY, function() { return null; });
            if ($cachedToken instanceof MeestAuthResponseDTO && !$cachedToken->isExpiringSoon()) {
                $this->currentAuth = $cachedToken;
            }
        }
    }

    /**
     * Authenticate with MEEST API
     */
    public function authenticate(): MeestAuthResponseDTO
    {
        $this->logger->info('Authenticating with MEEST API');

        try {
            $response = $this->executeRequest('POST', '/v2/api/auth', [
                'json' => $this->credentials->getAuthPayload(),
                'timeout' => self::TIMEOUT
            ]);

            $data = $response->toArray();

            if (!isset($data['access_token'])) {
                throw MeestIntegrationException::authenticationFailed('No access token in response');
            }

            $this->currentAuth = MeestAuthResponseDTO::fromApiResponse($data);
            $this->logger->info('MEEST authentication successful');

            // Cache the token if cache is available
            if ($this->cache) {
                $this->cache->set(
                    self::AUTH_CACHE_KEY,
                    $this->currentAuth,
                    self::AUTH_CACHE_LIFETIME
                );
            }

            return $this->currentAuth;

        } catch (\Exception $e) {
            $this->logger->error('MEEST authentication failed', [
                'error' => $e->getMessage(),
                'url' => $this->credentials->baseUrl
            ]);

            if ($e instanceof MeestIntegrationException) {
                throw $e;
            }

            throw MeestIntegrationException::authenticationFailed($e->getMessage());
        }
    }

    /**
     * Create a shipment
     */
    public function createShipment(MeestShipmentRequestDTO $request): MeestShipmentResponseDTO
    {
        $this->ensureAuthenticated();

        $endpoint = $request->shipmentType->getApiEndpoint();
        $this->logger->info('Creating MEEST shipment', [
            'endpoint' => $endpoint,
            'type' => $request->shipmentType->value
        ]);

        try {
            $response = $this->executeAuthenticatedRequest('POST', $endpoint, [
                'json' => $request->toApiPayload(),
                'timeout' => self::TIMEOUT
            ]);

            $data = $response->toArray();
            $result = MeestShipmentResponseDTO::fromApiResponse($data);

            $this->logger->info('MEEST shipment created successfully', [
                'tracking_number' => $result->trackingNumber,
                'shipment_id' => $result->shipmentId
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create MEEST shipment', [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint
            ]);

            if ($e instanceof MeestIntegrationException) {
                throw $e;
            }

            throw new MeestIntegrationException('Failed to create shipment: ' . $e->getMessage());
        }
    }

    /**
     * Get tracking information
     */
    public function getTracking(string $trackingNumber): MeestTrackingResponseDTO
    {
        $this->ensureAuthenticated();

        $this->logger->info('Getting MEEST tracking info', [
            'tracking_number' => $trackingNumber
        ]);

        try {
            $response = $this->executeAuthenticatedRequest('GET', '/v2/api/tracking', [
                'query' => ['tracking_number' => $trackingNumber],
                'timeout' => self::TIMEOUT
            ]);

            $data = $response->toArray();

            if (empty($data)) {
                throw MeestIntegrationException::shipmentNotFound($trackingNumber);
            }

            $result = MeestTrackingResponseDTO::fromApiResponse($data);

            $this->logger->info('MEEST tracking info retrieved', [
                'tracking_number' => $trackingNumber,
                'status' => $result->status->value
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get MEEST tracking info', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage()
            ]);

            if ($e instanceof MeestIntegrationException) {
                throw $e;
            }

            throw new MeestIntegrationException('Failed to get tracking info: ' . $e->getMessage());
        }
    }

    /**
     * Generate shipping label
     */
    /**
     * Create a return shipment
     */
    public function createReturnShipment(MeestShipmentRequestDTO $request): MeestShipmentResponseDTO
    {
        $this->ensureAuthenticated();

        $this->logger->info('Creating MEEST return shipment', [
            'original_tracking_number' => $request->originalTrackingNumber
        ]);

        try {
            $response = $this->executeAuthenticatedRequest('POST', '/v2/api/parcels/return', [
                'json' => $request->toApiPayload(),
                'timeout' => self::TIMEOUT
            ]);

            $data = $response->toArray();
            $result = MeestShipmentResponseDTO::fromApiResponse($data);

            $this->logger->info('MEEST return shipment created successfully', [
                'tracking_number' => $result->trackingNumber,
                'shipment_id' => $result->shipmentId
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create MEEST return shipment', [
                'error' => $e->getMessage()
            ]);

            if ($e instanceof MeestIntegrationException) {
                throw $e;
            }

            throw new MeestIntegrationException('Failed to create return shipment: ' . $e->getMessage());
        }
    }

    /**
     * Generate shipping label
     */
    public function generateLabel(string $trackingNumber): string
    {
        $this->ensureAuthenticated();

        $this->logger->info('Generating MEEST label', [
            'tracking_number' => $trackingNumber
        ]);

        try {
            $response = $this->executeAuthenticatedRequest('GET', '/v2/api/label', [
                'query' => ['tracking_number' => $trackingNumber],
                'timeout' => self::TIMEOUT
            ]);

            $data = $response->toArray();

            if (!isset($data['label_url']) && !isset($data['label_data'])) {
                throw MeestIntegrationException::labelGenerationFailed($trackingNumber);
            }

            $labelUrl = $data['label_url'] ?? $data['label_data'];

            $this->logger->info('MEEST label generated successfully', [
                'tracking_number' => $trackingNumber
            ]);

            return $labelUrl;

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate MEEST label', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage()
            ]);

            if ($e instanceof MeestIntegrationException) {
                throw $e;
            }

            throw MeestIntegrationException::labelGenerationFailed($trackingNumber);
        }
    }

    /**
     * Ensure we have a valid authentication token
     */
    private function ensureAuthenticated(): void
    {
        if (!$this->currentAuth || $this->currentAuth->isExpiringSoon()) {
            $this->authenticate();
        }
    }

    /**
     * Execute authenticated request
     */
    private function executeAuthenticatedRequest(string $method, string $endpoint, array $options = []): ResponseInterface
    {
        $options['headers'] = array_merge(
            $options['headers'] ?? [],
            ['Authorization' => $this->currentAuth->getAuthorizationHeader()]
        );

        return $this->executeRequest($method, $endpoint, $options);
    }

    /**
     * Execute HTTP request with retry logic
     */
    private function executeRequest(string $method, string $endpoint, array $options = []): ResponseInterface
    {
        $url = $this->credentials->baseUrl . $endpoint;
        $retries = 0;

        while ($retries < self::MAX_RETRIES) {
            try {
                $response = $this->httpClient->request($method, $url, $options);

                // Check for HTTP errors
                $statusCode = $response->getStatusCode();
                if ($statusCode >= 400) {
                    $this->handleHttpError($response, $statusCode);
                }

                return $response;

            } catch (\Exception $e) {
                $retries++;

                $this->logger->warning('MEEST API request failed', [
                    'attempt' => $retries,
                    'method' => $method,
                    'url' => $url,
                    'error' => $e->getMessage()
                ]);

                if ($retries >= self::MAX_RETRIES) {
                    throw new MeestIntegrationException(
                        "Request failed after {$retries} attempts: {$e->getMessage()}",
                        $e->getCode(),
                        $e
                    );
                }

                // Wait before retry
                usleep(self::RETRY_DELAY * 1000);
            }
        }

        throw new MeestIntegrationException('Unexpected error in request execution');
    }

    /**
     * Handle HTTP errors from API responses
     */
    private function handleHttpError(ResponseInterface $response, int $statusCode): void
    {
        try {
            $data = $response->toArray(false);
        } catch (\Exception) {
            $data = ['error' => 'Unknown error'];
        }

        // Check for specific MEEST API error messages
        $errorMessage = $data['message'] ?? $data['error'] ?? 'Unknown error';

        // Handle specific error cases
        if (str_contains($errorMessage, 'Non-unique parcel number')) {
            throw new MeestIntegrationException('Non-unique parcel number', 409);
        }

        if (str_contains($errorMessage, 'Any routing not found')) {
            throw new MeestIntegrationException('No routing found for the destination', 422);
        }

        match ($statusCode) {
            401 => throw MeestIntegrationException::authenticationFailed(),
            429 => throw MeestIntegrationException::rateLimitExceeded(),
            404 => throw new MeestIntegrationException('Resource not found', 404),
            409 => throw new MeestIntegrationException($errorMessage, 409),
            422 => throw new MeestIntegrationException($errorMessage, 422),
            default => throw MeestIntegrationException::fromApiResponse($data, $statusCode),
        };
    }
}