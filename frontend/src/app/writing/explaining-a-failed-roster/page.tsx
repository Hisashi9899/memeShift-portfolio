import type { Metadata } from 'next';

const SITE_URL = 'https://hisashi9899.github.io/memeShift-portfolio/';
const ARTICLE_URL = `${SITE_URL}writing/explaining-a-failed-roster/`;
const AUTHOR_URL = 'https://contra.com/neutron_d0wbklm2/work';

const TITLE = 'Why a roster fails, and how the scheduler explains it';
const DESCRIPTION =
  'Constraint solving is the easy part of shift scheduling. The hard part is telling a clinic manager which constraint is short, by how much, and what would change it.';

export const metadata: Metadata = {
  title: `${TITLE} — memeShift`,
  description: DESCRIPTION,
  alternates: { canonical: ARTICLE_URL },
  authors: [{ name: 'Hisashi Aoki', url: AUTHOR_URL }],
  openGraph: {
    title: TITLE,
    description: DESCRIPTION,
    url: ARTICLE_URL,
    siteName: 'memeShift',
    type: 'article',
    locale: 'en_US',
  },
  twitter: { card: 'summary', title: TITLE, description: DESCRIPTION },
};

const STRUCTURED_DATA = {
  '@context': 'https://schema.org',
  '@type': 'TechArticle',
  headline: TITLE,
  description: DESCRIPTION,
  url: ARTICLE_URL,
  datePublished: '2026-11-26',
  author: {
    '@type': 'Person',
    name: 'Hisashi Aoki',
    url: AUTHOR_URL,
    jobTitle: 'Full-Spectrum Product Engineer',
  },
  about: ['constraint programming', 'shift scheduling', 'healthcare operations'],
  isPartOf: { '@type': 'WebSite', url: SITE_URL, name: 'memeShift' },
};

export default function Article() {
  return (
    <div className="min-h-screen bg-[#0b1220] text-white">
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(STRUCTURED_DATA) }}
      />

      <header className="max-w-3xl mx-auto px-6 py-6 flex items-center justify-between">
        <a href={SITE_URL} className="flex items-center gap-2 text-sm text-slate-300 hover:text-white">
          <span aria-hidden="true">←</span> memeShift
        </a>
        <a
          href={AUTHOR_URL}
          className="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-medium text-white hover:bg-white/15"
        >
          Hire me on Contra
        </a>
      </header>

      <article className="max-w-3xl mx-auto px-6 pb-20">
        <p className="text-[11px] uppercase tracking-[0.18em] text-primary-300">Engineering note</p>
        <h1 className="mt-3 text-3xl md:text-4xl font-bold leading-tight tracking-tight">{TITLE}</h1>
        <p className="mt-4 text-lg leading-relaxed text-slate-300">{DESCRIPTION}</p>
        <p className="mt-3 text-xs text-slate-500">Hisashi Aoki · 26 November 2026 · 6 min read</p>

        <div className="mt-10 space-y-6 text-[15px] leading-relaxed text-slate-300">
          <p>
            Most scheduling tools treat “cannot generate” as an error state. A clinic manager
            experiences it as a dead end: the month is not done, the deadline is tomorrow, and the
            system offers a retry button. The useful output is not a different error message — it is
            a specific answer: <em>which constraint is short, by how much, and what would change it</em>.
          </p>

          <h2 className="pt-4 text-xl font-semibold text-white">The model is the interesting part</h2>
          <p>
            A monthly roster is a constraint satisfaction problem with domain rules that do not
            generalise well. memeShift models it explicitly:
          </p>
          <ul className="list-disc space-y-2 pl-5">
            <li>
              <strong>Night blocks.</strong> A night shift reserves the next day as post-night
              (<span className="font-mono text-[13px]">明け</span>) and the day after as rest. These are
              not two independent assignments.
            </li>
            <li>
              <strong>Headcount per shift type</strong>, with daily overrides for a specific date.
            </li>
            <li>
              <strong>Consecutive-day limits</strong> that differ per staff member.
            </li>
            <li>
              <strong>Compatibility rules</strong> — pairings that must happen together or must never
              happen (<span className="font-mono text-[13px]">must_together</span> /{' '}
              <span className="font-mono text-[13px]">must_separate</span>).
            </li>
            <li>
              <strong>Preferences</strong> — requested days off and fixed shifts — applied before
              general assignment.
            </li>
          </ul>
          <p>
            Generation is therefore not a round-robin with a random seed. It is a pipeline:
            constraint checking, assignment, off-day distribution, pattern rotation, then fairness
            balancing.
          </p>

          <h2 className="pt-4 text-xl font-semibold text-white">Why “infeasible” is a useless answer</h2>
          <p>
            A solver can prove infeasibility without telling anyone which decision caused it. In
            practice the cause is usually one of four things: not enough night-capable staff on a
            date, a consecutive-day limit that blocks the only available person, preferences
            concentrated on the same days, or a compatibility rule that removes the remaining
            candidates.
          </p>
          <p>
            memeShift computes that attribution per date and shift type and returns a structured
            explanation instead of a boolean:
          </p>
          <ul className="list-disc space-y-2 pl-5">
            <li>required versus assigned headcount;</li>
            <li>the constraint family that produced the shortfall;</li>
            <li>a suggested action, including which screen to open.</li>
          </ul>
          <p>
            That last part matters more than it sounds. “Night capability is short on the 12th” is
            information. “Open the staff screen and add night capability for Staff D” is a task.
          </p>

          <h2 className="pt-4 text-xl font-semibold text-white">Fairness has to be measured across the year</h2>
          <p>
            A monthly roster can look balanced and still be unfair. Night shifts cluster around the
            same people; weekends drift to whoever accepts them; early and late shifts split into
            two groups that never swap.
          </p>
          <p>
            The statistics service aggregates the year, not the month: night counts, night →
            post-night frequency, average consecutive days, early/late imbalance, holiday counts,
            preference achievement rate, and safety indicators such as short intervals between
            shifts. Those same numbers drive the dashboard and the generator’s balancing pass, so
            the thing being optimised and the thing being reported are the same thing.
          </p>

          <h2 className="pt-4 text-xl font-semibold text-white">Repair, not regeneration</h2>
          <p>
            Regenerating a published roster destroys work people have already planned around. The
            repair path is bounded and explicit: local conflicts are resolved while fixed cells stay
            fixed, and a break-route search proposes the smallest relaxations that would unlock
            progress — release a fixed shift, move a preference, relax a constraint — ranked by
            candidate gain against penalty.
          </p>
          <p>
            Applying a route is a confirmed action. The UI shows the route before it changes a
            single cell, because a scheduler who cannot predict the tool will not trust it.
          </p>

          <h2 className="pt-4 text-xl font-semibold text-white">Determinism is a feature</h2>
          <p>
            The same month always produces the same roster from the same inputs. That is not an
            implementation detail: it makes screenshots reproducible, tests stable, and disagreements
            debuggable. It is also why the public demo can run entirely in the browser on seeded
            synthetic data, with no backend and no real staff records anywhere.
          </p>

          <h2 className="pt-4 text-xl font-semibold text-white">What I would do differently</h2>
          <p>
            I would model constraint violations as first-class rows rather than derived objects.
            Today the explanation is computed, which is fast and easy to reason about; storing
            violations as records would make partial repairs auditable by default and would let the
            UI show the history of what was relaxed, when, and by whom. That is a schema decision,
            and it is much cheaper to make before the first facility than after the fifth.
          </p>

          <h2 className="pt-4 text-xl font-semibold text-white">The result</h2>
          <p>
            A scheduler that returns a reason instead of a failure, measures fairness over the year,
            and repairs locally with a visible route. The engine has no framework dependency, so the
            same code runs as a library, a CLI, or inside the API — and the public demo is the same
            UI with a deterministic data adapter.
          </p>
        </div>

        <div className="mt-12 rounded-2xl border border-white/10 bg-white/[0.03] p-6">
          <p className="text-[11px] uppercase tracking-[0.16em] text-primary-300">Work with me</p>
          <p className="mt-2 text-sm leading-relaxed text-slate-300">
            I build healthcare and operations software end to end — MVP in 2–6 weeks, production
            system plus CI/CD in 10–14 weeks, or fractional engineering at 20–30 hours a week.
          </p>
          <div className="mt-4 flex flex-wrap gap-3">
            <a
              href={AUTHOR_URL}
              className="rounded-xl bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-400"
            >
              Hire me on Contra
            </a>
            <a
              href={SITE_URL}
              className="rounded-xl border border-white/15 bg-white/5 px-4 py-2.5 text-sm font-semibold hover:bg-white/10"
            >
              Open the demo
            </a>
          </div>
        </div>
      </article>
    </div>
  );
}
