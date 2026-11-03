const TOKEN_KEY = 'token';
const REMEMBERED_USER_KEY = 'remembered_user';

export const tokenStorage = {
  get(): string | null {
    if (typeof window === 'undefined') return null;
    return localStorage.getItem(TOKEN_KEY);
  },

  set(token: string): void {
    localStorage.setItem(TOKEN_KEY, token);
  },

  remove(): void {
    localStorage.removeItem(TOKEN_KEY);
  },

  getRememberedUser(): string | null {
    if (typeof window === 'undefined') return null;
    return localStorage.getItem(REMEMBERED_USER_KEY);
  },

  setRememberedUser(username: string): void {
    localStorage.setItem(REMEMBERED_USER_KEY, username);
  },
};
