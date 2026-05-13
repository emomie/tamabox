# Roadmap: tamabox

## Milestones

- ✅ **v1 MVP** — Phases 1-4 (shipped 2026-05-13) — see `.planning/milestones/v1-ROADMAP.md`
- ✅ **v2 Calm Gacha UI / 4-tab 構造** — Phases 5-8 (shipped 2026-05-13) — see `.planning/milestones/v2-ROADMAP.md`
- 🚧 **v3** — TBD (`/gsd-new-milestone` to scope)

## Phases

<details>
<summary>✅ v1 MVP (Phases 1-4) — SHIPPED 2026-05-13</summary>

- [x] Phase 1: Foundation & Schema (4/4 plans) — completed 2026-04-22
- [x] Phase 2: Bluesky OAuth & Identity (4/4 plans) — verified 2026-04-24
- [x] Phase 3: Inbox, Message & SSR Reveal (4/4 plans) — verified 2026-04-26
- [x] Phase 4: Moderation & Production Launch (3/3 plans) — verified 2026-05-13

Full archive: `.planning/milestones/v1-ROADMAP.md`

</details>

<details>
<summary>✅ v2 Calm Gacha UI / 4-tab 構造 (Phases 5-8) — SHIPPED 2026-05-13</summary>

**Milestone Goal:** handoff_tamabox の Calm Gacha デザインシステムを tamabox v1 に注入し、4 タブ構造化と最小限の Reveal 演出まで到達させる UI/UX overhaul。バックエンド機能の新規追加なし。

- [x] Phase 5: Design System Foundation (5/5 plans) — deployed `e420a78`, smoke verified 2026-05-13
- [x] Phase 6: v1 画面の Calm Gacha 化 (8/8 plans) — deployed `1777c2a`, smoke verified 2026-05-13
- [x] Phase 7: Dashboard 4 タブ分離 + Reveal 演出 (8/8 plans) — code complete 2026-05-13 (HEAD `960fc11`, deploy pending push)
- [x] Phase 8: エッジケース & マイクロインタラクション (8/8 plans) — code complete 2026-05-13 (HEAD `960fc11`, deploy pending push)

**Requirements**: 28/28 implemented (DS×6, UI×8, NAV×6, MOTION×3, EDGE×5)
**Audit**: passed — see `.planning/v2-MILESTONE-AUDIT.md`
**Tests**: 203 / 576 assertions / 0 failures (baseline 195 → +8)

Full archive: `.planning/milestones/v2-ROADMAP.md`

</details>

### 🚧 v3 (TBD)

Run `/gsd-new-milestone` to scope. Candidate themes inherited from v2 audit + `REQUIREMENTS.md v3 Requirements (Deferred)` block:

- **Backend feature build-out**: Discover (DISC-01) + Notifications (NOTIF-01) backends behind the v2 empty-state stubs
- **Onboarding / Static**: 3-step Onboarding (ONB-01) + Login (ONB-02) + Help (STATIC-01) + Terms (STATIC-02) + Share (SHARE-01)
- **Motion polish**: 3D rotateX 封筒オープン (MOTION-X1) + bottom-sheet slide-up animation
- **Desktop responsive**: DESKTOP-01..03 (Public box / Send form / Dashboard 2-column)
- **Assets**: Bluesky butterfly logo (ASSET-01)
- **A11y formalization**: WCAG audit + focus-visible polish on 4 bespoke Phase 8 buttons
- **Tech debt sweep**: 6-L-02 readability, 6-IN-03 test-assert tightening, 7-IN-01 structural asserts, 8-IN-02 modal `displayName` decoupling

## Progress

**Execution Order:** 5 → 6 → 7 → 8 (v2 complete) → v3 (TBD)

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Foundation & Schema | v1 | 4/4 | Complete | 2026-04-22 |
| 2. Bluesky OAuth & Identity | v1 | 4/4 | Complete | 2026-04-24 |
| 3. Inbox, Message & SSR Reveal | v1 | 4/4 | Complete | 2026-04-26 |
| 4. Moderation & Production Launch | v1 | 3/3 | Complete | 2026-05-13 |
| 5. Design System Foundation | v2 | 5/5 | Shipped (deployed + smoke verified) | 2026-05-13 |
| 6. v1 画面の Calm Gacha 化 | v2 | 8/8 | Shipped (deployed + smoke verified) | 2026-05-13 |
| 7. Dashboard 4 タブ分離 + Reveal | v2 | 8/8 | Code complete (deploy pending) | 2026-05-13 |
| 8. エッジケース & マイクロインタラクション | v2 | 8/8 | Code complete (deploy pending) | 2026-05-13 |

---
*Last updated: 2026-05-13 — v2 milestone archived, v3 awaits scoping via `/gsd-new-milestone`*
