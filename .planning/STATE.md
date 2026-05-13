---
gsd_state_version: 1.0
milestone: v2
milestone_name: Calm Gacha UI / 4-tab 構造
status: defining requirements
last_updated: "2026-05-13T09:25:00.000Z"
progress:
  total_phases: 0
  completed_phases: 0
  total_plans: 0
  completed_plans: 0
  percent: 0
---

# tamabox — STATE

Project memory. Updated by every gsd-* command.

## Project Reference

**Core Value**: 「確率で名前がバレる」仕組みが悪意送信者の自己抑止になり、好意送信者にとってはガチャ的祝福演出となる匿名メッセージ箱 (V1 仮説)。SSR 露出確率は受け手が 0〜100% で設定可能。

**Current Focus**: v2 — Calm Gacha UI / 4-tab 構造 milestone を開始。handoff_tamabox の hi-fi デザインシステムを tamabox v1 に注入し、Dashboard 4 タブ分離と最小限の Reveal 演出まで到達させる。バックエンド機能の新規追加なし。

> v1 MVP (Phase 1-4) は 2026-05-13 shipped。詳細は `.planning/milestones/v1-ROADMAP.md` および `.planning/MILESTONES.md` を参照。

**Granularity**: coarse
**Mode**: yolo
**Model Profile**: balanced

## Current Position

Phase: Not started (defining requirements)
Plan: —
Status: Defining requirements
Last activity: 2026-05-13 — Milestone v2 started

## Phase Status

(v2 phases will be populated after roadmap creation)

## Performance Metrics

| Metric | Value |
|--------|-------|
| Milestones shipped | 1 (v1 MVP, 2026-05-13) |
| Phases completed (cumulative) | 4/4 (v1 Phase 1-4) |
| Plans completed (cumulative) | 15/15 (v1) |
| Requirements shipped (cumulative) | 34/34 (v1: AUTH×9, INBOX×6, MSG×8, MOD×4, INFRA×7) |
| Current milestone phases | 0 (defining) |
| Current milestone plans | 0 |

### Plan Duration Log (carried from v1)

See `.planning/milestones/v1-ROADMAP.md` for the full Phase 1-4 plan duration history (15 plans / 22 days / 109 commits).

## Accumulated Context

### Key Decisions (carried from v1)

Carried from PROJECT.md Key Decisions — re-summarized here for quick reference:

- Web only(ネイティブアプリ非対応)
- Bluesky OAuth 先行 / X provider は v3+ で extend (OAuthProviderInterface 抽象化済)
- slug は SNS handle 由来で自動付与・改名追従(1 世代 grace)
- SNS OAuth 送信必須(完全匿名送信不可)— V1 仮説の根幹
- SSR 判定は送信時確定 / 開封時は開示のみ(監査性)
- メッセージ本文は暗号化せず、OAuth トークンのみ AES-GCM
- AI 事前検閲は採用せず事後通報のみ(A2)
- UUID (CHAR(36)) PK 採用
- 退会時も送信者 snapshot 保持(MOD-03、V1 仮説補強)
- `DatabaseException | PDOException` union catch + SQLSTATE 23000 マッチング(UNIQUE 衝突冪等吸収パターン)
- REV-01 retired-user slug 404(timing oracle 防止)
- Lolipop git deploy で migrations は hook に含めず手動 SSH

### Codebase conventions (from v1 executor decisions — relevant to v2 UI work)

UI / Template 関連の v1 知見（v2 で参照する可能性が高い）:

- **既存 CSS スタック**: `webroot/css/{normalize.min, milligram.min, tamabox}.css` を `layout/default.php` で chain ロード。`tamabox.css` 911 行に `:root` CSS variables (`--color-bg / --color-accent / --space-* / --radius-* / --shadow-subtle / --font-family`) と全コンポーネントスタイル集中。v2 では tokens.css を追加 + `tamabox.css` の :root を Calm Gacha 値で書き換えるアプローチが破壊最小。
- **CakePHP テンプレート構造**: `templates/{Pages,Messages,Inboxes,Users,Account,Auth,Reports}/` + `templates/{layout,element,cell}/`。inline JS が `inbox_settings_form.php` (probability slider sync) に存在。
- **Dashboard モノリス**: `templates/Users/dashboard.php` 149 行に 受信リスト + Reveal inline + 設定 element + ブロックリスト element が同居。v2 タブ分離時に分割対象。
- **既存 Form helper パターン**: `$this->Form->create(null, ['url' => '/path', 'type' => 'post', 'class' => 'xxx-form'])` + `$this->Form->end()` で wrap、CSRF token は自動付与。
- **既存 Identity アクセス**: `$identity = $this->getRequest()->getAttribute('identity')` → `$identity->getOriginalData()->user_identity->handle_cached / avatar_url_cached` で SNS info 取得。v2 でも同 path 維持。
- **`postString()` / `queryString()` helper パターン**: phpstan level 8 安全な POST / query 値取得。新 controller でも踏襲必須。
- **既存 Phase 4 CSS section コメント慣習**: `tamabox.css` 内に `/* ========== Phase N — ... ========== */` で phase 単位の追加範囲明示。v2 でも同方式を踏襲予定。

### Open Todos

(v2 milestone 開始時点 — clean)

### Blockers

None currently.

### Research Flags

- v2 は frontend UI 改修中心、新規バックエンド機能なし。Tech stack 調査は不要。
- 設計仕様の一次情報: `~/projects/handoff_tamabox/` 配下 (tokens.css / colors_and_type.css / 30 画面 hi-fi)
- 4 タブ分離で CakePHP のルーティングをどう設計するか(複数 route vs single route + query / fragment)は Phase 7 planning 時に判断
- Bluesky 公式ロゴアセットは引き続き未取得 — 雲モチーフ仮置きで v2 を完走、差し替えは v3

## Session Continuity

**Last Agent Run**: /gsd-new-milestone v2 — milestone scope 定義中 (2026-05-13)
**Next Action**: REQUIREMENTS.md 定義 → roadmap 作成 → /gsd-discuss-phase 5 で Phase 5 着手
**Context Notes**: v1 shipped 完全クローズ済。v2 は UI/UX overhaul のみで、PHP/MySQL/Bluesky OAuth/SSR メカニクスへの変更は出ない想定。handoff package `~/projects/handoff_tamabox/` の `tokens.css` を一次情報として扱う。

---
*Last updated: 2026-05-13 — v2 Calm Gacha UI milestone started, defining requirements*
