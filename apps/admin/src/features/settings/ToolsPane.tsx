import { Download, ExternalLink, Sparkles, Upload, Zap } from "lucide-react";
import { useRef, useState } from "react";
import { Button } from "@/components/ui/button";
import { __ } from "@/lib/i18n";
import { useUiStore } from "@/lib/store";
import { getPluginGlobal } from "@/lib/wp";
import { type SettingsData, useImportSettings } from "./useSettings";

interface ToolsPaneProps {
  current: SettingsData;
  onImported: (data: SettingsData) => void;
}

interface ExportEnvelope {
  plugin: string;
  version: string;
  exported_at: string;
  settings: SettingsData;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

/**
 * Extract a settings object from an imported file: accept either our export
 * envelope (`{ plugin, settings }`) or a bare settings object. The server
 * sanitizes and drops unknown keys, so we only need a shape sanity check here.
 */
function parseImport(raw: string): Record<string, unknown> {
  const parsed: unknown = JSON.parse(raw);
  if (!isRecord(parsed)) {
    throw new Error(__("The file is not a valid settings object."));
  }
  if (isRecord(parsed.settings)) {
    return parsed.settings;
  }
  return parsed;
}

export function ToolsPane({ current, onImported }: ToolsPaneProps) {
  const fileRef = useRef<HTMLInputElement>(null);
  const [importError, setImportError] = useState("");
  const importer = useImportSettings();
  const showToast = useUiStore((s) => s.showToast);
  const openSetup = useUiStore((s) => s.openSetup);

  const onExport = () => {
    const envelope: ExportEnvelope = {
      plugin: "flexa-seo-aeo",
      version: window.flexaSeoAeo?.version ?? "",
      exported_at: new Date().toISOString(),
      settings: current,
    };
    const blob = new Blob([JSON.stringify(envelope, null, 2)], {
      type: "application/json",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    const stamp = new Date().toISOString().slice(0, 10);
    a.href = url;
    a.download = `flexa-seo-aeo-settings-${stamp}.json`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
    showToast(__("Settings exported."));
  };

  const onPickFile = () => {
    setImportError("");
    fileRef.current?.click();
  };

  const onFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    // Reset the input so re-selecting the same file fires change again.
    e.target.value = "";
    if (!file) {
      return;
    }

    const reader = new FileReader();
    reader.onload = () => {
      try {
        const data = parseImport(
          String(reader.result),
        ) as unknown as SettingsData;
        importer.mutate(data, {
          onSuccess: (next) => {
            onImported(next);
            showToast(__("Settings imported."));
          },
          onError: (err) => setImportError((err as Error).message),
        });
      } catch (err) {
        setImportError((err as Error).message);
      }
    };
    reader.onerror = () => setImportError(__("Could not read the file."));
    reader.readAsText(file);
  };

  return (
    <div className="fsa:space-y-5 fsa:p-5">
      <div className="fsa:rounded-lg fsa:border fsa:border-slate-200 fsa:p-4">
        <div className="fsa:flex fsa:items-start fsa:gap-3">
          <span className="fsa:flex fsa:h-9 fsa:w-9 fsa:shrink-0 fsa:items-center fsa:justify-center fsa:rounded-lg fsa:bg-brand-50 fsa:text-brand-600">
            <Sparkles className="fsa:h-4 fsa:w-4" aria-hidden />
          </span>
          <div className="fsa:min-w-0 fsa:flex-1">
            <div className="fsa:text-sm fsa:font-semibold fsa:text-slate-900">
              {__("Setup Assistant")}
            </div>
            <p className="fsa:text-xs fsa:text-slate-500">
              {__(
                "Re-run detection and recommendations. It only proposes still-unconfigured values.",
              )}
            </p>
          </div>
          <Button
            variant="outline"
            onClick={openSetup}
            className="fsa:shrink-0 fsa:gap-2"
          >
            <Sparkles className="fsa:h-4 fsa:w-4" aria-hidden />
            {__("Open")}
          </Button>
        </div>
      </div>

      <div className="fsa:rounded-lg fsa:border fsa:border-slate-200 fsa:p-4">
        <div className="fsa:flex fsa:items-start fsa:gap-3">
          <span className="fsa:flex fsa:h-9 fsa:w-9 fsa:shrink-0 fsa:items-center fsa:justify-center fsa:rounded-lg fsa:bg-brand-50 fsa:text-brand-600">
            <Download className="fsa:h-4 fsa:w-4" aria-hidden />
          </span>
          <div className="fsa:min-w-0 fsa:flex-1">
            <div className="fsa:text-sm fsa:font-semibold fsa:text-slate-900">
              {__("Export settings")}
            </div>
            <p className="fsa:text-xs fsa:text-slate-500">
              {__("Download the current configuration as a JSON file.")}
            </p>
          </div>
          <Button
            variant="outline"
            onClick={onExport}
            className="fsa:shrink-0 fsa:gap-2"
          >
            <Download className="fsa:h-4 fsa:w-4" aria-hidden />
            {__("Export")}
          </Button>
        </div>
      </div>

      <div className="fsa:rounded-lg fsa:border fsa:border-slate-200 fsa:p-4">
        <div className="fsa:flex fsa:items-start fsa:gap-3">
          <span className="fsa:flex fsa:h-9 fsa:w-9 fsa:shrink-0 fsa:items-center fsa:justify-center fsa:rounded-lg fsa:bg-brand-50 fsa:text-brand-600">
            <Upload className="fsa:h-4 fsa:w-4" aria-hidden />
          </span>
          <div className="fsa:min-w-0 fsa:flex-1">
            <div className="fsa:text-sm fsa:font-semibold fsa:text-slate-900">
              {__("Import settings")}
            </div>
            <p className="fsa:text-xs fsa:text-slate-500">
              {__(
                "Replace the current configuration from a previously exported file.",
              )}
            </p>
            {importError !== "" && (
              <p className="fsa:mt-1 fsa:text-xs fsa:text-red-700">
                {importError}
              </p>
            )}
          </div>
          <Button
            variant="outline"
            onClick={onPickFile}
            disabled={importer.isPending}
            className="fsa:shrink-0 fsa:gap-2"
          >
            <Upload className="fsa:h-4 fsa:w-4" aria-hidden />
            {importer.isPending ? __("Importing…") : __("Import")}
          </Button>
          <input
            ref={fileRef}
            type="file"
            accept="application/json,.json"
            onChange={onFileChange}
            className="fsa:hidden"
          />
        </div>
      </div>

      <CacheRow />
    </div>
  );
}

/**
 * A standing pointer to Flexa Cache. The dashboard card can be dismissed; this
 * one cannot, which is why it is a plain row rather than a notice. It is here
 * because a page cache is what keeps the tags, schema and sitemap entries this
 * plugin writes from being rebuilt on every visit. The server sends no hint at
 * all when Flexa Cache is already active or white-label is on, so there is
 * nothing to render in those cases.
 */
function CacheRow() {
  const hint = getPluginGlobal().cachePlugin;

  if (!hint) {
    return null;
  }

  const installed = hint.status === "inactive";

  return (
    <div className="fsa:rounded-lg fsa:border fsa:border-slate-200 fsa:p-4">
      <div className="fsa:flex fsa:items-start fsa:gap-3">
        <span className="fsa:flex fsa:h-9 fsa:w-9 fsa:shrink-0 fsa:items-center fsa:justify-center fsa:rounded-lg fsa:bg-emerald-50 fsa:text-emerald-600">
          <Zap className="fsa:h-4 fsa:w-4" aria-hidden />
        </span>
        <div className="fsa:min-w-0 fsa:flex-1">
          {/* A product name, so not translated. */}
          <div className="fsa:text-sm fsa:font-semibold fsa:text-slate-900">
            Flexa Cache
          </div>
          <p className="fsa:text-xs fsa:text-slate-500">
            {__(
              "Page cache and Redis object cache from the same team. A cached page is served as a static file, so the tags, schema and sitemap entries Flexa SEO builds are not rebuilt on every visit.",
            )}
          </p>
        </div>
        {hint.actionUrl !== "" ? (
          <Button asChild variant="outline" className="fsa:shrink-0 fsa:gap-2">
            <a href={hint.actionUrl}>
              <Zap className="fsa:h-4 fsa:w-4" aria-hidden />
              {installed ? __("Activate") : __("Install")}
            </a>
          </Button>
        ) : (
          <Button asChild variant="outline" className="fsa:shrink-0 fsa:gap-2">
            <a href={hint.infoUrl} target="_blank" rel="noreferrer">
              <ExternalLink className="fsa:h-4 fsa:w-4" aria-hidden />
              {__("Learn more")}
            </a>
          </Button>
        )}
      </div>
    </div>
  );
}
