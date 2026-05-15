---
phase: 7
slug: dashboard-4-reveal
status: passed
created: 2026-05-13
smoke_verified: 2026-05-15 (tamabox.emomie.com, deploy 77567cf)
---

# Phase 7 Verification — Dashboard 4 タブ分離 + Reveal 演出

**Baseline:** Phase 6 smoke commit `5385ede` (`tamabox.com` live with Calm Gacha v1).
**Current head:** see `git log --oneline | head -1` after the Phase 7 commits.
**Test suite:** `composer test` → 199 tests / 553 assertions / 0 failures (6 incomplete pre-existing).

`status: human_needed` until the user runs `git push lolipop main` and confirms the manual smoke checklist against the live https://tamabox.emomie.com.

---

## 1. TabBar 4 タブ切替 (NAV-01) — PASS (code), pending live smoke

**Audit method:** code review + tests.

- `templates/element/tb_tabbar.php` exists and emits 4 `<a>` items with hrefs `/dashboard`, `/dashboard/discover`, `/dashboard/notifications`, `/dashboard/settings`.
- All 4 templates (`Users/dashboard.php`, `Users/discover.php`, `Users/notifications.php`, `Inboxes/settings.php`) invoke `$this->element('tb_tabbar', ['active' => ..., 'unreadCount' => ...])` at footer.
- Backend routes registered in `config/routes.php`:
  - `/dashboard` → `Users::dashboard` (Phase 2)
  - `/dashboard/settings` → `Inboxes::settings` GET render (Phase 7, was 302 in Phase 3)
  - `/dashboard/discover` → `Users::discover` (Phase 7 NEW)
  - `/dashboard/notifications` → `Users::notifications` (Phase 7 NEW)
- `composer test` 4 new cases assert unauth 302 + auth 200 for discover/notifications.

**Verdict:** PASS (code). Live smoke required to confirm clickable tab navigation in browser.

---

## 2. TabBar アクティブハイライト + 未読ドット (NAV-02) — PASS (code), pending live smoke

**Audit method:** code review + DOM inspection at template level.

- `tb_tabbar.php` adds `is-active` class to the matching item and `aria-current="page"`.
- CSS already in `tokens.css` (line 229): `.tb-tabbar__item.is-active { color: var(--tb-turq-500); }` — turquoise highlight confirmed.
- Unread dot: `tb_tabbar.php` emits `<span class="tb-unread-dot">` inside the inbox tab's `.tb-tabbar__icon` ONLY when `$unreadCount > 0`. CSS already in `tamabox.css` (line 1089-1097) positions the dot top-right of the icon wrapper.
- `UsersController::dashboard()` computes `$unreadCount` via `Messages.opened_at IS null AND deleted_at IS null` COUNT and passes it through to the element.

**Verdict:** PASS (code). Live smoke: confirm the dot appears with unread messages and disappears when all are opened.

---

## 3. 受信タブ Dashboard.jsx 一致 + Reveal 開封動作 (NAV-03) — PASS (code), pending live smoke

**Audit method:** hi-fi side-by-side with `~/projects/handoff_tamabox/screens/Dashboard.jsx` + test suite.

Dashboard layout:
- TbAppBar (big) with title "受信箱", bell icon button (links to /dashboard/notifications), 32px gradient avatar circle with handle initial ✓
- Box card (`.tb-dash-box`): label "あなたの箱" + mono URL + warm SSR chip ✓
- Counts row (`.tb-dash-counts`): 受信 title + 件 mono num + 未開封 N pill (conditional) ✓
- Receive list (`.tb-receive-list`): each row `<details>` with state-dependent dot, anonymous/handle from text, SSR badge for HIT, 2-line preview, mono short date ✓
- Empty / page-out-of-range fallbacks preserved with `.tb-card-soft` styling ✓

Reveal open path:
- Click unread row → submit POST to `/dashboard/messages/{id}/open` → server redirects to `/dashboard#msg-{id}` → page re-renders with the message in `open` state → reveal-motion.js fires the fade-in once on initial paint
- Click already-opened row → `<details>` toggles, JS adds `.is-opening` to body, CSS fades in 400ms

Phase 4 test substrings all preserved (199/0 green confirms). All link/form structure preserved (action="/dashboard/messages/{id}/open", rel=noopener, https://bsky.app/profile/).

**Verdict:** PASS (code). Live smoke: open an unread message, confirm the body appears with fade.

---

## 4. Reveal `.is-opening` fade-in 400ms ease + RevealHit sender カード hi-fi 一致 (MOTION-02, MOTION-03) — PASS (code), pending live smoke

**Audit method:** hi-fi side-by-side with `~/projects/handoff_tamabox/screens/RevealHit.jsx` lines 24-98 (HIT) + `Reveal.jsx` lines 21-50 (MISS) + JS contract.

MOTION-02:
- `tamabox.css` §H.2: `@keyframes tb-fade-in { from { opacity: 0; transform: translateY(2px); } to { opacity: 1; transform: none; } }` + `.message-row__body.is-opening { animation: tb-fade-in 400ms ease; }` ✓
- `webroot/js/reveal-motion.js`: IIFE attaches `toggle` listener to each `details.message-row`, removes/re-adds `.is-opening` on open, removes after 500ms ✓
- `templates/layout/default.php`: `<?= $this->Html->script('reveal-motion', ['defer' => true]) ?>` in `<head>` ✓

MOTION-03 HIT card:
- Warm gradient bg (180deg, #FFF7E0 0%, #FBEFCC 100%) ✓
- ✦ watermark abs-positioned top-right with 10% honey alpha ✓
- 48px white circle with ✦ glyph (`.tb-reveal-hit-card__symbol`) ✓
- Section label "抽選結果 · SSR" + title "送信者が開示されました" + sub "<mono warm-700>{ssrPct}%</mono> を引き当てました" ✓
- Sender card: 44px gradient avatar (135deg, #E7C795, #B98449), handle name + SSR small label, mono @handle, プロフィール pill ✓

MOTION-03 MISS card (Reveal.jsx scope):
- Soft card with 48px dashed-border circle containing "—" ✓
- Section label "抽選結果" + title "送信者は匿名のまま" + sub "<mono warm-700>{100-ssrPct}%</mono> を引きました" ✓

**Verdict:** PASS (code). Live smoke: open an HIT and a MISS message, confirm fade animation + visual match.

---

## 5. 発見/通知 Empty state (NAV-04, NAV-05) — PASS (code), pending live smoke

**Audit method:** hi-fi side-by-side with Discover.jsx/Notifications.jsx + tests.

Discover:
- TbAppBar (big, title=発見, sub=箱をみつける) ✓
- Disabled search pill mock (`.tb-discover-search` with inline magnifier SVG + placeholder) ✓
- 6 tag chips (disabled, first pseudo-active) ✓
- Empty card with ✦ + "発見はもうすぐ来ます" + helper body ✓
- TabBar footer with active=discover ✓
- testDiscoverAuthRenders200 asserts body contains the Empty heading ✓

Notifications:
- TbAppBar (big, title=通知) ✓
- Centered `.tb-notif-empty` 96px paper-deep circle with 36px bell icon ✓
- Heading "通知はまだありません" + helper body ✓
- TabBar footer with active=notifications ✓
- testNotificationsAuthRenders200 asserts body contains the Empty heading ✓

**Verdict:** PASS (code). Live smoke: navigate via TabBar to discover / notifications, confirm Empty骨格 renders.

---

## 6. 設定タブ Settings.jsx 一致 (NAV-06) — PASS (code), pending live smoke

**Audit method:** hi-fi side-by-side with Settings.jsx + tests.

Settings tab:
- TbAppBar (default variant) with title "受信箱の設定" + left back icon button (history.back) ✓
- Body region with Phase 6 `inbox_settings_form` element (SSR slider + welcome textarea + accepting toggle + save button + danger zone) ✓
- Phase 6 `block_list` element (ブロック中ユーザー heading + rows or empty hint) ✓
- TabBar footer with active=settings ✓

Controller:
- `InboxesController::settings()` GET branch now renders this template (was 302 in Phase 3). POST branch unchanged — still saves and redirects to `/dashboard` with Flash success ✓

Tests:
- testSettingsTabRendersInboxSettingsForm asserts form fields at /dashboard/settings ✓
- testSettingsTabRendersBlockListSection asserts ブロック中ユーザー + block-list classes at /dashboard/settings ✓
- testSettingsGetRendersSettingsTab asserts 200 + form fields (was 302 assertion) ✓
- testSettingsStillReachableForActiveUser asserts 200 + form fields (was 302 assertion) ✓
- All Phase 6 POST behavior tests (happy path, 0%, 100%, validation errors, flash) PASS — POST branch untouched

**Verdict:** PASS (code). Live smoke: navigate to settings, change SSR%, confirm save flash returns to dashboard.

---

## Manual smoke checklist (run after `git push lolipop main`)

1. Log in at https://tamabox.emomie.com — confirm dashboard loads and 受信箱 AppBar renders with avatar circle
2. Confirm TabBar visible at footer with 4 items, 受信 highlighted in turquoise
3. Click 発見 → /dashboard/discover renders Empty state "発見はもうすぐ来ます"
4. Click 通知 → /dashboard/notifications renders Empty state "通知はまだありません"
5. Click 設定 → /dashboard/settings renders form + block list, 設定 tab highlighted
6. Back to 受信 (click 受信 in TabBar) → returns to /dashboard
7. Click an unread message → server records open → page reloads with message expanded → body fades in (visual 400ms ease)
8. Open an SSR hit message → warm gradient lottery card + sender card with プロフィール pill rendered
9. Open an SSR miss message → quiet dashed-circle MISS card rendered
10. Save settings (e.g. change SSR % to 5%) → 302 redirect to /dashboard with flash "保存しました"
11. Counts row shows 未開封 N pill when count > 0; pill hidden when 0
12. Unread dot appears on TabBar 受信 icon (next to icon) when unread count > 0

---

## Audit summary

| Criterion | Code | Tests | Live smoke |
|-----------|------|-------|------------|
| 1. TabBar 4 タブ切替 (NAV-01) | PASS | PASS (4 new) | pending |
| 2. ハイライト + 未読ドット (NAV-02) | PASS | n/a (visual) | pending |
| 3. 受信タブ Dashboard.jsx + Reveal (NAV-03) | PASS | PASS (all Phase 4 substrings) | pending |
| 4. `.is-opening` fade-in + RevealHit (MOTION-02, MOTION-03) | PASS | n/a (JS + visual) | pending |
| 5. Discover/Notifications Empty (NAV-04, NAV-05) | PASS | PASS (body substring) | pending |
| 6. 設定タブ Settings.jsx (NAV-06) | PASS | PASS (relocated 2 + updated 2) | pending |

**Composer test final:** 199 tests / 553 assertions / 0 failures / 6 incomplete (pre-existing).

**Backend-immutability audit:** only `config/routes.php` + `src/Controller/UsersController.php` + `src/Controller/InboxesController.php` modified — all within D-19 allowance. No model / migration / OAuth / moderation touched.

**Status:** `human_needed` — deploy to lolipop and run the 12-item smoke checklist.
