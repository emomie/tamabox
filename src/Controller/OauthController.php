<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\OAuth\KeyManager;
use Cake\Core\Configure;
use Cake\Http\Response;

/**
 * OAuth public endpoints + callback.
 *
 * Routes registered in config/routes.php by Plan 02-01:
 *   GET /oauth/client-metadata.json -> clientMetadata()
 *   GET /oauth/jwks.json            -> jwks()
 *   GET /oauth/callback             -> callback()   (this plan ships a 501 stub; Plan 02-04 fills the body)
 *
 * The first two are AT Protocol public metadata that Bluesky AS hits during
 * PAR / token exchange (CONTEXT D-16 / D-17). They must:
 *   - return 200 with Content-Type: application/json
 *   - never redirect
 *   - embed exact Configure-driven values (no dynamic URL building that could drift from client_id)
 */
class OauthController extends AppController
{
    /**
     * /oauth/client-metadata.json — AT Protocol client metadata (D-16).
     *
     * Returns Configure::read('Bluesky.client_metadata') verbatim with
     * JSON_UNESCAPED_SLASHES so the byte-for-byte match against client_id
     * URL (Pitfall 5) holds when the AS fetches this document.
     *
     * @return \Cake\Http\Response
     */
    public function clientMetadata(): Response
    {
        $metadata = Configure::read('Bluesky.client_metadata');
        if (!is_array($metadata) || !isset($metadata['client_id'])) {
            // Misconfiguration — Plan 02-01 should have populated this.
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody((string)json_encode(['error' => 'metadata_not_configured']));
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody((string)json_encode($metadata, JSON_UNESCAPED_SLASHES));
    }

    /**
     * /oauth/jwks.json — public JWKS containing the ES256 public key (D-17).
     *
     * Returns a single-key JWKS: {"keys":[{kty,crv,kid,use,alg,x,y}]}.
     * KeyManager reads the public key file via Configure::read('Bluesky.public_key_path').
     * If the key file is unreadable, returns 500 rather than leaking the path.
     *
     * @return \Cake\Http\Response
     */
    public function jwks(): Response
    {
        $keyManager = new KeyManager();
        try {
            $jwk = $keyManager->getPublicJwk();
        } catch (\RuntimeException $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody((string)json_encode(['error' => 'key_not_available']));
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody((string)json_encode(['keys' => [$jwk]], JSON_UNESCAPED_SLASHES));
    }

    /**
     * /oauth/callback — OAuth authorization code callback. STUB.
     *
     * Plan 02-04 replaces this body with: state verify -> token exchange (BlueskyOAuthClient)
     * -> UPSERT user_identities (AES-GCM encrypted tokens) -> setIdentity -> 302 /dashboard.
     *
     * Until then this returns 501 so that any accidental pre-Plan-02-04 hit is visibly broken
     * rather than silently succeeding with nothing done.
     *
     * @return \Cake\Http\Response
     */
    public function callback(): Response
    {
        return $this->response
            ->withStatus(501)
            ->withType('application/json')
            ->withStringBody((string)json_encode(['error' => 'callback_not_yet_implemented_plan_02_04']));
    }
}
