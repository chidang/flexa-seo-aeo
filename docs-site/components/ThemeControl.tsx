'use client';

import { useEffect, useState } from 'react';

/**
 * Floating Light / Auto / Dark control. Sits just above the
 * font-size pill (bottom-left) so the two reader-preference
 * controls stack as one column without fighting the WhatsApp FAB
 * on the right.
 *
 * State model:
 *  - 'light'  → force the light palette (html.dark removed)
 *  - 'dark'   → force the dark palette (html.dark added)
 *  - 'auto'   → follow the OS, via `prefers-color-scheme: dark`.
 *               Listens to the media query so the page tracks the
 *               system flipping at sunset without a reload.
 *
 * Persistence:
 *  - Stored in localStorage under THEME_KEY. Reads in the
 *    component effect after hydration; the synchronous preload
 *    script in app/layout.tsx (PRELOAD_SCRIPT below) applies it
 *    before paint so users on dark mode never see a flash of
 *    light chrome.
 *  - If nothing is stored we default to 'auto' — explicit
 *    "follow the OS" behavior is what most readers expect.
 */

export type Theme = 'light' | 'dark' | 'auto';
export const THEME_KEY = 'flexa-docs-theme';
const VALID: readonly Theme[] = ['light', 'dark', 'auto'] as const;

/**
 * Synchronous preload script, injected in <head> by layout.tsx.
 * Stays vanilla JS (no template literals beyond the static text,
 * no imports) so it can run before any framework code touches the
 * DOM. The matchMedia call handles 'auto' on first paint.
 */
export const PRELOAD_SCRIPT = `(function(){try{var k='${THEME_KEY}';var t=localStorage.getItem(k);if(t!=='light'&&t!=='dark'&&t!=='auto')t='auto';var dark=t==='dark'||(t==='auto'&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches);if(dark)document.documentElement.classList.add('dark');}catch(e){}})();`;

function applyTheme(t: Theme) {
  const dark =
    t === 'dark' ||
    (t === 'auto' &&
      typeof window !== 'undefined' &&
      window.matchMedia('(prefers-color-scheme: dark)').matches);
  document.documentElement.classList.toggle('dark', dark);
}

export function ThemeControl() {
  // We render with a neutral default and reconcile in the effect.
  // Doing that means the server-rendered HTML never disagrees with
  // the post-hydration markup on the *visible* control state, even
  // though the actual html.dark class may already be set by the
  // preload script.
  const [theme, setTheme] = useState<Theme>('auto');

  useEffect(() => {
    const raw = localStorage.getItem(THEME_KEY);
    const stored: Theme = (VALID as readonly string[]).includes(raw ?? '')
      ? (raw as Theme)
      : 'auto';
    setTheme(stored);
  }, []);

  // While in 'auto', track OS-level palette changes live.
  useEffect(() => {
    if (theme !== 'auto') return;
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    const onChange = () => applyTheme('auto');
    mq.addEventListener('change', onChange);
    return () => mq.removeEventListener('change', onChange);
  }, [theme]);

  const update = (next: Theme) => {
    setTheme(next);
    applyTheme(next);
    try {
      localStorage.setItem(THEME_KEY, next);
    } catch {
      // Storage may be disabled — the in-page toggle still works.
    }
  };

  const btn = (active: boolean) =>
    `flex size-9 items-center justify-center rounded-full text-ink-soft transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 ${
      active
        ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300'
        : 'hover:bg-canvas hover:text-ink dark:hover:bg-slate-800/60'
    }`;

  return (
    <div
      role="group"
      aria-label="Color theme"
      className="fixed z-50 flex items-center gap-0.5 rounded-full border border-line bg-surface/95 p-1 shadow-md shadow-slate-900/10 backdrop-blur"
      style={{
        left: 'max(1rem, env(safe-area-inset-left, 1rem))',
        // Sit above the font-size pill (~3.25rem tall + 1rem gap).
        bottom: 'calc(max(1rem, env(safe-area-inset-bottom, 1rem)) + 3.75rem)',
      }}
    >
      <button
        type="button"
        onClick={() => update('light')}
        aria-pressed={theme === 'light'}
        aria-label="Light theme"
        title="Light theme"
        className={btn(theme === 'light')}
      >
        <SunIcon />
      </button>
      <button
        type="button"
        onClick={() => update('auto')}
        aria-pressed={theme === 'auto'}
        aria-label="Match system theme"
        title="Match system theme"
        className={btn(theme === 'auto')}
      >
        <AutoIcon />
      </button>
      <button
        type="button"
        onClick={() => update('dark')}
        aria-pressed={theme === 'dark'}
        aria-label="Dark theme"
        title="Dark theme"
        className={btn(theme === 'dark')}
      >
        <MoonIcon />
      </button>
    </div>
  );
}

/* ── Icons ─────────────────────────────────────────────────────
 * Inline SVGs keep the control dependency-free. 18px (size-[18px])
 * matches Lucide's default visual weight at this 36px button. */

function SunIcon() {
  return (
    <svg
      viewBox="0 0 24 24"
      aria-hidden
      className="size-[18px]"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <circle cx="12" cy="12" r="4" />
      <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
    </svg>
  );
}

function MoonIcon() {
  return (
    <svg
      viewBox="0 0 24 24"
      aria-hidden
      className="size-[18px]"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
    </svg>
  );
}

function AutoIcon() {
  // Half-filled circle = "follow the system" — readable on both
  // light and dark variants of the button thanks to currentColor.
  return (
    <svg
      viewBox="0 0 24 24"
      aria-hidden
      className="size-[18px]"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <circle cx="12" cy="12" r="9" />
      <path d="M12 3v18" />
      <path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor" stroke="none" />
    </svg>
  );
}
