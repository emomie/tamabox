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
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();
        // All three OAuth entry points are public:
        //   - clientMetadata / jwks: hit by Bluesky AS during PAR / token exchange
        //   - callback: the redirect target from Bluesky AS — identity is established AFTER
        //     this action (via setIdentity), not required before.
        $this->Authentication->allowUnauthenticated(['clientMetadata', 'jwks', 'callback']);
    }

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
     * /oauth/callback — OAuth authorization code callback (D-08, AUTH-01/02/04/05/09).
     *
     * Flow:
     *   1. Reject if ?error=... (user cancelled on AS) → flash 'ログインをキャンセル', 302 /
     *   2. state verify via hash_equals(session.Oauth.state, $_GET['state']) → mismatch → flash 'STATE_MISMATCH'
     *   3. iss verify (if present, must match Bluesky.issuer)
     *   4. exchangeCodeForToken with session.Oauth.pkce_verifier
     *   5. DID format guard `^did:plc:[a-z2-7]{24}$`
     *   6. resolveProfile (DID → PDS → getProfile)
     *   7. UserIdentitiesTable::upsertBlueskyIdentity (encrypts tokens, UPSERT in txn)
     *   8. Clear transient session keys (T-02-04-12)
     *   9. Authentication->setIdentity (regenerates session ID, T-02-04-05)
     *   10. 302 /dashboard
     *
     * @return \Cake\Http\Response|null
     */
    public function callback(): ?Response
    {
        // (a) OAuth cancel — Bluesky sends ?error=access_denied&state=...
        if ($this->queryString('error') !== '') {
            $this->clearOauthSession();
            $this->Flash->error(__(
                'ログインをキャンセルしました。Bluesky の認証画面でキャンセルされました。'
                . '再度ログインするには下のボタンを押してください。'
            ));

            return $this->redirect('/');
        }

        // (b) state verify — constant-time compare with session-stored value (single-use).
        $queryState = $this->queryString('state');
        $sessionState = $this->sessionString('Oauth.state');
        if ($queryState === '' || $sessionState === '' || !hash_equals($sessionState, $queryState)) {
            $this->clearOauthSession();
            $this->Flash->error(__(
                'ログインに失敗しました。セッションの整合性を確認できませんでした。'
                . '再度ログインしてください。（エラーコード: STATE_MISMATCH）'
            ));

            return $this->redirect('/');
        }

        // iss validation (optional per AT Protocol; when present, must match).
        $iss = $this->queryString('iss');
        $expectedIssuer = (string)Configure::read('Bluesky.issuer');
        if ($iss !== '' && $iss !== $expectedIssuer) {
            $this->clearOauthSession();
            $this->Flash->error(__('ログインに失敗しました。（エラーコード: ISS_MISMATCH）'));

            return $this->redirect('/');
        }

        $code = $this->queryString('code');
        $verifier = $this->sessionString('Oauth.pkce_verifier');
        if ($code === '' || $verifier === '') {
            $this->clearOauthSession();
            $this->Flash->error(__(
                'ログインに失敗しました。再度ログインしてください。（エラーコード: STATE_MISMATCH）'
            ));

            return $this->redirect('/');
        }

        try {
            $client = $this->buildOAuthClient();
            $tokenResp = $client->exchangeCodeForToken($code, $verifier);

            $did = $tokenResp['sub'];
            if (!preg_match('/^did:plc:[a-z2-7]{24}$/', $did)) {
                throw new RuntimeException('Malformed sub (DID).');
            }

            // Capture any new AS nonce from token exchange for the subsequent getProfile call.
            $nonceAfterToken = $client->getLastAsNonce();
            if ($nonceAfterToken !== null) {
                $this->request->getSession()->write('Oauth.as_nonce', $nonceAfterToken);
            }

            // Fetch profile — DID→PDS→getProfile; RuntimeException on any step.
            $profile = $client->resolveProfile($did, $tokenResp['access_token']);

            // UPSERT — new user inserts users+user_identities, existing updates identity.
            /** @var \App\Model\Table\UserIdentitiesTable $identitiesTable */
            $identitiesTable = $this->fetchTable('UserIdentities');
            $user = $identitiesTable->upsertBlueskyIdentity(
                [
                    'did' => $did,
                    'handle' => $profile['handle'],
                    'avatar' => $profile['avatar'],
                    'profile_url' => $profile['profile_url'],
                ],
                [
                    'access_token' => $tokenResp['access_token'],
                    'refresh_token' => $tokenResp['refresh_token'],
                    'expires_in' => $tokenResp['expires_in'],
                ]
            );

            // Clear transient OAuth-flow session keys (defense-in-depth, T-02-04-12).
            $this->clearOauthSession();

            // setIdentity regenerates the session ID (T-02-04-05).
            $this->Authentication->setIdentity($user);

            // Phase 3 D-13: if a pending send was stashed before login, return to it.
            $session = $this->request->getSession();
            $pendingInboxId = $session->read('pending_message_inbox_id');
            if (is_string($pendingInboxId) && $pendingInboxId !== '') {
                /** @var \App\Model\Table\InboxesTable $inboxesTable */
                $inboxesTable = $this->fetchTable('Inboxes');
                /** @var \App\Model\Entity\Inbox|null $pendingInbox */
                $pendingInbox = $inboxesTable->find()
                    ->where(['Inboxes.id' => $pendingInboxId])
                    ->first();
                // consume inbox_id — body is kept until the send form reads it on ?restored=1
                $session->delete('pending_message_inbox_id');
                if ($pendingInbox !== null) {
                    return $this->redirect('/' . $pendingInbox->slug . '?restored=1');
                }
            }

            return $this->redirect('/dashboard');
        } catch (RuntimeException $e) {
            $this->clearOauthSession();
            $msg = $e->getMessage();
            if (str_contains($msg, 'PAR') || str_contains($msg, 'TOKEN_EXCHANGE')) {
                $this->Flash->error(__(
                    'ログインに失敗しました。Bluesky からアクセス権限を取得できませんでした。'
                    . 'しばらくしてから再度お試しください。（エラーコード: TOKEN_EXCHANGE_FAILED）'
                ));
            } elseif (str_contains($msg, 'Profile') || str_contains($msg, 'DID')) {
                $this->Flash->error(__(
                    'ログインに失敗しました。Bluesky との通信中にセキュリティエラーが発生しました。'
                    . 'しばらくしてから再度お試しください。（エラーコード: DPOP_REJECTED）'
                ));
            } else {
                $this->Flash->error(__('ログインに失敗しました。しばらくしてから再度お試しください。'));
            }

            return $this->redirect('/');
        }
    }

    /**
     * Wipe OAuth-flow transient session keys. Called both on success (defense-in-depth)
     * and on every error branch (T-02-04-12).
     *
     * @return void
     */
    private function clearOauthSession(): void
    {
        $session = $this->request->getSession();
        foreach (['Oauth.pkce_verifier', 'Oauth.state', 'Oauth.as_nonce'] as $k) {
            $session->delete($k);
        }
    }

    /**
     * Build a BlueskyOAuthClient with the Plan 02-02/02-03 service graph and seed the
     * AS-nonce from session so PAR→token→getProfile share a single nonce lineage.
     *
     * @return \App\Service\OAuth\Bluesky\BlueskyOAuthClient
     */
    private function buildOAuthClient(): BlueskyOAuthClient
    {
        $km = new KeyManager();
        $seedNonce = $this->sessionString('Oauth.as_nonce');
        $seedNonce = $seedNonce !== '' ? $seedNonce : null;

        return new BlueskyOAuthClient(
            new DpopService($km),
            new ClientJwtService($km),
            new DidResolver(),
            null,
            $seedNonce
        );
    }

    /**
     * Safely read a query parameter as a string. Non-string values (arrays, null) become ''.
     *
     * @param string $key Query key name.
     * @return string
     */
    private function queryString(string $key): string
    {
        $v = $this->request->getQuery($key);

        return is_string($v) ? $v : '';
    }

    /**
     * Safely read a session key as a string. Non-string values become ''.
     *
     * @param string $key Dot-path session key (e.g. 'Oauth.state').
     * @return string
     */
    private function sessionString(string $key): string
    {
        $v = $this->request->getSession()->read($key);

        return is_string($v) ? $v : '';
    }
}
