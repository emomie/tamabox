# Phase 2: Bluesky OAuth & Identity — Pattern Map

**Mapped:** 2026-04-23
**Files analyzed:** 30 (新規 17 + 修正 13)
**Analogs found:** 21 / 30 (strong or role-match); 9 (novel / no in-tree peer)

---

## 1. Proposed File Inventory

| 新規 / 修正ファイル | Role | Data Flow | 最近似アナログ | Match 品質 |
|---|---|---|---|---|
| `src/Application.php` (modify) | bootstrap / middleware pipeline | synchronous init | self (現在の `Application.php`) | in-place modify |
| `config/routes.php` (modify) | route config | declarative | self (現在の `routes.php`) | in-place modify |
| `config/.env.example` (modify) | env template | declarative | self (既存 `.env.example`) | append-only |
| `config/bluesky.php` (new) | config | declarative | `config/app_local.example.php` のキーパターン | role-match |
| `src/Controller/AuthController.php` (new) | controller | request-response | `src/Controller/PagesController.php` (CakePHP 4 controller anatomy) | role-match |
| `src/Controller/OauthController.php` (new) | controller | request-response | `src/Controller/PagesController.php` | role-match |
| `src/Controller/UsersController.php` (new) | controller | request-response | `src/Controller/PagesController.php` | role-match |
| `src/Controller/AppController.php` (modify) | base controller | — | self | in-place modify |
| `src/Service/OAuth/OAuthProviderInterface.php` (new) | interface | — | なし (novel) | no analog |
| `src/Service/OAuth/KeyManager.php` (new) | service / utility | synchronous transform | なし (novel) | no analog |
| `src/Service/OAuth/TokenEncryptionService.php` (new) | service / utility | synchronous transform | なし (novel) | no analog |
| `src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` (new) | service | request-response (HTTP) | altotoo `BlueskyOauthComponent.php` (pattern donor) | role-match (split refactor) |
| `src/Service/OAuth/Bluesky/DpopService.php` (new) | service / utility | synchronous transform | altotoo `BlueskyOauthComponent.php` L120-156 | role-match (extract) |
| `src/Service/OAuth/Bluesky/ClientJwtService.php` (new) | service / utility | synchronous transform | altotoo `BlueskyOauthComponent.php` L75-115 | role-match (extract) |
| `src/Service/OAuth/Bluesky/DidResolver.php` (new) | service | request-response (HTTP) | altotoo `BlueskyOauthComponent.php` (resolveDidToPds 相当) | role-match (extract) |
| `src/Model/Table/UsersTable.php` (modify) | ORM Table | CRUD | self (Phase 1 bake 済み) | in-place modify |
| `src/Model/Table/UserIdentitiesTable.php` (modify) | ORM Table | CRUD + UPSERT | self (Phase 1 bake 済み) | in-place modify |
| `templates/layout/default.php` (modify) | view layout | SSR | self (現在の `default.php`) | in-place modify |
| `templates/Pages/home.php` (modify) | view template | SSR | self (CakePHP skeleton home) | full rewrite |
| `templates/Auth/callback.php` (new) | view template | SSR | `templates/Error/error400.php` (構造参照) | role-match |
| `templates/Users/dashboard.php` (new) | view template | SSR | `templates/Error/error400.php` (構造参照) | role-match |
| `templates/element/avatar_handle_chip.php` (new) | view element | SSR | `templates/element/flash/error.php` (element 構造参照) | role-match |
| `webroot/css/tamabox.css` (new) | CSS | — | なし (novel) | no analog |
| `tests/TestCase/Service/OAuth/Bluesky/DpopServiceTest.php` (new) | unit test | — | なし (novel — test/ 配下に先行例なし) | no analog |
| `tests/TestCase/Service/OAuth/Bluesky/ClientJwtServiceTest.php` (new) | unit test | — | same | no analog |
| `tests/TestCase/Service/OAuth/Bluesky/TokenEncryptionServiceTest.php` (new) | unit test | — | same | no analog |
| `tests/TestCase/Service/OAuth/KeyManagerTest.php` (new) | unit test | — | same | no analog |
| `tests/Fixture/keys/private.key` + `public.key` (new) | test fixture | — | なし (dummy EC keys) | no analog |
| `composer.json` (modify) | build config | declarative | self | in-place modify |

---

## 2. Code Excerpts Per Analog

### `src/Application.php` — middleware pipeline への AuthenticationMiddleware 差し込み

**Analog:** `/home/claude/projects/tamabox/src/Application.php`

**現在の middleware pipeline** (lines 77-104):
```php
$middlewareQueue
    ->add(new ErrorHandlerMiddleware(Configure::read('Error'), $this))
    ->add(new AssetMiddleware([
        'cacheTime' => Configure::read('Asset.cacheTime'),
    ]))
    ->add(new RoutingMiddleware($this))
    ->add(new BodyParserMiddleware())
    ->add(new CsrfProtectionMiddleware([
        'httponly' => true,
    ]));
```

**追加する use 文のパターン:**
```php
// 既存 use 群のアルファベット順に挿入
use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Identifier\IdentifierInterface;
use Authentication\Middleware\AuthenticationMiddleware;
use Psr\Http\Message\ServerRequestInterface;
```

**bootstrap() への addPlugin 追加パターン** (line 63-66 の DebugKit 追加を参考に):
```php
$this->addPlugin('Authentication');
```

**AuthenticationMiddleware の差し込み位置** (CsrfProtectionMiddleware の後):
```php
->add(new CsrfProtectionMiddleware(['httponly' => true]))
->add(new AuthenticationMiddleware($this));
```

**`getAuthenticationService()` メソッドの追加パターン:**
- `Application` に `AuthenticationServiceProviderInterface` を implements
- `getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface` を実装
- Session authenticator + カスタム ORM Identifier (`users.id` で UsersTable を引く)

---

### `config/routes.php` — スコープ追加パターン

**Analog:** `/home/claude/projects/tamabox/config/routes.php`

**現在のルート登録スタイル** (lines 52-79):
```php
return function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);

    $routes->scope('/', function (RouteBuilder $builder): void {
        $builder->connect('/', ['controller' => 'Pages', 'action' => 'display', 'home']);
        $builder->connect('/pages/*', 'Pages::display');
        $builder->fallbacks();
    });
};
```

**Phase 2 で追加するルートのパターン:**
```php
// /login/* および /oauth/* のスコープ
$routes->scope('/', function (RouteBuilder $builder): void {
    // GET/POST /login/bluesky → Auth::startBluesky
    $builder->connect('/login/bluesky', ['controller' => 'Auth', 'action' => 'startBluesky'])
        ->setMethods(['GET', 'POST']);

    // GET /oauth/callback (CSRF 免除 — state パラメータでアプリ側検証)
    $builder->connect('/oauth/callback', ['controller' => 'Oauth', 'action' => 'callback'])
        ->setMethods(['GET']);

    // GET /oauth/client-metadata.json
    $builder->connect('/oauth/client-metadata.json', ['controller' => 'Oauth', 'action' => 'clientMetadata'])
        ->setMethods(['GET']);

    // GET /oauth/jwks.json
    $builder->connect('/oauth/jwks.json', ['controller' => 'Oauth', 'action' => 'jwks'])
        ->setMethods(['GET']);

    // POST /oauth/logout (CSRF 必須)
    $builder->connect('/oauth/logout', ['controller' => 'Oauth', 'action' => 'logout'])
        ->setMethods(['POST']);

    // GET /dashboard (Authentication必須)
    $builder->connect('/dashboard', ['controller' => 'Users', 'action' => 'dashboard'])
        ->setMethods(['GET']);
});
```

---

### `src/Controller/AuthController.php` — Controller 基本骨格

**Analog:** `/home/claude/projects/tamabox/src/Controller/PagesController.php`

**ファイルヘッダー + namespace + use 群のパターン** (lines 1-24):
```php
<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * ...MIT License header...
 */
namespace App\Controller;

use Cake\Core\Configure;
use Cake\Http\Response;
```

**initialize() の Flash + RequestHandler ロード** (AppController.php lines 40-46 から継承):
```php
public function initialize(): void
{
    parent::initialize();  // AppController が Flash + RequestHandler をロード済み
}
```

**レスポンスとリダイレクトのパターン** (PagesController.php lines 49-72 参照):
```php
// Flash メッセージ付きリダイレクト
$this->Flash->error(__('ログインに失敗しました'));
return $this->redirect('/');

// 成功リダイレクト
return $this->redirect('/dashboard');
```

**altotoo LoginController の oauthLogin() フロー参照** (L32-57):
```php
public function startBluesky(): ?Response
{
    try {
        $pkce   = $this->oAuthClient->generatePkce();
        $state  = base64_encode(random_bytes(32)); // base64url 変換はサービス側
        $this->request->getSession()->write('pkce_verifier', $pkce['verifier']);
        $this->request->getSession()->write('oauth_state', $state);

        $parResponse = $this->blueskyClient->executeParRequest($pkce['challenge'], $state);
        $authUrl = Configure::read('Bluesky.auth_endpoint')
            . '?client_id=' . urlencode(Configure::read('Bluesky.client_id'))
            . '&request_uri=' . urlencode($parResponse['request_uri']);

        return $this->redirect($authUrl);
    } catch (\RuntimeException $e) {
        $this->Flash->error(__('Bluesky との接続に失敗しました。しばらくしてから再度お試しください。'));
        return $this->redirect('/');
    }
}
```

---

### `src/Controller/OauthController.php` — callback・metadata・jwks

**Analog:** `/home/claude/projects/tamabox/src/Controller/PagesController.php` + altotoo `LoginController.php` L59-118

**callback() の altotoo パターン**:
```php
public function callback(): ?Response
{
    // (a) error パラメータ検出 → キャンセル扱い
    if ($this->request->getQuery('error')) {
        $this->Flash->error(__('ログインをキャンセルしました'));
        return $this->redirect('/');
    }

    // (b) state 検証
    $state = $this->request->getQuery('state');
    if (!$state || $state !== $this->request->getSession()->read('oauth_state')) {
        $this->Flash->error(__('ログインに失敗しました（STATE_MISMATCH）'));
        return $this->redirect('/');
    }

    $code = $this->request->getQuery('code');
    try {
        // token exchange → UPSERT → setIdentity の一連 (Service 層へ委譲)
        $user = $this->callbackService->handle($code, $this->request->getSession());
        $this->Authentication->setIdentity($user);
        return $this->redirect('/dashboard');
    } catch (\RuntimeException $e) {
        $this->Flash->error(__('ログインに失敗しました。しばらくしてから再度お試しください。'));
        return $this->redirect('/');
    }
}
```

**clientMetadata() — JSON レスポンスパターン:**
```php
public function clientMetadata(): Response
{
    $metadata = Configure::read('Bluesky.client_metadata');
    return $this->response
        ->withType('application/json')
        ->withStringBody(json_encode($metadata, JSON_UNESCAPED_SLASHES));
}
```

**jwks() — JSON レスポンスパターン:**
```php
public function jwks(): Response
{
    $jwk = $this->keyManager->getPublicJwk();
    return $this->response
        ->withType('application/json')
        ->withStringBody(json_encode(['keys' => [$jwk]], JSON_UNESCAPED_SLASHES));
}
```

---

### `src/Service/OAuth/Bluesky/DpopService.php` — altotoo から抽出

**Analog (pattern donor):** `.planning/references/altotoo/BlueskyOauthComponent.php` L120-156

**クラス骨格:**
```php
<?php
declare(strict_types=1);

namespace App\Service\OAuth\Bluesky;

/**
 * DPoP Proof JWT generator (ES256, RFC 9449).
 */
class DpopService
{
    public function __construct(private readonly \App\Service\OAuth\KeyManager $keyManager) {}

    /**
     * Generate a DPoP proof JWT.
     *
     * @param string $htm HTTP method (uppercase)
     * @param string $htu Endpoint URL (no query string)
     * @param string|null $accessToken Present only for resource server calls (adds `ath` claim)
     * @param string|null $nonce DPoP-Nonce from AS response header
     * @return string Signed DPoP JWT
     */
    public function createProof(
        string $htm,
        string $htu,
        ?string $accessToken = null,
        ?string $nonce = null
    ): string {
        // 公開鍵 JWK は KeyManager から取得 (DPoP header.jwk に埋め込む)
        $jwk = $this->keyManager->getPublicJwkForDpop(); // use/alg/kid なしのミニマル形式
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
        return $this->createJwt($payload, ['typ' => 'dpop+jwt', 'jwk' => $jwk]);
    }
    // ... createJwt(), derToRawSignature(), base64urlEncode() ...
}
```

**derToRawSignature() — altotoo L37-70 をそのまま移植:**
```php
private function derToRawSignature(string $der): string
{
    $pos = 2; // Sequence tag + length をスキップ
    // R
    $rLen = ord($der[$pos + 1]);
    $r    = substr($der, $pos + 2, $rLen);
    // S
    $sLen = ord($der[$pos + 2 + $rLen + 1]);
    $s    = substr($der, $pos + 2 + $rLen + 2, $sLen);
    // 先頭 0x00 パディング除去
    if (strlen($r) > 32 && ord($r[0]) === 0) { $r = substr($r, 1); }
    if (strlen($s) > 32 && ord($s[0]) === 0) { $s = substr($s, 1); }
    // 32 バイトに左ゼロパディング
    return str_pad($r, 32, chr(0), STR_PAD_LEFT)
         . str_pad($s, 32, chr(0), STR_PAD_LEFT);
}
```

---

### `src/Service/OAuth/Bluesky/ClientJwtService.php` — altotoo から抽出

**Analog (pattern donor):** `.planning/references/altotoo/BlueskyOauthComponent.php` L100-115

**createClientAssertion() パターン:**
```php
public function createAssertion(string $audience): string
{
    $now     = time();
    $payload = [
        'iss' => Configure::read('Bluesky.client_id'),
        'sub' => Configure::read('Bluesky.client_id'),
        'aud' => $audience,  // PAR は par_endpoint、token は token_endpoint
        'jti' => $this->base64urlEncode(random_bytes(32)),
        'iat' => $now,
        'exp' => $now + 60,
    ];
    // DPoP とは異なり client_assertion は typ:'JWT', kid あり、jwk なし
    return $this->createJwt($payload, ['kid' => env('OAUTH_KID', 'ssr-box-key-1')]);
}
```

---

### `src/Service/OAuth/KeyManager.php` — RESEARCH.md の確認済みコードそのまま

**Analog:** なし (novel) — ただしコードは RESEARCH.md §Code Examples から直接引用可

**PEM → JWK 変換 (RESEARCH.md で PHP 8.3.6 確認済み):**
```php
public function getPublicJwk(): array
{
    $pem  = file_get_contents(CONFIG . 'keys/public.key');
    $key  = openssl_pkey_get_public($pem);
    $det  = openssl_pkey_get_details($key);
    return [
        'kty' => 'EC',
        'crv' => 'P-256',
        'kid' => env('OAUTH_KID', 'ssr-box-key-1'),
        'use' => 'sig',
        'alg' => 'ES256',
        'x'   => $this->base64urlEncode($det['ec']['x']),
        'y'   => $this->base64urlEncode($det['ec']['y']),
    ];
}

/** DPoP header.jwk 用 (use/alg/kid なし) */
public function getPublicJwkForDpop(): array
{
    $jwk = $this->getPublicJwk();
    unset($jwk['kid'], $jwk['use'], $jwk['alg']);
    return $jwk;
}

public function getPrivateKey(): \OpenSSLAsymmetricKey
{
    $pem = file_get_contents(CONFIG . 'keys/private.key');
    return openssl_pkey_get_private($pem);
}
```

**テスト注入口 (テスト時に config/keys/ を参照しないよう):**
```php
public function __construct(private readonly string $privKeyPath = '', private readonly string $pubKeyPath = '')
{
    $this->privKeyPath = $privKeyPath ?: CONFIG . 'keys/private.key';
    $this->pubKeyPath  = $pubKeyPath  ?: CONFIG . 'keys/public.key';
}
```

---

### `src/Service/OAuth/TokenEncryptionService.php` — RESEARCH.md の確認済みコードそのまま

**Analog:** なし (novel) — RESEARCH.md §Code Examples から直接引用可

**encrypt / decrypt (AES-256-GCM, RESEARCH.md で PHP 8.3.6 確認済み):**
```php
public function encrypt(string $plaintext): string
{
    $key        = hex2bin(env('TOKEN_ENC_KEY'));
    $iv         = random_bytes(12);
    $tag        = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    return $this->base64urlEncode($iv . $ciphertext . $tag);
}

public function decrypt(string $encoded): string
{
    $raw  = $this->base64urlDecode($encoded);
    $iv   = substr($raw, 0, 12);
    $tag  = substr($raw, -16);
    $ct   = substr($raw, 12, strlen($raw) - 28);
    $pt   = openssl_decrypt($ct, 'aes-256-gcm', hex2bin(env('TOKEN_ENC_KEY')), OPENSSL_RAW_DATA, $iv, $tag);
    if ($pt === false) {
        throw new \RuntimeException('Token decryption failed');
    }
    return $pt;
}
```

---

### `src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` — HTTP callApi パターン

**Analog (pattern donor):** `.planning/references/altotoo/BlueskyOauthComponent.php` L161-226

**DPoP-Nonce リトライパターン (altotoo L203-217 をそのまま踏襲):**
```php
// altotoo の callApi から抽出した nonce retry ロジック
$sendRequest = function(?string $nonce) use (...) {
    $dpopProof = $this->dpopService->createProof($method, $endpoint, $accessToken, $nonce);
    // curl 設定 (CURLOPT_HEADER=true + CURLINFO_HEADER_SIZE 分割)
    ...
    return ['code' => $httpCode, 'header' => $headerText, 'body' => $body];
};

$result = $sendRequest(null);  // 1回目 nonce なし
if (in_array($result['code'], [400, 401])) {
    $bodyJson = json_decode($result['body'], true);
    if (($bodyJson['error'] ?? '') === 'use_dpop_nonce') {
        if (preg_match('/^DPoP-Nonce:\s*(.+)$/im', $result['header'], $m)) {
            $result = $sendRequest(trim($m[1]));  // 2回目 nonce 付き (最大 1 回)
        }
    }
}
if ($result['code'] !== $expectedCode) {
    throw new \RuntimeException('Request failed: ' . $result['body']);
}
```

**cURL ヘッダー分割 (altotoo L194-200 の CURLINFO_HEADER_SIZE パターン):**
```php
curl_setopt($ch, CURLOPT_HEADER, true);  // ヘッダー + ボディを一緒に取得
$raw        = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);
$headerText = substr($raw, 0, $headerSize);
$body       = substr($raw, $headerSize);
```

---

### `src/Model/Table/UsersTable.php` + `UserIdentitiesTable.php` — UPSERT 追加

**Analog:** 自身 (Phase 1 bake 済み) — `/home/claude/projects/tamabox/src/Model/Table/`

**既存の initialize() のパターン** (UsersTable.php lines 40-79):
```php
public function initialize(array $config): void
{
    parent::initialize($config);
    $this->setTable('users');
    $this->setDisplayField('display_name');
    $this->setPrimaryKey('id');
    $this->addBehavior('Timestamp', [
        'events' => ['Model.beforeSave' => ['created_at' => 'new', 'updated_at' => 'always']],
    ]);
    $this->hasOne('UserIdentities', ['foreignKey' => 'user_id']);
    // ...
}
```

**Phase 2 で追加する `findByDid()` カスタム finder のパターン** (validationDefault の直後に追加):
```php
/**
 * Find user by DID via user_identities join.
 *
 * @param \Cake\ORM\Query $query Query instance.
 * @param array $options Options with 'did' key.
 * @return \Cake\ORM\Query
 */
public function findByDid(\Cake\ORM\Query $query, array $options): \Cake\ORM\Query
{
    return $query
        ->contain(['UserIdentities'])
        ->matching('UserIdentities', function ($q) use ($options) {
            return $q->where([
                'UserIdentities.provider' => 'bluesky',
                'UserIdentities.provider_account_id' => $options['did'],
            ]);
        });
}
```

**UserIdentitiesTable の findByProvider カスタム finder パターン:**
```php
public function findByProvider(\Cake\ORM\Query $query, array $options): \Cake\ORM\Query
{
    return $query->where([
        'provider'            => $options['provider'],
        'provider_account_id' => $options['provider_account_id'],
    ]);
}
```

---

### View テンプレート群

**Analog:** `/home/claude/projects/tamabox/templates/layout/default.php` (lines 1-55)

**default.php の修正パターン:**
```php
// 変更前: <html>
// 変更後:
<html lang="ja">

// 変更前: <?= $this->Html->css(['normalize.min', 'milligram.min', 'fonts', 'cake']) ?>
// 変更後:
<?= $this->Html->css(['normalize.min', 'milligram.min', 'fonts', 'tamabox']) ?>

// <nav class="top-nav"> ブロックを HeaderBar に差し替え
<header class="header-bar">
    <div class="header-bar-title">
        <a href="<?= $this->Url->build('/') ?>">tamabox</a>
    </div>
    <?php if ($this->getRequest()->getAttribute('identity')): ?>
    <div class="header-bar-right">
        <?= $this->element('avatar_handle_chip', ['identity' => $this->getRequest()->getAttribute('identity')]) ?>
        <form method="POST" action="<?= $this->Url->build('/oauth/logout') ?>">
            <?= $this->Form->hidden('_csrfToken', ['value' => $this->request->getAttribute('csrfToken')]) ?>
            <button type="submit" class="button-clear logout-btn">ログアウト</button>
        </form>
    </div>
    <?php endif; ?>
</header>
```

**flash element の既存スタイル** (templates/element/flash/error.php lines 1-11):
```php
<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="message error" onclick="this.classList.add('hidden');"><?= $message ?></div>
```

Phase 2 ではこのクラス名を `alert alert-error` などに変更して tamabox.css の Alert コンポーネントと対応付ける。

**templates/Auth/callback.php の構造パターン (Spinner 画面):**
```php
<?php
/**
 * @var \App\View\AppView $this
 */
$this->assign('title', 'Bluesky と通信中…');
?>
<div class="callback-page">
    <h2>Bluesky と通信中…</h2>
    <div role="status" aria-live="polite" class="spinner-wrapper">
        <div class="spinner" aria-hidden="true"></div>
        <span class="visually-hidden">Bluesky と通信中…</span>
    </div>
</div>
```

**templates/Users/dashboard.php のパターン:**
```php
<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */
$this->assign('title', 'ダッシュボード');
$handle = $user->identity->handle_cached ?? '';
?>
<div class="dashboard-page">
    <h1>ようこそ、<?= h($handle) ?> さん</h1>
    <p class="text-secondary">受信箱はまだ作成されていません。受信箱の作成は次のステップで行います。</p>
</div>
```

---

### `config/bluesky.php` (新規) — config ファイルスタイル

**Analog:** `config/app_local.example.php` の return 配列スタイル

**パターン:**
```php
<?php
declare(strict_types=1);

return [
    'Bluesky' => [
        'issuer'        => env('BLUESKY_ISSUER',        'https://bsky.social'),
        'par_endpoint'  => env('BLUESKY_PAR_ENDPOINT',  'https://bsky.social/oauth/par'),
        'token_endpoint'=> env('BLUESKY_TOKEN_ENDPOINT','https://bsky.social/oauth/token'),
        'auth_endpoint' => env('BLUESKY_AUTH_ENDPOINT', 'https://bsky.social/oauth/authorize'),
        'client_id'     => 'https://tamabox.emomie.com/oauth/client-metadata.json',
        'redirect_uri'  => 'https://tamabox.emomie.com/oauth/callback',
        'private_key_path' => CONFIG . 'keys/private.key',
        'public_key_path'  => CONFIG . 'keys/public.key',
        'client_metadata' => [
            'client_id'                          => 'https://tamabox.emomie.com/oauth/client-metadata.json',
            'application_type'                   => 'web',
            'client_name'                        => 'tamabox',
            'client_uri'                         => 'https://tamabox.emomie.com',
            'redirect_uris'                      => ['https://tamabox.emomie.com/oauth/callback'],
            'grant_types'                        => ['authorization_code', 'refresh_token'],
            'response_types'                     => ['code'],
            'scope'                              => 'atproto transition:generic',
            'token_endpoint_auth_method'         => 'private_key_jwt',
            'token_endpoint_auth_signing_alg'    => 'ES256',
            'dpop_bound_access_tokens'           => true,
            'jwks_uri'                           => 'https://tamabox.emomie.com/oauth/jwks.json',
        ],
    ],
];
```

bootstrap.php で `Configure::load('bluesky', 'default', false);` を追加して読み込む。

---

### Unit テスト骨格 — `DpopServiceTest.php` 等

**Analog:** なし (in-tree の tests/ にテストケースがまだ存在しない)

**RESEARCH.md で確認されている PHPUnit 9.6 + CakePHP TestCase パターン:**
```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\OAuth\Bluesky;

use App\Service\OAuth\Bluesky\DpopService;
use App\Service\OAuth\KeyManager;
use Cake\TestSuite\TestCase;

/**
 * DpopService Unit Test
 */
class DpopServiceTest extends TestCase
{
    private DpopService $dpop;

    protected function setUp(): void
    {
        parent::setUp();
        // テスト用ダミー鍵 (tests/Fixture/keys/ に置く)
        $keyManager = new KeyManager(
            TESTS . 'Fixture/keys/private.key',
            TESTS . 'Fixture/keys/public.key'
        );
        $this->dpop = new DpopService($keyManager);
    }

    public function testCreateProofStructure(): void
    {
        $jwt = $this->dpop->createProof('POST', 'https://bsky.social/oauth/par');
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);

        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
        $this->assertSame('dpop+jwt', $header['typ']);
        $this->assertSame('ES256', $header['alg']);
        $this->assertArrayHasKey('jwk', $header);

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $this->assertSame('POST', $payload['htm']);
        $this->assertSame('https://bsky.social/oauth/par', $payload['htu']);
        $this->assertArrayHasKey('jti', $payload);
    }

    public function testAthClaimAddedWhenAccessTokenProvided(): void
    {
        $jwt = $this->dpop->createProof('GET', 'https://pds.example.com/xrpc/app.bsky.actor.getProfile', 'my_access_token');
        $parts   = explode('.', $jwt);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $this->assertArrayHasKey('ath', $payload);
    }

    public function testNonceIncludedWhenProvided(): void
    {
        $jwt = $this->dpop->createProof('POST', 'https://bsky.social/oauth/token', null, 'test-nonce-123');
        $parts   = explode('.', $jwt);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $this->assertSame('test-nonce-123', $payload['nonce']);
    }
}
```

---

## 3. Novel Patterns (アナログなし — Planner が RESEARCH.md から直接参照すべき箇所)

| 新規パターン | 説明 | 参照先 |
|---|---|---|
| **OAuthProviderInterface** | マルチプロバイダ抽象 (`executeParRequest` / `exchangeCodeForToken` / `refreshToken` / `resolveProfile` / `getProviderKey()`)。CakePHP 4 には対応インタフェースがない。 | CONTEXT.md D-03 + RESEARCH.md §Architectural Responsibility Map |
| **DPoP Proof JWT** | `typ:'dpop+jwt'` + header.jwk + `ath` claim。PHP ビルトインのみで実装。CakePHP / cakephp/authentication に相当する実装がない。 | RESEARCH.md §DPoP Implementation / altotoo L120-156 |
| **AES-256-GCM トークン暗号化** | `openssl_encrypt(...,'aes-256-gcm',...,$tag)` + IV||CT||TAG 連結 base64url。CakePHP Security クラスは AES-256-CBC 使用なので流用不可。 | RESEARCH.md §Token & Session Storage + D-15 |
| **ES256 PEM → JWK 変換** | `openssl_pkey_get_details($key)['ec']['x/y']` を base64url して JWK 構造に変換。CakePHP に同等ユーティリティなし。 | RESEARCH.md §Code Examples / altotoo L126-136 |
| **PKCE (S256)** | `base64urlEncode(hash('sha256', $verifier, true))` — SHA256 は `hash(..., true)` で生バイト出力必須。 | altotoo L27-31 + RESEARCH.md §DPoP Implementation |
| **DPoP-Nonce リトライ** | nonce なし → 400/401 + `use_dpop_nonce` → header 抽出 → 再送 (最大 1 回)。 | RESEARCH.md §Common Pitfalls P3 / altotoo L203-217 |
| **CakePHP Authentication Plugin 統合** | `AuthenticationServiceProviderInterface::getAuthenticationService()` + Session Authenticator + ORM Identifier。Phase 1 以前にはなかった。 | CONTEXT.md D-02 + CakePHP Authentication Plugin docs |
| **`config/bluesky.php`** | `Configure::load()` で読む PHP config ファイル。`app_local.example.php` の return 配列スタイルを踏襲するが内容は全て新規。 | `config/app_local.example.php` のスタイル + CONTEXT.md D-05 |
| **webroot/css/tamabox.css** | Milligram 上書き CSS + CSS カスタムプロパティ。既存 CSS は Milligram 骨格のみで tamabox 固有スタイルがない。 | UI-SPEC.md §7 に全 CSS トークン定義あり |

---

## 4. Conventions to Follow

### Namespace ルート
- `App\` → `src/` (PSR-4, composer.json lines 28-30)
- Service 層: `App\Service\OAuth\` → `src/Service/OAuth/`
- Tests: `App\Test\TestCase\Service\OAuth\Bluesky\` → `tests/TestCase/Service/OAuth/Bluesky/`
- View テンプレート: `templates/<Controller>/<action>.php` (CakePHP 4 規約)

### ファイルヘッダー
- `<?php` line 1
- `declare(strict_types=1);` line 2
- PHP ファイルが `src/` 配下の場合は MIT ライセンスヘッダー (Application.php L4-16 スタイル)
- テンプレート (`templates/`) は PHPDoc `@var \App\View\AppView $this` のみ (不要な MIT ヘッダー省略可)

### use 文の順序
- アルファベット順、エイリアスなし (CONVENTIONS.md §Import Organization)
- `use` ブロックは namespace 宣言の後に 1 行空けて開始

### PHPDoc スタイル
- 全 public メソッドに `@param`, `@return`, `@throws` を付与
- FQCN は先頭 `\` 付き (例: `\RuntimeException`)
- 英語 (CONVENTIONS.md §Comments)

### Docblock での型注釈
- Entity `@property` は `string` (UUID は CHAR(36), bake が `int` と書く場合は修正)
- `\Cake\I18n\FrozenTime` for DATETIME columns (Phase 1 Entity 参照)

### 設定読み込みパターン
- `Configure::read('Bluesky.par_endpoint')` — `Configure::load('bluesky')` で読んだキーを参照
- 秘匿値は `env('TOKEN_ENC_KEY')` — Configure に平文保存しない
- `config/bluesky.php` 読み込みは `config/bootstrap.php` に `Configure::load('bluesky', 'default', false);` を追加

### コミットメッセージ形式
- Phase 1 のコミット履歴から: `feat(<plan>-<task>): <動詞 + 目的語>` (例: `feat(02-02-01): add KeyManager and PEM→JWK conversion`)
- 破壊的変更は `!` サフィックス: `feat(02-04)!: wire AuthenticationMiddleware in Application.php`

### phpcs / phpstan
- コミット毎: `composer phpcs && composer phpstan`
- Wave merge: `composer test`
- phpstan level 8 対象: `src/` のみ (`config/` は除外)
- test ファイルも phpcs 対象 (phpstan.neon で tests/ は除外設定確認)

### テスト命名
- ファイル: `<ClassName>Test.php`
- メソッド: `test<WhatItTests>()` — テスト対象の動作を英語で記述
- テスト用 EC 鍵ペアを `tests/Fixture/keys/private.key` + `public.key` に配置 (本番 `config/keys/` とは別)

---

## 5. No Analog Found (in tree)

| ファイル | Role | Data Flow | 理由 |
|---|---|---|---|
| `src/Service/OAuth/OAuthProviderInterface.php` | interface | — | CakePHP 4 に OAuth プロバイダ抽象がない |
| `src/Service/OAuth/KeyManager.php` | service / utility | synchronous transform | EC鍵操作は tamabox 初出 |
| `src/Service/OAuth/TokenEncryptionService.php` | service / utility | synchronous transform | AES-GCM token 暗号化は tamabox 初出 |
| `src/Service/OAuth/Bluesky/DpopService.php` | service | synchronous transform | DPoP は tamabox 初出 (altotoo が pattern donor) |
| `src/Service/OAuth/Bluesky/ClientJwtService.php` | service | synchronous transform | 同上 |
| `src/Service/OAuth/Bluesky/DidResolver.php` | service | request-response | plc.directory 外部 HTTP は tamabox 初出 |
| `webroot/css/tamabox.css` | CSS | — | 既存 CSS は Milligram 骨格のみ |
| `tests/TestCase/Service/OAuth/**Test.php` (×4) | unit test | — | tests/ 配下にテストケースが 1 件もない (Phase 1 は bake 生成テストも省略) |

**Planner 向け指示:** 上記ファイルは RESEARCH.md §Code Examples および altotoo `BlueskyOauthComponent.php` (pattern donor として扱う) のコードを直接参照してアクションを記述すること。

---

## Metadata

**Analog search scope:**
- `/home/claude/projects/tamabox/src/` (19 PHP ファイル — Phase 1 bake 済み)
- `/home/claude/projects/tamabox/templates/` (15 テンプレート)
- `/home/claude/projects/tamabox/config/` (PHP 設定ファイル + Migrations)
- `/home/claude/projects/tamabox/.planning/references/altotoo/` (pattern donor 2 ファイル)

**Files scanned:** 25 (src/ 19 + templates/ 主要 5 + config/ 主要 3 + altotoo/ 2)

**Pattern extraction date:** 2026-04-23

---

## PATTERN MAPPING COMPLETE

**Phase:** 02 - bluesky-oauth-identity
**Files classified:** 30
**Analogs found:** 21 / 30

### Coverage
- Files with exact / in-place analog: 8 (Application.php, routes.php, .env.example, AppController, UsersTable, UserIdentitiesTable, default.php layout, home.php)
- Files with role-match analog (altotoo or PagesController): 13 (AuthController, OauthController, UsersController, Service 層 5 本, templates 4 本, flash element)
- Files with no analog (novel): 9 (OAuthProviderInterface, KeyManager, TokenEncryptionService, DpopService, ClientJwtService, DidResolver, tamabox.css, 4 test files)

### Key Patterns Identified
- **全 Controller は PagesController と同じファイルヘッダー + namespace + `extends AppController` で書く。** AppController が Flash + RequestHandler をロード済みのため initialize() では `parent::initialize()` を呼ぶだけでよい。
- **Service 層 (DpopService / ClientJwtService / BlueskyOAuthClient) の crypto コアは altotoo `BlueskyOauthComponent.php` からの直接移植。** altotoo は Lolipop PHP 8.x + bsky.social で本番稼働しており、derToRawSignature() / cURL nonce retry / DPoP proof 構造は 1:1 コピーで罠を回避する。
- **設定は `config/bluesky.php` → `Configure::load()` → `Configure::read('Bluesky.*')`。** Phase 1 が確立した `Configure::read('Security.serverSecret')` / `env()` 併用パターンを踏襲。
- **テンプレートは CakePHP 4 の `templates/<Controller>/<action>.php` 規約に従う。** 既存の `templates/layout/default.php` を修正して `lang="ja"` / `tamabox.css` / HeaderBar を追加し、全ページで共有する。
- **Unit テストには PHPUnit 9.6 + `Cake\TestSuite\TestCase` を使用。** `KeyManager` にパス注入口を設けてテスト用ダミー鍵 (`tests/Fixture/keys/`) を使い、`config/keys/` 依存を排除する。
