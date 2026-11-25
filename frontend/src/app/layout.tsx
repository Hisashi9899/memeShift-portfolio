import type { Metadata } from 'next';
import './globals.css';
import { AuthProvider } from '@/lib/auth';
import { ToastProvider } from '@/components/ui/Toast';

const BASE = process.env.NEXT_PUBLIC_BASE_PATH || '';

const DESCRIPTION =
  'Constraint-aware shift scheduling for healthcare professionals. Deterministic rosters, fairness scoring, and explainable failures. Fastest recorded MVP in 2 weeks; standard MVP 4–6 weeks; production + CI/CD 10–14 weeks.';

export const metadata: Metadata = {
  metadataBase: new URL('https://hisashi9899.github.io'),
  title: 'memeShift — Constraint-aware shift scheduling',
  description: DESCRIPTION,
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
    <html lang="ja">
      <body className="min-h-screen bg-surface text-gray-900 antialiased">
        <AuthProvider>
          {children}
          <ToastProvider />
        </AuthProvider>
      </body>
    </html>
  );
}
