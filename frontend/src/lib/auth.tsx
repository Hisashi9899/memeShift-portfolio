'use client';
import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { api } from './api';
import { tokenStorage } from './token';

interface User { id: number; username: string; display_name: string }

interface AuthContextType {
  user: User | null;
  loading: boolean;
  login: (username: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType>({} as AuthContextType);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = tokenStorage.get();
    if (token) {
      api.user().then(setUser).catch(() => setUser(null))
        .finally(() => setLoading(false));
    } else {
      setLoading(false);
    }
  }, []);

  const login = useCallback(async (username: string, password: string) => {
    const res = await api.login(username, password);
    tokenStorage.set(res.token);
    tokenStorage.setRememberedUser(username);
    setUser(res.user);
  }, []);

  const logout = useCallback(async () => {
    try { await api.logout(); } catch {}
    tokenStorage.remove();
    setUser(null);
  }, []);

  return (
    <AuthContext.Provider value={{ user, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
