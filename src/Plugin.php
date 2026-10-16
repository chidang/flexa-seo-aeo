<?php

declare(strict_types=1);

namespace Flexa\SeoAeo;

use Flexa\SeoAeo\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap. Wires the services that exist; every module is guarded by
 * class_exists so later phases (frontend metas, sitemaps, AEO, admin app) slot
 * in by adding a file without editing this method.
 */
final class Plugin {
	use SingletonTrait;

	private bool $booted = false;

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// Translations load automatically: WordPress.org-hosted plugins have
		// had just-in-time textdomain loading since WP 4.6.

		if ( class_exists( Install\Migrator::class ) ) {
			add_action( 'admin_init', [ Install\Migrator::class, 'maybe_upgrade' ] );
		}

		if ( class_exists( Rest\RegisterFacade::class ) ) {
			Rest\RegisterFacade::instance()->register();
		}

		// Keep the dashboard's cached health report in step with settings: the
		// technical checklist reads live from the settings toggles.
		if ( class_exists( Services\Aeo\SiteAudit::class ) ) {
			add_action( 'flexa_seo_aeo/settings/updated', [ Services\Aeo\SiteAudit::class, 'flush' ] );
			add_action( 'flexa_seo_aeo/data_reset', [ Services\Aeo\SiteAudit::class, 'purge' ] );
		}

		// Phase 1 — per-post SEO overrides (block-editor meta + classic metabox)
		// and the frontend <head> output (title, meta, OG, Twitter).
		if ( class_exists( Domain\PostMetaRepository::class ) ) {
			Domain\PostMetaRepository::instance()->register();
		}

		if ( is_admin() && class_exists( Admin\PostMetabox::class ) ) {
			Admin\PostMetabox::instance()->register();
		}

		// Block-editor sidebar for per-post SEO/AEO overrides (Phase 4).
		if ( class_exists( Admin\EditorAssets::class ) ) {
			Admin\EditorAssets::instance()->register();
		}

		if ( class_exists( Actions\Front\Metas::class ) ) {
			Actions\Front\Metas::instance()->register();
		}

		// Phase 2 — XML/HTML sitemaps, robots.txt, IndexNow.
		if ( class_exists( Actions\Sitemap\Router::class ) ) {
			Actions\Sitemap\Router::instance()->register();
		}
		if ( class_exists( Actions\Sitemap\HtmlSitemap::class ) ) {
			Actions\Sitemap\HtmlSitemap::instance()->register();
		}
		if ( class_exists( Actions\RobotsTxt::class ) ) {
			Actions\RobotsTxt::instance()->register();
		}
		if ( class_exists( Actions\IndexNow::class ) ) {
			Actions\IndexNow::instance()->register();
		}

		// Links: URL cleanup + link-attribute controls (strip category base,
		// attachment redirects, nofollow/new-tab for external links).
		if ( class_exists( Actions\Links\CategoryBase::class ) ) {
			Actions\Links\CategoryBase::instance()->register();
		}
		if ( class_exists( Actions\Links\AttachmentRedirect::class ) ) {
			Actions\Links\AttachmentRedirect::instance()->register();
		}
		if ( class_exists( Actions\Links\LinkAttributes::class ) ) {
			Actions\Links\LinkAttributes::instance()->register();
		}

		// Breadcrumbs (shortcode + template tag; the BreadcrumbList node is
		// emitted by the schema service) and missing-alt backfill.
		if ( class_exists( Actions\Front\Breadcrumbs::class ) ) {
			Actions\Front\Breadcrumbs::instance()->register();
		}
		if ( class_exists( Actions\Front\ImageAlt::class ) ) {
			Actions\Front\ImageAlt::instance()->register();
		}

		// Strip-category-base rewrites depend on the current category set, so a
		// flush is deferred to a flag that we honour once per request on init.
		add_action( 'init', [ $this, 'maybe_flush_rewrites' ], 99 );
		add_action( 'flexa_seo_aeo/settings/updated', [ $this, 'flush_on_link_toggle' ], 10, 2 );
		if ( class_exists( Actions\Blocks\SitemapBlock::class ) ) {
			Actions\Blocks\SitemapBlock::instance()->register();
		}

		// Phase 3 — AEO core: /llms.txt, Agent Readiness (Markdown export), JSON-LD.
		if ( class_exists( Actions\Aeo\LlmsTxt::class ) ) {
			Actions\Aeo\LlmsTxt::instance()->register();
		}
		if ( class_exists( Actions\Aeo\AgentReadiness::class ) ) {
			Actions\Aeo\AgentReadiness::instance()->register();
		}
		if ( class_exists( Actions\Front\Schema::class ) ) {
			Actions\Front\Schema::instance()->register();
		}

		// WooCommerce AEO — Product/Offer JSON-LD + OG product tags. Only wired
		// when the store is active; each action self-gates on its settings and
		// injects through the schema_nodes / front OG seams so the core stays
		// commerce-agnostic.
		if ( class_exists( 'WooCommerce' ) ) {
			if ( class_exists( Actions\Woo\ProductSchema::class ) ) {
				Actions\Woo\ProductSchema::instance()->register();
			}
			if ( class_exists( Actions\Woo\ProductMeta::class ) ) {
				Actions\Woo\ProductMeta::instance()->register();
			}
		}

		// Phase 4 — admin React app.
		// Setup Assistant: first-run redirect into the wizard, with a
		// Plugins-screen notice fallback. Admin-only; self-suppresses once
		// setup is completed or dismissed.
		if ( is_admin() && class_exists( Admin\ActivationRedirect::class ) ) {
			Admin\ActivationRedirect::instance()->register();
		}

		// Deactivation Intelligence: an optional feedback survey on the Plugins
		// screen. Self-gates to plugins.php and never blocks deactivation.
		if ( is_admin() && class_exists( Engine\DeactivationSurvey::class ) ) {
			Engine\DeactivationSurvey::instance();
		}

		if ( class_exists( Admin\AdminMenu::class ) ) {
			Admin\AdminMenu::instance()->register();
		}
		if ( class_exists( Admin\Enqueue::class ) ) {
			Admin\Enqueue::instance()->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( Cli\PluginCommand::class ) ) {
			Cli\PluginCommand::register();
		}
	}

	/**
	 * Flush rewrite rules once when the deferred flag is set (by a category
	 * change or a strip-category-base toggle). Runs late on init, after the
	 * rewrite filters have registered, so the regenerated rules are current.
	 */
	public function maybe_flush_rewrites(): void {
		if ( '1' !== get_option( 'flexa_seo_aeo_flush_rewrite' ) ) {
			return;
		}

		delete_option( 'flexa_seo_aeo_flush_rewrite' );
		flush_rewrite_rules();
	}

	/**
	 * Schedule a rewrite flush when the strip-category-base setting flips, so the
	 * clean (or restored) category URLs take effect without re-saving permalinks.
	 *
	 * @param array<string, mixed> $new Settings after the write.
	 * @param array<string, mixed> $old Settings before the write.
	 */
	public function flush_on_link_toggle( array $new, array $old ): void {
		if ( ( $new['strip_category_base'] ?? null ) !== ( $old['strip_category_base'] ?? null ) ) {
			update_option( 'flexa_seo_aeo_flush_rewrite', '1' );
		}
	}
}
