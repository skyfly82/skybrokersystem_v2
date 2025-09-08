import React from 'react';

const e = React.createElement;

const BrandMark = () => e('div', { className: 'brand-mark', 'aria-hidden': 'true' });

const Nav = () => e(
  'header', { className: 'nav' },
  e('div', { className: 'nav-inner' },
    e('a', { className: 'brand', href: '/web', 'aria-label': 'Sky start' },
      e(BrandMark),
      e('div', { className: 'brand-name' }, 'Sky')
    ),
    e('nav', { className: 'nav-links' },
      e('a', { className: 'btn', href: '/api/docs' }, 'API Docs'),
      e('a', { className: 'btn', href: '/dashboard' }, 'Dashboard'),
      e('a', { className: 'btn', href: '/register' }, 'Rejestracja'),
      e('a', { className: 'btn-primary', href: '/login' }, 'Zaloguj się')
    )
  )
);

const Hero = () => e(
  'section', { className: 'hero' },
  e('div', { className: 'hero-inner' },
    e('div', null,
      e('div', { className: 'hero-badge' }, 'Nowa platforma • v2'),
      e('h1', null, 'Nowoczesna platforma do obsługi klientów i partnerów'),
      e('p', null, 'Sky łączy rejestrację klientów, uwierzytelnianie, panel administracyjny i API‑first w jedno spójne środowisko — szybkie, bezpieczne i skalowalne.'),
      e('div', { className: 'hero-cta' },
        e('a', { className: 'btn-primary', href: '/login/customer' }, 'Logowanie klienta'),
        e('a', { className: 'btn', href: '/login/admin' }, 'Logowanie administratora'),
        e('a', { className: 'btn', href: '/api/v1/system/login' }, 'API: System Login')
      ),
      e('div', { className: 'hero-card', style: { marginTop: 12 } },
        e('strong', null, 'Najważniejsze:'), ' JWT, zaproszenia użytkowników firmowych, profil systemowy, dashboard, status zdrowia i pełna dokumentacja API.'
      )
    ),
    e('div', { className: 'hero-art', 'aria-hidden': 'true' })
  )
);

const Feature = ({ title, children }) => e(
  'div', { className: 'feature' },
  e('h3', null, title),
  e('p', null, children)
);

const Features = () => e(
  'section', { className: 'section' },
  e('div', { className: 'section-inner' },
    e('h2', null, 'Co zyskujesz z Sky'),
    e('div', { className: 'features' },
      e(Feature, { title: 'Bezpieczeństwo i sesje' }, 'Uwierzytelnianie JWT z krótkimi tokenami i bezpiecznymi politykami sesji (OWASP‑ready).'),
      e(Feature, { title: 'Rejestracja klientów' }, 'Wielostopniowa rejestracja, walidacje oraz integracja z rejestrem GUS.'),
      e(Feature, { title: 'Zaproszenia do zespołu' }, 'Wysyłka zaproszeń, role i uprawnienia dla użytkowników firmowych.'),
      e(Feature, { title: 'API‑First' }, 'Otwarte endpointy z dokumentacją — łatwa integracja z usługami zewnętrznymi.')
    )
  )
);

const CTA = () => e(
  'section', { className: 'section cta' },
  e('div', { className: 'section-inner' },
    e('div', { className: 'cta-box' },
      e('div', null,
        e('div', { className: 'cta-title' }, 'Gotowy, by zacząć?'),
        e('div', { style: { color: 'var(--muted)' } }, 'Załóż konto lub zaloguj się, aby przejść do panelu.')
      ),
      e('div', { style: { display: 'flex', gap: 10 } },
        e('a', { className: 'btn', href: '/register' }, 'Utwórz konto'),
        e('a', { className: 'btn-primary', href: '/login' }, 'Zaloguj się')
      )
    )
  )
);

const Footer = () => e(
  'footer', { className: 'footer' },
  e('div', { className: 'footer-inner' },
    e('div', null, `© ${new Date().getFullYear()} Sky`),
    e('div', { style: { display: 'flex', gap: 14 } },
      e('a', { href: '/health' }, 'Health'),
      e('a', { href: '/_profiler' }, 'Profiler'),
      e('a', { href: '/api/docs' }, 'API Docs')
    )
  )
);

export default function App() {
  return e(React.Fragment, null,
    e(Nav),
    e(Hero),
    e(Features),
    e(CTA),
    e(Footer)
  );
}
