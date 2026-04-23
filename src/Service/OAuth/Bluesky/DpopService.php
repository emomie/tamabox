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
    /**
     * @param \App\Service\OAuth\KeyManager $keyManager ES256 keypair provider (DI).
     */
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
            throw new RuntimeException('ES256 sign failed in DpopService.');
        }
        $raw = $this->derToRawSignature($der);

        return $signingInput . '.' . $this->base64urlEncode($raw);
    }

    /**
     * Convert DER-encoded ECDSA signature (0x30 ... 0x02 R 0x02 S) to JWT R||S raw 64 bytes.
     * Altotoo BlueskyOauthComponent.php L37-70 — VERBATIM per CONTEXT D-11.
     *
     * @param string $der DER-encoded ECDSA signature from openssl_sign.
     * @return string 64-byte R||S raw signature.
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
