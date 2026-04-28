<?php
declare(strict_types=1);

namespace App\Service\OAuth\Bluesky;

use App\Service\OAuth\OAuthProviderInterface;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use RuntimeException;

/**
 * Bluesky (AT Protocol) OAuth 2.0 client — PAR + PKCE + DPoP + private_key_jwt.
 *
 * Implements AUTH-06's OAuthProviderInterface. Depends on Plan 02-02 crypto services
 * (DpopService, ClientJwtService) and Plan 02-03 DidResolver.
 *
 * HTTP is via Cake\Http\Client, mockable through Client::addMockResponse() in tests
 * (diverging from altotoo's cURL pattern for testability, RESEARCH.md Open Question Q1).
 *
 * DPoP-Nonce retry (CONTEXT D-10): initial request without nonce; if response is 400/401
 * and body.error == 'use_dpop_nonce', extract DPoP-Nonce header and retry once with nonce.
 * The nonce is stashed in $lastAsNonce so the caller (AuthController / OauthController)
 * can persist it in session between requests within the same OAuth flow.
 */
final class BlueskyOAuthClient implements OAuthProviderInterface
{
    private const PROVIDER_KEY = 'bluesky';
    private const PAR_SUCCESS = 201;
    private const TOKEN_SUCCESS = 200;
    private const PROFILE_SUCCESS = 200;

    private Client $http;
    private ?string $lastAsNonce;

    /**
     * @param \App\Service\OAuth\Bluesky\DpopService $dpop DPoP proof JWT generator.
     * @param \App\Service\OAuth\Bluesky\ClientJwtService $clientJwt private_key_jwt client_assertion generator.
     * @param \App\Service\OAuth\Bluesky\DidResolver $didResolver DID→PDS resolver (used by resolveProfile).
     * @param \Cake\Http\Client|null $http Optional injected HTTP client. Tests use
     *   Client::addMockResponse() global adapter, which intercepts every Client instance,
     *   so the default construction works for tests too.
     * @param string|null $initialAsNonce Optional previously-captured AS nonce to seed
     *   the state (e.g., stashed in session across redirect boundary).
     */
    public function __construct(
        private readonly DpopService $dpop,
        private readonly ClientJwtService $clientJwt,
        private readonly DidResolver $didResolver,
        ?Client $http = null,
        ?string $initialAsNonce = null
    ) {
        $this->http = $http ?? new Client(['timeout' => 15]);
        $this->lastAsNonce = $initialAsNonce;
    }

    /**
     * @inheritDoc
     */
    public function getProviderKey(): string
    {
        return self::PROVIDER_KEY;
    }

    /**
     * Returns the most recently observed DPoP-Nonce value from any AS response,
     * or the initial seed nonce if none has been observed yet.
     *
     * @return string|null
     */
    public function getLastAsNonce(): ?string
    {
        return $this->lastAsNonce;
    }

    /**
     * @inheritDoc
     */
    public function executeParRequest(string $codeChallenge, string $state): array
    {
        $endpoint = (string)Configure::read('Bluesky.par_endpoint');
        $issuer = (string)Configure::read('Bluesky.issuer');
        $clientId = (string)Configure::read('Bluesky.client_id');
        $redirect = (string)Configure::read('Bluesky.redirect_uri');
        $scope = (string)Configure::read('Bluesky.client_metadata.scope');

        $params = [
            'client_id' => $clientId,
            'response_type' => 'code',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'redirect_uri' => $redirect,
            'state' => $state,
            'scope' => $scope,
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $this->clientJwt->createAssertion($issuer),
        ];

        $response = $this->postWithNonceRetry('POST', $endpoint, $params);
        $this->assertStatus($response['code'], self::PAR_SUCCESS, 'PAR');

        $body = json_decode($response['body'], true);
        if (!is_array($body) || !isset($body['request_uri']) || !is_string($body['request_uri'])) {
            throw new RuntimeException('PAR response missing request_uri.');
        }

        return [
            'request_uri' => $body['request_uri'],
            'expires_in' => (int)($body['expires_in'] ?? 60),
        ];
    }

    /**
     * @inheritDoc
     */
    public function exchangeCodeForToken(string $code, string $codeVerifier): array
    {
        $endpoint = (string)Configure::read('Bluesky.token_endpoint');
        $issuer = (string)Configure::read('Bluesky.issuer');
        $clientId = (string)Configure::read('Bluesky.client_id');
        $redirect = (string)Configure::read('Bluesky.redirect_uri');

        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect,
            'code_verifier' => $codeVerifier,
            'client_id' => $clientId,
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $this->clientJwt->createAssertion($issuer),
        ];

        $response = $this->postWithNonceRetry('POST', $endpoint, $params);
        $this->assertStatus($response['code'], self::TOKEN_SUCCESS, 'TOKEN_EXCHANGE');

        $body = json_decode($response['body'], true);
        if (!is_array($body)) {
            throw new RuntimeException('TOKEN_EXCHANGE response is not JSON.');
        }
        foreach (['access_token', 'refresh_token', 'token_type', 'expires_in', 'sub'] as $required) {
            if (!isset($body[$required])) {
                throw new RuntimeException("Token response missing `$required`.");
            }
        }

        return [
            'access_token' => (string)$body['access_token'],
            'refresh_token' => (string)$body['refresh_token'],
            'token_type' => (string)$body['token_type'],
            'expires_in' => (int)$body['expires_in'],
            'sub' => (string)$body['sub'],
        ];
    }

    /**
     * @inheritDoc
     */
    public function refreshToken(string $refreshToken): array
    {
        $endpoint = (string)Configure::read('Bluesky.token_endpoint');
        $issuer = (string)Configure::read('Bluesky.issuer');
        $clientId = (string)Configure::read('Bluesky.client_id');

        $params = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $this->clientJwt->createAssertion($issuer),
        ];

        $response = $this->postWithNonceRetry('POST', $endpoint, $params);
        $this->assertStatus($response['code'], self::TOKEN_SUCCESS, 'REFRESH');

        $body = json_decode($response['body'], true);
        if (!is_array($body)) {
            throw new RuntimeException('REFRESH response is not JSON.');
        }
        foreach (['access_token', 'refresh_token', 'expires_in'] as $required) {
            if (!isset($body[$required])) {
                throw new RuntimeException("Refresh response missing `$required`.");
            }
        }

        return [
            'access_token' => (string)$body['access_token'],
            'refresh_token' => (string)$body['refresh_token'],
            'expires_in' => (int)$body['expires_in'],
        ];
    }

    /**
     * @inheritDoc
     */
    public function resolveProfile(string $did, string $accessToken): array
    {
        $pds = $this->didResolver->resolveDidToPds($did);
        $endpoint = $pds . '/xrpc/app.bsky.actor.getProfile';
        $urlWithQuery = $endpoint . '?actor=' . rawurlencode($did);

        // getProfile is GET; DPoP proof htu is the endpoint WITHOUT query string (RFC 9449 §4.2).
        $headers = $this->buildDpopHeadersForResourceGet($endpoint, $accessToken, $this->lastAsNonce);

        $resp = $this->http->get($urlWithQuery, [], ['headers' => $headers]);
        $this->captureNonceFromResponse($resp);

        // Single nonce retry for RS calls as well (AS and RS may share nonce state).
        $status = $resp->getStatusCode();
        if ($status === 400 || $status === 401) {
            $body = json_decode((string)$resp->getStringBody(), true);
            if (
                is_array($body)
                && isset($body['error'])
                && $body['error'] === 'use_dpop_nonce'
                && $this->lastAsNonce !== null
            ) {
                $headers = $this->buildDpopHeadersForResourceGet($endpoint, $accessToken, $this->lastAsNonce);
                $resp = $this->http->get($urlWithQuery, [], ['headers' => $headers]);
                $this->captureNonceFromResponse($resp);
            }
        }

        if ($resp->getStatusCode() !== self::PROFILE_SUCCESS) {
            throw new RuntimeException('Profile fetch failed (HTTP ' . $resp->getStatusCode() . ').');
        }

        $profile = json_decode((string)$resp->getStringBody(), true);
        if (
            !is_array($profile)
            || !isset($profile['handle'])
            || !is_string($profile['handle'])
            || $profile['handle'] === ''
        ) {
            throw new RuntimeException('Profile response missing handle.');
        }

        $handle = $profile['handle'];
        $avatar = null;
        if (isset($profile['avatar']) && is_string($profile['avatar']) && $profile['avatar'] !== '') {
            $avatar = $profile['avatar'];
        }
        $displayName = null;
        if (isset($profile['displayName']) && is_string($profile['displayName']) && $profile['displayName'] !== '') {
            $displayName = $profile['displayName'];
        }

        return [
            'handle' => $handle,
            'avatar' => $avatar,
            'displayName' => $displayName,
            'profile_url' => 'https://bsky.app/profile/' . $handle,
        ];
    }

    /**
     * POST with DPoP + DPoP-Nonce retry (max 1 retry per CONTEXT D-10).
     *
     * @param string $htm HTTP method (uppercase).
     * @param string $url Target endpoint (no query).
     * @param array<string, string> $params form-urlencoded params.
     * @return array{code: int, body: string}
     */
    private function postWithNonceRetry(string $htm, string $url, array $params): array
    {
        $send = function (?string $nonce) use ($htm, $url, $params): array {
            $dpopProof = $this->dpop->createProof($htm, $url, null, $nonce);
            $resp = $this->http->post($url, http_build_query($params), [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'DPoP' => $dpopProof,
                    'Accept' => 'application/json',
                ],
            ]);
            $this->captureNonceFromResponse($resp);

            return [
                'code' => $resp->getStatusCode(),
                'body' => (string)$resp->getStringBody(),
            ];
        };

        $result = $send($this->lastAsNonce);
        if ($result['code'] === 400 || $result['code'] === 401) {
            $decoded = json_decode($result['body'], true);
            if (
                is_array($decoded)
                && isset($decoded['error'])
                && $decoded['error'] === 'use_dpop_nonce'
                && $this->lastAsNonce !== null
            ) {
                $result = $send($this->lastAsNonce);
            }
        }

        return $result;
    }

    /**
     * Build the Authorization + DPoP + Accept header triple for a bearer-DPoP GET.
     *
     * @param string $endpoint Bare endpoint URL (no query string — used as htu claim).
     * @param string $accessToken Bearer access token (also bound into DPoP via ath claim).
     * @param string|null $nonce Optional AS-provided DPoP-Nonce.
     * @return array<string, string>
     */
    private function buildDpopHeadersForResourceGet(string $endpoint, string $accessToken, ?string $nonce): array
    {
        $dpopProof = $this->dpop->createProof('GET', $endpoint, $accessToken, $nonce);

        return [
            'Authorization' => 'DPoP ' . $accessToken,
            'DPoP' => $dpopProof,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Refresh $this->lastAsNonce from the response's DPoP-Nonce header (empty-string safe).
     *
     * @param \Cake\Http\Client\Response $resp HTTP response to inspect.
     * @return void
     */
    private function captureNonceFromResponse(Response $resp): void
    {
        $headerValue = $resp->getHeaderLine('DPoP-Nonce');
        if ($headerValue !== '') {
            $this->lastAsNonce = trim($headerValue);
        }
    }

    /**
     * Throw RuntimeException if $actual !== $expected. Message excludes body to honor T-02-04-08.
     *
     * @param int $actual Observed HTTP status code.
     * @param int $expected Expected HTTP status code.
     * @param string $phase Human-readable phase label ('PAR' / 'TOKEN_EXCHANGE' / 'REFRESH').
     * @return void
     */
    private function assertStatus(int $actual, int $expected, string $phase): void
    {
        if ($actual === $expected) {
            return;
        }
        throw new RuntimeException(sprintf('%s request failed (HTTP %d).', $phase, $actual));
    }
}
