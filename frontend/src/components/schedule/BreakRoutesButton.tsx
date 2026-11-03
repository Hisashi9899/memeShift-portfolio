'use client';
import { useState } from 'react';
import { BreakRoutesPanel } from './BreakRoutesPanel';
import type { Conflict } from '@/types';

export function BreakRoutesButton({ conflicts }: { conflicts: Conflict[] }) {
  const [open, setOpen] = useState(false);

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(v => !v)}
        className="px-2.5 py-1.5 text-xs font-medium rounded-lg border border-amber-300 text-amber-700 bg-white hover:bg-amber-100 transition-colors"
      >
        routes ▼
      </button>
      {open && <BreakRoutesPanel conflicts={conflicts} onClose={() => setOpen(false)} />}
    </>
  );
}
