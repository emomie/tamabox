# 07-07-SUMMARY — Notifications tab Empty-state stub (NAV-05)

**Status:** complete

## One-liner
Replaced the 07-02 placeholder with the full 通知 tab Empty-state layout (TbAppBar + centered bell circle + "通知はまだありません" copy + TabBar) and strengthened the auth test to assert body copy.

## Files changed
- `templates/Users/notifications.php` (placeholder 2 → 34 lines)
- `tests/TestCase/Controller/UsersControllerTest.php` (testNotificationsAuthRenders200 +1 assertion)

## Decisions
- AppBar uses big variant (matching Notifications.jsx header). No sub-title (the hi-fi React adds "すべて既読" button which is deferred to NOTIF-01 v3).
- `.tb-notif-empty` uses `flex:1` to fill the space between AppBar and TabBar — defined in §H.6.
- Bell icon at size 36 inside a 96px circle creates the empty-state focal point. The icon doesn't constrain to the typography scale (typography scale governs font-size, not SVG dimensions).
- Body copy "メッセージへの返信や開封のお知らせがここに届きます。" matches the hi-fi tone.

## Metrics
- `composer test`: 199 tests / 0 failures, 553 assertions (was 552 — +1 for the body assertion)
- notifications.php: 2 → 34 lines

## Verification
- (a) Hi-fi side-by-side with `~/projects/handoff_tamabox/screens/Notifications.jsx` lines 13-22 (header) + concept of centered Empty (not in the React file but documented in UI-SPEC Screen 3). Layout matches.
- (b) `composer test`: 199 / 0 — green.
- (c) Controller behavior unchanged. UsersController::notifications() from 07-02 untouched.
- (d) NAV-05 satisfied (通知タブ Empty-state hi-fi 骨格).
