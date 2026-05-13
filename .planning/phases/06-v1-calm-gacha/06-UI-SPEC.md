---
phase: 6
slug: v1-calm-gacha
status: draft
preset: none
created: 2026-05-13
---

# Phase 6 — UI Design Contract: v1 画面の Calm Gacha 化

> Visual and interaction contract for converting 7 v1 PHP templates + 1 element to the Calm Gacha design system established in Phase 5.
> Source of truth: `~/projects/handoff_tamabox/screens/*.jsx` (hi-fi React references) and Phase 5 component CSS (`.tb-*` classes in `webroot/css/tamabox.css`).
> Phase 6 produces NO new components. It consumes the Phase 5 component library and re-renders 8 screens.

---

## Scope

8 PHP templates are rewritten in this phase. Backend (controllers / models / OAuth) is immutable. CakePHP form helpers (`Form->create`, `Form->control`, `Html->link`) continue to be used; visual classes are passed via the helper `class` option.

| # | Plan | UI Req | Target file | Hi-fi reference |
|---|------|--------|-------------|-----------------|
| 1 | 06-01 | UI-08 | `templates/element/avatar_handle_chip.php` | `~/projects/handoff_tamabox/components.jsx` (TbChip §76-82) |
| 2 | 06-02 | UI-01 | `templates/Pages/home.php` | `~/projects/handoff_tamabox/screens/Home.jsx` |
| 3 | 06-03 | UI-03 | `templates/Messages/send_done.php` | `~/projects/handoff_tamabox/screens/Done.jsx` |
| 4 | 06-04 | UI-06 | `templates/Account/delete.php` | `~/projects/handoff_tamabox/screens/ReportDelete.jsx` (Delete §90-163) |
| 5 | 06-05 | UI-05 | `templates/Reports/create.php` | `~/projects/handoff_tamabox/screens/ReportDelete.jsx` (Report §3-87) |
| 6 | 06-06 | UI-04 | `templates/element/inbox_settings_form.php` | `~/projects/handoff_tamabox/screens/Settings.jsx` |
| 7 | 06-07 | UI-07 | `templates/element/block_list.php` | `~/projects/handoff_tamabox/screens/Block.jsx` |
| 8 | 06-08 | UI-02 | `templates/Messages/send.php` | `~/projects/handoff_tamabox/screens/Send.jsx` |

Execution order is locked (CONTEXT.md D-01): shared element first → simpler screens → screens consuming chip/letter last.

---

## Inherited Locked Decisions (from Phase 5)

- **Typography scale override**: 8 sizes (22 / 18 / 16 / 15 / 14 / 12 / 11 / 10 px) and 4 weights (400 / 500 / 600 / 700). Approved verbatim.
- **Spacing exceptions**: `.tb-chip gap: 6px`, `.tb-input padding: 14px`, `.tb-card padding: 18px` — component-internal only.
- **Layout-level spacing**: always multiples of 4 via `--tb-sp-*` (4 / 8 / 12 / 16 / 20 / 24 / 32) or via the legacy `--space-*` aliases that resolve to the same values.
- **No emoji** in any user-facing string except `✦` (U+2726).
- **Voice**: 静かな日本語 (no hype, no exclamation marks).
- **Mono font for numbers, handles, percentages**: use `.tb-mono` class or `font-family: var(--tb-font-mono)`.

---

## Element Extraction Policy

Per CONTEXT.md D-04 / D-05, Phase 5 deferred PHP element extraction is performed in this phase under YAGNI rule (extract on the second call site, not the first).

Anticipated extractions (created when a second call site appears):

| Element file | First use | Second use (triggers extraction) |
|--------------|-----------|----------------------------------|
| `templates/element/tb_chip.php` | avatar_handle_chip (UI-08) | block_list (UI-07) or settings (UI-04) |
| `templates/element/tb_button.php` | home (UI-01) | send_done (UI-03) |
| `templates/element/tb_card.php` | report (UI-05) | delete (UI-06) |
| `templates/element/tb_input.php` | settings (UI-04) | send (UI-02) |
| `templates/element/tb_letter.php` | send (UI-02) | n/a in this phase — keep inline |

Element parameter API (per CONTEXT.md D-06):
```php
$this->element('tb_button', ['variant' => 'primary', 'label' => '送信する', 'url' => $url])
$this->element('tb_chip',   ['tone' => 'warm', 'label' => 'SSR 10%'])
```
Helpers should still pass `aria-*` and CSRF tokens correctly when wrapping form submit buttons.

Existing element files (`avatar_handle_chip.php`, `inbox_settings_form.php`, `block_list.php`) are **rewritten in place** (CONTEXT.md D-07) — signature and callers unchanged.

---

## Screen 1 — AvatarHandleChip (UI-08)

**File:** `templates/element/avatar_handle_chip.php`
**Hi-fi:** `~/projects/handoff_tamabox/components.jsx` lines 76-82 (TbChip), plus existing chip CSS in `tokens.css` lines 184-198 and `tamabox.css` lines 1060-1064.
**Controller data:** `$identity` (Authentication IdentityInterface) → `$originalData->user_identity->handle_cached` + `avatar_url_cached`.

**DOM contract:**
```
.tb-chip.tb-chip--avatar
  ├ img.tb-chip__avatar  (or span fallback with first character)
  └ span.tb-chip__handle  → "@handle"
```

**Tokens used:**
- background `--tb-turq-50` (default chip tone) — inherited from `.tb-chip` base
- text `--tb-turq-700`
- internal gap: 6px (Phase 5 locked exception)
- avatar size: 20px (chip-internal — smaller than the standalone `--avatar-sm: 24px`)
- typography: `--type-meta` (11px / 600) for handle text, JetBrains Mono for the handle string itself

**Verification:**
- Renders inside `.header-bar` without overflow
- Tests `tests/TestCase/Controller/UsersControllerTest.php` still pass (no class assertions on this element)
- Aria label preserved: `role="group" aria-label="ログイン中のユーザー"`

---

## Screen 2 — Home (UI-01)

**File:** `templates/Pages/home.php`
**Hi-fi:** `~/projects/handoff_tamabox/screens/Home.jsx` (89 lines, full file).
**Controller data:** none (static landing page); Form->create renders POST to `/auth/start-bluesky`.

**Layout (top to bottom):**
1. Hero block — centered ✦ glyph + display title "tamabox" + lead copy (送信者が出るかもしれない、匿名メッセージ箱。)
2. Divider with center "HOW" label
3. Three-step ordered list (01 受信箱をつくる / 02 URL をシェアする / 03 稀に、送信者が現れる)
4. Spacer
5. CTA block — primary `.tb-btn--primary.tb-btn--full` "Bluesky ではじめる"
6. Quiet ghost text button "既にアカウントをお持ちの方" (optional — keep as plain link to login flow)

**Components used:** `.tb-btn--primary`, `.tb-btn--full`, `.tb-mono` (for step numbers), `.tb-paper-grain` background.

**Tokens used:**
- Hero ✦: `--tb-warm-500`, 64px
- Title: 30px / 700 / letter-spacing 0.22em (display heading)
- Lead: 13.5px / 400 / line-height 1.85 / color `--tb-ink-2`
- Step number badge: 11px / 500, border `--tb-warm-300`, bg `--tb-warm-100`, color `--tb-warm-700`
- Step title: 15px / 600 / `--tb-ink`
- Step sub: 12px / `--tb-ink-3`
- Divider line: 1px `--tb-line`
- HOW label: 10px / 600 / letter-spacing 0.32em / `--tb-ink-3`
- Page background `--tb-paper`, padding `36px 28px 24px`

**Form behavior preserved:** `Form->create(null, [url => ['controller' => 'Auth', 'action' => 'startBluesky'], type => 'post'])` — CSRF intact.

**Verification:**
- Side-by-side with `Home.jsx`
- POST `/auth/start-bluesky` still works (unchanged controller behavior)
- `composer test` green

---

## Screen 3 — SendDone (UI-03)

**File:** `templates/Messages/send_done.php`
**Hi-fi:** `~/projects/handoff_tamabox/screens/Done.jsx` (67 lines).
**Controller data:** `$inbox->slug` (for the "同じ受信箱に再送する" link).

**Layout:**
1. AppBar (transparent, title empty, right slot: close icon-button)
2. Center column (flex 1)
   - Quiet check icon — 96×96 circle, border `--tb-turq-200`, bg `--tb-turq-50`, color `--tb-turq-500`, IconCheck size 42 sw 1.4
   - Heading "送信しました" — 22px / 700, letter-spacing 0.08em
   - Body — "受け手が開封したとき、抽選次第であなたのアカウントが開示されます。" (use existing copy; hi-fi version uses inbox handle which we don't have on this template)
   - Meta line — mono, color `--tb-ink-3`, letter-spacing 0.18em uppercase, surrounding short divider strokes
3. Bottom action block — primary "同じ受信箱に再送する" + ghost "他の受信箱を見る"

**Components used:** `.tb-appbar.tb-appbar--transparent`, `.tb-icon-btn`, `.tb-btn--primary.tb-btn--full`, `.tb-btn--ghost.tb-btn--full`, `icon` element (check, close).

**Tokens used:**
- Page bg `--tb-paper`
- Check circle border `--tb-turq-200`, bg `--tb-turq-50`, color `--tb-turq-500`
- Heading `--tb-ink`, 22px / 700
- Body `--tb-ink-2`, 13px / 1.9 line-height

**Verification:**
- Side-by-side with `Done.jsx`
- Links resolve to correct slugs
- `composer test` green

---

## Screen 4 — Delete (UI-06)

**File:** `templates/Account/delete.php`
**Hi-fi:** `~/projects/handoff_tamabox/screens/ReportDelete.jsx` lines 90-163 (Delete component).
**Controller data:** none specific; form posts to `/account/delete` with `confirm_delete` checkbox.

**Layout:**
1. AppBar — title "退会", left slot: back icon-button
2. Body (scrollable, padding 12px 20px 16px, gap 18px)
   - Heading block — H2 "tamabox から退会" (22px / 700) + paragraph (13px / 1.8)
   - Consequences card (`.tb-card`) — 3 rows describing what gets deleted (mapping from existing copy: 受信箱 / 送信履歴 / slug 再利用なし)
   - Consent label (`.tb-card-soft`) — checkbox + "上記の内容を理解し、取り消せない ことに同意します。"
3. Sticky CTA block — `.tb-btn--danger.tb-btn--full` "退会する" + `.tb-btn--quiet.tb-btn--full` "キャンセル" (link to `/dashboard`)

**Components used:** `.tb-appbar`, `.tb-icon-btn`, `.tb-card`, `.tb-card-soft`, `.tb-btn--danger`, `.tb-btn--quiet`, icon element (back, check).

**Tokens used:**
- Headline 22px / 700 / `--tb-ink`
- Body 13px / 1.8 / `--tb-ink-2`
- Row title 14px / 600 / `--tb-ink`
- Row sub 12px / `--tb-ink-3`
- Danger circle: bg `--tb-danger-bg`, color `--tb-danger`
- Checkbox tile: bg `--tb-card-soft`, border `--tb-line`
- CTA primary in danger variant uses `.tb-btn--danger` (which already maps to the same red)

**Verification:**
- Side-by-side with `ReportDelete.jsx :: Delete`
- POST `/account/delete` with consent still validates
- `composer test` green

---

## Screen 5 — Report (UI-05)

**File:** `templates/Reports/create.php`
**Hi-fi:** `~/projects/handoff_tamabox/screens/ReportDelete.jsx` lines 3-87 (Report component).
**Controller data:** `$message->body` (200-char excerpt), `$message->id` (form action target).

**Layout:**
1. AppBar — title "メッセージを通報", left slot: close icon-button
2. Body (scrollable, gap 16px)
   - Target message excerpt — soft card (`.tb-card-soft` styling: bg `--tb-card-soft`, border `--tb-line`, radius md, padding 12px 14px)
     - Label "対象メッセージ" (10px / 700 / letter-spacing 0.2em uppercase / `--tb-ink-3`)
     - Italic preview text (13px / `--tb-ink-2` / line-height 1.6)
   - Reasons section — SectionLabel "理由 *" + 4-5 radio rows with `.tb-card-style` selection state
     - The 4 existing reason values: harassment / spam / illegal / other (controller validation expects these IDs; do NOT change the radio values)
     - Each row: padding 12px 14px, border `--tb-line` (selected: `--tb-turq-300` + box-shadow `0 0 0 3px --tb-turq-50`)
     - Custom radio mark — hidden native input + visual circle
   - Detail section — SectionLabel "詳細 任意" + textarea using `.tb-input` class
3. Sticky CTA block — `.tb-btn--quiet.tb-btn--full` "キャンセル" (link to /dashboard) + `.tb-btn--danger.tb-btn--full` "通報を送信する"

**Components used:** `.tb-appbar`, `.tb-icon-btn`, `.tb-card-soft`, `.tb-input`, `.tb-btn--quiet`, `.tb-btn--danger`, icon (close).

**Tokens used:**
- Target excerpt bg `--tb-card-soft`, border `--tb-line`, radius `--tb-r-md`
- Section label 10.5px / 700 / 0.2em uppercase / `--tb-ink-3` (danger flag uses `--tb-danger`)
- Radio row default border `--tb-line`; selected border `--tb-turq-300` + box-shadow `0 0 0 3px --tb-turq-50`
- Radio dot 18px circle, selected fill `--tb-turq-400`

**Class assertion preserved:** Form helper still sets `class="report-form"` (`ReportsControllerTest:75` asserts this) — pass `'class' => 'report-form tb-report-form'` so legacy assertion holds.

**Verification:**
- Side-by-side with `ReportDelete.jsx :: Report`
- POST `/report/{id}` with reason value still validates
- `tests/TestCase/Controller/ReportsControllerTest.php` green

---

## Screen 6 — Settings (UI-04)

**File:** `templates/element/inbox_settings_form.php`
**Hi-fi:** `~/projects/handoff_tamabox/screens/Settings.jsx` (162 lines).
**Controller data:** `$inbox->ssr_probability` (0-1 float), `$inbox->welcome_message`, `$inbox->is_accepting`.

**Note:** This element is consumed by `Users/dashboard.php` and `Inboxes/settings.php`. AppBar is NOT rendered here (parent template owns header). Just the form section.

**Layout (sections separated by gap 18px):**
1. SSR section
   - Section label "SSR 確率" (10.5px / 700 / 0.2em)
   - Card (`.tb-card` styling)
     - Row: label "送信者が開示される確率" + mono percentage display (36px / 700 mono number + 16px % suffix) in `--tb-warm-700`
     - Slider — range input styled with track + filled portion (`--tb-warm-500`) + thumb (white circle, border `--tb-warm-500`)
     - Scale labels 0% / 25 / 50 / 75 / 100 in mono
     - Preset chips row (0% / 5% / 10% / 25% / 50%) — each is a button that sets the value
     - Helper paragraph
2. Welcome message section
   - Section label "ウェルカムメッセージ"
   - `.tb-input` textarea (rows 4, maxlength 1000)
   - Helper text "送信フォームの上部に表示されます" (11px / `--tb-ink-3`)
3. Toggle row (`.tb-card` row layout)
   - "新規メッセージを受け付ける" + subtitle "OFF で受信を停止します"
   - Custom toggle switch (visual styling per JSX lines 134-147) — controlled by hidden checkbox
4. Save button — `.tb-btn--primary` (preserve existing behavior)
5. Danger zone section
   - Section label "危険ゾーン" (color `--tb-danger`)
   - Card containing one DangerRow: "退会する" (link to `/account/delete`) with chevron right icon

**Components used:** `.tb-card`, `.tb-input`, `.tb-btn--primary`, icon element (chevron).

**Tokens used:**
- SSR card padding 20px 18px 18px, radius `--tb-r-lg`
- Mono display 36px / 700 / `--tb-font-mono` / `--tb-warm-700`
- Slider track 4px `--tb-paper-deep`, filled `--tb-warm-500`, thumb white border `--tb-warm-500` + shadow `--tb-shadow-2`
- Preset chip: padding 6px 12px, radius pill, selected bg `--tb-warm-500`, default border `--tb-line`
- Toggle row card padding 14px 16px, radius `--tb-r-lg`
- Toggle pill: 44×26, on bg `--tb-turq-400`, off `--tb-line-strong`, knob 22×22 white shadow-1
- Section label 10.5px / 700 / 0.2em / `--tb-ink-3` (danger: `--tb-danger`)

**Behavior preserved:**
- `prob-range` ↔ `prob-number` sync script (now also updates the visual filled track and thumb position via CSS var, plus the mono percentage display via JS)
- Confirm dialogs at 0% / 100% on submit
- Form action `/dashboard/settings` POST
- All existing form field names: `ssr_probability_pct_range`, `ssr_probability_pct`, `welcome_message`, `is_accepting`

**Verification:**
- Side-by-side with `Settings.jsx`
- Slider sync still works (1 hidden + 1 visible — both linked by JS)
- All existing dashboard tests pass
- `composer test` green

---

## Screen 7 — BlockList (UI-07)

**File:** `templates/element/block_list.php`
**Hi-fi:** `~/projects/handoff_tamabox/screens/Block.jsx` (112 lines, both List and Empty variants).
**Controller data:** `array<Block> $blocks` (each with `blocked_user.user_identity.handle_cached` and `avatar_url_cached`).

**Note:** AppBar is NOT rendered here (parent template — Users/dashboard.php — owns header). This element renders the section heading + note + list.

**Layout:**
1. Section heading "ブロック中ユーザー" (label-style, 10px / 700 / 0.2em uppercase / `--tb-ink-3`)
2. Note card (`.tb-card-soft`) — explainer text "ブロック中のユーザーは…" (only shown when non-empty — for empty state, use a different friendlier hint inline)
3. Either:
   - **Non-empty:** outer card (`.tb-card` with overflow hidden) containing rows
     - Each row: 40px avatar circle (img or fallback letter) + handle column (name 14px/600 + @handle in mono 11px/`--tb-ink-3`) + 解除 button (pill, `.tb-btn--ghost`-style mini variant)
     - Separated by top border `--tb-line` (first row no border)
   - **Empty:** small note "ブロックしているユーザーはいません" (`.tb-card-soft`)

**Components used:** `.tb-card-soft`, `.tb-card`, `.tb-btn--ghost` (mini variant via padding override) or inline button class, icon fallback for avatar.

**Tokens used:**
- Section label 10px / 700 / 0.2em / `--tb-ink-3`
- Card border `--tb-line`, radius `--tb-r-lg`
- Row padding 14px 14px, gap 12px
- Avatar circle 40px, bg `--tb-paper-deep`, border `--tb-line`
- Handle name 14px / 600 / `--tb-ink`
- @handle 11px mono `--tb-ink-3`
- Unblock button: padding 7px 12px, radius pill, border `--tb-line`, color `--tb-ink-2`, 11.5px / 600

**Class assertion preserved:** Outer section retains `class="block-list"` (UsersControllerTest:254). Each row retains `class="block-list__row"` (UsersControllerTest:256) — augment with new tb-* classes.

**Verification:**
- Side-by-side with `Block.jsx`
- Existing test `tests/TestCase/Controller/UsersControllerTest.php` assertions on `block-list` and `block-list__row` still pass
- Unblock form POST still works
- `composer test` green

---

## Screen 8 — Send (UI-02)

**File:** `templates/Messages/send.php`
**Hi-fi:** `~/projects/handoff_tamabox/screens/Send.jsx` (109 lines).
**Controller data:** `$inbox` (with `user.display_name`, `slug`, `welcome_message`, `ssr_probability`, `is_accepting`), `$isAuthenticated`, `$isOwnInbox`, `$isBlocked`, `$restoredBody`.

**Layout (top to bottom):**
1. AppBar — title "メッセージを送る", left: back, right: close
2. Body (scrollable, padding 14px 20px 18px, gap 16px)
   - **Receiver card** (`.tb-card` with display flex) — avatar (gradient circle or img) + display name + slug + SSR chip (`.tb-chip--warm`) showing `SSR {n}%`
   - **Receiver welcome** (only when `$welcomeMessage`) — honey-tinted card (custom inline styles: bg `--tb-warm-100`, border `#F0DCA8`, radius md, padding 12px 14px)
     - Label "受信者から" (10px / 600 / 0.16em uppercase / `--tb-warm-700`)
     - Message body (13.5px / 1.75 / `--tb-ink`)
   - **Inbox self-notice** (when `$isOwnInbox`) — `.tb-card-soft` row with link
   - **Error banner** (when `$isBlocked`) — `.tb-card-soft` with red left border (use existing `.error-banner` class, restyle in CSS section if needed — but the legacy class still aliases)
   - **Textarea section** (only when `$isAccepting && !$isBlocked`)
     - `.tb-label` "メッセージ"
     - `.tb-input` textarea (rows 7, min-height 168px, font-size 15px, line-height 1.8, maxlength 2000)
     - Counter row — left "改行・絵文字 OK" / right `<span class="tb-mono">{n} / 2000</span>` (11px / `--tb-ink-3` / 0.06em)
   - **Consent** (`.tb-card-soft` row with custom checkbox styling)
     - 18px square check (bg `--tb-turq-400`) with white check icon
     - Body text "<b style=color:warm-700>{n}%</b> の確率で、私の Bluesky アカウントが受信者に開示される事に同意します。"
   - **Inbox not accepting** (when `!$isAccepting`) — keep existing copy with `.tb-card-soft` styling, no form
3. Sticky CTA — `.tb-btn--primary.tb-btn--full` with trailing send icon, label "送信する" (or "Bluesky でログインして送信" when unauthenticated)

**Components used:** `.tb-appbar`, `.tb-icon-btn`, `.tb-card`, `.tb-card-soft`, `.tb-input`, `.tb-label`, `.tb-chip--warm`, `.tb-btn--primary`, `.tb-btn--full`, `.tb-mono`, icon element (back, close, send, check).

**Tokens used:**
- Receiver card padding 16px, shadow `--tb-shadow-1`, radius `--tb-r-lg`
- Avatar 56px circle, gradient `linear-gradient(135deg, --tb-turq-100, --tb-turq-200)`, border `--tb-turq-200`
- Name 16px / 700 / `--tb-ink`
- @slug 12px / `--tb-ink-3`
- Welcome card bg `--tb-warm-100`, border `#F0DCA8`
- Consent body 12.5px / `--tb-ink-2` / 1.6
- Sticky CTA top border `--tb-line`, bg `rgba(255,255,255,0.92)`, backdrop-filter blur(10px)

**Form behavior preserved:**
- POST to `/{slug}` with `body` and `consent` fields
- Validation `maxlength="2000"` and `required` on body
- Consent checkbox required
- Char counter live updating (existing data-counter span)
- Disabled-form-when-blocked modifier preserved (Phase 4 D-05/D-06)

**Verification:**
- Side-by-side with `Send.jsx`
- Welcome shown in honey "receiver letter" card (UI-02 acceptance criterion)
- CSRF intact via FormHelper
- Block-disabled state still renders error banner + disabled form
- `composer test` green

---

## Cross-Screen Patterns

### AppBar pattern
Every secondary screen (Send / SendDone / Delete / Report / Settings page / BlockList page) uses `.tb-appbar`. The base layout (`templates/layout/default.php`) currently renders a `.header-bar` for authenticated users. For Phase 6, we keep the existing `.header-bar` in the layout (it's a global app header). The hi-fi screen AppBars are **content-internal AppBars** placed inside each template's content region — they coexist with the global header without conflict (the legacy header is the v1 brand-bar, the in-screen AppBar is the per-screen contextual title).

This avoids touching `layout/default.php` and stays inside the "8 templates" boundary.

### Icon button pattern
```html
<button type="button" class="tb-icon-btn" aria-label="戻る" onclick="history.back()">
  <?= $this->element('icon', ['name' => 'back', 'size' => 22]) ?>
</button>
```
For "close" buttons in modals/screens, use `history.back()` as a sane default (no explicit close target in v1 routing).

### Form helper override pattern
To attach a `.tb-input` class without losing CSRF + validation:
```php
<?= $this->Form->control('field', [
  'type' => 'textarea',
  'label' => false,
  'class' => 'tb-input',
  'maxlength' => 2000,
  'rows' => 7,
]) ?>
```

For raw `<textarea>` already in templates (Send / Settings), just add `class="tb-input"` to the existing element — these are not Form->control invocations.

---

## CSS additions

Phase 6 anticipates appending a `/* ======== Phase 6 — screen-level adjustments ======== */` section to `tamabox.css` for screen-specific layout helpers that are not generic components:

- `.tb-screen` — content wrapper (`display: flex; flex-direction: column; min-height: 100%; background: var(--tb-paper);`)
- `.tb-screen__body` — scrollable middle region (`flex: 1; overflow: auto; padding: ...;`)
- `.tb-screen__cta` — sticky bottom block with top border + frosted bg
- `.tb-section-label` — uppercase 10.5px / 700 label used in Settings / Report / Delete (with `.tb-section-label--danger` for danger zone)
- `.tb-row-card` — flex row card layout used by Settings toggle row and Delete consequences
- `.tb-radio-tile` — radio button "tile" with selection box-shadow (Report)
- `.tb-toggle` — toggle switch (Settings)
- `.tb-slider` + thumb (Settings probability)
- `.tb-step-list` (Home steps)
- `.tb-divider-label` (Home HOW divider)

These are **layout helpers**, not new design-system components — they exist only to factor out repeated markup from individual screens.

---

## Verification Checklist (per-screen + phase-wide)

### Per-screen
For each of UI-01 through UI-08:
- [ ] Side-by-side visual review with hi-fi JSX file
- [ ] All `.tb-*` classes resolve (DevTools Styles panel)
- [ ] Controller view variables consumed correctly (no PHP notices)
- [ ] Existing form POST endpoints still validate and respond
- [ ] No emoji except `✦`
- [ ] No legacy class names introduced in markup beyond what tests require

### Phase-wide
- [ ] All 8 templates rewritten
- [ ] `composer test` shows 195+ tests / 0 failures
- [ ] Manual smoke checklist runs against tamabox.emomie.com after deploy (out of band — `status: human_needed`)
- [ ] No backend file touched (`src/Controller/`, `src/Model/`, `config/Migrations/` diff is empty)
- [ ] No new `--color-*` or `--space-*` token introduced; all new values use `--tb-*` direct or via aliases

---

## Checker Sign-Off (advisory, run at phase end)

- [ ] Dimension 1 Copywriting: PASS (no forbidden patterns; ✦ only)
- [ ] Dimension 2 Visuals: PASS (8 screens match hi-fi)
- [ ] Dimension 3 Color: PASS (turquoise + honey + ink only)
- [ ] Dimension 4 Typography: PASS (Phase 5 override inherited)
- [ ] Dimension 5 Spacing: PASS (4-grid + 3 locked exceptions inherited)
- [ ] Dimension 6 Registry Safety: PASS (no new registry)

---

## Pre-Population Sources

| Source | Decisions Used |
|--------|---------------|
| `~/projects/handoff_tamabox/screens/*.jsx` | All screen layout, DOM structure, inline tokens used |
| `~/projects/handoff_tamabox/components.jsx` | TbChip / TbButton / TbAppBar / TbLetter API shape |
| `.planning/phases/05-design-system-foundation/05-UI-SPEC.md` | Component contract (`.tb-btn`, `.tb-card`, `.tb-letter`, `.tb-chip`, `.tb-input`, `.tb-tabbar`, `.tb-appbar`), typography override, spacing exceptions, copywriting rules |
| `06-CONTEXT.md` | Decision D-01 (order) / D-02 (1 plan = 1 commit) / D-04~D-07 (element extraction) / D-08~D-10 (alias) / D-11~D-13 (hi-fi judgement) / D-14~D-16 (backend immutable) |
| Existing PHP templates | View data shape inferred from `@var` headers |
| Existing test assertions | `block-list`, `block-list__row`, `report-form` class names preserved on outer wrappers |
