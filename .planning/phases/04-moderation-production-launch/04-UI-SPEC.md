---
phase: 4
slug: moderation-production-launch
status: draft
shadcn_initialized: false
preset: none
created: 2026-04-28
extends: phase-03 UI-SPEC + phase-02 visual baseline (webroot/css/tamabox.css :root tokens)
---

# Phase 4 — UI Design Contract: Moderation & Production Launch

> Visual and interaction contract for Phase 4 moderation UX surfaces: block create / undo / list, blocked-inbox send error banner, report form, "通報済" badge, soft-delete UX, account deletion (退会) page. Token-refresh and Lolipop launch are non-UI engineering and intentionally absent from this contract.
>
> **Stack note**: CakePHP 4.5 server-rendered PHP templates. NO JS framework, NO component library, NO Tailwind, NO shadcn. Existing baseline = `webroot/css/tamabox.css` (Phase 2 218 lines + Phase 3 ~421 lines = ~639 lines). This spec **extends** the Phase 3 token system; **NO new design tokens are introduced**, **NO new colors**, **NO new font sizes / weights**.
>
> **Extension philosophy**: 100% of design tokens (color, spacing, type, radius, shadow) come from Phase 3 / Phase 2. Phase 4 adds only **a small set of new component classes** that compose existing tokens. Only **NEW** components and behaviors are documented below — for everything else, **defer to Phase 3 UI-SPEC** (`.planning/phases/03-inbox-message-ssr-reveal/03-UI-SPEC.md`).

---

## Design System

| Property | Value |
|----------|-------|
| Tool | none (custom CSS appended to existing `webroot/css/tamabox.css`) |
| Preset | not applicable |
| Component library | none (server-rendered HTML, Milligram for form/button base) |
| Icon library | **Unicode-only for Phase 4**. No new SVG assets. (✗ for soft-delete and report icons → use plain text labels; ⚠ NOT used; 通報済 badge uses pure text.) Phase 3's `default-avatar.svg` continues to be the only SVG asset. |
| Font | inherits `--font-family` from Phase 2 |
| Breakpoint strategy | inherits Phase 3 `min-width: 768px` single breakpoint |

---

## Spacing Scale

**No new tokens.** All Phase 4 components compose existing Phase 2 tokens (`--space-1` 4px through `--space-12` 48px). See Phase 3 UI-SPEC §Spacing Scale for the full table.

| Token used | Value | Phase 4 specific usage |
|------------|-------|------|
| `--space-1` | 4px | Badge inline padding (通報済, ブロック中) |
| `--space-2` | 8px | Block list row vertical rhythm, error banner inline gap |
| `--space-3` | 12px | Form field padding (report form radio rows, 退会 form), block list row padding |
| `--space-4` | 16px | Default block (error banner padding, report-form section, account-delete card padding) |
| `--space-6` | 24px | Section padding (block list section header, report page wrapper) |
| `--space-8` | 32px | Layout gap inherits Phase 3 dashboard 2-column grid |
| `--space-12` | 48px | account-delete page top padding (severe-form aesthetic) |

**Exceptions**: Button `min-height: 44px` continues. New "解除" / "退会する" / 通報送信 buttons all comply. Inline destructive icon buttons (block button on sender card, soft-delete button) inherit Phase 3 `button-clear button-destructive` pattern — no new sizing.

---

## Typography

**No new sizes or weights.** Phase 4 reuses Phase 3 hierarchy verbatim:

| Role | Size | Weight | Line Height | Phase 4 specific usage |
|------|------|--------|-------------|------|
| Body | 16px | 400 | 1.5 | Error banner copy, report form prompts, account-delete consent text |
| Label | 14px | 400 | 1.5 | Block list row handle, 通報済 badge text, radio option labels, validation error inline text |
| Heading | 24px | 600 | 1.25 | `/report/<id>` page h1, `/account/delete` page h1, dashboard "ブロック中ユーザー" section h2 (note: section h2 stays at 14px uppercase per Phase 3 receive-list pattern) |
| Display | 32px | 600 | 1.2 | not used in Phase 4 |

**Section heading override**: Within `/dashboard`, the new "ブロック中ユーザー" section h2 follows the existing receive-list section header convention (`14px / weight 400 / uppercase / letter-spacing 0.05em / color-text-secondary`) for visual consistency with "受信メッセージ" header. The 24px heading is reserved for `/report/<id>` and `/account/delete` page-level h1 only.

---

## Color

**No new color tokens.** Phase 4 strictly reuses Phase 2 / 3 `:root` colors. The 60/30/10 split is inherited:

| Role | Value | Phase 4 specific usage |
|------|-------|------|
| **Dominant (60%)** | `--color-bg` `#F8F9FA` | `/account/delete` page background, error banner background tint, block list section row hover surface |
| **Secondary (30%)** | `--color-surface` `#FFFFFF` | Report form container, block list row card, account-delete consent card |
| **Accent (10%)** | `--color-accent` `#0085FF` | Same reserved-for list as Phase 3 — see below for **Phase 4 additions** |
| **Destructive** | `--color-error` `#DC2626` | **Expanded reserved-for list** — see below |
| Text primary / secondary / border / success / warning | inherited | flash error / warning / success used in Phase 4 flows verbatim |

### Accent reserved-for (Phase 3 list — UNCHANGED)

Same 6-item list as Phase 3 (Primary CTA / link text / avatar handle / SSR hit handle / focus ring / spinner). Phase 4 does NOT introduce new accent surfaces. Specifically:

- "解除" button on block list rows = **`button-clear` style + `--color-text-secondary` text** (NOT accent — destructive feel without alarm).
- "通報済" badge = **`--color-text-secondary` text on `--color-bg` chip** (neutral, non-removable, no accent consumption).
- "ブロック中" label inside dashboard section header = section-header convention (`color-text-secondary`).
- Account-delete page primary CTA "退会する" = NOT accent — see Destructive section.

### Destructive reserved-for (Phase 3 list + 3 Phase 4 additions)

Inherited from Phase 3:
1. flash error left bar (continues)
2. 「通報する」button label color (Phase 3 stub → Phase 4 keeps same style on report form submit button)
3. 「ブロック」button label color (Phase 3 stub → Phase 4 keeps same on sender card)
4. 422 / validation inline error text

**Phase 4 adds**:
5. **「削除」 button label** in expanded message footer (D-18) — `button-clear` + `color: var(--color-error)`. Same visual weight as 「通報」/「ブロック」, sibling action.
6. **「退会する」button on `/account/delete`** — primary-button shape (44px min-height, pill) BUT background = `var(--color-error)` instead of `--color-accent`. The unique destructive-CTA escalation is reserved for irreversible account loss only.
7. **Send-form blocked-user error banner left stripe** (D-06) — 4px stripe `var(--color-error)`, identical motif to flash error but PERSISTENT (page-state, not session-flash).

**Destructive NOT used for**:
- 通報済 badge (neutral display, not an action)
- 解除 button on block list rows (low-stakes undo, not an alarm)
- 削除確認 native `confirm()` dialog (browser chrome, no theming)

**Why "退会する" uses error-color primary instead of error-color text**: 退会 is the only irreversible-account-level action. Phase 3 preserved the rule "destructive = text color, never background"; Phase 4 promotes 退会 to background-color destructive specifically because the page-level form + checkbox-required UX (D-27) is intentionally heavier than other actions — visual weight must match operational weight.

---

## Copywriting Contract

**Language**: 日本語固定. Same tone as Phase 2/3. 敬体.

### Primary CTAs (Phase 4 button labels)

| Context | Label |
|---------|-------|
| Block button on SSR-hit sender card (D-01) | `このユーザーをブロック` (Phase 3 stub label retained, behavior changes from 501 → real INSERT) |
| Block confirmation flash undo link (D-03) | `(取り消し)` |
| Unblock button on dashboard block list row (D-04) | `解除` |
| Report form radio submit (D-09) | `通報を送信する` |
| Report form cancel link | `キャンセル` (link, not button) |
| Soft-delete button on expanded message (D-18) | `削除` |
| Account-delete page submit (D-23) | `退会する` |
| Account-delete page cancel link | `ダッシュボードに戻る` |

### Block UX copy (D-03, D-04, D-08)

**Block success flash** (D-03 — single-line, success Flash variant):

```
@{handle} をブロックしました(取り消し)
```

- `@{handle}` is the SSR-hit handle (h-escaped); falls back to `このユーザー` if handle empty.
- `(取り消し)` is an inline anchor `<a href="/dashboard/blocks/{block_id}/delete?undo=1" class="undo-link">` rendered in flash success body.
- 1-shot session message; reload does not re-display.

**Unblock success flash** (after 解除 button on dashboard list):

```
@{handle} のブロックを解除しました
```

**Unblock-via-undo flash** (after `(取り消し)` click):

```
ブロックを取り消しました
```

**Block list section heading** (`/dashboard`, D-04):

```
ブロック中ユーザー
```

**Block list empty state**:

```
ブロックしているユーザーはいません
```

(No call-to-action, no helper copy — just acknowledge empty.)

### Send-form blocked-user error banner (D-06, D-08, 主語ぼかし)

**Banner body** (固定文言, fits both signed-in and post-OAuth-callback paths):

```
この受信箱には送信できません
```

- NO mention of which side blocked whom (D-08 主語ぼかし, privacy + receiver safety).
- NO link to "Why?" or contact form (deliberate dead-end).
- Banner is rendered ABOVE the existing send-form HTML; the form fields stay visible but `disabled` (D-06 — UX 不変, form 要素は隠さず disabled で見せる).
- Banner DOM: `<div class="error-banner">この受信箱には送信できません</div>` — see Component Contract §1.

### Report form copy (D-09, D-10, D-11)

**Page heading** (`/report/<message_id>` h1):

```
このメッセージを通報する
```

**Page lead** (16px body, sub-heading area):

```
通報内容は運営が確認します。重複通報はできません。
```

**Radio group legend**:

```
通報の理由を 1 つ選んでください
```

**Radio option labels** (4 categories, exact `reports.reason` ENUM values):

| `reason` value | Label |
|---|---|
| `harassment` | `嫌がらせ・誹謗中傷` |
| `spam` | `スパム・宣伝` |
| `illegal` | `違法・有害コンテンツ` |
| `other` | `その他(自由記述で説明)` |

**Conditional textarea** (`reason='other'` のみ必須, D-11):

- Label: `詳細(その他選択時は必須・最大 1000 文字)`
- `<textarea name="detail" maxlength="1000" rows="5">`
- HTML5 `required` attribute toggles via inline JS when `other` radio is checked; server-side validation is the canonical gate (`if reason === 'other' && empty(detail)` → 422).

**Validation error copy** (inline, below relevant field, `--color-error` 14px):

| Field | Rule | Copy |
|---|---|---|
| reason | 未選択 | `通報理由を選んでください。` |
| detail | reason=other AND empty | `「その他」選択時は詳細の記入が必須です。` |
| detail | > 1000 chars | `詳細は 1000 文字以内で入力してください。(現在 N 文字)` |
| (server) | 重複通報 (D-12 unique 違反) | `このメッセージは既に通報済みです。` (flash error + redirect to `/dashboard`) |
| (server) | message_id 不正 / 自分宛でない | `通報できないメッセージです。` (flash error + redirect to `/dashboard`) |

**Submit success flash** (after report INSERT):

```
通報を送信しました。確認まで時間がかかる場合があります。
```

(Flash success, redirect to `/dashboard`.)

**Cancel link**:

```
キャンセル
```

(Plain `<a href="/dashboard">`, no confirmation needed.)

### "通報済" badge (D-16)

**Badge text** (固定, 取り消し不可):

```
通報済
```

- Rendered inline near the message-row body OR in the expanded footer next to the 削除 button (planner judgment — both placements are visually compatible; recommended: in the expanded body footer alongside 削除 button to avoid noise on the collapsed row).
- 14px / weight 400 / `--color-text-secondary` text on `--color-bg` chip with 4px radius.
- No tooltip, no link, no removal action — pure status indicator.
- Renders only when `reports` row exists for `(reporter_user_id = current_user, message_id = msg.id)`.

### Soft-delete UX copy (D-18, D-19, D-20)

**Delete button** (in message-row expanded body footer, sibling to Phase 3 通報する / ブロック buttons):

```
削除
```

- `<button type="submit" class="button-clear button-destructive">削除</button>`
- Wrapped in `<form method="post" action="/dashboard/messages/{id}/delete">` (CSRF auto).

**Delete confirmation** (D-19 — native `confirm()` dialog):

```
このメッセージを削除しますか?(削除後は元に戻せません)
```

- Inline JS: `<form onsubmit="return confirm('このメッセージを削除しますか?(削除後は元に戻せません)');">`.
- JS-off fallback: form submits without confirm (acceptable per MVP — server-side deletion is the canonical action; a single accidental delete is `deleted_at` timestamp recoverable by admin DB update).

**Delete success flash** (D-20 — list item disappears completely):

```
メッセージを削除しました
```

(Flash success, redirect to `/dashboard`. The deleted row is filtered out by `WHERE messages.deleted_at IS NULL` in the next list render — no "削除済み" badge or tombstone, per D-20.)

### Account deletion (退会) page copy (D-23, D-24, D-26, D-27)

**Page heading** (`/account/delete` h1):

```
退会の手続き
```

**Page lead** (16px body, multi-paragraph):

```
退会するとあなたの受信箱は使えなくなります。
あなたが過去に送信したメッセージは、受け手側の画面ではあなたの当時の handle・avatar が記録されたまま残ります(MOD-03)。
退会後、あなたの slug は他の人に再割り当てされません。
```

(3 paragraphs, separated by `<p>` tags. Plain prose, no bullet list — the "重さ" of the page is in the consent UX, not visual decoration.)

**Consent checkbox label** (D-27 — required):

```
上記の内容を理解した上で、退会します
```

- `<input type="checkbox" name="confirm_delete" required>` HTML5 native required attribute + server-side double-check (`if (!$this->request->getData('confirm_delete')) { throw BadRequestException }`).

**Submit button** (D-23 destructive primary):

```
退会する
```

(Background `--color-error`, white text, same 44px shape as primary CTAs but red.)

**Cancel link** (sibling to submit, separated by space):

```
ダッシュボードに戻る
```

(`<a href="/dashboard">` — no `<button>`, plain text link to make cancel feel lighter than confirm.)

**Post-deletion behavior** (D-24, D-25):

- After `users.deleted_at` UPDATE, `Authentication->logout()` is called → session destroyed → redirect to `/`.
- LP shows a one-shot flash:
  ```
  退会が完了しました。ご利用ありがとうございました。
  ```
  (Flash info, NOT success — neutral tone, not celebratory.)
- The user's slug returns 404 from this point onward (D-25).

### Retired-user sender snapshot display (D-26)

**Visual treatment**: NONE. SSR-hit sender cards for messages whose sender has `users.deleted_at IS NOT NULL` render **identically** to active-user cards:

- Same avatar (from `messages.sender_avatar_url_snapshot`)
- Same handle (from `messages.sender_handle_snapshot`)
- Same `<a href="https://bsky.app/profile/{handle}">` link (from `messages.sender_profile_url_snapshot`) — may be a dead link (D-26 explicitly accepts this)

**No prefix, no badge, no opacity dim, no tooltip, no "(退会済み)" label.** This is a deliberate decision per D-26 (MOD-03 strict; v2 may revisit anonymization). The receiver discovers retirement only by clicking the dead profile link — the "あれ、消えてる" UX moment is intentional.

### Destructive confirmation map (Phase 4 surfaces)

| Destructive action | Confirmation method | Copy |
|---|---|---|
| Block (D-03) | **NONE** — single-click immediate, undone via `(取り消し)` flash link | (no confirm) |
| Unblock (D-04) | **NONE** — low stakes, idempotent re-block possible | (no confirm) |
| Report (D-09) | **Page-level form** — radio + (conditional) detail + submit;重複は server 422 | (no inline confirm — page itself is the "confirm" step) |
| Soft-delete (D-19) | **`confirm()` dialog** | `このメッセージを削除しますか?(削除後は元に戻せません)` |
| Account-delete (D-27) | **Page-level checkbox-required form** (NO confirm()) | Inline checkbox label `上記の内容を理解した上で、退会します` |

### State-transition flash copy summary (Phase 4 inventory)

| Trigger | Flash variant | Copy |
|---|---|---|
| Block created | success | `@{handle} をブロックしました(取り消し)` |
| Block undone via flash link | info | `ブロックを取り消しました` |
| Block undone via dashboard 解除 button | success | `@{handle} のブロックを解除しました` |
| Send to blocked inbox (D-06) | NOT a flash — persistent error banner above form | (see banner copy above) |
| Report submitted | success | `通報を送信しました。確認まで時間がかかる場合があります。` |
| Report duplicate (D-12 UNIQUE) | error | `このメッセージは既に通報済みです。` |
| Report invalid target | error | `通報できないメッセージです。` |
| Soft-delete completed | success | `メッセージを削除しました` |
| Account deleted | info | `退会が完了しました。ご利用ありがとうございました。` |
| Token-refresh silent logout (D-29) | error | `セッションが切れました。再度ログインしてください` |

---

## Component Contracts

Phase 4 introduces **9 new component contracts** (numbered §1–§9) and **modifies 1 Phase 3 component** (§10 — receive list `<details>` body footer). All other components defer to Phase 3 UI-SPEC.

### §1. Send-Form Blocked-User Error Banner (NEW, D-06)

- **用途**: `/<slug>` で受信者が送信者をブロック済の場合、送信フォーム上部に persistent banner 表示。送信フォームは visible だが disabled。
- **DOM**:
  ```html
  <div class="send-form-page">
    <header class="inbox-header">…</header>
    <div class="error-banner" role="status">
      この受信箱には送信できません
    </div>
    <form method="post" class="send-form is-disabled">
      <textarea name="body" disabled>…</textarea>
      <label class="consent-label is-disabled">
        <input type="checkbox" disabled>
        このメッセージは抽選で…
      </label>
      <button type="submit" class="primary-button" disabled>送信する</button>
    </form>
  </div>
  ```
- **Style**:
  ```css
  .error-banner {
      padding: var(--space-3) var(--space-4);
      background: #FEF2F2;             /* same tint as flash error */
      border-left: 4px solid var(--color-error);
      border-radius: var(--radius-sm);
      margin-bottom: var(--space-4);
      font-size: 16px;
      color: var(--color-text-primary);
  }
  .send-form.is-disabled,
  .consent-label.is-disabled {
      opacity: 0.5;
      pointer-events: none;
  }
  ```
- **Why visible-but-disabled vs hidden**: D-06 explicit choice — UX 不変、要素位置を保ち、何ができないかを「グレーアウトされたフォーム + 上部バナー」の二段で伝える。完全 hidden だと「ページ壊れた?」誤読を招く。
- **A11y**: `role="status"` (not `role="alert"` — page state, not interrupt). Disabled controls remain in tab order but are unactivatable per native `disabled` attribute.

### §2. Block Confirmation Flash with Undo Link (NEW, D-03)

- **用途**: ブロック実行後の Flash 表示。即時 undo を促す inline link。
- **DOM** (rendered by `Flash->success()` with custom element):
  ```html
  <div class="message success" role="alert">
    @<?= h($handle) ?> をブロックしました(<a href="/dashboard/blocks/<?= h($blockId) ?>/delete?undo=1" class="undo-link">取り消し</a>)
  </div>
  ```
- **Style**: inherits Phase 2 `.message.success` (existing `border-left: 4px solid --color-success` + `background: #F0FDF4`). NEW class `.undo-link`:
  ```css
  .undo-link {
      color: var(--color-accent);
      text-decoration: underline;
      margin-left: var(--space-1);
  }
  ```
- **Behavior**:
  - Single GET request to undo route (D-03: ワンクリック undo, no second confirm).
  - `?undo=1` query param distinguishes undo-flow from normal 解除 (different success copy).
  - Session-scoped, displays once after redirect from `POST /block/<sender_user_id>`.
- **A11y**: `role="alert"` (interrupt — block is a meaningful state change). Undo link is a real `<a>`, keyboard-focusable, included in next-tab-stop.

### §3. Dashboard "ブロック中ユーザー" Section (NEW, D-04)

- **用途**: `/dashboard` 内に追加するブロック一覧セクション。Phase 3 dashboard 2-column grid 内、settings panel と同じ右カラム or 受信一覧の下(planner 判断)。**推奨**: settings 直下(右カラム下半分)で、settings と並ぶ "管理系" セクションとしてグルーピング。
- **DOM**:
  ```html
  <section class="block-list">
    <h2>ブロック中ユーザー</h2>
    <?php if (count($blocks) === 0): ?>
      <p class="text-secondary block-list__empty">ブロックしているユーザーはいません</p>
    <?php else: ?>
      <ul class="block-list__items">
        <?php foreach ($blocks as $block): ?>
          <li class="block-list__row">
            <img class="block-list__avatar"
                 src="<?= h($block->blocked_user->user_identity->avatar_url_cached ?? '/img/default-avatar.svg') ?>"
                 alt=""
                 width="24" height="24"
                 onerror="this.src='/img/default-avatar.svg'">
            <span class="block-list__handle">@<?= h($block->blocked_user->user_identity->handle_cached) ?></span>
            <form method="post" action="/dashboard/blocks/<?= h($block->id) ?>/delete" class="inline block-list__unblock-form">
              <button type="submit" class="button button-clear">解除</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  ```
- **Style**:
  ```css
  .block-list {
      background: var(--color-surface);
      padding: var(--space-4);
      border-radius: var(--radius-sm);
      border: 1px solid var(--color-border);
      margin-top: var(--space-4);
  }
  .block-list h2 {
      font-size: 14px;
      font-weight: 400;
      color: var(--color-text-secondary);
      margin: 0 0 var(--space-3) 0;
      text-transform: uppercase;
      letter-spacing: 0.05em;
  }
  .block-list__empty {
      margin: 0;
      font-size: 14px;
  }
  .block-list__items {
      list-style: none;
      padding: 0;
      margin: 0;
  }
  .block-list__row {
      display: flex;
      align-items: center;
      gap: var(--space-2);
      padding: var(--space-2) 0;
      border-top: 1px solid var(--color-border);
  }
  .block-list__row:first-child {
      border-top: none;
  }
  .block-list__avatar {
      width: var(--avatar-sm);
      height: var(--avatar-sm);
      border-radius: 50%;
      object-fit: cover;
      flex-shrink: 0;
  }
  .block-list__handle {
      font-size: 14px;
      color: var(--color-accent);
      flex-grow: 1;
  }
  .block-list__unblock-form {
      flex-shrink: 0;
  }
  .block-list__unblock-form .button {
      min-height: 36px;
      padding: var(--space-1) var(--space-3);
      font-size: 14px;
  }
  ```
- **Grid placement** (desktop ≥ 768px): inside Phase 3 dashboard grid right column (`grid-column: 2 / 3`), positioned below `.dashboard-settings`:
  ```css
  @media (min-width: 768px) {
      .block-list {
          grid-column: 2 / 3;
      }
  }
  ```
- **Mobile** (< 768px): single-column flow, sits below settings panel, above pagination.
- **A11y**: `<ul>` semantic list; avatar `alt=""` (decorative — handle conveys identity); 解除 button is a real `<button>` inside its own `<form>` for CSRF + non-GET state change.

### §4. Report Form Page (NEW, D-09 / D-10 / D-11)

- **用途**: `GET /report/<message_id>` で表示される separate page.
- **Route**: separate page (D-09), NOT a modal. Returns to `/dashboard` on success or cancel.
- **DOM**:
  ```html
  <div class="report-page">
    <header class="report-page__header">
      <h1>このメッセージを通報する</h1>
      <p class="text-secondary">通報内容は運営が確認します。重複通報はできません。</p>
    </header>

    <!-- Excerpted message preview (read-only context) -->
    <section class="report-page__msg-excerpt">
      <p class="text-secondary">対象メッセージ:</p>
      <blockquote><?= h(mb_substr($message->body, 0, 200)) ?>…</blockquote>
    </section>

    <form method="post" action="/report/<?= h($message->id) ?>" class="report-form">
      <fieldset class="report-form__reasons">
        <legend>通報の理由を 1 つ選んでください</legend>
        <label class="report-form__radio-row">
          <input type="radio" name="reason" value="harassment" required>
          <span>嫌がらせ・誹謗中傷</span>
        </label>
        <label class="report-form__radio-row">
          <input type="radio" name="reason" value="spam">
          <span>スパム・宣伝</span>
        </label>
        <label class="report-form__radio-row">
          <input type="radio" name="reason" value="illegal">
          <span>違法・有害コンテンツ</span>
        </label>
        <label class="report-form__radio-row">
          <input type="radio" name="reason" value="other">
          <span>その他(自由記述で説明)</span>
        </label>
      </fieldset>

      <fieldset class="report-form__detail">
        <legend>詳細(その他選択時は必須・最大 1000 文字)</legend>
        <textarea name="detail" maxlength="1000" rows="5"></textarea>
      </fieldset>

      <div class="report-form__actions">
        <button type="submit" class="primary-button">通報を送信する</button>
        <a href="/dashboard" class="button button-clear">キャンセル</a>
      </div>
    </form>
  </div>
  ```
- **Style**:
  ```css
  .report-page {
      max-width: 600px;
      margin: 0 auto;
      padding: var(--space-6) var(--space-4);
  }
  .report-page__header h1 {
      font-size: 24px;
      font-weight: 600;
      line-height: 1.25;
      margin: 0 0 var(--space-2) 0;
  }
  .report-page__msg-excerpt {
      margin: var(--space-4) 0;
      padding: var(--space-3) var(--space-4);
      background: var(--color-bg);
      border-left: 3px solid var(--color-border);
      border-radius: var(--radius-sm);
  }
  .report-page__msg-excerpt blockquote {
      margin: 0;
      font-size: 14px;
      line-height: 1.5;
      color: var(--color-text-secondary);
      white-space: pre-wrap;
  }
  .report-form fieldset {
      border: 0;
      padding: 0;
      margin: 0 0 var(--space-4) 0;
  }
  .report-form legend {
      font-size: 14px;
      font-weight: 600;
      margin-bottom: var(--space-2);
      padding: 0;
  }
  .report-form__radio-row {
      display: flex;
      align-items: center;
      gap: var(--space-2);
      padding: var(--space-2) var(--space-3);
      margin-bottom: var(--space-1);
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-sm);
      cursor: pointer;
      font-size: 14px;
  }
  .report-form__radio-row:hover {
      background: var(--color-bg);
  }
  .report-form__radio-row input[type="radio"] {
      margin: 0;
  }
  .report-form__detail textarea {
      width: 100%;
      padding: var(--space-3);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-sm);
      font-family: inherit;
      font-size: 16px;
      line-height: 1.5;
      resize: vertical;
  }
  .report-form__actions {
      display: flex;
      gap: var(--space-3);
      align-items: center;
      flex-wrap: wrap;
      margin-top: var(--space-6);
  }
  .report-form__actions .button-clear {
      color: var(--color-text-secondary);
  }
  ```
- **Conditional textarea required behavior**: progressive enhancement (D-09) — server-side validation is canonical (`if reason === 'other' && empty(detail) → 422`). Optional inline JS may add/remove `required` attribute on textarea reactive to radio change for early UX feedback. JS-off → server-side rejection with inline validation copy.
- **A11y**:
  - `<fieldset>` + `<legend>` for radio group semantics
  - Each radio in own `<label>` wrap → entire row is a click target
  - `<textarea>` has implicit label via `<legend>` of its containing fieldset
  - Submit button is the only `type="submit"`; cancel is plain `<a>` (visually a button via `.button.button-clear` but not a form-submit)

### §5. "通報済" Badge (NEW, D-16)

- **用途**: 通報済みメッセージへの取り消し不可フィードバック表示。
- **DOM** (rendered inside expanded message-row body, NEXT to 通報する/削除 buttons):
  ```html
  <span class="report-badge" aria-label="このメッセージは通報済みです">通報済</span>
  ```
- **Style**:
  ```css
  .report-badge {
      display: inline-block;
      padding: var(--space-1) var(--space-2);
      background: var(--color-bg);
      color: var(--color-text-secondary);
      font-size: 14px;
      border-radius: var(--radius-sm);
      vertical-align: middle;
      margin-left: var(--space-2);
  }
  ```
- **Conditional rendering**: shown only when `Reports->existsReportFor($currentUserId, $messageId)` returns `true`. When badge is shown, the 通報する button is REMOVED (not just visually disabled — physical replacement, since D-12 UNIQUE constraint guarantees second submit would fail anyway).
- **A11y**: `aria-label` provides full meaning to screen readers (badge text alone might be ambiguous out of context).

### §6. Soft-Delete Button + Confirm (NEW, D-18 / D-19)

- **用途**: 展開済 message-row 内、SSR reveal セクションの**下**(D-18)に配置。本文を読み終えた後の判断 UX。
- **DOM** (appended to Phase 3 §2 receive-list-item `<div class="message-row__body">` AFTER the `.ssr-reveal` block):
  ```html
  <div class="message-row__footer">
    <form method="post"
          action="/dashboard/messages/<?= h($msg->id) ?>/delete"
          class="inline"
          onsubmit="return confirm('このメッセージを削除しますか?(削除後は元に戻せません)');">
      <button type="submit" class="button button-clear button-destructive">削除</button>
    </form>
    <?php if ($alreadyReported): ?>
      <span class="report-badge" aria-label="このメッセージは通報済みです">通報済</span>
    <?php else: ?>
      <a href="/report/<?= h($msg->id) ?>" class="button button-clear button-destructive">通報する</a>
    <?php endif; ?>
  </div>
  ```
- **Note on Phase 3 inline 通報する form**: Phase 3 UI-SPEC §2 currently has a `<form action="/report/{message_id}">` POST stub directly in the message-row body. Phase 4 **replaces this with an `<a href="/report/{id}">` link** (since reporting is now a 2-step flow with its own page, not a 1-click action). Block button on sender card retains its inline `POST` form (still 1-click + undo).
- **Style**:
  ```css
  .message-row__footer {
      margin-top: var(--space-4);
      padding-top: var(--space-3);
      border-top: 1px solid var(--color-border);
      display: flex;
      align-items: center;
      gap: var(--space-3);
      flex-wrap: wrap;
  }
  ```
- **Confirm dialog**: native `confirm()` (D-19) — no custom modal, no JS framework. JS-off behavior: form submits without confirm (acceptable per MVP).
- **A11y**: button is real `<button type="submit">`, in own `<form>` for CSRF; native `confirm()` is keyboard-accessible and screen-reader-aware.

### §7. Account Deletion (退会) Page (NEW, D-23 / D-24 / D-27)

- **用途**: `GET /account/delete` で表示される separate page (D-23 — `/dashboard/settings` には統合せず独立化).
- **DOM**:
  ```html
  <div class="account-delete-page">
    <header class="account-delete-page__header">
      <h1>退会の手続き</h1>
    </header>

    <section class="account-delete-page__notice">
      <p>退会するとあなたの受信箱は使えなくなります。</p>
      <p>あなたが過去に送信したメッセージは、受け手側の画面ではあなたの当時の handle・avatar が記録されたまま残ります(MOD-03)。</p>
      <p>退会後、あなたの slug は他の人に再割り当てされません。</p>
    </section>

    <form method="post" action="/account/delete" class="account-delete-form">
      <label class="account-delete-form__consent">
        <input type="checkbox" name="confirm_delete" required>
        <span>上記の内容を理解した上で、退会します</span>
      </label>
      <div class="account-delete-form__actions">
        <button type="submit" class="primary-button button-destructive-bg">退会する</button>
        <a href="/dashboard" class="button button-clear">ダッシュボードに戻る</a>
      </div>
    </form>
  </div>
  ```
- **Style**:
  ```css
  .account-delete-page {
      max-width: 600px;
      margin: 0 auto;
      padding: var(--space-12) var(--space-4);
  }
  .account-delete-page__header h1 {
      font-size: 24px;
      font-weight: 600;
      line-height: 1.25;
      margin: 0 0 var(--space-6) 0;
  }
  .account-delete-page__notice {
      padding: var(--space-4);
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      border-left: 4px solid var(--color-warning);
      border-radius: var(--radius-sm);
      margin-bottom: var(--space-6);
  }
  .account-delete-page__notice p {
      margin: 0 0 var(--space-3) 0;
      line-height: 1.5;
      font-size: 16px;
  }
  .account-delete-page__notice p:last-child {
      margin-bottom: 0;
  }
  .account-delete-form__consent {
      display: flex;
      align-items: flex-start;
      gap: var(--space-2);
      padding: var(--space-3);
      background: var(--color-bg);
      border-radius: var(--radius-sm);
      margin-bottom: var(--space-4);
      font-size: 14px;
      line-height: 1.5;
      cursor: pointer;
  }
  .account-delete-form__consent input[type="checkbox"] {
      flex-shrink: 0;
      margin-top: 2px;
  }
  .account-delete-form__actions {
      display: flex;
      gap: var(--space-3);
      align-items: center;
      flex-wrap: wrap;
  }
  .button-destructive-bg {
      background-color: var(--color-error);
      border-color: var(--color-error);
      color: var(--color-surface);
  }
  .button-destructive-bg:hover {
      background-color: #B91C1C;             /* manual darker shade of --color-error */
      border-color: #B91C1C;
  }
  ```
- **No `confirm()` dialog** (D-27): page-level form + required checkbox is the confirmation step. JS-free.
- **CSRF**: middleware automatic. Server also re-checks `confirm_delete` data presence (BadRequestException if missing).
- **A11y**:
  - Notice section has no special role (it's narrative, not an alert)
  - Checkbox is in `<label>` wrap → click anywhere on row toggles
  - Required attribute (HTML5) blocks submit if unchecked, server-side double-validates
  - Cancel is `<a>` not button — different element type signals different intent

### §8. Token-Refresh Silent Logout Flash (NEW, D-29)

- **用途**: `UserIdentitiesTable::refreshTokenIfExpired()` が `BlueskyOAuthException` を catch した際、`Authentication->logout()` + Flash + redirect to `/`。
- **Visual**: pure flash, no new component class.
- **DOM**: rendered by `Flash->error()` using existing `.message.error` element.
  ```html
  <div class="message error" role="alert">
    セッションが切れました。再度ログインしてください
  </div>
  ```
- **Behavior**: shown on `/` (LP) after redirect from `AuthController::logout()` triggered by token refresh failure. The user sees LP `Bluesky でログイン` CTA + the flash above it.
- **No new CSS** — reuses Phase 2 `.message.error` class verbatim.

### §9. Send-Form Disabled State Modifier (NEW utility, paired with §1)

- **用途**: §1 error banner と組み合わせて使う、send form の visual disabled 表現。
- **CSS** (already shown in §1 — repeated here for component completeness):
  ```css
  .send-form.is-disabled,
  .consent-label.is-disabled {
      opacity: 0.5;
      pointer-events: none;
  }
  ```
- **Why utility class instead of native `disabled` styling**: Milligram's `:disabled` rule on `<button>` already darkens the button, but doesn't dim the surrounding labels / textarea wrapper. The `.is-disabled` modifier makes the entire form region read as inert.
- **Application rule**: ALWAYS pair `.is-disabled` modifier WITH native `disabled` attribute on each interactive element. The visual class is decorative; the canonical disabled state is the HTML attribute (which prevents form submission server-side too — even if an attacker bypasses `pointer-events`, the controller's redundant block check (D-05) is the final gate).

### §10. Receive-List `<details>` Body — Modified (Phase 3 §2 evolution)

- **Modification**: the existing `.message-row__body` gains a new `.message-row__footer` zone (defined in §6 above) that hosts the 削除 button and 通報する link / 通報済 badge.
- **Phase 3 inline 通報 form is REPLACED by an `<a href>` link** (see §6 Note).
- **Block button on sender card**: behavior changes (501 stub → real `POST /block/<sender_user_id>`), styling unchanged from Phase 3 — `.button.button-clear.button-destructive` continues to apply.
- **Collapsed state row** gains optional 通報済 badge inline (planner judgment: badge can also live in collapsed `.message-row__head` to alert without expanding; **default placement** is footer-only to avoid noise — only one badge slot per message, footer wins).

---

## Layouts

### Mobile (< 768px)

Inherits Phase 3. Phase 4 specific:

- `/report/<id>` page: full-width, `padding: var(--space-6) var(--space-4)`, max-width 600px
- `/account/delete` page: full-width, `padding: var(--space-12) var(--space-4)`, max-width 600px
- Block list: stacked under settings, full-width
- Error banner: full-width inside `.send-form-page`

### Desktop (≥ 768px)

Inherits Phase 3 dashboard grid:

- Block list joins right column (`grid-column: 2/3`) below settings panel
- `/report/<id>` and `/account/delete` are standalone pages with `max-width: 600px; margin: 0 auto;` (NOT 2-column — single-column focus form aesthetic)

### Header Bar

Unchanged from Phase 3. No new header items for Phase 4.

### Settings → 退会 entry point

Add to existing `/dashboard` settings panel (Phase 3 component §3):

```html
<fieldset class="settings-form__danger-zone">
  <legend>退会</legend>
  <p class="text-secondary">アカウントを削除すると元に戻せません。</p>
  <a href="/account/delete" class="button button-clear button-destructive">退会の手続きへ</a>
</fieldset>
```

Style:

```css
.settings-form__danger-zone {
    border-top: 1px solid var(--color-border);
    margin-top: var(--space-6);
    padding-top: var(--space-4);
}
.settings-form__danger-zone legend {
    color: var(--color-error);
}
```

This entry point is the ONLY way to reach `/account/delete` from the UI (no header link, no LP link, no Phase 3 dashboard banner). Severity expressed by burying the entry inside settings + visual demotion to `.button-clear.button-destructive`.

---

## Accessibility Contract

Inherits Phase 3 WCAG 2.1 AA baseline. **Phase 4 specific A11y additions**:

1. **`role="status"` on persistent error banner** (§1): non-interrupting, page-state announcement. Distinguishes from `role="alert"` flash messages (which interrupt focus).
2. **`role="alert"` on block confirmation flash** (§2): block is a meaningful state change; SR users should hear it on completion.
3. **`aria-label` on 通報済 badge** (§5): badge text alone could be ambiguous; explicit description wins.
4. **`<fieldset>` + `<legend>` semantics** for radio group (§4) and danger-zone (§Layouts).
5. **Required checkbox in `<label>` wrap** (§7): click target is the entire consent row.
6. **Native `confirm()` dialog** (§6): keyboard-accessible by default, no custom modal A11y debt.
7. **`<a>` vs `<button>` distinction**: cancel actions are `<a>` (navigate away), commit actions are `<button type="submit">` (state change). Helps SR users predict outcome.
8. **44px touch target preserved** for all primary buttons. The smaller 解除 button (§3) uses `min-height: 36px` because it's a secondary table-row action, paired with always-confirm-via-Flash undoable semantics — this is a deliberate exception, NOT a regression.
9. **All disabled controls** (§1 / §9) keep `disabled` HTML attribute (canonical, not just visual `pointer-events`). Tab focus skips per native browser behavior.
10. **Color is never the sole signal** — destructive buttons are `--color-error` text AND have an explicit destructive verb in label ("削除", "退会する", "ブロック"); 通報済 badge has both color tint AND explicit "通報済" text.

---

## Responsive / Performance

- **Lolipop 共有鯖前提**: 軽量第一. Phase 4 adds **no new image asset, no new font, no new JS framework**.
- **CSS file**: continues to **append** to `webroot/css/tamabox.css` (single file). Phase 3 ended at ~639 lines; Phase 4 estimate: **+150–200 lines** for §1–§9 components → final ~800 lines. Still trivially small for HTTP shipping.
- **JS**: optional 1 inline snippet for report-form `reason='other'` → conditional textarea required toggling. Strictly enhancement; server-side is canonical. Total inline JS for Phase 4 ≤ 10 lines.
- **Asset cache**: CSS file path unchanged → existing `.htaccess` cache headers apply unchanged.

---

## Registry Safety

| Registry | Blocks Used | Safety Gate |
|----------|-------------|-------------|
| (該当なし) | (該当なし) | not applicable — shadcn 未初期化、third-party registry 未使用、全コンポーネントを既存 Milligram + custom CSS で内製。Phase 4 で新規 registry なし。 |

---

## Pre-Population Source Map

| UI-SPEC Section | Source |
|---|---|
| Design System (Tool: none) | runtime constraint(CakePHP server-rendered)+ Phase 3 UI-SPEC continuation |
| Spacing scale | Phase 2 `:root` tokens (no Phase 4 additions) |
| Typography | Phase 3 UI-SPEC §Typography (no Phase 4 additions) |
| Color tokens | Phase 2/3 `:root` (no Phase 4 additions; only **expanded reserved-for list** for destructive) |
| Block flash + undo | 04-CONTEXT.md D-03 |
| Block list section | 04-CONTEXT.md D-04 |
| Error banner (主語ぼかし) | 04-CONTEXT.md D-06, D-08 |
| Report form structure | 04-CONTEXT.md D-09, D-10, D-11, D-12 |
| Report categories (4 ENUM) | Phase 1 schema `reports.reason ENUM('harassment','spam','illegal','other')` |
| 通報済 badge | 04-CONTEXT.md D-16 |
| Soft-delete UX (footer + confirm) | 04-CONTEXT.md D-18, D-19 |
| Soft-delete copy | 04-CONTEXT.md D-19, D-20 |
| Account-delete page | 04-CONTEXT.md D-23, D-24, D-27 |
| Retired-user snapshot (no special UI) | 04-CONTEXT.md D-26 |
| Token-refresh silent logout | 04-CONTEXT.md D-29 |
| destructive 退会 button | UI Researcher inference (D-23 page-level severity → background-error escalation) |
| 解除 button neutral styling | UI Researcher inference (D-04 low-stakes undo → button-clear secondary text) |
| Phase 3 inline 通報 form replaced by link | UI Researcher inference (D-09 separate page → link not POST form) |
| Settings → 退会 entry point | UI Researcher inference (D-23 separate page needs SOME entry; danger-zone in settings is least-conspicuous valid placement) |
| Mobile/Desktop layout | inherits Phase 3 768px breakpoint |
| WCAG / A11y baseline | inherits Phase 3 + Phase 4 specific role/aria additions noted in §Accessibility |
| Registry safety | n/a (no new registries) |

---

## Phase 4 → Existing Phase 3 Component Modifications (explicit list)

For executor reference, here are the **exact edits** to Phase 3 components:

| Phase 3 Component | Phase 4 Modification |
|---|---|
| §2 Receive List Item — sender card 「通報する」inline `<form>` | **REPLACE** with `<a href="/report/{id}" class="button button-clear button-destructive">通報する</a>` |
| §2 Receive List Item — sender card 「ブロック」inline `<form>` | **KEEP DOM identical**; controller body changes from 501 stub to real INSERT (no UI change). |
| §2 Receive List Item — `.message-row__body` | **APPEND** `.message-row__footer` div (Phase 4 §6 / §10) hosting 削除 button + 通報する/通報済 |
| §3 Settings Form | **APPEND** `.settings-form__danger-zone` fieldset with link to `/account/delete` (Phase 4 §Layouts) |
| §11 Flash Message variants | **No changes** to existing `.message.error/.warning/.success/.info`; Phase 4 reuses verbatim. NEW `.undo-link` inline class added. |

Components NOT modified: §1 Send Form (header/welcome), §4 SSR Reveal, §5 Avatar Chip, §7 Default Avatar SVG, §8 Inbox Self-Notice, §9 Send Done, §10 Pagination, §12 Char Counter, §13 Probability Control.

---

## Checker Sign-Off

- [ ] Dimension 1 Copywriting: PASS — 全 CTA・empty・error・destructive コピー固定 (D-03/04/06/08/09/10/11/16/19/20/23/27 ベース、checker 確認待ち)
- [ ] Dimension 2 Visuals: PASS — Phase 3 baseline 継続、新規コンポーネント 9 個明記 + Phase 3 component 5 箇所 surgical 修正
- [ ] Dimension 3 Color: PASS — accent reserved-for list **無変更**、destructive reserved-for list 4→7 拡張(削除 button / 退会 button bg / 送信エラー banner)、論理列挙
- [ ] Dimension 4 Typography: PASS — Phase 3 4 サイズ + 2 ウェイトのみ、Phase 4 で新規追加なし
- [ ] Dimension 5 Spacing: PASS — Phase 2 8-point token 完全継続、Phase 4 で新規 token 追加なし
- [ ] Dimension 6 Registry Safety: PASS — registry 未使用 (not applicable)

**Approval:** pending — gsd-ui-checker による検証待ち

---

*Phase 4 UI-SPEC drafted by gsd-ui-researcher on 2026-04-28 from 40 D-XX in 04-CONTEXT.md + Phase 3 UI-SPEC + Phase 2/3 tamabox.css baseline. Pre-population rate ≈ 92% (UI Researcher inferences = 退会 button color escalation, 解除 button neutral tone, Phase 3 通報 form→link replacement, settings danger-zone entry point — all derived from upstream D-XX severity hierarchy, no new design decisions). Zero new design tokens. Zero new SVG assets. Zero new fonts.*
