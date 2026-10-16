<?php
/**
 * Uninstall handler.
 *
 * Removes the plugin's own configuration and derived caches. It deliberately
 * does NOT remove the per-post SEO fields in `Flexa\SeoAeo\Domain\PostMeta`
 * (`_flexa_seo_aeo_title`, `_description`, `_canonical`, `_noindex`,
 * `_nofollow`, the `_og_*` and the `_twitter_*` keys). Those are text a person
 * wrote by hand, post by post, and deleting a plugin should not silently
 * destroy writing with no undo. Removing and reinstalling therefore keeps
 * every per-post title and description. The readme FAQ says so too.
 *
 * Runs in isolation (the plugin is NOT bootstrapped during uninstall) so it
 * cannot use the plugin's classes. Every key below is therefore duplicated
 * from the class that owns it, named in a comment beside it. Keep them in
 * sync.
 *
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Flexa\SeoAeo\Support\Settings::OPTION_KEY.
delete_option( 'flexa_seo_aeo_settings' );
delete_option( 'flexa_seo_aeo_flush_rewrite' );

// Flexa\SeoAeo\Support\OnboardingState::OPTION_KEY, the Setup Assistant's
// progress.
delete_option( 'flexa_seo_aeo_onboarding' );

// Flexa\SeoAeo\Support\CachePlugin::OPTION_KEY, set when an administrator
// closes the Flexa Cache card.
delete_option( 'flexa_seo_aeo_cache_hint_dismissed' );

// Flexa\SeoAeo\Admin\ConflictNotice::OPTION_KEY, set when an administrator
// dismisses the "another SEO plugin is active" warning.
delete_option( 'flexa_seo_aeo_conflict_dismissed' );

// Flexa\SeoAeo\Services\Aeo\SiteAudit::TREND_KEY, the score history behind the
// Dashboard trend chart. SiteAudit::purge() deletes it too, but that is hooked
// on `flexa_seo_aeo/data_reset`, which uninstall never fires.
delete_option( 'flexa_seo_aeo_dashboard_trend' );

// The anonymous per-site id the Deactivation Intelligence SDK generates, keyed
// on the product slug set in Flexa\SeoAeo\Engine\DeactivationSurvey.
delete_option( 'flexa-seo-aeo_di_installation_id' );

// Flexa\SeoAeo\Services\Aeo\Schema::FAQ_CACHE_META, a derived per-post cache
// written on the frontend. Derived data, not authored, so it goes.
delete_post_meta_by_key( '_flexa_seo_aeo_faq_cache' );
