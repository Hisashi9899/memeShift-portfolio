const BASE = process.env.NEXT_PUBLIC_BASE_PATH || '';
const REPO = 'https://github.com/Hisashi9899/memeShift-portfolio';

function shot(name: string) {
  return `${BASE}/screenshots/${name}`;
}

const metrics = [
  { value: '43', label: 'REST endpoints' },
  { value: '14', label: 'domain services' },
  { value: '4,100+', label: 'lines of app code' },
  { value: '37 KB', label: 'scheduling engine' },
  { value: '15', label: 'DB migrations' },
  { value: '2 wk', label: 'fastest MVP' },
];

const hardParts = [
  {
    title: 'Night chains are not independent choices',
    body: 'A night shift reserves the next day as post-night (明け) and the day after as rest. Consecutive-day limits, night capability, and compatibility rules interact, so a local decision can invalidate a whole week.',
  },
  {
    title: 'Fairness has to be measured, not assumed',
    body: 'Night counts, early/late balance, holiday budgets, and preference achievement are calculated across the year. The generator uses those metrics to balance the roster instead of filling cells greedily.',
  },
  {
    title: 'Failures need an explanation',
    body: 'When a roster cannot be completed, the system names the date, shift type, required vs. assigned headcount, and the likely cause: night capability, consecutive limits, preference concentration, or a compatibility rule.',
  },
  {
    title: 'Repair must not silently rewrite the roster',
    body: 'Local repair preserves fixed cells. Break-route search ranks relaxations by candidate gain and penalty, and applies a route only after an explicit confirmation. A published roster stays under human control.',
  },
];

const steps = [
  {
    n: '01',
    title: 'Open the demo',
    body: 'Any credentials work. The demo runs entirely in the browser with synthetic Staff A–J data.',
    href: `${BASE}/login/`,
    cta: 'Open demo',
  },
  {
    n: '02',
    title: 'Generate a roster',
    body: 'Click “勤務自動作成” and watch the conflict panel explain the two deliberate shortfalls.',
    href: `${BASE}/login/`,
    cta: 'Try generation',
  },
  {
    n: '03',
    title: 'Inspect the analytics',
    body: 'The statistics screen shows workload, fairness, safety, and growth metrics across the year.',
    href: `${BASE}/statistics/`,
    cta: 'Open analytics',
  },
  {
    n: '04',
    title: 'Review preferences',
    body: 'See how day-off requests, night-only constraints, and fixed shifts are captured per staff member.',
    href: `${BASE}/preferences/`,
    cta: 'Open preferences',
  },
];

const scope = [
  ['Frontend', 'Next.js 14 App Router, 6 screens, 23 components, TypeScript + Tailwind'],
  ['Backend', 'Laravel 10, 43 REST endpoints, 14 domain services, Sanctum auth'],
  ['Engine', 'Standalone PHP scheduling engine (37 KB), deterministic, zero Laravel dependencies'],
  ['Data', '15 migrations, MySQL 8, requirements, preferences, compatibility rules, overrides'],
  ['Infra', 'Docker Compose, Nginx, GitHub Actions, static GitHub Pages demo'],
  ['Quality', 'PHP lint, backend PHPUnit, engine PHPUnit, TypeScript, Next build, gitleaks'],
  ['Timeline', 'Fastest recorded: MVP 2 weeks; production 1 month; CI/CD 1.5 months. Standard: MVP 4–6 weeks; production + CI/CD 10–14 weeks.'],
];

const stack = [
  'PHP 8.2', 'Laravel 10', 'Next.js 14', 'TypeScript', 'React 18',
  'Tailwind CSS', 'MySQL 8', 'Nginx', 'Docker', 'GitHub Actions',
];

function Arrow() {
  return <span aria-hidden="true">→</span>;
}

export function CaseStudy() {
  return (
    <div className="min-h-screen bg-[#0b1220] text-white">
      <header className="max-w-6xl mx-auto px-6 py-5 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <span className="text-lg" aria-hidden="true">🐾</span>
          <span className="font-semibold tracking-tight">memeShift</span>
          <span className="hidden sm:inline text-[10px] uppercase tracking-[0.2em] text-slate-500">
            Portfolio
          </span>
        </div>
        <nav className="flex items-center gap-3 text-sm text-slate-300">
          <a className="hidden sm:inline hover:text-white transition-colors" href={REPO}>GitHub</a>
          <a className="hidden sm:inline hover:text-white transition-colors" href={`${REPO}/blob/main/README.md`}>README</a>
          <a
            className="rounded-lg bg-white/10 px-3 py-1.5 font-medium text-white hover:bg-white/15 transition-colors"
            href={`${BASE}/login/`}
          >
            Open demo
          </a>
        </nav>
      </header>

      <main>
        <section className="max-w-6xl mx-auto px-6 pt-10 pb-16 grid lg:grid-cols-[1.05fr_1fr] gap-10 items-center">
          <div>
            <div className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-[11px] uppercase tracking-[0.16em] text-slate-300">
              <span className="h-1.5 w-1.5 rounded-full bg-emerald-400" />
              Solo build · MVP 2 weeks · standard 4–6 weeks
            </div>
            <h1 className="mt-5 text-4xl md:text-5xl font-bold leading-[1.08] tracking-tight">
              Shift scheduling<br />
              that can <span className="text-primary-300">explain itself.</span>
            </h1>
            <p className="mt-5 max-w-xl text-lg leading-relaxed text-slate-300">
              memeShift generates monthly rosters for healthcare professionals and care facilities under hard constraints,
              measures fairness across the year, and tells you exactly why a roster cannot be
              completed. When it can be repaired, it shows the route before changing anything.
            </p>
            <div className="mt-7 flex flex-wrap items-center gap-3">
              <a
                className="inline-flex items-center gap-2 rounded-xl bg-primary-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-primary-900/30 hover:bg-primary-400 transition-colors"
                href={`${BASE}/login/`}
              >
                Open interactive demo <Arrow />
              </a>
              <a
                className="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/5 px-5 py-3 text-sm font-semibold text-white hover:bg-white/10 transition-colors"
                href={REPO}
              >
                View source
              </a>
            </div>
            <p className="mt-5 text-xs text-slate-400">
              Synthetic data only · No account, API, or database required · Evaluation license
            </p>
          </div>

          <div className="relative">
            <div className="absolute -inset-6 rounded-full bg-primary-500/20 blur-3xl" aria-hidden="true" />
            <div className="relative overflow-hidden rounded-2xl border border-white/10 bg-white shadow-2xl">
              <img
                src={shot('dashboard.webp')}
                alt="memeShift monthly roster with synthetic Staff A-J data"
                className="w-full"
              />
            </div>
            <div className="relative mt-3 flex items-center justify-between text-[11px] text-slate-400">
              <span>Monthly roster · 10 synthetic staff · 30 days</span>
              <span className="font-mono">2026-11</span>
            </div>
          </div>
        </section>

        <section className="border-y border-white/10 bg-white/[0.03]">
          <div className="max-w-6xl mx-auto px-6 py-6 grid grid-cols-2 md:grid-cols-6 gap-4">
            {metrics.map((m) => (
              <div key={m.label}>
                <div className="text-2xl font-bold tracking-tight">{m.value}</div>
                <div className="mt-0.5 text-[11px] uppercase tracking-[0.12em] text-slate-400">{m.label}</div>
              </div>
            ))}
          </div>
        </section>

        <section className="max-w-6xl mx-auto px-6 py-16">
          <div className="max-w-3xl">
            <p className="text-[11px] uppercase tracking-[0.18em] text-primary-300">Why this is unusual</p>
            <h2 className="mt-2 text-3xl font-bold tracking-tight">The hard part is not the CRUD.</h2>
            <p className="mt-3 text-slate-300 leading-relaxed">
              A scheduling product is judged by what happens when the constraints collide. These are
              the parts that take real domain modelling, not another form builder.
            </p>
          </div>
          <div className="mt-8 grid md:grid-cols-2 gap-4">
            {hardParts.map((item, i) => (
              <div key={item.title} className="rounded-2xl border border-white/10 bg-white/[0.04] p-5">
                <div className="flex items-center gap-2">
                  <span className="flex h-6 w-6 items-center justify-center rounded-full bg-primary-500/20 text-[11px] font-bold text-primary-200">
                    {i + 1}
                  </span>
                  <h3 className="text-sm font-semibold text-white">{item.title}</h3>
                </div>
                <p className="mt-2 text-sm leading-relaxed text-slate-300">{item.body}</p>
              </div>
            ))}
          </div>
        </section>

        <section className="bg-surface text-slate-900">
          <div className="max-w-6xl mx-auto px-6 py-16">
            <div className="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
              <div className="max-w-2xl">
                <p className="text-[11px] uppercase tracking-[0.18em] text-primary-600">Two-minute walkthrough</p>
                <h2 className="mt-2 text-3xl font-bold tracking-tight">See the behaviour, not just the screens.</h2>
                <p className="mt-3 text-slate-600 leading-relaxed">
                  The demo is a static build with deterministic data. Everything below works without
                  a backend, account, or API key. The roster table is best viewed on a desktop browser;
                  the landing page and login are responsive.
                </p>
              </div>
              <a
                className="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-5 py-3 text-sm font-semibold text-white hover:bg-primary-700 transition-colors"
                href={`${BASE}/login/`}
              >
                Launch the demo <Arrow />
              </a>
            </div>
            <div className="mt-8 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
              {steps.map((s) => (
                <a
                  key={s.n}
                  href={s.href}
                  className="group rounded-2xl border border-surface-border bg-white p-5 shadow-card hover:shadow-elevated hover:-translate-y-0.5 transition-all"
                >
                  <div className="text-[11px] font-mono text-primary-600">{s.n}</div>
                  <h3 className="mt-2 text-sm font-semibold text-slate-900">{s.title}</h3>
                  <p className="mt-1.5 text-xs leading-relaxed text-slate-600">{s.body}</p>
                  <div className="mt-3 text-xs font-medium text-primary-700 group-hover:text-primary-800">
                    {s.cta} <Arrow />
                  </div>
                </a>
              ))}
            </div>

            <div className="mt-10 grid lg:grid-cols-[1.1fr_1fr] gap-6 items-start">
              <div className="overflow-hidden rounded-2xl border border-surface-border bg-white shadow-card">
                <img
                  src={shot('conflicts.webp')}
                  alt="Conflict explanation after roster generation"
                  className="w-full"
                />
              </div>
              <div className="space-y-4">
                <div className="rounded-2xl border border-surface-border bg-white p-5 shadow-card">
                  <h3 className="text-sm font-semibold text-slate-900">What the conflict panel tells you</h3>
                  <ul className="mt-2 space-y-1.5 text-xs leading-relaxed text-slate-600">
                    <li>• The exact date and shift type that is short</li>
                    <li>• Required vs. assigned headcount</li>
                    <li>• The likely cause: night capability, consecutive limits, or preference concentration</li>
                    <li>• A concrete next action, including which screen to open</li>
                  </ul>
                </div>
                <div className="rounded-2xl border border-surface-border bg-white p-5 shadow-card">
                  <h3 className="text-sm font-semibold text-slate-900">What it deliberately does not do</h3>
                  <p className="mt-2 text-xs leading-relaxed text-slate-600">
                    It does not silently rewrite a published roster. Repair routes are ranked and
                    shown first; applying one is an explicit human decision.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="max-w-6xl mx-auto px-6 py-16">
          <div className="grid lg:grid-cols-[0.9fr_1.1fr] gap-10">
            <div>
              <p className="text-[11px] uppercase tracking-[0.18em] text-primary-300">Built solo, end to end</p>
              <h2 className="mt-2 text-3xl font-bold tracking-tight">One engineer. One coherent system.</h2>
              <p className="mt-3 text-slate-300 leading-relaxed">
                The project covers the whole path: domain model, scheduling engine, API, front end,
                Docker, CI, and a safe public demo. The same person designed the constraints, wrote
                the engine, and shipped the interface.
              </p>
              <div className="mt-6 grid grid-cols-2 gap-3">
                {[
                  ['2 wk', 'fastest MVP (memeShift)'],
                  ['4–6 wk', 'standard MVP estimate'],
                  ['10–14 wk', 'production + CI/CD'],
                  ['1', 'engineer across the full stack'],
                  ['0', 'real staff or facility records in this repo'],
                  ['6', 'CI checks on every pull request'],
                ].map(([v, l]) => (
                  <div key={l} className="rounded-xl border border-white/10 bg-white/[0.04] p-4">
                    <div className="text-2xl font-bold">{v}</div>
                    <div className="mt-1 text-[11px] leading-snug text-slate-400">{l}</div>
                  </div>
                ))}
              </div>
              <p className="mt-4 text-[11px] leading-relaxed text-slate-400">
                Fastest recorded: MVP 2 weeks, production 1 month, CI/CD 1.5 months.
                Standard estimate: MVP 4–6 weeks, production + CI/CD 10–14 weeks.
              </p>
            </div>
            <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-5">
              <h3 className="text-sm font-semibold">Scope delivered</h3>
              <dl className="mt-4 divide-y divide-white/10">
                {scope.map(([k, v]) => (
                  <div key={k} className="grid grid-cols-[92px_1fr] gap-3 py-3">
                    <dt className="text-[11px] uppercase tracking-[0.12em] text-slate-400">{k}</dt>
                    <dd className="text-sm leading-relaxed text-slate-200">{v}</dd>
                  </div>
                ))}
              </dl>
            </div>
          </div>
        </section>

        <section className="border-y border-white/10 bg-white/[0.03]">
          <div className="max-w-6xl mx-auto px-6 py-14">
            <div className="grid lg:grid-cols-[1fr_1.1fr] gap-10 items-center">
              <div>
                <p className="text-[11px] uppercase tracking-[0.18em] text-primary-300">Architecture</p>
                <h2 className="mt-2 text-2xl font-bold tracking-tight">The engine is separate on purpose.</h2>
                <p className="mt-3 text-sm leading-relaxed text-slate-300">
                  The PHP scheduling engine has no Laravel dependency. It can run as a library, a CLI,
                  or inside the API. Shared DTOs keep the boundary typed, and the static demo reuses the
                  same UI with a client-side data adapter.
                </p>
                <div className="mt-5 flex flex-wrap gap-2">
                  {stack.map((s) => (
                    <span key={s} className="rounded-lg border border-white/10 bg-white/5 px-2.5 py-1 text-[11px] text-slate-300">
                      {s}
                    </span>
                  ))}
                </div>
              </div>
              <div className="rounded-2xl border border-white/10 bg-[#0e1729] p-5 font-mono text-[11px] text-slate-300">
                <div className="grid grid-cols-3 gap-2 text-center">
                  <div className="rounded-lg border border-white/10 bg-white/5 p-3">Browser</div>
                  <div className="rounded-lg border border-white/10 bg-white/5 p-3">Nginx</div>
                  <div className="rounded-lg border border-white/10 bg-white/5 p-3">Next.js 14</div>
                  <div className="rounded-lg border border-primary-400/30 bg-primary-500/10 p-3 text-primary-200">Laravel 10</div>
                  <div className="rounded-lg border border-white/10 bg-white/5 p-3">MySQL 8</div>
                  <div className="rounded-lg border border-emerald-400/30 bg-emerald-400/10 p-3 text-emerald-200">PHP Engine</div>
                </div>
                <div className="mt-3 text-center text-slate-500">
                  Next.js ── HTTP ──▶ Laravel ──▶ Engine / MySQL
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="max-w-6xl mx-auto px-6 py-16">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
              <p className="text-[11px] uppercase tracking-[0.18em] text-primary-300">For reviewers</p>
              <h2 className="mt-2 text-3xl font-bold tracking-tight">Start with the evidence.</h2>
              <p className="mt-2 max-w-xl text-sm leading-relaxed text-slate-300">
                The README covers architecture, setup, the static demo, security, and the license.
                The pull request and CI history show how the portfolio itself was delivered.
              </p>
            </div>
            <div className="flex flex-wrap gap-3">
              <a className="rounded-xl border border-white/15 bg-white/5 px-4 py-2.5 text-sm font-medium hover:bg-white/10 transition-colors" href={`${REPO}/blob/main/README.md`}>
                Read the README
              </a>
              <a className="rounded-xl border border-white/15 bg-white/5 px-4 py-2.5 text-sm font-medium hover:bg-white/10 transition-colors" href={`${REPO}/actions`}>
                CI history
              </a>
              <a className="rounded-xl border border-white/15 bg-white/5 px-4 py-2.5 text-sm font-medium hover:bg-white/10 transition-colors" href={`${REPO}/blob/main/LICENSE`}>
                License
              </a>
            </div>
          </div>
        </section>
      </main>

      <footer className="border-t border-white/10">
        <div className="max-w-6xl mx-auto px-6 py-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-[11px] text-slate-500">
          <span>memeShift · portfolio snapshot · synthetic data only</span>
          <span>Evaluation license · no real staff, facility, or customer data</span>
        </div>
      </footer>
    </div>
  );
}
