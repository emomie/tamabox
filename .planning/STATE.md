---
gsd_state_version: 1.0
milestone: v0.2
milestone_name: milestone
status: Phase 2 complete — ready for verify
last_updated: "2026-04-24T06:45:30.697Z"
progress:
  total_phases: 4
  completed_phases: 2
  total_plans: 8
  completed_plans: 8
  percent: 50
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

Phase: 02 (bluesky-oauth-identity) — COMPLETE (awaits verifier)
Plan: 4 of 4 complete (02-01 foundation ✓ 2026-04-23; 02-02 crypto ✓ 2026-04-23; 02-03 metadata+DID ✓ 2026-04-23; 02-04 oauth-flow ✓ 2026-04-24)
**Milestone**: v1 launch
**Phase**: Phase 2 — Bluesky OAuth & Identity (all 4 plans shipped; awaits `/gsd-verify-phase 2`)
**Plan**: 4 plans in 3 waves — all shipped. 02-01 foundation ✓ / 02-02 crypto ✓ / 02-03 metadata+DID ✓ / 02-04 oauth-flow ✓
**Next Plan**: `/gsd-verify-phase 2` to validate Phase 2 success criteria + ROADMAP delta; then `/gsd-plan-phase 3` to start Inbox/Message/SSR planning
**Status**: Phase 2 complete. All 8 requirements AUTH-01/02/04/05/06/07/08/09 closed. Full OAuth handshake wired end-to-end (PKCE + PAR + DPoP + private_key_jwt + nonce retry + DID→PDS → getProfile → UPSERT → setIdentity → /dashboard). 6 Phase-2 routes all live. composer test 85 tests green.
**Resume file**: `.planning/phases/02-bluesky-oauth-identity/02-04-SUMMARY.md` (latest plan summary)

**Progress**: Phase 2 at 4/4 plans — `[████████████████████] 100%` (Phase 2 internal)
Overall: Phases 2/4 complete — plans 8/8 done (of originally planned; Phase 3+4 plans not yet generated) — `[██████████░░░░░░░░░░] 50%` (phase-completion)

## Phase Status

- [x] **Phase 1: Foundation & Schema** — Complete (4/4 plans done: 01-01 ✓, 01-02a ✓, 01-02b ✓, 01-03 ✓); awaits verifier
- [x] **Phase 2: Bluesky OAuth & Identity** — Complete (4/4 plans: 02-01 ✓, 02-02 ✓, 02-03 ✓, 02-04 ✓); awaits verifier
- [ ] **Phase 3: Inbox, Message & SSR Reveal** — Not started
- [ ] **Phase 4: Moderation & Production Launch** — Not started

## Performance Metrics

| Metric | Value |
|--------|-------|
| Phases completed | 0/4 certified (Phase 1 + Phase 2 pending verify) |
| Plans completed | 8/8 (Phase 1: 01-01, 01-02a, 01-02b, 01-03; Phase 2: 02-01, 02-02, 02-03, 02-04) |
| Nodes completed | 28 tasks across 8 plans (+3 Phase 2-04: BlueskyOAuthClient / DB upsert / controllers+templates+tests) |
| Requirements shipped | 13/34 (INFRA-02, -03, -04, -05, -07; AUTH-01, AUTH-02, AUTH-04, AUTH-05, AUTH-06, AUTH-07, AUTH-08, AUTH-09) |
| Requirements partial | なし (Phase 2 で AUTH-06 concrete impl 完成、全 AUTH シリーズ closed) |

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
| 02-04 oauth-flow | 3 | 3 | ~17m 29s | 2026-04-24 |

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
- **Callback stub invariant for forward-plan reservation** (Plan 02-03 Task 1): `OauthController::callback()` ships as a 501 stub with integration test `testCallbackStubReturns501` locking that contract. Plan 02-04's first task MUST replace both the method body AND this test — if the 501 test still passes after Plan 02-04, the implementation never happened. This is the general pattern for "reserve the class method slot now so the future plan is pure logic fill-in without class-level edits." — **RESOLVED 2026-04-24 in Plan 02-04 Task 3**: callback 完全実装 + 501 test を 302 assert 版に差し替え、Plan 02-04 SUMMARY Self-Check で verified.
- **AppController Authentication component wiring missed in Plan 02-01** (Plan 02-04 deviation Rule 2): Plan 02-01 wired `AuthenticationMiddleware` in `Application::middleware()` but **did not** load the `Authentication.Authentication` component in `AppController::initialize()`. Middleware alone populates `request.identity` attribute, but `$this->Authentication->getIdentity()` / `setIdentity()` / `logout()` / `allowUnauthenticated()` calls need the component. Plan 02-04 added this retroactively. Future plans (Phase 3+) can assume `$this->Authentication` is always available in any AppController subclass.
- **CakePHP 4.5 `protected array $fixtures` typed-property collision** (Plan 02-04 Task 3 deviation Rule 1): `Cake\TestSuite\TestCase` declares `protected $fixtures = []` with no native type. Subclasses MUST NOT redeclare with `protected array $fixtures` — PHP emits `Fatal error: Type of $fixtures must not be defined`. Always use phpdoc `@var array<int, string>` + `protected $fixtures = [...]`. Future integration tests inherit this convention.
- **`$request->getQuery()` return type `array|string|null` breaks phpstan level 8** (Plan 02-04 Task 3 deviation Rule 1): naive `(string)$this->request->getQuery('k')` is flagged. Introduced `queryString(string $key): string` + `sessionString(string $key): string` private helpers on OauthController that `is_string()`-guard the value. Phase 3+ controllers consuming query params should use the same pattern.
- **Plan 02-04 replaced `templates/Pages/home.php`** — old CakePHP skeleton welcome page is gone; new version shows 'Bluesky でログイン' CTA. `PagesControllerTest::testDisplay` was updated accordingly. This likely **resolved D-DEF-01** (pre-existing `$connection->connect()` deprecation trace in composer test output); verifier should re-check `composer test 2>&1 | grep -i deprecated` and delete D-DEF-01 from `deferred-items.md` if the trace is gone.

### Open Todos

- [x] Phase 2 planning complete 2026-04-23 — 4 plans (02-01 foundation / 02-02 crypto / 02-03 metadata+DID / 02-04 oauth-flow), 3 waves, VERIFICATION PASSED on first pass.
- [x] Phase 2 Wave 1 done 2026-04-23 — 02-01 foundation shipped (cakephp/authentication wired, config/bluesky.php, 6 Phase-2 routes, OAuthProviderInterface shell, config/keys/ ES256 P-256 keypair).
- [x] Phase 2 Wave 2 crypto leg done 2026-04-23 — 02-02 crypto-services shipped (KeyManager / TokenEncryptionService / DpopService / ClientJwtService; 25 unit tests; signature-verification-via-openssl_verify invariant established; AUTH-07 closed, AUTH-08 partial).
- [x] Phase 2 Wave 2 metadata leg done 2026-04-23 — 02-03 metadata+DID shipped (OauthController with clientMetadata/jwks/callback-stub actions + DidResolver for plc.directory DID → PDS lookup; 20 new tests; AUTH-08 closed; callback 501 stub held as hand-off contract for 02-04).
- [x] Phase 2 Wave 3 oauth-flow done 2026-04-24 — 02-04 shipped (BlueskyOAuthClient 5-method impl + TDD RED/GREEN, UsersTable::findByDid + UserIdentitiesTable::upsertBlueskyIdentity with AES-GCM encrypt & UPSERT-in-txn, AuthController::startBluesky + logout, OauthController::callback full implementation replacing 501 stub, UsersController::dashboard, 5 templates + tamabox.css 218 lines, 10 new integration tests + 13 unit tests = 23 new tests; 85 tests total green). AUTH-01/02/04/05/06/09 closed.
- [ ] `/gsd-verify-phase 2` next — validate Phase 2 success criteria observable (signup / login / logout / UNIQUE guard / jwks+metadata / token encryption / OAuthProviderInterface abstraction) + ROADMAP delta. Verifier should also check for D-DEF-01 resolution and consider removing it from deferred-items.md.
- [ ] Phase 3 planning (`/gsd-plan-phase 3`) — Inbox, Message & SSR Reveal — after verify passes.

### Blockers

None currently. Resolved blockers:

- **Rule 3 (resolved in Plan 01-02b Task 4)**: `config/app_local.php` was absent, blocking `bin/cake migrations migrate`. Created locally with `DATABASE_URL` / `DATABASE_TEST_URL` passthroughs from `config/.env`. File is gitignored per CakePHP convention. If you wipe local state, recreate from `config/app_local.example.php`. See `.planning/phases/01-foundation-schema/01-02b-SUMMARY.md` deviation #12.

### Research Flags

- Lolipop 共有鯖での `trustProxy` / `fullBaseUrl` / TLS 終端の実測が必要 (Phase 2 で OAuth redirect_uri 決定時、または Phase 4 本番デプロイ時)
- `session.save_path` が Lolipop でどこに向くか実測 (Phase 1 または 2)
- `altotoo.emomie.com` の OAuth 実装知見を流用(Phase 2 開始時に参照)

## Session Continuity

**Last Agent Run**: execute-phase 2 wave 3 (oauth-flow) @ 2026-04-24 — Plan 02-04 BlueskyOAuthClient + UsersTable::findByDid + UserIdentitiesTable::upsertBlueskyIdentity + AuthController + UsersController + OauthController::callback impl + 5 templates + tamabox.css (218 lines) + 10 new integration tests + 13 unit tests. 4 atomic commits on main: c94c006 (Task 1 RED), da4028f (Task 1 GREEN), 3946ada (Task 2), 7fde47f (Task 3). Verification: phpcs 54/54 / phpstan level 8 [OK] / phpunit 85 tests 221 assertions, 6 pre-existing bake incompletes unchanged, 0 failures. Zero new composer deps. Duration ~17m 29s. 3 deviations: (Rule 2) AppController に Authentication component loadComponent 追加 (Plan 02-01 の漏れ)、(Rule 1) `protected array $fixtures` 型衝突 → phpdoc-only、(Rule 1) `$request->getQuery()` 型問題 → `queryString`/`sessionString` helpers。Callback 501 stub が 302 assert に flip されて Plan 02-03 の hand-off contract が正しく解除されたことを integration test で観測。
**Next Action**: `/gsd-verify-phase 2` to validate Phase 2 success criteria observable (signup → /dashboard; re-login sync; /oauth/logout destroys session; UNIQUE uk_user_identities_provider_account guard; /oauth/jwks.json + /oauth/client-metadata.json live; tokens AES-GCM encrypted in `*_enc`; OAuthProviderInterface 抽象化). Then `/gsd-plan-phase 3` to start Inbox/Message/SSR Reveal planning.
**Context Notes**: Phase 2 is COMPLETE. All 8 AUTH requirements (AUTH-01〜AUTH-09 minus 03) closed. Phase 2 Wave 3 used TDD for Task 1 only (BlueskyOAuthClient: RED commit c94c006 with 13 failing tests, then GREEN commit da4028f); Tasks 2+3 used plan-guided implementation (non-TDD). STATE.md decisions section now records the 3 newly-discovered patterns (Rule 2 AppController wiring, fixture type collision, query-cast helper pattern). D-DEF-01 (home.php `$connection->connect()` deprecation trace) **likely resolved** — home.php was fully replaced in Plan 02-04 Task 3 — verifier should re-check. Phase 3 hand-off: `BlueskyOAuthClient::refreshToken()` is implemented + unit-tested but unused; Phase 3 MSG-03 send flow should invoke it when `token_expires_at <= now()` (see 02-04 SUMMARY Handoff Notes for full lifecycle). `user_identities.last_synced_at` is updated on every login by upsertBlueskyIdentity, so Phase 3 can assume cached handle/avatar are "fresh enough" without additional TTL.

---
*Last updated: 2026-04-24 (Plan 02-04 Wave 3 oauth-flow complete; Phase 2 all 4 plans shipped, awaits verify)*
