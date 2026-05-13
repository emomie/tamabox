# Phase 7: Dashboard 4 タブ分離 + Reveal 演出 - Context

**Gathered:** 2026-05-13
**Status:** Ready for planning
**Mode:** Auto-generated (`/gsd-discuss-phase 7 --auto`) — Claude が推奨デフォを選択

<domain>
## Phase Boundary

Phase 6 で Calm Gacha 化した 8 PHP テンプレートの上に、`templates/Users/dashboard.php` を hi-fi `screens/Dashboard.jsx` の TbAppBar + Box card + 受信リスト構造に書き換え、TabBar (`.tb-tabbar` Phase 5 component) を `templates/element/tb_tabbar.php` に抽出して 4 タブ (受信 / 発見 / 通知 / 設定) ナビゲーションを成立させる。発見 (`screens/Discover.jsx`) と通知 (`screens/Notifications.jsx`) は Empty state 骨格のみを表示する新規スタブ画面として `templates/Users/discover.php` / `templates/Users/notifications.php` を追加 (バックエンドロジック本体は v3)、設定タブは既存 `templates/Inboxes/settings.php` を `screens/Settings.jsx` に視覚一致させる。Reveal motion は CSS animation 1 個 (`.is-opening { animation: tb-fade-in 400ms ease; }`) + 既存の details/open ハンドラへの class toggle 追加で MOTION-02 を満たし、HIT 結果の sender カード演出は `screens/RevealHit.jsx` の DOM 構造を `dashboard.php` 内 `<details>` body にミラーリングして MOTION-03 を満たす。3D rotateX 封筒オープン演出は v3 (MOTION-X1) で本 phase スコープ外。

</domain>

<decisions>
## Implementation Decisions

### Area 1 — タブ実装戦略 (4 タブをどう成立させるか)

- **D-01:** 4 タブ = 4 独立 URL の **SSR-pure 方式** を採用。`/dashboard` (受信) / `/dashboard/discover` (発見) / `/dashboard/notifications` (通知) / `/dashboard/settings` (設定) の 4 ルート。hi-fi の React state ベース切替は SSR 環境に直訳しない (CONTEXT.md Phase 6 D-11 「ピクセルパーフェクト非目標、layout/spacing 一致」の延長で、UX 等価でルーティング戦略は SSR ネイティブを優先)
- **D-02:** `/dashboard/settings` は既存ルート (`Inboxes::settings`, GET|POST) を再利用。GET アクセス時に `templates/Inboxes/settings.php` が render されることを保証し、ここに Phase 6 で完成した `inbox_settings_form` element を呼び出す + block list element を併置する形にする
- **D-03:** `/dashboard/discover` と `/dashboard/notifications` を新規追加。`UsersController::discover` / `UsersController::notifications` の stub アクションを追加 (要 login)、テンプレートは hi-fi Empty 骨格を render するだけで DB クエリなし。routes.php に 2 行追加 (`/dashboard/discover` → `Users::discover` / `/dashboard/notifications` → `Users::notifications`、GET only)
- **D-04:** Dashboard 受信タブ (`/dashboard`) からは Phase 6 までインラインで render していた settings aside (`<aside class="dashboard-settings">`) を **撤去**。settings は 4 タブ目に分離されたため。controller も settings 関連の view データ ($inbox / $blocks) を引き続き渡すが、template 側で参照しない (将来的に渡さなくする clean-up は plan 末尾 task で判断)
- **D-05:** TabBar の active state は server-side で path から判定 (`$this->request->getPath()`)、`.tb-tabbar__item.is-active` クラスを付与。JS なし

### Area 2 — TabBar 抽出と element 化

- **D-06:** `templates/element/tb_tabbar.php` を新規抽出。Phase 5 の `.tb-tabbar` CSS をそのまま使う、PHP element の責務は: 4 タブの SVG icon + label + active 判定 + 未読ドット (受信タブのみ条件付き)
- **D-07:** Element 引数: `$this->element('tb_tabbar', ['active' => 'inbox|discover|notifications|settings', 'unread_count' => $count])`。active は controller から渡す (`$this->set('active_tab', 'inbox')`)、unread_count は dashboard で計算済の値を流用
- **D-08:** 各タブのアイコンは Phase 5 で配置済の SVG セットを再利用 — 受信:`inbox`、発見:`compass`、通知:`bell`、設定:`user`。`element/icon.php` 経由で inline SVG
- **D-09:** TabBar は `templates/layout/default.php` のフッタ位置(認証済セッションでのみ)に組み込む案も検討したが、**画面ごとに element 呼び出し** とする (各 dashboard 系画面で `<?= $this->element('tb_tabbar', ...) ?>` を末尾に書く)。理由: TabBar は login 後の dashboard 系画面でのみ表示、layout で出すと Send 画面等で誤表示するリスクがある。明示的に画面ごとに呼ぶ方が安全

### Area 3 — Reveal 開封 fade-in 演出 (MOTION-02)

- **D-10:** `.is-opening` クラスでの fade-in CSS animation を `tamabox.css` Phase 7 section に追加。実装は `@keyframes tb-fade-in { from { opacity: 0; transform: translateY(2px); } to { opacity: 1; transform: none; } }` + `.message-row__body.is-opening { animation: tb-fade-in 400ms ease; }`
- **D-11:** Class toggle は既存の details open イベント上で発火。`webroot/js/reveal.js` (or 同等) が無ければ新規追加し、`<details data-state="unread">` の toggle 時に内部 `.message-row__body` に `.is-opening` を一時付与 (animation 終了で外す)。サーバーサイド開封 POST (`/dashboard/messages/{id}/open`) のレスポンスは現行維持 (redirect、新ステートで再描画)
- **D-12:** 3D rotateX 演出 (MOTION-X1) は v3 持ち越し。fade-in のみで MOTION-02 達成

### Area 4 — RevealHit sender カード演出 (MOTION-03)

- **D-13:** 既存 dashboard `<details>` body 内の sender カード markup (Phase 4 で実装) を hi-fi `screens/RevealHit.jsx` の sender card 部分に視覚一致させる。具体的には sender avatar (48-56px gradient circle) + handle (mono) + profile link icon ボタン + SSR HIT バッジ (`.tb-chip warm`) の構造に書き換え
- **D-14:** HIT 判定 (`$msg->is_ssr === true`) 時のみ sender カードを露出する既存ロジックを維持。MISS 時は匿名表示 (Phase 4 既存挙動を継承)
- **D-15:** RevealHit screen 全体 (305 行) を 1:1 移植するのではなく、`dashboard.php` の `<details>` body 内の HIT 結果ブロックに限定して hi-fi 一致を取る。reveal の 画面遷移自体は v1 (inline details open) を継承 — full-screen reveal page は Phase 8 EDGE / v3 候補で

### Area 5 — 発見 / 通知 タブの Empty state スタブ

- **D-16:** `templates/Users/discover.php`: hi-fi `screens/Discover.jsx` の TbAppBar + 検索風 input (disabled、placeholder のみ) + 「もうすぐ来ます」風の Empty illustration + Tag chip 群 (disabled) を **静的描画**。controller `UsersController::discover` は `$this->set('active_tab', 'discover')` のみ
- **D-17:** `templates/Users/notifications.php`: hi-fi `screens/Notifications.jsx` の Empty 状態 (アイコン + 「通知はまだありません」+ 説明文) を静的描画。controller も最小
- **D-18:** どちらの画面も DB クエリゼロ。バックエンド本体は v3 candidate (DISC-01 / NOTIF-01)

### Area 6 — バックエンド変更の範囲

- **D-19:** 本 phase は Phase 6 と異なり **`src/Controller/` と `config/routes.php` に追記が許可される**。ただし既存 controller アクションの挙動変更は禁止 (Phase 6 D-14 の精神を継承)。許可される変更: `UsersController::discover` / `UsersController::notifications` の 2 stub アクション追加、`routes.php` への 2 行追加、AuthorizationMiddleware 経由のアクセス制御は既存パターン (`$this->Authentication->getIdentity()`) を mirror
- **D-20:** Model / Migration / OAuth / SSR ロジック / モデレーション周りは依然 touch 禁止
- **D-21:** `composer test` 195 tests / 0 failures グリーンを維持。新規 controller アクションに対する rendering test (`tests/TestCase/Controller/UsersControllerTest`) を新規追加して unauth/auth で 302/200 を担保する task を plan 末尾に含める

### Area 7 — Hi-fi 一致判定の継承

- **D-22:** Phase 6 D-11 (「ピクセルパーフェクト非目標、layout/spacing/typography/color tone 一致」) を継続。half-pixel 値が hi-fi に存在しても locked typography scale (Phase 5 override の 8 sizes / 4 weights) に丸める方針を維持 (`06-REVIEW-FIX` で確立、`06-CONTEXT.md` Locked Decision で document 済)
- **D-23:** TabBar / Empty state / RevealHit の各 component は handoff の値を verbatim 採用するが、locked scale 外の値があれば自動で丸める。例外の追加は plan-phase で必要性が出てから個別判断、ad-hoc 追加禁止

### Claude's Discretion

- 既存 dashboard の settings aside 撤去で生じる data-flow 簡素化 ($inbox / $blocks を controller から渡さなくする) を本 phase で実施するか保留するかは plan-phase で判断 (clean-up コスト次第)
- Reveal fade-in の JS 実装 (新規 file vs 既存 layout に inline script) は plan-phase で codebase 慣習を確認して決定
- `tb_tabbar` element を Phase 6 で deferred した tb_* element 抽出ポリシー (Phase 6 D-04 / D-05) の延長として扱うか、Phase 7 独自の判断にするかは plan-phase で確認 (本 phase で TabBar は 4 画面再利用が確定するため抽出条件を満たす)

### Locked Decisions (Phase 5-6 から継承)

- **Typography Override** (Phase 5): 8 sizes (22/18/16/15/14/12/11/10) / 4 weights (400/500/600/700) を `gsd-ui-checker` Dimension 4 の override として継承
- **Spacing Exceptions** (Phase 5): `.tb-chip gap: 6px` / `.tb-input padding: 14px` / `.tb-card padding: 18px` を Dimension 5 multiple-of-4 の例外として継承
- **Home display title 30px exception** (Phase 6): `.tb-home__title` セレクタ単独
- **Half-pixel rounding to locked scale** (Phase 6 D-11 系): handoff の half-pixel font-sizes は locked scale (10/12/14...) に丸める

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase 5-6 outputs (Calm Gacha foundation)
- `.planning/phases/05-design-system-foundation/05-CONTEXT.md` — Phase 5 design decisions
- `.planning/phases/05-design-system-foundation/05-UI-SPEC.md` — `.tb-*` component contract
- `.planning/phases/06-v1-calm-gacha/06-CONTEXT.md` — Phase 6 decisions including hi-fi 判定基準 (D-11) と locked typography exceptions
- `.planning/phases/06-v1-calm-gacha/06-VERIFICATION.md` — Phase 6 PASS state (deploy `1777c2a` smoke verified)
- `webroot/css/tamabox.css` — `:root` alias + `.tb-*` component CSS (Phase 6 §G.1〜§G.10 を含む)

### Project-level
- `.planning/PROJECT.md` — v2 milestone goal
- `.planning/REQUIREMENTS.md` §NAV / §MOTION — NAV-01..06 + MOTION-02 / MOTION-03 の判定基準
- `.planning/ROADMAP.md` — Phase 7 entry (goal, 6 success criteria)
- `config/routes.php` — 既存 route 定義、本 phase で 2 行追加

### Hi-fi design source
- `~/projects/handoff_tamabox/screens/Dashboard.jsx` — NAV-03 受信タブ + 全体ヘッダ
- `~/projects/handoff_tamabox/screens/Discover.jsx` — NAV-04 発見タブ Empty state 骨格
- `~/projects/handoff_tamabox/screens/Notifications.jsx` — NAV-05 通知タブ Empty state 骨格
- `~/projects/handoff_tamabox/screens/Settings.jsx` — NAV-06 設定タブ layout (Phase 6 UI-04 で一部実装済、本 phase で Dashboard 内 inline → 独立画面に移行)
- `~/projects/handoff_tamabox/screens/Reveal.jsx` — MOTION-02 fade-in 演出のリファレンス
- `~/projects/handoff_tamabox/screens/RevealHit.jsx` — MOTION-03 sender カード演出
- `~/projects/handoff_tamabox/components.jsx` — TbTabBar / TbAppBar / TbCard / TbChip React 実装

### CakePHP integration points
- `src/Controller/UsersController.php` — `dashboard` action 既存、新規 `discover` / `notifications` action 追加
- `src/Controller/InboxesController.php` — `settings` action 既存、GET render 経路を新規通る
- `templates/layout/default.php` — header / footer wrapping、TabBar は画面ごと element 呼びで挿入

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets (Phase 5-6 で整備済)
- `webroot/css/tamabox.css` §5 (Phase 5) + §G.1-§G.10 (Phase 6) — `.tb-btn` / `.tb-card` / `.tb-chip` / `.tb-input` / `.tb-letter` / `.tb-appbar` / `.tb-tabbar` の全 CSS。`.tb-tabbar` は Phase 5 で CSS を整備したが PHP element 化は本 phase で初めて実施
- `templates/element/icon.php` — 13 SVG inline helper、TabBar アイコン (inbox / compass / bell / user) を呼び出す
- `templates/element/inbox_settings_form.php` — Phase 6 で Calm Gacha 化済、設定タブで再利用
- `templates/element/block_list.php` — Phase 6 で Calm Gacha 化済、設定タブで再利用
- `templates/element/avatar_handle_chip.php` — Phase 6 で Calm Gacha 化済、Discover / Notifications stub と RevealHit sender カードで再利用候補

### Target Files (this phase will create / rewrite)
**新規作成:**
- `templates/element/tb_tabbar.php` (~40 lines 想定) — TabBar element
- `templates/Users/discover.php` (~60 lines 想定) — Discover Empty state
- `templates/Users/notifications.php` (~50 lines 想定) — Notifications Empty state

**リライト:**
- `templates/Users/dashboard.php` (現 149 行 → ~160 行想定) — hi-fi Dashboard.jsx 構造 + TabBar 追加 + settings aside 撤去 + RevealHit sender カード書き換え
- `templates/Inboxes/settings.php` (現 14 行 → ~30 行想定) — TabBar 追加 + Settings.jsx 構造に視覚一致 + block list section 追加

**追加:**
- `src/Controller/UsersController.php` (現 160 行 → ~190 行想定) — `discover()` / `notifications()` stub action 追加 (各 ~10 行)
- `config/routes.php` (現 ~200 行 → +2 行) — Discover / Notifications route 追加
- `tests/TestCase/Controller/UsersControllerTest.php` — discover / notifications action の auth required + 200 OK の test 追加
- `tamabox.css` Phase 7 section (~150 lines 想定) — TabBar element 内部 spacing 微調整 + `.is-opening` animation + Empty state 用 helper class + Dashboard Box card / Counts row layout

**JS:**
- `webroot/js/reveal-motion.js` (新規、~30 lines) — `<details>` toggle 時に `.is-opening` class を一時付与する小スクリプト。`templates/layout/default.php` で defer ロード

### Established Patterns
- CakePHP 4.5 controller pattern: `public function discover(): void` を class に追加、`Authentication->getIdentity()` で auth check、Inbox model に触らない場合は `$this->loadModel()` 不要
- Routes 追加位置: 既存 `/dashboard` 系ルートのすぐ下にグループ追加
- Test pattern: `tests/TestCase/Controller/UsersControllerTest.php` を参照、`get('/dashboard/discover')` で 200 OK + `<title>` 確認のシンプルケース

### Integration Points
- TabBar element は `templates/Users/dashboard.php` / `discover.php` / `notifications.php` / `templates/Inboxes/settings.php` の 4 ファイルで呼ばれる
- Reveal motion CSS animation `.is-opening` は `tamabox.css` Phase 7 section に追加、JS は layout に link 追加
- Dashboard settings aside の撤去で `controller::dashboard` から `$inbox` / `$blocks` の view data が settings tab 移転後も dashboard で受け取り続けるが、template 側で参照しない (将来的に簡素化候補)

</code_context>

<specifics>
## Specific Ideas

- **Dashboard hi-fi (`Dashboard.jsx`):** TbAppBar (タイトル "受信箱" + Bell icon + 葵の avatar circle) → Box card (URL + SSR chip) → Counts row (受信件数 + 未読数) → 受信リスト (`.message-row` を hi-fi の `<div className="...">` 構造に近づけて Calm Gacha 化)
- **Discover hi-fi (`Discover.jsx`):** TbAppBar (発見 + sub "箱をみつける") → Search 風 input (検索 SVG icon + placeholder "@handle で箱をさがす") → Tag chip 群 (すべて/創作/音楽/研究/写真/ゲーム、全て disabled visual) → featured 箱 card (placeholder text "もうすぐ公開予定" 的) → 「箱を探す機能は近日公開」リード文。実データなし
- **Notifications hi-fi (`Notifications.jsx`):** TbAppBar (通知 + sub) → 中央配置の Empty illustration (Bell icon 大) + 「通知はまだありません」見出し + 「メッセージへの返信や開封通知がここに届きます」副文。実データなし
- **Settings tab (`Settings.jsx`):** TbAppBar (設定) → inbox_settings_form element (Phase 6 完成) → 区切り → block list section (block_list element、Phase 6 完成) → 退会リンク → footer TabBar
- **TabBar Active state:** `$activeTab` 変数を controller から `$this->set()`、element 側で `<a class="tb-tabbar__item <?= $active === 'inbox' ? 'is-active' : '' ?>">` 形式
- **Unread dot:** dashboard controller が既に未読 count を計算しているか確認、なければ `Messages` から `opened_at IS NULL` の COUNT 1 クエリ追加
- **Reveal fade-in:** `@keyframes tb-fade-in { 0% { opacity: 0.4; transform: translateY(4px); } 100% { opacity: 1; transform: none; } }` の 400ms ease

</specifics>

<deferred>
## Deferred Ideas

- Reveal の 3D rotateX 封筒オープン演出 → v3 (MOTION-X1)
- Full-screen Reveal page (`RevealHit.jsx` 全体 305 行を独立画面化) → v3 候補。本 phase は dashboard inline details 内の sender カード演出に限定
- Discover / Notifications のバックエンド機能本体 → v3 (DISC-01 / NOTIF-01)
- Settings の独立画面化に伴う dashboard data-flow 簡素化 ($inbox / $blocks を dashboard controller で渡さなくする) → 本 phase 内で実施判断、コスト次第で plan 末尾 or Phase 8 持ち越し
- Onboarding / Login 独立画面 / Help / Terms / Share → v3
- Desktop ブレイクポイント → v3 (DESKTOP-*)
- Send 4 エラー画面 (EDGE-01..04) + Block 確認モーダル (EDGE-05) + press scale (MOTION-01) → Phase 8

</deferred>

---

*Phase: 07-dashboard-4-reveal*
*Context gathered: 2026-05-13 (auto mode via /gsd-discuss-phase 7 --auto)*
