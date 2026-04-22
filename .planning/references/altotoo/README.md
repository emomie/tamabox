# altotoo 参照実装 (AT Protocol OAuth)

tamabox Phase 2 (Bluesky OAuth & Identity) の参照実装。`altotoo.emomie.com` で本番稼働している Bluesky OAuth クライアントのソース。

**取得経緯:** ユーザー (satie___) が 2026-04-22 にスマホから手動で altotoo サーバーから DL → Discord 経由でこのリポジトリに投入。

## ファイル

| File | 役割 | tamabox での利用方針 |
|---|---|---|
| `BlueskyOauthComponent.php` | CakePHP Controller Component。PKCE / JWT (ES256) / DPoP / PAR / Token Exchange / DID→PDS resolve を純自前実装 (~660 行、JWT ライブラリ未使用) | **参照実装**。tamabox は `src/Service/Bluesky/` に Service 層分離でリファクタ移植する (D-03)。DER→Raw 変換・DPoP-Nonce retry ロジックはそのまま踏襲 (D-09, D-10) |
| `LoginController.php` | `oauthLogin()` → PAR → リダイレクト / `callback()` → token 交換 → profile 取得 → セッション確立 → `/mypage` | **フロー参照のみ**。altotoo は `$_SESSION` 直書き / DB 永続化なし / `potibm/Bluesky` (legacy app-pass) 混在。tamabox は AUTH-04/05/07 の要件で DB 永続化 + AES-GCM 必須、CakePHP Authentication Plugin 経由で実装 (D-07, D-08) |
| `app_local.php` | Datasources の Lolipop MySQL 接続サンプル (`mysql311.phy.lolipop.lan` 等)。 | **インフラ情報のみ**。tamabox Phase 1 で既に `.env` 経由に切り替え済。altotoo は `'password' => 'goat'` を直接書いている等セキュリティ面は踏襲しない。 |

## altotoo の主な「罠潰し」実績 (tamabox で踏襲すべきポイント)

1. **DER → Raw 署名変換** — PHP `openssl_sign(SHA256)` の出力は DER 形式、JWT ES256 は R+S Raw (64 バイト固定) を要求。altotoo の `derToRawSignature()` が実績あり。
2. **DPoP-Nonce リトライ** — 初回 PAR/token request は nonce なしで送る → AS が `400 or 401 + body.error == 'use_dpop_nonce' + header DPoP-Nonce: <value>` を返す → nonce を含めて同一 request を再送。altotoo の `executeParRequest()` / `exchangeCodeForToken()` に実装済み。
3. **DPoP proof の `jwk` header claim** — public key の base64url x/y を DPoP JWT header に埋め込む。altotoo の `createDpopProof()` 参照。
4. **`ath` claim** — DPoP proof で access_token を使う時は `ath = base64url(sha256(access_token))` を proof payload に入れる。これが抜けると 401 に戻る。

## altotoo と tamabox のギャップ (意図的に分岐する箇所)

| 項目 | altotoo | tamabox | 根拠 |
|---|---|---|---|
| Scope | `atproto transition:generic` | `atproto transition:generic` | 将来 Bluesky 投稿機能を追加する可能性があるため、最初から legacy API 権限も確保 (ユーザ決定 2026-04-22) |
| Layer 構造 | Controller Component 1 枚 | `src/Service/Bluesky/` に分離 | 単体テスト容易性、AUTH-06 (マルチプロバイダ抽象化) のため (D-03) |
| Token 永続化 | `$_SESSION` 直書き | `user_identities.*_enc` + AES-GCM | AUTH-04/05/07 要件 (D-07) |
| Session 管理 | `session_start()` + `$_SESSION` 直触り | CakePHP `Authentication` Plugin | フレームワーク標準遵守 (D-08) |
| Endpoint 解決 | 静的 (`config/bluesky.php` で bsky.social 決め打ち) | 静的 (同じ) | MVP は bsky.social ユーザ前提、third-party PDS 対応は v2 以降 (D-04) |
| Handle 入力 | tamabox 側では聞かない | tamabox 側では聞かない | altotoo 方式踏襲、`login_hint` なしで AS 画面で入力 (D-06) |
| DID Document resolver | `plc.directory` のみ | `plc.directory` のみ | did:web 対応は必要時点で別 Phase |
