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
**Phase**: Phase 1 — Foundation & Schema (**COMPLETE** — 4/4 plans done)
**Plan**: 01-03 — Table Classes (done; 4/4 tasks complete)
**Next Plan**: Phase 2 kickoff (Bluesky OAuth & Identity)
**Status**: Phase 1 complete — awaiting `/gsd-verify-phase 1` then Phase 2 start

**Progress**: Phase 1 at 4/4 plans — `[████████████████████] 100%` (Phase 1 internal)
Overall: Phases 0/4 complete (Phase 1 pending verifier) — `[░░░░░░░░░░░░░░░░░░░░] 0%`

## Phase Status

- [x] **Phase 1: Foundation & Schema** — Complete (4/4 plans done: 01-01 ✓, 01-02a ✓, 01-02b ✓, 01-03 ✓); awaits verifier
- [ ] **Phase 2: Bluesky OAuth & Identity** — Not started
- [ ] **Phase 3: Inbox, Message & SSR Reveal** — Not started
- [ ] **Phase 4: Moderation & Production Launch** — Not started

## Performance Metrics

| Metric | Value |
|--------|-------|
| Phases completed | 0/4 (Phase 1 pending verify) |
| Plans completed | 4/4 (Phase 1: 01-01, 01-02a, 01-02b, 01-03) |
| Nodes completed | 17 tasks across 4 plans |
| Requirements shipped | 5/34 (INFRA-02, -03, -04, -05, -07) |

### Plan Duration Log

| Plan | Wave | Tasks | Duration | Date |
|------|------|-------|----------|------|
| 01-01 infra-hygiene | 1 | 5 | (not recorded) | 2026-04-22 |
| 01-02a schema-root | 2 | 4 | 4m 29s | 2026-04-22 |
| 01-02b schema-dependents | 3 | 4 | 6m 57s | 2026-04-22 |
| 01-03 table-classes | 4 | 4 | 12m | 2026-04-22 |

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
- cakephp/bake 2.8 correctly emits `@property string` for CHAR(36) UUID columns; RESEARCH Pitfall 6 does not apply to this bake version (verified empirically in Plan 01-03 Task 1).
- Bake default fixtures violate tamabox CHECK/ENUM/DATETIME constraints; Plan 01-03 rewrote all 6 fixtures with schema-valid data (deviation #1). Future bake re-runs will re-introduce the broken defaults — do NOT re-bake fixtures without re-applying the fix.
- BlocksTable's dual FK-to-users required manual alias disambiguation (BlockerUsers/BlockedUsers) — bake emitted duplicate `belongsTo('Users')` which silently overwrites in CakePHP's associations collection (Plan 01-03 deviation #2).

### Open Todos

- [ ] Phase 2 kickoff: `/gsd-plan-phase 2` (Bluesky OAuth & Identity).

### Blockers

None currently. Resolved blockers:
- **Rule 3 (resolved in Plan 01-02b Task 4)**: `config/app_local.php` was absent, blocking `bin/cake migrations migrate`. Created locally with `DATABASE_URL` / `DATABASE_TEST_URL` passthroughs from `config/.env`. File is gitignored per CakePHP convention. If you wipe local state, recreate from `config/app_local.example.php`. See `.planning/phases/01-foundation-schema/01-02b-SUMMARY.md` deviation #12.

### Research Flags

- Lolipop 共有鯖での `trustProxy` / `fullBaseUrl` / TLS 終端の実測が必要 (Phase 2 で OAuth redirect_uri 決定時、または Phase 4 本番デプロイ時)
- `session.save_path` が Lolipop でどこに向くか実測 (Phase 1 または 2)
- `altotoo.emomie.com` の OAuth 実装知見を流用(Phase 2 開始時に参照)

## Session Continuity

**Last Agent Run**: execute-phase 1 wave 4 @ 2026-04-22 — Plan 01-03 Table Classes complete. 6 App\Model\Table\* + 6 App\Model\Entity\* + 6 Fixture + 6 TableTest skeletons baked via `bin/cake bake model`, then post-bake Timestamp Behavior + association alias disambiguation + fixture data repair + LocatorSmokeTest. 4 commits on main (276c5fb, 8716ec1, 14e0412, e2b705a). All verification green: phpcs 0 / phpstan 0 (level 8) / phpunit 0 (17 tests, 29 assertions, 6 bake markTestIncomplete stubs). Phase 1 complete; all 5 ROADMAP success criteria closed. Duration 12m.
**Next Action**: `/gsd-verify-phase 1` to run phase-level verifier, then `/gsd-plan-phase 2` for Bluesky OAuth kickoff.
**Context Notes**: Phase 1 plans total 17 tasks; all 17 done (5 Wave 1, 4 Wave 2, 4 Wave 3, 4 Wave 4). 6 Table classes now resolvable via TableLocator::getTableLocator() under allowFallbackClass(false). LocatorSmokeTest committed as ongoing regression guard. For Phase 2 AUTH-07 (OAuth tokens): AES-GCM decorator/behavior needed for user_identities.access_token_enc / refresh_token_enc; `Text::uuid()` hook needed in UsersTable/UserIdentitiesTable initialize() for Phase 2 user creation; `$_accessible` mass-assignment hardening deferred to Phase 3. Bake-generated fixtures were rewritten with schema-valid data (all 6); re-baking fixtures in later phases requires re-applying the fix.

---
*Last updated: 2026-04-22 (Plan 01-03 Wave 4 complete; Phase 1 done pending verifier)*
