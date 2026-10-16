<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Engine;

use Flexa\SeoAeo\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the reusable Deactivation Intelligence client SDK into the plugin.
 *
 * Runs only on the Plugins screen and never blocks deactivation: the SDK is a
 * progressive enhancement over the native Deactivate link. The bundled SDK
 * lives outside the PSR-4 namespace (a plain class in `libraries/`), so it is
 * required explicitly here.
 */
final class DeactivationSurvey {

	use SingletonTrait;

	/** Central platform base URL (no trailing slash). */
	private const API_URL = 'https://product-intelligence.flexacommerce.com';

	/**
	 * Where the modal's "Get help" link points. One address for every Flexa
	 * plugin, so the link means the same thing wherever a user meets it.
	 */
	private const SUPPORT_URL = 'https://flexacommerce.com/support';

	/**
	 * Put this plugin's copy of the SDK on the ballot.
	 *
	 * Called from the bootstrap at plugin-file load time. The SDK client is a
	 * single global class, so on a site running several Flexa plugins only one
	 * bundled copy can define it, and that copy also serves the JS and CSS for
	 * every product (its enqueue resolves them relative to its own file).
	 * `loader.php` turns the include into an offer: every copy registers its
	 * version, and the newest one is loaded at `plugins_loaded`. Registering
	 * after that point means this copy missed the vote and the site keeps
	 * running whatever older copy a neighbouring plugin shipped.
	 *
	 * Deliberately not gated on the `enabled` filter: a filter cannot be trusted
	 * to exist this early, and offering the file costs nothing. Whether the
	 * survey runs for THIS plugin is decided below; a copy that wins the vote
	 * while disabled here still serves the other products correctly.
	 */
	public static function preload(): void {
		$loader = FLEXA_SEO_AEO_PATH . 'libraries/deactivation-intelligence/src/loader.php';
		if ( is_readable( $loader ) ) {
			require_once $loader;
		}
	}

	protected function __construct() {
		if ( ! apply_filters( 'flexa_seo_aeo/deactivation_survey/enabled', true ) ) {
			return;
		}

		if ( ! function_exists( 'deactivation_intelligence_init' ) ) {
			return;
		}

		\deactivation_intelligence_init(
			apply_filters(
				'flexa_seo_aeo/deactivation_survey/config',
				array(
					// The product slug has to match the one the dashboard knows.
					'product'     => 'flexa-seo-aeo',
					// Either free or pro; this is the free build.
					'tier'        => 'free',
					'version'     => FLEXA_SEO_AEO_VERSION,
					'plugin_file' => FLEXA_SEO_AEO_BASENAME,
					'api_url'     => self::API_URL,
					'support_url' => self::SUPPORT_URL,
				)
			)
		);
	}
}
