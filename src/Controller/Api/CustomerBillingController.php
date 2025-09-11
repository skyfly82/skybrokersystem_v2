<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

#[Route('/api/v1/customer')]
class CustomerBillingController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    #[Route('/invoices', name: 'api_customer_invoices', methods: ['GET'])]
    public function getInvoices(Request $request): JsonResponse
    {
        try {
            // Mock invoices data
            $invoices = [
                [
                    'id' => 'INV-2025-001',
                    'invoiceNumber' => 'INV-2025-001',
                    'date' => '2025-09-10',
                    'dueDate' => '2025-09-24',
                    'amount' => 299.99,
                    'currency' => 'PLN',
                    'status' => 'paid',
                    'paidAt' => '2025-09-11',
                    'items' => [
                        [
                            'description' => 'Przesyłka INP240901001',
                            'quantity' => 1,
                            'unitPrice' => 18.45,
                            'totalPrice' => 18.45
                        ],
                        [
                            'description' => 'Przesyłka INP240902002',
                            'quantity' => 1,
                            'unitPrice' => 22.30,
                            'totalPrice' => 22.30
                        ],
                        [
                            'description' => 'Przesyłka DHL240903001',
                            'quantity' => 1,
                            'unitPrice' => 45.00,
                            'totalPrice' => 45.00
                        ]
                    ],
                    'totals' => [
                        'subtotal' => 85.75,
                        'taxAmount' => 19.72,
                        'totalAmount' => 105.47
                    ]
                ],
                [
                    'id' => 'INV-2025-002',
                    'invoiceNumber' => 'INV-2025-002',
                    'date' => '2025-09-05',
                    'dueDate' => '2025-09-19',
                    'amount' => 156.75,
                    'currency' => 'PLN',
                    'status' => 'pending',
                    'items' => [
                        [
                            'description' => 'Przesyłka INP240905001',
                            'quantity' => 2,
                            'unitPrice' => 39.19,
                            'totalPrice' => 78.37
                        ]
                    ],
                    'totals' => [
                        'subtotal' => 127.47,
                        'taxAmount' => 29.28,
                        'totalAmount' => 156.75
                    ]
                ],
                [
                    'id' => 'INV-2025-003',
                    'invoiceNumber' => 'INV-2025-003',
                    'date' => '2025-08-28',
                    'dueDate' => '2025-09-11',
                    'amount' => 89.50,
                    'currency' => 'PLN',
                    'status' => 'overdue',
                    'items' => [
                        [
                            'description' => 'Przesyłka DHL240828001',
                            'quantity' => 1,
                            'unitPrice' => 72.76,
                            'totalPrice' => 72.76
                        ]
                    ],
                    'totals' => [
                        'subtotal' => 72.76,
                        'taxAmount' => 16.74,
                        'totalAmount' => 89.50
                    ]
                ]
            ];

            $this->logger->info('Customer invoices retrieved', [
                'customer_id' => $this->getUser()?->getId(),
                'invoices_count' => count($invoices)
            ]);

            return $this->json([
                'success' => true,
                'data' => $invoices
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve customer invoices', [
                'error' => $e->getMessage(),
                'customer_id' => $this->getUser()?->getId()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve invoices'
            ], 500);
        }
    }

    #[Route('/invoices/{id}', name: 'api_customer_invoice_details', methods: ['GET'])]
    public function getInvoiceDetails(string $id): JsonResponse
    {
        try {
            // Mock detailed invoice data
            $invoice = [
                'id' => $id,
                'invoiceNumber' => $id,
                'date' => '2025-09-10',
                'dueDate' => '2025-09-24',
                'amount' => 299.99,
                'currency' => 'PLN',
                'status' => 'paid',
                'paidAt' => '2025-09-11',
                'customer' => [
                    'name' => 'Example Company Sp. z o.o.',
                    'address' => 'ul. Biznesowa 123',
                    'city' => '00-001 Warszawa',
                    'taxId' => '1234567890',
                    'email' => 'billing@example.com'
                ],
                'items' => [
                    [
                        'description' => 'Przesyłka INP240901001 - Warszawa',
                        'quantity' => 1,
                        'unitPrice' => 18.45,
                        'totalPrice' => 18.45,
                        'taxRate' => 23
                    ]
                ],
                'totals' => [
                    'subtotal' => 85.75,
                    'taxAmount' => 19.72,
                    'totalAmount' => 105.47
                ],
                'paymentInfo' => [
                    'bankAccount' => 'PL61 1090 1014 0000 0712 1981 2874',
                    'paymentTitle' => $id,
                    'swift' => 'WBKPPLPP'
                ]
            ];

            return $this->json([
                'success' => true,
                'data' => $invoice
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve invoice details', [
                'error' => $e->getMessage(),
                'invoice_id' => $id
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Invoice not found'
            ], 404);
        }
    }

    #[Route('/invoices/{id}/download', name: 'api_customer_invoice_download', methods: ['GET'])]
    public function downloadInvoice(string $id): Response
    {
        try {
            // Mock PDF generation - in production, generate actual PDF
            $pdfContent = "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n2 0 obj\n<<\n/Type /Pages\n/Count 1\n/Kids [3 0 R]\n>>\nendobj\n3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n/Contents 4 0 R\n>>\nendobj\n4 0 obj\n<<\n/Length 44\n>>\nstream\nBT\n/F1 12 Tf\n100 700 Td\n(Invoice {$id}) Tj\nET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f \n0000000010 00000 n \n0000000053 00000 n \n0000000109 00000 n \n0000000158 00000 n \ntrailer\n<<\n/Size 5\n/Root 1 0 R\n>>\nstartxref\n238\n%%EOF";

            $this->logger->info('Invoice download requested', [
                'invoice_id' => $id,
                'customer_id' => $this->getUser()?->getId()
            ]);

            return new Response(
                $pdfContent,
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "attachment; filename=\"invoice-{$id}.pdf\"",
                    'Content-Length' => strlen($pdfContent)
                ]
            );

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate invoice PDF', [
                'error' => $e->getMessage(),
                'invoice_id' => $id
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to generate PDF'
            ], 500);
        }
    }

    #[Route('/payments', name: 'api_customer_payments', methods: ['GET'])]
    public function getPayments(Request $request): JsonResponse
    {
        try {
            // Mock payments data
            $payments = [
                [
                    'id' => 'PAY-001',
                    'paymentId' => 'PAY-001',
                    'invoiceId' => 'INV-2025-001',
                    'date' => '2025-09-11',
                    'amount' => 299.99,
                    'currency' => 'PLN',
                    'method' => 'transfer',
                    'methodLabel' => 'Przelew bankowy',
                    'status' => 'completed',
                    'statusLabel' => 'Zakończona',
                    'transactionId' => 'TXN-123456789'
                ],
                [
                    'id' => 'PAY-002',
                    'paymentId' => 'PAY-002',
                    'invoiceId' => 'INV-2025-002',
                    'date' => '2025-09-12',
                    'amount' => 156.75,
                    'currency' => 'PLN',
                    'method' => 'card',
                    'methodLabel' => 'Karta płatnicza',
                    'status' => 'processing',
                    'statusLabel' => 'W toku',
                    'transactionId' => 'TXN-987654321'
                ]
            ];

            $this->logger->info('Customer payments retrieved', [
                'customer_id' => $this->getUser()?->getId(),
                'payments_count' => count($payments)
            ]);

            return $this->json([
                'success' => true,
                'data' => $payments
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve customer payments', [
                'error' => $e->getMessage(),
                'customer_id' => $this->getUser()?->getId()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve payments'
            ], 500);
        }
    }

    #[Route('/invoices/{id}/pay', name: 'api_customer_pay_invoice', methods: ['POST'])]
    public function initiatePayment(string $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $paymentMethod = $data['method'] ?? 'transfer';

            // Mock payment initiation - integrate with PayNow/Stripe in production
            $paymentUrl = match($paymentMethod) {
                'card' => "https://secure.paynow.pl/payment/{$id}",
                'blik' => "https://secure.paynow.pl/blik/{$id}",
                default => null
            };

            $this->logger->info('Payment initiated', [
                'invoice_id' => $id,
                'customer_id' => $this->getUser()?->getId(),
                'method' => $paymentMethod
            ]);

            return $this->json([
                'success' => true,
                'data' => [
                    'paymentId' => 'PAY-' . uniqid(),
                    'paymentUrl' => $paymentUrl,
                    'status' => 'initiated'
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to initiate payment', [
                'error' => $e->getMessage(),
                'invoice_id' => $id
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to initiate payment'
            ], 500);
        }
    }

    #[Route('/billing/stats', name: 'api_customer_billing_stats', methods: ['GET'])]
    public function getBillingStats(): JsonResponse
    {
        try {
            // Mock billing statistics
            $stats = [
                'totalAmount' => 546.24,
                'paidAmount' => 299.99,
                'pendingAmount' => 156.75,
                'overdueAmount' => 89.50,
                'currency' => 'PLN',
                'invoiceCount' => [
                    'total' => 3,
                    'paid' => 1,
                    'pending' => 1,
                    'overdue' => 1
                ]
            ];

            return $this->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve billing stats', [
                'error' => $e->getMessage(),
                'customer_id' => $this->getUser()?->getId()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve stats'
            ], 500);
        }
    }
}