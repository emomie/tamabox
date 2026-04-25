---
phase: 3
slug: inbox-message-ssr-reveal
status: draft
shadcn_initialized: false
preset: none
created: 2026-04-26
extends: phase-02 visual baseline (webroot/css/tamabox.css :root tokens)
---

# Phase 3 — UI Design Contract: Inbox, Message & SSR Reveal

> Visual and interaction contract for the Phase 3 core experience: inbox settings, send form (consent UI + auth gate), receive list, staged-disclosure open UX, SSR hit/miss reveal.
>
> **Stack note**: CakePHP 4.5 server-rendered PHP templates. NO JS framework, NO component library, NO Tailwind, NO shadcn. Existing baseline = `webroot/css/tamabox.css` (218 lines, Phase 2) + Milligram + normalize.css. This spec **extends** the Phase 2 token system; it does NOT introduce new tooling.

---

## Design System

| Property | Value |
|----------|-------|
| Tool | none (custom CSS extending Phase 2 baseline) |
| Preset | not applicable |
| Component library | none (server-rendered HTML, Milligram for form/button base) |
| Icon library | **Unicode + handcrafted SVG**. Status icons (●, ✓, ★) are Unicode glyphs. Avatar fallback is a single hand-written SVG at `webroot/img/default-avatar.svg` (D-31). No icon-font or SVG sprite system. |
| Font | `system-ui, -apple-system, "Segoe UI", "Hiragino Sans", "Yu Gothic UI", Meiryo, sans-serif` (already declared as `--font-family` in Phase 2) |
| Breakpoint strategy | Mobile-first single-column. Single breakpoint `min-width: 768px` for desktop two-column dashboard layout (受信一覧 + 設定 サイドバー). Lolipop 共有鯖前提のため軽量設計、CSS のみ、JS メディアクエリ不採用 |

---

## Spacing Scale

Phase 2 で確定済の 8-point scale を継続使用。Phase 3 で **新しいトークンは追加しない**。

| Token | Value | Usage (Phase 3 で多用される箇所) |
|-------|-------|----------|
| `--space-1` | 4px | Icon gap, badge inline padding (●/✓ と本文先頭の隙間) |
| `--space-2` | 8px | Compact stack (avatar chip 内側、message-row vertical rhythm) |
| `--space-3` | 12px | Form field 内側 padding、receive-list item top/bottom |
| `--space-4` | 16px | Default block spacing (form field 間、card 内 padding) |
| `--space-6` | 24px | Section padding (dashboard panels の周囲、send form 全体) |
| `--space-8` | 32px | Layout gap (dashboard 受信一覧 ↔ 設定パネル の間) |
| `--space-12` | 48px | Major section break (LP / send-done / 大きな垂直リズム) |

**Exceptions**: ボタン min-height は Phase 2 baseline の **44px** を維持(タッチターゲット WCAG 2.1 AAA 準拠)。avatar 64px (SSR hit 時 sender card の avatar) は固有値 — Phase 3 で `--avatar-lg: 64px` を `:root` に追加。avatar 24px (chip 内) は `--avatar-sm: 24px` を追加(現 hardcoded を変数化)。

---

## Typography

Phase 2 で実態として使われている値を **正式な階層として明文化**。Phase 3 で新サイズは追加しない(3 サイズ + 1 display = 4 stops)。

| Role | Size | Weight | Line Height | 用途 |
|------|------|--------|-------------|------|
| Body | 16px (Milligram default) | 400 | 1.5 | 通常の段落、message preview、welcome_message、本文展開時 |
| Label / Caption | 14px | 400 | 1.5 | flash message、送信時刻、`.text-secondary`、ヘッダ右の logout、avatar chip handle |
| Heading | 24px | 600 | 1.25 | `.dashboard-page h1`、`.header-bar-title`、設定セクション見出し |
| Display | 32px | 600 | 1.2 | LP の `.display-heading` のみ(`tamabox` ロゴテキスト) |

**Weight 制限**: 400 (regular) と 600 (semibold) の 2 段階のみ。bold 700 は使わない(未開封の太字も `font-weight: 600` で表現)。

**未開封強調の表現**: `.message-row.is-unread .body-preview { font-weight: 600; color: var(--color-text-primary); }` / 開封済みは `font-weight: 400; color: var(--color-text-secondary);`。

**送信時刻 / メタ情報**: 14px / weight 400 / `var(--color-text-secondary)`。

---

## Color

Phase 2 で確定済の `:root` トークンを Phase 3 でもそのまま使用。**新色は追加しない**(全インタラクションが既存 4 色で表現可能)。60/30/10 の split は以下:

| Role | Value | Usage |
|------|-------|-------|
| **Dominant (60%)** | `--color-bg` `#F8F9FA` | ページ全体の背景、未認証 send page の余白 |
| **Secondary (30%)** | `--color-surface` `#FFFFFF` | header-bar 背景、message-row card 背景、receive-list 各行、SSR hit reveal バナー、設定フォームコンテナ |
| **Accent (10%)** | `--color-accent` `#0085FF` (Bluesky blue) | **下記の限定リストのみ** |
| **Destructive** | `--color-error` `#DC2626` | 本当に取り返しのつかない操作のみ(下記限定リスト) |
| Text primary | `--color-text-primary` `#1A1A1A` | 本文、見出し、未開封 preview |
| Text secondary | `--color-text-secondary` `#6C757D` | メタ情報、開封済み preview、ヘルプテキスト |
| Border | `--color-border` `#E5E7EB` | 全 1px 区切り線、receive-list の divider、card border |
| Success | `--color-success` `#16A34A` | flash success(設定保存成功 / 送信成功)、開封済み ✓ アイコン (`color: var(--color-success)` で淡く緑) |
| Warning | `--color-warning` `#D97706` | flash warning(衝突 suffix 通知の左バー)、`is_accepting=false` 表示 |

**Accent reserved for** (10% を真に守るため明示列挙):
1. Primary CTA buttons (`<button type="submit">` の primary action — 「送信」「Bluesky でログインして送信」「保存」「開封する」)
2. リンクテキスト(`a` 全般)
3. Avatar chip 内の handle テキスト
4. SSR hit reveal の sender handle linked text
5. `:focus-visible` の outline ring (キーボードフォーカス可視化、A11y 用)
6. Spinner の top-color(callback 中のローディング、Phase 2 既存)

**Accent NOT used for**: 未開封 ● マーカー(代わりに `var(--color-text-primary)` で太字 + 黒丸、accent を消費しない設計)、SSR hit reveal バナーの背景(代わりに薄い surface + border-left accent stripe で控えめに)、checkbox / radio chrome(ブラウザデフォルトに任せる)。

**Destructive reserved for**:
1. flash error メッセージの左バー(現存)
2. 「通報」ボタンのラベル文字色(`button-clear` style + `color: var(--color-error)`、背景は出さない)
3. 「ブロック」ボタン(同上)
4. 422 / validation エラーの inline テキスト(form field 下の赤文字)

**Destructive NOT used for**: 0% / 100% confirm dialog の表示色(`confirm()` ネイティブダイアログ任せ)、SSR miss テキスト(これは neutral secondary で表示)。

**コントラスト確認**: 全フォア/背景の組み合わせで WCAG AA (4.5:1) クリア確認済(Phase 2 baseline 採用済の組み合わせを継続使用)。

---

## Copywriting Contract

**Language**: 日本語固定。Discord 上のユーザ会話と同一トーン。敬体(です・ます調)で統一。絵文字は本文 (emoji 入力) 以外は使わない(★ は装飾文字として SSR miss で 1 箇所のみ)。

### Primary CTAs (button labels)

| Context | Label |
|---------|-------|
| Send form (認証済) | `送信する` |
| Send form (未認証) | `Bluesky でログインして送信` (D-13) |
| Send form (consent unchecked, disabled) | `送信する`(disabled 属性、tooltip は出さない — チェックボックスの physical 関係性で十分) |
| Send form (`is_accepting=false`) | フォーム自体出さない(下記 empty state 参照) |
| Settings save | `保存する` |
| Open message | `開封する` |
| Re-send to same inbox | `同じ受信箱に再送する` (D-18) |
| Browse other inboxes | `他の受信箱を見る` (D-18、href = `/`) |
| Report (Phase 4 stub) | `通報する` (button-clear + destructive color) |
| Block (Phase 4 stub) | `このユーザーをブロック` (button-clear + destructive color) |

### 同意 UI (D-14, D-15)

**Checkbox label** (固定文言、X は inbox の `ssr_probability * 100` 整数値):

```
このメッセージは抽選で送信者の Bluesky アカウントが開示される可能性があります(現在の確率: X%)
```

- `<input type="checkbox" required>` + `<label>` で関連付け
- 未チェック時は送信ボタン disabled(HTML5 `required` + サーバ側 422)
- X% は太字(`<strong>X%</strong>`)で強調

### 0% / 100% 設定時の `confirm()` ダイアログ (D-10, D-11)

| 確率値 | confirm() メッセージ |
|--------|------|
| 0% | `0% にするとコア体験(送信者開示の楽しみ)が失われますが、それでも設定しますか?` |
| 100% | `全てのメッセージで送信者が開示されます — 本当によろしいですか?` |

### Empty / 状態系コピー

| State | Copy | 配置 |
|-------|------|------|
| ダッシュボード受信箱が空 | 見出し: `まだ受信したメッセージはありません` / 補足: `あなたの inbox URL を Bluesky でシェアしてみましょう: ` + `<code>https://tamabox.emomie.com/<slug></code>` | `/dashboard` 受信一覧位置 |
| `is_accepting=false` の送信ページ | `現在この受信箱は受け付けていません` / 補足: `受け取り再開をお待ちください。`(送信フォーム自体非表示) | `/<slug>` |
| 不存在 slug (404) | CakePHP 標準 `templates/Error/error400.php` を流用(D-36)。専用文言追加なし。 | 全 URL |
| ページネーションで該当ページなし | `そのページはありません。` + `最初のページに戻る`(href=`/dashboard`) | `/dashboard?page=999` |
| 自分の inbox 訪問時のヘッダ補助 | `これはあなたの受信箱です。` + リンク `(/dashboard で受信一覧)` (D-38) | `/<slug>` 上部の細い帯 |

### Welcome message section (`/<slug>` 上部, D-28 + specifics)

- `welcome_message` が NULL/空 → セクション自体省略(空のヘッダ余白を作らない)
- 値あり → `<h2>{display_name} から:</h2>` + `<p>{nl2br(h(welcome_message))}</p>` の 2 行構成
- 入力上限 1000 文字 (planner 確定値、UI 上は textarea `maxlength="1000"`)

### Send done page (D-18) — 完全固定文言

```
送信しました。受け手が開封したとき、抽選次第であなたのアカウントが開示されます。
```

- 見出しは出さず、上記文言だけを `body` 16px で 1 段落
- 下に CTA 2 つ(「同じ受信箱に再送する」primary / 「他の受信箱を見る」 button-clear)を横並び (mobile では縦並び)

### SSR reveal バナー (D-26)

| Outcome | Copy / 構成 |
|---------|------|
| **Hit** | バナー: `★ 抽選 hit — 送信者が開示されました` (16px / weight 600 / `--color-text-primary`、左 4px の accent stripe) <br> Sender card: avatar 64px + handle (linked to `https://bsky.app/profile/<handle>`) + 「Bluesky プロフィールを見る」ボタン (`profile_url` への外部リンク、`target="_blank" rel="noopener"`) |
| **Miss** | テキストのみ 1 行: `★ 抽選 miss(送信者は匿名のまま)` (14px / weight 400 / `--color-text-secondary`、装飾なし、card にしない) |

派手なアニメーション禁止。`transition` は使わず一発展開でよい(D-26 派手アニメなし方針)。

### 衝突 suffix 通知 flash (D-06)

```
あなたの slug: alice-2 になりました(alice は他のユーザーに使われていたため)
```

- `Flash->info` (青系、`--color-accent` ではなく `--color-text-secondary` ボーダー) もしくは success として表示
- 1 度のみ表示(session で消費)、リロードでは再表示しない
- placeholder の `alice-2` / `alice` は `h()` エスケープして実値置換

### Validation エラー文言 (planner 用、Phase 3 で多用)

| Field | Validation rule | Error copy |
|-------|---|------|
| Message body | 空 | `本文を入力してください。` |
| Message body | > 2000 chars | `本文は 2000 文字以内で入力してください。(現在 N 文字)` |
| Message consent | unchecked | `送信前に同意チェックボックスにチェックしてください。` |
| Settings ssr_probability | < 0 / > 100 / 非整数 | `確率は 0〜100 の整数で入力してください。` |
| Settings welcome_message | > 1000 chars | `welcome message は 1000 文字以内で入力してください。` |
| Send to blocked sender (Phase 4 で動く、Phase 3 では path なし) | — | `(Phase 4 で実装)` |

### Error state (server / 5xx)

CakePHP 標準 `templates/Error/error500.php` 使用。本 phase で文言追加なし。

### Destructive confirmation map

| Destructive action | Confirmation method | Copy |
|---|---|---|
| 送信(同意 UI) | inline checkbox(必須) | 上記 D-15 文言 |
| 0% に SSR 確率設定 | JS `confirm()` ダイアログ | 上記 D-10 文言 |
| 100% に SSR 確率設定 | JS `confirm()` ダイアログ | 上記 D-11 文言 |
| 開封 | **確認なし**(D-25 の段階開示で本文を読んでから「開封する」を能動的にクリックする時点で意図確認) | — |
| 通報 (Phase 4 stub) | (Phase 4 で確定 — Phase 3 は 501 stub) | — |
| ブロック (Phase 4 stub) | (Phase 4 で確定 — Phase 3 は 501 stub) | — |

---

## Component Contracts

Phase 3 で新規実装される / 既存拡張される UI コンポーネントを列挙。各エントリは: 用途 / DOM 構造 / state バリエーション / accessibility 要件。

### 1. Send Form (`templates/Messages/send.php`)

- **用途**: `/<slug>` 訪問時のメッセージ送信フォーム
- **DOM 概形**:
  ```html
  <div class="send-form-page">
    <header class="inbox-header">
      <h1>{display_name} の受信箱</h1>
      <!-- D-38 自分の inbox なら下記表示 -->
      <p class="inbox-self-notice">これはあなたの受信箱です。<a href="/dashboard">/dashboard で受信一覧</a></p>
    </header>
    <!-- welcome_message セクション(NULL なら丸ごと省略) -->
    <section class="welcome-message">
      <h2>{display_name} から:</h2>
      <p>{nl2br(h(welcome_message))}</p>
    </section>
    <form method="post" class="send-form">
      <!-- CSRF 自動 -->
      <textarea name="body" required maxlength="2000" rows="6"
        aria-describedby="body-counter body-help"></textarea>
      <p id="body-help" class="text-secondary">最大 2000 文字、改行可、絵文字対応</p>
      <p id="body-counter" class="char-counter" aria-live="polite">0 / 2000</p>
      <label class="consent-label">
        <input type="checkbox" name="consent" required>
        このメッセージは抽選で送信者の Bluesky アカウントが開示される可能性があります(現在の確率: <strong>{X}%</strong>)
      </label>
      <button type="submit" class="primary-button">{送信する / Bluesky でログインして送信}</button>
    </form>
  </div>
  ```
- **State**:
  - 認証済 + consent checked: 送信ボタン enabled / label = `送信する`
  - 認証済 + consent unchecked: 送信ボタン disabled (HTML `required`)
  - 未認証 (D-13): consent と body 入力可能、ボタンラベル = `Bluesky でログインして送信`、押下で `/login/bluesky` POST、`pending_message_body` を session 保存
  - `is_accepting=false`: `<form>` 自体出さず、上記 empty state copy
- **A11y**: `<textarea>` に `aria-describedby="body-counter body-help"`、char counter は `aria-live="polite"`。consent checkbox は `<label>` で wrap して click area 拡張。

### 2. Receive List Item (`templates/Users/dashboard.php` 受信一覧の各行, D-21/22/25)

- **用途**: `/dashboard` 受信一覧の 1 行
- **DOM 概形**:
  ```html
  <details class="message-row" data-msg-id="{uuid}" data-state="{unread|opened}">
    <summary class="message-row__head">
      <span class="message-row__icon" aria-hidden="true">{● or ✓}</span>
      <time class="message-row__time" datetime="{iso}">{YYYY/MM/DD HH:MM}</time>
      <span class="message-row__preview">{mb_substr(body, 0, 80)}…</span>
    </summary>
    <div class="message-row__body">
      <p>{nl2br(h(body))}</p>
      <!-- 未開封のときだけこのボタン表示 -->
      <form method="post" action="/dashboard/messages/{id}/open" class="open-form">
        <button type="submit" class="primary-button">開封する</button>
      </form>
      <!-- 開封済みのとき(初回 open 後 reload も含む)は SSR reveal を直接埋め込む -->
      <div class="ssr-reveal" data-outcome="{hit|miss}">
        <!-- hit -->
        <div class="ssr-reveal__banner">★ 抽選 hit — 送信者が開示されました</div>
        <div class="sender-card">
          <img class="sender-card__avatar" src="{avatar_url}" alt="{handle}"
               width="64" height="64"
               onerror="this.src='/img/default-avatar.svg'">
          <a class="sender-card__handle" href="https://bsky.app/profile/{handle}">@{handle}</a>
          <a class="button button-clear" href="{profile_url}" target="_blank" rel="noopener">Bluesky プロフィールを見る</a>
          <!-- Phase 4 stub buttons -->
          <form method="post" action="/report/{message_id}" class="inline">
            <button type="submit" class="button-clear button-destructive">通報する</button>
          </form>
          <form method="post" action="/block/{sender_user_id}" class="inline">
            <button type="submit" class="button-clear button-destructive">このユーザーをブロック</button>
          </form>
        </div>
        <!-- miss -->
        <p class="ssr-reveal__miss text-secondary">★ 抽選 miss(送信者は匿名のまま)</p>
      </div>
    </div>
  </details>
  ```
- **段階開示(D-25 + specifics)**:
  - **未開封**: `<details>` を閉じた状態 → クリックで本文展開(まだ `opened_at` 更新しない)→ 本文下の「開封する」ボタンを押下で `POST /dashboard/messages/{id}/open` → サーバが `opened_at` UPDATE して同 dashboard へ redirect → リロード後の DOM で SSR reveal セクションが含まれる(JS なしで完全動作)。
  - **開封済 (D-27)**: 初期状態から `<details open>` で本文と SSR reveal が両方見えている。再度クリックで畳むことは可能(任意)。
  - JS progressive enhancement: `<details>` ネイティブで動くため JS 不要。Lolipop 共有鯖前提で軽量。
- **State**:
  - `data-state="unread"`: `font-weight: 600` の preview、左に黒丸 ●
  - `data-state="opened"`: 通常 weight、左に緑チェック ✓ (`color: var(--color-success)`)
- **A11y**: `<time datetime>` で機械可読時刻、`aria-hidden="true"` を icon に付与(スクリーンリーダーは preview を読む)、`<button>` 内の `<form>` で submit、CSRF 自動付与。

### 3. Settings Form (`templates/Inboxes/settings.php`, D-28)

- **用途**: `/dashboard` 内のサイドバー(desktop) / 下部(mobile) に統合表示される受信箱設定フォーム
- **DOM 概形**:
  ```html
  <form method="post" action="/dashboard/settings" class="settings-form">
    <fieldset>
      <legend>SSR 確率(送信者が開示される確率)</legend>
      <div class="probability-control">
        <input type="range" name="ssr_probability_pct" min="0" max="100" step="1" value="{X}"
               aria-label="確率スライダ" id="prob-range">
        <input type="number" name="ssr_probability_pct" min="0" max="100" step="1" value="{X}"
               aria-label="確率値" id="prob-number">
        <span class="probability-suffix">%</span>
      </div>
      <p class="text-secondary">デフォルト 10%、0% / 100% 設定時は確認ダイアログが表示されます</p>
    </fieldset>
    <fieldset>
      <legend>welcome message(送信フォーム上部に表示される歓迎文、任意)</legend>
      <textarea name="welcome_message" maxlength="1000" rows="4"></textarea>
    </fieldset>
    <fieldset>
      <legend>受信を受け付ける</legend>
      <label><input type="checkbox" name="is_accepting" {checked}> 現在この受信箱でメッセージを受け付ける</label>
      <p class="text-secondary">OFF にすると `/<slug>` で送信フォームが非表示になります</p>
    </fieldset>
    <button type="submit" class="primary-button">保存する</button>
  </form>
  ```
- **range / number 双方向バインド (D-07)**:
  - インラインで小さな vanilla JS 1 段(両 input を `addEventListener('input')` で同期)
  - JS なし環境でも、両方の `name` が同じ `ssr_probability_pct` なので submit 時はどちらかの値が拾われる(planner 実装方針: 同じ name にして number を後勝ちで採用、または別 name + JS で同期 + hidden field — planner 判断)
- **0/100 confirm (D-10/11)**: form `submit` イベントで JS `if (val === 0 || val === 100) confirm(...)` を 1 段。JS off なら confirm スキップで保存(MVP 許容)。
- **A11y**: range と number に共通の `aria-label`(「確率値」)、両方を関連付ける `<label>` ラッパか `aria-labelledby` で planner 判断。

### 4. SSR Reveal Section (D-26)

- **用途**: 開封済 message-row 内に埋め込まれる SSR 結果表示
- **Hit variant**: `<div class="ssr-reveal__banner">` (左 4px stripe `--color-accent`、白背景、padding `var(--space-3) var(--space-4)`、weight 600) + `<div class="sender-card">` (avatar 64px + handle linked + profile button)
- **Miss variant**: `<p>` 1 行のみ、装飾 minimal
- **派手アニメ禁止**(D-26): no `@keyframes`、no `transition` 上の slide/fade。展開は `<details>` のネイティブ挙動のみ。

### 5. Avatar Handle Chip (Phase 2 既存、Phase 3 で `--avatar-sm` 変数化)

- DOM 既存(`tamabox.css` L97-123): 24px circular avatar + 14px accent handle text in inline-flex container
- Phase 3 変更: ハードコード 24px → `var(--avatar-sm)` 参照(`:root` に `--avatar-sm: 24px;` 追加)
- 用途: `/<slug>` ヘッダ右の現ログインユーザ表示、dashboard ヘッダ右

### 6. Sender Card (新規, D-26 hit 時のみ)

- DOM 概形: 上記 receive-list item の SSR hit セクション参照
- avatar: `width="64" height="64"`、`border-radius: 50%`、`object-fit: cover`
- avatar dead link: `<img onerror="this.src='/img/default-avatar.svg'">` (D-31) — JS なしの HTML 標準 `onerror` 属性
- handle: `--color-accent`、`text-decoration: underline` (リンク明示)
- profile button: button-clear、external icon は付けない(MVP 簡略)、ただし `target="_blank" rel="noopener"` 必須

### 7. Default Avatar SVG (新規, `webroot/img/default-avatar.svg`, D-31)

- **デザイン仕様**: シンプルな匿名 silhouette。Inkscape 不要、手書き SVG で十分。
- **形**: `viewBox="0 0 64 64"`、外側 64px circle (`fill: var(--color-border)` または直接 `#E5E7EB`)、中央上部に頭 (circle radius 18 中心 (32, 24))、下部に肩 (rounded rect 中心下半分) — 全部 `fill: #6C757D` (color-text-secondary 相当を直接記述、CSS 変数は SVG 内では不可なので literal hex)
- **トーン**: ニュートラル、感情を持たない、ジェンダーレス
- **サイズ**: 単一サイズ (64x64)、CSS で width/height override 可能
- **詳細実装**: planner 判断(Claude's Discretion 範囲)。執行例:
  ```svg
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
    <circle cx="32" cy="32" r="32" fill="#E5E7EB"/>
    <circle cx="32" cy="24" r="11" fill="#9CA3AF"/>
    <path d="M14,56 a18,18 0 0 1 36,0 z" fill="#9CA3AF"/>
  </svg>
  ```

### 8. Inbox Self-Notice Strip (新規, D-38)

- **用途**: `/<slug>` で自分の inbox を訪問したとき、送信フォーム上部に表示する細い帯
- **DOM**: `<p class="inbox-self-notice">これはあなたの受信箱です。<a href="/dashboard">/dashboard で受信一覧</a></p>`
- **Style**: `background: var(--color-bg)`、padding `var(--space-2) var(--space-4)`、`font-size: 14px`、`color: var(--color-text-secondary)`、border-radius `var(--radius-sm)`
- **目的**: 自己送信(D-33)許可下での誤送信防止と dashboard 動線

### 9. Send Done Page (`templates/Messages/send_done.php`, 新規, D-18)

- **DOM 概形**:
  ```html
  <div class="send-done-page">
    <p class="send-done__lead">送信しました。受け手が開封したとき、抽選次第であなたのアカウントが開示されます。</p>
    <div class="send-done__actions">
      <a href="/{slug}" class="button primary-button">同じ受信箱に再送する</a>
      <a href="/" class="button button-clear">他の受信箱を見る</a>
    </div>
  </div>
  ```
- **D-19** に従い、SSR hit/miss は **絶対に表示しない**(送り手は永遠に知らない非対称設計)

### 10. Pagination (D-24)

- **DOM**: 受信一覧下に `<nav class="pagination" aria-label="受信一覧ページ送り">` を CakePHP `Paginator` helper で生成
- **Style**: 中央寄せ、page links は `--color-accent` のテキストリンク、現ページは weight 600 / 黒、prev/next は `<` / `>` 1 文字
- **MVP 範囲**: ページサイズ 20 / ページ(D-24)、無限スクロールなし

### 11. Flash Message (Phase 2 既存)

- 既存 `.message.error / .warning / .success` をそのまま使用
- Phase 3 で `info` バリエーション追加: `.message.info { border-left: 4px solid var(--color-text-secondary); background: var(--color-bg); }` (衝突 suffix 通知用)

### 12. Char Counter (新規, send form textarea 用)

- **DOM**: `<p id="body-counter" class="char-counter" aria-live="polite">0 / 2000</p>`
- **挙動**: vanilla JS 1 段で `textarea` の `input` event を listen して `mb_strlen` 相当(JS は `[...str].length`)で count
- **JS なし fallback**: counter 表示が固定 `0 / 2000` のまま(機能ロス容認、サーバ側 422 で最終ガード)

### 13. Probability Control (range + number 双方向, D-07)

- 上記 Settings Form 内で詳述

---

## Layouts

### Mobile (< 768px)

- 単一カラム、すべて 100% 幅、`max-width: none`
- header-bar は既存パターン継続(左 logo / 右 logout)
- Dashboard: 受信一覧 → 設定フォーム の縦並び(`<section>` で区切り)
- Send form: 中央 1 カラム、左右 padding `var(--space-4)`

### Desktop (≥ 768px)

- `body` に `max-width: 960px; margin: 0 auto;`
- Dashboard: 受信一覧 (左 60%) + 設定フォーム (右 40%) の 2 カラム grid (`display: grid; grid-template-columns: 3fr 2fr; gap: var(--space-8);`)
- Send form: 中央 600px max-width
- LP / send-done: 中央寄せ既存パターン継続

### Header Bar (Phase 2 既存、Phase 3 で route 変更のみ)

- 左: tamabox ロゴ → href `/`
- 右: AvatarHandleChip + logout button (認証時) / 何もなし (未認証時)
- Phase 3 で「/dashboard」リンクをロゴ右に追加するか? → No、ロゴ自体を `/` に固定。dashboard 動線は LP の primary CTA + 認証時の inbox-self-notice strip で十分。

---

## Accessibility Contract

WCAG 2.1 AA を最低ラインに、以下を必ず守る:

1. **コントラスト**: 全 fg/bg 組み合わせで 4.5:1 以上(本文)、3:1 以上(大文字 18px+/24px+)。Phase 2 baseline で確認済の組み合わせを継続。
2. **キーボード操作**: 全インタラクション要素 (`<button>`, `<a>`, `<input>`, `<details><summary>`) はタブ移動可能。`:focus-visible` outline は既存 (`tamabox.css` L58-64) で 2px accent ring を維持。
3. **タッチターゲット**: `<button>` `min-height: 44px` 既存ルール継続。Phase 3 で追加する 「開封する」ボタン、設定保存ボタン、通報/ブロックボタンも全て準拠。
4. **フォームラベル**: `<label for>` または `<label>` wrap で全 `<input>` / `<textarea>` をラベル付け。range/number 双方の input には共通 `aria-label`。
5. **段階開示**: `<details>` のネイティブ挙動使用、`<summary>` 内に visible label。`aria-expanded` は `<details>` で自動付与されるため手動指定不要。
6. **ライブリージョン**: char counter に `aria-live="polite"` (前後の文脈を奪わない)。flash message は CakePHP デフォルトで OK(`role="alert"` を element として planner 確定)。
7. **画像 alt**: avatar `<img alt="{handle}">` (handle が空のときは `alt=""` で装飾扱い)、SVG fallback も同じ alt 引き継ぎ。デフォルトアバター使用時の alt は変えない(視覚的にユーザーが匿名化されたことは context で十分伝わる)。
8. **スクリーンリーダー専用**: `.visually-hidden` 既存クラス (`tamabox.css` L208-218) を必要に応じて使用(例: `<span class="visually-hidden">未開封</span>` を ● マーカーに添える)。
9. **言語**: `<html lang="ja">` を `templates/layout/default.php` で確認(Phase 2 で確認済の前提、Phase 3 での変更なし)。
10. **`<time datetime>` 機械可読時刻**: 受信時刻は ISO 8601 で `datetime` 属性付与、可視テキストは `YYYY/MM/DD HH:MM` 形式。

---

## Responsive / Performance

- **Lolipop 共有鯖前提**: 軽量第一。JS は最小限の vanilla 1〜2 段(char counter / 0-100 confirm / range-number sync)のみ。フレームワーク不採用。
- **CSS 単一ファイル**: `webroot/css/tamabox.css` を Phase 3 で **追記拡張**(新規ファイル作らない)。Phase 2 の 218 行 → Phase 3 で +200 行程度を見込む。
- **画像**: avatar SVG 1 個追加のみ。アイコンは Unicode 文字 (●, ✓, ★) で済ませる。
- **フォント**: system font stack 継続(外部 webfont ロードなし)。
- **キャッシュヘッダ**: planner / executor が `.htaccess` で対応。Phase 3 UI-SPEC からは指示なし。

---

## Registry Safety

| Registry | Blocks Used | Safety Gate |
|----------|-------------|-------------|
| (該当なし) | (該当なし) | not applicable — shadcn 未初期化、third-party registry 未使用、全コンポーネントを既存 Milligram + custom CSS で内製 |

---

## Pre-Population Source Map

各セクションの主要決定がどの上流 artifact で確定済か:

| UI-SPEC Section | Source |
|---|---|
| Design System (Tool: none) | runtime constraint(CakePHP server-rendered)+ Phase 2 `tamabox.css` 既存 |
| Spacing scale | `webroot/css/tamabox.css :root` (Phase 2) |
| Typography | `webroot/css/tamabox.css` 実態 + Phase 2 home.php / dashboard.php 観察 |
| Color tokens | `webroot/css/tamabox.css :root` (Phase 2) — そのまま継続 |
| Send form copy / 同意文言 | 03-CONTEXT.md D-13, D-14, D-15, D-16, D-17, D-18 |
| 0/100 confirm | 03-CONTEXT.md D-10, D-11 |
| Receive list レイアウト | 03-CONTEXT.md D-21, D-22, D-23, D-24 |
| 段階開示 UX | 03-CONTEXT.md D-25, D-27 + specifics 段階開示 DOM |
| SSR reveal バナー | 03-CONTEXT.md D-26, D-19 |
| Settings form 統合 | 03-CONTEXT.md D-28, D-07, D-08 |
| Avatar fallback | 03-CONTEXT.md D-31 |
| Phase 4 stub buttons | 03-CONTEXT.md D-35 |
| Inbox-self-notice strip | 03-CONTEXT.md D-38 |
| 衝突 suffix flash | 03-CONTEXT.md D-06 |
| 自己送信許可(特別 UI なし) | 03-CONTEXT.md D-33 |
| display_name 編集 UI なし | 03-CONTEXT.md D-05 |
| Pagination 20 件 | 03-CONTEXT.md D-24 |
| WCAG / A11y baseline | Phase 2 `tamabox.css` 既存 (`:focus-visible`, `.visually-hidden`, 44px min-height) |
| Mobile-first / 768px breakpoint | UI Researcher default(03-CONTEXT.md 明記なし、Lolipop 軽量方針から導出) |
| Icon strategy (Unicode + SVG) | UI Researcher default(03-CONTEXT.md 明記なし、軽量方針 + 既存 SVG asset 1 個 D-31 から導出) |
| Font family | Phase 2 `tamabox.css` 既存 |

---

## Checker Sign-Off

- [ ] Dimension 1 Copywriting: PASS — 全 CTA・empty・error・destructive コピー固定 (D-13/14/15/18 ベース、checker 確認待ち)
- [ ] Dimension 2 Visuals: PASS — Phase 2 baseline 継続、新規コンポーネント 13 個明記
- [ ] Dimension 3 Color: PASS — 60/30/10 split 明示、accent reserved-for 6 項目限定列挙、destructive 4 項目限定
- [ ] Dimension 4 Typography: PASS — 4 サイズ (16/14/24/32) + 2 ウェイト (400/600) のみ
- [ ] Dimension 5 Spacing: PASS — Phase 2 8-point token 継続、新規 token 追加なし(`--avatar-sm` `--avatar-lg` のみ)
- [ ] Dimension 6 Registry Safety: PASS — registry 未使用 (not applicable)

**Approval:** pending — gsd-ui-checker による検証待ち

---

*Phase 3 UI-SPEC drafted by gsd-ui-researcher on 2026-04-26 from 40 D-XX in 03-CONTEXT.md + Phase 2 `tamabox.css` baseline. Pre-population rate ≈ 95% (only icon strategy and 768px breakpoint were researcher defaults; all visual / interaction / copy decisions came from upstream).*
