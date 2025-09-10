<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Geographical zones seed data migration
 * 
 * Creates 6 main geographical zones with countries and postal code mappings:
 * - LOCAL: Major Polish cities
 * - DOMESTIC: Rest of Poland  
 * - EU_WEST: Western European Union countries
 * - EU_EAST: Eastern European Union countries
 * - EUROPE: Non-EU European countries
 * - WORLD: Rest of the world
 */
final class Version20250909140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Insert geographical zones seed data for courier pricing system';
    }

    public function up(Schema $schema): void
    {
        // Insert 6 main geographical zones with corrected JSON escaping
        $this->addSql("INSERT IGNORE INTO v2_pricing_zones (code, name, description, zone_type, countries, postal_code_patterns, is_active, sort_order, created_at) VALUES 
            ('LOCAL', 'Strefa Lokalna', 'Główne miasta Polski: Warszawa, Kraków, Gdańsk, Wrocław, Poznań, Łódź', 'local', JSON_ARRAY('PL'), JSON_ARRAY('/^0[0-5]\\\\d{3}$/', '/^3[0-4]\\\\d{3}$/', '/^8[0-4]\\\\d{3}$/', '/^5[0-4]\\\\d{3}$/', '/^6[0-2]\\\\d{3}$/', '/^9[0-5]\\\\d{3}$/'), 1, 1, NOW())");
        
        $this->addSql("INSERT IGNORE INTO v2_pricing_zones (code, name, description, zone_type, countries, postal_code_patterns, is_active, sort_order, created_at) VALUES 
            ('DOMESTIC', 'Strefa Krajowa', 'Pozostały obszar Polski', 'national', JSON_ARRAY('PL'), JSON_ARRAY('/^\\\\d{5}$/'), 1, 2, NOW())");
        
        $this->addSql("INSERT IGNORE INTO v2_pricing_zones (code, name, description, zone_type, countries, postal_code_patterns, is_active, sort_order, created_at) VALUES 
            ('EU_WEST', 'Europa Zachodnia', 'Kraje Unii Europejskiej - Europa Zachodnia', 'international', JSON_ARRAY('DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'PT', 'IE', 'LU', 'FI', 'SE', 'DK', 'GB', 'UK'), NULL, 1, 3, NOW())");
        
        $this->addSql("INSERT IGNORE INTO v2_pricing_zones (code, name, description, zone_type, countries, postal_code_patterns, is_active, sort_order, created_at) VALUES 
            ('EU_EAST', 'Europa Wschodnia', 'Kraje Unii Europejskiej - Europa Wschodnia', 'international', JSON_ARRAY('CZ', 'SK', 'HU', 'SI', 'HR', 'BG', 'RO', 'EE', 'LV', 'LT'), NULL, 1, 4, NOW())");
        
        $this->addSql("INSERT IGNORE INTO v2_pricing_zones (code, name, description, zone_type, countries, postal_code_patterns, is_active, sort_order, created_at) VALUES 
            ('EUROPE', 'Europa (spoza UE)', 'Pozostałe kraje europejskie spoza Unii Europejskiej', 'international', JSON_ARRAY('NO', 'CH', 'IS', 'LI', 'AD', 'MC', 'SM', 'VA', 'MT', 'CY', 'RS', 'ME', 'BA', 'MK', 'AL', 'XK', 'MD', 'UA', 'BY', 'RU', 'TR', 'GE', 'AM', 'AZ'), NULL, 1, 5, NOW())");
        
        $this->addSql("INSERT IGNORE INTO v2_pricing_zones (code, name, description, zone_type, countries, postal_code_patterns, is_active, sort_order, created_at) VALUES 
            ('WORLD', 'Świat', 'Wszystkie pozostałe kraje świata', 'international', NULL, NULL, 1, 6, NOW())");
    }

    public function down(Schema $schema): void
    {
        // Remove geographical zones
        $this->addSql("DELETE FROM v2_pricing_zones WHERE code IN ('LOCAL', 'DOMESTIC', 'EU_WEST', 'EU_EAST', 'EUROPE', 'WORLD')");
    }
}