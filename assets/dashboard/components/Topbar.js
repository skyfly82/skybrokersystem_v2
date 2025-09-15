import React from 'react';

export default function Topbar({ user, onLogout, onToggleSidebar }) {
  const [isMobile, setIsMobile] = React.useState(false);
  const initials = user?.email ? user.email[0]?.toUpperCase() : 'U';
  
  React.useEffect(() => {
    const checkMobile = () => setIsMobile(window.innerWidth < 900);
    checkMobile();
    window.addEventListener('resize', checkMobile);
    return () => window.removeEventListener('resize', checkMobile);
  }, []);

  return React.createElement('header', { style: styles.header },
    React.createElement('div', { style: styles.inner },
      React.createElement('div', { style: styles.left },
        isMobile && React.createElement('button', {
          onClick: onToggleSidebar,
          style: styles.mobileToggle,
          'aria-label': 'Toggle menu'
        },
          React.createElement('span', { style: styles.hamburger },
            React.createElement('span', { style: styles.hamburgerLine }),
            React.createElement('span', { style: styles.hamburgerLine }),
            React.createElement('span', { style: styles.hamburgerLine })
          )
        ),
        React.createElement('a', { href: '/web', style: styles.brand, 'aria-label': 'Powrót do strony' },
          React.createElement('div', { style: styles.mark, 'aria-hidden': true }),
          React.createElement('strong', null, 'Sky')
        )
      ),
      React.createElement('div', { style: styles.right },
        user && React.createElement('div', { style: styles.user },
          React.createElement('div', { style: styles.avatar }, initials),
          !isMobile && React.createElement('div', null,
            React.createElement('div', { style: styles.userName }, user.email || 'Użytkownik'),
            user.company_name && React.createElement('div', { style: styles.userMuted }, user.company_name)
          )
        ),
        React.createElement('button', { 
          onClick: onLogout, 
          style: styles.logout,
          title: 'Wyloguj się'
        }, isMobile ? '↗' : 'Wyloguj')
      )
    )
  );
}

const styles = {
  header: { 
    position: 'sticky', 
    top: 0, 
    zIndex: 50, 
    background: 'rgba(255,255,255,0.95)', 
    backdropFilter: 'saturate(180%) blur(8px)', 
    borderBottom: '1px solid var(--border)'
  },
  inner: { 
    display: 'flex', 
    alignItems: 'center', 
    justifyContent: 'space-between', 
    padding: '12px 16px',
    maxWidth: '100vw'
  },
  left: {
    display: 'flex',
    alignItems: 'center',
    gap: 12
  },
  mobileToggle: {
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
    transition: 'all 0.3s ease'
  },
  brand: { 
    display: 'inline-flex', 
    alignItems: 'center', 
    gap: 10, 
    color: 'var(--ink)', 
    textDecoration: 'none',
    transition: 'opacity 0.2s ease'
  },
  mark: { 
    width: 22, 
    height: 22, 
    borderRadius: 6, 
    background: 'linear-gradient(135deg, var(--primary), var(--accent))', 
    boxShadow: '0 6px 18px rgba(47,125,255,0.28)'
  },
  right: { 
    display: 'flex', 
    alignItems: 'center', 
    gap: 12
  },
  user: { 
    display: 'flex', 
    alignItems: 'center', 
    gap: 10, 
    padding: '8px 10px', 
    border: '1px solid var(--border)', 
    borderRadius: 10, 
    background: '#fff',
    transition: 'box-shadow 0.2s ease'
  },
  avatar: { 
    width: 30, 
    height: 30, 
    borderRadius: 8, 
    background: 'var(--primary-50)', 
    color: 'var(--primary-600)', 
    fontWeight: 800, 
    display: 'grid', 
    placeItems: 'center',
    flexShrink: 0
  },
  userName: { 
    fontWeight: 700,
    whiteSpace: 'nowrap',
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    maxWidth: '150px'
  },
  userMuted: { 
    color: 'var(--muted)', 
    fontSize: 12,
    whiteSpace: 'nowrap',
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    maxWidth: '150px'
  },
  logout: { 
    border: '1px solid var(--border)', 
    background: '#fff', 
    borderRadius: 10, 
    padding: '10px 12px', 
    fontWeight: 700, 
    cursor: 'pointer',
    transition: 'all 0.2s ease'
  }
};

