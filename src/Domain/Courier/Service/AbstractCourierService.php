<?php

declare(strict_types=1);

namespace App\Domain\Courier\Service;

use App\Domain\Courier\Contracts\CourierIntegrationInterface;
use App\Domain\Courier\Exception\CourierIntegrationException;
use App\Service\SecretsManagerService;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractCourierService implements CourierIntegrationInterface
{
    protected const MAX_RETRIES = 3;
    protected const RETRY_DELAY_MS = 1000;

    public function __construct(
        protected HttpClientInterface $httpClient,
        protected SecretsManagerService $secretManager,
        protected LoggerInterface $logger
    ) {}

    /**
     * Execute HTTP request with retry and error handling
     *
     * @param callable $requestCallback Function to execute the request
     * @return mixed
     * @throws CourierIntegrationException
     */
    protected function executeWithRetry(callable $requestCallback)
    {
        $retries = 0;
        while ($retries < self::MAX_RETRIES) {
            try {
                return $requestCallback();
            } catch (\Exception $e) {
                $this->logger->error('Courier API request failed', [
                    'attempt' => $retries + 1,
                    'error' => $e->getMessage()
                ]);

                $retries++;
                
                if ($retries >= self::MAX_RETRIES) {
                    throw new CourierIntegrationException(
                        "Courier API request failed after {$retries} attempts: {$e->getMessage()}",
                        $e->getCode(),
                        $e
                    );
                }

                usleep(self::RETRY_DELAY_MS * 1000);
            }
        }

        throw new CourierIntegrationException('Unexpected error in executeWithRetry');
    }

    /**
     * Validate webhook payload signature
     *
     * @param array $payload Webhook payload
     * @param string $signatureHeader Signature from webhook header
     * @return bool
     */
    protected function validateWebhookSignature(array $payload, string $signatureHeader): bool
    {
        // Implement webhook signature validation specific to courier
        // This is a placeholder - each courier will have its own signature validation
        $secret = 'dummy_secret_for_now'; // TODO: Implement proper secret retrieval
        
        // Example generic validation (to be overridden)
        $computedSignature = hash_hmac(
            'sha256', 
            json_encode($payload), 
            $secret
        );

        return hash_equals($computedSignature, $signatureHeader);
    }

    /**
     * Log courier API interaction
     *
     * @param string $action Action being performed
     * @param array $context Additional context
     */
    protected function logCourierInteraction(string $action, array $context = []): void
    {
        $this->logger->info("Courier API: {$action}", $context);
    }
}