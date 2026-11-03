'use client';
import React, { useEffect, useRef } from 'react';

interface ModalProps {
  open: boolean;
  onClose: () => void;
  title: string;
  children: React.ReactNode;
  wide?: boolean;
}

export function Modal({ open, onClose, title, children, wide }: ModalProps) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const handler = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/20 backdrop-blur-[2px] transition-all duration-150"
      onClick={onClose}
    >
      <div
        ref={ref}
        onClick={e => e.stopPropagation()}
        className={`bg-surface-card rounded-xl shadow-modal border border-surface-border max-h-[85vh] overflow-auto transition-all duration-150 ${
          wide ? 'w-[min(960px,94vw)]' : 'w-[min(560px,94vw)]'
        }`}
      >
        <div className="flex items-center justify-between px-5 py-3.5 border-b border-surface-border">
          <h2 className="text-sm font-semibold text-gray-900">{title}</h2>
          <button
            onClick={onClose}
            className="flex items-center justify-center w-7 h-7 rounded-md text-gray-400 hover:text-gray-600 hover:bg-surface-hover transition-all duration-150 text-lg leading-none"
          >
            &times;
          </button>
        </div>
        <div className="p-5">{children}</div>
      </div>
    </div>
  );
}
