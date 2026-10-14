<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Wipes all Flexa SEO data. Used by the Settings danger zone and the
 * `wp flexa-seo-aeo reset` CLI command — one helper keeps both paths in sync
 * so they can never drift.
 *
 * When later phases add custom tables (e.g. redirects, 404 log), extend this
 * to drop them too (see Install\Migrator::drop()).
 */
final class Resetter {
	/**
	 * @return array{settings_removed:bool}
	 */
	public static function reset_all(): array {
		$removed = delete_option( Settings::OPTION_KEY );

		// Setup Assistant progress is Flexa data too — a full reset returns the
		// site to a first-run state, so the wizard offers itself again.
		delete_option( OnboardingState::OPTION_KEY );
		delete_option( CachePlugin::OPTION_KEY );

		// Transient flag for a deferred rewrite flush (strip-category-base).
		delete_option( 'flexa_seo_aeo_flush_rewrite' );

		if ( class_exists( \Flexa\SeoAeo\Install\Migrator::class ) ) {
			\Flexa\SeoAeo\Install\Migrator::drop();
		}

		do_action( 'flexa_seo_aeo/data_reset' );

		return [
			'settings_removed' => (bool) $removed,
		];
	}
}
