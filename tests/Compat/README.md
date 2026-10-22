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
| `cases[].observed` | One entry per run, oldest first. Never edit an old entry; add a new one. `effective` is `null` where that run counted tags but did not record the value. |

Directives are written the way `wp_robots()` prints them (`max-snippet:-1`),
lower case, sorted, joined with `, `.

## Moving to a new competitor version

Copy the case, change `competitor.version`, leave `observed` empty and run it.
Keep the old case: it is the record of what that version did.

## Where this is checked

- `tests/Unit/RobotsMergeTest.php` checks that every case points at a
  configuration that exists, and that the Flexa-alone `exact` values are what
  the `wp_robots` filter really produces. It runs in the normal unit suite.
- The pairings need a real WordPress install with the competitor active, so
  they are not part of `composer test`. The live runner that fills in
  `observed` is the next step.
