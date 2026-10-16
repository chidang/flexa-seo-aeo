# Answer-Engine Readiness Score: the rubric

This is the published rubric behind the number the plugin shows on a post and
on the Dashboard. It exists so you can disagree with it concretely. Nobody
outside the engines knows how ChatGPT, Perplexity, Claude or Gemini pick what
to quote, so a score that will not say what it measures is not worth much.

Everything here is implemented in `src/Services/Aeo/Readiness.php`. If this
document and that file ever disagree, the file is right and this is a bug.

A rendered version of this page, with the compatibility matrix next to it,
lives in `docs-site/`.

## How a post is scored

Eight checks run against the post. Each returns one of three statuses and
carries a fixed weight:

| Status | Earns |
|---|---|
| `pass` | the full weight |
| `warn` | half the weight |
| `fail` | nothing |

The earned total is divided by the sum of all weights and multiplied by 100,
so the result is always 0 to 100 even after an add-on adds or removes checks.
The result is rounded to a whole number.

```
score = round( sum( weight x factor ) / sum( weight ) x 100 )
```

## Evidence levels

Every check is labelled with how well-founded it is. The label travels with
the check through the REST API, the editor sidebar and the Dashboard, so you
never see a number without seeing what it rests on.

| Level | Meaning |
|---|---|
| `documented` | The signal is described in a search or AI vendor's own published guidance. The check tracks a stated requirement. |
| `observed` | Third-party studies and our own testing associate the signal with being quoted, but no engine documents it as a rule. |
| `heuristic` | A reasoned bet. Nobody has confirmed engines read it. It is scored because the cost of doing it is low and the upside is real. |

Two things this does not mean. A `documented` check is not a guarantee of a
citation, and a `heuristic` one is not a guess pulled out of the air: it is a
cheap thing to do that plausibly helps. The weights, separately, are editorial
in every row. See [Why these weights](#why-these-weights).

## The checks

| Check | Weight | Evidence | Passes when | Warns when |
|---|---|---|---|---|
| Structured data | 20 | `documented` | AEO Core → Structured data is on, so the post ships an Article/WebPage JSON-LD graph | never: it is on or it is not |
| Meta description | 15 | `documented` | the resolved description is 50 to 160 characters | a description exists but falls outside that range |
| Answer-first opening | 15 | `observed` | the first paragraph is 40 to 360 characters | a first paragraph exists but falls outside that range |
| Question-style headings | 15 | `observed` | at least 2 headings read as questions | exactly 1 does |
| FAQ / Q&A blocks | 10 | `observed` | the post contains at least one core Details block | no Details block found (this check never hard-fails) |
| Listed in llms.txt | 10 | `heuristic` | llms.txt is on and this post type is included and the post is not noindex | llms.txt is on but the post is excluded |
| Markdown alternate | 10 | `heuristic` | both Agent Readiness and the Markdown export are on | exactly one of the two is on |
| Content depth | 5 | `observed` | 300 or more words | 100 to 299 words |

The weights sum to 100 before any add-on touches them, which is why an
unmodified install reads as a percentage.

A few details worth stating plainly:

- **Meta description** uses the resolved description, which means your own
  value if you set one, and the frontend fallback if you did not. It is
  deliberately not `get_the_excerpt()`, which would run the whole
  `the_content` filter chain and make a 50-post scan unusable.
- **Answer-first opening** reads the first paragraph of rendered content,
  after shortcodes are unwrapped up to five levels deep.
- **Question-style headings** currently recognises English question forms
  (a trailing `?`, or an opening `how`, `what`, `why`, `when`, `where`,
  `which`, `who`, `can`, `do`, `does`, `is`, `are`, `should`, `will`).
  On a
  non-English site this check will under-report. That is a known limit, not a
  judgement about the content.
- **Content depth** counts words in the unwrapped content, not including the
  title or the excerpt.

## Grades

The score maps to a word. The first threshold the score meets wins:

| Grade | Score |
|---|---|
| Excellent | 85 and above |
| Good | 70 to 84 |
| Fair | 50 to 69 |
| Poor | below 50 |

The cut-offs are round numbers chosen to be legible, not calibrated against
any outcome data. A 69 and a 70 are the same post.

## Why these weights

No engine publishes how it trades these signals off against each other, so
the weights are a stated opinion. The reasoning, briefly:

- Structured data is the heaviest because it is the one signal every major
  vendor documents, and the only one that hands an engine facts rather than
  prose to parse.
- The description, the opening paragraph and the headings sit together at 15
  because each one is a different route to the same thing: a short,
  self-contained passage an engine can lift.
- FAQ blocks, llms.txt and the Markdown alternate are at 10 because they help
  when they are read and cost nothing when they are not.
- Content depth is at 5 because word count is the weakest proxy on the list.
  It is here to catch a stub, not to reward length.

If you would weight them differently, you can: see
[Changing the rubric](#changing-the-rubric).

## The site-wide scores

The Dashboard shows three numbers, which are built from the per-post score
rather than replacing it:

- **AEO score** is the mean readiness score across the scanned posts. The scan
  covers the 50 most recently modified published posts by default
  (`flexa_seo_aeo/dashboard/scan_limit`, clamped to 1 to 500), so on a large
  site it is a sample, not a census.
- **Technical score** is the share of site-level configuration checks that
  pass. It is a completeness measure and has nothing to do with content.
- **SEO score** is the mean of the two. If nothing was scanned, it falls back
  to the technical score alone.

## Changing the rubric

Checks are filterable. A row you append is scored exactly like a built-in one,
and its weight joins the normalisation, so adding a weight-20 check makes
every existing check worth proportionally less.

```php
add_filter(
	'flexa_seo_aeo/aeo/readiness_checks',
	function ( array $checks, WP_Post $post ): array {
		$checks[] = [
			'id'       => 'my_check',
			'label'    => __( 'Author byline', 'my-plugin' ),
			'status'   => get_the_author_meta( 'description', (int) $post->post_author ) ? 'pass' : 'warn',
			'weight'   => 10,
			'hint'     => __( 'A filled-in author bio supports E-E-A-T claims.', 'my-plugin' ),
			'fix'      => null,
			'evidence' => 'observed',
		];

		return $checks;
	},
	10,
	2
);
```

`evidence` is optional. A row that omits it, or names a level outside the
three above, is read as `heuristic`: the published rubric says what each label
means, so an add-on must not be able to invent a stronger-sounding one.

To drop a built-in check instead, filter it out by `id`. To reweight one,
change its `weight` in place.

## Disagreeing with a score

Useful disagreement names the check. "The score is wrong" is hard to act on;
"question_headings failed on a Vietnamese post with three question headings"
is a bug report, and that particular one is already known.

The three kinds of disagreement, and what happens to each:

- **The check misread the post.** That is a bug. The check's logic is wrong
  or too narrow, and it gets fixed.
- **The weight is wrong.** That is an opinion, and it is worth having, but it
  is settled by argument rather than by code. Reweight it locally in the
  meantime.
- **The signal does not matter at all.** That is the most useful kind. If a
  `heuristic` check turns out to be measurably useless, it should lose its
  weight or leave the rubric. Evidence that it does not work is more valuable
  here than evidence that it does.
