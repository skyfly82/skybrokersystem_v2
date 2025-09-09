import React from 'react';

const NavItem = ({ label, k, current, onClick }) => (
  React.createElement('button', {
    onClick: () => onClick(k),
    style: {
      ...styles.item,
      ...(current === k ? styles.itemActive : null)
    },
    'aria-current': current === k ? 'page' : undefined
  }, label)
);

export default function Sidebar({ className, userType, current, onNavigate }) {
  const items = userType === 'system'
    ? [
        { k: 'overview', label: 'Przegląd' },
        { k: 'team', label: 'Zespół' },
        { k: 'customers', label: 'Klienci' },
        { k: 'orders', label: 'Zamówienia' },
        { k: 'settings', label: 'Ustawienia' },
      ]
    : [
        { k: 'overview', label: 'Pulpit' },
        { k: 'orders', label: 'Zamówienia' },
        { k: 'shipments', label: 'Wysyłki' },
        { k: 'billing', label: 'Płatności' },
        { k: 'company', label: 'Firma' },
      ];

  return React.createElement('aside', { className, style: styles.aside },
    React.createElement('div', { style: styles.asideInner },
      React.createElement('div', { style: styles.sectionTitle }, 'Nawigacja'),
      items.map((it) => React.createElement(NavItem, { key: it.k, k: it.k, label: it.label, current, onClick: onNavigate }))
    )
  );
}

const styles = {
  aside: { position: 'relative', minHeight: '100%', background: '#fff' },
  asideInner: { position: 'sticky', top: 62, padding: 14, display: 'flex', gap: 6, flexDirection: 'column' },
  sectionTitle: { color: 'var(--muted)', fontSize: 12, textTransform: 'uppercase', letterSpacing: 0.4, padding: '6px 8px' },
  item: { textAlign: 'left', border: '1px solid var(--border)', background: '#fff', color: 'var(--ink)', padding: '10px 12px', borderRadius: 10, cursor: 'pointer', fontWeight: 600 },
  itemActive: { borderColor: 'var(--primary-600)', color: 'var(--primary-600)', boxShadow: '0 0 0 4px var(--primary-50)' }
};
