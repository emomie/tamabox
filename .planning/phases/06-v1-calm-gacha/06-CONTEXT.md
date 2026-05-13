# Phase 6: v1 画面の Calm Gacha 化 - Context

**Gathered:** 2026-05-13
**Status:** Ready for planning
**Mode:** Auto-generated (`/gsd-discuss-phase 6 --auto`) — Claude が推奨デフォを選択

<domain>
## Phase Boundary

Phase 5 で `webroot/css/` に整備した Calm Gacha デザインシステム (tokens / .tb-btn / .tb-card / .tb-letter / .tb-chip / .tb-input / .tb-tabbar / .tb-appbar / element/icon.php) を使い、v1 で稼働している 7 つの PHP テンプレート (Home / Send / SendDone / Settings / Report / Delete / BlockList) + AvatarHandleChip element を、`~/projects/handoff_tamabox/screens/` の React hi-fi リファレンスに視覚一致するよう PHP テンプレート差し替えで完成させる。バックエンド (controller / model / OAuth / SSR ロジック) には一切手を入れない。Dashboard の 4 タブ分離 (NAV-*) と Reveal 演出 (MOTION-*)、EDGE-* モーダル類は Phase 7-8 のスコープなので本 phase では扱わない。

</domain>

<decisions>
## Implementation Decisions

### Area 1 — 画面置換戦略 (per-screen migration order)

- **D-01:** 置換順は依存度の低い→高い順: AvatarHandleChip → Home → Done → Delete → Report → Settings(element/inbox_settings_form) → BlockList(element/block_list) → Send。AvatarHandleChip は Send / Settings / BlockList から再利用されるので最初に固める
- **D-02:** 1 画面 = 1 plan = 1 commit を基本粒度とする (8 plans 想定)。1 つの commit で 1 つの hi-fi スクリーンが視覚一致まで進む単位
- **D-03:** 各 plan の DoD は (a) PHP テンプレートが hi-fi React と layout / spacing / typography で視覚一致、(b) `composer test` グリーン維持、(c) 既存の controller→view データフローが壊れていない、(d) 該当画面の MANUAL-SMOKE-CHECKLIST 項目が pass

### Area 2 — PHP element 抽出ポリシー (Phase 5 から deferred)

- **D-04:** Phase 5 で deferred された PHP element 化を本 phase 内で並行実施。具体的には `templates/element/tb_button.php` (4 variant)、`templates/element/tb_card.php`、`templates/element/tb_letter.php`、`templates/element/tb_chip.php`、`templates/element/tb_input.php` を抽出。element ファイル名は `tb_*` prefix で統一 (icon.php と同じ慣習)
- **D-05:** 抽出のタイミングは「2 画面以上で再利用が確認できた時点」とする (YAGNI)。AvatarHandleChip 着手時点で TbChip 抽出、Home 着手時点で TbButton 抽出、Send 着手時点で TbInput / TbLetter 抽出、Report 着手時点で TbCard 抽出を見込む
- **D-06:** element の引数 API は CakePHP の `$this->element('tb_button', ['variant' => 'primary', 'label' => '送信', 'href' => $url])` 形式。`label` / `variant` / `href` / `disabled` / `data` (任意の data-* 属性) / `icon` (SVG name) を基本パラメータとする
- **D-07:** 既存 element (`avatar_handle_chip.php` / `inbox_settings_form.php` / `block_list.php`) は **rewrite** する (新規 tb_* element に置き換えずに、既存ファイル名のまま中身を Calm Gacha 化)。controller / template からの呼び出しシグネチャは維持

### Area 3 — 旧クラス / milligram の扱い

- **D-08:** Phase 5 で確立した alias 戦略を継続。既存 `.button` / `.primary-button` / `.button-destructive` / `--color-*` 等を新規追加で使うことは禁止。本 phase で書く新 markup はすべて `.tb-btn` / `.tb-card` 系を使う
- **D-09:** **milligram.min.css は本 phase 中も残す**。8 画面置換が完了した時点で `.planning/phases/06-v1-calm-gacha/` 末尾 plan (smoke + cleanup) で grep して未使用なら剥がす判断を行う。先に剥がすと既存の form / table デフォルトが崩れるリスクがある
- **D-10:** 旧 class はテンプレートからは全部消す (alias 残置は CSS 側のみ、markup は `.tb-*` に統一)。視覚一致と保守容易性を両立

### Area 4 — Hi-fi 一致の判定方法

- **D-11:** Hi-fi 一致の判定は「`~/projects/handoff_tamabox/screens/{Screen}.jsx` の component 構造を mental model にし、PHP テンプレートで同じ DOM / class 配置を再現したか」をベースにする。**ピクセルパーフェクトを目標にせず、layout / spacing / typography / color tone の一致を目標にする**
- **D-12:** 各 plan の verifier セクションに「hi-fi 参照ファイルと side-by-side で見比べて mismatch がない」のチェック項目を入れる (Claude が verifier 内で `Read tools` で hi-fi JSX を読み比較できるよう、verifier プロンプトに hi-fi ファイルパスを明示)
- **D-13:** UI-Review (advisory) は Phase 5 と同じく `gsd-ui-review` を phase 末で実行。Phase 5 で確立した typography override / spacing exceptions の locked decision を継承

### Area 5 — バックエンド不変ガード

- **D-14:** `src/Controller/` / `src/Model/` / `config/Migrations/` 配下は本 phase で touch しない。touch した場合はそれ単独で plan-phase に差し戻し
- **D-15:** Flash messages / form helpers (`$this->Form->control()`) / link helpers (`$this->Html->link()`) は CakePHP の helper を使い続ける (markup を生 HTML にせず、helper の class 引数で `.tb-*` を渡す形にする)。これで CSRF token / validation error 表示が壊れない
- **D-16:** `composer test` が 195 tests / 0 failures グリーンを維持。template の rendering test (`tests/TestCase/View/`) があれば該当箇所が壊れていないか毎 plan の verifier で確認

### Claude's Discretion

- 各 element 抽出の commit と画面置換 commit を分けるか同一 commit にするかは plan-phase で判断 (基本は同一)
- icon.php 経由で SVG inline するか img 参照するかは `~/projects/handoff_tamabox/screens/*.jsx` 内のアイコン使用パターンを mirror して決定
- form の validation error 表示の Calm Gacha 化 (`.tb-input` の error state) を本 phase で実装するか Phase 8 EDGE-* に持ち越すかは、Send (`Messages/send.php`) 着手時に判断 (EDGE-03 が文字数オーバーを Phase 8 で扱うので、validation error 全般は Phase 8 寄せ候補)
- Send 画面の welcome message TbLetter 化 (UI-02) で、welcome テキストのソース (現状ハードコード? settings?) を確認した上で TbLetter コンポーネント仕様に合うようテンプレートで構造化

### Locked Decisions (Phase 5 から継承)

- **Typography Override** — 8 sizes / 4 weights (22 / 18 / 16 / 15 / 14 / 12 / 11 / 10 px、400 / 500 / 600 / 700) を `gsd-ui-checker` Dimension 4 の override として継承
- **Spacing Exceptions** — `.tb-chip gap: 6px` / `.tb-input padding: 14px` / `.tb-card padding: 18px` を Dimension 5 multiple-of-4 規則の例外として継承

### Locked Decision — Home display title typography exception (Phase 6 追加)

- **Scope:** `.tb-home__title` セレクタ単独 (UI-01 Home 画面のヒーロー見出しのみ)。
- **Value:** `font-size: 30px; font-weight: 700;` — 22px (Phase 5 locked set の最大) では hi-fi `~/projects/handoff_tamabox/screens/Home.jsx` のマーケティング・ディスプレイ見出しとして弱く、視覚的アンカーとしての存在感が不足する
- **Justification:** Home 画面は Bluesky OAuth 開始エントリーポイントであり、hi-fi ではブランド名相当の display heading として 30px の重みを使っている。`marketing display heading` ロールに該当する一回限りの追加であり、本文 (body) / セクション見出し (h2/h3) / ラベル (label) など locked set 内に収まるべき role には影響しない
- **Scope-of-override:** 本セレクタのみ。他の display heading 用途で 30px を使う場合や、追加サイズを導入する場合は再度 locked decision エントリが必要 (ad-hoc 追加禁止の Phase 5 ポリシー継承)
- **Dimension 4 implication:** `gsd-ui-checker` Dimension 4 (locked typography scale) はこのセレクタを許容する exception として扱う

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase 5 outputs (Calm Gacha foundation)
- `.planning/phases/05-design-system-foundation/05-CONTEXT.md` — Phase 5 decisions (alias 戦略 / locked typography / spacing exceptions)
- `.planning/phases/05-design-system-foundation/05-UI-SPEC.md` — Phase 5 で確立した tb-* component spec、本 phase のテンプレートはこれに準拠
- `.planning/phases/05-design-system-foundation/05-VERIFICATION.md` — Phase 5 verification の合否、本 phase の前提
- `webroot/css/tokens.css` — Calm Gacha トークン (`--tb-*`) 一次情報
- `webroot/css/colors_and_type.css` — セマンティック層 (`--fg-*` / `--bg-*` / `--type-*`)
- `webroot/css/tamabox.css` — `:root` alias + `.tb-*` component CSS

### Project-level
- `.planning/PROJECT.md` — v2 milestone goal、`tamabox.emomie.com` live 制約
- `.planning/REQUIREMENTS.md` §UI — UI-01 〜 UI-08 の判定基準
- `.planning/ROADMAP.md` — Phase 6 entry (goal, success criteria, plans count hint)

### Hi-fi design source (handoff_tamabox)
- `~/projects/handoff_tamabox/screens/Home.jsx` — UI-01 リファレンス
- `~/projects/handoff_tamabox/screens/Send.jsx` — UI-02 リファレンス
- `~/projects/handoff_tamabox/screens/Done.jsx` — UI-03 リファレンス
- `~/projects/handoff_tamabox/screens/Settings.jsx` — UI-04 リファレンス
- `~/projects/handoff_tamabox/screens/ReportDelete.jsx` — UI-05 / UI-06 リファレンス (Report variant + Delete)
- `~/projects/handoff_tamabox/screens/Block.jsx` — UI-07 リファレンス
- `~/projects/handoff_tamabox/components.jsx` — TbChip / TbButton / TbCard / TbLetter / TbInput / TbAppBar / TbTabBar React 実装。AvatarHandleChip (UI-08) は TbChip variant
- `~/projects/handoff_tamabox/design-system/components-*.html` — 視覚仕様 HTML (buttons / cards / chips / inputs)
- `~/projects/handoff_tamabox/tokens.css` — 念のため一次情報側 (webroot コピーと一致を保つため)

### CakePHP framework conventions
- 既存 `templates/layout/default.php` — header / footer / flash / TabBar 配置の前提
- 既存 `templates/element/icon.php` — SVG inline helper (Phase 5 で確立)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets (Phase 5 で整備済)
- `webroot/css/tamabox.css` の §5 セクション — `.tb-btn` (primary / ghost / quiet / disabled / danger)、`.tb-card` / `.tb-card-soft` / `.tb-letter`、`.tb-chip` (3 tone)、`.tb-input` (4 state)、`.tb-tabbar` / `.tb-appbar`
- `webroot/css/tokens.css` — Calm Gacha 全トークン
- `webroot/css/colors_and_type.css` — セマンティック層
- `templates/element/icon.php` — SVG inline ヘルパ、`$this->element('icon', ['name' => 'bell', 'size' => 24])` 形式
- `webroot/img/icons/` — Phase 5 で配置した 13 種 SVG (Inbox / Send / User / Bell / Compass / Back / Close / More / Check / Chevron / Letter / Star / Heart)
- `templates/layout/default.php` — Html->css chain (normalize → milligram → tokens → colors_and_type → tamabox) + Google Fonts (Noto Sans JP + JetBrains Mono) 注入済

### Target Files (this phase will rewrite)
- `templates/Pages/home.php` (31 行) → UI-01、hi-fi 91 行 (`screens/Home.jsx`)
- `templates/Messages/send.php` (80 行) → UI-02、hi-fi 109 行 (`screens/Send.jsx`)
- `templates/Messages/send_done.php` (25 行) → UI-03、hi-fi 67 行 (`screens/Done.jsx`)
- `templates/element/inbox_settings_form.php` (84 行) → UI-04、hi-fi 162 行 (`screens/Settings.jsx`)
- `templates/Reports/create.php` (59 行) → UI-05、hi-fi 165 行 (`screens/ReportDelete.jsx`) report variant
- `templates/Account/delete.php` (34 行) → UI-06、hi-fi 165 行 (`screens/ReportDelete.jsx`) delete variant
- `templates/element/block_list.php` (45 行) → UI-07、hi-fi 112 行 (`screens/Block.jsx`)
- `templates/element/avatar_handle_chip.php` (36 行) → UI-08、hi-fi の TbChip variant

### Established Patterns
- CakePHP 4.5 template syntax: `<?= h($var) ?>` / `<?= $this->Form->control(...) ?>` / `<?= $this->Html->link(...) ?>` / `<?= $this->element('name', $args) ?>` / `<?= $this->Flash->render() ?>`
- Phase 5 で確立した CSS section コメント `/* ======== Phase N — ... ======== */` を維持。新 element CSS が必要なら `tamabox.css` の Phase 6 section に追加
- Form helper の `templateVars` / `templates` 引数で input 周りの markup を上書き可能 (CakePHP form templating)。`.tb-input` を活かしたい場合は FormHelper の template override を使う

### Integration Points
- 各 PHP テンプレートが render する controller action は **変更しない**。view data の shape は維持
- Flash messages は `templates/element/flash/` 配下の既存 element を Calm Gacha 化 (任意、本 phase スコープ内)
- 新規追加 element (`templates/element/tb_*.php`) は `templates/element/` に直接配置

</code_context>

<specifics>
## Specific Ideas

- **Home (UI-01):** hi-fi の AppBar (左 back / 中 title / 右 user) + ヒーローカード (`.tb-card`) + 大型 CTA ボタン (`.tb-btn` primary)。Bluesky OAuth へのエントリーリンクを目立たせる
- **Send (UI-02):** TbLetter で welcome message 表示 → TbInput textarea (本文入力) → 文字数カウンタ (footer) → primary CTA。文字数オーバーの視覚処理は Phase 8 EDGE-03 寄せ
- **SendDone (UI-03):** 中央寄せ祝福画面、Star アイコン + "送信できました" + 戻る CTA。シンプル
- **Settings (UI-04):** AppBar + settings form (受信オン/オフトグル、SSR 確率スライダー、退会 danger ボタン) + block list (BlockList element 呼び出し)
- **Report (UI-05):** AppBar + メッセージ引用 (TbLetter) + 4 カテゴリ ラジオ (TbChip 風) + 送信 CTA
- **Delete (UI-06):** AppBar + 退会説明 + 影響範囲 3 点リスト + danger CTA (`.tb-btn` danger variant)
- **BlockList (UI-07):** AvatarHandleChip x N + 各行に解除 CTA (`.tb-btn` ghost / quiet)
- **AvatarHandleChip (UI-08):** TbChip variant、avatar + handle + (任意で profile_url icon)
- 既存 welcome message のソース: TBC — Send 着手時に grep で確認

</specifics>

<deferred>
## Deferred Ideas

- Dashboard 4 タブ分離 / TabBar 配置 → Phase 7 (NAV-01 〜 NAV-06)
- Reveal 開封 fade-in 演出 → Phase 7 (MOTION-02 / MOTION-03)
- Press scale 0.985 (グローバル motion) → Phase 8 (MOTION-01)
- Send 文字数オーバーハイライト → Phase 8 (EDGE-03)
- Send 404 / 受信停止 / 送信失敗 各 error 画面 → Phase 8 (EDGE-01 / EDGE-02 / EDGE-04)
- Block 確認 bottom sheet モーダル → Phase 8 (EDGE-05)
- Onboarding / Login 独立画面 / Help / Terms / Share → v3 候補
- Discover / Notifications backend → v3 候補
- 3D rotateX 封筒オープン演出 → v3 候補 (MOTION-X1)
- Desktop ブレイクポイント → v3 候補 (DESKTOP-*)

</deferred>

---

*Phase: 06-v1-calm-gacha*
*Context gathered: 2026-05-13 (auto mode via /gsd-discuss-phase 6 --auto)*
