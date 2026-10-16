import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";

/** A 0–100 sub-score card; `score` is null when the signal is not yet measured. */
export interface HealthGroup {
    id: string;
    label: string;
    score: number | null;
    status: "good" | "warn" | "poor" | "na";
}

export interface Recommendation {
    id: string;
    label: string;
    hint: string;
    severity: "critical" | "warning" | "opportunity";
    /** A settings SectionId, or "pages" to scroll to the attention list. */
    target: string;
}

/**
 * A one-click remedy for a check whose only blocker is a global AEO toggle being
 * off. `patch` is a partial settings payload POSTed to /settings (which merges
 * it over the stored settings). `scope: "site"` flags that the change is
 * site-wide — enabling it clears this check on every page, not just this one.
 */
export interface SettingFix {
    label: string;
    scope: "site";
    patch: Record<string, unknown>;
}

/**
 * How well-founded a readiness check is. Mirrors Services\Aeo\Readiness::EVIDENCE:
 * `documented` tracks a vendor's published guidance, `observed` tracks a
 * correlation no engine commits to, `heuristic` is our own call. The rubric and
 * the reasoning behind every weight live in docs/SCORING.md.
 */
export type EvidenceLevel = "documented" | "observed" | "heuristic";

/** A single failing/warning readiness check on a page, with its fix hint. */
export interface PageIssue {
    id: string;
    label: string;
    status: "warn" | "fail";
    hint: string;
    /** A one-click site-wide toggle that resolves this check, or null. */
    fix: SettingFix | null;
    evidence: EvidenceLevel;
}

export interface AttentionPage {
    id: number;
    title: string;
    url: string;
    edit_url: string;
    score: number;
    grade: string;
    issues: number;
    checks: PageIssue[];
}

export interface TrendPoint {
    t: number;
    seo: number;
    aeo: number;
    technical: number;
}

export interface DashboardReport {
    generated_at: number;
    scanned: number;
    scan_limit: number;
    seo_score: number;
    seo_grade: string;
    aeo_score: number;
    aeo_grade: string;
    technical_score: number;
    technical_grade: string;
    issues: {
        critical: number;
        warnings: number;
        opportunities: number;
        passed: number;
    };
    seo_health: HealthGroup[];
    aeo_health: HealthGroup[];
    recommendations: Recommendation[];
    pages: AttentionPage[];
    trend: TrendPoint[];
}

/** One row of the per-post readiness report (GET /readiness/{id}). */
export interface ReadinessCheck {
    id: string;
    label: string;
    status: "pass" | "warn" | "fail";
    weight: number;
    hint: string;
    /** A one-click site-wide toggle that resolves this check, or null. */
    fix: SettingFix | null;
    evidence: EvidenceLevel;
}

export interface ReadinessReport {
    score: number;
    grade: string;
    checks: ReadinessCheck[];
}

/**
 * Re-score a single post via the same endpoint the editor sidebar uses, so a
 * page can be refreshed after an edit without re-running the whole site scan.
 */
export function rescanPage(id: number): Promise<ReadinessReport> {
    return api.get<ReadinessReport>(`readiness/${id}`);
}

const DASHBOARD_KEY = ["dashboard"] as const;

export function useDashboard() {
    return useQuery<DashboardReport>({
        queryKey: DASHBOARD_KEY,
        queryFn: () => api.get<DashboardReport>("dashboard"),
    });
}

export function useScan() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: () => api.post<DashboardReport>("dashboard/scan"),
        onSuccess: (next) => {
            qc.setQueryData(DASHBOARD_KEY, next);
        },
    });
}

/**
 * Apply a one-click site-wide fix: POST the toggle patch to the existing
 * settings endpoint (which partial-merges it), then invalidate the dashboard
 * (its cache is server-flushed on settings/updated) and the settings query so
 * both surfaces reflect the flipped toggle.
 */
export function useApplyFix() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (patch: Record<string, unknown>) =>
            api.post<unknown>("settings", patch),
        onSuccess: () => {
            void qc.invalidateQueries({ queryKey: DASHBOARD_KEY });
            void qc.invalidateQueries({ queryKey: ["settings"] });
        },
    });
}
