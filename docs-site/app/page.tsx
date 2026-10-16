import Link from 'next/link';

import { Sidebar } from '@/components/Sidebar';
import { SCORING_NAV } from '@/lib/nav';
import {
  Callout,
  Code,
  CodeBlock,
  Lead,
  Section,
  Table,
} from '@/components/ui';

export const metadata = {
  title: 'Flexa SEO - AEO - Scoring rubric',
  description:
    'Every check behind the Answer-Engine Readiness Score, with its weight, ' +
    'its thresholds, and how well-founded it is.',
};

/**
 * Small inline badge for an evidence level. Mirrors the badge the plugin
 * renders in the editor sidebar and on the Dashboard, so a reader moving
 * between the product and this page sees the same three words.
 */
function Evidence({ level }: { level: 'documented' | 'observed' | 'heuristic' }) {
  const map = {
    documented: 'bg-teal-100 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300',
    observed:
      'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
    heuristic:
      'bg-orange-100 text-orange-800 dark:bg-orange-500/15 dark:text-orange-300',
  } as const;

  return (
    <span
      className={`inline-block rounded-full px-2 py-px text-[11px] font-semibold uppercase tracking-wide ${map[level]}`}
    >
      {level}
    </span>
  );
}

export default function Scoring() {
  return (
    <div className="lg:flex">
      <Sidebar
        items={SCORING_NAV}
        title="Flexa SEO - AEO"
        subtitle="Scoring rubric · v0.6.0"
        crossLinkHref="/compatibility"
        crossLinkLabel="Compatibility matrix →"
        secondaryLink={{ href: '/services', label: 'Need a custom build?' }}
      />

      <main className="mx-auto w-full max-w-3xl flex-1 px-5 pb-24 sm:px-8 lg:px-12">
        {/* ── Hero ─────────────────────────────────────────────── */}
        <header className="py-14">
          <p className="text-xs font-semibold uppercase tracking-widest text-brand-600">
            Scoring rubric · v0.6.0
          </p>
          <h1 className="mt-3 text-4xl font-extrabold tracking-tight text-ink sm:text-5xl">
            How the Answer-Engine Readiness Score is calculated
          </h1>
          <p className="mt-4 max-w-2xl text-lg leading-8 text-ink-soft">
            Eight checks, a published weight for each, and a label saying how
            well-founded each one is. Nothing here is a trade secret, and the
            parts that are our opinion are marked as our opinion.
          </p>
        </header>

        <Section id="overview" kicker="Introduction" title="Why publish this">
          <Lead>
            A number between 0 and 100 looks like a measurement. This one is
            not. It is a weighted opinion about what makes a page easy for an
            AI answer engine to quote, and an opinion is only worth acting on
            if you can see the reasoning.
          </Lead>
          <p>
            So the whole rubric is here: every check, the exact thresholds that
            decide pass, warn and fail, the weight each one carries, and a
            label saying whether the check tracks something a search or AI
            vendor has published, something testing suggests, or something we
            simply think is a good bet.
          </p>
          <p>
            Two things follow from that. You can argue with a specific check
            instead of with the score as a whole. And you can tell at a glance
            which parts of your score rest on documented behaviour and which
            rest on our judgement.
          </p>
        </Section>

        <Section
          id="how-scored"
          kicker="Mechanics"
          title="How the score is built"
        >
          <p>
            Each check returns one of three statuses. A pass earns its full
            weight, a warn earns half, a fail earns nothing. The total is
            divided by the sum of all weights and multiplied by 100:
          </p>
          <CodeBlock caption="Services/Aeo/Readiness.php">
            {`score = round( sum( weight × factor ) / sum( weight ) × 100 )

factor: pass = 1.0, warn = 0.5, fail = 0.0`}
          </CodeBlock>
          <p>
            The eight built-in weights add up to 100, so in a default install
            the weight column reads directly as points. The division still
            matters: the score is normalised against whatever checks are
            actually present, so adding or removing one through the filter
            rebalances the rest instead of pushing the maximum above 100.
          </p>
          <Callout tone="info" title="What the scorer reads">
            <p>
              The analysis reads the post content as it is stored, not the
              rendered output. Block markup has its delimiters stripped,
              classic content goes through <Code>wpautop</Code>, and shortcodes
              are unwrapped to the text inside them, up to five levels deep.
            </p>
            <p>
              Running the full <Code>the_content</Code> chain would fire every
              shortcode, dynamic block and oEmbed on the site once per post,
              and a Dashboard scan reads up to fifty posts in one request. If
              your content lives outside <Code>post_content</Code>, as it does
              with most page builders, hand the scorer real markup through the{' '}
              <Code>flexa_seo_aeo/readiness/content_html</Code> filter.
            </p>
          </Callout>
        </Section>

        <Section
          id="evidence"
          kicker="Honesty"
          title="What the evidence levels mean"
        >
          <p>
            Every check carries one of three labels, shown next to it in the
            editor sidebar and on the Dashboard:
          </p>
          <Table
            head={['Level', 'What it claims', 'What it does not claim']}
            rows={[
              [
                <Evidence key="d" level="documented" />,
                'The signal is described in a search or AI vendor’s own published guidance, so the check tracks a stated requirement.',
                'That following it guarantees a citation, or that the weight we gave it is right.',
              ],
              [
                <Evidence key="o" level="observed" />,
                'Third-party studies and our own testing associate the signal with being quoted.',
                'That any engine documents it as a rule. Nobody has committed to it in writing.',
              ],
              [
                <Evidence key="h" level="heuristic" />,
                'A reasoned bet. The cost of doing it is low and the upside is plausible.',
                'That any engine reads it at all. This is the level where we could simply be wrong.',
              ],
            ]}
          />
          <p>
            The levels describe the <em>signal</em>, never the weight. Weights
            are editorial across the board, including on the two checks labelled
            documented. See{' '}
            <Link
              href="#weights"
              className="font-medium text-brand-600 underline underline-offset-2"
            >
              where the weights come from
            </Link>
            .
          </p>
        </Section>

        <Section id="checks" kicker="The rubric" title="The eight checks">
          <p>
            Weights are out of 100. Thresholds are exact: these are the numbers
            in the code, not a paraphrase of them.
          </p>

          <Table
            head={['Check', 'Weight', 'Evidence']}
            rows={[
              [
                <strong key="l">Structured data</strong>,
                '20',
                <Evidence key="e" level="documented" />,
              ],
              [
                <strong key="l">Meta description</strong>,
                '15',
                <Evidence key="e" level="documented" />,
              ],
              [
                <strong key="l">Answer-first opening</strong>,
                '15',
                <Evidence key="e" level="observed" />,
              ],
              [
                <strong key="l">Question-style headings</strong>,
                '15',
                <Evidence key="e" level="observed" />,
              ],
              [
                <strong key="l">FAQ / Q&amp;A blocks</strong>,
                '10',
                <Evidence key="e" level="observed" />,
              ],
              [
                <strong key="l">Listed in llms.txt</strong>,
                '10',
                <Evidence key="e" level="heuristic" />,
              ],
              [
                <strong key="l">Markdown alternate</strong>,
                '10',
                <Evidence key="e" level="heuristic" />,
              ],
              [
                <strong key="l">Content depth</strong>,
                '5',
                <Evidence key="e" level="observed" />,
              ],
            ]}
          />

          <h3 className="pt-4 text-lg font-bold text-ink">
            Structured data <span className="text-muted">· 20 ·</span>{' '}
            <Evidence level="documented" />
          </h3>
          <p>
            A site-wide check, not a per-post one. It passes when{' '}
            <strong>AEO Core → Structured data</strong> is on, which is what
            makes the post ship an Article or WebPage JSON-LD graph, and fails
            when it is off. There is no middle state and no warn, because the
            setting is a toggle. The fail carries a one-click site-wide fix.
          </p>
          <p>
            Documented because the JSON-LD vocabulary and the engines&rsquo;
            expectations around it are published at length. Weighted highest
            because it is the only check that hands an engine facts in a form
            it does not have to infer from prose.
          </p>

          <h3 className="pt-4 text-lg font-bold text-ink">
            Meta description <span className="text-muted">· 15 ·</span>{' '}
            <Evidence level="documented" />
          </h3>
          <Table
            head={['Status', 'Condition']}
            rows={[
              ['Pass', '50 to 160 characters inclusive'],
              ['Warn', '1 to 49, 161 and over, or nothing at all'],
              ['Fail', 'Never. This check cannot fail.'],
            ]}
          />
          <p>
            The length is measured on the description the front end will
            actually emit: the one typed into the post if there is one,
            otherwise whatever your template resolves to. A post with no
            hand-written description can still pass on the template alone.
          </p>
          <p>
            A page where nothing resolves scores zero for this check, and the
            row still reads <em>warn</em> rather than <em>fail</em>, because
            an empty description is usually not a fault in the content. A
            password-protected page is left unsummarised deliberately, in case
            the secret is in the body. A shortcode or page-builder layout reads
            as empty because the scan quotes plain text and does not render the
            page. Only the third case, a post with prose that simply has no
            sentence worth quoting yet, is something to go and write. The hint
            on the row names which of the three you are looking at.
          </p>
          <p>
            The 50 to 160 band is a convention rather than a published limit,
            so the band itself is our call even though the signal is documented.
          </p>

          <h3 className="pt-4 text-lg font-bold text-ink">
            Answer-first opening <span className="text-muted">· 15 ·</span>{' '}
            <Evidence level="observed" />
          </h3>
          <Table
            head={['Status', 'Condition']}
            rows={[
              ['Pass', 'First paragraph is 40 to 360 characters'],
              ['Warn', '1 to 39, or 361 and over'],
              ['Fail', 'No paragraph with any text in it'],
            ]}
          />
          <p>
            The first paragraph that carries text, measured after tags are
            stripped and whitespace is collapsed. The idea is that a
            self-contained opening can be lifted as an answer without the
            engine having to assemble one.
          </p>
          <p>
            Observed, not documented: engines favouring the lede is a pattern
            people have measured from the outside, not a rule anyone publishes.
          </p>

          <h3 className="pt-4 text-lg font-bold text-ink">
            Question-style headings <span className="text-muted">· 15 ·</span>{' '}
            <Evidence level="observed" />
          </h3>
          <Table
            head={['Status', 'Condition']}
            rows={[
              ['Pass', 'Two or more question headings'],
              ['Warn', 'Exactly one'],
              ['Fail', 'None'],
            ]}
          />
          <p>
            Only <Code>h1</Code> through <Code>h4</Code> are read. A heading
            counts as a question if it ends in a question mark, or if its first
            word is one of:
          </p>
          <CodeBlock>
            {`how  what  why  when  where  which  who
can  does  do  is  are  should  will`}
          </CodeBlock>
          <Callout tone="warn" title="This check only understands English">
            <p>
              The word list is English and is not translated. On a site written
              in another language, only headings that end in a question mark
              will be counted, so expect this check to under-report. If that
              describes your site, treat the 15 points as noise or replace the
              check through the filter below.
            </p>
          </Callout>

          <h3 className="pt-4 text-lg font-bold text-ink">
            FAQ / Q&amp;A blocks <span className="text-muted">· 10 ·</span>{' '}
            <Evidence level="observed" />
          </h3>
          <Table
            head={['Status', 'Condition']}
            rows={[
              ['Pass', 'At least one core Details block is present'],
              ['Warn', 'None found'],
              ['Fail', 'Never. This check cannot fail.'],
            ]}
          />
          <p>
            Detection looks for the core <Code>wp:details</Code> block, which
            is also what the plugin turns into <Code>FAQPage</Code> structured
            data. A third-party FAQ block will not be counted even when it
            renders something that looks identical, because the plugin cannot
            emit FAQPage markup for a shape it does not know how to read.
          </p>
          <p>
            It warns rather than fails because plenty of good pages have no
            business carrying an FAQ.
          </p>

          <h3 className="pt-4 text-lg font-bold text-ink">
            Listed in llms.txt <span className="text-muted">· 10 ·</span>{' '}
            <Evidence level="heuristic" />
          </h3>
          <Table
            head={['Status', 'Condition']}
            rows={[
              ['Pass', 'The post type is included and the post is not noindex'],
              [
                'Warn',
                'llms.txt is on, but this post is excluded (its type is off, or it is set to noindex)',
              ],
              ['Fail', 'AEO Core → llms.txt is off site-wide'],
            ]}
          />
          <p>
            Heuristic, and worth being blunt about: no major answer engine has
            committed to reading <Code>llms.txt</Code>. It is a proposed
            convention. We score it because publishing the file costs nothing
            and the file is correct if the convention is ever adopted, not
            because we have evidence that anything consumes it today.
          </p>

          <h3 className="pt-4 text-lg font-bold text-ink">
            Markdown alternate <span className="text-muted">· 10 ·</span>{' '}
            <Evidence level="heuristic" />
          </h3>
          <Table
            head={['Status', 'Condition']}
            rows={[
              ['Pass', 'Agent Readiness and the Markdown export are both on'],
              ['Warn', 'Exactly one of the two is on'],
              ['Fail', 'Neither is on'],
            ]}
          />
          <p>
            Both halves are needed for the feature to do anything: one serves
            the Markdown rendering, the other advertises it through a{' '}
            <Code>Link</Code> header and a{' '}
            <Code>&lt;link rel=&quot;alternate&quot;&gt;</Code>. Serving it
            without advertising it means nothing finds it; advertising it
            without serving it means a crawler follows the link to nothing.
          </p>
          <p>
            Heuristic for the same reason as llms.txt. Crawlers are free to
            prefer a Markdown alternate and some agents do, but no engine
            documents this as something it looks for.
          </p>

          <h3 className="pt-4 text-lg font-bold text-ink">
            Content depth <span className="text-muted">· 5 ·</span>{' '}
            <Evidence level="observed" />
          </h3>
          <Table
            head={['Status', 'Condition']}
            rows={[
              ['Pass', '300 words or more'],
              ['Warn', '100 to 299'],
              ['Fail', 'Under 100'],
            ]}
          />
          <p>
            Words are counted on the stripped text, split on whitespace. The
            lowest weight on the board, deliberately: word count is the easiest
            thing on this page to game and the weakest proxy for whether a page
            is worth quoting. It is here because very thin pages really do get
            skipped, not because longer is better.
          </p>
        </Section>

        <Section id="grades" kicker="Reading the number" title="Grades">
          <p>
            The letter grade is a band on the same number. The first threshold
            a score meets wins:
          </p>
          <Table
            head={['Grade', 'Score']}
            rows={[
              ['Excellent', '85 and above'],
              ['Good', '70 to 84'],
              ['Fair', '50 to 69'],
              ['Needs work', 'Under 50'],
            ]}
          />
          <p>
            Three of the eight checks are site-wide settings worth 40 points
            between them, so a site with structured data, llms.txt and the
            Markdown alternate all switched on starts every post at 40 before
            anyone writes a word. That is intentional, and it is also why a
            fresh install shows low scores everywhere until the settings are
            configured.
          </p>
        </Section>

        <Section
          id="weights"
          kicker="Honesty"
          title="Where the weights come from"
        >
          <Lead>
            They are our judgement. No engine publishes how it trades these
            signals off against each other, and we have not run an experiment
            that would let us derive them.
          </Lead>
          <p>What the numbers actually encode is an ordering:</p>
          <ul className="list-disc space-y-2 pl-5">
            <li>
              Machine-readable facts beat prose, so structured data sits at the
              top on its own.
            </li>
            <li>
              The three signals about how the page is written (description,
              opening, headings) are treated as equally important, at 15 each.
            </li>
            <li>
              Things that are either a nice-to-have or an unproven bet sit at
              10.
            </li>
            <li>
              Word count sits at 5 because it is the easiest to game.
            </li>
          </ul>
          <p>
            Reasonable people will disagree with that ordering, and we would
            rather you argue with the table than with the number it produces.
            If you have data that contradicts any of it, we would like to see
            it.
          </p>
        </Section>

        <Section
          id="site-score"
          kicker="Dashboard"
          title="The site-wide scores"
        >
          <p>
            The Dashboard shows three numbers, and only one of them is this
            rubric.
          </p>
          <Table
            head={['Score', 'What it is']}
            rows={[
              [
                <strong key="a">AEO</strong>,
                'The mean of the per-post readiness scores across the posts in the scan, computed with the rubric on this page.',
              ],
              [
                <strong key="t">Technical SEO</strong>,
                'A separate pass over site settings (titles and metas, XML sitemap, schema, Open Graph, Twitter cards, breadcrumbs, image alt text, IndexNow, llms.txt, commerce). These are on or off, not scored per post.',
              ],
              [
                <strong key="s">SEO</strong>,
                'A roll-up over those same technical groups.',
              ],
            ]}
          />
          <p>
            The scan is capped, so the AEO number is a sample of recent content
            rather than a census of the whole site. Treat a change of a point or
            two between scans as noise.
          </p>
        </Section>

        <Section
          id="extending"
          kicker="Developers"
          title="Adding your own check"
        >
          <p>
            A theme or add-on can append rows to the checklist. The score
            renormalises, so your weight is relative to the eight built-ins
            rather than added on top of 100.
          </p>
          <CodeBlock caption="functions.php">
            {`add_filter(
    'flexa_seo_aeo/aeo/readiness_checks',
    function ( array $checks, WP_Post $post ): array {
        $checks[] = [
            'id'       => 'my_byline',
            'label'    => __( 'Author byline', 'my-theme' ),
            'status'   => has_byline( $post ) ? 'pass' : 'warn',
            'weight'   => 10,
            'hint'     => __( 'Named authors help engines attribute a quote.', 'my-theme' ),
            'fix'      => null,
            'evidence' => 'observed',
        ];

        return $checks;
    },
    10,
    2
);`}
          </CodeBlock>
          <Table
            head={['Key', 'Notes']}
            rows={[
              ['id', 'Unique string. Used as the React key and for Dashboard tallies.'],
              ['status', 'pass, warn or fail. Anything else scores as a fail.'],
              ['weight', 'Any non-negative integer. Negative values are clamped to 0.'],
              [
                'fix',
                'Either null, or { label, scope, patch } where patch is a partial settings payload POSTed to /settings.',
              ],
              [
                'evidence',
                'documented, observed or heuristic. Missing or unrecognised values are read as heuristic.',
              ],
            ]}
          />
          <Callout tone="info" title="You cannot invent a stronger label">
            <p>
              An evidence value outside the three known levels is replaced with{' '}
              <Code>heuristic</Code> rather than passed through. The labels mean
              what this page says they mean, so a third-party check cannot ship
              a more authoritative-sounding one.
            </p>
          </Callout>
        </Section>

        <Section
          id="disagree"
          kicker="Feedback"
          title="If you disagree with a check"
        >
          <p>
            That is a useful bug report, and a more useful one than &quot;the
            score seems wrong&quot;. The things worth telling us:
          </p>
          <ul className="list-disc space-y-2 pl-5">
            <li>
              Which check, and whether you are disputing the threshold, the
              weight, or the evidence level.
            </li>
            <li>
              What you would set it to, and what you are basing that on. A link
              to vendor documentation is enough to move a check from observed to
              documented.
            </li>
            <li>
              Whether a check is scoring your content wrongly rather than being
              wrong in principle. Those are different fixes.
            </li>
          </ul>
          <p>
            The checks labelled heuristic are the ones most likely to be wrong,
            and we would rather retire one than keep scoring something nothing
            reads.
          </p>
        </Section>
      </main>
    </div>
  );
}
