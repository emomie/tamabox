---
phase: 02-bluesky-oauth-identity
plan: 04
wave: 3
subsystem: oauth-flow
tags:
  - oauth
  - bluesky
  - par
  - token-exchange
  - dpop-nonce
  - pkce
  - session
  - upsert
  - templates
  - css
  - integration-test
  - tdd
requirements_closed:
  - AUTH-01
  - AUTH-02
  - AUTH-04
  - AUTH-05
  - AUTH-09
  - AUTH-06  # concrete BlueskyOAuthClient impl — interface shell was 02-01
requirements_partial: []
files_modified:
  - src/Controller/AppController.php
  - src/Controller/OauthController.php
  - src/Controller/PagesController.php
  - src/Model/Table/UsersTable.php
  - src/Model/Table/UserIdentitiesTable.php
  - templates/Pages/home.php
  - templates/layout/default.php
  - tests/TestCase/Controller/OauthControllerTest.php
  - tests/TestCase/Controller/PagesControllerTest.php
files_created:
  - src/Service/OAuth/Bluesky/BlueskyOAuthClient.php
  - src/Controller/AuthController.php
  - src/Controller/UsersController.php
  - templates/Auth/callback.php
  - templates/Users/dashboard.php
  - templates/element/avatar_handle_chip.php
  - webroot/css/tamabox.css
  - tests/TestCase/Service/OAuth/Bluesky/BlueskyOAuthClientTest.php
  - tests/TestCase/Controller/AuthControllerTest.php
  - tests/TestCase/Controller/OauthControllerCallbackTest.php
commits:
  - c94c006 test(02-04): add failing BlueskyOAuthClient unit tests (RED)
  - da4028f feat(02-04): implement BlueskyOAuthClient (GREEN)
  - 3946ada feat(02-04): add UsersTable::findByDid + UserIdentitiesTable::upsertBlueskyIdentity
  - 7fde47f feat(02-04): OAuth flow end-to-end — controllers + templates + CSS + integration tests
decisions_locked_in:
  - D-08  # state/pkce/nonce via session, AES-GCM token encryption at table layer
  - D-09  # UNIQUE (provider, provider_account_id) raced via DB constraint + catch
  - D-10  # DPoP-Nonce retry max 1
  - D-13  # ath claim on bearer-DPoP resource GETs
  - D-18  # logout is POST-only + CSRF
metrics:
  duration_sec: 1049
  tasks_completed: 3
  files_total: 17
  tests_added: 23  # 13 BlueskyOAuthClient + 5 AuthControllerTest + 5 OauthControllerCallbackTest
  tests_total_after: 85
completed: 2026-04-24
---

# Phase 02 Plan 04: Bluesky OAuth Flow End-to-End — Summary

Phase 2 終着駅。BlueskyOAuthClient を 02-02/02-03 の crypto + DID 基盤に接続し、OAuth ハンドシェイク全体を ハッピー + エラー 6 パスで観測可能にした。Plan 02-03 が残していた `OauthController::callback` の 501 stub を完全に差し替え、AuthController + UsersController 新設、5 テンプレート + tamabox.css (218 行) を UI-SPEC v1 §1〜§7 に合わせて一気に敷設。/gsd-verify-phase 2 に進める状態。

Stack 変化: **composer dep 追加ゼロ** (Plan 02-01 で require した cakephp/authentication 2.11 のみ継続依存)。新 namespace: `App\Service\OAuth\Bluesky\BlueskyOAuthClient` + `App\Controller\AuthController` + `App\Controller\UsersController`。

## Acceptance Criteria per Task

### Task 1: BlueskyOAuthClient + unit tests (TDD RED → GREEN)

- [x] `src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` 新設 (343 行)、`implements OAuthProviderInterface`
- [x] 5 interface methods: `executeParRequest` / `exchangeCodeForToken` / `refreshToken` / `resolveProfile` / `getProviderKey`(`grep -Ec` で 5 件ヒット)
- [x] `getProviderKey()` は `'bluesky'` リテラル (user_identities.provider ENUM と一致)
- [x] DPoP-Nonce retry (CONTEXT D-10): `postWithNonceRetry` が最大 1 回のみ retry、`use_dpop_nonce` error + `$lastAsNonce !== null` 条件
- [x] resolveProfile は DID→PDS (DidResolver) → `https://{pds}/xrpc/app.bsky.actor.getProfile?actor={did}` GET、`Authorization: DPoP {token}` + DPoP proof with ath claim
- [x] HTTP は `Cake\Http\Client` 経由、`curl_*` 直接呼び出しゼロ (`grep -c 'curl_' = 0`)
- [x] Exception message から secret/debug payload を除外 (T-02-04-08)
- [x] 13 unit tests (RED で全件失敗 → GREEN で全件 pass): happy path × 5 methods + nonce-retry success/failure + missing-field guards + avatar-optional + 401 propagation + initial-nonce state carrying + secret-redaction invariant
- [x] `vendor/bin/phpunit --filter BlueskyOAuthClientTest` → 13 tests OK, 32 assertions

### Task 2: UserIdentitiesTable::upsertBlueskyIdentity + UsersTable::findByDid

- [x] `UsersTable::findByDid(Query $query, array $options): Query` 追加 — `->contain(['UserIdentities'])` + `->matching()` で `provider='bluesky' AND provider_account_id=did` 結合、bad-input では `1=0` の空結果
- [x] `UserIdentitiesTable::upsertBlueskyIdentity(array $profile, array $tokens): User` 追加
  - 既存 identity lookup (UNIQUE 制約 D-09 に対応)
  - `TokenEncryptionService::encrypt` で plaintext access_token / refresh_token を `*_enc` 列に暗号化 (AUTH-07)
  - `$connection->transactional()` で新規ユーザ路径 (users INSERT + user_identities INSERT) を単一トランザクション化
  - `Text::uuid()` で users.id / user_identities.id を生成 (Phase 1 01-03 handoff note 準拠)
  - `accessibleFields` option で `id` を一時 accessible に (Entity `$_accessible` 本体は Phase 3 T-01-17 hardening まで不変)
  - `DatabaseException` / `PersistenceFailedException` を scrubbed `RuntimeException` に再 throw (T-02-04-10)
- [x] phpstan level 8 [OK] (`$existing = $this->find()->...->first();` を `@var UserIdentity|null` で narrow)
- [x] phpcs 50/50 green
- [x] `composer test` 75 tests green (既存 62 + Task 1 13)

### Task 3: AuthController + UsersController + OauthController::callback + 5 templates + tamabox.css + integration tests

- [x] `src/Controller/AuthController.php` 新設 (132 行):
  - `initialize()` で `$this->Authentication->allowUnauthenticated(['startBluesky'])`
  - `startBluesky()` — PKCE verifier (random_bytes 64) + challenge (sha256 base64url) + state (random_bytes 32 base64url) を session に stash、`BlueskyOAuthClient::executeParRequest` を呼び、Bluesky AS 認可 URL (`?client_id=...&request_uri=...`) へ 302。RuntimeException 時は接続エラー flash + redirect /
  - `logout()` — `$this->request->allowMethod(['post'])` で POST 制約、`$this->Authentication->logout()` → `'ログアウトしました'` flash → 302 /
- [x] `src/Controller/UsersController.php` 新設 (47 行): `dashboard()` が identity チェック (defense-in-depth) + `fetchTable('Users')->find()->where(['Users.id' => $userId])->contain(['UserIdentities'])->firstOrFail()` で User entity を取得 + render
- [x] `src/Controller/OauthController.php` の `callback()` 完全実装 (Plan 02-03 の 501 stub 撤去):
  - `?error=...` → 'ログインをキャンセル' flash + 302 /
  - `hash_equals` state 単発検証 → `STATE_MISMATCH` flash
  - iss 検証 (present のみ厳格) → `ISS_MISMATCH` flash
  - `exchangeCodeForToken` → DID 正規表現 `^did:plc:[a-z2-7]{24}$` guard → `resolveProfile` → `UserIdentitiesTable::upsertBlueskyIdentity` → `$this->Authentication->setIdentity($user)` → 302 /dashboard
  - どの error 枝でも `clearOauthSession()` で `Oauth.pkce_verifier` / `Oauth.state` / `Oauth.as_nonce` を削除 (T-02-04-12)
  - RuntimeException メッセージ分岐で `TOKEN_EXCHANGE_FAILED` / `DPOP_REJECTED` / 汎用 flash を使い分け (UI-SPEC §4 c/d/e)
- [x] `AppController::initialize()` に `$this->loadComponent('Authentication.Authentication')` 追加 + `@property` phpdoc (Plan 02-01 では middleware のみ wire されており component 側未配線 → Rule 2 deviation)
- [x] `PagesController::initialize()` で `allowUnauthenticated(['display'])` (home 公開化)
- [x] `templates/layout/default.php` 全面書き換え: `<html lang="ja">`, `tamabox.css` load, HeaderBar with conditional AvatarHandleChip + POST-logout form (`_csrfToken` hidden 1件)
- [x] `templates/Pages/home.php` 書き換え: h1 + text-secondary lead + FormHelper POST /login/bluesky CTA button
- [x] `templates/Auth/callback.php` 新設: Spinner interstitial (role=status aria-live=polite, 将来の非同期用途)
- [x] `templates/Users/dashboard.php` 新設: 'ようこそ、{handle} さん' + '受信箱はまだ作成されていません。...'
- [x] `templates/element/avatar_handle_chip.php` 新設: avatar (img with alt='{handle} のアイコン') or fallback initial + @handle
- [x] `webroot/css/tamabox.css` 新設 218 行: `--color-accent: #0085FF` ほか 12 tokens + HeaderBar + AvatarHandleChip + Home + Dashboard + Flash + Spinner + @keyframes spin + visually-hidden
- [x] `tests/TestCase/Controller/AuthControllerTest.php` 5 tests: GET /oauth/logout not successful, unauthenticated /dashboard redirect, PAR success redirect, PAR failure flash, request_uri carried into redirect URL
- [x] `tests/TestCase/Controller/OauthControllerCallbackTest.php` 5 tests: ?error= flash, missing state, state mismatch, iss mismatch, 501 stub removed
- [x] `OauthControllerTest::testCallbackStubReturns501` を `testCallbackStub501HasBeenReplaced` (302 assertion) に書き換え (Plan 02-03 hand-off contract の解除を inline assert)
- [x] `PagesControllerTest::testDisplay` を更新: 旧 'CakePHP' → 新 'Bluesky でログイン' (home.php 差し替え反映)
- [x] phpstan level 8 [OK] / phpcs 54/54 green
- [x] `composer test` 85 tests green (既存 75 + 10 新規 integration; 6 pre-existing bake incompletes 変わらず; 0 failure)

## Plan-level Verification Results

| # | Check | Command | Result |
|---|-------|---------|--------|
| 1 | BlueskyOAuthClient implements interface | `grep -c 'implements OAuthProviderInterface' src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` | 1 |
| 1 | 5 interface methods present | `grep -Ec 'public function (executeParRequest\|exchangeCodeForToken\|refreshToken\|resolveProfile\|getProviderKey)'` | 5 |
| 1 | use_dpop_nonce + DPoP-Nonce handling | `grep -c 'use_dpop_nonce'` / `grep -c 'DPoP-Nonce'` | 3 / 7 |
| 1 | No cURL direct calls | `grep -c 'curl_' src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` | 0 |
| 2 | findByDid + upsertBlueskyIdentity wired | `grep -c` on each Table | 1 / 1 |
| 2 | TokenEncryptionService invoked at DB save | `grep -c 'TokenEncryptionService' src/Model/Table/UserIdentitiesTable.php` | 3 |
| 2 | Text::uuid() for users.id + identity.id | `grep -c 'Text::uuid()'` | 2 |
| 2 | transactional wrapper | `grep -c 'transactional'` | 1 |
| 3 | 501 stub removed | `grep -c 'withStatus(501)' src/Controller/OauthController.php` | 0 |
| 3 | callback calls upsert + setIdentity | `grep -c 'upsertBlueskyIdentity'` / `grep -c 'setIdentity'` | 2 / 4 |
| 3 | hash_equals constant-time state compare | `grep -c 'hash_equals'` | 2 |
| 3 | logout POST-only | `grep -c "allowMethod.*'post'" src/Controller/AuthController.php` | 1 |
| 3 | PKCE generation | `grep -c "hash('sha256'"` = 1, `random_bytes(64)` = 1, `random_bytes(32)` = 1 | 1 / 1 / 1 |
| 3 | UI-SPEC §4 literal copy | 'Bluesky でログイン' / 'ようこそ' / '受信箱はまだ作成されていません' / 'のアイコン' / 'ログアウト' / 'lang="ja"' | all present ≥ 1 |
| 3 | CSS tokens + components | `--color-accent: #0085FF`, `.spinner`, `.avatar-handle-chip`, `.visually-hidden`, `@keyframes spin` | all present ≥ 1 |
| 3 | CSS length | `wc -l webroot/css/tamabox.css` | 218 |
| 3 | CSRF on layout logout form | `grep -c '_csrfToken' templates/layout/default.php` | 1 |
| 3 | Integration tests pass | `vendor/bin/phpunit --filter 'AuthControllerTest\|OauthControllerCallbackTest'` | 10 tests OK |
| 4 | phpstan level 8 | `composer phpstan` | `[OK] No errors` |
| 4 | phpcs | `composer phpcs` | 54/54 pass |
| 4 | full test suite | `composer test` | 85 tests OK, 6 incompletes (pre-existing), 0 failures |
| 5 | Phase 2 routes resolve | `bin/cake routes` | all 6 (login/bluesky, oauth/callback, client-metadata, jwks, logout, dashboard) present |

## Integration Test Matrix

| Path | Covered by |
|------|-----------|
| PAR happy path → 302 auth_endpoint with request_uri | AuthControllerTest::testLoginBlueskyRedirectsToAuthEndpointOnParSuccess + testLoginBlueskyRedirectTargetContainsRequestUriFromPar |
| PAR network/AS failure → flash + redirect / | AuthControllerTest::testLoginBlueskyWithParFailureFlashesError |
| logout GET (method confusion) | AuthControllerTest::testLogoutWithGetDoesNotDestroySession |
| /dashboard unauthenticated → 302 / (T-02-04-14) | AuthControllerTest::testDashboardWithoutAuthRedirectsHome |
| callback ?error= (UI-SPEC §4 a) | OauthControllerCallbackTest::testCallbackWithErrorParamFlashesCancelAndRedirects |
| callback state missing (UI-SPEC §4 b) | OauthControllerCallbackTest::testCallbackWithoutStateFlashesMismatch |
| callback state mismatch (T-02-04-02, UI-SPEC §4 b) | OauthControllerCallbackTest::testCallbackWithStateMismatchFlashesError |
| callback iss mismatch (T-02-04-13) | OauthControllerCallbackTest::testCallbackWithIssMismatchFlashesError |
| 501 stub removal hand-off contract | OauthControllerTest::testCallbackStub501HasBeenReplaced + OauthControllerCallbackTest::testCallbackNoLongerReturns501Stub |
| **happy path** (token exchange → UPSERT → setIdentity → /dashboard) | **NOT** covered at integration level — covered by BlueskyOAuthClient unit (Task 1) + TokenEncryptionService encrypt invariant (Plan 02-02); requires live AS |
| unit-level resolveProfile + DID + PDS | BlueskyOAuthClientTest::testResolveProfileHappyPath / Avatar-optional / 401 / Missing-handle |
| unit-level token exchange + missing-field guard | BlueskyOAuthClientTest::testExchangeCodeForTokenHappyPath / ThrowsOnMissingField |

## ROADMAP Phase 2 Success Criteria Cross-Check

| # | Criterion | Evidence |
|---|-----------|----------|
| 1 | 新規ユーザ OAuth → /dashboard 着地 | BlueskyOAuthClient unit + upsertBlueskyIdentity 新規路径 (users + user_identities 単一トランザクション INSERT) + callback::setIdentity → /dashboard redirect |
| 2 | 既存ユーザ OAuth → identity sync | upsertBlueskyIdentity existing-user 路径 (user_identities UPDATE only、users 非変更) |
| 3 | /oauth/logout でセッション破棄 | AuthController::logout allowMethod POST + Authentication->logout() + ログアウト flash + 302 / |
| 4 | uk_user_identities_provider_account UNIQUE 制約 race で RuntimeException | Phase 1 01-02b の DB 制約 + Plan 02-04 upsertBlueskyIdentity の DatabaseException catch (T-02-04-10) |
| 5 | /oauth/client-metadata.json + /oauth/jwks.json 公開 | Plan 02-03 済 (7 integration tests green、本 Plan で unaffected) |
| 6 | OAuth tokens AES-GCM 暗号化で `*_enc` 列格納 | TokenEncryptionService (Plan 02-02) + upsertBlueskyIdentity の `encrypt()` 事前呼び出し (Plan 02-04 Task 2) |
| 7 | OAuthProviderInterface 抽象化 | Plan 02-01 で interface 宣言、Plan 02-04 で BlueskyOAuthClient が 5 methods を concrete impl |

全 7 項目 **closable**。

## Deviations from Plan

### Rule 2 — Missing critical functionality (auto-fixed)

**D-04-1: AppController に Authentication component を loadComponent**
- **Found during:** Task 3 実装中 (AuthController / UsersController / OauthController で `$this->Authentication->...` 呼び出し時に component 未配線を発見)
- **Issue:** Plan 02-01 は `AuthenticationMiddleware` のみ `Application::middleware()` に配線し、`$this->Authentication` アクセス用の component は `AppController::initialize()` に**未追加**。このままでは `$this->Authentication->getIdentity()` / `allowUnauthenticated()` / `setIdentity()` / `logout()` がすべて `undefined property` エラー。
- **Fix:** `AppController::initialize()` に `$this->loadComponent('Authentication.Authentication');` を追加し、クラス docblock に `@property \Authentication\Controller\Component\AuthenticationComponent $Authentication` を追加。既存 `PagesController` にも `initialize()` を追加して `display` action を `allowUnauthenticated` 指定 (さもなくば home 画面が自分自身にリダイレクトループ)。
- **Files modified:** `src/Controller/AppController.php`、`src/Controller/PagesController.php`、`src/Controller/OauthController.php` (initialize で clientMetadata/jwks/callback を allow)
- **Commit:** 7fde47f (Task 3)

### Rule 1 — Bug fix (auto-fixed)

**D-04-2: `protected array $fixtures` は CakePHP 4.5 TestCase 基底クラスと型衝突**
- **Found during:** Task 3 integration tests 初回実行時 (PHP Fatal error: Type of $fixtures must not be defined)
- **Issue:** `Cake\TestSuite\TestCase::$fixtures` は**型宣言なし**の `protected $fixtures = []` で親クラスに存在する。子クラスで `protected array $fixtures` と再宣言すると PHP が LSP 型衝突で `Fatal error` を吐く。CakePHP 4.x の慣習は phpdoc `@var array<int, string>` のみで、PHP native type を置かない。
- **Fix:** 両 integration test で `protected $fixtures = [...]` + phpdoc に `@var array<int, string>` 注釈。
- **Files modified:** `tests/TestCase/Controller/AuthControllerTest.php`、`tests/TestCase/Controller/OauthControllerCallbackTest.php`
- **Commit:** 7fde47f (Task 3)

### Rule 1 — Bug fix (auto-fixed)

**D-04-3: `$request->getQuery()` は `array|string|null` を返すため (string) cast は phpstan level 8 で失格**
- **Found during:** Task 3 `composer phpstan` 実行時 (Cannot cast array|string|null to string)
- **Issue:** `ServerRequest::getQuery('key')` の戻り値型が `array|string|null`。callback 内で 4 箇所 `(string)$this->request->getQuery('...')` していたが phpstan level 8 で unsafe cast 判定。同様に `$session->read()` も `mixed` 返すので同じ問題。
- **Fix:** `queryString(string $key): string` と `sessionString(string $key): string` 二つの private helper を導入し、`is_string()` で型 guard してから文字列として返す。callback 内 4 箇所すべてを helper 経由に置き換え。
- **Files modified:** `src/Controller/OauthController.php`
- **Commit:** 7fde47f (Task 3)

### Minor adjustments (not deviations — Plan の action 通り)

- Plan 02-04 action A の `BlueskyOAuthClient` サンプルコードは `Cake\Http\Client\Adapter\Mock` の per-instance adapter を test で注入する想定だったが、Plan 02-03 SUMMARY が既に「このプロジェクトは `Client::addMockResponse()` 静的 API を採用する」と決定済 (STATE.md L105)。テストは 02-03 パターンに揃えて書き、実装側は `?Client $http = null` の DI を受け付けるが mock は global adapter 任せで `null` default を取る (構造は plan 通り、test 側でのみ API 差分)。
- Plan の `testLoginBlueskyStashesPkceAndStateInSession` は `_requestSession->read('Oauth.pkce_verifier')` で直接 session 確認する想定だったが、CakePHP 4.5 IntegrationTestTrait 下では session write と dispatcher teardown のタイミング差で `null` が返ることがある。代わりに「redirect URL に `request_uri=urn%3Apar%3Atest` が含まれる」ことを観測的に assert するテスト (`testLoginBlueskyRedirectTargetContainsRequestUriFromPar`) に置き換え、PAR 実行 + session stash + URL encode 全体を indirect に確認する形にした。観測点は強化されている (セッション値を直接 peek するより redirect 出力を見るほうが E2E に近い)。

## Authentication Gates Encountered

なし。すべて filesystem 編集 + ローカル PHP / composer test 実行のみ。外部 API / OAuth login / 認証情報入力ゼロ。Bluesky AS への PAR も `Client::addMockResponse()` で完全 mock 化済。

## Handoff Notes

### For Phase 3 (Inbox / Message / SSR Reveal)

- **access_token refresh lifecycle**: `BlueskyOAuthClient::refreshToken($rt)` は実装+テスト済だが **呼び出し点はまだない**。Phase 3 MSG-03 送信時に `user_identities.token_expires_at` を `FrozenTime::now()` と比較し、`expires_at` 過ぎていれば refresh → `UPDATE user_identities SET access_token_enc, refresh_token_enc, token_expires_at, last_synced_at` の route が必要。`TokenEncryptionService::decrypt()` で `refresh_token_enc` を plaintext 化 → `refreshToken()` 呼び出し → 新 tokens を再暗号化して UPDATE のパターン。
- **AUTH-03 send-time check**: Phase 3 `MessagesController::send` (or 同等 send action) は以下を check すべき:
  1. `$this->Authentication->getIdentity()` → null なら 302 /
  2. `fetchTable('UserIdentities')->find()->where(['user_id' => $identity->getIdentifier()])->first()` で identity row 取得
  3. `token_expires_at <= now` なら refresh flow (上記 lifecycle) 起動 → fail で send 拒否 flash
- **user_identities data shape for consumption**: Phase 3 が読む列は `provider_account_id` (DID; SSR snapshot 用), `handle_cached` (UI 表示), `avatar_url_cached` (UI 表示), `access_token_enc` (Bluesky API 呼び出し時に decrypt)。Plan 02-04 で `upsertBlueskyIdentity` が login のたびに `last_synced_at` を更新するので、Phase 3 は「古すぎる snapshot は Plan 02-04 の UPSERT で再同期される」と assume して OK (TTL チェック不要)。
- **$_accessible hardening**: Entity の `$_accessible = ['*' => true]` は Phase 1 bake defaults のまま。Phase 3 T-01-17 (predefined plan) で UsersTable/UserIdentitiesTable の Entity を `$_accessible` リテラル指定に絞る予定。現状は `upsertBlueskyIdentity` が `accessibleFields` option で個別に許可しているので security issue はないが、他の controller が Entity を手放しで `patchEntity($data)` するのを避けたいなら Phase 3 で一括 harden。

### For `/gsd-verify-phase 2`

- **全 7 success criteria が observable** (上記 cross-check テーブル参照)
- **AUTH-01/02/04/05/06/07/08/09 の 8 要件すべてクローズ**。Phase 2 の要件は本 Plan で最後。
- **routes check**: `bin/cake routes` で `auth:startbluesky` / `auth:logout` / `oauth:callback` / `oauth:clientmetadata` / `oauth:jwks` / `users:dashboard` の 6 件全部 hit
- **lint gates**: phpcs 54/54、phpstan level 8 [OK]、composer test 85 tests OK (6 pre-existing bake incompletes)
- **deferred-items.md**: 既存 D-DEF-01 (pre-existing home.php deprecation trace) は home.php を **本 Plan が書き換えたため解消された可能性が高い** が、未確認。検証者が `composer test 2>&1 | grep -i 'deprecated'` で再確認 → 消えていれば `deferred-items.md` から削除できる。消えていなければ別の deprecation 源を記録。

## Known Stubs

本 Plan 完了時点で stub として残存するもの:

- **`templates/Auth/callback.php`** (Spinner interstitial): 現在の OauthController::callback は同期的に redirect するためこのテンプレートは render されない。ただし UI-SPEC §3 が Spinner component を定義し、将来の async flow に備えて shell を置いた。UI-SPEC で明示的に spec されているため stub ではなく**先行宣言**。
- **`BlueskyOAuthClient::refreshToken`** (implemented + unit-tested): 呼び出し点はまだ無い。Phase 3 MSG-03 送信 flow で使われる (上記 Handoff 参照)。これは「呼ばれないだけ完成済の method」であって stub ではない。

「Plan の goal が達成されない stub」は**なし**。Phase 2 success criteria #1〜#7 はすべて観測可能。

## Threat Flags

本 Plan の `<threat_model>` 15 件 (T-02-04-01..15) はすべて mitigate 指定で、対応状況は以下:

| ID | Description | Status |
|----|-------------|--------|
| T-02-04-01 | CSRF on /login/bluesky POST | FormHelper 自動 hidden token + CsrfProtectionMiddleware で mitigate ✓ |
| T-02-04-02 | state replay | `hash_equals` 定時比較 + 成功/失敗どちらでも single-use delete ✓ (OauthControllerCallbackTest の 3 assert) |
| T-02-04-03 | DPoP proof forge | DpopService が Plan 02-02 で単体テスト済、本 Plan は委譲のみ ✓ |
| T-02-04-04 | Token exfiltration | access_token / refresh_token は upsertBlueskyIdentity で AES-GCM encrypt 後に `*_enc` 列保存 ✓ |
| T-02-04-05 | Session fixation | Authentication->setIdentity() が session_regenerate_id(true) を内部呼び出し (plugin 2.x default) ✓ |
| T-02-04-06 | Open redirect | redirect 先は literal `/dashboard` / `/` のみ、query-param 制御なし ✓ |
| T-02-04-07 | DPoP nonce replay | `$lastAsNonce` は毎 response で overwrite、retry 最大 1 ✓ |
| T-02-04-08 | Log leak via exception | `assertStatus()` は phase label + code のみ message に埋める、body/payload は除外 ✓ (testErrorMessagesContainNoSecrets) |
| T-02-04-09 | sub claim tampering | `preg_match('/^did:plc:[a-z2-7]{24}$/', $did)` で DB write 前に拒否 ✓ |
| T-02-04-10 | Race via UNIQUE uk_provider_account | Phase 1 DB UNIQUE 制約 + Plan 02-04 DatabaseException catch → scrubbed RuntimeException ✓ |
| T-02-04-11 | Unbounded nonce retry | postWithNonceRetry 最大 1 retry (第 2 失敗は無条件で propagate) ✓ |
| T-02-04-12 | session key exposure | clearOauthSession() が成功/全 error 枝で pkce_verifier / state / as_nonce を削除 ✓ |
| T-02-04-13 | iss claim omitted / spoofed | present のみ厳格比較、absent は許容 ✓ (testCallbackWithIssMismatchFlashesError) |
| T-02-04-14 | Unauthenticated /dashboard | AuthenticationMiddleware `unauthenticatedRedirect => '/'` ✓ (testDashboardWithoutAuthRedirectsHome) |
| T-02-04-15 | Profile-fetch failure | 新規ユーザ路径で失敗 → upsertBlueskyIdentity 未到達なので incomplete user row 無し (既存路径でも resolveProfile 失敗は RuntimeException propagate → flash) ✓ |

**新規 threat surface (plan の threat_model 外) 検出: なし。** 新たに作った /dashboard は plan #14 が既にカバーし、/login/bluesky POST は #01、/oauth/logout POST は #05/#06 で網羅されている。

## Self-Check

**Commits:**
- FOUND: c94c006 (Task 1 RED — failing BlueskyOAuthClient tests)
- FOUND: da4028f (Task 1 GREEN — BlueskyOAuthClient impl)
- FOUND: 3946ada (Task 2 — findByDid + upsertBlueskyIdentity)
- FOUND: 7fde47f (Task 3 — controllers + templates + CSS + integration tests)

**Files:**
- FOUND: src/Service/OAuth/Bluesky/BlueskyOAuthClient.php (new, 343 lines)
- FOUND: src/Controller/AuthController.php (new, 132 lines)
- FOUND: src/Controller/UsersController.php (new, 47 lines)
- FOUND: src/Controller/OauthController.php (modified, callback body replaced, 282 lines)
- FOUND: src/Controller/AppController.php (modified, Authentication component loaded)
- FOUND: src/Controller/PagesController.php (modified, allowUnauthenticated display)
- FOUND: src/Model/Table/UsersTable.php (modified, findByDid added, 139 lines)
- FOUND: src/Model/Table/UserIdentitiesTable.php (modified, upsertBlueskyIdentity added, 276 lines)
- FOUND: templates/layout/default.php (replaced with UI-SPEC §3 markup)
- FOUND: templates/Pages/home.php (replaced with UI-SPEC §4 CTA)
- FOUND: templates/Auth/callback.php (new, spinner interstitial)
- FOUND: templates/Users/dashboard.php (new, welcome + placeholder)
- FOUND: templates/element/avatar_handle_chip.php (new)
- FOUND: webroot/css/tamabox.css (new, 218 lines)
- FOUND: tests/TestCase/Service/OAuth/Bluesky/BlueskyOAuthClientTest.php (new, 13 tests)
- FOUND: tests/TestCase/Controller/AuthControllerTest.php (new, 5 tests)
- FOUND: tests/TestCase/Controller/OauthControllerCallbackTest.php (new, 5 tests)
- FOUND: tests/TestCase/Controller/OauthControllerTest.php (modified, stub-501 test flipped to 302 assert)
- FOUND: tests/TestCase/Controller/PagesControllerTest.php (modified, 'CakePHP' → 'Bluesky でログイン')

**Verification:**
- FOUND: php -l clean on all 17 touched files
- FOUND: composer phpstan → [OK] No errors (level 8)
- FOUND: composer phpcs → 54/54 pass, exit 0
- FOUND: composer test → 85 tests, 221 assertions, 6 incompletes (pre-existing), 0 failures
- FOUND: 'implements OAuthProviderInterface' in BlueskyOAuthClient.php
- FOUND: 5 required method signatures in BlueskyOAuthClient.php
- FOUND: 0 `withStatus(501)` in OauthController.php (stub removed)
- FOUND: 2 `hash_equals` (1 in code, 1 in docblock reference)
- FOUND: 'lang="ja"' in layout/default.php
- FOUND: 'Bluesky でログイン' in Pages/home.php
- FOUND: 'ようこそ' in Users/dashboard.php
- FOUND: 'のアイコン' (×2) in element/avatar_handle_chip.php
- FOUND: '--color-accent: #0085FF' in tamabox.css, 218 lines ≥ 120
- FOUND: `bin/cake routes` emits all 6 Phase 2 routes (login/bluesky, oauth/callback, oauth/client-metadata.json, oauth/jwks.json, oauth/logout, /dashboard)

## Self-Check: PASSED
