# Requirements: tamabox

**Last updated:** 2026-05-13 (v2 milestone closed, all 28 v2 requirements validated)
**Core Value:** 「確率で名前がバレる」仕組みが悪意送信者の自己抑止になり、好意送信者にはガチャ的祝福演出となる匿名メッセージ箱 (V1 仮説)。

> Shipped milestones:
> - v1 MVP (Phases 1-4, 34 requirements) — `.planning/milestones/v1-REQUIREMENTS.md`
> - v2 Calm Gacha UI / 4-tab 構造 (Phases 5-8, 28 requirements) — `.planning/milestones/v2-REQUIREMENTS.md`

---

## Validated (v2 — shipped 2026-05-13)

All 28 v2 requirements implemented at code level. Phase 5+6 smoke verified on production (`tamabox.emomie.com`, deploys `e420a78` / `1777c2a`); Phase 7+8 code complete at HEAD `960fc11`, awaits final `git push lolipop main` + 18-item smoke checklist.

Full archive with per-requirement deploy traceability: `.planning/milestones/v2-REQUIREMENTS.md`.

### Design System (DS)

- ✓ **DS-01**: `tokens.css` が `webroot/css/` に配置され `layout/default.php` で chain ロードされる
- ✓ **DS-02**: Noto Sans JP + JetBrains Mono が Google Fonts 経由でロードされ `--tb-font-*` 経由で全画面に適用される
- ✓ **DS-03**: 共通ボタン `.tb-btn` が 5 variant (primary / ghost / quiet / disabled / danger) で `tamabox.css` に存在する
- ✓ **DS-04**: カードユーティリティ `.tb-card` / `.tb-card-soft` / `.tb-letter` が定義され、テンプレートから使用できる
- ✓ **DS-05**: `.tb-chip` (3 tone) / `.tb-input` (4 state) / `.tb-tabbar` / `.tb-appbar` がベースコンポーネントとして提供される
- ✓ **DS-06**: 既存 `tamabox.css` の `:root` CSS variables が Calm Gacha 値 (ターコイズ / 蜂蜜 / 紙基調 / Noto) に置き換わる

### UI Screen Migration (UI)

- ✓ **UI-01**: Home (`Pages/home.php`) が hi-fi `screens/Home.jsx` レイアウトに視覚一致
- ✓ **UI-02**: Send Form (`Messages/send.php`) が hi-fi `screens/Send.jsx` に一致、welcome は TbLetter で表示
- ✓ **UI-03**: Send Done (`Messages/send_done.php`) が hi-fi `screens/Done.jsx` に一致
- ✓ **UI-04**: Settings form (`element/inbox_settings_form.php`) が hi-fi `screens/Settings.jsx` に一致
- ✓ **UI-05**: Report Form (`Reports/create.php`) が hi-fi の report variant に一致
- ✓ **UI-06**: Account Delete (`Account/delete.php`) が hi-fi `screens/ReportDelete.jsx` に一致
- ✓ **UI-07**: Block List (`element/block_list.php`) が hi-fi の TbChip / TbCard パターンで再描画
- ✓ **UI-08**: AvatarHandleChip (`element/avatar_handle_chip.php`) が hi-fi の TbChip variant に一致

### Navigation / Tabs (NAV)

- ✓ **NAV-01**: Dashboard が 4 タブ構成 (受信 / 発見 / 通知 / 設定) で表示
- ✓ **NAV-02**: TabBar がアクティブタブをターコイズでハイライト、未読数ドットを表示
- ✓ **NAV-03**: 受信タブが現 dashboard 受信リスト + Reveal を hi-fi `screens/Dashboard.jsx` で表示
- ✓ **NAV-04**: 発見タブが hi-fi の Discover 空骨格 (Empty state) を表示
- ✓ **NAV-05**: 通知タブが hi-fi の `NotificationsEmpty` 空骨格を表示
- ✓ **NAV-06**: 設定タブが既存の inbox 設定 + block list を hi-fi `screens/Settings.jsx` で表示

### Motion / Interaction (MOTION)

- ✓ **MOTION-01**: 全 `.tb-btn` で `:active` 時に `scale(0.985)` 80ms フィードバック
- ✓ **MOTION-02**: Reveal 開封演出が `.is-opening` で fade-in 400ms ease を実行
- ✓ **MOTION-03**: Reveal HIT 結果が hi-fi `screens/RevealHit.jsx` の sender カード演出に一致

### Edge Cases / Modals (EDGE)

- ✓ **EDGE-01**: Send 404 (slug 不在) が hi-fi `SendErrors :: SendNotFound` レイアウトで表示
- ✓ **EDGE-02**: Send 受信停止中 (`is_accepting=false`) が hi-fi `SendErrors :: SendInboxClosed` で表示
- ✓ **EDGE-03**: Send 文字数オーバー (>2000) で counter 変色 + chip + disabled CTA
- ✓ **EDGE-04**: Send 送信失敗 (POST エラー) が hi-fi `SendErrors :: SendFailed` で表示
- ✓ **EDGE-05**: Block 確認モーダル (RevealHit 直後) が bottom sheet で hi-fi 一致

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
- **MOTION-X2**: Block modal bottom-sheet slide-up animation (現状 native `<dialog>` instant open)

### Desktop / Responsive

- **DESKTOP-01**: Desktop Public box (D1, `screens/Desktop.jsx :: DesktopPublic`)
- **DESKTOP-02**: Desktop Send form (D2)
- **DESKTOP-03**: Desktop Dashboard (D3, list + letter 2 カラム)

### Assets

- **ASSET-01**: Bluesky 公式 butterfly ロゴ差し替え (現状雲モチーフ仮置き)

### A11y / Tech debt (carried over from v2 audit)

- **A11Y-01**: WCAG audit + score card formalization
- **A11Y-02**: `:focus-visible` outlines on 4 bespoke Phase 8 buttons (`.tb-send-failed__retry`, `.tb-send__closed-cta`, `.tb-block-modal__confirm`, `.tb-block-modal__cancel`)
- **TECH-01**: Phase 6 L-02 — `.tb-radio-tile__mark` empty `<span>` → `::before` pseudo (readability)
- **TECH-02**: Phase 6 IN-03 — tighten substring test-asserts to exact `.tb-*` class names
- **TECH-03**: Phase 7 IN-01 — replace substring assertions in 4 dashboard tab tests with structural assertions
- **TECH-04**: Phase 8 IN-02 — Block modal element should accept `$displayName` as parameter (currently re-derives from handle)
- **TECH-05**: Phase 5 UI-REVIEW — `.button` backward-compat geometry (legacy v1 shim, only used in old templates)

---

## Out of Scope (cross-milestone)

| Feature | Reason |
|---------|--------|
| **Google / メール認証** | SNS 性を重視するため意図的に不採用 (v1 decision) |
| **AI 悪意度判定 / NG ワードフィルター** | A2 事後通報のみ (v1 decision) |
| **送信頻度レート制限** | MVP 不採用、将来拡張 |
| **メッセージ本文暗号化** | 共有サーバー前提、通報レビュー運営要件、トークンのみ AES-GCM |
| **ネイティブアプリ (iOS / Android)** | Web only で完結 |
| **DB セッションストレージ** | PHP ファイルセッション、Lolipop 制約 |
| **SSR 殿堂ページ** | E2 仮説 (二次的晒し行為化) リスク |
| **グローバル BAN** | 受け手側ブロックのみ |

---

## Coverage History

| Milestone | Requirements | Status |
|-----------|--------------|--------|
| v1 MVP | 34 (AUTH×9, INBOX×6, MSG×8, MOD×4, INFRA×7) | Shipped 2026-05-13 |
| v2 Calm Gacha UI / 4-tab | 28 (DS×6, UI×8, NAV×6, MOTION×3, EDGE×5) | Shipped 2026-05-13 |
| **Total shipped** | **62** | |

---
*Last updated: 2026-05-13 — v2 milestone validated (28/28), v3 tech-debt carry-over enumerated*
