<?php

declare(strict_types=1);

namespace App\Command;

use App\Courier\InPost\Service\InPostShipmentService;
use App\Courier\InPost\DTO\InPostShipmentRequestDTO;
use App\Courier\InPost\Exception\InPostIntegrationException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'inpost:shipment',
    description: 'Create, manage and test InPost shipments'
)]
class InPostShipmentCommand extends Command
{
    public function __construct(
        private readonly InPostShipmentService $shipmentService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform (create|label|status|points)')
            ->addOption('receiver-name', null, InputOption::VALUE_OPTIONAL, 'Receiver full name', 'Jan Kowalski')
            ->addOption('receiver-email', null, InputOption::VALUE_OPTIONAL, 'Receiver email', 'jan.kowalski@example.com')
            ->addOption('receiver-phone', null, InputOption::VALUE_OPTIONAL, 'Receiver phone', '+48123456789')
            ->addOption('post-code', null, InputOption::VALUE_OPTIONAL, 'Post code for pickup points', '00-001')
            ->addOption('target-point', null, InputOption::VALUE_OPTIONAL, 'Target pickup point code')
            ->addOption('service', null, InputOption::VALUE_OPTIONAL, 'InPost service type', 'inpost_locker_standard')
            ->addOption('reference', null, InputOption::VALUE_OPTIONAL, 'Shipment reference')
            ->addOption('shipment-id', null, InputOption::VALUE_OPTIONAL, 'Shipment ID for label/status operations')
            ->addOption('tracking-number', null, InputOption::VALUE_OPTIONAL, 'Tracking number for status operations')
            ->addOption('save-label', null, InputOption::VALUE_OPTIONAL, 'Save label to file path')
            ->setHelp(
                <<<'EOF'
Create and manage InPost shipments:

<info>Create shipment:</info>
  php bin/console inpost:shipment create --post-code=00-001 --receiver-name="Jan Kowalski"

<info>Get pickup points:</info>
  php bin/console inpost:shipment points --post-code=00-001

<info>Get shipment label:</info>
  php bin/console inpost:shipment label --shipment-id=12345 --save-label=/tmp/label.pdf

<info>Update shipment status:</info>
  php bin/console inpost:shipment status --tracking-number=ABC123456789

<info>Examples:</info>
  php bin/console inpost:shipment create --post-code=00-001
  php bin/console inpost:shipment points --post-code=00-030
  php bin/console inpost:shipment label --shipment-id=12345
  php bin/console inpost:shipment status --tracking-number=ABC123
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        $io->title("InPost Shipment Manager: {$action}");

        try {
            match ($action) {
                'create' => $this->createShipment($io, $input),
                'points' => $this->getPickupPoints($io, $input),
                'label' => $this->getShipmentLabel($io, $input),
                'status' => $this->updateShipmentStatus($io, $input),
                default => throw new \InvalidArgumentException("Unknown action: {$action}")
            };

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Command failed: " . $e->getMessage());
            if ($output->isVeryVerbose()) {
                $io->text("Stack trace:");
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function createShipment(SymfonyStyle $io, InputInterface $input): void
    {
        $io->section('🚛 Creating InPost Shipment');
        
        $postCode = $input->getOption('post-code');
        $receiverName = $input->getOption('receiver-name');
        $receiverEmail = $input->getOption('receiver-email');
        $receiverPhone = $input->getOption('receiver-phone');
        $targetPoint = $input->getOption('target-point');
        $service = $input->getOption('service');
        $reference = $input->getOption('reference') ?? 'CLI-' . date('YmdHis');

        // Get target pickup point if not specified
        if (!$targetPoint) {
            $io->text("Using sandbox test point for {$postCode}...");
            
            // Use known sandbox test points
            $testPoints = ['KRA010', 'KRA012', 'WAR01M'];
            $targetPoint = $testPoints[0]; // Use KRA010 as default
            
            $io->success("Selected sandbox test point: {$targetPoint}");
            $io->note("Note: Using sandbox test points. For production, use real pickup points from the API.");
        }

        // Prepare shipment data
        $names = explode(' ', $receiverName, 2);
        $firstName = $names[0];
        $lastName = $names[1] ?? '';

        $shipmentData = [
            'receiver' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $receiverEmail,
                'phone' => $receiverPhone,
                'address' => [
                    'street' => 'pl. Powstańców Warszawy',
                    'building_number' => '2A',
                    'city' => 'Warszawa',
                    'post_code' => '00-030',
                    'country_code' => 'PL'
                ]
            ],
            'parcels' => [
                [
                    'template' => 'small',
                    'dimensions' => [
                        'length' => 10,
                        'width' => 8,
                        'height' => 6
                    ],
                    'weight' => [
                        'amount' => 0.5
                    ]
                ]
            ],
            'service' => $service,
            'reference' => $reference,
            'comments' => 'Created via CLI command',
            'custom_attributes' => [
                'target_point' => $targetPoint
            ]
        ];

        $io->definitionList(
            ['Service' => $service],
            ['Receiver' => $receiverName],
            ['Email' => $receiverEmail],
            ['Phone' => $receiverPhone],
            ['Target Point' => $targetPoint],
            ['Reference' => $reference]
        );

        if (!$io->confirm('Create this shipment?', true)) {
            $io->text('Shipment creation cancelled.');
            return;
        }

        try {
            $request = new InPostShipmentRequestDTO($shipmentData);
            $response = $this->shipmentService->createShipment($request);

            $io->success('✅ Shipment created successfully!');
            
            $io->definitionList(
                ['Shipment ID' => $response->getId()],
                ['Tracking Number' => $response->getTrackingNumber()],
                ['Status' => $response->getStatus()],
                ['Created At' => $response->getCreatedAt()]
            );

            $io->note([
                'Save these details for further operations:',
                "Shipment ID: {$response->getId()}",
                "Tracking Number: {$response->getTrackingNumber()}"
            ]);

        } catch (InPostIntegrationException $e) {
            $io->error('Failed to create shipment: ' . $e->getMessage());
        }
    }

    private function getPickupPoints(SymfonyStyle $io, InputInterface $input): void
    {
        $io->section('📍 Getting Pickup Points');
        
        $postCode = $input->getOption('post-code');
        
        if (!$postCode) {
            $postCode = $io->ask('Enter post code', '00-001');
        }

        $points = $this->shipmentService->getPickupPoints($postCode, 10);
        
        if (empty($points)) {
            $io->error("No pickup points found for {$postCode}");
            return;
        }

        $io->success("Found " . count($points) . " pickup points for {$postCode}");

        $rows = [];
        foreach ($points as $point) {
            $rows[] = [
                $point['name'],
                $point['location_description'] ?? 'N/A',
                $point['address']['line1'] ?? 'N/A',
                $point['status'],
                implode(', ', array_slice($point['type'] ?? [], 0, 2))
            ];
        }

        $io->table(['Code', 'Description', 'Address', 'Status', 'Type'], $rows);
    }

    private function getShipmentLabel(SymfonyStyle $io, InputInterface $input): void
    {
        $io->section('📄 Getting Shipment Label');
        
        $shipmentId = $input->getOption('shipment-id');
        $savePath = $input->getOption('save-label');
        
        if (!$shipmentId) {
            $shipmentId = $io->ask('Enter shipment ID');
        }

        if (!$shipmentId) {
            $io->error('Shipment ID is required');
            return;
        }

        try {
            $io->text("Retrieving label for shipment {$shipmentId}...");
            
            $labelPdf = $this->shipmentService->getShipmentLabel($shipmentId);
            
            $io->success('✅ Label retrieved successfully!');
            $io->text('Label size: ' . number_format(strlen($labelPdf)) . ' bytes');

            if ($savePath) {
                file_put_contents($savePath, $labelPdf);
                $io->success("Label saved to: {$savePath}");
            } else {
                $defaultPath = "/tmp/inpost-label-{$shipmentId}.pdf";
                file_put_contents($defaultPath, $labelPdf);
                $io->success("Label saved to: {$defaultPath}");
            }

        } catch (InPostIntegrationException $e) {
            $io->error('Failed to get label: ' . $e->getMessage());
        }
    }

    private function updateShipmentStatus(SymfonyStyle $io, InputInterface $input): void
    {
        $io->section('📦 Updating Shipment Status');
        
        $trackingNumber = $input->getOption('tracking-number');
        
        if (!$trackingNumber) {
            $trackingNumber = $io->ask('Enter tracking number');
        }

        if (!$trackingNumber) {
            $io->error('Tracking number is required');
            return;
        }

        try {
            $io->text("Updating status for tracking number {$trackingNumber}...");
            
            $result = $this->shipmentService->updateShipmentStatus($trackingNumber);
            
            if ($result['updated']) {
                $io->success('✅ Status updated successfully!');
                
                $io->definitionList(
                    ['Old Status' => $result['old_status']],
                    ['New Status' => $result['new_status']]
                );
                
                if (!empty($result['tracking_data']['tracking_events'])) {
                    $io->text('Recent tracking events:');
                    
                    $events = array_slice($result['tracking_data']['tracking_events'], 0, 3);
                    $rows = [];
                    
                    foreach ($events as $event) {
                        $rows[] = [
                            $event['datetime'] ?? 'N/A',
                            $event['status'] ?? 'N/A',
                            $event['location'] ?? 'N/A'
                        ];
                    }
                    
                    $io->table(['Date/Time', 'Status', 'Location'], $rows);
                }
            } else {
                $io->warning('Status not updated: ' . ($result['message'] ?? 'Unknown reason'));
            }

        } catch (InPostIntegrationException $e) {
            $io->error('Failed to update status: ' . $e->getMessage());
        }
    }
}