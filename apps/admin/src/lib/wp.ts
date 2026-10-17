/**
 * Bridge to the `flexaSeoAeo` global published by Enqueue.php via
 * wp_localize_script.
 */

export type AppTheme = "light" | "dark";

/** What the admin app knows about Flexa Cache, our page-cache plugin. */
export interface CachePluginHint {
  /** "missing" (not installed) or "inactive" (installed, switched off). */
  status: "missing" | "inactive";
  /** True once an admin closed the dashboard card. The Tools row ignores it. */
  dismissed: boolean;
  /**
   * Nonced activate link. Empty whenever there is nothing to click: the
   * plugin isn't installed, or the user can't activate plugins.
   */
  actionUrl: string;
  /** The WordPress.org listing. The only route when actionUrl is empty. */
  infoUrl: string;
}

/** Links to the published documentation site. */
export interface DocsLinks {
  /** The scoring rubric: every readiness weight, threshold and its reasoning. */
  rubric: string;
  /** The tested compatibility matrix for the other major SEO plugins. */
  compatibility: string;
}

export interface PluginGlobal {
  restUrl: string;
  restNonce: string;
  version: string;
  pluginUrl: string;
  /** Site home URL with trailing slash — for links to /sitemap.xml, /llms.txt. */
  homeUrl: string;
  /** Admin-facing brand label; follows the white-label setting. */
  brandName: string;
  locale: string;
  theme: AppTheme;
  /** Whether WooCommerce is active — gates the commerce AEO toggle. */
  hasWoo: boolean;
  /**
   * Whether the current user can change global settings (manage_options) —
   * gates the one-click "Enable site-wide" fix on the dashboard.
   */
  canManageSettings: boolean;
  /**
   * Whether the current user can run a full site scan (edit_others_posts) —
   * gates the "Scan now" button. Authors see the cached report read-only.
   */
  canScan: boolean;
  /**
   * Flexa Cache suggestion, or null when there is nothing to suggest: the
   * plugin is already active, or white-label is hiding our branding.
   */
  cachePlugin: CachePluginHint | null;
  /**
   * Documentation links, or null when white-label is on and pointing somebody
   * else's client at our site would defeat the setting.
   */
  docs: DocsLinks | null;
  /** Public post types as `slug => label`, for the sitemap multi-select. */
  postTypes: Record<string, string>;
  /** Public taxonomies as `slug => label`, for the sitemap multi-select. */
  taxonomies: Record<string, string>;
}

declare global {
  interface Window {
    flexaSeoAeo?: PluginGlobal;
  }
}

export function getPluginGlobal(): PluginGlobal {
  if (!window.flexaSeoAeo) {
    throw new Error(
      "flexaSeoAeo global missing - make sure Enqueue::enqueue_admin ran before this script.",
    );
  }
  return window.flexaSeoAeo;
}
