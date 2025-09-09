# System Cenników Kurierskich - Dokumentacja Techniczna

## Przegląd Systemu

System cenników kurierskich został zaprojektowany zgodnie z zasadami Domain-Driven Design i Clean Architecture, obsługując kompleksowe wyceny dla 5 głównych kurierów (InPost, DHL, UPS, DPD, Meest) z pełną elastycznością cenową.

## Architektura Systemu

### Główne Domeny
- **Pricing Domain** - Zarządzanie cennikami i kalkulacjami
- **Customer Domain** - Obsługa klientów B2B/B2C  
- **Promotion Domain** - Promocje i rabaty
- **Audit Domain** - Historia zmian i kontrola wersji

## Struktura Tabel

### 1. v2_pricing_zones - Strefy Geograficzne

**Cel:** Definicja stref geograficznych dla różnicowania cen kurierskich.

```sql
-- Główne kolumny
id                    INT PRIMARY KEY AUTO_INCREMENT
code                  VARCHAR(10) UNIQUE NOT NULL -- Kod strefy (LOCAL, NAT_PL, EU)
name                  VARCHAR(100) NOT NULL       -- Nazwa wyświetlana
zone_type            ENUM('local','national','international')
countries            JSON                         -- Kody ISO krajów
postal_code_patterns JSON                         -- Wzorce kodów pocztowych
is_active            BOOLEAN DEFAULT TRUE
```

**Logika Biznesowa:**
- Automatyczne wykrywanie strefy na podstawie kodu pocztowego
- Hierarchiczna struktura: lokalne → krajowe → międzynarodowe
- Elastyczne mapowanie krajów do stref

**Przykładowe dane:**
```json
{
  "code": "EU_WEST",
  "countries": ["DE", "FR", "AT", "BE", "NL", "LU", "CH"],
  "postal_code_patterns": ["^[0-9]{5}$", "^[A-Z]{1}[0-9]{4}$"]
}
```

### 2. v2_carriers - Dostawcy Usług Kurierskich

**Cel:** Definicja kurierów z ich możliwościami technicznymi i geograficznymi.

```sql
-- Główne kolumny  
id                   INT PRIMARY KEY AUTO_INCREMENT
code                 VARCHAR(20) UNIQUE NOT NULL  -- INPOST, DHL, UPS, DPD, MEEST
name                 VARCHAR(100) NOT NULL        -- Nazwa wyświetlana
supported_zones      JSON NOT NULL               -- Obsługiwane strefy
max_weight_kg        DECIMAL(8,3)                -- Max waga w kg
max_dimensions_cm    JSON                        -- Max wymiary [d,sz,w]
api_config          JSON                         -- Konfiguracja API
```

**Logika Biznesowa:**
- Walidacja możliwości kuriera przed kalkulacją ceny
- Integracja z API kurierów
- Ograniczenia techniczne (waga, wymiary)

### 3. v2_pricing_tables - Główne Tabele Cenowe

**Cel:** Podstawowe cenniki per kurier-strefa-usługa z elastyczną strukturą wagową.

```sql
-- Struktura wagowa
weight_tiers         JSON NOT NULL              -- Progi wagowe z cenami
dimensional_weight_divisor INT DEFAULT 5000     -- Dzielnik wagi wymiarowej
minimum_charge       DECIMAL(10,2)              -- Minimalna opłata
fuel_surcharge_percent DECIMAL(5,2)             -- Dopłata paliwowa

-- Terminy dostawy
estimated_delivery_days_min INT                 -- Min dni dostawy
estimated_delivery_days_max INT                 -- Max dni dostawy

-- Ważność
effective_from       DATE NOT NULL              -- Data wejścia w życie
effective_to         DATE                       -- Data wygaśnięcia
```

**Przykład struktury weight_tiers:**
```json
[
  {"max_kg": 1.0, "price_base": 15.00, "price_per_kg": 0},
  {"max_kg": 5.0, "price_base": 18.00, "price_per_kg": 2.50},
  {"max_kg": 10.0, "price_base": 25.00, "price_per_kg": 1.80},
  {"max_kg": 25.0, "price_base": 35.00, "price_per_kg": 1.50}
]
```

**Logika Kalkulacji:**
1. Oblicz wagę wymiarową: `(długość × szerokość × wysokość) / divisor`
2. Wybierz większą z wagi rzeczywistej i wymiarowej
3. Znajdź odpowiedni próg wagowy
4. Zastosuj wzór: `price_base + (waga_nad_progiem × price_per_kg)`

### 4. v2_additional_services - Usługi Dodatkowe

**Cel:** Cennik dodatkowych usług (pobranie, ubezpieczenie, SMS, doręczenie sobotnie).

```sql
-- Rodzaje cenowania
pricing_type         ENUM('fixed','percentage','tiered')
fixed_price          DECIMAL(10,2)              -- Stała cena
percentage_rate      DECIMAL(5,2)               -- Procent od wartości
pricing_tiers        JSON                       -- Struktura progowa

-- Ograniczenia
max_value_amount     DECIMAL(12,2)              -- Max wartość ubezpieczenia/pobrania
applies_to_zones     JSON                       -- Strefy dostępności
is_mandatory         BOOLEAN DEFAULT FALSE      -- Czy obowiązkowa
```

**Przykłady usług:**
- **COD (Pobranie)**: 2.5% wartości, min 5 PLN, max 50 PLN
- **Ubezpieczenie**: 1% wartości, min 2 PLN, max wartość 50,000 PLN  
- **SMS**: Stała opłata 1 PLN
- **Sobota**: Dopłata 15 PLN

### 5. v2_customer_pricing - Cenniki B2B

**Cel:** Negocjowane cenniki dla klientów biznesowych z rabatami i specjalnymi warunkami.

```sql
-- Rodzaje rabatów
discount_type        ENUM('percentage','fixed_amount','custom_rates')
discount_percentage  DECIMAL(5,2)               -- Rabat procentowy
custom_weight_tiers  JSON                       -- Własne progi wagowe

-- Warunki wolumenowe  
monthly_volume_threshold INT                     -- Min przesyłki/miesiąc
volume_discount_tiers JSON                      -- Rabaty wolumenowe
minimum_monthly_spend DECIMAL(10,2)             -- Min wydatki/miesiąc

-- Warunki płatności
credit_limit         DECIMAL(12,2)              -- Limit kredytowy
payment_terms_days   INT DEFAULT 30             -- Termin płatności
```

**Przykład rabatów wolumenowych:**
```json
[
  {"min_shipments": 50, "discount_percent": 5.0},
  {"min_shipments": 200, "discount_percent": 10.0}, 
  {"min_shipments": 500, "discount_percent": 15.0}
]
```

### 6. v2_promotional_pricing - Promocje i Kampanie

**Cel:** Czasowe promocje, kody rabatowe, kampanie marketingowe.

```sql
-- Zakres promocji
applies_to_carriers  JSON                       -- ID kurierów
applies_to_zones     JSON                       -- ID stref  
customer_type        ENUM('all','b2b','b2c','new_customers')

-- Ograniczenia użycia
max_uses_total       INT                        -- Limit ogólny
max_uses_per_customer INT                       -- Limit per klient
min_order_value      DECIMAL(10,2)              -- Min wartość zamówienia
current_uses         INT DEFAULT 0              -- Aktualne użycia

-- Okres ważności
valid_from           DATETIME NOT NULL          -- Początek promocji
valid_to             DATETIME NOT NULL          -- Koniec promocji
```

**Typy promocji:**
- **Rabat procentowy**: 10% zniżki na wszystkie przesyłki
- **Stała kwota**: 5 PLN zniżki od każdej przesyłki  
- **Darmowa usługa**: Bezpłatne ubezpieczenie lub SMS

### 7. v2_pricing_rules - Reguły Biznesowe

**Cel:** Automatyczne reguły weryfikacji i modyfikacji cen.

```sql
-- Typy reguł
rule_type            ENUM('weight_adjustment','dimension_check','zone_restriction',
                          'service_compatibility','minimum_charge','surcharge')

-- Akcje  
action_type          ENUM('reject','surcharge','modify_price','add_service','warning')
rule_config          JSON NOT NULL              -- Konfiguracja reguły
action_config        JSON                       -- Parametry akcji
priority             INT DEFAULT 100            -- Priorytet wykonania
```

**Przykłady reguł:**
```json
{
  "rule_type": "dimension_check",
  "rule_config": {
    "max_total_dimensions": 300,
    "check_type": "sum_all_sides"
  },
  "action_type": "surcharge", 
  "action_config": {
    "surcharge_amount": 25.00,
    "reason": "Przesyłka negabarytowa"
  }
}
```

### 8. v2_pricing_audit_log - Historia Zmian

**Cel:** Pełna historia wszystkich zmian cenników dla zgodności i kontroli.

```sql
-- Identyfikacja zmiany
table_name           VARCHAR(50) NOT NULL       -- Zmieniona tabela
record_id            INT NOT NULL               -- ID rekordu
action               ENUM('INSERT','UPDATE','DELETE')

-- Szczegóły zmiany
field_name           VARCHAR(100)               -- Zmienione pole
old_value            JSON                       -- Stara wartość
new_value            JSON                       -- Nowa wartość

-- Kontekst
changed_by           INT NOT NULL               -- Użytkownik
changed_at           DATETIME DEFAULT NOW()     -- Czas zmiany
change_reason        TEXT                       -- Powód zmiany
ip_address           VARCHAR(45)                -- Adres IP
```

### 9. v2_price_calculation_cache - Cache Kalkulacji

**Cel:** Buforowanie obliczonych cen dla poprawy wydajności.

```sql
-- Hash parametrów kalkulacji
calculation_hash     VARCHAR(64) UNIQUE NOT NULL

-- Wyniki kalkulacji
base_price           DECIMAL(10,2) NOT NULL     -- Cena bazowa
additional_services_price DECIMAL(10,2)         -- Usługi dodatkowe  
promotional_discount DECIMAL(10,2)              -- Rabat promocyjny
customer_discount    DECIMAL(10,2)              -- Rabat klienta B2B
total_price          DECIMAL(10,2) NOT NULL     -- Cena finalna

-- Metadane cache
calculated_at        DATETIME DEFAULT NOW()     -- Czas kalkulacji
expires_at           DATETIME NOT NULL          -- Wygaśnięcie
hit_count            INT DEFAULT 1              -- Liczba użyć
```

## Algorytm Kalkulacji Ceny

### 1. Identyfikacja Parametrów
```php
$params = [
    'carrier_id' => $carrierId,
    'zone_id' => $zoneId,
    'service_type' => $serviceType,
    'weight_kg' => $weight,
    'dimensions_cm' => [$length, $width, $height],
    'declared_value' => $declaredValue,
    'additional_services' => ['COD', 'SMS'],
    'customer_id' => $customerId,
    'promotion_code' => $promoCode
];
```

### 2. Sprawdzenie Cache
```php
$hash = md5(json_encode($params));
$cachedPrice = $cacheRepository->findByHash($hash);
if ($cachedPrice && !$cachedPrice->isExpired()) {
    return $cachedPrice->getTotalPrice();
}
```

### 3. Kalkulacja Bazowa
```php
// Znajdź aktualny cennik
$pricingTable = $pricingRepository->findActivePricing(
    $carrierId, $zoneId, $serviceType
);

// Oblicz wagę efektywną
$dimensionalWeight = ($length * $width * $height) / $pricingTable->getDimensionalWeightDivisor();
$effectiveWeight = max($weight, $dimensionalWeight);

// Znajdź odpowiedni próg wagowy
$tier = $pricingTable->getWeightTierForWeight($effectiveWeight);
$basePrice = $tier['price_base'] + (max(0, $effectiveWeight - $tier['min_kg']) * $tier['price_per_kg']);
```

### 4. Usługi Dodatkowe
```php
$additionalServicesPrice = 0;
foreach ($additionalServices as $serviceCode) {
    $service = $additionalServicesRepository->findByCarrierAndCode($carrierId, $serviceCode);
    $additionalServicesPrice += $service->calculatePrice($declaredValue);
}
```

### 5. Rabaty Klienta B2B
```php
$customerDiscount = 0;
if ($customerId) {
    $customerPricing = $customerPricingRepository->findActiveForCustomer($customerId);
    if ($customerPricing) {
        $customerDiscount = $customerPricing->calculateDiscount($basePrice + $additionalServicesPrice);
    }
}
```

### 6. Promocje
```php
$promotionalDiscount = 0;
if ($promotionCode) {
    $promotion = $promotionalPricingRepository->findActiveByCode($promotionCode);
    if ($promotion && $promotion->isEligible($params)) {
        $promotionalDiscount = $promotion->calculateDiscount($basePrice + $additionalServicesPrice);
    }
}
```

### 7. Zastosowanie Reguł
```php
$rules = $pricingRulesRepository->findApplicableRules($params);
foreach ($rules as $rule) {
    $rule->apply($calculation); // Może modyfikować cenę lub dodać dopłaty
}
```

### 8. Finalizacja
```php
$totalPrice = $basePrice + $additionalServicesPrice - $customerDiscount - $promotionalDiscount;
$totalPrice = max($totalPrice, $pricingTable->getMinimumCharge());

// Zapisz w cache
$cacheRepository->save(new PriceCalculationCache($hash, $calculation, $expiresAt));

return $totalPrice;
```

## Optymalizacje Wydajności

### Indeksy Bazodanowe
- **Kompozytowe indeksy** dla najczęstszych zapytań
- **Partycjonowanie** tabeli audit_log po dacie
- **Cache query** dla statycznych danych (strefy, kurierzy)

### Strategia Cache
- **TTL**: 15 minut dla cen standardowych, 5 minut dla promocyjnych
- **Invalidacja**: Po zmianie cenników lub promocji  
- **Warm-up**: Prekalkuacja popularnych kombinacji

### Monitoring
- **Metryki**: Czas kalkulacji, hit rate cache, liczba reguł
- **Alerty**: Długie czasy obliczeń, błędy API kurierów
- **Reporting**: Analiza wykorzystania promocji i rabatów

## Bezpieczeństwo

### Autoryzacja
- **Cenniki publiczne**: Dostęp tylko do odczytu
- **Cenniki B2B**: Dostęp tylko dla właściciela i adminów
- **Promocje**: Kontrola uprawnień per typ klienta

### Audit Trail  
- **Pełna historia** wszystkich zmian cenników
- **Integralność danych** z podpisami cyfrowymi
- **Compliance** z wymogami prawnymi dotyczącymi przechowywania danych

## Integracje

### API Kurierów
- **Real-time rates**: Pobieranie aktualnych cen z API kurierów
- **Fallback pricing**: Użycie własnych cenników przy awarii API
- **Rate limiting**: Kontrola częstotliwości zapytań

### Systemy Zewnętrzne
- **ERP**: Synchronizacja cenników B2B z systemami klientów
- **Accounting**: Export raportów cenowych dla księgowości  
- **Marketing**: Integracja z systemami kampanii promocyjnych

Ta dokumentacja stanowi kompletny przewodnik implementacji systemu cenników kurierskich zgodny z najlepszymi praktykami architektury oprogramowania.