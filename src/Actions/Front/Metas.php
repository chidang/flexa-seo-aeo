<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Actions\Front;

use Flexa\SeoAeo\Services\Metas as MetasService;
use Flexa\SeoAeo\Services\ResolvedMeta;
use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend I/O layer: takes the {@see ResolvedMeta} computed by the service and
 * prints the `<title>` (via the document-title filter) plus the `<head>` meta,
 * canonical, Open Graph and Twitter/X Card tags, each gated by its module
 * toggle. This class does the escaping; the service never emits HTML.
 *
 * Robots is the exception: it goes through core's `wp_robots` filter rather
 * than being printed here, so the page ends up with one robots tag.
 */
final class Metas {
	use SingletonTrait;

	private ?ResolvedMeta $resolved = null;

	public function register(): void {
		if ( is_admin() ) {
			return;
		}

		add_filter( 'pre_get_document_title', [ $this, 'filter_document_title' ], 20 );
		add_filter( 'wp_robots', [ $this, 'filter_robots' ], 20 );
		add_action( 'wp_head', [ $this, 'print_head' ], 1 );
	}

	public function filter_document_title( string $title ): string {
		if ( ! (bool) Settings::get( 'titles_metas' ) ) {
			return $title;
		}

		$resolved = $this->meta()->title;

		return '' !== $resolved ? $resolved : $title;
	}

	/**
	 * Contributes the plugin's directives to core's single robots tag instead of
	 * printing a second one. Core prints `wp_robots()` at `wp_head` priority 1,
	 * so a plugin that echoes its own leaves every page carrying two
	 * `<meta name="robots">` tags that can disagree.
	 *
	 * @param array<string, string|bool|int> $robots
	 * @return array<string, string|bool|int>
	 */
	public function filter_robots( array $robots ): array {
		if ( ! (bool) Settings::get( 'titles_metas' ) ) {
			return $robots;
		}

		$ours = $this->meta()->robots_map();

		// noindex and nofollow are one-way latches: whoever asks for them wins.
		// Core knows about contexts the plugin does not (embeds, for one), and
		// the plugin knows about per-post settings core cannot see, so the safe
		// merge is the union of both refusals rather than the later writer.
		$noindex  = ! empty( $robots['noindex'] ) || ! empty( $ours['noindex'] );
		$nofollow = ! empty( $robots['nofollow'] ) || ! empty( $ours['nofollow'] );

		$merged = array_merge( $robots, $ours );

		if ( $noindex ) {
			unset(
				$merged['index'],
				$merged['max-snippet'],
				$merged['max-image-preview'],
				$merged['max-video-preview']
			);
			$merged['noindex'] = true;
		}

		if ( $nofollow ) {
			unset( $merged['follow'] );
			$merged['nofollow'] = true;
		}

		return $merged;
	}

	public function print_head(): void {
		$meta = $this->meta();

		echo "\n<!-- Flexa SEO -->\n";

		if ( (bool) Settings::get( 'titles_metas' ) ) {
			if ( '' !== $meta->description ) {
				$this->meta_tag( 'name', 'description', $meta->description );
			}

			if ( '' !== $meta->canonical ) {
				// Core prints its own canonical at `wp_head` priority 10 on singular
				// views. This method runs at priority 1, so dropping it here stops the
				// page carrying two canonical tags, which would contradict each other
				// as soon as a custom canonical is set on the post.
				remove_action( 'wp_head', 'rel_canonical' );
				printf( "<link rel=\"canonical\" href=\"%s\" />\n", esc_url( $meta->canonical ) );
			}
		}

		if ( (bool) Settings::get( 'open_graph' ) ) {
			/**
			 * Filters the Open Graph property => content map before output.
			 * Integrations (e.g. WooCommerce) use this to override `og:type` and
			 * append `product:*` tags for rich commerce previews in social and
			 * chat apps.
			 *
			 * @param array<string, string> $og
			 */
			$og = (array) apply_filters( 'flexa_seo_aeo/front/og', $meta->og );
			foreach ( $og as $property => $content ) {
				if ( ! is_scalar( $content ) || '' === (string) $content ) {
					continue;
				}
				printf(
					"<meta property=\"%s\" content=\"%s\" />\n",
					esc_attr( (string) $property ),
					esc_attr( (string) $content )
				);
			}
		}

		if ( (bool) Settings::get( 'twitter_cards' ) ) {
			foreach ( $meta->twitter as $name => $content ) {
				$this->meta_tag( 'name', $name, $content );
			}
		}

		echo "<!-- /Flexa SEO -->\n";
	}

	private function meta_tag( string $attr, string $key, string $content ): void {
		printf(
			"<meta %s=\"%s\" content=\"%s\" />\n",
			esc_attr( $attr ),
			esc_attr( $key ),
			esc_attr( $content )
		);
	}

	private function meta(): ResolvedMeta {
		if ( null === $this->resolved ) {
			$this->resolved = MetasService::instance()->resolve();
		}

		return $this->resolved;
	}
}
