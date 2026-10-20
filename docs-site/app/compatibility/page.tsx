import { Sidebar } from '@/components/Sidebar';
import { COMPATIBILITY_NAV } from '@/lib/nav';
import {
  Callout,
  Code,
  CodeBlock,
  Lead,
  Section,
  Table,
} from '@/components/ui';

export const metadata = {
  title: 'Flexa SEO - AEO - Compatibility matrix',
  description:
    'Tested behaviour alongside Yoast SEO, Rank Math, All in One SEO and ' +
    'SEOPress, at exact versions, with the duplicate output each pairing ' +
    'produces and the migration coverage for each.',
};

/** Green tick / red cross / neutral dash, for the yes-no cells. */
function Yes() {
  return (
    <span className="font-semibold text-emerald-600 dark:text-emerald-400">
      Yes
    </span>
  );
}

function No() {
  return <span className="font-semibold text-rose-600 dark:text-rose-400">No</span>;
}

/** A head-tag count, highlighted when it means duplicate output. */
function Count({ n }: { n: number }) {
  return (
    <span
      className={
        n > 1
          ? 'font-semibold text-rose-600 dark:text-rose-400'
          : 'text-ink-soft'
      }
    >
      {n}
    </span>
  );
}

export default function Compatibility() {
  return (
    <div className="lg:flex">
      <Sidebar
        items={COMPATIBILITY_NAV}
        title="Flexa SEO - AEO"
        subtitle="Compatibility · v0.6.1"
        crossLinkHref="/"
        crossLinkLabel="← Back to the scoring rubric"
        secondaryLink={{ href: '/services', label: 'Need a custom build?' }}
      />

      <main className="mx-auto w-full max-w-3xl flex-1 px-5 pb-24 sm:px-8 lg:px-12">
        {/* ── Hero ─────────────────────────────────────────────── */}
        <header className="py-14">
          <p className="text-xs font-semibold uppercase tracking-widest text-brand-600">
            Compatibility · v0.6.1
          </p>
          <h1 className="mt-3 text-4xl font-extrabold tracking-tight text-ink sm:text-5xl">
            Running alongside the other SEO plugins
          </h1>
          <p className="mt-4 max-w-2xl text-lg leading-8 text-ink-soft">
            Measured, not assumed. Every number below came from activating the
            pairing on a real install and reading what came out of the page.
          </p>
        </header>

        <Section id="overview" kicker="Scope" title="What was tested">
          <Lead>
            The short answer has not changed: run one SEO plugin at a time.
            This page exists so you can see exactly what happens when you do
            not, and so a bug report can say &quot;this pairing, this version,
            this tag&quot; instead of &quot;it conflicts&quot;.
          </Lead>
          <p>Four things were checked for each of the four plugins:</p>
          <ul className="list-disc space-y-2 pl-5">
            <li>
              What the <Code>&lt;head&gt;</Code> of a single post looks like
              with both plugins active, tag by tag.
            </li>
            <li>
              Which plugin ends up owning <Code>/sitemap.xml</Code>, and what{' '}
              <Code>robots.txt</Code> ends up advertising.
            </li>
            <li>
              How many per-post fields the one-click migration actually carries
              over.
            </li>
            <li>
              How many fields the read-fallback picks up while both plugins are
              still installed.
            </li>
          </ul>
        </Section>

        <Section id="baseline" kicker="Reproducibility" title="The test baseline">
          <p>
            Results below were produced on 17 October 2026 against these exact
            versions. If you are reporting something that disagrees with this
            page, please include your equivalent list.
          </p>
          <Table
            head={['Component', 'Version']}
            rows={[
              ['WordPress', '7.1.3'],
              ['PHP', '8.5.3'],
              ['WooCommerce', '11.0.1'],
              [<strong key="f">Flexa SEO - AEO</strong>, <strong key="v">0.6.1</strong>],
              ['Yoast SEO', '28.7'],
              ['Rank Math', '1.0.270'],
              ['All in One SEO', '5.0.3'],
              ['SEOPress', '10.1'],
            ]}
          />
          <p>
            Each competitor was activated on its own, alongside Flexa SEO, with
            default settings and no setup wizard run. A single published post
            was requested over HTTP and the head tags counted. The plugin was
            then deactivated before the next one was activated, so no two
            competitors were ever live together.
          </p>
          <Callout tone="warn" title="Default settings only">
            <p>
              None of the four were configured. A plugin you have actually set
              up may emit more than it did here, not less, so treat these counts
              as the floor.
            </p>
          </Callout>
          <Callout tone="info" title="What this re-run changed">
            <p>
              Every number on this page was measured again for 0.6.1, on the
              same install and the same post as the 0.5.5 run. One of them
              moved. Flexa now writes its robots directives through
              WordPress&rsquo;s own <Code>wp_robots</Code> filter, and so do
              Rank Math and All in One SEO, so each of those two pairings now
              produces one <Code>meta name=robots</Code> tag where it used to
              produce two. Read the note under the table before counting that
              as a clean result.
            </p>
            <p>
              Sitemap ownership and the <Code>robots.txt</Code> lines were
              measured again and came out the same. The migration and
              read-fallback tables below were not re-run on a test post this
              time; they were checked against the field maps 0.6.1 actually
              ships, which are the same maps the 0.5.5 run measured. Rank Math
              1.0.280 and SEOPress 10.3 were released after this run and have
              not been tested, so the versions in the table above are the ones
              every number below came from.
            </p>
          </Callout>
        </Section>

        <Section id="matrix" kicker="Summary" title="Compatibility matrix">
          <Table
            head={[
              'Plugin',
              'Safe to run together',
              'Migration',
              'Read-fallback',
              'Detected as a conflict',
            ]}
            rows={[
              [
                <strong key="n">Yoast SEO 28.7</strong>,
                <No key="s" />,
                <Yes key="m" />,
                <Yes key="r" />,
                <Yes key="d" />,
              ],
              [
                <strong key="n">Rank Math 1.0.270</strong>,
                <No key="s" />,
                <Yes key="m" />,
                <Yes key="r" />,
                <Yes key="d" />,
              ],
              [
                <strong key="n">SEOPress 10.1</strong>,
                <No key="s" />,
                <Yes key="m" />,
                <Yes key="r" />,
                <Yes key="d" />,
              ],
              [
                <strong key="n">All in One SEO 5.0.3</strong>,
                <No key="s" />,
                <No key="m" />,
                <No key="r" />,
                <Yes key="d" />,
              ],
            ]}
          />
          <Table
            head={['Column', 'What it means']}
            rows={[
              [
                'Safe to run together',
                'Whether the pairing produces a clean page. It never does: every one of the four duplicates head tags.',
              ],
              [
                'Migration',
                'Settings → Migration can read this plugin’s per-post fields and write them into Flexa’s.',
              ],
              [
                'Read-fallback',
                'Flexa reads this plugin’s meta directly when it has none of its own, so the front end keeps working mid-move.',
              ],
              [
                'Detected as a conflict',
                'The Setup Assistant notices the plugin is active and warns about duplicate output.',
              ],
            ]}
          />
          <Callout tone="warn" title="One gap worth knowing about">
            <p>
              All in One SEO is not supported in either direction. Flexa warns
              you that it is active, so you will not be left guessing why the
              head has two of everything, but there is no migration to run and
              no read-fallback to carry you through the move. Copy the fields
              you care about by hand before you switch off.
            </p>
          </Callout>
        </Section>

        <Section
          id="duplicate-tags"
          kicker="Measured"
          title="Duplicate head tags"
        >
          <p>
            How many times each tag appeared in the <Code>&lt;head&gt;</Code>{' '}
            of one published post. One is correct. Anything higher means both
            plugins emitted it and search engines get to pick.
          </p>
          <Table
            head={[
              'Tag',
              'Flexa alone',
              '+ Yoast',
              '+ Rank Math',
              '+ AIOSEO',
              '+ SEOPress',
            ]}
            rows={[
              [
                <Code key="t">link rel=canonical</Code>,
                <Count key="b" n={1} />,
                <Count key="y" n={2} />,
                <Count key="r" n={2} />,
                <Count key="a" n={2} />,
                <Count key="s" n={2} />,
              ],
              [
                <Code key="t">meta name=description</Code>,
                <Count key="b" n={1} />,
                <Count key="y" n={1} />,
                <Count key="r" n={2} />,
                <Count key="a" n={2} />,
                <Count key="s" n={2} />,
              ],
              [
                <Code key="t">og:title</Code>,
                <Count key="b" n={1} />,
                <Count key="y" n={2} />,
                <Count key="r" n={2} />,
                <Count key="a" n={2} />,
                <Count key="s" n={2} />,
              ],
              [
                <Code key="t">og:description</Code>,
                <Count key="b" n={1} />,
                <Count key="y" n={2} />,
                <Count key="r" n={2} />,
                <Count key="a" n={2} />,
                <Count key="s" n={2} />,
              ],
              [
                <Code key="t">og:url</Code>,
                <Count key="b" n={1} />,
                <Count key="y" n={2} />,
                <Count key="r" n={2} />,
                <Count key="a" n={2} />,
                <Count key="s" n={2} />,
              ],
              [
                <Code key="t">twitter:card</Code>,
                <Count key="b" n={1} />,
                <Count key="y" n={2} />,
                <Count key="r" n={2} />,
                <Count key="a" n={2} />,
                <Count key="s" n={2} />,
              ],
              [
                <Code key="t">twitter:title</Code>,
                <Count key="b" n={1} />,
                <Count key="y" n={1} />,
                <Count key="r" n={2} />,
                <Count key="a" n={2} />,
                <Count key="s" n={2} />,
              ],
              [
                <Code key="t">meta name=robots</Code>,
                <Count key="b" n={1} />,
                <Count key="y" n={1} />,
                <Count key="r" n={1} />,
                <Count key="a" n={1} />,
                <Count key="s" n={2} />,
              ],
              [
                <Code key="t">application/ld+json</Code>,
                <Count key="b" n={1} />,
                <Count key="y" n={2} />,
                <Count key="r" n={2} />,
                <Count key="a" n={2} />,
                <Count key="s" n={1} />,
              ],
            ]}
          />
          <p>
            The ones in the Yoast column are not Yoast behaving better. They
            are Yoast declining to emit a tag it had no value for on an
            unconfigured install. Give it a description and the count goes to
            two like the rest.
          </p>
          <Callout tone="warn" title="One robots tag is not the same as the right robots tag">
            <p>
              Four of the five columns show a single{' '}
              <Code>meta name=robots</Code> tag, because Flexa, Yoast, Rank
              Math and All in One SEO all write their directives through
              WordPress&rsquo;s <Code>wp_robots</Code> filter and the filter
              returns one array. That removes the duplicate tag. It does not
              decide whose directives survive: whichever plugin filters last
              owns the value.
            </p>
            <p>
              With All in One SEO 5.0.3 active and unconfigured, the single tag
              came out as <Code>max-image-preview:large</Code> and nothing
              else. Flexa&rsquo;s <Code>index, follow</Code> had been dropped
              on the way through. SEOPress is the opposite case: it prints its
              own tag instead of filtering, so the page carries two, one from
              each plugin, with the same directives in both.
            </p>
          </Callout>
          <p>
            Reproduce it on your own site with nothing more than curl:
          </p>
          <CodeBlock caption="Count canonical tags on a post">
            {`curl -s https://example.com/your-post/ \\
  | grep -c '<link rel="canonical"'`}
          </CodeBlock>
        </Section>

        <Section
          id="sitemaps"
          kicker="Measured"
          title="Sitemaps and robots.txt"
        >
          <p>
            Flexa SEO serves its sitemap index at <Code>/sitemap.xml</Code>.
            That is the same address SEOPress and All in One SEO want, while
            Yoast and Rank Math use <Code>/sitemap_index.xml</Code>.
          </p>
          <p>
            While Flexa&rsquo;s sitemap is on, core&rsquo;s own{' '}
            <Code>/wp-sitemap.xml</Code> is switched off, so the site has one
            index rather than two listing the same URLs, and{' '}
            <Code>robots.txt</Code> advertises one address. If you need core&rsquo;s
            back, return <Code>true</Code> from{' '}
            <Code>flexa_seo_aeo/sitemap/keep_core_sitemap</Code>.
          </p>
          <Table
            head={['Active alongside', '/sitemap.xml serves', 'robots.txt advertises']}
            rows={[
              [
                <strong key="n">Nothing</strong>,
                'Flexa',
                'sitemap.xml',
              ],
              [
                <strong key="n">Yoast</strong>,
                'Flexa',
                'sitemap.xml and sitemap_index.xml',
              ],
              [
                <strong key="n">Rank Math</strong>,
                'Flexa',
                'sitemap_index.xml and sitemap.xml',
              ],
              [
                <strong key="n">All in One SEO</strong>,
                <span key="a" className="font-semibold text-rose-600 dark:text-rose-400">
                  AIOSEO, not Flexa
                </span>,
                'sitemap.rss only',
              ],
              [
                <strong key="n">SEOPress</strong>,
                'Flexa',
                'sitemap.xml and sitemaps.xml',
              ],
            ]}
          />
          <p>
            All in One SEO is the one hard route conflict in the set. It claims{' '}
            <Code>/sitemap.xml</Code> and wins it, so Flexa&rsquo;s sitemap
            becomes unreachable, and its <Code>robots.txt</Code> handling drops
            Flexa&rsquo;s sitemap line entirely. Nothing in either plugin tells
            you this has happened.
          </p>
          <Callout tone="info" title="Flush rewrites before you judge a 404">
            <p>
              Activating or deactivating any of these plugins leaves the rewrite
              rules stale, and a sitemap URL will 404 until they are rebuilt
              even though nothing is actually conflicting. Rank
              Math&rsquo;s <Code>/sitemap_index.xml</Code> did exactly that
              during testing, and served a 200 straight after a flush. Re-save
              Settings → Permalinks, or run{' '}
              <Code>wp rewrite flush</Code>, before reporting a missing sitemap.
            </p>
          </Callout>
        </Section>

        <Section id="migration" kicker="Measured" title="Migration coverage">
          <p>
            Settings → Migration copies another plugin&rsquo;s per-post fields
            into Flexa&rsquo;s own. Eleven fields were written on a test post
            for each source, then migrated and read back.
          </p>
          <Table
            head={['Field', 'Yoast', 'Rank Math', 'SEOPress', 'AIOSEO']}
            rows={[
              ['SEO title', <Yes key="y" />, <Yes key="r" />, <Yes key="s" />, <No key="a" />],
              ['Meta description', <Yes key="y" />, <Yes key="r" />, <Yes key="s" />, <No key="a" />],
              ['Canonical URL', <Yes key="y" />, <Yes key="r" />, <Yes key="s" />, <No key="a" />],
              ['OG title', <Yes key="y" />, <Yes key="r" />, <Yes key="s" />, <No key="a" />],
              ['OG description', <Yes key="y" />, <Yes key="r" />, <Yes key="s" />, <No key="a" />],
              ['OG image', <Yes key="y" />, <Yes key="r" />, <Yes key="s" />, <No key="a" />],
              ['Twitter title', <Yes key="y" />, <Yes key="r" />, <Yes key="s" />, <No key="a" />],
              ['Twitter description', <Yes key="y" />, <Yes key="r" />, <Yes key="s" />, <No key="a" />],
              ['Twitter image', <Yes key="y" />, <Yes key="r" />, <Yes key="s" />, <No key="a" />],
              ['noindex', <Yes key="y" />, <Yes key="r" />, <Yes key="s" />, <No key="a" />],
              ['nofollow', <Yes key="y" />, <Yes key="r" />, <Yes key="s" />, <No key="a" />],
            ]}
          />
          <p>
            Template tokens are converted as part of the move. Rank
            Math&rsquo;s single-delimiter <Code>%sitename%</Code> becomes{' '}
            <Code>%%sitename%%</Code>, and names that differ between
            vocabularies are mapped across, so SEOPress&rsquo;s{' '}
            <Code>%%post_title%%</Code> becomes <Code>%%title%%</Code> and its{' '}
            <Code>%%sitetitle%%</Code> becomes <Code>%%sitename%%</Code>. A
            token with no equivalent resolves to an empty string rather than
            printing itself.
          </p>
          <p>
            SEOPress migration shipped in 1.0.0. Its column was measured on 20
            October 2026, against SEOPress 10.1 on the same install: all eleven
            fields were written on a test post, migrated and read back, and a
            second run skipped the post as already migrated. The Yoast and Rank
            Math columns are from the earlier runs described above.
          </p>
          <p>
            Migration never deletes the source plugin&rsquo;s meta. You can run
            it, check the result, and deactivate the old plugin afterwards, in
            that order.
          </p>
        </Section>

        <Section id="fallback" kicker="Measured" title="Read-fallback coverage">
          <p>
            Separately from migration, Flexa reads another plugin&rsquo;s meta
            on the fly when a post has nothing of its own. This is what keeps
            the front end intact the moment you activate Flexa, before any
            migration has run. It covers fewer fields:
          </p>
          <Table
            head={['Field', 'Yoast', 'Rank Math', 'SEOPress', 'AIOSEO']}
            rows={[
              ['SEO title', <Yes key="y" />, <Yes key="r" />, <Yes key="s" />, <No key="a" />],
              ['Meta description', <Yes key="y" />, <Yes key="r" />, <Yes key="s" />, <No key="a" />],
              ['Canonical URL', <Yes key="y" />, <Yes key="r" />, <Yes key="s" />, <No key="a" />],
              ['OG title', <Yes key="y" />, <Yes key="r" />, <Yes key="s" />, <No key="a" />],
              ['OG description', <Yes key="y" />, <Yes key="r" />, <Yes key="s" />, <No key="a" />],
              ['OG image', <Yes key="y" />, <Yes key="r" />, <Yes key="s" />, <No key="a" />],
              ['Twitter fields', <No key="y" />, <No key="r" />, <No key="s" />, <No key="a" />],
              ['noindex / nofollow', <No key="y" />, <No key="r" />, <No key="s" />, <No key="a" />],
            ]}
          />
          <Callout tone="warn" title="Robots flags are not covered by the fallback">
            <p>
              A post you marked <Code>noindex</Code> in Yoast, Rank Math or
              SEOPress is <strong>not</strong> noindex to Flexa until you either
              run the migration or tick the box again. The migration carries
              it from all three, so run it before you deactivate the old
              plugin.
            </p>
          </Callout>
        </Section>

        <Section id="known-issues" kicker="Open" title="Known issues">
          <Table
            head={['Issue', 'Affects', 'Status']}
            rows={[
              [
                <span key="i">
                  All in One SEO takes over <Code>/sitemap.xml</Code> and
                  Flexa&rsquo;s sitemap becomes unreachable, with no warning in
                  either plugin.
                </span>,
                'Flexa + All in One SEO',
                'Open, will not be fixed: run one SEO plugin',
              ],
              [
                <span key="i">
                  With All in One SEO active, the single{' '}
                  <Code>meta name=robots</Code> tag carries AIOSEO&rsquo;s
                  directives and Flexa&rsquo;s are dropped. Two plugins
                  filtering <Code>wp_robots</Code> means one of them loses.
                </span>,
                'Flexa + AIOSEO',
                'Open, will not be fixed: run one SEO plugin',
              ],
            ]}
          />
        </Section>

        <Section
          id="reporting"
          kicker="Feedback"
          title="Reporting a conflict"
        >
          <p>
            A report that can be acted on without a round trip contains these
            five things:
          </p>
          <ul className="list-disc space-y-2 pl-5">
            <li>
              Versions of WordPress, PHP, Flexa SEO and the other plugin, in the
              shape of the baseline table above.
            </li>
            <li>The URL of a page where you can see it, if the site is public.</li>
            <li>
              Which tag or route is wrong, and what you expected instead. A{' '}
              <Code>grep -c</Code> count is worth a paragraph of description.
            </li>
            <li>
              Whether the other plugin was configured or left at defaults.
            </li>
            <li>
              Whether it still happens with every other plugin deactivated and a
              default theme active.
            </li>
          </ul>
          <p>
            Pairings already on this page do not need re-reporting unless your
            result differs from what is written here. A result that differs is
            exactly the interesting case.
          </p>
        </Section>
      </main>
    </div>
  );
}
