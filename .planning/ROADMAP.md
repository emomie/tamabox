# Roadmap: tamabox

## Milestones

- ✅ **v1 MVP** — Phases 1-4 (shipped 2026-05-13) — see `.planning/milestones/v1-ROADMAP.md`
- 🚧 **v2 Calm Gacha UI / 4-tab 構造** — Phases 5-8 (in progress)

## Phases

<details>
<summary>✅ v1 MVP (Phases 1-4) — SHIPPED 2026-05-13</summary>

- [x] Phase 1: Foundation & Schema (4/4 plans) — completed 2026-04-22
- [x] Phase 2: Bluesky OAuth & Identity (4/4 plans) — verified 2026-04-24
- [x] Phase 3: Inbox, Message & SSR Reveal (4/4 plans) — verified 2026-04-26
- [x] Phase 4: Moderation & Production Launch (3/3 plans) — verified 2026-05-13

Full archive: `.planning/milestones/v1-ROADMAP.md`

</details>

### 🚧 v2 Calm Gacha UI / 4-tab 構造 (In Progress)

**Milestone Goal:** handoff_tamabox の Calm Gacha デザインシステムを tamabox v1 に注入し、4 タブ構造化と最小限の Reveal 演出まで到達させる UI/UX overhaul。バックエンド機能の新規追加なし。PHP テンプレート / CSS / 最小限 JS のみ。

- [ ] **Phase 5: Design System Foundation** — Calm Gacha トークン・共通コンポーネント整備
- [ ] **Phase 6: v1 画面の Calm Gacha 化** — 既存 7 画面 + AvatarHandleChip を hi-fi に置換
- [ ] **Phase 7: Dashboard 4 タブ分離 + Reveal 演出** — 受信/発見/通知/設定タブ構造化 + fade-in 開封演出
- [ ] **Phase 8: エッジケース & マイクロインタラクション** — Send エラー 4 種 + Block モーダル + press scale

## Phase Details

### Phase 5: Design System Foundation
**Goal**: Calm Gacha デザイントークンと共通コンポーネントが全画面に注入され、視覚的な統一基盤が成立している
**Depends on**: Phase 4 (v1 shipped)
**Requirements**: DS-01, DS-02, DS-03, DS-04, DS-05, DS-06
**Success Criteria** (what must be TRUE):
  1. `tokens.css` が `webroot/css/` に存在し `layout/default.php` でロードされ、既存ページが崩れていない
  2. 全画面で Noto Sans JP + JetBrains Mono が描画され、`--tb-font-*` 変数が有効になっている
  3. `.tb-btn` の 5 variant (primary / ghost / quiet / disabled / danger) がブラウザ inspector で確認できる
  4. `.tb-card` / `.tb-card-soft` / `.tb-letter` / `.tb-chip` / `.tb-input` / `.tb-tabbar` / `.tb-appbar` が CSS に定義されテンプレートから参照できる
  5. 既存ページのターコイズ / 蜂蜜 / 紙基調の配色が v1 と視覚的に異なり、Calm Gacha カラーパレットに切り替わっている
**Plans**: TBD
**UI hint**: yes

### Phase 6: v1 画面の Calm Gacha 化
**Goal**: v1 で稼働している全ユーザー向け画面が hi-fi デザインに視覚一致し、既存機能が損なわれていない
**Depends on**: Phase 5
**Requirements**: UI-01, UI-02, UI-03, UI-04, UI-05, UI-06, UI-07, UI-08
**Success Criteria** (what must be TRUE):
  1. Home 画面が hi-fi `Home.jsx` レイアウトに視覚一致する (AppBar / ヒーロー / CTA 配置)
  2. Send フォームが hi-fi `Send.jsx` に一致し、welcome メッセージが TbLetter カードで表示される
  3. SendDone / Report / Delete 各画面が hi-fi の対応スクリーンに一致する
  4. Settings フォーム (probability slider 含む) が hi-fi `Settings.jsx` に一致し、確率変更が引き続き動作する
  5. Block List が TbChip / TbCard パターンで再描画され、AvatarHandleChip が hi-fi TbChip variant に一致する
**Plans**: TBD
**UI hint**: yes

### Phase 7: Dashboard 4 タブ分離 + Reveal 演出
**Goal**: Dashboard が 4 タブ (受信 / 発見 / 通知 / 設定) に分離され、受信タブで Reveal fade-in 演出が動作する
**Depends on**: Phase 6
**Requirements**: NAV-01, NAV-02, NAV-03, NAV-04, NAV-05, NAV-06, MOTION-02, MOTION-03
**Success Criteria** (what must be TRUE):
  1. Dashboard に TabBar が表示され、4 タブ (受信 / 発見 / 通知 / 設定) が切り替えられる
  2. TabBar のアクティブタブがターコイズでハイライトされ、未読がある場合に受信タブにドットが表示される
  3. 受信タブが hi-fi `Dashboard.jsx` レイアウトで受信リストを表示し、既存の Reveal 開封が引き続き動作する
  4. Reveal 開封時に `.is-opening` クラスで fade-in 400ms ease が実行され、HIT 結果が hi-fi `RevealHit.jsx` の sender カード演出に一致する
  5. 発見タブ・通知タブが hi-fi の Empty state 骨格を表示する (機能は v3 候補)
  6. 設定タブが inbox 設定 + block list を hi-fi `Settings.jsx` レイアウトで表示する
**Plans**: TBD
**UI hint**: yes

### Phase 8: エッジケース & マイクロインタラクション
**Goal**: Send フローの 4 エラーケースと Block 確認モーダルが hi-fi に一致し、全ボタンに press scale フィードバックが効く
**Depends on**: Phase 7
**Requirements**: MOTION-01, EDGE-01, EDGE-02, EDGE-03, EDGE-04, EDGE-05
**Success Criteria** (what must be TRUE):
  1. 存在しない slug への Send アクセスが hi-fi `SendNotFound` レイアウトで表示される
  2. 受信停止中 inbox への Send フォームが hi-fi `SendInboxClosed` で表示される
  3. 2000 字を超えるとカウンタが `--tb-warm-700` 色に変わり、超過範囲がハイライトされ、CTA が disabled になり、「長すぎます」チップが上部に表示される
  4. POST 失敗時に hi-fi `SendFailed` エラー画面が表示される
  5. Block 確認モーダルが bottom sheet で影響範囲 3 点リストと danger / cancel ボタンを hi-fi 一致で表示する
  6. 全 `.tb-btn` で `:active` 時に `scale(0.985)` 80ms フィードバックが視覚的に確認できる
**Plans**: TBD
**UI hint**: yes

## Progress

**Execution Order:** 5 → 6 → 7 → 8

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Foundation & Schema | v1 | 4/4 | Complete | 2026-04-22 |
| 2. Bluesky OAuth & Identity | v1 | 4/4 | Complete | 2026-04-24 |
| 3. Inbox, Message & SSR Reveal | v1 | 4/4 | Complete | 2026-04-26 |
| 4. Moderation & Production Launch | v1 | 3/3 | Complete | 2026-05-13 |
| 5. Design System Foundation | v2 | 0/TBD | Not started | - |
| 6. v1 画面の Calm Gacha 化 | v2 | 0/TBD | Not started | - |
| 7. Dashboard 4 タブ分離 + Reveal 演出 | v2 | 0/TBD | Not started | - |
| 8. エッジケース & マイクロインタラクション | v2 | 0/TBD | Not started | - |

---
*Last updated: 2026-05-13 — v2 Calm Gacha UI roadmap created (Phases 5-8)*
