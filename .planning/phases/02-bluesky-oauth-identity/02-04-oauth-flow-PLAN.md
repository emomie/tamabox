---
phase: 02-bluesky-oauth-identity
plan: 04
type: execute
wave: 3
depends_on:
  - 02-02
  - 02-03
files_modified:
  - src/Service/OAuth/Bluesky/BlueskyOAuthClient.php
  - src/Controller/AuthController.php
  - src/Controller/UsersController.php
  - src/Controller/OauthController.php
  - src/Model/Table/UsersTable.php
  - src/Model/Table/UserIdentitiesTable.php
  - src/Model/Entity/User.php
  - src/Model/Entity/UserIdentity.php
  - templates/layout/default.php
  - templates/Pages/home.php
  - templates/Auth/callback.php
  - templates/Users/dashboard.php
  - templates/element/avatar_handle_chip.php
  - webroot/css/tamabox.css
  - tests/TestCase/Service/OAuth/Bluesky/BlueskyOAuthClientTest.php
  - tests/TestCase/Controller/AuthControllerTest.php
  - tests/TestCase/Controller/OauthControllerCallbackTest.php
autonomous: true
requirements:
  - AUTH-01
  - AUTH-02
  - AUTH-04
  - AUTH-05
  - AUTH-09
tags:
  - oauth
  - bluesky
  - flow
  - par
  - token-exchange
  - session
  - upsert
  - authentication-plugin
  - templates
  - css
  - integration-test

must_haves:
  truths:
    - "BlueskyOAuthClient implements OAuthProviderInterface and uses DpopService + ClientJwtService + DidResolver via constructor DI"
    - "BlueskyOAuthClient::executeParRequest sends DPoP + client_assertion to BLUESKY_PAR_ENDPOINT, handles use_dpop_nonce retry (max 1), expects HTTP 201, returns {request_uri, expires_in}"
    - "BlueskyOAuthClient::exchangeCodeForToken sends code + code_verifier + client_assertion + DPoP (with nonce if available), expects HTTP 200, returns {access_token, refresh_token, token_type, expires_in, sub}"
    - "BlueskyOAuthClient::resolveProfile calls DidResolver for PDS URL then PDS getProfile with DPoP+ath claim, returns {handle, avatar, displayName, profile_url}"
    - "AuthController::startBluesky generates PKCE verifier+challenge (random_bytes 64 / sha256 base64url), generates state (random_bytes 32 base64url), saves both to session, calls BlueskyOAuthClient::executeParRequest, redirects to auth_endpoint?client_id=<>&request_uri=<>"
    - "AuthController::logout validates POST + CSRF, calls $this->Authentication->logout(), sets Flash success 'ログアウトしました', redirects to /"
    - "OauthController::callback (replacing Plan 02-03 stub): validates state vs session, validates iss, calls BlueskyOAuthClient::exchangeCodeForToken, validates DID format, calls resolveProfile, UPSERTs user + user_identities (AES-GCM encrypts tokens), calls $this->Authentication->setIdentity, redirects /dashboard on success or / on error with Flash"
    - "UsersController::dashboard requires authentication, fetches current user + identity, renders templates/Users/dashboard.php with handle + welcome copy per UI-SPEC §4"
    - "UPSERT logic in UserIdentitiesTable::upsertBlueskyIdentity creates or updates user+identity in a single transaction; unique-constraint violation (race) → RuntimeException"
    - "templates/layout/default.php uses lang='ja', loads tamabox.css, renders HeaderBar with AvatarHandleChip + logout form when identity present"
    - "templates/Pages/home.php contains <form method='POST' action='/login/bluesky'> with CSRF token and 'Bluesky でログイン' button per UI-SPEC §4"
    - "templates/Users/dashboard.php displays 'ようこそ、<handle> さん' + subtext per UI-SPEC §4"
    - "webroot/css/tamabox.css defines :root custom properties + PrimaryButton + Alert + Spinner + AvatarHandleChip + HeaderBar classes per UI-SPEC §7"
    - "composer test exits 0 with all Phase 2 tests + Phase 1 baseline green; integration tests cover startBluesky redirect, callback success, callback state-mismatch, callback error param, logout"
  artifacts:
    - path: "src/Service/OAuth/Bluesky/BlueskyOAuthClient.php"
      provides: "Full OAuthProviderInterface impl (PAR / token / refresh / profile) with nonce retry + DPoP binding"
      min_lines: 200
      contains: "implements OAuthProviderInterface"
    - path: "src/Controller/AuthController.php"
      provides: "startBluesky (PKCE + PAR + redirect) + logout (CSRF POST)"
      min_lines: 60
      contains: "class AuthController"
    - path: "src/Controller/UsersController.php"
      provides: "dashboard action (protected route, renders user+identity)"
      min_lines: 30
      contains: "dashboard"
    - path: "src/Model/Table/UserIdentitiesTable.php"
      provides: "upsertBlueskyIdentity method handling new-user and existing-user paths in a single transaction"
      contains: "upsertBlueskyIdentity"
    - path: "templates/layout/default.php"
      provides: "Japanese lang, tamabox.css, HeaderBar with conditional AvatarHandleChip + logout form"
      contains: "lang=\"ja\""
    - path: "templates/Pages/home.php"
      provides: "CTA form POST /login/bluesky with CSRF token + 'Bluesky でログイン' button"
      contains: "Bluesky でログイン"
    - path: "templates/Auth/callback.php"
      provides: "Interstitial spinner page per UI-SPEC §3 (rendered only if error path needs HTML; default path is redirect)"
      contains: "Bluesky と通信中"
    - path: "templates/Users/dashboard.php"
      provides: "Dashboard with welcome + placeholder + avatar chip"
      contains: "受信箱はまだ作成されていません"
    - path: "templates/element/avatar_handle_chip.php"
      provides: "Reusable avatar + handle chip with alt=\"{handle} のアイコン\""
      contains: "avatar"
    - path: "webroot/css/tamabox.css"
      provides: "Design tokens + component CSS per UI-SPEC"
      min_lines: 120
      contains: "--color-accent"
  key_links:
    - from: "AuthController::startBluesky"
      to: "BlueskyOAuthClient::executeParRequest"
      via: "PKCE generated → saved to session → client->executeParRequest(challenge, state) → redirect"
      pattern: "executeParRequest"
    - from: "OauthController::callback"
      to: "BlueskyOAuthClient::exchangeCodeForToken + resolveProfile + UserIdentitiesTable::upsertBlueskyIdentity"
      via: "code → token → profile → DB UPSERT → Authentication->setIdentity"
      pattern: "exchangeCodeForToken|upsertBlueskyIdentity|setIdentity"
    - from: "UserIdentitiesTable::upsertBlueskyIdentity"
      to: "TokenEncryptionService::encrypt"
      via: "Before save, encrypt access_token + refresh_token to *_enc columns"
      pattern: "TokenEncryptionService|access_token_enc"
    - from: "templates/layout/default.php HeaderBar"
      to: "$this->request->getAttribute('identity')"
      via: "AuthenticationMiddleware populates request attribute; layout conditionally shows AvatarHandleChip"
      pattern: "getAttribute\\('identity'\\)"
    - from: "/dashboard route"
      to: "AuthenticationMiddleware unauthenticatedRedirect"
      via: "UsersController::initialize() or action-level check redirects unauthenticated users to /"
      pattern: "Authentication|identity"
---

<objective>
Phase 2 の **OAuth エンドツーエンドフロー** を完成させ、残りの 5 要件 (AUTH-01/02/04/05/09) をクローズする。

具体的には:
1. `BlueskyOAuthClient` を `OAuthProviderInterface` 実装として書き、PAR / token exchange / refresh / resolveProfile を DpopService + ClientJwtService + TokenEncryptionService + DidResolver と結線する。altotoo `callApi` / nonce retry / cURL header 分離パターンを踏襲 (CONTEXT D-10)。
2. `AuthController::startBluesky` + `AuthController::logout`、および Plan 02-03 で stub にしていた `OauthController::callback` の body を実装する。state / nonce / PKCE verifier はすべてセッション経由でやりとり (CONTEXT D-08)。
3. `user_identities` UPSERT ロジック: 新規ユーザは users + user_identities を 1 transaction で INSERT、既存ユーザは user_identities のみ UPDATE。トークンは AES-GCM 暗号化して `*_enc` 列へ格納 (D-08 / D-09 / AUTH-04 / AUTH-07)。
4. `UsersController::dashboard` プレースホルダ + テンプレート 5 本 + `webroot/css/tamabox.css` を作成し、UI-SPEC v1 の最小表面 (home / dashboard / layout / callback / element) を実装する。
5. Integration tests: PAR redirect / callback happy / state mismatch / error=access_denied / logout / dashboard unauthenticated redirect の 6 ケース以上。BlueskyOAuthClient 自体は `Cake\Http\Client` の Mock Adapter で単体テスト。

Purpose:
- ROADMAP Phase 2 success criteria #1 (未登録サインアップ) / #2 (既存ログイン) / #3 (ログアウト) / #4 (1:1 DB 制約 race 対応) / #6 (token *_enc 暗号化) を全達成
- UI-SPEC §4/§5 のハッピー + エラーパス (a/b/c/d/e/f) に対応するエラーハンドリング
- UI-SPEC §6 アクセシビリティ契約 (lang=ja / フォーカスリング / role=alert / no-JS 動作) を達成
- AUTH-08 (jwks + metadata) は Plan 02-03 で closed 済

Output:
- 1 Service クラス (BlueskyOAuthClient) + 3 Controller 更新 (Auth 新規 / Users 新規 / Oauth callback 埋め込み)
- 2 Table 更新 (UsersTable / UserIdentitiesTable に findByDid / upsertBlueskyIdentity 追加)
- 5 template + 1 CSS
- 3 test ファイル (BlueskyOAuthClient unit + Auth integration + Oauth callback integration)
- composer test green、ROADMAP Phase 2 success criteria 7 項目すべて observable state

注意: この Plan は Phase 2 の終着駅。Plan 終了時点で `/gsd-verify-phase 2` が走れる状態にする。
</objective>

<execution_context>
@/home/claude/.claude/get-shit-done/workflows/execute-plan.md
@/home/claude/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@/home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-CONTEXT.md
@/home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-RESEARCH.md
@/home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-PATTERNS.md
@/home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-UI-SPEC.md
@/home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-01-foundation-setup-PLAN.md
@/home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-02-crypto-services-PLAN.md
@/home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-03-metadata-did-PLAN.md
@/home/claude/projects/tamabox/.planning/references/altotoo/BlueskyOauthComponent.php
@/home/claude/projects/tamabox/.planning/references/altotoo/LoginController.php
@/home/claude/projects/tamabox/src/Service/OAuth/OAuthProviderInterface.php
@/home/claude/projects/tamabox/src/Service/OAuth/KeyManager.php
@/home/claude/projects/tamabox/src/Service/OAuth/TokenEncryptionService.php
@/home/claude/projects/tamabox/src/Service/OAuth/Bluesky/DpopService.php
@/home/claude/projects/tamabox/src/Service/OAuth/Bluesky/ClientJwtService.php
@/home/claude/projects/tamabox/src/Service/OAuth/Bluesky/DidResolver.php
@/home/claude/projects/tamabox/src/Controller/OauthController.php
@/home/claude/projects/tamabox/src/Controller/AppController.php
@/home/claude/projects/tamabox/src/Model/Table/UsersTable.php
@/home/claude/projects/tamabox/src/Model/Table/UserIdentitiesTable.php
@/home/claude/projects/tamabox/src/Model/Entity/UserIdentity.php
@/home/claude/projects/tamabox/templates/layout/default.php
@/home/claude/projects/tamabox/templates/Pages/home.php

<interfaces>
<!-- DI graph established in Plan 02-01/02/03 — Plan 02-04 consumes them -->

BlueskyOAuthClient (this plan, NEW) wires together:
- \App\Service\OAuth\Bluesky\DpopService           (Plan 02-02)
- \App\Service\OAuth\Bluesky\ClientJwtService      (Plan 02-02)
- \App\Service\OAuth\Bluesky\DidResolver           (Plan 02-03)
- \App\Service\OAuth\TokenEncryptionService        (Plan 02-02 — used by UserIdentitiesTable, not directly by this client)
- \App\Service\OAuth\KeyManager                    (Plan 02-02)
- \Cake\Http\Client (optional DI — mockable for tests)

Controller DI pattern (CakePHP 4 workaround — no automatic action-level DI):
```php
// AuthController / OauthController build the service graph inline:
$km         = new KeyManager();
$dpop       = new DpopService($km);
$clientJwt  = new ClientJwtService($km);
$didResolver = new DidResolver();
$client     = new BlueskyOAuthClient($dpop, $clientJwt, $didResolver);
```
(This keeps Plan 02-04 free of DI container wiring; Plan 02-04 `initialize()` or action-bodies construct as needed. CakePHP 5 DI container adoption is future work.)

Phase 1 `user_identities` schema columns available for UPSERT (per 01-02a-SUMMARY):
id (CHAR(36) UUID PK), user_id (CHAR(36) FK→users CASCADE), provider (ENUM 'bluesky','x'),
provider_account_id (VARCHAR(255) — holds DID), handle_cached (VARCHAR(255) NOT NULL),
avatar_url_cached (VARCHAR(2048) NULL), profile_url_cached (VARCHAR(2048) NULL),
access_token_enc (TEXT NULL), refresh_token_enc (TEXT NULL), token_expires_at (DATETIME(6) NULL),
last_synced_at (DATETIME(6) NULL), is_primary (TINYINT(1) DEFAULT 1),
created_at/updated_at (DATETIME(6) + TIMESTAMP(6)).

Unique indexes: (provider, provider_account_id), (user_id).

users columns: id (CHAR(36)), display_name (VARCHAR(64)), created_at/updated_at/deleted_at.

Timestamp behavior on UsersTable + UserIdentitiesTable + InboxesTable:
`created_at => 'new'` + `updated_at => 'always'` (Plan 01-03).

UUID generation: Phase 1 Plan 01-03 handoff note says "UsersTable and UserIdentitiesTable MUST call \Cake\Utility\Text::uuid() before save, or add a beforeSave hook". Plan 02-04 Task 2 handles this explicitly in upsertBlueskyIdentity.

CSRF: /login/bluesky POST and /oauth/logout POST both need CSRF token (CakePHP FormHelper adds automatically).

/oauth/callback GET is **CSRF-exempt by CakePHP default** (CSRFProtectionMiddleware only applies to unsafe methods POST/PUT/PATCH/DELETE — confirmed RESEARCH §Security Considerations / Open Questions Q3). State parameter is the app-layer replacement.

Authentication plugin identity:
- `$this->Authentication->setIdentity($user)` — $user is an entity resolving to user_id (UUID)
- `$this->request->getAttribute('identity')` — in templates, `$this->getRequest()->getAttribute('identity')`
- `$this->Authentication->logout()` — destroys the identity session
- `session_regenerate_id(true)` is called by setIdentity (A3 ASSUMED, CakePHP Authentication 2.x)

UI-SPEC §4 copy literals (JA, fixed):
- ホーム CTA: `Bluesky でログイン`
- Callback interstitial 見出し: `Bluesky と通信中…`
- Dashboard ウェルカム: `ようこそ、{handle} さん`
- Dashboard サブ: `受信箱はまだ作成されていません。受信箱の作成は次のステップで行います。`
- Logout button: `ログアウト`
- Error flash (a): `ログインをキャンセルしました` + `Bluesky の認証画面でキャンセルされました。再度ログインするには下のボタンを押してください。`
- Error flash (b): `ログインに失敗しました` + `セッションの整合性を確認できませんでした。再度ログインしてください。（エラーコード: STATE_MISMATCH）`
- Error flash (c): `ログインに失敗しました` + `Bluesky との通信中にセキュリティエラーが発生しました。しばらくしてから再度お試しください。（エラーコード: DPOP_REJECTED）`
- Error flash (d): `ログインに失敗しました` + `Bluesky からアクセス権限を取得できませんでした。しばらくしてから再度お試しください。（エラーコード: TOKEN_EXCHANGE_FAILED）`
- Error flash (e): `接続できませんでした` + `Bluesky のサーバーに接続できませんでした。ネットワーク接続を確認のうえ、再度お試しください。`
- Error flash (f): `ログインが必要です` + `セッションの有効期限が切れました。再度ログインしてください。`
- Logout success flash: `ログアウトしました`
</interfaces>
</context>

<threat_model>
## Trust Boundaries

| Boundary | Description |
|----------|-------------|
| browser → /login/bluesky POST | CSRF-protected; redirect target computed server-side |
| Bluesky AS → /oauth/callback GET | Untrusted — all query params (code, state, iss, error) must be validated |
| Bluesky AS → token endpoint response | Untrusted JSON — access_token / refresh_token / sub must be type-checked |
| plc.directory → DidResolver → memory | Plan 02-03 covers this boundary |
| decrypted access_token → PDS getProfile | access_token only exists in memory during callback; never logged |
| session storage → PHP file session (Lolipop filesystem) | pkce_verifier / oauth_state stored transiently, cleared after callback |

## STRIDE Threat Register

| Threat ID | Category | Component | Disposition | Mitigation Plan |
|-----------|----------|-----------|-------------|-----------------|
| T-02-04-01 | Spoofing (CSRF) | /login/bluesky POST | mitigate | FormHelper emits CSRF token; POST without token → CakePHP 403; integration test `testLoginBlueskyWithoutCsrfRejected` asserts 403 |
| T-02-04-02 | Spoofing (state replay) | /oauth/callback | mitigate | `state` generated as `base64url(random_bytes(32))`, stored in session, single-use (deleted after compare), compared with `hash_equals` (constant-time); integration test `testCallbackStateMismatchFlashesError` asserts 302→/ with flash 'STATE_MISMATCH' |
| T-02-04-03 | Tampering (DPoP proof forge) | PAR / token exchange | mitigate | Plan 02-02 DpopService unit-tested for signature validity; BlueskyOAuthClient only uses that service — no ad-hoc JWT construction in this plan |
| T-02-04-04 | Information Disclosure (token exfiltration) | user_identities | mitigate | access_token / refresh_token NEVER persisted raw; AES-GCM via TokenEncryptionService BEFORE save; schema columns are named `*_enc` to signal this; integration test `testCallbackPersistsTokensEncrypted` asserts DB row's `access_token_enc` does NOT contain raw token substring |
| T-02-04-05 | Session fixation | Post-login session | mitigate | `$this->Authentication->setIdentity()` triggers `session_regenerate_id(true)` (Authentication plugin 2.x default); integration test asserts session ID changes before/after setIdentity (`testSessionIdRotatesAfterLogin`) |
| T-02-04-06 | Open redirect | /oauth/callback post-auth redirect | mitigate | Post-login redirect target is HARDCODED `/dashboard` — no query-param-driven redirect; `unauthenticatedRedirect` (Plan 02-01) is also hardcoded to `/`; integration tests assert redirect destination string |
| T-02-04-07 | Replay (nonce reuse) | DPoP nonce handling | mitigate | `bsky_as_nonce` session key overwritten with each AS response; retry limited to 1 attempt (Plan 02-02 DpopService + this plan's BlueskyOAuthClient::executeParRequest loop-exit assertion) |
| T-02-04-08 | Information Disclosure (log leak) | Log::write during OAuth flow | mitigate | Controller catches RuntimeException and logs only `$e->getMessage()` — exception messages never include access_token / refresh_token / private_key content (verified by code review); test `testRuntimeExceptionMessageContainsNoSecret` validates |
| T-02-04-09 | Tampering (sub claim injection) | token response `sub` field | mitigate | After exchangeCodeForToken, controller validates `preg_match('/^did:plc:[a-z2-7]{24}$/', $did)` — rejects malformed subs before DB write |
| T-02-04-10 | Elevation (existing-user takeover via race) | UPSERT UNIQUE violation | mitigate | DB `uk_user_identities_provider_account` (Phase 1) + try/catch DatabaseException in upsertBlueskyIdentity; integration test with DatabaseException mock asserts error flash returned rather than silent takeover |
| T-02-04-11 | Denial-of-service | Unbounded DPoP-Nonce retry | mitigate | executeParRequest + exchangeCodeForToken each retry at most ONCE (altotoo pattern, CONTEXT D-10); unit test `testPar429OnSecondAttemptThrows` verifies second failure propagates |
| T-02-04-12 | Information Disclosure (session read without consent) | pkce_verifier exposure | mitigate | pkce_verifier + oauth_state unset from session immediately after successful callback token exchange (defense-in-depth) |
| T-02-04-13 | Spoofing (iss claim omitted) | /oauth/callback | mitigate | Controller validates `$_GET['iss'] === Configure::read('Bluesky.issuer')` when iss parameter is present; log but don't reject if absent (AT Protocol PAR-flow issuer presence varies) |
| T-02-04-14 | Information Disclosure (unauthenticated /dashboard) | UsersController::dashboard | mitigate | AuthenticationMiddleware + `unauthenticatedRedirect=/` (Plan 02-01) auto-redirects unauthenticated hits; integration test `testDashboardWithoutAuthRedirectsHome` asserts 302→/ |
| T-02-04-15 | Profile-fetch failure misclassified | new vs existing user divergent paths | mitigate | Per CONTEXT `<specifics>`: new-user path re-raises profile-fetch failure as OAuth failure (no incomplete row); existing-user path preserves cached values and proceeds. Unit+integration tests cover both paths |
</threat_model>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: BlueskyOAuthClient + unit tests (PAR / token exchange / profile / nonce retry / resolveProfile)</name>
  <files>src/Service/OAuth/Bluesky/BlueskyOAuthClient.php, tests/TestCase/Service/OAuth/Bluesky/BlueskyOAuthClientTest.php</files>

  <behavior>
    - BlueskyOAuthClient implements OAuthProviderInterface
    - executeParRequest($challenge, $state) sends POST to par_endpoint with DPoP + client_assertion + params; on HTTP 201, returns {request_uri, expires_in}; on 400/401 with body.error=='use_dpop_nonce', extracts DPoP-Nonce header and retries once with nonce; on second failure or non-201 on retry, throws RuntimeException
    - exchangeCodeForToken($code, $verifier) sends POST to token_endpoint with grant_type=authorization_code + code + code_verifier + client_id + client_assertion + DPoP (with session nonce if any); same retry semantics; on 200, returns {access_token, refresh_token, token_type, expires_in, sub}
    - refreshToken($refreshToken) sends POST to token_endpoint with grant_type=refresh_token; same retry; returns {access_token, refresh_token, expires_in}
    - resolveProfile($did, $accessToken) calls DidResolver to get PDS, then GET <pds>/xrpc/app.bsky.actor.getProfile?actor=<did> with Authorization: DPoP <token> + DPoP proof (with ath); returns {handle, avatar:?string, displayName:?string, profile_url:string} where profile_url='https://bsky.app/profile/<handle>'
    - getProviderKey() returns 'bluesky' (matches user_identities.provider ENUM)
    - All HTTP calls use Cake\Http\Client (DI'd or defaulted); nonce storage is caller's responsibility (passed via constructor-injected $nonceState object OR returned in the tuple) — simplified: we use an internal property $lastAsNonce that caller can read after each call, OR BlueskyOAuthClient exposes separate getLastAsNonce() for Plan 02-04 Task 3 controller to sync into session
    - Unit tests use Cake\Http\Client\Adapter\Mock to fake responses; ≥ 10 test cases cover: PAR success / PAR nonce retry success / PAR retry failure / token exchange success / token nonce retry / token non-200 / refresh success / resolveProfile happy path / resolveProfile getProfile 401 / getProviderKey returns 'bluesky'
  </behavior>

  <read_first>
    - /home/claude/projects/tamabox/.planning/references/altotoo/BlueskyOauthComponent.php 全文 (callApi / executeParRequest / exchangeCodeForToken / resolveDidToPds / getProfile の実装順序 — VERBATIM pattern donor per CONTEXT D-01)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-CONTEXT.md D-10 (nonce retry) / D-13 (ath claim) / D-15 (token encryption handled by UserIdentitiesTable not here)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-RESEARCH.md §AT Protocol OAuth Flow / §DPoP-Nonce リトライパターン / §Profile 取得 API / §Code Examples
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-PATTERNS.md §`BlueskyOAuthClient.php` (sendRequest nonce retry code + cURL → Cake\Http\Client 差し替えのノート)
    - /home/claude/projects/tamabox/src/Service/OAuth/OAuthProviderInterface.php (5 method sigs must match)
    - /home/claude/projects/tamabox/src/Service/OAuth/Bluesky/DpopService.php (createProof($htm, $htu, $accessToken?, $nonce?) シグネチャ)
    - /home/claude/projects/tamabox/src/Service/OAuth/Bluesky/ClientJwtService.php (createAssertion($audience) シグネチャ)
    - /home/claude/projects/tamabox/src/Service/OAuth/Bluesky/DidResolver.php (resolveDidToPds($did) シグネチャ)
    - /home/claude/projects/tamabox/tests/TestCase/Service/OAuth/Bluesky/DidResolverTest.php (Cake\Http\Client\Adapter\Mock の利用パターン参考)
  </read_first>

  <action>

  ## A. `src/Service/OAuth/Bluesky/BlueskyOAuthClient.php`

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Service\OAuth\Bluesky;

  use App\Service\OAuth\OAuthProviderInterface;
  use Cake\Core\Configure;
  use Cake\Http\Client;
  use RuntimeException;

  /**
   * Bluesky (AT Protocol) OAuth 2.0 client — PAR + PKCE + DPoP + private_key_jwt.
   *
   * Implements AUTH-06's OAuthProviderInterface. Depends on Plan 02-02 crypto services
   * (DpopService, ClientJwtService) and Plan 02-03 DidResolver.
   *
   * HTTP is via Cake\Http\Client (DI'd, mockable). cURL direct calls are NOT used,
   * diverging from altotoo's cURL pattern for testability (RESEARCH.md Open Question Q1).
   *
   * DPoP-Nonce retry (CONTEXT D-10): initial request without nonce; if response is 400/401
   * and body.error == 'use_dpop_nonce', extract DPoP-Nonce header and retry once with nonce.
   * Also stash the nonce in $this->lastAsNonce so the caller (OauthController::callback) can
   * save it to session for the next request within the same flow.
   */
  final class BlueskyOAuthClient implements OAuthProviderInterface
  {
      private const PROVIDER_KEY    = 'bluesky';
      private const PAR_SUCCESS     = 201;
      private const TOKEN_SUCCESS   = 200;
      private const PROFILE_SUCCESS = 200;

      private Client $http;
      private ?string $lastAsNonce = null;

      public function __construct(
          private readonly DpopService $dpop,
          private readonly ClientJwtService $clientJwt,
          private readonly DidResolver $didResolver,
          ?Client $http = null,
          private readonly ?string $initialAsNonce = null
      ) {
          $this->http = $http ?? new Client(['timeout' => 15]);
          $this->lastAsNonce = $this->initialAsNonce;
      }

      public function getProviderKey(): string
      {
          return self::PROVIDER_KEY;
      }

      public function getLastAsNonce(): ?string
      {
          return $this->lastAsNonce;
      }

      public function executeParRequest(string $codeChallenge, string $state): array
      {
          $endpoint = (string)Configure::read('Bluesky.par_endpoint');
          $clientId = (string)Configure::read('Bluesky.client_id');
          $redirect = (string)Configure::read('Bluesky.redirect_uri');
          $scope    = (string)Configure::read('Bluesky.client_metadata.scope');

          $params = [
              'client_id'             => $clientId,
              'response_type'         => 'code',
              'code_challenge'        => $codeChallenge,
              'code_challenge_method' => 'S256',
              'redirect_uri'          => $redirect,
              'state'                 => $state,
              'scope'                 => $scope,
              'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
              'client_assertion'      => $this->clientJwt->createAssertion($endpoint),
          ];

          $response = $this->postWithNonceRetry('POST', $endpoint, $params);
          $this->assertStatus($response['code'], self::PAR_SUCCESS, 'PAR', $response['body']);

          $body = json_decode($response['body'], true);
          if (!is_array($body) || !isset($body['request_uri'])) {
              throw new RuntimeException('PAR response missing request_uri.');
          }

          return [
              'request_uri' => (string)$body['request_uri'],
              'expires_in'  => (int)($body['expires_in'] ?? 60),
          ];
      }

      public function exchangeCodeForToken(string $code, string $codeVerifier): array
      {
          $endpoint = (string)Configure::read('Bluesky.token_endpoint');
          $clientId = (string)Configure::read('Bluesky.client_id');
          $redirect = (string)Configure::read('Bluesky.redirect_uri');

          $params = [
              'grant_type'            => 'authorization_code',
              'code'                  => $code,
              'redirect_uri'          => $redirect,
              'code_verifier'         => $codeVerifier,
              'client_id'             => $clientId,
              'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
              'client_assertion'      => $this->clientJwt->createAssertion($endpoint),
          ];

          $response = $this->postWithNonceRetry('POST', $endpoint, $params);
          $this->assertStatus($response['code'], self::TOKEN_SUCCESS, 'TOKEN_EXCHANGE', $response['body']);

          $body = json_decode($response['body'], true);
          foreach (['access_token', 'refresh_token', 'token_type', 'expires_in', 'sub'] as $required) {
              if (!is_array($body) || !isset($body[$required])) {
                  throw new RuntimeException("Token response missing `$required`.");
              }
          }

          return [
              'access_token'  => (string)$body['access_token'],
              'refresh_token' => (string)$body['refresh_token'],
              'token_type'    => (string)$body['token_type'],
              'expires_in'    => (int)$body['expires_in'],
              'sub'           => (string)$body['sub'],
          ];
      }

      public function refreshToken(string $refreshToken): array
      {
          $endpoint = (string)Configure::read('Bluesky.token_endpoint');
          $clientId = (string)Configure::read('Bluesky.client_id');

          $params = [
              'grant_type'            => 'refresh_token',
              'refresh_token'         => $refreshToken,
              'client_id'             => $clientId,
              'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
              'client_assertion'      => $this->clientJwt->createAssertion($endpoint),
          ];

          $response = $this->postWithNonceRetry('POST', $endpoint, $params);
          $this->assertStatus($response['code'], self::TOKEN_SUCCESS, 'REFRESH', $response['body']);

          $body = json_decode($response['body'], true);
          foreach (['access_token', 'refresh_token', 'expires_in'] as $required) {
              if (!is_array($body) || !isset($body[$required])) {
                  throw new RuntimeException("Refresh response missing `$required`.");
              }
          }

          return [
              'access_token'  => (string)$body['access_token'],
              'refresh_token' => (string)$body['refresh_token'],
              'expires_in'    => (int)$body['expires_in'],
          ];
      }

      public function resolveProfile(string $did, string $accessToken): array
      {
          $pds = $this->didResolver->resolveDidToPds($did);
          $endpoint = $pds . '/xrpc/app.bsky.actor.getProfile';
          $urlWithQuery = $endpoint . '?actor=' . rawurlencode($did);

          // getProfile is GET; DPoP proof htu is the endpoint WITHOUT query string (RFC 9449 §4.2).
          $dpopProof = $this->dpop->createProof('GET', $endpoint, $accessToken, $this->lastAsNonce);
          $headers = [
              'Authorization' => 'DPoP ' . $accessToken,
              'DPoP'          => $dpopProof,
              'Accept'        => 'application/json',
          ];

          $resp = $this->http->get($urlWithQuery, [], ['headers' => $headers]);
          $this->captureNonceFromResponse($resp);

          // Single nonce retry for RS calls as well (AS and RS may share nonce state).
          if ($resp->getStatusCode() === 401 || $resp->getStatusCode() === 400) {
              $body = $resp->getJson();
              if (is_array($body) && ($body['error'] ?? '') === 'use_dpop_nonce' && $this->lastAsNonce !== null) {
                  $dpopProof = $this->dpop->createProof('GET', $endpoint, $accessToken, $this->lastAsNonce);
                  $headers['DPoP'] = $dpopProof;
                  $resp = $this->http->get($urlWithQuery, [], ['headers' => $headers]);
                  $this->captureNonceFromResponse($resp);
              }
          }

          if ($resp->getStatusCode() !== self::PROFILE_SUCCESS) {
              throw new RuntimeException('Profile fetch failed (HTTP ' . $resp->getStatusCode() . ').');
          }

          $profile = $resp->getJson();
          if (!is_array($profile) || !isset($profile['handle']) || !is_string($profile['handle']) || $profile['handle'] === '') {
              throw new RuntimeException('Profile response missing handle.');
          }

          $handle = (string)$profile['handle'];

          return [
              'handle'       => $handle,
              'avatar'       => isset($profile['avatar']) && is_string($profile['avatar']) ? $profile['avatar'] : null,
              'displayName'  => isset($profile['displayName']) && is_string($profile['displayName']) ? $profile['displayName'] : null,
              'profile_url'  => 'https://bsky.app/profile/' . $handle,
          ];
      }

      /**
       * POST with DPoP + DPoP-Nonce retry (max 1 retry per CONTEXT D-10).
       *
       * @return array{code: int, body: string, headers: array}
       */
      private function postWithNonceRetry(string $htm, string $url, array $params): array
      {
          $send = function (?string $nonce) use ($htm, $url, $params): array {
              $dpopProof = $this->dpop->createProof($htm, $url, null, $nonce);
              $resp = $this->http->post($url, http_build_query($params), [
                  'headers' => [
                      'Content-Type' => 'application/x-www-form-urlencoded',
                      'DPoP'         => $dpopProof,
                      'Accept'       => 'application/json',
                  ],
              ]);
              $this->captureNonceFromResponse($resp);

              return [
                  'code'    => $resp->getStatusCode(),
                  'body'    => (string)$resp->getStringBody(),
                  'headers' => $resp->getHeaders(),
              ];
          };

          $result = $send($this->lastAsNonce);
          if (in_array($result['code'], [400, 401], true)) {
              $decoded = json_decode($result['body'], true);
              if (is_array($decoded) && ($decoded['error'] ?? '') === 'use_dpop_nonce' && $this->lastAsNonce !== null) {
                  $result = $send($this->lastAsNonce);
              }
          }

          return $result;
      }

      private function captureNonceFromResponse(\Cake\Http\Client\Response $resp): void
      {
          $headerValue = $resp->getHeaderLine('DPoP-Nonce');
          if ($headerValue !== '') {
              $this->lastAsNonce = trim($headerValue);
          }
      }

      private function assertStatus(int $actual, int $expected, string $phase, string $body): void
      {
          if ($actual === $expected) {
              return;
          }
          throw new RuntimeException(sprintf(
              '%s request failed (HTTP %d).',
              $phase,
              $actual
          ));
      }
  }
  ```

  実装ノート:
  - `$lastAsNonce` はインスタンスレベルの state — 各 request 後に response header を swap する。caller (controller) が `getLastAsNonce()` で取得して session に保存、新 request 開始時に `initialAsNonce` で constructor に渡せば state が持続する。
  - altotoo の cURL pattern は `Cake\Http\Client` に置き換え — `getStringBody()` / `getStatusCode()` / `getHeaderLine()` で header/body 分離が不要になる (Pitfall 6 も自動回避)。
  - `resolveProfile` の URL query は `rawurlencode` で包む。`did:plc:...` の `:` はパーセントエンコードされる。
  - エラーメッセージから access_token / refresh_token 文字列は除外する (T-02-04-08)。

  ## B. `tests/TestCase/Service/OAuth/Bluesky/BlueskyOAuthClientTest.php`

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Test\TestCase\Service\OAuth\Bluesky;

  use App\Service\OAuth\Bluesky\BlueskyOAuthClient;
  use App\Service\OAuth\Bluesky\ClientJwtService;
  use App\Service\OAuth\Bluesky\DidResolver;
  use App\Service\OAuth\Bluesky\DpopService;
  use App\Service\OAuth\KeyManager;
  use Cake\Core\Configure;
  use Cake\Http\Client;
  use Cake\Http\Client\Adapter\Mock as MockAdapter;
  use Cake\Http\Client\Response;
  use Cake\TestSuite\TestCase;

  class BlueskyOAuthClientTest extends TestCase
  {
      private MockAdapter $adapter;
      private Client $http;
      private KeyManager $km;
      private DpopService $dpop;
      private ClientJwtService $clientJwt;
      private DidResolver $didResolver;

      protected function setUp(): void
      {
          parent::setUp();
          putenv('OAUTH_KID=test-kid-1');
          $_ENV['OAUTH_KID'] = 'test-kid-1';
          Configure::write('Bluesky', [
              'issuer'         => 'https://bsky.social',
              'par_endpoint'   => 'https://bsky.social/oauth/par',
              'token_endpoint' => 'https://bsky.social/oauth/token',
              'auth_endpoint'  => 'https://bsky.social/oauth/authorize',
              'client_id'      => 'https://tamabox.emomie.com/oauth/client-metadata.json',
              'redirect_uri'   => 'https://tamabox.emomie.com/oauth/callback',
              'client_metadata' => ['scope' => 'atproto transition:generic'],
          ]);

          $this->adapter = new MockAdapter();
          $this->http    = new Client(['adapter' => $this->adapter, 'timeout' => 15]);

          $this->km          = new KeyManager(
              TESTS . 'Fixture' . DS . 'keys' . DS . 'private.key',
              TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key'
          );
          $this->dpop        = new DpopService($this->km);
          $this->clientJwt   = new ClientJwtService($this->km);
          $this->didResolver = new DidResolver($this->http);
      }

      public function testGetProviderKey(): void
      {
          $c = new BlueskyOAuthClient($this->dpop, $this->clientJwt, $this->didResolver, $this->http);
          $this->assertSame('bluesky', $c->getProviderKey());
      }

      public function testExecuteParReturnsRequestUriOn201(): void
      {
          $this->adapter->addResponse(
              new Response(['HTTP/1.1 201 Created', 'Content-Type: application/json'],
                  (string)json_encode(['request_uri' => 'urn:ietf:params:oauth:request_uri:ABC', 'expires_in' => 60])),
              ['method' => 'POST', 'url' => 'https://bsky.social/oauth/par']
          );
          $c = new BlueskyOAuthClient($this->dpop, $this->clientJwt, $this->didResolver, $this->http);
          $out = $c->executeParRequest('challenge-xyz', 'state-abc');
          $this->assertSame('urn:ietf:params:oauth:request_uri:ABC', $out['request_uri']);
          $this->assertSame(60, $out['expires_in']);
      }

      public function testExecuteParRetriesWithNonceOnUseDpopNonce(): void
      {
          // First response: 400 + use_dpop_nonce + DPoP-Nonce header
          $this->adapter->addResponse(
              new Response(
                  ['HTTP/1.1 400 Bad Request', 'Content-Type: application/json', 'DPoP-Nonce: nonce-abc-123'],
                  (string)json_encode(['error' => 'use_dpop_nonce'])
              ),
              ['method' => 'POST', 'url' => 'https://bsky.social/oauth/par']
          );
          // Second response: 201 success (this gets consumed on retry)
          $this->adapter->addResponse(
              new Response(['HTTP/1.1 201 Created', 'Content-Type: application/json'],
                  (string)json_encode(['request_uri' => 'urn:ok', 'expires_in' => 60])),
              ['method' => 'POST', 'url' => 'https://bsky.social/oauth/par']
          );

          $c = new BlueskyOAuthClient($this->dpop, $this->clientJwt, $this->didResolver, $this->http);
          $out = $c->executeParRequest('ch', 'st');
          $this->assertSame('urn:ok', $out['request_uri']);
          $this->assertSame('nonce-abc-123', $c->getLastAsNonce());
      }

      public function testExecuteParThrowsWhenNon201AndNotNonceError(): void
      {
          $this->adapter->addResponse(
              new Response(['HTTP/1.1 400 Bad Request'], (string)json_encode(['error' => 'invalid_client'])),
              ['method' => 'POST', 'url' => 'https://bsky.social/oauth/par']
          );

          $c = new BlueskyOAuthClient($this->dpop, $this->clientJwt, $this->didResolver, $this->http);
          $this->expectException(\RuntimeException::class);
          $this->expectExceptionMessage('PAR');
          $c->executeParRequest('ch', 'st');
      }

      public function testExchangeCodeForTokenHappyPath(): void
      {
          $this->adapter->addResponse(
              new Response(['HTTP/1.1 200 OK', 'Content-Type: application/json'],
                  (string)json_encode([
                      'access_token'  => 'at_test_123',
                      'refresh_token' => 'rt_test_456',
                      'token_type'    => 'DPoP',
                      'expires_in'    => 3600,
                      'sub'           => 'did:plc:abcdefghij234567klmnopqr',
                  ])),
              ['method' => 'POST', 'url' => 'https://bsky.social/oauth/token']
          );

          $c = new BlueskyOAuthClient($this->dpop, $this->clientJwt, $this->didResolver, $this->http);
          $out = $c->exchangeCodeForToken('code-abc', 'verifier-xyz');
          $this->assertSame('at_test_123', $out['access_token']);
          $this->assertSame('rt_test_456', $out['refresh_token']);
          $this->assertSame('did:plc:abcdefghij234567klmnopqr', $out['sub']);
      }

      public function testExchangeCodeForTokenThrowsOnMissingFields(): void
      {
          $this->adapter->addResponse(
              new Response(['HTTP/1.1 200 OK'], (string)json_encode([
                  'access_token' => 'x', 'token_type' => 'DPoP', 'expires_in' => 1, 'sub' => 'did:plc:x',
                  // refresh_token missing
              ])),
              ['method' => 'POST', 'url' => 'https://bsky.social/oauth/token']
          );

          $c = new BlueskyOAuthClient($this->dpop, $this->clientJwt, $this->didResolver, $this->http);
          $this->expectException(\RuntimeException::class);
          $this->expectExceptionMessage('refresh_token');
          $c->exchangeCodeForToken('c', 'v');
      }

      public function testRefreshTokenHappyPath(): void
      {
          $this->adapter->addResponse(
              new Response(['HTTP/1.1 200 OK'], (string)json_encode([
                  'access_token'  => 'at_new',
                  'refresh_token' => 'rt_new',
                  'expires_in'    => 3600,
              ])),
              ['method' => 'POST', 'url' => 'https://bsky.social/oauth/token']
          );

          $c = new BlueskyOAuthClient($this->dpop, $this->clientJwt, $this->didResolver, $this->http);
          $out = $c->refreshToken('old_rt');
          $this->assertSame('at_new', $out['access_token']);
      }

      public function testResolveProfileHappyPath(): void
      {
          $did = 'did:plc:abcdefghij234567klmnopqr';
          // DID resolution
          $this->adapter->addResponse(
              new Response(['HTTP/1.1 200 OK'], (string)json_encode([
                  'service' => [['type' => 'AtprotoPersonalDataServer', 'serviceEndpoint' => 'https://bsky.social']],
              ])),
              ['method' => 'GET', 'url' => 'https://plc.directory/' . $did]
          );
          // getProfile
          $this->adapter->addResponse(
              new Response(['HTTP/1.1 200 OK'], (string)json_encode([
                  'handle' => 'alice.bsky.social', 'avatar' => 'https://cdn/av.png', 'displayName' => 'Alice',
              ])),
              ['method' => 'GET', 'url' => 'https://bsky.social/xrpc/app.bsky.actor.getProfile?actor=' . rawurlencode($did)]
          );

          $c = new BlueskyOAuthClient($this->dpop, $this->clientJwt, $this->didResolver, $this->http);
          $out = $c->resolveProfile($did, 'at_xyz');
          $this->assertSame('alice.bsky.social', $out['handle']);
          $this->assertSame('https://cdn/av.png', $out['avatar']);
          $this->assertSame('Alice', $out['displayName']);
          $this->assertSame('https://bsky.app/profile/alice.bsky.social', $out['profile_url']);
      }

      public function testResolveProfileMissingHandleThrows(): void
      {
          $did = 'did:plc:abcdefghij234567klmnopqr';
          $this->adapter->addResponse(
              new Response(['HTTP/1.1 200 OK'], (string)json_encode([
                  'service' => [['type' => 'AtprotoPersonalDataServer', 'serviceEndpoint' => 'https://bsky.social']],
              ])),
              ['method' => 'GET', 'url' => 'https://plc.directory/' . $did]
          );
          $this->adapter->addResponse(
              new Response(['HTTP/1.1 200 OK'], (string)json_encode(['avatar' => 'x'])),
              ['method' => 'GET', 'url' => 'https://bsky.social/xrpc/app.bsky.actor.getProfile?actor=' . rawurlencode($did)]
          );
          $c = new BlueskyOAuthClient($this->dpop, $this->clientJwt, $this->didResolver, $this->http);
          $this->expectException(\RuntimeException::class);
          $this->expectExceptionMessage('handle');
          $c->resolveProfile($did, 'at');
      }

      public function testResolveProfile401Throws(): void
      {
          $did = 'did:plc:abcdefghij234567klmnopqr';
          $this->adapter->addResponse(
              new Response(['HTTP/1.1 200 OK'], (string)json_encode([
                  'service' => [['type' => 'AtprotoPersonalDataServer', 'serviceEndpoint' => 'https://bsky.social']],
              ])),
              ['method' => 'GET', 'url' => 'https://plc.directory/' . $did]
          );
          $this->adapter->addResponse(
              new Response(['HTTP/1.1 401 Unauthorized'], '{"error":"invalid_token"}'),
              ['method' => 'GET', 'url' => 'https://bsky.social/xrpc/app.bsky.actor.getProfile?actor=' . rawurlencode($did)]
          );

          $c = new BlueskyOAuthClient($this->dpop, $this->clientJwt, $this->didResolver, $this->http);
          $this->expectException(\RuntimeException::class);
          $this->expectExceptionMessage('Profile fetch failed');
          $c->resolveProfile($did, 'at_bad');
      }

      public function testErrorMessagesContainNoSecrets(): void
      {
          // T-02-04-08: assert that a token-exchange failure does not leak access_token value in message
          $this->adapter->addResponse(
              new Response(['HTTP/1.1 500 Internal Server Error'], '{"error":"server_error","debug_access_token":"should-not-leak-this"}'),
              ['method' => 'POST', 'url' => 'https://bsky.social/oauth/token']
          );

          $c = new BlueskyOAuthClient($this->dpop, $this->clientJwt, $this->didResolver, $this->http);
          try {
              $c->exchangeCodeForToken('c', 'v');
              $this->fail('Expected exception.');
          } catch (\RuntimeException $e) {
              $this->assertStringNotContainsString('should-not-leak-this', $e->getMessage());
              $this->assertStringContainsString('TOKEN_EXCHANGE', $e->getMessage());
          }
      }
  }
  ```

  ## C. Run + lint

  ```bash
  cd /home/claude/projects/tamabox && vendor/bin/phpunit --filter BlueskyOAuthClientTest --no-coverage --testdox
  composer phpcs
  composer phpstan
  ```
  </action>

  <verify>
    <automated>cd /home/claude/projects/tamabox && php -l src/Service/OAuth/Bluesky/BlueskyOAuthClient.php 2>&1 | grep -q 'No syntax errors' && php -l tests/TestCase/Service/OAuth/Bluesky/BlueskyOAuthClientTest.php 2>&1 | grep -q 'No syntax errors' && grep -q 'implements OAuthProviderInterface' src/Service/OAuth/Bluesky/BlueskyOAuthClient.php && grep -Ec 'public function (executeParRequest|exchangeCodeForToken|refreshToken|resolveProfile|getProviderKey)' src/Service/OAuth/Bluesky/BlueskyOAuthClient.php | grep -q '^5$' && grep -q 'use_dpop_nonce' src/Service/OAuth/Bluesky/BlueskyOAuthClient.php && grep -q 'DPoP-Nonce' src/Service/OAuth/Bluesky/BlueskyOAuthClient.php && grep -q "return 'bluesky'" src/Service/OAuth/Bluesky/BlueskyOAuthClient.php && vendor/bin/phpunit --filter BlueskyOAuthClientTest --no-coverage 2>&1 | tail -5 | grep -qE 'OK \([0-9]+ tests' && composer phpstan 2>&1 | grep -q '\[OK\] No errors' && composer phpcs 2>&1 | tail -3 | grep -v FAIL | grep -qE '100%|100\.0%' && echo VERIFY_OK</automated>
  </verify>

  <acceptance_criteria>
    - `test -f src/Service/OAuth/Bluesky/BlueskyOAuthClient.php && test -f tests/TestCase/Service/OAuth/Bluesky/BlueskyOAuthClientTest.php` exits 0
    - `php -l` clean on both
    - `grep -c 'implements OAuthProviderInterface' src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` = 1
    - All 5 interface methods: `grep -Ec 'public function (executeParRequest|exchangeCodeForToken|refreshToken|resolveProfile|getProviderKey)' src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` = 5
    - Provider key: `grep -qE "return 'bluesky'" src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` exits 0
    - Nonce retry: `grep -c 'use_dpop_nonce' src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` ≥ 1
    - DPoP-Nonce header: `grep -c 'DPoP-Nonce' src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` ≥ 1
    - NO curl_* direct calls (Cake\Http\Client only): `grep -c 'curl_' src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` = 0
    - `vendor/bin/phpunit --filter BlueskyOAuthClientTest --no-coverage` exits 0, tests ≥ 10
    - `composer phpstan` exits 0
    - `composer phpcs` exits 0 for new files
    - `composer test` exits 0 (Phase 1 + Plan 02-02 + Plan 02-03 + Plan 02-04 Task 1 all green, total ≥ 50 tests)
  </acceptance_criteria>

  <done>
    BlueskyOAuthClient が OAuthProviderInterface の 5 methods を実装、DPoP-Nonce retry + nonce state ignore semantics + エラーメッセージに secret 混入なし、すべて Cake\Http\Client Mock で単体テスト済み。phpcs / phpstan / phpunit 全 green。Plan 02-04 Task 2/3 の Controller が `new BlueskyOAuthClient($dpop, $clientJwt, $didResolver)` で組み立てられる状態。
  </done>
</task>

<task type="auto" tdd="false">
  <name>Task 2: UserIdentitiesTable::upsertBlueskyIdentity + UsersTable::findByDid + Entity 型アノテーション調整</name>
  <files>src/Model/Table/UsersTable.php, src/Model/Table/UserIdentitiesTable.php, src/Model/Entity/User.php, src/Model/Entity/UserIdentity.php</files>

  <read_first>
    - /home/claude/projects/tamabox/src/Model/Table/UsersTable.php (Phase 1 bake 済み — 既存 initialize / validation / associations を把握)
    - /home/claude/projects/tamabox/src/Model/Table/UserIdentitiesTable.php (Phase 1 bake 済み)
    - /home/claude/projects/tamabox/src/Model/Entity/User.php (Phase 1 bake 済み)
    - /home/claude/projects/tamabox/src/Model/Entity/UserIdentity.php (Phase 1 bake 済み — $_accessible 確認)
    - /home/claude/projects/tamabox/.planning/phases/01-foundation-schema/01-03-SUMMARY.md (Phase 1 bake 済みアソシエーション map — hasOne UserIdentities / 4 hasMany inverses 等)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-CONTEXT.md D-08 (UPSERT 方針) / D-09 (UNIQUE 制約)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-RESEARCH.md §Token & Session Storage / §UPSERT 戦略
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-PATTERNS.md §UsersTable findByDid + UserIdentitiesTable findByProvider パターン
    - /home/claude/projects/tamabox/src/Service/OAuth/TokenEncryptionService.php (Plan 02-02)
    - /home/claude/projects/tamabox/tests/Fixture/UsersFixture.php (Phase 1 01-03 で書き換え済みの schema-valid データ形式参考)
  </read_first>

  <action>

  ## A. `src/Model/Table/UsersTable.php` — findByDid 追加

  既存の initialize() と validationDefault() は **変更しない**。末尾に以下の public method を追加する (class 末尾、validationDefault の後):

  ```php
      /**
       * Custom finder: fetch user by SNS DID via user_identities join.
       *
       * Usage:
       *   $user = $this->Users->find('byDid', ['did' => 'did:plc:...'])->first();
       *
       * Returns a User entity with UserIdentities contained, or null if no match.
       *
       * @param \Cake\ORM\Query $query
       * @param array{did: string} $options
       * @return \Cake\ORM\Query
       */
      public function findByDid(\Cake\ORM\Query $query, array $options): \Cake\ORM\Query
      {
          if (!isset($options['did']) || !is_string($options['did']) || $options['did'] === '') {
              return $query->where('1 = 0'); // empty result on bad input
          }

          return $query
              ->contain(['UserIdentities'])
              ->matching('UserIdentities', function ($q) use ($options) {
                  return $q->where([
                      'UserIdentities.provider'            => 'bluesky',
                      'UserIdentities.provider_account_id' => $options['did'],
                  ]);
              });
      }
  ```

  ## B. `src/Model/Table/UserIdentitiesTable.php` — upsertBlueskyIdentity 追加

  既存の initialize() / validationDefault() は変更しない。末尾に以下の method を追加する:

  ```php
      /**
       * UPSERT a Bluesky identity: create a user + identity on first login, update the
       * identity on subsequent logins.
       *
       * Caller passes PLAINTEXT access/refresh tokens; this method encrypts them via
       * TokenEncryptionService before the DB save (AUTH-07).
       *
       * Transaction semantics:
       *   - New user path: users INSERT + user_identities INSERT in one transaction.
       *     DB constraint violation (UNIQUE provider+did race) → RuntimeException, neither row saved.
       *   - Existing user path: user_identities UPDATE only; users row untouched.
       *   - Profile-fetch failure handled by CALLER (see OauthController::callback).
       *
       * @param array{did: string, handle: string, avatar: ?string, profile_url: string} $profile
       * @param array{access_token: string, refresh_token: string, expires_in: int} $tokens Plaintext tokens — encrypted here.
       * @return \App\Model\Entity\User The (new or existing) User entity.
       * @throws \RuntimeException on DB constraint violation or save failure.
       */
      public function upsertBlueskyIdentity(array $profile, array $tokens): \App\Model\Entity\User
      {
          $did = (string)$profile['did'];
          $handle = (string)$profile['handle'];
          if ($did === '' || $handle === '') {
              throw new \RuntimeException('upsertBlueskyIdentity: did and handle are required.');
          }

          $tokenSvc = new \App\Service\OAuth\TokenEncryptionService();
          $accessEnc  = $tokenSvc->encrypt((string)$tokens['access_token']);
          $refreshEnc = $tokenSvc->encrypt((string)$tokens['refresh_token']);
          $tokenExpiresAt = new \Cake\I18n\FrozenTime('+' . (int)$tokens['expires_in'] . ' seconds');
          $now = \Cake\I18n\FrozenTime::now();

          $connection = $this->getConnection();

          /** @var \App\Model\Table\UsersTable $usersTable */
          $usersTable = $this->getAssociation('Users')->getTarget();

          // Look up existing identity (D-09 — UNIQUE (provider, provider_account_id))
          $existing = $this->find()
              ->where([
                  $this->aliasField('provider')            => 'bluesky',
                  $this->aliasField('provider_account_id') => $did,
              ])
              ->first();

          try {
              return $connection->transactional(
                  function () use ($existing, $usersTable, $did, $handle, $profile, $accessEnc, $refreshEnc, $tokenExpiresAt, $now): \App\Model\Entity\User {
                      if ($existing !== null) {
                          // Existing user — UPDATE identity only
                          $existing = $this->patchEntity($existing, [
                              'handle_cached'       => $handle,
                              'avatar_url_cached'   => $profile['avatar'] ?? null,
                              'profile_url_cached'  => (string)$profile['profile_url'],
                              'access_token_enc'    => $accessEnc,
                              'refresh_token_enc'   => $refreshEnc,
                              'token_expires_at'    => $tokenExpiresAt,
                              'last_synced_at'      => $now,
                          ], ['accessibleFields' => [
                              'handle_cached' => true, 'avatar_url_cached' => true, 'profile_url_cached' => true,
                              'access_token_enc' => true, 'refresh_token_enc' => true,
                              'token_expires_at' => true, 'last_synced_at' => true,
                          ]]);
                          $this->saveOrFail($existing);

                          $user = $usersTable->find()->where(['id' => $existing->user_id])->firstOrFail();
                          return $user;
                      }

                      // New user — INSERT users + user_identities
                      $newUser = $usersTable->newEntity([
                          'id'           => \Cake\Utility\Text::uuid(),
                          'display_name' => mb_substr($handle, 0, 64),
                      ], ['accessibleFields' => ['id' => true, 'display_name' => true]]);
                      $usersTable->saveOrFail($newUser);

                      $newIdentity = $this->newEntity([
                          'id'                  => \Cake\Utility\Text::uuid(),
                          'user_id'             => $newUser->id,
                          'provider'            => 'bluesky',
                          'provider_account_id' => $did,
                          'handle_cached'       => $handle,
                          'avatar_url_cached'   => $profile['avatar'] ?? null,
                          'profile_url_cached'  => (string)$profile['profile_url'],
                          'access_token_enc'    => $accessEnc,
                          'refresh_token_enc'   => $refreshEnc,
                          'token_expires_at'    => $tokenExpiresAt,
                          'last_synced_at'      => $now,
                          'is_primary'          => true,
                      ], ['accessibleFields' => [
                          'id' => true, 'user_id' => true, 'provider' => true, 'provider_account_id' => true,
                          'handle_cached' => true, 'avatar_url_cached' => true, 'profile_url_cached' => true,
                          'access_token_enc' => true, 'refresh_token_enc' => true,
                          'token_expires_at' => true, 'last_synced_at' => true, 'is_primary' => true,
                      ]]);
                      $this->saveOrFail($newIdentity);

                      return $newUser;
                  }
              );
          } catch (\Cake\Database\Exception\DatabaseException $e) {
              // T-02-04-10: UNIQUE violation (provider+did race) or other integrity error.
              throw new \RuntimeException('Identity upsert failed: database constraint violation.', 0, $e);
          } catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
              throw new \RuntimeException('Identity upsert failed: validation or save error.', 0, $e);
          }
      }
  ```

  実装ノート:
  - `accessibleFields` array を `patchEntity()` / `newEntity()` の option で渡すことで bake defaults の mass-assignment-open 状態 (T-01-17) を **このメソッド呼び出しに限定して** 許可する。Entity の `$_accessible = ['*' => true]` は変更しない (Phase 3 の $_accessible hardening で一括対応)。
  - `\Cake\Utility\Text::uuid()` で UUID を生成 — Phase 1 01-03 handoff note "Phase 2 user-creation flow MUST call Text::uuid() before save"に対応。Phase 1 では DB DEFAULT なし、Entity _accessible は bake default で id が accessible になっている。
  - `getAssociation('Users')->getTarget()` は UsersTable を association 経由で取得 (Plan 01-03 で `belongsTo('Users')` が設置済み)。`fetchTable('Users')` ではなく DI 指向の方法。

  ## C. `src/Model/Entity/User.php` + `src/Model/Entity/UserIdentity.php` — 型アノテーション調整

  Phase 1 bake が emitted した `@property` アノテーションを再確認し、必要なら以下を追加する:

  UserEntity (既存 @property 行の **後**, class body 前の PHPDoc 内):
  ```
   * @property \App\Model\Entity\UserIdentity|null $user_identity
  ```

  UserIdentity の `$_accessible` は **変更しない** (Phase 3 の T-01-17 hardening scope — upsertBlueskyIdentity が `accessibleFields` option で個別許可しているので現時点で変更不要)。

  ただし `UserIdentity::$token_expires_at` / `last_synced_at` は Phase 1 bake で `@property \Cake\I18n\FrozenTime|null` になっていれば OK、なっていなければ補足する。grep で確認:
  ```bash
  grep -E '@property.*(token_expires_at|last_synced_at)' src/Model/Entity/UserIdentity.php
  ```
  - ある場合: 不変
  - ない場合: docblock に追記 (phpstan level 8 対策)

  ## D. Lint + test

  `composer test` の Phase 1 baseline 17 tests + Plan 02-02 21 + Plan 02-03 20 + Plan 02-04 Task 1 10 = 68 は維持。

  ```bash
  composer phpcs src/Model/Table/UsersTable.php src/Model/Table/UserIdentitiesTable.php src/Model/Entity/
  composer phpstan
  composer test  # Phase 1 fixture ベースの LocatorSmokeTest / PagesControllerTest が引き続き green
  ```

  phpstan level 8 で失敗する場合:
  - `FrozenTime` の型アノテーション追加 (UserIdentity entity で nullable date 型がよく問題になる)
  - `new \Cake\I18n\FrozenTime('+' . (int)$tokens['expires_in'] . ' seconds')` の `(int)` 明示で level 8 pass
  </action>

  <verify>
    <automated>cd /home/claude/projects/tamabox && php -l src/Model/Table/UsersTable.php 2>&1 | grep -q 'No syntax errors' && php -l src/Model/Table/UserIdentitiesTable.php 2>&1 | grep -q 'No syntax errors' && php -l src/Model/Entity/User.php 2>&1 | grep -q 'No syntax errors' && php -l src/Model/Entity/UserIdentity.php 2>&1 | grep -q 'No syntax errors' && grep -q 'public function findByDid' src/Model/Table/UsersTable.php && grep -q 'public function upsertBlueskyIdentity' src/Model/Table/UserIdentitiesTable.php && grep -q 'TokenEncryptionService' src/Model/Table/UserIdentitiesTable.php && grep -q 'Text::uuid()' src/Model/Table/UserIdentitiesTable.php && grep -q 'transactional' src/Model/Table/UserIdentitiesTable.php && grep -q "'provider'.*=>.*'bluesky'" src/Model/Table/UserIdentitiesTable.php && composer phpstan 2>&1 | grep -q '\[OK\] No errors' && composer test 2>&1 | tail -5 | grep -qE 'OK \(|Tests: [0-9]+' && echo VERIFY_OK</automated>
  </verify>

  <acceptance_criteria>
    - `php -l` clean on all 4 files (Table ×2, Entity ×2)
    - `grep -c 'public function findByDid' src/Model/Table/UsersTable.php` = 1
    - `grep -c 'public function upsertBlueskyIdentity' src/Model/Table/UserIdentitiesTable.php` = 1
    - Encryption dependency: `grep -c 'TokenEncryptionService' src/Model/Table/UserIdentitiesTable.php` ≥ 1
    - UUID generation: `grep -c 'Text::uuid()' src/Model/Table/UserIdentitiesTable.php` ≥ 2 (users.id + user_identities.id)
    - Transaction wrapper: `grep -c 'transactional' src/Model/Table/UserIdentitiesTable.php` ≥ 1
    - Provider literal: `grep -c "'provider' *=> *'bluesky'" src/Model/Table/UserIdentitiesTable.php` ≥ 2 (find query + new entity data)
    - Exception catch: `grep -c 'DatabaseException\\|PersistenceFailedException' src/Model/Table/UserIdentitiesTable.php` ≥ 2
    - `composer phpstan` exits 0 (including the new method — level 8)
    - `composer phpcs` exits 0
    - `composer test` exits 0 (Phase 1 baseline not regressed — especially LocatorSmokeTest + Phase 1 Table tests still green)
  </acceptance_criteria>

  <done>
    UsersTable::findByDid + UserIdentitiesTable::upsertBlueskyIdentity 実装済。新規ユーザ作成時は users + user_identities を 1 transaction で INSERT、既存時は user_identities のみ UPDATE。tokens は TokenEncryptionService 経由で *_enc 列に暗号化格納 (AUTH-07 / AUTH-04)。phpstan level 8 + phpcs + 既存 test suite green。
  </done>
</task>

<task type="auto" tdd="false">
  <name>Task 3: AuthController + UsersController + OauthController::callback + 5 テンプレート + tamabox.css + integration tests</name>
  <files>src/Controller/AuthController.php, src/Controller/UsersController.php, src/Controller/OauthController.php, templates/layout/default.php, templates/Pages/home.php, templates/Auth/callback.php, templates/Users/dashboard.php, templates/element/avatar_handle_chip.php, webroot/css/tamabox.css, tests/TestCase/Controller/AuthControllerTest.php, tests/TestCase/Controller/OauthControllerCallbackTest.php</files>

  <read_first>
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-UI-SPEC.md 全文 (特に §1 routes / §2 tokens / §3 components / §4 copy / §5 flows / §6 a11y / §7 CSS)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-CONTEXT.md D-04 (Controller 配置) / D-18 (logout)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-RESEARCH.md §Security Considerations (state / nonce / session fixation / redirect_uri)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-PATTERNS.md §AuthController + §OauthController callback + §Template 群 + §config/bluesky.php + §View テンプレート具体例
    - /home/claude/projects/tamabox/.planning/references/altotoo/LoginController.php (oauthLogin / callback の序列)
    - /home/claude/projects/tamabox/src/Controller/AppController.php (Flash / RequestHandler Component 既読込確認)
    - /home/claude/projects/tamabox/src/Controller/OauthController.php (Plan 02-03 stub 実装)
    - /home/claude/projects/tamabox/src/Service/OAuth/Bluesky/BlueskyOAuthClient.php (Task 1 で実装済)
    - /home/claude/projects/tamabox/src/Model/Table/UserIdentitiesTable.php (Task 2 で upsertBlueskyIdentity 追加済)
    - /home/claude/projects/tamabox/templates/Pages/home.php (既存 CakePHP welcome テンプレ — 置換対象)
    - /home/claude/projects/tamabox/templates/layout/default.php (修正対象)
    - /home/claude/projects/tamabox/templates/element/flash/error.php (Alert element 既存スタイル参考)
    - /home/claude/projects/tamabox/webroot/css/milligram.min.css (Milligram ベース、tamabox.css で override)
  </read_first>

  <action>

  ## A. `src/Controller/AuthController.php` (新規)

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Controller;

  use App\Service\OAuth\Bluesky\BlueskyOAuthClient;
  use App\Service\OAuth\Bluesky\ClientJwtService;
  use App\Service\OAuth\Bluesky\DidResolver;
  use App\Service\OAuth\Bluesky\DpopService;
  use App\Service\OAuth\KeyManager;
  use Cake\Core\Configure;
  use Cake\Http\Response;

  /**
   * Auth — OAuth flow start + logout.
   *
   * Routes (config/routes.php Plan 02-01):
   *   GET|POST /login/bluesky → startBluesky
   *   POST /oauth/logout       → logout
   */
  class AuthController extends AppController
  {
      public function initialize(): void
      {
          parent::initialize();
          // startBluesky is pre-login (no identity required) but IS CSRF-protected (POST form).
          // logout requires identity AND CSRF.
          $this->Authentication->allowUnauthenticated(['startBluesky']);
      }

      public function startBluesky(): ?Response
      {
          try {
              [$verifier, $challenge, $state] = $this->newOAuthChallenge();
              $this->request->getSession()->write('Oauth.pkce_verifier', $verifier);
              $this->request->getSession()->write('Oauth.state', $state);

              $client = $this->buildOAuthClient();
              $par    = $client->executeParRequest($challenge, $state);

              // Stash the AS nonce captured during PAR so callback can pick it up for token exchange.
              if ($client->getLastAsNonce() !== null) {
                  $this->request->getSession()->write('Oauth.as_nonce', $client->getLastAsNonce());
              }

              $authUrl = Configure::read('Bluesky.auth_endpoint')
                  . '?client_id=' . rawurlencode((string)Configure::read('Bluesky.client_id'))
                  . '&request_uri=' . rawurlencode($par['request_uri']);

              return $this->redirect($authUrl);
          } catch (\RuntimeException $e) {
              $this->Flash->error(__('接続できませんでした。Bluesky のサーバーに接続できませんでした。ネットワーク接続を確認のうえ、再度お試しください。'));
              return $this->redirect('/');
          }
      }

      public function logout(): ?Response
      {
          $this->request->allowMethod(['post']);
          $this->Authentication->logout();
          $this->Flash->success(__('ログアウトしました'));
          return $this->redirect('/');
      }

      /**
       * @return array{0: string, 1: string, 2: string} [verifier, challenge, state]
       */
      private function newOAuthChallenge(): array
      {
          $verifier  = $this->base64url(random_bytes(64));
          $challenge = $this->base64url(hash('sha256', $verifier, true));
          $state     = $this->base64url(random_bytes(32));
          return [$verifier, $challenge, $state];
      }

      private function base64url(string $raw): string
      {
          return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
      }

      private function buildOAuthClient(): BlueskyOAuthClient
      {
          $km        = new KeyManager();
          return new BlueskyOAuthClient(
              new DpopService($km),
              new ClientJwtService($km),
              new DidResolver(),
              null,
              (string)$this->request->getSession()->read('Oauth.as_nonce') ?: null
          );
      }
  }
  ```

  ## B. `src/Controller/UsersController.php` (新規)

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Controller;

  /**
   * Users — authenticated landing pages.
   *
   * Phase 2 scope: /dashboard only (placeholder until Phase 3 wires inbox management).
   */
  class UsersController extends AppController
  {
      public function dashboard(): void
      {
          $identity = $this->Authentication->getIdentity();
          if ($identity === null) {
              // AuthenticationMiddleware usually catches this, but defend in depth.
              $this->redirect('/');
              return;
          }

          $userId = (string)$identity->getIdentifier();
          $user = $this->fetchTable('Users')
              ->find()
              ->where(['Users.id' => $userId])
              ->contain(['UserIdentities'])
              ->firstOrFail();

          $this->set('user', $user);
      }
  }
  ```

  注意: CakePHP 4.5 の DI container は action-argument injection を持たないため `fetchTable('Users')` でアクセス (Plan 01-03 の convention 踏襲)。Plan 02-03 OauthController と同パターン。

  ## C. `src/Controller/OauthController.php` の callback() 実装置き換え

  Plan 02-03 の 501 stub を **完全な実装** に置き換える。既存の `clientMetadata()` / `jwks()` は変更しない。

  ```php
      public function callback(): ?\Cake\Http\Response
      {
          // Plan 02-03 shipped a 501 stub here; Plan 02-04 replaces with the full flow.

          // (a) OAuth cancel — Bluesky sends ?error=access_denied&state=...
          if ($this->request->getQuery('error')) {
              $this->clearOauthSession();
              $this->Flash->error(__(
                  'ログインをキャンセルしました。Bluesky の認証画面でキャンセルされました。再度ログインするには下のボタンを押してください。'
              ));
              return $this->redirect('/');
          }

          // (b) state verify — constant-time compare with session-stored value (single-use).
          $queryState   = (string)$this->request->getQuery('state');
          $sessionState = (string)$this->request->getSession()->read('Oauth.state');
          if ($queryState === '' || $sessionState === '' || !hash_equals($sessionState, $queryState)) {
              $this->clearOauthSession();
              $this->Flash->error(__(
                  'ログインに失敗しました。セッションの整合性を確認できませんでした。再度ログインしてください。（エラーコード: STATE_MISMATCH）'
              ));
              return $this->redirect('/');
          }

          // iss validation (optional per AT Protocol; when present, must match).
          $iss = (string)$this->request->getQuery('iss');
          $expectedIssuer = (string)\Cake\Core\Configure::read('Bluesky.issuer');
          if ($iss !== '' && $iss !== $expectedIssuer) {
              $this->clearOauthSession();
              $this->Flash->error(__('ログインに失敗しました。（エラーコード: ISS_MISMATCH）'));
              return $this->redirect('/');
          }

          $code     = (string)$this->request->getQuery('code');
          $verifier = (string)$this->request->getSession()->read('Oauth.pkce_verifier');
          if ($code === '' || $verifier === '') {
              $this->clearOauthSession();
              $this->Flash->error(__(
                  'ログインに失敗しました。（エラーコード: STATE_MISMATCH）'
              ));
              return $this->redirect('/');
          }

          try {
              $client = $this->buildOAuthClient();
              $tokenResp = $client->exchangeCodeForToken($code, $verifier);

              $did = $tokenResp['sub'];
              if (!preg_match('/^did:plc:[a-z2-7]{24}$/', $did)) {
                  throw new \RuntimeException('Malformed sub (DID).');
              }

              // Capture any new AS nonce from token exchange for subsequent getProfile call.
              if ($client->getLastAsNonce() !== null) {
                  $this->request->getSession()->write('Oauth.as_nonce', $client->getLastAsNonce());
              }

              // Fetch profile — this is DID→PDS→getProfile; RuntimeException on any step.
              $profile = $client->resolveProfile($did, $tokenResp['access_token']);

              // UPSERT — new user inserts users+user_identities, existing updates identity.
              /** @var \App\Model\Table\UserIdentitiesTable $identitiesTable */
              $identitiesTable = $this->fetchTable('UserIdentities');
              $user = $identitiesTable->upsertBlueskyIdentity(
                  [
                      'did'         => $did,
                      'handle'      => $profile['handle'],
                      'avatar'      => $profile['avatar'],
                      'profile_url' => $profile['profile_url'],
                  ],
                  [
                      'access_token'  => $tokenResp['access_token'],
                      'refresh_token' => $tokenResp['refresh_token'],
                      'expires_in'    => $tokenResp['expires_in'],
                  ]
              );

              // Clear transient OAuth-flow session keys (defense-in-depth, T-02-04-12).
              $this->clearOauthSession();

              // setIdentity regenerates the session ID (T-02-04-05).
              $this->Authentication->setIdentity($user);

              return $this->redirect('/dashboard');
          } catch (\RuntimeException $e) {
              $this->clearOauthSession();
              $msg = $e->getMessage();
              if (str_contains($msg, 'PAR') || str_contains($msg, 'TOKEN_EXCHANGE')) {
                  $this->Flash->error(__(
                      'ログインに失敗しました。Bluesky からアクセス権限を取得できませんでした。しばらくしてから再度お試しください。（エラーコード: TOKEN_EXCHANGE_FAILED）'
                  ));
              } elseif (str_contains($msg, 'Profile')) {
                  $this->Flash->error(__(
                      'ログインに失敗しました。Bluesky との通信中にセキュリティエラーが発生しました。しばらくしてから再度お試しください。（エラーコード: DPOP_REJECTED）'
                  ));
              } else {
                  $this->Flash->error(__('ログインに失敗しました。しばらくしてから再度お試しください。'));
              }
              return $this->redirect('/');
          }
      }

      private function clearOauthSession(): void
      {
          $session = $this->request->getSession();
          foreach (['Oauth.pkce_verifier', 'Oauth.state', 'Oauth.as_nonce'] as $k) {
              $session->delete($k);
          }
      }

      private function buildOAuthClient(): \App\Service\OAuth\Bluesky\BlueskyOAuthClient
      {
          $km = new \App\Service\OAuth\KeyManager();
          return new \App\Service\OAuth\Bluesky\BlueskyOAuthClient(
              new \App\Service\OAuth\Bluesky\DpopService($km),
              new \App\Service\OAuth\Bluesky\ClientJwtService($km),
              new \App\Service\OAuth\Bluesky\DidResolver(),
              null,
              (string)$this->request->getSession()->read('Oauth.as_nonce') ?: null
          );
      }
  ```

  Plan 02-03 の OauthController.php の callback() method body を上記で置換し、末尾に `clearOauthSession()` + `buildOAuthClient()` private methods を追加する。class-level use 文に必要な `App\Service\OAuth\*` クラスを追加する。

  Plan 02-03 で書いた `testCallbackStubReturns501` テストは **削除または書き換え** する (stub が消えたため)。以下を Task 3 の後半で OauthControllerCallbackTest に置き換える。

  ## D. テンプレート + CSS

  ### D1. `templates/layout/default.php` を UI-SPEC §3 / §7 仕様で書き換え

  ```php
  <?php
  /**
   * @var \App\View\AppView $this
   */
  ?>
  <!DOCTYPE html>
  <html lang="ja">
  <head>
      <?= $this->Html->charset() ?>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>
          <?= h($this->fetch('title')) ?> — tamabox
      </title>
      <?= $this->Html->meta('icon') ?>
      <?= $this->Html->css(['normalize.min', 'milligram.min', 'tamabox']) ?>
      <?= $this->fetch('meta') ?>
      <?= $this->fetch('css') ?>
      <?= $this->fetch('script') ?>
  </head>
  <body>
      <header class="header-bar">
          <div class="header-bar-title">
              <a href="<?= $this->Url->build('/') ?>">tamabox</a>
          </div>
          <?php $identity = $this->getRequest()->getAttribute('identity'); ?>
          <?php if ($identity): ?>
              <div class="header-bar-right">
                  <?= $this->element('avatar_handle_chip', ['identity' => $identity]) ?>
                  <form method="post" action="<?= $this->Url->build('/oauth/logout') ?>" class="logout-form">
                      <?= $this->Form->hidden('_csrfToken', ['value' => $this->getRequest()->getAttribute('csrfToken')]) ?>
                      <button type="submit" class="button-clear logout-btn">ログアウト</button>
                  </form>
              </div>
          <?php endif; ?>
      </header>
      <main class="main">
          <div class="container">
              <?= $this->Flash->render() ?>
              <?= $this->fetch('content') ?>
          </div>
      </main>
  </body>
  </html>
  ```

  ### D2. `templates/Pages/home.php` を CTA 中心に置き換え

  ```php
  <?php
  /**
   * @var \App\View\AppView $this
   */
  $this->assign('title', 'ホーム');
  ?>
  <div class="home-page">
      <h1 class="display-heading">tamabox</h1>
      <p class="text-secondary home-lead">
          Bluesky アカウントでログインして、あなたの受信箱をはじめましょう。
      </p>

      <?= $this->Form->create(null, [
          'url'   => ['controller' => 'Auth', 'action' => 'startBluesky'],
          'type'  => 'post',
          'class' => 'login-form',
      ]) ?>
          <button type="submit" class="button primary-button">Bluesky でログイン</button>
      <?= $this->Form->end() ?>
  </div>
  ```

  CakePHP FormHelper::create() は CSRF hidden token を自動生成する。これで T-02-04-01 の緩和。

  ### D3. `templates/Auth/callback.php` (Spinner interstitial — currently unused since callback redirects immediately, but created per UI-SPEC §3 Spinner definition for future use)

  ```php
  <?php
  /**
   * @var \App\View\AppView $this
   * Unused in current flow (callback redirects immediately). Kept for UI-SPEC §3 Spinner
   * reference and for future async callback paths.
   */
  $this->assign('title', 'Bluesky と通信中');
  ?>
  <div class="callback-page">
      <h2>Bluesky と通信中…</h2>
      <div role="status" aria-live="polite" class="spinner-wrapper">
          <div class="spinner" aria-hidden="true"></div>
          <span class="visually-hidden">Bluesky と通信中…</span>
      </div>
  </div>
  ```

  ### D4. `templates/Users/dashboard.php`

  ```php
  <?php
  /**
   * @var \App\View\AppView $this
   * @var \App\Model\Entity\User $user
   */
  $this->assign('title', 'ダッシュボード');
  $handle = '';
  if (isset($user->user_identity) && $user->user_identity !== null) {
      $handle = (string)$user->user_identity->handle_cached;
  }
  ?>
  <div class="dashboard-page">
      <h1>ようこそ、<?= h($handle) ?> さん</h1>
      <p class="text-secondary">受信箱はまだ作成されていません。受信箱の作成は次のステップで行います。</p>
  </div>
  ```

  ### D5. `templates/element/avatar_handle_chip.php`

  ```php
  <?php
  /**
   * @var \App\View\AppView $this
   * @var \Authentication\IdentityInterface $identity
   */
  $handle = (string)($identity->getOriginalData()->user_identity->handle_cached ?? '');
  $avatar = $identity->getOriginalData()->user_identity->avatar_url_cached ?? null;
  ?>
  <div class="avatar-handle-chip" role="group" aria-label="ログイン中のユーザー">
      <?php if ($avatar): ?>
          <img src="<?= h($avatar) ?>" alt="<?= h($handle) ?> のアイコン" class="avatar-handle-chip__avatar" width="24" height="24">
      <?php else: ?>
          <span class="avatar-handle-chip__avatar avatar-handle-chip__avatar--fallback" aria-hidden="true"><?= h(mb_substr($handle, 0, 1)) ?></span>
      <?php endif; ?>
      <span class="avatar-handle-chip__handle">@<?= h($handle) ?></span>
  </div>
  ```

  注意: `$identity->getOriginalData()` は Authentication Plugin 2.x で underlying User entity を返す。handle_cached は `user_identity` association 経由で取得。

  ### D6. `webroot/css/tamabox.css`

  UI-SPEC §7 の CSS カスタムプロパティ + 各コンポーネント styles を実装。minimum lines 120 を目安に:

  ```css
  /* tamabox.css — Phase 2 Bluesky OAuth UI. Loaded after milligram.min.css. */
  :root {
      --color-bg: #F8F9FA;
      --color-surface: #FFFFFF;
      --color-text-primary: #1A1A1A;
      --color-text-secondary: #6C757D;
      --color-accent: #0085FF;
      --color-accent-hover: #006EDB;
      --color-success: #16A34A;
      --color-warning: #D97706;
      --color-error: #DC2626;
      --color-border: #E5E7EB;
      --space-1: 4px; --space-2: 8px; --space-3: 12px; --space-4: 16px;
      --space-6: 24px; --space-8: 32px; --space-12: 48px;
      --radius-sm: 4px; --radius-md: 8px;
      --shadow-subtle: 0 1px 3px rgba(0,0,0,0.08);
      --font-family: system-ui, -apple-system, "Segoe UI", "Hiragino Sans", "Yu Gothic UI", Meiryo, sans-serif;
  }
  body {
      font-family: var(--font-family);
      color: var(--color-text-primary);
      background-color: var(--color-bg);
  }
  /* Milligram accent override */
  .button, button, input[type='submit'] {
      background-color: var(--color-accent);
      border-color: var(--color-accent);
      color: var(--color-surface);
      min-height: 44px;
      border-radius: var(--radius-md);
  }
  .button:hover, button:hover, input[type='submit']:hover {
      background-color: var(--color-accent-hover);
      border-color: var(--color-accent-hover);
  }
  .button.button-clear { background: transparent; color: var(--color-text-secondary); border: none; }
  :focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }
  :focus:not(:focus-visible) { outline: none; }

  /* HeaderBar */
  .header-bar {
      display: flex; justify-content: space-between; align-items: center;
      background: var(--color-surface);
      border-bottom: 1px solid var(--color-border);
      padding: var(--space-3) var(--space-6);
  }
  .header-bar-title a { font-size: 24px; font-weight: 600; color: var(--color-text-primary); text-decoration: none; }
  .header-bar-right { display: flex; align-items: center; gap: var(--space-4); }
  .logout-form { margin: 0; display: inline; }
  .logout-btn { font-size: 14px; color: var(--color-text-secondary); padding: 0 var(--space-3); }

  /* AvatarHandleChip */
  .avatar-handle-chip {
      display: inline-flex; align-items: center; gap: var(--space-2);
      padding: var(--space-1) var(--space-2);
      border-radius: var(--radius-md);
      background: var(--color-bg);
  }
  .avatar-handle-chip__avatar {
      width: 24px; height: 24px; border-radius: 50%;
      display: inline-block; object-fit: cover;
  }
  .avatar-handle-chip__avatar--fallback {
      background: var(--color-border); color: var(--color-text-secondary);
      text-align: center; line-height: 24px; font-size: 12px;
  }
  .avatar-handle-chip__handle { font-size: 14px; color: var(--color-accent); }

  /* Home page */
  .home-page { padding: var(--space-12) var(--space-6); text-align: center; }
  .display-heading { font-size: 32px; font-weight: 600; line-height: 1.2; margin-bottom: var(--space-4); }
  .home-lead { margin-bottom: var(--space-8); color: var(--color-text-secondary); }
  .primary-button { padding: var(--space-3) var(--space-6); font-size: 16px; font-weight: 600; }

  /* Dashboard */
  .dashboard-page { padding: var(--space-8) var(--space-6); }
  .dashboard-page h1 { font-size: 24px; font-weight: 600; margin-bottom: var(--space-4); }
  .text-secondary { color: var(--color-text-secondary); }

  /* Flash / Alert */
  .message {
      padding: var(--space-3) var(--space-4);
      border-radius: var(--radius-sm);
      margin-bottom: var(--space-4);
      font-size: 14px;
  }
  .message.error, .message.error-message {
      border-left: 4px solid var(--color-error); background: #FEF2F2;
  }
  .message.warning { border-left: 4px solid var(--color-warning); background: #FFFBEB; }
  .message.success { border-left: 4px solid var(--color-success); background: #F0FDF4; }
  .message.hidden { display: none; }

  /* Spinner */
  .callback-page { padding: var(--space-12); text-align: center; }
  .spinner-wrapper { display: flex; justify-content: center; align-items: center; }
  .spinner {
      width: 40px; height: 40px;
      border: 3px solid var(--color-border);
      border-top-color: var(--color-accent);
      border-radius: 50%;
      animation: spin 1s linear infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }
  .visually-hidden {
      position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
      overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
  }
  ```

  ## E. Integration tests

  ### E1. `tests/TestCase/Controller/AuthControllerTest.php`

  `startBluesky` は PAR で outbound HTTP を呼ぶので、BlueskyOAuthClient を丸ごとモックするより **PAR エンドポイントを Cake\Http\Client Mock Adapter で差し替える** のが現実的。ただし Application::services() で Client をシングルトン DI していないため、最小テストは以下に絞る:

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Test\TestCase\Controller;

  use Cake\Core\Configure;
  use Cake\TestSuite\IntegrationTestTrait;
  use Cake\TestSuite\TestCase;

  class AuthControllerTest extends TestCase
  {
      use IntegrationTestTrait;

      protected array $fixtures = [
          'app.Users', 'app.UserIdentities', 'app.Inboxes', 'app.Messages', 'app.Blocks', 'app.Reports',
      ];

      protected function setUp(): void
      {
          parent::setUp();
          putenv('OAUTH_KID=test-kid-1');
          $_ENV['OAUTH_KID'] = 'test-kid-1';
          $hexKey = str_repeat('ab', 32);
          putenv('TOKEN_ENC_KEY=' . $hexKey);
          $_ENV['TOKEN_ENC_KEY'] = $hexKey;
          Configure::write('Bluesky.private_key_path', TESTS . 'Fixture' . DS . 'keys' . DS . 'private.key');
          Configure::write('Bluesky.public_key_path',  TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key');
      }

      public function testLogoutWithoutAuthRedirectsHome(): void
      {
          // Not authenticated — AuthenticationMiddleware redirects to unauthenticatedRedirect
          $this->enableCsrfToken();
          $this->post('/oauth/logout');
          // Either 302 to / (unauthenticated redirect) or 302 because logout of empty session
          $this->assertResponseCode(302);
      }

      public function testLogoutWithGetIsNotAllowed(): void
      {
          $this->get('/oauth/logout');
          $this->assertResponseCode(405);
      }

      public function testDashboardWithoutAuthRedirectsHome(): void
      {
          $this->get('/dashboard');
          $this->assertResponseCode(302);
          $this->assertRedirectContains('/');
      }

      public function testLoginBluesykRouteExistsAndAcceptsPost(): void
      {
          // PAR will fail (no network) — we just assert that the POST route is wired and
          // flashes an error rather than 404/500ing.
          $this->enableCsrfToken();
          $this->post('/login/bluesky');
          // Expected: 302 to / with error flash (network failure in PAR)
          $this->assertResponseCode(302);
          $this->assertRedirectContains('/');
      }
  }
  ```

  ### E2. `tests/TestCase/Controller/OauthControllerCallbackTest.php`

  Callback の内部で BlueskyOAuthClient が PAR / token endpoint を呼ぶ。テストは CakePHP Integration test level では HTTP mock を直接注入できないため、以下の 2 approach:

  1. **Simple (Plan 02-04 scope)**: state mismatch + error param の path は HTTP なしで exercise できる (callback 前段で reject)。これをカバーする。
  2. **Token exchange happy/fail path**: CakePHP の DI 機構経由でテストから mock 注入する必要があるため、Plan 02-04 scope では **Unit test で BlueskyOAuthClient を個別に covered** 済み (Task 1)。Integration レベルでは外部 HTTP 成功パスはスキップ (live Bluesky AS が無いため)。

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Test\TestCase\Controller;

  use Cake\Core\Configure;
  use Cake\TestSuite\IntegrationTestTrait;
  use Cake\TestSuite\TestCase;

  class OauthControllerCallbackTest extends TestCase
  {
      use IntegrationTestTrait;

      protected array $fixtures = [
          'app.Users', 'app.UserIdentities', 'app.Inboxes', 'app.Messages', 'app.Blocks', 'app.Reports',
      ];

      protected function setUp(): void
      {
          parent::setUp();
          putenv('OAUTH_KID=test-kid-1');
          $_ENV['OAUTH_KID'] = 'test-kid-1';
          $hexKey = str_repeat('ab', 32);
          putenv('TOKEN_ENC_KEY=' . $hexKey);
          $_ENV['TOKEN_ENC_KEY'] = $hexKey;
          Configure::write('Bluesky.private_key_path', TESTS . 'Fixture' . DS . 'keys' . DS . 'private.key');
          Configure::write('Bluesky.public_key_path',  TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key');
      }

      public function testCallbackWithErrorParamFlashesCancelAndRedirects(): void
      {
          // Error path (a): user cancelled on Bluesky's consent screen.
          $this->get('/oauth/callback?error=access_denied&state=whatever');
          $this->assertResponseCode(302);
          $this->assertRedirectContains('/');
          $this->assertSession('ログインをキャンセル', 'Flash.flash.0.message');
      }

      public function testCallbackWithoutStateFlashesMismatch(): void
      {
          // Error path (b): state is missing from session AND query.
          $this->get('/oauth/callback?code=x&state=missing_state');
          $this->assertResponseCode(302);
          $this->assertRedirectContains('/');
          $this->assertSession('STATE_MISMATCH', 'Flash.flash.0.message');
      }

      public function testCallbackWithStateMismatchFlashesError(): void
      {
          // Prime the session with a different state than query.
          $this->session([
              'Oauth' => ['state' => 'real_state_abc', 'pkce_verifier' => 'verifier'],
          ]);
          $this->get('/oauth/callback?code=x&state=WRONG');
          $this->assertResponseCode(302);
          $this->assertRedirectContains('/');
          $this->assertSession('STATE_MISMATCH', 'Flash.flash.0.message');
      }

      public function testCallbackWith501StubRemovedNoLongerReturns501(): void
      {
          // T-02-04 hand-off: Plan 02-03 shipped a 501 stub that Plan 02-04 MUST replace.
          $this->get('/oauth/callback?error=foo&state=bar');
          $this->assertResponseCode(302);
          $this->assertResponseCodeNotSame(501);
      }

      /**
       * Helper — PHPUnit IntegrationTestTrait does not expose assertResponseCodeNotSame.
       */
      private function assertResponseCodeNotSame(int $unwanted): void
      {
          $this->assertNotSame($unwanted, $this->_response->getStatusCode());
      }
  }
  ```

  注意:
  - `assertSession($substring, 'Flash.flash.0.message')` は CakePHP の `assertSession` で Flash message 文字列部分一致。実 API が異なる場合は `assertFlashMessage` を使うか、`$this->_session['Flash']['flash'][0]['message']` を直接 assert する。
  - Token exchange happy path は **BlueskyOAuthClient unit test** (Task 1) が既にカバーしているため、callback happy path の integration test は冗長。Plan 02-04 scope では エラーパスの path coverage で十分。

  ## F. Lint + Full Test Run + ROADMAP criteria verify

  ```bash
  composer phpcs
  composer phpstan
  composer test
  ```

  すべて exit 0. `composer test` はこの時点で Phase 1 baseline (17) + Plan 02-02 (~21) + Plan 02-03 (~20) + Plan 02-04 Task 1 (10) + Plan 02-04 Task 3 (~7) ≈ 75 tests all green。

  ## G. ROADMAP Phase 2 success criteria smoke

  Plan 02-04 完了時点で以下を **手動確認** (verify-phase が後に自動化):

  - `bin/cake routes check` で 6 新規ルートがすべて対応する Controller action に解決
  - `grep -c 'implements OAuthProviderInterface' src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` = 1 (AUTH-06)
  - `grep -c 'access_token_enc' src/Model/Table/UserIdentitiesTable.php` ≥ 2 (AUTH-07)
  - `curl http://localhost:8765/oauth/client-metadata.json | jq .scope` → `"atproto transition:generic"` (AUTH-08, Plan 02-03 で closed)
  - `curl http://localhost:8765/oauth/jwks.json | jq '.keys[0].kid'` → `"ssr-box-key-1"` (AUTH-08)
  - DB constraint: `mysql -e "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME='user_identities' AND CONSTRAINT_NAME='uk_user_identities_provider_account'"` returns 1 row (AUTH-04 — Phase 1 closed)
  </action>

  <verify>
    <automated>cd /home/claude/projects/tamabox && php -l src/Controller/AuthController.php 2>&1 | grep -q 'No syntax errors' && php -l src/Controller/UsersController.php 2>&1 | grep -q 'No syntax errors' && php -l src/Controller/OauthController.php 2>&1 | grep -q 'No syntax errors' && php -l templates/layout/default.php 2>&1 | grep -q 'No syntax errors' && php -l templates/Pages/home.php 2>&1 | grep -q 'No syntax errors' && php -l templates/Auth/callback.php 2>&1 | grep -q 'No syntax errors' && php -l templates/Users/dashboard.php 2>&1 | grep -q 'No syntax errors' && php -l templates/element/avatar_handle_chip.php 2>&1 | grep -q 'No syntax errors' && grep -q 'class AuthController' src/Controller/AuthController.php && grep -q 'class UsersController' src/Controller/UsersController.php && grep -q 'hash_equals' src/Controller/OauthController.php && grep -q 'setIdentity' src/Controller/OauthController.php && grep -q 'upsertBlueskyIdentity' src/Controller/OauthController.php && grep -q 'lang="ja"' templates/layout/default.php && grep -q 'Bluesky でログイン' templates/Pages/home.php && grep -q 'ようこそ' templates/Users/dashboard.php && grep -q 'のアイコン' templates/element/avatar_handle_chip.php && grep -q '\-\-color-accent: \#0085FF' webroot/css/tamabox.css && grep -q '\.spinner' webroot/css/tamabox.css && grep -q '\.avatar-handle-chip' webroot/css/tamabox.css && ! grep -q 'withStatus(501)' src/Controller/OauthController.php && vendor/bin/phpunit --filter 'AuthControllerTest|OauthControllerCallbackTest' --no-coverage 2>&1 | tail -5 | grep -qE 'OK \([0-9]+ tests' && composer phpstan 2>&1 | grep -q '\[OK\] No errors' && composer test 2>&1 | tail -5 | grep -qE 'OK \(|Tests: [0-9]+' && echo VERIFY_OK</automated>
  </verify>

  <acceptance_criteria>
    - `test -f src/Controller/AuthController.php && test -f src/Controller/UsersController.php` exits 0
    - OauthController::callback() は 501 stub ではなく完全実装: `grep -c 'withStatus(501)' src/Controller/OauthController.php` = 0 かつ `grep -c 'upsertBlueskyIdentity' src/Controller/OauthController.php` = 1 かつ `grep -c 'setIdentity' src/Controller/OauthController.php` = 1
    - State constant-time compare: `grep -c 'hash_equals' src/Controller/OauthController.php` ≥ 1
    - OAuth-session cleanup (T-02-04-12): `grep -c 'clearOauthSession\\|session->delete' src/Controller/OauthController.php` ≥ 2
    - DID validation: `grep -c "did:plc:\\[a-z2-7\\]{24}" src/Controller/OauthController.php` ≥ 1
    - AuthController logout POST-only: `grep -c "allowMethod.*'post'" src/Controller/AuthController.php` ≥ 1
    - PKCE: `grep -c "hash('sha256'" src/Controller/AuthController.php` ≥ 1 AND `grep -c 'random_bytes(64)' src/Controller/AuthController.php` = 1 (verifier) AND `grep -c 'random_bytes(32)' src/Controller/AuthController.php` = 1 (state)
    - Templates all valid PHP: `php -l` clean on 5 template files
    - UI-SPEC copy literals:
      - `grep -c 'lang="ja"' templates/layout/default.php` = 1
      - `grep -c 'Bluesky でログイン' templates/Pages/home.php` ≥ 1
      - `grep -c 'ようこそ' templates/Users/dashboard.php` = 1
      - `grep -c '受信箱はまだ作成されていません' templates/Users/dashboard.php` = 1
      - `grep -c 'Bluesky と通信中' templates/Auth/callback.php` ≥ 1
      - `grep -c 'のアイコン' templates/element/avatar_handle_chip.php` ≥ 1
      - `grep -c 'ログアウト' templates/layout/default.php` = 1
    - CSS tokens:
      - `grep -c '\\-\\-color-accent: #0085FF' webroot/css/tamabox.css` = 1
      - `grep -c '\\.primary-button\\|button \\{' webroot/css/tamabox.css` ≥ 1
      - `grep -c '\\.spinner' webroot/css/tamabox.css` ≥ 1
      - `grep -c '\\.avatar-handle-chip' webroot/css/tamabox.css` ≥ 1
      - `grep -c '\\.visually-hidden' webroot/css/tamabox.css` = 1
      - `grep -c '@keyframes spin' webroot/css/tamabox.css` = 1
      - `wc -l webroot/css/tamabox.css` ≥ 120
    - CSRF on logout form: `grep -c '_csrfToken' templates/layout/default.php` = 1
    - Integration tests: `vendor/bin/phpunit --filter 'AuthControllerTest|OauthControllerCallbackTest' --no-coverage` exits 0, ≥ 7 tests
    - Plan 02-03 が追加した `testCallbackStubReturns501` は book にない (Plan 02-04 で削除または書換) OR skipped
    - `composer phpstan` exits 0 (level 8)
    - `composer phpcs` exits 0
    - `composer test` exits 0 (Phase 1 17 + Plan 02-02 ~21 + Plan 02-03 ~20 + Plan 02-04 ~17 ≈ 75 total tests all green)
    - `bin/cake routes check` exits 0 (no route→Controller resolution errors)
  </acceptance_criteria>

  <done>
    AuthController + UsersController 新設、OauthController::callback 完全実装、全 5 テンプレート + tamabox.css が UI-SPEC 準拠、integration tests が callback error path 群をカバー。Phase 2 success criteria #1..#7 すべて observable。`/gsd-verify-phase 2` に進める状態。Plan 02-03 の 501 stub は撤去済。
  </done>
</task>

</tasks>

<verification>
## Plan-level Verification (Phase 2 全体の gate)

Run after all 3 Plan 02-04 tasks + preceding plans complete:

1. **Full interface coverage** (AUTH-06):
   ```
   grep -q 'implements OAuthProviderInterface' src/Service/OAuth/Bluesky/BlueskyOAuthClient.php
   php -r 'require "vendor/autoload.php"; exit((new App\Service\OAuth\Bluesky\BlueskyOAuthClient(...))->getProviderKey() === "bluesky" ? 0 : 1);'
   ```
   (The `...` placeholder shows the DI requirement — tests validate the actual signature.)

2. **ROADMAP Phase 2 success criteria all closable** (manual + test evidence):
   - #1 (signup new user → /dashboard): covered by BlueskyOAuthClient + upsertBlueskyIdentity unit / integration tests
   - #2 (existing user re-login → identity sync): upsertBlueskyIdentity existing-user path unit-tested indirectly via DB state
   - #3 (logout destroys session): AuthControllerTest::testLogout*
   - #4 (uk_user_identities_provider_account UNIQUE guard): Phase 1 DB introspection (01-02b-SUMMARY) + Plan 02-04 DatabaseException catch
   - #5 (/oauth/jwks.json + /oauth/client-metadata.json exposed): Plan 02-03 integration tests green
   - #6 (tokens AES-GCM encrypted in *_enc): unit test on TokenEncryptionService + upsertBlueskyIdentity calls encrypt before save
   - #7 (OAuthProviderInterface abstraction exists): interface file + implements clause

3. **Full test suite**:
   ```
   composer test 2>&1 | tail -3 | grep -qE 'OK \(|Tests: [0-9]+'
   # Expected ~75 tests, 0 failures.
   ```

4. **Lint / static**:
   ```
   composer phpcs && composer phpstan
   ```
   Both exit 0. Level 8 for all new src/ files.

5. **All Phase 2 routes resolve**:
   ```
   bin/cake routes check  # exit 0 — no unmapped routes
   ```

6. **Middleware invariant (T-02-04-05 session fixation)**:
   ```
   # After setIdentity, Authentication Plugin internally calls session_regenerate_id(true)
   # — verified via Plan 02-04 Task 3 integration test or Authentication 2.11.x source
   grep -q 'session_regenerate_id' vendor/cakephp/authentication/src/
   ```

7. **No leakage invariants**:
   - `grep -rE 'access_token|refresh_token' src/Service/OAuth/ | grep -v '_enc\\|test\\|Test'` — tokens should only appear in upsert/encrypt paths
   - `grep -r 'log\\|Log::write' src/Controller/ | grep -iE 'token|secret|key'` = empty (no Log writes of secrets)

8. **Frontend minimal smoke** (manual, not CI gate):
   ```
   bin/cake server  # boots at :8765
   # curl http://localhost:8765/  → contains 'Bluesky でログイン'
   # curl http://localhost:8765/oauth/client-metadata.json → valid JSON
   # curl http://localhost:8765/oauth/jwks.json  → valid JSON with 1 EC key
   # curl http://localhost:8765/dashboard → 302 to /
   ```
</verification>

<success_criteria>
Plan 02-04 complete when:
- [ ] BlueskyOAuthClient implements OAuthProviderInterface with all 5 methods and ≥ 10 unit tests green
- [ ] UsersTable::findByDid + UserIdentitiesTable::upsertBlueskyIdentity working; tokens encrypted via TokenEncryptionService before DB save
- [ ] AuthController::startBluesky generates PKCE + state, runs PAR, redirects to AS
- [ ] AuthController::logout POST-only + CSRF, destroys session, flashes success
- [ ] OauthController::callback (501 stub removed): state verify (hash_equals), iss optional match, token exchange, DID format guard, resolveProfile, UPSERT, setIdentity, redirect /dashboard
- [ ] All 5 templates + tamabox.css match UI-SPEC §1–§7 literal copy + color tokens + a11y markup
- [ ] Integration tests cover: /oauth/logout GET (405), /dashboard unauthenticated (302→/), /oauth/callback error param + state mismatch (302→/ with flash)
- [ ] composer phpcs / phpstan (level 8) / test all exit 0
- [ ] ROADMAP Phase 2 success criteria #1–#7 observable
- [ ] Plan 02-03's 501 stub for callback() has been replaced
- [ ] All AUTH-01/02/04/05/09 requirements closed (AUTH-06 at interface level via Task 1, AUTH-07/08 closed in earlier plans)
</success_criteria>

<output>
After completion, create `.planning/phases/02-bluesky-oauth-identity/02-04-SUMMARY.md` with:
- frontmatter listing requirements_closed (AUTH-01, AUTH-02, AUTH-04, AUTH-05, AUTH-09 + consolidating AUTH-06, AUTH-07, AUTH-08 closure across phase)
- commit log for 3 tasks
- per-task acceptance summary + any deviations (especially if DI container / Cake\Http\Client Mock adapter API diverges from anticipated)
- integration test matrix: which error path was covered by integration vs unit tests
- ROADMAP Phase 2 success criteria cross-check (all 7 → closed)
- Hand-off note to Phase 3 (access_token refresh lifecycle, AUTH-03 send-time check point, user_identities data shape for consumption)
- Self-check
</output>
