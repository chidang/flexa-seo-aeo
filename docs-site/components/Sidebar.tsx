'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import type { NavItem } from '@/lib/nav';

/**
 * Sticky anchor navigation with scroll-spy, shared by the technical
 * docs page, the user guide, and the services page. An
 * IntersectionObserver tracks which <Section> is in view and highlights
 * the matching link; on small screens the same list collapses behind a
 * toggle. `crossLink` renders a button to jump to a sibling document
 * (docs ↔ guide). `secondaryLink` is an optional second, visually
 * distinct CTA - used to surface the Services page from the docs
 * without pretending it's just another doc.
 */
export function Sidebar({
  items,
  title,
  subtitle,
  crossLinkHref,
  crossLinkLabel,
  secondaryLink,
}: {
  items: NavItem[];
  title: string;
  subtitle: string;
  crossLinkHref: string;
  crossLinkLabel: string;
  secondaryLink?: { href: string; label: string };
}) {
  const [active, setActive] = useState<string>(items[0]?.id ?? '');
  const [open, setOpen] = useState(false);

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries
          .filter((e) => e.isIntersecting)
          .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
        if (visible[0]) setActive(visible[0].target.id);
      },
      { rootMargin: '-20% 0px -70% 0px', threshold: 0 },
    );

    items.forEach(({ id }) => {
      const el = document.getElementById(id);
      if (el) observer.observe(el);
    });
    return () => observer.disconnect();
  }, [items]);

  return (
    <>
      {/* Mobile header */}
      <div className="sticky top-0 z-30 flex items-center justify-between border-b border-line bg-surface/90 px-4 py-3 backdrop-blur lg:hidden">
        <span className="text-sm font-semibold text-ink">{title}</span>
        <button
          type="button"
          onClick={() => setOpen((v) => !v)}
          className="rounded-md border border-line px-3 py-1.5 text-sm font-medium text-ink-soft"
          aria-expanded={open}
        >
          {open ? 'Close' : 'Menu'}
        </button>
      </div>

      <aside
        className={`${
          open ? 'block' : 'hidden'
        } border-b border-line bg-surface px-4 py-4 lg:sticky lg:top-0 lg:block lg:h-screen lg:w-72 lg:flex-none lg:overflow-y-auto lg:border-b-0 lg:border-r lg:px-6 lg:py-8`}
      >
        <div className="mb-5 hidden lg:block">
          <p className="text-sm font-bold text-ink">{title}</p>
          <p className="text-xs text-muted">{subtitle}</p>
        </div>

        <Link
          href={crossLinkHref}
          className="mb-2 flex items-center justify-between rounded-md border border-line bg-canvas px-3 py-2 text-xs font-medium text-brand-700 transition-colors hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-brand-500/10"
        >
          <span>{crossLinkLabel}</span>
          <span aria-hidden>→</span>
        </Link>

        {secondaryLink && (
          <Link
            href={secondaryLink.href}
            className="mb-5 flex items-center justify-between rounded-md bg-brand-500 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-brand-600"
          >
            <span>{secondaryLink.label}</span>
            <span aria-hidden>→</span>
          </Link>
        )}

        <nav>
          <ul className="space-y-0.5">
            {items.map(({ id, label }) => {
              const isActive = active === id;
              return (
                <li key={id}>
                  <a
                    href={`#${id}`}
                    onClick={() => setOpen(false)}
                    aria-current={isActive ? 'true' : undefined}
                    className={`block rounded-md px-3 py-1.5 text-sm transition-colors ${
                      isActive
                        ? 'bg-brand-50 font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300'
                        : 'text-ink-soft hover:bg-slate-50 hover:text-ink dark:hover:bg-slate-800/60'
                    }`}
                  >
                    {label}
                  </a>
                </li>
              );
            })}
          </ul>
        </nav>
      </aside>
    </>
  );
}
