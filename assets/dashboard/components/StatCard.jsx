import React from 'react';

export default function StatCard({ label, value, hint, tone = 'default' }) {
  const toneStyle = tone === 'primary' ? styles.primary : tone === 'success' ? styles.success : tone === 'danger' ? styles.danger : styles.default;
  return React.createElement('div', { style: { ...styles.card, ...toneStyle } },
    React.createElement('div', { style: styles.value }, value ?? '—'),
    React.createElement('div', { style: styles.label }, label),
    hint && React.createElement('div', { style: styles.hint }, hint)
  );
}

const styles = {
  card: { border: '1px solid var(--border)', borderRadius: 12, padding: 14, background: '#fff', boxShadow: '0 8px 26px rgba(2,6,23,0.05)', display: 'grid', gap: 6 },
  value: { fontWeight: 800, fontSize: 28 },
  label: { color: 'var(--muted)', fontWeight: 600 },
  hint: { color: 'var(--muted)', fontSize: 12 },
  default: {},
  primary: { background: 'linear-gradient(180deg, #FFFFFF 0%, #F9FBFF 100%)' },
  success: { borderColor: '#c6f6d5', background: '#f0fff4' },
  danger: { borderColor: '#fecaca', background: '#fef2f2' }
};

