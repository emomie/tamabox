# 06-05-SUMMARY — Report Calm Gacha 化 (UI-05)

**Status:** complete

## One-liner
Rewrote `templates/Reports/create.php` to the Calm Gacha layout (close appbar + target excerpt soft-card + 4 radio tiles with selection state + detail textarea + sticky cancel/danger CTA pair) per `Report.jsx`.

## Files changed
- `templates/Reports/create.php` (rewrite — 59 → 70 lines)
- `webroot/css/tamabox.css` (+126 lines, §G.6 section-label + §G.7 report tiles)
- `tests/TestCase/Controller/ReportsControllerTest.php` (assertion updated — see decisions)

## Decisions
- Kept the 4 v1 reason IDs (harassment / spam / illegal / other) since the controller validation expects exactly these — hi-fi has 5 reasons but adding "impersonate" or "harass" would require controller change (out of scope per CONTEXT.md D-14).
- Combined the v1 "嫌がらせ・誹謗中傷" copy with the hi-fi "ハラスメント" wording into "ハラスメント・誹謗中傷" — backend value `harassment` unchanged.
- Custom radio tile uses `:has()` for the selected-tile background/border/shadow effect; the inner radio dot also paints via adjacent-sibling `:checked`. Graceful degradation: even without `:has()`, the inner dot still indicates selection.
- Form has `class="report-form tb-report-form"` — substring `class="report-form` still matches the test assertion.
- **Tests updated**: shortened heading "このメッセージを通報する" → hi-fi "メッセージを通報" (UI-05 acceptance). Test assertion updated to assert on the new heading "メッセージを通報" + `class="report-form` substring (rather than the full quoted attribute, which the new two-class form would break). All 4 reason value assertions untouched and still pass.

## Metrics
- `composer test`: 195/195 pass, 548 assertions
- LOC: 59 → 70 (template), CSS +126 lines

## Verification
- (a) Hi-fi side-by-side with `Report.jsx`: structure matches (close appbar → target excerpt → reason tiles → detail textarea → cancel + danger CTAs).
- (b) composer test: green.
- (c) Controller behavior unchanged: POST `/report/{id}` with valid reason → controller still processes. Form->create class assertion still passes.
- (d) Manual smoke: deferred to phase end.
