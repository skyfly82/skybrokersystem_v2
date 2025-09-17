<?php

declare(strict_types=1);

namespace App\Courier\InPost\Service;

use App\Courier\InPost\Config\InPostConfiguration;
use App\Courier\InPost\Exception\InPostIntegrationException;
use App\Service\CourierSecretsService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class InPostAuthenticationService
{
    private const TOKEN_CACHE_KEY = 'inpost_access_token';
    private const TOKEN_EXPIRY_CACHE_KEY = 'inpost_token_expiry';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CourierSecretsService $courierSecretsService,
        private readonly InPostConfiguration $configuration,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * Get OAuth 2.0 access token for InPost API
     * 
     * @throws InPostIntegrationException If authentication fails
     */
    public function getAccessToken(string $environment = 'sandbox'): string
    {
        // Check cache first
        $cachedToken = $this->getCachedToken();
        if ($cachedToken) {
            return $cachedToken;
        }

        $apiKey = $this->courierSecretsService->getInpostApiKey($environment);
        if (!$apiKey) {
            throw new InPostIntegrationException('InPost API key not configured');
        }

        try {
            $response = $this->httpClient->request('POST', $this->configuration->getApiUrl($environment) . '/oauth/token', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode($apiKey . ':'),
                ],
                'body' => http_build_query([
                    'grant_type' => 'client_credentials',
                    'scope' => 'shipx_api'
                ])
            ]);

            $data = $response->toArray();

            // Cache the token with its expiration
            $this->cacheToken($data['access_token'], $data['expires_in'] ?? 3600);

            return $data['access_token'];
        } catch (\Exception $e) {
            throw new InPostIntegrationException('Failed to authenticate with InPost API: ' . $e->getMessage());
        }
    }

    private function getCachedToken(): ?string
    {
        $token = $this->cache->get(self::TOKEN_CACHE_KEY);
        $expiry = $this->cache->get(self::TOKEN_EXPIRY_CACHE_KEY);

        if ($token && $expiry && $expiry > time()) {
            return $token;
        }

        return null;
    }

    private function cacheToken(string $token, int $expiresIn): void
    {
        $this->cache->set(self::TOKEN_CACHE_KEY, $token, $expiresIn);
        $this->cache->set(self::TOKEN_EXPIRY_CACHE_KEY, time() + $expiresIn, $expiresIn);
    }
}