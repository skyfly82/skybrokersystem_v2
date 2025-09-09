import React, { useEffect } from 'react';

const Toast = ({ t, onClose }) => {
  useEffect(() => {
    const id = setTimeout(() => onClose(t.id), 4500);
    return () => clearTimeout(id);
  }, [t.id]);
  const color = t.type === 'success' ? '#16a34a' : t.type === 'danger' ? '#dc2626' : t.type === 'warning' ? '#d97706' : 'var(--primary-600)';
  return React.createElement('div', { style: { ...styles.toast, borderLeftColor: color } },
    React.createElement('div', { style: styles.title }, t.title || 'Powiadomienie'),
    t.body && React.createElement('div', { style: styles.body }, t.body)
  );
};

export default function ToastCenter({ toasts, onClose }) {
  if (!toasts?.length) return null;
  return React.createElement('div', { style: styles.wrap },
    toasts.map((t) => React.createElement(Toast, { key: t.id, t, onClose }))
  );
}

const styles = {
  wrap: { position: 'fixed', right: 14, bottom: 14, display: 'grid', gap: 10, zIndex: 100 },
  toast: { border: '1px solid var(--border)', borderLeftWidth: 6, borderRadius: 10, padding: '10px 12px', background: '#fff', minWidth: 260, boxShadow: '0 8px 26px rgba(2,6,23,0.12)' },
  title: { fontWeight: 800, marginBottom: 4 },
  body: { color: 'var(--muted)' }
};

