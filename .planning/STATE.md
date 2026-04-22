# tamabox — STATE

Project memory. Updated by every gsd-* command.

## Project Reference

**Core Value**: 「確率で名前がバレる」仕組みが悪意送信者の自己抑止になり、好意送信者にとってはガチャ的祝福演出となる匿名メッセージ箱 (V1 仮説)。SSR 露出確率は受け手が 0〜100% で設定可能。

**Current Focus**: v1 milestone — CakePHP 4.5 + MySQL 8.0 + Bluesky OAuth で `tamabox.emomie.com` に launch する。4 phase 構成 (coarse)。

**Granularity**: coarse
**Mode**: yolo
**Model Profile**: balanced

## Current Position

**Milestone**: v1 launch
**Phase**: — (not started; next → Phase 1: Foundation & Schema)
**Plan**: — (not created yet)
**Status**: roadmap complete, awaiting `/gsd-plan-phase 1`

**Progress**: Phase 0/4 — `[░░░░░░░░░░░░░░░░░░░░] 0%`

## Phase Status

- [ ] **Phase 1: Foundation & Schema** — Not started
- [ ] **Phase 2: Bluesky OAuth & Identity** — Not started
- [ ] **Phase 3: Inbox, Message & SSR Reveal** — Not started
- [ ] **Phase 4: Moderation & Production Launch** — Not started

## Performance Metrics

| Metric | Value |
|--------|-------|
| Phases completed | 0/4 |
| Plans completed | 0/? |
| Nodes completed | 0/? |
| Requirements shipped | 0/34 |

## Accumulated Context

### Key Decisions

(Carried from PROJECT.md Key Decisions — re-summarized here for quick reference)

- Bluesky OAuth 先行 / X は Phase 2 (本プロダクト外 v2) — マルチプロバイダ抽象化は最初から
- SNS OAuth 送信必須 (完全匿名送信不可) — V1 仮説の根幹
- SSR 判定は送信時確定 / 開封時は開示のみ (F2 仮説の監査性)
- メッセージ本文は暗号化せず、OAuth トークンのみ AES-GCM (通報レビュー運営要件とのバランス)
- AI 事前検閲は採用せず事後通報 (A2) — 言論抑圧リスク (E3) と MVP コスト回避
- UUID (CHAR(36)) PK 採用 — 共有鯖 + CakePHP 統合容易
- 退会時も送信者 snapshot 保持 — V1 仮説補強(逃げ得防止)

### Open Todos

(何も走っていない — Phase 1 開始時に plan-phase が埋める)

### Blockers

None currently. Pre-build risks tracked in `.planning/codebase/CONCERNS.md` and will be addressed as part of Phase 1 (PHP 8.0 整合 / .env / httpoxy / migrations) and Phase 4 (debug=false / webroot 配置 / key 管理).

### Research Flags

- Lolipop 共有鯖での `trustProxy` / `fullBaseUrl` / TLS 終端の実測が必要 (Phase 2 で OAuth redirect_uri 決定時、または Phase 4 本番デプロイ時)
- `session.save_path` が Lolipop でどこに向くか実測 (Phase 1 または 2)
- `altotoo.emomie.com` の OAuth 実装知見を流用(Phase 2 開始時に参照)

## Session Continuity

**Last Agent Run**: roadmapper @ 2026-04-22 — initial roadmap + STATE bootstrap
**Next Action**: `/gsd-plan-phase 1` to decompose Phase 1 (Foundation & Schema) into executable plans
**Context Notes**: Design source of truth は外部 `emomie/ssr-box-discovery` リポ (ASSUMPTIONS / DESIGN / DB-SCHEMA / AUTH-FLOW)。実装着手時に再読すること。

---
*Last updated: 2026-04-22 (roadmap created)*
