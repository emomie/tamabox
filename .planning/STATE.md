---
gsd_state_version: 1.0
milestone: v0.2
milestone_name: milestone
status: Ready to execute
last_updated: "2026-04-23T12:32:46.002Z"
progress:
  total_phases: 4
  completed_phases: 1
  total_plans: 8
  completed_plans: 7
  percent: 88
---

# tamabox — STATE

Project memory. Updated by every gsd-* command.

## Project Reference

**Core Value**: 「確率で名前がバレる」仕組みが悪意送信者の自己抑止になり、好意送信者にとってはガチャ的祝福演出となる匿名メッセージ箱 (V1 仮説)。SSR 露出確率は受け手が 0〜100% で設定可能。

**Current Focus**: v1 milestone — CakePHP 4.5 + MySQL 8.0 + Bluesky OAuth で `tamabox.emomie.com` に launch する。4 phase 構成 (coarse)。

**Granularity**: coarse
**Mode**: yolo
**Model Profile**: balanced

## Current Position

Phase: 02 (bluesky-oauth-identity) — EXECUTING
Plan: 3 of 4 complete (02-01 foundation ✓ 2026-04-23; 02-02 crypto ✓ 2026-04-23; 02-03 metadata+DID ✓ 2026-04-23)
**Milestone**: v1 launch
**Phase**: Phase 2 — Bluesky OAuth & Identity (execution in progress)
**Plan**: 4 plans in 3 waves — 02-01 foundation ✓ / 02-02 crypto ✓ / 02-03 metadata+DID ✓ / 02-04 oauth-flow (next, Wave 3)
**Next Plan**: `/gsd-execute-phase 2 --wave 3` to launch 02-04 oauth-flow (BlueskyOAuthClient wiring; consumes 02-02 crypto + 02-03 endpoints)
**Status**: Phase 2 Wave 2 both legs complete (02-02 crypto + 02-03 metadata). Wave 3 (02-04) now unblocked — depends on 02-01 + 02-02 + 02-03, all satisfied.
**Resume file**: `.planning/phases/02-bluesky-oauth-identity/02-04-oauth-flow-PLAN.md` (next, final Phase 2 plan)

**Progress**: Phase 2 at 3/4 plans — `[███████████████░░░░░] 75%` (Phase 2 internal)
Overall: Phases 1/4 complete + Phase 2 three-quarters shipped — plans 7/8 done — `[██████████████████░░] 88%`

## Phase Status

- [x] **Phase 1: Foundation & Schema** — Complete (4/4 plans done: 01-01 ✓, 01-02a ✓, 01-02b ✓, 01-03 ✓); awaits verifier
- [~] **Phase 2: Bluesky OAuth & Identity** — In progress (3/4 plans: 02-01 ✓, 02-02 ✓, 02-03 ✓; 02-04 pending)
- [ ] **Phase 3: Inbox, Message & SSR Reveal** — Not started
- [ ] **Phase 4: Moderation & Production Launch** — Not started

## Performance Metrics

| Metric | Value |
|--------|-------|
| Phases completed | 0/4 (Phase 1 pending verify; Phase 2 three-quarters shipped) |
| Plans completed | 7/8 (Phase 1: 01-01, 01-02a, 01-02b, 01-03; Phase 2: 02-01, 02-02, 02-03) |
| Nodes completed | 25 tasks across 7 plans (+4 Phase 2-01 + 2 Phase 2-02 + 2 Phase 2-03) |
| Requirements shipped | 7/34 (INFRA-02, -03, -04, -05, -07; AUTH-07, AUTH-08) |
| Requirements partial | AUTH-06 (interface shell 02-01; concrete impl pending 02-04) |

### Plan Duration Log

| Plan | Wave | Tasks | Duration | Date |
|------|------|-------|----------|------|
| 01-01 infra-hygiene | 1 | 5 | (not recorded) | 2026-04-22 |
| 01-02a schema-root | 2 | 4 | 4m 29s | 2026-04-22 |
| 01-02b schema-dependents | 3 | 4 | 6m 57s | 2026-04-22 |
| 01-03 table-classes | 4 | 4 | 12m | 2026-04-22 |
| 02-01 foundation-setup | 1 | 4 | (not recorded) | 2026-04-23 |
| 02-02 crypto-services | 2 | 2 | ~20m | 2026-04-23 |
| 02-03 metadata-did | 2 | 2 | ~4m | 2026-04-23 |

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

### Executor-discovered decisions (Phase 2)

- **phpstan level 8 requires CakePHP runtime constants bootstrap** (Plan 02-02 deviation #1): First `src/` file using `CONFIG`/`DS` (KeyManager) broke phpstan. Added `bootstrapFiles: - config/paths.php` to `phpstan.neon`. Applies to every future service file referencing CakePHP path constants — no per-file workaround needed. Phase 1 never hit this because no Phase 1 `src/` file referenced the constants.
- **`tests/Fixture/keys/` now hosts VCS-tracked dummy ES256 P-256 keypair** (Plan 02-02 Task 1). These are separate from production `config/keys/*.key` (gitignored). Any future crypto test should inject `TESTS . 'Fixture' . DS . 'keys' . DS . 'private.key'` (or `public.key`) through KeyManager's constructor override.
- **`derToRawSignature()` is duplicated across DpopService and ClientJwtService** by design (Plan 02-02 Task 2). Altotoo verbatim copy — per-class review locality chosen over DRY for 15 lines of crypto. Trait extraction deferred to post-MVP refactor. If Plan 02-03 or 02-04 needs a third signer, THEN extract to `App\Service\OAuth\Support\Es256JwtSignerTrait`.
- **OAUTH_KID must match across jwks.json and client_assertion**: `KeyManager::getPublicJwk()` uses `env('OAUTH_KID')` for `kid`; `ClientJwtService::createAssertion()` uses the same env var for header `kid`. Must be set once in `config/.env` and never drift. Default `'ssr-box-key-1'` covers test/CI; production deploy MUST set explicitly to enable future rotation.
- **Pre-existing `templates/Pages/home.php` $connection->connect() deprecation** (D-DEF-01 in `deferred-items.md`): emits harmless stack trace during `composer test`. Not caused by Phase 2 work; assigned to Phase 4 production-launch plan.
- **Cake\Http\Client mock pattern for 4.5** (Plan 02-03 deviation #1): the public static `Client::addMockResponse($method, $url, $response, $options = [])` is the idiomatic HTTP test stub — it installs a process-global mock adapter that intercepts every Client instance. The lower-level `Cake\Http\Client\Adapter\Mock::addResponse(RequestInterface, Response, array)` exists but the signature is awkward; skip it. Always call `Client::clearMockResponses()` in both `setUp()` AND `tearDown()` to prevent cross-test bleed. Applies to Plan 02-04 BlueskyOAuthClient tests (PAR / token / profile endpoint stubs all use the same pattern).
- **Callback stub invariant for forward-plan reservation** (Plan 02-03 Task 1): `OauthController::callback()` ships as a 501 stub with integration test `testCallbackStubReturns501` locking that contract. Plan 02-04's first task MUST replace both the method body AND this test — if the 501 test still passes after Plan 02-04, the implementation never happened. This is the general pattern for "reserve the class method slot now so the future plan is pure logic fill-in without class-level edits."

### Open Todos

- [x] Phase 2 planning complete 2026-04-23 — 4 plans (02-01 foundation / 02-02 crypto / 02-03 metadata+DID / 02-04 oauth-flow), 3 waves, VERIFICATION PASSED on first pass.
- [x] Phase 2 Wave 1 done 2026-04-23 — 02-01 foundation shipped (cakephp/authentication wired, config/bluesky.php, 6 Phase-2 routes, OAuthProviderInterface shell, config/keys/ ES256 P-256 keypair).
- [x] Phase 2 Wave 2 crypto leg done 2026-04-23 — 02-02 crypto-services shipped (KeyManager / TokenEncryptionService / DpopService / ClientJwtService; 25 unit tests; signature-verification-via-openssl_verify invariant established; AUTH-07 closed, AUTH-08 partial).
- [x] Phase 2 Wave 2 metadata leg done 2026-04-23 — 02-03 metadata+DID shipped (OauthController with clientMetadata/jwks/callback-stub actions + DidResolver for plc.directory DID → PDS lookup; 20 new tests; AUTH-08 closed; callback 501 stub held as hand-off contract for 02-04).
- [ ] Phase 2 Wave 3 oauth-flow next — `/gsd-execute-phase 2 --wave 3` to launch 02-04 (BlueskyOAuthClient implementing OAuthProviderInterface; AuthController::startBluesky; OauthController::callback body fill-in; UsersController::dashboard protected landing). Consumes 02-01 foundation + 02-02 crypto services + 02-03 DidResolver + metadata endpoints.

### Blockers

None currently. Resolved blockers:

- **Rule 3 (resolved in Plan 01-02b Task 4)**: `config/app_local.php` was absent, blocking `bin/cake migrations migrate`. Created locally with `DATABASE_URL` / `DATABASE_TEST_URL` passthroughs from `config/.env`. File is gitignored per CakePHP convention. If you wipe local state, recreate from `config/app_local.example.php`. See `.planning/phases/01-foundation-schema/01-02b-SUMMARY.md` deviation #12.

### Research Flags

- Lolipop 共有鯖での `trustProxy` / `fullBaseUrl` / TLS 終端の実測が必要 (Phase 2 で OAuth redirect_uri 決定時、または Phase 4 本番デプロイ時)
- `session.save_path` が Lolipop でどこに向くか実測 (Phase 1 または 2)
- `altotoo.emomie.com` の OAuth 実装知見を流用(Phase 2 開始時に参照)

## Session Continuity

**Last Agent Run**: execute-phase 2 wave 2 (metadata leg) @ 2026-04-23 — Plan 02-03 Metadata Endpoints + DID Resolver complete. 2 files in src/ (OauthController with clientMetadata/jwks/callback-stub actions; DidResolver for plc.directory DID→PDS lookup) + 2 PHPUnit test files (7 OauthController integration tests + 13 DidResolver unit tests via Client::addMockResponse global adapter). 3 atomic commits on main (d9f3972 Task 1, 22044c6 Task 2 RED, d728411 Task 2 GREEN). All verification green: phpcs 48/48 / phpstan level 8 [OK] / phpunit 62 tests (42 baseline + 20 new) 145 assertions, 6 pre-existing bake-stub incompletes unchanged, 0 failures. Zero new composer deps. Duration ~4m. 1 functional deviation (Rule 1 — Cake Http Client Mock adapter signature differs from plan; used the higher-level `Client::addMockResponse()` static helper instead) + 1 cosmetic (phpcs double-space in dataProvider comments fixed inline in GREEN commit).
**Next Action**: `/gsd-execute-phase 2 --wave 3` to run Plan 02-04 oauth-flow (BlueskyOAuthClient implementing OAuthProviderInterface; AuthController::startBluesky PAR initiation; OauthController::callback body fill-in replacing the 501 stub; UsersController::dashboard authenticated landing). Consumes 02-01 routes/config + 02-02 crypto services + 02-03 DidResolver + metadata endpoints. This is the final Phase 2 plan.
**Context Notes**: Plan 02-03 is the LAST plan before Phase 2 completes. AUTH-08 now closed (both /oauth/jwks.json and /oauth/client-metadata.json are live with byte-exact client_id matching, single EC P-256 key with kid=env(OAUTH_KID), no private scalar leak). Callback-stub invariant: `testCallbackStubReturns501` integration test will FAIL when Plan 02-04 replaces the body — this is the hand-off contract that guarantees Plan 02-04 actually implemented the flow rather than silently leaving the stub. DidResolver uses `Cake\Http\Client` with 10s timeout + validates `^did:plc:[a-z2-7]{24}$` before any HTTP — Plan 02-04 BlueskyOAuthClient::resolveProfile() DI's this as-is. HTTP test pattern: `Client::addMockResponse($method, $url, $response)` + `Client::clearMockResponses()` in setUp/tearDown. OAUTH_KID env coordination still critical between jwks endpoint (this plan) and ClientJwtService client_assertion (02-02; consumed by 02-04). phpstan bootstrapFiles directive from 02-02 continues covering all Phase 2 src/ files using CakePHP runtime constants — no new phpstan config needed for 02-04.

---
*Last updated: 2026-04-23 (Plan 02-03 Wave 2 metadata leg complete; Phase 2 at 3/4 plans)*
