<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Cli;

use Flexa\SeoAeo\Services\Migration\LegacyMapper;
use Flexa\SeoAeo\Services\Migration\Migrator;
use Flexa\SeoAeo\Support\Resetter;
use Flexa\SeoAeo\Support\Settings;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * WP-CLI commands for Flexa SEO.
 *
 *     wp flexa-seo-aeo status
 *     wp flexa-seo-aeo migrate --source=<yoast|rankmath|seopress|all> [--overwrite] [--dry-run]
 *     wp flexa-seo-aeo reset [--yes]
 *     wp flexa-seo-aeo ping
 */
final class PluginCommand {
	public static function register(): void {
		if ( ! class_exists( WP_CLI::class ) ) {
			return;
		}
		WP_CLI::add_command( 'flexa-seo-aeo', self::class );
	}

	/**
	 * Show which modules are enabled.
	 *
	 * @param array<int, string>    $args
	 * @param array<string, string> $assoc
	 * @when after_wp_load
	 */
	public function status( array $args, array $assoc ): void {
		unset( $args, $assoc );

		$aeo = Settings::get( 'aeo' );
		$aeo = is_array( $aeo ) ? $aeo : [];

		\WP_CLI\Utils\format_items(
			'table',
			[
				[
					'module'  => 'titles_metas',
					'enabled' => (bool) Settings::get( 'titles_metas' ) ? 'yes' : 'no',
				],
				[
					'module'  => 'open_graph',
					'enabled' => (bool) Settings::get( 'open_graph' ) ? 'yes' : 'no',
				],
				[
					'module'  => 'twitter_cards',
					'enabled' => (bool) Settings::get( 'twitter_cards' ) ? 'yes' : 'no',
				],
				[
					'module'  => 'xml_sitemap',
					'enabled' => (bool) Settings::get( 'xml_sitemap' ) ? 'yes' : 'no',
				],
				[
					'module'  => 'html_sitemap',
					'enabled' => (bool) Settings::get( 'html_sitemap' ) ? 'yes' : 'no',
				],
				[
					'module'  => 'breadcrumbs',
					'enabled' => (bool) Settings::get( 'breadcrumbs' ) ? 'yes' : 'no',
				],
				[
					'module'  => 'aeo.llms_txt',
					'enabled' => ! empty( $aeo['enabled'] ) ? 'yes' : 'no',
				],
				[
					'module'  => 'aeo.agent_readiness',
					'enabled' => ! empty( $aeo['agent_readiness'] ) ? 'yes' : 'no',
				],
			],
			[ 'module', 'enabled' ]
		);
	}

	/**
	 * Import per-post SEO meta from another plugin into Flexa SEO.
	 *
	 * ## OPTIONS
	 *
	 * [--source=<source>]
	 * : Which plugin to migrate from. One of: yoast, rankmath, seopress, all.
	 * ---
	 * default: all
	 * options:
	 *   - yoast
	 *   - rankmath
	 *   - seopress
	 *   - all
	 * ---
	 *
	 * [--overwrite]
	 * : Overwrite existing Flexa values. By default only empty fields are filled.
	 *
	 * [--dry-run]
	 * : Report how many posts each source has, without writing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp flexa-seo-aeo migrate --source=yoast
	 *     wp flexa-seo-aeo migrate --source=all --overwrite
	 *
	 * @param array<int, string>    $args
	 * @param array<string, string> $assoc
	 * @when after_wp_load
	 */
	public function migrate( array $args, array $assoc ): void {
		unset( $args );

		$source    = isset( $assoc['source'] ) ? (string) $assoc['source'] : 'all';
		$overwrite = isset( $assoc['overwrite'] );
		$dry_run   = isset( $assoc['dry-run'] );

		$sources = 'all' === $source ? LegacyMapper::source_ids() : [ $source ];
		foreach ( $sources as $candidate ) {
			if ( ! LegacyMapper::is_source( $candidate ) ) {
				WP_CLI::error( sprintf( 'Unknown source "%s". Use yoast, rankmath, seopress, or all.', $candidate ) );
			}
		}

		$migrator = Migrator::instance();

		if ( $dry_run ) {
			foreach ( $migrator->sources() as $info ) {
				if ( in_array( $info['id'], $sources, true ) ) {
					WP_CLI::log( sprintf( '%s: %d post(s) with data to migrate.', $info['label'], $info['count'] ) );
				}
			}
			WP_CLI::success( 'Dry run complete. Nothing was written.' );
			return;
		}

		foreach ( $sources as $candidate ) {
			$label    = LegacyMapper::label( $candidate );
			$offset   = 0;
			$migrated = 0;
			$skipped  = 0;

			do {
				$result = $migrator->migrate( $candidate, $overwrite, $offset, 100 );

				$migrated += $result['migrated'];
				$skipped  += $result['skipped'];
				$offset    = $result['next_offset'];

				if ( $result['total'] > 0 ) {
					WP_CLI::log( sprintf( 'Migrating %s: %d/%d', $label, min( $offset, $result['total'] ), $result['total'] ) );
				}
			} while ( ! $result['done'] );

			WP_CLI::success( sprintf( '%s: migrated %d, skipped %d.', $label, $migrated, $skipped ) );
		}
	}

	/**
	 * Wipe all Flexa SEO settings (danger zone). Routes through the shared
	 * Resetter so CLI and the REST danger zone never drift.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * @param array<int, string>    $args
	 * @param array<string, string> $assoc
	 * @when after_wp_load
	 */
	public function reset( array $args, array $assoc ): void {
		unset( $args );

		WP_CLI::confirm( 'This will delete all Flexa SEO settings. Continue?', $assoc );

		$result = Resetter::reset_all();
		WP_CLI::success(
			$result['settings_removed']
				? 'Flexa SEO settings removed.'
				: 'No Flexa SEO settings were stored.'
		);
	}

	/**
	 * Ping. Smoke-test that the command is wired.
	 *
	 * @param array<int, string>    $args
	 * @param array<string, string> $assoc
	 * @when after_wp_load
	 */
	public function ping( array $args, array $assoc ): void {
		unset( $args, $assoc );
		WP_CLI::success( sprintf( 'Flexa SEO v%s is alive.', FLEXA_SEO_AEO_VERSION ) );
	}
}
