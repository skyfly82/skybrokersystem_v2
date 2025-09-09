-- =====================================================
-- COMPREHENSIVE COURIER PRICING SYSTEM DATABASE SCHEMA
-- =====================================================
-- 
-- This schema supports:
-- - Multi-carrier pricing (InPost, DHL, UPS, DPD, Meest) 
-- - Geographic zones (local, national, international)
-- - Weight and dimensional pricing tiers
-- - Additional services (COD, insurance, SMS notifications)
-- - B2B/B2C customer-specific pricing
-- - Promotional pricing and volume discounts
-- - Complete audit trail for price changes
--
-- Version: 1.0
-- Date: 2025-09-09
-- =====================================================

-- 1. PRICING ZONES - Geographic zones for pricing differentiation
CREATE TABLE v2_pricing_zones (
    id INT AUTO_INCREMENT NOT NULL,
    code VARCHAR(10) NOT NULL COMMENT 'Unique zone code (LOCAL, NAT_PL, EU, WORLD)',
    name VARCHAR(100) NOT NULL COMMENT 'Human-readable zone name',
    description TEXT DEFAULT NULL COMMENT 'Detailed zone description',
    zone_type VARCHAR(20) NOT NULL COMMENT 'Zone classification: local, national, international',
    countries JSON DEFAULT NULL COMMENT 'List of ISO country codes in this zone',
    postal_code_patterns JSON DEFAULT NULL COMMENT 'Postal code patterns for automatic zone detection',
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Whether zone is currently active',
    sort_order INT NOT NULL DEFAULT 0 COMMENT 'Display order for zones',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY(id),
    UNIQUE INDEX UNIQ_ZONE_CODE (code),
    INDEX IDX_ZONE_TYPE (zone_type),
    INDEX IDX_ZONE_ACTIVE (is_active),
    INDEX IDX_ZONE_SORT (sort_order)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB 
COMMENT = 'Geographic zones for courier pricing';

-- 2. CARRIERS - Courier service providers (update existing table)
-- Note: v2_carriers table already exists, we'll just ensure it has required fields

-- 3. PRICING TABLES - Main pricing structure per carrier and zone
CREATE TABLE v2_pricing_tables (
    id INT AUTO_INCREMENT NOT NULL,
    carrier_id INT NOT NULL COMMENT 'Reference to carrier',
    zone_id INT NOT NULL COMMENT 'Reference to pricing zone',
    service_type VARCHAR(50) NOT NULL DEFAULT 'standard' COMMENT 'Service type (standard, express, economy, overnight, premium)',
    pricing_model VARCHAR(20) NOT NULL DEFAULT 'weight' COMMENT 'Pricing model: weight, volumetric, hybrid',
    version INT NOT NULL DEFAULT 1 COMMENT 'Pricing table version',
    name VARCHAR(100) NOT NULL COMMENT 'Pricing table name',
    description TEXT DEFAULT NULL COMMENT 'Detailed description of pricing table',
    
    -- Base pricing configuration
    base_price DECIMAL(10,4) NOT NULL COMMENT 'Base price for minimum weight/size',
    min_weight_kg DECIMAL(8,3) NOT NULL DEFAULT 0.100 COMMENT 'Minimum weight threshold in kg',
    max_weight_kg DECIMAL(8,3) DEFAULT NULL COMMENT 'Maximum weight threshold in kg (null = unlimited)',
    
    -- Dimensional limits
    min_dimensions_cm JSON DEFAULT NULL COMMENT 'Minimum dimensions [length, width, height] in cm',
    max_dimensions_cm JSON DEFAULT NULL COMMENT 'Maximum dimensions [length, width, height] in cm',
    volumetric_divisor INT DEFAULT NULL COMMENT 'Volumetric weight divisor (e.g., 5000 for DHL)',
    
    -- Financial settings
    currency VARCHAR(3) NOT NULL DEFAULT 'PLN' COMMENT 'ISO currency code',
    tax_rate DECIMAL(5,2) DEFAULT NULL COMMENT 'Tax rate as percentage (e.g., 23 for 23% VAT)',
    
    -- Configuration
    config JSON DEFAULT NULL COMMENT 'Additional configuration parameters',
    
    -- Validity
    effective_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When pricing becomes effective',
    effective_until DATETIME DEFAULT NULL COMMENT 'When pricing expires',
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Whether pricing table is active',
    
    -- Audit fields
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL COMMENT 'User who created the pricing',
    updated_by INT DEFAULT NULL COMMENT 'User who last updated the pricing',
    
    PRIMARY KEY(id),
    INDEX IDX_PRICING_CARRIER_ZONE (carrier_id, zone_id),
    INDEX IDX_PRICING_ACTIVE (is_active),
    INDEX IDX_PRICING_EFFECTIVE (effective_from, effective_until),
    INDEX IDX_PRICING_SERVICE (service_type),
    UNIQUE INDEX UNQ_PRICING_CARRIER_ZONE_SERVICE_VERSION (carrier_id, zone_id, service_type, version),
    
    FOREIGN KEY (carrier_id) REFERENCES v2_carriers (id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES v2_pricing_zones (id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES v2_users (id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES v2_users (id) ON DELETE SET NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB 
COMMENT = 'Main pricing tables per carrier and zone';

-- 4. PRICING RULES - Weight/dimension-based pricing rules within pricing tables
CREATE TABLE v2_pricing_rules (
    id INT AUTO_INCREMENT NOT NULL,
    pricing_table_id INT NOT NULL COMMENT 'Reference to pricing table',
    name VARCHAR(100) DEFAULT NULL COMMENT 'Rule name',
    
    -- Weight range
    weight_from DECIMAL(8,3) NOT NULL COMMENT 'Weight range start in kg',
    weight_to DECIMAL(8,3) DEFAULT NULL COMMENT 'Weight range end in kg (null = unlimited)',
    
    -- Dimension constraints
    dimensions_from JSON DEFAULT NULL COMMENT 'Minimum dimensions [L,W,H] in cm',
    dimensions_to JSON DEFAULT NULL COMMENT 'Maximum dimensions [L,W,H] in cm',
    
    -- Pricing calculation
    calculation_method VARCHAR(20) NOT NULL DEFAULT 'fixed' COMMENT 'fixed, per_kg, per_kg_step, percentage',
    price DECIMAL(10,4) NOT NULL COMMENT 'Base price for this weight range',
    price_per_kg DECIMAL(10,4) DEFAULT NULL COMMENT 'Additional price per kg above base weight',
    weight_step DECIMAL(8,3) DEFAULT NULL COMMENT 'Weight increment for stepped pricing (kg)',
    
    -- Price limits
    min_price DECIMAL(10,4) DEFAULT NULL COMMENT 'Minimum price for this rule',
    max_price DECIMAL(10,4) DEFAULT NULL COMMENT 'Maximum price for this rule',
    tax_rate_override DECIMAL(5,2) DEFAULT NULL COMMENT 'Override tax rate for this rule',
    
    -- Configuration
    config JSON DEFAULT NULL COMMENT 'Rule configuration parameters',
    sort_order INT NOT NULL DEFAULT 0 COMMENT 'Rule execution order',
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Whether rule is active',
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY(id),
    INDEX IDX_RULE_TABLE (pricing_table_id),
    INDEX IDX_RULE_WEIGHT_RANGE (weight_from, weight_to),
    INDEX IDX_RULE_SORT (sort_order),
    UNIQUE INDEX UNQ_PRICING_RULE_TABLE_WEIGHT (pricing_table_id, weight_from, weight_to),
    
    FOREIGN KEY (pricing_table_id) REFERENCES v2_pricing_tables (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB 
COMMENT = 'Weight/dimension-based pricing rules';

-- 5. ADDITIONAL SERVICES - Extra services offered by carriers
CREATE TABLE v2_additional_services (
    id INT AUTO_INCREMENT NOT NULL,
    carrier_id INT NOT NULL COMMENT 'Reference to carrier',
    code VARCHAR(50) NOT NULL COMMENT 'Service code (COD, INSURANCE, SMS, etc.)',
    name VARCHAR(100) NOT NULL COMMENT 'Human-readable service name',
    description TEXT DEFAULT NULL COMMENT 'Service description',
    
    -- Service classification
    service_type VARCHAR(30) NOT NULL COMMENT 'cod, insurance, sms, email, saturday, return, fragile, priority, pickup, signature',
    pricing_type VARCHAR(20) NOT NULL DEFAULT 'fixed' COMMENT 'fixed, percentage, per_package, tier_based',
    
    -- Default pricing
    default_price DECIMAL(10,4) DEFAULT NULL COMMENT 'Default price for this service',
    min_price DECIMAL(10,4) DEFAULT NULL COMMENT 'Minimum price limit',
    max_price DECIMAL(10,4) DEFAULT NULL COMMENT 'Maximum price limit',
    percentage_rate DECIMAL(5,2) DEFAULT NULL COMMENT 'Percentage rate for percentage-based pricing',
    
    -- Service configuration
    config JSON DEFAULT NULL COMMENT 'Service configuration parameters',
    required_fields JSON DEFAULT NULL COMMENT 'Required fields (e.g., phone number for SMS)',
    supported_zones JSON DEFAULT NULL COMMENT 'Supported zone codes',
    
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Whether service is currently active',
    sort_order INT NOT NULL DEFAULT 0 COMMENT 'Display order',
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY(id),
    INDEX IDX_SERVICE_CARRIER (carrier_id),
    INDEX IDX_SERVICE_TYPE (service_type),
    INDEX IDX_SERVICE_ACTIVE (is_active),
    UNIQUE INDEX UNQ_SERVICE_CARRIER_CODE (carrier_id, code),
    
    FOREIGN KEY (carrier_id) REFERENCES v2_carriers (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB 
COMMENT = 'Additional services offered by carriers';

-- 6. ADDITIONAL SERVICE PRICES - Pricing for additional services within specific pricing tables
CREATE TABLE v2_additional_service_prices (
    id INT AUTO_INCREMENT NOT NULL,
    pricing_table_id INT NOT NULL COMMENT 'Reference to pricing table',
    additional_service_id INT NOT NULL COMMENT 'Reference to additional service',
    
    -- Price overrides
    price DECIMAL(10,4) NOT NULL COMMENT 'Price override for this service in this pricing table',
    min_price DECIMAL(10,4) DEFAULT NULL COMMENT 'Minimum price override',
    max_price DECIMAL(10,4) DEFAULT NULL COMMENT 'Maximum price override',
    percentage_rate DECIMAL(5,2) DEFAULT NULL COMMENT 'Percentage rate override',
    
    -- Service-specific pricing
    config JSON DEFAULT NULL COMMENT 'Service-specific configuration for this pricing table',
    weight_tiers JSON DEFAULT NULL COMMENT 'Weight-based pricing tiers',
    value_tiers JSON DEFAULT NULL COMMENT 'Value-based pricing tiers (for insurance, COD)',
    
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Whether service pricing is active',
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY(id),
    INDEX IDX_SERVICE_PRICE_TABLE (pricing_table_id),
    INDEX IDX_SERVICE_PRICE_SERVICE (additional_service_id),
    INDEX IDX_SERVICE_PRICE_ACTIVE (is_active),
    UNIQUE INDEX UNQ_SERVICE_PRICE_TABLE_SERVICE (pricing_table_id, additional_service_id),
    
    FOREIGN KEY (pricing_table_id) REFERENCES v2_pricing_tables (id) ON DELETE CASCADE,
    FOREIGN KEY (additional_service_id) REFERENCES v2_additional_services (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB 
COMMENT = 'Pricing for additional services within specific pricing tables';

-- 7. CUSTOMER PRICING - B2B customer-specific pricing agreements
CREATE TABLE v2_customer_pricing (
    id INT AUTO_INCREMENT NOT NULL,
    customer_id INT NOT NULL COMMENT 'Reference to customer',
    base_pricing_table_id INT NOT NULL COMMENT 'Base pricing table',
    contract_name VARCHAR(100) NOT NULL COMMENT 'Contract name',
    contract_number VARCHAR(50) DEFAULT NULL COMMENT 'Contract reference number',
    
    -- Discount structure
    discount_type VARCHAR(20) NOT NULL DEFAULT 'percentage' COMMENT 'percentage, fixed, volume, custom_rules',
    base_discount DECIMAL(5,2) DEFAULT NULL COMMENT 'Base discount percentage',
    fixed_discount DECIMAL(10,4) DEFAULT NULL COMMENT 'Fixed amount discount per shipment',
    
    -- Order constraints
    minimum_order_value DECIMAL(10,4) DEFAULT NULL COMMENT 'Minimum order value for pricing',
    maximum_order_value DECIMAL(10,4) DEFAULT NULL COMMENT 'Maximum order value for pricing',
    
    -- Volume pricing
    volume_discounts JSON DEFAULT NULL COMMENT 'Volume discount configuration',
    volume_period VARCHAR(20) DEFAULT 'monthly' COMMENT 'daily, weekly, monthly, quarterly, yearly',
    
    -- Custom configurations
    custom_rules JSON DEFAULT NULL COMMENT 'Custom pricing rules override',
    service_discounts JSON DEFAULT NULL COMMENT 'Additional service discounts',
    payment_terms JSON DEFAULT NULL COMMENT 'Payment terms configuration',
    free_shipping_threshold DECIMAL(10,4) DEFAULT NULL COMMENT 'Free shipment threshold',
    
    -- Overrides
    tax_rate_override DECIMAL(5,2) DEFAULT NULL COMMENT 'Custom tax rate override',
    currency_override VARCHAR(3) DEFAULT NULL COMMENT 'Currency override',
    priority_level INT NOT NULL DEFAULT 1 COMMENT 'Priority level 1-5',
    
    -- Validity
    effective_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Contract effective date',
    effective_until DATETIME DEFAULT NULL COMMENT 'Contract expiry date',
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Whether pricing is active',
    auto_renewal BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Auto renewal flag',
    
    -- Audit fields
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL COMMENT 'User who created the pricing',
    updated_by INT DEFAULT NULL COMMENT 'User who last updated the pricing',
    
    PRIMARY KEY(id),
    INDEX IDX_CUSTOMER_PRICING_CUSTOMER (customer_id),
    INDEX IDX_CUSTOMER_PRICING_TABLE (base_pricing_table_id),
    INDEX IDX_CUSTOMER_PRICING_ACTIVE (is_active),
    INDEX IDX_CUSTOMER_PRICING_EFFECTIVE (effective_from, effective_until),
    UNIQUE INDEX UNQ_CUSTOMER_PRICING_CUSTOMER_TABLE (customer_id, base_pricing_table_id),
    
    FOREIGN KEY (customer_id) REFERENCES v2_customers (id) ON DELETE CASCADE,
    FOREIGN KEY (base_pricing_table_id) REFERENCES v2_pricing_tables (id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES v2_users (id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES v2_users (id) ON DELETE SET NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB 
COMMENT = 'B2B customer-specific pricing agreements';

-- 8. PROMOTIONAL PRICING - Time-limited promotions and discounts
CREATE TABLE v2_promotional_pricing (
    id INT AUTO_INCREMENT NOT NULL,
    pricing_table_id INT DEFAULT NULL COMMENT 'Optional: Specific pricing table',
    customer_pricing_id INT DEFAULT NULL COMMENT 'Optional: Customer-specific promotion',
    
    name VARCHAR(100) NOT NULL COMMENT 'Promotion display name',
    description TEXT DEFAULT NULL COMMENT 'Promotion description',
    promo_code VARCHAR(50) DEFAULT NULL COMMENT 'Unique promotion code',
    
    -- Discount configuration
    discount_type VARCHAR(20) NOT NULL DEFAULT 'percentage' COMMENT 'percentage, fixed_amount, free_shipping, buy_x_get_y, tier_discount',
    discount_value DECIMAL(10,4) NOT NULL COMMENT 'Discount percentage or fixed amount',
    minimum_order_value DECIMAL(10,4) DEFAULT NULL COMMENT 'Minimum order value to qualify',
    maximum_discount_amount DECIMAL(10,4) DEFAULT NULL COMMENT 'Maximum discount amount',
    
    -- Target configuration
    target_type VARCHAR(20) NOT NULL DEFAULT 'all' COMMENT 'all, carrier, zone, service_type, customer, customer_group',
    target_values JSON DEFAULT NULL COMMENT 'Target values (carrier codes, zone codes, etc.)',
    promotion_config JSON DEFAULT NULL COMMENT 'Promotion configuration',
    
    -- Usage limits
    usage_limit INT DEFAULT NULL COMMENT 'Total usage limit',
    usage_limit_type VARCHAR(20) DEFAULT 'total' COMMENT 'total, per_customer, per_day',
    usage_count INT NOT NULL DEFAULT 0 COMMENT 'Current usage count',
    
    -- Validity
    valid_from DATETIME NOT NULL COMMENT 'Promotion start date/time',
    valid_until DATETIME NOT NULL COMMENT 'Promotion end date/time',
    priority INT NOT NULL DEFAULT 1 COMMENT 'Priority level 1-100',
    stackable BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Can be combined with other promotions',
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Whether promotion is active',
    
    -- Audit fields
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL COMMENT 'User who created the promotion',
    updated_by INT DEFAULT NULL COMMENT 'User who last updated the promotion',
    
    PRIMARY KEY(id),
    INDEX IDX_PROMO_PRICING_TABLE (pricing_table_id),
    INDEX IDX_PROMO_CUSTOMER_PRICING (customer_pricing_id),
    INDEX IDX_PROMO_CODE (promo_code),
    INDEX IDX_PROMO_ACTIVE (is_active),
    INDEX IDX_PROMO_PERIOD (valid_from, valid_until),
    UNIQUE INDEX UNQ_PROMO_CODE (promo_code),
    
    FOREIGN KEY (pricing_table_id) REFERENCES v2_pricing_tables (id) ON DELETE CASCADE,
    FOREIGN KEY (customer_pricing_id) REFERENCES v2_customer_pricing (id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES v2_users (id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES v2_users (id) ON DELETE SET NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB 
COMMENT = 'Temporary promotions and time-limited pricing offers';

-- 9. CUSTOMER PRICING AUDIT - Audit trail for customer pricing changes
CREATE TABLE v2_customer_pricing_audit (
    id INT AUTO_INCREMENT NOT NULL,
    customer_pricing_id INT NOT NULL COMMENT 'Reference to customer pricing',
    action VARCHAR(20) NOT NULL COMMENT 'created, updated, activated, deactivated, expired, renewed, deleted',
    description TEXT DEFAULT NULL COMMENT 'Description of change',
    
    -- Change details
    old_values JSON DEFAULT NULL COMMENT 'Previous values',
    new_values JSON DEFAULT NULL COMMENT 'New values',
    metadata JSON DEFAULT NULL COMMENT 'Additional metadata',
    
    -- Context
    user_agent VARCHAR(255) DEFAULT NULL COMMENT 'User agent string',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP address',
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL COMMENT 'User who made the change',
    
    PRIMARY KEY(id),
    INDEX IDX_PRICING_AUDIT_CUSTOMER_PRICING (customer_pricing_id),
    INDEX IDX_PRICING_AUDIT_ACTION (action),
    INDEX IDX_PRICING_AUDIT_DATE (created_at),
    INDEX IDX_PRICING_AUDIT_USER (created_by),
    
    FOREIGN KEY (customer_pricing_id) REFERENCES v2_customer_pricing (id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES v2_users (id) ON DELETE SET NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB 
COMMENT = 'Audit trail for customer pricing changes';

-- =====================================================
-- INITIAL DATA INSERTS
-- =====================================================

-- Insert default pricing zones
INSERT INTO v2_pricing_zones (code, name, description, zone_type, countries, postal_code_patterns, sort_order) VALUES
('LOCAL', 'Lokalna', 'Przesyłki w obrębie tego samego miasta/regionu', 'local', '["PL"]', '["^[0-9]{2}-[0-9]{3}$"]', 1),
('NAT_PL', 'Krajowa', 'Przesyłki w obrębie Polski', 'national', '["PL"]', '["^[0-9]{2}-[0-9]{3}$"]', 2),
('EU', 'Unia Europejska', 'Przesyłki do krajów UE', 'international', '["AT","BE","BG","HR","CY","CZ","DK","EE","FI","FR","DE","GR","HU","IE","IT","LV","LT","LU","MT","NL","PT","RO","SK","SI","ES","SE"]', NULL, 3),
('EU_WEST', 'Europa Zachodnia', 'Kraje Europy Zachodniej', 'international', '["AT","BE","FR","DE","LU","NL","CH"]', NULL, 4),
('EU_EAST', 'Europa Wschodnia', 'Kraje Europy Wschodniej', 'international', '["BG","CZ","HU","RO","SK","SI"]', NULL, 5),
('WORLD', 'Międzynarodowa', 'Przesyłki poza UE', 'international', NULL, NULL, 6);

-- Update existing carriers table to ensure compatibility
-- (Assuming v2_carriers already exists, just ensure required fields are present)

-- Create performance optimization indexes
CREATE INDEX idx_pricing_performance ON v2_pricing_tables (carrier_id, zone_id, is_active, effective_from, effective_until);
CREATE INDEX idx_customer_pricing_lookup ON v2_customer_pricing (customer_id, is_active, effective_from, effective_until);
CREATE INDEX idx_promotion_validity ON v2_promotional_pricing (is_active, valid_from, valid_until);

-- =====================================================
-- EXAMPLE SAMPLE DATA (OPTIONAL)
-- =====================================================

-- Sample pricing table for InPost local delivery
-- INSERT INTO v2_pricing_tables (carrier_id, zone_id, service_type, name, base_price, min_weight_kg, max_weight_kg, currency, effective_from) 
-- SELECT c.id, z.id, 'standard', 'InPost Lokalna Standard', 15.99, 0.100, 25.000, 'PLN', NOW()
-- FROM v2_carriers c, v2_pricing_zones z 
-- WHERE c.code = 'INPOST' AND z.code = 'LOCAL';

-- Sample pricing rules
-- INSERT INTO v2_pricing_rules (pricing_table_id, weight_from, weight_to, calculation_method, price)
-- SELECT pt.id, 0.100, 1.000, 'fixed', 15.99
-- FROM v2_pricing_tables pt
-- JOIN v2_carriers c ON pt.carrier_id = c.id
-- JOIN v2_pricing_zones z ON pt.zone_id = z.id
-- WHERE c.code = 'INPOST' AND z.code = 'LOCAL' AND pt.service_type = 'standard';

-- =====================================================
-- SCHEMA DOCUMENTATION
-- =====================================================

/*
BUSINESS LOGIC OVERVIEW:

1. PRICING STRUCTURE:
   - Each carrier has pricing tables for different zones and service types
   - Pricing rules within tables define weight/dimension-based pricing tiers
   - Additional services have their own pricing structure

2. CUSTOMER PRICING:
   - B2B customers can have negotiated rates based on base pricing tables
   - Supports percentage discounts, fixed discounts, volume pricing
   - Customer pricing overrides standard pricing

3. PROMOTIONAL PRICING:
   - Time-limited promotions can apply to standard or customer pricing
   - Supports various discount types: percentage, fixed, buy-X-get-Y, tier-based
   - Usage limits and target audience controls

4. AUDIT TRAIL:
   - All customer pricing changes are logged with full audit information
   - Supports compliance and historical tracking

5. PERFORMANCE:
   - Optimized indexes for common lookup patterns
   - JSON fields for flexible configuration storage
   - Efficient foreign key relationships

USAGE PATTERNS:

1. Price Calculation Flow:
   a) Determine carrier and destination zone
   b) Find appropriate pricing table
   c) Apply pricing rules based on weight/dimensions
   d) Add additional service prices
   e) Check for customer-specific pricing
   f) Apply any valid promotions
   g) Calculate final price with taxes

2. Configuration Management:
   - Pricing tables can be versioned for historical tracking
   - Effective date ranges allow future pricing updates
   - Rules can be activated/deactivated without deletion

3. Customer Management:
   - Customer pricing agreements with start/end dates
   - Automatic renewal options
   - Priority levels for complex pricing scenarios
*/