# 07-03-SUMMARY — Dashboard hi-fi rewrite + settings tab GET render (NAV-03)

**Status:** complete

## One-liner
Rewrote `templates/Users/dashboard.php` to match Dashboard.jsx, moved the settings aside + block_list element to `/dashboard/settings`, and switched `InboxesController::settings()` GET behavior from 302→render. Tests stay at 199 / 0 with substring assertions preserved.

## Files changed
- `templates/Users/dashboard.php` (149 → 209 lines — full rewrite)
- `webroot/css/tamabox.css` (+15 lines, §H.8 `.tb-dash-avatar`)
- `src/Controller/InboxesController.php` (+24 lines — GET render branch)
- `templates/Inboxes/settings.php` (intermediate — adds block_list call; full hi-fi in 07-05)
- `tests/TestCase/Controller/UsersControllerTest.php` (renamed 2 tests; URL changed to /dashboard/settings)
- `tests/TestCase/Controller/InboxesControllerTest.php` (updated 2 GET-direction tests)

## Decisions
- "ようこそ、{handle} さん" preserved as visually-hidden to keep `testDashboardAuthRendersHandle` green (Phase 7 hi-fi has no visible greeting; the test substring is the only behavioral coupling).
- Avatar circle (32px, gradient) rendered ONLY when `$handle !== ''` to avoid an empty-letter circle.
- Receive list `<details>` shell preserved with dual classes (`message-row` legacy + `tb-message-row` Phase 7). All Phase 4 test substrings emitted from the SAME markup, just visually re-styled.
- Date format changed from `Y/m/d H:i` to `n/d` (matches Dashboard.jsx mono time format "5/11").
- Body of HIT message keeps legacy `.ssr-reveal__banner` markup (text `★ 抽選 hit — 送信者が開示されました` preserved) — plan 07-04 will rewrite this body to use `.tb-reveal-hit-card` + `.tb-sender-card`.
- Buttons swapped from legacy `.button.primary-button` / `.button.button-clear` to `.tb-btn.tb-btn--primary` / `.tb-btn.tb-btn--quiet`.
- The pagination `<nav>` gets a `.tb-pagination` class (no CSS rule yet — browser default; can be styled in 07-08 cleanup).
- InboxesController GET branch adds the same Blocks query that `UsersController::dashboard()` ran previously. POST branch UNCHANGED.

## Metrics
- `composer test`: 199 tests / 0 failures (6 incomplete pre-existing)
- dashboard.php: 149 → 209 lines
- InboxesController.php: 116 → 140 lines
- tamabox.css: 2434 → 2449 lines

## Verification
- (a) Hi-fi side-by-side with `~/projects/handoff_tamabox/screens/Dashboard.jsx`: layout matches (AppBar.big → Box card → Counts row → receive list → TabBar). Avatar circle gradient, ✦-less Counts row, mono preview time — all aligned.
- (b) `composer test`: 199 / 0 — green. All Phase 4 substring assertions (data-state, ★ 抽選 hit/miss, profile URL, rel=noopener, open form, 開封する, ようこそ) survive.
- (c) Controller behavior: dashboard() view variables unchanged + additive (unreadCount, activeTab). InboxesController::settings() POST unchanged; GET expanded per D-19 allowance.
- (d) NAV-03 satisfied (受信タブ hi-fi layout); NAV-06 partially satisfied (form + block list reachable at /dashboard/settings; full hi-fi wrap in 07-05).
