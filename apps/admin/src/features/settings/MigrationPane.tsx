import { AlertTriangle, CheckCircle2, DatabaseZap, Loader2 } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { __, sprintf } from "@/lib/i18n";
import { useUiStore } from "@/lib/store";
import {
    type MigrationProgress,
    runMigration,
    useMigrationSources,
} from "./useMigration";

interface RunState {
    progress?: MigrationProgress;
    done?: MigrationProgress;
    error?: string;
}

export function MigrationPane() {
    const { data: sources, isLoading, isError } = useMigrationSources();
    const showToast = useUiStore((s) => s.showToast);

    const [overwrite, setOverwrite] = useState<Record<string, boolean>>({});
    const [runs, setRuns] = useState<Record<string, RunState>>({});
    const [running, setRunning] = useState<string | null>(null);

    const onMigrate = async (id: string, label: string) => {
        setRunning(id);
        setRuns((r) => ({ ...r, [id]: { progress: undefined } }));
        try {
            const final = await runMigration(
                id,
                Boolean(overwrite[id]),
                (progress) =>
                    setRuns((r) => ({ ...r, [id]: { progress } })),
            );
            setRuns((r) => ({ ...r, [id]: { done: final } }));
            showToast(
                sprintf(
                    /* translators: 1: source label, 2: migrated count, 3: skipped count */
                    __("%1$s: migrated %2$d, skipped %3$d."),
                    label,
                    final.migrated,
                    final.skipped,
                ),
            );
        } catch (err) {
            setRuns((r) => ({
                ...r,
                [id]: { error: (err as Error).message },
            }));
        } finally {
            setRunning(null);
        }
    };

    return (
        <div className="fsa:space-y-5 fsa:p-5">
            <div className="fsa:flex fsa:items-start fsa:gap-3 fsa:rounded-lg fsa:border fsa:border-amber-200 fsa:bg-amber-50 fsa:p-4">
                <AlertTriangle
                    className="fsa:mt-0.5 fsa:h-4 fsa:w-4 fsa:shrink-0 fsa:text-amber-600"
                    aria-hidden
                />
                <p className="fsa:text-xs fsa:text-amber-800">
                    {__(
                        "Back up your database first. This copies titles, descriptions, canonical URLs, social tags, and robots settings into Flexa SEO. Without “Overwrite”, only empty Flexa fields are filled; your existing edits are kept.",
                    )}
                </p>
            </div>

            {isLoading && (
                <p className="fsa:text-sm fsa:text-slate-500">
                    {__("Scanning for existing SEO data…")}
                </p>
            )}

            {isError && (
                <p className="fsa:text-sm fsa:text-red-700">
                    {__("Could not read migration sources.")}
                </p>
            )}

            {sources?.map((source) => {
                const run = runs[source.id];
                const isThisRunning = running === source.id;
                const pct =
                    run?.progress && run.progress.total > 0
                        ? Math.round(
                              (run.progress.processed / run.progress.total) *
                                  100,
                          )
                        : 0;

                return (
                    <div
                        key={source.id}
                        className="fsa:rounded-lg fsa:border fsa:border-slate-200 fsa:p-4"
                    >
                        <div className="fsa:flex fsa:items-start fsa:gap-3">
                            <span className="fsa:flex fsa:h-9 fsa:w-9 fsa:shrink-0 fsa:items-center fsa:justify-center fsa:rounded-lg fsa:bg-brand-50 fsa:text-brand-600">
                                <DatabaseZap
                                    className="fsa:h-4 fsa:w-4"
                                    aria-hidden
                                />
                            </span>
                            <div className="fsa:min-w-0 fsa:flex-1">
                                <div className="fsa:text-sm fsa:font-semibold fsa:text-slate-900">
                                    {source.label}
                                </div>
                                <p className="fsa:text-xs fsa:text-slate-500">
                                    {source.available
                                        ? sprintf(
                                              /* translators: %d: number of posts */
                                              __(
                                                  "%d post(s) with data to import.",
                                              ),
                                              source.count,
                                          )
                                        : __("No data found from this plugin.")}
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                disabled={
                                    !source.available || running !== null
                                }
                                onClick={() =>
                                    onMigrate(source.id, source.label)
                                }
                                className="fsa:shrink-0 fsa:gap-2"
                            >
                                {isThisRunning ? (
                                    <Loader2 className="fsa:h-4 fsa:w-4 fsa:animate-spin" aria-hidden />
                                ) : (
                                    <DatabaseZap
                                        className="fsa:h-4 fsa:w-4"
                                        aria-hidden
                                    />
                                )}
                                {isThisRunning
                                    ? __("Migrating…")
                                    : __("Migrate")}
                            </Button>
                        </div>

                        {source.available && (
                            <label className="fsa:mt-3 fsa:flex fsa:items-center fsa:gap-2 fsa:text-xs fsa:text-slate-600">
                                <Switch
                                    checked={Boolean(overwrite[source.id])}
                                    disabled={running !== null}
                                    onCheckedChange={(next) =>
                                        setOverwrite((o) => ({
                                            ...o,
                                            [source.id]: next,
                                        }))
                                    }
                                />
                                {__("Overwrite existing Flexa values")}
                            </label>
                        )}

                        {run?.progress && !run.done && (
                            <div className="fsa:mt-3">
                                <div className="fsa:h-1.5 fsa:w-full fsa:overflow-hidden fsa:rounded-full fsa:bg-slate-100">
                                    <div
                                        className="fsa:h-full fsa:rounded-full fsa:bg-brand-600 fsa:transition-all"
                                        style={{ width: `${pct}%` }}
                                    />
                                </div>
                                <p className="fsa:mt-1 fsa:text-xs fsa:text-slate-500">
                                    {sprintf(
                                        /* translators: 1: processed, 2: total */
                                        __("%1$d / %2$d processed"),
                                        run.progress.processed,
                                        run.progress.total,
                                    )}
                                </p>
                            </div>
                        )}

                        {run?.done && (
                            <p className="fsa:mt-3 fsa:flex fsa:items-center fsa:gap-1.5 fsa:text-xs fsa:text-green-700">
                                <CheckCircle2
                                    className="fsa:h-4 fsa:w-4"
                                    aria-hidden
                                />
                                {sprintf(
                                    /* translators: 1: migrated count, 2: skipped count */
                                    __("Done: migrated %1$d, skipped %2$d."),
                                    run.done.migrated,
                                    run.done.skipped,
                                )}
                            </p>
                        )}

                        {run?.error && (
                            <p className="fsa:mt-3 fsa:text-xs fsa:text-red-700">
                                {run.error}
                            </p>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
