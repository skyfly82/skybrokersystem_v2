import React, { useEffect, useState } from 'react';

const Toast = ({ t, onClose }) => {
  const [isVisible, setIsVisible] = useState(false);
  const [isExiting, setIsExiting] = useState(false);

  useEffect(() => {
    // Animate in
    setTimeout(() => setIsVisible(true), 10);
    
    // Auto-hide after 5 seconds (matching Laravel behavior)
    const hideTimer = setTimeout(() => {
      setIsExiting(true);
      setTimeout(() => onClose(t.id), 300);
    }, 5000);
    
    return () => clearTimeout(hideTimer);
  }, [t.id, onClose]);

  const handleClick = () => {
    setIsExiting(true);
    setTimeout(() => onClose(t.id), 300);
  };

  const getToastStyle = () => {
    const baseStyle = {
      ...styles.toast,
      transform: isVisible && !isExiting ? 'translateX(0)' : 'translateX(100%)',
      opacity: isVisible && !isExiting ? 1 : 0
    };

    switch (t.type) {
      case 'success':
        return { ...baseStyle, borderLeftColor: '#16a34a', background: '#f0fdf4' };
      case 'danger':
      case 'error':
        return { ...baseStyle, borderLeftColor: '#dc2626', background: '#fef2f2' };
      case 'warning':
        return { ...baseStyle, borderLeftColor: '#d97706', background: '#fffbeb' };
      case 'info':
      default:
        return { ...baseStyle, borderLeftColor: 'var(--primary-600)', background: '#eff6ff' };
    }
  };

  const getIcon = () => {
    switch (t.type) {
      case 'success':
        return '✓';
      case 'danger':
      case 'error':
        return '✗';
      case 'warning':
        return '⚠';
      case 'info':
      default:
        return 'ℹ';
    }
  };

  return React.createElement('div', { 
    style: getToastStyle(),
    onClick: handleClick
  },
    React.createElement('div', { style: styles.toastHeader },
      React.createElement('span', { style: styles.toastIcon }, getIcon()),
      React.createElement('div', { style: styles.title }, t.title || 'Powiadomienie'),
      React.createElement('button', { style: styles.closeButton, onClick: handleClick }, '×')
    ),
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
  wrap: { position: 'fixed', right: 14, bottom: 14, display: 'flex', flexDirection: 'column', gap: 10, zIndex: 1000 },
  toast: { 
    border: '1px solid var(--border)', 
    borderLeftWidth: 6, 
    borderRadius: 10, 
    padding: 0,
    background: '#fff', 
    minWidth: 320, 
    maxWidth: 400,
    boxShadow: '0 10px 25px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05)',
    cursor: 'pointer',
    transition: 'all 0.3s ease-in-out',
    overflow: 'hidden'
  },
  toastHeader: {
    display: 'flex',
    alignItems: 'center',
    padding: '12px 16px 8px',
    gap: 8
  },
  toastIcon: {
    fontSize: 16,
    fontWeight: 800,
    minWidth: 20,
    textAlign: 'center'
  },
  title: { 
    fontWeight: 700, 
    flex: 1,
    margin: 0,
    fontSize: 15
  },
  body: { 
    color: 'var(--muted)', 
    fontSize: 14,
    padding: '0 16px 12px',
    lineHeight: 1.4
  },
  closeButton: {
    background: 'none',
    border: 'none',
    fontSize: 18,
    cursor: 'pointer',
    color: 'var(--muted)',
    padding: 0,
    width: 20,
    height: 20,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 4,
    transition: 'color 0.2s ease'
  }
};

