import {
  AlertTriangle,
  CheckCircle2,
  ChevronDown,
  ChevronRight,
  ExternalLink,
  Gauge,
  Lightbulb,
  ListChecks,
  Pencil,
  RefreshCw,
  Sparkles,
  TrendingUp,
  XCircle,
  Zap,
  type LucideIcon,
} from "lucide-react";
import { type ReactNode, useState } from "react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/cn";
import { __ } from "@/lib/i18n";
import { useUiStore } from "@/lib/store";
import { getPluginGlobal } from "@/lib/wp";
import { CachePromoCard } from "./CachePromoCard";
import { SetupCard } from "./SetupCard";
import {
  type AttentionPage,
  type DashboardReport,
  type EvidenceLevel,
  type HealthGroup,
  type PageIssue,
  type Recommendation,
  rescanPage,
  type SettingFix,
  type TrendPoint,
  useApplyFix,
  useDashboard,
  useScan,
} from "./useDashboard";

const GRADE_LABEL: Record<string, string> = {
  excellent: __("Excellent"),
  good: __("Good"),
  fair: __("Fair"),
  poor: __("Needs work"),
};

/** Tailwind tone classes for a 0–100 score by its grade band. */
function gradeTone(grade: string): { text: string; ring: string; bg: string } {
  switch (grade) {
    case "excellent":
    case "good":
      return {
        text: "fsa:text-emerald-600",
        ring: "fsa:text-emerald-500",
        bg: "fsa:bg-emerald-50",
      };
    case "fair":
      return {
        text: "fsa:text-amber-600",
        ring: "fsa:text-amber-500",
        bg: "fsa:bg-amber-50",
      };
    default:
      return {
        text: "fsa:text-red-600",
        ring: "fsa:text-red-500",
        bg: "fsa:bg-red-50",
      };
  }
}

function scrollToId(id: string) {
  document
    .getElementById(id)
    ?.scrollIntoView({ behavior: "smooth", block: "start" });
}

export function DashboardPage() {
  const report = useDashboard();
  const scan = useScan();
  const openSection = useUiStore((s) => s.openSection);

  if (report.isLoading) {
    return (
      <div className="fsa:mx-auto fsa:max-w-6xl fsa:p-6 fsa:text-sm fsa:text-slate-500">
        {__("Loading dashboard…")}
      </div>
    );
  }
  if (report.isError || !report.data) {
    return (
      <div className="fsa:mx-auto fsa:max-w-6xl fsa:m-6 fsa:rounded-md fsa:bg-red-50 fsa:p-4 fsa:text-sm fsa:text-red-700">
        {__("Failed to load the dashboard:")}{" "}
        {(report.error as Error)?.message ?? __("Unknown error.")}
      </div>
    );
  }

  const data = report.data;

  const onScan = () => scan.mutate();

  const onRecommendation = (rec: Recommendation) => {
    if (rec.target === "pages") {
      scrollToId("flexa-seo-aeo-pages");
    } else {
      openSection(rec.target);
    }
  };

  const canManageSettings = getPluginGlobal().canManageSettings;
  const canScan = getPluginGlobal().canScan;

  return (
    <div className="fsa:mx-auto fsa:max-w-6xl fsa:px-6 fsa:pt-8 fsa:pb-12">
      {/* Setup Assistant re-entry, only for users who can manage settings */}
      {canManageSettings && <SetupCard />}

      {/* Flexa Cache suggestion; renders nothing unless the server sent one */}
      {canManageSettings && <CachePromoCard />}

      {/* Header */}
      <div className="fsa:mb-6 fsa:flex fsa:flex-wrap fsa:items-start fsa:justify-between fsa:gap-4">
        <div className="fsa:space-y-1">
          <h1 className="fsa:text-3xl fsa:font-bold fsa:text-slate-900">
            {__("Dashboard")}
          </h1>
          <p className="fsa:text-sm fsa:text-slate-600">
            {__(
              "How healthy is your site, what is wrong, and what to fix next.",
            )}
          </p>
        </div>
        <div className="fsa:flex fsa:items-center fsa:gap-3">
          <span className="fsa:text-xs fsa:text-slate-500">
            {data.scanned > 0
              ? sprintfLike(__("Last scan analysed %d posts."), data.scanned)
              : __("No scan yet.")}
          </span>
          {canScan && (
            <Button
              onClick={onScan}
              disabled={scan.isPending}
              className="fsa:gap-2"
            >
              <RefreshCw
                className={cn(
                  "fsa:h-4 fsa:w-4",
                  scan.isPending && "fsa:animate-spin",
                )}
                aria-hidden
              />
              {scan.isPending ? __("Scanning…") : __("Scan now")}
            </Button>
          )}
        </div>
      </div>

      {/* Overall scores */}
      <div className="fsa:grid fsa:grid-cols-1 fsa:gap-4 fsa:sm:grid-cols-3">
        <ScoreCard
          icon={Gauge}
          label={__("SEO Score")}
          score={data.seo_score}
          grade={data.seo_grade}
        />
        <ScoreCard
          icon={Sparkles}
          label={__("AEO Score")}
          score={data.aeo_score}
          grade={data.aeo_grade}
        />
        <ScoreCard
          icon={ListChecks}
          label={__("Technical SEO")}
          score={data.technical_score}
          grade={data.technical_grade}
        />
      </div>

      {/* Trend + issues */}
      <div className="fsa:mt-4 fsa:grid fsa:grid-cols-1 fsa:gap-4 fsa:lg:grid-cols-3">
        <div className="fsa:lg:col-span-2">
          <TrendCard trend={data.trend} />
        </div>
        <IssuesCard issues={data.issues} />
      </div>

      {/* Health */}
      <div className="fsa:mt-4 fsa:grid fsa:grid-cols-1 fsa:gap-4 fsa:lg:grid-cols-2">
        <HealthCard title={__("SEO Health")} groups={data.seo_health} />
        <HealthCard title={__("AEO Health")} groups={data.aeo_health} />
      </div>

      {/* Recommended actions */}
      <div className="fsa:mt-4">
        <RecommendationsCard
          items={data.recommendations}
          onPick={onRecommendation}
        />
      </div>

      {/* Pages needing attention */}
      <div id="flexa-seo-aeo-pages" className="fsa:mt-4 fsa:scroll-mt-6">
        <PagesCard data={data} />
      </div>
    </div>
  );
}

/** Minimal %d interpolation — the wp.i18n sprintf isn't needed for one integer. */
function sprintfLike(template: string, n: number): string {
  return template.replace("%d", String(n));
}

function Card({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "fsa:rounded-xl fsa:border fsa:border-slate-200 fsa:bg-white fsa:shadow-sm",
        className,
      )}
    >
      {children}
    </div>
  );
}

function CardHeader({
  icon: Icon,
  title,
  action,
}: {
  icon: LucideIcon;
  title: string;
  action?: ReactNode;
}) {
  return (
    <div className="fsa:flex fsa:items-center fsa:justify-between fsa:gap-3 fsa:border-b fsa:border-slate-100 fsa:px-5 fsa:py-3.5">
      <div className="fsa:flex fsa:items-center fsa:gap-2.5">
        <span className="fsa:flex fsa:h-8 fsa:w-8 fsa:shrink-0 fsa:items-center fsa:justify-center fsa:rounded-lg fsa:bg-brand-50 fsa:text-brand-600">
          <Icon className="fsa:h-4 fsa:w-4" aria-hidden />
        </span>
        <h2 className="fsa:text-sm fsa:font-semibold fsa:text-slate-900">
          {title}
        </h2>
      </div>
      {action}
    </div>
  );
}

function ScoreCard({
  icon: Icon,
  label,
  score,
  grade,
}: {
  icon: LucideIcon;
  label: string;
  score: number;
  grade: string;
}) {
  const tone = gradeTone(grade);
  return (
    <Card className="fsa:p-5">
      <div className="fsa:flex fsa:items-center fsa:justify-between">
        <span className="fsa:flex fsa:items-center fsa:gap-2 fsa:text-sm fsa:font-medium fsa:text-slate-600">
          <Icon className="fsa:h-4 fsa:w-4 fsa:text-slate-400" aria-hidden />
          {label}
        </span>
        <Dial score={score} tone={tone} />
      </div>
      <div className="fsa:mt-3 fsa:flex fsa:items-end fsa:gap-2">
        <span className={cn("fsa:text-4xl fsa:font-bold", tone.text)}>
          {score}
        </span>
        <span className="fsa:pb-1 fsa:text-sm fsa:text-slate-400">/ 100</span>
      </div>
      <div
        className={cn(
          "fsa:mt-2 fsa:inline-flex fsa:rounded-full fsa:px-2.5 fsa:py-0.5 fsa:text-xs fsa:font-semibold",
          tone.bg,
          tone.text,
        )}
      >
        {GRADE_LABEL[grade] ?? grade}
      </div>
    </Card>
  );
}

/** A small circular progress dial rendered as an inline SVG. */
function Dial({ score, tone }: { score: number; tone: { ring: string } }) {
  const r = 16;
  const c = 2 * Math.PI * r;
  const offset = c - (Math.max(0, Math.min(100, score)) / 100) * c;
  return (
    <svg
      width="44"
      height="44"
      viewBox="0 0 44 44"
      className="fsa:shrink-0 fsa:-rotate-90"
      aria-hidden
    >
      <circle
        cx="22"
        cy="22"
        r={r}
        fill="none"
        strokeWidth="4"
        className="fsa:text-slate-100"
        stroke="currentColor"
      />
      <circle
        cx="22"
        cy="22"
        r={r}
        fill="none"
        strokeWidth="4"
        strokeLinecap="round"
        strokeDasharray={c}
        strokeDashoffset={offset}
        className={tone.ring}
        stroke="currentColor"
      />
    </svg>
  );
}

function TrendCard({ trend }: { trend: TrendPoint[] }) {
  return (
    <Card className="fsa:h-full">
      <CardHeader icon={TrendingUp} title={__("SEO & AEO trend")} />
      <div className="fsa:p-5">
        {trend.length < 2 ? (
          <div className="fsa:flex fsa:h-32 fsa:items-center fsa:justify-center fsa:text-center fsa:text-sm fsa:text-slate-400">
            {__("Run “Scan now” a few times to build a score history here.")}
          </div>
        ) : (
          <>
            <Sparkline trend={trend} />
            <div className="fsa:mt-3 fsa:flex fsa:items-center fsa:gap-4 fsa:text-xs">
              <Legend className="fsa:bg-brand-500" label={__("SEO")} />
              <Legend className="fsa:bg-emerald-500" label={__("AEO")} />
            </div>
          </>
        )}
      </div>
    </Card>
  );
}

function Legend({ className, label }: { className: string; label: string }) {
  return (
    <span className="fsa:flex fsa:items-center fsa:gap-1.5 fsa:text-slate-500">
      <span className={cn("fsa:h-2 fsa:w-2 fsa:rounded-full", className)} />
      {label}
    </span>
  );
}

/** Two-series line chart drawn as inline SVG — no charting dependency. */
function Sparkline({ trend }: { trend: TrendPoint[] }) {
  const w = 100;
  const h = 40;
  const n = trend.length;
  const x = (i: number) => (n === 1 ? 0 : (i / (n - 1)) * w);
  const y = (v: number) => h - (Math.max(0, Math.min(100, v)) / 100) * h;
  const line = (key: "seo" | "aeo") =>
    trend.map((p, i) => `${x(i).toFixed(1)},${y(p[key]).toFixed(1)}`).join(" ");

  return (
    <svg
      viewBox={`0 0 ${w} ${h}`}
      preserveAspectRatio="none"
      className="fsa:h-32 fsa:w-full"
      role="img"
      aria-label={__("SEO and AEO score history")}
    >
      {[0, 20, 40].map((gy) => (
        <line
          key={gy}
          x1="0"
          x2={w}
          y1={gy}
          y2={gy}
          stroke="currentColor"
          strokeWidth="0.3"
          className="fsa:text-slate-100"
        />
      ))}
      <polyline
        points={line("seo")}
        fill="none"
        stroke="currentColor"
        strokeWidth="1.4"
        strokeLinecap="round"
        strokeLinejoin="round"
        className="fsa:text-brand-500"
        vectorEffect="non-scaling-stroke"
      />
      <polyline
        points={line("aeo")}
        fill="none"
        stroke="currentColor"
        strokeWidth="1.4"
        strokeLinecap="round"
        strokeLinejoin="round"
        className="fsa:text-emerald-500"
        vectorEffect="non-scaling-stroke"
      />
    </svg>
  );
}

const ISSUE_META: Record<
  string,
  { label: string; icon: LucideIcon; text: string; bg: string; target: string }
> = {
  critical: {
    label: __("Critical"),
    icon: XCircle,
    text: "fsa:text-red-600",
    bg: "fsa:bg-red-50",
    target: "flexa-seo-aeo-pages",
  },
  warnings: {
    label: __("Warnings"),
    icon: AlertTriangle,
    text: "fsa:text-amber-600",
    bg: "fsa:bg-amber-50",
    target: "flexa-seo-aeo-actions",
  },
  opportunities: {
    label: __("Opportunities"),
    icon: Lightbulb,
    text: "fsa:text-blue-600",
    bg: "fsa:bg-blue-50",
    target: "flexa-seo-aeo-pages",
  },
  passed: {
    label: __("Passed"),
    icon: CheckCircle2,
    text: "fsa:text-emerald-600",
    bg: "fsa:bg-emerald-50",
    target: "",
  },
};

function IssuesCard({ issues }: { issues: DashboardReport["issues"] }) {
  const order: (keyof DashboardReport["issues"])[] = [
    "critical",
    "warnings",
    "opportunities",
    "passed",
  ];
  return (
    <Card className="fsa:h-full">
      <CardHeader icon={ListChecks} title={__("Issues overview")} />
      <div className="fsa:grid fsa:grid-cols-2 fsa:gap-px fsa:overflow-hidden fsa:rounded-b-xl fsa:bg-slate-100">
        {order.map((key) => {
          const meta = ISSUE_META[key];
          const Icon = meta.icon;
          const value = issues[key];
          const clickable = meta.target !== "";
          return (
            <button
              key={key}
              type="button"
              disabled={!clickable}
              onClick={() => clickable && scrollToId(meta.target)}
              className={cn(
                "fsa:flex fsa:flex-col fsa:items-start fsa:gap-1 fsa:bg-white fsa:p-4 fsa:text-left fsa:transition-colors",
                clickable
                  ? "fsa:cursor-pointer fsa:hover:bg-slate-50"
                  : "fsa:cursor-default",
              )}
            >
              <span
                className={cn(
                  "fsa:flex fsa:h-7 fsa:w-7 fsa:items-center fsa:justify-center fsa:rounded-lg",
                  meta.bg,
                  meta.text,
                )}
              >
                <Icon className="fsa:h-4 fsa:w-4" aria-hidden />
              </span>
              <span className="fsa:text-2xl fsa:font-bold fsa:text-slate-900">
                {value}
              </span>
              <span className="fsa:text-xs fsa:font-medium fsa:text-slate-500">
                {meta.label}
              </span>
            </button>
          );
        })}
      </div>
    </Card>
  );
}

function HealthCard({
  title,
  groups,
}: {
  title: string;
  groups: HealthGroup[];
}) {
  return (
    <Card>
      <CardHeader icon={Gauge} title={title} />
      <div className="fsa:divide-y fsa:divide-slate-100">
        {groups.map((g) => (
          <HealthRow key={g.id} group={g} />
        ))}
      </div>
    </Card>
  );
}

function barTone(status: HealthGroup["status"]): string {
  switch (status) {
    case "good":
      return "fsa:bg-emerald-500";
    case "warn":
      return "fsa:bg-amber-500";
    case "poor":
      return "fsa:bg-red-500";
    default:
      return "fsa:bg-slate-300";
  }
}

function HealthRow({ group }: { group: HealthGroup }) {
  const na = group.status === "na" || group.score === null;
  return (
    <div className="fsa:flex fsa:items-center fsa:gap-4 fsa:px-5 fsa:py-3">
      <span className="fsa:w-40 fsa:shrink-0 fsa:text-sm fsa:font-medium fsa:text-slate-700">
        {group.label}
      </span>
      <div className="fsa:h-2 fsa:flex-1 fsa:overflow-hidden fsa:rounded-full fsa:bg-slate-100">
        {!na && (
          <div
            className={cn("fsa:h-full fsa:rounded-full", barTone(group.status))}
            style={{ width: `${group.score}%` }}
          />
        )}
      </div>
      <span className="fsa:w-16 fsa:shrink-0 fsa:text-right fsa:text-sm fsa:font-semibold fsa:text-slate-600">
        {na ? (
          <span className="fsa:text-xs fsa:font-medium fsa:text-slate-400">
            {__("n/a")}
          </span>
        ) : (
          `${group.score}%`
        )}
      </span>
    </div>
  );
}

const REC_TONE: Record<Recommendation["severity"], string> = {
  critical: "fsa:bg-red-500",
  warning: "fsa:bg-amber-500",
  opportunity: "fsa:bg-blue-500",
};

function RecommendationsCard({
  items,
  onPick,
}: {
  items: Recommendation[];
  onPick: (rec: Recommendation) => void;
}) {
  return (
    <Card>
      <div id="flexa-seo-aeo-actions" className="fsa:scroll-mt-6">
        <CardHeader icon={Lightbulb} title={__("Recommended actions")} />
      </div>
      {items.length === 0 ? (
        <div className="fsa:flex fsa:items-center fsa:gap-2 fsa:p-5 fsa:text-sm fsa:text-emerald-700">
          <CheckCircle2 className="fsa:h-4 fsa:w-4" aria-hidden />
          {__("Nothing urgent: your configuration looks healthy.")}
        </div>
      ) : (
        <ul className="fsa:divide-y fsa:divide-slate-100">
          {items.map((rec) => (
            <li key={rec.id}>
              <button
                type="button"
                onClick={() => onPick(rec)}
                className="fsa:flex fsa:w-full fsa:items-center fsa:gap-3 fsa:px-5 fsa:py-3.5 fsa:text-left fsa:transition-colors fsa:cursor-pointer fsa:hover:bg-slate-50"
              >
                <span
                  className={cn(
                    "fsa:mt-1.5 fsa:h-2 fsa:w-2 fsa:shrink-0 fsa:self-start fsa:rounded-full",
                    REC_TONE[rec.severity],
                  )}
                  aria-hidden
                />
                <span className="fsa:min-w-0 fsa:flex-1">
                  <span className="fsa:block fsa:text-sm fsa:font-semibold fsa:text-slate-900">
                    {rec.label}
                  </span>
                  <span className="fsa:block fsa:text-xs fsa:text-slate-500">
                    {rec.hint}
                  </span>
                </span>
                <ChevronRight
                  className="fsa:h-4 fsa:w-4 fsa:shrink-0 fsa:text-slate-400"
                  aria-hidden
                />
              </button>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}

function PagesCard({ data }: { data: DashboardReport }) {
  const pages = data.pages;
  const [open, setOpen] = useState<Record<number, boolean>>({});
  const toggle = (id: number) => setOpen((o) => ({ ...o, [id]: !o[id] }));

  return (
    <Card>
      <CardHeader icon={AlertTriangle} title={__("Pages needing attention")} />
      {pages.length === 0 ? (
        <div className="fsa:p-5 fsa:text-sm fsa:text-slate-500">
          {data.scanned === 0
            ? __("Run a scan to surface the pages that need work.")
            : __("Every scanned page is in good shape. 🎉")}
        </div>
      ) : (
        <>
          <p className="fsa:px-5 fsa:pt-3 fsa:text-xs fsa:text-slate-400">
            {__("Click a row to see what to fix.")}
          </p>
          <div className="fsa:overflow-x-auto">
            <table className="fsa:w-full fsa:text-sm">
              <thead>
                <tr className="fsa:border-b fsa:border-slate-100 fsa:text-left fsa:text-xs fsa:font-semibold fsa:uppercase fsa:tracking-wide fsa:text-slate-400">
                  <th className="fsa:px-5 fsa:py-2.5">{__("Page")}</th>
                  <th className="fsa:px-3 fsa:py-2.5 fsa:text-center">
                    {__("AEO")}
                  </th>
                  <th className="fsa:px-3 fsa:py-2.5 fsa:text-center">
                    {__("Issues")}
                  </th>
                  <th className="fsa:px-5 fsa:py-2.5 fsa:text-right">
                    {__("Action")}
                  </th>
                </tr>
              </thead>
              <tbody className="fsa:divide-y fsa:divide-slate-100">
                {pages.map((p) => (
                  <PageRow
                    key={p.id}
                    page={p}
                    expanded={Boolean(open[p.id])}
                    onToggle={() => toggle(p.id)}
                  />
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </Card>
  );
}

const ISSUE_STATUS: Record<
  PageIssueStatus,
  { icon: LucideIcon; tone: string }
> = {
  fail: { icon: XCircle, tone: "fsa:text-red-500" },
  warn: { icon: AlertTriangle, tone: "fsa:text-amber-500" },
};

type PageIssueStatus = "warn" | "fail";

/**
 * Badge wording for how well-founded a check is. Kept beside the check so the
 * score is read as an argued position: a vendor-documented requirement and a
 * bet of ours should not look the same on screen. docs/SCORING.md carries the
 * full rubric, including why each weight is what it is.
 */
const EVIDENCE_BADGE: Record<
  EvidenceLevel,
  { label: string; title: string; className: string }
> = {
  documented: {
    label: __("Documented"),
    title: __("Described in a search or AI vendor’s own published guidance."),
    className: "fsa:bg-teal-100 fsa:text-teal-700",
  },
  observed: {
    label: __("Observed"),
    title: __(
      "Associated with being quoted in testing and third-party studies, but not documented by any engine.",
    ),
    className: "fsa:bg-indigo-100 fsa:text-indigo-700",
  },
  heuristic: {
    label: __("Heuristic"),
    title: __(
      "Our own judgement call. No engine has confirmed it reads this signal.",
    ),
    className: "fsa:bg-orange-100 fsa:text-orange-800",
  },
};

function EvidenceBadge({ level }: { level: EvidenceLevel | undefined }) {
  const meta = EVIDENCE_BADGE[level ?? "heuristic"] ?? EVIDENCE_BADGE.heuristic;

  return (
    <span
      title={meta.title}
      className={cn(
        "fsa:ml-1.5 fsa:inline-block fsa:rounded-full fsa:px-1.5 fsa:py-px fsa:align-middle fsa:text-[10px] fsa:font-semibold fsa:uppercase fsa:tracking-wide",
        meta.className,
      )}
    >
      {meta.label}
    </span>
  );
}

function PageRow({
  page,
  expanded,
  onToggle,
}: {
  page: AttentionPage;
  expanded: boolean;
  onToggle: () => void;
}) {
  const showToast = useUiStore((s) => s.showToast);
  const applyFix = useApplyFix();
  const canManageSettings = getPluginGlobal().canManageSettings;
  // A fresh per-page rescan overrides the (cached) site-scan figures for this row.
  const [override, setOverride] = useState<{
    score: number;
    grade: string;
    checks: PageIssue[];
  } | null>(null);
  const [rescanning, setRescanning] = useState(false);
  // The check id currently having its site-wide fix applied (for the spinner).
  const [applyingId, setApplyingId] = useState<string | null>(null);

  // Guard against a report cached before `checks` existed (transient TTL).
  const baseChecks = page.checks ?? [];
  const score = override?.score ?? page.score;
  const grade = override?.grade ?? page.grade;
  const checks = override?.checks ?? baseChecks;
  const issues = override ? override.checks.length : page.issues;

  const tone = gradeTone(grade);
  const Chevron = expanded ? ChevronDown : ChevronRight;

  const onRescan = async () => {
    setRescanning(true);
    try {
      const report = await rescanPage(page.id);
      const failing = report.checks
        .filter((c) => c.status !== "pass")
        .map((c) => ({
          id: c.id,
          label: c.label,
          status: c.status as PageIssueStatus,
          hint: c.hint,
          fix: c.fix,
          evidence: c.evidence,
        }))
        .sort(
          (a, b) =>
            (a.status === "fail" ? 0 : 1) - (b.status === "fail" ? 0 : 1),
        );
      setOverride({
        score: report.score,
        grade: report.grade,
        checks: failing,
      });
    } catch (err) {
      showToast((err as Error).message || __("Rescan failed."), "error");
    } finally {
      setRescanning(false);
    }
  };

  const onApplyFix = async (checkId: string, fix: SettingFix) => {
    setApplyingId(checkId);
    try {
      await applyFix.mutateAsync(fix.patch);
      // The fix is site-wide; drop the per-row override so the refreshed
      // site scan (invalidated by the mutation) drives this row.
      setOverride(null);
      showToast(
        __("Enabled site-wide: your pages are being re-scored."),
        "success",
      );
    } catch (err) {
      showToast(
        (err as Error).message || __("Couldn’t apply that fix."),
        "error",
      );
    } finally {
      setApplyingId(null);
    }
  };

  return (
    <>
      <tr className="fsa:hover:bg-slate-50">
        <td className="fsa:px-5 fsa:py-3">
          <div className="fsa:flex fsa:items-center fsa:gap-1.5">
            <button
              type="button"
              onClick={onToggle}
              aria-expanded={expanded}
              aria-label={__("Toggle issue details")}
              className="fsa:shrink-0 fsa:cursor-pointer fsa:rounded fsa:p-0.5 fsa:text-slate-400 fsa:hover:bg-slate-100 fsa:hover:text-slate-600"
            >
              <Chevron className="fsa:h-4 fsa:w-4" aria-hidden />
            </button>
            <a
              href={page.url}
              target="_blank"
              rel="noreferrer"
              className="fsa:flex fsa:min-w-0 fsa:items-center fsa:gap-1.5 fsa:font-medium fsa:text-slate-800 fsa:hover:text-brand-600"
            >
              <span className="fsa:max-w-xs fsa:truncate">
                {page.title || __("(no title)")}
              </span>
              <ExternalLink
                className="fsa:h-3.5 fsa:w-3.5 fsa:shrink-0 fsa:text-slate-400"
                aria-hidden
              />
            </a>
          </div>
        </td>
        <td className="fsa:px-3 fsa:py-3 fsa:text-center">
          <span
            className={cn(
              "fsa:inline-flex fsa:min-w-9 fsa:justify-center fsa:rounded-full fsa:px-2 fsa:py-0.5 fsa:text-xs fsa:font-bold",
              tone.bg,
              tone.text,
            )}
          >
            {score}
          </span>
        </td>
        <td className="fsa:px-3 fsa:py-3 fsa:text-center">
          <button
            type="button"
            onClick={onToggle}
            className="fsa:inline-flex fsa:items-center fsa:gap-1 fsa:rounded-full fsa:px-2 fsa:py-0.5 fsa:text-sm fsa:font-semibold fsa:text-slate-600 fsa:hover:bg-slate-100 fsa:cursor-pointer"
          >
            {issues}
            <Chevron
              className="fsa:h-3.5 fsa:w-3.5 fsa:text-slate-400"
              aria-hidden
            />
          </button>
        </td>
        <td className="fsa:px-5 fsa:py-3 fsa:text-right">
          <a
            href={page.edit_url}
            className="fsa:inline-flex fsa:items-center fsa:gap-1 fsa:text-xs fsa:font-semibold fsa:text-brand-600 fsa:hover:text-brand-700"
          >
            <Pencil className="fsa:h-3.5 fsa:w-3.5" aria-hidden />
            {__("Edit")}
          </a>
        </td>
      </tr>
      {expanded && (
        <tr className="fsa:bg-slate-50/60">
          <td colSpan={4} className="fsa:px-5 fsa:py-3">
            {checks.length === 0 ? (
              <p
                className={cn(
                  "fsa:text-xs",
                  override ? "fsa:text-emerald-700" : "fsa:text-slate-500",
                )}
              >
                {override
                  ? __("All readiness checks pass now. 🎉")
                  : __(
                      "Details aren’t loaded yet. Run “Scan now” at the top to refresh this list.",
                    )}
              </p>
            ) : (
              <ul className="fsa:space-y-2.5">
                {checks.map((c) => {
                  const meta =
                    ISSUE_STATUS[c.status as PageIssueStatus] ??
                    ISSUE_STATUS.warn;
                  const Icon = meta.icon;
                  return (
                    <li
                      key={c.id}
                      className="fsa:flex fsa:items-start fsa:gap-2.5"
                    >
                      <Icon
                        className={cn(
                          "fsa:mt-0.5 fsa:h-4 fsa:w-4 fsa:shrink-0",
                          meta.tone,
                        )}
                        aria-hidden
                      />
                      <div className="fsa:min-w-0">
                        <div className="fsa:text-sm fsa:font-semibold fsa:text-slate-800">
                          {c.label}
                          <EvidenceBadge level={c.evidence} />
                        </div>
                        <div className="fsa:text-xs fsa:text-slate-500">
                          {c.hint}
                        </div>
                        {c.fix && canManageSettings && (
                          <button
                            type="button"
                            onClick={() => c.fix && onApplyFix(c.id, c.fix)}
                            disabled={applyingId === c.id}
                            className="fsa:mt-1.5 fsa:inline-flex fsa:items-center fsa:gap-1 fsa:rounded-md fsa:bg-brand-50 fsa:px-2 fsa:py-1 fsa:text-xs fsa:font-semibold fsa:text-brand-700 fsa:transition-colors fsa:cursor-pointer fsa:hover:bg-brand-100 fsa:disabled:opacity-50"
                          >
                            <Zap
                              className={cn(
                                "fsa:h-3.5 fsa:w-3.5",
                                applyingId === c.id && "fsa:animate-pulse",
                              )}
                              aria-hidden
                            />
                            {applyingId === c.id
                              ? __("Enabling…")
                              : c.fix.label}
                            <span className="fsa:font-normal fsa:text-brand-500">
                              {__("· site-wide")}
                            </span>
                          </button>
                        )}
                      </div>
                    </li>
                  );
                })}
              </ul>
            )}
            <div className="fsa:mt-3 fsa:flex fsa:items-center fsa:gap-4">
              <a
                href={page.edit_url}
                className="fsa:inline-flex fsa:items-center fsa:gap-1 fsa:text-xs fsa:font-semibold fsa:text-brand-600 fsa:hover:text-brand-700"
              >
                <Pencil className="fsa:h-3.5 fsa:w-3.5" aria-hidden />
                {__("Fix in editor")}
              </a>
              <button
                type="button"
                onClick={onRescan}
                disabled={rescanning}
                className="fsa:inline-flex fsa:items-center fsa:gap-1 fsa:text-xs fsa:font-semibold fsa:text-slate-600 fsa:hover:text-slate-900 fsa:cursor-pointer fsa:disabled:opacity-50"
              >
                <RefreshCw
                  className={cn(
                    "fsa:h-3.5 fsa:w-3.5",
                    rescanning && "fsa:animate-spin",
                  )}
                  aria-hidden
                />
                {rescanning ? __("Rescanning…") : __("Rescan this page")}
              </button>
            </div>
          </td>
        </tr>
      )}
    </>
  );
}
