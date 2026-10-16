import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";

/**
 * The nested `aeo` group — the plugin's AEO differentiator. Mirrors
 * `Settings::aeo_defaults()`. The REST sanitizer merges a partial `aeo` object
 * over what is stored, so the form can send the whole group safely.
 */
export interface AeoSettings {
    enabled: boolean;
    agent_readiness: boolean;
    plain_text_export: boolean;
    schema: boolean;
    /** WooCommerce Product/Offer JSON-LD + OG product tags. */
    commerce: boolean;
    site_name: string;
    site_description: string;
}

/**
 * Mirrors `Flexa\SeoAeo\Support\Settings`. Keep keys in lockstep with the PHP
 * schema — the REST sanitizer drops anything not in it, so a typo here silently
 * no-ops on save. (`indexnow_key` is auto-managed server-side and intentionally
 * omitted from the form.)
 */
export interface SettingsData {
    titles_metas: boolean;
    open_graph: boolean;
    twitter_cards: boolean;
    xml_sitemap: boolean;
    xml_sitemap_images: boolean;
    html_sitemap: boolean;
    breadcrumbs: boolean;
    image_seo_alt: boolean;
    indexnow: boolean;
    separator: string;
    home_title: string;
    home_description: string;
    twitter_card_type: string;
    og_default_image: string;
    knowledge_type: string;
    knowledge_name: string;
    knowledge_logo: string;
    robots_txt: string;
    whitelabel: boolean;
    whitelabel_name: string;
    strip_category_base: boolean;
    redirect_attachments: boolean;
    redirect_orphan_attachments: string;
    nofollow_external_links: boolean;
    nofollow_image_links: boolean;
    new_window_external_links: boolean;
    sitemap_post_types: string[];
    sitemap_taxonomies: string[];
    aeo: AeoSettings;
}

const SETTINGS_KEY = ["settings"] as const;

export function useSettings() {
    return useQuery<SettingsData>({
        queryKey: SETTINGS_KEY,
        queryFn: () => api.get<SettingsData>("settings"),
    });
}

export function useSaveSettings() {
    const qc = useQueryClient();
    return useMutation({
        // Partial payload: only the changed fields. The PHP controller merges
        // the sanitized payload over what is stored, so sending the whole blob
        // would race a concurrent edit. (The `aeo` group, when changed, is sent
        // whole — the server merges it over the stored group.)
        mutationFn: (input: Partial<SettingsData>) =>
            api.post<SettingsData>("settings", input as Record<string, unknown>),
        onMutate: async (input) => {
            await qc.cancelQueries({ queryKey: SETTINGS_KEY });
            const prev = qc.getQueryData<SettingsData>(SETTINGS_KEY);
            if (prev) {
                qc.setQueryData<SettingsData>(SETTINGS_KEY, {
                    ...prev,
                    ...input,
                });
            }
            return { prev };
        },
        onError: (_err, _vars, ctx) => {
            if (ctx?.prev) {
                qc.setQueryData(SETTINGS_KEY, ctx.prev);
            }
        },
        onSuccess: (next) => {
            qc.setQueryData(SETTINGS_KEY, next);
        },
    });
}

/**
 * Import a full settings object (from an exported JSON file). Reuses the same
 * POST /settings endpoint as a normal save — because the payload carries every
 * key, the server's merge-over-stored acts as a full replace, and its sanitizer
 * drops anything not in the schema, so a hand-edited file can't inject junk.
 */
export function useImportSettings() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (data: SettingsData) =>
            api.post<SettingsData>("settings", data as unknown as Record<string, unknown>),
        onSuccess: (next) => {
            qc.setQueryData(SETTINGS_KEY, next);
        },
    });
}

export function useResetAllData() {
    const qc = useQueryClient();
    return useMutation({
        // The route is /settings/reset; the server runs Resetter::reset_all()
        // and ignores the body. We still send the confirm phrase for symmetry
        // with the typed-confirmation UX.
        mutationFn: (confirm: string) =>
            api.post<unknown>("settings/reset", { confirm }),
        onSuccess: () => {
            void qc.invalidateQueries();
        },
    });
}
