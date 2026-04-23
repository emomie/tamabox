# Phase 2: Bluesky OAuth & Identity — Research

**Researched:** 2026-04-23
**Domain:** AT Protocol OAuth 2.0 (PAR + PKCE + DPoP + private_key_jwt) on CakePHP 4.5 / PHP 8.3
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **D-01** JWT/OAuth ライブラリは使わず自前実装。altotoo の `BlueskyOauthComponent.php` を参照実装として PHP ビルトイン (`openssl_sign` / base64url / PKCE / DPoP JWT / client_assertion) のみで実装する。`composer require` で JWT ライブラリは増やさない。
- **D-02** CakePHP Authentication Plugin (`cakephp/authentication ^2.11`) を導入。`Session` Authenticator、カスタム識別子。
- **D-03** Service 層分離: `OAuthProviderInterface` / `BlueskyOAuthClient` / `DpopService` / `ClientJwtService` / `DidResolver` / `KeyManager` / `TokenEncryptionService`
- **D-04** Controller 配置: `AuthController` (`/login/bluesky`, `/logout`) + `OauthController` (`/oauth/callback`, `/oauth/client-metadata.json`, `/oauth/jwks.json`) + `UsersController::dashboard`
- **D-05** Endpoint 静的解決。`config/bluesky.php` + `.env` で bsky.social 決め打ち。動的 PDS 解決は V1 範囲外。
- **D-06** Scope = `atproto transition:generic`
- **D-07** handle 入力 UI なし。`/login/bluesky` は即 PAR → redirect。AS 側でユーザが handle 入力する。
- **D-08** token / identity は DB 永続化 (`user_identities.*_enc`)。セッションには `user_id` と DID のみ。
- **D-09** 1 ユーザー = 1 SNS 制約: `uk_user_identities_provider_account` UNIQUE 制約で MySQL 側エンフォース。UPSERT は `ON DUPLICATE KEY UPDATE` か try/catch。
- **D-10** DPoP-Nonce リトライ: 初回 nonce なし → 400/401 + `use_dpop_nonce` → header から nonce 抽出 → 再送。
- **D-11** DER → Raw 署名変換: altotoo `derToRawSignature()` 踏襲。
- **D-12** DPoP proof JWT header に `jwk` claim を埋める。
- **D-13** access_token 付き PDS 呼び出し時は `ath = base64url(sha256(access_token))` を DPoP proof に含める。
- **D-14** ES256 鍵ペアは `config/keys/private.key` / `public.key`。`OAUTH_KID` は `.env` の `OAUTH_KID` から読む。
- **D-15** AES-256-GCM 鍵 (`TOKEN_ENC_KEY`) を `.env` に 32 バイト hex で保管。フォーマット `base64url(iv(12) || ciphertext || tag(16))`。
- **D-16** `/oauth/client-metadata.json` は Controller 動的生成。`client_id` は配信 URL と完全一致。
- **D-17** `/oauth/jwks.json` は Controller 動的生成。PEM → JWK 変換 (`openssl_pkey_get_details`)。
- **D-18** ログアウトはセッション破棄のみ。Bluesky 側トークン revoke は行わない。POST 限定 + CSRF 必須。

### Claude's Discretion

- エラー UX の具体文言、再試行ボタン設置位置
- `config/bluesky.php` の具体ファイル構造・キー名
- Controller 内の Flash message 文言
- Test fixture の構成 (`UserIdentitiesFixture` の encrypted token サンプル値等)
- OAuth 関連 routes の具体名 (AUTH-FLOW.md の `/login/bluesky` 方針を尊重しつつ微調整可)
- `bin/cake oauth:setup_keys` コマンドを用意するかどうか
- DB トランザクション境界 (新規ユーザ INSERT の `users` + `user_identities` 同時性)
- Nonce セッション保存の key 名 (`bsky_as_nonce` 等)
- `/dashboard` プレースホルダ View の具体レイアウト

### Deferred Ideas (OUT OF SCOPE)

- Bluesky 投稿機能 (`transition:generic` scope 活用)
- X (Twitter) OAuth 追加 (v2)
- third-party PDS / did:web 対応
- `token_enc_key_version` カラム追加による鍵ローテーション
- Bluesky 側トークン revoke on logout
- Refresh token 自動更新・並行リクエスト保護 (`SELECT ... FOR UPDATE`)
- AS metadata 動的解決
- Handle → DID 自前解決 (`.well-known/atproto-did` / DNS TXT / DoH)
- `bin/cake oauth:setup_keys` CLI ツール (Claude's Discretion に移動)

</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| AUTH-01 | Bluesky OAuth (AT Protocol, ES256 confidential client, PAR + DPoP + PKCE 必須) でサインアップできる | AT Protocol OAuth フロー §2、altotoo 参照実装 |
| AUTH-02 | 既存ユーザーが Bluesky OAuth でログインでき、セッションが確立する | `user_identities` UPSERT + CakePHP Authentication Plugin §5 |
| AUTH-04 | `users` × `user_identities` で 1:1 DB 制約担保 | Phase 1 で UNIQUE 制約設置済、UPSERT 設計 §5 |
| AUTH-05 | ログイン時に SNS handle / avatar / profile_url を最新取得して DB 同期 | getProfile API (PDS 経由) §4 |
| AUTH-06 | OAuth プロバイダインタフェース抽象化 | `OAuthProviderInterface` 設計 §Service 層分離 |
| AUTH-07 | access/refresh token を AES-GCM でアプリ側暗号化して `*_enc` 列に格納 | `TokenEncryptionService` 設計 §3 |
| AUTH-08 | ES256 鍵ペアを `config/keys/` に配置、`jwks.json` と `client-metadata.json` を公開 | Client Metadata §Endpoint 設計 |
| AUTH-09 | ログアウト機能 (セッション破棄 + CSRF 対応) | §Logout 設計 |

</phase_requirements>

---

## Research Summary

AT Protocol OAuth 2.0 は標準 OAuth 2.0 のスーパーセットではなく、PAR / DPoP / private_key_jwt の3要素が全て必須という独自仕様を持つ。PHP ビルトイン (`openssl_sign`、`openssl_pkey_*`、`openssl_encrypt`) は本番環境 (PHP 8.3.6) でこれらを全て実装できることを確認済みである。altotoo (`altotoo.emomie.com`) が同じ Lolipop 環境・同一フロー・PHP ビルトイン自前実装で本番稼働しており、tamabox Phase 2 の直接的な参照実装として利用できる。CakePHP Authentication Plugin v2.11 は CakePHP 4.x と互換であり、Session Authenticator + カスタム Identifier でセッション管理を構築できる。主要な落とし穴はすべて altotoo が踏み潰しており、DER→Raw 署名変換・DPoP-Nonce 2 回送り・`jwk` header 埋め込み・`ath` claim 必須という 4 点を踏襲することで再発を防げる。

**Primary recommendation:** altotoo `BlueskyOauthComponent.php` をそのまま Service 層に分割移植すること。0 から書くよりも altotoo の既存ロジックを 1:1 でリファクタリングする方が罠を踏まない。

---

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| OAuth フロー開始 (PAR) | API / Backend (`AuthController`) | Service 層 (`BlueskyOAuthClient`) | サーバーサイド confidential client — ブラウザに秘密鍵を渡さない |
| Callback 処理 / token 交換 | API / Backend (`OauthController`) | Service 層 | state / code / nonce をすべてサーバー側で検証 |
| DPoP proof 生成 | Service 層 (`DpopService`) | — | 秘密鍵操作はブラウザ不可 |
| client_assertion 生成 | Service 層 (`ClientJwtService`) | — | 秘密鍵操作はブラウザ不可 |
| client-metadata.json 配信 | API / Backend (`OauthController`) | — | `Content-Type: application/json`、リダイレクト不可 |
| jwks.json 配信 | API / Backend (`OauthController`) | — | public.key を JWK に変換して返す |
| token 暗号化・復号 | Service 層 (`TokenEncryptionService`) | Database / Storage | AES-GCM はサーバー側、暗号文は MySQL `*_enc` 列 |
| DID → PDS 解決 | Service 層 (`DidResolver`) | — | plc.directory への HTTP 呼び出し |
| profile 取得 | Service 層 (`BlueskyOAuthClient::getProfile`) | — | PDS xrpc 経由、DPoP binding |
| セッション確立 | Frontend Server (CakePHP middleware) | Database | CakePHP Authentication Plugin |
| ログアウト | API / Backend (`AuthController`) | — | POST + CSRF、セッション破棄のみ |

---

## AT Protocol OAuth Flow (PAR / authorization / token / DPoP)

[VERIFIED: `.planning/references/altotoo/BlueskyOauthComponent.php` + `AUTH-FLOW.md`]

### 前提: AT Protocol と標準 OAuth 2.0 の違い

AT Protocol OAuth は以下の3要素が **全て同時に必須**:

1. **PAR (RFC 9126)** — authorization request を AS に先送りし、`request_uri` を取得してからブラウザをリダイレクトする。クエリパラメータで認証情報を渡さない。
2. **PKCE (RFC 7636)** — `code_verifier` / `code_challenge` (S256)。PAR 送信時に `code_challenge` を含め、token 交換時に `code_verifier` を送る。
3. **DPoP (RFC 9449)** — すべての PAR / token request に DPoP Proof JWT を付ける。proof は ES256 秘密鍵で署名し、header に公開鍵 (`jwk`) を埋め込む。

加えて **confidential client** として `private_key_jwt` (`client_assertion`) で自己認証する。

### Step-by-Step フロー

```
[ブラウザ] GET /login/bluesky
    ↓
[AuthController::startBluesky()]
  1. PKCE生成: verifier = base64url(random_bytes(64))
                challenge = base64url(sha256(verifier))
  2. state生成: base64url(random_bytes(32))
  3. セッション保存: pkce_verifier, oauth_state
  4. client_assertion生成 (ClientJwtService)
     {iss=client_id, sub=client_id, aud=par_endpoint, jti=random, iat, exp=now+60}
     → ES256署名 (config/keys/private.key)
  5. DPoP proof生成 (DpopService)
     header: {typ:"dpop+jwt", alg:"ES256", jwk:{kty,crv,x,y}}
     payload: {htm:"POST", htu:par_endpoint, iat, exp=now+60, jti=random}
     → ES256署名 (同じ秘密鍵)
  6. POST par_endpoint (DPoP: <proof>)
     params: client_id, response_type=code, code_challenge, code_challenge_method=S256,
             redirect_uri, state, scope="atproto transition:generic",
             client_assertion_type=urn:...:jwt-bearer, client_assertion

  ★ 初回は nonce なしで送る → AS が 400 + body.error="use_dpop_nonce" を返す想定
  ★ DPoP-Nonce レスポンスヘッダを抽出 → proof を nonce 含みで再生成 → 再送
  ← {request_uri, expires_in:60}

  7. 302 redirect: authorization_endpoint?client_id=<>&request_uri=<>
                   (パラメータはこの2つのみ — PAR済のため)
    ↓
[Bluesky AS でユーザが handle 入力・承認]
    ↓
[ブラウザ] GET /oauth/callback?code=<>&state=<>&iss=<>
    ↓
[OauthController::callback()]
  8. state 検証: $_SESSION['oauth_state'] と一致するか
  9. iss 検証: BLUESKY_ISSUER と一致するか
  10. client_assertion再生成 (aud=token_endpoint)
  11. DPoP proof再生成 (htm=POST, htu=token_endpoint, nonce=セッション値があれば)
  12. POST token_endpoint (DPoP: <proof>)
      params: grant_type=authorization_code, code, redirect_uri, code_verifier,
              client_id, client_assertion_type, client_assertion

  ★ 同様に 400 + use_dpop_nonce → nonce 込みで再送
  ← {access_token, refresh_token, token_type:"DPoP", expires_in, sub:<DID>}

  13. DID 検証: token_response.sub が期待形式 (did:plc:...) か確認
  14. DidResolver::resolve(did) → plc.directory/<did> → PDS URL
  15. getProfile(did, access_token, pds_url) → handle, avatar, profile_url
       ★ この呼び出しは DPoP + ath claim 必須 (D-13)
  16. TokenEncryptionService::encrypt(access_token), encrypt(refresh_token)
  17. UsersTable + UserIdentitiesTable UPSERT (トランザクション)
  18. Authentication->setIdentity($user)
  19. 302 redirect → /dashboard
```

### bsky.social の静的エンドポイント (D-05)

| 設定キー | 値 |
|---|---|
| `BLUESKY_ISSUER` | `https://bsky.social` |
| `BLUESKY_PAR_ENDPOINT` | `https://bsky.social/oauth/par` |
| `BLUESKY_TOKEN_ENDPOINT` | `https://bsky.social/oauth/token` |
| `BLUESKY_AUTH_ENDPOINT` | `https://bsky.social/oauth/authorize` |

[ASSUMED] これらの URL は 2026-04-23 時点の bsky.social の実際のエンドポイント値であることは未確認。altotoo が使用している値が正しいと想定している。初期セットアップタスクで `https://bsky.social/.well-known/oauth-authorization-server` を一度確認することを推奨する。

---

## Client Metadata (what needs publishing, where, format)

[VERIFIED: `AUTH-FLOW.md §1` + `CONTEXT.md D-16`]

AT Protocol では `client_id` = `client-metadata.json` の配信 URL が完全一致する必要がある。

### 必須フィールド (D-16 確定版、scope は D-06)

```json
{
  "client_id": "https://tamabox.emomie.com/oauth/client-metadata.json",
  "application_type": "web",
  "client_name": "tamabox",
  "client_uri": "https://tamabox.emomie.com",
  "redirect_uris": ["https://tamabox.emomie.com/oauth/callback"],
  "grant_types": ["authorization_code", "refresh_token"],
  "response_types": ["code"],
  "scope": "atproto transition:generic",
  "token_endpoint_auth_method": "private_key_jwt",
  "token_endpoint_auth_signing_alg": "ES256",
  "dpop_bound_access_tokens": true,
  "jwks_uri": "https://tamabox.emomie.com/oauth/jwks.json"
}
```

### 実装上の必須事項

- `Content-Type: application/json` で返す (CakePHP: `$this->response->withType('json')`)
- HTTP 200 直返し — リダイレクトすると AS がエラー
- `client_id` フィールドと配信 URL が **1 バイトも違えば** AS がエラー (末尾スラッシュ有無も含む)
- `logo_uri` / `tos_uri` / `policy_uri` はオプションだが設定すると AS 側の同意画面に表示される

### jwks.json フォーマット

```json
{
  "keys": [{
    "kty": "EC",
    "crv": "P-256",
    "kid": "<OAUTH_KID>",
    "use": "sig",
    "alg": "ES256",
    "x": "<base64url>",
    "y": "<base64url>"
  }]
}
```

`openssl_pkey_get_details($privkey)['ec']['x']` と `['ec']['y']` は生バイト列なので `base64url_encode` する。
PHP 8.3.6 で動作確認済み: `ec.x_len=32, ec.y_len=32` [VERIFIED: ローカル実行]

---

## DPoP Implementation in PHP

[VERIFIED: `BlueskyOauthComponent.php` 全文確認、PHP 8.3.6 で openssl 動作確認済]

### altotoo が実証した PHP ビルトイン実装 (ライブラリ不使用)

**全コンポーネントが PHP ビルトイン + openssl で実装可能**であることを確認済み:

| 機能 | PHP 関数 | 注意点 |
|---|---|---|
| base64url encode | `base64_encode` + `strtr('+/→-_')` + `rtrim('=')` | 標準 |
| PKCE verifier | `random_bytes(64)` → base64url | 64バイト |
| PKCE challenge | `hash('sha256', $verifier, true)` → base64url | `true` で生バイト出力 |
| ES256 署名 | `openssl_sign($input, $sig, $privkey, OPENSSL_ALGO_SHA256)` | DER 出力に注意 |
| DER→Raw 変換 | `derToRawSignature()` (altotoo) | 必須・後述 |
| 公開鍵 x/y 取得 | `openssl_pkey_get_details($key)['ec']['x/y']` | 生バイト |
| EC keygen | `openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC, 'curve_name'=>'prime256v1'])` | PHP 8 で確認済み |
| AES-256-GCM | `openssl_encrypt($pt, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag)` | PHP 8 で確認済み |
| access_token hash | `hash('sha256', $access_token, true)` → base64url | `ath` claim 用 |
| random jti | `random_bytes(32)` → base64url | replay 防止 |

### DER → Raw 変換 (altotoo `derToRawSignature()`)

PHP `openssl_sign(..., OPENSSL_ALGO_SHA256)` の出力は **DER エンコードされた ECDSA 署名**であり、JWT ES256 が要求する **64 バイト固定の R||S Raw 形式**とは異なる。

altotoo の実装 (`.planning/references/altotoo/BlueskyOauthComponent.php` L37-70):

```php
// DER 構造: 0x30 [len] 0x02 [r_len] [r] 0x02 [s_len] [s]
$pos = 2; // Sequence tag + length をスキップ
$r_len = ord($der[$pos+1]); $r = substr($der, $pos+2, $r_len);
$s_len = ord($der[$pos+2+$r_len+1]); $s = substr($der, $pos+2+$r_len+2, $s_len);
// 先頭 0x00 パディング除去 (DER はゼロを先頭に付ける場合がある)
if (strlen($r) > 32 && ord($r[0]) === 0) $r = substr($r, 1);
if (strlen($s) > 32 && ord($s[0]) === 0) $s = substr($s, 1);
// 32 バイトに左ゼロパディング
return str_pad($r, 32, chr(0), STR_PAD_LEFT) . str_pad($s, 32, chr(0), STR_PAD_LEFT);
```

### DPoP Proof JWT 構造

```
header: {
  "typ": "dpop+jwt",
  "alg": "ES256",
  "jwk": {"kty":"EC","crv":"P-256","x":"<b64u>","y":"<b64u>","use":"sig"}
}
payload: {
  "htm": "<HTTP_METHOD>",          // 大文字 "POST", "GET"
  "htu": "<endpoint_url>",         // クエリなしの URL
  "iat": <now>,
  "exp": <now+60>,
  "jti": "<random>",               // replay 防止、毎回生成
  "nonce": "<dpop_nonce>",         // AS から取得した nonce (再試行時のみ)
  "ath": "<base64url(sha256(at))>" // access_token 使用時のみ
}
```

**重要:** `DpopService` はステートレスにする（毎回新しい `jti` と `iat` を生成）。セッションに保存するのは AS から返ってきた `nonce` のみ。

---

## Handle / DID Resolution

[VERIFIED: `BlueskyOauthComponent.php::resolveDidToPds()` + `AUTH-FLOW.md`]

### Phase 2 で実装するのは DID → PDS 解決のみ (D-07)

handle 入力 UI を設けないため、`handle → DID` 解決は不要。altotoo の `resolveDidToPds($did)` と同等のロジックのみ実装する:

```
DID → DID document → PDS URL
GET https://plc.directory/<did>
  ← { "service": [{"type":"AtprotoPersonalDataServer","serviceEndpoint":"<pds_url>"}] }
```

**plc.directory はグローバルな DID レジストリ**であり、`did:plc:` 形式の全 DID を解決できる。MVP では did:web 対応は不要 (DEFERRED)。

### DID の形式検証

token response の `sub` クレームは `did:plc:` で始まる文字列。最低限 `preg_match('/^did:plc:[a-z2-7]{24}$/', $did)` で形式チェックする。

### Profile 取得 API

```
PDS: https://<pds_url>/xrpc/app.bsky.actor.getProfile?actor=<did>
Authorization: DPoP <access_token>
DPoP: <proof with ath=base64url(sha256(access_token))>
```

レスポンス:
- `handle`: Bluesky handle (例: `alice.bsky.social`)
- `avatar`: CDN URL (nullable)
- `displayName`: 表示名 (nullable)

`profile_url_cached` は `https://bsky.app/profile/<handle>` で生成する。

---

## Token & Session Storage

[VERIFIED: `CONTEXT.md D-08, D-15` + `DB-SCHEMA.md §2 user_identities`]

### Phase 1 で設置済みの user_identities カラム構成

| カラム | 型 | 用途 |
|---|---|---|
| `id` | CHAR(36) UUID | PK |
| `user_id` | CHAR(36) | FK → users |
| `provider` | ENUM('bluesky','x') | プロバイダ識別 |
| `provider_account_id` | VARCHAR(255) NOT NULL | DID (did:plc:...) |
| `handle_cached` | VARCHAR(255) NOT NULL | ログイン時に同期 |
| `avatar_url_cached` | VARCHAR(2048) | nullable |
| `profile_url_cached` | VARCHAR(2048) | `https://bsky.app/profile/<handle>` |
| `access_token_enc` | TEXT | AES-256-GCM 暗号文 (base64url) |
| `refresh_token_enc` | TEXT | AES-256-GCM 暗号文 (base64url) |
| `token_expires_at` | DATETIME(6) | `now + expires_in` 秒 |
| `last_synced_at` | DATETIME(6) | ログイン成功時更新 |
| `is_primary` | TINYINT(1) DEFAULT 1 | MVP は常 true |
| `created_at` | DATETIME(6) | |
| `updated_at` | TIMESTAMP(6) | |

UNIQUE 制約:
- `uk_user_identities_provider_account` = UNIQUE(`provider`, `provider_account_id`)
- `uk_user_identities_user` = UNIQUE(`user_id`) WHERE `is_primary=1` (MVP では全行が is_primary=1)

### AES-256-GCM 暗号化フォーマット (D-15)

```
base64url( IV(12バイト) || ciphertext || tag(16バイト) )
```

PHP コード (PHP 8.3.6 確認済み):
```php
$key = hex2bin(env('TOKEN_ENC_KEY'));  // 32バイト
$iv = random_bytes(12);               // 毎回ランダム
$ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key,
    OPENSSL_RAW_DATA, $iv, $tag, '', 16);
$encoded = base64url_encode($iv . $ciphertext . $tag);
```

復号:
```php
$raw = base64url_decode($encoded);
$iv = substr($raw, 0, 12);
$tag = substr($raw, -16);
$ct = substr($raw, 12, strlen($raw) - 28);
$plaintext = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
```

### セッション設計 (D-08)

セッションには PII 最小化原則で以下のみ保存:
- `user_id`: CakePHP Authentication が管理 (UUID)
- `did`: DID (token 検証用)
- `bsky_as_nonce` (仮): DPoP-Nonce (PAR/token 再試行用)
- `pkce_verifier`: OAuth フロー中のみ (callback 完了後に破棄)
- `oauth_state`: OAuth フロー中のみ (callback 完了後に破棄)

**access_token / refresh_token はセッションに保存しない。** DB の `*_enc` 列から必要時に復号する。

### UPSERT 戦略 (D-09)

```php
// CakePHP ORM で UPSERT
try {
    $identity = $userIdentitiesTable
        ->find()->where(['provider' => 'bluesky', 'provider_account_id' => $did])
        ->first();
    if ($identity === null) {
        // 新規ユーザ: users + user_identities をトランザクション内で INSERT
        $this->getConnection()->transactional(function() use (...) { ... });
    } else {
        // 既存: handle/avatar/token だけ UPDATE (users テーブルは触らない)
        $userIdentitiesTable->patchEntity($identity, [...]);
        $userIdentitiesTable->saveOrFail($identity);
    }
} catch (\Cake\Database\Exception\DatabaseException $e) {
    // UNIQUE 制約違反 → 別ユーザが同一 DID で登録済み (レースコンディション)
    // → ログアウト後エラー表示
}
```

---

## Reference Implementations

[VERIFIED: 実ファイル読み込み済み]

### 参照実装一覧

| 名称 | 所在 | 概要 |
|---|---|---|
| **altotoo BlueskyOauthComponent.php** | `.planning/references/altotoo/BlueskyOauthComponent.php` | Lolipop + CakePHP 4 で本番稼働する PHP ビルトイン自前実装 (~660行)。PKCE / JWT / DPoP / PAR / token exchange / DID解決 / getProfile / 投稿機能をすべて含む。**Phase 2 の primary reference。** |
| **altotoo LoginController.php** | `.planning/references/altotoo/LoginController.php` | `oauthLogin()` + `callback()` のフロー参照。altotoo は $_SESSION 直書き・DB 永続化なしの点が tamabox と異なる。 |
| **AUTH-FLOW.md** | `/home/claude/projects/ssr-box-discovery/AUTH-FLOW.md` | tamabox discovery の OAuth シーケンス全量。CONTEXT.md での D-05 (静的 endpoint) や D-07 (handle 入力 UI なし) によって一部手順がスキップされる点に注意。 |
| **atproto/oauth-client (TypeScript)** | https://github.com/bluesky-social/atproto (packages/oauth/oauth-client-*) | Bluesky 公式 OAuth クライアント。参考にできるがロジックを直訳すると over-engineering になる。altotoo の方が PHP での参考価値が高い。 |
| **pilcrowonpaper/atproto-oauth-example** | https://github.com/pilcrowonpaper/atproto-oauth-example | Astro (JS) だが DPoP フローのロジックを追うのに有益。 |

**コミュニティ PHP ポート:** 2026-04-23 時点で AT Protocol OAuth (DPoP + PAR) の完成度が高い PHP ライブラリは公開されていない。[ASSUMED] web-token/jwt-framework には DPoP サポートがあるが、CONTEXT.md D-01 でライブラリ不使用と確定しているため不要。

---

## Security Considerations

[VERIFIED: `AUTH-FLOW.md §セキュリティ要点` + ASVS カテゴリ確認]

### CSRF / state

- **state**: `random_bytes(32)` で生成、セッションに保存、callback で検証必須 (D-CSRF-1)
- `/oauth/callback` は **GET request** なので CakePHP の CSRF Middleware を CSRF 免除にする必要がある (CakePHP の CSRFProtectionMiddleware は GET には適用されないが、正確には `addUnlockedActions(['callback'])` または routes で適用除外を確認する)
- `/logout` は **POST のみ許可** + CSRF トークン必須 (D-18)

### nonce handling

- DPoP-Nonce はセッション (`$_SESSION['bsky_as_nonce']` 相当) に保存し、AS リクエスト毎に更新する
- AUTH-FLOW.md では AS nonce と RS nonce を分けて `nonce_as` / `nonce_rs` として保存することを推奨しているが、Phase 2 では PDS API 呼び出し (getProfile) を 1 回のみ行うため単一 nonce で十分 [ASSUMED: bsky.social AS と PDS が同一 nonce を返すと仮定]

### token binding (DPoP)

- DPoP は **proof-of-possession** の仕組みで、access_token が漏れても DPoP 秘密鍵がなければ使えない
- Phase 2 では DPoP 秘密鍵を `config/keys/private.key` のアプリ鍵と**兼用**する (altotoo と同じ)
- AUTH-FLOW.md の「DPoP セッション鍵 (セッション毎生成)」方式は採用しない → 鍵管理の簡略化 (D-01 に準拠)

### replay prevention

- `jti` (JWT ID): DPoP proof 毎に `random_bytes(32)` → base64url で生成。AS 側が jti を検証してリプレイを拒否する。

### token storage

- access/refresh token は AES-256-GCM + ランダム IV で暗号化 (D-15)。平文はメモリ上のみ存在し、ログ・セッション・DBの平文カラムには書かない
- 鍵 `TOKEN_ENC_KEY` は `.env` (gitignored、Lolipop ファイル権限で保護)

### session fixation

- CakePHP Authentication Plugin は `setIdentity()` 時に `session_regenerate_id(true)` を内部で呼ぶ [ASSUMED: CakePHP Authentication 2.11 の実装を未確認。AUTH-FLOW.md §5 で明示されているため実施されると期待]

### redirect_uri 完全一致

- Bluesky AS は `redirect_uri` の末尾スラッシュ・クエリ付加も含め厳格に検証する。`https://tamabox.emomie.com/oauth/callback` 固定 (末尾スラッシュなし)

### ログ非出力

- access_token / refresh_token / DPoP JWT は CakePHP Log に出力しない。デバッグログにも含めない。

### HTTPS 強制

- Lolipop は HTTPS ターミネーションを行うが、`.htaccess` での HTTP→HTTPS リダイレクトは Phase 4 で実施予定。Phase 2 時点では `tamabox.emomie.com` の本番デプロイ前なのでローカル開発環境 (HTTP) で動作確認する。

---

## Testing Strategy

[VERIFIED: `CONTEXT.md <specifics>` + PHPUnit 9.6 確認済み]

### テスト不可能なコンポーネント vs 可能なコンポーネント

| コンポーネント | テスト方法 |
|---|---|
| `DpopService` | **単体テスト可能。** 入力 (htm, htu, nonce, access_token) → 出力 JWT の構造を assert。署名検証は openssl_verify で可能。 |
| `ClientJwtService` | **単体テスト可能。** JWT デコードして payload を assert。 |
| `TokenEncryptionService` | **単体テスト可能。** encrypt → decrypt のラウンドトリップテスト。 |
| `KeyManager` | **単体テスト可能。** PEM → JWK 変換の x/y 値を assert。 |
| `BlueskyOAuthClient` | **HTTP mock 必要。** cURL を使うため CakePHP の `Client::class` を mock するか、HTTP レスポンスフィクスチャを用意する。 |
| `DidResolver` | **HTTP mock 必要。** plc.directory レスポンスの JSON フィクスチャを用意。 |
| `AuthController::startBluesky` | **Integration test (mock BlueskyOAuthClient)。** PAR のモック結果で redirect 先を assert。 |
| `OauthController::callback` | **Integration test (mock BlueskyOAuthClient)。** DB の user_identities 行が正しく作成されるか。 |

### 単体テストの置き場所 (D-CONTEXT.md `<specifics>`)

```
tests/TestCase/Service/OAuth/Bluesky/
├── DpopServiceTest.php         # DPoP JWT 構造・ath claim・nonce 付き
├── ClientJwtServiceTest.php    # client_assertion payload
├── TokenEncryptionServiceTest.php  # AES-GCM roundtrip
└── KeyManagerTest.php          # PEM→JWK x/y 変換
```

### DpopService の鍵テスト方針

テスト用の EC 鍵ペアを `tests/Fixture/keys/` に置く (VCS にコミット可能なダミー鍵)。本番鍵の `config/keys/` は `.gitignore` されているため、テストが本番鍵に依存しないよう KeyManager に設定注入口を作る。

### モック戦略: HTTP 呼び出しの擬似化

CakePHP 4.x の `\Cake\Http\Client` を使うか、または PHP cURL をラップするか。altotoo は `curl_*` 直接呼び出しを使っているが、テスタビリティのために CakePHP の `Http\Client` に差し替えるか、`callable $httpClient` を DI する設計にするかはプランナーの判断。[ASSUMED]

### フィクスチャで検証すべき境界値

| ケース | 期待動作 |
|---|---|
| 新規ユーザ (users 行なし) | users + user_identities が 1 トランザクションで INSERT |
| 既存ユーザ (provider + did ヒット) | user_identities のみ UPDATE、users は変更なし |
| UNIQUE 制約違反 (DID 競合) | DatabaseException をキャッチしてエラーレスポンス |
| profile 取得失敗 | 新規ユーザは OAuth 失敗扱い、既存ユーザは旧値を保持してログイン継続 |
| DPoP-Nonce 不一致 → 再送 | 2 回目で成功すること |

---

## Open Questions for Planner

以下はリサーチで判明した決定すべきポイントで、CONTEXT.md に明示されていないもの:

1. **`BlueskyOAuthClient` の HTTP クライアント選択**
   - What we know: altotoo は `curl_*` 直接呼び出し。tamabox は Service 層分離。
   - What's unclear: テスタビリティのために CakePHP `Http\Client` (mock 可能) に差し替えるか、cURL 直接でテストは integration test のみにするか。
   - Recommendation: CakePHP `Http\Client` の利用を検討 (unit test が容易)。ただし altotoo の cURL + header 手動解析パターンとの差分が増えるリスクがある。

2. **DPoP nonce の AS/RS 分離**
   - What we know: AUTH-FLOW.md は `nonce_as` / `nonce_rs` 分離推奨。bsky.social では AS と RS (PDS) が別ドメイン。
   - What's unclear: bsky.social の実際のレスポンスで AS nonce と RS nonce が同一かどうか。
   - Recommendation: Phase 2 では `nonce_as` のみ実装 (getProfile 1 回のみ)。Phase 3 で token refresh を実装する際に `nonce_rs` を追加。

3. **`/oauth/callback` への CSRF middleware 除外方法**
   - What we know: CakePHP の `CsrfProtectionMiddleware` は POST に主に適用されるが、GET callback で state 検証をアプリ側で行うため問題ない可能性が高い。
   - What's unclear: CakePHP 4.x の CSRF が GET /oauth/callback に干渉するかどうか。
   - Recommendation: routes の設定で確認し、必要に応じて `$builder->connect('/oauth/callback', ...)->setOptions(['_unlocked' => true])` または Controller 内 `addUnlockedActions` を使用。

4. **CakePHP Authentication Plugin の custom Identifier 設計**
   - What we know: D-02 で `Session` Authenticator + custom Identifier を使う方針。
   - What's unclear: `user_identities` → `users` をどう辿るか。`UsersTable` に `getAuthenticationFields()` を設定するか、カスタム `IdentifierInterface` を実装するか。
   - Recommendation: `users.id` をセッションに保存し、`UsersTable::findAuth` で `user_id` から User entity を返す CakePHP 標準の `orm-identification` パターンを使う。

5. **`bin/cake oauth:setup_keys` コマンド**
   - What we know: Claude's Discretion の範囲。なければ手動 `openssl ecparam` コマンド。
   - What's unclear: verify-phase でコマンド実行がチェックリストに含まれるかどうか。
   - Recommendation: Plan に `openssl ecparam` の手動手順をドキュメントタスクとして含め、Cake Command は省略する (MVP 優先)。

6. **bsky.social エンドポイント URL の最終確認**
   - What we know: D-05 で静的 URL を`.env` に設定。
   - What's unclear: `BLUESKY_PAR_ENDPOINT` 等の正確な URL が altotoo 実装から確認できていない (`config/app_local.php` にはなかった)。
   - Recommendation: Wave 0 タスクとして `curl https://bsky.social/.well-known/oauth-authorization-server` を実行して `pushed_authorization_request_endpoint` / `token_endpoint` / `authorization_endpoint` を取得・記録する。

---

## Recommended Phase Sub-Decomposition

以下は非拘束的な出発点。プランナーが最終判断する:

### Plan 02-01: Foundation Setup
- `cakephp/authentication ^2.11` の `composer require`
- `src/Application.php` の middleware pipeline 更新 (AuthenticationMiddleware 追加)
- `Application::getAuthenticationService()` 実装
- `config/bluesky.php` 作成 (静的 endpoint + client metadata テンプレート)
- `.env` + `.env.example` に OAuth 関連キー追加 (`OAUTH_KID`, `TOKEN_ENC_KEY`, `BLUESKY_*_ENDPOINT`)
- `config/keys/private.key` / `public.key` 生成 (`openssl ecparam` 手順をドキュメント化)
- 環境確認タスク: bsky.social OAuth AS metadata エンドポイント URL 確認

### Plan 02-02: Service Layer — Crypto & JWT
- `src/Service/OAuth/` ディレクトリ作成
- `OAuthProviderInterface.php` (AUTH-06 要件)
- `KeyManager.php` (PEM読込・JWK変換)
- `DpopService.php` (DPoP proof 生成・altotoo `createDpopProof` リファクタ移植)
- `ClientJwtService.php` (client_assertion 生成・altotoo `createClientAssertion` リファクタ移植)
- `TokenEncryptionService.php` (AES-256-GCM encrypt/decrypt)
- 単体テスト 4 本 (`DpopServiceTest`, `ClientJwtServiceTest`, `TokenEncryptionServiceTest`, `KeyManagerTest`)

### Plan 02-03: Client Metadata Endpoints + DidResolver
- `OauthController.php` 作成 (shell)
- `clientMetadata()` action (`/oauth/client-metadata.json`)
- `jwks()` action (`/oauth/jwks.json`)
- `DidResolver.php` (`resolveDidToPds`・altotoo リファクタ移植)
- `config/routes.php` 更新 (OAuth 名前空間のルート追加)
- Smoke テスト: `curl https://tamabox.emomie.com/oauth/client-metadata.json` 相当

### Plan 02-04: OAuth Flow (Login Start + Callback + Session)
- `src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` (executeParRequest / exchangeCodeForToken / getProfile)
- `AuthController::startBluesky()` (PKCE生成 → PAR → redirect)
- `OauthController::callback()` (state検証 → token交換 → UPSERT → setIdentity → redirect)
- `UserIdentitiesTable` に UPSERT 関連バリデーション追加
- `UsersController::dashboard()` プレースホルダ + View
- `AuthController::logout()` (POST + CSRF)
- Integration テスト (mock BlueskyOAuthClient)

---

## Common Pitfalls

### Pitfall 1: DER → Raw 署名変換を忘れる
**What goes wrong:** `openssl_sign` の出力を JWT signature として直接 base64url する。AS が `invalid_client` か `invalid_dpop_proof` を返す。
**Why it happens:** PHP の `openssl_sign` と JWT ES256 の署名フォーマットが異なることを見落とす。
**How to avoid:** altotoo `derToRawSignature()` をそのままコピーして `DpopService` / `ClientJwtService` に持ち込む。
**Warning signs:** AS が 400 / 401 を返し、body に `alg not supported` や `invalid_signature` が含まれる。

### Pitfall 2: DPoP proof の `jwk` claim 欠如
**What goes wrong:** DPoP JWT header に `jwk` クレームを付け忘れる。AS が proof を検証できず 401。
**Why it happens:** DPoP 仕様上 `jwk` は required だが、auth 系 JWT (client_assertion) には不要なため混同しやすい。
**How to avoid:** `createJwt` の `$header_extra` に必ず `['typ' => 'dpop+jwt', 'jwk' => $jwk]` を渡す。

### Pitfall 3: DPoP-Nonce リトライを 1 回に限定しない
**What goes wrong:** ループで無限に再送するコードを書いてしまう。
**Why it happens:** 仕様が「nonce 不正の場合は再送」と言っているが最大回数を定めていない。
**How to avoid:** altotoo パターンの通り、再送は最大 1 回まで。2 回目で失敗した場合は例外を投げてログアウト。

### Pitfall 4: `ath` claim 欠如
**What goes wrong:** getProfile 等の PDS API 呼び出しの DPoP proof に `ath` を付けない。RS が 401 を返す。
**Why it happens:** PAR / token endpoint の DPoP proof には `ath` が不要だが、access_token を使う API 呼び出しには必要。
**How to avoid:** `DpopService::createProof($htm, $htu, $accessToken)` のシグネチャで access_token を渡した場合に `ath` を自動付与する。

### Pitfall 5: `client_id` と client-metadata.json の URL 不一致
**What goes wrong:** URL に末尾スラッシュが付くかどうかで AS が異なるクライアントと判断する。
**Why it happens:** CakePHP の URL ビルダーが末尾スラッシュを付与する場合がある。
**How to avoid:** `client_id` を設定ファイルに文字列として固定し (`https://tamabox.emomie.com/oauth/client-metadata.json`)、Controller から `Configure::read('Bluesky.client_id')` で参照する。URL 生成には使わない。

### Pitfall 6: cURL レスポンスの header/body 分離
**What goes wrong:** `curl_exec` + `CURLOPT_HEADER=true` で返るレスポンスをパースするとき、`CURLINFO_HEADER_SIZE` 使わずに `\r\n\r\n` 文字列分割すると HTTP/2 時に壊れる。
**Why it happens:** altotoo が cURL header + body を一緒に取得して `CURLINFO_HEADER_SIZE` で分割している点を見落とす。
**How to avoid:** altotoo の `callApi` の curl パターンをそのまま踏襲する。

### Pitfall 7: profile 取得失敗時の挙動分岐
**What goes wrong:** altotoo は profile 取得失敗でもログイン継続するが、tamabox は新規ユーザに限り失敗をOAuth全体の失敗扱いにする (CONTEXT.md `<specifics>`)。
**Why it happens:** 既存ユーザと新規ユーザで異なる振る舞いが必要なのに同じパスで処理する。
**How to avoid:** `$identity === null` (新規) の場合の profile 失敗は例外スロー。`$identity !== null` (既存再ログイン) の場合は `last_synced_at` を更新せずにログイン継続。

### Pitfall 8: `config/keys/private.key` のパーミッション
**What goes wrong:** ロリポップ Git deploy 後に秘密鍵が他のプロセスから読めてしまう。
**Why it happens:** Git deploy でファイルパーミッションがリセットされる可能性。
**How to avoid:** `config/keys/` を `.gitignore` に追加 (Phase 1 で設定済み確認) + deploy 後に `chmod 600 config/keys/private.key` を手動実行する手順をドキュメント化。

---

## Code Examples

### AES-256-GCM 暗号化 (PHP 8.3.6 動作確認済み)
[VERIFIED: ローカル実行]

```php
// TokenEncryptionService::encrypt()
private function base64urlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

public function encrypt(string $plaintext): string {
    $key = hex2bin(env('TOKEN_ENC_KEY'));
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16
    );
    return $this->base64urlEncode($iv . $ciphertext . $tag);
}
```

### PEM → JWK 変換 (KeyManager)
[VERIFIED: PHP 8.3.6 で ec.x_len=32, ec.y_len=32 確認済み]

```php
public function getPublicJwk(): array {
    $pem = file_get_contents(CONFIG . 'keys/public.key');
    $key = openssl_pkey_get_public($pem);
    $details = openssl_pkey_get_details($key);
    return [
        'kty' => 'EC',
        'crv' => 'P-256',
        'kid' => env('OAUTH_KID', 'ssr-box-key-1'),
        'use' => 'sig',
        'alg' => 'ES256',
        'x'   => $this->base64urlEncode($details['ec']['x']),
        'y'   => $this->base64urlEncode($details['ec']['y']),
    ];
}
```

### PKCE 生成
[VERIFIED: altotoo `BlueskyOauthComponent.php` L22-31]

```php
public function generatePkce(): array {
    $verifier  = $this->base64urlEncode(random_bytes(64));
    $challenge = $this->base64urlEncode(hash('sha256', $verifier, true));
    return ['verifier' => $verifier, 'challenge' => $challenge];
}
```

### DPoP-Nonce リトライパターン (altotoo 踏襲)
[VERIFIED: altotoo `BlueskyOauthComponent.php` L598-666]

```php
// 1回目 (nonceなし)
$result = $sendRequest(null);
// DPoP-Nonce エラー判定
if (in_array($result['code'], [400, 401])) {
    $body = json_decode($result['body'], true);
    if (($body['error'] ?? '') === 'use_dpop_nonce') {
        if (preg_match('/^DPoP-Nonce:\s*(.+)$/im', $result['header'], $m)) {
            $result = $sendRequest(trim($m[1])); // 2回目 (nonce付き)
        }
    }
}
if ($result['code'] !== 201) {  // PAR は 201, token は 200
    throw new \RuntimeException("Request failed: " . $result['body']);
}
```

---

## Environment Availability

[VERIFIED: ローカル実行で確認済み]

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP openssl ext | JWT / DPoP / AES-GCM | ✓ | bundled with PHP 8.3.6 | — |
| PHP sodium ext | 代替暗号実装 (未使用) | ✓ | bundled with PHP 8.3.6 | — |
| PHP curl ext | HTTP (PAR/token/profile) | ✓ | bundled with PHP 8.3.6 | — |
| EC keygen (prime256v1) | DPoP 鍵生成 | ✓ | PHP 8.3.6 動作確認済み | — |
| AES-256-GCM | トークン暗号化 | ✓ | PHP 8.3.6 動作確認済み | — |
| cakephp/authentication | Session Auth | ✓ (要install) | ^2.11 (resolves 2.11.0) | — |
| config/keys/ dir | 鍵ファイル配置 | ✓ (.gitkeep) | Phase 1 で作成済み | — |
| config/.env | 秘匿値注入 | ✓ | Phase 1 で作成済み | — |
| MySQL UNIQUE 制約 | 1 ユーザー=1SNS | ✓ | Phase 1 で migration 済み | — |
| plc.directory API | DID → PDS 解決 | 外部依存 | — | テスト時は mock |

**Missing dependencies with no fallback:** なし

**Missing dependencies with fallback:**
- `config/keys/private.key` / `public.key`: 未生成 (`.gitkeep` のみ) → Wave 0 で `openssl ecparam` 実行が必要

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 9.6 (require-dev) |
| Config file | `phpunit.xml.dist` |
| Quick run command | `composer test -- --filter DpopServiceTest` |
| Full suite command | `composer test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| AUTH-01 | PAR → redirect で OAuth フロー開始 | integration | `composer test -- --filter AuthControllerTest` | ❌ Wave 0 |
| AUTH-02 | callback → UPSERT → setIdentity | integration | `composer test -- --filter OauthControllerTest` | ❌ Wave 0 |
| AUTH-04 | UNIQUE 制約違反で DatabaseException | unit | `composer test -- --filter UserIdentitiesTableTest` | ❌ Wave 0 |
| AUTH-05 | getProfile でハンドル・アバター取得 | unit (mock) | `composer test -- --filter BlueskyOAuthClientTest` | ❌ Wave 0 |
| AUTH-06 | interface を実装していること | unit (phpstan) | `composer phpstan` | ❌ Wave 0 |
| AUTH-07 | AES-GCM roundtrip | unit | `composer test -- --filter TokenEncryptionServiceTest` | ❌ Wave 0 |
| AUTH-08 | /oauth/client-metadata.json が正しい JSON を返す | unit | `composer test -- --filter OauthControllerTest` | ❌ Wave 0 |
| AUTH-09 | POST /logout でセッション破棄 | integration | `composer test -- --filter AuthControllerTest` | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** `composer phpcs && composer phpstan`
- **Per wave merge:** `composer test`
- **Phase gate:** Full suite green before `/gsd-verify-phase 2`

### Wave 0 Gaps

- [ ] `tests/TestCase/Service/OAuth/Bluesky/DpopServiceTest.php`
- [ ] `tests/TestCase/Service/OAuth/Bluesky/ClientJwtServiceTest.php`
- [ ] `tests/TestCase/Service/OAuth/Bluesky/TokenEncryptionServiceTest.php`
- [ ] `tests/TestCase/Service/OAuth/KeyManagerTest.php`
- [ ] `tests/Fixture/keys/private.key` + `public.key` (テスト用ダミー鍵)
- [ ] `config/keys/private.key` + `config/keys/public.key` 生成手順

---

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes | AT Protocol OAuth 2.0 + PKCE + DPoP |
| V3 Session Management | yes | CakePHP Authentication Plugin (Session Authenticator) |
| V4 Access Control | yes | `/dashboard` は認証必須ルート |
| V5 Input Validation | yes | DID 形式検証、state パラメータ検証 |
| V6 Cryptography | yes | AES-256-GCM (トークン暗号化)、ES256 (DPoP・client_assertion) |

### Known Threat Patterns for AT Protocol OAuth Stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| state 未検証による CSRF | Spoofing | state をセッションに保存し callback で完全一致検証 |
| DPoP jti リプレイ攻撃 | Tampering | AS 側が jti を管理・拒否 (サーバー側で実施) |
| access_token 盗難後の不正使用 | Elevation | DPoP binding — 秘密鍵なし token は使えない |
| token 漏洩 (DB から) | Information Disclosure | AES-256-GCM 暗号化、鍵は `.env` のみ |
| セッション固定攻撃 | Tampering | CakePHP `setIdentity()` が `session_regenerate_id` を呼ぶ [ASSUMED] |
| redirect_uri の差し替え | Spoofing | 完全一致チェック (末尾スラッシュも含む)、固定文字列で管理 |
| ログへの token 流出 | Information Disclosure | `Log::write()` で access_token / refresh_token を出力しない |

---

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | bsky.social の PAR endpoint は `https://bsky.social/oauth/par`、token endpoint は `https://bsky.social/oauth/token` | AT Protocol OAuth Flow | 静的設定が間違えば PAR / token exchange が 404 になる。Wave 0 で AS metadata を確認すれば回避可能。 |
| A2 | bsky.social の AS nonce と RS nonce (PDS getProfile) が同一チャンネルで返るため Phase 2 では単一 nonce 管理で十分 | Token & Session Storage | nonce 分離が必要な場合は `nonce_as` / `nonce_rs` を追加する改修が必要。DPoP-Nonce リトライで検出できる。 |
| A3 | CakePHP Authentication Plugin 2.11 の `setIdentity()` が内部で `session_regenerate_id(true)` を呼ぶ | Security Considerations | セッション固定攻撃への脆弱性。`SessionInterface::renew()` を明示的に呼ぶ追加実装が必要になる可能性。 |
| A4 | CakePHP 4.x の `CsrfProtectionMiddleware` は GET `/oauth/callback` に干渉しない | Testing Strategy | 干渉する場合は `addUnlockedActions` か route 設定の追加が必要。 |

---

## Sources

### Primary (HIGH confidence)
- `.planning/references/altotoo/BlueskyOauthComponent.php` — PHP ビルトイン実装の完全参照 (本番稼働実績あり)
- `.planning/references/altotoo/LoginController.php` — OAuth フロー Controller 参照
- `/home/claude/projects/ssr-box-discovery/AUTH-FLOW.md` — tamabox 向け OAuth シーケンス設計書
- ローカル PHP 実行 (PHP 8.3.6) — openssl EC keygen / AES-256-GCM / ec.x/y 取得 を直接確認

### Secondary (MEDIUM confidence)
- `.planning/phases/02-bluesky-oauth-identity/02-CONTEXT.md` — 確定済み実装決定
- Packagist API — `cakephp/authentication` 2.11.0 が CakePHP 4.x に対応していることを確認

### Tertiary (LOW confidence)
- AUTH-FLOW.md `セキュリティ要点` — `session_regenerate_id` 関連 (A3: CakePHP plugin 内部実装は未確認)
- bsky.social エンドポイント URL — 実際の AS metadata を未確認 (A1)

---

## Metadata

**Confidence breakdown:**
- Standard Stack: HIGH — PHP ビルトイン全機能を動作確認済み、altotoo 参照実装あり
- Architecture: HIGH — CONTEXT.md で Service 層・Controller 配置・インタフェースが確定済み
- Pitfalls: HIGH — altotoo が踏み潰した罠を直接参照できる
- bsky.social Endpoints: LOW — Wave 0 で確認要

**Research date:** 2026-04-23
**Valid until:** 2026-05-23 (bsky.social API は "Developer Preview" のため変更可能性あり)
