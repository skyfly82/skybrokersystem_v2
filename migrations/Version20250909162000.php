<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for Pricing Domain tables - creates missing pricing tables
 */
final class Version20250909162000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create missing Pricing Domain tables (additional_services, customer_pricing, promotional_pricing, etc.)';
    }

    public function up(Schema $schema): void
    {
        // Create v2_additional_services table
        $this->addSql('
            CREATE TABLE v2_additional_services (
                id INT AUTO_INCREMENT NOT NULL,
                code VARCHAR(50) NOT NULL COMMENT \'Service code\',
                name VARCHAR(100) NOT NULL COMMENT \'Service name\',
                description LONGTEXT DEFAULT NULL,
                service_type VARCHAR(30) NOT NULL COMMENT \'Type: percentage, fixed, weight_based, value_based\',
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                requires_value TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Whether service requires package value\',
                min_value NUMERIC(12, 2) DEFAULT NULL,
                max_value NUMERIC(12, 2) DEFAULT NULL,
                config JSON DEFAULT NULL COMMENT \'Additional configuration\',
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX UNIQ_ADDITIONAL_SERVICE_CODE (code),
                INDEX IDX_ADDITIONAL_SERVICE_TYPE (service_type),
                INDEX IDX_ADDITIONAL_SERVICE_ACTIVE (is_active),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Create v2_additional_service_prices table
        $this->addSql('
            CREATE TABLE v2_additional_service_prices (
                id INT AUTO_INCREMENT NOT NULL,
                pricing_table_id INT NOT NULL,
                service_id INT NOT NULL,
                price NUMERIC(10, 2) NOT NULL COMMENT \'Service price\',
                percentage_rate NUMERIC(5, 2) DEFAULT NULL COMMENT \'Percentage rate if applicable\',
                min_charge NUMERIC(10, 2) DEFAULT NULL COMMENT \'Minimum charge\',
                max_charge NUMERIC(10, 2) DEFAULT NULL COMMENT \'Maximum charge\',
                weight_threshold_kg NUMERIC(8, 3) DEFAULT NULL,
                value_threshold NUMERIC(12, 2) DEFAULT NULL,
                currency VARCHAR(3) NOT NULL,
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX IDX_A5A76AB7B7A2B881 (pricing_table_id),
                INDEX IDX_A5A76AB7ED5CA9E6 (service_id),
                INDEX IDX_SERVICE_PRICE_ACTIVE (is_active),
                UNIQUE INDEX UNQ_PRICING_SERVICE (pricing_table_id, service_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Create v2_pricing_rules table
        $this->addSql('
            CREATE TABLE v2_pricing_rules (
                id INT AUTO_INCREMENT NOT NULL,
                pricing_table_id INT NOT NULL,
                rule_type VARCHAR(30) NOT NULL COMMENT \'weight, dimension, volumetric, tiered\',
                weight_from_kg NUMERIC(8, 3) DEFAULT NULL,
                weight_to_kg NUMERIC(8, 3) DEFAULT NULL,
                price NUMERIC(10, 2) NOT NULL,
                price_per_kg NUMERIC(10, 2) DEFAULT NULL COMMENT \'Additional price per kg over threshold\',
                dimension_length_cm NUMERIC(8, 2) DEFAULT NULL,
                dimension_width_cm NUMERIC(8, 2) DEFAULT NULL,
                dimension_height_cm NUMERIC(8, 2) DEFAULT NULL,
                volumetric_divisor INT DEFAULT NULL,
                calculation_method VARCHAR(30) DEFAULT \'base\' NOT NULL COMMENT \'base, tiered, progressive\',
                tier_level INT DEFAULT 1 NOT NULL COMMENT \'Tier level for tiered pricing\',
                sort_order INT DEFAULT 0 NOT NULL,
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX IDX_PRICING_RULES_TABLE (pricing_table_id),
                INDEX IDX_PRICING_RULES_TYPE (rule_type),
                INDEX IDX_PRICING_RULES_WEIGHT (weight_from_kg, weight_to_kg),
                INDEX IDX_PRICING_RULES_ACTIVE (is_active),
                INDEX IDX_PRICING_RULES_SORT (sort_order),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Create v2_customer_pricing table
        $this->addSql('
            CREATE TABLE v2_customer_pricing (
                id INT AUTO_INCREMENT NOT NULL,
                customer_id INT NOT NULL,
                carrier_id INT NOT NULL,
                zone_id INT NOT NULL,
                service_type VARCHAR(50) NOT NULL,
                discount_type VARCHAR(20) NOT NULL COMMENT \'percentage, fixed, tiered, volume\',
                discount_value NUMERIC(10, 2) NOT NULL COMMENT \'Discount amount or percentage\',
                min_discount NUMERIC(10, 2) DEFAULT NULL,
                max_discount NUMERIC(10, 2) DEFAULT NULL,
                volume_threshold INT DEFAULT NULL COMMENT \'Monthly volume threshold\',
                volume_discount_rate NUMERIC(5, 2) DEFAULT NULL,
                payment_terms VARCHAR(20) DEFAULT \'net30\' NOT NULL COMMENT \'net15, net30, net60, prepaid\',
                credit_limit NUMERIC(15, 2) DEFAULT NULL,
                tax_type VARCHAR(20) DEFAULT \'standard_vat\' NOT NULL COMMENT \'standard_vat, reverse_charge, vat_exempt\',
                contract_reference VARCHAR(100) DEFAULT NULL,
                effective_from DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                effective_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                auto_renew TINYINT(1) DEFAULT 0 NOT NULL,
                created_by_id INT DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX IDX_CUSTOMER_PRICING_CUSTOMER (customer_id),
                INDEX IDX_CUSTOMER_PRICING_CARRIER (carrier_id),
                INDEX IDX_CUSTOMER_PRICING_ZONE (zone_id),
                INDEX IDX_CUSTOMER_PRICING_ACTIVE (is_active),
                INDEX IDX_CUSTOMER_PRICING_EFFECTIVE (effective_from, effective_until),
                INDEX IDX_CUSTOMER_PRICING_SERVICE (service_type),
                INDEX IDX_7B02E2F9B03A8386 (created_by_id),
                UNIQUE INDEX UNQ_CUSTOMER_CARRIER_ZONE_SERVICE (customer_id, carrier_id, zone_id, service_type),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Create v2_promotional_pricing table
        $this->addSql('
            CREATE TABLE v2_promotional_pricing (
                id INT AUTO_INCREMENT NOT NULL,
                code VARCHAR(50) NOT NULL COMMENT \'Promotion code\',
                name VARCHAR(100) NOT NULL,
                description LONGTEXT DEFAULT NULL,
                promo_type VARCHAR(30) NOT NULL COMMENT \'percentage, fixed, free_shipping, bulk, seasonal\',
                discount_value NUMERIC(10, 2) NOT NULL,
                min_order_value NUMERIC(12, 2) DEFAULT NULL,
                max_discount NUMERIC(10, 2) DEFAULT NULL,
                applicable_carriers JSON DEFAULT NULL COMMENT \'List of carrier IDs\',
                applicable_zones JSON DEFAULT NULL COMMENT \'List of zone IDs\',
                applicable_services JSON DEFAULT NULL COMMENT \'List of service types\',
                target_customer_types JSON DEFAULT NULL COMMENT \'b2b, b2c, new_customer, etc.\',
                target_customer_ids JSON DEFAULT NULL COMMENT \'Specific customer IDs\',
                usage_limit INT DEFAULT NULL COMMENT \'Total usage limit\',
                usage_count INT DEFAULT 0 NOT NULL COMMENT \'Current usage count\',
                customer_usage_limit INT DEFAULT NULL COMMENT \'Per customer usage limit\',
                requires_coupon_code TINYINT(1) DEFAULT 0 NOT NULL,
                is_stackable TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Can be combined with other promotions\',
                priority INT DEFAULT 1 NOT NULL COMMENT \'Priority for stacking\',
                valid_from DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                valid_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                created_by_id INT DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX UNIQ_PROMOTIONAL_CODE (code),
                INDEX IDX_PROMOTIONAL_TYPE (promo_type),
                INDEX IDX_PROMOTIONAL_ACTIVE (is_active),
                INDEX IDX_PROMOTIONAL_VALID (valid_from, valid_until),
                INDEX IDX_PROMOTIONAL_PRIORITY (priority),
                INDEX IDX_31A3EA51B03A8386 (created_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Create v2_customer_pricing_audit table
        $this->addSql('
            CREATE TABLE v2_customer_pricing_audit (
                id INT AUTO_INCREMENT NOT NULL,
                customer_pricing_id INT NOT NULL,
                action VARCHAR(20) NOT NULL COMMENT \'created, updated, deleted, activated, deactivated\',
                old_values JSON DEFAULT NULL COMMENT \'Previous values before change\',
                new_values JSON DEFAULT NULL COMMENT \'New values after change\',
                changed_fields JSON DEFAULT NULL COMMENT \'List of changed field names\',
                change_reason LONGTEXT DEFAULT NULL,
                changed_by_id INT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL COMMENT \'IPv4 or IPv6 address\',
                user_agent LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX IDX_CUSTOMER_PRICING_AUDIT_PRICING (customer_pricing_id),
                INDEX IDX_CUSTOMER_PRICING_AUDIT_ACTION (action),
                INDEX IDX_CUSTOMER_PRICING_AUDIT_DATE (created_at),
                INDEX IDX_9C8FD727896DBBDE (changed_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Add foreign key constraints
        $this->addSql('ALTER TABLE v2_additional_service_prices ADD CONSTRAINT FK_A5A76AB7B7A2B881 FOREIGN KEY (pricing_table_id) REFERENCES v2_pricing_tables (id)');
        $this->addSql('ALTER TABLE v2_additional_service_prices ADD CONSTRAINT FK_A5A76AB7ED5CA9E6 FOREIGN KEY (service_id) REFERENCES v2_additional_services (id)');
        $this->addSql('ALTER TABLE v2_pricing_rules ADD CONSTRAINT FK_6C1E7DA5B7A2B881 FOREIGN KEY (pricing_table_id) REFERENCES v2_pricing_tables (id)');
        $this->addSql('ALTER TABLE v2_customer_pricing ADD CONSTRAINT FK_7B02E2F99395C3F3 FOREIGN KEY (customer_id) REFERENCES v2_customers (id)');
        $this->addSql('ALTER TABLE v2_customer_pricing ADD CONSTRAINT FK_7B02E2F921DFC797 FOREIGN KEY (carrier_id) REFERENCES v2_carriers (id)');
        $this->addSql('ALTER TABLE v2_customer_pricing ADD CONSTRAINT FK_7B02E2F99F2C3FAB FOREIGN KEY (zone_id) REFERENCES v2_pricing_zones (id)');
        $this->addSql('ALTER TABLE v2_customer_pricing ADD CONSTRAINT FK_7B02E2F9B03A8386 FOREIGN KEY (created_by_id) REFERENCES v2_system_users (id)');
        $this->addSql('ALTER TABLE v2_promotional_pricing ADD CONSTRAINT FK_31A3EA51B03A8386 FOREIGN KEY (created_by_id) REFERENCES v2_system_users (id)');
        $this->addSql('ALTER TABLE v2_customer_pricing_audit ADD CONSTRAINT FK_9C8FD727F2C56620 FOREIGN KEY (customer_pricing_id) REFERENCES v2_customer_pricing (id)');
        $this->addSql('ALTER TABLE v2_customer_pricing_audit ADD CONSTRAINT FK_9C8FD727896DBBDE FOREIGN KEY (changed_by_id) REFERENCES v2_system_users (id)');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign key constraints first
        $this->addSql('ALTER TABLE v2_additional_service_prices DROP FOREIGN KEY FK_A5A76AB7B7A2B881');
        $this->addSql('ALTER TABLE v2_additional_service_prices DROP FOREIGN KEY FK_A5A76AB7ED5CA9E6');
        $this->addSql('ALTER TABLE v2_pricing_rules DROP FOREIGN KEY FK_6C1E7DA5B7A2B881');
        $this->addSql('ALTER TABLE v2_customer_pricing DROP FOREIGN KEY FK_7B02E2F99395C3F3');
        $this->addSql('ALTER TABLE v2_customer_pricing DROP FOREIGN KEY FK_7B02E2F921DFC797');
        $this->addSql('ALTER TABLE v2_customer_pricing DROP FOREIGN KEY FK_7B02E2F99F2C3FAB');
        $this->addSql('ALTER TABLE v2_customer_pricing DROP FOREIGN KEY FK_7B02E2F9B03A8386');
        $this->addSql('ALTER TABLE v2_promotional_pricing DROP FOREIGN KEY FK_31A3EA51B03A8386');
        $this->addSql('ALTER TABLE v2_customer_pricing_audit DROP FOREIGN KEY FK_9C8FD727F2C56620');
        $this->addSql('ALTER TABLE v2_customer_pricing_audit DROP FOREIGN KEY FK_9C8FD727896DBBDE');

        // Drop tables
        $this->addSql('DROP TABLE v2_customer_pricing_audit');
        $this->addSql('DROP TABLE v2_promotional_pricing');
        $this->addSql('DROP TABLE v2_customer_pricing');
        $this->addSql('DROP TABLE v2_pricing_rules');
        $this->addSql('DROP TABLE v2_additional_service_prices');
        $this->addSql('DROP TABLE v2_additional_services');
    }
}