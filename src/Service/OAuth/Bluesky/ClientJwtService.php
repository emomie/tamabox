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
    /**
     * @param \App\Service\OAuth\KeyManager $keyManager ES256 keypair provider (DI).
     */
    public function __construct(private readonly KeyManager $keyManager)
    {
    }

    /**
     * @param string $audience Either par_endpoint or token_endpoint per caller context.
     * @return string Compact JWT (3 parts, dot-separated).
     * @throws \RuntimeException If Bluesky.client_id is unset or sign fails.
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

    /**
     * Build compact JWT string (header.payload.signature) with ES256 signature.
     *
     * @param array<string, mixed> $header JOSE header claims.
     * @param array<string, mixed> $payload JWT claims set.
     * @return string Compact JWT (3 parts).
     * @throws \RuntimeException On openssl_sign failure.
     */
    private function signEs256(array $header, array $payload): string
    {
        $headerB64 = $this->base64urlEncode((string)json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadB64 = $this->base64urlEncode((string)json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signingInput = $headerB64 . '.' . $payloadB64;

        $der = '';
        $ok = openssl_sign($signingInput, $der, $this->keyManager->getPrivateKey(), OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new RuntimeException('ES256 sign failed in ClientJwtService.');
        }

        return $signingInput . '.' . $this->base64urlEncode($this->derToRawSignature($der));
    }

    /**
     * DER → R||S 64-byte raw conversion (altotoo L37-70, CONTEXT D-11).
     *
     * @param string $der DER-encoded ECDSA signature from openssl_sign.
     * @return string 64-byte R||S raw signature.
     */
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

    /**
     * RFC 4648 §5 base64url (no padding) encoder.
     *
     * @param string $raw Raw bytes to encode.
     * @return string base64url-encoded string.
     */
    private function base64urlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
