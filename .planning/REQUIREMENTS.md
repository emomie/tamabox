# Requirements: tamabox — v2 Calm Gacha UI / 4-tab 構造

**Defined:** 2026-05-13
**Core Value:** 「確率で名前がバレる」仕組みが悪意送信者の自己抑止になり、好意送信者にはガチャ的祝福演出となる匿名メッセージ箱 (V1 仮説)。
**Milestone scope:** handoff_tamabox の hi-fi デザインシステムを tamabox v1 (live `tamabox.emomie.com`) に注入し、Dashboard を 4 タブ構造化、最小限の Reveal 演出まで到達させる UI/UX overhaul。バックエンド機能の新規追加なし。

> v1 (Phase 1-4) で shipped 済みの 34 件は `.planning/milestones/v1-REQUIREMENTS.md` を参照。

---

## v2 Requirements

### Design System (DS)

- [ ] **DS-01**: `tokens.css` が `webroot/css/` に配置され `layout/default.php` で chain ロードされる
- [ ] **DS-02**: Noto Sans JP + JetBrains Mono が Google Fonts 経由でロードされ `--tb-font-*` 経由で全画面に適用される
- [ ] **DS-03**: 共通ボタン `.tb-btn` が 5 variant (primary / ghost / quiet / disabled / danger) で `tamabox.css` に存在する
- [ ] **DS-04**: カードユーティリティ `.tb-card` / `.tb-card-soft` / `.tb-letter` が定義され、テンプレートから使用できる
- [ ] **DS-05**: `.tb-chip` (3 tone) / `.tb-input` (4 state) / `.tb-tabbar` / `.tb-appbar` がベースコンポーネントとして提供される
- [ ] **DS-06**: 既存 `tamabox.css` の `:root` CSS variables が Calm Gacha 値 (ターコイズ / 蜂蜜 / 紙基調 / Noto) に置き換わり、全画面に視覚的に反映される

### UI Screen Migration (UI)

- [ ] **UI-01**: Home (`Pages/home.php`) が hi-fi `screens/Home.jsx` レイアウトに視覚一致する
- [ ] **UI-02**: Send Form (`Messages/send.php`) が hi-fi `screens/Send.jsx` に一致し、welcome は TbLetter で表示される
- [ ] **UI-03**: Send Done (`Messages/send_done.php`) が hi-fi `screens/Done.jsx` に一致する
- [ ] **UI-04**: Settings form (`element/inbox_settings_form.php`) が hi-fi `screens/Settings.jsx` に一致する
- [ ] **UI-05**: Report Form (`Reports/create.php`) が hi-fi の report variant に一致する
- [ ] **UI-06**: Account Delete (`Account/delete.php`) が hi-fi `screens/ReportDelete.jsx` に一致する
- [ ] **UI-07**: Block List (`element/block_list.php`) が hi-fi の TbChip / TbCard パターンで再描画される
- [ ] **UI-08**: AvatarHandleChip (`element/avatar_handle_chip.php`) が hi-fi の TbChip variant に一致する

### Navigation / Tabs (NAV)

- [ ] **NAV-01**: Dashboard が 4 タブ構成 (受信 / 発見 / 通知 / 設定) で表示される
- [ ] **NAV-02**: TabBar がアクティブタブをターコイズでハイライトし、受信タブに未読数ドットを表示できる
- [ ] **NAV-03**: 受信タブが現 dashboard 受信リスト + Reveal を hi-fi `screens/Dashboard.jsx` レイアウトで表示する
- [ ] **NAV-04**: 発見タブが hi-fi の Discover 空骨格 (Empty state) を表示する (バックエンド未実装、v3 候補)
- [ ] **NAV-05**: 通知タブが hi-fi の `NotificationsEmpty` 空骨格を表示する (バックエンド未実装、v3 候補)
- [ ] **NAV-06**: 設定タブが既存の inbox 設定 + block list を hi-fi `screens/Settings.jsx` レイアウトで表示する

### Motion / Interaction (MOTION)

- [ ] **MOTION-01**: 全 `.tb-btn` で `:active` 時に `scale(0.985)` 80ms フィードバックが効く
- [ ] **MOTION-02**: Reveal 開封演出が `.is-opening` で fade-in 400ms ease を実行する (3D rotateX 封筒オープンは v3 候補)
- [ ] **MOTION-03**: Reveal HIT 結果が hi-fi `screens/RevealHit.jsx` の sender カード演出に一致する

### Edge Cases / Modals (EDGE)

- [ ] **EDGE-01**: Send 404 (slug 不在) が hi-fi `SendErrors :: SendNotFound` レイアウトで表示される
- [ ] **EDGE-02**: Send 受信停止中 (`is_accepting=false`) が hi-fi `SendErrors :: SendInboxClosed` で表示される
- [ ] **EDGE-03**: Send 文字数オーバー (>2000) でカウンタが `--tb-warm-700` に変色、超過範囲がハイライト表示、CTA は disabled、上部に「長すぎます」チップが出る
- [ ] **EDGE-04**: Send 送信失敗 (POST エラー) が hi-fi `SendErrors :: SendFailed` で表示される
- [ ] **EDGE-05**: Block 確認モーダル (RevealHit 直後) が bottom sheet で影響範囲 3 点リスト + danger / cancel の hi-fi 一致

---

## v3 Requirements (Deferred)

v2 に含めない、後続 milestone の候補。

### Onboarding & Static Pages

- **ONB-01**: 3-step onboarding (`screens/Onboarding.jsx` O1/O2/O3)
- **ONB-02**: Login 画面 (`screens/Onboarding.jsx :: Login`) を独立画面化
- **STATIC-01**: Help / FAQ 静的ページ (`screens/Help.jsx`)
- **STATIC-02**: Terms 静的ページ (`screens/Help.jsx :: Terms`)
- **SHARE-01**: Share 画面 (URL / QR / Bluesky 投稿、`screens/Share.jsx`)

### Discover / Notifications backend

- **DISC-01**: 発見タブのバックエンド機能本体実装
- **NOTIF-01**: 通知タブのバックエンド機能本体実装

### Motion 拡張

- **MOTION-X1**: Reveal 3D rotateX 封筒オープン演出 (現状 v2 では fade-in のみ採用)

### Desktop / Responsive

- **DESKTOP-01**: Desktop Public box (D1, `screens/Desktop.jsx :: DesktopPublic`)
- **DESKTOP-02**: Desktop Send form (D2)
- **DESKTOP-03**: Desktop Dashboard (D3, list + letter 2 カラム)

### Assets

- **ASSET-01**: Bluesky 公式 butterfly ロゴ差し替え (現状雲モチーフ仮置き)

---

## Out of Scope (v2 では明示的に除外)

| Feature | Reason |
|---------|--------|
| 新規バックエンド機能 (Discover / Notifications の機能本体) | v2 は UI overhaul に focus、バックエンド変更は v3 候補 |
| Desktop ブレイクポイント | モバイル 390×844 hi-fi 設計に集中、Desktop は v3 候補 |
| Bluesky 公式ロゴ取得 | handoff 自体も雲モチーフ仮置き、v3 で対応 |
| Onboarding / Help / Terms / Share 画面 | v2 のスコープを既存 v1 画面の Calm Gacha 化に絞る、v3 候補 |
| 3D rotateX 封筒オープン演出 | CakePHP SSR との整合性検証コスト回避、v2 は fade-in のみ |
| 新規 PHP / DB / OAuth 変更 | UI 注入だけで完結する設計、バックエンド変更は v3 以降 |
| 既存 v1 ロジック (SSR 抽選 / OAuth / モデレーション) の挙動変更 | UI のみ差し替え、機能挙動は v1 verified を維持 |
| アクセシビリティ強化 (WCAG audit, scoreカード等) | v3 候補。v2 では既存 `aria-*` / `:focus-visible` を継承 |

---

## Traceability

各 REQ → Phase マッピング。roadmapper 実行時に埋まる。

| Requirement | Phase | Status |
|-------------|-------|--------|
| DS-01 | TBD | Pending |
| DS-02 | TBD | Pending |
| DS-03 | TBD | Pending |
| DS-04 | TBD | Pending |
| DS-05 | TBD | Pending |
| DS-06 | TBD | Pending |
| UI-01 | TBD | Pending |
| UI-02 | TBD | Pending |
| UI-03 | TBD | Pending |
| UI-04 | TBD | Pending |
| UI-05 | TBD | Pending |
| UI-06 | TBD | Pending |
| UI-07 | TBD | Pending |
| UI-08 | TBD | Pending |
| NAV-01 | TBD | Pending |
| NAV-02 | TBD | Pending |
| NAV-03 | TBD | Pending |
| NAV-04 | TBD | Pending |
| NAV-05 | TBD | Pending |
| NAV-06 | TBD | Pending |
| MOTION-01 | TBD | Pending |
| MOTION-02 | TBD | Pending |
| MOTION-03 | TBD | Pending |
| EDGE-01 | TBD | Pending |
| EDGE-02 | TBD | Pending |
| EDGE-03 | TBD | Pending |
| EDGE-04 | TBD | Pending |
| EDGE-05 | TBD | Pending |

**Coverage:**
- v2 requirements: 28 total (DS×6, UI×8, NAV×6, MOTION×3, EDGE×5)
- Mapped to phases: 0 (awaiting roadmapper)
- Unmapped: 28 ⚠️ (will be resolved by /gsd-new-milestone roadmap step)

---
*Requirements defined: 2026-05-13*
*Last updated: 2026-05-13 — v2 milestone initial requirements*
