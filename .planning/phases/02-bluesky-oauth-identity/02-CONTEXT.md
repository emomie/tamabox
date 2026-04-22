# Phase 2: Bluesky OAuth & Identity — Context

**Gathered:** 2026-04-23
**Status:** Ready for planning

<domain>
## Phase Boundary

受け手・送り手ともに Bluesky OAuth (AT Protocol, ES256 confidential client, PAR + DPoP + PKCE) で本人確認でき、1 ユーザー = 1 SNS アカウント制約が `users` × `user_identities` の DB 制約で守られ、アクセス/リフレッシュトークンは AES-GCM で `*_enc` 列に暗号化格納される状態まで。

`/oauth/client-metadata.json` と `/oauth/jwks.json` が tamabox.emomie.com 上で Controller action として公開され、Bluesky AS から参照可能な形式で返る。OAuth 成功後のランディングは **最小のプレースホルダ `/dashboard`** (「ログイン成功、受信箱作成は次フェーズで…」) までに留める。

**Requirements mapped to this phase:** AUTH-01, AUTH-02, AUTH-04, AUTH-05, AUTH-06, AUTH-07, AUTH-08, AUTH-09 (8 件)。AUTH-03 (送信時の OAuth 必須チェック) は Phase 3 (MSG-01/05 の送信フォーム側) の責務。

**Not in this phase:**
- Inbox 作成・slug 選択 UI (INBOX-01) → Phase 3
- 受信箱ダッシュボード (INBOX-03) → Phase 3
- 送信フォーム上の OAuth 必須エンフォース (AUTH-03) → Phase 3
- Bluesky 投稿機能 (DEFERRED — scope は確保するが実装は v1.1+)
- X (Twitter) OAuth 実装 (v2)
- third-party PDS (did:web / 自前 PDS) 対応 (必要時点で別 Phase)
</domain>

<decisions>
## Implementation Decisions

### Library / Tooling Strategy

- **D-01**: **JWT / OAuth ライブラリは使わず自前実装**。altotoo の `BlueskyOauthComponent.php` (`.planning/references/altotoo/BlueskyOauthComponent.php`) を参照実装として、ES256 sign (`openssl_sign` + DER→Raw 変換) / base64url / PKCE / DPoP JWT / client_assertion (private_key_jwt) をすべて PHP ビルトインで実装する。`composer require` で JWT ライブラリは増やさない。
  - 根拠: altotoo が同じ Lolipop + AT Protocol で本番稼働しており、`web-token/jwt-framework` の symfony 系 deps を持ち込むより低リスク。約 100 行で収まる。
  - DER→Raw 変換・DPoP-Nonce リトライ・jwk-in-header など altotoo が既に踏み抜いた罠はそのまま踏襲する (下記 D-09, D-10)。

- **D-02**: **CakePHP Authentication Plugin を導入**。`composer require cakephp/authentication`、`src/Application.php` の middleware pipeline に `AuthenticationMiddleware` を追加、`getAuthenticationService()` で `Session` Authenticator を登録。altotoo の `$_SESSION` 直触りパターンは採用しない。
  - セッションエンジンは CakePHP デフォルト (PHP file session) を維持。Lolipop 共有鯖前提、DB セッションは MVP 範囲外 (PROJECT.md Out of Scope)。

### Layer Architecture

- **D-03**: **Service 層分離** で実装。altotoo は Controller Component 一枚に全部乗っているが、tamabox は以下の分割を採用する:
  - `src/Service/OAuth/OAuthProviderInterface.php` — マルチプロバイダ抽象 (AUTH-06 要件)。`executeParRequest` / `exchangeCodeForToken` / `refreshToken` / `resolveProfile` / `getProviderKey()` を公開。
  - `src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` — interface 実装本体。PAR / token exchange / nonce retry。
  - `src/Service/OAuth/Bluesky/DpopService.php` — DPoP JWT 生成 (htm / htu / jti / iat / exp / nonce / ath / jwk header)。
  - `src/Service/OAuth/Bluesky/ClientJwtService.php` — `private_key_jwt` 用 client_assertion 生成。
  - `src/Service/OAuth/Bluesky/DidResolver.php` — DID → PDS (plc.directory 経由)。handle → DID resolver は実装しない (D-06 参照)。
  - `src/Service/OAuth/KeyManager.php` — `config/keys/private.key` / `public.key` 読込、PEM → JWK 変換 (`openssl_pkey_get_details` の `ec.x`/`ec.y` を base64url)。
  - `src/Service/OAuth/TokenEncryptionService.php` — AES-GCM による access/refresh token の暗号化・復号。
  - Controller は薄く保ち、上記 Service に委譲する。

- **D-04**: **Controller 配置**:
  - `src/Controller/AuthController.php` — `/login/bluesky` (OAuth フロー開始 = PAR 実行 → redirect) / `/logout` (セッション破棄)
  - `src/Controller/OauthController.php` — `/oauth/callback` (callback 処理 = state 検証 → token 交換 → `user_identities` UPSERT → セッション確立 → `/dashboard` リダイレクト) / `/oauth/client-metadata.json` (静的相当の JSON 動的生成) / `/oauth/jwks.json` (`config/keys/public.key` から JWK 動的生成)
  - altotoo は `LoginController::oauthLogin()` + `callback()` を同居させていたが、tamabox は `/oauth/*` 名前空間を `OauthController` に寄せて URL 設計に一貫性を持たせる。

### Endpoint Resolution

- **D-05**: **静的 endpoint 方式を採用**。`config/bluesky.php` で `par_endpoint` / `token_endpoint` / `auth_endpoint` / `issuer` を bsky.social 決め打ちで持つ。AUTH-FLOW.md が推奨する「PDS → AS metadata 動的解決」は V1 では行わない。
  - 根拠: MVP 利用者は bsky.social ユーザ前提 (discovery DESIGN.md Q3)。third-party PDS / 自前 PDS ユーザ対応は PROJECT.md の Out of Scope 境界に近く、必要になった時点で別 Phase を切る。
  - `.env` には `BLUESKY_AUTH_ENDPOINT` / `BLUESKY_TOKEN_ENDPOINT` / `BLUESKY_PAR_ENDPOINT` / `BLUESKY_ISSUER` を追加し、`config/bluesky.php` から `env()` 経由で読む (本番/開発差し替え可能性を残す)。

### OAuth Scope

- **D-06**: **Scope = `atproto transition:generic`** (altotoo と同じ)。認証のみなら `atproto` で十分だが、ユーザ決定 (2026-04-22) により Bluesky 投稿機能を将来追加する可能性を残すため legacy API 権限も確保する。`client-metadata.json` 側にもこの scope を記載し、AS 側に同意画面でそう表示される。
  - 投稿機能自体は Phase 2 では実装しない (DEFERRED)。
  - 将来 scope を縮小したい場合 (`atproto` のみに戻す) は、全ユーザに再認可を要求することになる点を認識した上で baking-in する。

### Handle Input UX

- **D-07**: **tamabox 側では handle 入力を受け付けない**。altotoo 方式を踏襲し、`/login/bluesky` はユーザー入力フォーム無しで即 PAR 実行 → Bluesky AS 画面にリダイレクト → AS 画面でユーザが handle を入力 → callback。
  - 結果として実装不要になるもの:
    - `handle → DID` resolver (HTTP `/.well-known/atproto-did` + DNS TXT fallback)
    - Lolipop 上での `dns_get_record()` / DoH fallback の検証
    - PAR request の `login_hint` パラメータ
  - AUTH-FLOW.md §3 (ログイン開始) のステップ 1〜4 は丸ごとスキップ。AS が返した token response の `sub` (DID) から `plc.directory` 経由で PDS URL を引く (altotoo `resolveDidToPds` と同等) のみ残す。
  - UX: 「Bluesky でログイン」ボタン 1 つ → Bluesky の承認画面 → tamabox に戻る。

### DB Persistence (altotoo からの分岐点)

- **D-08**: **token / identity は DB に永続化する** (altotoo は `$_SESSION` 直書きだが採用しない)。AUTH-04/05/07 の要件:
  - `user_identities` (Phase 1 で migrate 済) に以下を UPSERT:
    - `provider = 'bluesky'`, `provider_account_id = <did>` (sub)
    - `handle_cached`, `avatar_url_cached`, `profile_url_cached` (getProfile で最新取得)
    - `access_token_enc`, `refresh_token_enc` — AES-256-GCM で暗号化 (下記 D-15)
    - `token_expires_at` = `now + expires_in`
    - `last_synced_at` = `now`
    - `is_primary = true` (MVP は 1 ユーザー 1 identity のため)
  - 新規ユーザ時: `users` 行 + `user_identities` 行を同一トランザクション内で INSERT。`users.display_name` 初期値 = handle。
  - 既存ユーザ時 (provider + provider_account_id ヒット): `user_identities` を UPDATE、`users` は触らない。
  - セッションには PII 相当の値を入れない。`Authentication` Plugin のデフォルトに従い `user_id` (UUID) と DID のみ保持。

- **D-09**: **1 ユーザー = 1 SNS 制約の DB エンフォース**。Phase 1 で `uk_user_identities_provider_account` (UNIQUE(provider, provider_account_id)) + `uk_user_identities_user` (UNIQUE(user_id) for `is_primary=true`) を migrate 済。tamabox 側で race condition を考慮し、`user_identities` UPSERT は MySQL の `ON DUPLICATE KEY UPDATE` か CakePHP `saveMany` + try/catch で実装する。

### OAuth Protocol Details (altotoo 踏襲)

- **D-10**: **DPoP-Nonce リトライロジック** を altotoo `BlueskyOauthComponent::executeParRequest()` / `exchangeCodeForToken()` からコピペ踏襲。初回 request は nonce なしで送る → `response.code ∈ {400, 401}` かつ `response.body.error == 'use_dpop_nonce'` を検知 → `DPoP-Nonce` レスポンスヘッダから nonce を抽出 → 同一 request を nonce 含みで再送。最大 1 回まで再試行。

- **D-11**: **DER → Raw 署名変換** を altotoo `derToRawSignature()` から踏襲。PHP `openssl_sign(..., OPENSSL_ALGO_SHA256)` 出力は DER、JWT ES256 は R+S Raw (64 バイト固定) を要求するため変換必須。先頭 0x00 パディング除去・32 バイト STR_PAD_LEFT 右詰めのロジックをそのまま移植する。

- **D-12**: **DPoP proof JWT の header に `jwk` claim を埋める**。`openssl_pkey_get_details()` の `ec.x` / `ec.y` を base64url して `{"kty":"EC","crv":"P-256","x":...,"y":...,"use":"sig"}` 形式にする。AS / RS 側はこの public key で DPoP JWT 署名を検証する。

- **D-13**: **DPoP proof の `ath` claim**。access_token を伴う request (token endpoint 以外の PDS 呼び出し) の時は `ath = base64url(sha256(access_token))` を DPoP proof payload に必ず含める。抜けると RS 側で 401。

### Key Management

- **D-14**: **ES256 鍵ペアは `config/keys/private.key` / `public.key` に配置**。Phase 1 完了時点で `config/keys/` ディレクトリは存在し webroot 外。鍵生成コマンドは:
  ```bash
  openssl ecparam -genkey -name prime256v1 -noout -out config/keys/private.key
  openssl ec -in config/keys/private.key -pubout -out config/keys/public.key
  chmod 600 config/keys/private.key && chmod 644 config/keys/public.key
  ```
  `kid` は `.env` の `OAUTH_KID` から読む (デフォルト `ssr-box-key-1`)。鍵ローテーションは MVP 範囲外。

- **D-15**: **AES-256-GCM 鍵 (`TOKEN_ENC_KEY`)** を `.env` に 32 バイト hex で保管 (`openssl rand -hex 32` で生成)。MVP はローテーションなし、単一鍵。将来ローテーション対応時は `user_identities.token_enc_key_version TINYINT DEFAULT 1` カラム追加を別 Phase で切る (Phase 1 D-05 の `server_secret` と同じ路線)。
  - 暗号化フォーマット: `base64url(iv(12) || ciphertext || tag(16))` の一列連結。IV は request 毎にランダム。

### Public Metadata Endpoints

- **D-16**: **`/oauth/client-metadata.json` は Controller 動的生成**。静的ファイルではなく `OauthController::clientMetadata()` で組み立てて `application/json` で返す。`client_id` は配信 URL と**完全一致**させる (AT Protocol 仕様厳格): `https://tamabox.emomie.com/oauth/client-metadata.json`。
  - フィールドは AUTH-FLOW.md §1 に沿って、`application_type=web` / `client_name=tamabox` (tbd) / `redirect_uris=["https://tamabox.emomie.com/oauth/callback"]` / `grant_types=["authorization_code","refresh_token"]` / `response_types=["code"]` / `scope="atproto transition:generic"` / `token_endpoint_auth_method=private_key_jwt` / `token_endpoint_auth_signing_alg=ES256` / `dpop_bound_access_tokens=true` / `jwks_uri=https://tamabox.emomie.com/oauth/jwks.json`。

- **D-17**: **`/oauth/jwks.json` は Controller 動的生成**。`config/keys/public.key` を PEM → JWK 変換 (`openssl_pkey_get_details` の `ec.x`/`ec.y` を base64url) し、`{"keys":[{"kty":"EC","crv":"P-256","kid":<env OAUTH_KID>,"use":"sig","alg":"ES256","x":...,"y":...}]}` を返す。CDN キャッシュ可 (静的に近い挙動)。

### Logout

- **D-18**: **ログアウトはセッション破棄のみ** (AUTH-09)。Bluesky 側のトークン revoke は行わない (MVP 簡略化、token は `token_expires_at` で自然失効)。`/logout` は POST 限定 + CSRF トークン必須 (CakePHP デフォルト) で実装し、`$this->Authentication->logout()` → `/` 相当にリダイレクト。

### Claude's Discretion

以下は Claude が実装時に判断する範囲:
- エラー UX の具体文言 (「ログインに失敗しました。再度お試しください」等)、再試行ボタンの設置位置
- `config/bluesky.php` の具体ファイル構造 / キー名
- Controller 内の Flash message 文言
- Test fixture の構成 (`UserIdentitiesFixture` の encrypted token サンプル値等)
- OAuth 関連 routes の具体名 (`/login/bluesky` vs `/oauth/start` 等) — AUTH-FLOW.md の `/login/bluesky` 方針を尊重しつつ実装時微調整可
- `openssl_pkey_new()` で EC keypair を生成する CLI ツール (`bin/cake oauth:setup_keys` 相当) を用意するかどうか — 初期セットアップ体験次第で判断
- DB トランザクション境界 (新規ユーザ INSERT の `users` + `user_identities` 同時性)
- Nonce セッション保存の key 名 (`bsky_as_nonce` 等)
- `/dashboard` プレースホルダ View の具体レイアウト (ログイン確認 + 「受信箱の作成は次のフェーズで…」相当の文言)
</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Discovery (external repo, VPS 内 local clone)
- `/home/claude/projects/ssr-box-discovery/AUTH-FLOW.md` — OAuth シーケンス全量 (PAR / DPoP / PKCE / token exchange) と既知の落とし穴。Phase 2 実装の **single source of truth**。ただし scope / Service 層構造 / handle 入力 UX などで本 CONTEXT.md の決定を優先する (下記参照)。
- `/home/claude/projects/ssr-box-discovery/DB-SCHEMA.md` §2 `user_identities` — `provider_account_id` (DID) / `*_enc` 列 / `is_primary` / `handle_cached` 他の DDL 確定仕様。
- `/home/claude/projects/ssr-box-discovery/DESIGN.md` Q2 (SNS OAuth 必須) / Q3 (Bluesky 先行、マルチプロバイダ抽象化は最初から)
- `/home/claude/projects/ssr-box-discovery/ASSUMPTIONS.md` V1 (確率名前バレで悪意自己抑止) — この仮説のため OAuth identity は絶対匿名化しない / 逃げ得を許さない設計 (退会後も `user_identities` snapshot を保持する要件は Phase 4 側)。

### Altotoo 参照実装 (tamabox リポジトリ内)
- `.planning/references/altotoo/README.md` — tamabox と altotoo の対応表・ギャップ表
- `.planning/references/altotoo/BlueskyOauthComponent.php` — JWT/DPoP/PAR/token exchange の参照実装 (Service 層分離してコピペ移植する)
- `.planning/references/altotoo/LoginController.php` — OAuth 開始 + callback フローの参照 (tamabox は DB 永続化 + CakePHP Authentication Plugin 化する点で分岐)
- `.planning/references/altotoo/app_local.php` — Lolipop MySQL 接続・env 利用の参考情報のみ

### Project-Level
- `.planning/PROJECT.md` — Core Value (V1 仮説) と Out of Scope、Key Decisions のうち「SNS OAuth 送信必須」「Bluesky OAuth 先行」が本 Phase の直接的根拠
- `.planning/REQUIREMENTS.md` — AUTH-01..09 (AUTH-03 除く) の正規化
- `.planning/ROADMAP.md` — Phase 2 success criteria 7 項目が verify-phase のチェックリスト
- `.planning/STATE.md` — Phase 1 完了確認 (schema + `.env` loader + httpoxy + Table classes すべて shipped)
- `.planning/phases/01-foundation-schema/01-CONTEXT.md` — Phase 1 で確定した infra 決定 (特に `config/keys/` 配置・`.env` 運用・`TableLocator::allowFallbackClass(false)`・Timestamp Behavior 規約)

### Codebase State
- `.planning/codebase/STACK.md` — CakePHP 4.5 / PHP ^8.0 / MySQL 8.0 / `bin/cake` available
- `.planning/codebase/ARCHITECTURE.md` — Middleware pipeline (ErrorHandler → Asset → Routing → BodyParser → CSRF)、Authentication Middleware を差し込む箇所の把握
- `.planning/codebase/CONVENTIONS.md` — PSR-4 `App\` → `src/`、namespace 規約
- `.planning/codebase/INTEGRATIONS.md` — Phase 1 時点の依存関係一覧 (新規追加: `cakephp/authentication` のみ)
</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets (Phase 1 完了時点)
- **`config/keys/` ディレクトリ** — webroot 外、`.gitignore` 対象。Phase 2 は ES256 鍵ペアをここに追加するのみ。
- **`config/bootstrap.php` + `.env` loader** — 有効化済、新規 `OAUTH_KID` / `TOKEN_ENC_KEY` / `BLUESKY_*_ENDPOINT` を追加して `env()` 経由で読める。
- **`users` + `user_identities` テーブル** — migrate 済。`provider` / `provider_account_id` / `handle_cached` / `*_enc` 列を含む。UNIQUE 制約 (provider + provider_account_id) も Phase 1 で設置済。
- **`src/Model/Table/UsersTable.php` + `UserIdentitiesTable.php`** — bake 済み、UUID behavior と Timestamp behavior 設定済。Phase 2 で UPSERT ロジックと association validation を追加する。
- **CSRF middleware** — Phase 1 で維持済 (`src/Application.php`)、OAuth callback は GET なので CSRF 免除路線でルーティングが必要 (`/oauth/callback` は `addUnlockedActions` 的扱い)。
- **`bin/cake` CLI + `bake` プラグイン** — Controller/Service scaffold に利用可能。

### Established Patterns
- **Configure reads**: `Configure::read('Security.serverSecret')` / `env()` 併用パターン (Phase 1 01-01-SUMMARY 参照)。Phase 2 は `Configure::read('Bluesky.*')` を同じ流儀で追加。
- **Phinx migration**: Phase 2 では新規 migration は不要 (Phase 1 で全 6 テーブル migrate 済)。
- **`TableLocator::allowFallbackClass(false)` 維持**: 新規 Controller / Service には Table クラスを明示 inject する (`$this->fetchTable('Users')` より DI 推奨)。

### Integration Points
- **`composer.json`**: `cakephp/authentication: ^2.11` を `require` に追加、`composer update` / `composer.lock` 更新。
- **`src/Application.php`**:
  - `bootstrap()` で `$this->addPlugin('Authentication')`
  - `middleware()` pipeline に `AuthenticationMiddleware` を `CsrfProtectionMiddleware` の後に差し込む
  - `getAuthenticationService()` を実装 (`Session` authenticator + `Password` identifier は不要、`user_identities` 経由のカスタム identifier を用意する)
- **`config/routes.php`**:
  - `/login/bluesky` → `Auth::startBluesky` (GET/POST)
  - `/oauth/callback` → `Oauth::callback` (GET)
  - `/oauth/client-metadata.json` → `Oauth::clientMetadata` (GET)
  - `/oauth/jwks.json` → `Oauth::jwks` (GET)
  - `/logout` → `Auth::logout` (POST)
  - `/dashboard` → `Users::dashboard` (GET, authenticated only — プレースホルダ)
- **`config/bluesky.php` (新規)**: Bluesky 静的 endpoint 一式と client metadata テンプレートをここに集約。
- **`config/.env.example`**: `OAUTH_KID` / `TOKEN_ENC_KEY` / `BLUESKY_AUTH_ENDPOINT` / `BLUESKY_TOKEN_ENDPOINT` / `BLUESKY_PAR_ENDPOINT` / `BLUESKY_ISSUER` を追加。
</code_context>

<specifics>
## Specific Ideas

- **`/dashboard` は Phase 2 の終着駅**。View は最小 (「ようこそ、〇〇 さん。受信箱はまだ作成されていません」相当の静的文字列 + handle 表示のみ)。INBOX-01 の slug 選択 UI は Phase 3 で追加するので、この時点ではリンクすら貼らない (Phase 3 plan 時に `/inbox/setup` 等の導線を増設する)。
- **altotoo の `potibm/Bluesky` ライブラリ** (legacy app-pass 認証側で使用) は tamabox では **導入しない**。MVP は OAuth 経路のみで、app password 認証は要件に存在しない。
- **`setup_keys.phpartisan` 相当の CLI ツール**: altotoo は手動 `openssl ecparam` で鍵生成している様子だが、tamabox では `bin/cake oauth:setup_keys` コマンドを提供すると初期セットアップ + verify-phase チェックリストで便利 (Claude's Discretion, 最終判断は実装時)。
- **Profile 取得失敗時のフォールバック**: altotoo は profile 取得失敗でも Flash error を出しつつ session 確立を続ける。tamabox は `handle_cached` が必須 (空文字不許可) のため、**profile 取得失敗は OAuth 全体を失敗扱いにする**。既存 `user_identities` 行がある再ログイン時は旧値を保ってもよい (last_synced_at だけ更新できない前提になるが、ログイン自体は通す)。
- **テスト戦略**: PAR / token exchange は live call が難しいため、`tests/TestCase/Service/OAuth/Bluesky/` に DpopService / ClientJwtService / TokenEncryptionService の単体テストを置く (これらは外部通信なし)。OAuthClient 統合テストは HTTP クライアントを mock する方針 (Plan 時に具体化)。
</specifics>

<deferred>
## Deferred Ideas

- **Bluesky 投稿機能 (`transition:generic` scope 活用)** — Phase 2 で scope は `atproto transition:generic` を確保するが、実際の投稿 API 呼び出し (`com.atproto.repo.createRecord`) 実装は v1.1+ で別 Phase。ユーザ (2026-04-22) 決定: 「あとから生やしたい」。
- **X (Twitter) OAuth 追加** — v2 milestone。Phase 2 で `OAuthProviderInterface` を切っておくことで拡張点は確保済み (AUTH-06)。
- **third-party PDS / did:web 対応** — MVP は bsky.social (did:plc) のみ。第三者 PDS ユーザからのリクエストが観測されたら対応 Phase を切る。
- **`token_enc_key_version` カラム追加による鍵ローテーション** — MVP は `TOKEN_ENC_KEY` 単一固定。ローテーション必要時に別 Phase (Phase 1 の `ssr_secret_version` と同じ方針)。
- **Bluesky 側トークン revoke on logout** — MVP はローカルセッション破棄のみ。revoke endpoint の仕様確認と実装は運用上必要になった時点で追加。
- **Refresh token の自動更新・並行リクエスト保護 (SELECT ... FOR UPDATE)** — Phase 3 で送信フォーム / ダッシュボード表示時に token 期限チェック → refresh する実装を入れる時に初めて必要になる。Phase 2 は初回 token 取得と DB 格納まで。
- **AS (Authorization Server) metadata の動的解決** — AUTH-FLOW.md の推奨だが MVP は `.env` ハードコードで簡略化 (D-05)。
- **Handle → DID 自前解決 (`.well-known/atproto-did` / DNS TXT fallback / DoH)** — altotoo 方式踏襲 (D-07) により Phase 2 では不要。tamabox 側で handle 入力 UI を設けるタイミングで復活検討。
- **`bin/cake oauth:setup_keys` CLI ツール** — あれば便利だが必須ではない。実装時判断。
- **Lolipop 上の OpenSSL ext EC 鍵生成動作確認** — Phase 1 の composer install で openssl ext 自体は通っているはず。不安なら research-phase で `php -r "openssl_pkey_new(['curve_name'=>'prime256v1',...])"` の動作確認タスクを含める。
</deferred>

---

*Phase: 02-bluesky-oauth-identity*
*Context gathered: 2026-04-23*
