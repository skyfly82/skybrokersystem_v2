<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Pricing\Entity\PricingZone;
use App\Domain\Pricing\Repository\PricingZoneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to import or update geographical zones for courier pricing
 * 
 * Usage:
 * php bin/console pricing:zones:import
 * php bin/console pricing:zones:import --update
 * php bin/console pricing:zones:import --force
 */
#[AsCommand(
    name: 'pricing:zones:import',
    description: 'Import geographical zones and mapping data for courier pricing system'
)]
class PricingZonesImportCommand extends Command
{
    private const ZONES_DATA = [
        [
            'code' => PricingZone::ZONE_LOCAL,
            'name' => 'Strefa Lokalna',
            'description' => 'Główne miasta Polski: Warszawa, Kraków, Gdańsk, Wrocław, Poznań, Łódź',
            'zone_type' => PricingZone::ZONE_TYPE_LOCAL,
            'countries' => ['PL'],
            'postal_code_patterns' => [
                '/^0[0-5]\d{3}$/',  // Warsaw area
                '/^3[0-4]\d{3}$/',  // Krakow area
                '/^8[0-4]\d{3}$/',  // Gdansk area
                '/^5[0-4]\d{3}$/',  // Wroclaw area
                '/^6[0-2]\d{3}$/',  // Poznan area
                '/^9[0-5]\d{3}$/',  // Lodz area
            ],
            'sort_order' => 1
        ],
        [
            'code' => PricingZone::ZONE_DOMESTIC,
            'name' => 'Strefa Krajowa',
            'description' => 'Pozostały obszar Polski',
            'zone_type' => PricingZone::ZONE_TYPE_NATIONAL,
            'countries' => ['PL'],
            'postal_code_patterns' => ['/^\d{5}$/'],
            'sort_order' => 2
        ],
        [
            'code' => PricingZone::ZONE_EU_WEST,
            'name' => 'Europa Zachodnia',
            'description' => 'Kraje Unii Europejskiej - Europa Zachodnia',
            'zone_type' => PricingZone::ZONE_TYPE_INTERNATIONAL,
            'countries' => ['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'PT', 'IE', 'LU', 'FI', 'SE', 'DK', 'GB', 'UK'],
            'postal_code_patterns' => null,
            'sort_order' => 3
        ],
        [
            'code' => PricingZone::ZONE_EU_EAST,
            'name' => 'Europa Wschodnia',
            'description' => 'Kraje Unii Europejskiej - Europa Wschodnia',
            'zone_type' => PricingZone::ZONE_TYPE_INTERNATIONAL,
            'countries' => ['CZ', 'SK', 'HU', 'SI', 'HR', 'BG', 'RO', 'EE', 'LV', 'LT'],
            'postal_code_patterns' => null,
            'sort_order' => 4
        ],
        [
            'code' => PricingZone::ZONE_EUROPE,
            'name' => 'Europa (spoza UE)',
            'description' => 'Pozostałe kraje europejskie spoza Unii Europejskiej',
            'zone_type' => PricingZone::ZONE_TYPE_INTERNATIONAL,
            'countries' => [
                'NO', 'CH', 'IS', 'LI', 'AD', 'MC', 'SM', 'VA', 'MT', 'CY',
                'RS', 'ME', 'BA', 'MK', 'AL', 'XK', 'MD', 'UA', 'BY', 'RU',
                'TR', 'GE', 'AM', 'AZ'
            ],
            'postal_code_patterns' => null,
            'sort_order' => 5
        ],
        [
            'code' => PricingZone::ZONE_WORLD,
            'name' => 'Świat',
            'description' => 'Wszystkie pozostałe kraje świata',
            'zone_type' => PricingZone::ZONE_TYPE_INTERNATIONAL,
            'countries' => null,
            'postal_code_patterns' => null,
            'sort_order' => 6
        ]
    ];

    public function __construct(
        private readonly PricingZoneRepository $pricingZoneRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('update', 'u', InputOption::VALUE_NONE, 'Update existing zones')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force import without confirmation')
            ->setHelp(
                'This command imports geographical zones for the courier pricing system.' . PHP_EOL .
                'It creates 6 main zones: LOCAL, DOMESTIC, EU_WEST, EU_EAST, EUROPE, WORLD' . PHP_EOL .
                PHP_EOL .
                'Options:' . PHP_EOL .
                '  --update  Update existing zones if they already exist' . PHP_EOL .
                '  --force   Skip confirmation prompts'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $updateExisting = $input->getOption('update');
        $force = $input->getOption('force');

        $io->title('Geographical Zones Import');
        $io->text('Importing courier pricing geographical zones...');

        // Check existing zones
        $existingZones = $this->pricingZoneRepository->findAll();
        $existingCodes = array_map(fn($zone) => $zone->getCode(), $existingZones);

        if (!empty($existingZones) && !$updateExisting) {
            $io->warning(sprintf('Found %d existing zones: %s', 
                count($existingZones), 
                implode(', ', $existingCodes)
            ));
            
            if (!$force) {
                if (!$io->confirm('Do you want to continue? Use --update to update existing zones.', false)) {
                    $io->info('Import cancelled.');
                    return Command::SUCCESS;
                }
            }
        }

        if (!$force) {
            $io->section('Zones to be imported:');
            $io->table(
                ['Code', 'Name', 'Type', 'Countries Count', 'Sort Order'],
                array_map(fn($zone) => [
                    $zone['code'],
                    $zone['name'],
                    $zone['zone_type'],
                    $zone['countries'] ? count($zone['countries']) : 'All others',
                    $zone['sort_order']
                ], self::ZONES_DATA)
            );

            if (!$io->confirm('Proceed with import?', true)) {
                $io->info('Import cancelled.');
                return Command::SUCCESS;
            }
        }

        // Import zones
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach (self::ZONES_DATA as $zoneData) {
            try {
                $result = $this->importZone($zoneData, $updateExisting);
                
                match ($result) {
                    'created' => $created++,
                    'updated' => $updated++,
                    'skipped' => $skipped++,
                };

                $io->text(sprintf(
                    '%s: %s (%s)',
                    ucfirst($result),
                    $zoneData['code'],
                    $zoneData['name']
                ));

            } catch (\Exception $e) {
                $io->error(sprintf(
                    'Error importing zone %s: %s',
                    $zoneData['code'],
                    $e->getMessage()
                ));
                return Command::FAILURE;
            }
        }

        // Flush changes
        $this->entityManager->flush();

        $io->success(sprintf(
            'Import completed! Created: %d, Updated: %d, Skipped: %d',
            $created,
            $updated,
            $skipped
        ));

        // Display final statistics
        $this->displayZoneStatistics($io);

        return Command::SUCCESS;
    }

    private function importZone(array $zoneData, bool $updateExisting): string
    {
        $existingZone = $this->pricingZoneRepository->findOneBy(['code' => $zoneData['code']]);

        if ($existingZone) {
            if (!$updateExisting) {
                return 'skipped';
            }

            // Update existing zone
            $existingZone->setName($zoneData['name'])
                ->setDescription($zoneData['description'])
                ->setZoneType($zoneData['zone_type'])
                ->setCountries($zoneData['countries'])
                ->setPostalCodePatterns($zoneData['postal_code_patterns'])
                ->setSortOrder($zoneData['sort_order']);

            return 'updated';
        }

        // Create new zone
        $zone = new PricingZone(
            $zoneData['code'],
            $zoneData['name'],
            $zoneData['zone_type']
        );

        $zone->setDescription($zoneData['description'])
            ->setCountries($zoneData['countries'])
            ->setPostalCodePatterns($zoneData['postal_code_patterns'])
            ->setSortOrder($zoneData['sort_order']);

        $this->entityManager->persist($zone);

        return 'created';
    }

    private function displayZoneStatistics(SymfonyStyle $io): void
    {
        $zones = $this->pricingZoneRepository->findBy([], ['sortOrder' => 'ASC']);

        $io->section('Current Zones Summary:');
        
        $tableRows = [];
        foreach ($zones as $zone) {
            $countries = $zone->getCountries();
            $countriesText = $countries ? implode(', ', array_slice($countries, 0, 3)) : 'All others';
            if ($countries && count($countries) > 3) {
                $countriesText .= sprintf(' (+%d more)', count($countries) - 3);
            }

            $tableRows[] = [
                $zone->getCode(),
                $zone->getName(),
                $zone->getZoneType(),
                $countriesText,
                $zone->isActive() ? 'Yes' : 'No',
                $zone->getSortOrder()
            ];
        }

        $io->table(
            ['Code', 'Name', 'Type', 'Countries', 'Active', 'Order'],
            $tableRows
        );

        $io->text(sprintf('Total zones: %d', count($zones)));
    }
}