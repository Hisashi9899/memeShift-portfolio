import React from 'react';

interface CardProps {
  children: React.ReactNode;
  className?: string;
  padding?: 'sm' | 'md' | 'lg';
}

const paddings: Record<string, string> = {
  sm: 'p-4',
  md: 'p-5',
  lg: 'p-6',
};

export function Card({ children, className = '', padding = 'md' }: CardProps) {
  return (
    <div className={`bg-surface-card border border-surface-border rounded-xl shadow-card ${paddings[padding]} ${className}`}>
      {children}
    </div>
  );
}

export function CardSection({ label, description, children, className = '' }: {
  label?: string;
  description?: string;
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={`space-y-4 ${className}`}>
      {(label || description) && (
        <div className="space-y-0.5">
          {label && <h2 className="text-sm font-semibold text-gray-900">{label}</h2>}
          {description && <p className="text-xs text-gray-400 leading-relaxed">{description}</p>}
        </div>
      )}
      {children}
    </div>
  );
}
