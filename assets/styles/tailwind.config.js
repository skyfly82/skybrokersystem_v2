/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./assets/**/*.{js,ts,jsx,tsx}",
    "./templates/**/*.html.twig",
    "./src/**/*.php"
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
          950: '#172554',
        },
        gray: {
          50: '#f9fafb',
          100: '#f3f4f6',
          200: '#e5e7eb',
          300: '#d1d5db',
          400: '#9ca3af',
          500: '#6b7280',
          600: '#4b5563',
          700: '#374151',
          800: '#1f2937',
          900: '#111827',
          950: '#030712',
        },
        success: {
          50: '#f0fdf4',
          100: '#dcfce7',
          200: '#bbf7d0',
          300: '#86efac',
          400: '#4ade80',
          500: '#22c55e',
          600: '#16a34a',
          700: '#15803d',
          800: '#166534',
          900: '#14532d',
          950: '#052e16',
        },
        warning: {
          50: '#fffbeb',
          100: '#fef3c7',
          200: '#fde68a',
          300: '#fcd34d',
          400: '#fbbf24',
          500: '#f59e0b',
          600: '#d97706',
          700: '#b45309',
          800: '#92400e',
          900: '#78350f',
          950: '#451a03',
        },
        error: {
          50: '#fef2f2',
          100: '#fee2e2',
          200: '#fecaca',
          300: '#fca5a5',
          400: '#f87171',
          500: '#ef4444',
          600: '#dc2626',
          700: '#b91c1c',
          800: '#991b1b',
          900: '#7f1d1d',
          950: '#450a0a',
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'Menlo', 'Monaco', 'monospace'],
      },
      fontSize: {
        'xs': ['0.75rem', { lineHeight: '1rem' }],
        'sm': ['0.875rem', { lineHeight: '1.25rem' }],
        'base': ['1rem', { lineHeight: '1.5rem' }],
        'lg': ['1.125rem', { lineHeight: '1.75rem' }],
        'xl': ['1.25rem', { lineHeight: '1.75rem' }],
        '2xl': ['1.5rem', { lineHeight: '2rem' }],
        '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
        '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
        '5xl': ['3rem', { lineHeight: '1' }],
        '6xl': ['3.75rem', { lineHeight: '1' }],
      },
      spacing: {
        '18': '4.5rem',
        '88': '22rem',
        '128': '32rem',
        '144': '36rem',
      },
      maxWidth: {
        '8xl': '88rem',
        '9xl': '96rem',
      },
      boxShadow: {
        'card': '0 8px 26px rgba(2, 6, 23, 0.05)',
        'card-hover': '0 12px 32px rgba(2, 6, 23, 0.1)',
        'sidebar': '0 0 15px rgba(0, 0, 0, 0.05)',
        'modal': '0 25px 50px -12px rgba(0, 0, 0, 0.25)',
      },
      animation: {
        'fade-in': 'fadeIn 0.5s ease-in-out',
        'slide-up': 'slideUp 0.3s ease-out',
        'slide-down': 'slideDown 0.3s ease-out',
        'slide-left': 'slideLeft 0.3s ease-out',
        'slide-right': 'slideRight 0.3s ease-out',
        'scale-in': 'scaleIn 0.2s ease-out',
        'bounce-in': 'bounceIn 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55)',
        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { transform: 'translateY(10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
        slideDown: {
          '0%': { transform: 'translateY(-10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
        slideLeft: {
          '0%': { transform: 'translateX(10px)', opacity: '0' },
          '100%': { transform: 'translateX(0)', opacity: '1' },
        },
        slideRight: {
          '0%': { transform: 'translateX(-10px)', opacity: '0' },
          '100%': { transform: 'translateX(0)', opacity: '1' },
        },
        scaleIn: {
          '0%': { transform: 'scale(0.95)', opacity: '0' },
          '100%': { transform: 'scale(1)', opacity: '1' },
        },
        bounceIn: {
          '0%': { transform: 'scale(0.3)', opacity: '0' },
          '50%': { transform: 'scale(1.05)' },
          '70%': { transform: 'scale(0.9)' },
          '100%': { transform: 'scale(1)', opacity: '1' },
        },
      },
      backdropBlur: {
        'xs': '2px',
      },
      screens: {
        'xs': '475px',
        '3xl': '1600px',
      },
      transitionProperty: {
        'height': 'height',
        'spacing': 'margin, padding',
        'colors': 'color, background-color, border-color',
      },
      zIndex: {
        '60': '60',
        '70': '70',
        '80': '80',
        '90': '90',
        '100': '100',
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms')({
      strategy: 'class',
    }),
    require('@tailwindcss/typography'),
    require('@tailwindcss/aspect-ratio'),
    // Custom plugin for Sky Broker components
    function({ addUtilities, addComponents, theme }) {
      // Custom utilities
      const newUtilities = {
        '.nav-item': {
          transition: 'all 0.15s ease',
          '&:hover': {
            borderColor: theme('colors.primary.600'),
            backgroundColor: theme('colors.gray.50'),
          },
        },
        '.nav-item-active': {
          borderColor: theme('colors.primary.600'),
          color: theme('colors.primary.600'),
          boxShadow: `0 0 0 4px ${theme('colors.primary.50')}`,
        },
        '.card-shadow': {
          boxShadow: theme('boxShadow.card'),
        },
        '.sidebar-shadow': {
          boxShadow: theme('boxShadow.sidebar'),
        },
        '.text-balance': {
          textWrap: 'balance',
        },
      };

      // Custom components
      const newComponents = {
        '.btn': {
          padding: `${theme('spacing.2')} ${theme('spacing.4')}`,
          borderRadius: theme('borderRadius.lg'),
          fontWeight: theme('fontWeight.medium'),
          transition: 'all 0.2s ease',
          display: 'inline-flex',
          alignItems: 'center',
          justifyContent: 'center',
          '&:focus': {
            outline: 'none',
            ringWidth: '2px',
            ringOffset: '2px',
          },
        },
        '.btn-primary': {
          backgroundColor: theme('colors.primary.600'),
          color: theme('colors.white'),
          '&:hover': {
            backgroundColor: theme('colors.primary.700'),
          },
          '&:focus': {
            ringColor: theme('colors.primary.500'),
          },
          '&:disabled': {
            backgroundColor: theme('colors.gray.300'),
            cursor: 'not-allowed',
          },
        },
        '.btn-secondary': {
          backgroundColor: theme('colors.white'),
          color: theme('colors.gray.700'),
          borderWidth: '1px',
          borderColor: theme('colors.gray.300'),
          '&:hover': {
            backgroundColor: theme('colors.gray.50'),
          },
          '&:focus': {
            ringColor: theme('colors.primary.500'),
          },
        },
        '.input': {
          width: '100%',
          padding: `${theme('spacing.2')} ${theme('spacing.3')}`,
          borderWidth: '1px',
          borderColor: theme('colors.gray.300'),
          borderRadius: theme('borderRadius.lg'),
          transition: 'all 0.2s ease',
          '&:focus': {
            outline: 'none',
            ringWidth: '2px',
            ringColor: theme('colors.primary.500'),
            borderColor: 'transparent',
          },
          '&.error': {
            borderColor: theme('colors.error.300'),
            '&:focus': {
              ringColor: theme('colors.error.500'),
            },
          },
          '&:disabled': {
            backgroundColor: theme('colors.gray.100'),
            cursor: 'not-allowed',
          },
        },
        '.card': {
          backgroundColor: theme('colors.white'),
          borderWidth: '1px',
          borderColor: theme('colors.gray.200'),
          borderRadius: theme('borderRadius.xl'),
          padding: theme('spacing.6'),
          boxShadow: theme('boxShadow.card'),
        },
        '.status-badge': {
          padding: `${theme('spacing.1')} ${theme('spacing.2')}`,
          fontSize: theme('fontSize.xs'),
          fontWeight: theme('fontWeight.medium'),
          borderRadius: theme('borderRadius.full'),
          textTransform: 'uppercase',
          letterSpacing: theme('letterSpacing.wide'),
        },
        '.status-success': {
          backgroundColor: theme('colors.success.100'),
          color: theme('colors.success.800'),
        },
        '.status-warning': {
          backgroundColor: theme('colors.warning.100'),
          color: theme('colors.warning.800'),
        },
        '.status-error': {
          backgroundColor: theme('colors.error.100'),
          color: theme('colors.error.800'),
        },
        '.status-info': {
          backgroundColor: theme('colors.primary.100'),
          color: theme('colors.primary.800'),
        },
      };

      addUtilities(newUtilities);
      addComponents(newComponents);
    },
  ],
  // Safelist important classes that might be generated dynamically
  safelist: [
    'bg-blue-50',
    'bg-yellow-50',
    'bg-green-50',
    'bg-red-50',
    'text-blue-600',
    'text-yellow-600',
    'text-green-600',
    'text-red-600',
    'border-blue-200',
    'border-yellow-200',
    'border-green-200',
    'border-red-200',
  ],
};