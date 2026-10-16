/**
 * Single source of truth for the sidebar anchor navigation and the
 * `id`s used by each <Section> on the page. Order here = order of
 * sections rendered in the matching page file.
 */
export interface NavItem {
  id: string;
  label: string;
}

/** Anchor nav for the scoring rubric (app/page.tsx). */
export const SCORING_NAV: NavItem[] = [
  { id: 'overview', label: 'Why publish this' },
  { id: 'how-scored', label: 'How the score is built' },
  { id: 'evidence', label: 'Evidence levels' },
  { id: 'checks', label: 'The eight checks' },
  { id: 'grades', label: 'Grades' },
  { id: 'weights', label: 'Where the weights come from' },
  { id: 'site-score', label: 'The site-wide scores' },
  { id: 'extending', label: 'Adding your own check' },
  { id: 'disagree', label: 'If you disagree with a check' },
];

/** Anchor nav for the compatibility matrix (app/compatibility/page.tsx). */
export const COMPATIBILITY_NAV: NavItem[] = [
  { id: 'overview', label: 'What was tested' },
  { id: 'baseline', label: 'The test baseline' },
  { id: 'matrix', label: 'Compatibility matrix' },
  { id: 'duplicate-tags', label: 'Duplicate head tags' },
  { id: 'sitemaps', label: 'Sitemaps and robots.txt' },
  { id: 'migration', label: 'Migration coverage' },
  { id: 'fallback', label: 'Read-fallback coverage' },
  { id: 'known-issues', label: 'Known issues' },
  { id: 'reporting', label: 'Reporting a conflict' },
];

/** Anchor nav for the services / hire-me page (app/services/page.tsx). */
export const SERVICES_NAV: NavItem[] = [
  { id: 'overview', label: 'Overview' },
  { id: 'what-i-build', label: 'What I build' },
  { id: 'industries', label: 'Industries' },
  { id: 'process', label: 'How I work' },
  { id: 'contact', label: 'Get in touch' },
];
