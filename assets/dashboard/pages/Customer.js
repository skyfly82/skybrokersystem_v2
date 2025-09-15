import React, { useEffect, useState } from 'react';
import StatCard from '../components/StatCard.js';
import PricingCalculator from '../components/PricingCalculator.js';
import ShipmentTracker from '../components/ShipmentTracker.js';
import BillingManager from '../components/BillingManager.js';
import Chart from '../components/Chart.js';
import { api } from '../services/api.js';

export default function Customer({ token, user, current, addToast }) {
  const [stats, setStats] = useState({ orders: '—', inTransit: '—', invoices: '—', balance: 0 });
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [chartData, setChartData] = useState({
    activity: { labels: [], datasets: [] },
    status: { labels: [], datasets: [] }
  });

  useEffect(() => {
    (async () => {
      setLoading(true);
      try {
        // Fetch orders
        const res = await api.get('/customer/orders');
        setOrders(Array.isArray(res.data) ? res.data : []);
        
        // Set stats with enhanced data
        setStats({ 
          orders: 12, 
          inTransit: 4, 
          invoices: 7, 
          balance: 1250.75,
          delivered: 8,
          pending: 3
        });

        // Generate chart data for customer dashboard
        const last30Days = Array.from({ length: 30 }, (_, i) => {
          const date = new Date();
          date.setDate(date.getDate() - (29 - i));
          return date.toLocaleDateString('pl-PL', { month: 'short', day: 'numeric' });
        });

        const activityCounts = Array.from({ length: 30 }, () => Math.floor(Math.random() * 5) + 1);

        setChartData({
          activity: {
            labels: last30Days,
            datasets: [{
              label: 'Przesyłki',
              data: activityCounts,
              borderColor: 'rgb(59, 130, 246)',
              backgroundColor: 'rgba(59, 130, 246, 0.1)',
              tension: 0.4,
              fill: true
            }]
          },
          status: {
            labels: ['Dostarczone', 'W tranzycie', 'Oczekujące'],
            datasets: [{
              data: [8, 4, 3],
              backgroundColor: [
                'rgba(34, 197, 94, 0.8)',
                'rgba(59, 130, 246, 0.8)',
                'rgba(245, 158, 11, 0.8)'
              ]
            }]
          }
        });
      } catch (error) {
        console.error('Failed to fetch customer data:', error);
        addToast({
          title: 'Błąd',
          body: 'Nie udało się pobrać danych',
          type: 'error'
        });
      } finally {
        setLoading(false);
      }
    })();
  }, [token, addToast]);

  if (current === 'orders') return (
    React.createElement('div', { style: styles.page },
      React.createElement('h2', null, 'Zamówienia'),
      React.createElement('div', { style: styles.table },
        React.createElement('div', { style: styles.trHead }, ['Nr', 'Status', 'Kwota', 'Data'].map((h) => React.createElement('div', { key: h, style: styles.th }, h))),
        (orders.length ? orders : sampleOrders).map((o, i) => React.createElement('div', { key: i, style: styles.tr },
          React.createElement('div', { style: styles.td }, o.orderNumber || o.nr || '—'),
          React.createElement('div', { style: styles.td }, (o.status || 'processing').toString()),
          React.createElement('div', { style: styles.td }, formatAmount(o.amount || 0)),
          React.createElement('div', { style: styles.td }, (o.createdAt || '—').toString())
        ))
      )
    )
  );

  if (current === 'shipments') return (
    React.createElement('div', { style: styles.page },
      React.createElement('h2', null, 'Śledzenie przesyłek'),
      React.createElement(ShipmentTracker, { token, addToast })
    )
  );

  if (current === 'billing') return (
    React.createElement('div', { style: styles.page },
      React.createElement('h2', null, 'Płatności i faktury'),
      React.createElement(BillingManager, { token, addToast })
    )
  );

  if (current === 'pricing') return (
    React.createElement('div', { style: styles.page },
      React.createElement('h2', null, 'Kalkulator cen'),
      React.createElement(PricingCalculator, { token, addToast })
    )
  );

  if (current === 'company') return (
    React.createElement('div', { style: styles.page },
      React.createElement('h2', null, 'Dane firmy'),
      user?.customer ? React.createElement('pre', null, JSON.stringify(user.customer, null, 2)) : React.createElement('div', { style: styles.muted }, 'Brak danych firmy.')
    )
  );

  // overview with enhanced UI and loading state
  return React.createElement('div', { style: styles.page },
    // Welcome banner
    React.createElement('div', { style: styles.welcomeBanner },
      React.createElement('div', null,
        React.createElement('h2', { style: styles.welcomeTitle }, 'Witaj ponownie!'),
        user?.customer?.company_name && React.createElement('p', { style: styles.companyName }, user.customer.company_name)
      ),
      React.createElement('div', { style: styles.balanceInfo },
        React.createElement('p', { style: styles.balanceLabel }, 'Twoje saldo'),
        React.createElement('p', { style: styles.balanceAmount }, formatAmount(stats.balance)),
        stats.balance < 100 && React.createElement('p', { style: styles.lowBalanceWarning }, '⚠️ Niskie saldo')
      )
    ),
    
    loading ? React.createElement('div', { style: styles.loadingContainer },
      React.createElement('div', { style: styles.spinner }),
      React.createElement('p', null, 'Ładowanie danych...')
    ) : React.createElement(React.Fragment, null,
      // Quick actions
      React.createElement('div', { style: styles.quickActions },
        React.createElement('div', { style: { ...styles.quickAction, borderLeftColor: '#22c55e' } },
          React.createElement('span', { style: styles.actionIcon }, '📦'),
          React.createElement('div', null,
            React.createElement('h3', { style: styles.actionTitle }, 'Nowa Przesyłka'),
            React.createElement('p', { style: styles.actionDesc }, 'Utwórz nową przesyłkę')
          )
        ),
        React.createElement('div', { style: { ...styles.quickAction, borderLeftColor: '#3b82f6' } },
          React.createElement('span', { style: styles.actionIcon }, '💳'),
          React.createElement('div', null,
            React.createElement('h3', { style: styles.actionTitle }, 'Doadłuj Konto'),
            React.createElement('p', { style: styles.actionDesc }, 'Zwiększ swoje saldo')
          )
        ),
        React.createElement('div', { style: { ...styles.quickAction, borderLeftColor: '#8b5cf6' } },
          React.createElement('span', { style: styles.actionIcon }, '🔍'),
          React.createElement('div', null,
            React.createElement('h3', { style: styles.actionTitle }, 'śledz Przesyłki'),
            React.createElement('p', { style: styles.actionDesc }, 'Monitoruj swoje przesyłki')
          )
        )
      ),
      
      // Stats cards
      React.createElement('div', { style: styles.grid },
        React.createElement(StatCard, { label: 'Zamówienia', value: stats.orders, tone: 'primary' }),
        React.createElement(StatCard, { label: 'W tranzycie', value: stats.inTransit }),
        React.createElement(StatCard, { label: 'Dostarczone', value: stats.delivered, tone: 'success' }),
        React.createElement(StatCard, { label: 'Faktury', value: stats.invoices })
      ),
      
      // Charts section
      React.createElement('div', { style: styles.chartsGrid },
        React.createElement('div', { style: styles.card },
          React.createElement('div', { style: styles.sectionTitle }, 'Aktywność w ostatnich 30 dniach'),
          React.createElement(Chart, {
            type: 'line',
            data: chartData.activity,
            height: 250
          })
        ),
        React.createElement('div', { style: styles.card },
          React.createElement('div', { style: styles.sectionTitle }, 'Rozkład statusów przesyłek'),
          React.createElement(Chart, {
            type: 'doughnut',
            data: chartData.status,
            height: 250
          })
        )
      ),
      
      // Recent orders table
      React.createElement('div', { style: styles.card },
        React.createElement('div', { style: styles.sectionTitle }, 'Ostatnie zamówienia'),
        React.createElement('div', { style: styles.table },
          React.createElement('div', { style: styles.trHead }, ['Nr', 'Status', 'Kwota', 'Data'].map((h) => React.createElement('div', { key: h, style: styles.th }, h))),
          sampleOrders.slice(0, 5).map((o, i) => React.createElement('div', { key: i, style: styles.tr },
            React.createElement('div', { style: styles.td }, o.nr),
            React.createElement('div', { style: { ...styles.td, ...getStatusStyle(o.status) } }, getStatusLabel(o.status)),
            React.createElement('div', { style: styles.td }, formatAmount(o.amount)),
            React.createElement('div', { style: styles.td }, o.date)
          ))
        )
      )
    )
  );
}

const styles = {
  page: { display: 'grid', gap: 14 },
  grid: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 14 },
  chartsGrid: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(400px, 1fr))', gap: 14 },
  card: { border: '1px solid var(--border)', borderRadius: 12, background: '#fff', padding: 14, boxShadow: '0 8px 26px rgba(2,6,23,0.05)' },
  sectionTitle: { fontWeight: 800, marginBottom: 10 },
  muted: { color: 'var(--muted)' },
  table: { display: 'grid', border: '1px solid var(--border)', borderRadius: 12, overflow: 'hidden' },
  trHead: { display: 'grid', gridTemplateColumns: '1.4fr 1fr 1fr 1.2fr', background: 'var(--primary-50)', borderBottom: '1px solid var(--border)' },
  tr: { display: 'grid', gridTemplateColumns: '1.4fr 1fr 1fr 1.2fr', borderBottom: '1px solid var(--border)' },
  th: { padding: 10, fontWeight: 800 },
  td: { padding: 10 },
  loadingContainer: { textAlign: 'center', padding: 40 },
  spinner: {
    width: 40,
    height: 40,
    border: '4px solid var(--border)',
    borderTop: '4px solid var(--primary-600)',
    borderRadius: '50%',
    animation: 'spin 1s linear infinite',
    margin: '0 auto 16px'
  },
  welcomeBanner: {
    background: 'linear-gradient(135deg, #3b82f6, #1e40af)',
    borderRadius: 12,
    padding: 20,
    color: '#fff',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center'
  },
  welcomeTitle: { margin: 0, fontSize: 24, fontWeight: 800 },
  companyName: { margin: '4px 0 0 0', opacity: 0.9, fontSize: 14 },
  balanceInfo: { textAlign: 'right' },
  balanceLabel: { margin: 0, fontSize: 12, opacity: 0.8 },
  balanceAmount: { margin: '4px 0', fontSize: 28, fontWeight: 800 },
  lowBalanceWarning: { margin: '4px 0 0 0', fontSize: 12, color: '#fbbf24' },
  quickActions: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: 14 },
  quickAction: {
    background: '#fff',
    padding: 20,
    borderRadius: 12,
    border: '1px solid var(--border)',
    borderLeft: '4px solid',
    boxShadow: '0 8px 26px rgba(2,6,23,0.05)',
    display: 'flex',
    alignItems: 'center',
    gap: 16,
    cursor: 'pointer',
    transition: 'transform 0.2s ease, box-shadow 0.2s ease'
  },
  actionIcon: { fontSize: 32 },
  actionTitle: { margin: '0 0 4px 0', fontSize: 16, fontWeight: 700 },
  actionDesc: { margin: 0, fontSize: 14, color: 'var(--muted)' }
};

const sampleOrders = [
  { nr: 'ORD-2025-0012', status: 'processing', amount: 199.99, date: '2025-09-01 10:22' },
  { nr: 'ORD-2025-0011', status: 'shipped', amount: 89.50, date: '2025-08-30 14:12' },
  { nr: 'ORD-2025-0010', status: 'delivered', amount: 349.00, date: '2025-08-27 09:05' },
  { nr: 'ORD-2025-0009', status: 'processing', amount: 72.00, date: '2025-08-24 16:44' },
  { nr: 'ORD-2025-0008', status: 'cancelled', amount: 129.99, date: '2025-08-20 11:18' },
];

function formatAmount(a) {
  try { return new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(a); } catch { return `${a} PLN`; }
}

function getStatusLabel(status) {
  const statusMap = {
    'processing': 'Przetwarzanie',
    'shipped': 'Wysłano',
    'delivered': 'Dostarczone',
    'cancelled': 'Anulowane'
  };
  return statusMap[status] || status;
}

function getStatusStyle(status) {
  const statusStyles = {
    'processing': { color: '#f59e0b', fontWeight: 600 },
    'shipped': { color: '#3b82f6', fontWeight: 600 },
    'delivered': { color: '#10b981', fontWeight: 600 },
    'cancelled': { color: '#ef4444', fontWeight: 600 }
  };
  return statusStyles[status] || {};
}

