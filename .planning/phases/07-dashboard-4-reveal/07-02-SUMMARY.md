# 07-02-SUMMARY — discover + notifications stub actions (NAV-04, NAV-05)

**Status:** complete

## One-liner
Added two new GET routes + two new controller actions (`UsersController::discover` / `::notifications`), augmented `dashboard()` with `$unreadCount`, and grew the test suite from 195 → 199 / 0 failures with 4 new auth/unauth cases.

## Files changed
- `src/Controller/UsersController.php` (+47 lines — 2 new actions + unreadCount query + activeTab in both success and pageOutOfRange branches)
- `config/routes.php` (+13 lines — 2 GET-only routes)
- `tests/TestCase/Controller/UsersControllerTest.php` (+47 lines — 4 new test methods)
- `templates/Users/discover.php` (new placeholder; 2 lines — will be rewritten by 07-06)
- `templates/Users/notifications.php` (new placeholder; 2 lines — will be rewritten by 07-07)

## Decisions
- Defensive `Authentication->getIdentity() === null` redirect inside each stub action mirrors `dashboard()` Phase 3 pattern (defense-in-depth even though AuthenticationMiddleware usually catches unauth earlier).
- `unreadCount` uses `Messages.opened_at IS` null + `Messages.deleted_at IS` null to mirror soft-delete exclusion logic from existing `paginate()`.
- Routes placed immediately after `/dashboard/settings` and BEFORE `/report/{id}` and `/{slug}` catch-all — confirmed via diff that the slug regex catch-all stays last.
- Placeholder templates emit a title + HTML comment only; full templates ship in 07-06 / 07-07.

## Metrics
- `composer test`: 199 tests / 0 failures (6 incomplete pre-existing) — was 195
- New action LOC: ~30 (discover) + ~30 (notifications, including docblocks)

## Verification
- (a) Hi-fi side-by-side: n/a for this plan (pure backend infrastructure)
- (b) `composer test`: 199 / 0 — green
- (c) Controller behavior unchanged: existing dashboard view vars all preserved; `unreadCount` and `activeTab` are pure additions. No model/migration touched.
- (d) NAV-04 backend stub + NAV-05 backend stub satisfied; body content shipped in 07-06 / 07-07.
