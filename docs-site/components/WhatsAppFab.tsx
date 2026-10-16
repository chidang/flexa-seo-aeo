'use client';

import { usePathname } from 'next/navigation';
import { WHATSAPP_URL } from '@/lib/contact';

/**
 * Floating WhatsApp click-to-chat button anchored to the bottom-right.
 * Rendered globally from app/layout.tsx so it follows the reader on
 * every page (User Guide / Technical) - except the Services page
 * itself, where in-content CTAs are already abundant and the FAB would
 * be a redundant duplicate (and visually compete with the hero button).
 *
 * Client component because route-based suppression needs `usePathname`.
 * Still emits zero runtime work on every page beyond the path read;
 * during static export (`output: 'export'`) each route is prerendered,
 * so out/services.html ships without the FAB markup at all, and the
 * other pages ship it as plain HTML.
 *
 * Visual notes:
 *  - Brand green (#25D366) is the official WhatsApp wordmark color;
 *    the only place it appears on the site, so it reads as a
 *    platform affordance, not a theme break.
 *  - `safe-area-inset-*` keeps the button clear of the iOS home-bar.
 *  - The desktop-only tooltip on hover (`group-hover`) makes intent
 *    obvious without taking up space until the user reaches for it.
 */
export function WhatsAppFab() {
  const pathname = usePathname();
  if (pathname === '/services') return null;

  return (
    <a
      href={WHATSAPP_URL}
      target="_blank"
      rel="noopener noreferrer"
      aria-label="Chat with the developer on WhatsApp"
      className="group fixed z-50 flex size-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg shadow-emerald-600/30 transition-transform hover:scale-105 hover:bg-[#1ebe5d] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#25D366]"
      style={{
        right: 'max(1rem, env(safe-area-inset-right, 1rem))',
        bottom: 'max(1rem, env(safe-area-inset-bottom, 1rem))',
      }}
    >
      <svg
        viewBox="0 0 32 32"
        className="size-7"
        aria-hidden
        fill="currentColor"
      >
        <path d="M19.11 17.205c-.372 0-1.088 1.39-1.518 1.39a.63.63 0 0 1-.315-.1c-.802-.402-1.504-.817-2.163-1.447-.545-.516-1.146-1.29-1.46-1.963a.426.426 0 0 1-.073-.215c0-.33.99-.945.99-1.49 0-.143-.73-2.39-.832-2.564-.143-.244-.487-.273-.731-.273-.317 0-.917.215-1.146.401-.7.602-1.04 1.39-1.04 2.205 0 1.39.687 2.764 1.604 3.831 1.146 1.319 2.42 2.435 4.027 3.052.572.215 1.146.402 1.748.487.6.085 1.146.057 1.69-.043 1.046-.187 1.776-.832 2.135-1.776.115-.288.172-.6.172-.9 0-.215-.057-.43-.115-.616-.115-.358-.143-.358-.402-.487-.286-.143-1.246-.616-1.418-.616z" />
        <path d="M16 0C7.16 0 0 7.16 0 16c0 2.835.745 5.6 2.135 8.048L0 32l8.106-2.121A15.93 15.93 0 0 0 16 32c8.84 0 16-7.16 16-16S24.84 0 16 0zm0 29.387c-2.6 0-5.146-.7-7.348-2.02l-.516-.315-5.46 1.432 1.46-5.317-.343-.545A13.34 13.34 0 0 1 2.613 16C2.613 8.61 8.61 2.613 16 2.613S29.387 8.61 29.387 16 23.39 29.387 16 29.387z" />
      </svg>

      {/* Desktop hover tooltip - hidden by default, reveals on hover. */}
      <span
        role="tooltip"
        className="pointer-events-none absolute right-full mr-3 hidden whitespace-nowrap rounded-md bg-ink px-2.5 py-1.5 text-xs font-medium text-white opacity-0 shadow-md transition-opacity group-hover:opacity-100 lg:block"
      >
        Need a custom build? Chat on WhatsApp
      </span>
    </a>
  );
}
