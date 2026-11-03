import React from 'react';

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost';
  size?: 'sm' | 'md';
}

const base = 'inline-flex items-center justify-center font-medium transition-all duration-150 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400/60 focus-visible:ring-offset-2 focus-visible:ring-offset-white';

const sizes: Record<string, string> = {
  sm: 'px-3 py-1.5 text-sm rounded-md gap-1.5',
  md: 'px-4 py-2 text-sm rounded-lg gap-2',
};

const variants: Record<string, string> = {
  primary: 'bg-primary-500 text-white hover-ice-primary active:bg-primary-700 shadow-sm',
  secondary: 'bg-white text-gray-700 border border-surface-border hover-ice active:bg-gray-100 shadow-sm',
  danger: 'bg-white text-red-600 border border-red-200 hover-ice hover:text-red-700 active:bg-red-50',
  ghost: 'text-gray-500 hover:text-gray-900 hover-ice active:bg-gray-100',
};

export function Button({ variant = 'secondary', size = 'md', className = '', children, ...props }: ButtonProps) {
  return (
    <button className={`${base} ${sizes[size]} ${variants[variant]} ${className}`} {...props}>
      {children}
    </button>
  );
}
