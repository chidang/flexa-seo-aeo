<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Actions\Front;

use Flexa\SeoAeo\Services\Breadcrumbs as BreadcrumbsService;
use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the visible breadcrumb trail.
 *
 * Placement is left to the site, because no two themes want it in the same
 * spot and injecting it automatically would fight themes that already print
 * their own. Three ways in, all equivalent:
 *
 *  - `[flexa_breadcrumbs]` in content or a block
 *  - `flexa_seo_aeo_breadcrumbs()` in a template
 *  - `do_action( 'flexa_seo_aeo/breadcrumbs' )` in a template
 *
 * The `BreadcrumbList` schema is emitted separately by
 * {@see \Flexa\SeoAeo\Services\Aeo\Schema}, from the same trail.
 */
final class Breadcrumbs {
	use SingletonTrait;

	public function register(): void {
		// Registered even when the toggle is off, so a theme calling the
		// template tag gets an empty string rather than a fatal, and a page
		// holding the shortcode does not print raw `[flexa_breadcrumbs]` text.
		add_shortcode( 'flexa_breadcrumbs', [ $this, 'shortcode' ] );
		add_action( 'flexa_seo_aeo/breadcrumbs', [ $this, 'render' ] );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function shortcode( $atts = [] ): string {
		unset( $atts );

		return $this->html();
	}

	public function render(): void {
		// Built from escaped parts in html().
		echo $this->html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * The trail as an ordered list, or an empty string when the module is off
	 * or there is nothing to show.
	 */
	public function html(): string {
		if ( ! (bool) Settings::get( 'breadcrumbs' ) ) {
			return '';
		}

		$trail = BreadcrumbsService::instance()->trail();
		if ( [] === $trail ) {
			return '';
		}

		$last  = count( $trail ) - 1;
		$items = '';

		foreach ( $trail as $index => $crumb ) {
			$name = esc_html( $crumb['name'] );
			$body = ( '' !== $crumb['url'] && $index !== $last )
				? sprintf( '<a href="%s">%s</a>', esc_url( $crumb['url'] ), $name )
				: sprintf( '<span aria-current="page">%s</span>', $name );

			$items .= sprintf( '<li class="flexa-breadcrumbs__item">%s</li>', $body );
		}

		$html = sprintf(
			'<nav class="flexa-breadcrumbs" aria-label="%s"><ol class="flexa-breadcrumbs__list">%s</ol></nav>',
			esc_attr__( 'Breadcrumb', 'flexa-seo-aeo' ),
			$items
		);

		/**
		 * Filters the rendered breadcrumb markup.
		 *
		 * @param string                                 $html
		 * @param list<array{name: string, url: string}> $trail
		 */
		return (string) apply_filters( 'flexa_seo_aeo/breadcrumbs/html', $html, $trail );
	}
}
