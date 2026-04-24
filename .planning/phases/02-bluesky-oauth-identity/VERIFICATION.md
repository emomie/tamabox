---
phase: 02-bluesky-oauth-identity
verified: 2026-04-24T06:55:59Z
status: human_needed
score: 7/7 must-haves verified (automated); human verification required for 1 live-AS path
overrides_applied: 0
re_verification: null  # initial verification, no previous VERIFICATION.md
human_verification:
  - test: "End-to-end live OAuth signup via real Bluesky AS"
    expected: "Open tamabox.emomie.com in browser → click 'Bluesky でログイン' → Bluesky 認可画面で承認 → /dashboard に 'ようこそ、<handle> さん' が表示される。DB を覗き、users + user_identities 1 行ずつ生成 / access_token_enc に AES-GCM 暗号値 / 平文トークンは一切 DB に残らない。"
    why_human: "OAuth happy-path の unit/integration は BlueskyOAuthClient mock までしか到達しない (SUMMARY 記載の通り)。本物の Bluesky AS 相手の PAR → 認可 → callback → UPSERT の実接続確認は tamabox.emomie.com 本番デプロイ後に人手テスト必須。Plan 02-04 の integration test matrix でも 'happy path — requires live AS' と明示されている。"
  - test: "ログアウト後の保護リソース再アクセス"
    expected: "サインイン状態で /dashboard に着地 → /oauth/logout に POST (CSRF トークン付き) → Flash 'ログアウトしました' 表示、/ に redirect → 直後に /dashboard を GET すると未認証として / に redirect される"
    why_human: "AuthControllerTest で '/dashboard に未認証アクセスは / へ 302' は検証済 (testDashboardWithoutAuthRedirectsHome)。ただしセッション破棄の完全性 (cookie destroy / session file 削除 / session_regenerate_id) はブラウザレベルの挙動確認が必要。"
  - test: "同一 Bluesky handle で再ログイン時の identity 同期"
    expected: "初回ログイン → handle 変更 (Bluesky 側で) → 再ログイン → DB user_identities.handle_cached / avatar_url_cached / profile_url_cached / last_synced_at が新値で UPDATE されている (新規 user 行は増えていない)"
    why_human: "upsertBlueskyIdentity の existing-user UPDATE path は unit で触れているが、Bluesky AS 側で実際に handle を変えて再ログインしないと 'sync' の意味が確認できない。"
---

# Phase 2: Bluesky OAuth & Identity — Verification Report

**Phase Goal:** 受け手・送り手ともに Bluesky OAuth (AT Protocol, ES256 confidential client, PAR + DPoP + PKCE) で本人確認でき、1 ユーザー = 1 SNS アカウント制約が DB レベルで守られ、アクセストークンは暗号化 DB 格納される。
**Verified:** 2026-04-24T06:55:59Z
**Status:** human_needed (automated gates all pass; live-AS OAuth happy path + browser-session behaviour require human)
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths (ROADMAP Success Criteria 1-7)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | 未登録ユーザが Bluesky OAuth を経てサインアップでき users + user_identities が生成されセッションが確立する | ✓ VERIFIED (code-level) / ? HUMAN (live AS) | `OauthController::callback` 実装済 (L1-135 前後): `exchangeCodeForToken` → DID regex `^did:plc:[a-z2-7]{24}$` guard → `resolveProfile` → `UserIdentitiesTable::upsertBlueskyIdentity` (new-user path: `$connection->transactional()` で users+user_identities INSERT、`Text::uuid()` で PK 生成) → `$this->Authentication->setIdentity()` → `302 /dashboard`。BlueskyOAuthClient unit 13 tests green。live AS happy path は human 必要 (上記) |
| 2 | 既存ユーザが同じ Bluesky ハンドルで再ログインすると handle / avatar / profile_url が user_identities に同期される | ✓ VERIFIED (code-level) / ? HUMAN (sync 実挙動) | `UserIdentitiesTable::upsertBlueskyIdentity` existing-user path (L219-265 前後): `$this->find()->where(['provider' => 'bluesky', 'provider_account_id' => $did])->first()` で既存 row 取得 → `handle_cached / avatar_url_cached / profile_url_cached / last_synced_at / access_token_enc / refresh_token_enc` 全列 UPDATE (users 行は不変)。handle 変更シナリオの live 観察は human |
| 3 | ログアウトでセッション破棄 + 保護リソース再アクセスでログイン画面に戻る | ✓ VERIFIED | `AuthController::logout` (L80-84): `$this->request->allowMethod(['post'])` で CSRF-POST 強制 → `$this->Authentication->logout()` → Flash 'ログアウトしました' → 302 /。未認証 /dashboard 再アクセスは `AuthenticationMiddleware::unauthenticatedRedirect => '/'` で 302 / 済 (`AuthControllerTest::testDashboardWithoutAuthRedirectsHome` green)。ブラウザ cookie 破棄の完全性のみ human |
| 4 | users × user_identities の DB 制約で 1 ユーザーが同一プロバイダで複数 identity を持てない | ✓ VERIFIED | Phase 1 migration `20260422120002_CreateUserIdentities.php` L117: `'name' => 'uk_user_identities_provider_account'` UNIQUE index on (provider, provider_account_id)。Plan 02-04 `upsertBlueskyIdentity` は race 時の `DatabaseException`/`PersistenceFailedException` を scrubbed `RuntimeException` に再 throw (T-02-04-10 mitigation) |
| 5 | /oauth/jwks.json と /oauth/client-metadata.json が公開され Bluesky AS から参照可能 | ✓ VERIFIED | `bin/cake routes` で `oauth:jwks` (GET /oauth/jwks.json) と `oauth:clientmetadata` (GET /oauth/client-metadata.json) 両方登録済。`OauthController::clientMetadata` は `Configure::read('Bluesky.client_metadata')` を JSON で返す (Configure smoke で `client_id = https://tamabox.emomie.com/oauth/client-metadata.json` / `scope = atproto transition:generic` / `token_endpoint_auth_method = private_key_jwt` / `dpop_bound_access_tokens = true` 全部正常値確認)。`OauthController::jwks` は `KeyManager::getPublicJwk()` を `{keys: [jwk]}` で返す。Plan 02-03 integration tests 7 tests green |
| 6 | OAuth access/refresh トークンが *_enc 列に AES-GCM 暗号化で保存され平文が DB に残らない | ✓ VERIFIED | `UserIdentitiesTable::upsertBlueskyIdentity` L163-164: `$tokenSvc = new TokenEncryptionService(); $accessEnc = $tokenSvc->encrypt((string)$tokens['access_token']); $refreshEnc = $tokenSvc->encrypt((string)$tokens['refresh_token']);` — INSERT/UPDATE 時の data array に `access_token_enc => $accessEnc` / `refresh_token_enc => $refreshEnc` としてのみ入り、plaintext カラムは schema に存在しない (Phase 1 DB-SCHEMA)。TokenEncryptionServiceTest (Plan 02-02) が AES-256-GCM IV\|\|CT\|\|TAG round-trip + tamper detection 検証済 |
| 7 | OAuth プロバイダが interface 抽象化され将来 X (Twitter) 追加時に既存コード書き換え不要 | ✓ VERIFIED | `App\Service\OAuth\OAuthProviderInterface` (Plan 02-01 作成、5 methods 宣言) を `App\Service\OAuth\Bluesky\BlueskyOAuthClient` (Plan 02-04) が `implements` で実装。Reflection で `in_array('App\\Service\\OAuth\\OAuthProviderInterface', class_implements('App\\Service\\OAuth\\Bluesky\\BlueskyOAuthClient'))` = true、5 methods `executeParRequest / exchangeCodeForToken / refreshToken / resolveProfile / getProviderKey` すべて `$r->hasMethod()` = true。`getProviderKey()` は `'bluesky'` リテラル返却 (user_identities.provider ENUM と一致) |

**Score:** 7/7 truths verified at code level; 3 of them also have human items for live-AS / browser-cookie end-state observation.

---

## Required Artifacts (Level 1-3)

| Artifact | Expected | Exists | Substantive (≥min_lines, key patterns) | Wired (imported+used) | Status |
|----------|----------|--------|----------------------------------------|------------------------|--------|
| `src/Service/OAuth/OAuthProviderInterface.php` | 5 methods, AUTH-06 abstraction | ✓ (83 lines) | ✓ 5 method sigs grep = 5, `interface OAuthProviderInterface` grep = 1 | ✓ BlueskyOAuthClient implements it | ✓ VERIFIED |
| `src/Service/OAuth/KeyManager.php` | PEM→JWK, ≥80 lines | ✓ (129 lines) | ✓ `openssl_pkey_get_details` present | ✓ DpopService / ClientJwtService / OauthController::jwks use it | ✓ VERIFIED |
| `src/Service/OAuth/TokenEncryptionService.php` | AES-256-GCM, ≥60 lines | ✓ (125 lines) | ✓ `aes-256-gcm` pattern present | ✓ UserIdentitiesTable::upsertBlueskyIdentity uses it (3 refs) | ✓ VERIFIED |
| `src/Service/OAuth/Bluesky/DpopService.php` | RFC 9449, ≥100 lines | ✓ (121 lines) | ✓ `dpop+jwt` pattern | ✓ BlueskyOAuthClient DI receives it | ✓ VERIFIED |
| `src/Service/OAuth/Bluesky/ClientJwtService.php` | private_key_jwt, ≥50 lines | ✓ (110 lines) | ✓ `createAssertion` | ✓ BlueskyOAuthClient DI receives it | ✓ VERIFIED |
| `src/Service/OAuth/Bluesky/DidResolver.php` | DID→PDS, ≥50 lines | ✓ (107 lines) | ✓ `resolveDidToPds`, `Cake\\Http\\Client` | ✓ BlueskyOAuthClient DI receives it | ✓ VERIFIED |
| `src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` | OAuthProviderInterface impl, ≥200 lines | ✓ (343 lines) | ✓ `implements OAuthProviderInterface`, 5 methods, 3x `use_dpop_nonce`, 0x `curl_` | ✓ OauthController::callback + AuthController::startBluesky use it | ✓ VERIFIED |
| `src/Controller/OauthController.php` | clientMetadata + jwks + callback (fully impl), ≥80 lines | ✓ (282 lines) | ✓ `class OauthController`, 0x `withStatus(501)` (stub removed), callback body 100+ lines of real logic | ✓ routes.php wires 3 actions | ✓ VERIFIED |
| `src/Controller/AuthController.php` | startBluesky + logout, ≥60 lines | ✓ (132 lines) | ✓ `class AuthController`, `random_bytes(64)`, `hash('sha256'...)`, `executeParRequest`, `allowMethod(['post'])`, `Authentication->logout()` | ✓ routes.php wires to /login/bluesky + /oauth/logout | ✓ VERIFIED |
| `src/Controller/UsersController.php` | dashboard action, ≥30 lines | ✓ (47 lines) | ✓ `dashboard` method | ✓ routes.php wires to /dashboard | ✓ VERIFIED |
| `src/Model/Table/UserIdentitiesTable.php` | upsertBlueskyIdentity | ✓ (276 lines) | ✓ `upsertBlueskyIdentity` grep = 2, `TokenEncryptionService` grep = 3, `transactional` grep = 1 | ✓ OauthController::callback calls it | ✓ VERIFIED |
| `src/Model/Table/UsersTable.php` | findByDid | ✓ (139 lines) | ✓ `findByDid` present | ✓ Utility finder available for Phase 3 | ✓ VERIFIED |
| `templates/layout/default.php` | lang=ja, tamabox.css, HeaderBar, logout form | ✓ (45 lines) | ✓ `lang="ja"`, `_csrfToken` on logout form | ✓ rendered by all controllers | ✓ VERIFIED |
| `templates/Pages/home.php` | 'Bluesky でログイン' CTA | ✓ (22 lines) | ✓ 'Bluesky でログイン' present | ✓ served by PagesController::display | ✓ VERIFIED |
| `templates/Users/dashboard.php` | 'ようこそ、{handle} さん' | ✓ | ✓ 'ようこそ' present | ✓ rendered by UsersController::dashboard | ✓ VERIFIED |
| `templates/Auth/callback.php` | Spinner interstitial | ✓ | ✓ per SUMMARY (未使用だが UI-SPEC §3 先行宣言 — not a stub by plan definition) | - ORPHANED (known/intentional — Spinner は async flow 用の reserved shell) | ⚠️ DECLARED ORPHAN (accepted by plan 02-04 SUMMARY as 'forward reservation for async flow', not a stub) |
| `templates/element/avatar_handle_chip.php` | alt='{handle} のアイコン' | ✓ | ✓ 'avatar' + alt text per SUMMARY | ✓ included by layout when identity present | ✓ VERIFIED |
| `webroot/css/tamabox.css` | design tokens + components, ≥120 lines | ✓ (218 lines) | ✓ `--color-accent` grep = 9 | ✓ loaded by templates/layout/default.php | ✓ VERIFIED |
| `config/bluesky.php` | Bluesky endpoints + client_metadata | ✓ | ✓ `Configure::read` smoke returns expected values | ✓ loaded by bootstrap.php `Configure::load('bluesky')` | ✓ VERIFIED |
| `config/keys/.gitignore` | ignore *.key, track .gitignore | ✓ | ✓ `*.key` / `!.gitignore` | - | ✓ VERIFIED |
| `config/keys/private.key` | EC P-256, perm 600 | ✓ | ✓ perm = 600, git check-ignore exits 0 | ✓ KeyManager reads via Configure path | ✓ VERIFIED |
| `config/keys/public.key` | EC P-256, perm 644 | ✓ | ✓ perm = 644, git check-ignore exits 0 | ✓ KeyManager reads for JWK | ✓ VERIFIED |

All artifacts pass Levels 1-3. `templates/Auth/callback.php` is the only orphan; it is a declared forward-reservation per UI-SPEC §3 (intentional, not a stub).

---

## Key Link Verification (Level 3)

| From | To | Via | Status | Evidence |
|------|----|----|--------|----------|
| `AuthController::startBluesky` | `BlueskyOAuthClient::executeParRequest` | PKCE gen → session stash → `executeParRequest(challenge, state)` → redirect | ✓ WIRED | L50 `$par = $client->executeParRequest($challenge, $state);` + L62 `return $this->redirect($authUrl);` |
| `OauthController::callback` | `BlueskyOAuthClient::exchangeCodeForToken` → `::resolveProfile` → `UserIdentitiesTable::upsertBlueskyIdentity` → `Authentication->setIdentity` → `/dashboard` | Full chain in try block | ✓ WIRED | `$tokenResp = $client->exchangeCodeForToken($code, $verifier)` → DID regex guard → `$profile = $client->resolveProfile(...)` → `$identitiesTable->upsertBlueskyIdentity(...)` → `setIdentity($user)` → `redirect('/dashboard')` |
| `UserIdentitiesTable::upsertBlueskyIdentity` | `TokenEncryptionService::encrypt` → DB `*_enc` columns | `encrypt()` called BEFORE `INSERT`/`UPDATE` data assembly | ✓ WIRED | L163-164 `$accessEnc = $tokenSvc->encrypt(...)` → L202-203 INSERT / L245-246 UPDATE uses `$accessEnc` / `$refreshEnc` only (no plaintext column in schema) |
| `templates/layout/default.php` HeaderBar | `$this->request->getAttribute('identity')` | AuthenticationMiddleware populates request attribute | ✓ WIRED | Per SUMMARY; `getAttribute('identity')` conditionally renders AvatarHandleChip + logout form |
| `/dashboard` route | AuthenticationMiddleware `unauthenticatedRedirect` | Middleware 302 / when identity missing | ✓ WIRED | Configured in `Application::getAuthenticationService()` with `'unauthenticatedRedirect' => '/'`; `AuthControllerTest::testDashboardWithoutAuthRedirectsHome` green |
| DPoP nonce retry | `BlueskyOAuthClient::postWithNonceRetry` | `use_dpop_nonce` error body + `DPoP-Nonce` header → retry once max | ✓ WIRED | `use_dpop_nonce` grep = 3, `DPoP-Nonce` grep = 7 per SUMMARY; BlueskyOAuthClientTest 2 tests cover retry success/failure |
| logout CSRF | `AuthController::logout` `allowMethod(['post'])` + FormHelper `_csrfToken` | POST only, CSRF token auto in layout logout form | ✓ WIRED | L80-81, layout `_csrfToken` grep = 1 |
| `config/bootstrap.php` | `config/bluesky.php` | `Configure::load('bluesky', 'default', false)` | ✓ WIRED | Configure smoke returns expected values |
| `Application::middleware()` | `AuthenticationMiddleware` | after `CsrfProtectionMiddleware` | ✓ WIRED | `new AuthenticationMiddleware` grep = 1; middleware order enforced (Plan 02-01 Task 2 invariant) |
| `AppController::initialize()` | `Authentication.Authentication` component | `$this->loadComponent('Authentication.Authentication')` | ✓ WIRED | grep = 1 (Plan 02-04 Rule 2 deviation fix) |

All key links verified.

---

## Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
|----------|---------------|--------|--------------------|--------|
| `OauthController::clientMetadata` | `$metadata` | `Configure::read('Bluesky.client_metadata')` (config/bluesky.php) | ✓ (12 keys including client_id / scope / dpop_bound_access_tokens / jwks_uri) | ✓ FLOWING |
| `OauthController::jwks` | `$jwk` | `new KeyManager()` → `getPublicJwk()` (reads config/keys/public.key) | ✓ (kty/crv/kid/use/alg/x/y, no private `d` claim) | ✓ FLOWING |
| `UsersController::dashboard` | User entity with `UserIdentities` | `fetchTable('Users')->find()->contain(['UserIdentities'])->firstOrFail()` | ✓ (real Cake ORM query; dashboard template reads `$user->user_identities[0]->handle_cached` etc.) | ✓ FLOWING |
| `OauthController::callback` profile | `$profile` | `BlueskyOAuthClient::resolveProfile` (DID→PDS→getProfile HTTP chain) | ✓ (handle / avatar / displayName / profile_url) | ✓ FLOWING (mock-verified; live path = human) |
| `upsertBlueskyIdentity` tokens | `access_token_enc` / `refresh_token_enc` | `TokenEncryptionService::encrypt` of plaintext from `$tokens` arg | ✓ (AES-256-GCM IV\|\|CT\|\|TAG base64url) | ✓ FLOWING |

No hollow/disconnected components.

---

## Behavioral Spot-Checks (Step 7b)

| # | Behavior | Command | Result | Status |
|---|----------|---------|--------|--------|
| 1 | OAuthProviderInterface loadable at runtime | `php -r '... interface_exists(...)'` | exit 0 | ✓ PASS |
| 2 | BlueskyOAuthClient implements the interface at runtime (not just syntactically) | `class_implements(...)` contains interface | true | ✓ PASS |
| 3 | All 5 interface methods present via Reflection | `ReflectionClass::hasMethod` × 5 | 5/5 OK | ✓ PASS |
| 4 | Configure Bluesky.* smoke | `Configure::read('Bluesky.client_metadata.*')` | client_id / scope / token_endpoint_auth_method / dpop all match D-06/D-16 contract | ✓ PASS |
| 5 | All 6 Phase 2 routes registered | `bin/cake routes \| grep -E "login\|oauth\|dashboard"` | 6/6 present with correct HTTP methods (POST logout / GET 5) | ✓ PASS |
| 6 | `composer phpstan` level 8 | `composer phpstan` | `[OK] No errors` | ✓ PASS |
| 7 | `composer phpcs` | `composer phpcs` | `54 / 54 (100%)` | ✓ PASS |
| 8 | `composer test` | `composer test` | `85 tests, 221 assertions, 6 Incomplete (pre-existing bake), 0 failures` | ✓ PASS |
| 9 | EC keypair valid P-256 with correct perms | `stat -c '%a' config/keys/*.key` | 600 / 644 | ✓ PASS |
| 10 | Git ignores private key material | `git check-ignore config/keys/private.key` | exit 0 (ignored); `.gitignore` itself tracked (exit 1) | ✓ PASS |
| 11 | D-DEF-01 deprecation trace resolved | `grep -c '\\\$connection' templates/Pages/home.php` + `composer test 2>&1 \| grep -i deprecat` | 0 refs in home.php; only benign `phpunit.xml.dist` schema notice remains | ✓ PASS |

All 11 spot-checks pass.

---

## Requirements Coverage

Phase 2 scope per REQUIREMENTS.md: AUTH-01, AUTH-02, AUTH-04, AUTH-05, AUTH-06, AUTH-07, AUTH-08, AUTH-09 (8 IDs; AUTH-03 is Phase 3).

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|-------------|--------|----------|
| AUTH-01 | 02-04 | Bluesky OAuth signup (PAR+DPoP+PKCE+ES256 confidential client) | ✓ SATISFIED (code) / ? HUMAN (live) | BlueskyOAuthClient 5 methods + unit 13 tests; callback::upsertBlueskyIdentity new-user path |
| AUTH-02 | 02-04 | 既存ユーザ ログイン + セッション確立 | ✓ SATISFIED (code) / ? HUMAN (live) | upsertBlueskyIdentity existing-user UPDATE path + `$this->Authentication->setIdentity($user)` |
| AUTH-04 | 02-04 | 1 ユーザー = 1 SNS 制約 DB 担保 | ✓ SATISFIED | Phase 1 `uk_user_identities_provider_account` UNIQUE + Plan 02-04 race catch (DatabaseException → RuntimeException) |
| AUTH-05 | 02-04 | handle / avatar / profile_url 最新同期 | ✓ SATISFIED (code) / ? HUMAN (handle 変更 live) | upsertBlueskyIdentity が毎ログインで handle_cached / avatar_url_cached / profile_url_cached / last_synced_at を UPDATE |
| AUTH-06 | 02-01 + 02-04 | OAuth プロバイダ抽象化 | ✓ SATISFIED | OAuthProviderInterface 5 methods + BlueskyOAuthClient concrete impl; Reflection-verified |
| AUTH-07 | 02-02 + 02-04 | access/refresh tokens AES-GCM 暗号化 `*_enc` 列格納 | ✓ SATISFIED | TokenEncryptionService + upsertBlueskyIdentity encrypt→write path; schema に plaintext 列なし |
| AUTH-08 | 02-01 + 02-03 | ES256 鍵 + jwks.json + client-metadata.json 公開 | ✓ SATISFIED | config/keys/ EC P-256 600/644; OauthController::clientMetadata + ::jwks actions; routes 登録済; Plan 02-03 integration tests green |
| AUTH-09 | 02-04 | ログアウト (セッション破棄 + CSRF) | ✓ SATISFIED (code) / ? HUMAN (cookie destroy 観察) | AuthController::logout POST-only + CSRF + Authentication->logout + Flash + 302 / |

**Orphaned requirements check:** REQUIREMENTS.md L114 maps AUTH-01..09 (except 03) to Phase 2. All 8 IDs appear in Phase 2 plans' `requirements:` frontmatter (02-01: AUTH-06/08; 02-02: AUTH-07/08; 02-03: AUTH-08; 02-04: AUTH-01/02/04/05/06/09). No orphans.

---

## Anti-Patterns Found

Scanned all 15 Phase 2 source files + 5 templates + CSS for TODO / FIXME / XXX / HACK / PLACEHOLDER / 'not yet implemented' / 'coming soon' / static empty returns / `withStatus(501)` / console.log-only:

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| — | — | — | — | **None found** |

The Plan 02-03 `withStatus(501)` stub in `OauthController::callback` has been fully removed (grep count = 0). `templates/Auth/callback.php` is an intentional forward-reservation (documented in Plan 02-04 SUMMARY §Known Stubs) — not flagged.

---

## Deferred Items (Step 9b) — D-DEF-01 Disposition

`deferred-items.md` listed one item:

- **D-DEF-01** — `templates/Pages/home.php` deprecated `$connection->connect()` call.
  - **Finding:** `grep -c '\\\$connection' templates/Pages/home.php` returns 0. Plan 02-04 Task 3 fully replaced the CakePHP skeleton welcome page with a 22-line OAuth CTA template. `composer test 2>&1 | grep -i deprecated` returns only the benign `phpunit.xml.dist` XML-schema notice (configuration-migration hint, unrelated to D-DEF-01 origin).
  - **Disposition:** **RESOLVED** during Plan 02-04. `deferred-items.md` should have D-DEF-01 struck out or removed. Recommendation: leave a "Resolved 2026-04-24 by Plan 02-04 home.php rewrite" note in the file rather than deleting, for audit traceability. Not blocking Phase 2 — informational.

No deferred items remain for later-phase handling within Phase 2's scope.

---

## Human Verification Required

Automated checks prove code-level correctness of all 7 ROADMAP Success Criteria. These three items require human observation:

### 1. Live Bluesky OAuth signup end-to-end

**Test:** `tamabox.emomie.com` をブラウザで開き 'Bluesky でログイン' ボタン押下 → Bluesky 認可画面で承認 → `/dashboard` に `ようこそ、<handle> さん` が表示されること。DB (`SELECT id, handle_cached, avatar_url_cached, LENGTH(access_token_enc), LENGTH(refresh_token_enc) FROM user_identities WHERE provider='bluesky'`) で 1 行生成 / 暗号文長 > 40 / plaintext 列なし を確認。
**Expected:** サインアップ成功 + user_identities に AES-GCM 暗号トークン格納 + dashboard 到達
**Why human:** integration test は `Client::addMockResponse()` で BlueskyOAuthClient 以下を mock 化。本物の Bluesky AS + PDS 相手の PAR→code→token→profile chain は live HTTP を要し、automated scope 外 (Plan 02-04 SUMMARY の integration test matrix も 'happy path — requires live AS' と明示)。

### 2. ログアウト後の保護リソース再アクセス (cookie 破棄の完全性)

**Test:** サインイン → `/dashboard` 着地 → HeaderBar のログアウトボタン POST → Flash 'ログアウトしました' + / に redirect → 直後 `/dashboard` を GET → `/` に 302 redirect される。ブラウザ devtools で session cookie が新値に regen されている (session fixation 防御) ことを確認。
**Expected:** 未認証リダイレクト + session cookie 値変化 (Authentication plugin の session_regenerate_id 内部呼び出し)
**Why human:** AuthControllerTest で logic は確認済だが、CakePHP ファイルセッションの物理 destroy + cookie SameSite/Secure 属性の挙動はブラウザレベルでしか観察できない。

### 3. 同一 Bluesky handle で handle 変更後の再ログイン sync

**Test:** user A で初回ログイン → Bluesky アプリで handle を変更 → tamabox で再ログイン → `SELECT handle_cached, last_synced_at FROM user_identities WHERE provider='bluesky'` で handle_cached が新値 / last_synced_at が再ログイン時刻 / 新規 users 行は増えていない を確認。
**Expected:** identity UPDATE only (user 行 INSERT なし)、handle 最新化
**Why human:** upsertBlueskyIdentity existing-user path の unit tests は mock profile で動くが、'handle が変わった後' という AS 状態遷移は Bluesky 側で実操作しないと観察できない。

---

## Gaps Summary

**No blocking gaps.** Automated verification passes 7/7 observable truths and all 22 artifacts. All 10 key links wired. Data-flow Level 4 confirms no hollow components. 11/11 behavioral spot-checks pass. phpstan level 8 / phpcs 54/54 / phpunit 85 tests 221 assertions 0 failures.

Three items route to human verification (live Bluesky AS handshake, browser cookie-destroy, handle-change sync) — these are all inherent to the "requires live external OAuth provider" contract and were explicitly marked out of automated scope in the original plans.

D-DEF-01 (pre-existing `$connection->connect()` deprecation) is **resolved** as a side effect of Plan 02-04's full home.php rewrite. `deferred-items.md` can be updated (not blocking).

**Phase 2 goal — receiver & sender can authenticate via Bluesky OAuth, 1:1 DB constraint holds, tokens encrypted — is achieved at code level. Pending: production smoke test on tamabox.emomie.com after deployment.**

---

*Verified: 2026-04-24T06:55:59Z*
*Verifier: Claude (gsd-verifier)*
