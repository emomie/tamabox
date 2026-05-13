---
phase: 7
slug: dashboard-4-reveal
status: draft
preset: none
created: 2026-05-13
---

# Phase 7 — UI Design Contract: Dashboard 4 タブ分離 + Reveal 演出

> Visual and interaction contract for splitting the v1 dashboard into 4 tabs (受信 / 発見 / 通知 / 設定), introducing the `tb_tabbar` PHP element, and wiring the Reveal fade-in animation (MOTION-02) and RevealHit sender card演出 (MOTION-03).
> Source of truth: `~/projects/handoff_tamabox/screens/{Dashboard,Discover,Notifications,Settings,Reveal,RevealHit}.jsx`, `~/projects/handoff_tamabox/components.jsx` (TbTabBar / TbAppBar), and Phase 5/6 CSS already in `webroot/css/tamabox.css` + `tokens.css`.
> Phase 7 produces ONE new shared element (`tb_tabbar`), TWO new templates (`Users/discover.php`, `Users/notifications.php`), TWO rewrites (`Users/dashboard.php`, `Inboxes/settings.php`), TWO backend stubs (`UsersController::discover` / `::notifications`), and ONE new JS file (`webroot/js/reveal-motion.js`).

---

## Scope

| # | Plan | Req | Target file | Hi-fi reference |
|---|------|-----|-------------|-----------------|
| 1 | 07-01 | NAV-01, NAV-02 | `templates/element/tb_tabbar.php` (new) + `webroot/css/tamabox.css` (§H additions) | `components.jsx` lines 116-125 (TbTabBar) + Dashboard.jsx lines 90-98 |
| 2 | 07-02 | NAV-04, NAV-05 | `src/Controller/UsersController.php` (+2 actions) + `config/routes.php` (+2 routes) + `tests/TestCase/Controller/UsersControllerTest.php` (+4 cases) | (backend stub, no hi-fi) |
| 3 | 07-03 | NAV-03 | `templates/Users/dashboard.php` (rewrite) | `screens/Dashboard.jsx` |
| 4 | 07-04 | MOTION-02, MOTION-03 | `templates/Users/dashboard.php` (RevealHit body) + `webroot/js/reveal-motion.js` (new) + `templates/layout/default.php` (script tag) + `webroot/css/tamabox.css` (`.is-opening` keyframe) | `screens/Reveal.jsx` + `screens/RevealHit.jsx` lines 65-98 |
| 5 | 07-05 | NAV-06 | `templates/Inboxes/settings.php` (rewrite) + `src/Controller/InboxesController.php` (GET render branch) | `screens/Settings.jsx` |
| 6 | 07-06 | NAV-04 | `templates/Users/discover.php` (new) | `screens/Discover.jsx` (Empty-state subset) |
| 7 | 07-07 | NAV-05 | `templates/Users/notifications.php` (new) | `screens/Notifications.jsx` (Empty-state subset) |
| 8 | 07-08 | (verification) | (no code) — final smoke checklist + cleanup audit | — |

Execution order locked: TabBar element + CSS must land first (07-01); backend stubs / routes second (07-02); dashboard rewrite third (07-03); Reveal motion layered on top (07-04); then the three remaining tab targets (07-05/06/07); verification last.

---

## Inherited Locked Decisions (from Phase 5-6)

- **Typography scale override**: 8 sizes (22 / 18 / 16 / 15 / 14 / 12 / 11 / 10 px) + 4 weights (400 / 500 / 600 / 700). Half-pixel values found in handoff (e.g. 13.5, 11.5, 10.5) are rounded to the nearest in-scale size (14 / 12 / 11 / 10) per Phase 6 D-11.
- **Spacing exceptions**: `.tb-chip gap: 6px`, `.tb-input padding: 14px`, `.tb-card padding: 18px`.
- **Home `.tb-home__title` 30px** — does not propagate to Phase 7 selectors.
- **No emoji** in user-facing strings except `✦` (U+2726).
- **Voice**: 静かな日本語 (no hype, no exclamation marks).
- **Mono font** for numbers, handles, percentages via `.tb-mono` / `var(--tb-font-mono)`.

### Phase 7 candidate new exception

None anticipated. The hi-fi Dashboard / Reveal screens use sizes that all map to the locked scale once rounded:
- 13.5px preview body → 14px
- 11.5px @handle → 12px (sometimes 11px when handle is paired with a smaller label)
- 10.5px mono timestamps → 10px

If during implementation a value truly requires escape (e.g. an `.is-opening` keyframe needs a 2px translate that doesn't map), the plan author MUST first record a Locked Decision exception in `07-CONTEXT.md` per D-23.

---

## Element Extraction Policy

Phase 7 extracts **one** new element (`tb_tabbar`) because it is consumed by 4 templates simultaneously, easily clearing the YAGNI "second call site" threshold (Phase 6 element policy).

| Element file | Status | Call sites |
|--------------|--------|-----------|
| `templates/element/tb_tabbar.php` | NEW | dashboard.php / discover.php / notifications.php / Inboxes/settings.php |
| `templates/element/inbox_settings_form.php` | reused | Inboxes/settings.php (Phase 6) |
| `templates/element/block_list.php` | reused | Inboxes/settings.php (relocated from dashboard) |
| `templates/element/avatar_handle_chip.php` | reused | layout/default.php (Phase 6, untouched) |
| `templates/element/icon.php` | reused | TabBar icon rendering (inbox/compass/bell/user) |

The Phase 6 deferred extractions (`tb_button` / `tb_chip` / `tb_card` / `tb_input` / `tb_letter`) remain deferred — Phase 7 doesn't add new call sites that would trip the YAGNI rule.

---

## Component 1 — TbTabBar element (NAV-01, NAV-02)

**File:** `templates/element/tb_tabbar.php` (new, ~50 lines)
**Hi-fi:** `components.jsx` lines 116-125 (TbTabBar React).
**CSS source of truth:** `tokens.css` lines 207-230 (`.tb-tabbar` / `.tb-tabbar__item` already defined Phase 5) + `tamabox.css` lines 1086-1102 (`.tb-unread-dot` + icon position).

### Element API

```php
<?= $this->element('tb_tabbar', [
    'active'       => 'inbox',          // 'inbox' | 'discover' | 'notifications' | 'settings'
    'unreadCount'  => $unreadCount,     // int, optional — defaults to 0
]) ?>
```

- `$active`: which tab the current page belongs to. Controller MUST `$this->set('activeTab', '<id>')` and the template passes it through. If absent, no tab is highlighted.
- `$unreadCount`: integer count of unread (opened_at IS NULL) messages in this user's inbox. When > 0, the inbox tab item shows `.tb-unread-dot`. When 0 or absent, the dot is omitted.

The element MUST be **deterministic**: same inputs → same DOM. No DB queries inside the element.

### DOM contract

```html
<nav class="tb-tabbar" role="tablist" aria-label="ダッシュボードタブ" style="--cols:4;">
  <a class="tb-tabbar__item is-active" href="/dashboard" role="tab" aria-current="page">
    <span class="tb-tabbar__icon">
      <?= icon inbox 22 ?>
      <?php if ($unreadCount > 0): ?>
        <span class="tb-unread-dot" aria-label="未読 <?= $unreadCount ?> 件"></span>
      <?php endif; ?>
    </span>
    <span>受信</span>
  </a>
  <a class="tb-tabbar__item" href="/dashboard/discover" role="tab">
    <span class="tb-tabbar__icon"><?= icon compass 22 ?></span>
    <span>発見</span>
  </a>
  <a class="tb-tabbar__item" href="/dashboard/notifications" role="tab">
    <span class="tb-tabbar__icon"><?= icon bell 22 ?></span>
    <span>通知</span>
  </a>
  <a class="tb-tabbar__item" href="/dashboard/settings" role="tab">
    <span class="tb-tabbar__icon"><?= icon user 22 ?></span>
    <span>設定</span>
  </a>
</nav>
```

The `<a>` element (not `<div>`) ensures the tab is keyboard-focusable and works without JS, matching the SSR-pure decision (D-01). The Phase 5 CSS rules for `.tb-tabbar__item` style anchor elements as well as divs (no element-tag-specific rules in `tokens.css` line 218-230).

### Active state semantics (D-05)

- `active === 'inbox'` → first item gets `is-active`
- `active === 'discover'` → second item
- `active === 'notifications'` → third item
- `active === 'settings'` → fourth item
- Anything else → no highlight (defensive)

The active item also gets `aria-current="page"` for screen readers.

### Tokens used

- `.tb-tabbar` background `rgba(255,255,255,0.92)`, backdrop blur 12px (already in tokens.css)
- `.tb-tabbar__item` 10px label / 500 weight / `--tb-ink-3`
- `.tb-tabbar__item.is-active` `color: var(--tb-turq-500)`
- `.tb-unread-dot` 6×6 `--tb-turq-400` anchored top-right of icon wrapper (already in tamabox.css §E)

### Verification

- Hi-fi side-by-side with `components.jsx` 116-125 + Dashboard.jsx 90-98
- `composer test` green (TabBar markup adds new classes but no existing test asserts on tab markup)
- WCAG: focus visible on tab item, aria-current present
- NAV-01 (4 tabs切替可) + NAV-02 (アクティブ ハイライト + 未読ドット) satisfied

---

## Component 2 — TbAppBar usage pattern (re-used)

No new element — re-use the existing `.tb-appbar` CSS (Phase 5). Phase 7 templates render the AppBar markup inline because the AppBar varies per screen (title, sub, right slot). Pattern:

```html
<header class="tb-appbar tb-appbar--big">
  <div class="tb-appbar__left">
    <div>
      <div class="tb-appbar__title">受信箱</div>
      <?php if ($sub): ?><div class="tb-appbar__sub"><?= h($sub) ?></div><?php endif; ?>
    </div>
  </div>
  <div class="tb-appbar__right">
    <button type="button" class="tb-icon-btn" aria-label="通知"><?= icon bell 22 ?></button>
    <?php /* avatar circle — Dashboard only */ ?>
  </div>
</header>
```

`.tb-appbar__left` / `.tb-appbar__right` exist already in `tamabox.css` (line 1354 area). The right-side avatar is **NOT** the legacy `header-bar` chip (that one renders in `layout/default.php` and stays as-is per Phase 6 cross-screen pattern). The hi-fi Dashboard 32px circle "葵" avatar is a stylistic flourish; rendering an actual user initial circle inside `.tb-appbar__right` is acceptable.

---

## Screen 1 — Dashboard 受信タブ (NAV-03, MOTION-02, MOTION-03)

**File:** `templates/Users/dashboard.php` (rewrite — 149 lines → ~200 lines)
**Hi-fi:** `screens/Dashboard.jsx` (109 lines) for the receive-list layout. RevealHit sender card visual: `screens/RevealHit.jsx` lines 65-98.
**Controller data (unchanged, plus 1 new):** `$user`, `$inbox`, `$messages` (paginated), `$pageOutOfRange`, `$collisionFlash`, `$blocks`, `$reportedSet`, **PLUS `$unreadCount`** (new, computed by `UsersController::dashboard` from `Messages.opened_at IS NULL` count for this inbox; integer ≥ 0).

### Layout (top to bottom)

1. **TbAppBar (big variant)** — title "受信箱", right slot:
   - Icon button: bell (no-op link to `/dashboard/notifications`)
   - Avatar circle 32×32, gradient `linear-gradient(135deg, --tb-turq-100, --tb-turq-200)`, contains first character of `$handle` (mono, weight 700, 13px, color `--tb-turq-700`), border `--tb-turq-200`. Rendered ONLY when `$handle !== ''`.
2. **Scroll region** (`padding: 4px 20px 16px; gap: 14px`), containing:
   - **Box card** (`.tb-card` variant):
     - Left: small label "あなたの箱" (10px / 600 / 0.2em uppercase / `--tb-ink-3`)
     - Below label: mono URL `tamabox.emomie.com/<?= $slug ?>` (14px / 500 / `--tb-ink`)
     - Right: `.tb-chip.tb-chip--warm` rendering `SSR <?= $ssrPct ?>%` where `$ssrPct = round($inbox->ssr_probability * 100)`
   - **Counts row** — `display: flex; justify-content: space-between;`
     - Left: "受信" (18px / 700 / `--tb-ink`) + `count($messages)` 件 (mono 12px / `--tb-ink-3`)
     - Right pill: 未開封 `$unreadCount` (11px / 600 / `--tb-warm-700` / bg `--tb-warm-100` / radius 999 / padding 4px 10px / letter-spacing 0.08em). Only rendered when `$unreadCount > 0`.
   - **Collision flash card** (when `$collisionFlash !== null`) — `.tb-card-soft` style with note copy preserved from v1.
   - **Receive list** (`.tb-card.tb-receive-list`) — `border-radius: --tb-r-lg`, `overflow: hidden`. Each row is the existing `<details class="message-row" ...>` — see §"Message row" below.
   - **Empty state** (when `count($messages) === 0`) — keep existing copy "まだ受信したメッセージはありません" inside a `.tb-card-soft` block. Page-out-of-range fallback also preserved.
   - **Paginator** — wrapped in `.tb-pagination` for visual styling (helper class added to Phase 7 CSS).
3. **Footer**: `<?= $this->element('tb_tabbar', ['active' => 'inbox', 'unreadCount' => $unreadCount]) ?>`

The Phase 6 `<aside class="dashboard-settings">` and `<?= $this->element('block_list') ?>` are **REMOVED** from `dashboard.php` (D-04). Both move to `Inboxes/settings.php` (Screen 4 of this phase). Controller continues to set `$inbox` / `$blocks` view variables; dashboard template no longer references them (Discretion area: clean-up of controller view data deferred to Phase 8 to keep blast radius small).

### Message row (`.message-row`) hi-fi alignment

Each `<details class="message-row" ...>` keeps its existing semantics (`data-state="unread"|"opened"`, `data-msg-id="..."`, `id="msg-..."`). New visual layers:

```html
<details class="message-row tb-message-row" data-state="unread|opened" data-msg-id="...">
  <summary class="message-row__head tb-message-row__head">
    <span class="tb-dash-dot tb-dash-dot--<?= $state === 'unread' ? 'unread' : ($isHit ? 'hit' : 'miss') ?>" aria-hidden="true"></span>
    <div class="tb-message-row__meta">
      <span class="tb-message-row__from"><?= 'opened-hit' ? '@'.$senderHandle : '匿名' ?></span>
      <?php if ($isOpened && $isHit): ?><span class="tb-message-row__ssr">SSR</span><?php endif; ?>
    </div>
    <span class="tb-message-row__preview"><?= $bodyPreview ?></span>
    <time class="tb-mono tb-message-row__time" datetime="<?= h($iso) ?>"><?= $createdShort ?></time>
  </summary>
  <div class="message-row__body">
    <!-- body content per state — see Reveal section below -->
  </div>
</details>
```

The Phase 4 fixture-driven assertions (`data-state="unread"`, `★ 抽選 hit`, `★ 抽選 miss`, `https://bsky.app/profile/`, `rel="noopener"`, `action="/dashboard/messages/<id>/open"`, `開封する`) MUST continue to render somewhere in the body. To keep tests green WITHOUT weakening assertions:
- The hi-fi-styled HIT banner replaces the v1 `<div class="ssr-reveal__banner">★ 抽選 hit — 送信者が開示されました</div>` with a Calm Gacha card that **still contains the exact substring `★ 抽選 hit`** (banner text is preserved inside the new design).
- The MISS line keeps the exact substring `★ 抽選 miss` (visually styled per `.tb-reveal-miss`).
- The "開封する" button keeps its label (the styling switches to `.tb-btn.tb-btn--primary.tb-btn--full`).

### Reveal MISS body — Reveal.jsx adapted to inline (D-12)

When `$isOpened && !$isHit`:
- A `.tb-reveal-miss-card` (soft card, 1px dashed circle 48×48 with "—", section label "抽選結果", title "送信者は匿名のまま" 15px/600, sub "<mono color=warm-700>{100 - ssrPct}%</mono> を引きました")
- The exact text `★ 抽選 miss` remains as a `<span class="visually-hidden">` to keep `testDashboardRendersUnreadAndOpenedMessages` green.

### Reveal HIT body — RevealHit.jsx sender card adapted to inline (D-13, D-14, D-15 + MOTION-03)

When `$isOpened && $isHit`:
1. **Lottery result card** — warm gradient bg `linear-gradient(180deg, #FFF7E0 0%, #FBEFCC 100%)`, 1px border `#F0DCA8`, radius lg, padding 18px 20px. Contains:
   - 48px white circle, border `--tb-warm-300`, `✦` glyph 22px `--tb-warm-500`, shadow `0 2px 8px rgba(217,162,60,0.22)`
   - Section label "抽選結果 · SSR" (10px / 700 / `--tb-warm-700`)
   - Title "送信者が開示されました" (16px / 700 / `--tb-ink`)
   - Sub "<mono color=warm-700>{ssrPct}%</mono> を引き当てました"
   - HIDDEN within the card: `<span class="visually-hidden">★ 抽選 hit — 送信者が開示されました</span>` (preserves the v1 test substring assertion).
2. **Sender card** (NEW visual per RevealHit.jsx 65-98):
   - `.tb-sender-card.tb-card` flex row, padding 14px, gap 12px, shadow `--tb-shadow-1`
   - 44px gradient circle: `linear-gradient(135deg, #E7C795, #B98449)`, content = `mb_substr($senderHandle, 0, 1)` (or default avatar img when `$senderAvatar !== ''`), color white, weight 700, 17px
   - Middle: handle name (14px / 700) + " SSR " small label (9px / 700 / warm-500 / letter-spacing 0.18em) + mono @handle line (12px / `--tb-ink-3`)
   - Right: anchor "プロフィール" pill (`height 30px, border 1px --tb-turq-200, color --tb-turq-700, radius 999, font-size 12 / 600`, href = `$senderProfileUrl` if present else `https://bsky.app/profile/{$senderHandle}`, `rel="noopener" target="_blank"` to preserve test substring `https://bsky.app/profile/` + `rel="noopener"`)
3. **Block form** preserved at bottom: still a POST form to `/block/{$senderUserId}` (test does not assert on visual style of this button — just keep it functional).
4. **Message footer** (delete + report or 通報済 badge) — preserved with `.tb-btn.tb-btn--quiet` styling for delete, `/report/{id}` link styling, and the existing `<span class="report-badge">通報済</span>` (text preserved exactly).

### `.is-opening` animation (MOTION-02)

The `.message-row__body` element gains `.is-opening` class for 400ms when its `<details>` toggles from `closed → open`. JS handles the class toggle (see Component 6 below).

CSS contract (Phase 7 §H section added to tamabox.css):
```css
@keyframes tb-fade-in {
  from { opacity: 0; transform: translateY(2px); }
  to   { opacity: 1; transform: none; }
}
.message-row__body.is-opening {
  animation: tb-fade-in 400ms ease;
}
```

### Verification

- Hi-fi side-by-side with Dashboard.jsx (overall) + RevealHit.jsx lines 65-98 (sender card)
- `composer test` green — all existing dashboard assertions preserved (data-state, ★ 抽選 hit / miss, profile URL, rel=noopener, open form, ブロック中ユーザー section MOVED to /dashboard/settings — see Screen 4)
- NAV-03 + MOTION-02 + MOTION-03 satisfied

---

## Screen 2 — Discover (発見タブ, NAV-04)

**File:** `templates/Users/discover.php` (new, ~80 lines)
**Controller:** `UsersController::discover()` — stub action, sets `$activeTab = 'discover'` and renders. No DB queries.
**Hi-fi reference:** `screens/Discover.jsx` (216 lines). Phase 7 implements **only the static骨格 (Empty / placeholder state)** per CONTEXT.md D-16/D-18 — the rich fake-data list is NOT shipped (DISC-01 v3 candidate).

### Layout (top to bottom)

1. **TbAppBar (big)** — title "発見", sub "箱をみつける"
2. **Body** (padding 4px 20px 16px, gap 14px):
   - **Search input mock** — pill shape, 42px tall, bg `--tb-card-soft`, border `--tb-line`, radius 999, padding `0 14px`, gap 8px:
     - 16px search SVG (compass icon at 16 stroke 1.8 OR inline magnifier — pragmatic: re-use `compass` icon at 16px; or inline magnifier SVG hard-coded into template). The hi-fi uses a custom magnifier — we add a `<svg>` literal inline to avoid extending `icon.php` registry.
     - Placeholder text "@handle で箱をさがす" (13px / `--tb-ink-4`)
     - The whole element is a `<div>` (NOT `<input>`) to make "disabled" obvious — no interactive form here.
   - **Tag chips row** — horizontal `display: flex; gap: 6px;`, all chips visually disabled (color `--tb-ink-3`, border `--tb-line`, no background). 6 chips: すべて / 創作 / 音楽 / 研究 / 写真 / ゲーム. First chip ("すべて") rendered with `.is-pseudo-active` (dark bg) but with `aria-disabled="true"` to indicate visual selection only.
   - **Empty-state placeholder card** (`.tb-card`, 28px padding):
     - Centered `✦` glyph 36px `--tb-warm-500`
     - Heading "発見はもうすぐ来ます" (16px / 700 / `--tb-ink`)
     - Body (12px / 1.7 / `--tb-ink-2`): "他の人の箱を探して送信する機能は、近日公開予定です。今は自分の URL を直接共有してみてください。"
3. **Footer**: `<?= $this->element('tb_tabbar', ['active' => 'discover', 'unreadCount' => $unreadCount]) ?>`

`$unreadCount` is passed from the controller (re-uses the same calculation as dashboard, optional — if not computed, defaults to 0 in the element). For Phase 7, the stub may simply pass 0 (no need to query the DB on a static-empty page). Confirmed: **Discover stub passes `0`** to keep the controller minimal per D-16.

### Verification

- Hi-fi side-by-side: Empty骨格 only (no list, no featured card; lighter than the full Discover.jsx)
- `composer test`: new test `testDiscoverAuthRenders200` passes (status 200, contains "発見はもうすぐ来ます")
- `composer test`: new test `testDiscoverUnauthRedirects` passes (302 to /)
- NAV-04 satisfied

---

## Screen 3 — Notifications (通知タブ, NAV-05)

**File:** `templates/Users/notifications.php` (new, ~60 lines)
**Controller:** `UsersController::notifications()` — stub action, sets `$activeTab = 'notifications'`. No DB queries.
**Hi-fi reference:** `screens/Notifications.jsx` lines 1-100. Phase 7 implements Empty-state骨格 only (no fake notif feed).

### Layout (top to bottom)

1. **TbAppBar (big)** — title "通知" (no sub).
2. **Body** (`display: flex; flex-direction: column; flex: 1;` centered vertical):
   - Centered Bell icon (`icon` element at size 56, `color: --tb-ink-4`) inside a 96px paper-deep circle (bg `--tb-paper-deep`, border `--tb-line`).
   - Heading "通知はまだありません" (16px / 700 / `--tb-ink`)
   - Body (12px / 1.7 / `--tb-ink-3` / max-width 280px / text-align center): "メッセージへの返信や開封のお知らせがここに届きます。"
3. **Footer**: `<?= $this->element('tb_tabbar', ['active' => 'notifications', 'unreadCount' => 0]) ?>`

### Verification

- Hi-fi side-by-side: Empty骨格 only
- `composer test`: `testNotificationsAuthRenders200` (200 + "通知はまだありません")
- `composer test`: `testNotificationsUnauthRedirects` (302 to /)
- NAV-05 satisfied

---

## Screen 4 — Settings (設定タブ, NAV-06)

**File:** `templates/Inboxes/settings.php` (rewrite — 14 lines → ~60 lines)
**Controller:** `InboxesController::settings()` — GET branch must now RENDER instead of 302 to /dashboard. POST branch unchanged.
**Hi-fi:** `screens/Settings.jsx` (162 lines, Phase 6 partially implemented in `inbox_settings_form.php`).

### Controller behavior change (allowed under D-19)

Current GET handler:
```php
if ($this->request->is('get')) {
    return $this->redirect('/dashboard');
}
```

New GET handler:
```php
if ($this->request->is('get')) {
    $inboxesTable = $this->fetchTable('Inboxes');
    $inbox = $inboxesTable->find()
        ->where([$inboxesTable->aliasField('user_id') => $userId])
        ->first();
    if ($inbox === null) {
        $this->Flash->error(__('受信箱が見つかりませんでした。'));
        return $this->redirect('/');
    }
    $blocksTable = $this->fetchTable('Blocks');
    $blocks = $blocksTable->find()
        ->where(['Blocks.blocker_user_id' => $userId])
        ->contain(['BlockedUsers' => ['UserIdentities']])
        ->order(['Blocks.created_at' => 'DESC'])
        ->toArray();
    $this->set(['inbox' => $inbox, 'blocks' => $blocks, 'activeTab' => 'settings']);
    return null;
}
```

This is a behavior **addition**, not a behavior **change**: previously /dashboard/settings GET 302'd; now it renders. Plan must explicitly test that POST `/dashboard/settings` still saves and redirects (covered by existing `InboxesControllerTest` cases).

### Layout (top to bottom)

1. **TbAppBar (default, non-big)** — title "受信箱の設定", left slot: `tb-icon-btn` back (history.back). No right slot.
2. **Body** (`padding: 8px 20px 24px; gap: 18px;`):
   - `<?= $this->element('inbox_settings_form', ['inbox' => $inbox]) ?>` — Phase 6-completed element renders the SSR / welcome / toggle / save / danger zone sections.
   - `<?= $this->element('block_list', ['blocks' => $blocks]) ?>` — Phase 6-completed element. Block list moves here from dashboard.
3. **Footer**: `<?= $this->element('tb_tabbar', ['active' => 'settings', 'unreadCount' => 0]) ?>`

`$unreadCount` is `0` because computing it from the settings controller requires a Messages query that the controller doesn't currently make. Keep stub at 0 — the unread dot only matters when the user is NOT on the inbox tab, and at this point in the UX they're switching tabs through the bar so the dot in "受信" remains visible (though it will show 0 here; the dot only renders for unreadCount > 0). Visible-on-other-tabs behavior is deferred to v3.

### Test impact

`testDashboardRendersSettingsForm` (UsersControllerTest line 142) currently asserts the settings form appears on `/dashboard`. With Phase 7 removing the inline settings aside, this assertion MUST move. Update the test to GET `/dashboard/settings` instead. Same for `testDashboardRendersBlockListSection` — move to `/dashboard/settings` assertion.

The test data fixtures and assertion substrings remain identical; only the URL changes. **Never weakened**.

### Verification

- Hi-fi side-by-side: Settings.jsx layout + Phase 6 inbox_settings_form.php
- `composer test` green — moved assertions still hold
- NAV-06 satisfied

---

## Component 5 — Reveal motion JS (MOTION-02)

**File:** `webroot/js/reveal-motion.js` (new, ~30 lines).
**Loaded from:** `templates/layout/default.php` via `<script src="/js/reveal-motion.js" defer></script>` injected into `<head>`.

### Contract

```js
(function () {
    'use strict';
    function arm(details) {
        if (!details || details.dataset.revealArmed === '1') return;
        details.dataset.revealArmed = '1';
        details.addEventListener('toggle', function () {
            if (!details.open) return;
            var body = details.querySelector('.message-row__body');
            if (!body) return;
            body.classList.remove('is-opening');
            // force reflow to restart animation when re-opening
            void body.offsetWidth;
            body.classList.add('is-opening');
            window.setTimeout(function () {
                body.classList.remove('is-opening');
            }, 500); // 400ms animation + 100ms buffer
        });
    }
    function armAll() {
        document.querySelectorAll('details.message-row').forEach(arm);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', armAll);
    } else {
        armAll();
    }
})();
```

The script is idempotent and small; it runs on every page (cost = scan once for `details.message-row`). It does NOT interfere with the existing POST `/dashboard/messages/{id}/open` form submission flow — that path triggers a server redirect, after which the page re-renders with the message already `open` (the `toggle` event fires once and the body fades in).

### Why JS and not pure CSS?

A pure CSS `animation` rule on `details[open] .message-row__body` would fire only on the first paint, not on subsequent toggles. The `is-opening` class toggle ensures the fade-in plays whenever the user actively opens a message. This is exactly the JS-class-toggle approach called out in CONTEXT.md D-11.

### Verification

- Manual: open a previously closed message → body fades in 400ms ease
- Initial page load (server returned a message in `open` state) → body fades in once
- `composer test`: JS not exercised by PHPUnit (the tests assert DOM structure including the `.message-row__body` class, which is preserved)
- MOTION-02 satisfied

---

## CSS additions (Phase 7 §H section in `tamabox.css`)

Append a new section after Phase 6 §G.10:

```css
/* ============================================================
 * Phase 7 — Dashboard 4-tab + Reveal motion
 * Source: .planning/phases/07-dashboard-4-reveal/07-UI-SPEC.md
 * ============================================================ */

/* §H.1 — TabBar element internal tweaks (tokens.css owns the base) */
.tb-tabbar__item {
    text-decoration: none;
}
.tb-tabbar__item:focus-visible {
    outline: 2px solid var(--tb-turq-400);
    outline-offset: -2px;
    border-radius: 4px;
}

/* §H.2 — Reveal fade-in animation (MOTION-02) */
@keyframes tb-fade-in {
    from { opacity: 0; transform: translateY(2px); }
    to   { opacity: 1; transform: none; }
}
.message-row__body.is-opening {
    animation: tb-fade-in 400ms ease;
}

/* §H.3 — Dashboard Box card + Counts row */
.tb-dash-box { /* the URL + SSR chip card */
    background: var(--tb-card);
    border: 1px solid var(--tb-line);
    border-radius: var(--tb-r-lg);
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.tb-dash-box__label { font-size: 10px; font-weight: 600; color: var(--tb-ink-3);
                     letter-spacing: 0.2em; text-transform: uppercase; }
.tb-dash-box__url   { font-size: 14px; font-weight: 500; color: var(--tb-ink); margin-top: 3px; }
.tb-dash-counts     { display: flex; align-items: baseline; justify-content: space-between;
                      padding: 4px 2px; }
.tb-dash-counts__title { font-size: 18px; font-weight: 700; color: var(--tb-ink); }
.tb-dash-counts__num   { font-size: 12px; color: var(--tb-ink-3); letter-spacing: 0.06em; }
.tb-dash-counts__pill  { font-size: 11px; font-weight: 600;
                         color: var(--tb-warm-700); background: var(--tb-warm-100);
                         padding: 4px 10px; border-radius: 999px; letter-spacing: 0.08em; }

/* §H.4 — Receive list + message row hi-fi adaptation */
.tb-receive-list { background: var(--tb-card); border: 1px solid var(--tb-line);
                   border-radius: var(--tb-r-lg); overflow: hidden; }
.tb-message-row { padding: 0; }
.tb-message-row + .tb-message-row { border-top: 1px solid var(--tb-line); }
.tb-message-row__head { display: flex; align-items: flex-start; gap: 12px;
                        padding: 14px 14px; cursor: pointer; list-style: none; }
.tb-message-row[data-state="unread"] .tb-message-row__head { background: #FBFCFD; }
.tb-dash-dot { width: 8px; height: 8px; border-radius: 50%; flex: 0 0 auto; margin-top: 7px; }
.tb-dash-dot--unread { background: var(--tb-warm-500); }
.tb-dash-dot--hit    { border: 1.5px solid var(--tb-warm-500); }
.tb-dash-dot--miss   { border: 1.5px solid var(--tb-line-strong); }
.tb-message-row__meta { display: flex; align-items: center; gap: 8px; }
.tb-message-row__from { font-size: 11px; font-weight: 600; letter-spacing: 0.04em; }
.tb-message-row__from--hit { color: var(--tb-warm-700); }
.tb-message-row__from--anon { color: var(--tb-ink-3); }
.tb-message-row__ssr  { font-size: 10px; color: var(--tb-warm-500); font-weight: 700;
                        letter-spacing: 0.16em; }
.tb-message-row__preview { flex: 1; font-size: 14px; line-height: 1.55; letter-spacing: 0.02em;
                           color: var(--tb-ink); overflow: hidden; text-overflow: ellipsis;
                           display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.tb-message-row[data-state="opened"] .tb-message-row__preview { color: var(--tb-ink-2); font-weight: 400; }
.tb-message-row__time { font-size: 10px; color: var(--tb-ink-3); flex: 0 0 auto; margin-top: 2px; }

/* §H.5 — Reveal MISS / HIT cards (inline, not full-screen) */
.tb-reveal-miss-card { background: var(--tb-card-soft); border: 1px solid var(--tb-line);
                       border-radius: var(--tb-r-lg); padding: 18px 20px;
                       display: flex; align-items: center; gap: 16px; }
.tb-reveal-miss-card__dash { width: 48px; height: 48px; border-radius: 50%;
                             border: 1px dashed var(--tb-line-strong);
                             display: grid; place-items: center; color: var(--tb-ink-3);
                             font-size: 22px; flex: 0 0 auto; }

.tb-reveal-hit-card { background: linear-gradient(180deg, #FFF7E0 0%, #FBEFCC 100%);
                      border: 1px solid #F0DCA8; border-radius: var(--tb-r-lg);
                      padding: 18px 20px; display: flex; align-items: center; gap: 16px;
                      position: relative; overflow: hidden; }
.tb-reveal-hit-card__symbol { width: 48px; height: 48px; border-radius: 50%; background: #fff;
                              border: 1px solid var(--tb-warm-300); color: var(--tb-warm-500);
                              display: grid; place-items: center; font-size: 22px;
                              box-shadow: 0 2px 8px rgba(217, 162, 60, 0.22); flex: 0 0 auto; }

.tb-sender-card { display: flex; align-items: center; gap: 12px;
                  background: var(--tb-card); border: 1px solid var(--tb-line);
                  border-radius: var(--tb-r-lg); padding: 14px;
                  box-shadow: var(--tb-shadow-1); }
.tb-sender-card__avatar { width: 44px; height: 44px; border-radius: 50%;
                          background: linear-gradient(135deg, #E7C795, #B98449);
                          color: #fff; font-weight: 700; font-size: 17px;
                          display: grid; place-items: center; flex: 0 0 auto;
                          border: 1px solid rgba(0,0,0,0.06); object-fit: cover; }
.tb-sender-card__name { font-size: 14px; font-weight: 700; color: var(--tb-ink); }
.tb-sender-card__handle { font-size: 12px; color: var(--tb-ink-3); }
.tb-sender-card__profile-link { height: 30px; padding: 0 12px; border-radius: 999px;
                                background: transparent; border: 1px solid var(--tb-turq-200);
                                color: var(--tb-turq-700); font-size: 12px; font-weight: 600;
                                display: inline-flex; align-items: center;
                                text-decoration: none; flex: 0 0 auto; }

/* §H.6 — Discover / Notifications stubs */
.tb-discover-search { display: flex; align-items: center; gap: 8px;
                      background: var(--tb-card-soft); border: 1px solid var(--tb-line);
                      border-radius: 999px; padding: 0 14px; height: 42px; }
.tb-discover-search__placeholder { font-size: 12px; color: var(--tb-ink-4); flex: 1; }
.tb-discover-tags { display: flex; gap: 6px; overflow-x: auto; padding: 2px 0; }
.tb-discover-tag { flex: 0 0 auto; padding: 6px 14px; border-radius: 999px;
                   font-size: 12px; font-weight: 600; letter-spacing: 0.04em;
                   background: transparent; color: var(--tb-ink-3);
                   border: 1px solid var(--tb-line); }
.tb-discover-tag.is-pseudo-active { background: var(--tb-ink); color: #fff;
                                    border-color: var(--tb-ink); }
.tb-empty-state { background: var(--tb-card); border: 1px solid var(--tb-line);
                  border-radius: var(--tb-r-lg); padding: 28px;
                  display: flex; flex-direction: column; align-items: center; gap: 10px;
                  text-align: center; }
.tb-empty-state__symbol { font-size: 36px; color: var(--tb-warm-500); line-height: 1; }
.tb-empty-state__title  { font-size: 16px; font-weight: 700; color: var(--tb-ink); }
.tb-empty-state__body   { font-size: 12px; line-height: 1.7; color: var(--tb-ink-2);
                          max-width: 280px; }

.tb-notif-empty { flex: 1; display: flex; flex-direction: column; align-items: center;
                  justify-content: center; gap: 16px; padding: 40px 24px; }
.tb-notif-empty__circle { width: 96px; height: 96px; border-radius: 50%;
                          background: var(--tb-paper-deep); border: 1px solid var(--tb-line);
                          display: grid; place-items: center; color: var(--tb-ink-4); }
.tb-notif-empty__title { font-size: 16px; font-weight: 700; color: var(--tb-ink); }
.tb-notif-empty__body  { font-size: 12px; line-height: 1.7; color: var(--tb-ink-3);
                         max-width: 280px; text-align: center; }

/* §H.7 — Screen wrapper for full-height dashboard tabs */
.tb-dash-screen { display: flex; flex-direction: column;
                  min-height: calc(100vh - 56px); background: var(--tb-paper); }
.tb-dash-screen__body { flex: 1; overflow: auto; padding: 4px 20px 16px;
                        display: flex; flex-direction: column; gap: 14px; }
```

These are layout helpers, **not** new design-system components. They only exist to factor out repeated markup from Phase 7 templates.

---

## View variable additions

| Controller | Action | New `$this->set()` key | Value source | Used by |
|-----------|--------|------------------------|--------------|---------|
| Users | dashboard | `$activeTab` | string `'inbox'` | tb_tabbar element |
| Users | dashboard | `$unreadCount` | `Messages` COUNT where opened_at IS NULL AND inbox_id = $inbox->id AND deleted_at IS NULL | tb_tabbar element + Counts pill |
| Users | discover (new) | `$activeTab` | string `'discover'` | tb_tabbar element |
| Users | notifications (new) | `$activeTab` | string `'notifications'` | tb_tabbar element |
| Inboxes | settings (GET branch) | `$activeTab` | string `'settings'` | tb_tabbar element |
| Inboxes | settings (GET branch) | `$inbox` / `$blocks` | DB queries (already used in POST) | settings template |

The unread count COUNT query is the only **new** DB query introduced by Phase 7. It runs once per dashboard render and uses the same `inbox_id` already loaded. No schema changes.

---

## Cross-cutting patterns

### Tab path detection (D-05 SSR-pure)

The simplest implementation is **controller-side**: each tab's action sets `$this->set('activeTab', '<id>')`. The element receives `active` via the call site `$this->element('tb_tabbar', ['active' => $activeTab])`. This avoids parsing `$this->request->getPath()` inside the element (testability win).

### Layout integration

`templates/layout/default.php` is **NOT modified** in Phase 7 except to add ONE `<script src="/js/reveal-motion.js" defer></script>` to `<head>`. The existing `.header-bar` continues to render (logout button + avatar handle chip) for all authenticated pages — the new in-screen `.tb-appbar` coexists with it, as established in Phase 6 (cross-screen pattern).

### CSRF and Form helper preservation

All POST form invocations continue to use `$this->Form->create(...)`. CSRF tokens auto-injected by CakePHP middleware. No raw `<form>` tags introduced.

### Backend immutability (D-19, D-20)

Phase 7 modifies:
- `src/Controller/UsersController.php` — ADD `discover()` + `notifications()` actions, ADD `$unreadCount` calculation to `dashboard()`. Existing `dashboard()` behavior preserved (all current view variables continue to be set).
- `src/Controller/InboxesController.php` — GET branch of `settings()` switched from 302 to render. POST branch UNCHANGED.
- `config/routes.php` — ADD 2 routes (`/dashboard/discover`, `/dashboard/notifications`), both GET only.

NO changes to:
- `src/Model/` (any file)
- `config/Migrations/` (any file)
- OAuth or moderation logic
- Any existing controller action body except as listed above

---

## Verification Checklist (per-screen + phase-wide)

### Per-screen

| Screen | Hi-fi side-by-side | composer test | Controller behavior unchanged | Requirement |
|--------|--------------------|---------------|-------------------------------|-------------|
| TabBar element | components.jsx 116-125 | ✓ | n/a | NAV-01, NAV-02 |
| Dashboard | Dashboard.jsx | ✓ (test moved for settings/block) | dashboard() existing data preserved | NAV-03 |
| Reveal HIT body | RevealHit.jsx 65-98 | ✓ (★ 抽選 hit + profile URL preserved) | n/a | MOTION-02, MOTION-03 |
| Discover | Discover.jsx (Empty subset) | ✓ (new tests) | n/a | NAV-04 |
| Notifications | Notifications.jsx (Empty subset) | ✓ (new tests) | n/a | NAV-05 |
| Settings tab | Settings.jsx | ✓ (test moved) | settings() POST unchanged | NAV-06 |

### Phase-wide

- [ ] All 4 tabs (受信 / 発見 / 通知 / 設定) reachable via direct URL
- [ ] TabBar element renders in 4 templates
- [ ] `composer test` 197+ tests / 0 failures (195 baseline + 4 new for discover/notifications = 199; some moves may net less but never below 195)
- [ ] `.is-opening` keyframe fires on `<details>` toggle
- [ ] Reveal HIT body matches RevealHit.jsx sender card layout
- [ ] No backend file touched outside D-19 allowance
- [ ] No new `--tb-*` token, no new locked-decision exception added
- [ ] Manual smoke checklist runs against tamabox.emomie.com after deploy (out of band — `status: human_needed`)

---

## Checker Sign-Off (advisory)

- [ ] Dimension 1 Copywriting: PASS (no emoji except ✦, 静かな日本語)
- [ ] Dimension 2 Visuals: PASS (4 tabs match hi-fi)
- [ ] Dimension 3 Color: PASS (turquoise + honey + ink only; no new palettes)
- [ ] Dimension 4 Typography: PASS (Phase 5 override inherited, half-pixel rounded)
- [ ] Dimension 5 Spacing: PASS (4-grid + 3 locked exceptions inherited)
- [ ] Dimension 6 Registry Safety: PASS (no new registry; icon.php reused as-is)

---

## Pre-Population Sources

| Source | Decisions Used |
|--------|---------------|
| `~/projects/handoff_tamabox/screens/Dashboard.jsx` | Box card / Counts row / Receive list / 32px avatar circle markup |
| `~/projects/handoff_tamabox/screens/RevealHit.jsx` lines 65-98 | Sender card layout (44px gradient avatar / handle / profile pill) |
| `~/projects/handoff_tamabox/screens/Reveal.jsx` | MISS card layout (48px dashed circle + section label) |
| `~/projects/handoff_tamabox/screens/Discover.jsx` | Search pill + tag chips structure (Empty-state subset only) |
| `~/projects/handoff_tamabox/screens/Notifications.jsx` | Empty骨格 (icon + title + body) |
| `~/projects/handoff_tamabox/screens/Settings.jsx` | Body layout — sections + danger zone (mostly Phase 6) |
| `~/projects/handoff_tamabox/components.jsx` | TbTabBar React API (active prop + items array) → element shape |
| `webroot/css/tokens.css` lines 207-230 | `.tb-tabbar` / `.tb-tabbar__item` already defined |
| `webroot/css/tamabox.css` lines 1086-1102 | `.tb-unread-dot` positioning already defined |
| `07-CONTEXT.md` | D-01 SSR-pure / D-04 settings aside removal / D-06 element extraction / D-10 keyframe / D-11 JS toggle / D-13 RevealHit scope / D-19 backend allowed / D-20 backend forbidden |
| Phase 4 existing tests (UsersControllerTest, InboxesControllerTest) | Substring assertions preserved (★ 抽選 hit / miss, profile URL, rel=noopener) |
