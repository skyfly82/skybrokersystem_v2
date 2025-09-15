import React, { useState, useEffect } from 'react';

const NavItem = ({ label, k, current, onClick, icon }) => (
  React.createElement('button', {
    onClick: () => onClick(k),
    style: {
      ...styles.item,
      ...(current === k ? styles.itemActive : null)
    },
    'aria-current': current === k ? 'page' : undefined,
    className: 'nav-item'
  }, 
    icon && React.createElement('span', { style: styles.itemIcon }, icon),
    label
  )
);

const SidebarToggle = ({ isCollapsed, onToggle }) => (
  React.createElement('button', {
    onClick: onToggle,
    style: styles.toggleButton,
    'aria-label': isCollapsed ? 'Rozwiń menu' : 'Zwiń menu',
    className: 'sidebar-toggle'
  },
    React.createElement('span', { style: styles.hamburger },
      React.createElement('span', { style: { ...styles.hamburgerLine, transform: isCollapsed ? 'rotate(45deg) translate(5px, 5px)' : 'none' } }),
      React.createElement('span', { style: { ...styles.hamburgerLine, opacity: isCollapsed ? 0 : 1 } }),
      React.createElement('span', { style: { ...styles.hamburgerLine, transform: isCollapsed ? 'rotate(-45deg) translate(7px, -6px)' : 'none' } })
    )
  )
);

export default function Sidebar({ className, userType, current, onNavigate }) {
  const [isCollapsed, setIsCollapsed] = useState(false);
  const [isMobile, setIsMobile] = useState(false);

  // Responsive behavior
  useEffect(() => {
    const checkMobile = () => {
      const mobile = window.innerWidth < 900;
      setIsMobile(mobile);
      if (mobile) {
        setIsCollapsed(true);
      }
    };
    
    checkMobile();
    window.addEventListener('resize', checkMobile);
    return () => window.removeEventListener('resize', checkMobile);
  }, []);

  const items = userType === 'system'
    ? [
        { k: 'overview', label: 'Przegląd', icon: '📊' },
        { k: 'pricing', label: 'Cennik', icon: '💰' },
        { k: 'team', label: 'Zespół', icon: '👥' },
        { k: 'customers', label: 'Klienci', icon: '🏢' },
        { k: 'orders', label: 'Zamówienia', icon: '📦' },
        { k: 'settings', label: 'Ustawienia', icon: '⚙️' },
      ]
    : [
        { k: 'overview', label: 'Pulpit', icon: '🏠' },
        { k: 'pricing', label: 'Kalkulator cen', icon: '🧮' },
        { k: 'orders', label: 'Zamówienia', icon: '📋' },
        { k: 'shipments', label: 'Wysyłki', icon: '🚚' },
        { k: 'billing', label: 'Płatności', icon: '💳' },
        { k: 'company', label: 'Firma', icon: '🏭' },
      ];

  const handleNavigation = (key) => {
    onNavigate(key);
    // Auto-collapse on mobile after navigation
    if (isMobile) {
      setIsCollapsed(true);
    }
  };

  return React.createElement('aside', { 
    className: `${className} ${isCollapsed ? 'collapsed' : ''}`, 
    style: {
      ...styles.aside,
      width: isCollapsed && !isMobile ? '60px' : '280px',
      transform: isMobile && isCollapsed ? 'translateX(-100%)' : 'translateX(0)',
    }
  },
    React.createElement('div', { style: styles.asideInner },
      React.createElement('div', { style: styles.header },
        !isCollapsed && React.createElement('div', { style: styles.sectionTitle }, 'Nawigacja'),
        React.createElement(SidebarToggle, { isCollapsed, onToggle: () => setIsCollapsed(!isCollapsed) })
      ),
      items.map((it) => React.createElement(NavItem, { 
        key: it.k, 
        k: it.k, 
        label: isCollapsed ? '' : it.label, 
        icon: it.icon,
        current, 
        onClick: handleNavigation 
      }))
    ),
    // Mobile overlay
    isMobile && !isCollapsed && React.createElement('div', {
      style: styles.overlay,
      onClick: () => setIsCollapsed(true)
    })
  );
}

const styles = {
  aside: { 
    position: 'relative', 
    minHeight: '100%', 
    background: '#fff', 
    transition: 'all 0.3s ease-in-out',
    borderRight: '1px solid var(--border)',
    zIndex: 40
  },
  asideInner: { 
    position: 'sticky', 
    top: 62, 
    padding: 14, 
    display: 'flex', 
    gap: 6, 
    flexDirection: 'column',
    height: 'calc(100vh - 62px)',
    overflowY: 'auto'
  },
  header: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8
  },
  sectionTitle: { 
    color: 'var(--muted)', 
    fontSize: 12, 
    textTransform: 'uppercase', 
    letterSpacing: 0.4, 
    padding: '6px 8px',
    margin: 0
  },
  item: { 
    textAlign: 'left', 
    border: '1px solid var(--border)', 
    background: '#fff', 
    color: 'var(--ink)', 
    padding: '10px 12px', 
    borderRadius: 10, 
    cursor: 'pointer', 
    fontWeight: 600,
    transition: 'all 0.2s ease',
    display: 'flex',
    alignItems: 'center',
    gap: 8,
    minHeight: '44px'
  },
  itemActive: { 
    borderColor: 'var(--primary-600)', 
    color: 'var(--primary-600)', 
    boxShadow: '0 0 0 4px var(--primary-50)',
    background: 'var(--primary-50)'
  },
  itemIcon: {
    fontSize: 16,
    minWidth: 20,
    textAlign: 'center'
  },
  toggleButton: {
    background: 'none',
    border: 'none',
    cursor: 'pointer',
    padding: 8,
    borderRadius: 6,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    transition: 'background 0.2s ease'
  },
  hamburger: {
    width: 20,
    height: 15,
    position: 'relative',
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'space-between'
  },
  hamburgerLine: {
    width: '100%',
    height: 2,
    backgroundColor: 'var(--ink)',
    transition: 'all 0.3s ease',
    transformOrigin: 'center'
  },
  overlay: {
    position: 'fixed',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    zIndex: -1
  }
};

