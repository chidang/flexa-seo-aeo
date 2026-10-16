<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Services\Aeo;

use Flexa\SeoAeo\Domain\PostMetaRepository;
use Flexa\SeoAeo\Services\Metas as MetasService;
use Flexa\SeoAeo\Services\Sitemap as SitemapService;
use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Answer-Engine Readiness scorer — the plugin's flagship AEO surface.
 *
 * Where the rest of the ecosystem stops at *generating* an llms.txt file, this
 * scores how well an individual post is prepared to be quoted by AI answer
 * engines (ChatGPT, Perplexity, Claude, Gemini) and returns an actionable
 * checklist. It reads real signals — structured data, an answer-first summary,
 * question-style headings, FAQ blocks, llms.txt inclusion, a Markdown alternate,
 * and content depth — and weights them into a 0–100 score. Pure analysis: it
 * takes a post, returns a nested array; JSON encoding + escaping happen at the
 * REST/editor boundary.
 */
final class Readiness {
	use SingletonTrait;

	/**
	 * Grade cut-offs, high to low. The first threshold a score meets wins.
	 *
	 * @var array<string, int>
	 */
	private const GRADES = [
		'excellent' => 85,
		'good'      => 70,
		'fair'      => 50,
		'poor'      => 0,
	];

	/**
	 * How well-founded each check is, published alongside the score so a reader
	 * can tell a vendor-documented requirement from our own judgement call.
	 *
	 * - `documented` — the signal is described in a search or AI vendor's own
	 *   published guidance, so the check tracks a stated requirement.
	 * - `observed` — third-party studies and our own testing associate the
	 *   signal with being quoted, but no engine documents it as a rule.
	 * - `heuristic` — a reasoned bet. Nobody has confirmed engines read it; we
	 *   score it because the cost of doing it is low and the upside is real.
	 *
	 * The weights themselves are editorial throughout: no engine publishes how
	 * it trades these signals off, so treat the rubric in docs/SCORING.md as a
	 * stated opinion, not a measurement. A check added through
	 * `flexa_seo_aeo/aeo/readiness_checks` that names no level is `heuristic`.
	 *
	 * @var array<string, string>
	 */
	private const EVIDENCE = [
		'structured_data'    => 'documented',
		'meta_description'   => 'documented',
		'answer_first'       => 'observed',
		'question_headings'  => 'observed',
		'faq'                => 'observed',
		'content_depth'      => 'observed',
		'llms_txt'           => 'heuristic',
		'markdown_alternate' => 'heuristic',
	];

	/**
	 * The levels {@see self::EVIDENCE} may use; anything else is read as the
	 * most cautious one so a third-party check cannot overstate its footing.
	 *
	 * @var list<string>
	 */
	private const EVIDENCE_LEVELS = [ 'documented', 'observed', 'heuristic' ];

	/**
	 * How many times {@see self::unwrap_shortcodes()} walks the content. Each
	 * pass peels one nesting level off a shortcode-built layout; five is deeper
	 * than any real page nests and keeps a pathological post bounded.
	 */
	private const SHORTCODE_PASSES = 5;

	/**
	 * Build the full readiness report for a post.
	 *
	 * @return array{
	 *     score: int,
	 *     grade: string,
	 *     checks: list<array{id: string, label: string, status: string, weight: int, hint: string, fix: array{label: string, scope: string, patch: array<string, mixed>}|null, evidence: string}>
	 * }
	 */
	public function report( WP_Post $post ): array {
		$content = $this->analyze( $post );

		$checks = [
			$this->check_structured_data(),
			$this->check_meta_description( $post ),
			$this->check_answer_first( $content['first_paragraph'] ),
			$this->check_question_headings( $content['headings'] ),
			$this->check_faq( $post ),
			$this->check_llms_txt( $post ),
			$this->check_markdown_alternate(),
			$this->check_depth( $content['words'] ),
		];

		/**
		 * Filters the readiness checks for a post before they are scored. Add-ons
		 * (or Pro) can append their own `{id,label,status,weight,hint,fix}` rows,
		 * optionally with an `evidence` of `documented`, `observed` or
		 * `heuristic`; anything missing or unrecognised is read as `heuristic`.
		 *
		 * @param list<array{id: string, label: string, status: string, weight: int, hint: string, fix: array{label: string, scope: string, patch: array<string, mixed>}|null, evidence: string}> $checks
		 * @param WP_Post                                                                            $post
		 */
		$checks = $this->stamp_evidence(
			array_values( (array) apply_filters( 'flexa_seo_aeo/aeo/readiness_checks', $checks, $post ) )
		);

		$score = $this->score( $checks );

		return [
			'score'  => $score,
			'grade'  => $this->grade( $score ),
			'checks' => $checks,
		];
	}

	/**
	 * Guarantee every row carries an evidence level, including rows a filter
	 * appended. A level we do not recognise is read as `heuristic` rather than
	 * dropped: the published rubric says what each label means, so an add-on
	 * must not be able to invent a stronger-sounding one.
	 *
	 * @param list<array<string, mixed>> $checks
	 * @return list<array<string, mixed>>
	 */
	private function stamp_evidence( array $checks ): array {
		foreach ( $checks as $index => $check ) {
			$level = isset( $check['evidence'] ) ? (string) $check['evidence'] : '';

			if ( ! in_array( $level, self::EVIDENCE_LEVELS, true ) ) {
				$id                           = isset( $check['id'] ) ? (string) $check['id'] : '';
				$checks[ $index ]['evidence'] = self::EVIDENCE[ $id ] ?? 'heuristic';
			}
		}

		return $checks;
	}

	/**
	 * Weighted score: each check contributes its full weight when it passes, half
	 * when it warns, nothing when it fails. Normalised to the total weight so the
	 * result is always 0–100 even after a filter adds or removes rows.
	 *
	 * @param list<array{status: string, weight: int}> $checks
	 */
	private function score( array $checks ): int {
		$earned = 0.0;
		$total  = 0;

		foreach ( $checks as $check ) {
			$weight  = max( 0, (int) $check['weight'] );
			$total  += $weight;
			$earned += $weight * $this->factor( (string) $check['status'] );
		}

		if ( 0 === $total ) {
			return 0;
		}

		return (int) round( $earned / $total * 100 );
	}

	private function factor( string $status ): float {
		return match ( $status ) {
			'pass'  => 1.0,
			'warn'  => 0.5,
			default => 0.0,
		};
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
	 * @return array{id: string, label: string, status: string, weight: int, hint: string, fix: array{label: string, scope: string, patch: array<string, mixed>}|null, evidence: string}
	 */
	private function check_structured_data(): array {
		$enabled = (bool) Settings::get_aeo( 'schema' );
		$status  = $enabled ? 'pass' : 'fail';
		$hint    = $enabled
			? __( 'JSON-LD is emitted for this post so engines can lift facts out as structured data.', 'flexa-seo-aeo' )
			: __( 'Enable AEO Core → Structured data so this post ships an Article/WebPage JSON-LD graph.', 'flexa-seo-aeo' );
		$fix     = $enabled
			? null
			: $this->fix( __( 'Enable structured data', 'flexa-seo-aeo' ), [ 'aeo' => [ 'schema' => true ] ] );

		return $this->row( 'structured_data', __( 'Structured data', 'flexa-seo-aeo' ), $status, 20, $hint, $fix );
	}

	/**
	 * @return array{id: string, label: string, status: string, weight: int, hint: string, fix: array{label: string, scope: string, patch: array<string, mixed>}|null, evidence: string}
	 */
	private function check_meta_description( WP_Post $post ): array {
		$meta = PostMetaRepository::instance()->get( $post->ID );

		// Not get_the_excerpt(): core's wp_trim_excerpt() runs the whole
		// `the_content` chain to build a missing excerpt, which is the one thing
		// a 50-post scan cannot afford. The frontend resolver is both cheaper and
		// the authority on what this post's description will actually say.
		$description = '' !== $meta->description
			? $meta->description
			: MetasService::instance()->fallback_description( $post );

		$length = $this->length( $description );
		if ( 0 === $length ) {
			return $this->row(
				'meta_description',
				__( 'Meta description', 'flexa-seo-aeo' ),
				'fail',
				15,
				__( 'Add a meta description. Answer engines often quote it verbatim, and with no excerpt and nothing in the SEO panel this page has nothing of its own to quote.', 'flexa-seo-aeo' )
			);
		}

		$status = ( $length >= 50 && $length <= 160 ) ? 'pass' : 'warn';
		$hint   = 'pass' === $status
			? __( 'A concise summary is in place for engines to quote.', 'flexa-seo-aeo' )
			: __( 'Aim for roughly 50–160 characters so the summary is neither truncated nor thin.', 'flexa-seo-aeo' );

		return $this->row( 'meta_description', __( 'Meta description', 'flexa-seo-aeo' ), $status, 15, $hint );
	}

	/**
	 * @return array{id: string, label: string, status: string, weight: int, hint: string, fix: array{label: string, scope: string, patch: array<string, mixed>}|null, evidence: string}
	 */
	private function check_answer_first( string $paragraph ): array {
		$length = $this->length( $paragraph );
		if ( 0 === $length ) {
			return $this->row(
				'answer_first',
				__( 'Answer-first opening', 'flexa-seo-aeo' ),
				'fail',
				15,
				__( 'Open with a 2–3 sentence summary that answers the topic directly; engines favour the lede.', 'flexa-seo-aeo' )
			);
		}

		$status = ( $length >= 40 && $length <= 360 ) ? 'pass' : 'warn';
		$hint   = 'pass' === $status
			? __( 'The post opens with a self-contained summary an engine can quote as the answer.', 'flexa-seo-aeo' )
			: __( 'Tighten the opening paragraph to a self-contained 40–360 character answer.', 'flexa-seo-aeo' );

		return $this->row( 'answer_first', __( 'Answer-first opening', 'flexa-seo-aeo' ), $status, 15, $hint );
	}

	/**
	 * @param list<string> $headings
	 * @return array{id: string, label: string, status: string, weight: int, hint: string, fix: array{label: string, scope: string, patch: array<string, mixed>}|null, evidence: string}
	 */
	private function check_question_headings( array $headings ): array {
		$questions = 0;
		foreach ( $headings as $heading ) {
			if ( $this->is_question( $heading ) ) {
				++$questions;
			}
		}

		if ( $questions >= 2 ) {
			$status = 'pass';
		} elseif ( 1 === $questions ) {
			$status = 'warn';
		} else {
			$status = 'fail';
		}

		$hint = 'pass' === $status
			? __( 'Question-style headings map cleanly onto the prompts users actually ask.', 'flexa-seo-aeo' )
			: __( 'Phrase a few headings as the questions readers ask (e.g. “How does…”, “What is…?”).', 'flexa-seo-aeo' );

		return $this->row( 'question_headings', __( 'Question-style headings', 'flexa-seo-aeo' ), $status, 15, $hint );
	}

	/**
	 * @return array{id: string, label: string, status: string, weight: int, hint: string, fix: array{label: string, scope: string, patch: array<string, mixed>}|null, evidence: string}
	 */
	private function check_faq( WP_Post $post ): array {
		$status = $this->has_faq_blocks( $post ) ? 'pass' : 'warn';
		$hint   = 'pass' === $status
			? __( 'FAQ blocks were detected and are emitted as FAQPage structured data.', 'flexa-seo-aeo' )
			: __( 'Add a core Details/FAQ block: each question becomes a citable FAQPage entry.', 'flexa-seo-aeo' );

		return $this->row( 'faq', __( 'FAQ / Q&A blocks', 'flexa-seo-aeo' ), $status, 10, $hint );
	}

	/**
	 * @return array{id: string, label: string, status: string, weight: int, hint: string, fix: array{label: string, scope: string, patch: array<string, mixed>}|null, evidence: string}
	 */
	private function check_llms_txt( WP_Post $post ): array {
		if ( ! (bool) Settings::get_aeo( 'enabled' ) ) {
			return $this->row(
				'llms_txt',
				__( 'Listed in llms.txt', 'flexa-seo-aeo' ),
				'fail',
				10,
				__( 'Turn on AEO Core → llms.txt so this content is advertised to language models.', 'flexa-seo-aeo' ),
				$this->fix( __( 'Enable llms.txt', 'flexa-seo-aeo' ), [ 'aeo' => [ 'enabled' => true ] ] )
			);
		}

		$included = in_array( (string) get_post_type( $post ), SitemapService::instance()->post_types(), true );
		$meta     = PostMetaRepository::instance()->get( $post->ID );

		if ( ! $included || $meta->noindex ) {
			return $this->row(
				'llms_txt',
				__( 'Listed in llms.txt', 'flexa-seo-aeo' ),
				'warn',
				10,
				__( 'This post is excluded from llms.txt (its type is off or it is set to noindex).', 'flexa-seo-aeo' )
			);
		}

		return $this->row(
			'llms_txt',
			__( 'Listed in llms.txt', 'flexa-seo-aeo' ),
			'pass',
			10,
			__( 'This post is advertised in /llms.txt for language models to discover.', 'flexa-seo-aeo' )
		);
	}

	/**
	 * @return array{id: string, label: string, status: string, weight: int, hint: string, fix: array{label: string, scope: string, patch: array<string, mixed>}|null, evidence: string}
	 */
	private function check_markdown_alternate(): array {
		$advertised = (bool) Settings::get_aeo( 'agent_readiness' );
		$served     = (bool) Settings::get_aeo( 'plain_text_export' );

		if ( $advertised && $served ) {
			$status = 'pass';
			$hint   = __( 'A token-cheap Markdown alternate is exposed to AI crawlers via a Link header.', 'flexa-seo-aeo' );
		} elseif ( $served || $advertised ) {
			$status = 'warn';
			$hint   = __( 'Enable both Agent Readiness and the Markdown export so crawlers can find and fetch it.', 'flexa-seo-aeo' );
		} else {
			$status = 'fail';
			$hint   = __( 'Enable AEO Core → Agent Readiness to serve a clean Markdown rendering for AI agents.', 'flexa-seo-aeo' );
		}

		$fix = 'pass' === $status
			? null
			: $this->fix(
				__( 'Enable Markdown alternate', 'flexa-seo-aeo' ),
				[
					'aeo' => [
						'agent_readiness'   => true,
						'plain_text_export' => true,
					],
				]
			);

		return $this->row( 'markdown_alternate', __( 'Markdown alternate', 'flexa-seo-aeo' ), $status, 10, $hint, $fix );
	}

	/**
	 * @return array{id: string, label: string, status: string, weight: int, hint: string, fix: array{label: string, scope: string, patch: array<string, mixed>}|null, evidence: string}
	 */
	private function check_depth( int $words ): array {
		if ( $words >= 300 ) {
			$status = 'pass';
			$hint   = __( 'There is enough substance here for an engine to treat the page as authoritative.', 'flexa-seo-aeo' );
		} elseif ( $words >= 100 ) {
			$status = 'warn';
			$hint   = __( 'Thin content is rarely cited. Expand toward 300+ words of genuine detail.', 'flexa-seo-aeo' );
		} else {
			$status = 'fail';
			$hint   = __( 'This post is very short; engines seldom quote pages with little content.', 'flexa-seo-aeo' );
		}

		return $this->row( 'content_depth', __( 'Content depth', 'flexa-seo-aeo' ), $status, 5, $hint );
	}

	/**
	 * @param array{label: string, scope: string, patch: array<string, mixed>}|null $fix
	 * @return array{id: string, label: string, status: string, weight: int, hint: string, fix: array{label: string, scope: string, patch: array<string, mixed>}|null, evidence: string}
	 */
	private function row( string $id, string $label, string $status, int $weight, string $hint, ?array $fix = null ): array {
		return [
			'id'       => $id,
			'label'    => $label,
			'status'   => $status,
			'weight'   => $weight,
			'hint'     => $hint,
			'fix'      => $fix,
			'evidence' => self::EVIDENCE[ $id ] ?? 'heuristic',
		];
	}

	/**
	 * A one-click "enable this site-wide setting" remedy, attached to a check
	 * whose only blocker is a global AEO toggle being off. The dashboard and the
	 * editor sidebar POST the `patch` to /settings (which partial-merges it) and
	 * then rescan. Returned as null when the fix isn't a simple toggle — e.g. a
	 * per-post exclusion or a content gap — so the UI shows only the hint.
	 *
	 * @param array<string, mixed> $patch A partial settings payload for POST /settings.
	 * @return array{label: string, scope: string, patch: array<string, mixed>}
	 */
	private function fix( string $label, array $patch ): array {
		return [
			'label' => $label,
			'scope' => 'site',
			'patch' => $patch,
		];
	}

	/**
	 * Pull the three content signals the score needs: the first paragraph
	 * (answer-first), the heading texts (question headings), and the total word
	 * count (depth).
	 *
	 * @return array{first_paragraph: string, headings: list<string>, words: int}
	 */
	private function analyze( WP_Post $post ): array {
		$html = $this->content_html( $post );

		if ( '' === $html ) {
			return [
				'first_paragraph' => '',
				'headings'        => [],
				'words'           => 0,
			];
		}

		$plain = $this->one_line( wp_strip_all_tags( $html ) );

		return [
			'first_paragraph' => $this->first_paragraph( $html ),
			'headings'        => $this->headings( $html ),
			'words'           => '' === $plain ? 0 : count( (array) preg_split( '/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY ) ),
		];
	}

	/**
	 * The markup the analysis reads.
	 *
	 * Deliberately NOT `the_content`. A dashboard scan analyses up to 50 posts
	 * in one request, and running the render chain that many times means every
	 * shortcode, dynamic block, oEmbed and page-builder filter on the site
	 * executes 50 times over, outside the loop and with no global post set up.
	 * That is unbounded work with side effects (asset enqueues, remote oEmbed
	 * lookups) for something that only needs the text.
	 *
	 * Block markup already carries the static HTML, so stripping the delimiters
	 * is enough; classic content gets wpautop so its paragraphs are findable;
	 * shortcodes are unwrapped to the text they enclose. A builder that keeps
	 * its content outside post_content (Elementor and friends) can hand the
	 * analysis real markup through the filter below.
	 */
	private function content_html( WP_Post $post ): string {
		$html = $this->unwrap_shortcodes( $post->post_content );

		$html = has_blocks( $html )
			? (string) preg_replace( '@<!--\s*/?\s*wp:.*?-->@s', '', $html )
			: wpautop( $html );

		/**
		 * Filters the HTML the Answer-Engine Readiness analysis reads for a post.
		 *
		 * @param string  $html Markup derived from the stored post content.
		 * @param WP_Post $post The post being scored.
		 */
		$html = (string) apply_filters( 'flexa_seo_aeo/readiness/content_html', $html, $post );

		return trim( $html );
	}

	/**
	 * Replace registered shortcodes with the content they enclose, so a post
	 * built out of `[section][text]…[/text][/section]` still reads as prose.
	 * `strip_shortcodes()` would drop the enclosed text with the tag, and
	 * `do_shortcode()` would run the handlers — this does neither. One pass
	 * unwraps one nesting level, hence the bounded loop.
	 */
	private function unwrap_shortcodes( string $content ): string {
		if ( ! str_contains( $content, '[' ) ) {
			return $content;
		}

		// An escaped [[tag]] is handed back untouched, or the next pass would read
		// the unescaped result as a real shortcode and unwrap it to nothing. It is
		// unescaped once, below, after the loop has settled.
		$unwrap = static fn( array $m ): string => '[' === $m[1] && ']' === $m[6]
			? $m[0]
			: $m[1] . ( $m[5] ?? '' ) . $m[6];

		for ( $pass = 0; $pass < self::SHORTCODE_PASSES; $pass++ ) {
			$pattern = '/' . get_shortcode_regex() . '/s';
			$next    = (string) preg_replace_callback( $pattern, $unwrap, $content );
			if ( $next === $content ) {
				break;
			}
			$content = $next;
		}

		// Same end state as do_shortcode(): [[tag]] reads as the literal text [tag].
		return (string) preg_replace( '/\[(\[[^\[\]]+\])\]/', '$1', $content );
	}

	/**
	 * Text of the first paragraph that carries any.
	 */
	private function first_paragraph( string $html ): string {
		if ( ! preg_match_all( '@<p\b[^>]*>(.*?)</p>@is', $html, $matches ) ) {
			return '';
		}

		foreach ( $matches[1] as $inner ) {
			$text = $this->one_line( wp_strip_all_tags( $inner ) );
			if ( '' !== $text ) {
				return $text;
			}
		}

		return '';
	}

	/**
	 * Every h1–h4 heading, in document order.
	 *
	 * @return list<string>
	 */
	private function headings( string $html ): array {
		if ( ! preg_match_all( '@<h([1-4])\b[^>]*>(.*?)</h\1>@is', $html, $matches, PREG_SET_ORDER ) ) {
			return [];
		}

		$out = [];
		foreach ( $matches as $match ) {
			$text = $this->one_line( wp_strip_all_tags( $match[2] ) );
			if ( '' !== $text ) {
				$out[] = $text;
			}
		}

		return $out;
	}

	/**
	 * True when a heading reads like a user's question — it ends with a question
	 * mark or opens with a common interrogative word.
	 */
	private function is_question( string $heading ): bool {
		$heading = trim( $heading );
		if ( '' === $heading ) {
			return false;
		}
		if ( str_ends_with( $heading, '?' ) ) {
			return true;
		}

		$first = strtolower( (string) strtok( $heading, " \t" ) );
		$words = [ 'how', 'what', 'why', 'when', 'where', 'which', 'who', 'can', 'does', 'do', 'is', 'are', 'should', 'will' ];

		return in_array( $first, $words, true );
	}

	/**
	 * Whether the post contains at least one core Details block (the FAQ source
	 * the Schema service turns into FAQPage entries).
	 */
	private function has_faq_blocks( WP_Post $post ): bool {
		// Cheap gate before the block walk: serialized markup always names the
		// block in its comment delimiter, and a scan scores up to 50 posts in one
		// request, so parse_blocks is worth skipping for the posts that have none.
		if ( ! function_exists( 'parse_blocks' ) || ! str_contains( $post->post_content, '<!-- wp:details' ) ) {
			return false;
		}

		return $this->find_details( parse_blocks( $post->post_content ) );
	}

	/**
	 * @param array<int, mixed> $blocks
	 */
	private function find_details( array $blocks ): bool {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( 'core/details' === ( $block['blockName'] ?? '' ) ) {
				return true;
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) && $this->find_details( $block['innerBlocks'] ) ) {
				return true;
			}
		}

		return false;
	}

	private function length( string $text ): int {
		return (int) mb_strlen( $this->one_line( $text ) );
	}

	private function one_line( string $text ): string {
		return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
	}
}
