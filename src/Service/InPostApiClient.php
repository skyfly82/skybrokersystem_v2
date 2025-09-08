<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * InPost API Client for integration with InPost services
 */
class InPostApiClient
{
    private const DEFAULT_TIMEOUT = 30;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiUrl,
        private readonly string $token,
        private readonly string $organizationId
    ) {
    }

    /**
     * Test API connection
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/v1/organizations/' . $this->organizationId);
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get organization details
     */
    public function getOrganization(): array
    {
        $response = $this->makeRequest('GET', '/v1/organizations/' . $this->organizationId);
        return $response->toArray();
    }

    /**
     * Create shipment
     */
    public function createShipment(array $shipmentData): array
    {
        $response = $this->makeRequest('POST', '/v1/organizations/' . $this->organizationId . '/shipments', [
            'json' => $shipmentData
        ]);
        
        return $response->toArray();
    }

    /**
     * Get shipment details
     */
    public function getShipment(string $id): array
    {
        $response = $this->makeRequest('GET', '/shipments/' . $id);
        return $response->toArray();
    }

    /**
     * Get parcel lockers (public API - no authentication required)
     */
    public function getParcelLockers(array $params = []): array
    {
        // Points API is public and uses different URL structure
        $queryString = empty($params) ? '' : '?' . http_build_query($params);
        $pointsApiUrl = str_replace('sandbox-api-shipx-pl', 'api-pl-points', $this->apiUrl);
        $url = $pointsApiUrl . '/v1/points' . $queryString;
        
        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'timeout' => self::DEFAULT_TIMEOUT,
        ]);
        
        return $response->toArray();
    }

    /**
     * Create dispatch order
     */
    public function createDispatchOrder(array $shipmentIds): array
    {
        $response = $this->makeRequest('POST', '/organizations/' . $this->organizationId . '/dispatch_orders', [
            'json' => ['shipments' => $shipmentIds]
        ]);
        
        return $response->toArray();
    }

    /**
     * Get dispatch order details
     */
    public function getDispatchOrder(string $id): array
    {
        $response = $this->makeRequest('GET', '/dispatch_orders/' . $id);
        return $response->toArray();
    }

    /**
     * Track shipment by tracking number
     */
    public function trackShipment(string $trackingNumber): array
    {
        $response = $this->makeRequest('GET', '/v1/shipments/tracking/' . $trackingNumber);
        return $response->toArray();
    }

    /**
     * Get shipment by ID
     */
    public function getShipmentById(string $shipmentId): array
    {
        $response = $this->makeRequest('GET', '/v1/shipments/' . $shipmentId);
        return $response->toArray();
    }

    /**
     * Get shipment label (PDF)
     */
    public function getShipmentLabel(string $shipmentId, string $format = 'pdf'): string
    {
        $response = $this->makeRequest('GET', "/v1/shipments/{$shipmentId}/label", [
            'headers' => [
                'Accept' => 'application/pdf'
            ]
        ]);
        
        return $response->getContent();
    }

    /**
     * Get multiple shipment labels
     */
    public function getMultipleLabels(array $shipmentIds, string $format = 'pdf'): string
    {
        $queryString = 'ids=' . implode(',', $shipmentIds);
        
        $response = $this->makeRequest('GET', "/v1/organizations/{$this->organizationId}/shipments/labels?{$queryString}", [
            'headers' => [
                'Accept' => 'application/pdf'
            ]
        ]);
        
        return $response->getContent();
    }

    /**
     * Update shipment reference
     */
    public function updateShipmentReference(string $shipmentId, string $reference): array
    {
        $response = $this->makeRequest('PATCH', "/v1/shipments/{$shipmentId}", [
            'json' => [
                'reference' => $reference
            ]
        ]);
        
        return $response->toArray();
    }

    /**
     * Make HTTP request to InPost API
     */
    private function makeRequest(string $method, string $endpoint, array $options = []): ResponseInterface
    {
        $url = rtrim($this->apiUrl, '/') . '/' . ltrim($endpoint, '/');
        
        $defaultOptions = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => self::DEFAULT_TIMEOUT,
        ];

        $options = array_merge_recursive($defaultOptions, $options);

        return $this->httpClient->request($method, $url, $options);
    }

    /**
     * Get API configuration
     */
    public function getConfig(): array
    {
        return [
            'api_url' => $this->apiUrl,
            'organization_id' => $this->organizationId,
            'token_configured' => !empty($this->token),
        ];
    }
}