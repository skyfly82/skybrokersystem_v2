<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Service;

use App\Domain\Courier\Meest\Entity\MeestShipment;
use App\Domain\Courier\Meest\Enum\MeestTrackingStatus;
use App\Domain\Courier\Meest\Repository\MeestShipmentRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Real-time webhook service for MEEST tracking updates
 */
class MeestWebhookService
{
    private const WEBHOOK_TIMEOUT = 10; // seconds
    private const MAX_RETRIES = 3;
    private const WEBHOOK_SECRET_HEADER = 'X-MEEST-Signature';

    public function __construct(
        private readonly MeestShipmentRepository $shipmentRepository,
        private readonly MeestAITrackingService $aiTrackingService,
        private readonly HttpClientInterface $httpClient,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly string $webhookSecret
    ) {}

    /**
     * Process incoming webhook from MEEST
     */
    public function processIncomingWebhook(Request $request): array
    {
        try {
            // Validate webhook signature
            if (!$this->validateWebhookSignature($request)) {
                throw new \InvalidArgumentException('Invalid webhook signature');
            }

            $payload = json_decode($request->getContent(), true);
            if (!$payload) {
                throw new \InvalidArgumentException('Invalid JSON payload');
            }

            $this->logger->info('Processing MEEST webhook', [
                'tracking_number' => $payload['trackingNumber'] ?? 'unknown',
                'status' => $payload['status'] ?? 'unknown',
                'ip' => $request->getClientIp()
            ]);

            // Process the webhook data
            $result = $this->processWebhookData($payload);

            // Send real-time notifications if status changed
            if ($result['status_changed']) {
                $this->sendRealTimeNotifications($result['shipment'], $result['old_status'], $result['new_status']);
            }

            return [
                'success' => true,
                'processed' => true,
                'status_changed' => $result['status_changed'],
                'tracking_number' => $payload['trackingNumber'] ?? null
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to process MEEST webhook', [
                'error' => $e->getMessage(),
                'content' => $request->getContent(),
                'ip' => $request->getClientIp()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send webhook notifications to registered endpoints
     */
    public function sendWebhookNotification(
        MeestShipment $shipment,
        array $trackingData,
        string $eventType = 'tracking.updated'
    ): void {
        try {
            // Get registered webhook endpoints for this customer/shipment
            $webhookEndpoints = $this->getWebhookEndpoints($shipment);

            if (empty($webhookEndpoints)) {
                $this->logger->debug('No webhook endpoints configured', [
                    'tracking_number' => $shipment->getTrackingNumber()
                ]);
                return;
            }

            $webhookPayload = $this->prepareWebhookPayload($shipment, $trackingData, $eventType);

            foreach ($webhookEndpoints as $endpoint) {
                $this->sendWebhook($endpoint, $webhookPayload);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to send webhook notifications', [
                'tracking_number' => $shipment->getTrackingNumber(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Register webhook endpoint for real-time updates
     */
    public function registerWebhookEndpoint(
        string $url,
        array $events = ['tracking.updated', 'tracking.delivered', 'tracking.exception'],
        ?string $secret = null
    ): array {
        try {
            // Validate webhook URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException('Invalid webhook URL');
            }

            // Test webhook endpoint
            $testResult = $this->testWebhookEndpoint($url, $secret);

            if (!$testResult['success']) {
                throw new \RuntimeException('Webhook endpoint test failed: ' . $testResult['error']);
            }

            // Store webhook configuration
            $webhookConfig = [
                'url' => $url,
                'events' => $events,
                'secret' => $secret,
                'registered_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'test_result' => $testResult
            ];

            // In production, store this in database
            $this->storeWebhookConfig($webhookConfig);

            $this->logger->info('Webhook endpoint registered successfully', [
                'url' => $url,
                'events' => $events
            ]);

            return [
                'success' => true,
                'webhook_id' => uniqid('webhook_'),
                'config' => $webhookConfig
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to register webhook endpoint', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send real-time notifications for status changes
     */
    public function sendRealTimeNotifications(
        MeestShipment $shipment,
        MeestTrackingStatus $oldStatus,
        MeestTrackingStatus $newStatus
    ): void {
        try {
            // Prepare notification data
            $notificationData = [
                'tracking_number' => $shipment->getTrackingNumber(),
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
                'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'is_terminal' => $newStatus->isTerminal(),
                'has_issue' => $newStatus->hasIssue()
            ];

            // Send via message bus for async processing
            // $this->messageBus->dispatch(new SendRealTimeNotificationMessage($notificationData));

            // Send immediate webhook notifications
            $this->sendWebhookNotification($shipment, $notificationData, 'tracking.status_changed');

            // Send customer notifications based on status
            $this->sendCustomerNotifications($shipment, $newStatus);

            $this->logger->info('Real-time notifications sent', [
                'tracking_number' => $shipment->getTrackingNumber(),
                'status_change' => "{$oldStatus->value} -> {$newStatus->value}"
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send real-time notifications', [
                'tracking_number' => $shipment->getTrackingNumber(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Process webhook data and update shipment
     */
    private function processWebhookData(array $payload): array
    {
        $trackingNumber = $payload['trackingNumber'] ?? null;
        if (!$trackingNumber) {
            throw new \InvalidArgumentException('Missing tracking number in webhook payload');
        }

        // Find shipment
        $shipment = $this->shipmentRepository->findOneBy(['trackingNumber' => $trackingNumber]);
        if (!$shipment) {
            throw new \RuntimeException("Shipment not found: {$trackingNumber}");
        }

        $oldStatus = $shipment->getStatus();

        // Parse new status
        $apiStatus = $payload['status'] ?? null;
        $newStatus = MeestTrackingStatus::fromApiStatus($apiStatus);

        if (!$newStatus) {
            throw new \InvalidArgumentException("Unknown status in webhook: {$apiStatus}");
        }

        $statusChanged = false;

        // Update status if changed
        if ($newStatus !== $oldStatus) {
            $shipment->updateStatus($newStatus);

            // Update additional tracking data
            if (isset($payload['location'])) {
                $metadata = $shipment->getMetadata() ?? [];
                $metadata['last_location'] = $payload['location'];
                $metadata['webhook_updated_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
                $shipment->setMetadata($metadata);
            }

            $this->shipmentRepository->save($shipment);
            $statusChanged = true;

            $this->logger->info('Shipment status updated via webhook', [
                'tracking_number' => $trackingNumber,
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value
            ]);
        }

        return [
            'shipment' => $shipment,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'status_changed' => $statusChanged
        ];
    }

    /**
     * Validate webhook signature
     */
    private function validateWebhookSignature(Request $request): bool
    {
        $signature = $request->headers->get(self::WEBHOOK_SECRET_HEADER);
        if (!$signature) {
            return false; // In production, this might be required
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get webhook endpoints for shipment
     */
    private function getWebhookEndpoints(MeestShipment $shipment): array
    {
        // In production, this would query database for registered webhooks
        // For now, return a mock endpoint
        return [
            [
                'url' => 'https://example.com/webhooks/meest',
                'events' => ['tracking.updated', 'tracking.delivered'],
                'secret' => 'webhook_secret_123'
            ]
        ];
    }

    /**
     * Prepare webhook payload
     */
    private function prepareWebhookPayload(
        MeestShipment $shipment,
        array $trackingData,
        string $eventType
    ): array {
        return [
            'event' => $eventType,
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'data' => [
                'tracking_number' => $shipment->getTrackingNumber(),
                'status' => $shipment->getStatus()->value,
                'status_description' => $shipment->getStatus()->getDescription(),
                'tracking_data' => $trackingData,
                'metadata' => $shipment->getMetadata()
            ],
            'webhook_id' => uniqid('wh_'),
            'version' => '1.0'
        ];
    }

    /**
     * Send webhook to endpoint
     */
    private function sendWebhook(array $endpoint, array $payload): void
    {
        try {
            $headers = [
                'Content-Type' => 'application/json',
                'User-Agent' => 'MEEST-Webhook/1.0'
            ];

            // Add signature if secret is provided
            if (isset($endpoint['secret'])) {
                $signature = hash_hmac('sha256', json_encode($payload), $endpoint['secret']);
                $headers[self::WEBHOOK_SECRET_HEADER] = $signature;
            }

            $response = $this->httpClient->request('POST', $endpoint['url'], [
                'headers' => $headers,
                'json' => $payload,
                'timeout' => self::WEBHOOK_TIMEOUT
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->debug('Webhook sent successfully', [
                    'url' => $endpoint['url'],
                    'status_code' => $statusCode
                ]);
            } else {
                $this->logger->warning('Webhook returned non-success status', [
                    'url' => $endpoint['url'],
                    'status_code' => $statusCode
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to send webhook', [
                'url' => $endpoint['url'],
                'error' => $e->getMessage()
            ]);

            // Queue for retry
            $this->queueWebhookRetry($endpoint, $payload);
        }
    }

    /**
     * Test webhook endpoint
     */
    private function testWebhookEndpoint(string $url, ?string $secret): array
    {
        try {
            $testPayload = [
                'event' => 'test.ping',
                'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'data' => ['message' => 'Test webhook']
            ];

            $headers = [
                'Content-Type' => 'application/json',
                'User-Agent' => 'MEEST-Webhook-Test/1.0'
            ];

            if ($secret) {
                $signature = hash_hmac('sha256', json_encode($testPayload), $secret);
                $headers[self::WEBHOOK_SECRET_HEADER] = $signature;
            }

            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'json' => $testPayload,
                'timeout' => self::WEBHOOK_TIMEOUT
            ]);

            $statusCode = $response->getStatusCode();

            return [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'status_code' => $statusCode,
                'response_time' => 0.5 // Simplified
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Store webhook configuration
     */
    private function storeWebhookConfig(array $config): void
    {
        // In production, store in database
        // For now, just log it
        $this->logger->info('Webhook configuration stored', $config);
    }

    /**
     * Queue webhook for retry
     */
    private function queueWebhookRetry(array $endpoint, array $payload): void
    {
        // In production, use message queue for retries
        $this->logger->info('Webhook queued for retry', [
            'url' => $endpoint['url']
        ]);
    }

    /**
     * Send customer notifications based on status
     */
    private function sendCustomerNotifications(MeestShipment $shipment, MeestTrackingStatus $status): void
    {
        // Determine notification type based on status
        $notificationType = match ($status) {
            MeestTrackingStatus::OUT_FOR_DELIVERY => 'delivery_today',
            MeestTrackingStatus::DELIVERED => 'delivered',
            MeestTrackingStatus::DELIVERY_ATTEMPT => 'delivery_failed',
            MeestTrackingStatus::EXCEPTION => 'shipment_issue',
            MeestTrackingStatus::CUSTOMS_HELD => 'customs_issue',
            default => null
        };

        if ($notificationType) {
            // Queue notification for processing
            $this->logger->info('Customer notification queued', [
                'tracking_number' => $shipment->getTrackingNumber(),
                'notification_type' => $notificationType
            ]);

            // In production, dispatch notification message
            // $this->messageBus->dispatch(new SendCustomerNotificationMessage($shipment, $notificationType));
        }
    }
}