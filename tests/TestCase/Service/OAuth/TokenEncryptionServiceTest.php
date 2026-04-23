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
        $encoded = $this->svc->encrypt($original);
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
