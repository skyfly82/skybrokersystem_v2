# SYSTEM CENNIKÓW KURIERSKICH - DOKUMENTACJA TECHNICZNA

## PRZEGLĄD SYSTEMU

System cenników kurierskich został zaprojektowany jako kompletne rozwiązanie do zarządzania cenami usług kurierskich w systemie SkyBroker. Obsługuje 5 głównych kurierów (InPost, DHL, UPS, DPD, Meest) z pełnym wsparciem dla:

- Stref geograficznych (lokalne, krajowe, międzynarodowe)
- Progów wagowych i wymiarowych
- Usług dodatkowych (pobranie, ubezpieczenie, SMS)
- Cenników negocjowanych B2B/B2C
- Promocji czasowych i rabatów wolumenowych
- Pełnej historii zmian (audit trail)

## ARCHITEKTURA BAZY DANYCH

### 1. Tabele Główne

#### v2_pricing_zones - Strefy Geograficzne
```sql
CREATE TABLE v2_pricing_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) UNIQUE NOT NULL,          -- Kod strefy (LOCAL, NAT_PL, EU, WORLD)
    name VARCHAR(100) NOT NULL,                -- Nazwa strefy
    description TEXT,                          -- Opis strefy
    zone_type VARCHAR(20) NOT NULL,            -- Typ: local/national/international
    countries JSON,                            -- Lista kodów krajów ISO
    postal_code_patterns JSON,                 -- Wzorce kodów pocztowych
    is_active BOOLEAN DEFAULT TRUE,            -- Czy strefa jest aktywna
    sort_order INT DEFAULT 0,                  -- Kolejność sortowania
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
);
```

**Logika biznesowa:**
- Automatyczne wykrywanie strefy na podstawie kodu kraju i kodu pocztowego
- Hierarchia: lokalna → krajowa → międzynarodowa
- Możliwość definiowania wzorców kodów pocztowych regex

#### v2_carriers - Kurierzy
```sql
CREATE TABLE v2_carriers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,          -- Kod kuriera (INPOST, DHL, UPS, DPD, MEEST)
    name VARCHAR(100) NOT NULL,                -- Nazwa kuriera
    logo_url VARCHAR(255),                     -- URL do logo
    api_endpoint VARCHAR(255),                 -- Endpoint API
    api_config JSON,                           -- Konfiguracja API
    default_service_type VARCHAR(50),          -- Domyślny typ usługi
    supported_zones JSON NOT NULL,             -- Obsługiwane strefy
    max_weight_kg DECIMAL(8,3),               -- Maksymalna waga (kg)
    max_dimensions_cm JSON,                    -- Maksymalne wymiary [L,W,H]
    is_active BOOLEAN DEFAULT TRUE,            -- Czy kurierak aktywny
    sort_order INT DEFAULT 0,                  -- Kolejność wyświetlania
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
);
```

**Dane domyślne:**
- **InPost**: max 25kg, [64,38,64]cm, strefy: LOCAL, NAT_PL, EU
- **DHL**: max 70kg, [120,80,80]cm, strefy: NAT_PL, EU, EU_WEST, EU_EAST, WORLD
- **UPS**: max 70kg, [150,100,100]cm, strefy: NAT_PL, EU, EU_WEST, EU_EAST, WORLD
- **DPD**: max 31.5kg, [175,100,70]cm, strefy: LOCAL, NAT_PL, EU, EU_WEST, EU_EAST
- **Meest**: max 30kg, [100,60,60]cm, strefy: EU_EAST, WORLD

#### v2_pricing_tables - Główne Tabele Cenowe
```sql
CREATE TABLE v2_pricing_tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carrier_id INT NOT NULL,                   -- FK do v2_carriers
    zone_id INT NOT NULL,                      -- FK do v2_pricing_zones
    service_type VARCHAR(50) DEFAULT 'standard', -- standard/express/overnight/economy/premium
    pricing_model VARCHAR(20) DEFAULT 'weight',  -- weight/volumetric/hybrid
    version INT DEFAULT 1,                     -- Wersja cennika
    name VARCHAR(100) NOT NULL,                -- Nazwa cennika
    description TEXT,                          -- Opis cennika
    base_price DECIMAL(10,4) NOT NULL,        -- Cena bazowa
    min_weight_kg DECIMAL(8,3) DEFAULT 0.100, -- Minimalna waga
    max_weight_kg DECIMAL(8,3),               -- Maksymalna waga
    min_dimensions_cm JSON,                    -- Minimalne wymiary
    max_dimensions_cm JSON,                    -- Maksymalne wymiary
    volumetric_divisor INT,                    -- Dzielnik wagi objętościowej
    currency VARCHAR(3) DEFAULT 'PLN',         -- Waluta
    tax_rate DECIMAL(5,2),                    -- Stawka VAT (%)
    config JSON,                              -- Dodatkowa konfiguracja
    effective_from DATETIME DEFAULT CURRENT_TIMESTAMP, -- Data rozpoczęcia
    effective_until DATETIME,                  -- Data zakończenia
    is_active BOOLEAN DEFAULT TRUE,            -- Czy aktywna
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,                           -- FK do v2_users
    updated_by INT,                           -- FK do v2_users
    
    UNIQUE INDEX (carrier_id, zone_id, service_type, version),
    FOREIGN KEY (carrier_id) REFERENCES v2_carriers (id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES v2_pricing_zones (id) ON DELETE CASCADE
);
```

**Modele cenowe:**
- **weight**: Cennik bazuje na wadze fizycznej
- **volumetric**: Cennik bazuje na wadze objętościowej
- **hybrid**: Wybiera większą z wag (fizyczna vs objętościowa)

#### v2_pricing_rules - Reguły Cenowe
```sql
CREATE TABLE v2_pricing_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pricing_table_id INT NOT NULL,             -- FK do v2_pricing_tables
    name VARCHAR(100),                         -- Nazwa reguły
    weight_from DECIMAL(8,3) NOT NULL,        -- Waga od (kg)
    weight_to DECIMAL(8,3),                   -- Waga do (kg), NULL = bez limitu
    dimensions_from JSON,                      -- Wymiary od [L,W,H]
    dimensions_to JSON,                        -- Wymiary do [L,W,H]
    calculation_method VARCHAR(20) DEFAULT 'fixed', -- fixed/per_kg/per_kg_step/percentage
    price DECIMAL(10,4) NOT NULL,             -- Cena bazowa
    price_per_kg DECIMAL(10,4),               -- Cena za kg
    weight_step DECIMAL(8,3),                 -- Krok wagowy
    min_price DECIMAL(10,4),                  -- Minimalna cena
    max_price DECIMAL(10,4),                  -- Maksymalna cena
    tax_rate_override DECIMAL(5,2),           -- Nadpisanie stawki VAT
    config JSON,                              -- Konfiguracja reguły
    sort_order INT DEFAULT 0,                  -- Kolejność wykonania
    is_active BOOLEAN DEFAULT TRUE,            -- Czy aktywna
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE INDEX (pricing_table_id, weight_from, weight_to),
    FOREIGN KEY (pricing_table_id) REFERENCES v2_pricing_tables (id) ON DELETE CASCADE
);
```

**Metody kalkulacji:**
- **fixed**: Cena stała dla zakresu wagi
- **per_kg**: Cena bazowa + cena za każdy kg ponad minimum
- **per_kg_step**: Cena bazowa + cena za każdy pełny krok wagowy
- **percentage**: Cena jako procent wartości przesyłki

### 2. Usługi Dodatkowe

#### v2_additional_services - Usługi Dodatkowe
```sql
CREATE TABLE v2_additional_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carrier_id INT NOT NULL,                   -- FK do v2_carriers
    code VARCHAR(50) NOT NULL,                 -- Kod usługi
    name VARCHAR(100) NOT NULL,                -- Nazwa usługi
    description TEXT,                          -- Opis usługi
    service_type VARCHAR(30) NOT NULL,         -- Typ usługi
    pricing_type VARCHAR(20) DEFAULT 'fixed',  -- Typ cenowy
    default_price DECIMAL(10,4),              -- Cena domyślna
    min_price DECIMAL(10,4),                  -- Cena minimalna
    max_price DECIMAL(10,4),                  -- Cena maksymalna
    percentage_rate DECIMAL(5,2),             -- Stawka procentowa
    config JSON,                              -- Konfiguracja
    required_fields JSON,                      -- Wymagane pola
    supported_zones JSON,                      -- Obsługiwane strefy
    is_active BOOLEAN DEFAULT TRUE,            -- Czy aktywna
    sort_order INT DEFAULT 0,                  -- Kolejność
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE INDEX (carrier_id, code),
    FOREIGN KEY (carrier_id) REFERENCES v2_carriers (id) ON DELETE CASCADE
);
```

**Typy usług dodatkowych:**
- **cod**: Pobranie (Cash on Delivery)
- **insurance**: Ubezpieczenie przesyłki
- **sms**: Powiadomienia SMS
- **email**: Powiadomienia Email
- **saturday**: Dostarczenie w sobotę
- **return**: Usługa zwrotu
- **fragile**: Obsługa delikatnych przesyłek
- **priority**: Priorytetowa obsługa
- **pickup**: Odbiór przesyłki
- **signature**: Wymagany podpis

#### v2_additional_service_prices - Ceny Usług w Cennikach
```sql
CREATE TABLE v2_additional_service_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pricing_table_id INT NOT NULL,             -- FK do v2_pricing_tables
    additional_service_id INT NOT NULL,        -- FK do v2_additional_services
    price DECIMAL(10,4) NOT NULL,             -- Cena nadpisana
    min_price DECIMAL(10,4),                  -- Min cena nadpisana
    max_price DECIMAL(10,4),                  -- Max cena nadpisana
    percentage_rate DECIMAL(5,2),             -- Stawka % nadpisana
    config JSON,                              -- Konfiguracja specyficzna
    weight_tiers JSON,                         -- Progi wagowe
    value_tiers JSON,                          -- Progi wartościowe
    is_active BOOLEAN DEFAULT TRUE,            -- Czy aktywna
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE INDEX (pricing_table_id, additional_service_id),
    FOREIGN KEY (pricing_table_id) REFERENCES v2_pricing_tables (id) ON DELETE CASCADE,
    FOREIGN KEY (additional_service_id) REFERENCES v2_additional_services (id) ON DELETE CASCADE
);
```

### 3. Cenniki Klientów B2B

#### v2_customer_pricing - Cenniki Negocjowane
```sql
CREATE TABLE v2_customer_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,                  -- FK do v2_customers
    base_pricing_table_id INT NOT NULL,       -- FK do v2_pricing_tables (bazowy)
    contract_name VARCHAR(100) NOT NULL,       -- Nazwa kontraktu
    contract_number VARCHAR(50),               -- Numer kontraktu
    discount_type VARCHAR(20) DEFAULT 'percentage', -- percentage/fixed/volume/custom_rules
    base_discount DECIMAL(5,2),               -- Rabat bazowy (%)
    fixed_discount DECIMAL(10,4),             -- Rabat stały (kwota)
    minimum_order_value DECIMAL(10,4),        -- Min wartość zamówienia
    maximum_order_value DECIMAL(10,4),        -- Max wartość zamówienia
    volume_discounts JSON,                     -- Rabaty wolumenowe
    volume_period VARCHAR(20) DEFAULT 'monthly', -- Okres rozliczeniowy
    custom_rules JSON,                         -- Reguły niestandardowe
    service_discounts JSON,                    -- Rabaty na usługi
    payment_terms JSON,                        -- Warunki płatności
    free_shipping_threshold DECIMAL(10,4),    -- Próg darmowej wysyłki
    tax_rate_override DECIMAL(5,2),           -- Nadpisanie VAT
    currency_override VARCHAR(3),              -- Nadpisanie waluty
    priority_level INT DEFAULT 1,              -- Poziom priorytetu
    effective_from DATETIME DEFAULT CURRENT_TIMESTAMP, -- Data rozpoczęcia
    effective_until DATETIME,                  -- Data zakończenia
    is_active BOOLEAN DEFAULT TRUE,            -- Czy aktywny
    auto_renewal BOOLEAN DEFAULT FALSE,        -- Auto-odnowienie
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,                           -- FK do v2_users
    updated_by INT,                           -- FK do v2_users
    
    UNIQUE INDEX (customer_id, base_pricing_table_id),
    FOREIGN KEY (customer_id) REFERENCES v2_customers (id) ON DELETE CASCADE,
    FOREIGN KEY (base_pricing_table_id) REFERENCES v2_pricing_tables (id) ON DELETE CASCADE
);
```

**Typy rabatów:**
- **percentage**: Rabat procentowy od ceny bazowej
- **fixed**: Stała kwota rabatu za przesyłkę
- **volume**: Rabat wolumenowy na podstawie liczby przesyłek
- **custom_rules**: Niestandardowe reguły rabatowe

### 4. Promocje i Rabaty Czasowe

#### v2_promotional_pricing - Promocje
```sql
CREATE TABLE v2_promotional_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pricing_table_id INT,                      -- FK do v2_pricing_tables (opcjonalne)
    customer_pricing_id INT,                   -- FK do v2_customer_pricing (opcjonalne)
    name VARCHAR(100) NOT NULL,                -- Nazwa promocji
    description TEXT,                          -- Opis promocji
    promo_code VARCHAR(50) UNIQUE,            -- Kod promocyjny
    discount_type VARCHAR(20) DEFAULT 'percentage', -- Typ rabatu
    discount_value DECIMAL(10,4) NOT NULL,    -- Wartość rabatu
    minimum_order_value DECIMAL(10,4),        -- Min wartość zamówienia
    maximum_discount_amount DECIMAL(10,4),    -- Max kwota rabatu
    target_type VARCHAR(20) DEFAULT 'all',     -- all/carrier/zone/service_type/customer
    target_values JSON,                        -- Wartości docelowe
    promotion_config JSON,                     -- Konfiguracja promocji
    usage_limit INT,                          -- Limit użyć
    usage_limit_type VARCHAR(20) DEFAULT 'total', -- total/per_customer/per_day
    usage_count INT DEFAULT 0,                -- Liczba użyć
    valid_from DATETIME NOT NULL,             -- Data rozpoczęcia
    valid_until DATETIME NOT NULL,            -- Data zakończenia
    priority INT DEFAULT 1,                   -- Priorytet
    stackable BOOLEAN DEFAULT FALSE,           -- Czy stackowalna
    is_active BOOLEAN DEFAULT TRUE,            -- Czy aktywna
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,                           -- FK do v2_users
    updated_by INT,                           -- FK do v2_users
    
    FOREIGN KEY (pricing_table_id) REFERENCES v2_pricing_tables (id) ON DELETE CASCADE,
    FOREIGN KEY (customer_pricing_id) REFERENCES v2_customer_pricing (id) ON DELETE CASCADE
);
```

**Typy promocji:**
- **percentage**: Rabat procentowy
- **fixed_amount**: Stała kwota rabatu
- **free_shipping**: Darmowa wysyłka
- **buy_x_get_y**: Kup X otrzymaj Y
- **tier_discount**: Rabat progresywny

### 5. Audit Trail

#### v2_customer_pricing_audit - Historia Zmian
```sql
CREATE TABLE v2_customer_pricing_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_pricing_id INT NOT NULL,          -- FK do v2_customer_pricing
    action VARCHAR(20) NOT NULL,               -- created/updated/activated/deactivated/expired/renewed/deleted
    description TEXT,                          -- Opis zmiany
    old_values JSON,                          -- Poprzednie wartości
    new_values JSON,                          -- Nowe wartości
    metadata JSON,                            -- Dodatkowe metadane
    user_agent VARCHAR(255),                  -- User agent
    ip_address VARCHAR(45),                   -- Adres IP
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- Data zmiany
    created_by INT,                           -- FK do v2_users
    
    INDEX (customer_pricing_id),
    INDEX (action),
    INDEX (created_at),
    INDEX (created_by),
    FOREIGN KEY (customer_pricing_id) REFERENCES v2_customer_pricing (id) ON DELETE CASCADE
);
```

## INDEKSY I OPTYMALIZACJA

### Indeksy Wydajnościowe
```sql
-- Optymalizacja wyszukiwania cenników
CREATE INDEX idx_pricing_performance ON v2_pricing_tables 
    (carrier_id, zone_id, is_active, effective_from, effective_until);

-- Optymalizacja wyszukiwania cenników klientów
CREATE INDEX idx_customer_pricing_lookup ON v2_customer_pricing 
    (customer_id, is_active, effective_from, effective_until);

-- Optymalizacja promocji
CREATE INDEX idx_promotion_validity ON v2_promotional_pricing 
    (is_active, valid_from, valid_until);
```

### Strategie Partycjonowania (Opcjonalne)
```sql
-- Partycjonowanie tabeli audit po datach (przykład)
ALTER TABLE v2_customer_pricing_audit PARTITION BY RANGE (YEAR(created_at)) (
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

## LOGIKA BIZNESOWA

### Algorytm Kalkulacji Ceny

1. **Określenie Kuriera i Strefy**
   - Wybór kuriera przez klienta
   - Automatyczne wykrycie strefy na podstawie kodu kraju/kodu pocztowego

2. **Znalezienie Cennika**
   - Wyszukanie aktywnego cennika dla kuriera i strefy
   - Sprawdzenie dat ważności (effective_from/effective_until)

3. **Aplikacja Reguł Cenowych**
   - Znalezienie reguły odpowiadającej wadze/wymiarom
   - Kalkulacja ceny bazowej według metody (fixed/per_kg/per_kg_step/percentage)

4. **Dodanie Usług Dodatkowych**
   - Kalkulacja cen usług dodatkowych
   - Zastosowanie progów wagowych/wartościowych

5. **Cennik Klienta B2B**
   - Sprawdzenie czy klient ma negocjowany cennik
   - Aplikacja rabatów (percentage/fixed/volume/custom)

6. **Promocje**
   - Wyszukanie aktywnych promocji
   - Sprawdzenie kryteriów (daty, limity użyć, wartość min)
   - Aplikacja rabatów promocyjnych

7. **Finalizacja**
   - Dodanie podatków (VAT)
   - Sprawdzenie limitów min/max
   - Zwrócenie końcowej ceny

### Przykład Implementacji w PHP (Symfony)

```php
<?php

namespace App\Service;

use App\Entity\PricingTable;
use App\Repository\PricingTableRepository;

class PricingCalculatorService
{
    public function calculateShippingPrice(
        string $carrierCode,
        string $destinationCountry,
        string $postalCode,
        float $weight,
        array $dimensions,
        ?int $customerId = null,
        array $additionalServices = [],
        ?string $promoCode = null
    ): PriceCalculationResult {
        
        // 1. Określ strefę
        $zone = $this->determineZone($destinationCountry, $postalCode);
        
        // 2. Znajdź cennik
        $pricingTable = $this->findPricingTable($carrierCode, $zone->getCode());
        
        // 3. Kalkuluj cenę bazową
        $basePrice = $this->calculateBasePrice($pricingTable, $weight, $dimensions);
        
        // 4. Dodaj usługi dodatkowe
        $additionalServicesPrice = $this->calculateAdditionalServices($pricingTable, $additionalServices, $weight);
        
        // 5. Sprawdź cennik klienta
        $customerDiscount = $this->calculateCustomerDiscount($customerId, $pricingTable, $basePrice);
        
        // 6. Zastosuj promocje
        $promotionalDiscount = $this->calculatePromotionalDiscount($promoCode, $basePrice, $customerId);
        
        // 7. Finalizuj
        $totalPrice = $basePrice + $additionalServicesPrice - $customerDiscount - $promotionalDiscount;
        $totalPriceWithTax = $totalPrice * (1 + $pricingTable->getTaxRate() / 100);
        
        return new PriceCalculationResult($totalPriceWithTax, [
            'base_price' => $basePrice,
            'additional_services' => $additionalServicesPrice,
            'customer_discount' => $customerDiscount,
            'promotional_discount' => $promotionalDiscount,
            'tax_rate' => $pricingTable->getTaxRate(),
            'currency' => $pricingTable->getCurrency()
        ]);
    }
}
```

## KONFIGURACJA DOMYŚLNA

### Strefy Geograficzne

| Kod | Nazwa | Typ | Kraje | Opis |
|-----|-------|-----|-------|------|
| LOCAL | Lokalna | local | PL | Przesyłki w obrębie miasta/regionu |
| NAT_PL | Krajowa | national | PL | Przesyłki w obrębie Polski |
| EU | Unia Europejska | international | AT,BE,BG,HR,CY,CZ,DK,EE,FI,FR,DE,GR,HU,IE,IT,LV,LT,LU,MT,NL,PT,RO,SK,SI,ES,SE | Kraje UE |
| EU_WEST | Europa Zachodnia | international | AT,BE,FR,DE,LU,NL,CH | Europa Zachodnia |
| EU_EAST | Europa Wschodnia | international | BG,CZ,HU,RO,SK,SI | Europa Wschodnia |
| WORLD | Międzynarodowa | international | NULL | Wszystkie pozostałe kraje |

### Kurierzy i Ograniczenia

| Kurier | Max Waga (kg) | Max Wymiary (cm) | Obsługiwane Strefy |
|--------|---------------|------------------|-------------------|
| InPost | 25.0 | 64×38×64 | LOCAL, NAT_PL, EU |
| DHL | 70.0 | 120×80×80 | NAT_PL, EU, EU_WEST, EU_EAST, WORLD |
| UPS | 70.0 | 150×100×100 | NAT_PL, EU, EU_WEST, EU_EAST, WORLD |
| DPD | 31.5 | 175×100×70 | LOCAL, NAT_PL, EU, EU_WEST, EU_EAST |
| Meest | 30.0 | 100×60×60 | EU_EAST, WORLD |

## BEZPIECZEŃSTWO I COMPLIANCE

### Kontrola Dostępu
- Wszystkie operacje modyfikujące wymagają autoryzacji
- Różne poziomy dostępu dla różnych ról użytkowników
- Audit log dla wszystkich zmian cenników

### Zgodność z RODO
- Anonimizacja danych klientów w logach audit
- Możliwość usunięcia danych klienta z zachowaniem integralności systemu

### Backup i Recovery
- Regularne backup'y bazy danych z tabelami cenowymi
- Point-in-time recovery dla krytycznych zmian cenników

## MONITORING I ALERTING

### Metryki Biznesowe
- Liczba kalkulacji cen na minutę/godzinę
- Średni czas odpowiedzi kalkulatora cen
- Wykorzystanie promocji i kodów rabatowych
- Top używane kombinacje kurier-strefa

### Alerty
- Alert przy dezaktywacji cennika
- Alert przy przekroczeniu progów użycia promocji
- Alert przy błędach kalkulacji cen

## ROZWÓJ SYSTEMU

### Zaplanowane Funkcjonalności
1. **Dynamic Pricing**: Automatyczne dostosowywanie cen na podstawie popytu
2. **ML Pricing**: Używanie machine learning do optymalizacji cen
3. **Real-time API Integration**: Integracja z API kurierów dla rzeczywistych cen
4. **Multi-currency Support**: Pełne wsparcie dla wielu walut
5. **Advanced Analytics**: Zaawansowane raporty i analityka cenowa

### Rozszerzalność
System został zaprojektowany z myślą o łatwej rozszerzalności:
- JSON fields dla elastycznej konfiguracji
- Plugin-based architecture dla nowych kurierów
- Event-driven architecture dla integracji zewnętrznych
- API-first design dla łatwej integracji

---

*Ten dokument będzie aktualizowany wraz z rozwojem systemu. Wersja: 1.0, Data: 2025-09-09*