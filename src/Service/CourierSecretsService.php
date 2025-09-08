<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\SecretCategory;
use DateTimeImmutable;

/**
 * Helper service for managing courier service secrets
 */
class CourierSecretsService
{
    public function __construct(
        private readonly SecretsManagerService $secretsManager
    ) {
    }

    // InPost API Integration
    public function setInpostApiKey(string $apiKey, ?string $environment = 'sandbox'): void
    {
        $name = $environment === 'production' ? 'inpost_api_key_prod' : 'inpost_api_key_sandbox';
        
        $this->secretsManager->storeSecret(
            SecretCategory::COURIER_API_KEYS->value,
            $name,
            $apiKey,
            "InPost API key for {$environment} environment",
            (new DateTimeImmutable())->modify('+1 year')
        );
    }

    public function getInpostApiKey(?string $environment = 'sandbox'): ?string
    {
        $name = $environment === 'production' ? 'inpost_api_key_prod' : 'inpost_api_key_sandbox';
        return $this->secretsManager->getSecret(SecretCategory::COURIER_API_KEYS->value, $name);
    }

    // DHL API Integration
    public function setDhlCredentials(string $username, string $password, string $accountNumber, ?string $environment = 'sandbox'): void
    {
        $envSuffix = $environment === 'production' ? '_prod' : '_sandbox';
        
        $this->secretsManager->storeSecret(
            SecretCategory::COURIER_API_KEYS->value,
            "dhl_username{$envSuffix}",
            $username,
            "DHL API username for {$environment} environment"
        );

        $this->secretsManager->storeSecret(
            SecretCategory::COURIER_API_KEYS->value,
            "dhl_password{$envSuffix}",
            $password,
            "DHL API password for {$environment} environment"
        );

        $this->secretsManager->storeSecret(
            SecretCategory::COURIER_API_KEYS->value,
            "dhl_account_number{$envSuffix}",
            $accountNumber,
            "DHL account number for {$environment} environment"
        );
    }

    public function getDhlCredentials(?string $environment = 'sandbox'): array
    {
        $envSuffix = $environment === 'production' ? '_prod' : '_sandbox';
        
        return [
            'username' => $this->secretsManager->getSecret(
                SecretCategory::COURIER_API_KEYS->value,
                "dhl_username{$envSuffix}"
            ),
            'password' => $this->secretsManager->getSecret(
                SecretCategory::COURIER_API_KEYS->value,
                "dhl_password{$envSuffix}"
            ),
            'account_number' => $this->secretsManager->getSecret(
                SecretCategory::COURIER_API_KEYS->value,
                "dhl_account_number{$envSuffix}"
            ),
        ];
    }

    // Webhook Tokens
    public function generateWebhookToken(string $service): string
    {
        return $this->secretsManager->generateApiKey(
            SecretCategory::WEBHOOK_TOKENS->value,
            "{$service}_webhook_token",
            'whk_',
            48,
            "Webhook verification token for {$service}"
        );
    }

    public function getWebhookToken(string $service): ?string
    {
        return $this->secretsManager->getSecret(
            SecretCategory::WEBHOOK_TOKENS->value,
            "{$service}_webhook_token"
        );
    }

    public function verifyWebhookToken(string $service, string $token): bool
    {
        $storedToken = $this->getWebhookToken($service);
        return $storedToken && hash_equals($storedToken, $token);
    }

    // Payment Service Integration
    public function setPayNowCredentials(string $apiKey, string $signatureKey, ?string $environment = 'sandbox'): void
    {
        $envSuffix = $environment === 'production' ? '_prod' : '_sandbox';
        
        $this->secretsManager->storeSecret(
            SecretCategory::PAYMENT_KEYS->value,
            "paynow_api_key{$envSuffix}",
            $apiKey,
            "PayNow API key for {$environment} environment"
        );

        $this->secretsManager->storeSecret(
            SecretCategory::PAYMENT_KEYS->value,
            "paynow_signature_key{$envSuffix}",
            $signatureKey,
            "PayNow signature key for {$environment} environment"
        );
    }

    public function getPayNowCredentials(?string $environment = 'sandbox'): array
    {
        $envSuffix = $environment === 'production' ? '_prod' : '_sandbox';
        
        return [
            'api_key' => $this->secretsManager->getSecret(
                SecretCategory::PAYMENT_KEYS->value,
                "paynow_api_key{$envSuffix}"
            ),
            'signature_key' => $this->secretsManager->getSecret(
                SecretCategory::PAYMENT_KEYS->value,
                "paynow_signature_key{$envSuffix}"
            ),
        ];
    }

    public function setStripeKeys(string $publishableKey, string $secretKey, ?string $webhookSecret = null, ?string $environment = 'test'): void
    {
        $envSuffix = $environment === 'live' ? '_live' : '_test';
        
        $this->secretsManager->storeSecret(
            SecretCategory::PAYMENT_KEYS->value,
            "stripe_publishable_key{$envSuffix}",
            $publishableKey,
            "Stripe publishable key for {$environment} environment"
        );

        $this->secretsManager->storeSecret(
            SecretCategory::PAYMENT_KEYS->value,
            "stripe_secret_key{$envSuffix}",
            $secretKey,
            "Stripe secret key for {$environment} environment"
        );

        if ($webhookSecret) {
            $this->secretsManager->storeSecret(
                SecretCategory::PAYMENT_KEYS->value,
                "stripe_webhook_secret{$envSuffix}",
                $webhookSecret,
                "Stripe webhook endpoint secret for {$environment} environment"
            );
        }
    }

    public function getStripeKeys(?string $environment = 'test'): array
    {
        $envSuffix = $environment === 'live' ? '_live' : '_test';
        
        return [
            'publishable_key' => $this->secretsManager->getSecret(
                SecretCategory::PAYMENT_KEYS->value,
                "stripe_publishable_key{$envSuffix}"
            ),
            'secret_key' => $this->secretsManager->getSecret(
                SecretCategory::PAYMENT_KEYS->value,
                "stripe_secret_key{$envSuffix}"
            ),
            'webhook_secret' => $this->secretsManager->getSecret(
                SecretCategory::PAYMENT_KEYS->value,
                "stripe_webhook_secret{$envSuffix}"
            ),
        ];
    }

    // Internal Service Tokens
    public function generateInternalToken(string $service, ?DateTimeImmutable $expiresAt = null): string
    {
        return $this->secretsManager->generateApiKey(
            SecretCategory::INTERNAL_TOKENS->value,
            "{$service}_token",
            'sky_',
            64,
            "Internal service token for {$service}",
            $expiresAt
        );
    }

    public function getInternalToken(string $service): ?string
    {
        return $this->secretsManager->getSecret(
            SecretCategory::INTERNAL_TOKENS->value,
            "{$service}_token"
        );
    }

    // SMS API Integration
    public function setSmsApiCredentials(string $provider, array $credentials): void
    {
        foreach ($credentials as $key => $value) {
            $this->secretsManager->storeSecret(
                SecretCategory::SMS_API->value,
                "{$provider}_{$key}",
                $value,
                "SMS API {$key} for {$provider}"
            );
        }
    }

    public function getSmsApiCredentials(string $provider, array $keys): array
    {
        $credentials = [];
        foreach ($keys as $key) {
            $credentials[$key] = $this->secretsManager->getSecret(
                SecretCategory::SMS_API->value,
                "{$provider}_{$key}"
            );
        }
        return $credentials;
    }

    // Email SMTP Configuration
    public function setSmtpCredentials(
        string $host,
        int $port,
        string $username,
        string $password,
        string $encryption = 'tls'
    ): void {
        $metadata = [
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
        ];

        $this->secretsManager->storeSecret(
            SecretCategory::EMAIL_SMTP->value,
            'smtp_username',
            $username,
            'SMTP server username',
            null,
            $metadata
        );

        $this->secretsManager->storeSecret(
            SecretCategory::EMAIL_SMTP->value,
            'smtp_password',
            $password,
            'SMTP server password',
            null,
            $metadata
        );
    }

    public function getSmtpCredentials(): array
    {
        $secrets = $this->secretsManager->getSecretsByCategory(SecretCategory::EMAIL_SMTP->value);
        
        $username = $this->secretsManager->getSecret(SecretCategory::EMAIL_SMTP->value, 'smtp_username');
        $password = $this->secretsManager->getSecret(SecretCategory::EMAIL_SMTP->value, 'smtp_password');
        
        // Get metadata from one of the secrets
        $metadata = null;
        foreach ($secrets as $secret) {
            if ($secret->getMetadata()) {
                $metadata = $secret->getMetadata();
                break;
            }
        }

        return [
            'host' => $metadata['host'] ?? null,
            'port' => $metadata['port'] ?? 587,
            'username' => $username,
            'password' => $password,
            'encryption' => $metadata['encryption'] ?? 'tls',
        ];
    }

    /**
     * Rotate all secrets for a specific service
     */
    public function rotateServiceSecrets(string $service): array
    {
        $allSecrets = $this->secretsManager->getAllActiveSecrets();
        $serviceSecrets = array_filter($allSecrets, function ($secret) use ($service) {
            return str_contains($secret->getName(), $service);
        });

        $rotated = [];
        foreach ($serviceSecrets as $secret) {
            try {
                // Generate new value (this is a simple example - you might want different logic per secret type)
                $newValue = bin2hex(random_bytes(32));
                
                $newSecret = $this->secretsManager->rotateSecret($secret, $newValue);
                $rotated[] = $newSecret;
            } catch (\Exception $e) {
                // Log error but continue with other secrets
                error_log("Failed to rotate secret {$secret->getName()}: " . $e->getMessage());
            }
        }

        return $rotated;
    }

    /**
     * Get all secrets that are expiring soon for a specific service
     */
    public function getExpiringServiceSecrets(string $service, int $daysAhead = 30): array
    {
        $expiringSecrets = $this->secretsManager->getSecretsForRotation($daysAhead);
        
        return array_filter($expiringSecrets, function ($secret) use ($service) {
            return str_contains($secret->getName(), $service);
        });
    }

    /**
     * Health check for all courier service integrations
     */
    public function healthCheck(): array
    {
        $health = [
            'inpost' => [
                'sandbox' => $this->getInpostApiKey('sandbox') !== null,
                'production' => $this->getInpostApiKey('production') !== null,
            ],
            'dhl' => [
                'sandbox' => $this->isDhlConfigured('sandbox'),
                'production' => $this->isDhlConfigured('production'),
            ],
            'paynow' => [
                'sandbox' => $this->isPayNowConfigured('sandbox'),
                'production' => $this->isPayNowConfigured('production'),
            ],
            'stripe' => [
                'test' => $this->isStripeConfigured('test'),
                'live' => $this->isStripeConfigured('live'),
            ],
            'smtp' => $this->isSmtpConfigured(),
        ];

        return $health;
    }

    private function isDhlConfigured(string $environment): bool
    {
        $credentials = $this->getDhlCredentials($environment);
        return !empty($credentials['username']) && 
               !empty($credentials['password']) && 
               !empty($credentials['account_number']);
    }

    private function isPayNowConfigured(string $environment): bool
    {
        $credentials = $this->getPayNowCredentials($environment);
        return !empty($credentials['api_key']) && !empty($credentials['signature_key']);
    }

    private function isStripeConfigured(string $environment): bool
    {
        $keys = $this->getStripeKeys($environment);
        return !empty($keys['publishable_key']) && !empty($keys['secret_key']);
    }

    private function isSmtpConfigured(): bool
    {
        $credentials = $this->getSmtpCredentials();
        return !empty($credentials['username']) && 
               !empty($credentials['password']) && 
               !empty($credentials['host']);
    }
}