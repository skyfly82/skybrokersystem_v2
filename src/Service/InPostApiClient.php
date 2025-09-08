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
            $response = $this->makeRequest('GET', '/organizations/' . $this->organizationId);
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
        $response = $this->makeRequest('GET', '/organizations/' . $this->organizationId);
        return $response->toArray();
    }

    /**
     * Create shipment
     */
    public function createShipment(array $shipmentData): array
    {
        $response = $this->makeRequest('POST', '/organizations/' . $this->organizationId . '/shipments', [
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
     * Get parcel lockers
     */
    public function getParcelLockers(array $params = []): array
    {
        $queryString = empty($params) ? '' : '?' . http_build_query($params);
        $response = $this->makeRequest('GET', '/points' . $queryString);
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
     * Track shipment
     */
    public function trackShipment(string $trackingNumber): array
    {
        $response = $this->makeRequest('GET', '/shipments/tracking/' . $trackingNumber);
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