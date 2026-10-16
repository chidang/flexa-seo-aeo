import type { Metadata } from 'next';
import './globals.css';
import { WhatsAppFab } from '@/components/WhatsAppFab';
import {
  FontSizeControl,
  PRELOAD_SCRIPT as FONT_PRELOAD,
} from '@/components/FontSizeControl';
import {
  ThemeControl,
  PRELOAD_SCRIPT as THEME_PRELOAD,
} from '@/components/ThemeControl';

export const metadata: Metadata = {
  title: 'Flexa SEO - AEO - Scoring rubric',
  description:
    'The published rubric behind the Answer-Engine Readiness Score: every ' +
    'check, its weight, its pass and warn thresholds, and how well-founded ' +
    'it is. Plus a tested compatibility matrix for the major SEO plugins.',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <head>
        {/* Apply the saved reader preferences (theme + font scale)
            synchronously before paint so the first frame matches
            the user's choice. Dark-mode users would otherwise see a
            flash of light chrome on every navigation; large-text
            users would see 100% text reflow up. See
            components/ThemeControl.tsx and FontSizeControl.tsx. */}
        <script dangerouslySetInnerHTML={{ __html: THEME_PRELOAD }} />
        <script dangerouslySetInnerHTML={{ __html: FONT_PRELOAD }} />
      </head>
      <body className="min-h-screen antialiased">
        {children}
        <ThemeControl />
        <FontSizeControl />
        <WhatsAppFab />
      </body>
    </html>
  );
}
