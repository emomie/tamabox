# Phase 5: Design System Foundation - Context

**Gathered:** 2026-05-13
**Status:** Ready for planning

<domain>
## Phase Boundary

Calm Gacha デザイントークン (`handoff_tamabox/tokens.css` + `colors_and_type.css`) と共通コンポーネントクラス (`.tb-btn` / `.tb-card*` / `.tb-chip` / `.tb-input` / `.tb-tabbar` / `.tb-appbar` / アイコン SVG セット) を `webroot/css/` に配置し、`layout/default.php` でロード順を確立し、`tamabox.css` の `:root` 値を Calm Gacha 値で全面置換する。alias 戦略 (既存 `--color-*` を `--tb-*` に向ける) で既存全画面が破綻しない移行を保証する。本フェーズはコンポーネントの CSS のみ完成させ、PHP element / 画面差し替えは Phase 6 以降に持ち越す。

</domain>

<decisions>
## Implementation Decisions

### Area 1 — トークンファイル戦略

- `handoff_tamabox/tokens.css` をそのまま `webroot/css/tokens.css` にコピー (一次情報を維持、`tb-*` 接頭辞はそのまま)
- `colors_and_type.css` (セマンティック層) も `webroot/css/colors_and_type.css` として同時取り込み
- CSS 読み込み順 (`layout/default.php` Html->css 配列): `normalize → milligram → tokens → colors_and_type → tamabox` (cascade で tamabox.css が最後勝ち、必要時上書き可能)
- `milligram.min.css` は Phase 5 では残す。Phase 6 の画面個別置換時にコンポーネント別に削減判断する

### Area 2 — 既存 :root と CSS 互換性

- 既存 `:root` 変数 (`--color-bg`, `--color-accent`, `--color-text-primary`, `--color-error` etc.) は **alias 化** — 値を Calm Gacha トークンへ向ける (例: `--color-accent: var(--tb-turq-400)`, `--color-bg: var(--tb-paper)`). 既存テンプレートからの直接参照を切らない
- セマンティック層 (`--fg-1`, `--bg-page`, `--accent` 等) を Phase 6 以降のテンプレート参照の優先 API とする
- `:root` の Calm Gacha 値書き換え (DS-06) は Phase 5 内で完了させる
- 旧青系の固定値は完全置換 — git 履歴で復旧可能のため、コメントで旧値は残さない

### Area 3 — 共通コンポーネント実装スコープ

- Phase 5 は **CSS クラスのみ** を整備する (`.tb-btn`, `.tb-card`, `.tb-card-soft`, `.tb-letter`, `.tb-chip`, `.tb-input`, `.tb-tabbar`, `.tb-appbar`)。PHP element 化 (`templates/element/tb_button.php` 等) は Phase 6 で画面置換と同時に抽出する
- DS-03/04/05 の 7 種コンポーネントすべてを Phase 5 で CSS 整備 (TabBar / AppBar も含む。Phase 7 のタブ分離が来た時に再利用)
- アイコン SVG 集合 (Inbox / Send / User / Bell / Compass / Back / Close / More / Check / Chevron / Letter / Star / Heart) を `webroot/img/icons/` 配下に SVG ファイルとして配置、または `templates/element/icon.php` の inline SVG ヘルパとして提供する。Phase 5 で導入し Phase 6 以降の画面で使う前提
- `.tb-*` 接頭辞はそのまま採用 (handoff との一貫性)

### Area 4 — 移行戦略 / 破壊範囲

- Phase 5 完了時点で **既存全画面が破綻しないこと** を保証 (DS-06 Success Criteria に明示「既存ページが崩れていない」)
- 既存 `.button` / `.primary-button` / `.button-destructive` 等のクラスは alias 化 — 視覚は Calm Gacha 値を取りつつクラス名は維持。新規追加には `.tb-btn` を使う
- `webroot/css/{cake,fonts,home}.css` は Phase 5 内で grep して使用調査、参照ゼロなら削除タスクを含める
- 動作確認は `MANUAL-SMOKE-CHECKLIST.md` (12 項目) の再実行を Phase 5 verifier に組み込む (UI 視覚確認込み)。`composer test` (195 tests, 0 failures) も green を維持

### Claude's Discretion

- 各 CSS 編集の commit 粒度 (tokens 追加 / alias 追加 / `:root` 置換 / コンポーネントごとに分ける等) は plan-phase で task 分解時に決定
- アイコン SVG を「ファイル配置」と「inline element 化」のどちらにするかは plan-phase で codebase の既存 SVG 慣習を見て決定 (現状 `/img/default-avatar.svg` の前例あり)
- `tokens.css` / `colors_and_type.css` のコピー方法 (symlink / 物理 copy) は物理 copy で進める (`webroot/` は本番 git deploy 対象)

### Locked Decision — Typography Scale Override (UI-checker 制限値の override)

UI checker (`gsd-ui-checker`) の Dimension 4 は max 4 font sizes / max 2 weights を制限とするが、これは western SaaS UI 基準で **本 phase の CJK モバイル UI には適用しない** 旨をここで固定する。

- **Why**: handoff_tamabox は CJK タイポグラフィ密度要求 (本文 14px / メタ 11px / handle 10px label / mono 12px / display 22px) を満たすため 7-8 段の type role を持つ。`colors_and_type.css` の `--type-*` 一次情報を verbatim 採用するのが Phase 5 全体の指針 (Area 1〜4 全て一貫)。collapsing は hi-fi 視覚との不整合を生む。
- **Approved sizes (8)**: 22px (display/h1) / 18px (h2) / 16px (body-lg) / 15px (h3, button) / 14px (body) / 12px (mono) / 11px (meta/chip) / 10px (label/tab)
- **Approved weights (4)**: 400 (body) / 500 (mono, tab item) / 600 (chip / button label) / 700 (headings, label en)
- **Scope of override**: tamabox プロジェクト全体に適用 (Phase 5-8 全部)。UI-SPEC / UI-Review の Dimension 4 評価では本 override を参照して PASS 扱いとする。
- **How to apply**: 後続 Phase 6/7/8 の UI-SPEC.md でも同じ scale を継承する。新しい size/weight を追加する場合のみ、再度判断 (ad-hoc 追加禁止)。

### Locked Decision — Spacing Exceptions (verbatim handoff micro-values)

UI checker (`gsd-ui-checker`) の Dimension 5 は全 spacing 値が multiple-of-4 であることを求めるが、handoff_tamabox の component-level micro-spacing には multiple-of-4 ではない値が含まれる。Typography Override と同じ「verbatim from handoff」の指針に従い、以下の値を **明示的に許容**する。

- **Why**: `handoff_tamabox/tokens.css` の component CSS には CJK モバイル UI の視覚密度を最適化した micro-spacing が含まれる (chip の `gap: 6px`、input の `padding: 14px`、card の内部 padding 18px 等)。これらは 4px grid system の純粋な拡張ではなく、handoff デザイナーが視覚的に最適化した固定値。multiple-of-4 に丸めると visual diff (handoff 一致性) が損なわれる。
- **Approved spacing exceptions (component-internal only)**:
  - `6px` — `.tb-chip` 内部 `gap` (アイコン/ラベル間隔)
  - `14px` — `.tb-input` 内部 `padding` (上下/左右)
  - `18px` — `.tb-card` 内部 `padding` (page element として使用時の標準値)
- **Scope of override**: 上記 3 値のみ。**layout-level の spacing (margin, gap between sections, top-level padding) は全て multiple-of-4 を維持**する。spacing scale (`--tb-sp-*`) 自体は 4/8/12/16/20/24/32 を維持し、これらの例外は component CSS リテラルに限定する。
- **How to apply**: 後続 Phase 6/7/8 の UI-SPEC.md でも `.tb-chip` / `.tb-input` / `.tb-card` を使用する場合は同 micro-spacing を継承。新規 component を追加する場合は、grid 値を優先し、handoff verbatim な exception を導入する場合のみ本 locked decision に追記して文書化する。

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `webroot/css/tamabox.css` (911 行) に既存 `:root` 変数体系 (`--color-*`, `--space-*`, `--radius-*`, `--shadow-*`, `--font-family`) と全コンポーネント CSS が集中
- `templates/layout/default.php` で `$this->Html->css(['normalize.min', 'milligram.min', 'tamabox'])` 形式で chain ロード
- `templates/element/avatar_handle_chip.php` が hi-fi の `TbChip` 相当パターンの既存実装
- `templates/element/block_list.php` / `templates/element/inbox_settings_form.php` が既存 element パターン
- `webroot/img/default-avatar.svg` が SVG 参照の既存前例
- `handoff_tamabox/tokens.css` (259 行) + `handoff_tamabox/colors_and_type.css` (78 行) + `handoff_tamabox/components.jsx` (リファレンス) を `~/projects/handoff_tamabox/` から copy 取得可能

### Established Patterns
- CSS-in-file (preprocessor なし) — トークン値も `:root` の CSS Custom Properties として直接定義
- Phase N 単位の section コメント `/* ======== Phase N — ... ======== */` で `tamabox.css` 内に区切り
- 既存 Phase 4 CSS は `tamabox.css` 末尾に追記 (§1/§3/§5/§6/§9 各 section)
- アイコン: `webroot/img/*.svg` ファイル参照 or 文字シンボル (★・✓・●) を CSS で色付け

### Integration Points
- 新 CSS ファイル: `webroot/css/tokens.css` + `webroot/css/colors_and_type.css` を追加配置
- `templates/layout/default.php` の Html->css 配列に 2 ファイル追加 + Google Fonts link tag 追加
- `tamabox.css` の `:root` ブロックを完全書き換え (alias 戦略で全変数を `--tb-*` に向ける)
- 新コンポーネント CSS は `tamabox.css` の Phase 5 section コメント配下に追加
- アイコン SVG は `webroot/img/icons/` に新規ディレクトリ作成

</code_context>

<specifics>
## Specific Ideas

- Design source of truth: `~/projects/handoff_tamabox/` (PRIVATE GitHub repo `emomie/handoff_tamabox`、clone 済) の以下ファイル群:
  - `tokens.css` — 一次情報 (`--tb-paper` / `--tb-turq-*` / `--tb-warm-*` / `--tb-r-*` / `--tb-shadow-*` / `--tb-font-*`)
  - `colors_and_type.css` — セマンティック層 (`--fg-*` / `--bg-*` / `--accent` / `--type-*`)
  - `components.jsx` — TbPhone / TbAppBar / TbButton / TbChip / TbTabBar / TbLetter / Icon* の React リファレンス実装
  - `design-system/*.html` — components-buttons / components-cards / components-chips / components-inputs / components-tabbar / components-appbar / colors-* / spacing-* / type-* (21 ファイル、視覚仕様)
- フォント: Noto Sans JP (本文) + JetBrains Mono (メタ / 数値 / handle / ラベル英字) を Google Fonts CDN で取得 (handoff 指定)
- 配色テーマ: 紙基調 (`--tb-paper #FFFFFF`) + ターコイズプライマリ (`--tb-turq-400 #2FA597`) + 蜂蜜アクセント (`--tb-warm-500 #D9A23C`) + signal danger (`#B84238`)

</specifics>

<deferred>
## Deferred Ideas

- PHP element 化 (`templates/element/tb_*.php`) は Phase 6 (画面置換時にコンポーネント抽出)
- `milligram.min.css` の完全剥がし → Phase 6 でコンポーネント別に判断
- `webroot/css/{cake,fonts,home}.css` のクリーンアップ → 使用調査結果次第で Phase 5 内 or Phase 6 に
- 3D rotateX 封筒オープン演出 → v3 候補 (MOTION-X1)
- Bluesky 公式 butterfly ロゴ取得 → v3 候補 (ASSET-01)
- Desktop ブレイクポイント → v3 候補 (DESKTOP-01/02/03)

</deferred>
