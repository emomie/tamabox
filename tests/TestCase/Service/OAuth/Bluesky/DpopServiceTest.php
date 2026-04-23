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
        $this->km = new KeyManager(
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
        $parts = explode('.', $jwt);
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
        $parts = explode('.', $jwt);
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
        if (ord($r[0]) >= 0x80) {
            $r = chr(0) . $r;
        }
        if (ord($s[0]) >= 0x80) {
            $s = chr(0) . $s;
        }
        $der = chr(0x30)
             . chr(2 + strlen($r) + 2 + strlen($s))
             . chr(0x02) . chr(strlen($r)) . $r
             . chr(0x02) . chr(strlen($s)) . $s;

        $pubPem = (string)file_get_contents(TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key');
        $pub = openssl_pkey_get_public($pubPem);
        $ok = openssl_verify($signingInput, $der, $pub, OPENSSL_ALGO_SHA256);
        $this->assertSame(1, $ok, 'DPoP proof signature must validate against the public key (T-02-02-01).');
    }

    private function b64udec(string $s): string
    {
        $padded = str_pad($s, strlen($s) + (4 - strlen($s) % 4) % 4, '=');

        return (string)base64_decode(strtr($padded, '-_', '+/'));
    }
}
