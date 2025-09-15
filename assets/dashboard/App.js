import React, { useEffect, useMemo, useState } from 'react';
import Topbar from './components/Topbar.js';
import Sidebar from './components/Sidebar.js';
import ToastCenter from './components/ToastCenter.js';
import { api } from './services/api.js';
import { useNotifications } from './hooks/useNotifications.js';
import Admin from './pages/Admin.js';
import Customer from './pages/Customer.js';

const Layout = ({ children, user, userType, onLogout, onNavigate, current }) => {
  const [sidebarCollapsed, setSidebarCollapsed] = React.useState(false);
  const [isMobile, setIsMobile] = React.useState(false);

  // Handle responsive behavior
  React.useEffect(() => {
    const checkMobile = () => {
      const mobile = window.innerWidth < 900;
      setIsMobile(mobile);
      if (mobile) {
        setSidebarCollapsed(true);
      }
    };
    
    checkMobile();
    window.addEventListener('resize', checkMobile);
    return () => window.removeEventListener('resize', checkMobile);
  }, []);

  // Keyboard navigation
  React.useEffect(() => {
    const handleKeyDown = (event) => {
      // Toggle sidebar with Ctrl+B (like VS Code)
      if (event.ctrlKey && event.key === 'b') {
        event.preventDefault();
        setSidebarCollapsed(!sidebarCollapsed);
      }
      
      // Close sidebar on Escape (mobile)
      if (event.key === 'Escape' && isMobile && !sidebarCollapsed) {
        setSidebarCollapsed(true);
      }
    };
    
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [sidebarCollapsed, isMobile]);

  return React.createElement('div', { className: 'dash-root', style: styles.root },
    React.createElement(Topbar, { user, onLogout, onToggleSidebar: () => setSidebarCollapsed(!sidebarCollapsed) }),
    React.createElement('div', { className: 'dash-shell', style: styles.shell },
      React.createElement(Sidebar, { 
        className: `dash-aside ${sidebarCollapsed ? 'collapsed' : ''}`, 
        userType, 
        current, 
        onNavigate,
        isCollapsed: sidebarCollapsed,
        onToggle: setSidebarCollapsed
      }),
      React.createElement('main', { 
        className: 'dash-main', 
        style: {
          ...styles.main,
          marginLeft: isMobile ? 0 : (sidebarCollapsed ? '60px' : '0'),
          transition: 'margin-left 0.3s ease'
        }
      }, children),
      // Mobile overlay
      isMobile && !sidebarCollapsed && React.createElement('div', {
        className: 'mobile-overlay',
        style: styles.mobileOverlay,
        onClick: () => setSidebarCollapsed(true)
      })
    )
  );
};

export default function App() {
  const [token, setToken] = useState(null);
  const [userType, setUserType] = useState('customer'); // 'customer' | 'system'
  const [user, setUser] = useState(null);
  const [current, setCurrent] = useState('overview');
  const [toasts, setToasts] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);

  // initial auth bootstrap with enhanced error handling
  useEffect(() => {
    setIsLoading(true);
    try {
      // Check if we have dashboard config from server
      const dashboardConfig = window.DASHBOARD_CONFIG;
      if (dashboardConfig) {
        setUserType(dashboardConfig.userType);
        // Set in localStorage for consistency
        localStorage.setItem('user_type', dashboardConfig.userType);
        // Set user data if available
        if (dashboardConfig.user) {
          setUser(dashboardConfig.user);
        }
      }
      
      const t = localStorage.getItem('jwt_token');
      if (!t) {
        // Check if user is actually logged in via session
        // Check if we have session-based auth (new system)
        if (dashboardConfig?.user) {
          // We have user data from session, no need for JWT
          setIsLoading(false);
          return;
        }
        
        // If not, redirect based on user type
        if (dashboardConfig?.userType === 'system') {
          window.location.href = '/admin/login';
        } else {
          window.location.href = '/customer/login';
        }
        return;
      }
      setToken(t);
      const ut = dashboardConfig?.userType || localStorage.getItem('user_type') || 'customer';
      setUserType(ut);
    } catch (err) {
      console.error('Failed to initialize app:', err);
      setError('Failed to initialize dashboard');
    } finally {
      setIsLoading(false);
    }
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
      if (!token) {
        // Try to get user info from new dashboard API endpoint
        try {
          const userInfo = await api.get('/dashboard/api/user-info');
          if (active && userInfo?.data) {
            setUser(userInfo.data);
            setUserType(userInfo.data.type);
            localStorage.setItem('user_type', userInfo.data.type);
          }
        } catch (error) {
          console.error('Failed to fetch user info:', error);
          // If we can't get user info, redirect to appropriate login
          const dashboardConfig = window.DASHBOARD_CONFIG;
          if (dashboardConfig?.userType === 'system') {
            window.location.href = '/system/login';
          } else {
            window.location.href = '/customer/login';
          }
        }
        return;
      }
      
      // Legacy API call for backward compatibility
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
    
    // Redirect based on user type
    if (userType === 'system') {
      window.location.href = '/admin/logout';
    } else {
      window.location.href = '/customer/logout';
    }
  };

  const onNavigate = (key) => {
    setCurrent(key);
    // Add toast for navigation feedback (can be disabled in production)
    if (process.env.NODE_ENV === 'development') {
      addToast({
        title: 'Nawigacja',
        body: `Przejście do: ${key}`,
        type: 'info'
      });
    }
  };

  const page = useMemo(() => {
    if (userType === 'system') return React.createElement(Admin, { token, user, current, addToast });
    return React.createElement(Customer, { token, user, current, addToast });
  }, [userType, token, user, current]);

  // Loading state
  if (isLoading) {
    return React.createElement('div', { style: styles.loadingScreen },
      React.createElement('div', { style: styles.loadingContent },
        React.createElement('div', { style: styles.loadingSpinner }),
        React.createElement('h2', null, 'Sky Dashboard'),
        React.createElement('p', null, 'Ładowanie...')
      )
    );
  }

  // Error state
  if (error) {
    return React.createElement('div', { style: styles.errorScreen },
      React.createElement('div', { style: styles.errorContent },
        React.createElement('h2', null, 'Wystąpił błąd'),
        React.createElement('p', null, error),
        React.createElement('button', { 
          onClick: () => window.location.reload(),
          style: styles.retryButton
        }, 'Spróbuj ponownie')
      )
    );
  }

  return React.createElement(React.Fragment, null,
    React.createElement(Layout, { user, userType, onLogout, onNavigate, current }, page),
    React.createElement(ToastCenter, { toasts, onClose: removeToast })
  );
}

const styles = {
  root: {
    minHeight: '100%', 
    display: 'grid', 
    gridTemplateRows: 'auto 1fr'
  },
  shell: { 
    display: 'grid', 
    gridTemplateColumns: '280px 1fr',
    position: 'relative'
  },
  main: { 
    padding: 18, 
    background: '#fff', 
    borderLeft: '1px solid var(--border)',
    transition: 'margin-left 0.3s ease'
  },
  mobileOverlay: {
    position: 'fixed',
    top: 62,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    zIndex: 49
  },
  loadingScreen: {
    height: '100vh',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    background: 'linear-gradient(180deg, #f8fbff 0%, #ffffff 40%)'
  },
  loadingContent: {
    textAlign: 'center'
  },
  loadingSpinner: {
    width: 60,
    height: 60,
    border: '4px solid var(--border)',
    borderTop: '4px solid var(--primary-600)',
    borderRadius: '50%',
    animation: 'spin 1s linear infinite',
    margin: '0 auto 24px'
  },
  errorScreen: {
    height: '100vh',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    background: 'linear-gradient(180deg, #f8fbff 0%, #ffffff 40%)'
  },
  errorContent: {
    textAlign: 'center',
    padding: 32,
    borderRadius: 12,
    background: '#fff',
    boxShadow: '0 8px 26px rgba(2,6,23,0.05)',
    border: '1px solid var(--border)'
  },
  retryButton: {
    marginTop: 16,
    padding: '12px 24px',
    background: 'var(--primary-600)',
    color: '#fff',
    border: 'none',
    borderRadius: 8,
    fontWeight: 600,
    cursor: 'pointer'
  }
};
