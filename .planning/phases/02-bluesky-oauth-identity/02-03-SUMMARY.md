---
phase: 02-bluesky-oauth-identity
plan: 03
subsystem: oauth-metadata-did
wave: 2
depends_on:
  - 02-01
tags:
  - oauth
  - bluesky
  - metadata
  - jwks
  - did
  - plc-directory
  - integration-test
  - tdd
requirements_closed:
  - AUTH-08  # /oauth/jwks.json + /oauth/client-metadata.json endpoints live (ROADMAP Phase 2 success criterion #5)
requirements_partial: []
dependency_graph:
  requires:
    - 02-01 foundation (config/bluesky.php Bluesky.client_metadata dict, config/routes.php /oauth/{client-metadata.json,jwks.json,callback} routes, src/Controller/AppController.php base)
    - 02-02 crypto (KeyManager::getPublicJwk consumed by OauthController::jwks)
  provides:
    - App\Controller\OauthController — serves /oauth/client-metadata.json + /oauth/jwks.json; pre-allocates callback() as 501 stub for Plan 02-04 fill-in
    - App\Service\OAuth\Bluesky\DidResolver — DID (did:plc:*) -> PDS URL resolver via plc.directory (DI target for Plan 02-04 BlueskyOAuthClient::resolveProfile)
  affects: []
tech_stack:
  added:
    - (none — zero new composer packages; uses existing Cake\Http\Client + KeyManager)
  patterns:
    - Controller-returns-Response for JSON endpoints via $this->response->withType('application/json')->withStringBody(json_encode(..., JSON_UNESCAPED_SLASHES))
    - Controller action stub for forward plan reservation (501 response locks method slot; Plan 02-04 replaces body only, no class-level edits)
    - Global HTTP mock via Cake\Http\Client::addMockResponse() static adapter for DidResolver unit tests (no per-instance adapter injection needed)
    - DID format regex validation BEFORE outbound HTTP (T-02-03-05 pattern; re-usable for future did:web support)
key_files:
  created:
    - src/Controller/OauthController.php
    - src/Service/OAuth/Bluesky/DidResolver.php
    - tests/TestCase/Controller/OauthControllerTest.php
    - tests/TestCase/Service/OAuth/Bluesky/DidResolverTest.php
  modified: []
commits:
  - d9f3972 feat(02-03): add OauthController metadata + jwks endpoints with callback stub
  - 22044c6 test(02-03): add failing DidResolver unit tests (RED)
  - d728411 feat(02-03): implement DidResolver for plc.directory DID -> PDS lookup (GREEN)
decisions_locked_in:
  - D-16  # client_id === delivery URL (byte-for-byte), served verbatim from Configure
  - D-17  # /oauth/jwks.json returns exactly one EC P-256 key
  - D-03  # DidResolver placement at App\Service\OAuth\Bluesky\
  - D-07  # did:plc: scope only (handle->DID resolution delegated to AS)
decisions:
  - Callback stub MUST return 501 until Plan 02-04; integration test testCallbackStubReturns501 locks this invariant so Plan 02-04 first task fails loudly if the stub is still there, not silently.
  - Cake\Http\Client::addMockResponse() (static global adapter) is the right fit for DidResolver tests — the plan suggested per-instance Mock adapter but the actual CakePHP 4.5 API (Mock::addResponse) takes a RequestInterface, not an options array. Using the static helper is idiomatic and keeps DidResolver's production constructor simple (no adapter-inject ceremony).
  - DidResolver stays final + utility-only (does NOT implement OAuthProviderInterface) — the interface is Bluesky-wide provider contract (PAR/token/profile), DidResolver is a single-purpose helper. Same rationale as KeyManager staying off the interface (Plan 02-02).
metrics:
  tasks_completed: 2
  tests_added: 20  # 7 OauthController + 13 DidResolver (7 explicit + 7 dataProvider rows = 13 in --testdox count, but the 7-row invalidDidProvider is a single test method expanded by phpunit)
  assertions_added: 53  # 30 OauthController + 23 DidResolver
  files_created: 4
  files_modified: 0
  composer_deps_added: 0
  duration: ~4m  # 3m 22s measured (start 12:25:49Z -> end 12:29:51Z)
  completed: 2026-04-23
---

# Phase 02 Plan 03: Metadata Endpoints + DID Resolver — Summary

Bluesky OAuth の公開 metadata endpoints 2 本 (`/oauth/client-metadata.json` + `/oauth/jwks.json`) を `OauthController` の動的生成方式で提供し、DID → PDS 解決ユーティリティ `DidResolver` を `App\Service\OAuth\Bluesky\` 配下に実装完了。`/oauth/callback` は Plan 02-04 が body を埋めるまでの明示的 501 stub として in place。外部 HTTP は `DidResolver` 経由の plc.directory GET 1 種のみ — PAR/token endpoint hit は Plan 02-04 範疇。

Plan 02-01 で pre-registered されていた 3 ルート (`/oauth/client-metadata.json` / `/oauth/jwks.json` / `/oauth/callback`) が **これで dispatch 可能**になり、Bluesky AS が `client_id` URL を直接 validate できる状態。JWKS は KeyManager (Plan 02-02) の `getPublicJwk()` を経由して `kid=env(OAUTH_KID)` の単一 EC P-256 鍵を `{"keys":[...]}` 形式で返す。

Task 1 は直接実装 + 7 integration test、Task 2 は TDD 完全遂行(RED → GREEN、REFACTOR は不要レベルの clean impl のため skip)。RED 時点で 13 tests が "Class App\Service\OAuth\Bluesky\DidResolver not found" で失敗することを確認、GREEN 実装後に 13/13 green + 23 assertions。

Zero new composer deps / zero DB touch / zero new trust boundary 外 surface。phpcs 48/48 / phpstan level 8 [OK] No errors / composer test 62 tests 145 assertions 0 failures(6 pre-existing Phase 1 bake-stub incompletes unchanged)。

## Acceptance Criteria per Task

### Task 1: OauthController 作成 + integration test

- [x] `src/Controller/OauthController.php` 作成、`php -l` clean
- [x] `class OauthController extends AppController`
- [x] 3 actions present: `clientMetadata()` / `jwks()` / `callback()` — `grep -Ec = 3`
- [x] `JSON_UNESCAPED_SLASHES` usage: grep count = 3(clientMetadata + jwks + docblock reference of intent)
- [x] `Configure::read('Bluesky.client_metadata')` literal = 2(class docblock + code site — cosmetic over-match, same variance class as 02-02 Deviation #2)
- [x] `new KeyManager()` = 1(DI via constructor rather than ctor arg since CakePHP 4.x doesn't support action-arg DI)
- [x] `withStatus(501)` = 1(callback stub)
- [x] `tests/TestCase/Controller/OauthControllerTest.php` 作成 with `IntegrationTestTrait`
- [x] 7 test methods present: `testClientMetadataReturns200Json` / `testClientMetadataHasExpectedClientId` / `testClientMetadataBodyDoesNotContainEscapedSlashes` / `testJwksReturns200Json` / `testJwksContainsExactlyOneEcKey` / `testJwksDoesNotLeakPrivateScalar` / `testCallbackStubReturns501`
- [x] `setUp()` sets `putenv('OAUTH_KID=test-kid-1')` + Configure-override to test fixture keys
- [x] `testJwksDoesNotLeakPrivateScalar` (T-02-03-04 mitigation) green
- [x] `testClientMetadataBodyDoesNotContainEscapedSlashes` (Pitfall 5 byte-match) green
- [x] `vendor/bin/phpunit --filter OauthControllerTest` → 7 tests OK, 30 assertions
- [x] phpcs 48/48 pass
- [x] phpstan level 8 [OK] No errors

### Task 2: DidResolver + Cake\Http\Client mock unit test (TDD)

- [x] RED: 13-test file created first, all fail with "Class ... not found" → commit `22044c6 test(02-03): add failing DidResolver unit tests (RED)`
- [x] GREEN: `src/Service/OAuth/Bluesky/DidResolver.php` 作成、13/13 tests pass → commit `d728411 feat(02-03): implement DidResolver ... (GREEN)`
- [x] `final class DidResolver` with nullable `Cake\Http\Client` constructor arg
- [x] DID regex `/^did:plc:[a-z2-7]{24}$/` validated BEFORE outbound HTTP (T-02-03-05)
- [x] `resolveDidToPds()` returns PDS URL from `AtprotoPersonalDataServer` entry, trailing slash stripped
- [x] `Cake\Http\Client` with `timeout => 10` + `redirect => 3` (T-02-03-06)
- [x] Generic `RuntimeException` messages — no echo of response body (T-02-03-07)
- [x] 7 dataProvider rows cover: empty / wrong-scheme / too-short / too-long / uppercase / invalid base32 char / SQL-injection-shape string
- [x] `testResolveStripsTrailingSlash` covers the `rtrim('/')` contract
- [x] `testPlcDirectoryNon200Throws` covers 404 path
- [x] `testMissingServiceArrayThrows` covers malformed DID document
- [x] `testNoAtprotoServiceEntryThrows` covers DID document without an Atproto PDS entry
- [x] `testDefaultConstructorBuildsClient` covers no-arg ctor smoke
- [x] `vendor/bin/phpunit --filter DidResolverTest` → 13 tests OK, 23 assertions
- [x] phpcs 48/48 pass (after double-space fix in dataProvider comments)
- [x] phpstan level 8 [OK] No errors

## Plan-level Verification Results

| # | Check | Command | Result |
|---|-------|---------|--------|
| 1 | Endpoints live | `vendor/bin/phpunit --filter OauthControllerTest --no-coverage` | `OK (7 tests, 30 assertions)` |
| 2 | DidResolver unit | `vendor/bin/phpunit --filter DidResolverTest --no-coverage` | `OK (13 tests, 23 assertions)` |
| 3 | No composer deps | `git diff composer.json composer.lock \| wc -l` | `0` — zero new packages |
| 4 | Full test suite | `composer test` | `Tests: 62, Assertions: 145, Incomplete: 6 (Phase 1 unchanged), Failures: 0, Errors: 0` |
| 5 | phpcs | `composer phpcs` | 48/48 pass, exit 0, 0 errors 0 warnings |
| 6 | phpstan level 8 | `composer phpstan` | `[OK] No errors`, exit 0 |
| 7 | Callback stub invariant | `grep -q 'withStatus(501)' src/Controller/OauthController.php` | match — Plan 02-04 hand-off contract preserved |
| 8 | Syntax clean (all 4 files) | `php -l` | `No syntax errors detected` on all 4 new PHP files |

## Integration-test JSON snapshot

### `GET /oauth/client-metadata.json`

- **Status:** 200
- **Content-Type:** `application/json`
- **Body** (from test fixture — identical at runtime via `Configure::read('Bluesky.client_metadata')`):

```json
{
    "client_id": "https://tamabox.emomie.com/oauth/client-metadata.json",
    "application_type": "web",
    "client_name": "tamabox",
    "client_uri": "https://tamabox.emomie.com",
    "redirect_uris": ["https://tamabox.emomie.com/oauth/callback"],
    "grant_types": ["authorization_code", "refresh_token"],
    "response_types": ["code"],
    "scope": "atproto transition:generic",
    "token_endpoint_auth_method": "private_key_jwt",
    "token_endpoint_auth_signing_alg": "ES256",
    "dpop_bound_access_tokens": true,
    "jwks_uri": "https://tamabox.emomie.com/oauth/jwks.json"
}
```

Served via `JSON_UNESCAPED_SLASHES` — no `\/` escapes in wire bytes (verified by `testClientMetadataBodyDoesNotContainEscapedSlashes`). This is the Pitfall 5 byte-for-byte invariant: AT Protocol requires `client_id` URL string to match exactly between the metadata document body and subsequent request parameters.

### `GET /oauth/jwks.json`

- **Status:** 200
- **Content-Type:** `application/json`
- **Body shape** (test fixture keys; production differs on `x`/`y` coordinates):

```json
{
    "keys": [
        {
            "kty": "EC",
            "crv": "P-256",
            "kid": "test-kid-1",
            "use": "sig",
            "alg": "ES256",
            "x": "<43-char base64url>",
            "y": "<43-char base64url>"
        }
    ]
}
```

- Exactly 1 key (`testJwksContainsExactlyOneEcKey`)
- No `d` (private scalar) claim (`testJwksDoesNotLeakPrivateScalar`, T-02-03-04 mitigation)
- `kid` matches `env('OAUTH_KID', 'ssr-box-key-1')` — in tests this is forced to `test-kid-1` via `putenv()` in `setUp()`; in production this MUST agree with `ClientJwtService::createAssertion()`'s `kid` (Plan 02-04 flow)

### `GET /oauth/callback?code=x&state=y`

- **Status:** 501
- **Content-Type:** `application/json`
- **Body:** `{"error":"callback_not_yet_implemented_plan_02_04"}`

Plan 02-04 first task MUST replace the body of `OauthController::callback()` with the real token-exchange flow. `testCallbackStubReturns501` is a hand-off contract test: it will fail in Plan 02-04 if the stub is still in place, forcing the verifier to confirm the implementation actually happened.

## Deviations from Plan

### 1. [Rule 1 - Bug] Cake\Http\Client Mock adapter signature differs from plan snippet

- **Found during:** Task 2 RED phase, while writing `DidResolverTest.php`.
- **Issue:** The plan's sample test code (lines 556-593) used
  `MockAdapter::addResponse(Response $response, array ['method' => ..., 'url' => ...])`
  but the actual CakePHP 4.5 API in
  `vendor/cakephp/cakephp/src/Http/Client/Adapter/Mock.php` is
  `Mock::addResponse(RequestInterface $request, Response $response, array $options)` —
  the first argument is a full PSR-7 Request, not a Response, and the method/url go *inside* the Request. Mis-using the plan signature would either throw "RequestInterface expected" at runtime or silently never match any request.
- **Fix:** Used the higher-level static helper `Cake\Http\Client::addMockResponse($method, $url, $response, $options)` (see `vendor/cakephp/cakephp/src/Http/Client.php:534`). This is a class-level public API that constructs the `Request` internally and intercepts every `Client` instance via the global `$_mockAdapter` — simpler than per-instance adapter injection, matches Bluesky OAuth reality where we don't need multiple isolated clients in the same test.
- **Files modified:** `tests/TestCase/Service/OAuth/Bluesky/DidResolverTest.php` (used `Client::addMockResponse()` instead of per-instance `MockAdapter::addResponse()`; `setUp()`/`tearDown()` call `Client::clearMockResponses()` for isolation)
- **Commit:** `22044c6` (RED commit — correct signature from the first attempt, never had a broken on-disk version)
- **Forward impact:** Plan 02-04 BlueskyOAuthClient tests should use the same pattern — `Client::addMockResponse()` for PAR / token / profile endpoint stubs. No per-instance adapter ceremony needed.

### 2. [Cosmetic - under-strict acceptance grep] OauthController docblock reference + KeyManager DI ceremony

- **Found during:** Task 1 acceptance criteria grep verification.
- **Issue:** Plan line 404 says `grep -c "Configure::read\\('Bluesky.client_metadata'\\)" src/Controller/OauthController.php` = 1 but my count = 2. The extra hit is the class-level docblock which mentions `Configure` behavior for reader orientation. The code binding site is still 1 (line 38), satisfying intent.
- **Decision:** Leave docblock intact. Same variance class as Plan 02-02 Deviation #2 — strict-equality `= 1` vs. intent "single code-site usage." Removing documentation to satisfy a literal count harms code-review readability for a cosmetic metric.
- **No fix applied.**

### 3. [Plan-snippet over-spec] JSON_UNESCAPED_SLASHES grep = 3 not 1

- **Found during:** Task 1 acceptance criteria grep verification.
- **Issue:** Plan line 403 says `grep -c 'JSON_UNESCAPED_SLASHES' src/Controller/OauthController.php` ≥ 1; my count = 3. Both `clientMetadata()` and `jwks()` use the flag on their `json_encode` calls (= 2 code uses), plus one docblock mention. This MEETS the ≥ 1 threshold (it is not an `= 1` constraint), so no deviation strictly — logging for transparency.
- **No fix applied.**

### 4. [Whitespace - fixed inline in GREEN commit] phpcs double-space alignment errors

- **Found during:** Task 2 GREEN phase, first `composer phpcs` run after writing DidResolverTest.php.
- **Issue:** The `invalidDidProvider()` dataProvider had comment alignment with multiple spaces before `// comment` to line up visually (4 lines: `'too-short'`, `'too-long'`, `'uppercase'`, `'with-1'`). CakePHP's phpcs ruleset flags any double-space as "Double space found" in array contexts.
- **Fix:** Collapsed the alignment padding so each line has a single space between value and `//`. No functional change.
- **Files modified:** `tests/TestCase/Service/OAuth/Bluesky/DidResolverTest.php` (4 lines, trailing-space-before-comment collapsed to single-space)
- **Commit:** `d728411` (bundled into GREEN since it's a cosmetic fix to the test file that was already staged).

## Authentication Gates Encountered

**None.** All work was filesystem edits + local phpunit/phpcs/phpstan runs. Zero external API calls. The DidResolver test uses `Client::addMockResponse()` to intercept every would-be outbound HTTP — the process never talks to `plc.directory` in CI.

## Handoff Notes

### For Plan 02-04 (OAuth Flow — BlueskyOAuthClient)

**Key contract:** `OauthController::callback()` is a 501 stub. Plan 02-04's first task MUST replace the **body** of this method (lines 82-88) with the real flow:

```php
public function callback(): Response
{
    // Plan 02-04 adds:
    //  1. verify state (CSRF-equivalent) against session-stored value
    //  2. $bluesky = $this->getBlueskyOAuthClient();  // or DI from services()
    //  3. $tokenResponse = $bluesky->exchangeCodeForToken($code, $codeVerifier, ...);
    //  4. $profile = $bluesky->resolveProfile($tokenResponse->did);  // uses DidResolver internally
    //  5. UPSERT user_identities (AES-GCM encrypted tokens via TokenEncryptionService from 02-02)
    //  6. $this->Authentication->setIdentity($user);
    //  7. return $this->redirect('/dashboard');
    // On error: redirect to '/' with Flash error message (UI-SPEC §4).
}
```

Verification hook: `testCallbackStubReturns501` (`tests/TestCase/Controller/OauthControllerTest.php` line ~98) MUST be updated or deleted in Plan 02-04. If Plan 02-04 accidentally leaves the 501 body, this test passes — a red flag the plan verifier should catch.

### DidResolver DI into BlueskyOAuthClient

```php
$didResolver = new DidResolver();   // Configure default: plc.directory + 10s timeout
$oauthClient = new BlueskyOAuthClient(
    dpopService: new DpopService($km),             // from Plan 02-02
    clientJwtService: new ClientJwtService($km),   // from Plan 02-02
    tokenEncryption: new TokenEncryptionService(), // from Plan 02-02
    didResolver: $didResolver,                     // NEW from this plan
    httpClient: $httpClient
);
```

`BlueskyOAuthClient::resolveProfile($did)` flow:

1. `$pdsUrl = $this->didResolver->resolveDidToPds($did)` — throws `\RuntimeException` on bad DID / plc.directory failure
2. `$dpop = $this->dpopService->createProof('GET', $pdsUrl . '/xrpc/app.bsky.actor.getProfile', $accessToken)` (accessToken → `ath` claim per D-13)
3. `$response = $this->httpClient->get("{$pdsUrl}/xrpc/app.bsky.actor.getProfile?actor={$did}", [], ['headers' => ['Authorization' => "DPoP {$accessToken}", 'DPoP' => $dpop]])`
4. Parse returned profile JSON; map to local Users / UserIdentities.

**Integration test pattern:** `Client::addMockResponse('GET', 'https://plc.directory/did:plc:...', ...)` + `Client::addMockResponse('GET', 'https://bsky.social/xrpc/app.bsky.actor.getProfile?actor=...', ...)` in sequence; both intercepted by the same global mock adapter.

### OAUTH_KID coordination reminder (carried from 02-02 handoff)

- `KeyManager::getPublicJwk()` (served by THIS plan's `OauthController::jwks()`) uses `env('OAUTH_KID', 'ssr-box-key-1')` for the `kid` claim.
- `ClientJwtService::createAssertion()` (Plan 02-04 calls it) uses the SAME env var in the JWT header `kid`.
- They MUST agree — if /oauth/jwks.json publishes `kid=A` but client_assertion sends `kid=B`, the AS can't find the key and rejects. Verified by both integration tests using `putenv('OAUTH_KID=test-kid-1')` in `setUp()`.

### Pattern: Global mock adapter for CakePHP 4.5 HTTP tests

- Use `Cake\Http\Client::addMockResponse($method, $url, $response, $options = [])` — registers a stub that intercepts every `Client` instance.
- Always call `Client::clearMockResponses()` in both `setUp()` AND `tearDown()` for test isolation — the adapter is process-global.
- The lower-level `Cake\Http\Client\Adapter\Mock` class exists but its `addResponse(RequestInterface, Response, array)` signature is more awkward; use `addMockResponse()` unless you need per-instance isolation (which we don't).

## Known Stubs

- **`OauthController::callback()` body — 501 response returning `{"error":"callback_not_yet_implemented_plan_02_04"}`.** This is INTENTIONAL per the plan's `must_haves` contract ("OauthController::callback() is a pre-allocated stub that exists but returns 501 (Plan 02-04 fills the body)"). It reserves the class method slot so Plan 02-04 is a pure logic fill-in without class-level edits. The existing `testCallbackStubReturns501` integration test locks this invariant; Plan 02-04 MUST replace both the method body and this test.

No other stubs. `DidResolver` and both `OauthController` public endpoints are fully functional.

## Threat Flags

All 8 declared threats (T-02-03-01 through T-02-03-08) handled per plan dispositions:

| ID | Disposition | Status |
|----|-------------|--------|
| T-02-03-01 Spoofing (client_id URL mismatch) | mitigate | **Done** — `clientMetadata()` returns `Configure::read('Bluesky.client_metadata')` verbatim; `testClientMetadataHasExpectedClientId` asserts exact string. |
| T-02-03-02 Tampering (wrong Content-Type) | mitigate | **Done** — every response uses `->withType('application/json')`; `testClientMetadataReturns200Json` + `testJwksReturns200Json` assert header. |
| T-02-03-03 Open redirect (callback) | mitigate | **Done** — stub returns 501 with static body, zero redirect, zero dynamic URL handling. Plan 02-04 inherits this as the baseline and must add state validation before any redirect. |
| T-02-03-04 Info Disclosure (private key in jwks) | mitigate | **Done** — KeyManager::getPublicJwk() reads only the public PEM; `testJwksDoesNotLeakPrivateScalar` asserts response has no `d` claim. |
| T-02-03-05 Tampering (malicious DID) | mitigate | **Done** — DidResolver validates `/^did:plc:[a-z2-7]{24}$/` BEFORE any HTTP call; 7 dataProvider rows cover empty, wrong-scheme, length boundaries, case, invalid base32 chars, injection shape. |
| T-02-03-06 DoS (plc.directory hang) | mitigate | **Done** — `Cake\Http\Client` constructed with `timeout => 10`; network errors mapped to `\RuntimeException('DID resolution failed (network error).')`. |
| T-02-03-07 Info Disclosure (internal paths leak via plc response) | mitigate | **Done** — generic exception message `"DID resolution failed."` — original response body never embedded. |
| T-02-03-08 Cache poisoning (scope rotation) | accept | **Accepted** per CONTEXT D-06 tradeoff — out-of-scope for MVP (scope rotation requires re-auth of all users). |

**No new threat surface outside the declared `<threat_model>`.** Trust boundaries touched: only plc.directory (new, but declared) and Bluesky AS (unchanged — AS fetches our public static endpoints, no new state). No new DB access, no new auth paths, no schema changes.

## Self-Check

**Commits:**
- FOUND: d9f3972 (Task 1 — OauthController + integration tests)
- FOUND: 22044c6 (Task 2 RED — failing DidResolver tests)
- FOUND: d728411 (Task 2 GREEN — DidResolver impl + phpcs whitespace fix)

**Files created (4):**
- FOUND: src/Controller/OauthController.php
- FOUND: src/Service/OAuth/Bluesky/DidResolver.php
- FOUND: tests/TestCase/Controller/OauthControllerTest.php
- FOUND: tests/TestCase/Service/OAuth/Bluesky/DidResolverTest.php

**Verification gates passed:**
- FOUND: php -l clean on all 4 new PHP files
- FOUND: `vendor/bin/phpunit --filter OauthControllerTest --no-coverage` → OK (7 tests, 30 assertions)
- FOUND: `vendor/bin/phpunit --filter DidResolverTest --no-coverage` → OK (13 tests, 23 assertions)
- FOUND: `composer test` → Tests: 62, Assertions: 145, Incomplete: 6, Failures: 0, Errors: 0
- FOUND: `composer phpcs` → 48/48 pass, exit 0, 0 errors 0 warnings
- FOUND: `composer phpstan` → `[OK] No errors`, exit 0
- FOUND: `git diff composer.json composer.lock | wc -l` → 0 (zero new composer deps)
- FOUND: `grep 'withStatus(501)' src/Controller/OauthController.php` → match (callback stub invariant preserved for Plan 02-04 hand-off)
- FOUND: `grep 'did:plc:[a-z2-7]{24}' src/Service/OAuth/Bluesky/DidResolver.php` → match (T-02-03-05 validator present)
- FOUND: `grep 'AtprotoPersonalDataServer' src/Service/OAuth/Bluesky/DidResolver.php` → match (PDS service type filter present)

## TDD Gate Compliance

Plan-level TDD was not in the frontmatter (`type: execute`, not `type: tdd`), but Task 2 was individually `tdd="true"`. Gate sequence verified:

- RED commit: `22044c6 test(02-03): add failing DidResolver unit tests (RED)` — test-only, all 13 fail with missing-class errors before any impl
- GREEN commit: `d728411 feat(02-03): implement DidResolver for plc.directory DID -> PDS lookup (GREEN)` — all 13 pass
- REFACTOR: skipped (code was clean; no post-green cleanup warranted)

Both gate commits are present in `git log --oneline`.

## Self-Check: PASSED
