---
phase: 02-bluesky-oauth-identity
plan: 02
type: execute
wave: 2
depends_on:
  - 02-01
files_modified:
  - src/Service/OAuth/KeyManager.php
  - src/Service/OAuth/TokenEncryptionService.php
  - src/Service/OAuth/Bluesky/DpopService.php
  - src/Service/OAuth/Bluesky/ClientJwtService.php
  - tests/TestCase/Service/OAuth/KeyManagerTest.php
  - tests/TestCase/Service/OAuth/TokenEncryptionServiceTest.php
  - tests/TestCase/Service/OAuth/Bluesky/DpopServiceTest.php
  - tests/TestCase/Service/OAuth/Bluesky/ClientJwtServiceTest.php
  - tests/Fixture/keys/private.key
  - tests/Fixture/keys/public.key
autonomous: true
requirements:
  - AUTH-07
  - AUTH-08
tags:
  - oauth
  - crypto
  - es256
  - dpop
  - aes-gcm
  - jwk
  - jwt
  - unit-tests

must_haves:
  truths:
    - "KeyManager::getPublicJwk() returns array with kty=EC, crv=P-256, use=sig, alg=ES256, x/y base64url-encoded from config/keys/public.key"
    - "KeyManager::getPublicJwkForDpop() returns same x/y/kty/crv but omits kid/use/alg (DPoP header.jwk minimal form per CONTEXT D-12)"
    - "KeyManager::getPrivateKey() returns a valid OpenSSLAsymmetricKey handle usable by openssl_sign"
    - "TokenEncryptionService::encrypt($plaintext) -> decrypt($ciphertext) round-trips to the original plaintext for any input 1..2000 bytes"
    - "TokenEncryptionService encryption output is base64url(iv(12) || ciphertext || tag(16)) per CONTEXT D-15; tampering any byte causes decrypt to throw"
    - "DpopService::createProof(htm, htu) emits a 3-part JWT with typ=dpop+jwt / alg=ES256 / jwk claim in header; payload has htm, htu, iat, exp=iat+60, jti"
    - "DpopService::createProof with access_token arg adds ath=base64url(sha256(access_token)) claim (CONTEXT D-13)"
    - "DpopService::createProof with nonce arg adds nonce claim"
    - "ClientJwtService::createAssertion(audience) emits a 3-part JWT with alg=ES256 + kid header; payload has iss=sub=client_id, aud=<audience arg>, jti, iat, exp=iat+60"
    - "All 4 service classes use PHP builtin openssl_sign + derToRawSignature (DER→R||S raw 64 bytes per CONTEXT D-11) — NO external JWT library"
    - "All unit tests pass (PHPUnit 9.6): 4 test files, each asserting JWT structure + signature verification via openssl_verify"
    - "composer phpcs / phpstan / test all exit 0 after this plan"
  artifacts:
    - path: "src/Service/OAuth/KeyManager.php"
      provides: "PEM → JWK conversion, EC private key loader; testable via path-injection constructor"
      min_lines: 80
      contains: "openssl_pkey_get_details"
    - path: "src/Service/OAuth/TokenEncryptionService.php"
      provides: "AES-256-GCM encrypt/decrypt with IV||CT||TAG base64url encoding"
      min_lines: 60
      contains: "aes-256-gcm"
    - path: "src/Service/OAuth/Bluesky/DpopService.php"
      provides: "DPoP proof JWT generator (RFC 9449) with header.jwk, ath, nonce support"
      min_lines: 100
      contains: "dpop+jwt"
    - path: "src/Service/OAuth/Bluesky/ClientJwtService.php"
      provides: "private_key_jwt client_assertion generator with kid header"
      min_lines: 50
      contains: "createAssertion"
    - path: "tests/TestCase/Service/OAuth/KeyManagerTest.php"
      provides: "PEM → JWK coordinate extraction tests"
      contains: "testGetPublicJwk"
    - path: "tests/TestCase/Service/OAuth/TokenEncryptionServiceTest.php"
      provides: "AES-GCM round-trip + tamper detection tests"
      contains: "testRoundTrip"
    - path: "tests/TestCase/Service/OAuth/Bluesky/DpopServiceTest.php"
      provides: "DPoP JWT structure + ath + nonce tests + openssl_verify signature check"
      contains: "testCreateProof"
    - path: "tests/TestCase/Service/OAuth/Bluesky/ClientJwtServiceTest.php"
      provides: "client_assertion JWT structure + signature verification tests"
      contains: "testCreateAssertion"
    - path: "tests/Fixture/keys/private.key"
      provides: "Test-only ES256 EC P-256 private key (dummy, VCS-tracked)"
    - path: "tests/Fixture/keys/public.key"
      provides: "Test-only ES256 EC P-256 public key"
  key_links:
    - from: "DpopService"
      to: "KeyManager"
      via: "constructor injection — DpopService uses KeyManager->getPublicJwkForDpop() + getPrivateKey()"
      pattern: "new DpopService.*KeyManager|KeyManager \\$keyManager"
    - from: "ClientJwtService"
      to: "KeyManager"
      via: "constructor injection — getPrivateKey() for ES256 sign"
      pattern: "ClientJwtService.*KeyManager"
    - from: "DpopService::createProof"
      to: "Output JWT"
      via: "derToRawSignature() converts openssl_sign DER output to 64-byte R||S raw"
      pattern: "derToRawSignature"
    - from: "TokenEncryptionService"
      to: "env('TOKEN_ENC_KEY')"
      via: "hex2bin(env('TOKEN_ENC_KEY')) → 32-byte AES key per invocation"
      pattern: "hex2bin.*TOKEN_ENC_KEY"
---

<objective>
純粋暗号サービス 4 クラス (KeyManager / TokenEncryptionService / DpopService / ClientJwtService) を実装し、それぞれを単体テストで検証する。外部 HTTP 呼び出しなし、DB アクセスなし、Controller なし — すべて openssl ext + PHP ビルトインのみで完結する副作用のないコンポーネント層。

Purpose:
- altotoo `BlueskyOauthComponent.php` の crypto コアを Service 層として抽出 (D-03)
- 罠 (DER → R||S 変換 / jwk header 埋め / ath claim / AES-GCM IV||CT||TAG) を altotoo 踏襲で一括回避 (D-11, D-12, D-13, D-15)
- Plan 02-04 の BlueskyOAuthClient が `new BlueskyOAuthClient($dpopService, $clientJwtService, $tokenEncryption, ...)` で DI できる状態にする
- 単体テスト済みの crypto primitives があれば Plan 02-04 の integration test は HTTP mock のみ書けばよい (crypto 正しさは別途保証)

Output:
- 4 service クラス (1 shared `src/Service/OAuth/` + Bluesky-specific `src/Service/OAuth/Bluesky/`)
- 4 PHPUnit test ファイル + テスト用 EC 鍵ペア (VCS-tracked dummy)
- `composer test` green (新規テストが 4 本パス + Phase 1 ベースライン保持)
- AUTH-07 (AES-GCM) 達成、AUTH-08 (鍵管理) 部分達成 (jwks endpoint は Plan 02-03)
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
@/home/claude/projects/tamabox/src/Service/OAuth/OAuthProviderInterface.php
@/home/claude/projects/tamabox/config/bluesky.php
@/home/claude/projects/tamabox/phpunit.xml.dist

<interfaces>
<!-- この Plan 内で定義される class interface — Plan 02-04 が DI する -->

```php
namespace App\Service\OAuth;

final class KeyManager {
    public function __construct(
        private readonly string $privateKeyPath = '',
        private readonly string $publicKeyPath = ''
    );  // Empty string → defaults to Configure::read('Bluesky.private_key_path') etc.
    public function getPublicJwk(): array;           // Full JWK with kid/use/alg (for jwks.json)
    public function getPublicJwkForDpop(): array;    // Minimal JWK (kty/crv/x/y) for DPoP header
    public function getPrivateKey(): \OpenSSLAsymmetricKey;
}

final class TokenEncryptionService {
    public function encrypt(string $plaintext): string;  // Returns base64url(IV||CT||TAG)
    public function decrypt(string $encoded): string;    // Throws \RuntimeException on tamper
}

namespace App\Service\OAuth\Bluesky;

final class DpopService {
    public function __construct(private readonly \App\Service\OAuth\KeyManager $keyManager);
    public function createProof(
        string $htm,
        string $htu,
        ?string $accessToken = null,
        ?string $nonce = null
    ): string;  // Returns compact JWT (3 parts, dot-separated)
}

final class ClientJwtService {
    public function __construct(private readonly \App\Service\OAuth\KeyManager $keyManager);
    public function createAssertion(string $audience): string;  // private_key_jwt per D-16
}
```

<!-- altotoo derToRawSignature() — MUST be copy-migrated verbatim into DpopService + ClientJwtService.
     RESEARCH.md Pitfall 1 warns that openssl_sign outputs DER; JWT ES256 requires 64-byte R||S raw. -->

Phase 1 で生成済みの test infrastructure:
- `phpunit.xml.dist` exists, `composer test` invokes it
- `tests/TestCase/Model/LocatorSmokeTest.php` はクリーンに passing (Phase 1 baseline)
- `tests/Fixture/` 配下は `Users/User Identities/.../` と個別テーブル fixture がすでに在る (Plan 01-03)
- PHPUnit 9.6 + `Cake\TestSuite\TestCase` パターンが Phase 1 で稼働

Bluesky public JWK 形式 (jwks.json — Plan 02-03 で配信):
```json
{"keys": [{"kty":"EC","crv":"P-256","kid":"<OAUTH_KID>","use":"sig","alg":"ES256","x":"<b64u>","y":"<b64u>"}]}
```

DPoP header.jwk 形式 (minimal — kid/use/alg なし):
```json
{"kty":"EC","crv":"P-256","x":"<b64u>","y":"<b64u>"}
```

AES-256-GCM フォーマット (D-15):
```
plaintext → openssl_encrypt(AES-256-GCM, IV=random_bytes(12), tag=16)
          → base64url( IV(12) || ciphertext || tag(16) )
```
</interfaces>
</context>

<threat_model>
## Trust Boundaries

| Boundary | Description |
|----------|-------------|
| config/keys/private.key → PHP process | Private ES256 key crosses filesystem→memory |
| env('TOKEN_ENC_KEY') → process | AES-256-GCM key from .env |
| user_identities.*_enc ciphertext → memory | Encrypted OAuth tokens decrypted on-demand |

## STRIDE Threat Register

| Threat ID | Category | Component | Disposition | Mitigation Plan |
|-----------|----------|-----------|-------------|-----------------|
| T-02-02-01 | Tampering | DPoP proof forgery (wrong signature format) | mitigate | `DpopService` uses `derToRawSignature()` (CONTEXT D-11, altotoo L37-70 verbatim) to convert openssl_sign DER to JWT-compliant R\|\|S 64 bytes; unit test verifies via `openssl_verify` that the JWT signature validates against the public key |
| T-02-02-02 | Tampering | DPoP proof accepted without `jwk` header → AS cannot verify | mitigate | `DpopService::createProof` always emits `jwk` in header (CONTEXT D-12); unit test `testJwkClaimPresent` asserts `header.jwk.kty == 'EC' && header.jwk.crv == 'P-256'` |
| T-02-02-03 | Tampering | PDS resource call rejected for missing `ath` claim → 401 | mitigate | When `$accessToken !== null`, `DpopService::createProof` adds `ath = base64url(sha256(access_token))` (CONTEXT D-13); unit test `testAthClaimAddedWhenAccessTokenProvided` covers it |
| T-02-02-04 | Replay | DPoP proof replay (jti reuse) | mitigate | `DpopService::createProof` generates `jti = base64url(random_bytes(32))` and `iat = time()` per invocation (stateless); every call produces a distinct jti; unit test `testEveryProofHasUniqueJti` generates 10 proofs and asserts uniqueness |
| T-02-02-05 | Information Disclosure | AES-GCM ciphertext disclosure via timing / tag-length trimming | accept | openssl_decrypt returns false on tag mismatch (constant-time tag verify in OpenSSL); we wrap in `throw` on false; no partial-plaintext leakage possible with GCM |
| T-02-02-06 | Information Disclosure | TOKEN_ENC_KEY leaked via error message | mitigate | Error messages in TokenEncryptionService never include key or plaintext — only generic "Token decryption failed" strings; unit test `testDecryptTamperedCiphertextThrows` confirms exception message is generic |
| T-02-02-07 | Tampering | IV reuse catastrophic to AES-GCM confidentiality+integrity | mitigate | `encrypt()` calls `random_bytes(12)` per invocation (no static IV); unit test `testTwoEncryptionsOfSameInputDiffer` asserts distinct ciphertexts for the same plaintext |
| T-02-02-08 | Information Disclosure | Private key file reachable via webroot | accept | Out-of-scope here — `config/keys/` is webroot-external; Lolipop webroot isolation ensured in Phase 4 INFRA-06 |
| T-02-02-09 | Spoofing | client_assertion accepted at wrong `aud` (token vs PAR confusion) | mitigate | `ClientJwtService::createAssertion(string $audience)` forces caller to pass the correct aud (Plan 02-04 BlueskyOAuthClient passes par_endpoint for PAR and token_endpoint for token exchange — enforced at call sites) |
</threat_model>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: KeyManager + TokenEncryptionService + 2 unit tests + test-fixture EC keys</name>
  <files>src/Service/OAuth/KeyManager.php, src/Service/OAuth/TokenEncryptionService.php, tests/TestCase/Service/OAuth/KeyManagerTest.php, tests/TestCase/Service/OAuth/TokenEncryptionServiceTest.php, tests/Fixture/keys/private.key, tests/Fixture/keys/public.key</files>

  <behavior>
    - KeyManager::getPublicJwk() returns complete JWK with kid=env('OAUTH_KID'), use=sig, alg=ES256, EC P-256 coordinates from config/keys/public.key (or injected path)
    - KeyManager::getPublicJwkForDpop() returns {kty,crv,x,y} only (no kid/use/alg)
    - KeyManager::getPrivateKey() returns a valid OpenSSLAsymmetricKey that openssl_sign accepts
    - TokenEncryptionService::encrypt → decrypt round-trips preserve the input plaintext (1 byte → 2 KB)
    - TokenEncryptionService: encrypting the same plaintext twice produces different ciphertexts (distinct IVs)
    - TokenEncryptionService::decrypt throws \RuntimeException when the ciphertext has been tampered (any byte flipped)
    - TokenEncryptionService output format is base64url(IV(12) || CT || TAG(16)); decoded length is 12 + strlen(plaintext) + 16 (matches GCM raw output)
  </behavior>

  <read_first>
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-RESEARCH.md §Code Examples (PEM→JWK + AES-GCM encrypt/decrypt — PHP 8.3.6 で動作確認済みコード)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-PATTERNS.md §`KeyManager.php` + §`TokenEncryptionService.php`
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-CONTEXT.md D-14 (鍵パス) / D-15 (AES-GCM フォーマット: base64url(IV||CT||TAG))
    - /home/claude/projects/tamabox/.planning/references/altotoo/BlueskyOauthComponent.php L126-140 (getPublicJwk() → openssl_pkey_get_details の 'ec' key 使用方)
    - /home/claude/projects/tamabox/tests/TestCase/Model/LocatorSmokeTest.php (既存テスト CakePHP TestCase スタイル確認)
    - /home/claude/projects/tamabox/config/keys/private.key (Plan 02-01 で生成済み)
  </read_first>

  <action>

  ## A. Generate test-only EC P-256 keypair

  Phase 2 Plan 02-01 で生成した `config/keys/private.key` は本番相当の秘密鍵 (gitignored)。テストはこれに依存しないよう、**別途 dummy キーペアを** `tests/Fixture/keys/` に配置する (CONTEXT `<specifics>` のテスト戦略、PATTERNS.md テスト方針)。

  ```bash
  cd /home/claude/projects/tamabox
  mkdir -p tests/Fixture/keys
  openssl ecparam -genkey -name prime256v1 -noout -out tests/Fixture/keys/private.key
  openssl ec -in tests/Fixture/keys/private.key -pubout -out tests/Fixture/keys/public.key
  chmod 644 tests/Fixture/keys/private.key  # dummy — OK to track in VCS
  chmod 644 tests/Fixture/keys/public.key
  ```

  これらは **VCS に commit する** (Phase 1 の fixture と同じ扱い、機密性なし)。production private.key は別物なので `.gitignore` パターン `config/keys/*.key` とは衝突しない (tests/Fixture/ は別ディレクトリ)。

  ## B. `src/Service/OAuth/KeyManager.php` を作成

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Service\OAuth;

  use Cake\Core\Configure;
  use RuntimeException;

  /**
   * ES256 keypair loader + PEM → JWK converter.
   *
   * Production key paths default to config/keys/private.key + public.key (Phase 2 Plan 02-01).
   * Tests inject tests/Fixture/keys/ via constructor args so that config/keys/ doesn't
   * need to exist in CI.
   */
  final class KeyManager
  {
      public function __construct(
          private string $privateKeyPath = '',
          private string $publicKeyPath = ''
      ) {
          if ($this->privateKeyPath === '') {
              $this->privateKeyPath = (string)Configure::read(
                  'Bluesky.private_key_path',
                  CONFIG . 'keys' . DS . 'private.key'
              );
          }
          if ($this->publicKeyPath === '') {
              $this->publicKeyPath = (string)Configure::read(
                  'Bluesky.public_key_path',
                  CONFIG . 'keys' . DS . 'public.key'
              );
          }
      }

      /**
       * Return the public JWK with all standard fields (kid/use/alg). Used in /oauth/jwks.json.
       *
       * @return array{kty: string, crv: string, kid: string, use: string, alg: string, x: string, y: string}
       */
      public function getPublicJwk(): array
      {
          $coords = $this->extractEcCoordinates();

          return [
              'kty' => 'EC',
              'crv' => 'P-256',
              'kid' => (string)env('OAUTH_KID', 'ssr-box-key-1'),
              'use' => 'sig',
              'alg' => 'ES256',
              'x'   => $this->base64urlEncode($coords['x']),
              'y'   => $this->base64urlEncode($coords['y']),
          ];
      }

      /**
       * Return the minimal JWK form used in DPoP proof header (RFC 9449).
       * DPoP specifies kty/crv/x/y only; kid/use/alg would duplicate values already in header.
       *
       * @return array{kty: string, crv: string, x: string, y: string}
       */
      public function getPublicJwkForDpop(): array
      {
          $coords = $this->extractEcCoordinates();

          return [
              'kty' => 'EC',
              'crv' => 'P-256',
              'x'   => $this->base64urlEncode($coords['x']),
              'y'   => $this->base64urlEncode($coords['y']),
          ];
      }

      /**
       * Return the raw OpenSSLAsymmetricKey handle for openssl_sign consumption.
       *
       * @throws \RuntimeException if the key file is absent or unreadable.
       */
      public function getPrivateKey(): \OpenSSLAsymmetricKey
      {
          if (!is_readable($this->privateKeyPath)) {
              throw new RuntimeException('ES256 private key not readable at ' . $this->privateKeyPath);
          }
          $pem = (string)file_get_contents($this->privateKeyPath);
          $key = openssl_pkey_get_private($pem);
          if ($key === false) {
              throw new RuntimeException('Failed to parse ES256 private key (not a valid PEM EC P-256 key).');
          }

          return $key;
      }

      /**
       * @return array{x: string, y: string} Raw bytes from openssl_pkey_get_details['ec'].
       */
      private function extractEcCoordinates(): array
      {
          if (!is_readable($this->publicKeyPath)) {
              throw new RuntimeException('ES256 public key not readable at ' . $this->publicKeyPath);
          }
          $pem = (string)file_get_contents($this->publicKeyPath);
          $pub = openssl_pkey_get_public($pem);
          if ($pub === false) {
              throw new RuntimeException('Failed to parse ES256 public key.');
          }
          $details = openssl_pkey_get_details($pub);
          if ($details === false || !isset($details['ec']['x'], $details['ec']['y'])) {
              throw new RuntimeException('Public key does not contain EC coordinates.');
          }

          return ['x' => $details['ec']['x'], 'y' => $details['ec']['y']];
      }

      private function base64urlEncode(string $raw): string
      {
          return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
      }
  }
  ```

  ## C. `src/Service/OAuth/TokenEncryptionService.php` を作成

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Service\OAuth;

  use RuntimeException;

  /**
   * AES-256-GCM authenticated encryption for OAuth access/refresh tokens (AUTH-07).
   *
   * Format (CONTEXT D-15): base64url( IV(12) || ciphertext || tag(16) ).
   * Key material: env('TOKEN_ENC_KEY') as 64-hex-char string (32 bytes after hex2bin).
   */
  final class TokenEncryptionService
  {
      private const CIPHER   = 'aes-256-gcm';
      private const IV_LEN   = 12;
      private const TAG_LEN  = 16;

      public function encrypt(string $plaintext): string
      {
          $key = $this->getKey();
          $iv  = random_bytes(self::IV_LEN);
          $tag = '';
          $ciphertext = openssl_encrypt(
              $plaintext,
              self::CIPHER,
              $key,
              OPENSSL_RAW_DATA,
              $iv,
              $tag,
              '',
              self::TAG_LEN
          );
          if ($ciphertext === false) {
              throw new RuntimeException('Token encryption failed.');
          }

          return $this->base64urlEncode($iv . $ciphertext . $tag);
      }

      public function decrypt(string $encoded): string
      {
          $raw = $this->base64urlDecode($encoded);
          if (strlen($raw) < self::IV_LEN + self::TAG_LEN + 1) {
              throw new RuntimeException('Token decryption failed.');
          }
          $iv  = substr($raw, 0, self::IV_LEN);
          $tag = substr($raw, -self::TAG_LEN);
          $ct  = substr($raw, self::IV_LEN, strlen($raw) - self::IV_LEN - self::TAG_LEN);

          $plaintext = openssl_decrypt(
              $ct,
              self::CIPHER,
              $this->getKey(),
              OPENSSL_RAW_DATA,
              $iv,
              $tag
          );
          if ($plaintext === false) {
              throw new RuntimeException('Token decryption failed.');
          }

          return $plaintext;
      }

      private function getKey(): string
      {
          $hex = (string)env('TOKEN_ENC_KEY', '');
          if ($hex === '' || strlen($hex) !== 64 || !ctype_xdigit($hex)) {
              throw new RuntimeException('TOKEN_ENC_KEY env var must be 64 hex characters (32 bytes).');
          }

          return (string)hex2bin($hex);
      }

      private function base64urlEncode(string $raw): string
      {
          return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
      }

      private function base64urlDecode(string $encoded): string
      {
          $padded = str_pad($encoded, strlen($encoded) + (4 - strlen($encoded) % 4) % 4, '=');
          $decoded = base64_decode(strtr($padded, '-_', '+/'), true);
          if ($decoded === false) {
              throw new RuntimeException('Token decryption failed.');
          }

          return $decoded;
      }
  }
  ```

  ## D. `tests/TestCase/Service/OAuth/KeyManagerTest.php` を作成

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Test\TestCase\Service\OAuth;

  use App\Service\OAuth\KeyManager;
  use Cake\TestSuite\TestCase;

  class KeyManagerTest extends TestCase
  {
      private KeyManager $km;

      protected function setUp(): void
      {
          parent::setUp();
          // Ensure OAUTH_KID is set so getPublicJwk() returns a deterministic kid.
          putenv('OAUTH_KID=test-kid-1');
          $_ENV['OAUTH_KID'] = 'test-kid-1';
          $this->km = new KeyManager(
              TESTS . 'Fixture' . DS . 'keys' . DS . 'private.key',
              TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key'
          );
      }

      public function testGetPublicJwkReturnsEs256Structure(): void
      {
          $jwk = $this->km->getPublicJwk();
          $this->assertSame('EC', $jwk['kty']);
          $this->assertSame('P-256', $jwk['crv']);
          $this->assertSame('ES256', $jwk['alg']);
          $this->assertSame('sig', $jwk['use']);
          $this->assertSame('test-kid-1', $jwk['kid']);
          $this->assertIsString($jwk['x']);
          $this->assertIsString($jwk['y']);
      }

      public function testJwkCoordinatesAre32BytesWhenBase64UrlDecoded(): void
      {
          $jwk = $this->km->getPublicJwk();
          $xRaw = base64_decode(strtr($jwk['x'] . str_repeat('=', (4 - strlen($jwk['x']) % 4) % 4), '-_', '+/'));
          $yRaw = base64_decode(strtr($jwk['y'] . str_repeat('=', (4 - strlen($jwk['y']) % 4) % 4), '-_', '+/'));
          $this->assertSame(32, strlen($xRaw), 'EC P-256 x coordinate must be 32 bytes.');
          $this->assertSame(32, strlen($yRaw), 'EC P-256 y coordinate must be 32 bytes.');
      }

      public function testGetPublicJwkForDpopOmitsKidUseAlg(): void
      {
          $dpopJwk = $this->km->getPublicJwkForDpop();
          $this->assertArrayHasKey('kty', $dpopJwk);
          $this->assertArrayHasKey('crv', $dpopJwk);
          $this->assertArrayHasKey('x', $dpopJwk);
          $this->assertArrayHasKey('y', $dpopJwk);
          $this->assertArrayNotHasKey('kid', $dpopJwk);
          $this->assertArrayNotHasKey('use', $dpopJwk);
          $this->assertArrayNotHasKey('alg', $dpopJwk);
      }

      public function testGetPrivateKeyReturnsUsableOpenSslKey(): void
      {
          $key = $this->km->getPrivateKey();
          $this->assertInstanceOf(\OpenSSLAsymmetricKey::class, $key);
          // Attempt to sign — if it works end-to-end, it's a valid ES256 private key.
          $sig = '';
          $ok  = openssl_sign('hello', $sig, $key, OPENSSL_ALGO_SHA256);
          $this->assertTrue($ok);
          $this->assertNotEmpty($sig);
      }

      public function testMissingPrivateKeyThrowsRuntime(): void
      {
          $km = new KeyManager('/nonexistent/private.key', TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key');
          $this->expectException(\RuntimeException::class);
          $km->getPrivateKey();
      }
  }
  ```

  ## E. `tests/TestCase/Service/OAuth/TokenEncryptionServiceTest.php` を作成

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Test\TestCase\Service\OAuth;

  use App\Service\OAuth\TokenEncryptionService;
  use Cake\TestSuite\TestCase;

  class TokenEncryptionServiceTest extends TestCase
  {
      private TokenEncryptionService $svc;

      protected function setUp(): void
      {
          parent::setUp();
          // Deterministic 32-byte hex test key (64 hex chars).
          $testKey = str_repeat('ab', 32);
          putenv('TOKEN_ENC_KEY=' . $testKey);
          $_ENV['TOKEN_ENC_KEY'] = $testKey;
          $this->svc = new TokenEncryptionService();
      }

      public function testRoundTripPreservesPlaintext(): void
      {
          $original = 'bsky-access-token-abc123';
          $encoded  = $this->svc->encrypt($original);
          $this->assertSame($original, $this->svc->decrypt($encoded));
      }

      public function testTwoEncryptionsOfSameInputDiffer(): void
      {
          $a = $this->svc->encrypt('same-input');
          $b = $this->svc->encrypt('same-input');
          $this->assertNotSame($a, $b, 'Distinct IVs MUST produce distinct ciphertexts (T-02-02-07).');
      }

      public function testEncryptedOutputLengthMatchesIvCtTagFormat(): void
      {
          $pt = 'hello'; // 5 bytes
          $encoded = $this->svc->encrypt($pt);
          $padded = str_pad($encoded, strlen($encoded) + (4 - strlen($encoded) % 4) % 4, '=');
          $raw = base64_decode(strtr($padded, '-_', '+/'));
          // Expected length: 12 (IV) + 5 (CT same len as PT in GCM) + 16 (TAG) = 33.
          $this->assertSame(12 + 5 + 16, strlen($raw));
      }

      public function testDecryptTamperedCiphertextThrows(): void
      {
          $encoded = $this->svc->encrypt('plaintext');
          // Flip one byte in the middle (ciphertext region).
          $padded = str_pad($encoded, strlen($encoded) + (4 - strlen($encoded) % 4) % 4, '=');
          $raw = base64_decode(strtr($padded, '-_', '+/'));
          $raw[15] = chr(ord($raw[15]) ^ 0xFF);
          $tampered = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

          $this->expectException(\RuntimeException::class);
          $this->expectExceptionMessage('Token decryption failed');
          $this->svc->decrypt($tampered);
      }

      public function testDecryptWithWrongKeyThrows(): void
      {
          $encoded = $this->svc->encrypt('plaintext');
          // Swap the key for decryption.
          $otherKey = str_repeat('cd', 32);
          putenv('TOKEN_ENC_KEY=' . $otherKey);
          $_ENV['TOKEN_ENC_KEY'] = $otherKey;
          $this->expectException(\RuntimeException::class);
          $this->svc->decrypt($encoded);
      }

      public function testEmptyKeyThrowsOnEncrypt(): void
      {
          putenv('TOKEN_ENC_KEY=');
          $_ENV['TOKEN_ENC_KEY'] = '';
          $this->expectException(\RuntimeException::class);
          $this->expectExceptionMessage('TOKEN_ENC_KEY');
          (new TokenEncryptionService())->encrypt('x');
      }
  }
  ```

  ## F. Run tests

  ```bash
  cd /home/claude/projects/tamabox && composer test -- --filter 'KeyManagerTest|TokenEncryptionServiceTest' --testdox
  ```

  Expected: all tests pass. If any fail, fix source (service classes) not the tests — tests are specification, not suggestion.

  ## G. Lint/static checks

  - `composer phpcs src/Service/OAuth/ tests/TestCase/Service/OAuth/` (or just full `composer phpcs` since whitelist is easier)
  - `composer phpstan` — must pass level 8 on new src/ files

  注意: KeyManager 自体は OAuthProviderInterface を implement しない (interface は Bluesky 固有の PAR/token/profile を定義しており、KeyManager は Bluesky と他プロバイダで共有されるユーティリティのため)。
  </action>

  <verify>
    <automated>cd /home/claude/projects/tamabox && test -f tests/Fixture/keys/private.key && test -f tests/Fixture/keys/public.key && php -l src/Service/OAuth/KeyManager.php 2>&1 | grep -q 'No syntax errors' && php -l src/Service/OAuth/TokenEncryptionService.php 2>&1 | grep -q 'No syntax errors' && php -l tests/TestCase/Service/OAuth/KeyManagerTest.php 2>&1 | grep -q 'No syntax errors' && php -l tests/TestCase/Service/OAuth/TokenEncryptionServiceTest.php 2>&1 | grep -q 'No syntax errors' && grep -q 'openssl_pkey_get_details' src/Service/OAuth/KeyManager.php && grep -q 'aes-256-gcm' src/Service/OAuth/TokenEncryptionService.php && grep -q 'random_bytes(12)' src/Service/OAuth/TokenEncryptionService.php && vendor/bin/phpunit --filter 'KeyManagerTest|TokenEncryptionServiceTest' --no-coverage 2>&1 | tail -5 | grep -qE 'OK \([0-9]+ tests' && composer phpstan 2>&1 | grep -q '\[OK\] No errors' && echo VERIFY_OK</automated>
  </verify>

  <acceptance_criteria>
    - `test -f src/Service/OAuth/KeyManager.php && test -f src/Service/OAuth/TokenEncryptionService.php` exits 0
    - `php -l` clean on both source files
    - `grep -c 'interface OAuthProviderInterface' src/Service/OAuth/KeyManager.php` = 0 (KeyManager does NOT implement the interface — utility, not provider)
    - KeyManager: `grep -c 'openssl_pkey_get_details' src/Service/OAuth/KeyManager.php` ≥ 1
    - KeyManager: `grep -c 'base64urlEncode' src/Service/OAuth/KeyManager.php` ≥ 1
    - TokenEncryptionService: `grep -c "'aes-256-gcm'" src/Service/OAuth/TokenEncryptionService.php` ≥ 1
    - TokenEncryptionService: `grep -c 'random_bytes(12)' src/Service/OAuth/TokenEncryptionService.php` = 1 (IV generation)
    - TokenEncryptionService: `grep -c 'OPENSSL_RAW_DATA' src/Service/OAuth/TokenEncryptionService.php` ≥ 2 (encrypt + decrypt)
    - `test -f tests/Fixture/keys/private.key && test -f tests/Fixture/keys/public.key` exits 0
    - `openssl ec -in tests/Fixture/keys/private.key -noout -text 2>&1 | grep -q 'NIST CURVE: P-256'` exits 0
    - `vendor/bin/phpunit --filter KeyManagerTest --no-coverage` exits 0, reports ≥ 5 tests
    - `vendor/bin/phpunit --filter TokenEncryptionServiceTest --no-coverage` exits 0, reports ≥ 6 tests
    - `composer phpstan` exits 0 ([OK] No errors)
    - `composer phpcs src/Service/OAuth/ tests/TestCase/Service/OAuth/ 2>&1 | tail -1` does NOT contain "FOUND" / "ERROR"
  </acceptance_criteria>

  <done>
    KeyManager + TokenEncryptionService 実装 + 2 unit tests すべて green、test-fixture 鍵ペア配置済み、phpcs/phpstan/phpunit すべて通過。DpopService + ClientJwtService (次タスク) が KeyManager を DI 可能な状態。
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: DpopService + ClientJwtService + 2 unit tests (JWT 構造 + openssl_verify 署名検証)</name>
  <files>src/Service/OAuth/Bluesky/DpopService.php, src/Service/OAuth/Bluesky/ClientJwtService.php, tests/TestCase/Service/OAuth/Bluesky/DpopServiceTest.php, tests/TestCase/Service/OAuth/Bluesky/ClientJwtServiceTest.php</files>

  <behavior>
    - DpopService::createProof(htm, htu) returns a 3-part JWT (header.payload.signature) with: header.typ='dpop+jwt', header.alg='ES256', header.jwk.{kty,crv,x,y} present, payload.htm=arg, payload.htu=arg, payload.iat=now±1s, payload.exp=iat+60, payload.jti distinct each call
    - DpopService::createProof with $accessToken arg populates payload.ath = base64url(sha256(access_token raw))
    - DpopService::createProof with $nonce arg populates payload.nonce=arg
    - DpopService signature validates via openssl_verify($signing_input, $der_signature, $public_key, SHA256) after converting the R||S 64-byte raw signature back to DER for verification
    - ClientJwtService::createAssertion(audience) returns 3-part JWT with: header.alg='ES256', header.kid=env('OAUTH_KID'), payload.iss=Configure::read('Bluesky.client_id'), payload.sub=same, payload.aud=arg, payload.jti random, payload.iat=now, payload.exp=iat+60
    - ClientJwtService signature validates via openssl_verify
    - Both services are stateless — calling createProof twice yields different jti and iat (or same iat within 1s, but different jti)
  </behavior>

  <read_first>
    - /home/claude/projects/tamabox/.planning/references/altotoo/BlueskyOauthComponent.php (L37-70 derToRawSignature / L75-115 createClientAssertion / L120-156 createDpopProof — VERBATIM pattern donor)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-PATTERNS.md §`DpopService.php` / §`ClientJwtService.php`
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-RESEARCH.md §DPoP Implementation in PHP (DER→Raw detail), §Common Pitfalls P1/P2/P3/P4
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-CONTEXT.md D-11 / D-12 / D-13
    - /home/claude/projects/tamabox/src/Service/OAuth/KeyManager.php (Task 1 で生成済 — constructor injection 対象)
    - /home/claude/projects/tamabox/tests/TestCase/Service/OAuth/KeyManagerTest.php (setUp パターン参考)
  </read_first>

  <action>

  ## A. `src/Service/OAuth/Bluesky/DpopService.php` を作成

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Service\OAuth\Bluesky;

  use App\Service\OAuth\KeyManager;
  use RuntimeException;

  /**
   * DPoP Proof JWT generator (RFC 9449, CONTEXT D-12/D-13).
   *
   * Header: typ=dpop+jwt, alg=ES256, jwk={kty,crv,x,y} (minimal, no kid/use/alg).
   * Payload: htm, htu, iat, exp=iat+60, jti, ath? (if accessToken provided), nonce? (if nonce provided).
   * Signature: ES256 via KeyManager->getPrivateKey(); DER openssl_sign output is converted to
   * 64-byte R||S raw format via derToRawSignature (altotoo L37-70, CONTEXT D-11).
   */
  final class DpopService
  {
      public function __construct(private readonly KeyManager $keyManager)
      {
      }

      /**
       * @param string $htm HTTP method (must be uppercase — POST, GET).
       * @param string $htu Endpoint URL (no query string, no fragment).
       * @param string|null $accessToken When set, adds `ath = base64url(sha256(accessToken))` claim (D-13).
       * @param string|null $nonce When set (AS retry path, D-10), adds `nonce` claim.
       * @return string Compact JWT (3 parts, dot-separated).
       */
      public function createProof(
          string $htm,
          string $htu,
          ?string $accessToken = null,
          ?string $nonce = null
      ): string {
          $header = [
              'typ' => 'dpop+jwt',
              'alg' => 'ES256',
              'jwk' => $this->keyManager->getPublicJwkForDpop(),
          ];
          $now = time();
          $payload = [
              'htm' => $htm,
              'htu' => $htu,
              'iat' => $now,
              'exp' => $now + 60,
              'jti' => $this->base64urlEncode(random_bytes(32)),
          ];
          if ($accessToken !== null) {
              $payload['ath'] = $this->base64urlEncode(hash('sha256', $accessToken, true));
          }
          if ($nonce !== null) {
              $payload['nonce'] = $nonce;
          }

          return $this->signEs256($header, $payload);
      }

      private function signEs256(array $header, array $payload): string
      {
          $headerB64  = $this->base64urlEncode((string)json_encode($header, JSON_UNESCAPED_SLASHES));
          $payloadB64 = $this->base64urlEncode((string)json_encode($payload, JSON_UNESCAPED_SLASHES));
          $signingInput = $headerB64 . '.' . $payloadB64;

          $der = '';
          $ok  = openssl_sign($signingInput, $der, $this->keyManager->getPrivateKey(), OPENSSL_ALGO_SHA256);
          if (!$ok) {
              throw new RuntimeException('ES256 sign failed in DpopService.');
          }
          $raw = $this->derToRawSignature($der);

          return $signingInput . '.' . $this->base64urlEncode($raw);
      }

      /**
       * Convert DER-encoded ECDSA signature (0x30 ... 0x02 R 0x02 S) to JWT R||S raw 64 bytes.
       * Altotoo BlueskyOauthComponent.php L37-70 — VERBATIM per CONTEXT D-11.
       */
      private function derToRawSignature(string $der): string
      {
          $pos = 2; // skip 0x30 sequence tag + total-length byte
          $rLen = ord($der[$pos + 1]);
          $r = substr($der, $pos + 2, $rLen);
          $sLen = ord($der[$pos + 2 + $rLen + 1]);
          $s = substr($der, $pos + 2 + $rLen + 2, $sLen);
          // Strip leading 0x00 DER padding.
          if (strlen($r) > 32 && ord($r[0]) === 0) {
              $r = substr($r, 1);
          }
          if (strlen($s) > 32 && ord($s[0]) === 0) {
              $s = substr($s, 1);
          }

          return str_pad($r, 32, chr(0), STR_PAD_LEFT) . str_pad($s, 32, chr(0), STR_PAD_LEFT);
      }

      private function base64urlEncode(string $raw): string
      {
          return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
      }
  }
  ```

  ## B. `src/Service/OAuth/Bluesky/ClientJwtService.php` を作成

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Service\OAuth\Bluesky;

  use App\Service\OAuth\KeyManager;
  use Cake\Core\Configure;
  use RuntimeException;

  /**
   * private_key_jwt client_assertion generator (RFC 7523, CONTEXT D-16).
   *
   * Header: alg=ES256, kid=env('OAUTH_KID'). Not a DPoP JWT — no typ, no jwk.
   * Payload: iss=sub=Configure::read('Bluesky.client_id'), aud=<caller arg>, jti, iat, exp=iat+60.
   */
  final class ClientJwtService
  {
      public function __construct(private readonly KeyManager $keyManager)
      {
      }

      /**
       * @param string $audience Either par_endpoint or token_endpoint per caller context.
       */
      public function createAssertion(string $audience): string
      {
          $clientId = (string)Configure::read('Bluesky.client_id');
          if ($clientId === '') {
              throw new RuntimeException('Bluesky.client_id is not configured.');
          }

          $header = [
              'alg' => 'ES256',
              'kid' => (string)env('OAUTH_KID', 'ssr-box-key-1'),
          ];
          $now = time();
          $payload = [
              'iss' => $clientId,
              'sub' => $clientId,
              'aud' => $audience,
              'jti' => $this->base64urlEncode(random_bytes(32)),
              'iat' => $now,
              'exp' => $now + 60,
          ];

          return $this->signEs256($header, $payload);
      }

      private function signEs256(array $header, array $payload): string
      {
          $headerB64  = $this->base64urlEncode((string)json_encode($header, JSON_UNESCAPED_SLASHES));
          $payloadB64 = $this->base64urlEncode((string)json_encode($payload, JSON_UNESCAPED_SLASHES));
          $signingInput = $headerB64 . '.' . $payloadB64;

          $der = '';
          $ok  = openssl_sign($signingInput, $der, $this->keyManager->getPrivateKey(), OPENSSL_ALGO_SHA256);
          if (!$ok) {
              throw new RuntimeException('ES256 sign failed in ClientJwtService.');
          }

          return $signingInput . '.' . $this->base64urlEncode($this->derToRawSignature($der));
      }

      private function derToRawSignature(string $der): string
      {
          $pos = 2;
          $rLen = ord($der[$pos + 1]);
          $r = substr($der, $pos + 2, $rLen);
          $sLen = ord($der[$pos + 2 + $rLen + 1]);
          $s = substr($der, $pos + 2 + $rLen + 2, $sLen);
          if (strlen($r) > 32 && ord($r[0]) === 0) {
              $r = substr($r, 1);
          }
          if (strlen($s) > 32 && ord($s[0]) === 0) {
              $s = substr($s, 1);
          }

          return str_pad($r, 32, chr(0), STR_PAD_LEFT) . str_pad($s, 32, chr(0), STR_PAD_LEFT);
      }

      private function base64urlEncode(string $raw): string
      {
          return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
      }
  }
  ```

  注意: `derToRawSignature` と `base64urlEncode` は DpopService と ClientJwtService で重複する。これは意図的:
  - altotoo 踏襲で per-class self-contained にする (外部 trait 依存なし — レビュー時に「この関数は D-11 の DER→Raw か」が1ファイル内で確認できる)
  - 将来 trait 化 (`Es256JwtSignerTrait`) することは可能だが MVP scope 外

  ## C. `tests/TestCase/Service/OAuth/Bluesky/DpopServiceTest.php` を作成

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Test\TestCase\Service\OAuth\Bluesky;

  use App\Service\OAuth\Bluesky\DpopService;
  use App\Service\OAuth\KeyManager;
  use Cake\TestSuite\TestCase;

  class DpopServiceTest extends TestCase
  {
      private DpopService $svc;
      private KeyManager $km;

      protected function setUp(): void
      {
          parent::setUp();
          putenv('OAUTH_KID=test-kid-1');
          $_ENV['OAUTH_KID'] = 'test-kid-1';
          $this->km  = new KeyManager(
              TESTS . 'Fixture' . DS . 'keys' . DS . 'private.key',
              TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key'
          );
          $this->svc = new DpopService($this->km);
      }

      public function testCreateProofHeaderIsDpopJwt(): void
      {
          $jwt = $this->svc->createProof('POST', 'https://bsky.social/oauth/par');
          $parts = explode('.', $jwt);
          $this->assertCount(3, $parts);
          $header = json_decode($this->b64udec($parts[0]), true);
          $this->assertSame('dpop+jwt', $header['typ']);
          $this->assertSame('ES256', $header['alg']);
      }

      public function testCreateProofHeaderContainsJwk(): void
      {
          $jwt = $this->svc->createProof('POST', 'https://bsky.social/oauth/par');
          $parts  = explode('.', $jwt);
          $header = json_decode($this->b64udec($parts[0]), true);
          $this->assertIsArray($header['jwk']);
          $this->assertSame('EC', $header['jwk']['kty']);
          $this->assertSame('P-256', $header['jwk']['crv']);
          $this->assertArrayHasKey('x', $header['jwk']);
          $this->assertArrayHasKey('y', $header['jwk']);
          // T-02-02-02 mitigation: DPoP mini-jwk must NOT include kid/use/alg.
          $this->assertArrayNotHasKey('kid', $header['jwk']);
      }

      public function testPayloadContainsHtmHtuIatExpJti(): void
      {
          $jwt = $this->svc->createProof('POST', 'https://bsky.social/oauth/par');
          $parts = explode('.', $jwt);
          $payload = json_decode($this->b64udec($parts[1]), true);
          $this->assertSame('POST', $payload['htm']);
          $this->assertSame('https://bsky.social/oauth/par', $payload['htu']);
          $this->assertIsInt($payload['iat']);
          $this->assertIsInt($payload['exp']);
          $this->assertSame(60, $payload['exp'] - $payload['iat']);
          $this->assertNotEmpty($payload['jti']);
      }

      public function testAthClaimAddedWhenAccessTokenProvided(): void
      {
          $jwt = $this->svc->createProof('GET', 'https://pds.example/xrpc/x', 'my_token');
          $parts   = explode('.', $jwt);
          $payload = json_decode($this->b64udec($parts[1]), true);
          $this->assertArrayHasKey('ath', $payload);
          $expected = rtrim(strtr(base64_encode(hash('sha256', 'my_token', true)), '+/', '-_'), '=');
          $this->assertSame($expected, $payload['ath']);
      }

      public function testNonceClaimAddedWhenNonceProvided(): void
      {
          $jwt = $this->svc->createProof('POST', 'https://bsky.social/oauth/par', null, 'abc-nonce-xyz');
          $payload = json_decode($this->b64udec(explode('.', $jwt)[1]), true);
          $this->assertSame('abc-nonce-xyz', $payload['nonce']);
      }

      public function testAthNotPresentWhenAccessTokenNull(): void
      {
          $jwt = $this->svc->createProof('POST', 'https://bsky.social/oauth/par');
          $payload = json_decode($this->b64udec(explode('.', $jwt)[1]), true);
          $this->assertArrayNotHasKey('ath', $payload);
      }

      public function testEveryProofHasUniqueJti(): void
      {
          $jtis = [];
          for ($i = 0; $i < 10; $i++) {
              $payload = json_decode($this->b64udec(explode('.', $this->svc->createProof('POST', 'https://x/y'))[1]), true);
              $jtis[] = $payload['jti'];
          }
          $this->assertCount(10, array_unique($jtis), 'Every DPoP proof MUST have a distinct jti (T-02-02-04).');
      }

      public function testSignatureVerifiesAgainstPublicKey(): void
      {
          $jwt = $this->svc->createProof('POST', 'https://bsky.social/oauth/par');
          [$h, $p, $sig] = explode('.', $jwt);
          $signingInput = $h . '.' . $p;
          $rawSig = $this->b64udec($sig);
          $this->assertSame(64, strlen($rawSig), 'ES256 raw signature is 64 bytes.');
          // Convert R||S back to DER for openssl_verify.
          $r = ltrim(substr($rawSig, 0, 32), chr(0));
          $s = ltrim(substr($rawSig, 32, 32), chr(0));
          if (ord($r[0]) >= 0x80) { $r = chr(0) . $r; }
          if (ord($s[0]) >= 0x80) { $s = chr(0) . $s; }
          $der = chr(0x30)
               . chr(2 + strlen($r) + 2 + strlen($s))
               . chr(0x02) . chr(strlen($r)) . $r
               . chr(0x02) . chr(strlen($s)) . $s;

          $pubPem = (string)file_get_contents(TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key');
          $pub = openssl_pkey_get_public($pubPem);
          $ok  = openssl_verify($signingInput, $der, $pub, OPENSSL_ALGO_SHA256);
          $this->assertSame(1, $ok, 'DPoP proof signature must validate against the public key (T-02-02-01).');
      }

      private function b64udec(string $s): string
      {
          $padded = str_pad($s, strlen($s) + (4 - strlen($s) % 4) % 4, '=');
          return (string)base64_decode(strtr($padded, '-_', '+/'));
      }
  }
  ```

  ## D. `tests/TestCase/Service/OAuth/Bluesky/ClientJwtServiceTest.php` を作成

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Test\TestCase\Service\OAuth\Bluesky;

  use App\Service\OAuth\Bluesky\ClientJwtService;
  use App\Service\OAuth\KeyManager;
  use Cake\Core\Configure;
  use Cake\TestSuite\TestCase;

  class ClientJwtServiceTest extends TestCase
  {
      private ClientJwtService $svc;

      protected function setUp(): void
      {
          parent::setUp();
          putenv('OAUTH_KID=test-kid-1');
          $_ENV['OAUTH_KID'] = 'test-kid-1';
          Configure::write('Bluesky.client_id', 'https://tamabox.emomie.com/oauth/client-metadata.json');
          $km = new KeyManager(
              TESTS . 'Fixture' . DS . 'keys' . DS . 'private.key',
              TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key'
          );
          $this->svc = new ClientJwtService($km);
      }

      public function testAssertionHeaderHasKid(): void
      {
          $jwt = $this->svc->createAssertion('https://bsky.social/oauth/par');
          $header = json_decode($this->b64udec(explode('.', $jwt)[0]), true);
          $this->assertSame('ES256', $header['alg']);
          $this->assertSame('test-kid-1', $header['kid']);
          // T-02-02-09: client_assertion MUST NOT include DPoP-style jwk
          $this->assertArrayNotHasKey('jwk', $header);
          $this->assertArrayNotHasKey('typ', $header);
      }

      public function testAssertionPayloadIssSubEqualsClientId(): void
      {
          $jwt = $this->svc->createAssertion('https://bsky.social/oauth/par');
          $payload = json_decode($this->b64udec(explode('.', $jwt)[1]), true);
          $this->assertSame('https://tamabox.emomie.com/oauth/client-metadata.json', $payload['iss']);
          $this->assertSame('https://tamabox.emomie.com/oauth/client-metadata.json', $payload['sub']);
      }

      public function testAssertionAudMatchesArgument(): void
      {
          $jwt = $this->svc->createAssertion('https://bsky.social/oauth/token');
          $payload = json_decode($this->b64udec(explode('.', $jwt)[1]), true);
          $this->assertSame('https://bsky.social/oauth/token', $payload['aud']);
      }

      public function testAssertionHasJtiAndExpiry(): void
      {
          $jwt = $this->svc->createAssertion('https://bsky.social/oauth/par');
          $payload = json_decode($this->b64udec(explode('.', $jwt)[1]), true);
          $this->assertNotEmpty($payload['jti']);
          $this->assertSame(60, $payload['exp'] - $payload['iat']);
      }

      public function testSignatureVerifiesAgainstPublicKey(): void
      {
          $jwt = $this->svc->createAssertion('https://bsky.social/oauth/par');
          [$h, $p, $sig] = explode('.', $jwt);
          $rawSig = $this->b64udec($sig);
          $this->assertSame(64, strlen($rawSig));
          $r = ltrim(substr($rawSig, 0, 32), chr(0));
          $s = ltrim(substr($rawSig, 32, 32), chr(0));
          if (ord($r[0]) >= 0x80) { $r = chr(0) . $r; }
          if (ord($s[0]) >= 0x80) { $s = chr(0) . $s; }
          $der = chr(0x30) . chr(2 + strlen($r) + 2 + strlen($s)) . chr(0x02) . chr(strlen($r)) . $r . chr(0x02) . chr(strlen($s)) . $s;
          $pub = openssl_pkey_get_public((string)file_get_contents(TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key'));
          $this->assertSame(1, openssl_verify($h . '.' . $p, $der, $pub, OPENSSL_ALGO_SHA256));
      }

      public function testEmptyClientIdThrows(): void
      {
          Configure::write('Bluesky.client_id', '');
          $this->expectException(\RuntimeException::class);
          $this->expectExceptionMessage('client_id');
          $this->svc->createAssertion('https://bsky.social/oauth/par');
      }

      private function b64udec(string $s): string
      {
          $padded = str_pad($s, strlen($s) + (4 - strlen($s) % 4) % 4, '=');
          return (string)base64_decode(strtr($padded, '-_', '+/'));
      }
  }
  ```

  ## E. Run new tests

  ```bash
  cd /home/claude/projects/tamabox && composer test -- --filter 'DpopServiceTest|ClientJwtServiceTest' --testdox
  ```

  Expected: ≥ 13 tests (DpopService 8 + ClientJwtService 6 — ±1 acceptable), all green.

  ## F. Full suite + lint

  ```bash
  composer phpcs
  composer phpstan
  composer test
  ```

  すべて exit 0 であること。Phase 1 の 17 tests に加えて新規 ~21 tests (Task 1+2) が合流した状態。
  </action>

  <verify>
    <automated>cd /home/claude/projects/tamabox && php -l src/Service/OAuth/Bluesky/DpopService.php 2>&1 | grep -q 'No syntax errors' && php -l src/Service/OAuth/Bluesky/ClientJwtService.php 2>&1 | grep -q 'No syntax errors' && php -l tests/TestCase/Service/OAuth/Bluesky/DpopServiceTest.php 2>&1 | grep -q 'No syntax errors' && php -l tests/TestCase/Service/OAuth/Bluesky/ClientJwtServiceTest.php 2>&1 | grep -q 'No syntax errors' && grep -q "'typ' => 'dpop+jwt'" src/Service/OAuth/Bluesky/DpopService.php && grep -q 'derToRawSignature' src/Service/OAuth/Bluesky/DpopService.php && grep -q 'derToRawSignature' src/Service/OAuth/Bluesky/ClientJwtService.php && grep -q 'random_bytes(32)' src/Service/OAuth/Bluesky/DpopService.php && grep -q "hash('sha256'" src/Service/OAuth/Bluesky/DpopService.php && vendor/bin/phpunit --filter 'DpopServiceTest|ClientJwtServiceTest' --no-coverage 2>&1 | tail -3 | grep -qE 'OK \([0-9]+ tests' && composer phpstan 2>&1 | grep -q '\[OK\] No errors' && composer test 2>&1 | tail -3 | grep -qE 'OK \(|Tests: [0-9]+' && echo VERIFY_OK</automated>
  </verify>

  <acceptance_criteria>
    - `test -f src/Service/OAuth/Bluesky/DpopService.php && test -f src/Service/OAuth/Bluesky/ClientJwtService.php` exits 0
    - `php -l` clean on all 4 files (2 service + 2 test)
    - DpopService structural: `grep -c "'typ' => 'dpop+jwt'" src/Service/OAuth/Bluesky/DpopService.php` = 1
    - DpopService: `grep -c 'derToRawSignature' src/Service/OAuth/Bluesky/DpopService.php` ≥ 2 (declare + call)
    - DpopService: `grep -c 'random_bytes(32)' src/Service/OAuth/Bluesky/DpopService.php` = 1 (jti generation)
    - DpopService: `grep -c "hash('sha256'" src/Service/OAuth/Bluesky/DpopService.php` = 1 (ath claim)
    - ClientJwtService structural: `grep -c 'derToRawSignature' src/Service/OAuth/Bluesky/ClientJwtService.php` ≥ 2
    - ClientJwtService: `grep -c "Configure::read\\('Bluesky.client_id'\\)" src/Service/OAuth/Bluesky/ClientJwtService.php` ≥ 1
    - ClientJwtService: `grep -c "OAUTH_KID" src/Service/OAuth/Bluesky/ClientJwtService.php` = 1
    - `vendor/bin/phpunit --filter DpopServiceTest --no-coverage` exits 0, tests ≥ 7
    - `vendor/bin/phpunit --filter ClientJwtServiceTest --no-coverage` exits 0, tests ≥ 5
    - Critical signature test pass: `vendor/bin/phpunit --filter 'testSignatureVerifiesAgainstPublicKey' --no-coverage` passes for both services
    - `composer phpstan` exits 0
    - `composer phpcs` exits 0 (new tests + service classes phpcs clean)
    - `composer test` exits 0 (Phase 1 baseline + all Plan 02-02 tests green)
  </acceptance_criteria>

  <done>
    DpopService + ClientJwtService 実装、DER→Raw 変換が altotoo 踏襲でテスト検証済み (openssl_verify で署名検証が通る)、合計 ~21 tests + Phase 1 17 tests 全 green、phpcs + phpstan level 8 通過。Plan 02-04 BlueskyOAuthClient が `new BlueskyOAuthClient(new DpopService($km), new ClientJwtService($km), new TokenEncryptionService(), ...)` で組み立てられる状態。
  </done>
</task>

</tasks>

<verification>
## Plan-level Verification

Run after both tasks complete:

1. **File inventory**:
   - `test -f src/Service/OAuth/KeyManager.php` exits 0
   - `test -f src/Service/OAuth/TokenEncryptionService.php` exits 0
   - `test -f src/Service/OAuth/Bluesky/DpopService.php` exits 0
   - `test -f src/Service/OAuth/Bluesky/ClientJwtService.php` exits 0
   - 4 test files under `tests/TestCase/Service/OAuth/` mirror structure
   - `test -f tests/Fixture/keys/private.key && test -f tests/Fixture/keys/public.key` exits 0

2. **No external deps added**: `git diff composer.json composer.lock` should be empty (Plan 02-02 adds zero new composer packages — all PHP builtin). If there's any diff, plan deviation.

3. **PSR-4 autoload**:
   ```bash
   php -r 'require "vendor/autoload.php"; foreach (["App\\Service\\OAuth\\KeyManager","App\\Service\\OAuth\\TokenEncryptionService","App\\Service\\OAuth\\Bluesky\\DpopService","App\\Service\\OAuth\\Bluesky\\ClientJwtService"] as $c) { exit(class_exists($c) ? 0 : 1); }'
   ```
   exits 0.

4. **No DB write / no HTTP**: `grep -rE '(Connection|ConnectionManager|getTableLocator|curl_init|http_build|file_get_contents.*http)' src/Service/OAuth/` should only return `file_get_contents` for local PEM files — zero HTTP/DB touch.

5. **Test suite**:
   - `composer test` exits 0
   - Full test count = Phase 1 baseline (17) + Plan 02-02 added (~21) = ≥ 38 (±3)
   - Zero failures, zero errors
   - `vendor/bin/phpunit tests/TestCase/Service/OAuth/ --no-coverage 2>&1 | grep -qE 'OK \([0-9]+ tests'`

6. **Lint/static**:
   - `composer phpcs` exit 0
   - `composer phpstan` exit 0 ([OK] No errors)

7. **Signature invariant** (Plan 02-04 prerequisite):
   ```
   # Assert that the JWT produced by DpopService validates via openssl_verify with the test public key
   vendor/bin/phpunit --filter testSignatureVerifiesAgainstPublicKey --no-coverage  # exits 0
   ```
   This single criterion blocks Plan 02-04 from proceeding with a silently-broken DER→Raw implementation.
</verification>

<success_criteria>
Plan 02-02 complete when:
- [ ] KeyManager + TokenEncryptionService + DpopService + ClientJwtService exist, pass phpcs / phpstan level 8
- [ ] 4 unit test files exist, each with ≥ 5 test cases
- [ ] `composer test` exits 0; new tests add to Phase 1 baseline
- [ ] Test-fixture EC P-256 keypair committed at `tests/Fixture/keys/`
- [ ] derToRawSignature (altotoo L37-70 verbatim) present in both DpopService and ClientJwtService
- [ ] Signature verification via openssl_verify passes (proves DER→Raw conversion is cryptographically correct)
- [ ] AES-GCM round-trip + tamper-detection + IV-uniqueness tests pass
- [ ] No new composer deps added
- [ ] No DB / HTTP access in these services (pure crypto layer)
</success_criteria>

<output>
After completion, create `.planning/phases/02-bluesky-oauth-identity/02-02-SUMMARY.md` with frontmatter (requirements_closed: AUTH-07 full, AUTH-08 partial — JWKS endpoint is Plan 02-03), commits log, per-task acceptance, deviations, handoff to Plan 02-04 (BlueskyOAuthClient DI contract), self-check.
</output>
