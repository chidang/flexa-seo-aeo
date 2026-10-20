# Flexa SEO — Implementation Plan

> AEO/GEO-first WordPress SEO plugin. Positioning + comparison rationale:
> `../seopress-vs-rankmath-and-plan.md`. Naming locked in project memory.

## Substitution vocabulary (locked)

| Token | Value |
|---|---|
| slug / text-domain | `flexa-seo-aeo` |
| namespace | `Flexa\SeoAeo\` → `src/` (Pro: `Flexa\SeoAeoPro\` → `src-pro/`) |
| constants | `FLEXA_SEO_AEO_*` |
| REST namespace | `flexa-seo-aeo/v1` |
| hook prefix | `flexa_seo_aeo/` (slash — deliberate) |
| option | `flexa_seo_aeo_settings` |
| js global / mount / tw prefix | `flexaSeoAeo` / `flexa-seo-aeo-admin-root` / `fsa` |

Canonical reference: `../flexa-cache/` and `../flexa-media-folders-pro/`.

## Progress tracker

### ✅ Phase 0 — Scaffold & foundation (DONE)
- [x] Bootstrap `flexa-seo-aeo.php` (constants, PSR-4 + fallback autoload, PHP 8.2 guard, activation/deactivation hooks).
- [x] `composer.json` (PSR-4, phpstan/phpcs/phpunit dev deps).
- [x] `Support\SingletonTrait`, `Support\Capabilities`.
- [x] `Support\Settings` — typed schema/defaults/coerce/sanitize, one option, nested `aeo` group with partial-merge.
- [x] `Support\Resetter` — single destructive path (REST + CLI share it).
- [x] `Install\Activator` (seed defaults + flush rewrites) / `Install\Deactivator`.
- [x] `Rest\BaseRestController` (permission_callback), `Rest\RegisterFacade` (registers every controller), `Rest\SettingsController` (GET/POST/reset, partial merge).
- [x] `Cli\PluginCommand` (status / reset / ping).
- [x] `src/Plugin.php` boot with class_exists guards for every future module.
- [x] `uninstall.php`.
- [x] `php -l` clean on all files.
- [ ] `composer install` + phpstan level 6 clean (needs vendor — run when deps installed).

### Phase 1 — SEO metadata engine (frontend core) — code DONE (phpstan pending vendor)
- [x] `Services\Variables` (`%%token%%` engine: sitename/tagline/sep/year/searchphrase/page + caller context) + `Services\Metas` (resolve title/desc/canonical/robots per query context) → `Services\ResolvedMeta` VO + `Actions\Front\Metas` (I/O: `pre_get_document_title` + `wp_head`). Robots default carries AEO-friendly `max-snippet:-1 / max-image-preview:large / max-video-preview:-1`.
- [x] Open Graph + Twitter/X Card output (gated by `open_graph` / `twitter_cards`; per-post overrides fall back to OG then computed title/desc/featured image).
- [x] Per-post overrides: `Domain\PostMeta` VO + `Domain\PostMetaRepository` (register_post_meta for the block editor, get/save, **migration-read fallback** for Yoast/RankMath/SEOPress keys), `Admin\PostMetabox` (classic editor), `Rest\PostMetaController` (per-post `edit_post` gate, registered in RegisterFacade).
- [ ] **Deferred:** native Gutenberg React sidebar → Phase 4 (needs build step; block editor already reads/writes via register_post_meta). Settings import/export + write-side bulk migration → fold into Phase 4 admin app.
- [ ] Verify phpstan L6 clean once `composer install` runs (shared Phase 0 TODO).

### Phase 2 — Sitemaps & indexing — code DONE (phpstan pending vendor)
- [x] `Services\Sitemap` (pure builder: enabled post types/taxonomies ∩ registered-public, `MAX_PER_PAGE=2000` pagination, per-post `noindex` excluded via `meta_query`, image entries, `entry_url`/`index_url`/`stylesheet_url` with pretty-permalink + query-var fallback) + `Actions\Sitemap\Router` (rewrite `sitemap.xml` / `sitemap.xsl` / `sitemap-(posts|taxonomies)-{subtype}-{page}.xml`, query vars, `template_redirect` render → templates, `X-Robots-Tag: noindex`, real 404 for empty pages). Templates `templates/sitemap/{index,urlset,stylesheet}.php` (escaping + branded XSL browser view).
- [x] HTML sitemap `[flexa_seo_aeo_sitemap]` shortcode (`Actions\Sitemap\HtmlSitemap`, reuses the same `Sitemap::html_tree()`). Gutenberg block wrapper deferred to Phase 4 (needs build step).
- [x] robots.txt filter (`Actions\RobotsTxt`: `Sitemap:` line + custom `robots_txt` append, skipped on non-public sites) + IndexNow (`Services\IndexNow` submit + auto key, `Actions\IndexNow` publish/update ping + `/{key}.txt` rewrite + flush-on-toggle).
- [x] Settings extended: `indexnow` toggle, `robots_txt`, `indexnow_key` (schema/coerce/sanitize).
- [ ] **Deferred:** Google Indexing API ping (needs OAuth service-account) → Pro/later. Sitemap image entries: featured image plus every `<img>` in the stored content (`Support\ContentImages`, added 2026-10-20).
- [ ] Verify phpstan L6 clean once `composer install` runs (shared Phase 0 TODO).

### Phase 3 — AEO core (differentiator, high priority) — code DONE (phpstan pending vendor)
- [x] `Services\Aeo\LlmsTxt` (pure builder: `# site` + `> summary` + one `## section` per enabled post type, entries `- [title](url): note`, reuses `Sitemap::post_types()` + the same `noindex` exclusion, capped by `flexa_seo_aeo/aeo/llms_limit`) + `Actions\Aeo\LlmsTxt` (rewrite `^llms\.txt$` → `flexa_llms`, `text/plain` + `X-Robots-Tag: noindex`, query-var fallback, flush-on-toggle attached unconditionally). Gated by `aeo.enabled`.
- [x] Agent Readiness: `Services\Aeo\ContentMarkdown` (DOMDocument HTML→Markdown of the rendered `the_content`, front-matter-style header) + `Actions\Aeo\AgentReadiness` — serves `?flexa-aeo=md` (gated `aeo.plain_text_export`, skips password-protected / non-publicly-viewable) and advertises the variant via HTTP `Link:` header **and** `<link rel="alternate" type="text/markdown">` (gated `aeo.agent_readiness`).
- [x] AEO schema: `Services\Aeo\Schema` (`@graph` — Article/BlogPosting/WebPage per post type, WebSite + SearchAction on front page, Organization/Person publisher from knowledge settings, **FAQPage auto-detected from core `details` blocks**, HowTo via `flexa_seo_aeo/aeo/howto_steps` filter) + `Actions\Front\Schema` (`wp_head` JSON-LD, `JSON_HEX_TAG|JSON_HEX_AMP` hardening). Gated by `aeo.schema` (new toggle in the `aeo` group).
- [ ] **Deferred:** QAPage node (filter seam `flexa_seo_aeo/aeo/schema_nodes` for now). (Pro seed) AI metadata generator using the user's own API key (default: latest Claude models) → Phase 5 / Pro.
- [ ] Verify phpstan L6 clean once `composer install` runs (shared Phase 0 TODO).

### Phase 4 — Admin app (React) — code DONE (type-check + build clean)
- [x] `Admin\AdminMenu` (top-level `flexa-seo-aeo` menu, `dashicons-search`, Plugins-row Settings link) + `Admin\Enqueue` (Vite-manifest asset loader + dev-server HMR path; localizes `flexaSeoAeo` = restUrl/nonce/version/theme + **postTypes/taxonomies** for the sitemap multi-selects) + `views/admin-app.php` mount (`flexa-seo-aeo-admin-root`). Already wired in `Plugin::boot`.
- [x] Vite 6 + React 18 + TS 5 strict + Tailwind v4 (`fsa:` prefix) + TanStack Query v5 + Zustand (`flexa-seo-aeo:ui`) app under `apps/admin/`, mirrored from the flexa-cache reference (re-substituted vocabulary). Hand-vendored `components/ui/{button,input,label,switch,select,dialog,tooltip}`, `Toaster` (activeClaim singleton), `lib/{cn,api,wp,i18n,store}`, `app/providers` (module-singleton queryClient).
- [x] Settings screen row-for-row against the schema: 7 sections (Titles & Meta / Social / Organization / Sitemaps / Indexing / **AEO Core** / Danger Zone) with `Switch` toggles, `Select` enums, text/textarea fields, post-type/taxonomy checkbox groups, and the typed-confirm `DangerZone` → `POST /settings/reset`. Partial-diff save (`POST /settings`, changed fields only; `aeo` group sent whole → server merges).
- [x] WP-admin CSS override block in `styles/index.css`: heading margin + `revert-layer` color, paragraph `margin-block:0`, dark-surface defaults, `.flexa-seo-aeo-control` form-chrome reset (marker added to `Input`/`Select`), `.flexa-seo-aeo-check` checkbox-ring reset, `.fsa\:text-white` portal override.
- [x] Verified: `pnpm type-check` clean + `pnpm build` clean → `assets/dist/` (292 KB JS / 33 KB CSS, manifest entry `src/main.tsx`).
- [x] **Command palette** (⌘K / Ctrl-K): `components/CommandPalette.tsx` (portal, keyboard nav, store-driven open via `paletteOpen`) — jumps to any of the 7 sections + opens `/sitemap.xml` and `/llms.txt` (new `homeUrl` in the localized global). Header has a discoverable `Search ⌘K` trigger. Section registry extracted to `features/settings/sections.ts` so the palette and page share it.
- [x] **Gutenberg — editor sidebar** (Phase 1 carry-over): `editor/sidebar.js` (vanilla `wp.*` globals, no build step) + `Admin\EditorAssets` (`enqueue_block_editor_assets`). PluginSidebar with Search Appearance / Robots / Open Graph / X panels, reading & writing the `_flexa_seo_aeo_*` post meta via `useEntityProp` (works across the wp.editPost→wp.editor PluginSidebar move).
- [x] **Gutenberg — sitemap block** (Phase 2 carry-over): `blocks/sitemap/{block.json,index.js}` dynamic block + `Actions\Blocks\SitemapBlock` (render_callback reuses `HtmlSitemap::render()`; `ServerSideRender` preview in the editor). Gated by `html_sitemap`, wired in `Plugin::boot`.
- [x] **White-label**: `whitelabel` toggle + `whitelabel_name` in `Settings` (+ `Settings::brand_name()` single source). `AdminMenu` menu/page title and the localized `brandName` (in-app brand strip) both follow it. New **Branding** section in the app.
- [x] **Import / Export**: new **Tools** section — `ToolsPane` exports the current config as a JSON envelope (`{plugin,version,exported_at,settings}`, client-side download) and imports a file back via `POST /settings` (full-blob → server merge-over-stored acts as replace; sanitizer drops unknown keys; import resets the local form). `useImportSettings` hook.
- [x] Rebuild clean after both: `assets/dist/` (299 KB JS / 33 KB CSS).
- [ ] **Deferred:** write-side bulk migration (one-shot import of Yoast/RankMath/SEOPress per-post meta into `_flexa_seo_aeo_*`) → Phase 5 / follow-up.

### WooCommerce AEO — code DONE (pulled forward from Phase 5)
- [x] `Services\Aeo\WooProduct` — **pure** Product/Offer JSON-LD builder (no WooCommerce coupling: takes an extracted data array, returns nested array). Emits `Product` with `sku`/`gtin`/`brand`, single `Offer` (price, priceCurrency, availability, priceValidUntil on sale) or `AggregateOffer` (low/high price for variable products), and `aggregateRating` from review counts.
- [x] `Actions\Woo\ProductSchema` — the only WooCommerce-touching class: extracts the data array from the current `WC_Product` (defensive probes for native GTIN 9.2+ / `product_brand` taxonomy 9.6+ / tax-aware display price) and injects the node via the `flexa_seo_aeo/aeo/schema_nodes` seam, **dropping the auto Article node** for the product URL so the graph carries a single correct primary entity. `Services\Aeo\Schema` never learns about commerce.
- [x] `Actions\Woo\ProductMeta` — overrides `og:type` → `product` and appends `product:price:amount`/`:currency` + availability via a new `flexa_seo_aeo/front/og` seam added to `Actions\Front\Metas` (core meta layer stays commerce-agnostic).
- [x] Gated by new `aeo.commerce` toggle (+ `aeo.schema` / `open_graph`); both actions only booted when `class_exists('WooCommerce')`. Admin app surfaces the toggle in **AEO Core** only when `hasWoo` is localized true.
- [x] `php -l` clean; `pnpm type-check` + `pnpm build` clean (299 KB JS / 33 KB CSS). Added `php-stubs/woocommerce-stubs` dev dep + `phpstan.neon.dist` (level 6, WC bootstrap) so the bridge is analysable.

### Phase 5 — Pro split & release
- [ ] `Flexa\SeoAeoPro\` → `src-pro/`, gated by `flexa_seo_aeo/pro/is_licensed` (Pro presence = file absence, not a runtime flag).
- Release gate (in progress — "release gate first" chosen 2026-08-21):
  - [x] `composer install` — vendor/ installed (phpstan/phpcs/wpcs/stubs).
  - [x] **phpstan level 6 clean** across the whole codebase (Phases 1–4 + WooCommerce). `phpstan.neon.dist`: base WP extension, `treatPhpDocTypesAsCertain: false`, `bootstrapFiles` = `tests/phpstan-constants.php` (the FLEXA_SEO_AEO_* literals — the real defines sit after the version-guard `return`) + WooCommerce + WP-CLI stubs. Needs `--memory-limit=3G` (WC stubs are large). Real fixes: `get_lastpostmodified('gmt')`, `method_exists` guards on the variable-only `get_variation_price()`, one scoped `@phpstan-ignore` on the WC 9.2+ GTIN getter.
  - [x] **phpcs clean** (0 errors / 0 warnings). `phpcs.xml.dist` encodes the house style: base **WordPress-Extra** (not full WordPress — Docs is dropped, docblocks are sparse by design), excludes `WordPress.Files.FileName` (PSR-4 PascalCase), `Universal.Arrays.DisallowShortArraySyntax` (short arrays), `WordPress.NamingConventions.ValidHookName` (slash hooks); sets text-domain + prefixes; DOM camelCase props whitelisted; `PrefixAllGlobals` off for `templates/` (method-scoped at include); dev-server.php excluded. Real fixes: Yoda flips, reserved-keyword param renames (`$default`→`$fallback`, `$class`→`$class_name`, `$public`→`$is_public`, `$list`→`$element`), increment refactor, two scoped `phpcs:ignore` (core `the_content` filter, local `file_get_contents` of the Vite manifest).
  - [x] **`.pot` regenerated** → `i18n/languages/flexa-seo-aeo.pot` (**182 strings**: PHP + `editor/sidebar.js` + `blocks/` + `block.json` + templates + all `apps/admin/` React strings). `wp i18n make-pot` can't parse TSX (Peast is pure-ECMAScript), so `tools/i18n/extract-js.mjs` walks the `apps/admin/src` sources via the **TypeScript compiler AST** (no new deps — `typescript` is already installed; gotcha: use `Identifier.text`, not `.escapedText`, which mangles a leading `__`→`___`) and the fragment is `msgcat --use-first`-merged. One-liner: **`pnpm i18n:pot`**. Runtime is wired (`Enqueue.php` → `wp_set_script_translations` at `i18n/languages`). Remaining (deferred, only matters once real `.po` translations exist): `wp i18n make-json` ↔ built-bundle handle mapping — `.pot` `#:` refs point at `apps/admin/src/*.tsx`, not the enqueued `assets/dist` bundle.
  - [x] `wp-plugin-review` audit — **all 21 code checks clean** (per-object `edit_post` on the id-taking REST route, `manage_options` on settings, nonce+cap on the metabox, no raw SQL/dangerous fns, prefixed globals, ABSPATH guards, escaped output). Only packaging follow-ups remained, now done below.
  - [x] **`readme.txt`** — distinctive title (matches Plugin Name), `== External services ==` disclosing the optional IndexNow ping (what/when/endpoint + Terms/Privacy links; off by default), and `== Source code ==` pointing at the shipped `apps/admin/src` + `pnpm install && pnpm build`. NOTE: `Tested up to: 6.6` is a placeholder — confirm against the target WP before submission.
  - [x] **`.distignore`** — strips dev cruft from the release ZIP (`vendor/`, `node_modules/`, `tests/`, `tools/`, `docs/`, QA config, and the localhost Vite HMR shim `src/Admin/dev-server.php`) while KEEPING `apps/admin/src` + build config so the compiled `assets/dist` bundle has published human-readable source (Check 21). Runtime has no composer deps (spl-autoload fallback), so excluding `vendor/` is safe; `dev-server.php` is only `require`d in dev mode (prod has the manifest).

## Write-side migration (Yoast SEO + Rank Math) — ✅ DONE
Bulk-import per-post SEO meta from Yoast/Rank Math into `_flexa_seo_aeo_*` (the write-side counterpart to `PostMetaRepository`'s read fallback, so a site can deactivate the old plugin).
- `Services\Migration\LegacyMapper` — **pure** (no WP calls, phpstan-clean/testable): per-source field-key map, robots parsing (Yoast `meta-robots-noindex` tri-state `'1'`=noindex + `-adv` list; Rank Math `rank_math_robots` array), and `%%token%%` conversion (Rank Math `%x%`→`%%x%%`; name-map `name`→`author`, `seo_title`→`title`, `term`/`category`→`term_title`).
- `Services\Migration\Migrator` — offset-paged batches (≤100) via `WP_Query` `meta_query EXISTS` on sentinel keys; stable across batches (reads legacy keys, writes Flexa keys); `only_missing()` preserves manual edits unless `--overwrite`; writes via `PostMetaRepository::save`.
- Surfaces: `Rest\MigrationController` (GET `/migrate/sources`, POST `/migrate`; `settings_permission`; wired in `RegisterFacade`) · CLI `wp flexa-seo-aeo migrate --source=yoast|rankmath|all [--overwrite] [--dry-run]` · React `MigrationPane` + `useMigration` (new **Migrate** nav section, batch loop + progress bar + Overwrite switch + toast).
- Clean: phpstan L6, phpcs 0/0, type-check, build (305 KB). `.pot` → 196 strings. SEOPress remains read-fallback-only. Live smoke-test must run inside Local's env (this shell can't reach Local's MySQL).

## Known toolchain debt
- phpstan level 6 must be clean; run `vendor/bin/phpstan analyse --no-progress --memory-limit=3G` (config auto-loaded from `phpstan.neon.dist`; the 1G/256M limits OOM because the WooCommerce stubs are loaded). `WP_REST_Request::get_json_params()` is stubbed as `array` → always cast `(array)` (already done). Templated stubs (`rest_sanitize_boolean`) don't resolve from `mixed` → local `to_bool()` used instead (already done).
- phpcs is now clean under `phpcs.xml.dist` (run `composer lint`). The config is the house style — don't "fix" files back to full-`WordPress`-standard docblocks / long arrays / underscore hooks; that's what the exclusions deliberately allow.
- Slash-separated hooks (`flexa_seo_aeo/...`) are deliberate; never "fix" to underscores.
- **React-app i18n:** TSX `__()` strings are extracted by `tools/i18n/extract-js.mjs` (TS-AST) and merged into the `.pot` via `pnpm i18n:pot` — `wp i18n make-pot` alone skips `.tsx`. Only `wp i18n make-json` bundle-handle mapping remains, and only once translations exist. See the release-gate note above.
