<?php
declare(strict_types=1);

/**
 * Bluesky OAuth configuration.
 *
 * Loaded via Configure::load('bluesky', 'default', false) in config/bootstrap.php.
 * Static endpoints (D-05): bsky.social is hardcoded for MVP — third-party PDS / dynamic
 * AS metadata resolution is out of scope (CONTEXT.md Deferred Ideas).
 *
 * client_id MUST byte-for-byte equal the delivery URL of /oauth/client-metadata.json
 * (AT Protocol strict match requirement — CONTEXT D-16, Pitfall 5).
 */
return [
    'Bluesky' => [
        'issuer'         => env('BLUESKY_ISSUER',         'https://bsky.social'),
        'par_endpoint'   => env('BLUESKY_PAR_ENDPOINT',   'https://bsky.social/oauth/par'),
        'token_endpoint' => env('BLUESKY_TOKEN_ENDPOINT', 'https://bsky.social/oauth/token'),
        'auth_endpoint'  => env('BLUESKY_AUTH_ENDPOINT',  'https://bsky.social/oauth/authorize'),

        // D-16: client_id === delivery URL (byte-for-byte). No env override — production-fixed.
        'client_id'    => 'https://tamabox.emomie.com/oauth/client-metadata.json',
        'redirect_uri' => 'https://tamabox.emomie.com/oauth/callback',

        // D-14: ES256 key paths. Do NOT commit config/keys/*.key (see config/keys/.gitignore).
        'private_key_path' => CONFIG . 'keys' . DS . 'private.key',
        'public_key_path'  => CONFIG . 'keys' . DS . 'public.key',

        // AUTH-FLOW §1 / D-06 / D-16: client_metadata.json payload.
        // Exact fields served by OauthController::clientMetadata() in Plan 02-03.
        'client_metadata' => [
            'client_id'                       => 'https://tamabox.emomie.com/oauth/client-metadata.json',
            'application_type'                => 'web',
            'client_name'                     => 'tamabox',
            'client_uri'                      => 'https://tamabox.emomie.com',
            'redirect_uris'                   => ['https://tamabox.emomie.com/oauth/callback'],
            'grant_types'                     => ['authorization_code', 'refresh_token'],
            'response_types'                  => ['code'],
            'scope'                           => 'atproto transition:generic',  // D-06
            'token_endpoint_auth_method'      => 'private_key_jwt',              // D-16
            'token_endpoint_auth_signing_alg' => 'ES256',
            'dpop_bound_access_tokens'        => true,
            'jwks_uri'                        => 'https://tamabox.emomie.com/oauth/jwks.json',
        ],
    ],
];
