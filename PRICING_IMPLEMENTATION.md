# PRICING IMPLEMENTATION - System cenników kurierskich

## 📋 Plan wdrożenia systemu cenników kurierskich

### 🎯 **Analiza wymagań biznesowych**

#### **Kurierzy do obsługi:**
- **InPost** (Paczkomaty + kurier)
- **DHL** (krajowy + międzynarodowy)  
- **UPS** (krajowy + międzynarodowy)
- **DPD** (krajowy + międzynarodowy)
- **Meest** (głównie Ukraina/Europa Wschodnia)

#### **Typy klientów:**
- **B2C (Indywidualny)** - ceny standardowe, promocje
- **B2B (Firmowy)** - negocjowane stawki, rabaty wolumenowe

### 📐 **Architektura systemu cenników**

#### **1. Domena Pricing (nowa)**
```
src/Domain/Pricing/
├── Entity/
│   ├── PricingTable.php           # Główna tabela cenowa
│   ├── PricingZone.php            # Strefy geograficzne
│   ├── CarrierPricing.php         # Cenniki per kurier
│   ├── CustomerPricing.php        # Negocjowane cenniki B2B
│   ├── PricingRule.php            # Reguły cenowe (waga, wymiary)
│   └── PromotionalPricing.php     # Promocje i rabaty
├── Service/
│   ├── PricingCalculatorService.php
│   ├── CarrierRateService.php
│   └── PricingRuleEngine.php
├── Repository/
└── DTO/
```

#### **2. Integracja z domeną Shipment**
- Kalkulacja kosztów przed wysyłką
- Porównanie cen między kurierami
- Automatyczny wybór najtańszej opcji

### 🏗️ **Struktura bazy danych**

#### **Tabele główne:**
1. **v2_pricing_tables** - główne tabele cenowe
2. **v2_pricing_zones** - strefy geograficzne (kod pocztowy → strefa)
3. **v2_carrier_pricing** - cenniki per kurier per strefa
4. **v2_customer_pricing** - negocjowane cenniki B2B
5. **v2_pricing_rules** - reguły (waga, wymiary, typ paczki)
6. **v2_promotional_pricing** - promocje czasowe

#### **Kluczowe pola cenowe:**
- Waga (do 1kg, 1-5kg, 5-10kg, 10-30kg, >30kg)
- Wymiary (standardowa, niestandardowa, oversize)
- Typ usługi (standard, express, economy)
- Dodatkowe usługi (pobranie, ubezpieczenie, SMS)

#### **Konfiguracja klienta B2B:**
- Typ podatku: standard_vat | reverse_charge | vat_exempt
- Rabaty wolumenowe (progi miesięczne/roczne)
- Negocjowane stawki per usługa/strefa
- Limity kredytowe i terminy płatności

### 💰 **Model cenowy branży logistycznej**

#### **Główne składniki ceny:**
1. **Cena bazowa** - według wagi/strefy
2. **Dopłaty wymiarowe** - za gabaryty
3. **Usługi dodatkowe**:
   - Pobranie (+2-5 PLN)
   - Ubezpieczenie (0.5-2% wartości)  
   - SMS/email (+1-2 PLN)
   - Dostawa w sobotę (+5-10 PLN)
   - Zwrot dokumentów (+10-15 PLN)

#### **Strefy geograficzne (typowe):**
- **Strefa 1** - Miasto lokalne
- **Strefa 2** - Region/województwo  
- **Strefa 3** - Polska (pozostałe)
- **Strefa 4** - UE
- **Strefa 5** - Europa (non-UE)
- **Strefa 6** - Świat

### 📊 **Plan implementacji (6 tygodni)**

#### **Tydzień 1-2: Fundament**
1. **Analiza konkurencji** - zbieranie aktualnych cenników
2. **Projektowanie bazy danych** - schemat tabel cenowych
3. **Utworzenie encji** Doctrine dla domeny Pricing
4. **Migracje bazy danych**

#### **Tydzień 3-4: Logika biznesowa** 
1. **PricingCalculatorService** - główny kalkulator
2. **CarrierRateService** - obsługa stawek per kurier
3. **PricingRuleEngine** - reguły cenowe
4. **Strefy geograficzne** - mapowanie kodów pocztowych

#### **Tydzień 5-6: Integracja i UI**
1. **API endpoints** dla kalkulacji cen
2. **Panel administracyjny** - zarządzanie cennikami
3. **Frontend calculator** - kalkulator dla klientów
4. **Testy i optymalizacja**

### 🔄 **Integracja z systemem zamówień**

#### **Workflow cenowy:**
1. Klient podaje: waga, wymiary, kod pocztowy, typ klienta
2. System identyfikuje strefę geograficzną  
3. Pobiera cenniki wszystkich kurierów
4. Aplikuje reguły cenowe i rabaty
5. Zwraca porównanie cen z rekomendacją

#### **API Endpoints:**
```
GET  /api/pricing/calculate           # Kalkulacja ceny
GET  /api/pricing/compare             # Porównanie kurierów
POST /api/pricing/bulk-calculate      # Kalkulacja hurtowa
GET  /api/pricing/zones/{postal}      # Strefa dla kodu pocztowego
```

### 📋 **Funkcjonalności dla różnych typów klientów**

#### **B2C (Klienci indywidualni):**
- Ceny standardowe z cennika
- Promocje czasowe i kody rabatowe
- Kalkulator online z porównaniem
- Przejrzyste ceny z VAT

#### **B2B (Klienci firmowi):**
- Negocjowane stawki per klient
- Rabaty wolumenowe (skala miesięczna)
- Umowy ramowe z fiksowanymi cenami
- Faktury zbiorowe standardowe z VAT
- Opcjonalnie: reverse charge (do włączenia per klient)
- Dashboard z analizą kosztów

### ⚡ **Zaawansowane funkcjonalności**

#### **1. Dynamiczne ceny:**
- Aktualizacja cen w czasie rzeczywistym
- Seasonal pricing (Święta, Black Friday)
- Surge pricing (wysokie zapotrzebowanie)

#### **2. Optymalizacje:**
- Cache cenników (Redis)
- Bulk pricing dla dużych zamówień
- Predictive pricing ML

#### **3. Raporty i analityki:**
- ROI per kurier/strefa
- Analiza marż 
- Trending kosztów
- Customer lifetime value

### 🛡️ **Bezpieczeństwo i zgodność**

- **Audit trail** - historia zmian cenników
- **Role-based access** - kto może zmieniać ceny  
- **Approval workflow** - zatwierdzanie nowych stawek
- **Compliance** - zgodność z regulacjami UE

### 📈 **Metryki sukcesu**

- **Margin optimization** - poprawa marżowości o 5-15%
- **Customer satisfaction** - transparentność cen
- **Operational efficiency** - automatyzacja kalkulacji  
- **Revenue growth** - lepsze wyceny = wyższe przychody

---

## 🚀 **Następne kroki implementacji**

### **Faza 1: Fundament (Tydzień 1-2)**
1. Analiza cenników konkurencji i zbieranie danych
2. Projektowanie szczegółowego schematu bazy danych
3. Implementacja podstawowych encji Pricing Domain
4. Utworzenie migracji bazy danych

### **Faza 2: Logika biznesowa (Tydzień 3-4)**
1. Implementacja PricingCalculatorService
2. Rozwój CarrierRateService dla każdego kuriera
3. Budowa PricingRuleEngine z regułami cenowymi
4. Mapowanie stref geograficznych

### **Faza 3: Integracja (Tydzień 5-6)**
1. Rozwój API endpoints dla kalkulacji
2. Integracja z systemem zamówień
3. Panel administracyjny do zarządzania cenami
4. Frontend kalkulator dla klientów

### **Rekomendowane rozpoczęcie:**
Rozpocząć od analizy cenników konkurencji i implementacji podstawowych encji jako POC (Proof of Concept).