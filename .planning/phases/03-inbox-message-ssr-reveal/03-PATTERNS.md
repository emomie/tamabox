# Phase 3: Inbox, Message & SSR Reveal — Pattern Map

**Mapped:** 2026-04-24
**Files analyzed:** 23 new/modified files across controller, service, template, migration, asset, CSS, test layers
**Analogs found:** 22 / 23 (1 file — `webroot/img/default-avatar.svg` — has no in-tree analog; raw SVG asset)

---

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `src/Controller/MessagesController.php` (new) | controller | request-response (CRUD + state mutation) | `src/Controller/AuthController.php` | exact — auth-gated POST handler with try/catch + Flash + redirect |
| `src/Controller/InboxesController.php` (new — settings update) | controller | request-response (UPDATE) | `src/Controller/UsersController.php::dashboard` | role-match — auth-required + `fetchTable()` + `firstOrFail()` |
| `src/Controller/BlocksController.php` (new — 501 stub) | controller | request-response (501 stub) | `src/Controller/OauthController.php::callback` (Plan 02-03 pre-04 form) | exact — same reservation contract; test pattern in `OauthControllerTest::testCallbackStub501HasBeenReplaced` |
| `src/Controller/UsersController.php` (modify — extend `dashboard()`) | controller | request-response (paginated read) | `src/Controller/UsersController.php` (current state) | self-extension |
| `src/Service/Inbox/SlugDeriver.php` (new) | service / utility | transform (pure, deterministic) | `src/Service/OAuth/KeyManager.php` | role-match — `final class`, single-responsibility, no I/O on the hot path, `Configure::read` for defaults |
| `src/Service/Message/SsrJudge.php` (new) | service / utility | transform (deterministic, hex/sha256) | `src/Service/OAuth/Bluesky/DpopService.php` | role-match — pure crypto-transform with `final class`, constructor-injected dependency, `Configure::read('Security.serverSecret')` parallel to `Configure::read('Bluesky.client_id')` |
| `src/Model/Table/InboxesTable.php` (modify — add `findBySlug`, `assignUniqueSlug`, slug-update on handle rename) | model / table | CRUD + transactional retry | `src/Model/Table/UserIdentitiesTable.php::upsertBlueskyIdentity` | exact — `Connection::transactional` + `DatabaseException` catch + suffix retry |
| `src/Model/Table/MessagesTable.php` (modify — add `sendMessage` / `markOpened`) | model / table | INSERT (snapshot bake) + UPDATE (single field) | `src/Model/Table/UserIdentitiesTable.php::upsertBlueskyIdentity` | role-match — newEntity + `accessibleFields` + `saveOrFail` |
| `templates/Messages/send.php` (new) | template | form display + auth-gated CTA | `templates/Pages/home.php` | exact — `$this->Form->create(null, [...])` POST form with primary-button |
| `templates/Messages/send_done.php` (new) | template | static text + 2 CTAs | `templates/Pages/home.php` | exact — same minimal `<div class="page">` + heading-less paragraph + button row |
| `templates/Inboxes/settings.php` (new) | template | settings form | `templates/Pages/home.php` (form) + `templates/Users/dashboard.php` (auth context) | role-match |
| `templates/Users/dashboard.php` (modify — receive list + paginator + settings sidebar) | template | paginated list + nested form | `templates/Users/dashboard.php` (current Phase 2 placeholder) | self-extension |
| `templates/Error/error400.php` (existing — Phase 1) | template | 404 fallback | (existing CakePHP scaffold) | reuse without change (D-36) |
| `config/Migrations/<datestamp>_AddSlugPreviousToInboxes.php` (new — 1 column or thin history table) | migration | DDL | `config/Migrations/20260422120003_CreateInboxes.php` | role-match — same `AbstractMigration` + `$autoId = false` + raw SQL CHECK pattern (only if check needed) |
| `config/routes.php` (modify — add 7 routes) | config | routing | `config/routes.php` (existing Phase 2 routes block) | self-extension |
| `webroot/css/tamabox.css` (modify — append ~200 lines) | asset / css | static | `webroot/css/tamabox.css` (existing Phase 2 baseline) | self-extension |
| `webroot/img/default-avatar.svg` (new) | asset / svg | static | (none — first SVG asset) | no analog — UI-SPEC §7 has executor example |
| `tests/Fixture/InboxesFixture.php` (modify — add 2nd inbox + ssr_probability variants) | fixture | test data | `tests/Fixture/InboxesFixture.php` (current) | self-extension |
| `tests/Fixture/MessagesFixture.php` (modify — add unread/opened/SSR-hit variants) | fixture | test data | `tests/Fixture/MessagesFixture.php` (current) | self-extension |
| `tests/TestCase/Controller/MessagesControllerTest.php` (new) | test | integration | `tests/TestCase/Controller/AuthControllerTest.php` | exact — `IntegrationTestTrait` + `protected $fixtures` + Flash retain + Configure setup |
| `tests/TestCase/Controller/InboxesControllerTest.php` (new) | test | integration | `tests/TestCase/Controller/AuthControllerTest.php` | exact (same family) |
| `tests/TestCase/Controller/UsersControllerTest.php` (new — for extended dashboard) | test | integration | `tests/TestCase/Controller/AuthControllerTest.php` | exact (same family) |
| `tests/TestCase/Service/Inbox/SlugDeriverTest.php` (new) | test | unit | `tests/TestCase/Service/OAuth/KeyManagerTest.php` | exact — pure unit test, `private $svc; setUp; assertSame` |
| `tests/TestCase/Service/Message/SsrJudgeTest.php` (new) | test | unit (deterministic table-driven) | `tests/TestCase/Service/OAuth/Bluesky/DpopServiceTest.php` | exact — deterministic-table assertions on hex/sha256 outputs |
| `tests/TestCase/Model/Table/InboxesTableTest.php` (modify — add slug tests) | test | unit | `tests/TestCase/Service/OAuth/KeyManagerTest.php` | role-match |

---

## Pattern Assignments

### 1. `src/Controller/MessagesController.php` (controller, request-response)

**Analog:** `src/Controller/AuthController.php` (the closest controller with a try/catch + Flash + redirect shape that wraps a session-touching POST; `OauthController::callback` is also relevant for the multi-branch error redirect pattern but is heavier).

**File header + namespace + imports** — copy from `AuthController.php` lines 1–14:
```php
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
```
For `MessagesController` the imports change but the **shape is the same**: `App\Service\Inbox\SlugDeriver`, `App\Service\Message\SsrJudge`, `Cake\Core\Configure`, `Cake\Http\Exception\NotFoundException`, `Cake\Http\Response`, `RuntimeException`.

**Class docblock** (`AuthController.php` lines 15–24) — pattern for declaring routes inline:
```php
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
```
For `MessagesController`, document the 4 routes (`GET|POST /<slug>`, `POST /dashboard/messages/{id}/open`, `POST /report/{id}` 501 stub) and which require authentication vs which are open.

**`initialize()` with `allowUnauthenticated`** — `AuthController.php` lines 27–35:
```php
public function initialize(): void
{
    parent::initialize();
    // startBluesky is the entry point — identity is established AFTER the callback.
    $this->Authentication->allowUnauthenticated(['startBluesky']);
}
```
For `MessagesController`, allow `['send']` (D-13 — sender form is reachable while logged out so `pending_message_body` can be stashed).

**Action method with try/catch + Flash + redirect** — `AuthController.php` lines 37–71 (`startBluesky`):
```php
public function startBluesky(): ?Response
{
    try {
        [$verifier, $challenge, $state] = $this->newOAuthChallenge();
        $this->request->getSession()->write('Oauth.pkce_verifier', $verifier);
        // ... domain work ...
        return $this->redirect($authUrl);
    } catch (RuntimeException $e) {
        $this->Flash->error(__(
            '接続できませんでした。Bluesky のサーバーに接続できませんでした。'
            . 'ネットワーク接続を確認のうえ、再度お試しください。'
        ));
        return $this->redirect('/');
    }
}
```
**Use this exact shape** for `send($slug)`, `open($id)`, `report($id)`. Each returns `?Response`, throws `NotFoundException` for missing slug/message (D-36), wraps domain calls in try/`catch (RuntimeException)`, and redirects with a flash on error.

**`queryString` / `sessionString` helpers** — `OauthController.php` lines 257–281 (sticky note #3, phpstan level 8 narrowing of `getQuery()` / session reads):
```php
/**
 * Safely read a query parameter as a string. Non-string values (arrays, null) become ''.
 */
private function queryString(string $key): string
{
    $v = $this->request->getQuery($key);
    return is_string($v) ? $v : '';
}

private function sessionString(string $key): string
{
    $v = $this->request->getSession()->read($key);
    return is_string($v) ? $v : '';
}
```
Use verbatim in `MessagesController` for reading `pending_message_body`, `pending_message_inbox_id`, `?restored=1`, `?page=N`.

**`$this->fetchTable()` + `firstOrFail()` pattern** — `UsersController.php` lines 23–46:
```php
$identity = $this->Authentication->getIdentity();
if ($identity === null) {
    return $this->redirect('/');
}
$identifier = $identity->getIdentifier();
$userId = is_scalar($identifier) ? (string)$identifier : '';
if ($userId === '') {
    return $this->redirect('/');
}
/** @var \App\Model\Entity\User $user */
$user = $this->fetchTable('Users')
    ->find()
    ->where(['Users.id' => $userId])
    ->contain(['UserIdentities'])
    ->firstOrFail();
$this->set('user', $user);
return null;
```
Use in `MessagesController::send` for inbox lookup by slug (`InboxesTable::findBySlug($slug)` → throws NotFoundException on null — this is D-36).

---

### 2. `src/Controller/InboxesController.php` (controller, request-response, UPDATE)

**Analog:** `src/Controller/UsersController.php` for the auth gate + `fetchTable` + identity check; `OauthController::callback` for the multi-error-branch redirect pattern.

**Class docblock + initialize** — copy `UsersController.php` lines 8–14 shape:
```php
/**
 * Users — authenticated landing pages.
 *
 * Phase 2 scope: /dashboard only (placeholder until Phase 3 wires inbox management).
 * AuthenticationMiddleware redirects unauthenticated hits to '/' via
 * Application::getAuthenticationService's unauthenticatedRedirect setting.
 */
```
`InboxesController` has no `allowUnauthenticated` — every action requires auth (settings UPDATE).

**`update()` action shape** — combine `AuthController::logout` (POST-only allowMethod + Flash success + redirect) and `OauthController::callback` (multi-branch validation):
```php
public function update(): ?Response
{
    $this->request->allowMethod(['post']);  // ← from AuthController.php L80
    $identity = $this->Authentication->getIdentity();  // ← from UsersController.php L25
    // ... validate + patchEntity + saveOrFail in try/catch ...
    $this->Flash->success(__('保存しました'));
    return $this->redirect('/dashboard');
}
```

---

### 3. `src/Controller/BlocksController.php` (controller, 501 stub)

**Analog:** Plan 02-03's pre-replacement `OauthController::callback` (the 501 stub itself was replaced by Plan 02-04 in the current `OauthController.php`, but the **contract** is locked in by `tests/TestCase/Controller/OauthControllerTest.php::testCallbackStub501HasBeenReplaced` lines 102–109):

```php
public function testCallbackStub501HasBeenReplaced(): void
{
    // Plan 02-03 shipped a 501 stub held as a hand-off contract; Plan 02-04 replaces
    // the body with the real flow. Any state-bearing hit should now redirect (302),
    // never 501 (stub) or 200 (naive OK).
    $this->get('/oauth/callback?code=x&state=y');
    $this->assertResponseCode(302);
}
```

**Stub pattern** for Phase 3 → Phase 4 hand-off (D-35):
```php
/**
 * POST /block/{sender_user_id} — 501 stub. Plan Phase 4 replaces the body.
 *
 * The stub MUST return 501 Not Implemented (no flash, no DB write) so a
 * downstream verifier-authored test (Phase 4) can assert the body is gone.
 */
public function create(string $senderUserId): Response
{
    $this->request->allowMethod(['post']);
    return $this->response->withStatus(501)->withStringBody('Not Implemented');
}
```
The same shape applies to `MessagesController::report($id)`. Both must have a corresponding test asserting `assertResponseCode(501)` so Phase 4 knows what to replace (mirror of `OauthControllerTest::testCallbackStub501HasBeenReplaced`).

---

### 4. `src/Service/Inbox/SlugDeriver.php` (service, transform / pure)

**Analog:** `src/Service/OAuth/KeyManager.php` (closest pure-utility service: `final class`, single responsibility, optional Configure-defaulted constructor args).

**Class shell + namespace** — `KeyManager.php` lines 1–17:
```php
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
```
For `SlugDeriver`, namespace is `App\Service\Inbox`, class is `final class SlugDeriver`. Same `<?php` + `declare(strict_types=1);` + namespace + use + class docblock structure.

**Constructor with Configure-default fallback** — `KeyManager.php` lines 22–38:
```php
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
    // ...
}
```
For `SlugDeriver`, the constructor can take an injected `InboxesTable` (for collision lookups) defaulted to `null` and resolved via `TableRegistry::getTableLocator()->get('Inboxes')` if absent — same defensive-default shape.

**Pure transform method with regex normalization** — model after `KeyManager::extractEcCoordinates` lines 100–117 in shape (regex check → throw or return) but for slug the regex is `[a-zA-Z0-9_-]{3,32}` (matches `inboxes_slug_format` CHECK in migration `20260422120003_CreateInboxes.php` lines 117–122):
```php
public function deriveFromHandle(string $handle, string $did): string
{
    // Domain-prefix extraction: 'satie.bsky.social' → 'satie'
    // Fallback when handle is empty / not slug-safe: 'user-' . substr(hash('sha256', $did), 0, 8)
    // Collision retry: caller (InboxesTable) handles -2 / -3 suffix
}
```

---

### 5. `src/Service/Message/SsrJudge.php` (service, deterministic transform)

**Analog:** `src/Service/OAuth/Bluesky/DpopService.php` (closest deterministic crypto-transform service: same `final class`, constructor-injected dependency, hash/encode operations).

**Class shape + readonly DI** — `DpopService.php` lines 17–24:
```php
final class DpopService
{
    /**
     * @param \App\Service\OAuth\KeyManager $keyManager ES256 keypair provider (DI).
     */
    public function __construct(private readonly KeyManager $keyManager)
    {
    }
```
For `SsrJudge`, no DI dependency needed — but if the planner decides to inject a clock or a `serverSecret` provider, use the same `private readonly` constructor promotion.

**Deterministic hash-based transform** — `DpopService.php` lines 33–60 (`createProof`) for the shape (build payload from inputs + hash → emit fixed-format string):
```php
public function createProof(string $htm, string $htu, ?string $accessToken = null, ?string $nonce = null): string
{
    $header = ['typ' => 'dpop+jwt', 'alg' => 'ES256', 'jwk' => $this->keyManager->getPublicJwkForDpop()];
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
    // ...
}
```
For `SsrJudge`, the equivalent deterministic transform is **D-09**:
```php
public function judge(string $messageId, string $createdAtMicro, string $ssrProbability): array
{
    $serverSecret = (string)Configure::read('Security.serverSecret');
    $seed = hash('sha256', $serverSecret . $messageId . $createdAtMicro);  // 64 hex chars
    $rand01 = hexdec(substr($seed, 0, 8)) / 0xFFFFFFFF;  // [0, 1)
    $isHit = $rand01 < (float)$ssrProbability;
    return ['ssr_seed' => $seed, 'is_ssr' => $isHit];
}
```
The point of analog: `hash('sha256', ...)` + `Configure::read` + deterministic struct return — exactly mirrors how `DpopService` builds claims from inputs.

**Configure usage** — both services use `Configure::read('Bluesky.client_id')` (DpopService context) / `Configure::read('Security.serverSecret')` (SsrJudge) the same way:
```php
$serverSecret = (string)Configure::read('Security.serverSecret');
if ($serverSecret === '') {
    throw new RuntimeException('Security.serverSecret is not configured.');
}
```
Mirror `ClientJwtService.php` lines 32–35 (the most concise example of the Configure-or-throw guard).

---

### 6. `src/Model/Table/InboxesTable.php` (modify — add `findBySlug`, `assignUniqueSlug`, slug-update on rename)

**Analog:** `src/Model/Table/UserIdentitiesTable.php::upsertBlueskyIdentity` (closest table method: `Connection::transactional` + `DatabaseException` catch + suffix retry semantics).

**Transactional UPSERT with DatabaseException catch** — `UserIdentitiesTable.php` lines 154–275:
```php
public function upsertBlueskyIdentity(array $profile, array $tokens): User
{
    // ... validate inputs ...
    $connection = $this->getConnection();
    /** @var \App\Model\Table\UsersTable $usersTable */
    $usersTable = $this->getAssociation('Users')->getTarget();

    /** @var \App\Model\Entity\UserIdentity|null $existing */
    $existing = $this->find()
        ->where([
            $this->aliasField('provider') => 'bluesky',
            $this->aliasField('provider_account_id') => $did,
        ])
        ->first();

    try {
        return $connection->transactional(
            function () use (...) : User {
                if ($existing !== null) {
                    $patched = $this->patchEntity($existing, [...], ['accessibleFields' => [...]]);
                    $this->saveOrFail($patched);
                    return $user;
                }
                // INSERT new
                $newUser = $usersTable->newEntity([...], ['accessibleFields' => [...]]);
                $usersTable->saveOrFail($newUser);
                $newIdentity = $this->newEntity([...], ['accessibleFields' => [...]]);
                $this->saveOrFail($newIdentity);
                return $newUser;
            }
        );
    } catch (DatabaseException $e) {
        throw new RuntimeException('Identity upsert failed: database constraint violation.', 0, $e);
    } catch (PersistenceFailedException $e) {
        throw new RuntimeException('Identity upsert failed: validation or save error.', 0, $e);
    }
}
```

**Use this verbatim shape** for `InboxesTable::assignUniqueSlug($baseSlug, $userId)` — the body retries `INSERT` (or `UPDATE slug=?` if rename) inside a loop, catching `DatabaseException` from the `uk_inboxes_slug` UNIQUE violation, and incrementing the `-N` suffix until success or N=100 (D-02 fallback to `<prefix>-<did_hash8>`).

**`accessibleFields` per call** — `UserIdentitiesTable.php` lines 198–214 (the `accessibleFields` payload):
```php
$patched = $this->patchEntity($existing, [
    'handle_cached' => $handle,
    // ...
], ['accessibleFields' => [
    'handle_cached' => true,
    // ...
]]);
```
For `InboxesTable`, the per-call accessibleFields list is `['slug' => true, 'ssr_probability' => true, 'welcome_message' => true, 'is_accepting' => true]` (the existing entity already lists them in `_accessible`, but per-call override gives belt-and-suspenders for `update()` + slug rename).

**`Timestamp` behavior already wired** — `InboxesTable.php` lines 44–51:
```php
$this->addBehavior('Timestamp', [
    'events' => [
        'Model.beforeSave' => [
            'created_at' => 'new',
            'updated_at' => 'always',
        ],
    ],
]);
```
No change needed — the `updated_at` will update on every slug rename automatically.

---

### 7. `src/Model/Table/MessagesTable.php` (modify — add `sendMessage`, `markOpened`)

**Analog:** `UserIdentitiesTable::upsertBlueskyIdentity` for the `newEntity` + `accessibleFields` + `saveOrFail` shape.

**`sendMessage()` body shape** — derived from `UserIdentitiesTable.php` lines 237–264:
```php
$newMessage = $this->newEntity([
    'id' => Text::uuid(),
    'inbox_id' => $inboxId,
    'sender_user_id' => $senderUserId,
    'body' => $body,
    'body_length' => mb_strlen($body),
    // SSR (D-09 — computed by SsrJudge):
    'is_ssr' => $isSsr,
    'ssr_probability_at_send' => $probAtSend,
    'ssr_seed' => $seed,
    // Sender snapshot (D-29 — copied from user_identities):
    'sender_provider' => 'bluesky',
    'sender_handle_snapshot' => $identity->handle_cached,
    'sender_avatar_url_snapshot' => $identity->avatar_url_cached,
    'sender_profile_url_snapshot' => $identity->profile_url_cached,
], ['accessibleFields' => [
    'id' => true,
    'inbox_id' => true,
    'sender_user_id' => true,
    'body' => true,
    'body_length' => true,
    'is_ssr' => true,
    'ssr_probability_at_send' => true,
    'ssr_seed' => true,
    'sender_provider' => true,
    'sender_handle_snapshot' => true,
    'sender_avatar_url_snapshot' => true,
    'sender_profile_url_snapshot' => true,
]]);
$this->saveOrFail($newMessage);
```

**`markOpened($messageId)`** — single-field UPDATE, mirror the `patchEntity` + `saveOrFail` half of `upsertBlueskyIdentity`:
```php
$message = $this->get($messageId);
if ($message->opened_at !== null) {
    return $message;  // idempotent — D-27
}
$patched = $this->patchEntity($message, [
    'opened_at' => FrozenTime::now(),
], ['accessibleFields' => ['opened_at' => true]]);
$this->saveOrFail($patched);
return $patched;
```
**Note:** `MessagesTable.php` lines 47–53 already declare `Timestamp` behavior with **no `updated_at`** (immutable post-send). The `opened_at` field is updated explicitly via `patchEntity` — do not add `updated_at` to the messages timestamp config.

---

### 8. `templates/Messages/send.php` (template, form display)

**Analog:** `templates/Pages/home.php` (lines 1–22) — closest existing form template:
```php
<?php
/**
 * @var \App\View\AppView $this
 *
 * Phase 2 home / login-CTA page (UI-SPEC §4).
 */
$this->assign('title', 'ホーム');
?>
<div class="home-page">
    <h1 class="display-heading">tamabox</h1>
    <p class="text-secondary home-lead">
        Bluesky アカウントでログインして、あなたの受信箱をはじめましょう。
    </p>

    <?= $this->Form->create(null, [
        'url' => ['controller' => 'Auth', 'action' => 'startBluesky'],
        'type' => 'post',
        'class' => 'login-form',
    ]) ?>
        <button type="submit" class="button primary-button">Bluesky でログイン</button>
    <?= $this->Form->end() ?>
</div>
```

**Pattern to copy:**
- File header docblock with `@var \App\View\AppView $this` + variable declarations + `$this->assign('title', ...)`
- `<?= $this->Form->create(null, [...]) ?>` ... `<?= $this->Form->end() ?>` for CSRF auto-injection (Form helper handles it; manual `<form>` tags would lose CSRF)
- Outermost `<div class="...-page">` wrapper for CSS scoping (`home-page`, `dashboard-page`, `callback-page` already exist; new `send-form-page` follows same convention)
- Single primary `<button type="submit" class="button primary-button">` per form

For `send.php`, additional needs (from UI-SPEC §1):
- `<textarea name="body" required maxlength="2000" rows="6">` with `aria-describedby` (CakePHP FormHelper supports `'maxlength'`, `'aria-describedby'` via the options array in `$this->Form->control('body', [...])`)
- Consent `<input type="checkbox" name="consent" required>` wrapped in `<label>` (use `$this->Form->control('consent', ['type' => 'checkbox', 'required' => true])`)
- Conditional button label: authenticated → 「送信する」, unauthenticated → 「Bluesky でログインして送信」 (PHP `if ($identity)` switch around the button)

**Output escaping** — copy `templates/Users/dashboard.php` lines 10–16 pattern for `welcome_message`:
```php
$handle = '';
if (isset($user->user_identity) && $user->user_identity !== null) {
    $handle = (string)$user->user_identity->handle_cached;
}
?>
<h1>ようこそ、<?= h($handle) ?> さん</h1>
```
For `send.php`, same `h(...)` for `welcome_message` body and `nl2br()` for line breaks (D-17): `<?= nl2br(h((string)$inbox->welcome_message)) ?>`.

---

### 9. `templates/Messages/send_done.php` (template, static text + 2 CTAs)

**Analog:** `templates/Pages/home.php` again (same minimal page shape; no form, just heading + buttons).

**Copy this layout from `home.php`** but replace the `Form->create` with two `$this->Html->link()` CTAs styled `button primary-button` and `button button-clear`:
```php
<?= $this->Html->link('同じ受信箱に再送する', ['controller' => 'Messages', 'action' => 'send', $inbox->slug], ['class' => 'button primary-button']) ?>
<?= $this->Html->link('他の受信箱を見る', '/', ['class' => 'button button-clear']) ?>
```

---

### 10. `templates/Inboxes/settings.php` (template, settings form)

**Analog:** combined `templates/Pages/home.php` (form shape) + `templates/Users/dashboard.php` (auth context). Use `$this->Form->create($inbox, ['url' => ['controller' => 'Inboxes', 'action' => 'update']])` so CakePHP wires the entity for re-display on validation error.

---

### 11. `templates/Users/dashboard.php` (modify — extend Phase 2 placeholder)

**Analog:** `templates/Users/dashboard.php` (current state, lines 1–18 self-extension). Keep the existing header structure:
```php
<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 *
 * UI-SPEC §4 — welcome + Phase 3 placeholder.
 */
$this->assign('title', 'ダッシュボード');

$handle = '';
if (isset($user->user_identity) && $user->user_identity !== null) {
    $handle = (string)$user->user_identity->handle_cached;
}
?>
<div class="dashboard-page">
    <h1>ようこそ、<?= h($handle) ?> さん</h1>
    <p class="text-secondary">受信箱はまだ作成されていません。受信箱の作成は次のステップで行います。</p>
</div>
```
**Replace** the placeholder `<p>` with:
- A receive-list `<section>` rendering `<details>` rows (UI-SPEC §2 — DOM concrete)
- The `<?= $this->element('Inboxes/settings_form') ?>` element OR the inline settings form
- The `<?= $this->Paginator->numbers() ?>` paginator block

Add `@var \App\Model\Entity\Message[] $messages` and `@var \App\Model\Entity\Inbox $inbox` to the docblock.

---

### 12. `config/Migrations/<datestamp>_AddSlugPreviousToInboxes.php` (migration, DDL)

**Analog:** `config/Migrations/20260422120003_CreateInboxes.php` lines 1–132.

**File header + class skeleton** — lines 1–32:
```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateInboxes migration.
 *
 * Source of truth: emomie/ssr-box-discovery:DB-SCHEMA.md v0.2 §3.
 * ...
 */
class CreateInboxes extends AbstractMigration
{
    public $autoId = false;

    public function up(): void
    {
```
**Copy** the file header, namespace-less use of `Migrations\AbstractMigration`, and (only if adding a UUID PK / new table) the `public $autoId = false;` declaration. For a single-column ALTER, `$autoId` is irrelevant.

**Datestamped filename** — use `bin/cake bake migration AddSlugPreviousToInboxes` so Phinx generates `20260424HHMMSS_AddSlugPreviousToInboxes.php`. Pattern matches the 6 existing migrations (lines 22–28 of `ls config/Migrations/`).

**ALTER TABLE shape** — for adding `slug_previous`, follow the `addColumn` pattern from lines 40–69 but using `update()` instead of `create()`:
```php
public function up(): void
{
    $this->table('inboxes')
        ->addColumn('slug_previous', 'string', [
            'limit' => 32,
            'null' => true,
            'after' => 'slug',
        ])
        ->update();
}

public function down(): void
{
    $this->table('inboxes')
        ->removeColumn('slug_previous')
        ->update();
}
```

**Raw SQL CHECK** — only if the planner adds a CHECK on `slug_previous` (probably not needed since NULL allowed; cite as reference). Pattern from lines 109–122:
```php
$this->execute(<<<SQL
ALTER TABLE inboxes
  ADD CONSTRAINT inboxes_slug_previous_format
  CHECK (slug_previous IS NULL OR slug_previous REGEXP '^[a-zA-Z0-9_-]{3,32}\$')
SQL);
```
Note the heredoc `\$` escape — verbatim from the existing migration line 121.

---

### 13. `tests/TestCase/Controller/MessagesControllerTest.php` (test, integration)

**Analog:** `tests/TestCase/Controller/AuthControllerTest.php` (closest controller integration test with all the Phase 2 conventions).

**Header + namespace + use + class shell** — lines 1–22:
```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * AuthController integration tests — startBluesky + logout.
 *
 * BlueskyOAuthClient's PAR call is stubbed via Client::addMockResponse so the test
 * does not hit the live Bluesky AS. ...
 */
class AuthControllerTest extends TestCase
{
    use IntegrationTestTrait;
```

**Fixtures + setUp + tearDown** — lines 27–54 (THIS IS THE STICKY-NOTE PATTERN: untyped `protected $fixtures`):
```php
/**
 * @var array<int, string>
 */
protected $fixtures = [
    'app.Users',
    'app.UserIdentities',
    'app.Inboxes',
    'app.Messages',
    'app.Blocks',
    'app.Reports',
];

protected function setUp(): void
{
    parent::setUp();
    Client::clearMockResponses();
    $this->enableRetainFlashMessages();
    putenv('OAUTH_KID=test-kid-1');
    $_ENV['OAUTH_KID'] = 'test-kid-1';
    $hexKey = str_repeat('ab', 32);
    putenv('TOKEN_ENC_KEY=' . $hexKey);
    $_ENV['TOKEN_ENC_KEY'] = $hexKey;
    Configure::write('Bluesky.private_key_path', TESTS . 'Fixture' . DS . 'keys' . DS . 'private.key');
    Configure::write('Bluesky.public_key_path', TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key');
}

protected function tearDown(): void
{
    Client::clearMockResponses();
    parent::tearDown();
}
```
**CRITICAL — typed-property avoidance** (Phase 2 Executor sticky-note from STATE.md): use `protected $fixtures` (untyped declaration), NOT `protected array $fixtures`. CakePHP's `TestSuite\Fixture` parent declares it untyped; PHP 8 typed-property collision otherwise. Copy verbatim from line 27.

**`enableCsrfToken()` for POST tests** — line 91:
```php
$this->enableCsrfToken();
$this->post('/login/bluesky');
$this->assertResponseCode(302);
```
Required for any POST — without it, the CsrfProtectionMiddleware returns 403.

**Session priming for stateful flows** — `OauthControllerCallbackTest.php` lines 84–90:
```php
$this->session([
    'Oauth' => ['state' => 'real_state_abc', 'pkce_verifier' => 'verifier'],
]);
$this->get('/oauth/callback?code=x&state=WRONG');
```
Use this pattern to test `MessagesController::send` with `pending_message_body` already in session (D-13 unauth flow restoration).

**Identity priming for authenticated tests** — Phase 2 used `setIdentity()` only inside the controller; for direct test priming use:
```php
$this->session([
    'Auth' => ['User' => $this->fetchTable('Users')->get('11111111-1111-1111-1111-111111111111')],
]);
```
(Verify the exact session key with `Application::getAuthenticationService()`'s `sessionKey` setting; Phase 2 did not test this path, so check `src/Application.php` first.)

**Flash regex assertion helper** — `OauthControllerCallbackTest.php` lines 113–127:
```php
private function assertFlashMessageMatches(string $pattern): void
{
    $session = $this->_requestSession;
    $this->assertNotNull($session, 'Session not available — request not yet dispatched.');
    $flash = $session->read('Flash.flash');
    $this->assertIsArray($flash, 'No Flash.flash array in session.');
    $this->assertNotEmpty($flash, 'No Flash message recorded.');
    $this->assertMatchesRegularExpression($pattern, (string)$flash[0]['message']);
}
```
**Copy verbatim** to MessagesControllerTest, InboxesControllerTest. Used to assert e.g. `/送信しました/`, `/同意/`, `/2000 文字以内/`.

**`Client::addMockResponse` not needed in Phase 3** — D-29 says no external API calls during Phase 3 message send. Keep the `Client::clearMockResponses()` in setUp/tearDown anyway (cheap, defensive against test pollution).

**501 stub assertion pattern** — `OauthControllerTest.php` lines 102–109 (cited above in Pattern 3) — use the same shape for the future `BlocksControllerTest::testCreateReturns501Stub` and `MessagesControllerTest::testReportReturns501Stub`.

---

### 14. `tests/TestCase/Service/Inbox/SlugDeriverTest.php` (test, unit)

**Analog:** `tests/TestCase/Service/OAuth/KeyManagerTest.php` (lines 1–75).

**Class shell + DI in setUp** — lines 1–24:
```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\OAuth;

use App\Service\OAuth\KeyManager;
use Cake\TestSuite\TestCase;

class KeyManagerTest extends TestCase
{
    private KeyManager $km;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure OAUTH_KID is set so getPublicJwk() returns a deterministic kid.
        putenv('OAUTH_KID=test-kid-1');
        $_ENV['OAUTH_KID'] = 'test-kid-1';
        $this->km = new KeyManager(
            TESTS . 'Fixture' . DS . 'keys' . DS . 'private.key',
            TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key'
        );
    }
```
**Note:** typed property `private KeyManager $km;` is used here — this is fine because `KeyManager` is a leaf class with no inherited property collision. `$fixtures` is the special case (parent class declares untyped).

**Deterministic table-driven assertions** — lines 25–44 (assertions on each JWK field):
```php
public function testGetPublicJwkReturnsEs256Structure(): void
{
    $jwk = $this->km->getPublicJwk();
    $this->assertSame('EC', $jwk['kty']);
    $this->assertSame('P-256', $jwk['crv']);
    // ...
}
```
For `SlugDeriverTest`, write similar one-method-per-fact tests:
- `testDomainPrefixExtraction()` — `'satie.bsky.social'` → `'satie'`
- `testNonAsciiHandleFallsBackToDidHash()` — `'_atproto.example.com'` → `'user-' . substr(hash('sha256', $did), 0, 8)`
- `testEmptyHandleFallsBackToDidHash()` — `''` → fallback
- `testNormalizationStripsCase()` — `'SATIE.bsky.social'` → `'satie'` (or whatever the rule decides)

**Negative-path test with expectException** — lines 69–74:
```php
public function testMissingPrivateKeyThrowsRuntime(): void
{
    $km = new KeyManager('/nonexistent/private.key', TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key');
    $this->expectException(\RuntimeException::class);
    $km->getPrivateKey();
}
```
For SlugDeriver, throw `RuntimeException` on impossible inputs (e.g., empty DID + empty handle simultaneously) and assert via `expectException`.

---

### 15. `tests/TestCase/Service/Message/SsrJudgeTest.php` (test, unit)

**Analog:** `tests/TestCase/Service/OAuth/Bluesky/DpopServiceTest.php` (lines 1–131) — closest deterministic-transform test.

**Setup pattern** — lines 12–25:
```php
class DpopServiceTest extends TestCase
{
    private DpopService $svc;
    private KeyManager $km;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('OAUTH_KID=test-kid-1');
        $_ENV['OAUTH_KID'] = 'test-kid-1';
        $this->km = new KeyManager(
            TESTS . 'Fixture' . DS . 'keys' . DS . 'private.key',
            TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key'
        );
        $this->svc = new DpopService($this->km);
    }
```
For `SsrJudgeTest`, set `Configure::write('Security.serverSecret', 'test-secret-32-chars-deterministic');` instead of OAUTH_KID.

**Determinism assertion pattern** — lines 88–96 (`testEveryProofHasUniqueJti` for the inverse — uniqueness):
```php
public function testEveryProofHasUniqueJti(): void
{
    $jtis = [];
    for ($i = 0; $i < 10; $i++) {
        $payload = json_decode($this->b64udec(explode('.', $this->svc->createProof('POST', 'https://x/y'))[1]), true);
        $jtis[] = $payload['jti'];
    }
    $this->assertCount(10, array_unique($jtis), 'Every DPoP proof MUST have a distinct jti (T-02-02-04).');
}
```
For `SsrJudgeTest`, the **inverse** — same inputs → same seed → same is_ssr (deterministic, F2 audit):
```php
public function testJudgeIsDeterministicForSameInputs(): void
{
    $r1 = $this->svc->judge($messageId, $createdAt, '0.500');
    $r2 = $this->svc->judge($messageId, $createdAt, '0.500');
    $this->assertSame($r1['ssr_seed'], $r2['ssr_seed']);
    $this->assertSame($r1['is_ssr'], $r2['is_ssr']);
}
```

**Table-driven boundary tests** — invent fixed `(messageId, createdAt, prob)` triples chosen so `hexdec(substr(seed, 0, 8)) / 0xFFFFFFFF` lands on known sides of the threshold. Mirror the assertion style of `testAthClaimAddedWhenAccessTokenProvided` (line 64–72) — compute the expected value in the test and `assertSame`:
```php
$expected = rtrim(strtr(base64_encode(hash('sha256', 'my_token', true)), '+/', '-_'), '=');
$this->assertSame($expected, $payload['ath']);
```
For SSR: pre-compute `$expectedSeed = hash('sha256', $secret . $msgId . $createdAt);` then assert `assertSame($expectedSeed, $r['ssr_seed']);` — exact same shape.

---

## Shared Patterns

### Auth gate (controllers)

**Source:** `src/Controller/AppController.php` lines 41–53 (loads `Authentication.Authentication` component) + `src/Controller/AuthController.php` lines 30–35 (`allowUnauthenticated` per-action) + `src/Controller/UsersController.php` lines 25–35 (defense-in-depth identity null check).

**Apply to:** All Phase 3 controllers (`MessagesController`, `InboxesController`, `BlocksController`, modified `UsersController`).

```php
public function initialize(): void
{
    parent::initialize();
    // Pre-auth actions: send (D-13 lets unauthenticated users compose).
    $this->Authentication->allowUnauthenticated(['send']);
}

public function action(): ?Response
{
    $identity = $this->Authentication->getIdentity();
    if ($identity === null) {
        return $this->redirect('/');
    }
    $userId = is_scalar($identity->getIdentifier()) ? (string)$identity->getIdentifier() : '';
    if ($userId === '') {
        return $this->redirect('/');
    }
    // ... domain ...
}
```

CSRF middleware is wired globally at `src/Application.php` (Plan 02-01). Do NOT call `loadComponent('FormProtection')` — it's commented out in `AppController::initialize()` (line 59). All POST actions are auto-CSRF'd.

---

### Error handling (controllers)

**Source:** `src/Controller/AuthController.php` lines 44–70 + `src/Controller/OauthController.php` lines 200–219 (multi-branch RuntimeException dispatch).

**Apply to:** `MessagesController::send`, `InboxesController::update`. NOT for the 501 stubs (those use `$this->response->withStatus(501)` directly).

```php
try {
    // domain calls
    return $this->redirect('/dashboard');
} catch (RuntimeException $e) {
    $msg = $e->getMessage();
    if (str_contains($msg, 'SLUG_NOT_FOUND')) {
        throw new NotFoundException();  // → CakePHP standard error400.php (D-36)
    }
    if (str_contains($msg, 'BODY_TOO_LONG')) {
        $this->Flash->error(__('本文は 2000 文字以内で入力してください。'));
        return $this->redirect(['action' => 'send', $slug]);
    }
    $this->Flash->error(__('送信に失敗しました。しばらくしてから再度お試しください。'));
    return $this->redirect('/');
}
```

**Use `NotFoundException` for missing slug/message** (matches D-36 — CakePHP's built-in 404 path renders `templates/Error/error400.php`).

---

### Validation (table layer)

**Source:** `src/Model/Table/InboxesTable.php` lines 68–103 (existing rules) + `src/Model/Table/MessagesTable.php` lines 75–149 (existing rules).

**Apply to:** All table modifications. The Phase 1-baked validators already cover most fields; Phase 3 must **add**:

For `InboxesTable`:
```php
$validator
    ->scalar('slug')
    ->maxLength('slug', 32)
    ->minLength('slug', 3)  // matches inboxes_slug_format CHECK
    ->add('slug', 'format', [
        'rule' => ['custom', '/^[a-zA-Z0-9_-]{3,32}$/'],
        'message' => 'スラッグは英数字とハイフン・アンダースコアのみ、3〜32文字で指定してください。',
    ]);
```

For `MessagesTable`:
```php
$validator
    ->maxLength('body', 2000)  // D-16 server-side enforcement, mb_strlen-aware via custom rule
    ->add('body', 'mbLength', [
        'rule' => function ($value) {
            return mb_strlen((string)$value) <= 2000;
        },
        'message' => '本文は 2000 文字以内で入力してください。',
    ]);
```

`accessibleFields` per call (UserIdentitiesTable.php lines 198–214) — overrides Inbox/Message entity `_accessible` for that single call.

---

### Service-class shape

**Source:** `src/Service/OAuth/KeyManager.php` lines 1–17 (header + namespace + class declaration).

**Apply to:** `src/Service/Inbox/SlugDeriver.php`, `src/Service/Message/SsrJudge.php`.

```php
<?php
declare(strict_types=1);

namespace App\Service\Inbox;

use Cake\Core\Configure;
use RuntimeException;

/**
 * One-line summary, then a paragraph explaining responsibility, inputs/outputs,
 * and any cross-references (CONTEXT D-XX, RESEARCH section).
 *
 * Tests inject overrides via constructor args so default-source artifacts (Configure
 * keys / DB tables) don't need to exist in CI.
 */
final class SlugDeriver
{
    public function __construct(
        // constructor-promoted, no-default required + Configure-fallback optional
    ) {
    }
}
```

`final class` is the Phase 2 norm (`KeyManager`, `DpopService`, `ClientJwtService`, `DidResolver`, `TokenEncryptionService` all are `final`). Continue the convention.

---

### Test setup (controller integration)

**Source:** `tests/TestCase/Controller/AuthControllerTest.php` lines 22–54 (fixtures + setUp + tearDown) + `OauthControllerCallbackTest.php` lines 28–61 (identical block).

**Apply to:** All new `tests/TestCase/Controller/*Test.php`.

**KEY GOTCHA — sticky-note from Phase 2 Executor (STATE.md):**
- `protected $fixtures` MUST be untyped. NOT `protected array $fixtures` — typed property collides with the untyped declaration in `Cake\TestSuite\Fixture\TestFixture`'s ancestor.
- The `@var array<int, string>` docblock on the property satisfies PHPStan level 8 without forcing the type at runtime.

---

### Test setup (service unit)

**Source:** `tests/TestCase/Service/OAuth/KeyManagerTest.php` lines 9–24 (typed private property + putenv + DI in setUp).

**Apply to:** All new `tests/TestCase/Service/**/*Test.php`. Typed private properties are FINE for service tests — only the controller `$fixtures` array has the typed-property quirk.

---

### Migration shape

**Source:** `config/Migrations/20260422120003_CreateInboxes.php` lines 1–132.

**Apply to:** `config/Migrations/<datestamp>_AddSlugPreviousToInboxes.php` (or whichever DDL the planner picks for D-04).

- File header: `<?php` + `declare(strict_types=1);` + `use Migrations\AbstractMigration;` (no namespace — this is the global namespace by Phinx convention).
- Class extends `AbstractMigration`.
- For new tables: `public $autoId = false;` + explicit UUID PK.
- For ALTER: `public function up(): void` calling `$this->table('inboxes')->addColumn(...)->update();` (NOT `->save()` for existing tables).
- For CHECK constraints: raw SQL via `$this->execute(<<<SQL ... SQL);` with `\$` escape inside the heredoc.
- Constraint names use **snake_case without `_check` suffix** to match DB-SCHEMA.md v0.2 (deviation noted in `CreateInboxes.php` lines 18–20).
- `down()` mirrors `up()` for reversibility.

---

### Template shape

**Source:** `templates/Pages/home.php` lines 1–22 + `templates/Users/dashboard.php` lines 1–18.

**Apply to:** All new `templates/Messages/*.php`, `templates/Inboxes/*.php`, modified `templates/Users/dashboard.php`.

- File header: `<?php` open, then `/** @var \App\View\AppView $this */` + `@var` for each `$this->set('foo', ...)` in the controller.
- `$this->assign('title', '...');` to set browser-tab title.
- Outer `<div class="...-page">` for CSS scoping (matches `home-page`, `dashboard-page`, `callback-page` already in `tamabox.css`).
- Forms: `<?= $this->Form->create(null, ['url' => [...], 'type' => 'post']) ?>` (gives free CSRF) ... `<?= $this->Form->end() ?>`.
- Output escaping: `<?= h($value) ?>` ALWAYS for user data; `<?= nl2br(h($value)) ?>` for line-break-preserving text (welcome_message, body).
- Comments in templates: Japanese OK (per CONVENTIONS.md — Japanese is acceptable in design docs / templates; English in source-level docblocks).

---

### CSS — `webroot/css/tamabox.css` extension

**Source:** `webroot/css/tamabox.css` lines 1–218 (existing Phase 2 baseline).

**Apply to:** Append new rules at the bottom; do NOT modify existing rules. Phase 2 token system at `:root` (lines 4–26) is the source of truth. UI-SPEC §3 says to add `--avatar-sm: 24px;` and `--avatar-lg: 64px;` — insert these **inside the existing `:root` block** (lines 4–26) keeping the `--space-*` ordering convention.

New section heading comments: copy the style of line 1 (`/* tamabox.css — Phase 2 Bluesky OAuth UI. ... */`) and line 66 (`/* HeaderBar */`):
```css
/* Phase 3 — Receive list, send form, SSR reveal, settings form. */

/* Receive list */
.message-row { ... }

/* Send form */
.send-form { ... }
```

---

### Fixture shape

**Source:** `tests/Fixture/InboxesFixture.php` lines 1–35 + `tests/Fixture/MessagesFixture.php` lines 1–43 + `tests/Fixture/UsersFixture.php` lines 1–38.

**Apply to:** Modified `InboxesFixture.php` (add 2nd record), `MessagesFixture.php` (add SSR-hit / opened variants).

```php
public function init(): void
{
    $this->records = [
        [/* existing record */],
        [/* new record */ 'id' => '...', 'inbox_id' => '...', /* etc */],
    ];
    parent::init();
}
```
- Fixed UUIDs (`11111111-1111-1111-1111-111111111111`, `22222222-...`) for deterministic test references.
- Boolean fields: `0` / `1` (NOT `false` / `true`) — matches MySQL TINYINT round-trip.
- `ssr_probability` as PHP float (e.g., `0.100`) — Cake ORM marshals to DECIMAL.
- `ssr_seed` 64-char hex string (e.g., `'a' . str_repeat('0', 63)` per MessagesFixture line 30).
- Datetimes as `'2026-04-22 12:00:00'` strings (no timezone — local time).
- `sender_avatar_url_snapshot` / `sender_profile_url_snapshot` allowed `null`.

Phase 1 deviation #1 (referenced in CONTEXT.md "Test fixture" line 181): records are hand-written and **not regenerated by `bake fixture`** — keep the hand-edited shape across additions.

---

## No Analog Found

| File | Role | Reason |
|---|---|---|
| `webroot/img/default-avatar.svg` | static asset (SVG) | First in-tree SVG asset (existing `webroot/img/cake.logo.svg` is a CakePHP boilerplate logo, not a UI element). Use the executor example in **UI-SPEC §7** lines 372–377 as the source of truth. Pattern: `viewBox="0 0 64 64"`, hex literals (no CSS vars in SVG), three shapes (background circle, head circle, shoulder path). |

---

## Metadata

**Analog search scope:** `/home/claude/projects/tamabox/src/`, `/home/claude/projects/tamabox/templates/`, `/home/claude/projects/tamabox/tests/`, `/home/claude/projects/tamabox/config/Migrations/`, `/home/claude/projects/tamabox/webroot/`.

**Files scanned:**
- 6 controllers (`AppController`, `AuthController`, `ErrorController`, `OauthController`, `PagesController`, `UsersController`)
- 6 table classes (`Blocks`, `Inboxes`, `Messages`, `Reports`, `UserIdentities`, `Users`)
- 6 entity classes
- 5 service classes (`KeyManager`, `OAuthProviderInterface`, `TokenEncryptionService`, `BlueskyOAuthClient`, `ClientJwtService`, `DidResolver`, `DpopService`)
- 4 controller integration tests (`AuthController`, `OauthControllerCallback`, `OauthController`, `Pages`)
- 4 service unit tests (`KeyManager`, `TokenEncryptionService`, `ClientJwtService`, `DidResolver`, `DpopService`, `BlueskyOAuthClient`)
- 6 model table unit tests
- 6 migrations
- 4 templates (`Pages/home`, `Users/dashboard`, `Auth/callback`, `element/flash/info`)
- 1 CSS file (`tamabox.css`)
- 6 fixtures + `tests/Fixture/keys/`
- `config/routes.php`

**Pattern extraction date:** 2026-04-24

**Phase 2 sticky notes carried into Phase 3 (from STATE.md `## Accumulated Context`):**
1. `protected $fixtures` MUST be untyped (typed-property collision)
2. `Authentication->getIdentity()` returns `?IdentityInterface`; `getIdentifier()` returns scalar — narrow to string defensively
3. `queryString()` / `sessionString()` helpers for phpstan level 8 narrowing of `$request->getQuery()` and session reads
4. `Client::addMockResponse()` / `Client::clearMockResponses()` in setUp/tearDown — kept defensively even when no mocks expected
5. `refreshTokenIfExpired()` deferred to Phase 4 (D-30)

---

*Pattern map written by gsd-pattern-mapper 2026-04-24, consumed by gsd-planner.*
