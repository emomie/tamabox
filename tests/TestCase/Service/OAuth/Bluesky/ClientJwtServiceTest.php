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
        if (ord($r[0]) >= 0x80) {
            $r = chr(0) . $r;
        }
        if (ord($s[0]) >= 0x80) {
            $s = chr(0) . $s;
        }
        $der = chr(0x30) . chr(2 + strlen($r) + 2 + strlen($s))
             . chr(0x02) . chr(strlen($r)) . $r
             . chr(0x02) . chr(strlen($s)) . $s;
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
