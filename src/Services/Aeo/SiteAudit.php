<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Services\Aeo;

use Flexa\SeoAeo\Services\Sitemap as SitemapService;
use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;
use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Site-wide SEO/AEO health aggregator powering the admin Dashboard.
 *
 * This deliberately reuses what the plugin already computes rather than building
 * a second analytics stack: the AEO side is the mean of {@see Readiness} run over
 * the most-recently-modified posts, and the technical side is a checklist derived
 * from the global {@see Settings} toggles. A batch scan is bounded (defaults to 50
 * posts) and cached in a transient; the block-editor-style "Scan now" action is
 * the only thing that recomputes and appends a lightweight trend snapshot. There
 * is no cron and no per-request tracking — the dashboard answers "how healthy is
 * my site, what is wrong, and what should I fix next?" from data on hand.
 */
final class SiteAudit {
	use SingletonTrait;

	private const CACHE_KEY = 'flexa_seo_aeo_dashboard_report';
	private const TREND_KEY = 'flexa_seo_aeo_dashboard_trend';

	/** How long a computed report stays cached before a plain GET recomputes it. */
	private const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Report array-shape version. Bump whenever the compute() payload changes so a
	 * report cached by an older build (e.g. one without per-page `checks`) is
	 * treated as a miss and recomputed, instead of serving a stale shape.
	 */
	private const SCHEMA_VERSION = 2;

	/** How many trend snapshots to retain (oldest dropped). */
	private const TREND_MAX = 12;

	/** Grade cut-offs, high to low — mirrors {@see Readiness}. */
	private const GRADES = [
		'excellent' => 85,
		'good'      => 70,
		'fair'      => 50,
		'poor'      => 0,
	];

	/**
	 * Return the dashboard report, using the cached compute when available.
	 *
	 * @return array<string, mixed>
	 */
	public function report(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && self::SCHEMA_VERSION === ( $cached['schema'] ?? null ) ) {
			$cached['trend'] = $this->trend();
			return $cached;
		}

		$report = $this->compute();
		set_transient( self::CACHE_KEY, $report, self::CACHE_TTL );

		$report['trend'] = $this->trend();
		return $report;
	}

	/**
	 * Drop the cached report so the next read recomputes. Hooked on settings
	 * updates: the technical checklist is derived from the settings toggles, so a
	 * flipped toggle must not read as stale on the dashboard.
	 */
	public static function flush(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Remove all dashboard state — cached report and the trend log. Hooked on the
	 * plugin's data-reset so the danger zone / CLI reset leaves nothing behind.
	 */
	public static function purge(): void {
		delete_transient( self::CACHE_KEY );
		delete_option( self::TREND_KEY );
	}

	/**
	 * Force a fresh scan: recompute, refresh the cache, and append a trend point.
	 *
	 * @return array<string, mixed>
	 */
	public function scan(): array {
		$report = $this->compute();
		set_transient( self::CACHE_KEY, $report, self::CACHE_TTL );

		$report['trend'] = $this->push_trend(
			(int) $report['seo_score'],
			(int) $report['aeo_score'],
			(int) $report['technical_score']
		);

		return $report;
	}

	/**
	 * Run the batch scan + aggregation. Pure w.r.t. the trend log (which is
	 * merged in by the callers) so it can back both the cached and forced paths.
	 *
	 * @return array<string, mixed>
	 */
	private function compute(): array {
		$posts     = $this->collect_posts();
		$readiness = Readiness::instance();

		/** @var list<array{id:int,title:string,url:string,edit_url:string,score:int,grade:string,issues:int,checks:list<array{id:string,label:string,status:string,hint:string,fix:array{label:string,scope:string,patch:array<string,mixed>}|null,evidence:string}>}> $pages */
		$pages = [];
		/** @var array<string,array{pass:int,warn:int,fail:int}> $tally */
		$tally     = [];
		$score_sum = 0;

		foreach ( $posts as $post ) {
			$rep        = $readiness->report( $post );
			$score_sum += (int) $rep['score'];

			$issues = 0;
			/** @var list<array{id:string,label:string,status:string,hint:string,fix:array{label:string,scope:string,patch:array<string,mixed>}|null,evidence:string}> $failing */
			$failing = [];
			foreach ( $rep['checks'] as $check ) {
				$id     = (string) $check['id'];
				$status = (string) $check['status'];
				if ( ! isset( $tally[ $id ] ) ) {
					$tally[ $id ] = [
						'pass' => 0,
						'warn' => 0,
						'fail' => 0,
					];
				}
				if ( isset( $tally[ $id ][ $status ] ) ) {
					++$tally[ $id ][ $status ];
				}
				if ( 'pass' !== $status ) {
					++$issues;
					// Carry the exact failing/​warning checks (with their fix hint
					// and any one-click site-wide remedy) so the dashboard can say
					// *what* is wrong and *how* to fix it, not just a count. Sorted
					// fail-before-warn below.
					$failing[] = [
						'id'       => $id,
						'label'    => (string) $check['label'],
						'status'   => $status,
						'hint'     => (string) $check['hint'],
						'fix'      => $check['fix'] ?? null,
						'evidence' => (string) ( $check['evidence'] ?? 'heuristic' ),
					];
				}
			}

			usort(
				$failing,
				fn( array $a, array $b ): int => $this->severity_rank( $a['status'] ) <=> $this->severity_rank( $b['status'] )
			);

			$pages[] = [
				'id'       => (int) $post->ID,
				'title'    => (string) get_the_title( $post ),
				'url'      => (string) get_permalink( $post ),
				'edit_url' => (string) get_edit_post_link( $post->ID, 'raw' ),
				'score'    => (int) $rep['score'],
				'grade'    => (string) $rep['grade'],
				'issues'   => $issues,
				'checks'   => $failing,
			];
		}

		$scanned   = count( $pages );
		$aeo_score = $scanned > 0 ? (int) round( $score_sum / $scanned ) : 0;

		$technical       = $this->technical_checks();
		$technical_score = $this->technical_score( $technical );

		// Overall SEO score blends the config-completeness (technical) side with
		// the content (AEO) side; when nothing has been scanned yet it is just the
		// technical baseline so the headline number is never misleadingly zero.
		$seo_score = $scanned > 0
			? (int) round( ( $technical_score + $aeo_score ) / 2 )
			: $technical_score;

		return [
			'schema'          => self::SCHEMA_VERSION,
			'generated_at'    => time(),
			'scanned'         => $scanned,
			'scan_limit'      => $this->scan_limit(),
			'seo_score'       => $seo_score,
			'seo_grade'       => $this->grade( $seo_score ),
			'aeo_score'       => $aeo_score,
			'aeo_grade'       => $this->grade( $aeo_score ),
			'technical_score' => $technical_score,
			'technical_grade' => $this->grade( $technical_score ),
			'issues'          => $this->issues( $tally, $technical ),
			'seo_health'      => $this->seo_health( $technical ),
			'aeo_health'      => $this->aeo_health( $tally, $scanned ),
			'recommendations' => $this->recommendations( $technical, $tally, $scanned ),
			'pages'           => $this->attention_pages( $pages ),
		];
	}

	/**
	 * The most-recently-modified published posts across the sitemap post types,
	 * capped so an on-demand scan never runs unbounded on a large site.
	 *
	 * Same exclusions as the sitemap, through the same method: a page you marked
	 * noindex or put behind a password is one you have decided search engines
	 * will not see, so grading its title and description only pushes the site
	 * score around for work nobody can act on.
	 *
	 * @return list<WP_Post>
	 */
	private function collect_posts(): array {
		$sitemap = SitemapService::instance();
		$types   = $sitemap->post_types();
		if ( [] === $types ) {
			return [];
		}

		$query = new WP_Query(
			$sitemap->exclude_noindexed(
				[
					'post_type'              => $types,
					'post_status'            => 'publish',
					'posts_per_page'         => $this->scan_limit(),
					'orderby'                => 'modified',
					'order'                  => 'DESC',
					'ignore_sticky_posts'    => true,
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
				]
			)
		);

		$out = [];
		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$out[] = $post;
			}
		}

		return $out;
	}

	private function scan_limit(): int {
		/**
		 * Filters how many posts a single dashboard scan analyses. Kept modest so
		 * the scan stays a quick, on-demand action rather than a background job.
		 *
		 * @param int $limit
		 */
		$limit = (int) apply_filters( 'flexa_seo_aeo/dashboard/scan_limit', 50 );

		return max( 1, min( 500, $limit ) );
	}

	/**
	 * The global technical checklist, derived purely from settings toggles. Each
	 * row is a single, binary configuration signal the dashboard can score, group,
	 * count, and turn into a recommendation.
	 *
	 * @return list<array{id:string,label:string,on:bool,group:string,severity:string,section:string,hint:string}>
	 */
	private function technical_checks(): array {
		$checks = [
			[
				'id'       => 'titles_metas',
				'label'    => __( 'Titles & meta output', 'flexa-seo-aeo' ),
				'on'       => (bool) Settings::get( 'titles_metas' ),
				'group'    => 'content',
				'severity' => 'critical',
				'section'  => 'general',
				'hint'     => __( 'Turn on Titles & meta so every page ships a <title> and meta description.', 'flexa-seo-aeo' ),
			],
			[
				'id'       => 'xml_sitemap',
				'label'    => __( 'XML sitemap', 'flexa-seo-aeo' ),
				'on'       => (bool) Settings::get( 'xml_sitemap' ),
				'group'    => 'technical',
				'severity' => 'critical',
				'section'  => 'sitemaps',
				'hint'     => __( 'Enable the XML sitemap so search and answer engines can discover every post.', 'flexa-seo-aeo' ),
			],
			[
				'id'       => 'aeo_schema',
				'label'    => __( 'Structured data (JSON-LD)', 'flexa-seo-aeo' ),
				'on'       => (bool) Settings::get_aeo( 'schema' ),
				'group'    => 'schema',
				'severity' => 'critical',
				'section'  => 'aeo',
				'hint'     => __( 'Enable AEO structured data so engines can lift facts out as JSON-LD.', 'flexa-seo-aeo' ),
			],
			[
				'id'       => 'open_graph',
				'label'    => __( 'Open Graph tags', 'flexa-seo-aeo' ),
				'on'       => (bool) Settings::get( 'open_graph' ),
				'group'    => 'content',
				'severity' => 'warning',
				'section'  => 'social',
				'hint'     => __( 'Turn on Open Graph so shared links render a rich preview.', 'flexa-seo-aeo' ),
			],
			[
				'id'       => 'twitter_cards',
				'label'    => __( 'X (Twitter) Cards', 'flexa-seo-aeo' ),
				'on'       => (bool) Settings::get( 'twitter_cards' ),
				'group'    => 'content',
				'severity' => 'opportunity',
				'section'  => 'social',
				'hint'     => __( 'Enable X Cards for richer previews when content is shared on X.', 'flexa-seo-aeo' ),
			],
			[
				'id'       => 'breadcrumbs',
				'label'    => __( 'Breadcrumbs', 'flexa-seo-aeo' ),
				'on'       => (bool) Settings::get( 'breadcrumbs' ),
				'group'    => 'content',
				'severity' => 'opportunity',
				'section'  => 'general',
				'hint'     => __( 'Enable breadcrumbs to add BreadcrumbList schema and clearer navigation.', 'flexa-seo-aeo' ),
			],
			[
				'id'       => 'image_seo_alt',
				'label'    => __( 'Image alt text', 'flexa-seo-aeo' ),
				'on'       => (bool) Settings::get( 'image_seo_alt' ),
				'group'    => 'content',
				'severity' => 'opportunity',
				'section'  => 'general',
				'hint'     => __( 'Auto-fill missing image alt text so images are described for search and screen readers.', 'flexa-seo-aeo' ),
			],
			[
				'id'       => 'indexnow',
				'label'    => __( 'IndexNow', 'flexa-seo-aeo' ),
				'on'       => (bool) Settings::get( 'indexnow' ),
				'group'    => 'technical',
				'severity' => 'opportunity',
				'section'  => 'indexing',
				'hint'     => __( 'Turn on IndexNow to push near-instant recrawl signals on publish and update.', 'flexa-seo-aeo' ),
			],
			[
				'id'       => 'llms_txt',
				'label'    => __( 'llms.txt', 'flexa-seo-aeo' ),
				'on'       => (bool) Settings::get_aeo( 'enabled' ),
				'group'    => 'schema',
				'severity' => 'opportunity',
				'section'  => 'aeo',
				'hint'     => __( 'Serve /llms.txt so AI answer engines get a curated map of your content.', 'flexa-seo-aeo' ),
			],
		];

		if ( class_exists( 'WooCommerce' ) ) {
			$checks[] = [
				'id'       => 'commerce',
				'label'    => __( 'WooCommerce product schema', 'flexa-seo-aeo' ),
				'on'       => (bool) Settings::get_aeo( 'commerce' ),
				'group'    => 'schema',
				'severity' => 'warning',
				'section'  => 'aeo',
				'hint'     => __( 'Emit Product / Offer JSON-LD so products surface in AI shopping answers.', 'flexa-seo-aeo' ),
			];
		}

		return $checks;
	}

	/**
	 * @param list<array{on:bool}> $checks
	 */
	private function technical_score( array $checks ): int {
		$total = count( $checks );
		if ( 0 === $total ) {
			return 0;
		}

		$passing = 0;
		foreach ( $checks as $check ) {
			if ( $check['on'] ) {
				++$passing;
			}
		}

		return (int) round( $passing / $total * 100 );
	}

	/**
	 * Issue counts across the whole dashboard. Per-post `fail`s are Critical and
	 * per-post `warn`s are Opportunities (both come from the AEO scan); failing
	 * global technical toggles are Warnings; every per-post `pass` is Passed.
	 *
	 * @param array<string,array{pass:int,warn:int,fail:int}>                    $tally
	 * @param list<array{on:bool}>                                               $technical
	 * @return array{critical:int,warnings:int,opportunities:int,passed:int}
	 */
	private function issues( array $tally, array $technical ): array {
		$critical = 0;
		$warn     = 0;
		$passed   = 0;
		foreach ( $tally as $counts ) {
			$critical += $counts['fail'];
			$warn     += $counts['warn'];
			$passed   += $counts['pass'];
		}

		$warnings = 0;
		foreach ( $technical as $check ) {
			if ( ! $check['on'] ) {
				++$warnings;
			}
		}

		return [
			'critical'      => $critical,
			'warnings'      => $warnings,
			'opportunities' => $warn,
			'passed'        => $passed,
		];
	}

	/**
	 * SEO Health grouped from the technical checklist. "Internal linking" is
	 * surfaced as not-yet-measured rather than faked, so the card set matches the
	 * canonical layout without inventing a signal the plugin does not compute.
	 *
	 * @param list<array{group:string,on:bool}> $technical
	 * @return list<array{id:string,label:string,score:int|null,status:string}>
	 */
	private function seo_health( array $technical ): array {
		return [
			$this->group_row( 'technical', __( 'Technical SEO', 'flexa-seo-aeo' ), $technical, 'technical' ),
			$this->group_row( 'content', __( 'Content SEO', 'flexa-seo-aeo' ), $technical, 'content' ),
			$this->group_row( 'schema', __( 'Schema', 'flexa-seo-aeo' ), $technical, 'schema' ),
			[
				'id'     => 'internal_linking',
				'label'  => __( 'Internal linking', 'flexa-seo-aeo' ),
				'score'  => null,
				'status' => 'na',
			],
		];
	}

	/**
	 * Score one technical group as the share of its members that are enabled.
	 *
	 * @param list<array{group:string,on:bool}> $technical
	 * @return array{id:string,label:string,score:int|null,status:string}
	 */
	private function group_row( string $id, string $label, array $technical, string $group ): array {
		$members = array_values( array_filter( $technical, static fn( array $c ): bool => $c['group'] === $group ) );
		$total   = count( $members );
		if ( 0 === $total ) {
			return [
				'id'     => $id,
				'label'  => $label,
				'score'  => null,
				'status' => 'na',
			];
		}

		$on = 0;
		foreach ( $members as $member ) {
			if ( $member['on'] ) {
				++$on;
			}
		}
		$score = (int) round( $on / $total * 100 );

		return [
			'id'     => $id,
			'label'  => $label,
			'score'  => $score,
			'status' => $this->band( $score ),
		];
	}

	/**
	 * AEO Health grouped from the per-post scan. "Entity clarity" is reported as
	 * not-yet-measured (the scan has no entity signal), keeping the canonical
	 * four-card layout honest.
	 *
	 * @param array<string,array{pass:int,warn:int,fail:int}> $tally
	 * @return list<array{id:string,label:string,score:int|null,status:string}>
	 */
	private function aeo_health( array $tally, int $scanned ): array {
		$groups = [
			'answer_readiness'          => [ 'answer_first', 'question_headings', 'meta_description' ],
			'structured_data'           => [ 'structured_data', 'faq', 'markdown_alternate', 'llms_txt' ],
			'content_comprehensiveness' => [ 'content_depth' ],
		];
		$labels = [
			'answer_readiness'          => __( 'Answer readiness', 'flexa-seo-aeo' ),
			'structured_data'           => __( 'Structured data', 'flexa-seo-aeo' ),
			'content_comprehensiveness' => __( 'Content comprehensiveness', 'flexa-seo-aeo' ),
		];

		$rows = [
			$this->aeo_group_row( 'answer_readiness', $labels['answer_readiness'], $groups['answer_readiness'], $tally, $scanned ),
			[
				'id'     => 'entity_clarity',
				'label'  => __( 'Entity clarity', 'flexa-seo-aeo' ),
				'score'  => null,
				'status' => 'na',
			],
			$this->aeo_group_row( 'structured_data', $labels['structured_data'], $groups['structured_data'], $tally, $scanned ),
			$this->aeo_group_row( 'content_comprehensiveness', $labels['content_comprehensiveness'], $groups['content_comprehensiveness'], $tally, $scanned ),
		];

		return $rows;
	}

	/**
	 * Average pass-factor (pass=1, warn=0.5, fail=0) across a bucket's member
	 * checks over every scanned post.
	 *
	 * @param list<string>                                     $ids
	 * @param array<string,array{pass:int,warn:int,fail:int}> $tally
	 * @return array{id:string,label:string,score:int|null,status:string}
	 */
	private function aeo_group_row( string $id, string $label, array $ids, array $tally, int $scanned ): array {
		if ( 0 === $scanned ) {
			return [
				'id'     => $id,
				'label'  => $label,
				'score'  => null,
				'status' => 'na',
			];
		}

		$earned = 0.0;
		$slots  = 0;
		foreach ( $ids as $check_id ) {
			if ( ! isset( $tally[ $check_id ] ) ) {
				continue;
			}
			$counts  = $tally[ $check_id ];
			$earned += $counts['pass'] + ( $counts['warn'] * 0.5 );
			$slots  += $scanned;
		}

		if ( 0 === $slots ) {
			return [
				'id'     => $id,
				'label'  => $label,
				'score'  => null,
				'status' => 'na',
			];
		}

		$score = (int) round( $earned / $slots * 100 );

		return [
			'id'     => $id,
			'label'  => $label,
			'score'  => $score,
			'status' => $this->band( $score ),
		];
	}

	/**
	 * The 3–5 highest-impact fixes: disabled technical toggles first (ranked by
	 * severity), then the single most common per-post gap. Each links to the
	 * screen that resolves it.
	 *
	 * @param list<array{id:string,label:string,on:bool,severity:string,section:string,hint:string}> $technical
	 * @param array<string,array{pass:int,warn:int,fail:int}>                                        $tally
	 * @return list<array{id:string,label:string,hint:string,severity:string,target:string}>
	 */
	private function recommendations( array $technical, array $tally, int $scanned ): array {
		$rank = [
			'critical'    => 0,
			'warning'     => 1,
			'opportunity' => 2,
		];

		$candidates = [];
		foreach ( $technical as $check ) {
			if ( $check['on'] ) {
				continue;
			}
			$candidates[] = [
				'id'       => $check['id'],
				'label'    => $check['label'],
				'hint'     => $check['hint'],
				'severity' => $check['severity'],
				'target'   => $check['section'],
				'_rank'    => $rank[ $check['severity'] ] ?? 3,
			];
		}

		usort(
			$candidates,
			static fn( array $a, array $b ): int => $a['_rank'] <=> $b['_rank']
		);

		$out = [];
		foreach ( array_slice( $candidates, 0, 4 ) as $item ) {
			unset( $item['_rank'] );
			$out[] = $item;
		}

		$content = $this->top_content_gap( $tally, $scanned );
		if ( null !== $content ) {
			$out[] = $content;
		}

		return array_slice( $out, 0, 5 );
	}

	/**
	 * The per-post check failing on the most scanned posts, expressed as one
	 * content-side recommendation pointing at the attention list.
	 *
	 * @param array<string,array{pass:int,warn:int,fail:int}> $tally
	 * @return array{id:string,label:string,hint:string,severity:string,target:string}|null
	 */
	private function top_content_gap( array $tally, int $scanned ): ?array {
		if ( 0 === $scanned ) {
			return null;
		}

		$labels = [
			'answer_first'      => __( 'Answer-first openings', 'flexa-seo-aeo' ),
			'question_headings' => __( 'Question-style headings', 'flexa-seo-aeo' ),
			'meta_description'  => __( 'Meta descriptions', 'flexa-seo-aeo' ),
			'content_depth'     => __( 'Content depth', 'flexa-seo-aeo' ),
			'faq'               => __( 'FAQ / Q&A blocks', 'flexa-seo-aeo' ),
		];

		$worst_id    = '';
		$worst_count = 0;
		foreach ( $labels as $id => $label ) {
			if ( ! isset( $tally[ $id ] ) ) {
				continue;
			}
			$missing = $tally[ $id ]['fail'] + $tally[ $id ]['warn'];
			if ( $missing > $worst_count ) {
				$worst_count = $missing;
				$worst_id    = $id;
			}
		}

		if ( '' === $worst_id || 0 === $worst_count ) {
			return null;
		}

		return [
			'id'       => 'content_' . $worst_id,
			'label'    => (string) $labels[ $worst_id ],
			/* translators: 1: number of posts, 2: signal name e.g. "Answer-first openings". */
			'hint'     => sprintf(
				/* translators: 1: number of posts, 2: signal name. */
				_n(
					'%1$d scanned post needs work on %2$s. Open each and follow the readiness checklist.',
					'%1$d scanned posts need work on %2$s. Open each and follow the readiness checklist.',
					$worst_count,
					'flexa-seo-aeo'
				),
				$worst_count,
				strtolower( (string) $labels[ $worst_id ] )
			),
			'severity' => 'opportunity',
			'target'   => 'pages',
		];
	}

	/**
	 * Lowest-scoring scanned posts that still have room to improve (score < 100),
	 * capped to a short list for the "Pages needing attention" card.
	 *
	 * @param list<array{id:int,title:string,url:string,edit_url:string,score:int,grade:string,issues:int,checks:list<array{id:string,label:string,status:string,hint:string,fix:array{label:string,scope:string,patch:array<string,mixed>}|null,evidence:string}>}> $pages
	 * @return list<array{id:int,title:string,url:string,edit_url:string,score:int,grade:string,issues:int,checks:list<array{id:string,label:string,status:string,hint:string,fix:array{label:string,scope:string,patch:array<string,mixed>}|null,evidence:string}>}>
	 */
	private function attention_pages( array $pages ): array {
		$needy = array_values( array_filter( $pages, static fn( array $p ): bool => $p['score'] < 100 ) );

		usort( $needy, static fn( array $a, array $b ): int => $a['score'] <=> $b['score'] );

		return array_slice( $needy, 0, 8 );
	}

	/**
	 * Read the persisted trend log.
	 *
	 * @return list<array{t:int,seo:int,aeo:int,technical:int}>
	 */
	private function trend(): array {
		$stored = get_option( self::TREND_KEY, [] );
		if ( ! is_array( $stored ) ) {
			return [];
		}

		$out = [];
		foreach ( $stored as $point ) {
			if ( ! is_array( $point ) ) {
				continue;
			}
			$out[] = [
				't'         => (int) ( $point['t'] ?? 0 ),
				'seo'       => (int) ( $point['seo'] ?? 0 ),
				'aeo'       => (int) ( $point['aeo'] ?? 0 ),
				'technical' => (int) ( $point['technical'] ?? 0 ),
			];
		}

		return $out;
	}

	/**
	 * Append a snapshot to the trend log, retaining the most recent TREND_MAX.
	 *
	 * @return list<array{t:int,seo:int,aeo:int,technical:int}>
	 */
	private function push_trend( int $seo, int $aeo, int $technical ): array {
		$trend   = $this->trend();
		$trend[] = [
			't'         => time(),
			'seo'       => $seo,
			'aeo'       => $aeo,
			'technical' => $technical,
		];

		if ( count( $trend ) > self::TREND_MAX ) {
			$trend = array_slice( $trend, -self::TREND_MAX );
		}

		update_option( self::TREND_KEY, $trend, false );

		return $trend;
	}

	private function grade( int $score ): string {
		foreach ( self::GRADES as $grade => $threshold ) {
			if ( $score >= $threshold ) {
				return $grade;
			}
		}

		return 'poor';
	}

	/**
	 * Coarse status band for a 0–100 sub-score, used to colour the health cards.
	 */
	private function band( int $score ): string {
		if ( $score >= 70 ) {
			return 'good';
		}
		if ( $score >= 40 ) {
			return 'warn';
		}

		return 'poor';
	}

	/**
	 * Sort weight for a check status — hard failures before soft warnings.
	 */
	private function severity_rank( string $status ): int {
		return match ( $status ) {
			'fail'  => 0,
			'warn'  => 1,
			default => 2,
		};
	}
}
