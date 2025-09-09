<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Payment\Service\PayNowService;
use App\Domain\Payment\Service\PaymentHandler;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PayNowWebhookController extends AbstractController
{
    private PayNowService $payNowService;
    private PaymentHandler $paymentHandler;
    private LoggerInterface $logger;

    public function __construct(
        PayNowService $payNowService,
        PaymentHandler $paymentHandler,
        LoggerInterface $logger
    ) {
        $this->payNowService = $payNowService;
        $this->paymentHandler = $paymentHandler;
        $this->logger = $logger;
    }

    #[Route('/api/paynow/webhook', name: 'paynow_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->headers->get('X-PayNow-Signature', '');
        $timestamp = $request->headers->get('X-PayNow-Timestamp', '');

        $this->logger->info('Received PayNow webhook', [
            'signature' => $signature,
            'timestamp' => $timestamp,
        ]);

        try {
            // Weryfikacja sygnatury
            if (!$this->payNowService->verifyWebhookSignature($payload, $signature, $timestamp)) {
                $this->logger->warning('Invalid webhook signature');
                return new Response('Invalid signature', Response::HTTP_FORBIDDEN);
            }

            // Parsowanie danych
            $webhookData = json_decode($payload, true);
            if (!$webhookData) {
                $this->logger->warning('Invalid webhook payload');
                return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
            }

            // Przetwarzanie statusu płatności
            $statusResponse = $this->payNowService->processWebhookNotification($webhookData);

            // Aktualizacja statusu płatności
            $this->paymentHandler->updatePaymentStatusFromWebhook($statusResponse);

            return new Response('Webhook processed successfully', Response::HTTP_OK);

        } catch (\Exception $e) {
            $this->logger->error('PayNow webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new Response('Webhook processing error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/paynow/webhook/test', name: 'paynow_webhook_test', methods: ['POST'])]
    public function testWebhook(Request $request): Response
    {
        $this->logger->info('PayNow webhook test endpoint called', [
            'payload' => $request->getContent(),
        ]);

        return new Response('Test webhook received', Response::HTTP_OK);
    }
}