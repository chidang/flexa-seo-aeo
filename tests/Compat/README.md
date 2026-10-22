# Compatibility fixtures

What a page should say when Flexa SEO - AEO runs next to another SEO plugin,
written down so each new release of Yoast, Rank Math, All in One SEO or
SEOPress can be checked against the same assertions instead of being measured
by hand from scratch.

The 0.6.1 run is why this exists. With All in One SEO 5.0.3 active the post had
one `meta name=robots` tag, which a tag count calls clean, but the tag said
`max-image-preview:large` and nothing else. Flexa's `index, follow` was gone.
So these fixtures assert the effective directives, not the number of tags.

## `fixtures/robots.json`

| Key | Meaning |
| --- | --- |
| `fixture_version` | Bump when the shape of the file changes, not when a result does. |
| `configurations` | Named setups. `post_meta` is written to the test post before the page is requested. |
| `assertions` | What a correct page looks like for each configuration. `tag_count` is the number of robots tags; `must_include` and `must_not_include` are checked against the directives of every robots tag on the page combined. |
| `cases[].competitor` | The plugin active next to Flexa, with the exact version tested. `null` means Flexa on its own. |
| `cases[].exact` | Only on the Flexa-alone cases: the full directive list, sorted. A competitor may add harmless hints of its own, so pairings are held to the assertions instead. |
| `cases[].known_failure` | Why a case is expected to fail its assertions right now. A runner reports it as a known failure, and as news if it starts passing. |
| `cases[].observed` | One entry per run, oldest first. Never edit an old entry; add a new one. `effective` is `null` where that run counted tags but did not record the value. Runs from the live runner also keep `tags`, the content of each robots tag as printed, and `competitor_version` when the case ran against a version other than its own. |

Directives are written the way `wp_robots()` prints them (`max-snippet:-1`),
lower case, sorted, joined with `, `.

## Moving to a new competitor version

Copy the case, change `competitor.version`, leave `observed` empty and run it.
Keep the old case: it is the record of what that version did.

## Running the pairings

`run-robots.php` checks every case against a real install. For each case it
activates the competitor next to Flexa, writes the configuration's post meta
to a throwaway post, requests the post over HTTP, and holds every robots tag
on the page against the assertions. All tags are combined before
`must_include` and `must_not_include` are checked, so two tags that disagree
fail the check.

```bash
composer compat:robots -- --path=/path/to/wordpress
# or, with options:
php tests/Compat/run-robots.php --path=/path/to/wordpress --only=rank-math --write
```

| Option | Meaning |
| --- | --- |
| `--path` | WordPress root. Required. |
| `--wp` | The command that runs WP-CLI. Defaults to `$FLEXA_COMPAT_WP`, then `wp`. On a Local site, point PHP at its MySQL socket: `FLEXA_COMPAT_WP="php -d display_errors=stderr -d mysqli.default_socket='<socket>' wp"`. |
| `--only` | Run only the cases whose id starts with this. |
| `--allow-drift` | Run a case even when the installed competitor is a different version. The result is labelled. Without it the case is skipped. |
| `--write` | Append this run to each case's `observed` list. |

Each case comes out as `PASS`, `FAIL`, `KNOWN` (fails, and the fixture says
why), `FIXED` (has a `known_failure` but passes now) or `SKIP`. The exit
status is 1 on any `FAIL` or `FIXED`, or on an error.

The run changes the site while it goes: plugins are switched on and off, a
post is created, rewrite rules are flushed. It puts all of that back at the
end, including on Ctrl-C. Use a development site.

Two things it does not control. Competitors run with whatever settings the
site already holds for them, so a site where one was configured is not a
default-settings run. And it refuses to start when Flexa's Titles & Metas
module is off, rather than change Flexa's settings itself.

## Where this is checked

- `tests/Unit/RobotsMergeTest.php` checks that every case points at a
  configuration that exists, and that the Flexa-alone `exact` values are what
  the `wp_robots` filter really produces. It runs in the normal unit suite.
- `run-robots.php` checks the pairings. It needs a real WordPress install
  with the competitors in `wp-content/plugins`, so it is not part of
  `composer test`.
