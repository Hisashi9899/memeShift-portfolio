'use client';
import { useEffect, useState, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/lib/auth';
import { api } from '@/lib/api';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { Loading } from '@/components/ui/Loading';
import {
  LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
} from 'recharts';

function toChartData(months: string[], data: number[]): { month: string; value: number }[] {
  return months.map((m, i) => ({ month: m, value: data[i] || 0 }));
}

function MetricChart({ label, unit, data, months, color = '#1976d2' }: {
  label: string; unit: string; data: number[]; months: string[]; color?: string;
}) {
  const chartData = toChartData(months, data);
  return (
    <div className="bg-white border border-surface-border rounded-xl p-4">
      <h4 className="text-sm font-semibold text-gray-800 mb-3">{label}（{unit}）</h4>
      <ResponsiveContainer width="100%" height={200}>
        <LineChart data={chartData}>
          <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
          <XAxis dataKey="month" tick={{ fontSize: 11 }} />
          <YAxis domain={[0, 100]} tick={{ fontSize: 11 }} />
          <Tooltip />
          <Line type="monotone" dataKey="value" stroke={color} strokeWidth={2} dot={{ r: 3 }} name={label} unit={unit} />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}

function SectionCard({ title, description, children }: { title: string; description: string; children: React.ReactNode }) {
  const [open, setOpen] = useState(true);
  return (
    <div className="bg-surface-card border border-surface-border rounded-xl shadow-card overflow-hidden">
      <button onClick={() => setOpen(!open)}
        className="w-full flex items-center justify-between px-5 py-4 bg-gray-50/50 border-b border-surface-border hover:bg-gray-50 transition-colors">
        <div className="text-left">
          <h3 className="text-sm font-semibold text-gray-900">{title}</h3>
          <p className="text-xs text-gray-500 mt-0.5">{description}</p>
        </div>
        <span className={'text-gray-400 transition-transform duration-200 ' + (open ? 'rotate-180' : '')}>&#x25BE;</span>
      </button>
      {open && <div className="p-5 space-y-4">{children}</div>}
    </div>
  );
}

export default function GrowthDashboardPage() {
  const { user, loading: authLoading, logout } = useAuth();
  const router = useRouter();
  const currentYear = new Date().getFullYear();
  const [year, setYear] = useState(currentYear);
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedStaffId, setSelectedStaffId] = useState<number | null>(null);

  const loadStats = useCallback(async () => {
    setLoading(true);
    setError('');
    setSelectedStaffId(null);
    try {
      const res = await api.getGrowthStats(year);
      setData(res);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [year]);

  useEffect(() => {
    if (!authLoading && !user) { router.push('/login'); return; }
    if (!authLoading && user) loadStats();
  }, [authLoading, user, router, loadStats]);

  if (authLoading) return <Loading />;
  if (!user) return null;

  function growthChart(key: string, color: string) {
    if (!data || !data.growth || !data.growth[key]) return null;
    const m = data.growth[key];
    const vals = selectedStaffId && m.per_staff?.[selectedStaffId]
      ? m.per_staff[selectedStaffId]
      : m.data;
    return (
      <MetricChart
        label={m.label}
        unit={m.unit}
        data={vals}
        months={data.months}
        color={color}
      />
    );
  }

  return (
    <div className="min-h-screen bg-surface">
      <PageHeader
        title="成長・適性ダッシュボード"
        subtitle={`${year}年の成長・適性データ`}
        backTo="/statistics"
        backLabel="統計"
        actions={
          <>
            <select
              value={year}
              onChange={e => setYear(Number(e.target.value))}
              className="text-sm border border-surface-border rounded-lg px-3 py-1.5 bg-white"
            >
              {[currentYear, currentYear - 1, currentYear - 2, currentYear - 3, currentYear - 4].map(y => (
                <option key={y} value={y}>{y}年</option>
              ))}
            </select>
            <Button variant="ghost" size="sm" onClick={() => router.push('/dashboard')}>勤務表</Button>
            <Button variant="ghost" size="sm" onClick={logout}>ログアウト</Button>
          </>
        }
      />

      <div className="max-w-7xl mx-auto px-6 py-6 space-y-6">
        {error && (
          <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">{error}</div>
        )}

        {loading && <Loading />}

        {data && !loading && (
          <>
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-semibold text-gray-700">スタッフフィルター</h2>
              <select
                value={selectedStaffId ?? ''}
                onChange={e => setSelectedStaffId(e.target.value ? Number(e.target.value) : null)}
                className="text-sm border border-surface-border rounded-lg px-3 py-1.5 bg-white"
              >
                <option value="">全スタッフ平均</option>
                {data.staff.map((s: any) => (
                  <option key={s.id} value={s.id}>{s.name}</option>
                ))}
              </select>
            </div>

            <SectionCard title="総合スコア" description="経験・適性・負担耐性の月別推移">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {growthChart('experience_score', '#9b59b6')}
                {growthChart('aptitude_score', '#3498db')}
                {growthChart('tolerance_score', '#2ecc71')}
              </div>
            </SectionCard>

            <SectionCard title="勤務種別適性" description="夜勤・早番・遅番それぞれの適性スコア">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {growthChart('night_aptitude', '#e74c3c')}
                {growthChart('early_aptitude', '#f39c12')}
                {growthChart('late_aptitude', '#8e44ad')}
              </div>
            </SectionCard>

            <SectionCard title="スコア詳細" description="各指標の月別データ">
              <div className="overflow-x-auto">
                <table className="w-full text-xs text-gray-700">
                  <thead>
                    <tr className="border-b border-surface-border">
                      <th className="text-left py-2 px-2 font-semibold">月</th>
                      <th className="text-center py-2 px-2 font-semibold">経験スコア</th>
                      <th className="text-center py-2 px-2 font-semibold">適性スコア</th>
                      <th className="text-center py-2 px-2 font-semibold">負担耐性</th>
                      <th className="text-center py-2 px-2 font-semibold">夜勤適性</th>
                      <th className="text-center py-2 px-2 font-semibold">早番適性</th>
                      <th className="text-center py-2 px-2 font-semibold">遅番適性</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.months.map((m: string, i: number) => {
                      const getVal = (key: string) => {
                        if (selectedStaffId && data.growth[key]?.per_staff?.[selectedStaffId]) {
                          return data.growth[key].per_staff[selectedStaffId][i] ?? '-';
                        }
                        return data.growth[key]?.data[i] ?? '-';
                      };
                      return (
                        <tr key={i} className="border-b border-surface-border/50 hover:bg-gray-50">
                          <td className="py-2 px-2 font-medium">{m}</td>
                          <td className="text-center py-2 px-2">{getVal('experience_score')}</td>
                          <td className="text-center py-2 px-2">{getVal('aptitude_score')}</td>
                          <td className="text-center py-2 px-2">{getVal('tolerance_score')}</td>
                          <td className="text-center py-2 px-2">{getVal('night_aptitude')}</td>
                          <td className="text-center py-2 px-2">{getVal('early_aptitude')}</td>
                          <td className="text-center py-2 px-2">{getVal('late_aptitude')}</td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </SectionCard>

            {data.growth?.newcomer_night_debut?.data?.length > 0 && (
              <SectionCard title="新人夜勤デビュー" description="新規入職者で夜勤を開始した月">
                <div className="text-sm text-gray-600 space-y-1">
                  {data.growth.newcomer_night_debut.data.map((d: any, i: number) => (
                    <div key={i} className="flex gap-2">
                      <span className="font-medium text-gray-800">{d.name}</span>
                      <span>{d.month}（{d.night_count}回の夜勤）</span>
                    </div>
                  ))}
                </div>
              </SectionCard>
            )}
          </>
        )}
      </div>
    </div>
  );
}
