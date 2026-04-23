---
phase: 02-bluesky-oauth-identity
plan: 02
subsystem: oauth-crypto
wave: 2
depends_on:
  - 02-01
tags:
  - oauth
  - crypto
  - es256
  - dpop
  - aes-gcm
  - jwk
  - jwt
  - unit-tests
requirements_closed:
  - AUTH-07  # AES-256-GCM token encryption (TokenEncryptionService full impl + tests)
requirements_partial:
  - AUTH-08  # ES256 key management — KeyManager loads keys and exports JWK, but /oauth/jwks.json endpoint is Plan 02-03
dependency_graph:
  requires:
    - 02-01 foundation (config/bluesky.php, config/keys/*.key production-path Configure keys, cakephp/authentication plugin, PSR-4 autoload of App\Service\OAuth\)
  provides:
    - App\Service\OAuth\KeyManager — PEM → JWK + EC private-key loader (DI target for DpopService/ClientJwtService; consumer of Bluesky.private_key_path / Bluesky.public_key_path)
    - App\Service\OAuth\TokenEncryptionService — AES-256-GCM encrypt/decrypt for user_identities.access_token_enc / refresh_token_enc (AUTH-07)
    - App\Service\OAuth\Bluesky\DpopService — RFC 9449 DPoP proof JWT (stateless; jti random, ath/nonce optional)
    - App\Service\OAuth\Bluesky\ClientJwtService — RFC 7523 private_key_jwt client_assertion (audience injected per call)
  affects:
    - phpstan.neon  # added bootstrapFiles: config/paths.php so CONFIG/DS/ROOT/APP constants resolve under level 8 static analysis
tech_stack:
  added:
    - (none — zero new composer packages; all implementation uses PHP openssl ext + builtin hash/random_bytes/base64)
  patterns:
    - DER → R||S raw signature conversion (altotoo BlueskyOauthComponent.php L37-70 verbatim, CONTEXT D-11)
    - base64url (RFC 4648 §5, no `=` padding) encoding/decoding pattern repeated per-class (self-contained, trait-free)
    - Constructor-injected KeyManager (DI) so tests pass path-override; no service locator
    - Configure::read() with fallback for runtime path resolution; tests inject TESTS . 'Fixture/keys/…' directly
key_files:
  created:
    - src/Service/OAuth/KeyManager.php
    - src/Service/OAuth/TokenEncryptionService.php
    - src/Service/OAuth/Bluesky/DpopService.php
    - src/Service/OAuth/Bluesky/ClientJwtService.php
    - tests/TestCase/Service/OAuth/KeyManagerTest.php
    - tests/TestCase/Service/OAuth/TokenEncryptionServiceTest.php
    - tests/TestCase/Service/OAuth/Bluesky/DpopServiceTest.php
    - tests/TestCase/Service/OAuth/Bluesky/ClientJwtServiceTest.php
    - tests/Fixture/keys/private.key   # VCS-tracked dummy ES256 P-256 private key (NOT production; separate from config/keys/)
    - tests/Fixture/keys/public.key    # VCS-tracked dummy ES256 P-256 public key
    - .planning/phases/02-bluesky-oauth-identity/deferred-items.md
  modified:
    - phpstan.neon  # bootstrapFiles addition (Rule 3 blocking fix — CakePHP CONFIG/DS constants unknown to phpstan level 8 without bootstrap)
commits:
  - 210442a feat(02-02): add KeyManager + TokenEncryptionService crypto primitives
  - 2834924 feat(02-02): add DpopService + ClientJwtService ES256 JWT signers
decisions_locked_in:
  - D-11  # DER → R||S 64-byte raw conversion (altotoo L37-70 verbatim)
  - D-12  # DPoP header.jwk minimal form (kty/crv/x/y only; no kid/use/alg)
  - D-13  # ath claim = base64url(sha256(access_token)) — added when $accessToken arg set
  - D-15  # AES-256-GCM format = base64url(IV(12) || CT || TAG(16))
decisions:
  - KeyManager stays utility-only (does NOT implement OAuthProviderInterface) — the interface is Bluesky-specific (PAR/token/profile), KeyManager is shared across providers.
  - Both DpopService and ClientJwtService keep private-copy derToRawSignature + base64urlEncode (intentional duplication vs. trait) — per-class review locality outweighs DRY for 15 lines of crypto glue. Trait extraction deferred to post-MVP refactor.
  - phpstan level 8 requires CakePHP runtime constants (CONFIG/DS/ROOT/APP) to exist at parse time; added `bootstrapFiles: config/paths.php` to phpstan.neon. This is the idiomatic CakePHP solution and will benefit every future src/ file referencing those constants (e.g., 02-03 metadata endpoints, 02-04 BlueskyOAuthClient path resolution).
metrics:
  tasks_completed: 2
  tests_added: 25  # 5 KeyManager + 6 TokenEncryption + 8 DpopService + 6 ClientJwtService
  assertions_added: 63  # 20 + 8 + 22 + 13
  files_created: 11
  files_modified: 1
  composer_deps_added: 0
  duration: ~20m  # Task 1 setup-to-commit + Task 1 phpcs/phpstan fix loop + Task 2 impl + Task 2 phpcs fix loop + verify + SUMMARY
  completed: 2026-04-23
---

# Phase 02 Plan 02: Crypto & JWT Service Layer — Summary

純粋暗号サービス 4 クラス (`KeyManager` / `TokenEncryptionService` / `DpopService` / `ClientJwtService`) を PHP builtin openssl ext のみで実装完了。外部 HTTP ゼロ / DB アクセスゼロ / Controller ゼロ / composer 新規依存ゼロ — Wave 2 並列作業対象の crypto primitive 層がフル unit-test 済みで着地。

altotoo `BlueskyOauthComponent.php` L37-70 の `derToRawSignature()` (DER ECDSA → 64-byte R||S raw) を DpopService と ClientJwtService に verbatim 移植し、`openssl_verify` で生成 JWT の署名が自前生成鍵に対して実際に通ることをテストで確認済み (T-02-02-01 mitigation 完成)。AES-256-GCM の round-trip / tamper-detection / distinct-IV / wrong-key / empty-key 6 テストで AUTH-07 要件を full coverage。

25 新規テスト (5+6+8+6) 追加、Phase 1 ベースライン 17 テストと合流して総 42 テスト / 92 assertions / 6 pre-existing bake-stub incompletes (Phase 1 から不変) / 0 failures / 0 errors。phpcs 44/44 pass、phpstan level 8 `[OK] No errors`。

Plan 02-04 の `BlueskyOAuthClient` は `new BlueskyOAuthClient(new DpopService($km), new ClientJwtService($km), new TokenEncryptionService(), ...)` で直接組み立て可能 — crypto 層の正しさは本 plan で保証済みのため、Plan 02-04 の integration test は HTTP response のモックのみでよい。

## Acceptance Criteria per Task

### Task 1: KeyManager + TokenEncryptionService + 2 unit tests + test-fixture EC keys

- [x] `src/Service/OAuth/KeyManager.php` 作成、124 lines、`php -l` clean
- [x] `KeyManager::getPublicJwk()` returns `{kty:EC, crv:P-256, kid:env(OAUTH_KID), use:sig, alg:ES256, x:<b64u>, y:<b64u>}` — 5 tests / 20 assertions
- [x] `KeyManager::getPublicJwkForDpop()` omits `kid`/`use`/`alg` (DPoP minimal form, D-12)
- [x] `KeyManager::getPrivateKey()` returns `\OpenSSLAsymmetricKey` usable by `openssl_sign`
- [x] `getPrivateKey()` throws `\RuntimeException` when key path unreadable
- [x] EC P-256 coordinates are 32 bytes each when base64url-decoded (testJwkCoordinatesAre32BytesWhenBase64UrlDecoded)
- [x] `src/Service/OAuth/TokenEncryptionService.php` 作成、121 lines、`php -l` clean
- [x] AES-256-GCM round-trip preserves plaintext (testRoundTripPreservesPlaintext)
- [x] Same plaintext encrypted twice produces distinct ciphertext via `random_bytes(12)` IV (T-02-02-07 mitigation)
- [x] Tampered ciphertext byte-flip causes `openssl_decrypt` tag-mismatch → `\RuntimeException` thrown, generic "Token decryption failed" message (T-02-02-06)
- [x] Wrong-key decrypt throws (key isolation)
- [x] Empty/non-hex `TOKEN_ENC_KEY` throws on encrypt (fail-fast)
- [x] Output format exactly `base64url(IV(12) || CT || TAG(16))` = 33 bytes for 5-byte plaintext (verified in testEncryptedOutputLengthMatchesIvCtTagFormat)
- [x] `tests/Fixture/keys/private.key` + `public.key` ES256 P-256 dummy keypair committed (VCS-tracked, NOT gitignored, separate from production `config/keys/`)
- [x] `openssl ec -in tests/Fixture/keys/private.key -noout -text` shows `NIST CURVE: P-256`
- [x] `vendor/bin/phpunit --filter KeyManagerTest` → 5 tests OK
- [x] `vendor/bin/phpunit --filter TokenEncryptionServiceTest` → 6 tests OK
- [x] `composer phpcs` 0 errors 0 warnings (after docblock + double-space auto-fix loop)
- [x] `composer phpstan` `[OK] No errors` (required phpstan.neon bootstrapFiles addition — see Deviation #1)
- [x] KeyManager does NOT implement OAuthProviderInterface (utility, not provider) — verified `grep -c 'interface OAuthProviderInterface' src/Service/OAuth/KeyManager.php` = 0
- [x] structural greps: `openssl_pkey_get_details`=2, `base64urlEncode`=5, `'aes-256-gcm'`=1, `random_bytes(12)`=1, `OPENSSL_RAW_DATA`=2 — all meet or exceed acceptance thresholds

### Task 2: DpopService + ClientJwtService + 2 unit tests (JWT 構造 + openssl_verify 署名検証)

- [x] `src/Service/OAuth/Bluesky/DpopService.php` 作成、121 lines、`php -l` clean
- [x] `DpopService::createProof(htm, htu)` emits 3-part JWT; header `typ=dpop+jwt`, `alg=ES256`, `jwk.{kty,crv,x,y}` present — testCreateProofHeaderIsDpopJwt + testCreateProofHeaderContainsJwk
- [x] Payload contains `htm`, `htu`, `iat`, `exp=iat+60`, `jti` (all always); optional `ath = base64url(sha256(accessToken))` only when `$accessToken` arg set (D-13, T-02-02-03 mitigation); optional `nonce` only when `$nonce` arg set (D-10 retry)
- [x] `testEveryProofHasUniqueJti`: 10 sequential calls produce 10 distinct jtis (T-02-02-04 replay mitigation)
- [x] `testSignatureVerifiesAgainstPublicKey`: raw R||S is exactly 64 bytes; converted back to DER the signature validates via `openssl_verify` with the fixture public key (= 1) — end-to-end crypto correctness proof (T-02-02-01 mitigation)
- [x] `src/Service/OAuth/Bluesky/ClientJwtService.php` 作成、104 lines, `php -l` clean
- [x] `ClientJwtService::createAssertion(audience)` emits 3-part JWT; header `alg=ES256`, `kid=env(OAUTH_KID)`; payload `iss=sub=Configure::read('Bluesky.client_id')`, `aud=<arg>`, `jti`, `iat`, `exp=iat+60`
- [x] client_assertion header does NOT contain `typ` or `jwk` (T-02-02-09 mitigation — no DPoP-style header leakage into client_assertion context)
- [x] `ClientJwtService::testSignatureVerifiesAgainstPublicKey` passes — both services emit signatures that validate through `openssl_verify`
- [x] Empty `Bluesky.client_id` raises `\RuntimeException` with "client_id" message (fail-fast)
- [x] `vendor/bin/phpunit --filter DpopServiceTest` → 8 tests OK, 22 assertions
- [x] `vendor/bin/phpunit --filter ClientJwtServiceTest` → 6 tests OK, 13 assertions
- [x] structural greps: `'typ' => 'dpop+jwt'`=1, `derToRawSignature` in DpopService=3 (declare+call+doc), `derToRawSignature` in ClientJwtService=2, `random_bytes(32)` in DpopService=1, `hash('sha256'` in DpopService=1 — all meet thresholds
- [x] `ClientJwtService` `Configure::read('Bluesky.client_id')`=2 (code + class docblock) — see Deviation #2
- [x] `OAUTH_KID` in ClientJwtService=2 (code + class docblock) — see Deviation #2

## Plan-level Verification Results

| # | Check | Command | Result |
|---|-------|---------|--------|
| 1 | File inventory | 10 files (4 src + 4 test + 2 fixture key) | all `test -f` pass |
| 2 | No composer deps | `git diff composer.json composer.lock \| wc -l` | 0 lines — zero new packages |
| 3 | PSR-4 autoload | `class_exists()` for all 4 new App\\Service\\OAuth\\ classes | true for all 4 |
| 4 | No HTTP/DB touch | `grep -rE '(ConnectionManager\|getTableLocator\|curl_init\|http_build\|file_get_contents.*http)' src/Service/OAuth/` | empty — pure crypto |
| 5 | Full test suite | `composer test` | Tests: 42, Assertions: 92, Incomplete: 6 (Phase 1 unchanged), Failures: 0, Errors: 0 |
| 5 | New-plan tests alone | `vendor/bin/phpunit tests/TestCase/Service/OAuth/ --no-coverage` | 25 tests OK (5+6+8+6) |
| 6 | phpcs | `composer phpcs` | 44/44 pass, 0 errors, 0 warnings, exit 0 |
| 6 | phpstan level 8 | `composer phpstan` | `[OK] No errors`, exit 0 |
| 7 | Signature invariant | `vendor/bin/phpunit --filter testSignatureVerifiesAgainstPublicKey` | 2 tests OK (DpopService + ClientJwtService both validate through `openssl_verify`) — DER → Raw conversion is cryptographically correct, Plan 02-04 unblocked |

## Deviations from Plan

### 1. [Rule 3 - Blocking] phpstan.neon bootstrapFiles addition

- **Found during:** Task 1, after writing `src/Service/OAuth/KeyManager.php` with `CONFIG . 'keys' . DS . 'private.key'` fallback (exactly per plan line 287-297).
- **Issue:** `composer phpstan` failed level 8 with "Constant CONFIG not found" and "Constant DS not found" at lines 29 and 35. KeyManager is the first `src/` file in the repo to use these CakePHP runtime constants; Phase 1 01-01 phpstan.neon didn't declare a bootstrap file because no Phase 1 code referenced them.
- **Fix:** Added `bootstrapFiles: - config/paths.php` to `phpstan.neon`. `config/paths.php` is the idiomatic CakePHP location that defines `DS`, `ROOT`, `APP`, `CONFIG`, `WWW_ROOT`, and `TESTS` via `define()`. It is already loaded at runtime by both `webroot/index.php` and `tests/bootstrap.php`; adding it to phpstan's bootstrap makes these constants known at static analysis time without changing runtime behavior.
- **Files modified:** `phpstan.neon` (1 line added)
- **Commit:** 210442a (bundled with Task 1 since it was a prerequisite for Task 1 phpstan gate)
- **Forward impact:** Every future service file that references `CONFIG`/`DS`/`ROOT` (e.g., Plan 02-03 `OauthController::clientMetadata()` reading `Configure`, Plan 02-04 `BlueskyOAuthClient` path resolution) will now pass phpstan level 8 without additional configuration. This is a positive side-effect of a one-line fix.

### 2. [Cosmetic - under-strict acceptance grep] ClientJwtService docblock adds +1 to `OAUTH_KID` / `Configure::read` literal counts

- **Found during:** Task 2 acceptance criteria grep verification.
- **Issue:** Plan acceptance line 1171-1173 specifies:
  - `grep -c "Configure::read\\('Bluesky.client_id'\\)" src/Service/OAuth/Bluesky/ClientJwtService.php` ≥ 1  ← met (my count = 2)
  - `grep -c "OAUTH_KID" src/Service/OAuth/Bluesky/ClientJwtService.php` = 1  ← my count = 2
  Both over-count because the class-level docblock (lines 13-14) also mentions these symbols for documentation purposes. The actual code references are 1 each (line 32 and line 39), satisfying the plan's **intent** (single code-site usage).
- **Decision:** Leave docblock intact. The criterion is a strict-equality count, but the intent (one binding site in executable code) is met. Removing the documentation mention to satisfy `= 1` literally would actively harm code-review readability for a cosmetic metric. Documenting here for transparency.
- **No fix applied.** Plan reviewer should read this as "acceptance criterion wording slightly under-specified; intent satisfied."

### 3. [Scope boundary - NOT fixed, logged to deferred-items.md] Pre-existing `templates/Pages/home.php` deprecation

- **Found during:** Task 1 `composer test` run.
- **Issue:** PHPUnit prints a stack trace at suite startup: `If you cannot use automatic connection management, use $connection->getDriver()->connect() instead.` The offender is `templates/Pages/home.php:30` — part of the CakePHP 4.5 skeleton that came from `composer create-project` in Phase 1 01-01.
- **Decision:** Out of scope per SCOPE BOUNDARY rule in execute-plan.md. Not caused by Plan 02-02. Does not fail any test. Suite exits 0 with the deprecation visible.
- **Logged to:** `.planning/phases/02-bluesky-oauth-identity/deferred-items.md` as D-DEF-01, assigned to Phase 4 production-launch plan (where the skeleton landing page gets replaced with real tamabox home anyway).

## Authentication Gates Encountered

**None.** All work was filesystem edits + `openssl` CLI key generation (local) + `composer` autoload dump (no network) + PHPUnit/phpcs/phpstan invocation (local tooling). Zero external API calls, zero credential input.

## Handoff Notes

### For Plan 02-03 (Client Metadata Endpoints / JWKS)

- `KeyManager::getPublicJwk()` returns the fully-formed JWK with `kid` / `use` / `alg`. Plan 02-03 `OauthController::jwks()` returns:
  ```php
  $km = new KeyManager();  // Uses Configure defaults (config/keys/private.key + public.key)
  $this->set('keys', [$km->getPublicJwk()]);
  // response: {"keys": [{"kty":"EC","crv":"P-256","kid":"<env OAUTH_KID>","use":"sig","alg":"ES256","x":"…","y":"…"}]}
  ```
- `config/bluesky.php` sets `Bluesky.private_key_path` / `Bluesky.public_key_path` to `CONFIG . 'keys' . DS . 'private.key'` / `public.key`. KeyManager constructor reads these via `Configure::read()` when given empty-string paths — so production code uses `new KeyManager()` (no args), tests use `new KeyManager(TESTS . 'Fixture' . DS . 'keys' . DS . 'private.key', …)`.
- `config/keys/.gitignore` (from 02-01) excludes `*.key` / `*.pem`. Real production keys must be placed there manually (permissions 600/644 per Phase 2 Plan 02-01 handoff).
- `TOKEN_ENC_KEY` placeholder is in `config/.env.example` (from 02-01). Local dev requires `openssl rand -hex 32 | sed 's/^/export TOKEN_ENC_KEY="/;s/$/"/'` appended to `config/.env`. Tests don't need this — they `putenv()` inside `setUp()`.

### For Plan 02-04 (OAuth Flow — BlueskyOAuthClient)

**Constructor contract for BlueskyOAuthClient:**
```php
$km = new KeyManager();  // Configure-driven paths for production
$oauthClient = new BlueskyOAuthClient(
    dpopService: new DpopService($km),
    clientJwtService: new ClientJwtService($km),
    tokenEncryption: new TokenEncryptionService(),
    httpClient: $httpClient  // Cake\Http\Client or equivalent — Plan 02-04 owns this choice
);
```

- `DpopService::createProof($htm, $htu, $accessToken = null, $nonce = null)`:
  - **PAR call (no access_token yet, maybe initial nonce=null):** `createProof('POST', $par_endpoint)` — first attempt. If AS returns `use_dpop_nonce` error with a `DPoP-Nonce` response header, retry with `createProof('POST', $par_endpoint, null, $responseNonce)` (CONTEXT D-10, max 1 retry).
  - **Token exchange (same pattern):** `createProof('POST', $token_endpoint, null, $nonceIfAny)`.
  - **PDS resource call (profile fetch):** `createProof('GET', $pds_getProfile_url, $accessToken)` — `ath` claim auto-added.
- `ClientJwtService::createAssertion($audience)`:
  - Pass **`Configure::read('Bluesky.par_endpoint')`** as audience when attaching client_assertion to PAR request body.
  - Pass **`Configure::read('Bluesky.token_endpoint')`** as audience when attaching client_assertion to token exchange.
  - The caller MUST pick the right audience per step (T-02-02-09 mitigation — service can't know which endpoint you're talking to).
- `TokenEncryptionService::encrypt($plaintext)` / `decrypt($b64u)`:
  - After successful token exchange, before `$userIdentitiesTable->save($ui)`: `$ui->access_token_enc = $svc->encrypt($access_token); $ui->refresh_token_enc = $svc->encrypt($refresh_token);`
  - Before using a stored token (e.g., resource call): `$accessToken = $svc->decrypt($ui->access_token_enc);` then pass to DpopService for `ath`.
  - `decrypt()` throws `\RuntimeException('Token decryption failed.')` on any tamper, wrong-key, or malformed-input — callers should catch and translate to user-facing re-auth prompt (UI-SPEC §4).

### For Plan 02-03 + 02-04 — OAUTH_KID coordination

- Both `KeyManager::getPublicJwk()` (for jwks.json) and `ClientJwtService::createAssertion()` (for client_assertion header.kid) read `env('OAUTH_KID', 'ssr-box-key-1')`. They MUST agree — if the jwks endpoint publishes `kid=A` but client_assertion sends `kid=B`, the AS can't match the key and rejects the assertion.
- `OAUTH_KID` is declared in `config/.env.example` (Phase 2 Plan 02-01). Local dev: set once in `config/.env`, production: deploy env injection.
- Default `'ssr-box-key-1'` is used when env var unset — suitable for CI/test, not production (production MUST set explicitly to track rotation events).

### Unit-test convention established

- All 4 unit tests use `putenv('OAUTH_KID=…'); $_ENV['OAUTH_KID'] = '…';` in `setUp()` (both forms needed because Cake's `env()` helper checks both).
- `TESTS . 'Fixture' . DS . 'keys' . DS . 'private.key'` is now the canonical test key path. Future crypto tests (none anticipated in Phase 2) should reuse the same fixture.
- `Configure::write('Bluesky.client_id', '…')` inside `setUp()` is the pattern for tests that need a deterministic client_id (ClientJwtServiceTest).

## Known Stubs

**None.** Every service class emits working output end-to-end:
- `KeyManager` → reads real PEM, returns real JWK with real coordinates
- `TokenEncryptionService` → round-trips real AES-GCM with real tamper-detection
- `DpopService` → produces RFC 9449-compliant JWT that validates via `openssl_verify`
- `ClientJwtService` → produces RFC 7523-compliant JWT that validates via `openssl_verify`

No placeholder returns, no `throw new \LogicException('TODO')`, no mock data. Plan 02-04 can integrate these services immediately.

## Threat Flags

**Nothing new outside the plan's declared `<threat_model>`.** All 9 threats T-02-02-01..09 are addressed per the plan (mitigate / accept dispositions):

| ID | Disposition | Status |
|----|-------------|--------|
| T-02-02-01 Tampering (DPoP sig format) | mitigate | **Done** — `derToRawSignature` + `testSignatureVerifiesAgainstPublicKey` passes |
| T-02-02-02 Tampering (missing jwk) | mitigate | **Done** — `testCreateProofHeaderContainsJwk` asserts jwk.kty / jwk.crv / jwk.x / jwk.y |
| T-02-02-03 Tampering (missing ath) | mitigate | **Done** — `testAthClaimAddedWhenAccessTokenProvided` + `testAthNotPresentWhenAccessTokenNull` |
| T-02-02-04 Replay (jti reuse) | mitigate | **Done** — `testEveryProofHasUniqueJti` (10 distinct) + `random_bytes(32)` per invocation |
| T-02-02-05 Info Disclosure (GCM timing) | accept | **Accepted** — OpenSSL constant-time tag verify; GCM provides no partial-plaintext leakage |
| T-02-02-06 Info Disclosure (key in errors) | mitigate | **Done** — generic "Token decryption failed" / "Token encryption failed" messages, never echo key or ciphertext; verified in `testDecryptTamperedCiphertextThrows` |
| T-02-02-07 Tampering (IV reuse) | mitigate | **Done** — `random_bytes(12)` per encrypt call; `testTwoEncryptionsOfSameInputDiffer` asserts distinct ciphertexts |
| T-02-02-08 Info Disclosure (key file via webroot) | accept | **Accepted** — webroot isolation is a Phase 4 INFRA-06 concern; out of scope here |
| T-02-02-09 Spoofing (client_assertion aud confusion) | mitigate | **Done** — `createAssertion(string $audience)` forces per-call audience; `testAssertionAudMatchesArgument` verifies the arg is copied verbatim; `testAssertionHeaderHasKid` asserts no DPoP-jwk leaks into client_assertion header |

**No new threat surface introduced** — zero HTTP endpoints, zero DB access, zero session writes, no new trust boundaries beyond what Plan 02-01 already established.

## Self-Check

**Commits:**
- FOUND: 210442a (Task 1 — KeyManager + TokenEncryptionService + 2 tests + fixture keys + phpstan.neon)
- FOUND: 2834924 (Task 2 — DpopService + ClientJwtService + 2 tests)

**Files created (10):**
- FOUND: src/Service/OAuth/KeyManager.php
- FOUND: src/Service/OAuth/TokenEncryptionService.php
- FOUND: src/Service/OAuth/Bluesky/DpopService.php
- FOUND: src/Service/OAuth/Bluesky/ClientJwtService.php
- FOUND: tests/TestCase/Service/OAuth/KeyManagerTest.php
- FOUND: tests/TestCase/Service/OAuth/TokenEncryptionServiceTest.php
- FOUND: tests/TestCase/Service/OAuth/Bluesky/DpopServiceTest.php
- FOUND: tests/TestCase/Service/OAuth/Bluesky/ClientJwtServiceTest.php
- FOUND: tests/Fixture/keys/private.key (ES256 P-256, VCS-tracked)
- FOUND: tests/Fixture/keys/public.key (ES256 P-256, VCS-tracked)

**Files modified (1):**
- FOUND: phpstan.neon (bootstrapFiles: config/paths.php)

**Verification gates passed:**
- FOUND: php -l clean on all 8 PHP files
- FOUND: `vendor/bin/phpunit --filter KeyManagerTest` → OK (5 tests, 20 assertions)
- FOUND: `vendor/bin/phpunit --filter TokenEncryptionServiceTest` → OK (6 tests, 8 assertions)
- FOUND: `vendor/bin/phpunit --filter DpopServiceTest` → OK (8 tests, 22 assertions)
- FOUND: `vendor/bin/phpunit --filter ClientJwtServiceTest` → OK (6 tests, 13 assertions)
- FOUND: `vendor/bin/phpunit --filter testSignatureVerifiesAgainstPublicKey` → OK (2 tests, 4 assertions)
- FOUND: `composer test` → Tests: 42, Assertions: 92, Incomplete: 6, Failures: 0
- FOUND: `composer phpcs` → 44/44 pass, exit 0, 0 errors 0 warnings
- FOUND: `composer phpstan` → `[OK] No errors`, exit 0
- FOUND: `git diff composer.json composer.lock | wc -l` → 0 (zero new composer deps)
- FOUND: `openssl ec -in tests/Fixture/keys/private.key -noout -text | grep 'NIST CURVE: P-256'` → match
- FOUND: class_exists() returns true for App\\Service\\OAuth\\KeyManager, TokenEncryptionService, Bluesky\\DpopService, Bluesky\\ClientJwtService
- FOUND: `grep -rE '(ConnectionManager|curl_init|http_build)' src/Service/OAuth/` → empty (pure crypto, no HTTP/DB)

## Self-Check: PASSED
