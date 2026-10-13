<?php
/**
 * Uninstall handler. Removes every trace of Flexa SEO. Runs in isolation (the
 * plugin is NOT bootstrapped during uninstall) so it cannot use the plugin's
 * classes. Every key below is therefore duplicated from the class that owns it,
 * named in a comment beside it. Keep them in sync.
 *
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Flexa\SeoAeo\Support\Settings::OPTION_KEY.
delete_option( 'flexa_seo_aeo_settings' );
delete_option( 'flexa_seo_aeo_flush_rewrite' );

// Flexa\SeoAeo\Services\Aeo\Schema::FAQ_CACHE_META, a derived per-post cache
// written on the frontend.
delete_post_meta_by_key( '_flexa_seo_aeo_faq_cache' );
