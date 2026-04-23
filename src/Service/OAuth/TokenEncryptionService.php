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
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN = 12;
    private const TAG_LEN = 16;

    /**
     * Encrypt plaintext under env('TOKEN_ENC_KEY') using AES-256-GCM.
     *
     * @param string $plaintext Raw token string (any length).
     * @return string base64url(IV(12) || ciphertext || tag(16)).
     * @throws \RuntimeException On openssl failure or missing / malformed key.
     */
    public function encrypt(string $plaintext): string
    {
        $key = $this->getKey();
        $iv = random_bytes(12);
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

    /**
     * Decrypt a base64url(IV || CT || TAG) blob under env('TOKEN_ENC_KEY').
     *
     * @param string $encoded Ciphertext produced by encrypt().
     * @return string Original plaintext.
     * @throws \RuntimeException On tamper (tag mismatch) or malformed input.
     */
    public function decrypt(string $encoded): string
    {
        $raw = $this->base64urlDecode($encoded);
        if (strlen($raw) < self::IV_LEN + self::TAG_LEN + 1) {
            throw new RuntimeException('Token decryption failed.');
        }
        $iv = substr($raw, 0, self::IV_LEN);
        $tag = substr($raw, -self::TAG_LEN);
        $ct = substr($raw, self::IV_LEN, strlen($raw) - self::IV_LEN - self::TAG_LEN);

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

    /**
     * Resolve the 32-byte AES key from env('TOKEN_ENC_KEY') (64 hex chars).
     *
     * @return string 32 raw bytes.
     * @throws \RuntimeException If the env var is absent, wrong length, or non-hex.
     */
    private function getKey(): string
    {
        $hex = (string)env('TOKEN_ENC_KEY', '');
        if ($hex === '' || strlen($hex) !== 64 || !ctype_xdigit($hex)) {
            throw new RuntimeException('TOKEN_ENC_KEY env var must be 64 hex characters (32 bytes).');
        }

        return (string)hex2bin($hex);
    }

    /**
     * RFC 4648 §5 base64url (no padding) encoder.
     *
     * @param string $raw Raw bytes to encode.
     * @return string base64url-encoded string, no `=` padding.
     */
    private function base64urlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * RFC 4648 §5 base64url (no padding) decoder. Throws on malformed input.
     *
     * @param string $encoded base64url-encoded input.
     * @return string Decoded raw bytes.
     * @throws \RuntimeException On non-base64 input.
     */
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
