import React, { useState, useEffect } from 'react';
import { api } from '../services/api.js';

export default function PricingManagement({ token, addToast }) {
  const [activeTab, setActiveTab] = useState('calculator');
  const [carriers, setCarriers] = useState([]);
  const [pricingTables, setPricingTables] = useState([]);
  const [analytics, setAnalytics] = useState(null);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      // Load carriers
      const carriersResponse = await fetch('/api/v1/pricing/carriers/available?zone_code=LOCAL&weight_kg=1&length=30&width=20&height=10');
      const carriersData = await carriersResponse.json();
      if (carriersData.success) {
        setCarriers(carriersData.data.carriers || []);
      }

      // Mock pricing tables data
      setPricingTables([
        { id: 1, carrier: 'InPost', zone: 'LOCAL', basePrice: '15.00', active: true, updated: '2025-09-10' },
        { id: 2, carrier: 'DHL', zone: 'LOCAL', basePrice: '25.00', active: true, updated: '2025-09-09' },
        { id: 3, carrier: 'InPost', zone: 'DOMESTIC', basePrice: '18.00', active: true, updated: '2025-09-08' }
      ]);

      // Mock analytics
      setAnalytics({
        totalCalculations: 1245,
        avgCalculationTime: '42ms',
        topCarrier: 'InPost',
        topZone: 'LOCAL'
      });
    } catch (error) {
      console.error('Failed to load pricing data:', error);
    }
  };

  const TabButton = ({ id, label, active, onClick }) => (
    React.createElement('button', {
      style: { ...styles.tabButton, ...(active ? styles.tabButtonActive : {}) },
      onClick: () => onClick(id)
    }, label)
  );

  return React.createElement('div', { style: styles.container },
    React.createElement('div', { style: styles.header },
      React.createElement('h3', { style: styles.title }, 'Zarządzanie cennikiem'),
      React.createElement('div', { style: styles.tabs },
        React.createElement(TabButton, { id: 'calculator', label: 'Kalkulator', active: activeTab === 'calculator', onClick: setActiveTab }),
        React.createElement(TabButton, { id: 'tables', label: 'Tabele cen', active: activeTab === 'tables', onClick: setActiveTab }),
        React.createElement(TabButton, { id: 'analytics', label: 'Analityka', active: activeTab === 'analytics', onClick: setActiveTab })
      )
    ),

    React.createElement('div', { style: styles.content },
      activeTab === 'calculator' && renderCalculatorTab(),
      activeTab === 'tables' && renderTablesTab(pricingTables),
      activeTab === 'analytics' && renderAnalyticsTab(analytics)
    )
  );

  function renderCalculatorTab() {
    const PricingCalculator = React.lazy(() => import('./PricingCalculator.js'));
    return React.createElement(React.Suspense, { fallback: React.createElement('div', null, 'Ładowanie...') },
      React.createElement(PricingCalculator, { token, addToast })
    );
  }

  function renderTablesTab(tables) {
    return React.createElement('div', { style: styles.tabContent },
      React.createElement('div', { style: styles.actionsBar },
        React.createElement('button', { style: styles.primaryButton }, '+ Dodaj cennik'),
        React.createElement('button', { style: styles.secondaryButton }, 'Import CSV'),
        React.createElement('button', { style: styles.secondaryButton }, 'Export')
      ),
      
      React.createElement('div', { style: styles.tableContainer },
        React.createElement('div', { style: styles.tableHeader },
          ['Kurier', 'Strefa', 'Cena bazowa', 'Status', 'Ostatnia aktualizacja', 'Akcje'].map(h =>
            React.createElement('div', { key: h, style: styles.headerCell }, h)
          )
        ),
        tables.map(table => React.createElement('div', { key: table.id, style: styles.tableRow },
          React.createElement('div', { style: styles.cell },
            React.createElement('div', { style: styles.carrierName }, table.carrier),
          ),
          React.createElement('div', { style: styles.cell }, table.zone),
          React.createElement('div', { style: styles.cell },
            React.createElement('span', { style: styles.price }, table.basePrice, ' PLN')
          ),
          React.createElement('div', { style: styles.cell },
            React.createElement('span', { 
              style: { ...styles.badge, ...(table.active ? styles.badgeActive : styles.badgeInactive) }
            }, table.active ? 'Aktywny' : 'Nieaktywny')
          ),
          React.createElement('div', { style: styles.cell }, table.updated),
          React.createElement('div', { style: styles.cell },
            React.createElement('div', { style: styles.actions },
              React.createElement('button', { style: styles.actionButton }, 'Edytuj'),
              React.createElement('button', { style: styles.actionButton }, 'Duplikuj'),
              React.createElement('button', { style: { ...styles.actionButton, ...styles.dangerButton } }, 'Usuń')
            )
          )
        ))
      )
    );
  }

  function renderAnalyticsTab(data) {
    if (!data) return React.createElement('div', { style: styles.loading }, 'Ładowanie analityki...');

    return React.createElement('div', { style: styles.tabContent },
      React.createElement('div', { style: styles.statsGrid },
        React.createElement('div', { style: styles.statCard },
          React.createElement('div', { style: styles.statValue }, data.totalCalculations.toLocaleString()),
          React.createElement('div', { style: styles.statLabel }, 'Łączne kalkulacje')
        ),
        React.createElement('div', { style: styles.statCard },
          React.createElement('div', { style: styles.statValue }, data.avgCalculationTime),
          React.createElement('div', { style: styles.statLabel }, 'Średni czas kalkulacji')
        ),
        React.createElement('div', { style: styles.statCard },
          React.createElement('div', { style: styles.statValue }, data.topCarrier),
          React.createElement('div', { style: styles.statLabel }, 'Najpopularniejszy kurier')
        ),
        React.createElement('div', { style: styles.statCard },
          React.createElement('div', { style: styles.statValue }, data.topZone),
          React.createElement('div', { style: styles.statLabel }, 'Najpopularniejsza strefa')
        )
      ),
      
      React.createElement('div', { style: styles.chartContainer },
        React.createElement('h4', { style: styles.chartTitle }, 'Kalkulacje w czasie'),
        React.createElement('div', { style: styles.chartPlaceholder },
          React.createElement('div', { style: styles.chartBar, style: { height: '60%' } }),
          React.createElement('div', { style: styles.chartBar, style: { height: '80%' } }),
          React.createElement('div', { style: styles.chartBar, style: { height: '45%' } }),
          React.createElement('div', { style: styles.chartBar, style: { height: '90%' } }),
          React.createElement('div', { style: styles.chartBar, style: { height: '75%' } }),
          React.createElement('div', { style: styles.chartBar, style: { height: '65%' } }),
          React.createElement('div', { style: styles.chartBar, style: { height: '85%' } })
        )
      )
    );
  }
}

const styles = {
  container: {
    border: '1px solid var(--border)',
    borderRadius: 12,
    background: '#fff',
    boxShadow: '0 8px 26px rgba(2,6,23,0.05)',
    overflow: 'hidden'
  },
  header: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: '20px 20px 0',
    borderBottom: '1px solid var(--border)'
  },
  title: {
    margin: 0,
    fontSize: 18,
    fontWeight: 800
  },
  tabs: {
    display: 'flex',
    gap: 2
  },
  tabButton: {
    padding: '8px 16px',
    border: '1px solid var(--border)',
    background: '#fff',
    borderRadius: '8px 8px 0 0',
    cursor: 'pointer',
    fontSize: 14,
    fontWeight: 600,
    color: 'var(--muted)'
  },
  tabButtonActive: {
    background: 'var(--primary-50)',
    borderColor: 'var(--primary)',
    color: 'var(--primary)'
  },
  content: {
    padding: 20
  },
  tabContent: {
    display: 'grid',
    gap: 20
  },
  actionsBar: {
    display: 'flex',
    gap: 10,
    justifyContent: 'flex-end'
  },
  primaryButton: {
    padding: '10px 16px',
    border: 'none',
    borderRadius: 8,
    background: 'var(--primary)',
    color: '#fff',
    fontSize: 14,
    fontWeight: 600,
    cursor: 'pointer'
  },
  secondaryButton: {
    padding: '10px 16px',
    border: '1px solid var(--border)',
    borderRadius: 8,
    background: '#fff',
    color: 'var(--text)',
    fontSize: 14,
    fontWeight: 600,
    cursor: 'pointer'
  },
  tableContainer: {
    border: '1px solid var(--border)',
    borderRadius: 8,
    overflow: 'hidden'
  },
  tableHeader: {
    display: 'grid',
    gridTemplateColumns: '1.5fr 1fr 1fr 1fr 1.2fr 1.5fr',
    background: 'var(--primary-50)',
    borderBottom: '1px solid var(--border)'
  },
  tableRow: {
    display: 'grid',
    gridTemplateColumns: '1.5fr 1fr 1fr 1fr 1.2fr 1.5fr',
    borderBottom: '1px solid var(--border)'
  },
  headerCell: {
    padding: 12,
    fontWeight: 800,
    fontSize: 14
  },
  cell: {
    padding: 12,
    display: 'flex',
    alignItems: 'center',
    fontSize: 14
  },
  carrierName: {
    fontWeight: 600
  },
  price: {
    fontWeight: 600,
    color: 'var(--primary)'
  },
  badge: {
    padding: '4px 8px',
    borderRadius: 6,
    fontSize: 12,
    fontWeight: 600
  },
  badgeActive: {
    background: '#dcfce7',
    color: '#166534'
  },
  badgeInactive: {
    background: '#fee2e2',
    color: '#dc2626'
  },
  actions: {
    display: 'flex',
    gap: 6
  },
  actionButton: {
    padding: '6px 10px',
    border: '1px solid var(--border)',
    borderRadius: 6,
    background: '#fff',
    fontSize: 12,
    cursor: 'pointer'
  },
  dangerButton: {
    borderColor: '#fecaca',
    color: '#dc2626'
  },
  statsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
    gap: 16
  },
  statCard: {
    padding: 20,
    border: '1px solid var(--border)',
    borderRadius: 8,
    textAlign: 'center',
    background: 'var(--primary-50)'
  },
  statValue: {
    fontSize: 24,
    fontWeight: 800,
    color: 'var(--primary)',
    marginBottom: 4
  },
  statLabel: {
    fontSize: 12,
    color: 'var(--muted)',
    textTransform: 'uppercase',
    letterSpacing: 0.5
  },
  chartContainer: {
    padding: 20,
    border: '1px solid var(--border)',
    borderRadius: 8
  },
  chartTitle: {
    margin: '0 0 20px 0',
    fontSize: 16,
    fontWeight: 700
  },
  chartPlaceholder: {
    display: 'flex',
    alignItems: 'flex-end',
    justifyContent: 'space-around',
    height: 200,
    gap: 8
  },
  chartBar: {
    width: 40,
    background: 'var(--primary)',
    borderRadius: '4px 4px 0 0',
    opacity: 0.7
  },
  loading: {
    padding: 40,
    textAlign: 'center',
    color: 'var(--muted)'
  }
};