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
      <h4 className="text-sm font-semibold text-gray-800 mb-3">{label} ({unit})</h4>
      <ResponsiveContainer width="100%" height={200}>
        <LineChart data={chartData}>
          <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
          <XAxis dataKey="month" tick={{ fontSize: 11 }} />
          <YAxis tick={{ fontSize: 11 }} />
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

export default function StatisticsPage() {
  const { user, loading: authLoading, logout } = useAuth();
  const router = useRouter();
  const now = new Date();
  const [year, setYear] = useState(now.getFullYear());
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedStaffId, setSelectedStaffId] = useState<number | null>(null);

  useEffect(() => {
    if (!authLoading && !user) router.push('/login');
  }, [user, authLoading, router]);

  const loadStats = useCallback(async () => {
    setLoading(true);
    setError('');
    setSelectedStaffId(null);
    try {
      const res = await api.getYearlyStats(year);
      setData(res);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [year]);

  useEffect(() => {
    if (user) loadStats();
  }, [user, loadStats]);

  if (authLoading) return <Loading />;
  if (!user) return null;

  const metricChart = (category: string, key: string, color?: string) => {
    if (!data) return null;
    const metric = data[category]?.[key];
    if (!metric) return null;
    const values = selectedStaffId && metric.per_staff?.[selectedStaffId]
      ? metric.per_staff[selectedStaffId]
      : metric.data;
    return (
      <MetricChart
        label={metric.label}
        unit={metric.unit}
        data={values}
        months={data.months}
        color={color}
      />
    );
  };

  if (loading) return <Loading />;

  return (
    <div className="min-h-screen bg-surface">
      <PageHeader
        title="統計ダッシュボード"
        subtitle="年間推移の分析"
        backTo="/dashboard"
        actions={
          <>
            <Button variant="ghost" size="sm" onClick={() => router.push('/growth')}>成長分析</Button>
            <Button variant="ghost" size="sm" onClick={() => router.push('/dashboard')}>勤務表</Button>
            <Button variant="ghost" size="sm" onClick={logout}>ログアウト</Button>
          </>
        }
      />
      <main className="max-w-7xl mx-auto px-6 py-6 space-y-6">
        <div className="flex items-center gap-3 flex-wrap">
          <Button variant="ghost" size="sm" onClick={() => setYear(y => y - 1)}>&larr;</Button>
          <h2 className="text-lg font-semibold text-gray-900">{year}年</h2>
          <Button variant="ghost" size="sm" onClick={() => setYear(y => y + 1)}>&rarr;</Button>
          <select
            value={selectedStaffId ?? ''}
            onChange={e => setSelectedStaffId(e.target.value ? Number(e.target.value) : null)}
            className="border border-surface-border rounded-lg px-3 py-1.5 text-sm bg-white"
          >
            <option value="">全体表示</option>
            {data?.staff?.map((s: any) => (
              <option key={s.id} value={s.id}>{s.name}</option>
            ))}
          </select>
          <Button variant="primary" size="sm" onClick={loadStats} disabled={loading}>
            {loading ? '読込中...' : '再読込'}
          </Button>
        </div>

        {error && (
          <div className="p-4 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-800">{error}</div>
        )}

        {data && (
          <div className="space-y-6">
            <SectionCard title="&#x2460; 負担の可視化" description="身体的・精神的負荷の年間推移">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {metricChart('burden', 'night_shift_by_month', '#e74c3c')}
                {metricChart('burden', 'night_to_ake_next_day', '#e67e22')}
                {metricChart('burden', 'avg_consecutive_days', '#f39c12')}
                {metricChart('burden', 'early_late_imbalance', '#8e44ad')}
                {metricChart('burden', 'avg_holiday_days', '#2ecc71')}
              </div>
            </SectionCard>

            <SectionCard title="&#x2461; 公平性の可視化" description="不満・離職リスクの予兆">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {metricChart('fairness', 'shift_type_stddev', '#3498db')}
                {metricChart('fairness', 'night_stddev', '#2980b9')}
                {metricChart('fairness', 'preference_achievement_rate', '#27ae60')}
                {metricChart('fairness', 'change_request_count', '#e74c3c')}
              </div>
            </SectionCard>

            <SectionCard title="&#x2462; 安全性の可視化" description="事故防止のための指標">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {metricChart('safety', 'danger_pattern_count', '#c0392b')}
                {metricChart('safety', 'night_consecutive_max_avg', '#d35400')}
                {metricChart('safety', 'short_interval_count', '#e67e22')}
                {metricChart('safety', 'newcomer_night_ratio', '#f1c40f')}
              </div>
            </SectionCard>

            <SectionCard title="&#x2463; 成長・適性の可視化" description="経験・適性・負担耐性の年間推移と勤務種別の適性">
              <h4 className="text-xs font-semibold text-gray-500 mb-3">総合スコア推移</h4>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {metricChart('growth', 'experience_score', '#9b59b6')}
                {metricChart('growth', 'aptitude_score', '#3498db')}
                {metricChart('growth', 'tolerance_score', '#2ecc71')}
              </div>
              <div className="mt-6">
                <h4 className="text-xs font-semibold text-gray-500 mb-3">勤務種別適性</h4>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  {metricChart('growth', 'night_aptitude', '#e74c3c')}
                  {metricChart('growth', 'early_aptitude', '#f39c12')}
                  {metricChart('growth', 'late_aptitude', '#8e44ad')}
                </div>
              </div>
              {data.growth?.newcomer_night_debut?.data?.length > 0 && (
                <div className="mt-6 bg-white border border-surface-border rounded-xl p-4">
                  <h4 className="text-sm font-semibold text-gray-800 mb-3">新人の夜勤デビュー</h4>
                  <div className="text-sm text-gray-600 space-y-1">
                    {data.growth.newcomer_night_debut.data.map((d: any, i: number) => (
                      <div key={i} className="flex gap-2">
                        <span className="font-medium text-gray-800">{d.name}</span>
                        <span>{d.month}（{d.night_count}回の夜勤）</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </SectionCard>

            <SectionCard title="職員別 月別データ" description="各職員の月毎の勤務内訳（早番/遅番/日勤/夜勤/休み/計）">
              <div className="overflow-x-auto">
                <table className="w-full text-xs">
                  <thead>
                    <tr className="border-b border-surface-border">
                      <th className="text-left py-2 pr-3 font-medium text-gray-500">職員</th>
                      <th className="text-left py-2 pr-2 font-medium text-gray-500">区分</th>
                      {data.months.map((m: string) => (
                        <th key={m} className="text-center py-2 px-1 font-medium text-gray-500">{m}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {data.per_staff_monthly
                      .filter((ps: any) => !selectedStaffId || ps.staff_id === selectedStaffId)
                      .map((ps: any) => {
                        const subRows = [
                          { key: 'early', label: '早番', cls: 'text-blue-600' },
                          { key: 'late', label: '遅番', cls: 'text-purple-600' },
                          { key: 'day', label: '日勤', cls: 'text-green-600' },
                          { key: 'night', label: '夜勤', cls: 'text-red-600' },
                          { key: 'off', label: '休み', cls: 'text-pink-500' },
                          { key: 'total', label: '計', cls: 'font-semibold text-gray-800' },
                        ];
                        return subRows.map((sr, ri) => (
                          <tr key={`${ps.staff_id}-${ri}`} className="border-b border-surface-border/20">
                            {ri === 0 && (
                              <td className="py-1 pr-3 font-medium text-gray-700 align-top" rowSpan={subRows.length}>
                                {ps.name}
                              </td>
                            )}
                            <td className={`py-1 pr-2 text-[10px] ${sr.cls}`}>{sr.label}</td>
                            {data.months.map((m: string, i: number) => {
                              const hasData = ps.monthly.total[i] > 0;
                              let val: string;
                              if (!hasData) {
                                val = '-';
                              } else if (sr.key === 'off') {
                                val = String(new Date(year, i + 1, 0).getDate() - ps.monthly.total[i]);
                              } else {
                                val = String(ps.monthly[sr.key]?.[i] || 0);
                              }
                              return (
                                <td key={m} className={`text-center py-1 px-1 text-[11px] ${sr.key === 'total' ? 'font-semibold text-gray-800' : 'text-gray-600'}`}>
                                  {val}
                                </td>
                              );
                            })}
                          </tr>
                        ));
                      })}
                  </tbody>
                </table>
              </div>
            </SectionCard>
          </div>
        )}
      </main>
    </div>
  );
}
