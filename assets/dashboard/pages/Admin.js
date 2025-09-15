import React, { useEffect, useState } from 'react';
import StatCard from '../components/StatCard.js';
import PricingManagement from '../components/PricingManagement.js';
import Chart from '../components/Chart.js';
import { api, dashboardService } from '../services/api.js';

export default function Admin({ token, user, current, addToast }) {
  const [stats, setStats] = useState({ users: '—', customers: '—', orders: '—' });
  const [team, setTeam] = useState([]);
  const [loading, setLoading] = useState(true);
  const [chartData, setChartData] = useState({
    monthly: { labels: [], datasets: [] },
    revenue: { labels: [], datasets: [] }
  });

  useEffect(() => {
    (async () => {
      setLoading(true);
      try {
        // Fetch real dashboard stats
        const statsRes = await dashboardService.getStats();
        if (statsRes?.data) {
          setStats(statsRes.data);
        } else {
          // Fallback to simulated stats
          setStats({ users: 42, customers: 18, orders: 256, revenue: 125000, active_shipments: 23 });
        }

        // Fetch team data
        try { 
          const res = await api.get('/system/team'); 
          setTeam(Array.isArray(res.data) ? res.data : []); 
        } catch (error) {
          console.error('Failed to fetch team data:', error);
        }

        // Generate chart data (simulate monthly data for demo)
        const last30Days = Array.from({ length: 30 }, (_, i) => {
          const date = new Date();
          date.setDate(date.getDate() - (29 - i));
          return date.toLocaleDateString('pl-PL', { month: 'short', day: 'numeric' });
        });

        const shipmentCounts = Array.from({ length: 30 }, () => Math.floor(Math.random() * 15) + 5);
        const revenueCounts = Array.from({ length: 30 }, () => Math.floor(Math.random() * 5000) + 2000);

        setChartData({
          monthly: {
            labels: last30Days,
            datasets: [{
              label: 'Przesyłki',
              data: shipmentCounts,
              borderColor: 'rgb(59, 130, 246)',
              backgroundColor: 'rgba(59, 130, 246, 0.1)',
              tension: 0.4,
              fill: true
            }]
          },
          revenue: {
            labels: last30Days.slice(-7), // Last 7 days for revenue
            datasets: [{
              label: 'Przychód (PLN)',
              data: revenueCounts.slice(-7),
              backgroundColor: 'rgba(34, 197, 94, 0.8)',
              borderColor: 'rgb(34, 197, 94)',
              borderWidth: 1
            }]
          }
        });
      } catch (error) {
        console.error('Failed to fetch dashboard data:', error);
        addToast({
          title: 'Błąd',
          body: 'Nie udało się pobrać danych dashboardu',
          type: 'error'
        });
      } finally {
        setLoading(false);
      }
    })();
  }, [token, addToast]);

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

  if (current === 'pricing') return (
    React.createElement('div', { style: styles.page },
      React.createElement('h2', null, 'Zarządzanie cennikiem'),
      React.createElement(PricingManagement, { token, addToast })
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

  // overview with loading state
  return React.createElement('div', { style: styles.page },
    React.createElement('h2', null, 'Przegląd systemu'),
    loading ? React.createElement('div', { style: styles.loadingContainer },
      React.createElement('div', { style: styles.spinner }),
      React.createElement('p', null, 'Ładowanie danych...')
    ) : React.createElement(React.Fragment, null,
      React.createElement('div', { style: styles.grid },
        React.createElement(StatCard, { label: 'Użytkownicy', value: stats.users, tone: 'primary' }),
        React.createElement(StatCard, { label: 'Klienci', value: stats.customers }),
        React.createElement(StatCard, { label: 'Zamówienia (30d)', value: stats.orders }),
        stats.revenue && React.createElement(StatCard, { label: 'Przychód (PLN)', value: formatAmount(stats.revenue), tone: 'success' }),
        stats.active_shipments && React.createElement(StatCard, { label: 'Aktywne przesyłki', value: stats.active_shipments })
      ),
      // Charts section
      React.createElement('div', { style: styles.chartsGrid },
        React.createElement('div', { style: styles.card },
          React.createElement('div', { style: styles.sectionTitle }, 'Przesyłki - ostatnie 30 dni'),
          React.createElement(Chart, {
            type: 'line',
            data: chartData.monthly,
            height: 250
          })
        ),
        React.createElement('div', { style: styles.card },
          React.createElement('div', { style: styles.sectionTitle }, 'Przychód - ostatni tydzień'),
          React.createElement(Chart, {
            type: 'bar',
            data: chartData.revenue,
            height: 250
          })
        )
      ),
      React.createElement('div', { style: styles.card },
        React.createElement('div', { style: styles.sectionTitle }, 'Aktywność'),
        React.createElement(ActivityFeed, { addToast })
      )
    )
  );
}

// Activity Feed Component
const ActivityFeed = ({ addToast }) => {
  const [activity, setActivity] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      try {
        const activityRes = await dashboardService.getRecentActivity();
        if (activityRes?.data) {
          setActivity(activityRes.data);
        }
      } catch (error) {
        console.error('Failed to fetch activity:', error);
        addToast({
          title: 'Błąd',
          body: 'Nie udało się pobrać ostatniej aktywności',
          type: 'error'
        });
      } finally {
        setLoading(false);
      }
    })();
  }, [addToast]);

  if (loading) {
    return React.createElement('div', { style: { ...styles.muted, textAlign: 'center', padding: 20 } }, 
      'Ładowanie aktywności...'
    );
  }

  if (!activity.length) {
    return React.createElement('div', { style: styles.muted }, 
      'Brak najnowszej aktywności.'
    );
  }

  return React.createElement('div', { style: styles.activityList },
    activity.map((item, index) => React.createElement('div', { 
      key: item.id || index, 
      style: styles.activityItem 
    },
      React.createElement('div', { style: styles.activityIcon },
        getActivityIcon(item.type)
      ),
      React.createElement('div', { style: styles.activityContent },
        React.createElement('div', { style: styles.activityTitle }, item.title),
        React.createElement('div', { style: styles.activityDescription }, item.description),
        React.createElement('div', { style: styles.activityTime }, 
          formatActivityTime(item.timestamp)
        )
      )
    ))
  );
};

// Helper functions
function getActivityIcon(type) {
  const iconStyle = { width: 16, height: 16, borderRadius: '50%' };
  switch (type) {
    case 'order_created':
      return React.createElement('div', { 
        style: { ...iconStyle, backgroundColor: '#10b981' } 
      });
    case 'shipment_update':
      return React.createElement('div', { 
        style: { ...iconStyle, backgroundColor: '#3b82f6' } 
      });
    case 'payment_received':
      return React.createElement('div', { 
        style: { ...iconStyle, backgroundColor: '#f59e0b' } 
      });
    default:
      return React.createElement('div', { 
        style: { ...iconStyle, backgroundColor: '#6b7280' } 
      });
  }
}

function formatActivityTime(timestamp) {
  try {
    const date = new Date(timestamp);
    const now = new Date();
    const diffInMinutes = Math.floor((now - date) / (1000 * 60));
    
    if (diffInMinutes < 1) return 'Teraz';
    if (diffInMinutes < 60) return `${diffInMinutes} min temu`;
    if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)} godz. temu`;
    return `${Math.floor(diffInMinutes / 1440)} dni temu`;
  } catch {
    return 'Nieznany czas';
  }
}

function formatAmount(amount) {
  try {
    return new Intl.NumberFormat('pl-PL', {
      style: 'currency',
      currency: 'PLN',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(amount);
  } catch {
    return `${amount} PLN`;
  }
}
}

const styles = {
  page: { display: 'grid', gap: 14 },
  grid: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 14 },
  chartsGrid: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(400px, 1fr))', gap: 14 },
  card: { border: '1px solid var(--border)', borderRadius: 12, background: '#fff', padding: 14, boxShadow: '0 8px 26px rgba(2,6,23,0.05)' },
  sectionTitle: { fontWeight: 800, marginBottom: 10 },
  muted: { color: 'var(--muted)' },
  table: { display: 'grid', border: '1px solid var(--border)', borderRadius: 12, overflow: 'hidden' },
  trHead: { display: 'grid', gridTemplateColumns: '2fr 2fr 1fr 1fr', background: 'var(--primary-50)', borderBottom: '1px solid var(--border)' },
  tr: { display: 'grid', gridTemplateColumns: '2fr 2fr 1fr 1fr', borderBottom: '1px solid var(--border)' },
  th: { padding: 10, fontWeight: 800 },
  td: { padding: 10 },
  btn: { border: '1px solid var(--border)', background: '#fff', borderRadius: 10, padding: '10px 12px', fontWeight: 700, cursor: 'pointer' },
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
  activityList: { display: 'flex', flexDirection: 'column', gap: 12 },
  activityItem: { display: 'flex', alignItems: 'flex-start', gap: 12, padding: 12, borderRadius: 8, background: 'var(--primary-50)' },
  activityIcon: { flexShrink: 0, marginTop: 2 },
  activityContent: { flex: 1 },
  activityTitle: { fontWeight: 600, marginBottom: 4 },
  activityDescription: { color: 'var(--muted)', fontSize: 14, marginBottom: 4 },
  activityTime: { color: 'var(--muted)', fontSize: 12 }
};

