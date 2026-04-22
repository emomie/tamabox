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
**Phase**: Phase 1 — Foundation & Schema (in progress, Wave 3/4 complete)
**Plan**: 01-02b — Schema Dependents (done; 4/4 tasks complete)
**Next Plan**: 01-03 — Table Classes (Wave 4)
**Status**: executing — next `/gsd-execute-phase 1` resumes at plan 01-03

**Progress**: Phase 1 at 3/4 plans — `[███████████████░░░░░] 75%` (Phase 1 internal)
Overall: Phases 0/4 complete — `[░░░░░░░░░░░░░░░░░░░░] 0%`

## Phase Status

- [ ] **Phase 1: Foundation & Schema** — In progress (3/4 plans done: 01-01 ✓, 01-02a ✓, 01-02b ✓; 01-03 pending)
- [ ] **Phase 2: Bluesky OAuth & Identity** — Not started
- [ ] **Phase 3: Inbox, Message & SSR Reveal** — Not started
- [ ] **Phase 4: Moderation & Production Launch** — Not started

## Performance Metrics

| Metric | Value |
|--------|-------|
| Phases completed | 0/4 |
| Plans completed | 3/4 (Phase 1: 01-01, 01-02a, 01-02b) |
| Nodes completed | 13 tasks across 3 plans |
| Requirements shipped | 4/34 (INFRA-02, -03, -04, -05) |

### Plan Duration Log

| Plan | Wave | Tasks | Duration | Date |
|------|------|-------|----------|------|
| 01-01 infra-hygiene | 1 | 5 | (not recorded) | 2026-04-22 |
| 01-02a schema-root | 2 | 4 | 4m 29s | 2026-04-22 |
| 01-02b schema-dependents | 3 | 4 | 6m 57s | 2026-04-22 |

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

### Executor-discovered decisions (Phase 1)

- **D-10 applied 13 times** in Waves 2+3: DB-SCHEMA.md v0.2 wins over plan text paraphrases. Every migration's column set, CHECK name, FK cascade direction, and index name matches DB-SCHEMA verbatim.
- `messages`, `blocks`, `reports` tables have **NO `updated_at`** column (DB-SCHEMA v0.2 §4-§6 define only `created_at`). Plan 01-03 Table classes must NOT apply default Timestamp Behavior `modified` mapping on these three.
- `messages.ssr_seed` is `VARCHAR(64) NOT NULL` (not nullable). Phase 3 MSG-03 must compute the sha256 before INSERT.
- `reports.status` ENUM has 4 values: `pending` / `reviewed` / `actioned` / `dismissed` (Phase 4 moderation UI must handle the intermediate `reviewed` state).
- `messages.deleted_reason` is `VARCHAR(64)` NOT ENUM, with allowed values enforced at app layer (app-layer validation in Phase 4 MOD-03).
- `config/app_local.php` is required for any `bin/cake` invocation but is gitignored; recreate from `config/app_local.example.php` if local state is wiped.

### Open Todos

- [ ] Plan 01-03 (Wave 4): bake Table/Entity classes for 6 tables, UUID @property fix, TableLocator smoke test under `allowFallbackClass(false)` → closes INFRA-07 and Phase 1.

### Blockers

None currently. Resolved blockers:
- **Rule 3 (resolved in Plan 01-02b Task 4)**: `config/app_local.php` was absent, blocking `bin/cake migrations migrate`. Created locally with `DATABASE_URL` / `DATABASE_TEST_URL` passthroughs from `config/.env`. File is gitignored per CakePHP convention. If you wipe local state, recreate from `config/app_local.example.php`. See `.planning/phases/01-foundation-schema/01-02b-SUMMARY.md` deviation #12.

### Research Flags

- Lolipop 共有鯖での `trustProxy` / `fullBaseUrl` / TLS 終端の実測が必要 (Phase 2 で OAuth redirect_uri 決定時、または Phase 4 本番デプロイ時)
- `session.save_path` が Lolipop でどこに向くか実測 (Phase 1 または 2)
- `altotoo.emomie.com` の OAuth 実装知見を流用(Phase 2 開始時に参照)

## Session Continuity

**Last Agent Run**: execute-phase 1 wave 3 @ 2026-04-22 — Plan 01-02b Schema Dependents complete. 3 new Phinx migrations (CreateMessages, CreateBlocks, CreateReports) authored + `bin/cake migrations migrate` applied + INFORMATION_SCHEMA verified + rollback-to-zero + re-migrate round trip passed. 13 D-10 DB-SCHEMA-verbatim deviations tracked across Waves 2+3. 4 commits on `main` (dff4cbf, 3d8662a, 2238c7d, 4eb0704). Duration 6m 57s.
**Next Action**: `/gsd-execute-phase 1` resumes at Plan 01-03 (Wave 4 — Table class bake).
**Context Notes**: Phase 1 plans total 17 tasks; 13 done (5 in Wave 1, 4 in Wave 2, 4 in Wave 3), 4 remaining (Wave 4 bake). Wave 3 Rule 3 blocker: `config/app_local.php` was absent blocking `bin/cake migrations`; created locally (gitignored), see 01-02b-SUMMARY deviation #12. MySQL state post-Wave-3: all 6 domain tables + phinxlog present, empty (0 rows). DB ready for Plan 01-03 `bin/cake bake model` introspection. Key Phinx 0.13 details for Wave 4 consumers: messages/blocks/reports have NO `updated_at` (Table class Timestamp behavior must skip `modified` mapping); messages.ssr_seed is NOT NULL (Phase 3 computes pre-INSERT); CHECK constraints applied via raw `$this->execute()`.

---
*Last updated: 2026-04-22 (Plan 01-02b Wave 3 complete)*
