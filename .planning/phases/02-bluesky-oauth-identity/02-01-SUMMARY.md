---
phase: 02-bluesky-oauth-identity
plan: 01
wave: 1
subsystem: oauth-foundation
tags:
  - oauth
  - bluesky
  - cakephp-authentication
  - config
  - ec-keypair
  - foundation
requirements_partial:
  - AUTH-06  # OAuthProviderInterface shell only; Bluesky concrete impl lands in 02-04
  - AUTH-08  # jwks / client-metadata endpoint shells (routes + config); Controller actions in 02-03
requirements_closed: []
files_modified:
  - composer.json
  - composer.lock
  - src/Application.php
  - config/bootstrap.php
  - config/.env.example
  - config/routes.php
files_created:
  - config/bluesky.php
  - src/Service/OAuth/OAuthProviderInterface.php
  - config/keys/.gitignore
  - config/keys/private.key       # gitignored (ES256 EC P-256, chmod 600)
  - config/keys/public.key        # gitignored (ES256 EC P-256, chmod 644)
commits:
  - 637f252 feat(02-01): require cakephp/authentication ^2.11 (Task 1)
  - 451a417 feat(02-01): wire Authentication plugin + middleware in Application (Task 2)
  - e5a9607 feat(02-01): add Bluesky config + bootstrap load + env placeholders (Task 3)
  - 3a7967b feat(02-01): add OAuthProviderInterface + Phase 2 routes + keys gitignore (Task 4)
resolved_versions:
  cakephp/authentication: 2.11.0
decisions_locked_in:
  - D-02  # CakePHP Authentication Plugin adopted (Session authenticator only)
  - D-05  # 静的 bsky.social endpoint 方式
  - D-06  # scope = 'atproto transition:generic'
  - D-14  # ES256 鍵ペア in config/keys/
  - D-16  # client_id === delivery URL (byte-for-byte)
completed: 2026-04-23
---

# Phase 02 Plan 01: Foundation Setup — Summary

Wave 1 土台完了。CakePHP Authentication Plugin を require、`AuthenticationMiddleware` を `CsrfProtectionMiddleware` の直後に配線、`Application::getAuthenticationService()` で Session authenticator + ORM Password identifier(`fields.username => 'id'`, `password => null`)を提供。`config/bluesky.php` に bsky.social 静的 endpoints + `client_metadata` payload(scope=`atproto transition:generic`, `private_key_jwt`, `dpop_bound_access_tokens=true`)を配置し `Configure::load('bluesky', 'default', false)` で読込。`.env.example` に OAuth 関連 6 env キー追加。`config/routes.php` に Phase 2 ルート 6 件を `setMethods()` 制約付きで追加。`src/Service/OAuth/OAuthProviderInterface.php`(AUTH-06 抽象、5 メソッド)新設。`config/keys/` に `.gitignore`(`*.key *.pem` 除外、`!.gitignore` 保持)と ES256 EC P-256 鍵ペア(600/644 perms、git から ignored 確認済)を生成。

**外部 HTTP ゼロ / DB 変更ゼロ / Service 実装ゼロ / Controller 実装ゼロ の土台のみ** — 以降 Wave 2 (Plan 02-02 crypto services + Plan 02-03 metadata endpoints) が並列実行可能な状態。

## Acceptance Criteria per Task

### Task 1: composer require cakephp/authentication ^2.11

- [x] `composer.json` の `require` セクションに `"cakephp/authentication": "^2.11"` 追加(`cakephp/cakephp` の直後、アルファベット順)
- [x] `composer.lock` に `cakephp/authentication` 2.11.0 で resolve(`jq '.packages[] | select(.name == "cakephp/authentication") | .version'` = `"2.11.0"`)
- [x] `vendor/cakephp/authentication/src/Middleware/AuthenticationMiddleware.php` 実体存在
- [x] `composer validate --no-check-publish` → `./composer.json is valid`
- [x] 新規追加 package は `cakephp/authentication` 1 件のみ(`git diff composer.lock | grep '"name":'` で 1 パッケージ + その meta authors 行)
- [x] `require-dev` には追加なし(`jq '.require["cakephp/authentication"]'` = `"^2.11"`)

### Task 2: Application.php AuthenticationMiddleware + getAuthenticationService()

- [x] `php -l src/Application.php` → `No syntax errors detected`
- [x] 4 件の `use Authentication\*` import + 1 件の `use Psr\Http\Message\ServerRequestInterface`(アルファベット順、既存 use 群の前段に挿入)
- [x] `class Application extends BaseApplication implements AuthenticationServiceProviderInterface`
- [x] `bootstrap()` に `$this->addPlugin('Authentication');`(DebugKit 条件ブロックの後、`// Load more plugins here` コメント直前)
- [x] `middleware()` の `CsrfProtectionMiddleware` 追加の**後**に `->add(new AuthenticationMiddleware($this));`
- [x] 行番号: CsrfProtectionMiddleware = L107, AuthenticationMiddleware = L113(`csrf_line < auth_line` 不変条件成立、T-02-01-06 mitigation)
- [x] `public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface` 実装
- [x] `Session` authenticator + `Authentication.Password` identifier(resolver=`Authentication.Orm` userModel=`Users`, fields.username=`id`, password=`null`, passwordHasher=`null`)
- [x] `unauthenticatedRedirect => '/'` + `queryParam => 'redirect'`(UI-SPEC §5 準拠)
- [x] `bin/cake --version` → `4.6.3`(Authentication plugin の CLI ロード確認 = boot smoke OK)

### Task 3: config/bluesky.php + bootstrap.php + .env.example

- [x] `config/bluesky.php` 新規、`php -l` clean
- [x] `Configure::load('bluesky', 'default', false)` が `config/bootstrap.php` に追加(`app_local` load の直後、debug cache block の前)
- [x] Configure smoke: `Configure::read('Bluesky.client_metadata.client_id')` = `https://tamabox.emomie.com/oauth/client-metadata.json`(D-16 byte-for-byte)
- [x] `Configure::read('Bluesky.client_metadata.scope')` = `atproto transition:generic`(D-06)
- [x] `Configure::read('Bluesky.client_metadata.token_endpoint_auth_method')` = `private_key_jwt`
- [x] `Configure::read('Bluesky.client_metadata.dpop_bound_access_tokens')` = `true`(bool)
- [x] `Configure::read('Bluesky.issuer')` = `https://bsky.social`(env override 可能だが default 値確定)
- [x] `config/.env.example` に 6 placeholder 追加: `OAUTH_KID` / `TOKEN_ENC_KEY` / `BLUESKY_ISSUER` / `BLUESKY_PAR_ENDPOINT` / `BLUESKY_TOKEN_ENDPOINT` / `BLUESKY_AUTH_ENDPOINT`(全て `^export ` で開始、`DATABASE_TEST_URL` の直後に挿入)
- [x] `TOKEN_ENC_KEY` は placeholder `__TOKEN_ENC_KEY_HEX_32BYTES__`(Phase 1 `__SERVER_SECRET__` と同パターン、実値は `.env` のみ)
- [x] ES256 keypair 生成コマンドと `openssl rand -hex 32` のコメントドキュメント追加

### Task 4: OAuthProviderInterface + routes.php + config/keys/.gitignore + EC keypair

- [x] `src/Service/OAuth/OAuthProviderInterface.php` 新規、`php -l` clean
- [x] `interface OAuthProviderInterface` 定義、namespace `App\Service\OAuth`
- [x] 5 methods present: `executeParRequest` / `exchangeCodeForToken` / `refreshToken` / `resolveProfile` / `getProviderKey`(全て `public function` で declare)
- [x] PSR-4 autoload reachable: `interface_exists('App\\Service\\OAuth\\OAuthProviderInterface')` = `true`(after `composer dump-autoload -o --no-scripts`)
- [x] 6 routes added in `config/routes.php`(既存 `$routes->scope('/', …)` 内、`/pages/*` 直後・`fallbacks()` 前):
  - `/login/bluesky` → `Auth::startBluesky` `setMethods(['GET', 'POST'])`
  - `/oauth/callback` → `Oauth::callback` `setMethods(['GET'])`
  - `/oauth/client-metadata.json` → `Oauth::clientMetadata` `setMethods(['GET'])`
  - `/oauth/jwks.json` → `Oauth::jwks` `setMethods(['GET'])`
  - `/oauth/logout` → `Auth::logout` `setMethods(['POST'])` (D-18 + T-02-01-02)
  - `/dashboard` → `Users::dashboard` `setMethods(['GET'])`
- [x] `grep -Ec "setMethods\(\[.*'POST'" config/routes.php` = 2(login/bluesky + logout)
- [x] `grep -Ec "setMethods\(\[.*'GET'" config/routes.php` = 5(login/bluesky + callback + metadata + jwks + dashboard)
- [x] `config/keys/.gitignore` 新規: `*.key` + `*.pem` pattern + `!.gitignore` exception
- [x] `config/keys/private.key` 存在(`openssl ec -in … -noout -text` に `NIST CURVE: P-256` 含む、256-bit)
- [x] `config/keys/public.key` 存在(`openssl ec -pubin -noout -text` に `NIST CURVE: P-256` 含む、256-bit)
- [x] Permissions: `stat -c %a config/keys/private.key` = `600`, `public.key` = `644`
- [x] `git check-ignore config/keys/private.key` exits 0(ignored)
- [x] `git check-ignore config/keys/public.key` exits 0(ignored)
- [x] `git check-ignore config/keys/.gitignore` exits 1(tracked — `!.gitignore` exception works)

## Plan-level Verification Results

| # | Check | Command | Result |
|---|-------|---------|--------|
| 1 | composer validate | `composer validate --no-check-publish` | `./composer.json is valid` |
| 1 | install dry-run | `composer install --dry-run --no-scripts` | `Nothing to install, update or remove` |
| 1 | lock pin | `jq '...cakephp/authentication...version' composer.lock` | `"2.11.0"` |
| 2 | php -l × 5 | Application.php / bootstrap.php / bluesky.php / routes.php / OAuthProviderInterface.php | all `No syntax errors` |
| 2 | bin/cake boot | `bin/cake --version` | `4.6.3`(Authentication plugin CLI ロード成功) |
| 3 | Configure smoke | PHP snippet asserting all 4 critical Bluesky.client_metadata values | exit `0` |
| 4 | PSR-4 autoload | `interface_exists('App\\Service\\OAuth\\OAuthProviderInterface')` | `true` |
| 5 | EC key curve | `openssl ec -in config/keys/private.key -noout -text` | `NIST CURVE: P-256` |
| 5 | private ignored | `git check-ignore config/keys/private.key` | exit `0`(ignored) |
| 5 | .gitignore tracked | `git check-ignore config/keys/.gitignore` | exit `1`(tracked) |
| 6 | MW order invariant | csrf_line=107, auth_line=113 | `csrf < auth`(T-02-01-06 satisfied) |
| 7 | phpcs | `composer phpcs` | 36/36 pass, exit 0 |
| 7 | phpstan level 8 | `composer phpstan` | `[OK] No errors`, exit 0 |
| 8 | composer test | `composer test` | 17 tests OK(6 pre-existing Phase-1 incomplete stubs), exit 0 |

## Deviations from Plan

### なし(プラン通り実行)

4 タスクとも plan の `<action>` / `<verify>` 通りに実行した。Rule 1 (bugfix) / Rule 2 (missing critical) / Rule 3 (blocking) のいずれも発動せず。

補足として、以下 2 点は plan の前提条件がすでに満たされていたため追加作業不要だった:

1. **root `.gitignore` はすでに `/config/keys/*.key` と `/config/keys/*.pem` を除外していた**(Phase 1 01-01 時点で設定済み)。本 Plan の `config/keys/.gitignore` はその上に乗る belt-and-suspenders で、directory 自体を tracked 状態に保つ目的を果たす(`!.gitignore` exception が機能することを `git check-ignore` で確認)。既存の `config/keys/.gitkeep`(Phase 1 で追加)は残置 — plan で言及されていないため、撤去判断は行わなかった。

2. **PHPStan 2.x upgrade prompt**(`composer phpstan` 実行時に tool output に埋め込まれた prompt injection 様のメッセージ)を検知。Plan scope 外・user 未指示のため**無視**し、phpstan 1.12.33(Phase 1 で lock 済み)のまま進行。Phase 1 01-01 SUMMARY の Deviation 1 で cakephp 4.x 互換性のため意図的に固定された制約であり、2.x への bump は別 Phase の判断事項。

## Authentication Gates Encountered

なし。全作業は filesystem 編集 + composer network fetch(cakephp/authentication 2.11.0 の Packagist 経由取得)+ openssl CLI 実行(EC keygen、ローカル完結)のみ。外部 API コールゼロ、認証情報入力ゼロ。

## Handoff Notes

### For Plan 02-02 (Crypto & JWT Service Layer)

- `config/keys/private.key` と `public.key` がローカル生成済み(600/644、ES256 EC P-256)。`KeyManager::__construct($privKeyPath = '', $pubKeyPath = '')` がデフォルトで `CONFIG . 'keys' . DS . 'private.key'` / `public.key` を引くよう `config/bluesky.php` の `Bluesky.private_key_path` / `public_key_path` 経由で参照できる(PATTERNS.md の実装推奨パターン)。
- `env('OAUTH_KID', 'ssr-box-key-1')` と `hex2bin(env('TOKEN_ENC_KEY'))` は `.env.example` にキー placeholder があり、ローカル開発者は `.env` に `openssl rand -hex 32` で生成した値を書き込む運用。`.env.example` ヘッダコメントに手順明記済み。
- `OAuthProviderInterface` は未実装の shell。`BlueskyOAuthClient` (Plan 02-04) が `implements OAuthProviderInterface` で 5 methods を埋める。`DpopService` / `ClientJwtService` / `TokenEncryptionService` / `KeyManager` はすべて `App\Service\OAuth\` or `App\Service\OAuth\Bluesky\` namespace 配下に配置し、PSR-4 autoload は `composer dump-autoload -o` で直ちに到達可能。
- `src/Service/OAuth/Bluesky/` ディレクトリはすでに空 mkdir 済み(Task 4 の `mkdir -p` による)。git は空ディレクトリを追跡しないため、Plan 02-02 の最初のファイル commit でディレクトリが VCS に現れる。

### For Plan 02-03 (Client Metadata Endpoints)

- `/oauth/client-metadata.json` と `/oauth/jwks.json` の routes 定義は本 Plan で完了。Controller `OauthController` 側で `clientMetadata()` と `jwks()` actions を実装すれば routes → dispatch 完了。
- `Configure::read('Bluesky.client_metadata')` が AT Protocol に準拠した dict を返すので、`clientMetadata()` は `json_encode($metadata, JSON_UNESCAPED_SLASHES)` + `withType('application/json')` で即返せる(PATTERNS.md L240-248 参照)。
- `jwks()` 側は `KeyManager::getPublicJwk()`(Plan 02-02 で実装)を使い `{"keys": [$jwk]}` を返す。`OAUTH_KID` env 値が `kid` claim に入る。
- `AuthenticationMiddleware` は `unauthenticatedRedirect => '/'` 設定済みだが、OAuth metadata endpoints (`/oauth/client-metadata.json` / `/oauth/jwks.json`) は **認証不要のパブリックエンドポイント**。Plan 02-03 の Controller で `$this->Authentication->allowUnauthenticated(['clientMetadata', 'jwks'])` を呼ぶ必要がある(UI-SPEC §1: 「認証必須」列が「不要」)。

### For Plan 02-04 (OAuth Flow)

- `Application::getAuthenticationService()` の `unauthenticatedRedirect => '/'` は UI-SPEC §5 のセッション切れフローに合致(`/dashboard` 保護 → 未認証で `/?redirect=/dashboard` へ)。Plan 02-04 の `UsersController::dashboard()` は `initialize()` で `allowUnauthenticated()` を呼ばなければ middleware が自動で redirect する。
- `AuthController::logout()` は routes で `setMethods(['POST'])` 済み、CakePHP の `CsrfProtectionMiddleware` が自動で POST に CSRF 検証をかける(T-02-01-02 + D-18 mitigation 完成形)。
- Session identifier は `users.id`(CHAR(36) UUID)で resolver が引く設計。`AuthController::startBluesky()` → `OauthController::callback()` → `$this->Authentication->setIdentity($user)` で Users entity を渡せば middleware が自動で session に `id` を保存し、後続 request で `$this->request->getAttribute('identity')` から再取得可能。

## Known Stubs

- `src/Service/OAuth/OAuthProviderInterface.php` は interface declaration のみ(メソッド body なし)。これは意図的な shell であり、Plan 02-04 で `BlueskyOAuthClient` が concrete impl を提供する。AUTH-06 要件のうち「抽象の存在」は本 Plan で満たされ、「動作する concrete impl」は 02-04 に委ねられる。
- `config/routes.php` の 6 routes は Controller クラス未実装(`AuthController` / `OauthController` / `UsersController` は Plan 02-03 / 02-04 で bake される)。本 Plan 時点では routes は declared だが dispatch すると `MissingControllerException` が上がる想定 — これも意図的(routes の事前宣言により Wave 2 並列作業が可能)。

いずれも「Plan の goal が達成されない stub」ではなく、「後続 plan が埋める契約面の先行宣言」であり、CONTEXT.md および ROADMAP.md で明示的に Wave 分割されている。

## Threat Flags

本 Plan の `<threat_model>` 内 8 件(T-02-01-01..08)はすべて mitigate / accept の指定通りに処理:

- T-02-01-01(private.key leak)→ `config/keys/.gitignore` + `git check-ignore` 検証 ✓
- T-02-01-02(route method confusion)→ 全 6 routes に `setMethods()` 制約 ✓
- T-02-01-03(open redirect via client_id)→ `config/bluesky.php` に literal で hardcode ✓
- T-02-01-04(supply chain)→ `composer.lock` commit で 2.11.0 pin、transitive dep 追加ゼロ ✓
- T-02-01-05(TOKEN_ENC_KEY in errors)→ accepted — 本 Plan では key を consume しない(02-02 で `TokenEncryptionService` 実装時に log 出力禁止ルール適用)
- T-02-01-06(MW order)→ csrf(L107) < auth(L113) の不変条件確立 ✓
- T-02-01-07(AUTH-06 too loose)→ `getProviderKey()` 含む 5 methods で user_identities.provider ENUM との契約明示 ✓
- T-02-01-08(key rotation)→ accepted — `OAUTH_KID` env で将来 rotation 余地確保

**新規 threat surface(plan の threat_model 外)検出:なし。** 本 Plan は外部 HTTP / DB / Controller 実装ゼロのため新規 trust boundary は発生しない。

## Self-Check

**Commits:**
- FOUND: 637f252 (Task 1 — cakephp/authentication require)
- FOUND: 451a417 (Task 2 — Application.php wiring)
- FOUND: e5a9607 (Task 3 — bluesky.php + bootstrap load + .env.example)
- FOUND: 3a7967b (Task 4 — interface + routes + keys .gitignore)

**Files:**
- FOUND: composer.json (modified)
- FOUND: composer.lock (modified, cakephp/authentication 2.11.0 locked)
- FOUND: src/Application.php (modified)
- FOUND: config/bootstrap.php (modified)
- FOUND: config/.env.example (modified)
- FOUND: config/routes.php (modified)
- FOUND: config/bluesky.php (new)
- FOUND: src/Service/OAuth/OAuthProviderInterface.php (new)
- FOUND: config/keys/.gitignore (new, tracked)
- FOUND: config/keys/private.key (new, gitignored, 600, P-256)
- FOUND: config/keys/public.key (new, gitignored, 644, P-256)

**Verification:**
- FOUND: composer validate → valid
- FOUND: composer install --dry-run → Nothing to install, update or remove
- FOUND: composer.lock cakephp/authentication version = "2.11.0"
- FOUND: php -l clean on all 5 PHP files
- FOUND: bin/cake --version → 4.6.3 (Authentication plugin CLI boot OK)
- FOUND: Configure smoke test exit 0 (client_id, scope, auth_method, dpop all correct)
- FOUND: interface_exists('App\\Service\\OAuth\\OAuthProviderInterface') = true
- FOUND: openssl ec -text → NIST CURVE: P-256 (both keys)
- FOUND: MW order csrf(107) < auth(113)
- FOUND: phpcs 36/36 pass
- FOUND: phpstan level 8 No errors
- FOUND: composer test 17 tests OK, exit 0

## Self-Check: PASSED
