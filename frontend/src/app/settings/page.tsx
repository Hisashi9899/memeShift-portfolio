'use client';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/lib/auth';
import { Button } from '@/components/ui/Button';
import { Card, CardSection } from '@/components/ui/Card';
import { Loading } from '@/components/ui/Loading';
import { PageHeader } from '@/components/layout/PageHeader';
import { StaffEditor } from '@/components/staff/StaffEditor';
import { ShiftTypeEditor } from '@/components/shifts/ShiftTypeEditor';
import { RequirementEditor } from '@/components/shifts/RequirementEditor';
import { DailyOverrideEditor } from '@/components/shifts/DailyOverrideEditor';
import { CompatibilityRuleEditor } from '@/components/rules/CompatibilityRuleEditor';
import { api } from '@/lib/api';
import toast from 'react-hot-toast';

export default function SettingsPage() {
  const { user, loading: authLoading } = useAuth();
  const router = useRouter();
  const now = new Date();
  const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  const [holidayCount, setHolidayCount] = useState(0);
  const [savingHoliday, setSavingHoliday] = useState(false);
  const [staffVersion, setStaffVersion] = useState(0);

  useEffect(() => {
    if (!authLoading && !user) router.push('/login');
  }, [user, authLoading, router]);

  useEffect(() => {
    if (!user) return;
    api.getHolidayCount(month).then(r => setHolidayCount(r.count)).catch(() => toast.error('公休日数の取得に失敗しました'));
  }, [user, month]);

  const saveHoliday = async () => {
    setSavingHoliday(true);
    try {
      await api.saveHolidayCount(month, holidayCount);
      toast.success('保存しました');
    } catch (e: any) { toast.error(e.message); }
    finally { setSavingHoliday(false); }
  };

  if (authLoading) return <Loading />;
  if (!user) return null;

  return (
    <div className="min-h-screen bg-surface">
      <PageHeader
        title="設定"
        backTo="/dashboard"
        backLabel="勤務表"
      />
      <main className="max-w-3xl mx-auto px-6 py-8 space-y-6">
        {/* Public holiday count */}
        <Card padding="md">
          <CardSection label="月の公休日数" description="今月の公休日（休日）の総数を設定します。この数をもとに自動作成が各職員の勤務日数を調整します。">
            <div className="flex items-center gap-3">
              <label className="text-xs text-gray-500 font-medium">公休日数</label>
              <input
                type="number"
                value={holidayCount}
                onChange={e => setHolidayCount(parseInt(e.target.value) || 0)}
                className="w-20 px-2.5 py-1.5 text-sm border border-surface-border rounded-md bg-white text-gray-900 text-center focus:outline-none focus:ring-2 focus:ring-primary-400/40 transition-all duration-150"
                min={0}
                max={15}
              />
              <span className="text-xs text-gray-400">日/月</span>
              <Button variant="primary" size="sm" onClick={saveHoliday} disabled={savingHoliday}>保存</Button>
            </div>
          </CardSection>
        </Card>

        {/* Shift types */}
        <Card padding="md">
          <CardSection label="勤務種別" description="勤務名・時間帯・色を編集・追加できます。">
            <ShiftTypeEditor />
          </CardSection>
        </Card>

        {/* Requirements */}
        <Card padding="md">
          <CardSection label="必要人数" description="各勤務種別に必要な人数を設定します。">
            <RequirementEditor />
          </CardSection>
        </Card>

        {/* Daily overrides */}
        <Card padding="md">
          <CardSection label="日別の必要人数" description="特定の日付に必要な人数を上書き設定します。">
            <DailyOverrideEditor month={month} />
          </CardSection>
        </Card>

        {/* Staff */}
        <Card padding="md">
          <CardSection label="職員一覧" description="職員の氏名・雇用区分・夜勤可否・最大連勤日数を編集できます。">
            <StaffEditor month={month} onSave={() => setStaffVersion(v => v + 1)} />
          </CardSection>
        </Card>

        {/* Compatibility rules */}
        <Card padding="md">
          <CardSection label="相性ルール" description="職員間の相性ルールを設定します。">
            <CompatibilityRuleEditor key={staffVersion} />
          </CardSection>
        </Card>
      </main>
    </div>
  );
}
