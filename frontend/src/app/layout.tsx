import type { Metadata } from 'next';
import './globals.css';
import { AuthProvider } from '@/lib/auth';
import { ToastProvider } from '@/components/ui/Toast';

const BASE = process.env.NEXT_PUBLIC_BASE_PATH || '';

const DESCRIPTION =
  'Constraint-aware shift scheduling for healthcare professionals. Deterministic rosters, fairness scoring, and explainable failures. Fastest recorded MVP in 2 weeks; standard MVP 4–6 weeks; production + CI/CD 10–14 weeks.';

const SITE_URL = 'https://hisashi9899.github.io/memeShift-portfolio/';
const AUTHOR_URL = 'https://contra.com/neutron_d0wbklm2/work';
const GITHUB_URL = 'https://github.com/Hisashi9899';

const STRUCTURED_DATA = {
  '@context': 'https://schema.org',
  '@graph': [
    {
      '@type': 'Person',
      '@id': `${AUTHOR_URL}#person`,
      name: 'Hisashi Aoki',
      jobTitle: 'Full-Spectrum Product Engineer',
      url: AUTHOR_URL,
      sameAs: [GITHUB_URL, AUTHOR_URL],
      knowsAbout: [
        'Full-stack product engineering',
        'Next.js',
        'TypeScript',
        'PostgreSQL',
        'CI/CD',
        'Healthcare software',
        'Constraint scheduling',
      ],
    },
    {
      '@type': 'SoftwareApplication',
      name: 'memeShift',
      applicationCategory: 'BusinessApplication',
      operatingSystem: 'Web',
      url: SITE_URL,
      description: DESCRIPTION,
      author: { '@id': `${AUTHOR_URL}#person` },
      offers: { '@type': 'Offer', price: '0', priceCurrency: 'USD' },
    },
  ],
};

export const metadata: Metadata = {
  metadataBase: new URL('https://hisashi9899.github.io'),
  title: 'memeShift — Constraint-aware shift scheduling',
  description: DESCRIPTION,
  keywords: [
    'shift scheduling software',
    'healthcare rostering',
    'constraint scheduling',
    'nurse shift management',
    'Next.js portfolio',
    'product engineering case study',
  ],
  authors: [{ name: 'Hisashi Aoki', url: AUTHOR_URL }],
  creator: 'Hisashi Aoki',
  alternates: { canonical: SITE_URL },
  robots: { index: true, follow: true },
  openGraph: {
    title: 'memeShift — Constraint-aware shift scheduling',
    description: DESCRIPTION,
    url: 'https://hisashi9899.github.io/memeShift-portfolio/',
    siteName: 'memeShift',
    images: [
      {
        url: `${BASE}/og-image.png`,
        width: 1200,
        height: 630,
        alt: 'memeShift — constraint-aware shift scheduling',
      },
    ],
    type: 'website',
    locale: 'en_US',
  },
  twitter: {
    card: 'summary_large_image',
    title: 'memeShift — Constraint-aware shift scheduling',
    description: DESCRIPTION,
    images: [`${BASE}/og-image.png`],
  },
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body className="min-h-screen bg-surface text-gray-900 antialiased">
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(STRUCTURED_DATA) }}
        />
        <AuthProvider>
          {children}
          <ToastProvider />
        </AuthProvider>
      </body>
    </html>
  );
}
