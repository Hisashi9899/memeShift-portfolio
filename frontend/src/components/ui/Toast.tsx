'use client';
import { Toaster } from 'react-hot-toast';

export function ToastProvider() {
  return <Toaster position="top-center" toastOptions={{ duration: 2000, style: { fontSize: '14px' } }} />;
}
