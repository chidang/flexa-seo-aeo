<?php
/**
 * Plugin Name:       Flexa SEO - AEO | AI SEO & Answer Engine Optimization
 * Description:       Complete WordPress SEO (schema, sitemaps, meta, Open Graph), plus a per-post Answer-Engine Readiness score and a site health dashboard for AI search.
 * Version:           0.5.6
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            FlexaTech
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flexa-seo-aeo
 * Domain Path:       /i18n/languages
 * WC requires at least: 8.0
 * WC tested up to:   11.0
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Flexa SEO requires PHP 8.2 or higher. The plugin has been disabled.', 'flexa-seo-aeo' );
			echo '</p></div>';
		}
	);
	return;
}

define( 'FLEXA_SEO_AEO_VERSION', '0.5.6' );
define( 'FLEXA_SEO_AEO_FILE', __FILE__ );
define( 'FLEXA_SEO_AEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'FLEXA_SEO_AEO_URL', plugin_dir_url( __FILE__ ) );
define( 'FLEXA_SEO_AEO_BASENAME', plugin_basename( __FILE__ ) );
define( 'FLEXA_SEO_AEO_REST_NAMESPACE', 'flexa-seo-aeo/v1' );
define( 'FLEXA_SEO_AEO_TEXT_DOMAIN', 'flexa-seo-aeo' );

if ( file_exists( FLEXA_SEO_AEO_PATH . 'vendor/autoload.php' ) ) {
	require_once FLEXA_SEO_AEO_PATH . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'Flexa\SeoAeo\\';
			if ( ! str_starts_with( $class_name, $prefix ) ) {
				return;
			}
			$relative = substr( $class_name, strlen( $prefix ) );
			$file     = FLEXA_SEO_AEO_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $file ) ) {
				require $file;
			}
		}
	);
}

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FLEXA_SEO_AEO_FILE, true );
		}
	}
);

// Deactivation Intelligence SDK. Offered here, at file-load time, rather than
// from the boot sequence: the SDK is a single global class shared by every
// Flexa plugin on the site, and its loader picks the newest bundled copy at
// `plugins_loaded`, so a copy registered later than that has already lost the
// vote. The survey's own config is set up in Engine\DeactivationSurvey.
\Flexa\SeoAeo\Engine\DeactivationSurvey::preload();

if ( ! function_exists( 'flexa_seo_aeo_breadcrumbs' ) ) {
	/**
	 * Template tag for the breadcrumb trail. Pass false to get the markup back
	 * without printing it, so a theme can wrap it. Empty string when the
	 * Breadcrumbs module is off or the current view has no trail (the front
	 * page, for one).
	 */
	function flexa_seo_aeo_breadcrumbs( bool $display = true ): string {
		if ( ! class_exists( \Flexa\SeoAeo\Actions\Front\Breadcrumbs::class ) ) {
			return '';
		}

		$html = \Flexa\SeoAeo\Actions\Front\Breadcrumbs::instance()->html();

		if ( $display ) {
			// Assembled from escaped parts inside html().
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $html;
	}
}

register_activation_hook( __FILE__, [ \Flexa\SeoAeo\Install\Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \Flexa\SeoAeo\Install\Deactivator::class, 'deactivate' ] );

add_action(
	'plugins_loaded',
	static function (): void {
		\Flexa\SeoAeo\Plugin::instance()->boot();
	}
);
