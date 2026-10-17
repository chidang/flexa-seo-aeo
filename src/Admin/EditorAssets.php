<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Admin;

use Flexa\SeoAeo\Support\Docs;
use Flexa\SeoAeo\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the block-editor sidebar (per-post SEO/AEO overrides). The script is
 * plain `wp.*`-globals JavaScript — no build step — so it ships as-is and loads
 * with the standard editor script dependencies. The fields it edits are the
 * `_flexa_seo_aeo_*` post meta registered by {@see \Flexa\SeoAeo\Domain\PostMetaRepository}.
 */
final class EditorAssets {
	use SingletonTrait;

	private const HANDLE = 'flexa-seo-aeo-editor';

	public function register(): void {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue' ] );
	}

	public function enqueue(): void {
		wp_enqueue_script(
			self::HANDLE,
			FLEXA_SEO_AEO_URL . 'editor/sidebar.js',
			[
				'wp-plugins',
				'wp-editor',
				'wp-edit-post',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-core-data',
				'wp-api-fetch',
				'wp-i18n',
			],
			FLEXA_SEO_AEO_VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE,
			'flexaSeoAeoEditor',
			[
				'docs' => Docs::for_script(),
			]
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				self::HANDLE,
				FLEXA_SEO_AEO_TEXT_DOMAIN,
				FLEXA_SEO_AEO_PATH . 'i18n/languages'
			);
		}
	}
}
