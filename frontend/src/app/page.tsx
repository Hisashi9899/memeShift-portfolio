'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { DEMO_MODE } from '@/lib/demo';
import { CaseStudy } from '@/components/marketing/CaseStudy';

export default function Home() {
  const router = useRouter();

  useEffect(() => {
    if (!DEMO_MODE) router.replace('/login');
  }, [router]);

  if (DEMO_MODE) return <CaseStudy />;

  return (
    <div className="min-h-screen flex items-center justify-center text-sm text-gray-500">
      Loading…
    </div>
  );
}
