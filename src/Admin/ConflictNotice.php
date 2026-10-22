<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Admin;

use Flexa\SeoAeo\Support\Docs;
use Flexa\SeoAeo\Support\SeoConflicts;
use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Warns, in wp-admin, when another SEO plugin is active alongside this one.
 *
 * Two SEO plugins running together produce two canonicals, two sets of Open
 * Graph tags and often two JSON-LD graphs, and the search engine picks between
 * them. The Setup Assistant already checked for this, but only during
 * onboarding, so anything switched on afterwards went unnoticed for good.
 *
 * The dismissal records which plugins were active at the time, so hiding the
 * notice for Yoast does not also hide it when Rank Math turns up later.
 */
final class ConflictNotice {
	use SingletonTrait;

	/** Stores the conflict signature the user dismissed. */
	public const OPTION_KEY = 'flexa_seo_aeo_conflict_dismissed';

	private const ACTION = 'flexa_seo_aeo_dismiss_conflict';

	public function register(): void {
		add_action( 'admin_notices', [ $this, 'maybe_render' ] );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'dismiss' ] );
	}

	public function maybe_render(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$active = SeoConflicts::active();
		if ( [] === $active ) {
			return;
		}

		if ( SeoConflicts::signature() === (string) get_option( self::OPTION_KEY, '' ) ) {
			return;
		}

		$brand = (bool) Settings::get( 'whitelabel' )
			? __( 'This plugin', 'flexa-seo-aeo' )
			: 'Flexa SEO';

		// "Often" and "pick between them" are the honest words for it, so the
		// notice offers the measurements behind them rather than asking to be
		// believed: which tag each pairing duplicates, at which versions.
		$evidence = Docs::is_visible()
			? sprintf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				esc_url( Docs::compatibility() ),
				esc_html__( 'See which tags each pairing actually duplicates', 'flexa-seo-aeo' )
			)
			: '';

		$robots = $this->robots_warning();

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p><p>%s</p>%s%s<p><a href="%s" class="button button-secondary">%s</a></p></div>',
			esc_html( $brand ),
			esc_html(
				sprintf(
					/* translators: %s: comma-separated list of SEO plugin names. */
					__( 'is running alongside %s.', 'flexa-seo-aeo' ),
					implode( ', ', $active )
				)
			),
			esc_html__( 'Two SEO plugins on one site means two canonical tags, two sets of Open Graph tags and often two JSON-LD graphs, and search engines pick between them. Run one and deactivate the other.', 'flexa-seo-aeo' ),
			// Built from esc_html() in robots_warning().
			$robots, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			// Assembled just above from esc_url() and esc_html__(); escaping the
			// whole string again would print the markup instead of rendering it.
			$evidence, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_url( $this->dismiss_url() ),
			esc_html__( 'I know, hide this', 'flexa-seo-aeo' )
		);
	}

	/**
	 * The one consequence of a pairing that is worse than a duplicate tag: a
	 * post set to noindex here can still be indexed. Said per plugin, because
	 * the advice differs: where the other plugin owns the tag, the setting has
	 * to be made there until it is switched off.
	 */
	private function robots_warning(): string {
		// Mid-sentence, so lower case under white-label.
		$brand = (bool) Settings::get( 'whitelabel' )
			? __( 'this plugin', 'flexa-seo-aeo' )
			: 'Flexa SEO';

		$html = '';

		foreach ( SeoConflicts::robots_replaced() as $name ) {
			$html .= '<p>' . esc_html(
				sprintf(
					/* translators: 1: an SEO plugin's name, 2: this plugin's name. */
					__( 'While %1$s is active, noindex and nofollow set in %2$s are not applied: it removes WordPress\'s robots output and prints its own robots tag. A post you set to noindex here can still be indexed. Set them in %1$s until you switch that plugin off.', 'flexa-seo-aeo' ),
					$name,
					$brand
				)
			) . '</p>';
		}

		$duplicated = SeoConflicts::robots_duplicated();
		if ( [] !== $duplicated ) {
			$html .= '<p>' . esc_html(
				sprintf(
					/* translators: %s: comma-separated list of SEO plugin names. */
					__( '%s prints a second robots tag, so a post set to noindex here carries one tag saying noindex and another saying index.', 'flexa-seo-aeo' ),
					implode( ', ', $duplicated )
				)
			) . '</p>';
		}

		return $html;
	}

	public function dismiss(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'flexa-seo-aeo' ), '', [ 'response' => 403 ] );
		}

		check_admin_referer( self::ACTION );

		update_option( self::OPTION_KEY, SeoConflicts::signature(), false );

		$referer = wp_get_referer();
		wp_safe_redirect( is_string( $referer ) && '' !== $referer ? $referer : admin_url() );
		exit;
	}

	private function dismiss_url(): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION );
	}
}
