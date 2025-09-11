import React, { useState, useEffect } from 'react';
import { api } from '../services/api.js';

export default function PricingCalculator({ token, addToast }) {
  const [formData, setFormData] = useState({
    carrier_code: '',
    zone_code: 'LOCAL',
    weight_kg: '',
    length: '',
    width: '',
    height: '',
    service_type: 'standard',
    currency: 'PLN'
  });
  
  const [carriers, setCarriers] = useState([]);
  const [zones] = useState([
    { code: 'LOCAL', name: 'Strefa Lokalna' },
    { code: 'DOMESTIC', name: 'Strefa Krajowa' },
    { code: 'EU_WEST', name: 'Europa Zachodnia' },
    { code: 'EU_EAST', name: 'Europa Wschodnia' },
    { code: 'WORLD', name: 'Świat' }
  ]);
  
  const [result, setResult] = useState(null);
  const [loading, setLoading] = useState(false);
  const [compareMode, setCompareMode] = useState(false);

  // Load available carriers
  useEffect(() => {
    const loadCarriers = async () => {
      try {
        // Use public endpoint without auth for carrier list
        const response = await fetch('/api/v1/pricing/carriers/available?zone_code=LOCAL&weight_kg=1&length=30&width=20&height=10');
        const data = await response.json();
        if (data.success) {
          setCarriers(data.data.carriers || []);
          if (data.data.carriers?.length > 0) {
            setFormData(prev => ({ ...prev, carrier_code: data.data.carriers[0].code }));
          }
        }
      } catch (error) {
        console.error('Failed to load carriers:', error);
        addToast({ title: 'Błąd', body: 'Nie udało się załadować listy kurierów', type: 'error' });
      }
    };
    loadCarriers();
  }, []);

  const handleInputChange = (field, value) => {
    setFormData(prev => ({ ...prev, [field]: value }));
  };

  const calculatePrice = async () => {
    if (!formData.carrier_code || !formData.weight_kg || !formData.length || !formData.width || !formData.height) {
      addToast({ title: 'Błąd', body: 'Wypełnij wszystkie wymagane pola', type: 'error' });
      return;
    }

    setLoading(true);
    try {
      const endpoint = compareMode ? '/pricing/compare' : '/pricing/calculate';
      const payload = {
        ...(compareMode ? {} : { carrier_code: formData.carrier_code }),
        zone_code: formData.zone_code,
        weight_kg: parseFloat(formData.weight_kg),
        dimensions_cm: {
          length: parseInt(formData.length),
          width: parseInt(formData.width), 
          height: parseInt(formData.height)
        },
        service_type: formData.service_type,
        currency: formData.currency
      };

      // Use public pricing API - no auth needed for price calculations
      const response = await fetch(`/api/v1${endpoint}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const data = await response.json();
      if (data.success) {
        setResult(data.data);
        addToast({ title: 'Sukces', body: 'Cennik obliczony pomyślnie', type: 'success' });
      } else {
        addToast({ title: 'Błąd', body: data.error || data.message || 'Nie udało się obliczyć ceny', type: 'error' });
      }
    } catch (error) {
      console.error('Pricing calculation error:', error);
      addToast({ title: 'Błąd', body: 'Błąd połączenia z serwerem', type: 'error' });
    } finally {
      setLoading(false);
    }
  };

  return React.createElement('div', { style: styles.container },
    React.createElement('div', { style: styles.header },
      React.createElement('h3', { style: styles.title }, 'Kalkulator Cen'),
      React.createElement('div', { style: styles.toggleGroup },
        React.createElement('button', {
          style: { ...styles.toggle, ...(compareMode ? {} : styles.toggleActive) },
          onClick: () => setCompareMode(false)
        }, 'Jeden kurier'),
        React.createElement('button', {
          style: { ...styles.toggle, ...(compareMode ? styles.toggleActive : {}) },
          onClick: () => setCompareMode(true)
        }, 'Porównaj ceny')
      )
    ),

    React.createElement('div', { style: styles.form },
      React.createElement('div', { style: styles.row },
        !compareMode && React.createElement('div', { style: styles.field },
          React.createElement('label', { style: styles.label }, 'Kurier'),
          React.createElement('select', {
            style: styles.select,
            value: formData.carrier_code,
            onChange: (e) => handleInputChange('carrier_code', e.target.value)
          },
            React.createElement('option', { value: '' }, 'Wybierz kuriera'),
            carriers.map(c => React.createElement('option', { key: c.code, value: c.code }, c.name))
          )
        ),
        React.createElement('div', { style: styles.field },
          React.createElement('label', { style: styles.label }, 'Strefa'),
          React.createElement('select', {
            style: styles.select,
            value: formData.zone_code,
            onChange: (e) => handleInputChange('zone_code', e.target.value)
          },
            zones.map(z => React.createElement('option', { key: z.code, value: z.code }, z.name))
          )
        )
      ),

      React.createElement('div', { style: styles.row },
        React.createElement('div', { style: styles.field },
          React.createElement('label', { style: styles.label }, 'Waga (kg)'),
          React.createElement('input', {
            style: styles.input,
            type: 'number',
            step: '0.1',
            placeholder: '2.5',
            value: formData.weight_kg,
            onChange: (e) => handleInputChange('weight_kg', e.target.value)
          })
        ),
        React.createElement('div', { style: styles.field },
          React.createElement('label', { style: styles.label }, 'Typ usługi'),
          React.createElement('select', {
            style: styles.select,
            value: formData.service_type,
            onChange: (e) => handleInputChange('service_type', e.target.value)
          },
            React.createElement('option', { value: 'standard' }, 'Standard'),
            React.createElement('option', { value: 'express' }, 'Express'),
            React.createElement('option', { value: 'economy' }, 'Economy')
          )
        )
      ),

      React.createElement('div', { style: styles.dimensionsGroup },
        React.createElement('label', { style: styles.label }, 'Wymiary (cm)'),
        React.createElement('div', { style: styles.dimensionsRow },
          React.createElement('input', {
            style: styles.dimensionInput,
            type: 'number',
            placeholder: 'Długość',
            value: formData.length,
            onChange: (e) => handleInputChange('length', e.target.value)
          }),
          React.createElement('span', { style: styles.separator }, '×'),
          React.createElement('input', {
            style: styles.dimensionInput,
            type: 'number',
            placeholder: 'Szerokość',
            value: formData.width,
            onChange: (e) => handleInputChange('width', e.target.value)
          }),
          React.createElement('span', { style: styles.separator }, '×'),
          React.createElement('input', {
            style: styles.dimensionInput,
            type: 'number',
            placeholder: 'Wysokość',
            value: formData.height,
            onChange: (e) => handleInputChange('height', e.target.value)
          })
        )
      ),

      React.createElement('button', {
        style: { ...styles.calculateBtn, ...(loading ? styles.calculating : {}) },
        onClick: calculatePrice,
        disabled: loading
      }, loading ? 'Obliczanie...' : (compareMode ? 'Porównaj ceny' : 'Oblicz cenę'))
    ),

    result && React.createElement('div', { style: styles.results },
      compareMode ? renderCompareResults(result) : renderSingleResult(result)
    )
  );
}

function renderSingleResult(result) {
  return React.createElement('div', { style: styles.resultCard },
    React.createElement('div', { style: styles.resultHeader },
      React.createElement('div', { style: styles.carrierInfo },
        React.createElement('strong', null, result.carrier.name),
        React.createElement('span', { style: styles.zone }, result.zone.name)
      ),
      React.createElement('div', { style: styles.totalPrice },
        result.pricing.total_price, ' ', result.pricing.currency
      )
    ),
    React.createElement('div', { style: styles.breakdown },
      React.createElement('div', { style: styles.breakdownItem },
        React.createElement('span', null, 'Cena bazowa:'),
        React.createElement('span', null, result.pricing.base_price, ' PLN')
      ),
      React.createElement('div', { style: styles.breakdownItem },
        React.createElement('span', null, 'Podatek (', result.pricing.tax_rate, '%):'),
        React.createElement('span', null, result.pricing.tax_amount, ' PLN')
      )
    )
  );
}

function renderCompareResults(result) {
  return React.createElement('div', { style: styles.compareResults },
    React.createElement('div', { style: styles.compareHeader },
      React.createElement('h4', null, 'Porównanie cen (', result.prices.length, ' kurierów)'),
      result.statistics.average_price && React.createElement('div', { style: styles.avgPrice },
        'Średnia cena: ', result.statistics.average_price, ' PLN'
      )
    ),
    result.prices.map((price, i) => React.createElement('div', { key: i, style: styles.compareItem },
      React.createElement('div', { style: styles.compareCarrier }, price.carrier.name),
      React.createElement('div', { style: styles.comparePrice }, price.pricing.total_price, ' ', price.pricing.currency),
      React.createElement('div', { style: styles.compareDetails },
        'Bazowa: ', price.pricing.base_price, ' + VAT: ', price.pricing.tax_amount
      )
    ))
  );
}

const styles = {
  container: {
    border: '1px solid var(--border)',
    borderRadius: 12,
    background: '#fff',
    padding: 20,
    boxShadow: '0 8px 26px rgba(2,6,23,0.05)'
  },
  header: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 20
  },
  title: {
    margin: 0,
    fontSize: 18,
    fontWeight: 800
  },
  toggleGroup: {
    display: 'flex',
    border: '1px solid var(--border)',
    borderRadius: 8,
    overflow: 'hidden'
  },
  toggle: {
    padding: '8px 16px',
    border: 'none',
    background: '#fff',
    cursor: 'pointer',
    fontSize: 14,
    fontWeight: 600,
    borderRight: '1px solid var(--border)'
  },
  toggleActive: {
    background: 'var(--primary)',
    color: '#fff'
  },
  form: {
    display: 'grid',
    gap: 16
  },
  row: {
    display: 'grid',
    gridTemplateColumns: '1fr 1fr',
    gap: 16
  },
  field: {
    display: 'grid',
    gap: 6
  },
  label: {
    fontSize: 14,
    fontWeight: 600,
    color: 'var(--text)'
  },
  input: {
    padding: 10,
    border: '1px solid var(--border)',
    borderRadius: 8,
    fontSize: 14
  },
  select: {
    padding: 10,
    border: '1px solid var(--border)',
    borderRadius: 8,
    fontSize: 14,
    background: '#fff'
  },
  dimensionsGroup: {
    display: 'grid',
    gap: 6
  },
  dimensionsRow: {
    display: 'flex',
    alignItems: 'center',
    gap: 8
  },
  dimensionInput: {
    flex: 1,
    padding: 10,
    border: '1px solid var(--border)',
    borderRadius: 8,
    fontSize: 14,
    textAlign: 'center'
  },
  separator: {
    color: 'var(--muted)',
    fontWeight: 600
  },
  calculateBtn: {
    padding: 14,
    border: 'none',
    borderRadius: 8,
    background: 'var(--primary)',
    color: '#fff',
    fontSize: 16,
    fontWeight: 700,
    cursor: 'pointer',
    marginTop: 10
  },
  calculating: {
    opacity: 0.6,
    cursor: 'not-allowed'
  },
  results: {
    marginTop: 20,
    paddingTop: 20,
    borderTop: '1px solid var(--border)'
  },
  resultCard: {
    border: '1px solid var(--border)',
    borderRadius: 8,
    padding: 16,
    background: 'var(--primary-50)'
  },
  resultHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12
  },
  carrierInfo: {
    display: 'flex',
    flexDirection: 'column',
    gap: 2
  },
  zone: {
    fontSize: 12,
    color: 'var(--muted)'
  },
  totalPrice: {
    fontSize: 24,
    fontWeight: 800,
    color: 'var(--primary)'
  },
  breakdown: {
    display: 'flex',
    gap: 20,
    fontSize: 14
  },
  breakdownItem: {
    display: 'flex',
    gap: 6
  },
  compareResults: {
    display: 'grid',
    gap: 12
  },
  compareHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingBottom: 12,
    borderBottom: '1px solid var(--border)'
  },
  avgPrice: {
    fontSize: 14,
    color: 'var(--muted)'
  },
  compareItem: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 12,
    border: '1px solid var(--border)',
    borderRadius: 8,
    background: '#fff'
  },
  compareCarrier: {
    fontWeight: 600
  },
  comparePrice: {
    fontSize: 18,
    fontWeight: 800,
    color: 'var(--primary)'
  },
  compareDetails: {
    fontSize: 12,
    color: 'var(--muted)'
  }
};