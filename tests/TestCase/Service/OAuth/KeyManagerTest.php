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
        $ok = openssl_sign('hello', $sig, $key, OPENSSL_ALGO_SHA256);
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
