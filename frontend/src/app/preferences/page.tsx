'use client';
import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/lib/auth';
import { Loading } from '@/components/ui/Loading';
import { PageHeader } from '@/components/layout/PageHeader';
import { PreferenceEditor } from '@/components/preferences/PreferenceEditor';

export default function PreferencesPage() {
  const { user, loading: authLoading } = useAuth();
  const router = useRouter();
  const now = new Date();
  const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

  useEffect(() => {
    if (!authLoading && !user) router.push('/login');
  }, [user, authLoading, router]);

  if (authLoading) return <Loading />;
  if (!user) return null;

  return (
    <div className="min-h-screen bg-surface">
      <PageHeader
        title="希望入力"
        subtitle={`対象月: ${month}`}
        backTo="/dashboard"
        backLabel="勤務表"
      />
      <main className="max-w-3xl mx-auto px-6 py-8">
        <PreferenceEditor month={month} />
      </main>
    </div>
  );
}
