---
phase: 2
slug: bluesky-oauth-identity
status: draft
shadcn_initialized: false
preset: none
created: 2026-04-23
---

# Phase 2 — UI デザイン契約 (Bluesky OAuth & Identity)

> Phase 2 の視覚・インタラクション契約。gsd-ui-researcher が生成し、gsd-ui-checker が検証する。
> 日本語ファーストサイト。このフェーズのUI表面は最小限 — OAuth ログインフロー専用。

---

## デザインシステム

| プロパティ | 値 |
|-----------|-----|
| Tool | none (shadcn 非対象 — PHP/CakePHP 4.5 SSR スタック) |
| Preset | not applicable |
| Component library | none (Milligram v1.3.0 ベース + Phase 2 専用 CSS 追加) |
| Icon library | none (テキストラベルのみ。アイコンは Phase 3+ で検討) |
| Font | system-ui, -apple-system, "Segoe UI", "Hiragino Sans", "Yu Gothic UI", Meiryo, sans-serif |

**根拠:**
- 既存コードベースに `webroot/css/milligram.min.css` + `normalize.min.css` が存在。Phase 2 では完全リプレースせずに上乗せ CSS (`webroot/css/tamabox.css`) を新規追加する。
- Google Fonts 依存なし (ユーザー決定: system-ui で外部ネットワーク負荷ゼロ)。
- shadcn は React/Next.js/Vite プロジェクト対象。CakePHP SSR には適用しない。

---

## 1. 情報アーキテクチャ — ルート一覧

| ルート | 役割 | 認証必須 | レスポンス種別 |
|--------|------|----------|--------------|
| `/` | ホームページ (ランディング)。「Bluesky でログイン」CTA を設置。未ログイン時のデフォルト表示 | 不要 | HTML |
| `/login/bluesky` | OAuth フロー開始。PKCE 生成 → PAR 実行 → Bluesky AS へリダイレクト。ユーザ入力フォームなし (D-07) | 不要 | `302 Redirect` (Bluesky AS) |
| `/oauth/callback` | OAuth コールバック。state 検証 → token 交換 → DB UPSERT → セッション確立 → `/dashboard` リダイレクト。失敗時はエラー画面 | 不要 (GET) | HTML (スピナー中間画面) または `302 Redirect` |
| `/oauth/logout` | ログアウト。POST 限定 + CSRF。セッション破棄 → `/` リダイレクト | 必要 | `302 Redirect` (/) |
| `/oauth/client-metadata.json` | AT Protocol クライアントメタデータ。Bluesky AS が参照 | 不要 | `application/json` (リダイレクト不可) |
| `/oauth/jwks.json` | ES256 公開鍵 (JWK)。Bluesky AS が参照 | 不要 | `application/json` |
| `/dashboard` | ログイン成功後のプレースホルダ。handle + 「受信箱はまだ作成されていません」表示のみ (Phase 3 で機能拡張) | 必要 | HTML |

---

## 2. ビジュアルデザイントークン

### カラーパレット

出典: ユーザー決定 (2026-04-23) — NEUTRAL starter。

| トークン名 | 値 | 役割 (60/30/10 分配) |
|-----------|-----|---------------------|
| `--color-bg` | `#F8F9FA` | 60% — ページ背景、オフホワイト |
| `--color-surface` | `#FFFFFF` | 30% — カード、インタースティシャル、ヘッダー背景 |
| `--color-text-primary` | `#1A1A1A` | 本文テキスト |
| `--color-text-secondary` | `#6C757D` | サブテキスト、プレースホルダ、補足情報 |
| `--color-accent` | `#0085FF` | 10% — Bluesky CTA ボタン、リンク、フォーカスリング |
| `--color-accent-hover` | `#006EDB` | CTA ホバー / アクティブ状態 |
| `--color-success` | `#16A34A` | 成功メッセージ (ログイン成功フラッシュ) |
| `--color-warning` | `#D97706` | 警告メッセージ |
| `--color-error` | `#DC2626` | エラーメッセージ、破壊的アクション強調 |
| `--color-border` | `#E5E7EB` | 区切り線、インプット枠 |

**アクセントカラーの使用先 (10% ルール — この要素だけに限定):**
- 「Bluesky でログイン」ボタン背景
- ページ内リンクのテキスト色
- フォーカスリング (`outline: 2px solid var(--color-accent)`)
- AvatarHandleChip のハンドルテキスト

**Milligram 既存カラーとの整合:**
- Milligram のデフォルト accent は `#d33c43` (赤)。Phase 2 からは `--color-accent: #0085FF` で上書きする。
- `webroot/css/tamabox.css` で CSS カスタムプロパティを宣言し、Milligram の後にロードする。

### スペーシングスケール

8pt グリッドベース。

| トークン | 値 | 用途 |
|---------|-----|------|
| `--space-1` | 4px | アイコン間隔、インラインパディング |
| `--space-2` | 8px | コンパクト要素間隔 |
| `--space-3` | 12px | 小さなコンポーネント内余白 |
| `--space-4` | 16px | 標準要素間隔 (デフォルト) |
| `--space-6` | 24px | セクション内パディング |
| `--space-8` | 32px | レイアウトギャップ |
| `--space-12` | 48px | 大セクション区切り |

例外: タッチターゲット最小 44px (WCAG 2.5.5)。ボタンの `min-height: 44px`。

### タイポグラフィ

| 役割 | サイズ | ウェイト | Line-height | 使用箇所 |
|------|--------|----------|-------------|---------|
| Display | 32px | 600 (semibold) | 1.2 | ページ `<h1>` (例: 「tamabox」ロゴテキスト) |
| Heading | 24px | 600 (semibold) | 1.2 | セクション見出し、エラータイトル |
| Body | 16px | 400 (regular) | 1.6 | 通常テキスト、ボタンラベル、フォームラベル |
| Label / Caption | 14px | 400 (regular) | 1.5 | ハンドル表示、補足説明、フラッシュメッセージ |
| Small | 12px | 400 (regular) | 1.4 | 法的注記、タイムスタンプ (Phase 3+) |

font-family (全要素共通):
```
system-ui, -apple-system, "Segoe UI", "Hiragino Sans", "Yu Gothic UI", Meiryo, sans-serif
```

### ボーダー半径

| トークン | 値 | 用途 |
|---------|-----|------|
| `--radius-sm` | 4px | アラート、インプット、タグ |
| `--radius-md` | 8px | ボタン、カード、AvatarHandleChip |

### シャドウ

| トークン | 値 | 用途 |
|---------|-----|------|
| `--shadow-subtle` | `0 1px 3px rgba(0,0,0,0.08)` | ヘッダー下線の代替、カード浮き上がり |

---

## 3. コンポーネント一覧

Phase 2 で使用する最小セット。すべて `webroot/css/tamabox.css` + PHP テンプレートで実装する。

### PrimaryButton

OAuth ログイン CTA。

```
セマンティクス: <form method="POST" action="/login/bluesky"> 内の <button type="submit">
テキスト: 「Bluesky でログイン」(固定)
色: background #0085FF, text #FFFFFF
hover: background #006EDB
disabled: opacity 0.5, cursor default
サイズ: padding 12px 24px, min-height 44px, font-size 16px, font-weight 600
border-radius: 8px
JS なし動作: フォームサブミットなので JS 不要 (no-JS 要件)
CSRF: CakePHP SecurityComponent / FormHelper の hidden フィールドを含む
```

### Alert

エラー・警告・成功の 3 バリアント。既存の `templates/element/flash/*.php` を拡張して適用。

```
エラー:   border-left 4px solid #DC2626, background #FEF2F2, text #1A1A1A
警告:     border-left 4px solid #D97706, background #FFFBEB, text #1A1A1A
成功:     border-left 4px solid #16A34A, background #F0FDF4, text #1A1A1A
padding:  12px 16px
border-radius: 4px (右側のみ)
font-size: 14px
role="alert" aria-live="assertive" (エラー・警告)
role="status" aria-live="polite" (成功)
```

### Spinner

OAuth コールバック中間画面で使用。

```
実装: CSS animation (border-spinning circle)
サイズ: 40px × 40px
色: border-color #E5E7EB, border-top-color #0085FF
セマンティクス: <div role="status" aria-live="polite"><span class="visually-hidden">Bluesky と通信中…</span></div>
no-JS デグラード: JS なし環境ではサーバーレンダリングされたインタースティシャルページとして表示。スピナーは CSS animation のみで動作 (JS 不要)
```

### AvatarHandleChip

ログイン済み状態のヘッダー内 ID 表示。Phase 2 では `/dashboard` と HeaderBar にのみ表示。

```
構成: [アバター画像 (24×24px, border-radius 50%)] + [@handle テキスト]
アバター: <img> fallback は初期文字のプレースホルダ (avatar_url_cached が null の場合)
handle テキスト: font-size 14px, color #0085FF
gap: 8px
border-radius: 8px
padding: 4px 8px
background: #F8F9FA (ヘッダー背景と同化して枠なし)
アバター alt: "{handle} のアイコン"
```

### HeaderBar

全ページ共通ヘッダー。既存 `templates/layout/default.php` の `<nav class="top-nav">` を置き換え。

```
構成 (未ログイン時): [アプリ名 "tamabox" (h1 相当、Display 32px)] + [余白]
構成 (ログイン時):   [アプリ名] + [AvatarHandleChip] + [ログアウト リンク/ボタン]
background: #FFFFFF
border-bottom: 1px solid #E5E7EB
padding: 12px 24px
ログアウトボタン: <form method="POST" action="/oauth/logout"> + CSRFトークン + <button type="submit" class="button-clear">ログアウト</button>
ログアウトテキスト: font-size 14px, color #6C757D (secondary)
```

---

## 4. コピーライティング契約

すべて日本語。リテラル文字列を確定する。

### CTA・ラベル

| 要素 | 確定コピー |
|------|-----------|
| ホームページ CTA ボタン | `Bluesky でログイン` |
| コールバック中間画面 見出し | `Bluesky と通信中…` |
| ダッシュボード ウェルカム見出し | `ようこそ、{handle} さん` |
| ダッシュボード サブテキスト | `受信箱はまだ作成されていません。受信箱の作成は次のステップで行います。` |
| ログアウトリンク | `ログアウト` |

### エラー文言

各エラーはフラッシュメッセージまたはインラインアラートとして表示する。すべて `<div role="alert">` でラップし、`aria-live="assertive"` を付与する。

| エラーシナリオ | 見出し (短い) | 本文 + 次のアクション |
|---------------|-------------|---------------------|
| (a) ユーザーが Bluesky 側でキャンセル | `ログインをキャンセルしました` | `Bluesky の認証画面でキャンセルされました。再度ログインするには下のボタンを押してください。` |
| (b) state/nonce ミスマッチ | `ログインに失敗しました` | `セッションの整合性を確認できませんでした。再度ログインしてください。（エラーコード: STATE_MISMATCH）` |
| (c) DPoP proof 拒否 | `ログインに失敗しました` | `Bluesky との通信中にセキュリティエラーが発生しました。しばらくしてから再度お試しください。（エラーコード: DPOP_REJECTED）` |
| (d) トークン取得失敗 | `ログインに失敗しました` | `Bluesky からアクセス権限を取得できませんでした。しばらくしてから再度お試しください。（エラーコード: TOKEN_EXCHANGE_FAILED）` |
| (e) ネットワーク/タイムアウト | `接続できませんでした` | `Bluesky のサーバーに接続できませんでした。ネットワーク接続を確認のうえ、再度お試しください。` |
| (f) セッション切れ (保護ルートアクセス) | `ログインが必要です` | `セッションの有効期限が切れました。再度ログインしてください。` |

### 破壊的アクション

| アクション | 確認方式 | 確認コピー |
|------------|---------|-----------|
| ログアウト | インライン確認なし (ログアウトは軽微)。POST + CSRF で誤操作防止とする。完了後フラッシュを表示 | — |
| ログアウト完了フラッシュ | フラッシュ (success) | `ログアウトしました` |

注: アカウント削除 (退会) は Phase 4 の責務。Phase 2 では破壊的確認モーダルは不要。

### 空状態

| 画面 | コピー |
|------|--------|
| ホーム (未ログイン) | 見出し: `tamabox` / サブ: `Bluesky アカウントでログインして、あなたの受信箱をはじめましょう。` |
| ダッシュボード (inbox 未作成) | `受信箱はまだ作成されていません。受信箱の作成は次のステップで行います。` |

---

## 5. インタラクションフロー

### ハッピーパス: ログイン成功

```
[ユーザー]                    [tamabox]                      [Bluesky AS]
    |                              |                               |
    | GET /                        |                               |
    |----------------------------->|                               |
    |  ホーム (CTA表示)            |                               |
    |<-----------------------------|                               |
    |                              |                               |
    | POST /login/bluesky          |                               |
    |----------------------------->|                               |
    |                    PKCE生成・state生成                        |
    |                    PAR実行 (DPoP-Nonce リトライあり)           |
    |                              |---PAR POST------------------->|
    |                              |<--request_uri + 400+nonce-----|
    |                              |---PAR POST (with nonce)------>|
    |                              |<--{request_uri}---------------|
    |                              |                               |
    |  302 → Bluesky認可画面       |                               |
    |<-----------------------------|                               |
    |                              |                               |
    | [Bluesky側でhandle入力・承認] |                               |
    |                              |                               |
    | GET /oauth/callback?code=..&state=..                         |
    |----------------------------->|                               |
    |    インタースティシャル表示   |                               |
    |    (Spinner + "Bluesky と通信中…")                           |
    |<-----------------------------|                               |
    |                    state検証 → token交換                     |
    |                    DID解決 → getProfile                      |
    |                    DB UPSERT (users + user_identities)       |
    |                    setIdentity → セッション確立              |
    |                              |                               |
    |  302 → /dashboard            |                               |
    |<-----------------------------|                               |
    |                              |                               |
    | GET /dashboard               |                               |
    |----------------------------->|                               |
    |  ダッシュボード              |                               |
    |  (handle表示 + AvatarHandleChip)                             |
    |<-----------------------------|                               |
```

### エラーパス A: Bluesky 側でキャンセル

```
[ユーザー]                    [tamabox]
    |                              |
    | GET /oauth/callback?error=access_denied&state=..
    |----------------------------->|
    |                    error パラメータ検出
    |                    フラッシュ (error) セット:
    |                    「ログインをキャンセルしました」
    |  302 → /                     |
    |<-----------------------------|
    |  ホーム (Alert: エラーa)     |
    |<-----------------------------|
```

### エラーパス B: state ミスマッチ

```
[ユーザー]                    [tamabox]
    |                              |
    | GET /oauth/callback?code=..&state=INVALID
    |----------------------------->|
    |                    $_SESSION['oauth_state'] != state
    |                    フラッシュ (error) セット:
    |                    「ログインに失敗しました」STATE_MISMATCH
    |  302 → /                     |
    |<-----------------------------|
    |  ホーム (Alert: エラーb)     |
    |<-----------------------------|
```

### エラーパス C: DPoP / token exchange 失敗

```
[ユーザー]                    [tamabox]
    |                              |
    | GET /oauth/callback?code=..&state=VALID
    |----------------------------->|
    |                    token exchange → RuntimeException
    |                    (DPoP拒否 or token取得失敗)
    |                    フラッシュ (error) セット: エラーc or d
    |  302 → /                     |
    |<-----------------------------|
    |  ホーム (Alert: エラーc/d)   |
    |<-----------------------------|
```

### セッション切れ: 保護ルートアクセス → /login リダイレクト

```
[ユーザー]                    [tamabox]
    |                              |
    | GET /dashboard (セッション切れ)
    |----------------------------->|
    |                    AuthenticationMiddleware → 未認証
    |                    302 → /?reason=expired
    |<-----------------------------|
    |  ホーム (Alert: エラーf)     |
    |  「セッションの有効期限が切れました。再度ログインしてください。」
    |<-----------------------------|
    |                              |
    | POST /login/bluesky (CTAクリック)
    |----------------------------->| ... (ハッピーパスへ)
```

### ログアウト

```
[ユーザー]                    [tamabox]
    |                              |
    | POST /oauth/logout (CSRFトークン付き)
    |----------------------------->|
    |                    $this->Authentication->logout()
    |                    セッション破棄
    |                    フラッシュ (success) セット:
    |                    「ログアウトしました」
    |  302 → /                     |
    |<-----------------------------|
    |  ホーム (Alert: 「ログアウトしました」)
    |<-----------------------------|
```

---

## 6. アクセシビリティ基準

### フォーカス表示

- すべてのインタラクティブ要素 (`button`, `a`, `input`) に可視フォーカスリングを適用する。
- CSS: `outline: 2px solid #0085FF; outline-offset: 2px;` (`:focus-visible` 疑似クラス使用)
- `:focus:not(:focus-visible)` でマウスクリック時の outline を非表示にする。

### カラーコントラスト (WCAG AA)

| 組み合わせ | コントラスト比 | 判定 |
|------------|--------------|------|
| `#1A1A1A` on `#F8F9FA` | ~17.5:1 | AA 合格 (本文) |
| `#1A1A1A` on `#FFFFFF` | ~19.5:1 | AA 合格 (カード内本文) |
| `#FFFFFF` on `#0085FF` | ~4.5:1 | AA 合格 (CTA ボタン) |
| `#FFFFFF` on `#006EDB` | ~5.6:1 | AA 合格 (CTA ホバー) |
| `#6C757D` on `#F8F9FA` | ~4.7:1 | AA 合格 (secondary text) |
| `#DC2626` on `#FEF2F2` | ~4.2:1 | AA 合格 (エラーアラート) |

### スピナーのアクセシビリティ

```html
<div role="status" aria-live="polite" class="spinner-wrapper">
    <div class="spinner" aria-hidden="true"></div>
    <span class="visually-hidden">Bluesky と通信中…</span>
</div>
```

`.visually-hidden` クラス定義:
```css
.visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0,0,0,0);
    white-space: nowrap;
    border: 0;
}
```

### ボタン / フォームのアクセシビリティ

- 「Bluesky でログイン」ボタン: テキストラベルあり → `aria-label` 不要。
- ログアウトボタン: テキストラベルあり → `aria-label` 不要。
- フォームエラーアラート: `role="alert"` + `aria-live="assertive"` で即時読み上げ。
- アバター画像: `<img src="..." alt="{handle} のアイコン">` (意味のある alt テキスト)。

### No-JS 動作保証

| 要素 | no-JS 動作 |
|------|-----------|
| 「Bluesky でログイン」CTA | `<form method="POST" action="/login/bluesky">` → JS なしで送信可能 |
| ログアウトボタン | `<form method="POST" action="/oauth/logout">` → JS なしで送信可能 |
| スピナー | CSS animation のみ → JS なしで動作する |
| エラーアラート | サーバーサイドレンダリング (CakePHP Flash) → JS なしで表示される |

### 言語宣言

```html
<html lang="ja">
```

---

## 7. CSS 実装ガイド (executor 向け)

### 新規ファイル配置

- `webroot/css/tamabox.css` — Phase 2 専用スタイル。Milligram の後にロードする。
- `templates/layout/default.php` の `<head>` を更新: `<?= $this->Html->css(['normalize.min', 'milligram.min', 'fonts', 'tamabox']) ?>`
- `<html lang="ja">` を追加する。

### CSS カスタムプロパティ宣言 (tamabox.css 先頭)

```css
:root {
    /* カラー */
    --color-bg: #F8F9FA;
    --color-surface: #FFFFFF;
    --color-text-primary: #1A1A1A;
    --color-text-secondary: #6C757D;
    --color-accent: #0085FF;
    --color-accent-hover: #006EDB;
    --color-success: #16A34A;
    --color-warning: #D97706;
    --color-error: #DC2626;
    --color-border: #E5E7EB;

    /* スペーシング */
    --space-1: 4px;
    --space-2: 8px;
    --space-3: 12px;
    --space-4: 16px;
    --space-6: 24px;
    --space-8: 32px;
    --space-12: 48px;

    /* ボーダー半径 */
    --radius-sm: 4px;
    --radius-md: 8px;

    /* シャドウ */
    --shadow-subtle: 0 1px 3px rgba(0,0,0,0.08);

    /* タイポグラフィ */
    --font-family: system-ui, -apple-system, "Segoe UI", "Hiragino Sans", "Yu Gothic UI", Meiryo, sans-serif;
}

/* Milligram のデフォルト accent (#d33c43) を上書き */
.button, button, input[type='submit'] {
    background-color: var(--color-accent);
    border-color: var(--color-accent);
}
.button:hover, button:hover, input[type='submit']:hover {
    background-color: var(--color-accent-hover);
    border-color: var(--color-accent-hover);
}

/* body フォント上書き */
body {
    font-family: var(--font-family);
    color: var(--color-text-primary);
    background-color: var(--color-bg);
}
```

### テンプレート構成 (Phase 2 で新規追加)

| テンプレートパス | 用途 |
|----------------|------|
| `templates/layout/default.php` | ヘッダー更新 (HeaderBar, `lang="ja"`, `tamabox.css` 追加) |
| `templates/Pages/home.php` | ホームページ (CTA ボタン) |
| `templates/Auth/callback.php` | OAuth コールバック中間画面 (Spinner) |
| `templates/Users/dashboard.php` | ダッシュボード プレースホルダ (AvatarHandleChip 含む) |
| `templates/element/avatar_handle_chip.php` | AvatarHandleChip 再利用 element |

---

## レジストリ安全ゲート

| レジストリ | 使用ブロック | 安全ゲート |
|-----------|------------|-----------|
| shadcn official | なし (非適用) | not required |
| サードパーティ | なし | not required |

---

## Checker サインオフ

- [ ] Dimension 1 コピーライティング: PASS
- [ ] Dimension 2 ビジュアル: PASS
- [ ] Dimension 3 カラー: PASS
- [ ] Dimension 4 タイポグラフィ: PASS
- [ ] Dimension 5 スペーシング: PASS
- [ ] Dimension 6 レジストリ安全: PASS

**承認:** pending

---

## 事前入力ソース

| ソース | 使用した決定事項 |
|--------|---------------|
| ユーザー決定 (2026-04-23) | カラーパレット全色、フォントファミリー |
| 02-CONTEXT.md | D-07 (handle 入力 UI なし)、D-18 (ログアウト仕様)、D-16/17 (メタデータエンドポイント)、ルート設計 |
| 02-RESEARCH.md | OAuth フローシーケンス、エラーパターン |
| REQUIREMENTS.md | AUTH-01/02/08/09 から導出した UI 要件 |
| 既存コードベース | Milligram v1.3.0 存在確認、flash element 構造、layout/default.php 構造 |

---

*Phase: 02-bluesky-oauth-identity*
*UI-SPEC 作成: 2026-04-23*
