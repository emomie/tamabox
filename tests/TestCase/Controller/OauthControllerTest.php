<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * OauthController integration tests.
 *
 * Verifies:
 *   - /oauth/client-metadata.json returns 200 JSON with byte-exact client_id (D-16, Pitfall 5)
 *   - /oauth/jwks.json returns 200 JSON with exactly 1 EC P-256 key, no private scalar 'd' (T-02-03-04)
 *   - /oauth/callback returns 501 (stub locked in until Plan 02-04 replaces it)
 *
 * @uses \App\Controller\OauthController
 */
class OauthControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        // Deterministic kid for JWKS assertions.
        putenv('OAUTH_KID=test-kid-1');
        $_ENV['OAUTH_KID'] = 'test-kid-1';
        // Point KeyManager at the test fixture keys so tests do not depend on
        // config/keys/ existing in CI (production keys are gitignored).
        Configure::write('Bluesky.private_key_path', TESTS . 'Fixture' . DS . 'keys' . DS . 'private.key');
        Configure::write('Bluesky.public_key_path', TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key');
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
        $this->assertIsArray($body);
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
        $this->assertIsArray($body);
        $this->assertArrayNotHasKey('d', $body['keys'][0]);
    }

    public function testCallbackStubReturns501(): void
    {
        // Plan 02-03 ships a 501 stub so pre-Plan-02-04 hits are visibly broken.
        // Plan 02-04 MUST flip this to either 302 (success) or 302 to /?flash=error (failure).
        $this->get('/oauth/callback?code=x&state=y');
        $this->assertResponseCode(501);
    }
}
