=== Flexa SEO - AEO | AI SEO & Answer Engine Optimization ===
Contributors: flexatech
Tags: seo, schema, sitemap, aeo, woocommerce
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.5.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 8.0
WC tested up to: 11.0

Complete WordPress SEO (schema, sitemaps, meta, Open Graph), plus an Answer-Engine Readiness score that grades every post for AI search.

== Description ==

Flexa SEO is a complete, privacy-first SEO plugin: title & meta templating, JSON-LD schema, XML sitemaps, Open Graph, `robots.txt`, IndexNow, and one-click migration from Yoast and Rank Math. It covers the SEO baseline you expect, and then goes one step further than any of them.

A guided **Setup Assistant** takes you from activation to a configured, answer-engine-ready site in a few minutes: it detects your setup, recommends the SEO and AEO settings that fit, and applies them in one click. Every recommendation is compared against the defaults, so you see exactly which settings will change before anything is applied and any value you set yourself is kept.

**What makes it different: the Answer-Engine Readiness Score**

AI answer engines (ChatGPT, Perplexity, Claude, Google AI Overviews) now decide whether your content gets *quoted*, not just ranked. Dozens of plugins will generate an `llms.txt` file and stop there. Flexa SEO is the only one that **measures and scores how quotable each post actually is**, right in the block editor:

* A live **0–100 readiness score** with a letter grade for the post you're editing, refreshed on every save.
* An **actionable checklist** across eight signals answer engines rely on: structured data, a concise meta description, an answer-first opening paragraph, question-style headings, FAQ/Q&A blocks, `llms.txt` inclusion, a Markdown alternate for crawlers, and content depth.
* Each item comes with a plain-English fix, so writers know *exactly* what to change to become citable, with no guesswork and no external tool.
* A site-wide **health Dashboard** rolls those scores up across your content: overall SEO / AEO / Technical scores, an issues overview, health breakdowns, prioritised actions, and a "Pages needing attention" list where each page reveals what to fix, with a one-click **Enable site-wide** button on the checks that are just a settings toggle away (structured data, llms.txt, Markdown alternate).
* Extensible via the `flexa_seo_aeo/aeo/readiness_checks` filter for themes and add-ons.

This turns "AEO" from a file you generate once into a workflow your team improves post by post: the gap the crowded llms.txt category leaves wide open.

**Core SEO**

* Title & meta description templating with a token engine (site, post, term, author, date, pagination variables).
* JSON-LD structured data (`@graph`): Article / BlogPosting / WebPage, WebSite + SearchAction, Organization / Person publisher, and automatic FAQPage from core FAQ blocks.
* Open Graph and Twitter Card tags, canonical URLs, and configurable robots directives (AEO-friendly defaults: `max-snippet:-1`, `max-image-preview:large`).
* XML sitemaps (index + per-type sub-sitemaps, image entries) with a branded XSL stylesheet, plus an HTML sitemap shortcode `[flexa_seo_aeo_sitemap]` and a sitemap block.
* Per-post editing in a classic metabox **and** a Gutenberg editor sidebar, with one-click **migration from Yoast and Rank Math**, and a read-fallback that also covers SEOPress so nothing breaks mid-move.
* `robots.txt` management and optional **IndexNow** ping on publish/update (see *External services* below).
* **Links** controls: strip the category base from archive URLs, redirect attachment pages to their parent (with an orphan fallback), and add `nofollow` / `target="_blank"` to external links at display time.

**AI answer-engine tooling**

* Native **`/llms.txt`** endpoint describing your site for large language models.
* **Agent Readiness**: advertises a Markdown alternate of each post (`?flexa-aeo=md`) via a `Link` header and `<link rel="alternate">`, and serves a clean, token-cheap Markdown rendering for AI crawlers and coding agents.
* No cloud account and no per-request AI fees: the readiness score and every AEO feature run entirely on your own server.

**WooCommerce (optional)**

* When WooCommerce is active, emits Product / Offer / AggregateOffer JSON-LD (price, availability, rating) and Open Graph product tags for AI shopping surfaces.

**Privacy**

* No account, no telemetry, no phone-home. The only outbound request is the optional IndexNow ping you enable yourself (disclosed below).

== External services ==

This plugin connects to two external services: IndexNow, only when you explicitly enable it, and an optional deactivation feedback survey that runs only in the admin.

**IndexNow (optional, "IndexNow ping" setting)**

When the IndexNow feature is turned on, the plugin notifies the IndexNow API each time you publish or update a post so participating search engines (e.g. Microsoft Bing, Yandex) can recrawl the changed URL quickly.

* **What is sent:** your site host, the changed URL(s), and an IndexNow verification key (a random key the plugin generates and serves at `/{key}.txt` on your own site). No personal data and no post content are transmitted; only the public URL that changed.
* **When:** on the `publish`/update transition of a post, as a non-blocking background request. Nothing is sent while the feature is disabled (it is off by default).
* **Endpoint:** `https://api.indexnow.org/indexnow` (operated by Microsoft).

IndexNow is an open protocol. Documentation and terms: https://www.indexnow.org/documentation (privacy statement for Microsoft, the endpoint operator: https://privacy.microsoft.com/privacystatement).

**Deactivation feedback (Flexa Product Intelligence)**

When you go to deactivate Flexa SEO on the Plugins screen, a short optional survey asks why. It is served by Flexa's product intelligence service at `https://product-intelligence.flexacommerce.com`. It runs only on `wp-admin/plugins.php`, never on the front end, and never blocks or delays deactivation.

* **What is sent, and when:** on opening the Plugins screen, a request to `/api/v1/config` (the product slug and tier) loads the survey configuration, cached for 6 hours. When you deactivate or interact with the survey, the reason you pick and any optional message you type are sent to `/api/v1/deactivations`, `/api/v1/events`, `/api/v1/feedback`, `/api/v1/feature-requests`, and `/api/v1/recovery-events`. If you later reactivate the plugin, a single `/api/v1/recovery-events` request records the reactivation and how long the plugin was inactive.
* **What is included:** an anonymous per-site identifier (a random UUID), the plugin version and tier, and by default your WordPress/PHP version and locale. No email, site domain, user identity, or raw IP is collected.
* **Opt out:** turn the environment data off with `add_filter( 'flexa_seo_aeo/deactivation_survey/config', fn( $c ) => array( 'collect_environment' => false ) + $c );`, or disable the whole survey with `add_filter( 'flexa_seo_aeo/deactivation_survey/enabled', '__return_false' );`.

Service terms and privacy policy: https://flexacommerce.com/pages/terms and https://flexacommerce.com/pages/privacy

== Source code ==

The plugin ships compiled JavaScript/CSS for its admin app in `assets/dist/`. The human-readable source (`apps/admin/src/`) and its build configuration are **included in the plugin package**, so no external download is required.

The admin app is built with pnpm, Vite, React and TypeScript:

1. `pnpm install`
2. `pnpm build`   (or `pnpm dev` for a watched build)

The `.pot` translation template is regenerated with `pnpm i18n:pot` (PHP + vanilla JS via WP-CLI, React strings via a TypeScript-AST extractor in `tools/i18n/`).

== Installation ==

1. Upload the `flexa-seo-aeo` folder to `/wp-content/plugins/`, or install it through the Plugins screen.
2. Activate the plugin through the *Plugins* screen in WordPress.
3. Open **Flexa SEO** in the admin menu to configure titles, social, sitemaps, indexing, and the AEO Core options.

== Frequently Asked Questions ==

= What is the Answer-Engine Readiness Score? =

It is a per-post grade (0–100) shown in the block-editor sidebar that measures how ready the post is to be quoted by AI answer engines. It checks eight signals (structured data, meta description, an answer-first opening, question-style headings, FAQ blocks, llms.txt inclusion, a Markdown alternate, and content depth) and gives a plain-English fix for each. It runs entirely on your server, with no AI account or API key. The full rubric, with every weight and threshold, is published at https://flexa-seo-aeo.vercel.app/.

= What do the evidence labels next to each check mean? =

Every readiness check is labelled `documented`, `observed` or `heuristic`, both in the editor sidebar and on the Dashboard, so you can tell a vendor-documented requirement from our own judgement call. `documented` means a search or AI vendor publishes guidance on that signal. `observed` means studies and testing associate it with being quoted, but no engine states it as a rule. `heuristic` means it is a reasoned bet: cheap to do, plausibly useful, unconfirmed. The weights are editorial in every row, and the reasoning behind each one is set out in the published rubric at https://flexa-seo-aeo.vercel.app/.

= What happens to my data if I delete the plugin? =

Deleting the plugin removes its settings, the Setup Assistant's progress, the Dashboard score history, the anonymous survey id and the cached FAQ data. The SEO fields you filled in per post are kept: your titles, descriptions, canonicals, robots boxes, and the Open Graph and Twitter overrides all stay in the database. That is deliberate. Those are things you wrote, often across hundreds of posts, and removing a plugin should not destroy them with no way back. If you reinstall, they are picked up again. If you want them gone for good, delete the post meta keys beginning `_flexa_seo_aeo_` yourself.

= How is this different from the many llms.txt plugins? =

Most of those generate a single `llms.txt` file and stop. Flexa SEO does that too, but its focus is *measuring and improving* how quotable each page is, post by post, plus a full SEO baseline (schema, sitemaps, meta, migration). The readiness score is the workflow those file-only tools don't offer.

= Does this plugin require an account or send my data anywhere? =

No account is required, and no content is sent anywhere. There are only two optional outbound requests, both covered under *External services*: the IndexNow ping, which you enable yourself and which sends only changed public URLs, and a short deactivation feedback survey on the Plugins screen, which is anonymous and which you can disable with a filter.

= Will it conflict with Yoast, Rank Math, or SEOPress? =

Run one SEO plugin at a time. With two active you get two canonical tags, two sets of Open Graph tags and often two JSON-LD graphs, and search engines pick between them. A tested compatibility matrix, with exact plugin versions and the duplicate tags each pairing produces, is published at https://flexa-seo-aeo.vercel.app/compatibility.

While you are still moving, Flexa SEO reads existing per-post meta from Yoast, Rank Math and SEOPress as a fallback, so the front end keeps working. One-click migration covers Yoast and Rank Math.

= Does it work without WooCommerce? =

Yes. WooCommerce product schema is an optional layer that activates only when WooCommerce is installed.

== Screenshots ==

1. Health Dashboard: overall SEO, AEO and Technical SEO scores, an SEO & AEO score trend, an issues overview (critical, warnings, opportunities, passed), and SEO Health and AEO Health breakdowns.
2. "Pages needing attention" list: per-page AEO score and issue count, expandable to show exactly what to fix, with a link straight to the editor.
3. Settings: title and meta description templating with the token engine, plus site-wide title and meta defaults.
4. Settings: Organization identity used for the knowledge-graph (Organization/Person) schema.
5. Settings: XML and HTML sitemaps, with per-post-type and per-taxonomy inclusion.
6. Settings: indexing controls, IndexNow, and custom robots.txt rules.
7. Settings: AEO Core options: llms.txt, the plain-text and Markdown alternates for AI crawlers, Agent Readiness, and JSON-LD schema.
8. Settings: branding and white-label, to replace the plugin name across the admin.
9. Settings: one-click migration of titles, meta and settings from Yoast SEO or Rank Math.
10. Settings: import and export the full plugin configuration to move it between sites.
11. Settings: Danger Zone, to restore defaults and clear per-post SEO overrides.
12. Block editor: the Search & AI panel, editing title, meta description and canonical URL per page.
13. Block editor: the Social panel, editing Open Graph title, description and share image per page.
14. Settings: social sharing with Open Graph and X (Twitter) Cards, card type, and a default share image.

== Changelog ==

= 0.5.6 =
* Security: a password-protected post could have its contents read by anyone, with no password. The first 160 characters of the body were published as the meta description and repeated in the Open Graph and Twitter tags, `/llms.txt` carried a 150-character extract next to the URL, the XML and HTML sitemaps advertised the post, and a post using Details blocks had its questions and answers published in full as FAQ structured data, with no length limit at all. All of those now treat a protected post as having nothing quotable, and the sitemaps leave it out. If you want protected posts listed in the sitemap anyway, because only the body is meant to be private, `add_filter( 'flexa_seo_aeo/sitemap/include_password_protected', '__return_true' );` brings that part back.
* Fix: the site served two sitemap indexes at once, WordPress's own at `/wp-sitemap.xml` and this plugin's at `/sitemap.xml`, listing the same content under two sets of URLs and leaving search engines to pick. WordPress's is switched off now whenever the plugin's sitemap is on, so there is one index to submit. The `flexa_seo_aeo/sitemap/keep_core_sitemap` filter keeps both if you need them.
* Fix: a generated description could run two words together where one paragraph or heading ended and the next began, so a post opening "What is QA?" followed by "It is testing." produced "What is QA?It is testing." Block boundaries become a space before the tags are stripped. Words inside a sentence, around `<strong>` or a link, still close up the way they should.
* New: every readiness check is now labelled `documented`, `observed` or `heuristic`, in the block-editor sidebar and on the Dashboard, so you can tell a requirement a search or AI vendor publishes from a reasoned bet of ours.
* New: the full scoring rubric and a tested compatibility matrix are published at https://flexa-seo-aeo.vercel.app/. The rubric gives every weight and threshold and the reasoning behind them; the matrix records what actually happens when this plugin runs next to Yoast, Rank Math, All in One SEO or SEOPress, naming the versions tested and the duplicate tags each pairing produces.
* Fix: the readme described one-click migration from SEOPress, which the plugin does not do. Migration covers Yoast and Rank Math; the SEOPress claims are gone.
* Fix: every page carried two `<meta name="robots">` tags, WordPress's and this plugin's, and when they disagreed the one a crawler honours was down to reading order. There is one tag now: the plugin writes through the `wp_robots` filter that WordPress provides, so its per-post noindex and nofollow boxes merge with whatever core and other plugins add instead of competing with it.
* Fix: with Settings > Reading set to discourage search engines, the plugin still published `index, follow`, which is the opposite of what that box asks for and the stronger signal of the two. Discouraging search engines now forces noindex and nofollow on everything, whatever the per-post settings say.
* Fix: the Breadcrumbs and Image SEO toggles did nothing. Both sat in the settings screen, both counted towards the Dashboard score, and turning them on changed no output at all. They work now. Breadcrumbs gives you a `[flexa_breadcrumbs]` shortcode, a `flexa_seo_aeo_breadcrumbs()` template tag and a matching action, plus BreadcrumbList structured data built from the same trail, so the markup and the structured data can never describe different paths. Nothing is placed automatically, because a theme that already prints a trail would end up with two. Image SEO fills a missing `alt` from the attachment's alt text, then its title, then the post title, leaving `alt=""` alone because that is the correct way to mark a decorative image. Stored content is untouched either way.
* Fix: a title or description migrated from Yoast that used `%%name%%` came out blank on posts and pages. The author name is now available there, not only on author archives.
* Fix: the plugin only warned about a conflicting SEO plugin during the Setup Assistant, which runs once, and only knew about Yoast and Rank Math. It now notices SEOPress and All in One SEO too, and shows a dismissible warning whenever one of them is switched on, since two SEO plugins means two canonical tags and two sets of social tags with search engines left to choose. Dismissing it is remembered per combination, so it comes back if you activate a different one later.
* Fix: deleting the plugin left two options behind, the Dashboard score history and the anonymous id used by the deactivation survey. Both are removed now. Your per-post SEO fields are still kept on purpose, and the FAQ above says so and explains how to clear them yourself.
* New: `/llms.txt`, the XML sitemaps and the Markdown version of a post now send `Cache-Control`, `ETag` and `Last-Modified`, and answer 304 when a crawler already has the current copy. Less bandwidth and less work for your server on the URLs bots re-fetch most.
* New: `/sitemap_index.xml` and the `post-sitemap.xml` style URLs that Yoast and Rank Math publish now redirect to the matching Flexa sitemap instead of returning 404, so a site that migrates keeps the sitemap URL it already submitted to Search Console.
* Fix: the sitemap post type and taxonomy settings accepted names that no longer exist, so the stored value could drift into something the settings screen could not display or clear. Both lists are now checked against what is actually registered.
* Fix: the REST endpoint behind the editor sidebar answered differently for a post id that exists and one that does not, which let someone without permission work out which posts are real. Both cases now give the same answer.
* Fix: a WooCommerce product or category page carried two BreadcrumbList blocks, the plugin's and WooCommerce's, describing the same page by different paths. WooCommerce's is now dropped wherever the plugin emits its own, and kept untouched where it does not, so switching Breadcrumbs or Schema off hands the structured data straight back to WooCommerce.
* Fix: the BreadcrumbList on a 404 or a search results page was keyed to the home page URL, which made every one of those views claim to be the same thing. Neither view has a canonical of its own, so neither emits the block any more.
* Fix: post type archives, the WooCommerce shop page and the date archives shipped no `rel="canonical"` at all. WordPress prints one only on single posts and pages, and the plugin had nothing to put there, so those pages were left to the search engine's own guess. Each now points at itself, which also gives the BreadcrumbList block on those pages a stable id.
* Fix: IndexNow handed search engines URLs that nothing else on the site advertises. A post you marked noindex, a password-protected post and every post on a site set to discourage search engines were all pushed the moment they were saved, and because the ping fired on each save, one editing session could submit the same URL a dozen times. The checks the sitemap already applies now apply here too, and a URL just submitted is not submitted again for fifteen minutes. The `flexa_seo_aeo/indexnow/resubmit_after` filter changes that window.
* Fix: page 2 and beyond of a blog, category, tag, author or shop listing told search engines it was page 1, and carried page 1's title. Each page now points at itself and says which page it is, so the later pages can be indexed instead of being treated as copies. A canonical you set by hand on a post still wins.
* Fix: the sitemap index dated each file from the newest published post of that type, including posts the file leaves out. Marking your most recent page noindex left the index advertising a date no URL inside it carried. The date now comes from the posts that are actually listed.
* Fix: a WooCommerce product page carried two Product blocks in its structured data, this plugin's and WooCommerce's, both claiming the same identifier and disagreeing about the contents. WooCommerce's now stands down wherever the plugin emits its own, and is left alone where it does not, so turning Schema or WooCommerce AEO off hands the markup straight back.
* Fix: the Dashboard scored pages you had marked noindex and pages behind a password, so work you had deliberately hidden from search engines dragged the site score down. The scan now reads the same list the sitemap does.
* Fix: archive titles carried the heading prefix WordPress adds for the page itself, so a search result read "Archives: Shop" or "Month: May 2026". The prefix is gone from the title tag; the heading on the page is unchanged.
* New: the Organization or Person block in your structured data has its own Logo field, under Settings > Organization. It used to borrow the default share image, which is a wide social card rather than the square logo search engines ask for. If you leave the field empty it falls back to your theme's logo, then to the share image as before.
* Fix: a shortcode belonging to a plugin that is switched off, or not loaded on that request, was published as the page's own words. `[contact-form-7 id="12"]` came out as the meta description, repeated in the Open Graph and Twitter tags, in the BlogPosting description and in the `/llms.txt` line. Anything shaped like a shortcode is now removed from a generated description, whether or not the plugin that owns it is running, and a page left with nothing to say ships no description tag rather than an empty one.
* Fix: with the knowledge block set to Person, the structured data gave it a `logo`, a property schema.org defines for an organization and not for a person. A Person now carries the same picture as `image` instead. The Organization block is unchanged.
* Fix: the Dashboard and the editor sidebar failed a page on its meta description even where the plugin itself declines to write one: a page built from shortcodes or a page builder has no text to quote, and a protected post is never summarised on purpose. Those pages are a warning now, and the hint says which of the two it is and what the page will actually ship.
* Performance: settings are read dozens of times while a page is built, and each read rebuilt and re-checked the whole stored option. They are now resolved once per request. A legacy sitemap URL that has to be redirected no longer builds the entire sitemap it was only testing for emptiness.

= 0.5.5 =
* New: the Dashboard and Settings > Tools now point you at Flexa Cache, our free page cache. Flexa SEO builds your tags, schema and sitemap entries while WordPress puts a page together, and a page cache is what stops that happening again for the next visitor. The button opens the WordPress.org listing in a new tab, so nothing is installed behind your back; if you already have Flexa Cache sitting there switched off, it activates it instead. The Dashboard card can be closed for good; the Tools row stays. Neither appears if Flexa Cache is already running, or if you have white-label mode on.
* Fix: uninstalling the plugin left the Setup Assistant's progress behind in the options table. It is removed now, along with the new dismissal flag.

= 0.5.4 =
* Fix: on a site with a very large number of posts, building an XML sitemap could use a lot of memory. The step that skips posts you marked noindex collected every one of those IDs first, with no limit, then repeated the list on each sitemap page. It now stops collecting past a thousand and asks the database to filter instead, so the cost stays flat however big the site gets.
* Fix: /llms.txt still used the slower post meta lookup that XML sitemaps dropped in 0.5.3, so it was both heavier than it needed to be and able to disagree with the sitemap about which posts are indexable. Both now share the same exclusion.

= 0.5.3 =
* Fix: the Dashboard content scan was slow, and on sites with long posts or a page builder it could time out before the report appeared. Scoring a post ran the whole WordPress content filter chain, once per post, so page builders, galleries, embeds and related-post widgets all fired up to fifty times over for a report that only reads text. The scan now reads the stored content directly. Scores are unchanged.
* Fix: every XML sitemap query joined the post meta table twice to skip the posts you marked noindex, which made sitemaps slow to build on large sites. That exclusion is now a single indexed lookup, reused across the whole sitemap request.
* Fix: a page holding Details blocks rebuilt its FAQ structured data on every visit, re-reading and re-rendering those blocks each time. The result is now stored and reused until you edit the post, and a page with no Details block skips the work entirely.
* Fix: the "Before you go" survey shown when you deactivate the plugin now offers a Get help link, so you can reach support instead of only leaving feedback.

= 0.5.2 =
* Fix: author, search, 404, date and post type archives carried no meta description at all, which also cost them their Open Graph and Twitter descriptions. Each of those views now gets one, built from what WordPress already holds: the author's bio, the description a post type was registered with, or a short sentence naming the archive and the site. Category, tag and custom taxonomy pages still use the term description, and now fall back the same way when a term has none.
* Fix: a blog index with nothing written on it (a separate Posts page, or a front page set to your latest posts) also came out with no description. It falls back to the tagline, then the AEO site summary, then a plain line naming the site.
* New: a `flexa_seo_aeo/metas/description` filter, so you can replace any of this wording with your own.

= 0.5.1 =
* Fix: the front page could end up with no meta description at all. When the homepage is a static page, its own SEO fields were skipped, so unless you had filled the global Home description (or your site tagline), the description tag was dropped entirely, and Open Graph and Twitter lost their descriptions with it. The front page now falls back to the page's own SEO description, then its excerpt, then the tagline. The same page's SEO title, canonical, Open Graph image (including the featured image) and noindex/nofollow boxes are now honored too, instead of being ignored.
* Fix: a separate Posts page never produced a meta description under any configuration. It now uses its own SEO description, then its excerpt, then the site tagline, and honors its SEO title, canonical, Open Graph image and robots settings.
* Fix: pages carried two `<link rel="canonical">` tags, one from this plugin and one from WordPress itself. WordPress's copy is now removed whenever the plugin outputs its own, so a custom canonical set on a post is no longer contradicted by a second tag pointing at the default URL.

= 0.5.0 =
* New: a **Links** settings section for cleaning up URLs and controlling link behavior on the front end. Strip Category Base removes `/category/` from category archive URLs (with a 301 from the old URL); Redirect Attachments sends media attachment pages to their parent post, with an optional fallback URL for attachments that have no parent. It also adds `rel="nofollow"` to external links, `rel="nofollow"` to links pointing at external image files, and `target="_blank"` to open external links in a new tab. The link attributes are applied when content is displayed, so your stored content is never changed.

= 0.4.1 =
* Fix: the deactivation feedback survey no longer opens more than one "Before you go" dialog. With several Flexa plugins active at once, each bundled its own copy of the survey script and every copy added a handler to the Deactivate link, so the dialog stacked and needed one click to dismiss each copy. Each Deactivate link now opens a single dialog.

= 0.4.0 =
* New: an optional deactivation feedback survey. If you deactivate the plugin from the Plugins screen, a short survey asks why, so we can fix what is not working. It is anonymous (a random per-site id, no email or domain), runs only in the admin, and never blocks or delays deactivation. If you reactivate later, that win-back is recorded too (anonymously). Disable it all with `add_filter( 'flexa_seo_aeo/deactivation_survey/enabled', '__return_false' );`. See *External services* for exactly what is sent.

= 0.3.0 =
* New: **Setup Assistant**, a guided onboarding wizard. It detects your site, recommends the SEO and AEO settings that fit, and applies them in one click. The flow covers search essentials, AI readiness, a quick content-readiness scan of your recent pages, optional migration from Yoast or Rank Math, and a final readiness report that seeds your Dashboard. Nothing is ever overwritten: recommendations are compared against the defaults, you see every setting that will change before applying, and any value you already customized is kept.
* New: re-enter the Setup Assistant any time from the Dashboard card, from Settings › Tools, or from the command palette. A re-run only proposes settings that are still at their defaults, so it is safe to run repeatedly.
* Security: running a full site scan now requires the "edit others' posts" capability (Editors and Administrators), so lower-privileged roles can no longer trigger the site-wide scan through the REST API. Viewing the cached Dashboard report is unchanged.

= 0.2.0 =
* New: **SEO/AEO health Dashboard**: overall SEO, AEO and Technical scores, an issues overview, SEO & AEO health breakdowns, prioritised recommended actions, and a "Pages needing attention" list. Each listed page expands to show exactly which readiness checks fail (with a plain-English fix), links straight to the editor, and can be re-scanned on its own after an edit, with no full site scan needed.
* New: **one-click "Enable site-wide" fix**: readiness checks that fail only because a global toggle is off (structured data, llms.txt, Markdown alternate) get a Fix button on the dashboard and in the block-editor sidebar; it flips the setting and re-scores your pages. Shown only to users who can manage settings. No AI, no external calls.
* New: score trend, a lightweight history of your SEO/AEO scores, captured each time you run a scan (no cron, no external analytics).
* Fix: migration source detection could run an extremely slow database query on large sites (a multi-key meta lookup), which under a small PHP-FPM pool could tie up all workers. Rewritten as a single indexed query so opening the plugin (and running a migration) stays fast.
* Improved: the plugin now opens on the Dashboard, with a Dashboard / Settings switch in the header.

= 0.1.0 =
* Initial release: **Answer-Engine Readiness Score** (per-post 0–100 grade + actionable checklist in the block editor), title/meta templating, Open Graph & Twitter, canonical & robots, XML/HTML sitemaps, robots.txt, IndexNow, llms.txt, Agent Readiness Markdown export, JSON-LD schema (Article/WebPage/WebSite/Organization/FAQ), one-click migration from Yoast and Rank Math, optional WooCommerce Product schema, and a React admin app.

== Upgrade Notice ==

= 0.5.6 =
Security: password-protected posts had their contents published in meta tags, FAQ structured data, sitemaps and llms.txt, and IndexNow was pushing hidden URLs at search engines. Update if you use post passwords or IndexNow.

= 0.5.5 =
Adds a suggestion to pair Flexa SEO with Flexa Cache, and cleans up one option that uninstall used to leave behind.

= 0.5.4 =
Performance: skipping noindexed posts no longer gets more expensive as the site grows, and /llms.txt now uses the same cheap lookup the sitemaps do.

= 0.5.3 =
Performance: the Dashboard content scan, XML sitemap queries and FAQ structured data are all much cheaper to produce, on the front end and in the admin.

= 0.5.2 =
Author, search, 404, date and post type archives now output a meta description instead of none, and so does an empty blog index.

= 0.5.1 =
Fixes a missing meta description on the homepage and on a separate Posts page, makes those pages honor their own SEO fields, and removes the duplicate canonical tag.

= 0.5.0 =
Adds a Links settings section: strip the category base, redirect attachment pages, and control nofollow / new-tab behavior for external links.

= 0.4.1 =
Fixes the deactivation survey stacking multiple "Before you go" dialogs when several Flexa plugins are active.

= 0.4.0 =
Adds an optional, anonymous deactivation feedback survey on the Plugins screen. It never blocks deactivation and can be disabled with a filter.
