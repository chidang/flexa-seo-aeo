<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Admin;

use Flexa\SeoAeo\Support\Capabilities;
use Flexa\SeoAeo\Support\CachePlugin;
use Flexa\SeoAeo\Support\Docs;
use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the Vite manifest and enqueues built assets. In dev mode (the
 * FLEXA_SEO_AEO_DEV constant defined, or `apps/admin/.dev` marker file
 * present) it loads from the local Vite dev server (default http://localhost:5173)
 * with HMR.
 */
final class Enqueue {
	use SingletonTrait;

	private const HANDLE = 'flexa-seo-aeo-admin';

	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
	}

	public function enqueue_admin( string $hook_suffix ): void {
		if ( 'toplevel_page_' . AdminMenu::SLUG !== $hook_suffix ) {
			return;
		}

		$entry      = 'src/main.tsx';
		$handle     = self::HANDLE;
		$dev_server = __DIR__ . '/dev-server.php';

		if ( $this->is_dev_mode() && is_readable( $dev_server ) ) {
			require_once $dev_server;
			\Flexa\SeoAeo\Admin\flexa_seo_aeo_enqueue_dev_server( $handle, $entry );
		} else {
			$this->enqueue_prod( $handle, $entry );
		}

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				$handle,
				FLEXA_SEO_AEO_TEXT_DOMAIN,
				FLEXA_SEO_AEO_PATH . 'i18n/languages'
			);
		}

		wp_localize_script(
			$handle,
			'flexaSeoAeo',
			[
				'restUrl'           => esc_url_raw( rest_url( FLEXA_SEO_AEO_REST_NAMESPACE . '/' ) ),
				'restNonce'         => wp_create_nonce( 'wp_rest' ),
				'version'           => FLEXA_SEO_AEO_VERSION,
				'pluginUrl'         => esc_url_raw( FLEXA_SEO_AEO_URL ),
				'homeUrl'           => esc_url_raw( home_url( '/' ) ),
				'brandName'         => Settings::brand_name(),
				'locale'            => determine_locale(),
				'theme'             => $this->detect_admin_theme(),
				'hasWoo'            => class_exists( 'WooCommerce' ),
				// Gates the one-click "Enable site-wide" fix, which POSTs to the
				// settings endpoint (manage_options) — editors see the hint only.
				'canManageSettings' => Capabilities::can_manage_settings(),
				// Gates the "Scan now" button, which POSTs to /dashboard/scan
				// (edit_others_posts) — Authors see the cached report read-only.
				'canScan'           => Capabilities::can_scan(),
				// Null unless Flexa Cache is worth suggesting: absent or switched
				// off, and the white-label setting is not hiding our branding.
				'cachePlugin'       => CachePlugin::hint(),
				// Links to the published rubric and compatibility matrix. Null
				// on a white-labelled install, where the UI drops them.
				'docs'              => Docs::for_script(),
				'postTypes'         => $this->public_objects( 'post_types' ),
				'taxonomies'        => $this->public_objects( 'taxonomies' ),
			]
		);
	}

	/**
	 * Public post types or taxonomies as `slug => label`, for the sitemap
	 * multi-selects on the settings screen. `attachment` is dropped — it is
	 * public but never belongs in a sitemap.
	 *
	 * @return array<string, string>
	 */
	private function public_objects( string $kind ): array {
		$objects = 'taxonomies' === $kind
			? get_taxonomies( [ 'public' => true ], 'objects' )
			: get_post_types( [ 'public' => true ], 'objects' );

		$out = [];
		foreach ( $objects as $slug => $object ) {
			$slug = (string) $slug;
			if ( 'attachment' === $slug ) {
				continue;
			}
			$label        = isset( $object->labels->name ) ? (string) $object->labels->name : $slug;
			$out[ $slug ] = $label;
		}

		return $out;
	}

	private function detect_admin_theme(): string {
		$scheme = (string) get_user_option( 'admin_color' );
		if ( '' === $scheme ) {
			$scheme = 'fresh';
		}
		$dark = [ 'midnight', 'ectoplasm', 'ocean', 'coffee' ];
		return in_array( $scheme, $dark, true ) ? 'dark' : 'light';
	}

	private function is_dev_mode(): bool {
		if ( defined( 'FLEXA_SEO_AEO_DEV' ) && constant( 'FLEXA_SEO_AEO_DEV' ) ) {
			return true;
		}
		return file_exists( FLEXA_SEO_AEO_PATH . 'apps/admin/.dev' );
	}

	private function enqueue_prod( string $handle, string $entry ): void {
		$manifest_path = FLEXA_SEO_AEO_PATH . 'assets/dist/.vite/manifest.json';
		if ( ! is_readable( $manifest_path ) ) {
			$manifest_path = FLEXA_SEO_AEO_PATH . 'assets/dist/manifest.json';
		}
		if ( ! is_readable( $manifest_path ) ) {
			return;
		}

		// Reading a local build artifact off disk, not a remote URL — the HTTP
		// API would be the wrong tool here.
		$manifest_raw = file_get_contents( $manifest_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $manifest_raw ) {
			return;
		}
		$manifest = json_decode( $manifest_raw, true );
		if ( ! is_array( $manifest ) || ! isset( $manifest[ $entry ] ) ) {
			return;
		}

		$item     = $manifest[ $entry ];
		$dist_url = FLEXA_SEO_AEO_URL . 'assets/dist/';

		$css_files = $this->collect_manifest_css( $manifest, $entry );
		foreach ( $css_files as $i => $css_file ) {
			wp_enqueue_style(
				$handle . '-css-' . $i,
				$dist_url . $css_file,
				[],
				FLEXA_SEO_AEO_VERSION
			);
		}

		wp_enqueue_script(
			$handle,
			$dist_url . ( $item['file'] ?? '' ),
			[ 'wp-i18n' ],
			FLEXA_SEO_AEO_VERSION,
			true
		);

		add_filter(
			'script_loader_tag',
			static function ( string $tag, string $h ) use ( $handle ): string {
				if ( $h === $handle ) {
					return str_replace( '<script ', '<script type="module" ', $tag );
				}
				return $tag;
			},
			10,
			2
		);
	}

	/**
	 * @param array<string,mixed> $manifest
	 * @param array<string,true>  $seen
	 * @return list<string>
	 */
	private function collect_manifest_css( array $manifest, string $key, array &$seen = [] ): array {
		if ( isset( $seen[ $key ] ) || ! isset( $manifest[ $key ] ) || ! is_array( $manifest[ $key ] ) ) {
			return [];
		}
		$seen[ $key ] = true;

		$item = $manifest[ $key ];
		$css  = [];

		if ( ! empty( $item['css'] ) && is_array( $item['css'] ) ) {
			$css = $item['css'];
		}
		if ( ! empty( $item['imports'] ) && is_array( $item['imports'] ) ) {
			foreach ( $item['imports'] as $import_key ) {
				$css = array_merge(
					$css,
					$this->collect_manifest_css( $manifest, (string) $import_key, $seen )
				);
			}
		}

		return array_values( array_unique( $css ) );
	}
}
