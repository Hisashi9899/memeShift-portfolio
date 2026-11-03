'use client';
import { useState, useRef, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/lib/auth';

function CornerPaw({ position, pressed }: { position: string; pressed: boolean }) {
  const posClass: Record<string, string> = {
    tl: 'top-0 left-0',
    tr: 'top-0 right-0',
    bl: 'bottom-0 left-0',
    br: 'bottom-0 right-0',
  };
  const rotateClass: Record<string, string> = {
    tl: '-rotate-12',
    tr: 'rotate-12',
    bl: 'rotate-12',
    br: '-rotate-12',
  };
  return (
    <div className={`absolute ${posClass[position]} w-14 h-14 overflow-hidden pointer-events-none z-10`}>
      <div className={`w-full h-full flex items-center justify-center transition-none ${
        pressed ? 'animate-paw-step' : 'opacity-0'
      }`}
        style={pressed ? { animationFillMode: 'forwards' } : {}}>
        <div className={`w-10 h-10 rounded-2xl flex items-center justify-center shadow-lg ${rotateClass[position]}`}
          style={{
            background: 'linear-gradient(135deg, #fbbf24, #d97706)',
            boxShadow: pressed
              ? 'inset 0 -3px 6px rgba(0,0,0,0.2), 0 2px 4px rgba(0,0,0,0.15)'
              : 'none'
          }}>
          <span className="text-base drop-shadow-sm" style={{ filter: 'drop-shadow(0 1px 1px rgba(0,0,0,0.2))' }}>🐾</span>
        </div>
      </div>
    </div>
  );
}

export default function LoginPage() {
  const { login, user } = useAuth();
  const router = useRouter();
  const usernameRef = useRef<HTMLInputElement>(null);
  const passwordRef = useRef<HTMLInputElement>(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [bgUrl, setBgUrl] = useState<string | null>(null);
  const [bgLoading, setBgLoading] = useState(false);
  const [pos, setPos] = useState({ x: 0, y: 0 });
  const [dragging, setDragging] = useState(false);
  const [dragStart, setDragStart] = useState({ x: 0, y: 0, px: 0, py: 0 });
  const [pressed, setPressed] = useState<Set<string>>(new Set());
  const cornerTimer = useRef<ReturnType<typeof setTimeout>>();
  const wasDrag = useRef(false);
  const cardRef = useRef<HTMLDivElement>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (user) router.push('/dashboard');
  }, [user, router]);

  useEffect(() => {
    const saved = localStorage.getItem('memeShift_bg');
    if (saved) {
      setBgUrl(saved);
      const sx = localStorage.getItem('memeShift_posX');
      const sy = localStorage.getItem('memeShift_posY');
      if (sx && sy) setPos({ x: parseFloat(sx), y: parseFloat(sy) });
    }
  }, []);

  const resetCorners = useCallback(() => {
    setPressed(new Set());
    cornerTimer.current = undefined;
  }, []);

  const handleCornerClick = useCallback((k: string) => {
    setPressed(prev => {
      const next = new Set(prev);
      next.add(k);
      return next;
    });
    clearTimeout(cornerTimer.current);
    cornerTimer.current = setTimeout(resetCorners, 2000);
    if (pressed.has(k)) return;
    if (pressed.size + 1 === 4) {
      clearTimeout(cornerTimer.current);
      cornerTimer.current = undefined;
      if (bgUrl) {
        setBgUrl(null);
        setPos({ x: 0, y: 0 });
        localStorage.removeItem('memeShift_bg');
        localStorage.removeItem('memeShift_posX');
        localStorage.removeItem('memeShift_posY');
      } else {
        fileInputRef.current?.click();
      }
      setPressed(new Set());
    }
  }, [bgUrl, resetCorners, pressed.size]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const u = usernameRef.current?.value || '';
    const p = passwordRef.current?.value || '';
    if (!u || !p) { setError('ユーザー名とパスワードを入力してください'); return; }
    setError('');
    setLoading(true);
    try {
      await login(u, p);
      router.push('/dashboard');
    } catch (err: any) {
      setError(err.message || 'ログインに失敗しました');
    } finally {
      setLoading(false);
    }
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setBgLoading(true);
    const reader = new FileReader();
    reader.onload = (ev) => {
      const url = ev.target?.result as string;
      setBgUrl(url);
      setBgLoading(false);
      try { localStorage.setItem('memeShift_bg', url); } catch {}
    };
    reader.onerror = () => setBgLoading(false);
    reader.readAsDataURL(file);
  };

  const handleMouseDown = (e: React.MouseEvent) => {
    if (!bgUrl) return;
    const t = e.target as HTMLElement;
    if (['INPUT', 'TEXTAREA', 'BUTTON', 'LABEL', 'SELECT'].includes(t.tagName)) return;
    wasDrag.current = false;
    setDragging(true);
    setDragStart({ x: e.clientX, y: e.clientY, px: pos.x, py: pos.y });
  };

  const handleMouseMove = (e: React.MouseEvent) => {
    if (!dragging) return;
    if (Math.abs(e.clientX - dragStart.x) > 5 || Math.abs(e.clientY - dragStart.y) > 5) {
      wasDrag.current = true;
    }
    setPos({ x: dragStart.px + e.clientX - dragStart.x, y: dragStart.py + e.clientY - dragStart.y });
  };

  const handleMouseUp = () => {
    if (dragging) {
      setDragging(false);
      if (wasDrag.current) {
        try {
          localStorage.setItem('memeShift_posX', String(pos.x));
          localStorage.setItem('memeShift_posY', String(pos.y));
        } catch {}
      }
    }
  };

  const handleCardClick = (e: React.MouseEvent) => {
    if (wasDrag.current) return;
    const t = e.target as HTMLElement;
    if (['INPUT', 'TEXTAREA', 'BUTTON', 'LABEL', 'SELECT'].includes(t.tagName)) return;
    const el = cardRef.current;
    if (!el) return;
    const r = el.getBoundingClientRect();
    const x = e.clientX - r.left;
    const y = e.clientY - r.top;
    const m = 40;
    let k: string | null = null;
    if (y <= m && x <= m) k = 'tl';
    else if (y <= m && x >= r.width - m) k = 'tr';
    else if (y >= r.height - m && x <= m) k = 'bl';
    else if (y >= r.height - m && x >= r.width - m) k = 'br';
    if (k) handleCornerClick(k);
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-surface relative overflow-hidden">
      {bgUrl && (
        <div className="absolute inset-0 bg-cover bg-center"
          style={{ backgroundImage: `url(${bgUrl})` }} />
      )}
      <div ref={cardRef}
        onClick={handleCardClick}
        onMouseDown={handleMouseDown}
        onMouseMove={handleMouseMove}
        onMouseUp={handleMouseUp}
        onMouseLeave={handleMouseUp}
        style={bgUrl ? { left: pos.x, top: pos.y, position: 'relative', cursor: dragging ? 'grabbing' : 'grab' } : {}}
        className={'relative rounded-xl p-8 w-full max-w-md select-none shadow-card border' + (bgUrl ? ' backdrop-blur-md border-white/15' : ' bg-surface-card border-surface-border')}>
        <CornerPaw position="tl" pressed={pressed.has('tl')} />
        <CornerPaw position="tr" pressed={pressed.has('tr')} />
        <CornerPaw position="bl" pressed={pressed.has('bl')} />
        <CornerPaw position="br" pressed={pressed.has('br')} />
        <h1 className={`text-center mb-8 ${bgUrl ? 'text-white drop-shadow-lg' : ''}`}>
          <span className="inline-flex items-center gap-2 text-xl font-bold tracking-tight select-none">
            <span className="text-lg drop-shadow-sm" style={{ filter: 'drop-shadow(0 1px 1px rgba(0,0,0,0.2))' }}>🐾</span>
            <span className="bg-gradient-to-r from-amber-500 to-orange-600 bg-clip-text text-transparent drop-shadow-sm">
              MeMeシフト
            </span>
            <span className="text-lg drop-shadow-sm animate-paw-step" style={{ filter: 'drop-shadow(0 1px 1px rgba(0,0,0,0.2))', animationDelay: '0.3s' }}>🐾</span>
          </span>
        </h1>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className={`block text-xs font-medium mb-1.5 ${bgUrl ? 'text-white/80 drop-shadow-lg' : 'text-gray-500'}`}>ユーザー名</label>
            <input ref={usernameRef} type="text" name="username" autoComplete="username"
              defaultValue={typeof window !== 'undefined' ? localStorage.getItem('remembered_user') || '' : ''}
              className={'w-full px-3.5 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-400/40 transition-all duration-150' + (bgUrl ? ' bg-white/30 backdrop-blur-sm border-white/20 text-white placeholder-white/40' : ' bg-white border-surface-border text-gray-900 placeholder-gray-300')} required />
          </div>
          <div>
            <label className={`block text-xs font-medium mb-1.5 ${bgUrl ? 'text-white/80 drop-shadow-lg' : 'text-gray-500'}`}>パスワード</label>
            <input ref={passwordRef} type="password" name="password" autoComplete="current-password"
              className={'w-full px-3.5 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-400/40 transition-all duration-150' + (bgUrl ? ' bg-white/30 backdrop-blur-sm border-white/20 text-white placeholder-white/40' : ' bg-white border-surface-border text-gray-900 placeholder-gray-300')} required />
          </div>
          {error && <p className={`text-xs ${bgUrl ? 'text-red-300' : 'text-red-500'}`}>{error}</p>}
          <button type="submit" disabled={loading}
            className={'w-full py-2 px-4 rounded-lg disabled:opacity-40 transition-all duration-150 text-sm font-medium' + (bgUrl ? ' bg-primary-600/70 backdrop-blur-sm text-white hover:bg-primary-600/90 border border-primary-400/30' : ' bg-primary-500 text-white hover:bg-primary-600 active:bg-primary-700 shadow-sm')}>
            {loading ? 'ログイン中...' : 'ログイン'}
          </button>
          <div className="text-xs text-gray-400 text-right mt-2">v1.0.0</div>
        </form>
      </div>
      <input ref={fileInputRef} type="file" accept="image/*" className="hidden" onChange={handleFileChange} />
      {bgLoading && (
        <div className="fixed inset-0 bg-black/30 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl px-6 py-4 shadow-lg text-sm text-gray-600">画像を読み込み中...</div>
        </div>
      )}
    </div>
  );
}
