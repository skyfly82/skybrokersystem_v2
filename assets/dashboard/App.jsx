import React, { useEffect, useMemo, useState } from 'react';
import Topbar from './components/Topbar.jsx';
import Sidebar from './components/Sidebar.jsx';
import ToastCenter from './components/ToastCenter.jsx';
import { api } from './services/api.js';
import { useNotifications } from './hooks/useNotifications.js';
import Admin from './pages/Admin.jsx';
import Customer from './pages/Customer.jsx';

const Layout = ({ children, user, userType, onLogout, onNavigate, current }) => (
  React.createElement('div', { className: 'dash-root', style: styles.root },
    React.createElement(Topbar, { user, onLogout }),
    React.createElement('div', { className: 'dash-shell', style: styles.shell },
      React.createElement(Sidebar, { className: 'dash-aside', userType, current, onNavigate }),
      React.createElement('main', { className: 'dash-main', style: styles.main }, children)
    )
  )
);

export default function App() {
  const [token, setToken] = useState(null);
  const [userType, setUserType] = useState('customer'); // 'customer' | 'system'
  const [user, setUser] = useState(null);
  const [current, setCurrent] = useState('overview');
  const [toasts, setToasts] = useState([]);

  // initial auth bootstrap
  useEffect(() => {
    const t = localStorage.getItem('jwt_token');
    if (!t) {
      window.location.href = '/auth';
      return;
    }
    setToken(t);
    const ut = localStorage.getItem('user_type') || 'customer';
    setUserType(ut);
  }, []);

  // notifications
  useNotifications({
    token,
    onMessage: (msg) => addToast({ title: msg.title || 'Powiadomienie', body: msg.body || String(msg.type || ''), type: msg.level || 'info' }),
    onError: () => {/* silent */},
    demo: true,
  });

  // fetch profile
  useEffect(() => {
    let active = true;
    (async () => {
      if (!token) return;
      const profile = await api.get(`/${userType}/profile`).catch(() => null);
      if (active) setUser(profile?.data || null);
    })();
    return () => { active = false; };
  }, [token, userType]);

  const addToast = (t) => setToasts((arr) => [...arr, { id: Date.now() + Math.random(), ...t }]);
  const removeToast = (id) => setToasts((arr) => arr.filter(t => t.id !== id));

  const onLogout = () => {
    try {
      localStorage.removeItem('jwt_token');
      localStorage.removeItem('user_type');
      localStorage.removeItem('user_data');
    } catch (e) {}
    window.location.href = '/auth';
  };

  const onNavigate = (key) => setCurrent(key);

  const page = useMemo(() => {
    if (userType === 'system') return React.createElement(Admin, { token, user, current, addToast });
    return React.createElement(Customer, { token, user, current, addToast });
  }, [userType, token, user, current]);

  return React.createElement(React.Fragment, null,
    React.createElement(Layout, { user, userType, onLogout, onNavigate, current }, page),
    React.createElement(ToastCenter, { toasts, onClose: removeToast })
  );
}

const styles = {
  root: {
    minHeight: '100%', display: 'grid', gridTemplateRows: 'auto 1fr'
  },
  shell: { display: 'grid', gridTemplateColumns: '280px 1fr' },
  main: { padding: 18, background: '#fff', borderLeft: '1px solid var(--border)' }
};
