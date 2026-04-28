# Phase 4: Moderation & Production Launch — Context

**Gathered:** 2026-04-28
**Status:** Ready for planning

<domain>
## Phase Boundary

ブロック・通報・論理削除・退会(account deletion)の **モデレーションレーン** を整え、Phase 2/3 から defer されてきた `UserIdentitiesTable::refreshTokenIfExpired()` を本実装し、本番ドメイン `tamabox.emomie.com` で `debug=false` 固定の Lolipop 共有レンタルサーバ運用に乗せる **MVP launch** を完成させる。

**Requirements mapped to this phase (9 件):** INBOX-04, INBOX-05, MSG-08, MOD-01, MOD-02, MOD-03, MOD-04, INFRA-01, INFRA-06

**Carried in from prior phases:**
- Phase 3 D-35 ハンドオフ: `BlocksController::create()` 501 stub の本実装 / `MessagesController::report()` 501 stub の本実装
- Phase 2 sticky note 5 (Phase 3 D-30 で defer): `UserIdentitiesTable::refreshTokenIfExpired()` の method 本体実装 + call site 配置
- Phase 2/3 verify-phase の `human_needed` 3 件(live Bluesky AS / browser cookie destroy / handle-change sync)を Phase 4 deploy 後に消化

**Not in this phase:**
- 運営側レビュー管理画面 → v2 deferred(MVP は DB 直接クエリ運用)
- グローバル BAN(通報経由の自動送信停止)→ 永遠 Out of Scope(MOD-04 で明示)
- 通知機能(email / Bluesky DM)→ v2 deferred
- AI 検閲 / NG ワードフィルター → 永遠 Out of Scope(MOD-02 で明示)
- 送信レート制限 → 永遠 Out of Scope(PROJECT.md で明示)
- 管理者向け対応 CLI(`bin/cake reports:resolve` 等)→ MVP では DB 直接 UPDATE で十分

</domain>

<decisions>
## Implementation Decisions

### Block UX (Area 1 — INBOX-04)

- **D-01**: ブロックボタン設置 = SSR hit 時の送信者カード上のみ。SSR miss(送信者匿名のまま)時は識別子なしのため設置しない。Phase 3 D-35 で予約済の SSR 結果セクション内ボタン契約を踏襲。
- **D-02**: **ブロック単位 = `users.id`(`blocks.blocker_user_id` / `blocks.blocked_user_id`)**。Phase 1 `CreateBlocks` migration が既に `users.id` 基準で作成済(`uk_blocks_pair` UNIQUE + `blocks_no_self` CHECK)。AUTH-04 で 1 user = 1 identity (1:1) 担保のため identity 単位と operationally 等価。将来 AUTH-06 拡張(X 連携追加)時も同一人物を user 単位で連動ブロックする世界観を維持。
- **D-03**: ブロック実行 = ワンクリック即時 + Flash「<handle> をブロックしました(取り消し)」表示。確認ダイアログなし(取り消しリンクで即座に undo 可能なので誤爆リスク許容)。
- **D-04**: 解除 UI = `/dashboard` 内に「ブロック中ユーザー」セクション、各行に handle + avatar + 解除ボタン。Phase 3 で `/dashboard` を全機能集約(D-20)した方針継承、別ページ化はしない。

### Block Error Display (Area 2 — INBOX-05)

- **D-05**: ブロック判定 = GET / POST 両方で実施(GET で UI 制御、POST で redundant 検証 + race condition 防御)。Phase 3 D-13 の OAuth 経由送信フローと整合。
- **D-06**: エラー UI = 送信フォーム自体は disabled / readonly、上部にバナー表示「この受信箱には送信できません」。フォーム要素は隠さず disabled で見せて UX 不変。
- **D-07**: 未認証ユーザの挙動 = Phase 3 D-13 通り `pending_message_body` を session に保持して OAuth 経由 → callback 完了直後の判定で弾く。callback 後に redirect する送信フォーム再表示時には既にエラー画面状態。
- **D-08**: エラー文言 = **主語をぼかす**「この受信箱には送信できません」。「<受信者 handle> はあなたをブロックしています」のような直接的な明示はしない(プライバシ + 受け手の安全性)。

### Report UX (Area 3 — MOD-01, MOD-02)

- **D-09**: 通報フォーム = **別ページ** `GET /report/<message_id>` → `POST /report/<message_id>`。Phase 3 D-25 段階開示の progressive enhancement(JS 不要)方針継続。Phase 3 で予約済の 501 stub 契約に沿う。
- **D-10**: カテゴリ選択 = ラジオボタン必須、1 通報 1 reason(`reports.reason ENUM('harassment','spam','illegal','other')` Phase 1 schema 既存)。
- **D-11**: 自由記述 (`reports.detail TEXT NULL`) = `reason='other'` 選択時のみ必須、他カテゴリは任意。最大 1000 文字程度(planner 判断)。
- **D-12**: 重複通報制限 = 同じ受け手 × 同じ message で 1 件まで。Phase 1 schema に重複制約はないため Phase 4 で **追加 migration** が必要(候補: `UNIQUE KEY uk_reports_reporter_message (reporter_user_id, message_id)`、planner 判断)。アプリ層で先に存在確認 → INSERT、`DatabaseException` catch で UPSERT 不可エラー扱い。
- **D-13**: MOD-02 担保 = 通報送信時に AI 検閲・NG ワードフィルタ呼び出しを **書かない**。サーバ側は単純 INSERT のみ。

### Report Review (Area 4 — 運営側)

- **D-14**: レビュー手段 = **管理画面なし**、DB 直接クエリ運用(SSH で `SELECT * FROM reports WHERE status = 'pending' ORDER BY created_at`)。MVP 段階(Vi2 仮説検証前)は運営 = 自分のみ前提。
- **D-15**: 通報受信通知 = なし。運営が定期確認(週次想定、運営判断)。
- **D-16**: receiver UI フィードバック = 通報したメッセージに「通報済」バッジ表示、取り消し不可。D-12 の UNIQUE 制約と整合(取り消し不可 ⇔ 重複通報できない)。
- **D-17**: 対応アクション = DB 直接 UPDATE で `reports.status = 'reviewed'/'actioned'/'dismissed'` + `reviewed_at = NOW()` + `resolution_note` 記入。必要なら個別に `messages.deleted_at` を運営側で立てる(receiver 側 D-19 とは別経路)。Phase 4 で CLI コマンドは作らない。

### Soft Delete UX (Area 5 — MSG-08)

- **D-18**: 削除ボタン位置 = メッセージ展開後のフッタ(SSR 結果セクションの下)。Phase 3 D-25 段階開示 UX と整合(本文を読んでから判断)、未開封段階での誤爆を防ぐ。
- **D-19**: 削除確認 = `confirm()` ダイアログ「このメッセージを削除しますか?」。不可逆操作のため確認 1 段階、JS 不要(ブラウザ標準)。
- **D-20**: 削除後の表示 = 一覧から完全に消える(`messages.deleted_at IS NULL` で WHERE フィルター)。「削除済み」バッジは UX 混乱を生むため不採用。
- **D-21**: 復元 UI = なし。`deleted_at` は一方向、運営側は DB 直接 UPDATE で対応可(`deleted_at = NULL`)。
- **D-22**: `messages.deleted_reason` 列の運用 = receiver 側削除時は固定値 `'user_deleted'` を入れる(Phase 1 で `VARCHAR(64) NULL` として用意済)。運営側削除時は `'admin_action'` 等を別途規定(planner 判断)。

### Account Deletion (退会) Flow (Area 6 — MOD-03)

- **D-23**: 退会導線 = **別ページ** `/account/delete` に分離。Phase 3 で `/dashboard/settings` 集約(D-28)とは別ページとして UI 上の重さを表現、誤爆防止。
- **D-24**: 退会時の DB 処理 = `users.deleted_at` のみ UPDATE(timestamp set)。`inboxes.deleted_at` も同時 UPDATE(inbox 隠蔽)、`messages` / `messages.sender_*_snapshot` / `blocks` / `reports` / `user_identities` はすべて **保持**。MOD-03 厳守(逃げ得防止 + V1 仮説補強)。
- **D-25**: 退会後の slug 再利用 = **解放しない**(永遠に dead)。`/<slug>` は 404(`users.deleted_at IS NULL` を slug ルックアップに WHERE 条件追加)。Phase 3 D-37 で deferred だった「退会済み slug 扱い」を本 phase で確定。
- **D-26**: 退会済みユーザの過去メッセージ表示 = sender snapshot **そのまま表示**(handle / avatar / profile_url 全部)。`profile_url` (`https://bsky.app/profile/<handle>`) が dead link 化する可能性は仕様として許容(receiver が「あれ、消えてる」と気付けるのは UX 上 OK)。MOD-03 厳守。
- **D-27**: 退会フォームの確認 = ページ上に `confirm()` 等は使わず、別ページに「退会するには下のチェックを入れて『退会する』ボタンを押してください」テキスト + checkbox 必須 + ボタン。再認証は不要(認証済セッションでの操作前提)。

### Token Refresh (Area 7 — Phase 2 sticky #5 hand-off)

- **D-28**: Refresh 発火ポイント = `UserIdentitiesTable::upsertBlueskyIdentity()` 内の `BlueskyOAuthClient::resolveProfile()` (= `getProfile`) 呼び出し直前で `expires_at_enc` を復号して expiry チェック → 必要なら `refreshTokenIfExpired()` 呼び出し → 新 access token を取得してから `getProfile`。login flow に統合、毎ログイン 1 回のみ判定。
- **D-29**: Refresh 失敗時 = silently ログアウト(`Authentication->logout()` + session 破棄)+ Flash「セッションが切れました。再度ログインしてください」+ LP `/` へ redirect。再 OAuth は user の自発的アクションを待つ。
- **D-30**: Refresh token rotation = **あり**(Bluesky AS 推奨パターン)。refresh のたびに新 access token + 新 refresh token を取得 → AES-GCM 再暗号化で `access_token_enc` / `refresh_token_enc` / `expires_at_enc` の 3 列を UPDATE。
- **D-31**: Phase 4 でのスコープ = **Full 実装**(call site + method 本体 + integration test)。本番 launch で expired セッションに困らないように本 phase で完成させる。HTTP mock test は Phase 2 の `Client::addMockResponse()` パターン(STATE.md `## Accumulated Context` 参照)を踏襲。

### Production Launch (Area 8 — INFRA-01, INFRA-06)

- **D-32**: デプロイ trigger = **Lolipop git deploy**(main push → SSH hook で `composer install --no-dev` + `bin/cake cache:clear_all` を自動実行、migration は手動)。PROJECT.md 既定方針に準拠。
- **D-33**: `.env` と ES256 鍵の配置 = **初回 deploy 前に SSH で 1 度だけ手動配置**(`/path/to/lolipop/config/.env` + `/path/to/lolipop/config/keys/es256-private.pem` + `es256-public.pem`)。リポジトリ外、永続。以後の deploy では一切触らない。鍵生成は VPS 側で `openssl ecparam -name prime256v1 -genkey -noout -out es256-private.pem` で行い `scp` で本番転送。
- **D-34**: Migration 適用方針 = **SSH で手動実行**(`bin/cake migrations migrate` を deploy 後に確認しながら)。Phase 4 で追加する migration は最大 1 件(D-12 重複通報 UNIQUE 制約)程度を想定。Deploy hook 自動実行は migration fail 時に deploy 全体が壊れるリスクがあるため不採用。
- **D-35**: Smoke test 範囲 = **Manual のみ**。本番 launch 後に実 Bluesky アカウントで signup → `/<slug>` 送信 → `/dashboard` 開封 → SSR 確認 → ブロック試行 → 通報試行 → 削除試行 → 退会試行 まで人間が walkthrough。Phase 2/3 verify-phase で defer された `human_needed` 3 件(live Bluesky AS / browser cookie destroy / handle-change sync)も同時に消化。`bin/cake smoke` 自動スクリプトは Phase 4 では追加しない(MVP minimum)。
- **D-36**: 本番 `debug=false` 固定 = `config/app.php` の `Configure::write('debug', filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN))` パターン + 本番 `.env` で `DEBUG=false` 固定。DebugKit は `composer.json` の `require-dev` 配下にあるため `composer install --no-dev` 経由で本番に入らない構造を活用。INFRA-06 担保。
- **D-37**: 初回 deploy 順序 = (1) Lolipop に空の git remote 設定 → (2) SSH で `config/.env` + `config/keys/*.pem` 配置 → (3) `git push lolipop main`(初回 hook で `composer install --no-dev`) → (4) SSH で `bin/cake migrations migrate` → (5) Bluesky AS に `client-metadata.json` URL 登録 → (6) Manual smoke test。

### Phase 設計の境界とハンドオフ (Area 8 横断)

- **D-38**: 通報・ブロック・削除・退会のすべての操作で、Phase 1 schema が用意した列(`messages.deleted_at` / `messages.deleted_reason` / `users.deleted_at` / `inboxes.deleted_at` / `reports` 全列 / `blocks` 全列)を **新 migration なしで UPDATE / INSERT のみで足りる**。例外は D-12 の `uk_reports_reporter_message` UNIQUE のみ(planner 判断で migration 1 件追加)。
- **D-39**: Phase 4 verify-phase の `human_needed` 項目 = launch 直後の実 Bluesky walkthrough(D-35)。Phase 2/3 から繰り越された 3 件 + Phase 4 新規(ブロック実 walkthrough / 通報 / 削除 / 退会)を含む 1 セッションで消化。
- **D-40**: PROJECT.md / REQUIREMENTS.md / ROADMAP.md の **書き換えは Phase 4 では発生しない**(Phase 3 のような scope 変更はなく、要件は元から MVP 範囲内で固定)。CONTEXT.md commit のみ。

### Claude's Discretion

以下は Claude が実装時に判断する範囲:
- D-12 重複通報 UNIQUE 制約の実装形(migration 1 件 vs アプリ層 SELECT-then-INSERT、`DatabaseException` の catch ハンドリング)
- D-22 `deleted_reason` の値文言(`'user_deleted'` / `'admin_action'` 等の規約)
- D-3 / D-19 / D-23 等の Flash message 文言詳細(Phase 2/3 で確立した CakePHP `Flash->success` / `Flash->error` パターンに従う)
- ブロック・通報・削除・退会の各 controller の class 配置(`BlocksController` 既存 stub / 新規 `ReportsController` / `MessagesController::delete` など、Phase 4 plan-phase で planner が決める)
- `BlocksController::create()` の本実装ロジック(POST `/block/<sender_user_id>` のルーティング既存、body 内の view 生成パス、redirect 先)
- 退会 form (`/account/delete`) の HTML 構造(checkbox 必須要素 + CSRF + POST submit)
- Token refresh の HTTP mock fixture(Phase 2 verifier の `Client::addMockResponse()` パターン拡張)
- Lolipop SSH hook script の書き方(具体的 shell 呼び出し、`composer install` のオプション、エラー時の挙動)
- 退会 flow で `users.deleted_at` UPDATE トランザクション境界(`inboxes.deleted_at` も同時 UPDATE するなら同一 transaction 内)
- 通報・ブロック・削除の整合 integration test の HTTP request fixture(認証済セッションでの POST、CSRF token の引き回し)
- Manual smoke test の checklist 形式(Phase 4 verify-phase で定義する具体的手順、Markdown table か checkbox list か)
- Lolipop 環境特有の制約(file ownership / permission、cache:clear の権限、Apache `mod_rewrite` 動作、共有鯖での `bin/cake` 実行可否確認)— 想定外があれば実 deploy 中に planner 判断

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents (researcher / planner / executor) MUST read these before planning or implementing.**

### Discovery (external repo, VPS 内 local clone)

- `/home/claude/projects/ssr-box-discovery/DB-SCHEMA.md` §5 (`blocks`) / §6 (`reports`) / §3 (`inboxes` の `deleted_at`)/ §4 (`messages` の `deleted_at` / `deleted_reason` / `sender_*_snapshot`) — Phase 4 全機能の DDL **single source of truth**
- `/home/claude/projects/ssr-box-discovery/DESIGN.md` Q5(モデレーション方針:事後通報 / 受信側ブロック / グローバル BAN なし)— MOD-01..04 の根拠
- `/home/claude/projects/ssr-box-discovery/AUTH-FLOW.md` §refresh — Bluesky AT Protocol token refresh 仕様(`refresh_token` POST + DPoP-bound、AS rotation あり)— D-28〜D-31 の根拠
- `/home/claude/projects/ssr-box-discovery/ASSUMPTIONS.md` V1(逃げ得防止 = MOD-03 根拠)/ E3(言論抑圧リスク = MOD-02 / 事前検閲 NO の根拠)/ Vi2(通報運用コスト未検証)— Area 4 で運営最小化の根拠

### Project-Level

- `.planning/PROJECT.md` — Out of Scope に「グローバル BAN」「AI 検閲」「レート制限」「殿堂ページ」「メッセージ本文暗号化」が明記。Phase 4 では **書き換え不要**(D-40)
- `.planning/REQUIREMENTS.md` — Phase 4 マッピング 9 件(INBOX-04, 05, MSG-08, MOD-01..04, INFRA-01, 06)。Traceability テーブル §82-119
- `.planning/ROADMAP.md` Phase 4 §77-94 — Goal + 6 件の Success Criteria(verify-phase のチェックリスト)
- `.planning/STATE.md` — Phase 2 sticky #5 (`refreshTokenIfExpired()` defer、Phase 4 D-28〜D-31 で本実装)/ Phase 3 D-30 / D-37 で deferred な item / `## Accumulated Context` の Phase 2 executor-discovered patterns

### Prior Phases

- `.planning/phases/01-foundation-schema/01-CONTEXT.md` — `blocks` / `reports` / `messages.deleted_at` / `messages.deleted_reason` / `users.deleted_at` / `inboxes.deleted_at` の DDL 確定(Phase 4 で UPDATE / INSERT のみ、新 migration は最大 1 件)
- `.planning/phases/02-bluesky-oauth-identity/02-CONTEXT.md` — Authentication / OAuth client 構造、`upsertBlueskyIdentity` の挙動、`*_enc` 列の暗号化規約、501 stub パターン、HTTP mock パターン
- `.planning/phases/03-inbox-message-ssr-reveal/03-CONTEXT.md` — D-13 OAuth 経由送信の `pending_message_body` session pattern(D-7 で再利用)/ D-25 段階開示 UX(D-18 と整合)/ D-29 sender snapshot(D-26 と整合)/ D-35 Phase 4 stub 契約 / D-37 退会 defer / D-39 verify-phase human_needed 慣行(D-39 と整合)

### Codebase State

- `.planning/codebase/STACK.md` — CakePHP 4.5 / PHP 8.0+ / MySQL 8.0 / Lolipop 共有鯖(SUPER 権限なし、トリガ不可)
- `.planning/codebase/ARCHITECTURE.md` — Middleware pipeline、Authentication component、CSRF
- `.planning/codebase/CONVENTIONS.md` — PSR-4 `App\` → `src/`、`declare(strict_types=1)` 必須、`TableLocator::allowFallbackClass(false)` 維持
- `config/Migrations/20260422120005_CreateBlocks.php` — D-02 の根拠(`blocks.blocker_user_id` / `blocked_user_id` UNIQUE pair)
- `config/Migrations/20260422120006_CreateReports.php` — D-10 / D-11 の根拠(`reason` ENUM 4 値 + `detail TEXT NULL` + `status` ENUM 4 値 + `reviewed_at` / `resolution_note`)
- `config/Migrations/20260422120004_CreateMessages.php` — D-22 の根拠(`messages.deleted_at` + `deleted_reason VARCHAR(64) NULL`)
- `src/Controller/BlocksController.php`(Phase 3 で 501 stub 作成済)— D-1〜D-4 の本実装対象
- `src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` + `src/Model/Table/UserIdentitiesTable.php`(Phase 2 で実装済 `upsertBlueskyIdentity`)— D-28〜D-31 の本実装対象

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets (Phase 3 完了時点)

- **`src/Controller/BlocksController.php` 501 stub**(Phase 3 D-35 で予約済) — D-1〜D-4 で本実装。`POST /block/<sender_user_id>` ルート既存。
- **`src/Controller/MessagesController.php`**(Phase 3 で `send` / `open` 実装済、`report` は 501 stub) — D-09 `report` 本実装 + D-18 削除 action 追加(`MessagesController::delete($id)` または専用 controller、planner 判断)。
- **`src/Controller/UsersController.php` `dashboard` action**(Phase 3 で受信一覧 + 設定 + slug 通知集約済、D-20)— D-04 ブロック中ユーザー一覧セクション追加 + D-16 「通報済」バッジレンダリング追加。
- **`src/Controller/AuthController.php`**(Phase 2 `startBluesky` / `logout` 既存) — D-29 silently ログアウト時に再利用。
- **`src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` `resolveProfile()` (= getProfile)**(Phase 2 既存)— D-28 token expiry チェック後の呼び出し対象。
- **`src/Model/Table/UserIdentitiesTable.php` `upsertBlueskyIdentity()`**(Phase 2 既存、Phase 3 D-03 で slug 自動再計算追加済)— D-28 に `refreshTokenIfExpired()` 呼び出しを統合。
- **`src/Model/Table/MessagesTable.php`**(Phase 3 で `sendMessage` 実装済)— D-18 削除 method (`softDeleteByReceiver`) 追加。
- **`src/Model/Table/InboxesTable.php` `findBySlug`**(Phase 3 既存)— D-25 退会後の slug 隠蔽として `WHERE users.deleted_at IS NULL` 追加。
- **CSRF middleware + Authentication component**(Phase 2 wired 済)— Phase 4 全 POST(block / report / delete / 退会)で自動 CSRF token、`getIdentity()` 認証ユーザ取得。
- **`Client::addMockResponse()` HTTP mock pattern**(Phase 2 verifier 確立)— D-31 token refresh の HTTP mock fixture で再利用。
- **`webroot/css/tamabox.css`**(Phase 2 ベース 218 行 + Phase 3 D-35 で +421 行)— Phase 4 でブロック中一覧セクション、通報フォーム、退会ページ、エラーバナー(D-06)、削除確認(D-19)のスタイルを追記。
- **`templates/Pages/home.php`**(Phase 2 で Bluesky CTA 化済) — D-29 silently ログアウト後の redirect 先として既存ベース。

### Established Patterns

- **Configure reads**: `Configure::read('Security.serverSecret')` / `Configure::read('debug')` を D-36 本番設定で参照。
- **Phinx migration**: D-12 重複通報 UNIQUE 制約を Phase 4 で 1 件追加(planner 判断)。
- **Test fixture**: `tests/Fixture/BlocksFixture.php` / `ReportsFixture.php` は Phase 1 で bake 済(schema-valid 手書き)。Phase 4 で integration test 用に records 追加。
- **`TableLocator::allowFallbackClass(false)` 維持**: 新規 Reports Table クラスを `src/Model/Table/ReportsTable.php` で明示作成(Phase 1 で `BlocksTable.php` は既存)。
- **`queryString` / `sessionString` helper pattern** (Phase 2/3 確立、phpstan level 8 対応): Phase 4 controllers でも踏襲。
- **`*_enc` 列の暗号化規約**(Phase 2 D-AUTH-07): D-30 token rotation で `TokenEncryptionService` (Phase 2 既存)を再利用、新 access/refresh token を AES-GCM 再暗号化。

### Integration Points

- **`config/routes.php`** 新規ルート(planner 詳細詰め):
  - `GET /report/<message_id>` → `MessagesController::report($id)` または `ReportsController::create($id)` 通報フォーム表示(D-09)
  - `POST /report/<message_id>` → 同 action 通報処理(D-09)
  - `POST /block/<sender_user_id>` → `BlocksController::create($id)` 既存ルート、501 stub の本実装(D-1〜D-3)
  - `DELETE /block/<sender_user_id>` または `POST /block/<id>/delete` → 解除(D-04)
  - `POST /messages/<id>/delete` → 論理削除(D-18)
  - `GET /account/delete` → 退会フォーム表示(D-23)
  - `POST /account/delete` → 退会処理(D-24)
- **`templates/`** 新規:
  - `templates/Reports/create.php` または `templates/Messages/report.php`(D-09)
  - `templates/Account/delete.php`(D-23)
  - `templates/Users/dashboard.php` 拡張(D-04 ブロック中一覧セクション、D-16 「通報済」バッジ、D-18 削除ボタン)
  - `templates/element/sender_card.php` 等(Phase 3 で部分既存)に「ブロック」ボタン本実装(D-01)
- **`config/app.php` / `.env`**(D-36): `DEBUG` env 経由化、本番 `.env` で `DEBUG=false` 固定。
- **`composer.json`**: `composer install --no-dev` で DebugKit 除外を活用(D-36)。新規依存なし。
- **`config/keys/`**(Phase 2 既存): 本番では Lolipop 側 SSH で個別配置(D-33)、git tracked ではない。

</code_context>

<specifics>
## Specific Ideas

- **D-12 重複通報 UNIQUE migration の DDL 候補**: `ALTER TABLE reports ADD UNIQUE KEY uk_reports_reporter_message (reporter_user_id, message_id)`(planner 判断、Phinx 0.13 の `addIndex(['reporter_user_id', 'message_id'], ['unique' => true, 'name' => 'uk_reports_reporter_message'])`)。注意: `reporter_user_id` は NULL 許可(通報者退会で SET NULL される)、UNIQUE 列に NULL を含むと MySQL は複数 NULL を許す挙動 → 退会後の通報行は重複可能だが、退会後に同じ user が再通報する経路はないため運用上問題なし。
- **D-28 token expiry チェック実装**: `expires_at_enc` を `TokenEncryptionService::decrypt()` で復号 → ISO datetime としてパース → `new DateTime() + safety_margin (60秒)` と比較。expired なら `BlueskyOAuthClient::refreshAccessToken($refresh_token, $dpop_jwk)` 呼び出し → 新 access/refresh/expires_at を `TokenEncryptionService::encrypt()` で再暗号化 → `UserIdentitiesTable::saveOrFail()` で 3 列同時 UPDATE。トランザクション境界は `upsertBlueskyIdentity` 全体に既存の `Connection::transactional()` を活かす。
- **D-29 Refresh 失敗時の判定**: `BlueskyOAuthClient::refreshAccessToken()` が `BlueskyOAuthException` を throw した場合(refresh_token expired or revoked)、catch して `Authentication->logout()` + Flash + redirect。ログには WARN レベルで残す(運営調査用)。
- **D-32 Lolipop git deploy hook の構造**: `~/repo.git/hooks/post-receive` で `cd /path/to/working-dir && git --git-dir=/path/to/repo.git --work-tree=/path/to/working-dir checkout -f main && composer install --no-dev --optimize-autoloader && bin/cake cache:clear_all`。Migration は意図的に hook に入れない(D-34)。
- **D-33 ES256 鍵生成手順**: VPS 側で `openssl ecparam -name prime256v1 -genkey -noout -out es256-private.pem && openssl ec -in es256-private.pem -pubout -out es256-public.pem`。`scp es256-*.pem lolipop:/path/to/config/keys/`。webroot 公開外であることを `.htaccess` で確認(Phase 1 INFRA-05 で httpoxy block 有効化済)。
- **D-35 Manual smoke test の walkthrough 順序**: (1) 実 Bluesky アカウント A で signup → /<a-slug> 見える → (2) Bluesky アカウント B で別 device login → A の inbox に送信 → (3) 切替して A の dashboard 開封 → SSR hit/miss 演出確認 → (4) hit 時に B をブロック → (5) B から再送 → エラーバナー(D-06)→ (6) A 側で B の通報 → reports 行確認(SSH SQL) → (7) A が message 削除 → 一覧から消える → (8) A が退会 → /<a-slug> 404 → B の dashboard で過去送信履歴確認(MOD-03 確認、ただし B は送信履歴 UI を持たないので DB 直接確認)
- **D-36 DEBUG env 化の差分**: `config/app.php` 既存の `'debug' => filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN)` パターンが既に CakePHP 4.5 skeleton で書かれていることを確認(Phase 1 で `config/.env` ローダ有効化済 INFRA-02、`DEBUG=false` の env 値は本番でのみ有効)。
- **退会フォーム CSRF と confirm checkbox**: `<input type="checkbox" name="confirm_delete" required>` HTML5 必須属性 + サーバ側でも検証(`if (!$this->request->getData('confirm_delete')) { throw new BadRequestException(); }`)。CSRF は CSRF middleware 自動。

</specifics>

<deferred>
## Deferred Ideas

- **運営側レビュー管理画面 (admin web UI)** → v2 deferred (PROJECT.md / REQUIREMENTS.md に記載済)。MVP は DB 直接クエリ。
- **`bin/cake reports:resolve <id> --action <delete|dismiss>` CLI** → Vi2 仮説検証後に必要であれば。
- **email / Bluesky DM での通報受信通知** → v2 deferred。
- **削除復元 (undelete) UI** → 不要、`deleted_at` は一方向、運営は DB 直接 UPDATE。
- **退会済みユーザの sender snapshot anonymize**(handle / avatar の masked 表示)→ v2 検討。MVP は MOD-03 厳守でそのまま表示。
- **退会済み slug の quarantine 後解放** → 永遠 Out of Scope(slug 履歴の整合性のため、退会した alice が将来別人として戻ってきても元の slug は復活しない設計)。
- **token refresh の eager middleware 化**(毎 request 時に expiry チェック)→ MVP では login 時の 1 回判定で十分(D-28 lazy 路線)。本番運用で問題が出てから検討。
- **`bin/cake smoke` 自動 smoke スクリプト** → MVP では Manual のみ(D-35)。継続運用時に必要なら別 phase。
- **GitHub Actions CI → Lolipop deploy パイプライン** → MVP では Lolipop git deploy 直接(D-32)。ステージング環境 + CI ゲートが必要になったら別 phase。
- **Synthetic monitor (外部 ping)** → MVP では人手監視。launch 後の運用で必要であれば。
- **複数回退会 / 復活 flow** → 永遠 Out of Scope(退会は一方向、再 signup で同 SNS account でも別 user 行になる)。
- **送信前 NG ワード警告 UI**(検閲ではなく warning)→ MOD-02 と矛盾するため Out of Scope。
- **通報統計 (週次レポート / カテゴリ別 count)** → v2 deferred、運営者の手動 SQL で十分。
- **退会理由のヒアリング(任意 textarea)** → MVP では `confirm()` 相当のチェックボックスのみ(D-27)。理由収集は v2。

</deferred>

<revisions>
## Revisions (post-research, 2026-04-28)

研究フェーズで以下 3 件の不整合を検出。**downstream agents (planner / executor) は本セクションを優先する**(D-XX 上書き)。

### REV-01: D-24 訂正 — `inboxes.deleted_at` 列は存在しない

**元の決定**: D-24「`inboxes.deleted_at` も同時 UPDATE(inbox 隠蔽)」
**実態**: Phase 1 `CreateInboxes` migration / DB-SCHEMA.md v0.2 §3 ともに `inboxes.deleted_at` は**未定義**。`inboxes` テーブルには `deleted_at` 列がない。
**確定方針**: 退会後の slug 404 は `InboxesTable::findBySlug()` の WHERE に `users.deleted_at IS NULL` JOIN フィルタを追加して実現。`inboxes` 行は触らない。`messages.sender_*_snapshot` の保持(MOD-03)には影響しない。
**影響範囲**: D-24 / D-25 / D-37 関連の plan task。新 migration 不要。

### REV-02: D-28 訂正 — 暗号化対象は access/refresh のみ、expiry は plaintext

**元の決定**: D-28「`expires_at_enc` を `TokenEncryptionService::decrypt()` で復号して expiry チェック」
**実態**: Phase 1/2 schema は `access_token_enc` / `refresh_token_enc` (BLOB AES-GCM) + `token_expires_at` (DATETIME plaintext)。expiry は **暗号化しない**(non-PII、頻繁参照、AS で発行された publicly verifiable 値)。
**確定方針**: 仮に token refresh を Phase 4 で実装する場合、`token_expires_at` を直接 datetime として比較する。`expires_at_enc` という列は存在しないため、別 phase に持ち越し時もこの訂正に基づく。

### REV-03: D-31 訂正(descope)— Phase 4 で token refresh は実装しない

**元の決定**: D-31「Phase 4 でのスコープ = Full 実装(call site + method 本体 + integration test)」
**実態**:
1. `BlueskyOAuthClient::refreshToken()` は Phase 2 で既に実装済(`src/Service/OAuth/Bluesky/BlueskyOAuthClient.php:156-187`)、コードは存在する。
2. Phase 4 機能(block / report / soft-delete / 退会 / 本番デプロイ)は **1 度も外部 PDS API を呼ばない**。token refresh の **call site が存在しない**。
3. ログインフローは毎回 `exchangeCodeForToken()` で新規 access_token を発行する。`upsertBlueskyIdentity()` が呼ぶのも login 時の `getProfile` 1 回だけで、access_token は always fresh。
4. D-28 通りに refresh を統合すると 100% dead code(test だけが叩くロジック)になる。

**確定方針 (researcher Resolution A、ユーザ確認済 2026-04-28 03:10 JST)**:
- Phase 4 では **token refresh の call site 統合と method 本体実装は行わない**。
- Phase 2 sticky note #5 (`refreshTokenIfExpired()` defer) は `resolved-as-not-needed-for-MVP` で close。STATE.md にその旨記録。
- 既に存在する Phase 2 の `BlueskyOAuthClient::refreshToken()` のコード自体は残す(将来 PDS API 呼び出し phase が立ったときに統合する)。
- D-25 / D-26 / D-27 / D-29 / D-30 / D-31 の決定はすべて **将来の phase に持ち越し**。Phase 4 では執行しない。
- Phase 4 plan は **moderation CRUD + 退会 + 本番 launch の 3 軸** に専念。

**影響範囲**: Phase 4 の plan 数が 1〜2 本減る(refresh 専用 plan が無くなる)。Phase 4 機能(MVP launch)は完全に維持。送信機能 / ログイン機能 / セッション継続性すべて従来通り動作。

**ユーザ確認**: 2026-04-28 03:10 JST、Discord で「A で OK」明示。

</revisions>

---

*Phase: 04-moderation-production-launch*
*Context gathered: 2026-04-28 (interactive discuss-phase via Discord, 8 areas, 40 decisions captured)*
*Revised: 2026-04-28 03:15 JST (post-research, 3 revisions: REV-01 inbox.deleted_at unset / REV-02 token_expires_at plaintext / REV-03 token refresh descope)*
