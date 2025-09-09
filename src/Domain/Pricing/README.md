# PricingRuleEngine - Dokumentacja

Zaawansowany silnik reguł cenowych dla systemu kurierskiego obsługujący różnorodne scenariusze kalkulacji cen i rabatów.

## Przegląd

PricingRuleEngine to elastyczny system reguł cenowych, który obsługuje:

- **Reguły wagowe** - kalkulacja na podstawie wagi przesyłki
- **Reguły wymiarowe** - uwzględnienie gabarytów i wagi objętościowej  
- **Reguły tiered** - progresywne stawki dla różnych progów
- **Promocje czasowe** - rabaty sezonowe, Black Friday, święta
- **Rabaty wolumenowe** - dla klientów o wysokim wolumenie
- **Kombinowanie reguł** - stackowanie rabatów z priorytetami

## Architektura

### Komponenty główne:

```
PricingRuleEngineInterface     - Kontrakt silnika
├── PricingRuleEngine          - Główna implementacja  
├── RuleValidator              - Walidator reguł
├── RuleContext               - Kontekst kalkulacji
├── RuleResult                - Wynik aplikacji reguł
└── RuleContextFactory        - Factory dla kontekstów
```

### Typy reguł:

- `weight` - na podstawie wagi (rzeczywistej/objętościowej)
- `dimension` - na podstawie wymiarów i gabarytów
- `volumetric` - kalkulacja wagi objętościowej
- `tiered` - poziomowe stawki (Bronze/Silver/Gold/Platinum)
- `progressive` - progresywne rabaty (więcej = taniej)
- `seasonal` - sezonowe promocje (Black Friday, Boże Narodzenie)
- `volume_based` - wolumenowe rabaty na podstawie historii

## Podstawowe użycie

### 1. Prosta kalkulacja cenowa

```php
use App\Domain\Pricing\Service\PricingRuleEngine;
use App\Domain\Pricing\Factory\RuleContextFactory;

$context = $contextFactory->createFromShipmentData(
    weightKg: 2.5,
    lengthCm: 30.0,
    widthCm: 20.0, 
    heightCm: 15.0,
    serviceType: 'standard',
    zoneCode: 'domestic',
    basePrice: '25.00'
);

$result = $ruleEngine->applyRules($context);

echo "Cena podstawowa: " . $result->originalPrice . " PLN\n";
echo "Cena końcowa: " . $result->finalPrice . " PLN\n";
echo "Rabat: " . $result->totalDiscount . " PLN\n";
echo "Oszczędności: " . $result->getDiscountPercentage() . "%\n";
```

### 2. Kalkulacja wagi objętościowej

```php
// Duża ale lekka paczka - liczy się waga objętościowa
$volumetricWeight = $ruleEngine->calculateVolumetricWeight(
    lengthCm: 60.0,
    widthCm: 40.0, 
    heightCm: 30.0,
    divisor: 5000.0  // Standard dla większości kurierów
);

$chargeableWeight = max($actualWeight, $volumetricWeight);
echo "Waga rozliczeniowa: {$chargeableWeight} kg\n";
```

### 3. Rabaty dla klientów biznesowych

```php
$context = $contextFactory->createEnrichedContext(
    weightKg: 5.0,
    lengthCm: 40.0,
    widthCm: 30.0,
    heightCm: 25.0, 
    serviceType: 'express',
    zoneCode: 'domestic',
    basePrice: '35.00',
    customer: $customer
);

$result = $ruleEngine->applyRules($context);

// Sprawdź zastosowane rabaty
foreach ($result->discountBreakdown as $discount) {
    echo "{$discount['type']}: {$discount['amount']} PLN\n";
}
```

### 4. Promocje Black Friday

```php
$context = $contextFactory->createBlackFridayContext(
    weightKg: 3.0,
    lengthCm: 35.0,
    widthCm: 25.0,
    heightCm: 20.0,
    serviceType: 'express', 
    zoneCode: 'eu',
    basePrice: '50.00',
    customer: $customer
);

$result = $ruleEngine->applyRules($context);
echo "Rabat Black Friday: " . $result->getDiscountByType('seasonal_promotion') . " PLN\n";
```

## Zaawansowane scenariusze

### Kombinowanie rabatów

Silnik automatycznie kombinuje compatible rabaty według priorytetów:

1. **Reguły wagowe/wymiarowe** (najwyższy priorytet)
2. **Rabaty klientów biznesowych** 
3. **Promocje sezonowe**
4. **Rabaty wolumenowe**
5. **Progresywne rabaty** (najniższy priorytet)

### Walidacja reguł

```php
$rules = [
    ['type' => 'weight', 'weight_from' => 0.0, 'weight_to' => 1.0, 'price' => 15.00],
    ['type' => 'seasonal', 'season' => 'black_friday', 'discount' => 25.0],
    ['type' => 'tiered', 'tiers' => [
        ['threshold' => 1000.0, 'discount' => 5.0],
        ['threshold' => 5000.0, 'discount' => 10.0]
    ]]
];

$isValid = $ruleEngine->validateRules($rules);
if (!$isValid) {
    throw new \InvalidArgumentException('Nieprawidłowe reguły cenowe');
}
```

### Sortowanie reguł według priorytetów

```php
$sortedRules = $ruleEngine->getPriorityRules($rules);
// Reguły posortowane rosnąco według priorytetu (1 = najwyższy)
```

## Kontekst kalkulacji (RuleContext)

### Pola podstawowe:
- `weightKg` - waga rzeczywista w kg
- `lengthCm`, `widthCm`, `heightCm` - wymiary w cm
- `serviceType` - typ usługi ('standard', 'express', 'economy')
- `zoneCode` - strefa dostawy ('domestic', 'eu', 'international')
- `basePrice` - cena podstawowa

### Pola kontekstowe:
- `customer` - obiekt klienta
- `calculationDate` - data kalkulacji
- `seasonalPeriod` - okres sezonowy
- `customerTier` - tier klienta (bronze/silver/gold/platinum)
- `monthlyOrderVolume` - miesięczny wolumen zamówień
- `isBusinessCustomer` - czy klient biznesowy

### Metody pomocnicze:
- `getVolumetricWeight()` - waga objętościowa
- `getChargeableWeight()` - waga rozliczeniowa (max z rzeczywistej i objętościowej)
- `isOversized()` - czy paczka przekracza standardowe wymiary
- `qualifiesForVolumeDiscount()` - czy kwalifikuje się do rabatu wolumenowego

## Wynik kalkulacji (RuleResult)

### Pola podstawowe:
```php
$result->originalPrice      // Cena wyjściowa
$result->finalPrice         // Cena końcowa po rabatach
$result->totalDiscount      // Łączny rabat
$result->appliedRules       // Zastosowane reguły
$result->appliedPromotions  // Zastosowane promocje
$result->discountBreakdown  // Szczegółowy rozkład rabatów
```

### Metody pomocnicze:
```php
$result->getDiscountPercentage()     // Procent rabatu
$result->getSavings()                // Kwota oszczędności  
$result->hasDiscounts()              // Czy są jakieś rabaty
$result->getAppliedRuleNames()       // Nazwy zastosowanych reguł
$result->getDiscountByType('tier')   // Rabat danego typu
$result->hasRuleType('seasonal')     // Czy zastosowano regułę typu
```

## Konfiguracja rabatów

### Rabaty tier (poziomowe):
- **Bronze**: 5% (>2000 PLN lifetime, >5 miesięcznych zamówień)
- **Silver**: 10% (>10000 PLN lifetime, >20 miesięcznych zamówień)  
- **Gold**: 15% (>25000 PLN lifetime, >50 miesięcznych zamówień)
- **Platinum**: 20% (>50000 PLN lifetime, >100 miesięcznych zamówień)

### Rabaty progresywne:
- **1000-2000 PLN**: 5%
- **2000-5000 PLN**: 7.5% 
- **5000-10000 PLN**: 10%
- **10000+ PLN**: 15%

### Rabaty sezonowe:
- **Black Friday**: 25%
- **Boże Narodzenie**: 15%
- **Lato**: 10%
- **Zima**: 5%

### Rabaty wolumenowe:
- **10+ miesięcznych zamówień + 1000+ PLN**: 5%
- **25+ miesięcznych zamówień + 2500+ PLN**: 10%
- **50+ miesięcznych zamówień + 5000+ PLN**: 15%
- **100+ miesięcznych zamówień + 10000+ PLN**: 20%

## Rozszerzanie systemu

### Dodanie nowego typu reguły:

1. Rozszerz `PricingRuleEngineInterface` o nową metodę
2. Implementuj metodę w `PricingRuleEngine`
3. Dodaj walidację w `RuleValidator`
4. Zdefiniuj logikę aplikacji reguły
5. Dodaj testy jednostkowe

### Przykład nowej reguły "loyalty":

```php
// W interfejsie
public function applyLoyaltyRules(array $rules, RuleContext $context): RuleResult;

// W implementacji  
public function applyLoyaltyRules(array $rules, RuleContext $context): RuleResult
{
    $loyaltyYears = $this->calculateLoyaltyYears($context->customer);
    $discount = min($loyaltyYears * 2, 20); // Max 20%
    
    // ... implementacja
}
```

## Integracja z systemem

PricingRuleEngine integruje się z:

- **PricingCalculatorService** - główny serwis cenowy
- **CustomerPricingRepository** - rabaty B2B 
- **PromotionalPricingRepository** - promocje czasowe
- **PricingRuleRepository** - reguły wagowe/wymiarowe
- **OrderService** - historia zamówień klienta

## Przykłady użycia

Zobacz szczegółowe przykłady w:
- `PricingRuleEngineExamples.php` - 10 praktycznych scenariuszy
- `PricingRuleEngineTest.php` - testy jednostkowe
- `RuleContextFactory.php` - factory pattern dla kontekstów

## Monitoring i debugging

### Logi aplikacji reguł:
```php
$this->logger->info('Pricing rules applied', [
    'customer_id' => $context->customer?->getId(),
    'original_price' => $result->originalPrice,
    'final_price' => $result->finalPrice, 
    'applied_rules' => $result->getAppliedRuleNames(),
    'total_discount' => $result->totalDiscount
]);
```

### Debug info w wynikach:
```php
$debugInfo = $result->debugInfo;
// Zawiera informacje o czasie wykonania, wagach, zastosowanych regułach
```

## Optymalizacja wydajności

1. **Cache reguł** - stosuj cache dla często używanych reguł
2. **Lazy loading** - ładuj dane klienta tylko gdy potrzeba  
3. **Bulk operations** - grupuj kalkulacje dla wielu przesyłek
4. **Query optimization** - optymalizuj zapytania do bazy

## Bezpieczeństwo

1. **Walidacja wejścia** - wszystkie parametry są walidowane
2. **Sanityzacja** - dane są oczyszczane przed użyciem
3. **Rate limiting** - zastosuj limity dla API
4. **Audit trail** - loguj wszystkie kalkulacje cenowe

## Backup i monitoring

- Monitoruj błędy kalkulacji w logach aplikacji
- Śledź wydajność zapytań do bazy
- Ustaw alerty dla niespodziewanych rabatów >50%
- Backup konfiguracji reguł przed zmianami