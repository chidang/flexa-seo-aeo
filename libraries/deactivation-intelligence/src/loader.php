<?php
/**
 * Deactivation Intelligence - version-negotiating loader.
 *
 * Several Flexa plugins each bundle their own copy of this SDK, but the client
 * is a single global class, so exactly one copy can define it. That copy also
 * owns the asset URLs for every product on the site, because the enqueue
 * resolves them from `plugin_dir_url( __FILE__ )`, which binds at definition
 * time. Requiring the class file directly therefore hands the whole site to
 * whichever plugin happens to load first (alphabetical plugin directory order),
 * and one stale copy can hold back every other plugin's SDK features
 * indefinitely, with no symptom other than the new behaviour never appearing.
 *
 * This file fixes the ordering. Including it only *offers* a copy: the version
 * and path are recorded, nothing is defined. At `plugins_loaded` the highest
 * registered version is loaded, once, and every queued product is initialized
 * against it. Deferring is safe because the SDK does all of its work on
 * `admin_enqueue_scripts` and `admin_init`, both far later.
 *
 * Host plugins require THIS file, never the class file, and require it at
 * plugin-file load time so the copy takes part in the vote:
 *
 *   require_once __DIR__ . '/libraries/deactivation-intelligence/src/loader.php';
 *
 *   add_action( 'plugins_loaded', function () {
 *       deactivation_intelligence_init( array(
 *           'product'     => 'flexa',
 *           'tier'        => 'free',
 *           'version'     => FLEXA_VERSION,
 *           'plugin_file' => plugin_basename( __FILE__ ),
 *           'api_url'     => 'https://product-intelligence.flexacommerce.com',
 *       ) );
 *   } );
 *
 * `deactivation_intelligence_init()` is safe to call at any point, before or
 * after the winner is chosen.
 *
 * Migration note: a copy old enough to predate this loader still requires the
 * class file directly, at its own load time, and so can still win by being
 * early. When that happens the queued products are initialized against that
 * older class and simply miss anything added since. The negotiation becomes
 * authoritative for a site once every Flexa plugin on it ships this loader.
 *
 * Keep this file dumb and stable. Every bundled copy defines the same three
 * functions and the first one included wins, so a behaviour change here would
 * reintroduce the exact "oldest copy decides" problem the file exists to solve.
 * New features belong in the class.
 *
 * @package DeactivationIntelligence
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- same contract as the
// class itself: these three functions, the registry global and the version constant are
// the shared handshake between every plugin's bundled copy, so they cannot carry any one
// plugin's prefix. The `deactivation_intelligence_` prefix is the SDK's own.

if ( ! function_exists( 'deactivation_intelligence_register' ) ) {

	/**
	 * Offer a copy of the SDK as a candidate. The highest version wins; ties go
	 * to whichever copy registered first.
	 *
	 * @param string $version Semantic version of this copy.
	 * @param string $file    Absolute path to its class file.
	 * @return void
	 */
	function deactivation_intelligence_register( $version, $file ) {
		if ( ! isset( $GLOBALS['deactivation_intelligence_sdk'] ) ) {
			$GLOBALS['deactivation_intelligence_sdk'] = array(
				'candidates' => array(),
				'queue'      => array(),
				'loaded'     => null,
			);
		}

		// Keyed by path so a plugin that includes the loader twice (and WordPress
		// loading the same plugin through a symlink) counts once.
		$GLOBALS['deactivation_intelligence_sdk']['candidates'][ (string) $file ] = (string) $version;
	}

	/**
	 * Register a product with the SDK.
	 *
	 * @param array $config See Deactivation_Intelligence::init().
	 * @return void
	 */
	function deactivation_intelligence_init( array $config ) {
		if ( ! isset( $GLOBALS['deactivation_intelligence_sdk'] ) ) {
			return;
		}

		$GLOBALS['deactivation_intelligence_sdk']['queue'][] = $config;

		// `plugins_loaded` is the first moment every plugin file has been
		// included, so it is the earliest the winner is knowable. Once it has
		// fired there is nothing left to wait for, and a host that initializes
		// late (conditionally, or from `init`) must not be dropped.
		if ( did_action( 'plugins_loaded' ) ) {
			deactivation_intelligence_boot();
		}
	}

	/**
	 * Load the winning copy and flush the queued products into it.
	 *
	 * @return void
	 */
	function deactivation_intelligence_boot() {
		if ( ! isset( $GLOBALS['deactivation_intelligence_sdk'] ) ) {
			return;
		}

		$state = &$GLOBALS['deactivation_intelligence_sdk'];

		if ( null === $state['loaded'] ) {
			if ( class_exists( 'Deactivation_Intelligence' ) ) {
				// A copy that predates this loader required the class file
				// directly and got there first. Nothing to choose any more: use
				// what is defined and accept that newer features are absent.
				$state['loaded'] = 'external';
			} else {
				$winner = '';
				$best   = '';

				foreach ( $state['candidates'] as $file => $version ) {
					if ( '' === $winner || version_compare( $version, $best, '>' ) ) {
						$winner = $file;
						$best   = $version;
					}
				}

				if ( '' !== $winner && is_readable( $winner ) ) {
					if ( ! defined( 'DEACTIVATION_INTELLIGENCE_VERSION' ) ) {
						define( 'DEACTIVATION_INTELLIGENCE_VERSION', $best );
					}
					require_once $winner;
					$state['loaded'] = $winner;
				}
			}
		}

		if ( ! class_exists( 'Deactivation_Intelligence' ) ) {
			// No readable copy, or a broken one. Leave wp-admin alone: the
			// native Deactivate link keeps working, which is the whole contract.
			return;
		}

		while ( $state['queue'] ) {
			Deactivation_Intelligence::init( array_shift( $state['queue'] ) );
		}
	}

	add_action( 'plugins_loaded', 'deactivation_intelligence_boot', -9999 );
}

// This copy's offer. The version lives here, not in the class, so that the
// winning loader can hand it to the class it loads (see the define() above).
deactivation_intelligence_register( '1.1.0', __DIR__ . '/class-deactivation-intelligence.php' );

// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals
