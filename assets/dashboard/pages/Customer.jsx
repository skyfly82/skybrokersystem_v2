import React, { useEffect, useState } from 'react';
import StatCard from '../components/StatCard.jsx';
import { api } from '../services/api.js';

export default function Customer({ token, user, current, addToast }) {
  const [stats, setStats] = useState({ orders: '—', inTransit: '—', invoices: '—' });
  const [orders, setOrders] = useState([]);

  useEffect(() => {
    (async () => {
      try { const res = await api.get('/customer/orders'); setOrders(Array.isArray(res.data) ? res.data : []); } catch {}
      setStats({ orders: 12, inTransit: 4, invoices: 7 });
    })();
  }, [token]);

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
      React.createElement('h2', null, 'Wysyłki'),
      React.createElement('div', { style: styles.muted }, 'Śledzenie przesyłek (do podpięcia pod API).')
    )
  );

  if (current === 'billing') return (
    React.createElement('div', { style: styles.page },
      React.createElement('h2', null, 'Płatności i faktury'),
      React.createElement('div', { style: styles.muted }, 'Widok faktur i płatności (do podpięcia pod API).')
    )
  );

  if (current === 'company') return (
    React.createElement('div', { style: styles.page },
      React.createElement('h2', null, 'Dane firmy'),
      user?.customer ? React.createElement('pre', null, JSON.stringify(user.customer, null, 2)) : React.createElement('div', { style: styles.muted }, 'Brak danych firmy.')
    )
  );

  // overview
  return React.createElement('div', { style: styles.page },
    React.createElement('h2', null, 'Pulpit'),
    React.createElement('div', { style: styles.grid },
      React.createElement(StatCard, { label: 'Zamówienia', value: stats.orders, tone: 'primary' }),
      React.createElement(StatCard, { label: 'W tranzycie', value: stats.inTransit }),
      React.createElement(StatCard, { label: 'Faktury', value: stats.invoices })
    ),
    React.createElement('div', { style: styles.card },
      React.createElement('div', { style: styles.sectionTitle }, 'Ostatnie zamówienia'),
      React.createElement('div', { style: styles.table },
        React.createElement('div', { style: styles.trHead }, ['Nr', 'Status', 'Kwota', 'Data'].map((h) => React.createElement('div', { key: h, style: styles.th }, h))),
        sampleOrders.slice(0, 5).map((o, i) => React.createElement('div', { key: i, style: styles.tr },
          React.createElement('div', { style: styles.td }, o.nr),
          React.createElement('div', { style: styles.td }, o.status),
          React.createElement('div', { style: styles.td }, formatAmount(o.amount)),
          React.createElement('div', { style: styles.td }, o.date)
        ))
      )
    )
  );
}

const styles = {
  page: { display: 'grid', gap: 14 },
  grid: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 14 },
  card: { border: '1px solid var(--border)', borderRadius: 12, background: '#fff', padding: 14, boxShadow: '0 8px 26px rgba(2,6,23,0.05)' },
  sectionTitle: { fontWeight: 800, marginBottom: 10 },
  muted: { color: 'var(--muted)' },
  table: { display: 'grid', border: '1px solid var(--border)', borderRadius: 12, overflow: 'hidden' },
  trHead: { display: 'grid', gridTemplateColumns: '1.4fr 1fr 1fr 1.2fr', background: 'var(--primary-50)', borderBottom: '1px solid var(--border)' },
  tr: { display: 'grid', gridTemplateColumns: '1.4fr 1fr 1fr 1.2fr', borderBottom: '1px solid var(--border)' },
  th: { padding: 10, fontWeight: 800 },
  td: { padding: 10 },
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
