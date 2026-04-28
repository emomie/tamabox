# tamabox — v1 Requirements

PROJECT.md の Active 要件を REQ-ID 付きで正規化したもの。ROADMAP のフェーズは各 REQ-ID を最低1つのフェーズにマップする。

**Source of truth**: PROJECT.md(高レベル意図)+ `emomie/ssr-box-discovery`(詳細設計)。当ファイルは **build 可能な粒度** で表現する。

---

## v1 Requirements

### 認証 / アイデンティティ (AUTH)

- [x] **AUTH-01** — 受け手は Bluesky OAuth (AT Protocol, ES256 confidential client, PAR + DPoP + PKCE 必須) でサインアップできる _(Plan 02-04: BlueskyOAuthClient PAR+PKCE+DPoP → OauthController::callback upsert 新規 user + identity)_
- [x] **AUTH-02** — 既存ユーザーは Bluesky OAuth でログインでき、セッションが確立する _(Plan 02-04: upsertBlueskyIdentity existing-user path + Authentication->setIdentity)_
- [x] **AUTH-03** — 送り手は Bluesky OAuth を完了していないとメッセージ送信フォームから送信できない
- [x] **AUTH-04** — `users` テーブルと `user_identities` テーブルで 1 ユーザー = 1 SNS アカウント (1:1) を DB 制約で担保する _(Phase 1 uk_user_identities_provider_account UNIQUE 制約 + Plan 02-04 DatabaseException catch の race 対応)_
- [x] **AUTH-05** — ユーザーログイン時に SNS handle / avatar / profile_url を最新取得して DB に同期する _(Plan 02-04: upsertBlueskyIdentity が毎ログイン時に handle_cached / avatar_url_cached / profile_url_cached / last_synced_at を更新)_
- [x] **AUTH-06** — OAuth プロバイダインタフェースを抽象化し、将来 X (Twitter) を追加できる構造にする(Bluesky 実装は具体クラス) _(Plan 02-01 interface shell + Plan 02-04 BlueskyOAuthClient が 5 methods concrete impl)_
- [x] **AUTH-07** — OAuth アクセストークン / リフレッシュトークンは AES-GCM でアプリ側暗号化して `*_enc` 列に格納する _(Plan 02-02: TokenEncryptionService、Plan 02-04 で upsertBlueskyIdentity が encrypt 後に *_enc 列に保存する write-path 完了)_
- [x] **AUTH-08** — ES256 鍵ペアは `config/keys/`(web 公開外)に配置、`jwks.json` と `client-metadata.json` のエンドポイントを公開する _(Plan 02-01 鍵 + Plan 02-03 endpoint actions)_
- [x] **AUTH-09** — ログアウト動作を提供(セッション破棄 + CSRF 対応) _(Plan 02-04: AuthController::logout POST-only + allowMethod + $this->Authentication->logout() + ログアウト flash)_

### 受信箱 (INBOX)

- [x] **INBOX-01** — 受け手のサインアップ時に SNS handle から導出した slug が自動付与され、受け手は inbox URL (`/<slug>`) を持つ。slug 衝突時は `-2` / `-3` の suffix が自動で付く(Phase 3 改定: 元は『カスタム slug を選んで』だったが、receiver と sender の SNS identity 共有を維持するため自動付与方式に変更)
- [x] **INBOX-02** — 受け手は自分の inbox の SSR 確率を 0〜100% で設定・変更できる(デフォルト 10%)
- [x] **INBOX-03** — 受け手は自分の inbox の受信メッセージ一覧をダッシュボードで閲覧できる
- [x] **INBOX-04** — 受け手は送信者(identity)単位でブロックできる
- [x] **INBOX-05** — ブロックされた送信者が同じ inbox に送信しようとするとフォーム上でエラー表示される(「このユーザーには送信できません」)
- [x] **INBOX-06** — 受け手の SNS handle 改名時に inbox の slug と display_name が自動追従する。tamabox 単独で slug / display_name を変更する機能はない(Phase 3 改定: 元は『受け手は slug / display_name を後から変更できる』だったが、SNS identity 連動方針に変更)

### メッセージ / SSR メカニクス (MSG)

- [x] **MSG-01** — 送り手は送信フォームにメッセージ本文を入力して送信できる(絵文字対応: `utf8mb4_0900_ai_ci`)
- [x] **MSG-02** — SSR 判定(`is_ssr`)は送信時に確定して `messages` 行に刻まれる(開封時には変更されない)
- [x] **MSG-03** — `ssr_seed = sha256(server_secret + message_id + created_at)` を格納し、監査可能性(F2 仮説)を担保する
- [x] **MSG-04** — 送信者の handle / avatar / profile_url を送信時点の値でメッセージ行にスナップショット保存する
- [x] **MSG-05** — 送信フォームに「確率で名前がバレる可能性がある」旨の同意 UI を明示し、同意なしでは送信できない(E1 仮説 / 法的合意)
- [x] **MSG-06** — 受け手が未開封メッセージを「開封」する操作で `opened_at` が記録され、SSR 露出情報がフロントに開示される
- [x] **MSG-07** — 開封前・開封済みは一覧上で視覚的に区別される(ガチャ演出の土台)
- [x] **MSG-08** — 受け手はメッセージを論理削除(`deleted_at` セット)できる

### 通報 / モデレーション (MOD)

- [x] **MOD-01** — 受け手は任意のメッセージを 4 カテゴリ(`harassment` / `spam` / `illegal` / `other`)で通報でき、`reports` 行に記録される _(Plan 04-02: ReportsController::create GET+POST + uk_reports_reporter_message UNIQUE migration + DatabaseException|PDOException catch idempotent)_
- [x] **MOD-02** — 通報はあくまで事後レビュー方式で、送信時点で AI 検閲や NG ワードフィルターは実施しない _(Plan 04-02: ReportsController は INSERT-only、AI/NG フィルタ呼び出しなし; PROJECT.md Out-of-Scope に明記)_
- [x] **MOD-03** — 退会(ユーザー削除)時も過去メッセージの送信者 snapshot(handle / avatar / profile_url)は保持する(V1 仮説補強) _(Plan 04-02: AccountController::delete は users.deleted_at のみ UPDATE、messages.sender_*_snapshot は不変; testDeletePostPreservesSenderSnapshots で sentinel)_
- [x] **MOD-04** — 通報された送信者でも、受け手側のブロック操作がない限り、他 inbox への送信は妨げない(グローバル BAN は MVP 範囲外)

### インフラ / 運用 (INFRA)

- [x] **INFRA-01** — 本番ドメイン `tamabox.emomie.com` で動作し、ロリポップの webroot 制約(`webroot/` のみ公開)に準拠する
- [x] **INFRA-02** — `.env` ローダ(`config/.env`)を有効化し、秘匿値(DB認証情報 / server_secret / OAuth 関連キー)を環境変数経由で注入する
- [x] **INFRA-03** — `composer.json` の PHP 要件を `^8.0` に整合させ、本番 Lolipop PHP 8.0+ と合わせる
- [x] **INFRA-04** — `bin/cake migrations migrate` で MySQL 8.0 向けスキーマ(users / user_identities / inboxes / messages / reports / blocks)を適用できる
- [x] **INFRA-05** — `.htaccess` の httpoxy ブロックを有効化する(現在コメントアウト)
- [x] **INFRA-06** — 本番は `debug=false` 固定で DebugKit 無効化、ステージ/開発時のみ true
- [x] **INFRA-07** — CakePHP `TableLocator::allowFallbackClass(false)` は維持、全テーブルに明示 Table クラスを作成する

---

## v2 Requirements (deferred)

- X (Twitter) OAuth プロバイダ追加(AUTH-06 の拡張)
- プレミアム課金 / カスタム演出(Vi1 仮説検証後に判断)
- 通知機能(受信時メール / Push)
- 運営側レビュー管理画面(通報に対する対応ワークフロー)

## Out of Scope

- **Google / メール認証** — SNS 性を重視するため意図的に不採用
- **AI 悪意度判定 / NG ワードフィルター** — A2(事後通報のみ)を採用、F1 / E3 仮説検証後に再考
- **送信頻度レート制限** — MVP 不採用(将来拡張)
- **メッセージ本文暗号化** — 共有サーバー前提、通報レビュー運営要件とのバランスで不採用(トークンのみ AES-GCM)
- **ネイティブアプリ (iOS / Android)** — Web only で完結
- **DB セッションストレージ** — PHP ファイルセッションで出発、Lolipop 制約で MVP 範囲外
- **SSR 殿堂ページ** — E2 仮説(二次的晒し行為化)リスクのため MVP 不採用
- **グローバル BAN** — 通報経由での自動送信停止は MVP 範囲外、受け手側ブロックのみ

---

## Traceability

| REQ-ID | Phase | Status |
|--------|-------|--------|
| AUTH-01 | Phase 2: Bluesky OAuth & Identity | Shipped 2026-04-24 (Plan 02-04: BlueskyOAuthClient PAR+PKCE+DPoP + OauthController::callback new-user path) |
| AUTH-02 | Phase 2: Bluesky OAuth & Identity | Shipped 2026-04-24 (Plan 02-04: upsertBlueskyIdentity existing-user path + setIdentity) |
| AUTH-03 | Phase 3: Inbox, Message & SSR Reveal | Complete |
| AUTH-04 | Phase 2: Bluesky OAuth & Identity | Shipped 2026-04-24 (Phase 1 UNIQUE 制約 + Plan 02-04 DatabaseException catch) |
| AUTH-05 | Phase 2: Bluesky OAuth & Identity | Shipped 2026-04-24 (Plan 02-04: upsertBlueskyIdentity が毎ログイン時に handle/avatar/profile/last_synced_at 更新) |
| AUTH-06 | Phase 2: Bluesky OAuth & Identity | Shipped 2026-04-24 (Plan 02-01 interface + Plan 02-04 BlueskyOAuthClient concrete impl) |
| AUTH-07 | Phase 2: Bluesky OAuth & Identity | Shipped 2026-04-24 (Plan 02-02: TokenEncryptionService service; Plan 02-04: UserIdentitiesTable write-path 完成) |
| AUTH-08 | Phase 2: Bluesky OAuth & Identity | Shipped 2026-04-23 (Plan 02-01 鍵 + Plan 02-03 /oauth/jwks.json + /oauth/client-metadata.json endpoints) |
| AUTH-09 | Phase 2: Bluesky OAuth & Identity | Shipped 2026-04-24 (Plan 02-04: AuthController::logout POST-only + CSRF + session destroy) |
| INBOX-01 | Phase 3: Inbox, Message & SSR Reveal | Shipped 2026-04-26 (Plan 03-01: SlugDeriver + UserIdentitiesTable hook + assignSlugForUser collision retry) |
| INBOX-02 | Phase 3: Inbox, Message & SSR Reveal | Complete |
| INBOX-03 | Phase 3: Inbox, Message & SSR Reveal | Complete |
| INBOX-04 | Phase 4: Moderation & Production Launch | Shipped 2026-04-28 (Plan 04-01: BlocksController create/delete + isBlocked finder + dashboard block-list section + DatabaseException catch idempotent) |
| INBOX-05 | Phase 4: Moderation & Production Launch | Shipped 2026-04-28 (Plan 04-01: MessagesController::send dual-gate block check + send.php error-banner + disabled form) |
| INBOX-06 | Phase 3: Inbox, Message & SSR Reveal | Shipped 2026-04-26 (Plan 03-01: UserIdentitiesTable handle-change hook → slug rotation + slug_previous tracking + SlugCollisionSuffixApplied event) |
| MSG-01 | Phase 3: Inbox, Message & SSR Reveal | Complete |
| MSG-02 | Phase 3: Inbox, Message & SSR Reveal | Complete |
| MSG-03 | Phase 3: Inbox, Message & SSR Reveal | Complete |
| MSG-04 | Phase 3: Inbox, Message & SSR Reveal | Complete |
| MSG-05 | Phase 3: Inbox, Message & SSR Reveal | Complete |
| MSG-06 | Phase 3: Inbox, Message & SSR Reveal | Complete |
| MSG-07 | Phase 3: Inbox, Message & SSR Reveal | Complete |
| MSG-08 | Phase 4: Moderation & Production Launch | Shipped 2026-04-28 (Plan 04-01: MessagesController::delete + MessagesTable::softDeleteByReceiver + dashboard message-row__footer + UsersController::dashboard messages.deleted_at IS NULL filter) |
| MOD-01 | Phase 4: Moderation & Production Launch | Shipped 2026-04-28 (Plan 04-02: ReportsController::create GET+POST + uk_reports_reporter_message UNIQUE migration + 4 reason ENUM 検証 + 1000 char detail 上限) |
| MOD-02 | Phase 4: Moderation & Production Launch | Shipped 2026-04-28 (Plan 04-02: AI/NG フィルタなし INSERT-only ポリシー + PROJECT.md Out-of-Scope) |
| MOD-03 | Phase 4: Moderation & Production Launch | Shipped 2026-04-28 (Plan 04-02: AccountController::delete users.deleted_at のみ UPDATE + sender snapshot 不変 + InboxesTable retired-user 404 REV-01) |
| MOD-04 | Phase 4: Moderation & Production Launch | Shipped 2026-04-28 (Plan 04-01: dual-gate block check は inbox.user_id × current sender の組のみ参照、testSendPostUnrelatedInboxIgnoresUnrelatedBlocks sentinel が確認済) |
| INFRA-01 | Phase 4: Moderation & Production Launch | Complete |
| INFRA-02 | Phase 1: Foundation & Schema | Shipped (01-01) |
| INFRA-03 | Phase 1: Foundation & Schema | Shipped (01-01) |
| INFRA-04 | Phase 1: Foundation & Schema | Shipped (01-02a + 01-02b) |
| INFRA-05 | Phase 1: Foundation & Schema | Shipped (01-01) |
| INFRA-06 | Phase 4: Moderation & Production Launch | Complete |
| INFRA-07 | Phase 1: Foundation & Schema | Shipped (01-03) |

**Coverage**: 34/34 v1 requirements mapped to exactly one phase. No orphans, no duplicates.

---
*Last updated: 2026-04-28 after Phase 4 Plan 04-01 execution (INBOX-04 / INBOX-05 / MSG-08 / MOD-04 closed; 29/34 requirements shipped)*
