-- Create remaining pricing system tables

-- PRICING TABLES
CREATE TABLE v2_pricing_tables (
    id INT AUTO_INCREMENT NOT NULL,
    carrier_id INT NOT NULL,
    zone_id INT NOT NULL,
    service_type VARCHAR(50) NOT NULL DEFAULT 'standard',
    pricing_model VARCHAR(20) NOT NULL DEFAULT 'weight',
    version INT NOT NULL DEFAULT 1,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    base_price DECIMAL(10,4) NOT NULL,
    min_weight_kg DECIMAL(8,3) NOT NULL DEFAULT 0.100,
    max_weight_kg DECIMAL(8,3) DEFAULT NULL,
    min_dimensions_cm JSON DEFAULT NULL,
    max_dimensions_cm JSON DEFAULT NULL,
    volumetric_divisor INT DEFAULT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'PLN',
    tax_rate DECIMAL(5,2) DEFAULT NULL,
    config JSON DEFAULT NULL,
    effective_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    effective_until DATETIME DEFAULT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    
    PRIMARY KEY(id),
    INDEX IDX_PRICING_CARRIER_ZONE (carrier_id, zone_id),
    INDEX IDX_PRICING_ACTIVE (is_active),
    INDEX IDX_PRICING_EFFECTIVE (effective_from, effective_until),
    INDEX IDX_PRICING_SERVICE (service_type),
    UNIQUE INDEX UNQ_PRICING_CARRIER_ZONE_SERVICE_VERSION (carrier_id, zone_id, service_type, version),
    
    FOREIGN KEY (carrier_id) REFERENCES v2_carriers (id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES v2_pricing_zones (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- PRICING RULES
CREATE TABLE v2_pricing_rules (
    id INT AUTO_INCREMENT NOT NULL,
    pricing_table_id INT NOT NULL,
    name VARCHAR(100) DEFAULT NULL,
    weight_from DECIMAL(8,3) NOT NULL,
    weight_to DECIMAL(8,3) DEFAULT NULL,
    dimensions_from JSON DEFAULT NULL,
    dimensions_to JSON DEFAULT NULL,
    calculation_method VARCHAR(20) NOT NULL DEFAULT 'fixed',
    price DECIMAL(10,4) NOT NULL,
    price_per_kg DECIMAL(10,4) DEFAULT NULL,
    weight_step DECIMAL(8,3) DEFAULT NULL,
    min_price DECIMAL(10,4) DEFAULT NULL,
    max_price DECIMAL(10,4) DEFAULT NULL,
    tax_rate_override DECIMAL(5,2) DEFAULT NULL,
    config JSON DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY(id),
    INDEX IDX_RULE_TABLE (pricing_table_id),
    INDEX IDX_RULE_WEIGHT_RANGE (weight_from, weight_to),
    INDEX IDX_RULE_SORT (sort_order),
    
    FOREIGN KEY (pricing_table_id) REFERENCES v2_pricing_tables (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- ADDITIONAL SERVICES
CREATE TABLE v2_additional_services (
    id INT AUTO_INCREMENT NOT NULL,
    carrier_id INT NOT NULL,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    service_type VARCHAR(30) NOT NULL,
    pricing_type VARCHAR(20) NOT NULL DEFAULT 'fixed',
    default_price DECIMAL(10,4) DEFAULT NULL,
    min_price DECIMAL(10,4) DEFAULT NULL,
    max_price DECIMAL(10,4) DEFAULT NULL,
    percentage_rate DECIMAL(5,2) DEFAULT NULL,
    config JSON DEFAULT NULL,
    required_fields JSON DEFAULT NULL,
    supported_zones JSON DEFAULT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY(id),
    INDEX IDX_SERVICE_CARRIER (carrier_id),
    INDEX IDX_SERVICE_TYPE (service_type),
    INDEX IDX_SERVICE_ACTIVE (is_active),
    UNIQUE INDEX UNQ_SERVICE_CARRIER_CODE (carrier_id, code),
    
    FOREIGN KEY (carrier_id) REFERENCES v2_carriers (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- ADDITIONAL SERVICE PRICES
CREATE TABLE v2_additional_service_prices (
    id INT AUTO_INCREMENT NOT NULL,
    pricing_table_id INT NOT NULL,
    additional_service_id INT NOT NULL,
    price DECIMAL(10,4) NOT NULL,
    min_price DECIMAL(10,4) DEFAULT NULL,
    max_price DECIMAL(10,4) DEFAULT NULL,
    percentage_rate DECIMAL(5,2) DEFAULT NULL,
    config JSON DEFAULT NULL,
    weight_tiers JSON DEFAULT NULL,
    value_tiers JSON DEFAULT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY(id),
    INDEX IDX_SERVICE_PRICE_TABLE (pricing_table_id),
    INDEX IDX_SERVICE_PRICE_SERVICE (additional_service_id),
    INDEX IDX_SERVICE_PRICE_ACTIVE (is_active),
    UNIQUE INDEX UNQ_SERVICE_PRICE_TABLE_SERVICE (pricing_table_id, additional_service_id),
    
    FOREIGN KEY (pricing_table_id) REFERENCES v2_pricing_tables (id) ON DELETE CASCADE,
    FOREIGN KEY (additional_service_id) REFERENCES v2_additional_services (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- CUSTOMER PRICING
CREATE TABLE v2_customer_pricing (
    id INT AUTO_INCREMENT NOT NULL,
    customer_id INT NOT NULL,
    base_pricing_table_id INT NOT NULL,
    contract_name VARCHAR(100) NOT NULL,
    contract_number VARCHAR(50) DEFAULT NULL,
    discount_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
    base_discount DECIMAL(5,2) DEFAULT NULL,
    fixed_discount DECIMAL(10,4) DEFAULT NULL,
    minimum_order_value DECIMAL(10,4) DEFAULT NULL,
    maximum_order_value DECIMAL(10,4) DEFAULT NULL,
    volume_discounts JSON DEFAULT NULL,
    volume_period VARCHAR(20) DEFAULT 'monthly',
    custom_rules JSON DEFAULT NULL,
    service_discounts JSON DEFAULT NULL,
    payment_terms JSON DEFAULT NULL,
    free_shipping_threshold DECIMAL(10,4) DEFAULT NULL,
    tax_rate_override DECIMAL(5,2) DEFAULT NULL,
    currency_override VARCHAR(3) DEFAULT NULL,
    priority_level INT NOT NULL DEFAULT 1,
    effective_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    effective_until DATETIME DEFAULT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    auto_renewal BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    
    PRIMARY KEY(id),
    INDEX IDX_CUSTOMER_PRICING_CUSTOMER (customer_id),
    INDEX IDX_CUSTOMER_PRICING_TABLE (base_pricing_table_id),
    INDEX IDX_CUSTOMER_PRICING_ACTIVE (is_active),
    INDEX IDX_CUSTOMER_PRICING_EFFECTIVE (effective_from, effective_until),
    
    FOREIGN KEY (customer_id) REFERENCES v2_customers (id) ON DELETE CASCADE,
    FOREIGN KEY (base_pricing_table_id) REFERENCES v2_pricing_tables (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- PROMOTIONAL PRICING
CREATE TABLE v2_promotional_pricing (
    id INT AUTO_INCREMENT NOT NULL,
    pricing_table_id INT DEFAULT NULL,
    customer_pricing_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    promo_code VARCHAR(50) DEFAULT NULL,
    discount_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
    discount_value DECIMAL(10,4) NOT NULL,
    minimum_order_value DECIMAL(10,4) DEFAULT NULL,
    maximum_discount_amount DECIMAL(10,4) DEFAULT NULL,
    target_type VARCHAR(20) NOT NULL DEFAULT 'all',
    target_values JSON DEFAULT NULL,
    promotion_config JSON DEFAULT NULL,
    usage_limit INT DEFAULT NULL,
    usage_limit_type VARCHAR(20) DEFAULT 'total',
    usage_count INT NOT NULL DEFAULT 0,
    valid_from DATETIME NOT NULL,
    valid_until DATETIME NOT NULL,
    priority INT NOT NULL DEFAULT 1,
    stackable BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    
    PRIMARY KEY(id),
    INDEX IDX_PROMO_PRICING_TABLE (pricing_table_id),
    INDEX IDX_PROMO_CUSTOMER_PRICING (customer_pricing_id),
    INDEX IDX_PROMO_CODE (promo_code),
    INDEX IDX_PROMO_ACTIVE (is_active),
    INDEX IDX_PROMO_PERIOD (valid_from, valid_until),
    UNIQUE INDEX UNQ_PROMO_CODE (promo_code),
    
    FOREIGN KEY (pricing_table_id) REFERENCES v2_pricing_tables (id) ON DELETE CASCADE,
    FOREIGN KEY (customer_pricing_id) REFERENCES v2_customer_pricing (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- CUSTOMER PRICING AUDIT
CREATE TABLE v2_customer_pricing_audit (
    id INT AUTO_INCREMENT NOT NULL,
    customer_pricing_id INT NOT NULL,
    action VARCHAR(20) NOT NULL,
    description TEXT DEFAULT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    
    PRIMARY KEY(id),
    INDEX IDX_PRICING_AUDIT_CUSTOMER_PRICING (customer_pricing_id),
    INDEX IDX_PRICING_AUDIT_ACTION (action),
    INDEX IDX_PRICING_AUDIT_DATE (created_at),
    INDEX IDX_PRICING_AUDIT_USER (created_by),
    
    FOREIGN KEY (customer_pricing_id) REFERENCES v2_customer_pricing (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Insert default pricing zones
INSERT INTO v2_pricing_zones (code, name, description, zone_type, countries, postal_code_patterns, sort_order) VALUES
('LOCAL', 'Lokalna', 'Przesyłki w obrębie tego samego miasta/regionu', 'local', '["PL"]', '["^[0-9]{2}-[0-9]{3}$"]', 1),
('NAT_PL', 'Krajowa', 'Przesyłki w obrębie Polski', 'national', '["PL"]', '["^[0-9]{2}-[0-9]{3}$"]', 2),
('EU', 'Unia Europejska', 'Przesyłki do krajów UE', 'international', '["AT","BE","BG","HR","CY","CZ","DK","EE","FI","FR","DE","GR","HU","IE","IT","LV","LT","LU","MT","NL","PT","RO","SK","SI","ES","SE"]', NULL, 3),
('EU_WEST', 'Europa Zachodnia', 'Kraje Europy Zachodniej', 'international', '["AT","BE","FR","DE","LU","NL","CH"]', NULL, 4),
('EU_EAST', 'Europa Wschodnia', 'Kraje Europy Wschodniej', 'international', '["BG","CZ","HU","RO","SK","SI"]', NULL, 5),
('WORLD', 'Międzynarodowa', 'Przesyłki poza UE', 'international', NULL, NULL, 6)
ON DUPLICATE KEY UPDATE name=VALUES(name);