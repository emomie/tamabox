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
    /**
     * @param string $privateKeyPath Absolute path to ES256 PEM private key. Empty string → default from Configure.
     * @param string $publicKeyPath Absolute path to ES256 PEM public key. Empty string → default from Configure.
     */
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
     * @return array<string, string> JWK with kty, crv, kid, use, alg, x, y.
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
            'x' => $this->base64urlEncode($coords['x']),
            'y' => $this->base64urlEncode($coords['y']),
        ];
    }

    /**
     * Return the minimal JWK form used in DPoP proof header (RFC 9449).
     * DPoP specifies kty/crv/x/y only; kid/use/alg would duplicate values already in header.
     *
     * @return array<string, string> JWK with kty, crv, x, y.
     */
    public function getPublicJwkForDpop(): array
    {
        $coords = $this->extractEcCoordinates();

        return [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => $this->base64urlEncode($coords['x']),
            'y' => $this->base64urlEncode($coords['y']),
        ];
    }

    /**
     * Return the raw OpenSSLAsymmetricKey handle for openssl_sign consumption.
     *
     * @return \OpenSSLAsymmetricKey
     * @throws \RuntimeException If the key file is absent or unreadable.
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
     * @return array<string, string> Raw bytes from openssl_pkey_get_details['ec']: x and y keys.
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
}
