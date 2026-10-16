import { Sidebar } from '@/components/Sidebar';
import { SERVICES_NAV } from '@/lib/nav';
import { WHATSAPP_DISPLAY, WHATSAPP_URL } from '@/lib/contact';
import { Callout, Lead, Section } from '@/components/ui';

export const metadata = {
  title: 'Custom development services - Flexa SEO - AEO',
  description:
    'Beyond the Flexa SEO - AEO plugin: I also build custom WordPress, ' +
    'WooCommerce and headless web systems for e-commerce, healthcare, and ' +
    'booking businesses. Reach me on WhatsApp.',
};

export default function Services() {
  return (
    <div className="lg:flex">
      <Sidebar
        items={SERVICES_NAV}
        title="Custom development"
        subtitle="Hire me · WordPress, WooCommerce & headless"
        crossLinkHref="/"
        crossLinkLabel="← Back to the scoring rubric"
        secondaryLink={{ href: WHATSAPP_URL, label: 'Chat on WhatsApp →' }}
      />

      <main className="mx-auto w-full max-w-3xl flex-1 px-5 pb-24 sm:px-8 lg:px-12">
        {/* ── Hero ─────────────────────────────────────────────── */}
        <header className="py-14">
          <p className="text-xs font-semibold uppercase tracking-widest text-brand-600">
            Custom development
          </p>
          <h1 className="mt-3 text-4xl font-extrabold tracking-tight text-ink sm:text-5xl">
            Need more than the plugin can do on its own?
          </h1>
          <p className="mt-4 max-w-2xl text-lg leading-8 text-ink-soft">
            I&apos;m the developer behind Flexa SEO - AEO. Outside of this
            plugin, I help product owners design and ship custom web systems -
            online stores, patient portals, booking platforms - built on
            WordPress / WooCommerce or modern headless stacks.
          </p>
          <div className="mt-6 flex flex-wrap items-center gap-3">
            <WhatsAppButton href={WHATSAPP_URL} />
            <a
              href="#what-i-build"
              className="inline-flex items-center rounded-md border border-line bg-canvas px-4 py-2.5 text-sm font-semibold text-ink-soft transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60"
            >
              See what I can build
            </a>
          </div>
        </header>

        {/* ── Overview ─────────────────────────────────────────── */}
        <Section id="overview" kicker="Who I am" title="A WordPress developer who ships">
          <Lead>
            I&apos;ve spent years building production WordPress plugins,
            WooCommerce stores, and React-driven admin experiences (this
            documentation site is one of them). Engagements range from a
            single bug fix to a months-long custom build.
          </Lead>
          <p>
            Every project starts with a short scoping call so I can either
            quote it confidently or tell you it&apos;s not the right fit.
            No surprise invoices, no &ldquo;final 20%&rdquo; that takes
            another six weeks.
          </p>
        </Section>

        {/* ── What I build ─────────────────────────────────────── */}
        <Section id="what-i-build" kicker="Services" title="What I build">
          <Lead>
            Pick the closest match - or describe your problem and I&apos;ll
            tell you which bucket it lands in.
          </Lead>
          <div className="grid gap-4 sm:grid-cols-2">
            <ServiceCard
              title="WordPress plugins"
              body="Custom plugins from scratch, or extensions on top of WooCommerce, Easy Digital Downloads, LearnDash, Memberpress, etc. Includes WP.org-submission-ready code review."
            />
            <ServiceCard
              title="WooCommerce stores"
              body="Theme builds, checkout customisations, B2B pricing rules, payment / shipping integrations, headless storefronts on Next.js."
            />
            <ServiceCard
              title="React admin dashboards"
              body="Modern in-WP admin UIs (React + TypeScript + shadcn/ui) backed by the WP REST API - like the Flexa SEO - AEO admin app."
            />
            <ServiceCard
              title="Headless &amp; integrations"
              body="Decoupled front-ends (Next.js / Astro) talking to WordPress, plus third-party integrations: Stripe, Twilio, HubSpot, Mailchimp, Klaviyo, Zoho."
            />
            <ServiceCard
              title="Performance &amp; SEO audits"
              body="Core Web Vitals, query profiling, caching strategy, image / asset pipeline, schema markup. Concrete report with before / after numbers."
            />
            <ServiceCard
              title="Rescue &amp; maintenance"
              body="Inherit a broken / abandoned WP site, untangle it, get it back on supported versions, set up a sane deploy flow, hand it back documented."
            />
          </div>
        </Section>

        {/* ── Industries ───────────────────────────────────────── */}
        <Section id="industries" kicker="Domain experience" title="Industries I&rsquo;ve shipped for">
          <Lead>
            Industry knowledge matters as much as code. These are the
            verticals where I&apos;ve built enough to know the rough edges
            before you do.
          </Lead>
          <div className="grid gap-4 sm:grid-cols-3">
            <IndustryCard
              emoji="🛒"
              title="E-commerce"
              body="WooCommerce stores, B2B portals, subscription products, multi-currency, headless storefronts, abandoned-cart automation."
            />
            <IndustryCard
              emoji="🩺"
              title="Healthcare"
              body="Patient portals, appointment intake forms, HIPAA-aware data handling, secure document exchange, clinician dashboards."
            />
            <IndustryCard
              emoji="📅"
              title="Booking &amp; scheduling"
              body="Multi-resource calendars, room / staff allocation, deposits &amp; refunds, SMS / email reminders, Google Calendar sync."
            />
          </div>
          <p>
            Not on the list? Reach out anyway - most builds carry across
            verticals. If I&apos;ve never touched your industry I&apos;ll say
            so.
          </p>
        </Section>

        {/* ── Process ──────────────────────────────────────────── */}
        <Section id="process" kicker="How I work" title="From first message to launch">
          <ol className="space-y-4">
            <ProcessStep
              n={1}
              title="Discovery call (free, ~30 min)"
              body="We talk through the problem on WhatsApp / Google Meet. I push back on anything that smells wrong before you sign anything."
            />
            <ProcessStep
              n={2}
              title="Written scope &amp; fixed quote"
              body="You get a one-page scope: what's in, what's out, deliverables, timeline, price. Fixed-price for clear work, hourly for ongoing maintenance."
            />
            <ProcessStep
              n={3}
              title="Build in the open"
              body="Weekly demo on a staging URL. Code in your GitHub from day one. You can stop at any milestone and keep everything that's been shipped."
            />
            <ProcessStep
              n={4}
              title="Launch &amp; handover"
              body="Deploy, smoke-test, documentation, a 14-day warranty window for anything I missed. Optional retainer for ongoing work."
            />
          </ol>
          <Callout tone="info" title="What I don&rsquo;t do">
            <ul className="list-disc space-y-1 pl-5">
              <li>Page-builder (Elementor / Divi) cosmetic redesigns.</li>
              <li>SEO link-building or content writing.</li>
              <li>Engagements where the &ldquo;requirements&rdquo; are a
                Slack channel without a single source of truth.</li>
            </ul>
          </Callout>
        </Section>

        {/* ── Contact ──────────────────────────────────────────── */}
        <Section id="contact" kicker="Let&rsquo;s talk" title="Get in touch">
          <Lead>
            WhatsApp is the fastest channel - usually a same-day reply during
            UTC+7 business hours. Feel free to lead with a one-paragraph
            problem statement; you don&apos;t need to have it scoped before
            reaching out.
          </Lead>

          <div className="rounded-xl border border-line bg-surface p-6 shadow-sm">
            <p className="text-xs font-semibold uppercase tracking-widest text-brand-600">
              WhatsApp
            </p>
            <p className="mt-1 font-mono text-2xl font-bold text-ink">
              {WHATSAPP_DISPLAY}
            </p>
            <p className="mt-2 text-sm text-ink-soft">
              Tap the button to open WhatsApp with a starter message
              pre-filled - edit it freely, of course.
            </p>
            <div className="mt-5">
              <WhatsAppButton href={WHATSAPP_URL} />
            </div>
          </div>

          <Callout tone="info" title="Prefer something other than WhatsApp?">
            <p>
              Tell me in the first message and we&apos;ll switch - email,
              Telegram, Signal, scheduled Google Meet all work. WhatsApp is
              just where I keep an eye out fastest.
            </p>
          </Callout>
        </Section>
      </main>
    </div>
  );
}

/* ──────────────────────────────────────────────────────────────
 * Local presentational helpers - kept on this page (not promoted
 * to components/ui.tsx) because they only render here.
 * ────────────────────────────────────────────────────────────── */

function ServiceCard({ title, body }: { title: string; body: string }) {
  return (
    <div className="rounded-xl border border-line bg-surface p-5 shadow-sm transition-colors hover:border-brand-300">
      <h3 className="text-base font-semibold text-ink">{title}</h3>
      <p className="mt-2 text-sm leading-6 text-ink-soft">{body}</p>
    </div>
  );
}

function IndustryCard({
  emoji,
  title,
  body,
}: {
  emoji: string;
  title: string;
  body: string;
}) {
  return (
    <div className="rounded-xl border border-line bg-surface p-5 shadow-sm">
      <div className="text-2xl" aria-hidden>
        {emoji}
      </div>
      <h3 className="mt-2 text-base font-semibold text-ink">{title}</h3>
      <p className="mt-2 text-sm leading-6 text-ink-soft">{body}</p>
    </div>
  );
}

function ProcessStep({
  n,
  title,
  body,
}: {
  n: number;
  title: string;
  body: string;
}) {
  return (
    <li className="flex gap-4">
      <span className="flex size-8 flex-none items-center justify-center rounded-full bg-brand-500 font-bold text-white">
        {n}
      </span>
      <div className="flex-1 leading-7">
        <p className="font-semibold text-ink">{title}</p>
        <p className="text-ink-soft">{body}</p>
      </div>
    </li>
  );
}

/**
 * WhatsApp click-to-chat button. Inline-SVG logo so the bundle stays
 * dependency-free; brand green (#25D366) is the official wordmark color
 * and the only place that hue appears on the site, so it reads as a
 * platform affordance, not a theme break.
 */
function WhatsAppButton({ href }: { href: string }) {
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className="inline-flex items-center gap-2 rounded-md bg-[#25D366] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#1ebe5d]"
    >
      <svg
        viewBox="0 0 32 32"
        className="size-5"
        aria-hidden
        fill="currentColor"
      >
        <path d="M19.11 17.205c-.372 0-1.088 1.39-1.518 1.39a.63.63 0 0 1-.315-.1c-.802-.402-1.504-.817-2.163-1.447-.545-.516-1.146-1.29-1.46-1.963a.426.426 0 0 1-.073-.215c0-.33.99-.945.99-1.49 0-.143-.73-2.39-.832-2.564-.143-.244-.487-.273-.731-.273-.317 0-.917.215-1.146.401-.7.602-1.04 1.39-1.04 2.205 0 1.39.687 2.764 1.604 3.831 1.146 1.319 2.42 2.435 4.027 3.052.572.215 1.146.402 1.748.487.6.085 1.146.057 1.69-.043 1.046-.187 1.776-.832 2.135-1.776.115-.288.172-.6.172-.9 0-.215-.057-.43-.115-.616-.115-.358-.143-.358-.402-.487-.286-.143-1.246-.616-1.418-.616z" />
        <path d="M16 0C7.16 0 0 7.16 0 16c0 2.835.745 5.6 2.135 8.048L0 32l8.106-2.121A15.93 15.93 0 0 0 16 32c8.84 0 16-7.16 16-16S24.84 0 16 0zm0 29.387c-2.6 0-5.146-.7-7.348-2.02l-.516-.315-5.46 1.432 1.46-5.317-.343-.545A13.34 13.34 0 0 1 2.613 16C2.613 8.61 8.61 2.613 16 2.613S29.387 8.61 29.387 16 23.39 29.387 16 29.387z" />
      </svg>
      Chat on WhatsApp
    </a>
  );
}
