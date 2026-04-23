<?php
declare(strict_types=1);

namespace App\Service\OAuth;

/**
 * OAuth provider abstraction (AUTH-06).
 *
 * Allows tamabox to add additional SNS providers (X/Twitter in v2) without
 * rewriting consumers. The Bluesky implementation (BlueskyOAuthClient, Plan 02-04)
 * is the only concrete implementation for MVP.
 *
 * All methods may throw \RuntimeException on non-2xx provider responses or on
 * cryptographic failure. Callers are expected to catch and translate to user-
 * facing error messages (UI-SPEC.md §4).
 */
interface OAuthProviderInterface
{
    /**
     * Execute a Pushed Authorization Request (RFC 9126) with PKCE challenge and state.
     *
     * Must include: DPoP proof, client_assertion (private_key_jwt), scope from
     * Configure::read('Bluesky.client_metadata.scope').
     *
     * Must implement DPoP-Nonce retry (one retry max, CONTEXT D-10) — initial request
     * without nonce, then resend with DPoP-Nonce header value if body.error == 'use_dpop_nonce'.
     *
     * @param string $codeChallenge PKCE S256 challenge (base64url of sha256(verifier)).
     * @param string $state Opaque random state bound to caller's session.
     * @return array{request_uri: string, expires_in: int}
     * @throws \RuntimeException on non-201 response or DPoP rejection.
     */
    public function executeParRequest(string $codeChallenge, string $state): array;

    /**
     * Exchange an authorization code for access + refresh tokens at the token endpoint.
     *
     * Must include PKCE code_verifier, client_assertion, DPoP proof. Nonce retry identical
     * to PAR. Response includes provider-issued access_token (DPoP-bound), refresh_token,
     * token_type ('DPoP'), expires_in (seconds), and sub (the DID, e.g. did:plc:...).
     *
     * @param string $code Authorization code from /oauth/callback query.
     * @param string $codeVerifier PKCE verifier previously stashed in session.
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, sub: string}
     * @throws \RuntimeException on non-200 response.
     */
    public function exchangeCodeForToken(string $code, string $codeVerifier): array;

    /**
     * Refresh an access token using a refresh token. Phase 2 implements the call but
     * does not wire automatic refresh (Phase 3 will, when sending messages requires
     * a valid token — per CONTEXT Deferred Ideas).
     *
     * @param string $refreshToken Decrypted refresh_token from user_identities.refresh_token_enc.
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     * @throws \RuntimeException on non-200 response.
     */
    public function refreshToken(string $refreshToken): array;

    /**
     * Fetch the account profile for the given DID using the access_token.
     *
     * Must resolve the DID to its PDS URL (plc.directory) and call
     * `GET <pds>/xrpc/app.bsky.actor.getProfile?actor=<did>` with
     * `Authorization: DPoP <access_token>` and a DPoP proof containing `ath`
     * claim = base64url(sha256(access_token)) (CONTEXT D-13).
     *
     * @param string $did Subject DID from token response (e.g., did:plc:abc...xyz).
     * @param string $accessToken Decrypted access_token.
     * @return array{handle: string, avatar: string|null, displayName: string|null, profile_url: string}
     * @throws \RuntimeException on DID resolution failure or non-200 getProfile response.
     */
    public function resolveProfile(string $did, string $accessToken): array;

    /**
     * Returns the provider key used in user_identities.provider ENUM column.
     * For Bluesky, must return the literal string 'bluesky' (MySQL ENUM value
     * defined in config/Migrations/20260422120002_CreateUserIdentities.php).
     *
     * @return string One of: 'bluesky', 'x'
     */
    public function getProviderKey(): string;
}
