import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { __ } from "@/lib/i18n";

/** Wizard lifecycle status, mirrored from Support\OnboardingState. */
export type OnboardingStatus =
  "pending" | "in_progress" | "completed" | "dismissed";

/** Wizard step ids, in canonical order. */
export type StepId =
  "welcome" | "seo" | "aeo" | "content" | "migration" | "report";

/** Persisted setup progress (the `flexa_seo_aeo_onboarding` option). */
export interface OnboardingStateData {
  version: number;
  status: OnboardingStatus;
  current_step: StepId;
  completed_steps: StepId[];
  applied: { seo: string[]; aeo: string[] };
  started_at: number | null;
  completed_at: number | null;
  dismissed_at: number | null;
}

/** A detected legacy SEO plugin (Yoast / Rank Math / SEOPress). */
export interface SeoPlugin {
  id: string;
  label: string;
  /** Still active — a duplicate-output conflict to warn about. */
  active: boolean;
  /** Has data worth importing — enables the migration step. */
  migratable: boolean;
  count: number;
}

/** Read-only site detection returned alongside the state. */
export interface DetectPayload {
  site_name: string;
  tagline: string;
  site_url: string;
  language: string;
  logo_url: string | null;
  site_type: "shop" | "blog" | "site";
  has_woocommerce: boolean;
  pretty_permalinks: boolean;
  posts_published: number;
  pages_published: number;
  latest_post_id: number | null;
  knowledge_type_suggestion: string;
  seo_plugins: SeoPlugin[];
  settings_configured: boolean;
}

/**
 * How a recommended setting relates to the current value:
 * `will_enable` (off, will be turned on), `already_on` (matches), or
 * `user_configured_skip` (user changed it — never overwritten).
 */
export type RecommendationState =
  "will_enable" | "already_on" | "user_configured_skip";

export interface RecommendedItem {
  key: string;
  label: string;
  current: boolean | string;
  recommended: boolean | string;
  state: RecommendationState;
}

export interface RecommendedPayload {
  seo: RecommendedItem[];
  aeo: RecommendedItem[];
}

/** The single boot payload for the wizard (GET /onboarding). */
export interface OnboardingResponse {
  state: OnboardingStateData;
  detect: DetectPayload;
  recommended: RecommendedPayload;
}

/** A partial state write (POST /onboarding). */
export interface OnboardingUpdate {
  status?: OnboardingStatus;
  current_step?: StepId;
  completed_steps?: StepId[];
  applied?: { seo?: string[]; aeo?: string[] };
}

const ONBOARDING_KEY = ["onboarding"] as const;

export function useOnboarding() {
  return useQuery<OnboardingResponse>({
    queryKey: ONBOARDING_KEY,
    queryFn: () => api.get<OnboardingResponse>("onboarding"),
  });
}

/**
 * Persist a partial state update. The POST returns only `{ state }`, so the
 * cached detect/recommended are preserved and just the state slice is replaced.
 */
export function useUpdateOnboarding() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (patch: OnboardingUpdate) =>
      api.post<{ state: OnboardingStateData }>(
        "onboarding",
        patch as Record<string, unknown>,
      ),
    onSuccess: (next) => {
      qc.setQueryData<OnboardingResponse>(ONBOARDING_KEY, (prev) =>
        prev ? { ...prev, state: next.state } : prev,
      );
    },
  });
}

export interface WizardStep {
  id: StepId;
  label: string;
}

/**
 * The shared contract every step component receives from `OnboardingPage`. The
 * page owns navigation and step-transition persistence; a step owns its body
 * and its own footer actions (which call these callbacks).
 */
export interface StepProps {
  detect: DetectPayload;
  state: OnboardingStateData;
  recommended: RecommendedPayload;
  /** Advance to the next applicable step, marking the current one complete. */
  onContinue: () => void;
  /** Go back one step; undefined on the first step. */
  onBack?: () => void;
  /** Finish setup; relevant only on the last step. */
  onFinish: () => void;
  /** True while a step-transition write is in flight. */
  busy: boolean;
}

/**
 * The steps that actually apply to this site, in order. The content step is
 * skipped on a near-empty site; the migration step appears only when a legacy
 * plugin has data to import. Mirrors docs/onboarding-design.md §7.3.
 */
export function applicableSteps(detect: DetectPayload): WizardStep[] {
  const steps: WizardStep[] = [
    { id: "welcome", label: __("Welcome") },
    { id: "seo", label: __("Search essentials") },
    { id: "aeo", label: __("AI readiness") },
  ];

  if (detect.posts_published + detect.pages_published > 2) {
    steps.push({ id: "content", label: __("Content") });
  }
  if (detect.seo_plugins.some((plugin) => plugin.migratable)) {
    steps.push({ id: "migration", label: __("Migrate") });
  }

  steps.push({ id: "report", label: __("Report") });

  return steps;
}

/**
 * The completion percentage, computed client-side from the applicable steps and
 * what has been completed. The server stores no percentage: it would go stale
 * the moment detection changes. Mirrors docs/onboarding-design.md §7.3.
 */
export function completionPercent(
  state: OnboardingStateData,
  detect: DetectPayload,
): number {
  const applicable = applicableSteps(detect).map((step) => step.id);
  if (applicable.length === 0) {
    return 0;
  }
  const done = new Set(
    state.completed_steps.filter((id) => applicable.includes(id)),
  );
  return Math.round((100 * done.size) / applicable.length);
}
