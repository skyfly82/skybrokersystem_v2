<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Command;

use App\Domain\Courier\Meest\DTO\MeestShipmentRequestDTO;
use App\Domain\Courier\Meest\Enum\MeestShipmentType;
use App\Domain\Courier\Meest\Service\MeestCourierService;
use App\Domain\Courier\Meest\ValueObject\MeestAddress;
use App\Domain\Courier\Meest\ValueObject\MeestParcel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'meest:test',
    description: 'Test MEEST integration'
)]
class MeestTestCommand extends Command
{
    public function __construct(
        private readonly MeestCourierService $meestService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to test (auth|create|track|label)')
            ->addArgument('tracking_number', InputArgument::OPTIONAL, 'Tracking number for track/label actions')
            ->addOption('test-mode', 't', InputOption::VALUE_NONE, 'Use test environment');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');
        $trackingNumber = $input->getArgument('tracking_number');

        $io->title('MEEST Integration Test');

        try {
            match ($action) {
                'auth' => $this->testAuthentication($io),
                'create' => $this->testCreateShipment($io),
                'track' => $this->testTracking($io, $trackingNumber),
                'label' => $this->testLabelGeneration($io, $trackingNumber),
                default => throw new \InvalidArgumentException("Unknown action: {$action}")
            };

            $io->success('Test completed successfully');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Test failed: {$e->getMessage()}");
            $io->text("Stack trace:");
            $io->text($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    private function testAuthentication(SymfonyStyle $io): void
    {
        $io->section('Testing Authentication');

        // Authentication is handled internally by MeestCourierService
        $io->text('Authentication test requires creating a shipment or tracking...');
        $io->text('Use "create" or "track" action to test authentication.');
    }

    private function testCreateShipment(SymfonyStyle $io): void
    {
        $io->section('Testing Shipment Creation');

        $sender = new MeestAddress(
            firstName: 'John',
            lastName: 'Sender',
            phone: '+48123456789',
            email: 'sender@example.com',
            country: 'PL',
            city: 'Warsaw',
            address: 'ul. Testowa 1',
            postalCode: '00-001',
            company: 'Test Company'
        );

        $recipient = new MeestAddress(
            firstName: 'Jane',
            lastName: 'Recipient',
            phone: '+49123456789',
            email: 'recipient@example.com',
            country: 'DE',
            city: 'Berlin',
            address: 'Teststraße 1',
            postalCode: '10115'
        );

        $parcel = new MeestParcel(
            weight: 1.5,
            length: 20.0,
            width: 15.0,
            height: 10.0,
            value: 50.0,
            currency: 'EUR',
            contents: 'Test merchandise'
        );

        $request = new MeestShipmentRequestDTO(
            sender: $sender,
            recipient: $recipient,
            parcel: $parcel,
            shipmentType: MeestShipmentType::STANDARD,
            specialInstructions: 'Test shipment - do not deliver'
        );

        $io->text('Creating test shipment...');

        // Convert to generic DTO for the service
        $genericRequest = new \App\Domain\Courier\DTO\ShipmentRequestDTO();
        $genericRequest->senderName = $sender->getFullName();
        $genericRequest->senderEmail = $sender->email;
        $genericRequest->senderAddress = $sender->address;
        $genericRequest->recipientName = $recipient->getFullName();
        $genericRequest->recipientEmail = $recipient->email;
        $genericRequest->recipientAddress = $recipient->address;
        $genericRequest->weight = $parcel->weight;
        $genericRequest->serviceType = 'standard';
        $genericRequest->specialInstructions = 'Test shipment - do not deliver';

        $response = $this->meestService->createShipment($genericRequest);

        $io->table(['Field', 'Value'], [
            ['Tracking Number', $response->trackingNumber],
            ['Shipment ID', $response->shipmentId],
            ['Status', $response->status],
            ['Cost', $response->cost . ' ' . $response->currency],
            ['Label URL', $response->labelUrl ?? 'N/A'],
            ['Estimated Delivery', $response->estimatedDelivery?->format('Y-m-d H:i:s') ?? 'N/A']
        ]);
    }

    private function testTracking(SymfonyStyle $io, ?string $trackingNumber): void
    {
        if (!$trackingNumber) {
            throw new \InvalidArgumentException('Tracking number is required for tracking test');
        }

        $io->section('Testing Tracking');
        $io->text("Tracking shipment: {$trackingNumber}");

        $trackingDetails = $this->meestService->getTrackingDetails($trackingNumber);

        $io->table(['Field', 'Value'], [
            ['Tracking Number', $trackingDetails->trackingNumber],
            ['Status', $trackingDetails->status],
            ['Status Description', $trackingDetails->statusDescription],
            ['Last Updated', $trackingDetails->lastUpdated->format('Y-m-d H:i:s')],
            ['Estimated Delivery', $trackingDetails->estimatedDelivery?->format('Y-m-d H:i:s') ?? 'N/A'],
            ['Events Count', count($trackingDetails->events)]
        ]);

        if (!empty($trackingDetails->events)) {
            $io->section('Tracking Events');
            $events = array_map(function($event) {
                return [
                    $event['timestamp']->format('Y-m-d H:i:s'),
                    $event['status'],
                    $event['description'],
                    $event['location'] ?? 'N/A'
                ];
            }, array_slice($trackingDetails->events, -5)); // Show last 5 events

            $io->table(['Timestamp', 'Status', 'Description', 'Location'], $events);
        }
    }

    private function testLabelGeneration(SymfonyStyle $io, ?string $trackingNumber): void
    {
        if (!$trackingNumber) {
            throw new \InvalidArgumentException('Tracking number is required for label generation test');
        }

        $io->section('Testing Label Generation');
        $io->text("Generating label for: {$trackingNumber}");

        $labelUrl = $this->meestService->generateLabel($trackingNumber);

        $io->table(['Field', 'Value'], [
            ['Tracking Number', $trackingNumber],
            ['Label URL', $labelUrl]
        ]);

        if (filter_var($labelUrl, FILTER_VALIDATE_URL)) {
            $io->success('Label URL is valid');
        } else {
            $io->warning('Label URL might be base64 encoded data or invalid');
        }
    }
}