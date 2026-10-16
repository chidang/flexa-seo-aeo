# Unit tests

Regression tests for the behaviours that have broken at least once. Every case
here was written against a defect found during QA of 0.5.5 and 0.5.6, so a
failure in this suite means a known bug is back.

```bash
composer test            # or: vendor/bin/phpunit
vendor/bin/phpunit --filter MetasPagination
```

No database, no WordPress install, no network. The suite runs in about 30ms.

## What is covered

| File | Pins |
| --- | --- |
| `Unit/MetasPaginationTest.php` | Page numbering, whether a view really has page N, and the canonical for page N (defects R-01 and S-01) |
| `Unit/PostSummaryTest.php` | Meta description built from content: unregistered shortcodes, bracketed prose, password-protected posts (defect S-02 and the 0.5.6 password fix) |
| `Unit/VariablesTest.php` | Template token replacement and the separator left behind by an empty token, over all nine separators (defects R-03 and S-04) |
| `Unit/SettingsMemoTest.php` | The per-request settings memo, its invalidation, and post-type pruning across the `init` boundary (defects P-07, R-02, R-07) |
| `Unit/SettingsSchemaTest.php` | What a REST write is allowed to persist: unknown keys, boolean coercion, separator and IndexNow key sanitising |
| `Unit/SitemapExclusionTest.php` | Keeping noindexed and password-protected posts out of the sitemap, in both the inline-list and subquery modes, including the single-join query shape |

## How the WordPress side is faked

`Stubs/wp-functions.php` and `Stubs/wp-classes.php` define the handful of
WordPress functions and classes the code under test calls, each guarded with
`function_exists()` so the files are inert inside a real WordPress process.
All of their mutable state lives in one place, `Stubs/WpState.php`, which
`TestCase::setUp()` resets between tests.

The stubs are a fixture, not an emulator. They are faithful where a test
depends on the behaviour (`strip_shortcodes()` only removes tags registered on
the current request, `get_option()` runs the `option_{$name}` filter,
`wp_strip_all_tags()` mirrors core's two-step regex) and crude everywhere else.

That line matters when adding a test: assert on the plugin's own logic, never
on something whose outcome depends on how good a stub is. A test that passes
because `add_query_arg()` happens to be stubbed the way the assertion expects
is testing the fixture.

Private methods are reached with reflection through `TestCase::call_private()`
rather than by widening them, and services with singleton traits are built with
`TestCase::bare()`, which skips the constructor.

## Checking that a test has teeth

The suite was validated by reverting each fix it covers and confirming the
relevant tests fail:

| Reverted | Failures |
| --- | --- |
| `paginates_to()` counting `<!--nextpage-->` markers by hand instead of asking core | 4 |
| `condense()` without the bracket-stripping pass | 6 |
| Settings resolving the option on every read | 3 |
| An early-resolved settings memo never rebuilt after `init` | 2 |
| `Variables` without the sentinel for an empty token | 48 |
| The sitemap keeping password-protected posts | 1 |

Worth repeating for any new test: break the code on purpose, watch the test
fail, put it back.
