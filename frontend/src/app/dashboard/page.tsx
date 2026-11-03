'use client';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/lib/auth';
import { ScheduleBoard } from '@/components/schedule/ScheduleBoard';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { Loading } from '@/components/ui/Loading';

export default function DashboardPage() {
  const { user, loading, logout } = useAuth();
  const router = useRouter();
  const now = new Date();
  const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  const [monthDisplay, setMonthDisplay] = useState(month);

  useEffect(() => {
    if (!loading && !user) router.push('/login');
  }, [user, loading, router]);

  const navigateMonth = (delta: number) => {
    const [y, m] = monthDisplay.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    setMonthDisplay(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
  };

  if (loading) return <Loading />;
  if (!user) return null;

  return (
    <div className="min-h-screen bg-surface">
      <PageHeader
        title="MeMeシフト"
        subtitle="勤務表自動作成アプリケーション"
        actions={
          <>
            <Button variant="ghost" size="sm" onClick={() => router.push('/settings')}>設定</Button>
            <Button variant="ghost" size="sm" onClick={() => router.push('/preferences')}>希望入力</Button>
            <Button variant="ghost" size="sm" onClick={() => router.push('/statistics')}>統計</Button>
            <Button variant="ghost" size="sm" onClick={logout}>ログアウト</Button>
          </>
        }
      />
      <main className="max-w-7xl mx-auto px-6 py-6">
        {/* Month navigation */}
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <Button variant="ghost" size="sm" onClick={() => navigateMonth(-1)}>&larr;</Button>
            <h2 className="text-lg font-semibold text-gray-900 tracking-tight">
              {monthDisplay}
            </h2>
            <Button variant="ghost" size="sm" onClick={() => navigateMonth(1)}>&rarr;</Button>
          </div>
        </div>
        <ScheduleBoard month={monthDisplay} />
      </main>
    </div>
  );
}
