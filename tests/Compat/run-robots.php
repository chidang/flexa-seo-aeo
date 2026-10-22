<?php
/**
 * Live check of the robots fixtures against a real WordPress install.
 *
 * For each case in fixtures/robots.json this activates the competitor next to
 * Flexa, sets up the configuration on a throwaway post, requests the post over
 * HTTP and compares every robots tag on the page with the fixture's
 * assertions. It runs outside WordPress and drives the site through WP-CLI,
 * because a plugin activated inside a PHP process is not loaded by it.
 *
 * The site is changed while this runs: plugins are switched on and off, a post
 * is created, rewrite rules are flushed. Everything is put back at the end,
 * including on Ctrl-C. Run it against a development site, never production.
 *
 *     php tests/Compat/run-robots.php --path=/path/to/wordpress [--wp="wp"]
 *         [--only=aioseo] [--allow-drift] [--write]
 *
 * --wp           Command that runs WP-CLI. Defaults to $FLEXA_COMPAT_WP, then `wp`.
 * --path         WordPress root, passed to every WP-CLI call.
 * --only         Run only the cases whose id starts with this.
 * --allow-drift  Run a case even when the installed competitor version is not
 *                the one the case was written for. The result is labelled.
 * --write        Append this run to each case's `observed` list.
 *
 * Exit status is 1 when a case fails without a known failure, when a known
 * failure starts passing (the fixture needs updating), or on an error.
 *
 * @package Flexa\SeoAeo
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.PHP.DiscouragedPHPFunctions, WordPress.WP.GlobalVariablesOverride, WordPress.NamingConventions.PrefixAllGlobals, Generic.Files.OneObjectStructurePerFile, Universal.Files.SeparateFunctionsFromOO -- A single-file CLI script run without WordPress loaded.

declare(strict_types=1);

namespace Flexa\SeoAeo\Tests\Compat;

use DOMDocument;
use DOMXPath;
use RuntimeException;

const FIXTURE      = __DIR__ . '/fixtures/robots.json';
const FLEXA_SLUG   = 'flexa-seo-aeo';
const FLEXA_MARKER = '<!-- Flexa SEO -->';

/**
 * Thin wrapper over the WP-CLI binary. PHP notices from the site go to
 * stderr and are dropped, so stdout carries only the command's answer.
 */
final class Wp {
	public function __construct( private string $command, private string $path ) {
	}

	/**
	 * @param list<string> $args
	 */
	public function run( array $args ): string {
		$line = $this->command . ' ' . implode( ' ', array_map( 'escapeshellarg', $args ) )
			. ' --path=' . escapeshellarg( $this->path ) . ' 2>/dev/null';

		$output = [];
		$status = 0;
		exec( $line, $output, $status );

		if ( 0 !== $status ) {
			throw new RuntimeException( 'WP-CLI failed (' . $status . '): wp ' . implode( ' ', $args ) );
		}

		return trim( implode( "\n", $output ) );
	}

	/**
	 * Run a command whose failure is an expected answer, such as deleting a
	 * meta key that was never set.
	 *
	 * @param list<string> $args
	 */
	public function attempt( array $args ): void {
		try {
			$this->run( $args );
		} catch ( RuntimeException ) {
			return;
		}
	}

	/**
	 * @param list<string> $args
	 */
	public function json( array $args ): mixed {
		return json_decode( $this->run( [ ...$args, '--format=json' ] ), true, 512, JSON_THROW_ON_ERROR );
	}
}

/**
 * What one request returned, reduced to the robots signals.
 */
final class Observation {
	/**
	 * @param list<string> $tags      The content of each robots meta tag, as printed.
	 * @param list<string> $effective Every directive from every tag, normalised and sorted.
	 */
	public function __construct(
		public readonly int $status,
		public readonly array $tags,
		public readonly array $effective,
		public readonly ?string $x_robots_tag,
		public readonly bool $flexa_printed,
		public readonly ?string $cache_hit,
	) {
	}

	public function effective_line(): string {
		return implode( ', ', $this->effective );
	}
}

/**
 * Request a URL and pull out the robots tags.
 */
function observe( string $url ): Observation {
	$headers = [];
	$curl    = curl_init( $url );

	curl_setopt_array(
		$curl,
		[
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_HTTPHEADER     => [ 'Cache-Control: no-cache', 'Pragma: no-cache' ],
			CURLOPT_HEADERFUNCTION => static function ( $handle, string $header ) use ( &$headers ): int {
				$parts = explode( ':', $header, 2 );
				if ( 2 === count( $parts ) ) {
					$headers[ strtolower( trim( $parts[0] ) ) ] = trim( $parts[1] );
				}

				return strlen( $header );
			},
		]
	);

	$html   = curl_exec( $curl );
	$status = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );

	if ( ! is_string( $html ) ) {
		throw new RuntimeException( 'Request failed: ' . $url . ' (' . curl_error( $curl ) . ')' );
	}

	$tags = robots_tags( $html );

	$effective = [];
	foreach ( $tags as $content ) {
		foreach ( directives( $content ) as $directive ) {
			$effective[ $directive ] = true;
		}
	}
	$effective = array_keys( $effective );
	sort( $effective );

	$cache_hit = null;
	foreach ( [ 'x-cache', 'x-flexa-cache', 'cf-cache-status', 'x-litespeed-cache' ] as $name ) {
		if ( isset( $headers[ $name ] ) && str_contains( strtolower( $headers[ $name ] ), 'hit' ) ) {
			$cache_hit = $name . ': ' . $headers[ $name ];
		}
	}

	return new Observation(
		$status,
		$tags,
		$effective,
		$headers['x-robots-tag'] ?? null,
		str_contains( $html, FLEXA_MARKER ),
		$cache_hit
	);
}

/**
 * @return list<string> The content attribute of every `<meta name="robots">`.
 */
function robots_tags( string $html ): array {
	$dom      = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	$tags = [];
	$path = "//meta[translate(@name, 'ROBTS', 'robts') = 'robots']";
	foreach ( ( new DOMXPath( $dom ) )->query( $path ) as $node ) {
		$tags[] = (string) $node->getAttribute( 'content' );
	}

	return $tags;
}

/**
 * Split a robots value the way `wp_robots()` prints it: comma separated,
 * `key:value` for a valued hint. Lower case, no stray spaces.
 *
 * @return list<string>
 */
function directives( string $content ): array {
	$out = [];
	foreach ( explode( ',', $content ) as $part ) {
		$part = strtolower( trim( $part ) );
		$part = (string) preg_replace( '/\s*:\s*/', ':', $part );
		if ( '' !== $part ) {
			$out[] = $part;
		}
	}

	return $out;
}

/**
 * Hold an observation against the case's assertions.
 *
 * @param array<string, mixed> $fixture_case
 * @param array<string, mixed> $assert
 * @return list<string> Why it fails; empty when it passes.
 */
function failures( array $fixture_case, array $assert, Observation $seen ): array {
	$why = [];

	if ( count( $seen->tags ) !== $assert['tag_count'] ) {
		$why[] = sprintf( 'expected %d robots tag(s), found %d', $assert['tag_count'], count( $seen->tags ) );
	}

	foreach ( $assert['must_include'] as $directive ) {
		if ( ! in_array( $directive, $seen->effective, true ) ) {
			$why[] = 'missing ' . $directive;
		}
	}

	foreach ( $assert['must_not_include'] as $directive ) {
		if ( in_array( $directive, $seen->effective, true ) ) {
			$why[] = 'should not carry ' . $directive;
		}
	}

	if ( isset( $fixture_case['exact'] ) && $fixture_case['exact'] !== $seen->effective_line() ) {
		$why[] = 'expected exactly "' . $fixture_case['exact'] . '"';
	}

	return $why;
}

/**
 * @return array<string, string|bool>
 */
function options( array $argv ): array {
	$opts = [
		'wp'          => 'wp',
		'path'        => '',
		'only'        => '',
		'allow-drift' => false,
		'write'       => false,
	];

	$env = getenv( 'FLEXA_COMPAT_WP' );
	if ( is_string( $env ) && '' !== $env ) {
		$opts['wp'] = $env;
	}

	foreach ( array_slice( $argv, 1 ) as $arg ) {
		if ( ! str_starts_with( $arg, '--' ) ) {
			throw new RuntimeException( 'Unexpected argument: ' . $arg );
		}

		$parts = explode( '=', substr( $arg, 2 ), 2 );
		if ( ! array_key_exists( $parts[0], $opts ) ) {
			throw new RuntimeException( 'Unknown option: --' . $parts[0] );
		}

		$opts[ $parts[0] ] = $parts[1] ?? true;
	}

	if ( '' === $opts['path'] ) {
		throw new RuntimeException( '--path=<WordPress root> is required' );
	}

	return $opts;
}

/**
 * Re-encode the fixture with tab indents, the way it is kept in the repo.
 */
function save_fixture( array $fixture ): void {
	// Decoded as arrays, an empty map comes back as a list. Keep it a map.
	foreach ( $fixture['configurations'] as $name => $config ) {
		if ( [] === $config['post_meta'] ) {
			$fixture['configurations'][ $name ]['post_meta'] = new \stdClass();
		}
	}

	$json = json_encode( $fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR );
	$json = (string) preg_replace_callback(
		'/^(?: {4})+/m',
		static fn( array $m ): string => str_repeat( "\t", intdiv( strlen( $m[0] ), 4 ) ),
		$json
	);

	file_put_contents( FIXTURE, $json . "\n" );
}

function main( array $argv ): int {
	$opts    = options( $argv );
	$wp      = new Wp( (string) $opts['wp'], (string) $opts['path'] );
	$fixture = json_decode( (string) file_get_contents( FIXTURE ), true, 512, JSON_THROW_ON_ERROR );

	$run = [
		'run'       => gmdate( 'Y-m-d' ),
		'flexa'     => $wp->run( [ 'plugin', 'get', FLEXA_SLUG, '--field=version' ] ),
		'wordpress' => $wp->run( [ 'core', 'version' ] ),
	];

	printf( "Flexa %s on WordPress %s, %s\n\n", $run['flexa'], $run['wordpress'], $run['run'] );

	// The assertions describe Flexa's output, so Flexa has to be printing it.
	// Switching the module on here would be a settings change the run does not
	// own, so a site with it off is refused instead.
	$titles_on = $wp->run( [ 'eval', 'echo \Flexa\SeoAeo\Support\Settings::get( "titles_metas" ) ? "1" : "0";' ] );
	if ( '1' !== $titles_on ) {
		throw new RuntimeException( 'Titles & Metas is off in Flexa; switch it on before running.' );
	}

	$installed = [];
	foreach ( $wp->json( [ 'plugin', 'list', '--fields=name,status,version' ] ) as $plugin ) {
		$installed[ $plugin['name'] ] = $plugin;
	}

	$competitors = [];
	foreach ( $fixture['cases'] as $case ) {
		if ( null !== $case['competitor'] ) {
			$competitors[ $case['competitor']['slug'] ] = true;
		}
	}

	$was_active = array_values(
		array_filter(
			array_keys( $competitors ),
			static fn( string $slug ): bool => 'active' === ( $installed[ $slug ]['status'] ?? '' )
		)
	);

	$post_id = 0;
	$current = null;

	$restore = static function () use ( $wp, &$post_id, &$current, $was_active ): void {
		if ( null !== $current ) {
			$wp->run( [ 'plugin', 'deactivate', $current ] );
			$current = null;
		}
		if ( [] !== $was_active ) {
			$wp->run( [ 'plugin', 'activate', ...$was_active ] );
		}
		if ( $post_id > 0 ) {
			$wp->run( [ 'post', 'delete', (string) $post_id, '--force' ] );
			$post_id = 0;
		}
		$wp->run( [ 'rewrite', 'flush' ] );
	};

	if ( function_exists( 'pcntl_async_signals' ) ) {
		pcntl_async_signals( true );
		$stop = static function (): void {
			throw new RuntimeException( 'Interrupted' );
		};
		pcntl_signal( SIGINT, $stop );
		pcntl_signal( SIGTERM, $stop );
	}

	$bad = 0;

	try {
		if ( [] !== $was_active ) {
			$wp->run( [ 'plugin', 'deactivate', ...$was_active ] );
		}

		$post_id = (int) $wp->run(
			[
				'post',
				'create',
				'--post_type=post',
				'--post_status=publish',
				'--post_title=Flexa compat robots fixture',
				'--post_content=A post that exists only while the robots compatibility run is going.',
				'--porcelain',
			]
		);
		$url     = $wp->run( [ 'post', 'list', '--post__in=' . $post_id, '--post_type=post', '--field=url' ] );

		foreach ( $fixture['cases'] as $i => $case ) {
			if ( '' !== $opts['only'] && ! str_starts_with( $case['id'], (string) $opts['only'] ) ) {
				continue;
			}

			$slug  = $case['competitor']['slug'] ?? null;
			$label = '';

			if ( null !== $slug ) {
				if ( ! isset( $installed[ $slug ] ) ) {
					printf( "SKIP   %s  (%s is not installed)\n", $case['id'], $slug );
					continue;
				}

				$have = $installed[ $slug ]['version'];
				if ( $have !== $case['competitor']['version'] ) {
					if ( ! $opts['allow-drift'] ) {
						printf( "SKIP   %s  (installed %s, case is for %s)\n", $case['id'], $have, $case['competitor']['version'] );
						continue;
					}
					$label = sprintf( ' [ran against %s]', $have );
				}
			}

			if ( $current !== $slug ) {
				if ( null !== $current ) {
					$wp->run( [ 'plugin', 'deactivate', $current ] );
					$current = null;
				}
				if ( null !== $slug ) {
					$wp->run( [ 'plugin', 'activate', $slug ] );
					$current = $slug;
				}
				$wp->run( [ 'rewrite', 'flush' ] );
			}

			$config = $fixture['configurations'][ $case['configuration'] ];
			foreach ( $fixture['configurations'] as $other ) {
				foreach ( array_keys( $other['post_meta'] ) as $key ) {
					$wp->attempt( [ 'post', 'meta', 'delete', (string) $post_id, $key ] );
				}
			}
			foreach ( $config['post_meta'] as $key => $value ) {
				$wp->run( [ 'post', 'meta', 'update', (string) $post_id, $key, (string) $value ] );
			}

			$seen = observe( $url );
			$why  = failures( $case, $fixture['assertions'][ $case['configuration'] ], $seen );

			if ( 200 !== $seen->status ) {
				$why[] = 'HTTP ' . $seen->status;
			}
			if ( ! $seen->flexa_printed ) {
				$why[] = 'Flexa\'s head output is missing from the page';
			}

			$known = null !== $case['known_failure'];
			$state = match ( true ) {
				[] === $why && ! $known => 'PASS',
				[] === $why && $known   => 'FIXED',
				$known                  => 'KNOWN',
				default                 => 'FAIL',
			};

			if ( 'FIXED' === $state || 'FAIL' === $state ) {
				++$bad;
			}

			printf( "%-6s %s%s\n", $state, $case['id'], $label );
			foreach ( $seen->tags as $n => $content ) {
				printf( "         tag %d: %s\n", $n + 1, $content );
			}
			if ( [] === $seen->tags ) {
				echo "         no robots tag\n";
			}
			if ( null !== $seen->x_robots_tag ) {
				echo '         X-Robots-Tag: ' . $seen->x_robots_tag . "\n";
			}
			if ( null !== $seen->cache_hit ) {
				echo '         warning, served from cache (' . $seen->cache_hit . ")\n";
			}
			foreach ( $why as $reason ) {
				echo '         - ' . $reason . "\n";
			}
			if ( 'KNOWN' === $state ) {
				echo '         known: ' . $case['known_failure'] . "\n";
			}
			if ( 'FIXED' === $state ) {
				echo "         passes now; clear known_failure in the fixture\n";
			}

			if ( $opts['write'] ) {
				$entry = $run + [
					'tag_count' => count( $seen->tags ),
					'effective' => $seen->effective_line(),
					'tags'      => $seen->tags,
				];
				if ( '' !== $label ) {
					$entry['competitor_version'] = $installed[ $slug ]['version'];
				}
				$fixture['cases'][ $i ]['observed'][] = $entry;
			}
		}
	} finally {
		$restore();
	}

	if ( $opts['write'] ) {
		save_fixture( $fixture );
		echo "\nObservations appended to " . FIXTURE . "\n";
	}

	echo "\nCompetitors ran with whatever settings this site already holds for them.\n";

	return $bad > 0 ? 1 : 0;
}

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

try {
	exit( main( $argv ) );
} catch ( \Throwable $e ) {
	fwrite( STDERR, 'Error: ' . $e->getMessage() . "\n" );
	exit( 1 );
}
