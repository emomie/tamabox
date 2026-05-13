# 06-02-SUMMARY — Home Calm Gacha 化 (UI-01)

**Status:** complete

## One-liner
Rewrote `templates/Pages/home.php` to the Calm Gacha hero layout (✦ + display title + lead → HOW divider → 3-step list → Bluesky CTA) per `Home.jsx`. Preserved the form POST behavior.

## Files changed
- `templates/Pages/home.php` (rewrite — 31 → 60 lines)
- `webroot/css/tamabox.css` (+105 lines, §G.2)
- `tests/TestCase/Controller/PagesControllerTest.php` (assertion text updated — see decisions)

## Decisions
- Kept the existing v1 CTA copy "Bluesky でログイン" rather than hi-fi's "Bluesky ではじめる" to preserve production user expectation.
- Used 30px display title for the hero. This is outside the Phase 5 locked 8-size scale (22 / 18 / 16 / 15 / 14 / 12 / 11 / 10) — accepted as a one-off display heading per the existing visual identity. Flagged in plan risks; not blocking.
- Bluesky logo SVG inlined verbatim from `Home.jsx` (placeholder cloud mark — v3 ASSET-01 will replace with official butterfly).
- **Test assertion updated**: `testHomePageContainsPhase3Explainer` previously asserted on the long paragraph "確率で送信者の名前がバレる" which was removed in the Calm Gacha redesign. Updated to assert on the equivalent step copy "SSR 確率で身元が開示されます" — same concept, new hi-fi-aligned wording. The Bluesky CTA assertion is untouched.

## Metrics
- `composer test`: 195/195 pass (1 line of assertion text updated, not weakened)
- LOC: 31 → 60 (template), test +1 line of comment, -1+1 assertion change

## Verification
- (a) Hi-fi side-by-side with `Home.jsx`: structure matches (hero → HOW divider → 3 steps → CTA).
- (b) composer test: green after assertion update.
- (c) Controller behavior unchanged: POST `/auth/start-bluesky` resolves; CSRF token still emitted.
- (d) Manual smoke: deferred to phase end.
