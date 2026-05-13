---
phase: 8
plan: 08-02
status: complete
commit: 7c95a18
requirements: [EDGE-01]
completed: 2026-05-13
---

# Plan 08-02 Summary — EDGE-01 404 SendNotFound

## What shipped

- **`templates/Error/error400.php`** rewritten body from bake-default `<h2>` + `<p class="error">` to Calm Gacha SendNotFound layout: `.tb-error-screen` scaffold + dashed-circle `?` symbol + mono URL pill + 280-char body text + 2-button CTA stack ("tamabox に戻る" primary + "URL を確認しなおす" quiet link).
- **`templates/layout/error.php`** migrated from legacy Milligram chain (`cake.css` + `fonts.css`) to the Calm Gacha chain (`tokens.css` + `colors_and_type.css` + `tamabox.css`). Added Google Fonts links (Noto Sans JP, JetBrains Mono) for parity with `default.php`. Dropped the trailing `Html->link('Back', ...)` and the legacy `.error-container` wrapper.
- **Dev-mode preserved** — `Configure::read('debug') === true` still routes through `dev_error` layout with the bake stack-trace dump.
- **New test** `testSendNotFoundRendersHiFiTemplate` asserts 404 + body contains `'この箱は見つかりません'` + `'tb-error-screen'`.
- **Updated test** `PagesControllerTest::testMissingTemplate` — assertion target shifted from `'Error'` (legacy English bake copy) to `'この箱は見つかりません'` (new Japanese hi-fi title). Strictly stronger.

## Files touched

- `templates/Error/error400.php` (rewrite)
- `templates/layout/error.php` (CSS chain swap + body cleanup)
- `tests/TestCase/Controller/MessagesControllerTest.php` (+1 test method, 11 lines)
- `tests/TestCase/Controller/PagesControllerTest.php` (1 assertion update + comment)

## Tests

```
Tests: 200, Assertions: 556, Incomplete: 6.
OK
```

Delta: 199 → 200 (+1 new test). 1 existing test updated (assertion strengthened).

## Decisions confirmed

- D-01: every CakePHP 400-class error → SendNotFound (single-pattern simplification). The `$url` variable is escaped via `h()` and shown only when non-empty.
- D-03: error500 stays out of scope (v3 candidate).
- Custom ExceptionRenderer NOT introduced (D-01 simplification).

## Risks closed

- `cake.css` + `fonts.css` orphaned references: deletion deferred to v3 polish PR (CONTEXT discretion area). Files retained on disk; no longer loaded by `error.php`.
- Dev-mode regression: the `dev_error` branch (lines 23-46) is unchanged — debug=true users still see stack traces.
- The `PagesControllerTest::testMissingTemplate` was the only test that asserted on the legacy bake `'Error'` string. No other tests assert on 404 body content (besides the new EDGE-01 test).

## Unlocks

08-03..08-06 — share the §I CSS scaffolds already in place.
