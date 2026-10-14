import { ExternalLink, Zap, X } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { api } from "@/lib/api";
import { __ } from "@/lib/i18n";
import { getPluginGlobal } from "@/lib/wp";

/**
 * Suggests Flexa Cache on the Dashboard. Flexa SEO builds its tags, JSON-LD
 * and sitemap entries while WordPress assembles a page; a page cache serves
 * the finished HTML instead, so that work runs once rather than per visit.
 *
 * The server decides whether there is anything to suggest (see CachePlugin),
 * so this renders nothing unless it was handed a hint. Closing the card is
 * remembered per site, and the quieter Tools row stays regardless.
 */
export function CachePromoCard() {
  const hint = getPluginGlobal().cachePlugin;
  const [closed, setClosed] = useState(false);

  if (!hint || hint.dismissed || closed) {
    return null;
  }

  const installed = hint.status === "inactive";

  // Hide first, then tell the server. A failed write only means the card
  // returns on the next page load, which is not worth an error toast.
  const dismiss = () => {
    setClosed(true);
    void api.post("hints/cache-plugin/dismiss").catch(() => undefined);
  };

  return (
    <div className="fsa:mb-4 fsa:flex fsa:flex-wrap fsa:items-center fsa:justify-between fsa:gap-3 fsa:rounded-xl fsa:border fsa:border-slate-200 fsa:border-l-4 fsa:border-l-emerald-500 fsa:bg-white fsa:px-5 fsa:py-4 fsa:shadow-sm">
      <div className="fsa:min-w-0 fsa:flex-1">
        <div className="fsa:flex fsa:items-center fsa:gap-2 fsa:text-sm fsa:font-semibold fsa:text-slate-900">
          <Zap className="fsa:h-4 fsa:w-4 fsa:text-emerald-600" aria-hidden />
          {installed
            ? __("Flexa Cache is installed but switched off.")
            : __("Serve these pages from a cache.")}
        </div>
        <p className="fsa:mt-0.5 fsa:text-xs fsa:text-slate-500">
          {__(
            "Flexa SEO writes your tags, schema and sitemap entries while WordPress builds a page. Flexa Cache stores the finished page as a static file, so that work happens once instead of on every visit. Free, from the same team.",
          )}
        </p>
      </div>
      <div className="fsa:flex fsa:items-center fsa:gap-2">
        {/*
          An activate link only ever appears for a copy already on the site.
          With no copy installed there is nothing to click here, so the reader
          goes to the listing and installs from there if they want to.
        */}
        {hint.actionUrl !== "" ? (
          <Button asChild size="sm">
            <a href={hint.actionUrl}>{__("Activate Flexa Cache")}</a>
          </Button>
        ) : (
          <Button asChild variant="outline" size="sm">
            <a href={hint.infoUrl} target="_blank" rel="noreferrer">
              {__("View on WordPress.org")}
              <ExternalLink className="fsa:h-3.5 fsa:w-3.5" aria-hidden />
            </a>
          </Button>
        )}
        <button
          type="button"
          onClick={dismiss}
          aria-label={__("Dismiss the Flexa Cache suggestion")}
          className="fsa:shrink-0 fsa:rounded fsa:p-1 fsa:text-slate-400 fsa:transition-colors fsa:cursor-pointer fsa:hover:bg-slate-100 fsa:hover:text-slate-600"
        >
          <X className="fsa:h-4 fsa:w-4" aria-hidden />
        </button>
      </div>
    </div>
  );
}
