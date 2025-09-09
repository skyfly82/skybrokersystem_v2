import React from 'react';

export default function Topbar({ user, onLogout }) {
  const initials = user?.email ? user.email[0]?.toUpperCase() : 'U';
  return React.createElement('header', { style: styles.header },
    React.createElement('div', { style: styles.inner },
      React.createElement('a', { href: '/web', style: styles.brand, 'aria-label': 'Powrót do strony' },
        React.createElement('div', { style: styles.mark, 'aria-hidden': true }),
        React.createElement('strong', null, 'Sky')
      ),
      React.createElement('div', { style: styles.right },
        user && React.createElement('div', { style: styles.user },
          React.createElement('div', { style: styles.avatar }, initials),
          React.createElement('div', null,
            React.createElement('div', { style: styles.userName }, user.email || 'Użytkownik'),
            user.company_name && React.createElement('div', { style: styles.userMuted }, user.company_name)
          )
        ),
        React.createElement('button', { onClick: onLogout, style: styles.logout }, 'Wyloguj')
      )
    )
  );
}

const styles = {
  header: { position: 'sticky', top: 0, zIndex: 30, background: 'rgba(255,255,255,0.72)', backdropFilter: 'saturate(180%) blur(8px)', borderBottom: '1px solid var(--border)' },
  inner: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '12px 16px' },
  brand: { display: 'inline-flex', alignItems: 'center', gap: 10, color: 'var(--ink)', textDecoration: 'none' },
  mark: { width: 22, height: 22, borderRadius: 6, background: 'linear-gradient(135deg, var(--primary), var(--accent))', boxShadow: '0 6px 18px rgba(47,125,255,0.28)' },
  right: { display: 'flex', alignItems: 'center', gap: 12 },
  user: { display: 'flex', alignItems: 'center', gap: 10, padding: '8px 10px', border: '1px solid var(--border)', borderRadius: 10, background: '#fff' },
  avatar: { width: 30, height: 30, borderRadius: 8, background: 'var(--primary-50)', color: 'var(--primary-600)', fontWeight: 800, display: 'grid', placeItems: 'center' },
  userName: { fontWeight: 700 },
  userMuted: { color: 'var(--muted)', fontSize: 12 },
  logout: { border: '1px solid var(--border)', background: '#fff', borderRadius: 10, padding: '10px 12px', fontWeight: 700, cursor: 'pointer' }
};

