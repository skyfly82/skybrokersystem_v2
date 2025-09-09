<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Comprehensive Courier Pricing System Database Schema
 * 
 * This migration creates a complete pricing system supporting:
 * - Multi-carrier pricing (InPost, DHL, UPS, DPD, Meest)
 * - Geographic zones (local, national, international)
 * - Weight and dimensional pricing tiers
 * - Additional services (COD, insurance, SMS notifications)
 * - B2B/B2C customer-specific pricing
 * - Promotional pricing and volume discounts
 * - Complete audit trail for price changes
 */
final class Version20250909150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create comprehensive courier pricing system tables with multi-carrier support, geographic zones, customer-specific pricing, promotions and audit trail';
    }

    public function up(Schema $schema): void
    {
        // 1. PRICING ZONES - Geographic zones for pricing differentiation
        $this->addSql('CREATE TABLE v2_pricing_zones (
            id INT AUTO_INCREMENT NOT NULL,
            code VARCHAR(10) NOT NULL COMMENT "Unique zone code (LOCAL, NAT_PL, EU, WORLD)",
            name VARCHAR(100) NOT NULL COMMENT "Human-readable zone name",
            description TEXT DEFAULT NULL COMMENT "Detailed zone description",
            zone_type VARCHAR(20) NOT NULL COMMENT "Zone classification",
            countries JSON DEFAULT NULL COMMENT "List of ISO country codes in this zone",
            postal_code_patterns JSON DEFAULT NULL COMMENT "Postal code patterns for automatic zone detection",
            is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT "Whether zone is currently active",
            sort_order INT NOT NULL DEFAULT 0 COMMENT "Display order for zones",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE INDEX UNIQ_ZONE_CODE (code),
            INDEX IDX_ZONE_TYPE (zone_type),
            INDEX IDX_ZONE_ACTIVE (is_active),
            INDEX IDX_ZONE_SORT (sort_order),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB COMMENT = "Geographic zones for courier pricing"');

        // 2. CARRIERS - Courier service providers
        $this->addSql('CREATE TABLE v2_carriers (
            id INT AUTO_INCREMENT NOT NULL,
            code VARCHAR(20) NOT NULL COMMENT "Unique carrier code (INPOST, DHL, UPS, DPD, MEEST)",
            name VARCHAR(100) NOT NULL COMMENT "Carrier display name",
            logo_url VARCHAR(255) DEFAULT NULL COMMENT "URL to carrier logo",
            api_endpoint VARCHAR(255) DEFAULT NULL COMMENT "API endpoint for integration",
            api_config JSON DEFAULT NULL COMMENT "API configuration parameters",
            default_service_type VARCHAR(50) DEFAULT NULL COMMENT "Default service type for this carrier",
            supported_zones JSON NOT NULL COMMENT "List of supported zone codes",
            max_weight_kg DECIMAL(8,3) DEFAULT NULL COMMENT "Maximum package weight in kg",
            max_dimensions_cm JSON DEFAULT NULL COMMENT "Maximum dimensions [length, width, height] in cm",
            is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT "Whether carrier is currently active",
            sort_order INT NOT NULL DEFAULT 0 COMMENT "Display order for carriers",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE INDEX UNIQ_CARRIER_CODE (code),
            INDEX IDX_CARRIER_ACTIVE (is_active),
            INDEX IDX_CARRIER_SORT (sort_order),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB COMMENT = "Courier service providers"');

        // 3. PRICING TABLES - Main pricing structure per carrier and zone
        $this->addSql('CREATE TABLE v2_pricing_tables (
            id INT AUTO_INCREMENT NOT NULL,
            carrier_id INT NOT NULL COMMENT "Reference to carrier",
            zone_id INT NOT NULL COMMENT "Reference to pricing zone",
            service_type VARCHAR(50) NOT NULL COMMENT "Service type (standard, express, economy)",
            name VARCHAR(100) NOT NULL COMMENT "Pricing table name",
            description TEXT DEFAULT NULL COMMENT "Detailed description of pricing table",
            currency VARCHAR(3) NOT NULL DEFAULT "PLN" COMMENT "ISO currency code",
            
            -- Weight-based pricing tiers
            weight_tiers JSON NOT NULL COMMENT "Weight tiers with prices [{max_kg, price_base, price_per_kg}]",
            
            -- Dimensional pricing
            max_length_cm INT DEFAULT NULL COMMENT "Maximum length in cm",
            max_width_cm INT DEFAULT NULL COMMENT "Maximum width in cm", 
            max_height_cm INT DEFAULT NULL COMMENT "Maximum height in cm",
            dimensional_weight_divisor INT DEFAULT 5000 COMMENT "Divisor for dimensional weight calculation",
            oversized_surcharge DECIMAL(10,2) DEFAULT NULL COMMENT "Surcharge for oversized packages",
            
            -- Base pricing configuration
            minimum_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT "Minimum charge for any shipment",
            fuel_surcharge_percent DECIMAL(5,2) DEFAULT NULL COMMENT "Fuel surcharge percentage",
            
            -- Delivery timeframes
            estimated_delivery_days_min INT DEFAULT NULL COMMENT "Minimum delivery days",
            estimated_delivery_days_max INT DEFAULT NULL COMMENT "Maximum delivery days",
            
            -- Status and metadata
            effective_from DATE NOT NULL COMMENT "Date when pricing becomes effective",
            effective_to DATE DEFAULT NULL COMMENT "Date when pricing expires",
            is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT "Whether pricing table is active",
            is_public BOOLEAN NOT NULL DEFAULT TRUE COMMENT "Whether pricing is publicly available",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX IDX_CARRIER (carrier_id),
            INDEX IDX_ZONE (zone_id),
            INDEX IDX_SERVICE_TYPE (service_type),
            INDEX IDX_EFFECTIVE_DATES (effective_from, effective_to),
            INDEX IDX_ACTIVE_PUBLIC (is_active, is_public),
            UNIQUE INDEX UNIQ_CARRIER_ZONE_SERVICE (carrier_id, zone_id, service_type, effective_from),
            
            FOREIGN KEY (carrier_id) REFERENCES v2_carriers (id) ON DELETE CASCADE,
            FOREIGN KEY (zone_id) REFERENCES v2_pricing_zones (id) ON DELETE CASCADE,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB COMMENT = "Main pricing tables per carrier and zone"');

        // 4. ADDITIONAL SERVICES - Extra services pricing (COD, insurance, SMS)
        $this->addSql('CREATE TABLE v2_additional_services (
            id INT AUTO_INCREMENT NOT NULL,
            carrier_id INT NOT NULL COMMENT "Reference to carrier",
            service_code VARCHAR(50) NOT NULL COMMENT "Service code (COD, INSURANCE, SMS, SATURDAY_DELIVERY)",
            service_name VARCHAR(100) NOT NULL COMMENT "Human-readable service name",
            description TEXT DEFAULT NULL COMMENT "Service description",
            
            -- Pricing structure
            pricing_type VARCHAR(20) NOT NULL COMMENT "How the service is priced",
            fixed_price DECIMAL(10,2) DEFAULT NULL COMMENT "Fixed price for the service",
            percentage_rate DECIMAL(5,2) DEFAULT NULL COMMENT "Percentage of shipment value",
            min_charge DECIMAL(10,2) DEFAULT NULL COMMENT "Minimum charge for percentage-based pricing",
            max_charge DECIMAL(10,2) DEFAULT NULL COMMENT "Maximum charge for percentage-based pricing",
            pricing_tiers JSON DEFAULT NULL COMMENT "Tiered pricing structure [{min_value, max_value, price}]",
            
            -- Service configuration
            applies_to_zones JSON DEFAULT NULL COMMENT "Zone codes where service is available",
            max_value_amount DECIMAL(12,2) DEFAULT NULL COMMENT "Maximum insurable/COD value",
            currency VARCHAR(3) NOT NULL DEFAULT "PLN" COMMENT "ISO currency code",
            is_mandatory BOOLEAN NOT NULL DEFAULT FALSE COMMENT "Whether service is mandatory",
            is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT "Whether service is currently active",
            sort_order INT NOT NULL DEFAULT 0 COMMENT "Display order",
            
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX IDX_CARRIER_SERVICE (carrier_id),
            INDEX IDX_SERVICE_CODE (service_code),
            INDEX IDX_ACTIVE (is_active),
            UNIQUE INDEX UNIQ_CARRIER_SERVICE_CODE (carrier_id, service_code),
            
            FOREIGN KEY (carrier_id) REFERENCES v2_carriers (id) ON DELETE CASCADE,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB COMMENT = "Additional services pricing (COD, insurance, SMS)"');

        // 5. CUSTOMER PRICING - Negotiated pricing for B2B customers
        $this->addSql('CREATE TABLE v2_customer_pricing (
            id INT AUTO_INCREMENT NOT NULL,
            customer_id INT NOT NULL COMMENT "Reference to customer",
            pricing_table_id INT NOT NULL COMMENT "Base pricing table",
            contract_number VARCHAR(100) DEFAULT NULL COMMENT "Contract reference number",
            
            -- Discount structure
            discount_type VARCHAR(30) NOT NULL COMMENT "Type of discount applied",
            discount_percentage DECIMAL(5,2) DEFAULT NULL COMMENT "Percentage discount from base rates",
            fixed_discount_amount DECIMAL(10,2) DEFAULT NULL COMMENT "Fixed amount discount per shipment",
            
            -- Custom pricing overrides
            custom_weight_tiers JSON DEFAULT NULL COMMENT "Custom weight tiers overriding base pricing",
            custom_additional_services JSON DEFAULT NULL COMMENT "Custom pricing for additional services",
            
            -- Volume-based pricing
            monthly_volume_threshold INT DEFAULT NULL COMMENT "Minimum monthly shipments for pricing",
            volume_discount_tiers JSON DEFAULT NULL COMMENT "Volume-based discount tiers [{min_shipments, discount_percent}]",
            
            -- Contract terms
            minimum_monthly_spend DECIMAL(10,2) DEFAULT NULL COMMENT "Minimum monthly spend commitment",
            credit_limit DECIMAL(12,2) DEFAULT NULL COMMENT "Credit limit for customer",
            payment_terms_days INT DEFAULT 30 COMMENT "Payment terms in days",
            
            -- Validity
            effective_from DATE NOT NULL COMMENT "Contract effective date",
            effective_to DATE DEFAULT NULL COMMENT "Contract expiry date",
            is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT "Whether pricing is active",
            
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            created_by INT DEFAULT NULL COMMENT "User who created the pricing",
            
            INDEX IDX_CUSTOMER (customer_id),
            INDEX IDX_PRICING_TABLE (pricing_table_id),
            INDEX IDX_CONTRACT (contract_number),
            INDEX IDX_EFFECTIVE_DATES (effective_from, effective_to),
            INDEX IDX_ACTIVE (is_active),
            
            FOREIGN KEY (customer_id) REFERENCES v2_customers (id) ON DELETE CASCADE,
            FOREIGN KEY (pricing_table_id) REFERENCES v2_pricing_tables (id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES v2_users (id) ON DELETE SET NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB COMMENT = "Customer-specific negotiated pricing"');

        // 6. PROMOTIONAL PRICING - Time-limited promotions and discounts
        $this->addSql('CREATE TABLE v2_promotional_pricing (
            id INT AUTO_INCREMENT NOT NULL,
            promotion_code VARCHAR(50) NOT NULL COMMENT "Unique promotion code",
            name VARCHAR(100) NOT NULL COMMENT "Promotion display name",
            description TEXT DEFAULT NULL COMMENT "Promotion description",
            
            -- Promotion scope
            applies_to_carriers JSON DEFAULT NULL COMMENT "Carrier IDs this promotion applies to",
            applies_to_zones JSON DEFAULT NULL COMMENT "Zone IDs this promotion applies to", 
            applies_to_services JSON DEFAULT NULL COMMENT "Service types this promotion applies to",
            applies_to_customers JSON DEFAULT NULL COMMENT "Specific customer IDs (NULL = all customers)",
            customer_type VARCHAR(20) DEFAULT "all" COMMENT "Customer type eligibility",
            
            -- Discount structure
            discount_type VARCHAR(20) NOT NULL COMMENT "Type of discount",
            discount_value DECIMAL(10,2) NOT NULL COMMENT "Discount percentage or fixed amount",
            applies_to_service VARCHAR(50) DEFAULT NULL COMMENT "Which part of pricing (base, additional_service_code)",
            max_discount_amount DECIMAL(10,2) DEFAULT NULL COMMENT "Maximum discount amount per shipment",
            
            -- Usage limits
            max_uses_total INT DEFAULT NULL COMMENT "Total number of times promotion can be used",
            max_uses_per_customer INT DEFAULT NULL COMMENT "Maximum uses per customer",
            min_order_value DECIMAL(10,2) DEFAULT NULL COMMENT "Minimum order value to qualify",
            current_uses INT NOT NULL DEFAULT 0 COMMENT "Current number of uses",
            
            -- Validity period
            valid_from DATETIME NOT NULL COMMENT "Promotion start date/time",
            valid_to DATETIME NOT NULL COMMENT "Promotion end date/time",
            is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT "Whether promotion is active",
            is_public BOOLEAN NOT NULL DEFAULT FALSE COMMENT "Whether promotion is publicly visible",
            
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            created_by INT NOT NULL COMMENT "User who created the promotion",
            
            UNIQUE INDEX UNIQ_PROMOTION_CODE (promotion_code),
            INDEX IDX_VALIDITY (valid_from, valid_to),
            INDEX IDX_ACTIVE (is_active),
            INDEX IDX_PUBLIC (is_public),
            INDEX IDX_CUSTOMER_TYPE (customer_type),
            
            FOREIGN KEY (created_by) REFERENCES v2_users (id) ON DELETE RESTRICT,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB COMMENT = "Promotional pricing and discount campaigns"');

        // 7. PROMOTION USAGE - Track promotion usage
        $this->addSql('CREATE TABLE v2_promotion_usage (
            id INT AUTO_INCREMENT NOT NULL,
            promotion_id INT NOT NULL COMMENT "Reference to promotion",
            customer_id INT DEFAULT NULL COMMENT "Customer who used the promotion",
            order_id INT DEFAULT NULL COMMENT "Order where promotion was applied",
            shipment_id INT DEFAULT NULL COMMENT "Shipment where promotion was applied",
            
            discount_amount DECIMAL(10,2) NOT NULL COMMENT "Actual discount amount applied",
            original_amount DECIMAL(10,2) NOT NULL COMMENT "Original amount before discount",
            final_amount DECIMAL(10,2) NOT NULL COMMENT "Final amount after discount",
            
            used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT "When promotion was used",
            
            INDEX IDX_PROMOTION (promotion_id),
            INDEX IDX_CUSTOMER (customer_id),
            INDEX IDX_ORDER (order_id),
            INDEX IDX_SHIPMENT (shipment_id),
            INDEX IDX_USED_AT (used_at),
            
            FOREIGN KEY (promotion_id) REFERENCES v2_promotional_pricing (id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES v2_customers (id) ON DELETE SET NULL,
            FOREIGN KEY (order_id) REFERENCES v2_orders (id) ON DELETE SET NULL,
            FOREIGN KEY (shipment_id) REFERENCES v2_shipments (id) ON DELETE SET NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB COMMENT = "Track promotion usage"');

        // 8. PRICING RULES - Business rules for price calculation
        $this->addSql('CREATE TABLE v2_pricing_rules (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL COMMENT "Rule name",
            description TEXT DEFAULT NULL COMMENT "Rule description",
            rule_type VARCHAR(30) NOT NULL COMMENT "Type of pricing rule",
            
            -- Rule configuration
            applies_to_carriers JSON DEFAULT NULL COMMENT "Carrier IDs this rule applies to",
            applies_to_zones JSON DEFAULT NULL COMMENT "Zone IDs this rule applies to",
            applies_to_services JSON DEFAULT NULL COMMENT "Service types this rule applies to",
            
            -- Rule logic (stored as JSON for flexibility)
            rule_config JSON NOT NULL COMMENT "Rule configuration and parameters",
            
            -- Rule actions
            action_type VARCHAR(20) NOT NULL COMMENT "Action to take when rule triggers",
            action_config JSON DEFAULT NULL COMMENT "Action configuration parameters",
            
            -- Priority and status
            priority INT NOT NULL DEFAULT 100 COMMENT "Rule execution priority (lower = higher priority)",
            is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT "Whether rule is active",
            
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            created_by INT NOT NULL COMMENT "User who created the rule",
            
            INDEX IDX_RULE_TYPE (rule_type),
            INDEX IDX_PRIORITY (priority),
            INDEX IDX_ACTIVE (is_active),
            
            FOREIGN KEY (created_by) REFERENCES v2_users (id) ON DELETE RESTRICT,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB COMMENT = "Business rules for pricing calculations"');

        // 9. PRICING AUDIT LOG - Complete audit trail for pricing changes
        $this->addSql('CREATE TABLE v2_pricing_audit_log (
            id INT AUTO_INCREMENT NOT NULL,
            
            -- What was changed
            table_name VARCHAR(50) NOT NULL COMMENT "Table that was modified",
            record_id INT NOT NULL COMMENT "ID of the modified record",
            action VARCHAR(10) NOT NULL COMMENT "Type of action performed",
            
            -- Change details
            field_name VARCHAR(100) DEFAULT NULL COMMENT "Specific field that was changed (for updates)",
            old_value JSON DEFAULT NULL COMMENT "Previous value (for updates and deletes)",
            new_value JSON DEFAULT NULL COMMENT "New value (for inserts and updates)",
            
            -- Context
            changed_by INT NOT NULL COMMENT "User who made the change",
            changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT "When the change was made",
            ip_address VARCHAR(45) DEFAULT NULL COMMENT "IP address of the user",
            user_agent TEXT DEFAULT NULL COMMENT "User agent string",
            change_reason TEXT DEFAULT NULL COMMENT "Reason for the change",
            
            -- Additional context
            session_id VARCHAR(100) DEFAULT NULL COMMENT "Session ID for grouping related changes",
            batch_id VARCHAR(100) DEFAULT NULL COMMENT "Batch ID for bulk operations",
            
            INDEX IDX_TABLE_RECORD (table_name, record_id),
            INDEX IDX_ACTION (action),
            INDEX IDX_CHANGED_BY (changed_by),
            INDEX IDX_CHANGED_AT (changed_at),
            INDEX IDX_SESSION (session_id),
            INDEX IDX_BATCH (batch_id),
            
            FOREIGN KEY (changed_by) REFERENCES v2_users (id) ON DELETE RESTRICT,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB COMMENT = "Audit trail for all pricing changes"');

        // 10. PRICE CALCULATION CACHE - Cache calculated prices for performance
        $this->addSql('CREATE TABLE v2_price_calculation_cache (
            id INT AUTO_INCREMENT NOT NULL,
            
            -- Calculation input hash
            calculation_hash VARCHAR(64) NOT NULL COMMENT "MD5 hash of calculation parameters",
            
            -- Input parameters
            carrier_id INT NOT NULL COMMENT "Carrier used in calculation",
            zone_id INT NOT NULL COMMENT "Zone used in calculation", 
            service_type VARCHAR(50) NOT NULL COMMENT "Service type",
            weight_kg DECIMAL(8,3) NOT NULL COMMENT "Package weight in kg",
            dimensions_cm JSON DEFAULT NULL COMMENT "Package dimensions [length, width, height]",
            declared_value DECIMAL(12,2) DEFAULT NULL COMMENT "Declared value for insurance",
            additional_services JSON DEFAULT NULL COMMENT "List of additional service codes",
            customer_id INT DEFAULT NULL COMMENT "Customer ID for customer-specific pricing",
            promotion_code VARCHAR(50) DEFAULT NULL COMMENT "Promotion code applied",
            
            -- Calculation results
            base_price DECIMAL(10,2) NOT NULL COMMENT "Base shipping price",
            additional_services_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT "Additional services total price",
            promotional_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT "Promotional discount amount",
            customer_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT "Customer-specific discount",
            total_price DECIMAL(10,2) NOT NULL COMMENT "Final total price",
            currency VARCHAR(3) NOT NULL DEFAULT "PLN" COMMENT "Price currency",
            
            -- Price breakdown for transparency
            price_breakdown JSON DEFAULT NULL COMMENT "Detailed price breakdown",
            applied_rules JSON DEFAULT NULL COMMENT "List of pricing rules that were applied",
            
            -- Cache metadata
            calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT "When price was calculated",
            expires_at DATETIME NOT NULL COMMENT "When cache entry expires",
            hit_count INT NOT NULL DEFAULT 1 COMMENT "Number of times this cache entry was used",
            last_accessed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT "When cache was last accessed",
            
            UNIQUE INDEX UNIQ_CALCULATION_HASH (calculation_hash),
            INDEX IDX_CARRIER_ZONE (carrier_id, zone_id),
            INDEX IDX_EXPIRES_AT (expires_at),
            INDEX IDX_CUSTOMER (customer_id),
            INDEX IDX_LAST_ACCESSED (last_accessed_at),
            
            FOREIGN KEY (carrier_id) REFERENCES v2_carriers (id) ON DELETE CASCADE,
            FOREIGN KEY (zone_id) REFERENCES v2_pricing_zones (id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES v2_customers (id) ON DELETE CASCADE,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB COMMENT = "Cache for calculated shipping prices"');

        // Insert default data
        $this->addSql("INSERT INTO v2_pricing_zones (code, name, description, zone_type, countries, postal_code_patterns) VALUES
            ('LOCAL', 'Lokalna', 'Przesyłki w obrębie tego samego miasta/regionu', 'local', '[\"PL\"]', '[\"^[0-9]{2}-[0-9]{3}$\"]'),
            ('NAT_PL', 'Krajowa', 'Przesyłki w obrębie Polski', 'national', '[\"PL\"]', '[\"^[0-9]{2}-[0-9]{3}$\"]'),
            ('EU', 'Unia Europejska', 'Przesyłki do krajów UE', 'international', '[\"AT\",\"BE\",\"BG\",\"HR\",\"CY\",\"CZ\",\"DK\",\"EE\",\"FI\",\"FR\",\"DE\",\"GR\",\"HU\",\"IE\",\"IT\",\"LV\",\"LT\",\"LU\",\"MT\",\"NL\",\"PT\",\"RO\",\"SK\",\"SI\",\"ES\",\"SE\"]', NULL),
            ('EU_WEST', 'Europa Zachodnia', 'Kraje Europy Zachodniej', 'international', '[\"AT\",\"BE\",\"FR\",\"DE\",\"LU\",\"NL\",\"CH\"]', NULL),
            ('EU_EAST', 'Europa Wschodnia', 'Kraje Europy Wschodniej', 'international', '[\"BG\",\"CZ\",\"HU\",\"RO\",\"SK\",\"SI\"]', NULL),
            ('WORLD', 'Międzynarodowa', 'Przesyłki poza UE', 'international', NULL, NULL)
        ");

        $this->addSql("INSERT INTO v2_carriers (code, name, supported_zones, max_weight_kg, max_dimensions_cm, is_active) VALUES
            ('INPOST', 'InPost', '[\"LOCAL\",\"NAT_PL\",\"EU\"]', 25.000, '[64,38,64]', TRUE),
            ('DHL', 'DHL Express', '[\"NAT_PL\",\"EU\",\"EU_WEST\",\"EU_EAST\",\"WORLD\"]', 70.000, '[120,80,80]', TRUE),
            ('UPS', 'UPS', '[\"NAT_PL\",\"EU\",\"EU_WEST\",\"EU_EAST\",\"WORLD\"]', 70.000, '[150,100,100]', TRUE),
            ('DPD', 'DPD', '[\"LOCAL\",\"NAT_PL\",\"EU\",\"EU_WEST\",\"EU_EAST\"]', 31.500, '[175,100,70]', TRUE),
            ('MEEST', 'Meest Express', '[\"EU_EAST\",\"WORLD\"]', 30.000, '[100,60,60]', TRUE)
        ");

        // Create indexes for performance optimization
        $this->addSql('CREATE INDEX idx_pricing_performance ON v2_pricing_tables (carrier_id, zone_id, is_active, effective_from, effective_to)');
        $this->addSql('CREATE INDEX idx_customer_pricing_lookup ON v2_customer_pricing (customer_id, is_active, effective_from, effective_to)');
        $this->addSql('CREATE INDEX idx_promotion_validity ON v2_promotional_pricing (is_active, valid_from, valid_to)');
        $this->addSql('CREATE INDEX idx_audit_performance ON v2_pricing_audit_log (table_name, record_id, changed_at)');
    }

    public function down(Schema $schema): void
    {
        // Drop tables in reverse order to respect foreign key constraints
        $this->addSql('DROP TABLE IF EXISTS v2_price_calculation_cache');
        $this->addSql('DROP TABLE IF EXISTS v2_pricing_audit_log');
        $this->addSql('DROP TABLE IF EXISTS v2_pricing_rules');
        $this->addSql('DROP TABLE IF EXISTS v2_promotion_usage');
        $this->addSql('DROP TABLE IF EXISTS v2_promotional_pricing');
        $this->addSql('DROP TABLE IF EXISTS v2_customer_pricing');
        $this->addSql('DROP TABLE IF EXISTS v2_additional_services');
        $this->addSql('DROP TABLE IF EXISTS v2_pricing_tables');
        $this->addSql('DROP TABLE IF EXISTS v2_carriers');
        $this->addSql('DROP TABLE IF EXISTS v2_pricing_zones');
    }
}