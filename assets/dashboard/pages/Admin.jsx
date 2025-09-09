import React, { useEffect, useState } from 'react';
import StatCard from '../components/StatCard.jsx';
import { api } from '../services/api.js';

export default function Admin({ token, user, current, addToast }) {
  const [stats, setStats] = useState({ users: '—', customers: '—', orders: '—' });
  const [team, setTeam] = useState([]);

  useEffect(() => {
    (async () => {
      // Best-effort: if endpoints are missing, keep placeholders
      try { const res = await api.get('/system/team'); setTeam(Array.isArray(res.data) ? res.data : []); } catch {}
      // Simulated stats
      setStats({ users: 42, customers: 18, orders: 256 });
    })();
  }, [token]);

  if (current === 'team') return (
    React.createElement('div', { style: styles.page },
      React.createElement('h2', null, 'Zespół'),
      React.createElement('div', { style: styles.table },
        React.createElement('div', { style: styles.trHead },
          ['Imię', 'Email', 'Dział', 'Rola'].map((h) => React.createElement('div', { key: h, style: styles.th }, h))
        ),
        (team.length ? team : [{ name: 'Anna Kowalska', email: 'anna@sky.test', department: 'support', role: 'ROLE_USER' }]).map((m, i) => (
          React.createElement('div', { key: i, style: styles.tr },
            React.createElement('div', { style: styles.td }, m.name || '—'),
            React.createElement('div', { style: styles.td }, m.email || '—'),
            React.createElement('div', { style: styles.td }, m.department || '—'),
            React.createElement('div', { style: styles.td }, Array.isArray(m.roles) ? m.roles.join(', ') : (m.role || '—'))
          )
        ))
      )
    )
  );

  if (current === 'customers') return (
    React.createElement('div', { style: styles.page },
      React.createElement('h2', null, 'Klienci'),
      React.createElement('div', { style: styles.muted }, 'Widok listy klientów (do podpięcia pod API).')
    )
  );

  if (current === 'orders') return (
    React.createElement('div', { style: styles.page },
      React.createElement('h2', null, 'Zamówienia'),
      React.createElement('div', { style: styles.muted }, 'Widok zamówień (do podpięcia pod API).')
    )
  );

  if (current === 'settings') return (
    React.createElement('div', { style: styles.page },
      React.createElement('h2', null, 'Ustawienia'),
      React.createElement('div', { style: styles.card },
        React.createElement('div', { style: styles.sectionTitle }, 'Powiadomienia'),
        React.createElement('button', { style: styles.btn, onClick: () => addToast({ title: 'Test powiadomienia', body: 'To jest przykładowy toast', type: 'success' }) }, 'Wyślij testowy toast')
      )
    )
  );

  // overview
  return React.createElement('div', { style: styles.page },
    React.createElement('h2', null, 'Przegląd systemu'),
    React.createElement('div', { style: styles.grid },
      React.createElement(StatCard, { label: 'Użytkownicy', value: stats.users, tone: 'primary' }),
      React.createElement(StatCard, { label: 'Klienci', value: stats.customers }),
      React.createElement(StatCard, { label: 'Zamówienia (30d)', value: stats.orders })
    ),
    React.createElement('div', { style: styles.card },
      React.createElement('div', { style: styles.sectionTitle }, 'Aktywność'),
      React.createElement('div', { style: styles.muted }, 'Ostatnie zdarzenia i aktywności pojawią się tutaj.')
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
  trHead: { display: 'grid', gridTemplateColumns: '2fr 2fr 1fr 1fr', background: 'var(--primary-50)', borderBottom: '1px solid var(--border)' },
  tr: { display: 'grid', gridTemplateColumns: '2fr 2fr 1fr 1fr', borderBottom: '1px solid var(--border)' },
  th: { padding: 10, fontWeight: 800 },
  td: { padding: 10 },
  btn: { border: '1px solid var(--border)', background: '#fff', borderRadius: 10, padding: '10px 12px', fontWeight: 700, cursor: 'pointer' }
};
