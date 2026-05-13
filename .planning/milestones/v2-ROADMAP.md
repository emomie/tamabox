# tamabox — v2 Roadmap

**Granularity**: coarse (4 phases)
**Coverage**: 28/28 v2 requirements mapped
**Created**: 2026-05-13
**Shipped**: 2026-05-13
**Status**: SHIPPED (code complete + Phase 5+6 smoke verified on `tamabox.emomie.com`; Phase 7+8 pending push)

## Milestone Goal

handoff_tamabox の Calm Gacha デザインシステムを tamabox v1 (live `tamabox.emomie.com`) に注入し、Dashboard を 4 タブ構造化、最小限の Reveal 演出まで到達させる UI/UX overhaul。バックエンド機能の新規追加なし — PHP テンプレート / CSS / 最小限 JS のみ。

## Phases

- [x] **Phase 5: Design System Foundation** — Calm Gacha トークン・共通コンポーネント整備 (completed 2026-05-13)
- [x] **Phase 6: v1 画面の Calm Gacha 化** — 既存 7 画面 + AvatarHandleChip を hi-fi に置換 (completed 2026-05-13)
- [x] **Phase 7: Dashboard 4 タブ分離 + Reveal 演出** — 受信/発見/通知/設定タブ構造化 + fade-in 開封演出 (completed 2026-05-13)
- [x] **Phase 8: エッジケース & マイクロインタラクション** — Send エラー 4 種 + Block モーダル + press scale (completed 2026-05-13)

## Phase Details

### Phase 5: Design System Foundation
**Goal**: Calm Gacha デザイントークンと共通コンポーネントが全画面に注入され、視覚的な統一基盤が成立している
**Depends on**: Phase 4 (v1 shipped)
**Requirements**: DS-01, DS-02, DS-03, DS-04, DS-05, DS-06
**Success Criteria**:
  1. `tokens.css` が `webroot/css/` に存在し `layout/default.php` でロードされ、既存ページが崩れていない
  2. 全画面で Noto Sans JP + JetBrains Mono が描画され、`--tb-font-*` 変数が有効になっている
  3. `.tb-btn` の 5 variant (primary / ghost / quiet / disabled / danger) がブラウザ inspector で確認できる
  4. `.tb-card` / `.tb-card-soft` / `.tb-letter` / `.tb-chip` / `.tb-input` / `.tb-tabbar` / `.tb-appbar` が CSS に定義されテンプレートから参照できる
  5. 既存ページのターコイズ / 蜂蜜 / 紙基調の配色が v1 と視覚的に異なり、Calm Gacha カラーパレットに切り替わっている
**Plans**: 5 plans (4 waves) — all complete 2026-05-13
  - [x] 05-01-PLAN.md — Token files + Google Fonts + layout chain (DS-01, DS-02)
  - [x] 05-02-PLAN.md — 13 icon SVG files + `element/icon.php` inline helper
  - [x] 05-03-PLAN.md — `:root` alias rewrite + `.tb-btn` 5 variants + `.tb-icon-btn` (DS-03, DS-06)
  - [x] 05-04-PLAN.md — `.tb-card` / `.tb-letter` / `.tb-input` states / `.tb-tabbar` dot / `.tb-appbar` (DS-04, DS-05)
  - [x] 05-05-PLAN.md — Legacy CSS cleanup + `composer test` + smoke verification checkpoint
**UI hint**: yes
**Production deploy**: `e420a78` (smoke verified 2026-05-13)

### Phase 6: v1 画面の Calm Gacha 化
**Goal**: v1 で稼働している全ユーザー向け画面が hi-fi デザインに視覚一致し、既存機能が損なわれていない
**Depends on**: Phase 5
**Requirements**: UI-01, UI-02, UI-03, UI-04, UI-05, UI-06, UI-07, UI-08
**Success Criteria**:
  1. Home 画面が hi-fi `Home.jsx` レイアウトに視覚一致する (AppBar / ヒーロー / CTA 配置)
  2. Send フォームが hi-fi `Send.jsx` に一致し、welcome メッセージが TbLetter カードで表示される
  3. SendDone / Report / Delete 各画面が hi-fi の対応スクリーンに一致する
  4. Settings フォーム (probability slider 含む) が hi-fi `Settings.jsx` に一致し、確率変更が引き続き動作する
  5. Block List が TbChip / TbCard パターンで再描画され、AvatarHandleChip が hi-fi TbChip variant に一致する
**Plans**: 8 plans — all complete 2026-05-13
  - [x] 06-01: AvatarHandleChip (UI-08)
  - [x] 06-02: Home (UI-01)
  - [x] 06-03: SendDone (UI-03)
  - [x] 06-04: Delete (UI-06)
  - [x] 06-05: Report (UI-05)
  - [x] 06-06: Settings form (UI-04)
  - [x] 06-07: Block list (UI-07)
  - [x] 06-08: Send form (UI-02)
**UI hint**: yes
**Production deploy**: `1777c2a` (smoke verified 2026-05-13)

### Phase 7: Dashboard 4 タブ分離 + Reveal 演出
**Goal**: Dashboard が 4 タブ (受信 / 発見 / 通知 / 設定) に分離され、受信タブで Reveal fade-in 演出が動作する
**Depends on**: Phase 6
**Requirements**: NAV-01, NAV-02, NAV-03, NAV-04, NAV-05, NAV-06, MOTION-02, MOTION-03
**Success Criteria**:
  1. Dashboard に TabBar が表示され、4 タブ (受信 / 発見 / 通知 / 設定) が切り替えられる
  2. TabBar のアクティブタブがターコイズでハイライトされ、未読がある場合に受信タブにドットが表示される
  3. 受信タブが hi-fi `Dashboard.jsx` レイアウトで受信リストを表示し、既存の Reveal 開封が引き続き動作する
  4. Reveal 開封時に `.is-opening` クラスで fade-in 400ms ease が実行され、HIT 結果が hi-fi `RevealHit.jsx` の sender カード演出に一致する
  5. 発見タブ・通知タブが hi-fi の Empty state 骨格を表示する (機能は v3 候補)
  6. 設定タブが inbox 設定 + block list を hi-fi `Settings.jsx` レイアウトで表示する
**Plans**: 8 plans — all complete 2026-05-13
  - [x] 07-01: TbTabBar element + routes
  - [x] 07-02: Discover + Notifications controllers (NAV-04, NAV-05 stubs)
  - [x] 07-03: Dashboard hi-fi rewrite + Settings GET render (NAV-03, NAV-06)
  - [x] 07-04: RevealHit sender card + reveal-motion.js (MOTION-02, MOTION-03)
  - [x] 07-05: Settings tab hi-fi layout (NAV-06)
  - [x] 07-06: Discover tab Empty-state stub (NAV-04)
  - [x] 07-07: Notifications tab Empty-state stub (NAV-05)
  - [x] 07-08: Phase 7 closer
**UI hint**: yes
**Production deploy**: pending push (HEAD `960fc11`)

### Phase 8: エッジケース & マイクロインタラクション
**Goal**: Send フローの 4 エラーケースと Block 確認モーダルが hi-fi に一致し、全ボタンに press scale フィードバックが効く
**Depends on**: Phase 7
**Requirements**: MOTION-01, EDGE-01, EDGE-02, EDGE-03, EDGE-04, EDGE-05
**Success Criteria**:
  1. 存在しない slug への Send アクセスが hi-fi `SendNotFound` レイアウトで表示される
  2. 受信停止中 inbox への Send フォームが hi-fi `SendInboxClosed` で表示される
  3. 2000 字を超えるとカウンタが `--tb-warm-700` 色に変わり、超過範囲がハイライトされ、CTA が disabled になり、「長すぎます」チップが上部に表示される
  4. POST 失敗時に hi-fi `SendFailed` エラー画面が表示される
  5. Block 確認モーダルが bottom sheet で影響範囲 3 点リストと danger / cancel ボタンを hi-fi 一致で表示する
  6. 全 `.tb-btn` で `:active` 時に `scale(0.985)` 80ms フィードバックが視覚的に確認できる
**Plans**: 8 plans — all complete 2026-05-13
  - [x] 08-01: §I CSS section + universal press scale (MOTION-01)
  - [x] 08-02: 404 hi-fi SendNotFound (EDGE-01)
  - [x] 08-03: SendInboxClosed hi-fi rewrite (EDGE-02)
  - [x] 08-04: Send overflow live feedback chip + counter (EDGE-03)
  - [x] 08-05: SendFailed render path on RuntimeException (EDGE-04)
  - [x] 08-06: Block confirm modal element + dashboard wiring (EDGE-05)
  - [x] 08-07: Phase 7 deferred cleanup (inline styles, $blocks, locked decisions)
  - [x] 08-08: Phase 8 closer / milestone wrap-up
**UI hint**: yes
**Production deploy**: pending push (HEAD `960fc11`)

## Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 5. Design System Foundation | 5/5 | Shipped (deployed `e420a78` + smoke verified) | 2026-05-13 |
| 6. v1 画面の Calm Gacha 化 | 8/8 | Shipped (deployed `1777c2a` + smoke verified) | 2026-05-13 |
| 7. Dashboard 4 タブ分離 + Reveal | 8/8 | Code complete + reviewed (deploy pending) | 2026-05-13 |
| 8. エッジケース & マイクロインタラクション | 8/8 | Code complete + reviewed (deploy pending) | 2026-05-13 |

## Coverage Summary

| Category | Count | Phase(s) |
|----------|-------|----------|
| DS (6) | DS-01..06 → Phase 5 | 5 |
| UI (8) | UI-01..08 → Phase 6 | 6 |
| NAV (6) | NAV-01..06 → Phase 7 | 7 |
| MOTION (3) | MOTION-02, MOTION-03 → Phase 7 / MOTION-01 → Phase 8 | 7, 8 |
| EDGE (5) | EDGE-01..05 → Phase 8 | 8 |

**Total**: 28/28 v2 requirements mapped, no orphans, no duplicates.

## Milestone Audit

See `.planning/v2-MILESTONE-AUDIT.md` (audited 2026-05-13, status: passed).

---
*Archived: 2026-05-13 — v2 milestone all 4 phases code complete, audit PASS, ready for production deploy of Phase 7+8.*
