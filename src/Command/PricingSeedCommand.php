<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Pricing\Entity\AdditionalService;
use App\Domain\Pricing\Entity\AdditionalServicePrice;
use App\Domain\Pricing\Entity\Carrier;
use App\Domain\Pricing\Entity\PricingRule;
use App\Domain\Pricing\Entity\PricingTable;
use App\Domain\Pricing\Entity\PricingZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pricing:seed',
    description: 'Seed the database with sample pricing data (zones, carriers, services, pricing tables)',
)]
class PricingSeedCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force seeding even if data already exists')
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Clear existing pricing data before seeding')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');
        $clear = $input->getOption('clear');

        $io->title('Pricing System Data Seeding');

        // Check if data already exists
        if (!$force && !$clear) {
            $zoneCount = $this->entityManager->getRepository(PricingZone::class)->count([]);
            $carrierCount = $this->entityManager->getRepository(Carrier::class)->count([]);
            
            if ($zoneCount > 0 || $carrierCount > 0) {
                $io->warning('Pricing data already exists. Use --force to add more data or --clear to replace existing data.');
                return Command::FAILURE;
            }
        }

        // Clear existing data if requested
        if ($clear) {
            $io->section('Clearing existing pricing data...');
            $this->clearExistingData($io);
        }

        // Begin transaction for data consistency
        $this->entityManager->getConnection()->beginTransaction();
        
        try {
            $io->section('Creating Pricing Zones...');
            $zones = $this->createPricingZones($io);
            
            // Flush zones first to get IDs
            $this->entityManager->flush();

            $io->section('Creating Carriers...');
            $carriers = $this->createCarriers($io);
            
            // Flush carriers to get IDs
            $this->entityManager->flush();

            $io->section('Creating Additional Services...');
            $this->createAdditionalServices($io, $carriers);
            
            // Flush services to get IDs
            $this->entityManager->flush();

            $io->section('Creating Pricing Tables...');
            $pricingTables = $this->createPricingTables($io, $carriers, $zones);
            
            // Flush pricing tables to get IDs
            $this->entityManager->flush();

            $io->section('Creating Pricing Rules...');
            $this->createPricingRules($io, $pricingTables);

            $io->section('Creating Additional Service Prices...');
            $this->createAdditionalServicePrices($io, $pricingTables);

            // Final flush
            $this->entityManager->flush();
            
            // Commit transaction
            $this->entityManager->getConnection()->commit();

            $io->success('Pricing system seeding completed successfully!');
            $io->table(['Component', 'Count'], [
                ['Zones', count($zones)],
                ['Carriers', count($carriers)],
                ['Pricing Tables', count($pricingTables)],
                ['Additional Services', $this->entityManager->getRepository(AdditionalService::class)->count([])],
            ]);

        } catch (\Exception $e) {
            // Rollback transaction on error
            $this->entityManager->getConnection()->rollback();
            $io->error('Error during seeding: ' . $e->getMessage());
            $io->error('Stack trace: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function clearExistingData(SymfonyStyle $io): void
    {
        try {
            // Clear in correct order to avoid foreign key constraints
            $this->entityManager->createQuery('DELETE FROM ' . AdditionalServicePrice::class)->execute();
            $this->entityManager->createQuery('DELETE FROM ' . PricingRule::class)->execute();
            $this->entityManager->createQuery('DELETE FROM ' . PricingTable::class)->execute();
            $this->entityManager->createQuery('DELETE FROM ' . AdditionalService::class)->execute();
            $this->entityManager->createQuery('DELETE FROM ' . Carrier::class)->execute();
            $this->entityManager->createQuery('DELETE FROM ' . PricingZone::class)->execute();
            
            $this->entityManager->flush();
            
            $io->text('Cleared existing pricing data.');
        } catch (\Exception $e) {
            $io->error('Error clearing existing data: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @return PricingZone[]
     */
    private function createPricingZones(SymfonyStyle $io): array
    {
        $zones = [];
        $zoneData = [
            ['LOCAL', 'Lokalne', PricingZone::ZONE_TYPE_LOCAL, 'Przesyłki lokalne w obrębie miasta', ['PL'], ['/^00-\d{3}$/', '/^01-\d{3}$/']],
            ['NATIONAL', 'Krajowe', PricingZone::ZONE_TYPE_NATIONAL, 'Przesyłki krajowe w Polsce', ['PL'], ['/^\d{2}-\d{3}$/']],
            ['EU_WEST', 'Europa Zachodnia', PricingZone::ZONE_TYPE_INTERNATIONAL, 'Europa Zachodnia (DE, FR, NL, BE)', ['DE', 'FR', 'NL', 'BE', 'AT', 'CH'], null],
            ['EU_EAST', 'Europa Wschodnia', PricingZone::ZONE_TYPE_INTERNATIONAL, 'Europa Wschodnia (CZ, SK, HU)', ['CZ', 'SK', 'HU', 'SI', 'HR'], null],
            ['EU_NORTH', 'Europa Północna', PricingZone::ZONE_TYPE_INTERNATIONAL, 'Kraje nordyckie', ['SE', 'NO', 'DK', 'FI'], null],
            ['WORLDWIDE', 'Świat', PricingZone::ZONE_TYPE_INTERNATIONAL, 'Pozostałe kraje świata', [], null],
        ];

        foreach ($zoneData as [$code, $name, $type, $description, $countries, $patterns]) {
            $zone = new PricingZone($code, $name, $type);
            $zone->setDescription($description);
            $zone->setCountries($countries);
            if ($patterns) {
                $zone->setPostalCodePatterns($patterns);
            }
            
            $this->entityManager->persist($zone);
            $zones[] = $zone;
            $io->text("Created zone: $name ($code)");
        }

        return $zones;
    }

    /**
     * @return Carrier[]
     */
    private function createCarriers(SymfonyStyle $io): array
    {
        $carriers = [];
        $carrierData = [
            [
                'INPOST', 'InPost', 
                'https://inpost.pl/sites/default/files/logo-inpost.svg',
                'https://api-shipx-pl.easypack24.net/v1/',
                ['api_key' => '', 'organization_id' => ''],
                'standard',
                ['LOCAL', 'NATIONAL'],
                30.0,
                [64, 38, 8]
            ],
            [
                'DHL', 'DHL Express',
                'https://www.dhl.com/content/dam/dhl/global/core/images/logos/dhl-logo.svg',
                'https://api-eu.dhl.com/track/shipments',
                ['api_key' => '', 'account_number' => ''],
                'express',
                ['NATIONAL', 'EU_WEST', 'EU_EAST', 'EU_NORTH', 'WORLDWIDE'],
                70.0,
                [120, 80, 80]
            ],
            [
                'UPS', 'UPS',
                'https://www.ups.com/assets/resources/images/ups-shield.svg',
                'https://onlinetools.ups.com/api/',
                ['access_key' => '', 'username' => '', 'password' => ''],
                'standard',
                ['NATIONAL', 'EU_WEST', 'EU_EAST', 'WORLDWIDE'],
                70.0,
                [120, 80, 80]
            ],
            [
                'DPD', 'DPD Polska',
                'https://www.dpd.com/pl/sites/all/themes/dpdtheme/logo.png',
                'https://api.dpd.pl/',
                ['login' => '', 'password' => '', 'fid' => ''],
                'standard',
                ['NATIONAL', 'EU_WEST', 'EU_EAST'],
                31.5,
                [100, 60, 60]
            ],
            [
                'MEEST', 'Meest Express',
                null,
                'https://api.meest-group.com/',
                ['token' => ''],
                'standard',
                ['EU_EAST', 'WORLDWIDE'],
                50.0,
                [120, 80, 80]
            ]
        ];

        foreach ($carrierData as [$code, $name, $logo, $endpoint, $config, $service, $zones, $weight, $dimensions]) {
            $carrier = new Carrier($code, $name);
            $carrier->setLogoUrl($logo);
            $carrier->setApiEndpoint($endpoint);
            $carrier->setApiConfig($config);
            $carrier->setDefaultServiceType($service);
            $carrier->setSupportedZones($zones);
            $carrier->setMaxWeightKg((string)$weight);
            $carrier->setMaxDimensionsCm($dimensions);
            
            $this->entityManager->persist($carrier);
            $carriers[] = $carrier;
            $io->text("Created carrier: $name ($code)");
        }

        return $carriers;
    }

    /**
     * @param Carrier[] $carriers
     */
    private function createAdditionalServices(SymfonyStyle $io, array $carriers): void
    {
        $serviceData = [
            ['COD', 'Pobranie', 'Cash on Delivery service', AdditionalService::SERVICE_TYPE_COD, AdditionalService::PRICING_TYPE_PERCENTAGE, '2.0', '2.0', '100.0', '1.5'],
            ['INSURANCE', 'Ubezpieczenie', 'Insurance service', AdditionalService::SERVICE_TYPE_INSURANCE, AdditionalService::PRICING_TYPE_PERCENTAGE, '1.0', '3.0', '50.0', '0.5'],
            ['PRIORITY', 'Przesyłka priorytetowa', 'Priority delivery', AdditionalService::SERVICE_TYPE_PRIORITY, AdditionalService::PRICING_TYPE_FIXED, '10.0', '5.0', '25.0', null],
            ['SMS', 'SMS Info', 'SMS notifications', AdditionalService::SERVICE_TYPE_SMS, AdditionalService::PRICING_TYPE_FIXED, '1.5', '1.0', '3.0', null],
        ];

        foreach ($carriers as $carrier) {
            foreach ($serviceData as [$code, $name, $desc, $type, $pricing, $default, $min, $max, $percentage]) {
                $service = new AdditionalService($carrier, $code, $name, $type, $pricing);
                $service->setDescription($desc);
                $service->setDefaultPrice($default);
                $service->setMinPrice($min);
                $service->setMaxPrice($max);
                if ($percentage) {
                    $service->setPercentageRate($percentage);
                }
                
                // Set supported zones based on carrier
                $service->setSupportedZones($carrier->getSupportedZones());
                
                $this->entityManager->persist($service);
                $io->text("Created service: $name for {$carrier->getName()}");
            }
        }
    }

    /**
     * @param Carrier[] $carriers
     * @param PricingZone[] $zones
     * @return PricingTable[]
     */
    private function createPricingTables(SymfonyStyle $io, array $carriers, array $zones): array
    {
        $pricingTables = [];
        
        foreach ($carriers as $carrier) {
            foreach ($zones as $zone) {
                // Only create pricing tables for zones that carrier supports
                if (!$carrier->supportsZone($zone->getCode())) {
                    continue;
                }

                $table = new PricingTable($carrier, $zone, $carrier->getDefaultServiceType());
                $table->setName("{$carrier->getName()} - {$zone->getName()}");
                $table->setPricingModel(PricingTable::PRICING_MODEL_WEIGHT);
                $table->setVersion(1);
                $table->setDescription("Standard pricing for {$carrier->getName()} to {$zone->getName()}");
                
                // Set weight and dimension limits based on zone type
                if ($zone->isLocal()) {
                    $table->setMinWeightKg(0.1);
                    $table->setMaxWeightKg(30.0);
                    $table->setVolumetricDivisor(5000);
                } elseif ($zone->isNational()) {
                    $table->setMinWeightKg(0.1);
                    $table->setMaxWeightKg($carrier->getMaxWeightKgFloat() ?? 70.0);
                    $table->setVolumetricDivisor(5000);
                } else {
                    $table->setMinWeightKg(0.1);
                    $table->setMaxWeightKg($carrier->getMaxWeightKgFloat() ?? 70.0);
                    $table->setVolumetricDivisor(5000);
                }
                
                $table->setCurrency('PLN');
                $table->setTaxRate(23.0); // Polish VAT
                
                $this->entityManager->persist($table);
                $pricingTables[] = $table;
                $io->text("Created pricing table: {$table->getName()}");
            }
        }

        return $pricingTables;
    }

    /**
     * @param PricingTable[] $pricingTables
     */
    private function createPricingRules(SymfonyStyle $io, array $pricingTables): void
    {
        foreach ($pricingTables as $table) {
            $zone = $table->getZone();
            $carrier = $table->getCarrier();
            
            if (!$zone) {
                $io->warning("No zone found for table: {$table->getName()}");
                continue;
            }
            
            // Different pricing based on zone type
            if ($zone->isLocal()) {
                $this->createLocalPricingRules($table);
            } elseif ($zone->isNational()) {
                $this->createNationalPricingRules($table);
            } else {
                $this->createInternationalPricingRules($table);
            }
            
            $io->text("Created pricing rules for {$table->getName()}");
        }
    }

    private function createLocalPricingRules(PricingTable $table): void
    {
        $rules = [
            ['0.1', '1.0', '8.50', '0.50'],
            ['1.0', '3.0', '10.00', '1.00'],
            ['3.0', '5.0', '12.00', '1.50'],
            ['5.0', '10.0', '15.00', '2.00'],
            ['10.0', null, '20.00', '3.00'],
        ];

        $sortOrder = 1;
        foreach ($rules as [$weightFrom, $weightTo, $price, $pricePerKg]) {
            $rule = new PricingRule();
            $rule->setPricingTable($table);
            $rule->setName($weightTo ? "Do {$weightTo}kg" : "Powyżej {$weightFrom}kg");
            $rule->setWeightFrom($weightFrom);
            $rule->setWeightTo($weightTo);
            $rule->setCalculationMethod(PricingRule::CALCULATION_METHOD_FIXED);
            $rule->setPrice($price);
            $rule->setPricePerKg($pricePerKg);
            $rule->setSortOrder($sortOrder++);
            
            $this->entityManager->persist($rule);
        }
    }

    private function createNationalPricingRules(PricingTable $table): void
    {
        $rules = [
            ['0.1', '1.0', '12.00', '1.00'],
            ['1.0', '3.0', '15.00', '2.00'],
            ['3.0', '5.0', '18.00', '3.00'],
            ['5.0', '10.0', '25.00', '4.00'],
            ['10.0', '20.0', '35.00', '5.00'],
            ['20.0', null, '50.00', '6.00'],
        ];

        $sortOrder = 1;
        foreach ($rules as [$weightFrom, $weightTo, $price, $pricePerKg]) {
            $rule = new PricingRule();
            $rule->setPricingTable($table);
            $rule->setName($weightTo ? "Do {$weightTo}kg" : "Powyżej {$weightFrom}kg");
            $rule->setWeightFrom($weightFrom);
            $rule->setWeightTo($weightTo);
            $rule->setCalculationMethod(PricingRule::CALCULATION_METHOD_FIXED);
            $rule->setPrice($price);
            $rule->setPricePerKg($pricePerKg);
            $rule->setSortOrder($sortOrder++);
            
            $this->entityManager->persist($rule);
        }
    }

    private function createInternationalPricingRules(PricingTable $table): void
    {
        $baseMultiplier = match($table->getZone()->getCode()) {
            'EU_WEST' => 1.5,
            'EU_EAST' => 1.2,
            'EU_NORTH' => 1.8,
            'WORLDWIDE' => 2.5,
            default => 1.0
        };

        $rules = [
            ['0.1', '1.0', 18.00 * $baseMultiplier, '2.00'],
            ['1.0', '3.0', '25.00', '3.00'],
            ['3.0', '5.0', '35.00', '5.00'],
            ['5.0', '10.0', '50.00', '8.00'],
            ['10.0', '20.0', '80.00', '10.00'],
            ['20.0', null, '120.00', '15.00'],
        ];

        $sortOrder = 1;
        foreach ($rules as [$weightFrom, $weightTo, $price, $pricePerKg]) {
            $rule = new PricingRule();
            $rule->setPricingTable($table);
            $rule->setName($weightTo ? "Do {$weightTo}kg" : "Powyżej {$weightFrom}kg");
            $rule->setWeightFrom($weightFrom);
            $rule->setWeightTo($weightTo);
            $rule->setCalculationMethod(PricingRule::CALCULATION_METHOD_FIXED);
            $rule->setPrice(number_format($price * $baseMultiplier, 2, '.', ''));
            $rule->setPricePerKg(number_format(floatval($pricePerKg) * $baseMultiplier, 2, '.', ''));
            $rule->setSortOrder($sortOrder++);
            
            $this->entityManager->persist($rule);
        }
    }

    /**
     * @param PricingTable[] $pricingTables
     */
    private function createAdditionalServicePrices(SymfonyStyle $io, array $pricingTables): void
    {
        foreach ($pricingTables as $table) {
            $services = $this->entityManager->getRepository(AdditionalService::class)
                ->findBy(['carrier' => $table->getCarrier()]);
                
            foreach ($services as $service) {
                // Create pricing for this service in this table
                $servicePrice = new AdditionalServicePrice($table, $service);
                $servicePrice->setPrice($service->getDefaultPrice());
                $servicePrice->setMinPrice($service->getMinPrice());
                $servicePrice->setMaxPrice($service->getMaxPrice());
                $servicePrice->setPercentageRate($service->getPercentageRate());
                
                $this->entityManager->persist($servicePrice);
            }
            
            $io->text("Created service prices for {$table->getName()}");
        }
    }
}