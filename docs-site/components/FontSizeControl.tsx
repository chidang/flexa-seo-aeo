'use client';

import { useEffect, useState } from 'react';

/**
 * Floating "A− Aa A+" pill that lets readers scale the docs text up
 * or down. Rendered globally from app/layout.tsx, anchored
 * bottom-left so it doesn't fight the WhatsApp FAB (bottom-right).
 *
 * How it works:
 *  - 5 discrete steps (87.5% → 137.5%, anchored at 100%) so the
 *    layout doesn't reflow chaotically. The percentage is applied to
 *    `document.documentElement.style.fontSize`; everything in the
 *    page is sized in `rem`, so the whole page scales uniformly.
 *  - The chosen step index is persisted in localStorage under
 *    `flexa-docs-font-scale` so the preference survives reloads and
 *    navigation between pages.
 *  - layout.tsx ships a tiny inline script in <head> (see
 *    PRELOAD_SCRIPT below) that applies the saved scale before
 *    first paint — without it large-text users would see a flash of
 *    100% text on every navigation.
 *
 * Accessibility:
 *  - Hidden by default for screen readers? No, the opposite: each
 *    button has an explicit aria-label, and the pill itself is a
 *    labelled `role="group"`. The middle "Aa" button doubles as a
 *    reset (back to 100%) and is keyboard-reachable.
 */

const STEPS = [87.5, 100, 112.5, 125, 137.5] as const;
const DEFAULT_IDX = 1; // 100%
export const STORAGE_KEY = 'flexa-docs-font-scale';

/**
 * Inline script string that runs synchronously in <head> before the
 * body paints, so the saved font scale is applied without a flash.
 * Keep the body small and self-contained — no imports, no template
 * literals beyond the static text, no React.
 */
export const PRELOAD_SCRIPT = `(function(){try{var k='${STORAGE_KEY}';var s=parseInt(localStorage.getItem(k),10);var steps=[${STEPS.join(',')}];if(Number.isInteger(s)&&s>=0&&s<steps.length){document.documentElement.style.fontSize=steps[s]+'%';}}catch(e){}})();`;

function applyScale(idx: number) {
  document.documentElement.style.fontSize = `${STEPS[idx]}%`;
}

export function FontSizeControl() {
  const [idx, setIdx] = useState(DEFAULT_IDX);

  // Sync component state with the value the preload script already
  // applied. Reading localStorage here is safe because this is a
  // client component and the effect runs after hydration.
  useEffect(() => {
    const raw = localStorage.getItem(STORAGE_KEY);
    const stored = raw === null ? NaN : parseInt(raw, 10);
    if (Number.isInteger(stored) && stored >= 0 && stored < STEPS.length) {
      setIdx(stored);
    }
  }, []);

  const update = (next: number) => {
    setIdx(next);
    applyScale(next);
    try {
      localStorage.setItem(STORAGE_KEY, String(next));
    } catch {
      // Storage may be disabled (private mode, quota); the in-page
      // scale still works for this session.
    }
  };

  const dec = () => update(Math.max(0, idx - 1));
  const inc = () => update(Math.min(STEPS.length - 1, idx + 1));
  const reset = () => update(DEFAULT_IDX);

  const atMin = idx === 0;
  const atMax = idx === STEPS.length - 1;
  const pct = STEPS[idx];

  return (
    <div
      role="group"
      aria-label="Adjust text size"
      className="fixed z-50 flex items-center gap-0.5 rounded-full border border-line bg-surface/95 p-1 shadow-md shadow-slate-900/10 backdrop-blur"
      style={{
        left: 'max(1rem, env(safe-area-inset-left, 1rem))',
        bottom: 'max(1rem, env(safe-area-inset-bottom, 1rem))',
      }}
    >
      <button
        type="button"
        onClick={dec}
        disabled={atMin}
        aria-label="Decrease text size"
        className="flex size-9 items-center justify-center rounded-full text-sm font-bold leading-none text-ink-soft transition hover:bg-canvas focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
      >
        A<span aria-hidden className="ml-0.5 text-xs">−</span>
      </button>
      <button
        type="button"
        onClick={reset}
        aria-label={`Reset text size (currently ${pct}%)`}
        title={`Text size: ${pct}% — click to reset`}
        className="flex size-9 items-center justify-center rounded-full text-xs font-semibold leading-none text-muted transition hover:bg-canvas hover:text-ink-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
      >
        Aa
      </button>
      <button
        type="button"
        onClick={inc}
        disabled={atMax}
        aria-label="Increase text size"
        className="flex size-9 items-center justify-center rounded-full text-base font-bold leading-none text-ink-soft transition hover:bg-canvas focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
      >
        A<span aria-hidden className="ml-0.5 text-xs">+</span>
      </button>
    </div>
  );
}
