import {
    AtSign,
    Bot,
    Braces,
    ChevronDown,
    ExternalLink,
    FileCode,
    FileText,
    FolderTree,
    Home,
    Image,
    ImageOff,
    Link2,
    List,
    Navigation,
    Network,
    Palette,
    Paperclip,
    Rss,
    Save,
    Search,
    Share2,
    ShoppingBag,
    Tag,
    Tags,
    Type,
    type LucideIcon,
} from "lucide-react";
import { useEffect, useState, type ReactNode } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { cn } from "@/lib/cn";
import { __ } from "@/lib/i18n";
import { useUiStore } from "@/lib/store";
import { DangerZone } from "./DangerZone";
import { MigrationPane } from "./MigrationPane";
import { SECTIONS, type SectionId, type SectionMeta } from "./sections";
import { ToolsPane } from "./ToolsPane";
import { type SettingsData, useSaveSettings, useSettings } from "./useSettings";

const BOOL_KEYS = [
    "titles_metas",
    "open_graph",
    "twitter_cards",
    "xml_sitemap",
    "xml_sitemap_images",
    "html_sitemap",
    "breadcrumbs",
    "image_seo_alt",
    "indexnow",
    "whitelabel",
    "strip_category_base",
    "redirect_attachments",
    "nofollow_external_links",
    "nofollow_image_links",
    "new_window_external_links",
] as const;

const STRING_KEYS = [
    "separator",
    "home_title",
    "home_description",
    "twitter_card_type",
    "og_default_image",
    "knowledge_type",
    "knowledge_name",
    "knowledge_logo",
    "redirect_orphan_attachments",
    "robots_txt",
    "whitelabel_name",
] as const;

const ARRAY_KEYS = ["sitemap_post_types", "sitemap_taxonomies"] as const;

const SEPARATORS = ["-", "–", "—", "·", "•", "|", "/", "»", ">"];

const SEPARATOR_OPTIONS = SEPARATORS.map((s) => ({ value: s, label: s }));

const TWITTER_CARD_OPTIONS = [
    { value: "summary_large_image", label: __("Summary with large image") },
    { value: "summary", label: __("Summary") },
];

const KNOWLEDGE_TYPE_OPTIONS = [
    { value: "organization", label: __("Organization") },
    { value: "person", label: __("Person") },
];

const TEXTAREA_CLS =
    "flexa-seo-aeo-control fsa:w-full fsa:rounded-md fsa:border fsa:border-slate-300 fsa:bg-white fsa:px-3 fsa:py-2 fsa:text-sm fsa:shadow-sm fsa:transition-colors fsa:placeholder:text-slate-400";

const ROW_DIVIDER = "fsa:border-t fsa:border-slate-100";

interface ToggleMeta {
    icon: LucideIcon;
    title: string;
    description: string;
    checked: boolean;
    onChange: (v: boolean) => void;
}

function diffSettings(
    form: SettingsData,
    base: SettingsData,
): Partial<SettingsData> {
    const out: Partial<SettingsData> = {};
    for (const k of BOOL_KEYS) {
        if (form[k] !== base[k]) {
            out[k] = form[k];
        }
    }
    for (const k of STRING_KEYS) {
        if (form[k] !== base[k]) {
            out[k] = form[k];
        }
    }
    for (const k of ARRAY_KEYS) {
        if (JSON.stringify(form[k]) !== JSON.stringify(base[k])) {
            out[k] = form[k];
        }
    }
    if (JSON.stringify(form.aeo) !== JSON.stringify(base.aeo)) {
        out.aeo = form.aeo;
    }
    return out;
}

export function SettingsPage() {
    const settings = useSettings();
    const save = useSaveSettings();
    const showToast = useUiStore((s) => s.showToast);
    const active = useUiStore((s) => s.activeSection) as SectionId;
    const setActive = useUiStore((s) => s.setActiveSection);
    const setPaletteOpen = useUiStore((s) => s.setPaletteOpen);

    const [form, setForm] = useState<SettingsData | null>(null);
    useEffect(() => {
        if (settings.data && !form) {
            setForm(settings.data);
        }
    }, [settings.data, form]);

    if (settings.isLoading || !form) {
        return (
            <div className="fsa:p-6 fsa:text-sm fsa:text-slate-500">
                {__("Loading settings…")}
            </div>
        );
    }
    if (settings.isError) {
        return (
            <div className="fsa:m-6 fsa:rounded-md fsa:bg-red-50 fsa:p-4 fsa:text-sm fsa:text-red-700">
                {__("Failed to load settings:")}{" "}
                {(settings.error as Error).message}
            </div>
        );
    }

    const base = settings.data as SettingsData;
    const changed = diffSettings(form, base);
    const dirty = Object.keys(changed).length > 0;

    const onSave = () => {
        if (!dirty) {
            return;
        }
        save.mutate(changed, {
            onSuccess: () => showToast(__("Settings saved.")),
            onError: () => showToast(__("Save failed."), "error"),
        });
    };

    const setBool = (key: (typeof BOOL_KEYS)[number], value: boolean) =>
        setForm({ ...form, [key]: value });
    const setString = (key: (typeof STRING_KEYS)[number], value: string) =>
        setForm({ ...form, [key]: value });
    const setAeoBool = (
        key:
            | "enabled"
            | "agent_readiness"
            | "plain_text_export"
            | "schema"
            | "commerce",
        value: boolean,
    ) => setForm({ ...form, aeo: { ...form.aeo, [key]: value } });
    const setAeoString = (
        key: "site_name" | "site_description",
        value: string,
    ) => setForm({ ...form, aeo: { ...form.aeo, [key]: value } });
    const toggleInArray = (
        key: (typeof ARRAY_KEYS)[number],
        slug: string,
        on: boolean,
    ) => {
        const next = on
            ? Array.from(new Set([...form[key], slug]))
            : form[key].filter((s) => s !== slug);
        setForm({ ...form, [key]: next });
    };

    const activeSection = SECTIONS.find((s) => s.id === active) ?? SECTIONS[0];

    const generalToggles: ToggleMeta[] = [
        {
            icon: Type,
            title: __("Titles & meta descriptions"),
            description: __("Output the <title> and meta description tags."),
            checked: form.titles_metas,
            onChange: (v) => setBool("titles_metas", v),
        },
        {
            icon: Navigation,
            title: __("Breadcrumbs"),
            description: __("Enable breadcrumb trail + BreadcrumbList schema."),
            checked: form.breadcrumbs,
            onChange: (v) => setBool("breadcrumbs", v),
        },
        {
            icon: Image,
            title: __("Image alt text"),
            description: __("Auto-fill missing image alt attributes."),
            checked: form.image_seo_alt,
            onChange: (v) => setBool("image_seo_alt", v),
        },
    ];

    const socialToggles: ToggleMeta[] = [
        {
            icon: Share2,
            title: __("Open Graph"),
            description: __("Facebook / LinkedIn / chat-app preview tags."),
            checked: form.open_graph,
            onChange: (v) => setBool("open_graph", v),
        },
        {
            icon: AtSign,
            title: __("X (Twitter) Cards"),
            description: __("Rich preview cards on X."),
            checked: form.twitter_cards,
            onChange: (v) => setBool("twitter_cards", v),
        },
    ];

    const sitemapToggles: ToggleMeta[] = [
        {
            icon: Network,
            title: __("XML sitemap"),
            description: __("Serve /sitemap.xml and per-type sub-sitemaps."),
            checked: form.xml_sitemap,
            onChange: (v) => setBool("xml_sitemap", v),
        },
        {
            icon: Image,
            title: __("Include images"),
            description: __("Add <image:image> entries to the XML sitemap."),
            checked: form.xml_sitemap_images,
            onChange: (v) => setBool("xml_sitemap_images", v),
        },
        {
            icon: List,
            title: __("HTML sitemap"),
            description: __("Enable the [flexa_seo_aeo_sitemap] shortcode."),
            checked: form.html_sitemap,
            onChange: (v) => setBool("html_sitemap", v),
        },
    ];

    const indexingToggles: ToggleMeta[] = [
        {
            icon: Rss,
            title: __("IndexNow"),
            description: __(
                "Ping IndexNow when content is published or updated for near-instant indexing.",
            ),
            checked: form.indexnow,
            onChange: (v) => setBool("indexnow", v),
        },
    ];

    const linksUrlToggles: ToggleMeta[] = [
        {
            icon: FolderTree,
            title: __("Strip Category Base"),
            description: __(
                "Remove /category/ from category archive URLs. e.g. example.com/category/news/ becomes example.com/news/.",
            ),
            checked: form.strip_category_base,
            onChange: (v) => setBool("strip_category_base", v),
        },
        {
            icon: Paperclip,
            title: __("Redirect Attachments"),
            description: __(
                "Redirect attachment page URLs to the post they appear in.",
            ),
            checked: form.redirect_attachments,
            onChange: (v) => setBool("redirect_attachments", v),
        },
    ];

    const linksAttrToggles: ToggleMeta[] = [
        {
            icon: Link2,
            title: __("Nofollow External Links"),
            description: __(
                'Add rel="nofollow" to external links in your content. Applied when the content is displayed; the stored content is not changed.',
            ),
            checked: form.nofollow_external_links,
            onChange: (v) => setBool("nofollow_external_links", v),
        },
        {
            icon: ImageOff,
            title: __("Nofollow Image File Links"),
            description: __(
                'Add rel="nofollow" to links pointing to external image files. Applied when the content is displayed; the stored content is not changed.',
            ),
            checked: form.nofollow_image_links,
            onChange: (v) => setBool("nofollow_image_links", v),
        },
        {
            icon: ExternalLink,
            title: __("Open External Links in New Tab/Window"),
            description: __(
                'Add target="_blank" to external links so they open in a new tab. Applied when the content is displayed; the stored content is not changed.',
            ),
            checked: form.new_window_external_links,
            onChange: (v) => setBool("new_window_external_links", v),
        },
    ];

    const aeoToggles: ToggleMeta[] = [
        {
            icon: FileText,
            title: __("llms.txt"),
            description: __("Serve /llms.txt, a curated map of your content for AI."),
            checked: form.aeo.enabled,
            onChange: (v) => setAeoBool("enabled", v),
        },
        {
            icon: Bot,
            title: __("Agent Readiness"),
            description: __(
                "Advertise a Markdown variant of each page to AI crawlers.",
            ),
            checked: form.aeo.agent_readiness,
            onChange: (v) => setAeoBool("agent_readiness", v),
        },
        {
            icon: FileCode,
            title: __("Plain-text export"),
            description: __("Serve ?flexa-aeo=md, clean Markdown of any post."),
            checked: form.aeo.plain_text_export,
            onChange: (v) => setAeoBool("plain_text_export", v),
        },
        {
            icon: Braces,
            title: __("AEO schema"),
            description: __(
                "Emit Article / FAQPage / WebSite JSON-LD tuned for answer engines.",
            ),
            checked: form.aeo.schema,
            onChange: (v) => setAeoBool("schema", v),
        },
        // Commerce schema only makes sense with WooCommerce active, so the row
        // is hidden entirely otherwise (the toggle still exists server-side).
        ...(window.flexaSeoAeo?.hasWoo
            ? [
                  {
                      icon: ShoppingBag,
                      title: __("WooCommerce product schema"),
                      description: __(
                          "Emit Product / Offer JSON-LD (price, availability, rating) + OG product tags for AI shopping.",
                      ),
                      checked: form.aeo.commerce,
                      onChange: (v: boolean) => setAeoBool("commerce", v),
                  },
              ]
            : []),
    ];

    const postTypes = window.flexaSeoAeo?.postTypes ?? {};
    const taxonomies = window.flexaSeoAeo?.taxonomies ?? {};

    return (
        <>
            {/* Page header */}
            <div className="fsa:mx-auto fsa:flex fsa:max-w-6xl fsa:flex-wrap fsa:items-start fsa:justify-between fsa:gap-4 fsa:px-6 fsa:pt-8 fsa:pb-6">
                <div className="fsa:space-y-1">
                    <h1 className="fsa:text-3xl fsa:font-bold fsa:text-slate-900">
                        {__("Settings")}
                    </h1>
                    <p className="fsa:text-sm fsa:text-slate-600">
                        {__(
                            "Configure how Flexa SEO optimizes your site for search and answer engines.",
                        )}
                    </p>
                </div>
                <div className="fsa:flex fsa:items-center fsa:gap-3">
                    {save.isSuccess && !dirty && (
                        <span className="fsa:text-sm fsa:text-emerald-700">
                            {__("Settings saved.")}
                        </span>
                    )}
                    {save.isError && (
                        <span className="fsa:text-sm fsa:text-red-700">
                            {__("Save failed:")}{" "}
                            {(save.error as Error).message}
                        </span>
                    )}
                    <Button
                        variant="outline"
                        onClick={() => setPaletteOpen(true)}
                        className="fsa:gap-2"
                        aria-label={__("Open command palette")}
                    >
                        <Search className="fsa:h-4 fsa:w-4" aria-hidden />
                        <span className="fsa:hidden fsa:sm:inline">
                            {__("Search")}
                        </span>
                        <kbd className="fsa:rounded fsa:border fsa:border-slate-300 fsa:bg-slate-50 fsa:px-1.5 fsa:text-[10px] fsa:font-medium fsa:text-slate-500">
                            ⌘K
                        </kbd>
                    </Button>
                    <Button
                        onClick={onSave}
                        disabled={!dirty || save.isPending}
                        className="fsa:gap-2"
                    >
                        <Save className="fsa:h-4 fsa:w-4" aria-hidden />
                        {save.isPending ? __("Saving…") : __("Save Settings")}
                    </Button>
                </div>
            </div>

            {/* Body: nav + pane */}
            <div className="fsa:mx-auto fsa:flex fsa:max-w-6xl fsa:flex-col fsa:gap-6 fsa:px-6 fsa:pb-12 fsa:md:flex-row">
                <aside className="fsa:w-full fsa:shrink-0 fsa:space-y-2 fsa:md:w-72">
                    {SECTIONS.map((s) => (
                        <NavItem
                            key={s.id}
                            section={s}
                            selected={active === s.id}
                            onSelect={() => setActive(s.id)}
                        />
                    ))}
                </aside>

                <main className="fsa:flex-1">
                    <div className="fsa:overflow-hidden fsa:rounded-xl fsa:border fsa:border-slate-200 fsa:bg-white fsa:shadow-sm">
                        <PaneHeader section={activeSection} />

                        {active === "general" && (
                            <div>
                                <ToggleList items={generalToggles} />
                                <div className={cn(ROW_DIVIDER, "fsa:space-y-5 fsa:p-5")}>
                                    <div>
                                        <Label
                                            htmlFor="flexa-seo-aeo-separator"
                                            className="fsa:mb-2 fsa:block fsa:text-sm fsa:font-semibold fsa:text-slate-900"
                                        >
                                            {__("Title separator")}
                                        </Label>
                                        <Select
                                            id="flexa-seo-aeo-separator"
                                            value={form.separator}
                                            options={SEPARATOR_OPTIONS}
                                            onChange={(e) =>
                                                setString("separator", e.target.value)
                                            }
                                        />
                                    </div>
                                    <StackedField
                                        id="flexa-seo-aeo-home-title"
                                        icon={Home}
                                        title={__("Homepage title")}
                                        description={__(
                                            "Leave blank to use %%sitename%% %%sep%% %%tagline%%.",
                                        )}
                                    >
                                        <Input
                                            id="flexa-seo-aeo-home-title"
                                            value={form.home_title}
                                            onChange={(e) =>
                                                setString("home_title", e.target.value)
                                            }
                                            placeholder="%%sitename%% %%sep%% %%tagline%%"
                                        />
                                    </StackedField>
                                    <StackedField
                                        id="flexa-seo-aeo-home-desc"
                                        icon={FileText}
                                        title={__("Homepage description")}
                                        description={__(
                                            "The meta description for your front page.",
                                        )}
                                    >
                                        <textarea
                                            id="flexa-seo-aeo-home-desc"
                                            rows={2}
                                            value={form.home_description}
                                            onChange={(e) =>
                                                setString(
                                                    "home_description",
                                                    e.target.value,
                                                )
                                            }
                                            className={TEXTAREA_CLS}
                                        />
                                    </StackedField>
                                </div>
                            </div>
                        )}

                        {active === "social" && (
                            <div>
                                <ToggleList items={socialToggles} />
                                <div className={cn(ROW_DIVIDER, "fsa:space-y-5 fsa:p-5")}>
                                    <div>
                                        <Label
                                            htmlFor="flexa-seo-aeo-card-type"
                                            className="fsa:mb-2 fsa:block fsa:text-sm fsa:font-semibold fsa:text-slate-900"
                                        >
                                            {__("X Card type")}
                                        </Label>
                                        <Select
                                            id="flexa-seo-aeo-card-type"
                                            value={form.twitter_card_type}
                                            options={TWITTER_CARD_OPTIONS}
                                            onChange={(e) =>
                                                setString(
                                                    "twitter_card_type",
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <StackedField
                                        id="flexa-seo-aeo-og-image"
                                        icon={Image}
                                        title={__("Default share image")}
                                        description={__(
                                            "Used when a page has no featured image (full URL).",
                                        )}
                                    >
                                        <Input
                                            id="flexa-seo-aeo-og-image"
                                            type="url"
                                            inputMode="url"
                                            value={form.og_default_image}
                                            onChange={(e) =>
                                                setString(
                                                    "og_default_image",
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="https://example.com/share.jpg"
                                        />
                                    </StackedField>
                                </div>
                            </div>
                        )}

                        {active === "organization" && (
                            <div className="fsa:space-y-5 fsa:p-5">
                                <div>
                                    <Label
                                        htmlFor="flexa-seo-aeo-knowledge-type"
                                        className="fsa:mb-2 fsa:block fsa:text-sm fsa:font-semibold fsa:text-slate-900"
                                    >
                                        {__("This site represents")}
                                    </Label>
                                    <Select
                                        id="flexa-seo-aeo-knowledge-type"
                                        value={form.knowledge_type}
                                        options={KNOWLEDGE_TYPE_OPTIONS}
                                        onChange={(e) =>
                                            setString("knowledge_type", e.target.value)
                                        }
                                    />
                                </div>
                                <StackedField
                                    id="flexa-seo-aeo-knowledge-name"
                                    icon={Tag}
                                    title={__("Name")}
                                    description={__(
                                        "Organization or person name for the publisher schema. Defaults to the site title.",
                                    )}
                                >
                                    <Input
                                        id="flexa-seo-aeo-knowledge-name"
                                        value={form.knowledge_name}
                                        onChange={(e) =>
                                            setString("knowledge_name", e.target.value)
                                        }
                                    />
                                </StackedField>
                                <StackedField
                                    id="flexa-seo-aeo-knowledge-logo"
                                    icon={Image}
                                    title={__("Logo")}
                                    description={__(
                                        "Square brand logo for the publisher schema (full URL). Falls back to the theme logo, then the default share image.",
                                    )}
                                >
                                    <Input
                                        id="flexa-seo-aeo-knowledge-logo"
                                        type="url"
                                        inputMode="url"
                                        value={form.knowledge_logo}
                                        onChange={(e) =>
                                            setString("knowledge_logo", e.target.value)
                                        }
                                        placeholder="https://example.com/logo.png"
                                    />
                                </StackedField>
                            </div>
                        )}

                        {active === "sitemaps" && (
                            <div>
                                <ToggleList items={sitemapToggles} />
                                <div className={cn(ROW_DIVIDER, "fsa:space-y-5 fsa:p-5")}>
                                    <CheckboxGroup
                                        icon={FileText}
                                        title={__("Included post types")}
                                        description={__(
                                            "Which content types appear in the sitemap and llms.txt.",
                                        )}
                                        options={postTypes}
                                        selected={form.sitemap_post_types}
                                        onToggle={(slug, on) =>
                                            toggleInArray(
                                                "sitemap_post_types",
                                                slug,
                                                on,
                                            )
                                        }
                                    />
                                    <CheckboxGroup
                                        icon={Tags}
                                        title={__("Included taxonomies")}
                                        description={__(
                                            "Which term archives appear in the sitemap.",
                                        )}
                                        options={taxonomies}
                                        selected={form.sitemap_taxonomies}
                                        onToggle={(slug, on) =>
                                            toggleInArray(
                                                "sitemap_taxonomies",
                                                slug,
                                                on,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                        )}

                        {active === "indexing" && (
                            <div>
                                <ToggleList items={indexingToggles} />
                                <div className={cn(ROW_DIVIDER, "fsa:space-y-5 fsa:p-5")}>
                                    <StackedField
                                        id="flexa-seo-aeo-robots"
                                        icon={Bot}
                                        title={__("Custom robots.txt rules")}
                                        description={__(
                                            "Appended to the virtual robots.txt. The sitemap line is added automatically.",
                                        )}
                                    >
                                        <textarea
                                            id="flexa-seo-aeo-robots"
                                            rows={5}
                                            value={form.robots_txt}
                                            onChange={(e) =>
                                                setString("robots_txt", e.target.value)
                                            }
                                            placeholder={"User-agent: *\nDisallow: /wp-admin/"}
                                            className={cn(TEXTAREA_CLS, "fsa:font-mono")}
                                        />
                                    </StackedField>
                                </div>
                            </div>
                        )}

                        {active === "links" && (
                            <div>
                                <ToggleList items={linksUrlToggles} />
                                <div className={cn(ROW_DIVIDER, "fsa:space-y-5 fsa:p-5")}>
                                    <StackedField
                                        id="flexa-seo-aeo-orphan-attachment"
                                        icon={ExternalLink}
                                        title={__("Redirect Orphan Attachments")}
                                        description={__(
                                            "Redirect attachments without a parent post to this URL. Leave empty for no redirection.",
                                        )}
                                    >
                                        <Input
                                            id="flexa-seo-aeo-orphan-attachment"
                                            type="url"
                                            inputMode="url"
                                            value={form.redirect_orphan_attachments}
                                            onChange={(e) =>
                                                setString(
                                                    "redirect_orphan_attachments",
                                                    e.target.value,
                                                )
                                            }
                                            placeholder={
                                                window.flexaSeoAeo?.homeUrl ??
                                                "https://example.com"
                                            }
                                        />
                                    </StackedField>
                                </div>
                                <div className={ROW_DIVIDER}>
                                    <ToggleList items={linksAttrToggles} />
                                </div>
                            </div>
                        )}

                        {active === "aeo" && (
                            <div>
                                <ToggleList items={aeoToggles} />
                                <div className={cn(ROW_DIVIDER, "fsa:space-y-5 fsa:p-5")}>
                                    <StackedField
                                        id="flexa-seo-aeo-site-name"
                                        icon={Tag}
                                        title={__("Site name for AI")}
                                        description={__(
                                            "Overrides the site title in llms.txt. Defaults to the site title.",
                                        )}
                                    >
                                        <Input
                                            id="flexa-seo-aeo-site-name"
                                            value={form.aeo.site_name}
                                            onChange={(e) =>
                                                setAeoString(
                                                    "site_name",
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </StackedField>
                                    <StackedField
                                        id="flexa-seo-aeo-site-desc"
                                        icon={FileText}
                                        title={__("Site summary for AI")}
                                        description={__(
                                            "A short description answer engines can quote. Shown at the top of llms.txt.",
                                        )}
                                    >
                                        <textarea
                                            id="flexa-seo-aeo-site-desc"
                                            rows={3}
                                            value={form.aeo.site_description}
                                            onChange={(e) =>
                                                setAeoString(
                                                    "site_description",
                                                    e.target.value,
                                                )
                                            }
                                            className={TEXTAREA_CLS}
                                        />
                                    </StackedField>
                                </div>
                            </div>
                        )}

                        {active === "branding" && (
                            <div>
                                <ToggleList
                                    items={[
                                        {
                                            icon: Palette,
                                            title: __("White-label"),
                                            description: __(
                                                "Replace the “Flexa SEO” name in the admin menu and this screen.",
                                            ),
                                            checked: form.whitelabel,
                                            onChange: (v) => setBool("whitelabel", v),
                                        },
                                    ]}
                                />
                                <div className={cn(ROW_DIVIDER, "fsa:space-y-5 fsa:p-5")}>
                                    <StackedField
                                        id="flexa-seo-aeo-whitelabel-name"
                                        icon={Tag}
                                        title={__("Brand name")}
                                        description={__(
                                            "Shown when white-label is on. Takes effect after the next page load.",
                                        )}
                                    >
                                        <Input
                                            id="flexa-seo-aeo-whitelabel-name"
                                            value={form.whitelabel_name}
                                            onChange={(e) =>
                                                setString(
                                                    "whitelabel_name",
                                                    e.target.value,
                                                )
                                            }
                                            placeholder={__("e.g. Acme SEO")}
                                        />
                                    </StackedField>
                                </div>
                            </div>
                        )}

                        {active === "migrate" && <MigrationPane />}

                        {active === "tools" && (
                            <ToolsPane current={form} onImported={setForm} />
                        )}

                        {active === "danger" && (
                            <div className="fsa:p-5">
                                <DangerZone />
                            </div>
                        )}
                    </div>
                </main>
            </div>
        </>
    );
}

interface NavItemProps {
    section: SectionMeta;
    selected: boolean;
    onSelect: () => void;
}

function NavItem({ section, selected, onSelect }: NavItemProps) {
    const Icon = section.icon;
    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={selected}
            className={cn(
                "fsa:group fsa:flex fsa:w-full fsa:items-center fsa:gap-3 fsa:rounded-xl fsa:border fsa:px-3 fsa:py-2.5 fsa:text-left fsa:transition-colors fsa:cursor-pointer",
                "fsa:focus-visible:outline-none fsa:focus-visible:ring-2 fsa:focus-visible:ring-brand-500",
                selected
                    ? "fsa:border-brand-500 fsa:bg-brand-500 fsa:text-white fsa:shadow-sm"
                    : "fsa:border-slate-200 fsa:bg-white fsa:text-slate-800 fsa:hover:border-slate-300 fsa:hover:bg-slate-50",
            )}
        >
            <span
                className={cn(
                    "fsa:flex fsa:h-9 fsa:w-9 fsa:shrink-0 fsa:items-center fsa:justify-center fsa:rounded-lg",
                    selected
                        ? "fsa:bg-white/15 fsa:text-white"
                        : "fsa:bg-slate-100 fsa:text-slate-600",
                )}
            >
                <Icon className="fsa:h-4 fsa:w-4" aria-hidden />
            </span>
            <span className="fsa:min-w-0 fsa:flex-1">
                <span
                    className={cn(
                        "fsa:block fsa:text-sm fsa:font-semibold",
                        selected ? "fsa:text-white" : "fsa:text-slate-900",
                    )}
                >
                    {section.title}
                </span>
                <span
                    className={cn(
                        "fsa:block fsa:text-xs",
                        selected ? "fsa:text-white/80" : "fsa:text-slate-500",
                    )}
                >
                    {section.subtitle}
                </span>
            </span>
            <ChevronDown
                className={cn(
                    "fsa:h-4 fsa:w-4 fsa:shrink-0 fsa:transition-transform",
                    selected ? "fsa:text-white" : "fsa:-rotate-90 fsa:text-slate-400",
                )}
                aria-hidden
            />
        </button>
    );
}

function PaneHeader({ section }: { section: SectionMeta }) {
    const Icon = section.icon;
    return (
        <div className="fsa:flex fsa:items-start fsa:gap-3 fsa:border-b fsa:border-slate-100 fsa:bg-slate-50/60 fsa:px-5 fsa:py-4">
            <span className="fsa:flex fsa:h-10 fsa:w-10 fsa:shrink-0 fsa:items-center fsa:justify-center fsa:rounded-lg fsa:bg-brand-50 fsa:text-brand-600">
                <Icon className="fsa:h-5 fsa:w-5" aria-hidden />
            </span>
            <div className="fsa:min-w-0 fsa:space-y-0.5">
                <h2 className="fsa:text-base fsa:font-semibold fsa:text-slate-900">
                    {section.paneTitle}
                </h2>
                <p className="fsa:text-sm fsa:text-slate-500">
                    {section.paneSubtitle}
                </p>
            </div>
        </div>
    );
}

interface SettingRowProps {
    icon: LucideIcon;
    title: string;
    description?: string;
    htmlFor?: string;
    children: ReactNode;
}

function SettingRow({
    icon: Icon,
    title,
    description,
    htmlFor,
    children,
}: SettingRowProps) {
    return (
        <div className="fsa:flex fsa:items-center fsa:gap-3 fsa:px-5 fsa:py-4">
            <span className="fsa:flex fsa:h-10 fsa:w-10 fsa:shrink-0 fsa:items-center fsa:justify-center fsa:rounded-lg fsa:bg-slate-100 fsa:text-slate-600">
                <Icon className="fsa:h-4 fsa:w-4" aria-hidden />
            </span>
            <div className="fsa:min-w-0 fsa:flex-1">
                <Label
                    htmlFor={htmlFor}
                    className="fsa:block fsa:text-sm fsa:font-semibold fsa:text-slate-900"
                >
                    {title}
                </Label>
                {description && (
                    <p className="fsa:mt-0.5 fsa:text-xs fsa:text-slate-500">
                        {description}
                    </p>
                )}
            </div>
            <div className="fsa:shrink-0">{children}</div>
        </div>
    );
}

function ToggleList({ items }: { items: ToggleMeta[] }) {
    return (
        <div>
            {items.map((item, i) => {
                const id = `flexa-seo-aeo-toggle-${i}`;
                return (
                    <div key={item.title} className={i === 0 ? undefined : ROW_DIVIDER}>
                        <SettingRow
                            icon={item.icon}
                            htmlFor={id}
                            title={item.title}
                            description={item.description}
                        >
                            <Switch
                                id={id}
                                checked={item.checked}
                                onCheckedChange={item.onChange}
                            />
                        </SettingRow>
                    </div>
                );
            })}
        </div>
    );
}

interface StackedFieldProps {
    id: string;
    icon: LucideIcon;
    title: string;
    description?: string;
    children: ReactNode;
}

function StackedField({
    id,
    icon: Icon,
    title,
    description,
    children,
}: StackedFieldProps) {
    return (
        <div className="fsa:space-y-1.5">
            <div className="fsa:flex fsa:items-center fsa:gap-2">
                <Icon
                    className="fsa:h-4 fsa:w-4 fsa:shrink-0 fsa:text-slate-500"
                    aria-hidden
                />
                <Label
                    htmlFor={id}
                    className="fsa:text-sm fsa:font-semibold fsa:text-slate-900"
                >
                    {title}
                </Label>
            </div>
            {description && (
                <p className="fsa:text-xs fsa:text-slate-500">{description}</p>
            )}
            {children}
        </div>
    );
}

interface CheckboxGroupProps {
    icon: LucideIcon;
    title: string;
    description?: string;
    options: Record<string, string>;
    selected: string[];
    onToggle: (slug: string, on: boolean) => void;
}

function CheckboxGroup({
    icon: Icon,
    title,
    description,
    options,
    selected,
    onToggle,
}: CheckboxGroupProps) {
    const entries = Object.entries(options);
    return (
        <div className="fsa:space-y-1.5">
            <div className="fsa:flex fsa:items-center fsa:gap-2">
                <Icon
                    className="fsa:h-4 fsa:w-4 fsa:shrink-0 fsa:text-slate-500"
                    aria-hidden
                />
                <span className="fsa:text-sm fsa:font-semibold fsa:text-slate-900">
                    {title}
                </span>
            </div>
            {description && (
                <p className="fsa:text-xs fsa:text-slate-500">{description}</p>
            )}
            {entries.length === 0 ? (
                <p className="fsa:text-xs fsa:text-slate-400">
                    {__("Nothing available.")}
                </p>
            ) : (
                <div className="fsa:grid fsa:grid-cols-2 fsa:gap-2 fsa:sm:grid-cols-3">
                    {entries.map(([slug, label]) => {
                        const id = `flexa-seo-aeo-obj-${slug}`;
                        const on = selected.includes(slug);
                        return (
                            <label
                                key={slug}
                                htmlFor={id}
                                className={cn(
                                    "fsa:flex fsa:cursor-pointer fsa:items-center fsa:gap-2 fsa:rounded-md fsa:border fsa:px-3 fsa:py-2 fsa:text-sm fsa:transition-colors",
                                    on
                                        ? "fsa:border-brand-500 fsa:bg-brand-50 fsa:text-slate-900"
                                        : "fsa:border-slate-200 fsa:bg-white fsa:text-slate-700 fsa:hover:bg-slate-50",
                                )}
                            >
                                <input
                                    id={id}
                                    type="checkbox"
                                    checked={on}
                                    onChange={(e) => onToggle(slug, e.target.checked)}
                                    className="flexa-seo-aeo-check fsa:h-4 fsa:w-4 fsa:shrink-0 fsa:accent-brand-600"
                                />
                                <span className="fsa:min-w-0 fsa:truncate">{label}</span>
                            </label>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
