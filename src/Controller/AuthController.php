<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\OAuth\Bluesky\BlueskyOAuthClient;
use App\Service\OAuth\Bluesky\ClientJwtService;
use App\Service\OAuth\Bluesky\DidResolver;
use App\Service\OAuth\Bluesky\DpopService;
use App\Service\OAuth\KeyManager;
use Cake\Core\Configure;
use Cake\Http\Response;
use RuntimeException;

/**
 * Auth — OAuth flow start + logout.
 *
 * Routes (config/routes.php Plan 02-01):
 *   GET|POST /login/bluesky → startBluesky
 *   POST /oauth/logout       → logout
 *
 * startBluesky is pre-login (no identity required); logout requires an existing identity.
 * Both endpoints are CSRF-protected (CakePHP CsrfProtectionMiddleware for POST).
 */
class AuthController extends AppController
{
    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();
        // startBluesky is the entry point — identity is established AFTER the callback.
        $this->Authentication->allowUnauthenticated(['startBluesky']);
    }

    /**
     * GET|POST /login/bluesky — generate PKCE + state, run PAR, redirect to AS.
     *
     * @return \Cake\Http\Response|null
     */
    public function startBluesky(): ?Response
    {
        try {
            [$verifier, $challenge, $state] = $this->newOAuthChallenge();
            $this->request->getSession()->write('Oauth.pkce_verifier', $verifier);
            $this->request->getSession()->write('Oauth.state', $state);

            $client = $this->buildOAuthClient();
            $par = $client->executeParRequest($challenge, $state);

            // Stash the AS nonce captured during PAR so callback can pick it up for token exchange.
            $nonceAfterPar = $client->getLastAsNonce();
            if ($nonceAfterPar !== null) {
                $this->request->getSession()->write('Oauth.as_nonce', $nonceAfterPar);
            }

            $authUrl = (string)Configure::read('Bluesky.auth_endpoint')
                . '?client_id=' . rawurlencode((string)Configure::read('Bluesky.client_id'))
                . '&request_uri=' . rawurlencode($par['request_uri']);

            return $this->redirect($authUrl);
        } catch (RuntimeException $e) {
            $this->Flash->error(__(
                '接続できませんでした。Bluesky のサーバーに接続できませんでした。'
                . 'ネットワーク接続を確認のうえ、再度お試しください。'
            ));

            return $this->redirect('/');
        }
    }

    /**
     * POST /oauth/logout — destroys the identity session and flashes success (D-18).
     *
     * @return \Cake\Http\Response|null
     */
    public function logout(): ?Response
    {
        $this->request->allowMethod(['post']);
        $this->Authentication->logout();
        $this->Flash->success(__('ログアウトしました'));

        return $this->redirect('/');
    }

    /**
     * Generate a PKCE verifier + S256 challenge + opaque state, all base64url-encoded.
     *
     * @return array{0: string, 1: string, 2: string} [verifier, challenge, state]
     */
    private function newOAuthChallenge(): array
    {
        $verifier = $this->base64url(random_bytes(64));
        $challenge = $this->base64url(hash('sha256', $verifier, true));
        $state = $this->base64url(random_bytes(32));

        return [$verifier, $challenge, $state];
    }

    /**
     * RFC 4648 §5 base64url (no padding) encoder.
     *
     * @param string $raw Raw bytes to encode.
     * @return string base64url-encoded string, no `=` padding.
     */
    private function base64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * Build a BlueskyOAuthClient with the Plan 02-02/02-03 service graph and seed the
     * AS-nonce from session if a prior flow left one behind.
     *
     * @return \App\Service\OAuth\Bluesky\BlueskyOAuthClient
     */
    private function buildOAuthClient(): BlueskyOAuthClient
    {
        $km = new KeyManager();
        $seedNonce = $this->request->getSession()->read('Oauth.as_nonce');
        $seedNonce = is_string($seedNonce) && $seedNonce !== '' ? $seedNonce : null;

        return new BlueskyOAuthClient(
            new DpopService($km),
            new ClientJwtService($km),
            new DidResolver(),
            null,
            $seedNonce
        );
    }
}
