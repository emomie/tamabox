# Phase 8: エッジケース & マイクロインタラクション - Context

**Gathered:** 2026-05-13
**Status:** Ready for planning
**Mode:** Auto-generated (`/gsd-discuss-phase 8 --auto`) — Claude が推奨デフォを選択

<domain>
## Phase Boundary

v2 milestone の最終 phase。Send フローの 4 エラー画面 (EDGE-01 404 / EDGE-02 受信停止 / EDGE-03 文字数オーバー / EDGE-04 送信失敗) を hi-fi `~/projects/handoff_tamabox/screens/SendErrors.jsx` に視覚一致させ、Block 確認モーダル (EDGE-05) を bottom sheet 形式の hi-fi 一致で実装、全 `.tb-btn` に `:active` 時の `scale(0.985)` 80ms フィードバック (MOTION-01) を効かせる。さらに Phase 7 で deferred された artifacts (`dashboard.php` ~18 inline `style="..."` の CSS class 化、`UsersController::dashboard()` の `$inbox` / `$blocks` view data 撤去、`#FBFCFD` undocumented hex の locked decision 化、`margin-top: 3px` 系の文書化) を本 phase 末で cleanup する。本 phase 完了で v2 milestone の 28 requirements 全てが ship 可能状態に到達する。

</domain>

<decisions>
## Implementation Decisions

### Area 1 — EDGE-01: Send 404 (`SendNotFound`)

- **D-01:** 404 path の実装はカスタム ExceptionRenderer 経由ではなく、**`templates/Error/error400.php` の rewrite** で hi-fi `SendNotFound` レイアウトに統一。/{slug} 404 専用ではなく全 404 で hi-fi 表示にする (single-pattern simplification)。Phase 5 で deferred された error layout migration を本 phase で回収
- **D-02:** Layout は `templates/layout/error.php` (Phase 5 で既に Calm Gacha 化済) を継続使用。error400.php の body だけを hi-fi SendNotFound 構造 (中央配置 illustration + 見出し「箱が見つかりません」+ 説明文 + 戻る CTA) に書き換える
- **D-03:** error500 はスコープ外 (v3 候補)。本 phase は 404 のみカバー

### Area 2 — EDGE-02: 受信停止中 (`SendInboxClosed`)

- **D-04:** `is_accepting=false` の場合の表示は現在 `templates/Messages/send.php` 内の `<?php if ($isAccepting): ?>` 分岐の else 経路で起きている。**else 経路の markup を hi-fi `SendErrors :: SendInboxClosed` 構造に書き換え**: TbAppBar + 中央配置 illustration + 「この箱は今は閉まっています」見出し + 受信者 handle + back CTA
- **D-05:** 既存の `$inbox->user->display_name` データフローを再利用、controller 変更なし

### Area 3 — EDGE-03: 文字数オーバー (>2000)

- **D-06:** 既存の `maxlength="2000"` 属性 + サーバー側 `mb_strlen($body) > 2000` ガード (`MessagesController::processSend`) は維持。本 phase の追加は **JS による live 視覚フィードバック**: counter 色変更、超過範囲ハイライト、CTA disabled、上部「長すぎます」チップ
- **D-07:** 実装は `webroot/js/send-counter.js` (新規、~50 lines)。`<textarea name="body">` の `input` イベントで length を取得、2000 超過時に: (a) `.tb-send__counter` を `--tb-warm-700` 色化、(b) textarea に `.is-overflow` クラス付与 (CSS で超過部分のグラデハイライト)、(c) submit ボタンに `disabled` 属性、(d) `.tb-send__overflow-chip` を form の上部に display:flex で表示
- **D-08:** 超過範囲ハイライトは CSS の `background: linear-gradient` で実装。textarea で「特定範囲」をマークすることは難しいので、`.is-overflow::after` または隣接 ghost element でビジュアルを近似 (hi-fi の SendErrors を見て plan-phase で詳細決定)
- **D-09:** JS が無効/失敗してもサーバーガードが効く (defense in depth)。プログレッシブエンハンスメント

### Area 4 — EDGE-04: 送信失敗 (`SendFailed`)

- **D-10:** 現在の POST 失敗時挙動 (Flash error + redirect to GET `/{slug}`) を **render-pattern に変更**: `MessagesController::processSend` の `catch (RuntimeException $e)` 経路で `return $this->render('send_failed')` する (Flash 経路は維持、`is_accepting=false` redirect は維持して `send_failed` は POST 例外のみ対象)
- **D-11:** 新規 template `templates/Messages/send_failed.php` を作成。hi-fi `SendErrors :: SendFailed` 構造: TbAppBar + 中央 illustration + 「送信できませんでした」見出し + 説明 + 再試行 CTA (→ `/{slug}`)
- **D-12:** その他の Flash error (consent 未チェック / body 空 / 2000 字超え) は EDGE-04 の対象外。これらは UX-wise に form に戻ってバナー表示の方が自然 (誤入力 retry path)。本 phase で send.php の Flash バナー (`templates/element/flash/error.php` 経由) を hi-fi に視覚一致させる task は plan 末尾で判断

### Area 5 — EDGE-05: Block 確認モーダル

- **D-13:** Block POST (`/block/{senderUserId}`) を直接実行ではなく、**bottom sheet モーダル** 経由に変更。現在 dashboard の HIT 結果 sender カードに Block ボタンがあり、それを click したら hi-fi `Block.jsx` の bottom sheet が立ち上がる構造
- **D-14:** モーダル実装は **CSS + minimal JS** (no React/Vue): `<dialog>` 要素 (native HTML5 dialog) + `webroot/js/block-modal.js` (~40 lines) で open/close 制御。`<form action="/block/{id}" method="post">` を dialog 内に内包、cancel ボタンは `dialog.close()`、danger ボタンは form submit
- **D-15:** モーダル content は hi-fi `Block.jsx` 構造: bottom sheet (画面下からスライドアップ) + 「@handle をブロックしますか?」見出し + 影響範囲 3 点リスト (1. このユーザーからの新規メッセージを受け付けない、2. 既存のメッセージは残る、3. いつでも解除可能) + danger / cancel ボタン
- **D-16:** Element 化: `templates/element/tb_block_modal.php` (新規、~50 lines)。引数 `['sender_handle' => ..., 'sender_user_id' => ..., 'message_id' => ...]`。`$this->element('tb_block_modal', ...)` で dashboard.php / Reveal 経路 から呼ぶ
- **D-17:** Modal trigger は `data-block-modal-trigger` 属性 + `data-block-modal-id` で対象 modal を指定。JS が click を listen して `dialog.showModal()`

### Area 6 — MOTION-01: 全 `.tb-btn` press scale

- **D-18:** CSS のみで実装、JS なし。`tamabox.css` Phase 8 section に `.tb-btn:active { transform: scale(0.985); transition: transform 80ms ease; }` を追加
- **D-19:** `prefers-reduced-motion: reduce` 条件下では transform 無効化 (Phase 7 M-02 の続きで a11y 一貫性)
- **D-20:** `.tb-icon-btn` にも適用するか plan-phase で判断 (hi-fi が icon button を press scale するなら適用、しないなら見送り)

### Area 7 — Phase 7 deferred cleanup

- **D-21:** `templates/Users/dashboard.php` の ~18 inline `style="..."` 属性を CSS class に抽出。Phase 7 で hi-fi 速度優先で inline 化したものを `.tb-dash-*` 系 class に整理 (e.g., `.tb-dash-msg-row__opened-hit`、`.tb-dash-msg-row__hit-sender-card`) — Phase 8 末の plan で実施
- **D-22:** `UsersController::dashboard()` の `$inbox` / `$blocks` view data 撤去 (Phase 7 D-04 deferred)。dashboard template から settings aside が消えたので不要、テスト更新も必要なら同 commit
- **D-23:** `#FBFCFD` (unread row tint at `tamabox.css:2127`) を locked decision として `08-CONTEXT.md` (本ファイル) に文書化 — Phase 8 §I CSS section に追加する時点で記載
- **D-24:** `margin-top: 3px` 等の Phase 7 §H 内 minor off-grid を確認、必要なら locked decision として補完文書化

### Area 8 — バックエンド変更範囲

- **D-25:** 本 phase は Phase 7 D-19 の精神を継承。`MessagesController::processSend` の `catch` 内 render 切替 (D-10) は許可される (既存ロジックの failure-mode 変更ではなく display 切替)。新規 controller action は追加しない (modal は dialog element で完結、POST 先は既存 `/block/{id}` を再利用)
- **D-26:** Model / Migration / OAuth / SSR ロジック / モデレーション本体 は依然 touch 禁止
- **D-27:** `composer test` 199 tests / 0 failures グリーンを維持。`send_failed` template の rendering test を新規追加 (RuntimeException モック経由)、`block_modal` element の rendering test (引数を渡して output を assert)、EDGE-01 error400 の rendering test を加算する想定

### Area 9 — Hi-fi 一致判定の継承

- **D-28:** Phase 5-7 の全 locked decisions を継承 (typography override / spacing exceptions / home 30px / half-pixel rounding)
- **D-29:** SendErrors.jsx (416 行) は 4 variant が 1 file 内に存在。各 PHP template で該当 variant 部分のみ移植
- **D-30:** Block.jsx (112 行) は bottom sheet の完成形リファレンス

### Claude's Discretion

- error400 を /{slug} 404 専用 vs グローバル 404 にするかは plan-phase で codebase 影響範囲確認後に最終判断 (現在の方針は D-01 で「グローバル 404 を hi-fi に統一」だが、他 controller の 404 paths を見て変更余地)
- `send_failed.php` を独立 template にするか send.php 内分岐で吸収するかは plan-phase 判断 (独立推奨、retry CTA URL が異なるため)
- Block modal の dialog vs カスタム overlay は dialog 推奨 (native a11y + ESC 閉じ)、ただし IE11 サポート要不要は plan-phase で確認 (Lolipop の利用層が IE11 を含む可能性は低いが、対象ブラウザ範囲は CONTEXT 5/6/7 では明示されていない)
- Phase 7 cleanup (D-21..D-24) を Phase 8 plan の最末 1 plan に集約 vs 関連 plan に分散するかは plan-phase で判断 (集約推奨、PR review しやすい)

### Locked Decisions (Phase 5-7 から継承)

- **Typography Override** (Phase 5): 8 sizes (22/18/16/15/14/12/11/10) / 4 weights (400/500/600/700)
- **Spacing Exceptions** (Phase 5): `.tb-chip gap: 6px` / `.tb-input padding: 14px` / `.tb-card padding: 18px`
- **Home display title 30px** (Phase 6): `.tb-home__title` セレクタ単独
- **Half-pixel rounding** (Phase 6 D-22, D-23): hi-fi half-pixel font-sizes は locked scale に丸める
- **4 タブ SSR-pure routing** (Phase 7 D-01): `/dashboard`, `/dashboard/discover`, `/dashboard/notifications`, `/dashboard/settings`
- **`prefers-reduced-motion` 対応** (Phase 7 M-02): 全 animation で reduced-motion ユーザー向けに opt-out

### Locked Decision — Phase 7 deferred color exception (Phase 8 追記)

- **Scope:** `.tb-dash-msg-row[data-state="unread"]` の row tint
- **Value:** `background: #FBFCFD;` — pure white より極わずかに blue 寄りの紙色、unread row を視覚的に区別する微差
- **Justification:** hi-fi `Dashboard.jsx` の unread row 表現で使用。Calm Gacha tokens の `--tb-paper` (#FFFFFF) と `--tb-paper-soft` (#F8F4EC、honey paper) の中間が必要だが既存トークンで近似値が無い。新トークン `--tb-paper-cold` を追加する案もあるが、現状 1 箇所限定使用なので逐次拡大時に再判断 (ad-hoc 追加禁止ポリシーは継承)
- **Scope-of-override:** 本セレクタのみ。他で同色を使う場合は新トークン化を検討

### Locked Decision — Acknowledged sub-grid micro-offset (Phase 8 D-24)

- **Scope:** `.tb-dash-box__url` (tamabox.css §H.3), `.tb-reveal-hit-card__title` (§I.7), `.tb-reveal-miss-card__title` (§I.7)
- **Value:** `margin-top: 3px` (1px off the 4-grid spacing system)
- **Justification:** matches hi-fi `Dashboard.jsx` / `RevealHit.jsx` / `Reveal.jsx` typographic optical baseline — the 4px nominal would create a visually-too-loose gap between the small kicker label and the larger title beneath. The 3px keeps the title visually anchored without making the kicker appear glued.
- **Scope-of-override:** these 3 selectors only. New 3px values require fresh review against the 4-grid policy.
- **Locked at:** Plan 08-07 (Phase 7 deferred cleanup carry-over)

### Locked Decision — Single-use literal hex values (Phase 8 §I)

- **Scope (3 locations):**
  - `#F0DCA8` — reused from Phase 7 §H.5 (warm-card border), also used in §I.3 inbox-closed status block border.
  - `#FFFBEF` — §I.4 textarea overflow background (cream tint of warm-100).
  - `#EFD5D2` — §I.5 send-failed banner border (edge tone of `--tb-danger-bg`).
- **Justification:** each value is hi-fi-required and used exactly once (or in the case of #F0DCA8, twice in the same edge-tone role). Promoting to `--tb-*` tokens would expand the design-token surface without payoff. If a second non-edge consumer arises for any value, promote at that time.
- **Scope-of-override:** these specific selectors only.

### Locked Decision — Single-use rgba literals (Phase 8 §I, post-UI-REVIEW)

- **Scope (2 locations):**
  - `rgba(20, 28, 32, 0.42)` — `.tb-block-modal::backdrop` modal backdrop wash (§I.6). Hi-fi-pinned from `RevealHit.jsx` `BlockConfirmModal` backdrop value.
  - `rgba(217, 162, 60, 0.10)` — `.tb-reveal-hit-card__corner-glyph` color (§I.7). Decorative 10%-alpha corner-✦ glyph; lifted from `dashboard.php` inline style in Phase 7 audit Recommendation §2 follow-up.
- **Justification:** both are decorative-only, single-use, and hi-fi-pinned. The backdrop is intrinsically a modal-only concern (no second consumer expected); the 10%-alpha glyph color is intentionally edge-faded relative to the warm-500 sibling so promotion to a token would suggest a reuse that does not exist.
- **Scope-of-override:** these specific selectors only. New rgba literals require fresh review.
- **Locked at:** 08-UI-REVIEW Pillar 2 follow-up (v2 milestone close).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase 5-7 outputs
- `.planning/phases/05-design-system-foundation/05-UI-SPEC.md` — `.tb-*` component contract
- `.planning/phases/06-v1-calm-gacha/06-UI-SPEC.md` — 8 PHP screens contract (send.php contract is here)
- `.planning/phases/07-dashboard-4-reveal/07-UI-SPEC.md` — Dashboard / TabBar / Reveal motion contract
- `.planning/phases/07-dashboard-4-reveal/07-VERIFICATION.md` — Phase 7 PASS state
- `.planning/phases/07-dashboard-4-reveal/07-REVIEW-FIX.md` — fix carry-over (deferred IN-01/IN-02/IN-03)
- `webroot/css/tamabox.css` — all `.tb-*` component CSS (§5 Phase 5、§G Phase 6、§H Phase 7)

### Project-level
- `.planning/PROJECT.md` — v2 milestone goal
- `.planning/REQUIREMENTS.md` §EDGE / §MOTION — EDGE-01..05 + MOTION-01
- `.planning/ROADMAP.md` — Phase 8 entry (6 success criteria)

### Hi-fi design source
- `~/projects/handoff_tamabox/screens/SendErrors.jsx` (416 行) — 4 error variants
  - SendNotFound (EDGE-01)
  - SendInboxClosed (EDGE-02)
  - SendOverflow (EDGE-03 reference for chip + counter color)
  - SendFailed (EDGE-04)
- `~/projects/handoff_tamabox/screens/Block.jsx` (112 行) — Block confirm modal (EDGE-05)
- `~/projects/handoff_tamabox/components.jsx` — TbButton / TbCard / TbChip / TbAppBar 既存使用

### CakePHP integration points
- `src/Controller/MessagesController.php` (~280 行) — `send` action + `processSend` private method
- `src/Controller/BlocksController.php` — POST `/block/{id}` 既存 (modal の form submit 先)
- `templates/Error/error400.php` — CakePHP デフォルト 404 template、本 phase で rewrite
- `templates/layout/error.php` — Phase 5 で Calm Gacha 化済、継続使用

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets (Phase 5-7 完成済)
- `webroot/css/tamabox.css` の `.tb-btn` (5 variant) / `.tb-card` / `.tb-chip` / `.tb-letter` / `.tb-appbar` — Send error 4 画面 + Block modal 全部これで賄える
- `templates/element/icon.php` — illustration 用の large SVG を流用可能 (back / close / letter / heart など)
- `webroot/js/reveal-motion.js` の class-toggle pattern — `webroot/js/send-counter.js` と `webroot/js/block-modal.js` の参考実装

### Target Files (this phase will create / rewrite)
**新規作成:**
- `templates/Messages/send_failed.php` (~30 lines) — EDGE-04
- `templates/element/tb_block_modal.php` (~50 lines) — EDGE-05
- `webroot/js/send-counter.js` (~50 lines) — EDGE-03 live feedback
- `webroot/js/block-modal.js` (~40 lines) — EDGE-05 dialog control

**リライト:**
- `templates/Error/error400.php` (デフォルト → hi-fi SendNotFound) — EDGE-01
- `templates/Messages/send.php` の `<?php if ($isAccepting): ?>` else 経路 (現状簡素) → hi-fi SendInboxClosed — EDGE-02
- `templates/Messages/send.php` form 上部 + counter section に overflow chip + counter color logic 用 markup 追加 — EDGE-03
- `templates/Users/dashboard.php` の Block ボタン → modal trigger 化 + element 呼び出し追加 — EDGE-05

**バックエンド:**
- `src/Controller/MessagesController.php::processSend` の catch 経路を render 切替 (D-10)

**追加:**
- `tamabox.css` Phase 8 §I section (~200 lines 想定) — `.tb-btn:active` press scale、send error variants 用 layout、`.is-overflow` highlight、bottom-sheet `<dialog>` styling
- `tests/TestCase/Controller/MessagesControllerTest.php` — send_failed render test、404 routing test
- 各 view rendering test を element 単位で
- `dashboard.php` inline style → CSS class 抽出 (Phase 7 cleanup、Phase 8 最末 plan)

### Established Patterns
- Phase 5-7 で確立した `tb_*` element 抽出ポリシー (YAGNI: 2+ 使用予定で抽出)
- Phase 7 で確立した `tamabox.css` の section コメント (`/* ======== Phase N — ... ======== */`)
- defensive coding: defense in depth (D-09)、CakePHP CSRF/Form helper 維持

### Integration Points
- `error400.php` rewrite は CakePHP の DebugKit / debug=false 経路で確実に表示されるか確認 (Phase 1 で `debug=false` 固定化済)
- Block modal の dialog element は dashboard.php に複数 modal を出すケース (複数 sender からの message が並ぶ) を考慮、ID 衝突回避のため modal id に `$msg->id` を含める
- `prefers-reduced-motion` メディアクエリは tamabox.css §I で `.tb-btn:active` の transform を抑止

</code_context>

<specifics>
## Specific Ideas

- **SendNotFound (EDGE-01):** TbAppBar + ターコイズ大型 SVG (lost mark) + 「箱が見つかりません」+ 「URL が間違っているか、退会された可能性があります」+ Home に戻る CTA
- **SendInboxClosed (EDGE-02):** TbAppBar + 受信者 handle ("@xxx") + 「この箱は今は閉まっています」+ 「受信者が一時的に受付を停止しています」+ 戻る CTA (どこに戻る? plan-phase で判断、history.back() or `/`)
- **SendOverflow chip (EDGE-03):** form 上部の chip は `.tb-chip danger` variant + 「長すぎます」 + 該当文字数表示。textarea のハイライトは `linear-gradient(transparent 0% maxRatio%, rgba(216,82,82,0.18) maxRatio% 100%)` の overlay (位置精度は近似)
- **SendFailed (EDGE-04):** TbAppBar + 中央 illustration (✗ icon or 雲モチーフ) + 「送信できませんでした」+ 「時間をおいて再度お試しください」+ 再試行 CTA (`/{slug}` への link)
- **Block modal (EDGE-05):** bottom sheet (CSS: `position: fixed; bottom: 0; border-radius: 16px 16px 0 0;`)、影響範囲 3 点リストは `<ul>` + checkmark icon、ボタン横並び (cancel ghost / danger primary 逆配置でも hi-fi 準拠)
- **MOTION-01:** CSS 一行で完結、テスト不要 (CSS rendering test は overkill)

</specifics>

<deferred>
## Deferred Ideas (v3 候補)

- error500 hi-fi 一致 (本 phase は 404 のみ)
- Send error の i18n (現状 ja のみ)
- Block modal の rich animation (slide-up + backdrop fade) — 本 phase は dialog の default transition で許容
- 3D rotateX 封筒オープン演出 (MOTION-X1)
- Onboarding / Login 独立 / Help / Terms / Share / Discover backend / Notifications backend — 全 v3
- Desktop ブレイクポイント — v3 (DESKTOP-*)

</deferred>

---

*Phase: 08-edge-microinteractions*
*Context gathered: 2026-05-13 (auto mode via /gsd-discuss-phase 8 --auto)*
*v2 milestone 最終 phase — 完了で全 28 requirements ship 可能*
