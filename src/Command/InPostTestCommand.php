<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\InPostApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'inpost:test',
    description: 'Test InPost API connectivity and basic operations'
)]
class InPostTestCommand extends Command
{
    public function __construct(
        private readonly InPostApiClient $apiClient
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('test', 't', InputOption::VALUE_OPTIONAL, 'Specific test to run (connection|points|organization|services|create-test|tracking)', 'all')
            ->addOption('postcode', 'p', InputOption::VALUE_OPTIONAL, 'Postcode to filter points (e.g., 00-001)')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of results', '10')
            ->addOption('tracking-number', null, InputOption::VALUE_OPTIONAL, 'Tracking number for testing tracking functionality')
            ->setHelp(
                <<<'EOF'
Test InPost API integration:

<info>php bin/console inpost:test</info>              - Run all tests
<info>php bin/console inpost:test --test=connection</info>   - Test API connection
<info>php bin/console inpost:test --test=points</info>       - Get pickup points
<info>php bin/console inpost:test --test=organization</info> - Get organization info
<info>php bin/console inpost:test --test=points --postcode=00-001 --limit=5</info> - Get 5 points near 00-001

Available tests: connection, points, organization, all
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $test = $input->getOption('test');

        $io->title('InPost API Test Suite');

        // Show configuration
        $config = $this->apiClient->getConfig();
        $io->section('API Configuration');
        $io->definitionList(
            ['API URL' => $config['api_url']],
            ['Organization ID' => $config['organization_id']],
            ['Token Configured' => $config['token_configured'] ? '✅ Yes' : '❌ No']
        );

        try {
            match ($test) {
                'connection' => $this->testConnection($io),
                'points' => $this->testGetPickupPoints($io, $input),
                'organization' => $this->testGetOrganization($io),
                'services' => $this->testGetServices($io),
                'create-test' => $this->testCreateShipment($io),
                'tracking' => $this->testTracking($io, $input),
                'all' => $this->runAllTests($io, $input),
                default => throw new \InvalidArgumentException("Unknown test: {$test}")
            };

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Test failed: " . $e->getMessage());
            if ($output->isVeryVerbose()) {
                $io->text("Stack trace:");
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function testConnection(SymfonyStyle $io): void
    {
        $io->section('🔗 Testing API Connection');
        
        $startTime = microtime(true);
        $connected = $this->apiClient->testConnection();
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        if ($connected) {
            $io->success("✅ API Connection successful! ({$duration}ms)");
        } else {
            $io->error("❌ API Connection failed! ({$duration}ms)");
        }
    }

    private function testGetPickupPoints(SymfonyStyle $io, InputInterface $input): void
    {
        $io->section('📍 Testing Pickup Points API');
        
        $postcode = $input->getOption('postcode');
        $limit = (int) $input->getOption('limit');
        
        $params = [];
        if ($postcode) {
            $params['relative_post_code'] = $postcode;
            $io->text("Filtering by postcode: {$postcode}");
        }
        
        $startTime = microtime(true);
        $points = $this->apiClient->getParcelLockers($params);
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        $pointsCount = count($points);
        $io->success("✅ Retrieved {$pointsCount} pickup points ({$duration}ms)");
        
        if ($pointsCount > 0) {
            $io->text("Showing first {$limit} points:");
            
            // Check if response has items array
            $items = $points['items'] ?? $points;
            $displayPoints = array_slice($items, 0, $limit);
            $rows = [];
            
            foreach ($displayPoints as $point) {
                $rows[] = [
                    $point['name'] ?? 'N/A',
                    $point['address']['line1'] ?? 'N/A',
                    $point['address_details']['post_code'] ?? $point['address']['post_code'] ?? 'N/A',
                    implode(', ', $point['type'] ?? []) ?: 'N/A',
                    $point['status'] ?? 'N/A'
                ];
            }
            
            $io->table(['Name', 'Address', 'Post Code', 'Type', 'Status'], $rows);
            
            // Show additional info about the first point
            if (!empty($displayPoints[0])) {
                $firstPoint = $displayPoints[0];
                $io->text("Example point details:");
                $io->listing([
                    "Name: {$firstPoint['name']}",
                    "Description: {$firstPoint['location_description']}",
                    "Full address: {$firstPoint['address']['line1']}, {$firstPoint['address_details']['city']}",
                    "Functions: " . implode(', ', array_slice($firstPoint['functions'] ?? [], 0, 3))
                ]);
            }
        }
    }

    private function testGetOrganization(SymfonyStyle $io): void
    {
        $io->section('🏢 Testing Organization Info API');
        
        $startTime = microtime(true);
        $organization = $this->apiClient->getOrganization();
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        $io->success("✅ Retrieved organization info ({$duration}ms)");
        
        $io->definitionList(
            ['ID' => $organization['id'] ?? 'N/A'],
            ['Name' => $organization['name'] ?? 'N/A'],
            ['Status' => $organization['status'] ?? 'N/A'],
            ['Type' => $organization['type'] ?? 'N/A']
        );
    }

    private function runAllTests(SymfonyStyle $io, InputInterface $input): void
    {
        $io->text('Running all available tests...');
        
        $this->testConnection($io);
        $this->testGetOrganization($io);
        $this->testGetPickupPoints($io, $input);
        
        $io->success('🎉 All tests completed!');
    }

    private function testGetServices(SymfonyStyle $io): void
    {
        $io->section('📦 Testing Services API');
        
        $startTime = microtime(true);
        $organization = $this->apiClient->getOrganization();
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        $services = $organization['services'] ?? [];
        $carriers = $organization['carriers'] ?? [];
        
        $io->success("✅ Retrieved organization services ({$duration}ms)");
        
        $io->text("Available carriers:");
        $io->listing($carriers);
        
        $io->text("Available services:");
        $servicesList = [];
        foreach ($services as $service) {
            $servicesList[] = $service;
        }
        $io->listing($servicesList);
    }

    private function testCreateShipment(SymfonyStyle $io): void
    {
        $io->section('🚛 Testing Shipment Creation (Mock Data)');
        
        // First get an operating pickup point for the destination
        $io->text('Getting operating pickup points for Warsaw (00-001)...');
        $points = $this->apiClient->getParcelLockers([
            'relative_post_code' => '00-001',
            'status' => 'Operating',
            'type' => 'parcel_locker'
        ]);
        $items = $points['items'] ?? [];
        
        if (empty($items)) {
            $io->error('No operating pickup points found for testing');
            return;
        }
        
        $targetPoint = $items[0];
        $io->success("Selected pickup point: {$targetPoint['name']} - {$targetPoint['location_description']}");
        
        // Prepare test shipment data based on InPost API requirements
        $testShipmentData = [
            'receiver' => [
                'first_name' => 'Jan',
                'last_name' => 'Kowalski',
                'email' => 'jan.kowalski@example.com',
                'phone' => '+48123456789',
                // Add address from the target point for locker delivery
                'address' => [
                    'street' => $targetPoint['address_details']['street'],
                    'building_number' => $targetPoint['address_details']['building_number'],
                    'city' => $targetPoint['address_details']['city'],
                    'post_code' => $targetPoint['address_details']['post_code'],
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
            'service' => 'inpost_locker_standard',
            'reference' => 'TEST-' . date('YmdHis'),
            'comments' => 'Test shipment from API integration',
            // Try using end_point instead of custom_attributes
            'end_point' => $targetPoint['name']
        ];
        
        $io->text('Test shipment data prepared:');
        $io->definitionList(
            ['Service' => $testShipmentData['service']],
            ['Receiver' => "{$testShipmentData['receiver']['first_name']} {$testShipmentData['receiver']['last_name']}"],
            ['End Point' => $testShipmentData['end_point']],
            ['Reference' => $testShipmentData['reference']]
        );
        
        $io->warning('This would create a real shipment in sandbox. Use carefully!');
        
        if ($io->confirm('Do you want to proceed with creating a test shipment?', false)) {
            try {
                $startTime = microtime(true);
                $shipment = $this->apiClient->createShipment($testShipmentData);
                $endTime = microtime(true);
                $duration = round(($endTime - $startTime) * 1000, 2);
                
                $io->success("✅ Shipment created successfully! ({$duration}ms)");
                $io->definitionList(
                    ['Shipment ID' => $shipment['id'] ?? 'N/A'],
                    ['Status' => $shipment['status'] ?? 'N/A'],
                    ['Tracking Number' => $shipment['tracking_number'] ?? 'N/A']
                );
                
            } catch (\Exception $e) {
                $io->error("Failed to create shipment: " . $e->getMessage());
                if (method_exists($e, 'getResponse')) {
                    $io->text("Response: " . $e->getResponse()->getContent(false));
                }
            }
        } else {
            $io->text('Shipment creation cancelled by user.');
        }
    }

    private function testTracking(SymfonyStyle $io, InputInterface $input): void
    {
        $io->section('📦 Testing Shipment Tracking');
        
        $trackingNumber = $input->getOption('tracking-number');
        
        if (!$trackingNumber) {
            $trackingNumber = $io->ask('Enter a tracking number to test (or leave empty to skip)', '');
            
            if (!$trackingNumber) {
                $io->note('No tracking number provided. Skipping tracking test.');
                $io->text('Use: php bin/console inpost:test --test=tracking --tracking-number=YOUR_TRACKING_NUMBER');
                return;
            }
        }
        
        try {
            $startTime = microtime(true);
            $trackingInfo = $this->apiClient->trackShipment($trackingNumber);
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            $io->success("✅ Retrieved tracking information ({$duration}ms)");
            
            $io->definitionList(
                ['Tracking Number' => $trackingInfo['tracking_number'] ?? 'N/A'],
                ['Status' => $trackingInfo['status'] ?? 'N/A'],
                ['Service' => $trackingInfo['service'] ?? 'N/A'],
                ['Created At' => $trackingInfo['created_at'] ?? 'N/A'],
                ['Updated At' => $trackingInfo['updated_at'] ?? 'N/A']
            );
            
            if (!empty($trackingInfo['tracking_events'])) {
                $io->text("Recent tracking events:");
                $events = array_slice($trackingInfo['tracking_events'], 0, 5);
                
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
            
        } catch (\Exception $e) {
            $io->error("Failed to track shipment: " . $e->getMessage());
            
            if (method_exists($e, 'getResponse')) {
                $io->text("Response: " . $e->getResponse()->getContent(false));
            }
        }
    }
}