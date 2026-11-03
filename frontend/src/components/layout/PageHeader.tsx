'use client';
import React from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/Button';

interface PageHeaderProps {
  title: string;
  subtitle?: string;
  backTo?: string;
  backLabel?: string;
  actions?: React.ReactNode;
}

export function PageHeader({ title, subtitle, backTo, backLabel = '戻る', actions }: PageHeaderProps) {
  const router = useRouter();

  return (
    <header className="sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-surface-border">
      <div className="flex items-center justify-between h-14 px-6 max-w-7xl mx-auto">
        <div className="flex items-center gap-4 min-w-0">
          {backTo && (
            <Button variant="ghost" size="sm" onClick={() => router.push(backTo)}>
              &larr; {backLabel}
            </Button>
          )}
          <div className="min-w-0">
            <h1 className="text-base font-semibold text-gray-900 truncate">{title}</h1>
            {subtitle && <p className="text-xs text-gray-500 truncate">{subtitle}</p>}
          </div>
        </div>
        <div className="flex items-center gap-2 flex-shrink-0">
          {actions}
        </div>
      </div>
    </header>
  );
}
