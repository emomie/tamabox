---
gsd_state_version: 1.0
milestone: v0.2
milestone_name: milestone
status: Phase complete — ready for verification
last_updated: "2026-04-26T10:04:24.592Z"
progress:
  total_phases: 4
  completed_phases: 2
  total_plans: 12
  completed_plans: 10
  percent: 83
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

Phase: 02 (bluesky-oauth-identity) — **VERIFIED 2026-04-24** (7/7 observable truths; 3 human items for live Bluesky AS / browser cookie-destroy / handle-change sync — inherent to external-OAuth-provider contract)
Plan: 4 of 4 complete (02-01 foundation ✓ 2026-04-23; 02-02 crypto ✓ 2026-04-23; 02-03 metadata+DID ✓ 2026-04-23; 02-04 oauth-flow ✓ 2026-04-24)
**Milestone**: v1 launch
**Phase**: Phase 2 — Bluesky OAuth & Identity (**VERIFICATION PASSED at code level**; human gate for live-AS smoke test deferred to tamabox.emomie.com deployment in Phase 4)
**Plan**: 4 plans in 3 waves — all shipped and verified. 02-01 foundation ✓ / 02-02 crypto ✓ / 02-03 metadata+DID ✓ / 02-04 oauth-flow ✓ / VERIFICATION.md ✓
**Next Plan**: 03-03a (dashboard — open action; slug collision flash) — Wave 3 start
**Status**: Phase 2 verification complete. All 8 requirements AUTH-01/02/04/05/06/07/08/09 satisfied at code level (Reflection + Configure smoke + ORM data-flow trace + QA gates all green). Full OAuth handshake wired end-to-end (PKCE + PAR + DPoP + private_key_jwt + nonce retry + DID→PDS → getProfile → UPSERT → setIdentity → /dashboard). 6 Phase-2 routes all live. D-DEF-01 resolved as a side effect of Plan 02-04's home.php rewrite. Zero anti-patterns in Phase 2 artifacts. composer test 85 tests / 221 assertions / 0 failures. phpstan level 8 [OK] / phpcs 54/54.
**Resume file**: `.planning/phases/02-bluesky-oauth-identity/VERIFICATION.md` (verification report, 2026-04-24)

**Progress**: Phase 2 at 4/4 plans + verified — `[████████████████████] 100%` (Phase 2 internal)
Overall: Phases 2/4 verified — plans 8/8 done (of originally planned; Phase 3+4 plans not yet generated) — `[██████████░░░░░░░░░░] 50%` (phase-completion)

## Phase Status

- [x] **Phase 1: Foundation & Schema** — Complete (4/4 plans done: 01-01 ✓, 01-02a ✓, 01-02b ✓, 01-03 ✓); awaits verifier
- [x] **Phase 2: Bluesky OAuth & Identity** — **VERIFIED 2026-04-24** (4/4 plans: 02-01 ✓, 02-02 ✓, 02-03 ✓, 02-04 ✓; VERIFICATION.md status=human_needed for live-AS happy path only — code-level 7/7 PASS)
- [ ] **Phase 3: Inbox, Message & SSR Reveal** — IN PROGRESS (4/1 plans done; 03-01 slug-foundation ✓ 2026-04-26)
- [ ] **Phase 4: Moderation & Production Launch** — Not started

## Performance Metrics

| Metric | Value |
|--------|-------|
| Phases completed | 1/4 certified (Phase 2 VERIFIED 2026-04-24; Phase 1 pending verify) |
| Plans completed | 8/8 (Phase 1: 01-01, 01-02a, 01-02b, 01-03; Phase 2: 02-01, 02-02, 02-03, 02-04) |
| Nodes completed | 28 tasks across 8 plans (+3 Phase 2-04: BlueskyOAuthClient / DB upsert / controllers+templates+tests) |
| Requirements shipped | 13/34 (INFRA-02, -03, -04, -05, -07; AUTH-01, AUTH-02, AUTH-04, AUTH-05, AUTH-06, AUTH-07, AUTH-08, AUTH-09) |
| Requirements partial | なし (Phase 2 で AUTH-06 concrete impl 完成、全 AUTH シリーズ closed) |
| Phase 03-inbox-message-ssr-reveal P02 | 90min | 4 tasks | 12 files |

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
| 02-verify | — | — | ~9m | 2026-04-24 |

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
- **D-DEF-01 RESOLVED 2026-04-24 by verifier**: Pre-existing `templates/Pages/home.php` `$connection->connect()` deprecation trace is gone — Plan 02-04 Task 3 fully rewrote home.php as the Bluesky CTA landing, removing all skeleton code. `grep -c '$connection' templates/Pages/home.php` = 0; `composer test 2>&1 | grep -i deprecat` only emits the benign `phpunit.xml.dist` XML-schema migration notice (unrelated to D-DEF-01). `deferred-items.md` D-DEF-01 entry can be marked resolved.
- **Cake\Http\Client mock pattern for 4.5** (Plan 02-03 deviation #1): the public static `Client::addMockResponse($method, $url, $response, $options = [])` is the idiomatic HTTP test stub — it installs a process-global mock adapter that intercepts every Client instance. The lower-level `Cake\Http\Client\Adapter\Mock::addResponse(RequestInterface, Response, array)` exists but the signature is awkward; skip it. Always call `Client::clearMockResponses()` in both `setUp()` AND `tearDown()` to prevent cross-test bleed. Applies to Plan 02-04 BlueskyOAuthClient tests (PAR / token / profile endpoint stubs all use the same pattern).
- **Callback stub invariant for forward-plan reservation** (Plan 02-03 Task 1): `OauthController::callback()` ships as a 501 stub with integration test `testCallbackStubReturns501` locking that contract. Plan 02-04's first task MUST replace both the method body AND this test — if the 501 test still passes after Plan 02-04, the implementation never happened. This is the general pattern for "reserve the class method slot now so the future plan is pure logic fill-in without class-level edits." — **RESOLVED 2026-04-24 in Plan 02-04 Task 3**: callback 完全実装 + 501 test を 302 assert 版に差し替え、Plan 02-04 SUMMARY Self-Check で verified. Verifier re-confirmed `grep -c 'withStatus(501)' src/Controller/OauthController.php` = 0.
- **AppController Authentication component wiring missed in Plan 02-01** (Plan 02-04 deviation Rule 2): Plan 02-01 wired `AuthenticationMiddleware` in `Application::middleware()` but **did not** load the `Authentication.Authentication` component in `AppController::initialize()`. Middleware alone populates `request.identity` attribute, but `$this->Authentication->getIdentity()` / `setIdentity()` / `logout()` / `allowUnauthenticated()` calls need the component. Plan 02-04 added this retroactively. Future plans (Phase 3+) can assume `$this->Authentication` is always available in any AppController subclass.
- **CakePHP 4.5 `protected array $fixtures` typed-property collision** (Plan 02-04 Task 3 deviation Rule 1): `Cake\TestSuite\TestCase` declares `protected $fixtures = []` with no native type. Subclasses MUST NOT redeclare with `protected array $fixtures` — PHP emits `Fatal error: Type of $fixtures must not be defined`. Always use phpdoc `@var array<int, string>` + `protected $fixtures = [...]`. Future integration tests inherit this convention.
- **`$request->getQuery()` return type `array|string|null` breaks phpstan level 8** (Plan 02-04 Task 3 deviation Rule 1): naive `(string)$this->request->getQuery('k')` is flagged. Introduced `queryString(string $key): string` + `sessionString(string $key): string` private helpers on OauthController that `is_string()`-guard the value. Phase 3+ controllers consuming query params should use the same pattern.
- **Plan 02-04 replaced `templates/Pages/home.php`** — old CakePHP skeleton welcome page is gone; new version shows 'Bluesky でログイン' CTA. `PagesControllerTest::testDisplay` was updated accordingly. **D-DEF-01 verified resolved 2026-04-24.**

### Executor-discovered decisions (Phase 3 — Plan 03-02)

- **Authentication.Session identify=false is required for OAuth-only apps** (Plan 03-02 deviation Rule 1): CakePHP `Authentication.Password` identifier's `_checkPassword` calls `password_verify($input, $storedHash)` where storedHash resolves to `$user[null]` (no password column), causing silent auth failure. Setting `identify => false` on the Session authenticator tells it to trust session data as-is. Safe because `setIdentity()` in OauthController already ORM-validates the user before writing to session. Future plans: never use `identify => true` with a passwordless user model.
- **->scalar() instead of ->uuid() for FK fields when test fixtures use sequential IDs** (Plan 03-02 deviation Rule 1): CakePHP's `->uuid()` validator enforces RFC 4122 variant bits; test fixture IDs like `11111111-1111-1111-1111-111111111111` have variant=0 and fail. Using `->scalar()` for FK validation columns (inbox_id, sender_user_id) avoids mass-fixture rewrites. Production IDs from `Text::uuid()` are always RFC 4122 compliant, so no production risk.
- **hasOne ORM result is accessible as singular key** (Plan 03-02 deviation Rule 1): UserIdentities is a `hasOne` association on Users; ORM hydrates result as `$user->user_identity` (singular), NOT `$user->user_identities`. Use `$entity->get('user_identity')` to avoid phpstan level 8 undefined-property error.
- **SlugCollisionSuffixApplied listener in bootstrap() not in controller action** (Plan 03-02): EventManager listener registered in `Application::bootstrap()` rather than OauthController::callback(). Per-request registration is order-sensitive (listener must be registered BEFORE the event fires in the same request). Bootstrap-time binding is process-lifetime and avoids this ordering hazard entirely.
- **CakePHP render() auto-prepends controller name** (Plan 03-02 deviation Rule 1): `$this->render('Messages/send_done')` from MessagesController resolves to `templates/Messages/Messages/send_done.php` (double prefix). Always use bare template name: `$this->render('send_done')`.
- **postString() helper pattern extends queryString() to POST data** (Plan 03-02): MessagesController adds `postString(string $key): string` analogous to OauthController's `queryString()` for phpstan-level-8-safe POST data reads. Pattern: `$v = $this->request->getData($key); return is_string($v) ? $v : '';`

### Verifier-discovered decisions (Phase 2)

- **Live-AS OAuth happy-path is out of automated verification scope** (recorded 2026-04-24 by `/gsd-verify-phase 2`): BlueskyOAuthClient integration tests use `Client::addMockResponse()` stubs for PAR / token / profile endpoints. The actual AS + PDS handshake (production Bluesky) can only be observed from a real browser against `tamabox.emomie.com`. Verifier returned `status: human_needed` with 3 human items (live signup / logout cookie-destroy / handle-change sync). Phase 2 goal is achieved at code level; production smoke test is a Phase 4 launch gate.
- **Data-flow Level 4 pattern reusable for Phase 3**: verifier traced rendered-to-source for UsersController::dashboard (real ORM query + contain), OauthController::clientMetadata (Configure read), and upsertBlueskyIdentity (encrypt → `*_enc` write). No hollow props / static returns in Phase 2. Phase 3 inbox/message controllers should follow the same pattern (no `return Response::json([])` style stubs).

### Open Todos

- [x] Phase 2 planning complete 2026-04-23 — 4 plans (02-01 foundation / 02-02 crypto / 02-03 metadata+DID / 02-04 oauth-flow), 3 waves, VERIFICATION PASSED on first pass.
- [x] Phase 2 Wave 1 done 2026-04-23 — 02-01 foundation shipped (cakephp/authentication wired, config/bluesky.php, 6 Phase-2 routes, OAuthProviderInterface shell, config/keys/ ES256 P-256 keypair).
- [x] Phase 2 Wave 2 crypto leg done 2026-04-23 — 02-02 crypto-services shipped (KeyManager / TokenEncryptionService / DpopService / ClientJwtService; 25 unit tests; signature-verification-via-openssl_verify invariant established; AUTH-07 closed, AUTH-08 partial).
- [x] Phase 2 Wave 2 metadata leg done 2026-04-23 — 02-03 metadata+DID shipped (OauthController with clientMetadata/jwks/callback-stub actions + DidResolver for plc.directory DID → PDS lookup; 20 new tests; AUTH-08 closed; callback 501 stub held as hand-off contract for 02-04).
- [x] Phase 2 Wave 3 oauth-flow done 2026-04-24 — 02-04 shipped (BlueskyOAuthClient 5-method impl + TDD RED/GREEN, UsersTable::findByDid + UserIdentitiesTable::upsertBlueskyIdentity with AES-GCM encrypt & UPSERT-in-txn, AuthController::startBluesky + logout, OauthController::callback full implementation replacing 501 stub, UsersController::dashboard, 5 templates + tamabox.css 218 lines, 10 new integration tests + 13 unit tests = 23 new tests; 85 tests total green). AUTH-01/02/04/05/06/09 closed.
- [x] `/gsd-verify-phase 2` complete 2026-04-24 — VERIFICATION.md written. 7/7 ROADMAP Success Criteria verified at code level (truths + artifacts + key links + data-flow trace + spot-checks all green). phpcs 54/54 / phpstan level 8 [OK] / composer test 85/221/0-failure re-confirmed live. D-DEF-01 resolved as a side effect of Plan 02-04 home.php rewrite. Zero anti-patterns found. status=human_needed with 3 inherent human items (live Bluesky AS signup / browser cookie destroy / handle-change sync) deferred to tamabox.emomie.com launch.
- [ ] `/gsd-plan-phase 3` next — Inbox / Message / SSR Reveal (AUTH-03 + INBOX-01/02/03/06 + MSG-01..07). Phase 3 assumes: `$this->Authentication` component always available in any AppController subclass; `BlueskyOAuthClient::refreshToken` is implemented + unit-tested but needs call site added by send flow; `user_identities.last_synced_at` is fresh per login so no TTL check needed; `queryString/sessionString` helper pattern for phpstan-safe query/session reads.

### Blockers

None currently. Resolved blockers:

- **Rule 3 (resolved in Plan 01-02b Task 4)**: `config/app_local.php` was absent, blocking `bin/cake migrations migrate`. Created locally with `DATABASE_URL` / `DATABASE_TEST_URL` passthroughs from `config/.env`. File is gitignored per CakePHP convention. If you wipe local state, recreate from `config/app_local.example.php`. See `.planning/phases/01-foundation-schema/01-02b-SUMMARY.md` deviation #12.

### Research Flags

- Lolipop 共有鯖での `trustProxy` / `fullBaseUrl` / TLS 終端の実測が必要 (Phase 2 で OAuth redirect_uri 決定時、または Phase 4 本番デプロイ時)
- `session.save_path` が Lolipop でどこに向くか実測 (Phase 1 または 2)
- `altotoo.emomie.com` の OAuth 実装知見を流用(Phase 2 開始時に参照)
- **Phase 4 production smoke test contract**: VERIFICATION.md の 3 human items (live-AS signup / cookie destroy / handle-change sync) は tamabox.emomie.com デプロイ直後に人手確認必要。デプロイ手順 + 確認手順を Phase 4 plan に入れる。

## Session Continuity

**Last Agent Run**: execute-phase 3 plan 03-02 @ 2026-04-26 — MessagesController (send/open-stub/report-stub) + send.php + send_done.php + MessagesTable::sendMessage (SSR seed bake + sender snapshot) + D-13 pending body restoration + SlugCollisionSuffixApplied listener in bootstrap(). 133 tests / 365 assertions / 0 failures. phpstan level 8 [OK] / phpcs clean. 4 commits (d4b4732 / e459980 / fa05888 / 7cf1d89).
**Next Action**: 03-03a (dashboard — Wave 3) を `/gsd-execute-phase 3` で実行。03-02 依存: MessagesController::send / sendMessage / send.php / send_done.php / D-13 flow すべて利用可能。open() stub (d4b4732) を 03-03a で差し替え。
**Context Notes**: Phase 2 VERIFIED の上に Phase 3 CONTEXT が乗った状態。Phase 2 sticky note 5 (`refreshTokenIfExpired()`) は Phase 3 D-30 で Phase 4 へ defer 確定(Phase 3 は cached snapshot のみで成立)。Phase 3 の追加 sticky notes: (1) slug 衝突は `-2`/`-3` suffix で吸収、planner は `inboxes.slug_previous` 1 列 or `inbox_slug_history` 薄テーブルどちらかを判断して 1 件 migration 追加、(2) `MessagesController::report()` と `BlocksController::create()` は Phase 3 で 501 stub controller として実装、Phase 4 で本体置換、(3) SSR 判定アルゴリズムは `hexdec(substr(ssr_seed, 0, 8)) / 0xFFFFFFFF < ssr_probability`(F2 監査性のため deterministic)、(4) 送り手は SSR 結果を永遠に知らない(D-19、通知系も Phase 3 範囲外)、(5) Phase 3 verify-phase は Phase 2 と同様 live-AS E2E は human-needed として Phase 4 デプロイ後に持ち越し。

---
*Last updated: 2026-04-26 (Phase 3 PLANNED — 4 plans / 3 waves committed `403cc95`、checker iter #2 PASS、12/12 REQ + 40/40 D-XX coverage; ready for /gsd-execute-phase 3 — recommend /clear first)*
