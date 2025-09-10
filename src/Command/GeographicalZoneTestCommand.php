<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Pricing\Service\GeographicalZoneService;
use App\Domain\Pricing\Service\PostalCodeMapper;
use App\Domain\Pricing\Service\CountryZoneMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to test geographical zone mapping services
 * 
 * Usage:
 * php bin/console pricing:zones:test
 * php bin/console pricing:zones:test --detailed
 */
#[AsCommand(
    name: 'pricing:zones:test',
    description: 'Test geographical zone mapping services with sample data'
)]
class GeographicalZoneTestCommand extends Command
{
    private const TEST_POSTAL_CODES = [
        // Polish postal codes
        ['00001', 'PL', 'Warsaw - should be LOCAL'],
        ['02001', 'PL', 'Warsaw - should be LOCAL'],
        ['30001', 'PL', 'Krakow - should be LOCAL'],
        ['80001', 'PL', 'Gdansk - should be LOCAL'],
        ['50001', 'PL', 'Wroclaw - should be LOCAL'],
        ['60001', 'PL', 'Poznan - should be LOCAL'],
        ['90001', 'PL', 'Lodz - should be LOCAL'],
        ['25001', 'PL', 'Other Poland - should be DOMESTIC'],
        ['70001', 'PL', 'Other Poland - should be DOMESTIC'],
    ];

    private const TEST_COUNTRIES = [
        ['PL', 'Poland - should be DOMESTIC'],
        ['DE', 'Germany - should be EU_WEST'],
        ['FR', 'France - should be EU_WEST'],
        ['GB', 'United Kingdom - should be EU_WEST'],
        ['CZ', 'Czech Republic - should be EU_EAST'],
        ['SK', 'Slovakia - should be EU_EAST'],
        ['HU', 'Hungary - should be EU_EAST'],
        ['NO', 'Norway - should be EUROPE'],
        ['CH', 'Switzerland - should be EUROPE'],
        ['RU', 'Russia - should be WORLD'],
        ['US', 'United States - should be WORLD'],
        ['JP', 'Japan - should be WORLD'],
        ['AU', 'Australia - should be WORLD'],
    ];

    public function __construct(
        private readonly GeographicalZoneService $geographicalZoneService,
        private readonly PostalCodeMapper $postalCodeMapper,
        private readonly CountryZoneMapper $countryZoneMapper
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('detailed', 'd', InputOption::VALUE_NONE, 'Show detailed information for each test')
            ->setHelp(
                'This command tests geographical zone mapping services with sample data.' . PHP_EOL .
                'It tests postal code mapping, country mapping, and the main geographical zone service.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $detailed = $input->getOption('detailed');

        $io->title('Geographical Zone Services Test');

        // Test postal code mapping
        $this->testPostalCodeMapping($io, $detailed);

        // Test country mapping
        $this->testCountryMapping($io, $detailed);

        // Test geographical zone service
        $this->testGeographicalZoneService($io, $detailed);

        // Test additional features
        $this->testAdditionalFeatures($io, $detailed);

        $io->success('All geographical zone tests completed!');

        return Command::SUCCESS;
    }

    private function testPostalCodeMapping(SymfonyStyle $io, bool $detailed): void
    {
        $io->section('Testing Postal Code Mapping');

        $results = [];
        foreach (self::TEST_POSTAL_CODES as [$postalCode, $country, $description]) {
            $zone = $this->postalCodeMapper->getZoneByPostalCode($postalCode, $country);
            $info = $this->postalCodeMapper->getPostalCodeInfo($postalCode, $country);
            
            $results[] = [
                'input' => sprintf('%s (%s)', $postalCode, $country),
                'expected' => $description,
                'zone' => $zone ?? 'NULL',
                'valid' => $this->postalCodeMapper->isValidPostalCode($postalCode, $country) ? 'Yes' : 'No',
                'info' => $detailed ? json_encode($info, JSON_PRETTY_PRINT) : 'N/A'
            ];
        }

        if ($detailed) {
            foreach ($results as $result) {
                $io->text(sprintf('<info>%s</info> → Zone: <comment>%s</comment>', 
                    $result['input'], 
                    $result['zone']
                ));
                $io->text($result['expected']);
                $io->text('Valid: ' . $result['valid']);
                if ($result['info'] !== 'N/A') {
                    $io->text('Details: ' . $result['info']);
                }
                $io->newLine();
            }
        } else {
            $io->table(
                ['Input', 'Zone', 'Expected', 'Valid'],
                array_map(fn($r) => [
                    $r['input'],
                    $r['zone'],
                    $r['expected'],
                    $r['valid']
                ], $results)
            );
        }
    }

    private function testCountryMapping(SymfonyStyle $io, bool $detailed): void
    {
        $io->section('Testing Country Mapping');

        $results = [];
        foreach (self::TEST_COUNTRIES as [$country, $description]) {
            $zone = $this->countryZoneMapper->getZoneByCountry($country);
            $info = $this->countryZoneMapper->getCountryInfo($country);
            $isEu = $this->countryZoneMapper->isEuCountry($country);
            $isEurope = $this->countryZoneMapper->isEuropeanCountry($country);
            $difficulty = $this->countryZoneMapper->getShippingDifficulty($country);
            
            $results[] = [
                'country' => $country,
                'zone' => $zone ?? 'NULL',
                'expected' => $description,
                'is_eu' => $isEu ? 'Yes' : 'No',
                'is_europe' => $isEurope ? 'Yes' : 'No',
                'difficulty' => $difficulty,
                'info' => $detailed ? $info : null
            ];
        }

        if ($detailed) {
            foreach ($results as $result) {
                $io->text(sprintf('<info>%s</info> → Zone: <comment>%s</comment>', 
                    $result['country'], 
                    $result['zone']
                ));
                $io->text($result['expected']);
                $io->text(sprintf('EU: %s, Europe: %s, Difficulty: %d/10',
                    $result['is_eu'],
                    $result['is_europe'],
                    $result['difficulty']
                ));
                if ($result['info']) {
                    $io->text('Details: ' . json_encode($result['info'], JSON_PRETTY_PRINT));
                }
                $io->newLine();
            }
        } else {
            $io->table(
                ['Country', 'Zone', 'EU', 'Europe', 'Difficulty', 'Expected'],
                array_map(fn($r) => [
                    $r['country'],
                    $r['zone'],
                    $r['is_eu'],
                    $r['is_europe'],
                    $r['difficulty'] . '/10',
                    $r['expected']
                ], $results)
            );
        }
    }

    private function testGeographicalZoneService(SymfonyStyle $io, bool $detailed): void
    {
        $io->section('Testing Geographical Zone Service');

        // Test postal code zone detection
        $io->text('<comment>Testing postal code zone detection:</comment>');
        $testCodes = ['00001', '30001', '25001', '80001'];
        
        foreach ($testCodes as $postalCode) {
            $zone = $this->geographicalZoneService->getZoneByPostalCode($postalCode, 'PL');
            $io->text(sprintf('  %s → %s', $postalCode, $zone ? $zone->getCode() : 'NULL'));
        }

        // Test country zone detection
        $io->text('<comment>Testing country zone detection:</comment>');
        $testCountries = ['PL', 'DE', 'CZ', 'NO', 'US'];
        
        foreach ($testCountries as $country) {
            $zone = $this->geographicalZoneService->getZoneByCountry($country);
            $io->text(sprintf('  %s → %s', $country, $zone ? $zone->getCode() : 'NULL'));
        }

        // Test coordinates
        $io->text('<comment>Testing coordinate-based zone detection:</comment>');
        $testCoordinates = [
            [52.2297, 21.0122, 'Warsaw'],
            [50.0647, 19.9450, 'Krakow'],
            [51.5074, -0.1278, 'London'],
            [40.7128, -74.0060, 'New York']
        ];

        foreach ($testCoordinates as [$lat, $lng, $city]) {
            $zone = $this->geographicalZoneService->getZoneByCoordinates($lat, $lng);
            $io->text(sprintf('  %s (%.4f, %.4f) → %s', 
                $city, $lat, $lng, $zone ? $zone->getCode() : 'NULL'));
        }

        // Test distance calculation
        if ($detailed) {
            $io->text('<comment>Testing distance calculation:</comment>');
            $distance = $this->geographicalZoneService->calculateDistance(
                52.2297, 21.0122, // Warsaw
                50.0647, 19.9450   // Krakow
            );
            $io->text(sprintf('  Warsaw → Krakow: %.2f km', $distance));
        }
    }

    private function testAdditionalFeatures(SymfonyStyle $io, bool $detailed): void
    {
        $io->section('Testing Additional Features');

        // Test zone priority
        $io->text('<comment>Zone priorities:</comment>');
        $zones = ['LOCAL', 'DOMESTIC', 'EU_WEST', 'EU_EAST', 'EUROPE', 'WORLD'];
        foreach ($zones as $zone) {
            $priority = $this->countryZoneMapper->getZonePriority($zone);
            $io->text(sprintf('  %s: Priority %d', $zone, $priority));
        }

        // Test local zone ranges
        if ($detailed) {
            $io->text('<comment>Local zone postal code ranges:</comment>');
            $ranges = $this->postalCodeMapper->getLocalZoneRanges();
            foreach ($ranges as $city => $range) {
                $io->text(sprintf('  %s: %s - %s', 
                    ucfirst($city), $range[0], $range[1]));
            }
        }

        // Test all active zones
        $io->text('<comment>All active zones:</comment>');
        $allZones = $this->geographicalZoneService->getAllActiveZones();
        foreach ($allZones as $zone) {
            $countries = $zone->getCountries();
            $countryCount = $countries ? count($countries) : 'All others';
            $io->text(sprintf('  %s: %s (%s countries)', 
                $zone->getCode(), $zone->getName(), $countryCount));
        }

        // Test delivery time estimates
        if ($detailed) {
            $io->text('<comment>Delivery time estimates:</comment>');
            $testData = [
                ['LOCAL', 'inpost'],
                ['DOMESTIC', 'dhl'],
                ['EU_WEST', 'dhl'],
                ['WORLD', 'dhl']
            ];

            foreach ($testData as [$zone, $carrier]) {
                $deliveryTime = $this->geographicalZoneService->getDeliveryTime($zone, $carrier);
                if ($deliveryTime) {
                    $io->text(sprintf('  %s via %s: %d-%d %s',
                        $zone, $carrier,
                        $deliveryTime['min'],
                        $deliveryTime['max'],
                        $deliveryTime['unit']
                    ));
                }
            }
        }
    }
}