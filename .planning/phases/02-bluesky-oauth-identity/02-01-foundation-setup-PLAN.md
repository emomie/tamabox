---
phase: 02-bluesky-oauth-identity
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - composer.json
  - composer.lock
  - config/bootstrap.php
  - config/bluesky.php
  - config/.env.example
  - src/Application.php
  - config/routes.php
  - src/Service/OAuth/OAuthProviderInterface.php
  - config/keys/.gitignore
autonomous: true
requirements:
  - AUTH-06
  - AUTH-08
tags:
  - oauth
  - bluesky
  - foundation
  - cakephp-authentication
  - config
  - ec-keypair

must_haves:
  truths:
    - "composer install succeeds with cakephp/authentication ^2.11 resolved"
    - "Application::middleware() pipeline has AuthenticationMiddleware inserted after CsrfProtectionMiddleware"
    - "Application::getAuthenticationService() returns an AuthenticationService with a Session authenticator + OrmIdentifier resolving by users.id"
    - "Configure::read('Bluesky.issuer') returns 'https://bsky.social' by default (from env-overridable config/bluesky.php)"
    - "Configure::read('Bluesky.client_metadata.client_id') equals 'https://tamabox.emomie.com/oauth/client-metadata.json' (exact byte-for-byte match with delivery URL)"
    - "Configure::read('Bluesky.client_metadata.scope') equals 'atproto transition:generic' (D-06)"
    - "config/.env.example declares OAUTH_KID, TOKEN_ENC_KEY, BLUESKY_ISSUER, BLUESKY_PAR_ENDPOINT, BLUESKY_TOKEN_ENDPOINT, BLUESKY_AUTH_ENDPOINT placeholders"
    - "config/keys/ directory is git-tracked but .gitignores all *.key files so private.key/public.key never leak"
    - "config/routes.php declares 6 new routes (/login/bluesky, /oauth/callback, /oauth/client-metadata.json, /oauth/jwks.json, /oauth/logout, /dashboard) with correct HTTP methods"
    - "src/Service/OAuth/OAuthProviderInterface.php defines 5 methods (executeParRequest / exchangeCodeForToken / refreshToken / resolveProfile / getProviderKey) for AUTH-06 multi-provider abstraction"
    - "config/keys/private.key and public.key exist on dev machine (ES256 EC P-256 pair generated via openssl ecparam) — file permissions 600/644"
  artifacts:
    - path: "composer.json"
      provides: "cakephp/authentication ^2.11 added to require"
      contains: '"cakephp/authentication"'
    - path: "composer.lock"
      provides: "Resolved cakephp/authentication ^2.11.x pinned"
      contains: "cakephp/authentication"
    - path: "config/bluesky.php"
      provides: "Bluesky endpoint + client_metadata templates, loaded by bootstrap.php"
      min_lines: 40
      contains: "atproto transition:generic"
    - path: "config/bootstrap.php"
      provides: "Configure::load('bluesky', 'default', false) call after app_local load"
      contains: "Configure::load('bluesky'"
    - path: "config/.env.example"
      provides: "6 OAuth env placeholders (OAUTH_KID / TOKEN_ENC_KEY / BLUESKY_*)"
      contains: "TOKEN_ENC_KEY"
    - path: "src/Application.php"
      provides: "implements AuthenticationServiceProviderInterface, getAuthenticationService() method, addPlugin('Authentication'), AuthenticationMiddleware in pipeline"
      contains: "AuthenticationMiddleware"
    - path: "config/routes.php"
      provides: "6 Phase 2 routes (login/oauth/dashboard) with setMethods() restrictions"
      contains: "/oauth/client-metadata.json"
    - path: "src/Service/OAuth/OAuthProviderInterface.php"
      provides: "AUTH-06 multi-provider abstraction (5 methods)"
      contains: "interface OAuthProviderInterface"
    - path: "config/keys/.gitignore"
      provides: "Keeps config/keys/ directory tracked but excludes *.key files"
      contains: "*.key"
  key_links:
    - from: "config/bootstrap.php"
      to: "config/bluesky.php"
      via: "Configure::load('bluesky', 'default', false)"
      pattern: "Configure::load\\('bluesky'"
    - from: "src/Application.php middleware()"
      to: "Authentication\\Middleware\\AuthenticationMiddleware"
      via: "->add(new AuthenticationMiddleware($this)) after CsrfProtectionMiddleware"
      pattern: "new AuthenticationMiddleware"
    - from: "src/Application.php bootstrap()"
      to: "Authentication plugin"
      via: "$this->addPlugin('Authentication')"
      pattern: "addPlugin\\('Authentication'\\)"
    - from: "src/Application.php getAuthenticationService()"
      to: "Authentication\\AuthenticationService + Session authenticator"
      via: "Session authenticator + OrmIdentifier keyed on users.id"
      pattern: "Authentication\\\\Authenticator\\\\SessionAuthenticator|loadIdentifier.*Identifiers\\.Password|loadAuthenticator.*Authenticators\\.Session"
    - from: "config/routes.php"
      to: "AuthController / OauthController / UsersController (shells in later plans)"
      via: "$builder->connect('/login/bluesky', ...)->setMethods(['GET','POST'])"
      pattern: "login/bluesky"
---

<objective>
Phase 2 の土台セットアップ。外部 HTTP 呼び出しを一切せず、以下をすべて offline で完了する:

1. `cakephp/authentication ^2.11` を `composer require` し `composer.lock` に反映 (D-02)
2. `src/Application.php` に `AuthenticationServiceProviderInterface` を実装、`AuthenticationMiddleware` を pipeline に差し込む (D-02)
3. `config/bluesky.php` に Bluesky 静的 endpoint + client_metadata テンプレートを配置し、`config/bootstrap.php` から `Configure::load` する (D-05, D-06, D-16)
4. `config/.env.example` に OAuth 関連 env キー 6 件を追加 (OAUTH_KID / TOKEN_ENC_KEY / BLUESKY_*) (D-14, D-15)
5. `config/routes.php` に Phase 2 の 6 ルート (`/login/bluesky`, `/oauth/callback`, `/oauth/client-metadata.json`, `/oauth/jwks.json`, `/oauth/logout`, `/dashboard`) を追加し HTTP メソッド制約を付ける (D-04, D-18, UI-SPEC §1)
6. `src/Service/OAuth/OAuthProviderInterface.php` を作成し AUTH-06 のマルチプロバイダ抽象を定義 (D-03)
7. `config/keys/.gitignore` で private.key / public.key を除外、ES256 EC P-256 鍵ペアをローカル生成 (D-14)

Purpose:
- Plan 02-02 (crypto services) と Plan 02-03 (metadata endpoints) の Wave 2 並列実行を可能にする土台を揃える
- `composer install` / `Configure::read('Bluesky.*')` / `config/keys/private.key` の 3 点セットが下流すべての前提条件

Output:
- 外部 HTTP なし / DB 変更なし / Service 実装ゼロ / Controller 実装ゼロ の土台のみ
- Wave 2 の 2 plan が並列に作業できる状態
</objective>

<execution_context>
@/home/claude/.claude/get-shit-done/workflows/execute-plan.md
@/home/claude/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@/home/claude/projects/tamabox/.planning/PROJECT.md
@/home/claude/projects/tamabox/.planning/ROADMAP.md
@/home/claude/projects/tamabox/.planning/STATE.md
@/home/claude/projects/tamabox/.planning/REQUIREMENTS.md
@/home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-CONTEXT.md
@/home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-RESEARCH.md
@/home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-PATTERNS.md
@/home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-UI-SPEC.md
@/home/claude/projects/tamabox/.planning/phases/01-foundation-schema/01-01-SUMMARY.md
@/home/claude/projects/tamabox/src/Application.php
@/home/claude/projects/tamabox/config/routes.php
@/home/claude/projects/tamabox/config/bootstrap.php
@/home/claude/projects/tamabox/composer.json

<interfaces>
<!-- Phase 2 で新設する AUTH-06 抽象 (この Plan で作成) -->

`App\Service\OAuth\OAuthProviderInterface`:
```php
interface OAuthProviderInterface
{
    /** @return array{request_uri:string,expires_in:int} */
    public function executeParRequest(string $codeChallenge, string $state): array;

    /** @return array{access_token:string,refresh_token:string,token_type:string,expires_in:int,sub:string} */
    public function exchangeCodeForToken(string $code, string $codeVerifier): array;

    /** @return array{access_token:string,refresh_token:string,expires_in:int} */
    public function refreshToken(string $refreshToken): array;

    /** @return array{handle:string,avatar:?string,displayName:?string,profile_url:string} */
    public function resolveProfile(string $did, string $accessToken): array;

    /** @return string e.g. 'bluesky' — matches user_identities.provider ENUM values */
    public function getProviderKey(): string;
}
```

現在の `config/routes.php` (修正前、抜粋):
```php
$routes->scope('/', function (RouteBuilder $builder): void {
    $builder->connect('/', ['controller' => 'Pages', 'action' => 'display', 'home']);
    $builder->connect('/pages/*', 'Pages::display');
    $builder->fallbacks();
});
```
Phase 2 追加ルートは既存 scope の中に差し込む (fallbacks() の前)。fallbacks() は CONCERNS.md が Phase 4 で除去予定なので Phase 2 では触らない。

現在の `src/Application.php::middleware()` pipeline:
ErrorHandler → Asset → Routing → BodyParser → CsrfProtection
Phase 2 で差し込む場所: **CsrfProtectionMiddleware の後**。

既存 use 群 (Application.php L24-31): Cake\Core\Configure / ContainerInterface / Datasource\FactoryLocator / Error\Middleware\ErrorHandlerMiddleware / Http\BaseApplication / BodyParserMiddleware / CsrfProtectionMiddleware / MiddlewareQueue / ORM\Locator\TableLocator / Routing\Middleware\AssetMiddleware / RoutingMiddleware。

Phase 2 追加 use (アルファベット順で挿入):
```php
use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Middleware\AuthenticationMiddleware;
use Psr\Http\Message\ServerRequestInterface;
```

Phase 1 の `users` テーブル id カラムは CHAR(36) UUID (Phase 1 01-02a SUMMARY 参照)。`UsersTable` は `src/Model/Table/UsersTable.php` に存在 (Plan 01-03 で bake 済み)。

既存 `config/.env.example` 抜粋 (Phase 1 01-01 で生成済み):
```
export APP_NAME="__APP_NAME__"
export DEBUG="true"
export SECURITY_SALT="__SALT__"
export SERVER_SECRET="__SERVER_SECRET__"
export DATABASE_URL="mysql://tamabox:secret@localhost/${APP_NAME}?encoding=utf8mb4&..."
export DATABASE_TEST_URL="mysql://tamabox:secret@localhost/test_${APP_NAME}?encoding=utf8mb4&..."
```
Phase 2 追加キーは `DATABASE_TEST_URL` の後に挿入する (ファイル末尾は cache/email config コメントアウトブロック)。
</interfaces>
</context>

<threat_model>
## Trust Boundaries

| Boundary | Description |
|----------|-------------|
| browser → CakePHP router | Untrusted inbound HTTP; all Phase 2 routes with setMethods() |
| env() → Configure | .env file content crosses into app config; must not echo secrets |
| composer network → vendor/ | Third-party code ingestion (cakephp/authentication) |
| config/keys/ filesystem → process | ES256 private key material — must never enter VCS or logs |

## STRIDE Threat Register

| Threat ID | Category | Component | Disposition | Mitigation Plan |
|-----------|----------|-----------|-------------|-----------------|
| T-02-01-01 | Information Disclosure | `config/keys/private.key` leaked to git | mitigate | `config/keys/.gitignore` with `*.key` pattern; verify `git check-ignore config/keys/private.key` exits 0 |
| T-02-01-02 | Tampering | Route method confusion (POST /logout via GET) | mitigate | Every route declared with `->setMethods(['GET'])` or `->setMethods(['POST'])`; verify routes with `bin/cake routes check` |
| T-02-01-03 | Spoofing | Open redirect via misconfigured `client_id` | mitigate | `client_id` in `config/bluesky.php` hardcoded to production URL literal; `redirect_uri` hardcoded to `https://tamabox.emomie.com/oauth/callback` |
| T-02-01-04 | Tampering | Supply chain via cakephp/authentication | mitigate | Pin version via `composer.lock` commit; only add 1 new dep (no transitive bloat beyond what CakePHP 4 already requires) |
| T-02-01-05 | Information Disclosure | `TOKEN_ENC_KEY` printed in error messages | accept | Phase 2 does not consume the key yet (Plan 02-02 handles it); placeholder in `.env.example` is `__TOKEN_ENC_KEY__` string, not a real secret |
| T-02-01-06 | Spoofing | AuthenticationMiddleware placed before CSRF → identity bypass | mitigate | Middleware order enforced: Error → Asset → Routing → BodyParser → **CsrfProtection → AuthenticationMiddleware** (after CSRF per CakePHP docs) |
| T-02-01-07 | Elevation of Privilege | AUTH-06 interface too loose (missing provider key) | mitigate | `getProviderKey()` returns string matching `user_identities.provider` ENUM; enforced at implementation (Plan 02-04) |
| T-02-01-08 | Repudiation | Private key rotation not planned | accept | MVP scope (D-15 deferred); `OAUTH_KID` env allows future rotation without code change |
</threat_model>

<tasks>

<task type="auto" tdd="false">
  <name>Task 1: composer require cakephp/authentication + lock regeneration</name>
  <files>composer.json, composer.lock</files>

  <read_first>
    - /home/claude/projects/tamabox/composer.json (現在の require / require-dev / scripts を確認)
    - /home/claude/projects/tamabox/composer.lock (Phase 1 で生成済み、cakephp 4.5 deps の resolve 状態確認)
    - /home/claude/projects/tamabox/.planning/phases/01-foundation-schema/01-01-SUMMARY.md (composer.json 現在の整形スタイル / scripts セクション定義)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-CONTEXT.md §`<code_context>` の `composer.json` integration points ("cakephp/authentication: ^2.11 を require に追加")
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-RESEARCH.md §Environment Availability (cakephp/authentication ^2.11 が CakePHP 4.x 互換と確認済)
  </read_first>

  <action>
  1. `composer.json` の `"require"` セクションに `"cakephp/authentication": "^2.11"` を追加する。キーの並びはアルファベット順 (`cakephp/cakephp` の直後)。他のキーは一切変更しない。
  2. `cd /home/claude/projects/tamabox && composer require cakephp/authentication:^2.11 --no-scripts --no-interaction` を実行する。composer が自動的に `composer.json` の update + `composer.lock` の regeneration + `vendor/` の install を行う。
  3. `composer.lock` の `packages` 配列に `cakephp/authentication` エントリが 2.11.x で resolve されていることを確認する。
  4. `vendor/cakephp/authentication/src/Middleware/AuthenticationMiddleware.php` が実体ファイルとして存在することを確認する (次タスクの use 文解決のため)。
  5. `composer validate --no-check-publish` が `valid` を返すこと。

  注意:
  - `--no-scripts` を付けるのは Phase 1 `post-install-cmd` (CakePHP Installer::postInstall) が dev 環境で副作用を起こすのを避けるため。
  - `vendor/` は `.gitignore` 済み (Phase 1)。コミットするのは `composer.json` + `composer.lock` のみ。
  - ES256 / DPoP / JWT 用のライブラリは **追加しない** (D-01: ライブラリ不使用、PHP ビルトインのみ)。
  </action>

  <verify>
    <automated>cd /home/claude/projects/tamabox && composer validate --no-check-publish 2>&1 | grep -q '^./composer.json is valid' && grep -q '"cakephp/authentication"' composer.json && grep -q '"name": "cakephp/authentication"' composer.lock && test -f vendor/cakephp/authentication/src/Middleware/AuthenticationMiddleware.php && echo VERIFY_OK</automated>
  </verify>

  <acceptance_criteria>
    - `grep -c '"cakephp/authentication"' composer.json` ≥ 1
    - `composer.json` の `require` 配列にのみ追加されていて、`require-dev` にはない → `jq '.require["cakephp/authentication"]' composer.json` → `"^2.11"` (または `"^2.11.x"` 相当) を返す
    - `jq '.packages[] | select(.name == "cakephp/authentication") | .version' composer.lock` → `"2.11.x"` (patch は resolve 結果に委ねる) を返す
    - `test -f vendor/cakephp/authentication/src/Middleware/AuthenticationMiddleware.php` exits 0
    - `composer validate --no-check-publish` exits 0 with "./composer.json is valid"
    - `cd /home/claude/projects/tamabox && php -l composer.json` は不要だが `jq . composer.json > /dev/null` exits 0 で JSON 整合確認
    - 新規追加された package は `cakephp/authentication` のみ (他 deps は Phase 1 から不変 — `git diff composer.lock | grep '"name"' | wc -l` は cakephp/authentication 1 件のみ + その transitive deps に限定)
  </acceptance_criteria>

  <done>
    composer.json に cakephp/authentication が require 追加され、composer.lock に resolve 結果がコミット可能状態で記録されている。vendor/cakephp/authentication/ 配下のファイルが実体として存在し、次タスクの use 文解決の前提が揃った。
  </done>
</task>

<task type="auto" tdd="false">
  <name>Task 2: Application.php に Authentication plugin + AuthenticationMiddleware + getAuthenticationService() を配線</name>
  <files>src/Application.php</files>

  <read_first>
    - /home/claude/projects/tamabox/src/Application.php 全文 (既存 bootstrap() / middleware() / bootstrapCli() のスタイル確認)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-PATTERNS.md §`src/Application.php` (middleware 差し込み位置 / use 文追加パターン)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-CONTEXT.md D-02 (Session Authenticator + カスタム ORM Identifier 方針)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-RESEARCH.md §Open Questions for Planner #4 (carmel's UsersTable::findAuth パターン推奨)
    - /home/claude/projects/tamabox/vendor/cakephp/authentication/src/AuthenticationService.php (loadAuthenticator / loadIdentifier の実 API)
    - /home/claude/projects/tamabox/vendor/cakephp/authentication/src/Middleware/AuthenticationMiddleware.php (__construct シグネチャ)
  </read_first>

  <action>
  1. `src/Application.php` の先頭 `use` 群を以下の順序で拡張する (アルファベット順 — 既存 `Cake\Core\Configure;` の前に `Authentication\*` が入り、`Cake\Routing\Middleware\RoutingMiddleware;` の後に `Psr\Http\Message\ServerRequestInterface;` を入れる):
     ```php
     use Authentication\AuthenticationService;
     use Authentication\AuthenticationServiceInterface;
     use Authentication\AuthenticationServiceProviderInterface;
     use Authentication\Middleware\AuthenticationMiddleware;
     use Cake\Core\Configure;
     use Cake\Core\ContainerInterface;
     use Cake\Datasource\FactoryLocator;
     use Cake\Error\Middleware\ErrorHandlerMiddleware;
     use Cake\Http\BaseApplication;
     use Cake\Http\Middleware\BodyParserMiddleware;
     use Cake\Http\Middleware\CsrfProtectionMiddleware;
     use Cake\Http\MiddlewareQueue;
     use Cake\ORM\Locator\TableLocator;
     use Cake\Routing\Middleware\AssetMiddleware;
     use Cake\Routing\Middleware\RoutingMiddleware;
     use Psr\Http\Message\ServerRequestInterface;
     ```

  2. class 宣言を変更する:
     ```php
     class Application extends BaseApplication implements AuthenticationServiceProviderInterface
     ```

  3. `bootstrap()` メソッドの末尾、`// Load more plugins here` コメントの直前に以下を追加する:
     ```php
     $this->addPlugin('Authentication');
     ```

  4. `middleware()` メソッドの `CsrfProtectionMiddleware` 追加の **後** (つまり `->add(new CsrfProtectionMiddleware([...]))` の直後、`return $middlewareQueue` の前) に以下を追加する:
     ```php
     ->add(new AuthenticationMiddleware($this));
     ```
     (既存の CsrfProtectionMiddleware 行末のセミコロンは削除し、新行はセミコロン付きで締めくくる)

  5. `services()` メソッドの直後に以下の新規 public method を追加する:
     ```php
     /**
      * Returns the AuthenticationService instance used by AuthenticationMiddleware.
      *
      * Session authenticator only (no form/password authenticator — OAuth is the sole identity source).
      * ORM identifier resolves an authenticated user by its `id` (UUID, users table PK).
      *
      * @param \Psr\Http\Message\ServerRequestInterface $request Request used to inspect identity config if needed.
      * @return \Authentication\AuthenticationServiceInterface
      */
     public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
     {
         $service = new AuthenticationService();
         $service->setConfig([
             // Users hitting a protected route without a session are redirected to '/' (home with login CTA).
             'unauthenticatedRedirect' => '/',
             'queryParam' => 'redirect',
         ]);
         $service->loadIdentifier('Authentication.Password', [
             'resolver' => [
                 'className' => 'Authentication.Orm',
                 'userModel' => 'Users',
                 'finder' => 'all',
             ],
             'fields' => [
                 // OAuth-only: no password field, we identify solely by session-stored id.
                 'username' => 'id',
                 'password' => null,
             ],
             'passwordHasher' => null,
         ]);
         $service->loadAuthenticator('Authentication.Session', [
             'identify' => true,
         ]);

         return $service;
     }
     ```

     実装メモ (D-02 + RESEARCH.md Q4 推奨):
     - `Session` Authenticator のみ。Form/Password は使わない (OAuth が唯一のログイン手段)。
     - `Password` identifier を Orm resolver で使い、`fields.username => 'id'` + `password => null` で「セッションに保存された id を UsersTable 経由で引く」用途に流用する (Authentication Plugin 2.x の公式パターン)。
     - `unauthenticatedRedirect => '/'` は UI-SPEC §5「セッション切れ: 保護ルート → /?reason=expired 相当」のため (queryParam で `?redirect=/dashboard` が付くが UI-SPEC 準拠は後続 plan)。

  6. `php -l src/Application.php` が syntax error なしで通ること。
  7. CLI boot smoke test: `cd /home/claude/projects/tamabox && bin/cake --version` がエラーを吐かずに CakePHP バージョンを出力すること (Authentication plugin の initialize が CLI でも動く確認)。
  8. `Psr\Http\Message\ServerRequestInterface` は `vendor/psr/http-message/` 由来で CakePHP 4 が既に transitive dep として持っているため追加 require 不要。

  注意:
  - `Authentication\AuthenticationServiceProviderInterface` の実装漏れは fatal になるため class 宣言行と `getAuthenticationService()` の両方を同じコミット内でコミットする。
  - middleware 順序の違反 (Authentication を CSRF の **前** に置く) は T-02-01-06 の脅威になる。CakePHP Authentication Plugin 2.x の公式 docs で CSRF 後配置が推奨されている。
  </action>

  <verify>
    <automated>cd /home/claude/projects/tamabox && php -l src/Application.php 2>&1 | grep -q 'No syntax errors' && grep -q 'implements AuthenticationServiceProviderInterface' src/Application.php && grep -q "addPlugin('Authentication')" src/Application.php && grep -q 'new AuthenticationMiddleware($this)' src/Application.php && grep -q 'public function getAuthenticationService' src/Application.php && grep -q "loadAuthenticator('Authentication.Session'" src/Application.php && bin/cake --version 2>&1 | grep -qE '^[0-9]+\.[0-9]+' && echo VERIFY_OK</automated>
  </verify>

  <acceptance_criteria>
    - `php -l src/Application.php` → `No syntax errors detected`
    - `grep -c 'use Authentication\\\\' src/Application.php` ≥ 4 (AuthenticationService / AuthenticationServiceInterface / AuthenticationServiceProviderInterface / Middleware\\AuthenticationMiddleware)
    - `grep -c 'use Psr\\\\Http\\\\Message\\\\ServerRequestInterface' src/Application.php` = 1
    - `grep -c 'implements AuthenticationServiceProviderInterface' src/Application.php` = 1
    - `grep -c "addPlugin('Authentication')" src/Application.php` = 1
    - `grep -c 'new AuthenticationMiddleware' src/Application.php` = 1
    - `grep -n 'CsrfProtectionMiddleware' src/Application.php` の行番号が `grep -n 'AuthenticationMiddleware' src/Application.php` の行番号より小さい (CSRF が先、Authentication が後)
    - `grep -c 'public function getAuthenticationService' src/Application.php` = 1
    - `grep -q 'unauthenticatedRedirect' src/Application.php` exits 0
    - `grep -q "loadAuthenticator('Authentication.Session'" src/Application.php` exits 0
    - `bin/cake --version` exits 0 (boot smoke — Authentication plugin load が CLI で壊れないこと)
  </acceptance_criteria>

  <done>
    Application.php が AuthenticationServiceProviderInterface を実装し、Authentication plugin が load され、middleware pipeline に AuthenticationMiddleware が CSRF の後に配置され、getAuthenticationService() が Session authenticator + OrmIdentifier を返す状態。php -l 通過、bin/cake も boot する。
  </done>
</task>

<task type="auto" tdd="false">
  <name>Task 3: config/bluesky.php 作成 + bootstrap.php 読込 + .env.example に OAuth キー追加</name>
  <files>config/bluesky.php, config/bootstrap.php, config/.env.example</files>

  <read_first>
    - /home/claude/projects/tamabox/config/app_local.example.php (return-array スタイルの参考)
    - /home/claude/projects/tamabox/config/bootstrap.php 全文 (Configure::load('app_local', ...) 相当の既存呼び出し位置確認)
    - /home/claude/projects/tamabox/config/.env.example 全文 (既存 SERVER_SECRET / DATABASE_URL の定義スタイル)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-CONTEXT.md D-05 / D-06 / D-14 / D-15 / D-16
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-PATTERNS.md §`config/bluesky.php` (return-array 具体例)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-RESEARCH.md §Client Metadata (フィールド全量)
  </read_first>

  <action>
  1. **新規ファイル `config/bluesky.php`** を以下の内容で作成する (D-05 / D-06 / D-16 準拠、PATTERNS.md §`config/bluesky.php` そのまま):

     ```php
     <?php
     declare(strict_types=1);

     /**
      * Bluesky OAuth configuration.
      *
      * Loaded via Configure::load('bluesky', 'default', false) in config/bootstrap.php.
      * Static endpoints (D-05): bsky.social is hardcoded for MVP — third-party PDS / dynamic AS
      * metadata resolution is out of scope (CONTEXT.md Deferred Ideas).
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

             // AUTH-FLOW §1 / D-06 / D-16: client_metadata.json payload
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
     ```

  2. **`config/bootstrap.php` の修正**:
     - `Configure::load('app_local', 'default');` の呼び出し位置を grep で特定する (`file_exists(CONFIG . 'app_local.php')` 条件付き load ブロック)
     - そのブロックの直後、かつ `if (Configure::read('debug'))` ブロックの前に以下を挿入:
     ```php
     /*
      * Load Bluesky OAuth configuration (Phase 2). Shipped as config/bluesky.php.
      * Non-sensitive defaults; env() calls inside override per-environment.
      */
     Configure::load('bluesky', 'default', false);
     ```
     - Configure::load() の第3引数 `false` は「merge せず上書き」意味 (app_local のキーと衝突しないよう `Bluesky.*` 名前空間で隔離)

  3. **`config/.env.example` の修正**:
     - 既存の `export DATABASE_TEST_URL=...` 行の **直後** に以下のブロックを挿入する (cache/email config のコメントアウト群より前):
     ```
     # Bluesky OAuth (Phase 2 — AUTH-01..09 except 03)
     # Generate ES256 keypair once per environment:
     #   openssl ecparam -genkey -name prime256v1 -noout -out config/keys/private.key
     #   openssl ec -in config/keys/private.key -pubout -out config/keys/public.key
     #   chmod 600 config/keys/private.key && chmod 644 config/keys/public.key

     # JWK kid value served in /oauth/jwks.json. Matches 'kid' claim in client_assertion JWTs.
     export OAUTH_KID="ssr-box-key-1"

     # AES-256-GCM key for encrypting user_identities.access_token_enc / refresh_token_enc.
     # Generate: openssl rand -hex 32 (produces 64 hex chars = 32 bytes).
     # Single fixed key for MVP — rotation is Deferred in CONTEXT.md.
     export TOKEN_ENC_KEY="__TOKEN_ENC_KEY_HEX_32BYTES__"

     # bsky.social endpoints — do NOT override for MVP unless Bluesky moves host.
     export BLUESKY_ISSUER="https://bsky.social"
     export BLUESKY_PAR_ENDPOINT="https://bsky.social/oauth/par"
     export BLUESKY_TOKEN_ENDPOINT="https://bsky.social/oauth/token"
     export BLUESKY_AUTH_ENDPOINT="https://bsky.social/oauth/authorize"
     ```

     注意:
     - `TOKEN_ENC_KEY` の placeholder は `__TOKEN_ENC_KEY_HEX_32BYTES__` (Phase 1 `__SERVER_SECRET__` と同じ patter)。実値は `.env` にのみ置く (gitignored)。
     - `.env.example` は **git-tracked の雛形**。placeholder のまま commit する。
     - 実環境の `config/.env` には dev/prod 担当者が `openssl rand -hex 32` で生成した値を書き込む (CONTEXT.md D-15)。

  4. syntax check:
     - `php -l config/bluesky.php` が通ること
     - `php -l config/bootstrap.php` が通ること

  5. Configure smoke test (手動でもよいが autoverify 用):
     ```bash
     php -r 'define("DS", DIRECTORY_SEPARATOR); require "vendor/autoload.php"; require "config/paths.php"; require "config/bootstrap.php"; echo Cake\Core\Configure::read("Bluesky.issuer") . "\n" . Cake\Core\Configure::read("Bluesky.client_metadata.scope") . "\n" . Cake\Core\Configure::read("Bluesky.client_metadata.client_id") . "\n";'
     ```
     期待出力:
     ```
     https://bsky.social
     atproto transition:generic
     https://tamabox.emomie.com/oauth/client-metadata.json
     ```
  </action>

  <verify>
    <automated>cd /home/claude/projects/tamabox && php -l config/bluesky.php 2>&1 | grep -q 'No syntax errors' && php -l config/bootstrap.php 2>&1 | grep -q 'No syntax errors' && grep -q "Configure::load('bluesky'" config/bootstrap.php && grep -q "'scope'.*=>.*'atproto transition:generic'" config/bluesky.php && grep -q "'client_id'.*=>.*'https://tamabox.emomie.com/oauth/client-metadata.json'" config/bluesky.php && grep -q 'TOKEN_ENC_KEY' config/.env.example && grep -q 'OAUTH_KID' config/.env.example && grep -q 'BLUESKY_PAR_ENDPOINT' config/.env.example && php -r 'require "vendor/autoload.php"; require "config/paths.php"; require "config/bootstrap.php"; exit(Cake\Core\Configure::read("Bluesky.client_metadata.client_id") === "https://tamabox.emomie.com/oauth/client-metadata.json" ? 0 : 1);' && echo VERIFY_OK</automated>
  </verify>

  <acceptance_criteria>
    - `test -f config/bluesky.php` exits 0
    - `php -l config/bluesky.php` exits 0
    - `php -l config/bootstrap.php` exits 0
    - `grep -c "Configure::load('bluesky'" config/bootstrap.php` = 1
    - `grep -c "atproto transition:generic" config/bluesky.php` = 1 (D-06 exact scope string)
    - `grep -c "'client_id' *=> *'https://tamabox.emomie.com/oauth/client-metadata.json'" config/bluesky.php` ≥ 1 (top-level + inside client_metadata array)
    - `grep -c "'private_key_jwt'" config/bluesky.php` = 1
    - `grep -c "'dpop_bound_access_tokens' *=> *true" config/bluesky.php` = 1
    - `grep -c "'OAUTH_KID'" config/.env.example` ≥ 1 OR `grep -c '^export OAUTH_KID=' config/.env.example` = 1
    - `grep -c '^export TOKEN_ENC_KEY=' config/.env.example` = 1
    - `grep -c '^export BLUESKY_PAR_ENDPOINT=' config/.env.example` = 1
    - `grep -c '^export BLUESKY_TOKEN_ENDPOINT=' config/.env.example` = 1
    - `grep -c '^export BLUESKY_AUTH_ENDPOINT=' config/.env.example` = 1
    - `grep -c '^export BLUESKY_ISSUER=' config/.env.example` = 1
    - Configure smoke: `php -r 'require "vendor/autoload.php"; require "config/paths.php"; require "config/bootstrap.php"; exit(Cake\Core\Configure::read("Bluesky.client_metadata.scope") === "atproto transition:generic" ? 0 : 1);'` exits 0
  </acceptance_criteria>

  <done>
    config/bluesky.php が return-array で Bluesky endpoints + client_metadata を提供し、bootstrap.php が Configure::load 経由で読み、.env.example に OAuth 関連 6 env キーが placeholder 付きで documented されている状態。Configure::read('Bluesky.*') が期待値を返すことが確認された。
  </done>
</task>

<task type="auto" tdd="false">
  <name>Task 4: OAuthProviderInterface 作成 + routes.php 拡張 + config/keys/.gitignore + ローカル EC 鍵ペア生成</name>
  <files>src/Service/OAuth/OAuthProviderInterface.php, config/routes.php, config/keys/.gitignore, config/keys/private.key, config/keys/public.key</files>

  <read_first>
    - /home/claude/projects/tamabox/config/routes.php 全文 (既存 fallbacks() + DashedRoute の確認)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-CONTEXT.md D-03 (Service 層分割 / OAuthProviderInterface 5 メソッド仕様), D-04 (Controller 配置 / routes), D-14 (EC 鍵生成コマンド)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-RESEARCH.md §Architectural Responsibility Map (Service 層の責務)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-PATTERNS.md §`config/routes.php` (Phase 2 で追加する scope パターン具体例), §Novel Patterns #OAuthProviderInterface
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-UI-SPEC.md §1 (routes / auth required 表)
    - /home/claude/projects/tamabox/.planning/codebase/CONVENTIONS.md (PSR-4 / strict_types / docblock 規約)
  </read_first>

  <action>
  1. **`src/Service/OAuth/` ディレクトリ新設** (Interface 配置用):
     ```bash
     mkdir -p src/Service/OAuth
     mkdir -p src/Service/OAuth/Bluesky   # Plan 02-02, 02-03, 02-04 が埋める
     ```

  2. **新規ファイル `src/Service/OAuth/OAuthProviderInterface.php`** を以下の内容で作成する:

     ```php
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
     ```

  3. **`config/routes.php` 修正**: 既存 `$routes->scope('/', function (RouteBuilder $builder): void {` の内部で、`$builder->connect('/pages/*', 'Pages::display');` の直後、`$builder->fallbacks();` の前に以下を挿入する (UI-SPEC §1 と CONTEXT D-04 に準拠、HTTP method 制約付き):

     ```php
             /*
              * Phase 2 — Bluesky OAuth routes.
              * See UI-SPEC.md §1 for route/method/auth-required mapping.
              */
             // GET (render login-start form if ever added) / POST (CTA form submission starts PAR).
             $builder->connect('/login/bluesky', ['controller' => 'Auth', 'action' => 'startBluesky'])
                 ->setMethods(['GET', 'POST']);

             // GET only — Bluesky AS redirects here with code/state/iss.
             $builder->connect('/oauth/callback', ['controller' => 'Oauth', 'action' => 'callback'])
                 ->setMethods(['GET']);

             // GET only — Bluesky AS polls this as client_id. Must return application/json verbatim.
             $builder->connect('/oauth/client-metadata.json', ['controller' => 'Oauth', 'action' => 'clientMetadata'])
                 ->setMethods(['GET']);

             // GET only — public JWKS for AS signature verification.
             $builder->connect('/oauth/jwks.json', ['controller' => 'Oauth', 'action' => 'jwks'])
                 ->setMethods(['GET']);

             // POST only — CSRF-protected logout (D-18).
             $builder->connect('/oauth/logout', ['controller' => 'Auth', 'action' => 'logout'])
                 ->setMethods(['POST']);

             // GET only — authenticated landing (placeholder for Plan 02-04; Phase 3 adds inbox).
             $builder->connect('/dashboard', ['controller' => 'Users', 'action' => 'dashboard'])
                 ->setMethods(['GET']);
     ```

     注意:
     - Controller クラス (`AuthController`, `OauthController`, `UsersController`) は Plan 02-03 および 02-04 で作成される。Plan 02-01 時点では routes から参照される Controller が未実装のため `bin/cake routes check` は実在 Controller のないルートを 404 相当で返すが route 定義自体は正当。
     - `setMethods(['GET', 'POST'])` は `/login/bluesky` のみ。ブラウザ GET 直接アクセスもフォームサブミット (POST) も両方受け付ける (UI-SPEC §1)。
     - `/oauth/logout` パスは UI-SPEC §5 / D-18 準拠の「POST + CSRF 必須」。

  4. **`config/keys/.gitignore` 新規作成** (T-02-01-01 mitigation):
     ```
     # config/keys/.gitignore — Phase 2 added to keep directory tracked while excluding key material.
     # Rotation/generation: see config/.env.example header comment.
     *.key
     *.pem
     !.gitignore
     ```

     これにより `config/keys/` ディレクトリ自体は git に残るが、`*.key` / `*.pem` ファイルは除外される (Phase 1 で `.gitkeep` が置かれていた運用の上位互換)。

  5. **ローカル EC P-256 鍵ペア生成** (D-14):
     ```bash
     cd /home/claude/projects/tamabox
     openssl ecparam -genkey -name prime256v1 -noout -out config/keys/private.key
     openssl ec -in config/keys/private.key -pubout -out config/keys/public.key
     chmod 600 config/keys/private.key
     chmod 644 config/keys/public.key
     ```

     生成確認:
     - `openssl ec -in config/keys/private.key -noout -text 2>&1 | grep -q 'NIST CURVE: P-256'`
     - `openssl ec -in config/keys/public.key -pubin -noout -text 2>&1 | grep -q 'NIST CURVE: P-256'`
     - `stat -c '%a' config/keys/private.key` → `600`
     - `git check-ignore config/keys/private.key` exits 0 (ignored) / `git check-ignore config/keys/.gitignore` exits 1 (tracked)

  6. syntax check on all new PHP:
     - `php -l src/Service/OAuth/OAuthProviderInterface.php`
     - `php -l config/routes.php`

  7. PSR-4 autoload check:
     ```bash
     cd /home/claude/projects/tamabox && composer dump-autoload -o --no-scripts 2>&1
     php -r 'require "vendor/autoload.php"; exit(interface_exists("App\\Service\\OAuth\\OAuthProviderInterface") ? 0 : 1);'
     ```

  注意: Plan 02-02 で作成される `BlueskyOAuthClient` がこの interface を `implements` する。Plan 02-04 で `AuthController` / `OauthController` が interface 型で DI を受ける。
  </action>

  <verify>
    <automated>cd /home/claude/projects/tamabox && php -l src/Service/OAuth/OAuthProviderInterface.php 2>&1 | grep -q 'No syntax errors' && php -l config/routes.php 2>&1 | grep -q 'No syntax errors' && grep -q 'interface OAuthProviderInterface' src/Service/OAuth/OAuthProviderInterface.php && grep -Ec 'public function (executeParRequest|exchangeCodeForToken|refreshToken|resolveProfile|getProviderKey)' src/Service/OAuth/OAuthProviderInterface.php | grep -q '^5$' && grep -q '/login/bluesky' config/routes.php && grep -q '/oauth/client-metadata.json' config/routes.php && grep -q '/oauth/jwks.json' config/routes.php && grep -q '/oauth/callback' config/routes.php && grep -q '/oauth/logout' config/routes.php && grep -q '/dashboard' config/routes.php && test -f config/keys/private.key && test -f config/keys/public.key && test -f config/keys/.gitignore && openssl ec -in config/keys/private.key -noout -text 2>&1 | grep -q 'NIST CURVE: P-256' && test "$(stat -c '%a' config/keys/private.key)" = "600" && git check-ignore config/keys/private.key >/dev/null 2>&1 && composer dump-autoload -o --no-scripts >/dev/null 2>&1 && php -r 'require "vendor/autoload.php"; exit(interface_exists("App\\Service\\OAuth\\OAuthProviderInterface") ? 0 : 1);' && echo VERIFY_OK</automated>
  </verify>

  <acceptance_criteria>
    - `test -f src/Service/OAuth/OAuthProviderInterface.php` exits 0
    - `php -l src/Service/OAuth/OAuthProviderInterface.php` exits 0
    - `grep -c 'interface OAuthProviderInterface' src/Service/OAuth/OAuthProviderInterface.php` = 1
    - 5 abstract method sigs present: `grep -Ec 'public function (executeParRequest|exchangeCodeForToken|refreshToken|resolveProfile|getProviderKey)' src/Service/OAuth/OAuthProviderInterface.php` = 5
    - PSR-4 autoload: `php -r 'require "vendor/autoload.php"; exit(interface_exists("App\\Service\\OAuth\\OAuthProviderInterface") ? 0 : 1);'` exits 0
    - `grep -c '/login/bluesky' config/routes.php` = 1
    - `grep -c '/oauth/callback' config/routes.php` = 1
    - `grep -c '/oauth/client-metadata.json' config/routes.php` = 1
    - `grep -c '/oauth/jwks.json' config/routes.php` = 1
    - `grep -c '/oauth/logout' config/routes.php` = 1
    - `grep -c '/dashboard' config/routes.php` = 1
    - `grep -c "setMethods.*'POST'" config/routes.php` ≥ 2 (login/bluesky includes POST; logout is POST only)
    - `grep -c "setMethods.*'GET'" config/routes.php` ≥ 4 (callback / metadata / jwks / dashboard)
    - `php -l config/routes.php` exits 0
    - `test -f config/keys/.gitignore` exits 0
    - `grep -qE '^\\*\\.key$' config/keys/.gitignore` exits 0
    - `test -f config/keys/private.key && test -f config/keys/public.key` exits 0
    - `openssl ec -in config/keys/private.key -noout -text` exits 0 AND output contains `NIST CURVE: P-256`
    - `openssl ec -in config/keys/public.key -pubin -noout -text` exits 0 AND output contains `NIST CURVE: P-256`
    - `test "$(stat -c '%a' config/keys/private.key)" = "600"` exits 0 (private key owner-only)
    - `test "$(stat -c '%a' config/keys/public.key)" = "644"` exits 0
    - `git check-ignore config/keys/private.key` exits 0 (ignored)
    - `git check-ignore config/keys/public.key` exits 0 (ignored)
    - `git check-ignore config/keys/.gitignore` exits 1 (tracked — `!.gitignore` exception)
  </acceptance_criteria>

  <done>
    OAuthProviderInterface が 5 methods 定義済みで PSR-4 autoload から reachable、config/routes.php に Phase 2 ルート 6 件が HTTP method 制約付きで追加、config/keys/ に EC P-256 鍵ペアが 600/644 権限で生成され git から ignored されている状態。
  </done>
</task>

</tasks>

<verification>
## Plan-level Verification

Run after all 4 tasks complete:

1. **composer 整合性**:
   - `cd /home/claude/projects/tamabox && composer validate --no-check-publish` → "valid"
   - `composer install --dry-run --no-scripts 2>&1 | grep -qE 'Nothing to install|nothing to install'`
   - `jq '.packages[] | select(.name == "cakephp/authentication") | .version' composer.lock` returns a 2.11.x string

2. **PHP syntax + boot**:
   - `php -l` clean on: src/Application.php / config/bootstrap.php / config/bluesky.php / config/routes.php / src/Service/OAuth/OAuthProviderInterface.php (5 files)
   - `bin/cake --version` exits 0 (Authentication plugin loads in CLI without error)

3. **Configure wiring**:
   ```
   php -r 'require "vendor/autoload.php"; require "config/paths.php"; require "config/bootstrap.php"; $m = Cake\Core\Configure::read("Bluesky.client_metadata"); exit($m["client_id"] === "https://tamabox.emomie.com/oauth/client-metadata.json" && $m["scope"] === "atproto transition:generic" && $m["token_endpoint_auth_method"] === "private_key_jwt" && $m["dpop_bound_access_tokens"] === true ? 0 : 1);'
   ```

4. **PSR-4 reachability**:
   ```
   php -r 'require "vendor/autoload.php"; exit(interface_exists("App\\Service\\OAuth\\OAuthProviderInterface") ? 0 : 1);'
   ```

5. **Key material + gitignore**:
   - `openssl ec -in config/keys/private.key -noout -text | grep -q 'P-256'` exits 0
   - `git check-ignore config/keys/private.key` exits 0
   - `git check-ignore config/keys/.gitignore` exits 1

6. **Middleware order invariant** (T-02-01-06):
   ```
   csrf_line=$(grep -n 'CsrfProtectionMiddleware' src/Application.php | grep -v use | head -1 | cut -d: -f1)
   auth_line=$(grep -n 'AuthenticationMiddleware' src/Application.php | grep -v use | head -1 | cut -d: -f1)
   test "$csrf_line" -lt "$auth_line"
   ```

7. **Lint/Static (Phase 1 scripts reused)**:
   - `composer phpcs` exit 0 (no new PHP files introduce phpcs violations)
   - `composer phpstan` exit 0 (interface + Application.php changes pass level 8)

8. **Full test suite smoke**:
   - `composer test` exit 0 (Phase 1 test suite still passes; no new tests yet — Plan 02-02 adds them)
</verification>

<success_criteria>
Plan 02-01 complete when:
- [ ] composer.json + composer.lock include cakephp/authentication ^2.11, committed
- [ ] src/Application.php: implements AuthenticationServiceProviderInterface, addPlugin('Authentication'), AuthenticationMiddleware after CSRF, getAuthenticationService() implemented with Session authenticator + OrmIdentifier(users.id)
- [ ] config/bluesky.php exists and loaded by config/bootstrap.php; Configure::read('Bluesky.client_metadata.client_id') returns the production URL exactly, 'Bluesky.client_metadata.scope' returns 'atproto transition:generic'
- [ ] config/.env.example declares OAUTH_KID / TOKEN_ENC_KEY / BLUESKY_ISSUER / BLUESKY_PAR_ENDPOINT / BLUESKY_TOKEN_ENDPOINT / BLUESKY_AUTH_ENDPOINT (6 placeholders)
- [ ] config/routes.php declares 6 Phase 2 routes with setMethods() constraints
- [ ] src/Service/OAuth/OAuthProviderInterface.php exists; 5 abstract methods; PSR-4 autoloadable (interface_exists())
- [ ] config/keys/private.key + public.key generated (EC P-256, 600/644 perms)
- [ ] config/keys/.gitignore ignores *.key *.pem but tracks .gitignore itself
- [ ] composer phpcs / phpstan / test all exit 0 (Phase 1 baseline preserved)
- [ ] No outbound HTTP, no DB write, no Service implementation, no Controller implementation in this plan
</success_criteria>

<output>
After completion, create `.planning/phases/02-bluesky-oauth-identity/02-01-SUMMARY.md` matching the Phase 1 SUMMARY structure (frontmatter with requirements_partial/closed, commits log, per-task acceptance, deviations, handoff note to Plans 02-02 and 02-03, self-check).
</output>
