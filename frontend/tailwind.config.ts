import type { Config } from 'tailwindcss';

const config: Config = {
  content: ['./src/**/*.{js,ts,jsx,tsx,mdx}'],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#eff4ff',
          100: '#dbe7f7',
          200: '#c0d6f0',
          300: '#98bce3',
          400: '#6a9cd1',
          500: '#4a80b8',
          600: '#3a689e',
          700: '#315480',
          800: '#2c486a',
          900: '#283e58',
        },
        accent: {
          50: '#fffbeb',
          100: '#fef3c7',
          200: '#fde68a',
          300: '#fcd34d',
          400: '#fbbf24',
          500: '#d97706',
          600: '#b45309',
          700: '#92400e',
          800: '#78350f',
          900: '#651e0f',
        },
        surface: {
          DEFAULT: '#fafaf9',
          card: '#ffffff',
          hover: '#f2f1f0',
          border: '#dddcd9',
          'border-light': '#e8e7e4',
        },
      },
      fontFamily: {
        sans: [
          '"Noto Sans JP"', '"Hiragino Sans"', '"Hiragino Kaku Gothic ProN"',
          '"Yu Gothic"', 'Meiryo', 'sans-serif',
        ],
        mono: [
          '"JetBrains Mono"', '"Noto Sans Mono"', 'Consolas', 'monospace',
        ],
      },
      borderRadius: {
        DEFAULT: '0.5rem',
        sm: '0.375rem',
        md: '0.625rem',
        lg: '0.75rem',
        xl: '1rem',
        '2xl': '1.25rem',
      },
      boxShadow: {
        subtle: '0 1px 2px 0 rgb(0 0 0 / 0.04)',
        card: '0 1px 3px 0 rgb(0 0 0 / 0.06), 0 1px 2px -1px rgb(0 0 0 / 0.04)',
        elevated: '0 4px 12px 0 rgb(0 0 0 / 0.08), 0 1px 3px 0 rgb(0 0 0 / 0.05)',
        modal: '0 20px 60px 0 rgb(0 0 0 / 0.12), 0 4px 16px 0 rgb(0 0 0 / 0.06)',
      },
      transitionDuration: {
        DEFAULT: '150ms',
        fast: '100ms',
        slow: '250ms',
      },
      transitionTimingFunction: {
        DEFAULT: 'cubic-bezier(0.4, 0, 0.2, 1)',
        'in-out': 'cubic-bezier(0.4, 0, 0.2, 1)',
      },
      fontSize: {
        '2xs': ['0.625rem', { lineHeight: '0.875rem' }],
      },
      spacing: {
        18: '4.5rem',
        22: '5.5rem',
      },
    },
  },
  plugins: [],
};

export default config;
