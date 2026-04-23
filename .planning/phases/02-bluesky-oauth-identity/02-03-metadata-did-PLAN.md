---
phase: 02-bluesky-oauth-identity
plan: 03
type: execute
wave: 2
depends_on:
  - 02-01
files_modified:
  - src/Controller/OauthController.php
  - src/Service/OAuth/Bluesky/DidResolver.php
  - tests/TestCase/Controller/OauthControllerTest.php
  - tests/TestCase/Service/OAuth/Bluesky/DidResolverTest.php
autonomous: true
requirements:
  - AUTH-08
tags:
  - oauth
  - bluesky
  - metadata
  - jwks
  - did
  - plc-directory
  - integration-test

must_haves:
  truths:
    - "GET /oauth/client-metadata.json returns HTTP 200 application/json with client_id equal to 'https://tamabox.emomie.com/oauth/client-metadata.json' and scope 'atproto transition:generic'"
    - "GET /oauth/jwks.json returns HTTP 200 application/json with exactly 1 key in 'keys' array; key has kty=EC, crv=P-256, use=sig, alg=ES256, kid=<env OAUTH_KID>, x/y populated"
    - "OauthController is the only controller handling /oauth/* metadata paths; it's loaded from src/Controller/OauthController.php; it extends App\\Controller\\AppController"
    - "OauthController::callback() is a pre-allocated stub that exists but returns 501 (Plan 02-04 fills the body); this reserves the method slot so Plan 02-04 is a pure logic fill-in without class-level edits"
    - "DidResolver::resolveDidToPds(did) returns the PDS endpoint URL parsed from plc.directory/<did> JSON response"
    - "DidResolver validates DID format: did:plc:[a-z2-7]{24} before making the HTTP call (RESEARCH §Handle / DID Resolution)"
    - "DidResolver uses \\Cake\\Http\\Client (not curl_*) — this makes unit tests possible via CakePHP's Client mock adapter"
    - "Integration tests assert the actual JSON body + headers served by the CakePHP integration test framework"
    - "composer test exits 0 with all Plan 02-03 tests + Phase 1 + Plan 02-02 tests green"
  artifacts:
    - path: "src/Controller/OauthController.php"
      provides: "/oauth/client-metadata.json + /oauth/jwks.json endpoints; callback() stub; extends AppController"
      min_lines: 80
      contains: "class OauthController"
    - path: "src/Service/OAuth/Bluesky/DidResolver.php"
      provides: "DID → PDS URL resolution via plc.directory using Cake\\Http\\Client"
      min_lines: 50
      contains: "resolveDidToPds"
    - path: "tests/TestCase/Controller/OauthControllerTest.php"
      provides: "IntegrationTestTrait test for clientMetadata + jwks actions"
      contains: "testClientMetadataReturnsJson"
    - path: "tests/TestCase/Service/OAuth/Bluesky/DidResolverTest.php"
      provides: "DID format validation + plc.directory response parsing (Cake\\Http\\Client mocked)"
      contains: "testResolveDidToPds"
  key_links:
    - from: "OauthController::clientMetadata"
      to: "Configure::read('Bluesky.client_metadata')"
      via: "$this->response->withType('json')->withStringBody(json_encode(...))"
      pattern: "Configure::read.*Bluesky\\.client_metadata"
    - from: "OauthController::jwks"
      to: "KeyManager::getPublicJwk()"
      via: "DI via services() container OR fetchTable-style inline construction; returns {keys:[jwk]}"
      pattern: "KeyManager|getPublicJwk"
    - from: "DidResolver"
      to: "https://plc.directory/<did>"
      via: "Cake\\Http\\Client::get() with timeout and error handling"
      pattern: "plc.directory"
    - from: "config/routes.php (Plan 02-01)"
      to: "OauthController::clientMetadata / ::jwks / ::callback"
      via: "$builder->connect('/oauth/client-metadata.json', [...'action' => 'clientMetadata'])"
      pattern: "Oauth.*clientMetadata|Oauth.*jwks"
---

<objective>
Bluesky OAuth の **公開 metadata エンドポイント 2 本** (`/oauth/client-metadata.json` / `/oauth/jwks.json`) を Controller 動的生成方式で提供し、DID → PDS 解決ユーティリティ (`DidResolver`) を Service 層に実装する。外部 AS (Bluesky) 側からこの 2 URL を即座に参照可能な状態にする (Plan 02-04 の PAR 実行前に AS が validate する)。

Purpose:
- AUTH-08 クロージング: `/oauth/jwks.json` と `/oauth/client-metadata.json` の公開 (ROADMAP Phase 2 success criterion #5)
- Bluesky AS 側から見て `client_id` URL が 200 + 正しい JSON を返すことを保証 (AT Protocol 仕様: client_id は metadata JSON URL と byte-for-byte 一致、D-16 / Pitfall 5)
- `DidResolver` を独立 Service として用意 → Plan 02-04 の `BlueskyOAuthClient::resolveProfile()` がこれを DI する
- Plan 02-04 と並列に Wave 2 で実行可能 (Plan 02-02 crypto services とは file-overlap 無し)

Output:
- 新規 `OauthController` (3 action: `clientMetadata`, `jwks`, `callback` — ただし callback は Plan 02-04 が中身を埋める stub)
- 新規 `DidResolver` Service
- `composer test` で IntegrationTestTrait を使った endpoint assertion + DidResolver の unit test が green

Scope boundary: この Plan では Bluesky への outbound HTTP は **DidResolver 側のみ** (plc.directory へ 1 request)。PAR / token endpoint は Plan 02-04 で `BlueskyOAuthClient` が実装する。OauthController::callback() は method slot のみ確保し、body は `throw new NotImplementedException()` 相当の 501 レスポンスにする (Plan 02-04 が置き換える)。
</objective>

<execution_context>
@/home/claude/.claude/get-shit-done/workflows/execute-plan.md
@/home/claude/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@/home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-CONTEXT.md
@/home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-RESEARCH.md
@/home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-PATTERNS.md
@/home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-01-foundation-setup-PLAN.md
@/home/claude/projects/tamabox/.planning/references/altotoo/BlueskyOauthComponent.php
@/home/claude/projects/tamabox/src/Controller/AppController.php
@/home/claude/projects/tamabox/src/Controller/PagesController.php
@/home/claude/projects/tamabox/config/bluesky.php
@/home/claude/projects/tamabox/config/routes.php

<interfaces>
<!-- OauthController extends AppController (existing in src/Controller/AppController.php).
     AppController already loads Flash + RequestHandler — OauthController just adds its 3 actions. -->

<!-- Plan 02-01 が routes.php で既に登録済みの経路 (Controller 未存在の状態で Plan 02-03 が埋める): -->
/oauth/callback             → OauthController::callback      (GET)   — stub in this plan, filled by Plan 02-04
/oauth/client-metadata.json → OauthController::clientMetadata (GET)  — implemented here
/oauth/jwks.json            → OauthController::jwks          (GET)   — implemented here

<!-- DidResolver Service signature for Plan 02-04 consumption: -->
namespace App\Service\OAuth\Bluesky;

final class DidResolver {
    public function __construct(?\Cake\Http\Client $http = null);  // Injectable for testing
    public function resolveDidToPds(string $did): string;  // Returns PDS URL (e.g. https://bsky.social)
    // Throws \RuntimeException on invalid DID format or plc.directory non-200.
}

<!-- CakePHP 4.5 IntegrationTestTrait for controller testing (already set up in Phase 1 — PagesControllerTest uses it). -->

<!-- Configure::read('Bluesky.client_metadata') from Plan 02-01's config/bluesky.php returns the full JSON body for client-metadata endpoint. -->

<!-- KeyManager::getPublicJwk() from Plan 02-02 returns {kty,crv,kid,use,alg,x,y} for jwks endpoint. -->
</interfaces>
</context>

<threat_model>
## Trust Boundaries

| Boundary | Description |
|----------|-------------|
| browser / Bluesky AS → /oauth/* GET | Public endpoints serving non-secret metadata; no auth required |
| plc.directory → DidResolver | External HTTP — DID document content is untrusted |
| CakePHP CSRF middleware → public GET endpoints | CSRF doesn't apply to GET but we must not accidentally echo CSRF tokens in JSON |

## STRIDE Threat Register

| Threat ID | Category | Component | Disposition | Mitigation Plan |
|-----------|----------|-----------|-------------|-----------------|
| T-02-03-01 | Spoofing | `client_id` URL mismatch (Pitfall 5) | mitigate | `OauthController::clientMetadata` returns Configure::read('Bluesky.client_metadata') verbatim; integration test asserts the exact URL string with no trailing slash |
| T-02-03-02 | Tampering | AS receives response with wrong Content-Type | mitigate | `$this->response->withType('application/json')` mandatory; integration test asserts `Content-Type: application/json` header |
| T-02-03-03 | Open redirect | `/oauth/callback` stub redirects attacker-controlled URL | mitigate | Stub returns 501 with static body — no redirect, no dynamic URL handling; Plan 02-04 adds state validation before any redirect |
| T-02-03-04 | Information Disclosure | `/oauth/jwks.json` exposes private key material | mitigate | KeyManager::getPublicJwk() reads only the public key file; integration test asserts the response JSON contains no 'd' claim (private scalar) |
| T-02-03-05 | Tampering | Attacker supplies malicious DID to resolveDidToPds | mitigate | DID format regex validation `^did:plc:[a-z2-7]{24}$` BEFORE any HTTP call; unit test asserts throw on `did:evil:`, `did:plc:<33 chars>`, `did:plc:<upper>` |
| T-02-03-06 | Denial of Service | plc.directory slow / hung responses | mitigate | `Cake\Http\Client` with `timeout => 10` seconds; unit test with mocked slow response asserts timeout behavior captures as RuntimeException |
| T-02-03-07 | Information Disclosure | HTTP error from plc.directory exposes internal paths | mitigate | `RuntimeException` message is generic `"DID resolution failed"`; original response body is NOT embedded |
| T-02-03-08 | Replay / Cache Poisoning | AS caches client_metadata; if we change scope later, old clients have stale view | accept | Out-of-scope for MVP per CONTEXT Deferred Ideas (scope rotation requires re-auth of all users — documented tradeoff D-06) |
</threat_model>

<tasks>

<task type="auto" tdd="false">
  <name>Task 1: OauthController 作成 (clientMetadata + jwks + callback stub) + integration test</name>
  <files>src/Controller/OauthController.php, tests/TestCase/Controller/OauthControllerTest.php</files>

  <read_first>
    - /home/claude/projects/tamabox/src/Controller/AppController.php (parent — Flash + RequestHandler 読込済か確認)
    - /home/claude/projects/tamabox/src/Controller/PagesController.php (既存 Controller の file header / namespace / action style)
    - /home/claude/projects/tamabox/tests/TestCase/Controller/PagesControllerTest.php (既存 IntegrationTestTrait 利用例 — assertResponseCode / assertContentType / assertResponseContains)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-PATTERNS.md §`src/Controller/OauthController.php` (clientMetadata + jwks JSON response pattern)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-CONTEXT.md D-04 (Controller 配置) / D-16 (client-metadata endpoint) / D-17 (jwks endpoint)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-RESEARCH.md §Client Metadata (必須フィールド全量)
    - /home/claude/projects/tamabox/config/bluesky.php (Configure::read で引く key の完全なパス確認)
    - /home/claude/projects/tamabox/src/Service/OAuth/KeyManager.php (Plan 02-02 から DI 対象)
    - /home/claude/projects/tamabox/config/routes.php (Plan 02-01 で登録済みの /oauth/client-metadata.json, /oauth/jwks.json, /oauth/callback 3 ルート確認)
  </read_first>

  <action>

  ## A. Verify prerequisites

  ```bash
  cd /home/claude/projects/tamabox
  # Confirm Plan 02-01 + 02-02 artifacts are in place:
  test -f config/bluesky.php && test -f src/Service/OAuth/KeyManager.php && grep -q '/oauth/client-metadata.json' config/routes.php
  ```
  Exit 0 required before proceeding.

  ## B. `src/Controller/OauthController.php` を作成

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Controller;

  use App\Service\OAuth\KeyManager;
  use Cake\Core\Configure;
  use Cake\Http\Response;

  /**
   * OAuth public endpoints + callback.
   *
   * Routes registered in config/routes.php by Plan 02-01:
   *   GET /oauth/client-metadata.json → clientMetadata()
   *   GET /oauth/jwks.json            → jwks()
   *   GET /oauth/callback             → callback()   (this plan ships a 501 stub; Plan 02-04 fills the body)
   *
   * The first two are AT Protocol public metadata that Bluesky AS hits during
   * PAR / token exchange (CONTEXT D-16 / D-17). They must:
   *   - return 200 with Content-Type: application/json
   *   - never redirect
   *   - embed exact Configure-driven values (no dynamic URL building that could drift from client_id)
   */
  class OauthController extends AppController
  {
      /**
       * /oauth/client-metadata.json — AT Protocol client metadata (D-16).
       */
      public function clientMetadata(): Response
      {
          $metadata = Configure::read('Bluesky.client_metadata');
          if (!is_array($metadata) || !isset($metadata['client_id'])) {
              // Misconfiguration — Plan 02-01 should have populated this.
              return $this->response
                  ->withStatus(500)
                  ->withType('application/json')
                  ->withStringBody((string)json_encode(['error' => 'metadata_not_configured']));
          }

          return $this->response
              ->withType('application/json')
              ->withStringBody((string)json_encode($metadata, JSON_UNESCAPED_SLASHES));
      }

      /**
       * /oauth/jwks.json — public JWKS containing the ES256 public key (D-17).
       */
      public function jwks(): Response
      {
          $keyManager = new KeyManager();
          try {
              $jwk = $keyManager->getPublicJwk();
          } catch (\RuntimeException $e) {
              return $this->response
                  ->withStatus(500)
                  ->withType('application/json')
                  ->withStringBody((string)json_encode(['error' => 'key_not_available']));
          }

          return $this->response
              ->withType('application/json')
              ->withStringBody((string)json_encode(['keys' => [$jwk]], JSON_UNESCAPED_SLASHES));
      }

      /**
       * /oauth/callback — OAuth authorization code callback. STUB.
       *
       * Plan 02-04 replaces this body with: state verify → token exchange (BlueskyOAuthClient)
       * → UPSERT user_identities (AES-GCM encrypted tokens) → setIdentity → 302 /dashboard.
       *
       * Until then this returns 501 so that any accidental pre-Plan-02-04 hit is visibly broken
       * rather than silently succeeding with nothing done.
       *
       * @return \Cake\Http\Response
       */
      public function callback(): Response
      {
          return $this->response
              ->withStatus(501)
              ->withType('application/json')
              ->withStringBody((string)json_encode(['error' => 'callback_not_yet_implemented_plan_02_04']));
      }
  }
  ```

  注意:
  - `KeyManager()` を `new` で生成している: CakePHP 4.x の DI Container は Controller action の argument DI をまだサポートしていない (CakePHP 5 の機能)。Plan 02-04 で `AuthController` に service injection するときに同じパターンを使う。
  - `callback()` は 501 stub — 署名・state 検証 ロジックは Plan 02-04 責務。routes.php には既に登録済 (Plan 02-01) なので `bin/cake routes check` で 404 にはならない。
  - `Configure::read('Bluesky.client_metadata')` は Plan 02-01 config/bluesky.php の array を丸ごと返す。JSON encode して返す。dynamic URL building はしない (T-02-03-01 mitigation)。
  - `JSON_UNESCAPED_SLASHES` は URL に含まれる `/` を `\/` にエスケープしないため — byte-for-byte match 要件 (Pitfall 5) のために必須。

  ## C. `tests/TestCase/Controller/OauthControllerTest.php` を作成

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Test\TestCase\Controller;

  use Cake\Core\Configure;
  use Cake\TestSuite\IntegrationTestTrait;
  use Cake\TestSuite\TestCase;

  class OauthControllerTest extends TestCase
  {
      use IntegrationTestTrait;

      protected function setUp(): void
      {
          parent::setUp();
          putenv('OAUTH_KID=test-kid-1');
          $_ENV['OAUTH_KID'] = 'test-kid-1';
          // Point KeyManager at the test fixture keys (production config/keys/ may or may not exist in CI).
          Configure::write('Bluesky.private_key_path', TESTS . 'Fixture' . DS . 'keys' . DS . 'private.key');
          Configure::write('Bluesky.public_key_path',  TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key');
      }

      public function testClientMetadataReturns200Json(): void
      {
          $this->get('/oauth/client-metadata.json');
          $this->assertResponseCode(200);
          $this->assertContentType('application/json');
      }

      public function testClientMetadataHasExpectedClientId(): void
      {
          $this->get('/oauth/client-metadata.json');
          $this->assertResponseCode(200);
          $body = json_decode((string)$this->_response->getBody(), true);
          $this->assertIsArray($body);
          $this->assertSame('https://tamabox.emomie.com/oauth/client-metadata.json', $body['client_id']);
          $this->assertSame('atproto transition:generic', $body['scope']);
          $this->assertSame('private_key_jwt', $body['token_endpoint_auth_method']);
          $this->assertSame('ES256', $body['token_endpoint_auth_signing_alg']);
          $this->assertTrue($body['dpop_bound_access_tokens']);
          $this->assertSame(['https://tamabox.emomie.com/oauth/callback'], $body['redirect_uris']);
          $this->assertSame('https://tamabox.emomie.com/oauth/jwks.json', $body['jwks_uri']);
      }

      public function testClientMetadataBodyDoesNotContainEscapedSlashes(): void
      {
          // JSON_UNESCAPED_SLASHES must keep '/' literal — AT Protocol byte-for-byte match (Pitfall 5).
          $this->get('/oauth/client-metadata.json');
          $this->assertResponseCode(200);
          $raw = (string)$this->_response->getBody();
          $this->assertStringNotContainsString('\\/', $raw);
          $this->assertStringContainsString('https://tamabox.emomie.com/', $raw);
      }

      public function testJwksReturns200Json(): void
      {
          $this->get('/oauth/jwks.json');
          $this->assertResponseCode(200);
          $this->assertContentType('application/json');
      }

      public function testJwksContainsExactlyOneEcKey(): void
      {
          $this->get('/oauth/jwks.json');
          $this->assertResponseCode(200);
          $body = json_decode((string)$this->_response->getBody(), true);
          $this->assertArrayHasKey('keys', $body);
          $this->assertCount(1, $body['keys']);
          $key = $body['keys'][0];
          $this->assertSame('EC', $key['kty']);
          $this->assertSame('P-256', $key['crv']);
          $this->assertSame('ES256', $key['alg']);
          $this->assertSame('sig', $key['use']);
          $this->assertSame('test-kid-1', $key['kid']);
          $this->assertArrayHasKey('x', $key);
          $this->assertArrayHasKey('y', $key);
      }

      public function testJwksDoesNotLeakPrivateScalar(): void
      {
          // T-02-03-04 mitigation: 'd' (ECDSA private scalar) MUST NOT appear in public JWKS.
          $this->get('/oauth/jwks.json');
          $body = json_decode((string)$this->_response->getBody(), true);
          $this->assertArrayNotHasKey('d', $body['keys'][0]);
      }

      public function testCallbackStubReturns501(): void
      {
          // This test locks in that Plan 02-03 ships a 501 stub so pre-Plan-02-04 hits are visibly broken.
          // Plan 02-04 MUST flip this to either 302 (success) or 302 to /?flash=error (failure).
          $this->get('/oauth/callback?code=x&state=y');
          $this->assertResponseCode(501);
      }
  }
  ```

  ## D. Run tests

  ```bash
  cd /home/claude/projects/tamabox && vendor/bin/phpunit --filter OauthControllerTest --no-coverage --testdox
  ```

  Expected: 7 tests, all green. If the `callback()` test fails because Plan 02-04 hasn't been run yet... wait, we're in Plan 02-03, so callback should be the 501 stub. Test expects 501 — this locks in the hand-off contract.

  ## E. Lint/static

  ```bash
  composer phpcs src/Controller/OauthController.php tests/TestCase/Controller/OauthControllerTest.php
  composer phpstan
  composer test
  ```

  すべて exit 0.
  </action>

  <verify>
    <automated>cd /home/claude/projects/tamabox && php -l src/Controller/OauthController.php 2>&1 | grep -q 'No syntax errors' && php -l tests/TestCase/Controller/OauthControllerTest.php 2>&1 | grep -q 'No syntax errors' && grep -q 'class OauthController extends AppController' src/Controller/OauthController.php && grep -qE 'public function clientMetadata\(\):' src/Controller/OauthController.php && grep -qE 'public function jwks\(\):' src/Controller/OauthController.php && grep -qE 'public function callback\(\):' src/Controller/OauthController.php && grep -q 'JSON_UNESCAPED_SLASHES' src/Controller/OauthController.php && grep -q "Configure::read\\('Bluesky.client_metadata'\\)" src/Controller/OauthController.php && grep -q 'withStatus(501)' src/Controller/OauthController.php && vendor/bin/phpunit --filter OauthControllerTest --no-coverage 2>&1 | tail -5 | grep -qE 'OK \([0-9]+ tests' && composer phpstan 2>&1 | grep -q '\[OK\] No errors' && echo VERIFY_OK</automated>
  </verify>

  <acceptance_criteria>
    - `test -f src/Controller/OauthController.php && test -f tests/TestCase/Controller/OauthControllerTest.php` exits 0
    - `php -l` clean on both
    - `grep -c 'class OauthController extends AppController' src/Controller/OauthController.php` = 1
    - 3 actions present: `grep -Ec 'public function (clientMetadata|jwks|callback)' src/Controller/OauthController.php` = 3
    - JSON correctness: `grep -c 'JSON_UNESCAPED_SLASHES' src/Controller/OauthController.php` ≥ 1
    - Config pull: `grep -c "Configure::read\\('Bluesky.client_metadata'\\)" src/Controller/OauthController.php` = 1
    - KeyManager DI: `grep -c 'new KeyManager' src/Controller/OauthController.php` = 1
    - Stub: `grep -c 'withStatus(501)' src/Controller/OauthController.php` = 1
    - `vendor/bin/phpunit --filter OauthControllerTest --no-coverage` exits 0, tests ≥ 7, 0 failures
    - Integration test passes the byte-match invariant: `testClientMetadataBodyDoesNotContainEscapedSlashes` green
    - Integration test passes key safety invariant: `testJwksDoesNotLeakPrivateScalar` green
    - `composer phpstan` exits 0
    - `composer phpcs` exits 0 for all new files
  </acceptance_criteria>

  <done>
    OauthController が作成され、/oauth/client-metadata.json および /oauth/jwks.json が integration test で期待 JSON を返すことが検証済み。/oauth/callback は 501 stub として in place (Plan 02-04 が埋めるまでの明示的 broken 状態)。phpcs / phpstan / phpunit 全 green。
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: DidResolver (plc.directory 経由 DID→PDS) + Cake\Http\Client mock による unit test</name>
  <files>src/Service/OAuth/Bluesky/DidResolver.php, tests/TestCase/Service/OAuth/Bluesky/DidResolverTest.php</files>

  <behavior>
    - DidResolver::resolveDidToPds('did:plc:abcdefghij2345nopqrstuvwx') returns the PDS URL parsed from plc.directory DID document (e.g., 'https://bsky.social')
    - DidResolver throws \RuntimeException on malformed DID BEFORE any HTTP call (regex `^did:plc:[a-z2-7]{24}$`)
    - DidResolver throws \RuntimeException on plc.directory 404 / 5xx / timeout
    - DidResolver throws \RuntimeException when response JSON has no 'service' with type 'AtprotoPersonalDataServer'
    - Constructor accepts optional Cake\Http\Client — nullable for production convenience (builds default with 10s timeout when null); tests always inject a mock
    - HTTP request is GET with Accept: application/json, no authentication, 10s timeout
  </behavior>

  <read_first>
    - /home/claude/projects/tamabox/.planning/references/altotoo/BlueskyOauthComponent.php (resolveDidToPds 相当ロジック — grep で見つけて参照、altotoo は curl_* 使用)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-RESEARCH.md §Handle / DID Resolution (DID format regex `^did:plc:[a-z2-7]{24}$` / plc.directory レスポンス形式) + §Open Questions Q1 (Cake\\Http\\Client 推奨理由)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-PATTERNS.md §Novel Patterns #DidResolver
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-CONTEXT.md D-03 (DidResolver 配置) / D-07 (handle→DID resolver は不要、DID→PDS のみ)
    - /home/claude/projects/tamabox/vendor/cakephp/cakephp/src/Http/Client.php (get() / get($url, [], ['type' => 'json']) API)
    - /home/claude/projects/tamabox/src/Service/OAuth/Bluesky/DpopService.php (Plan 02-02 namespace スタイル確認)
  </read_first>

  <action>

  ## A. `src/Service/OAuth/Bluesky/DidResolver.php` を作成

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Service\OAuth\Bluesky;

  use Cake\Http\Client;
  use RuntimeException;

  /**
   * DID → PDS URL resolver via plc.directory.
   *
   * Phase 2 scope: did:plc: only. did:web / third-party PDS is Deferred (CONTEXT.md).
   * CONTEXT D-07 precludes handle→DID resolution (AS handles that UI); only token.sub→PDS
   * mapping is needed here, which is a single plc.directory GET.
   */
  final class DidResolver
  {
      private const PLC_DIRECTORY_BASE = 'https://plc.directory/';
      private const DID_FORMAT_REGEX   = '/^did:plc:[a-z2-7]{24}$/';
      private const HTTP_TIMEOUT_SEC   = 10;

      private Client $http;

      public function __construct(?Client $http = null)
      {
          $this->http = $http ?? new Client([
              'timeout' => self::HTTP_TIMEOUT_SEC,
              'redirect' => 3,
          ]);
      }

      /**
       * @param string $did Must match `did:plc:[a-z2-7]{24}` (24 base32 chars after did:plc:).
       * @return string PDS URL (e.g. 'https://bsky.social').
       * @throws \RuntimeException Invalid DID, plc.directory non-200, missing PDS service entry.
       */
      public function resolveDidToPds(string $did): string
      {
          // T-02-03-05 mitigation: validate DID syntax BEFORE outbound HTTP.
          if (!preg_match(self::DID_FORMAT_REGEX, $did)) {
              throw new RuntimeException('Invalid DID format.');
          }

          try {
              $response = $this->http->get(
                  self::PLC_DIRECTORY_BASE . $did,
                  [],
                  ['headers' => ['Accept' => 'application/json']]
              );
          } catch (\Throwable $e) {
              throw new RuntimeException('DID resolution failed (network error).');
          }

          if ($response->getStatusCode() !== 200) {
              throw new RuntimeException('DID resolution failed.');
          }

          $doc = $response->getJson();
          if (!is_array($doc) || !isset($doc['service']) || !is_array($doc['service'])) {
              throw new RuntimeException('DID document missing service array.');
          }

          foreach ($doc['service'] as $entry) {
              if (is_array($entry)
                  && isset($entry['type'], $entry['serviceEndpoint'])
                  && $entry['type'] === 'AtprotoPersonalDataServer'
                  && is_string($entry['serviceEndpoint'])
                  && $entry['serviceEndpoint'] !== ''
              ) {
                  return rtrim($entry['serviceEndpoint'], '/');
              }
          }

          throw new RuntimeException('DID document has no AtprotoPersonalDataServer service.');
      }
  }
  ```

  ## B. `tests/TestCase/Service/OAuth/Bluesky/DidResolverTest.php` を作成

  CakePHP `Cake\Http\Client` は `Client\Adapter\Mock` で HTTP モック可能。

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Test\TestCase\Service\OAuth\Bluesky;

  use App\Service\OAuth\Bluesky\DidResolver;
  use Cake\Http\Client;
  use Cake\Http\Client\Adapter\Mock as MockAdapter;
  use Cake\Http\Client\Response;
  use Cake\TestSuite\TestCase;

  class DidResolverTest extends TestCase
  {
      private MockAdapter $adapter;
      private Client $http;

      protected function setUp(): void
      {
          parent::setUp();
          $this->adapter = new MockAdapter();
          $this->http = new Client([
              'adapter' => $this->adapter,
              'timeout' => 10,
          ]);
      }

      public function testResolveDidToPdsReturnsPdsUrl(): void
      {
          $did = 'did:plc:abcdefghij234567klmnopqr';
          $this->adapter->addResponse(
              new Response([
                  'HTTP/1.1 200 OK',
                  'Content-Type: application/json',
              ], (string)json_encode([
                  'id' => $did,
                  'service' => [
                      ['type' => 'AtprotoPersonalDataServer', 'serviceEndpoint' => 'https://bsky.social'],
                  ],
              ])),
              ['method' => 'GET', 'url' => 'https://plc.directory/' . $did]
          );

          $resolver = new DidResolver($this->http);
          $this->assertSame('https://bsky.social', $resolver->resolveDidToPds($did));
      }

      public function testResolveStripsTrailingSlash(): void
      {
          $did = 'did:plc:abcdefghij234567klmnopqr';
          $this->adapter->addResponse(
              new Response([
                  'HTTP/1.1 200 OK',
                  'Content-Type: application/json',
              ], (string)json_encode([
                  'service' => [
                      ['type' => 'AtprotoPersonalDataServer', 'serviceEndpoint' => 'https://bsky.social/'],
                  ],
              ])),
              ['method' => 'GET', 'url' => 'https://plc.directory/' . $did]
          );

          $this->assertSame('https://bsky.social', (new DidResolver($this->http))->resolveDidToPds($did));
      }

      /**
       * @dataProvider invalidDidProvider
       */
      public function testInvalidDidFormatThrows(string $bad): void
      {
          $this->expectException(\RuntimeException::class);
          $this->expectExceptionMessage('Invalid DID format');
          (new DidResolver($this->http))->resolveDidToPds($bad);
      }

      public static function invalidDidProvider(): array
      {
          return [
              'empty'      => [''],
              'wrong-scheme' => ['did:evil:abcdefghij234567klmnopqr'],
              'too-short'  => ['did:plc:abcdefghij234567klmnopq'],     // 23 chars
              'too-long'   => ['did:plc:abcdefghij234567klmnopqrs'],   // 25 chars
              'uppercase'  => ['did:plc:ABCDEFGHIJ234567KLMNOPQR'],    // A-Z not allowed
              'with-1'     => ['did:plc:1bcdefghij234567klmnopqr'],    // '1' not in base32-hex [a-z2-7]
              'sql-inject' => ["did:plc:' OR 1=1 --                 "],
          ];
      }

      public function testPlcDirectoryNon200Throws(): void
      {
          $did = 'did:plc:abcdefghij234567klmnopqr';
          $this->adapter->addResponse(
              new Response(['HTTP/1.1 404 Not Found'], '{"error":"not found"}'),
              ['method' => 'GET', 'url' => 'https://plc.directory/' . $did]
          );

          $this->expectException(\RuntimeException::class);
          $this->expectExceptionMessage('DID resolution failed');
          (new DidResolver($this->http))->resolveDidToPds($did);
      }

      public function testMissingServiceArrayThrows(): void
      {
          $did = 'did:plc:abcdefghij234567klmnopqr';
          $this->adapter->addResponse(
              new Response(['HTTP/1.1 200 OK', 'Content-Type: application/json'], (string)json_encode(['id' => $did])),
              ['method' => 'GET', 'url' => 'https://plc.directory/' . $did]
          );

          $this->expectException(\RuntimeException::class);
          $this->expectExceptionMessage('service array');
          (new DidResolver($this->http))->resolveDidToPds($did);
      }

      public function testNoAtprotoServiceEntryThrows(): void
      {
          $did = 'did:plc:abcdefghij234567klmnopqr';
          $this->adapter->addResponse(
              new Response(['HTTP/1.1 200 OK', 'Content-Type: application/json'], (string)json_encode([
                  'service' => [
                      ['type' => 'OtherService', 'serviceEndpoint' => 'https://not-pds.example'],
                  ],
              ])),
              ['method' => 'GET', 'url' => 'https://plc.directory/' . $did]
          );

          $this->expectException(\RuntimeException::class);
          $this->expectExceptionMessage('AtprotoPersonalDataServer');
          (new DidResolver($this->http))->resolveDidToPds($did);
      }

      public function testDefaultConstructorBuildsClient(): void
      {
          // Smoke test: no mock passed in — constructor still succeeds, just defaults to a real Client.
          $r = new DidResolver();
          $this->assertInstanceOf(DidResolver::class, $r);
      }
  }
  ```

  注意:
  - `Cake\Http\Client\Adapter\Mock::addResponse()` は CakePHP 4.5 で使える。API 要確認: `vendor/cakephp/cakephp/src/Http/Client/Adapter/Mock.php` で実 API を確認してから書く。もし API が差分ある場合 (例: `->addResponse(Response, ['method' => ..., 'url' => ...])`) 実装時に実シグネチャに合わせる。
  - DID validation test で dataProvider + `static` (PHPUnit 9.x) 構文を使用。Phase 1 の PHPUnit 9.6 で動作する。
  - `testDefaultConstructorBuildsClient` は無引数構築の smoke test — 実 HTTP 発行はしない (resolveDidToPds を呼ばない)。

  ## C. Run tests

  ```bash
  cd /home/claude/projects/tamabox && vendor/bin/phpunit --filter DidResolverTest --no-coverage --testdox
  ```

  Expected: ≥ 12 tests (~7 explicit + 7 dataProvider rows = 14), all green.

  ## D. Lint/static

  ```bash
  composer phpcs src/Service/OAuth/Bluesky/DidResolver.php tests/TestCase/Service/OAuth/Bluesky/DidResolverTest.php
  composer phpstan
  composer test
  ```

  すべて exit 0. Plan 02-03 終了時点でテスト総数 = Phase 1 17 + Plan 02-02 ~21 + Plan 02-03 ~20 = ~58 以上。
  </action>

  <verify>
    <automated>cd /home/claude/projects/tamabox && php -l src/Service/OAuth/Bluesky/DidResolver.php 2>&1 | grep -q 'No syntax errors' && php -l tests/TestCase/Service/OAuth/Bluesky/DidResolverTest.php 2>&1 | grep -q 'No syntax errors' && grep -q 'class DidResolver' src/Service/OAuth/Bluesky/DidResolver.php && grep -q "did:plc:\[a-z2-7\]{24}" src/Service/OAuth/Bluesky/DidResolver.php && grep -q 'plc.directory' src/Service/OAuth/Bluesky/DidResolver.php && grep -q 'AtprotoPersonalDataServer' src/Service/OAuth/Bluesky/DidResolver.php && grep -q 'Cake\\\\Http\\\\Client' src/Service/OAuth/Bluesky/DidResolver.php && vendor/bin/phpunit --filter DidResolverTest --no-coverage 2>&1 | tail -5 | grep -qE 'OK \([0-9]+ tests' && composer phpstan 2>&1 | grep -q '\[OK\] No errors' && composer test 2>&1 | tail -5 | grep -qE 'OK \(|Tests: [0-9]+' && echo VERIFY_OK</automated>
  </verify>

  <acceptance_criteria>
    - `test -f src/Service/OAuth/Bluesky/DidResolver.php && test -f tests/TestCase/Service/OAuth/Bluesky/DidResolverTest.php` exits 0
    - `php -l` clean on both
    - `grep -c 'class DidResolver' src/Service/OAuth/Bluesky/DidResolver.php` = 1
    - DID regex: `grep -c 'did:plc:\\[a-z2-7\\]{24}' src/Service/OAuth/Bluesky/DidResolver.php` ≥ 1
    - plc.directory constant: `grep -c 'plc.directory' src/Service/OAuth/Bluesky/DidResolver.php` ≥ 1
    - AtprotoPersonalDataServer matcher: `grep -c 'AtprotoPersonalDataServer' src/Service/OAuth/Bluesky/DidResolver.php` = 1
    - Cake\Http\Client use: `grep -c 'use Cake\\\\Http\\\\Client' src/Service/OAuth/Bluesky/DidResolver.php` ≥ 1
    - `grep -c 'timeout' src/Service/OAuth/Bluesky/DidResolver.php` ≥ 1 (T-02-03-06)
    - `vendor/bin/phpunit --filter DidResolverTest --no-coverage` exits 0, tests ≥ 10 (7 methods + 7 dataProvider rows with 1 test method = 13)
    - `composer test` exits 0, total tests ≥ 40 (Phase 1 + Plan 02-02 + Plan 02-03)
    - `composer phpstan` exits 0
    - `composer phpcs` exits 0
  </acceptance_criteria>

  <done>
    DidResolver 実装 + unit test 完了。plc.directory との HTTP やり取りが Cake\Http\Client + Mock Adapter で検証可能。不正 DID を HTTP 発行前にブロックし、plc 応答の不備を逐一 RuntimeException に変換する。Plan 02-04 の BlueskyOAuthClient::resolveProfile() が `new DidResolver()` で DI できる状態。
  </done>
</task>

</tasks>

<verification>
## Plan-level Verification

Run after both tasks complete:

1. **Endpoints live** (integration test harness):
   ```
   vendor/bin/phpunit --filter OauthControllerTest --no-coverage 2>&1 | tail -3 | grep -qE 'OK \([0-9]+ tests'
   ```

2. **Actual HTTP hit smoke** (manual if Lolipop / local web server is up):
   ```
   # Optional — requires a running PHP-FPM or `bin/cake server`:
   # curl -s -H 'Accept: application/json' http://localhost:8765/oauth/client-metadata.json | jq -e '.client_id == "https://tamabox.emomie.com/oauth/client-metadata.json"'
   # curl -s http://localhost:8765/oauth/jwks.json | jq -e '.keys[0].kty == "EC"'
   ```
   Note: not a CI gate — integration tests are authoritative.

3. **DidResolver unit coverage**:
   ```
   vendor/bin/phpunit --filter DidResolverTest --no-coverage 2>&1 | tail -3 | grep -qE 'OK \([0-9]+ tests'
   ```

4. **No composer deps added**: `git diff composer.json composer.lock` empty for this plan.

5. **Full suite**:
   ```
   composer test 2>&1 | tail -5 | grep -qE 'OK \(|Tests: [0-9]+'
   ```

6. **Lint/static**:
   ```
   composer phpcs && composer phpstan
   ```
   Both exit 0.

7. **Callback stub invariant for Plan 02-04 hand-off**:
   ```
   # The 501 stub MUST still be in place when Plan 02-04 picks up — Plan 02-04 first task
   # is "replace callback() body with full logic" and MUST fail if callback() already returns 200/302.
   grep -q 'withStatus(501)' src/Controller/OauthController.php
   ```
</verification>

<success_criteria>
Plan 02-03 complete when:
- [ ] `src/Controller/OauthController.php` exists with clientMetadata + jwks + callback (stub 501) actions
- [ ] `src/Service/OAuth/Bluesky/DidResolver.php` exists with resolveDidToPds using Cake\Http\Client
- [ ] 2 test files exist with integration + unit coverage
- [ ] `GET /oauth/client-metadata.json` → 200 JSON with client_id byte-exact match (integration test green)
- [ ] `GET /oauth/jwks.json` → 200 JSON with 1 EC P-256 key, no `d` claim (integration test green)
- [ ] `GET /oauth/callback?...` → 501 (stub; Plan 02-04 replaces)
- [ ] DidResolver validates DID format before any HTTP call
- [ ] DidResolver throws on plc.directory errors / missing PDS service
- [ ] `composer phpcs / phpstan / test` all exit 0
- [ ] No composer deps added
</success_criteria>

<output>
After completion, create `.planning/phases/02-bluesky-oauth-identity/02-03-SUMMARY.md` with frontmatter (requirements_closed: AUTH-08), commits log, per-task acceptance, deviations, integration-test snapshot of the served JSON bodies, hand-off note to Plan 02-04 ("replace callback stub with full flow"), self-check.
</output>
