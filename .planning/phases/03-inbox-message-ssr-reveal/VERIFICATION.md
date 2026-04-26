---
phase: 03-inbox-message-ssr-reveal
verified: 2026-04-26T20:00:00Z
status: human_needed
score: 7/7 must-haves verified (code level)
overrides_applied: 0
human_verification:
  - test: "実際の Bluesky アカウントから tamabox にログインし、slug が自動付与されること、handle 変更後にログインし直したとき slug が追従することを確認する"
    expected: "slug が satie.bsky.social → satie のように付与される。改名後は新 slug に更新され、旧 slug は slug_previous に退避される。dashboard でコリジョン flash が 1 回だけ表示される"
    why_human: "Bluesky AS への実接続と実アカウントのハンドル変更操作が必要。統合テストは DB fixture + session モックで代替している"
  - test: "ブラウザで /<slug> にアクセスし、未認証状態で本文を入力 → 送信ボタン押下 → Bluesky OAuth → callback 後に /<slug>?restored=1 にリダイレクトされ、入力本文が textarea に復元されていることを確認する"
    expected: "OAuth 完了後に send フォームに戻り、以前入力した本文が textarea に表示される"
    why_human: "OAuth redirect loop はブラウザのセッション cookie + AS callback が必要。E2E テストでは HTTP mock で代替"
  - test: "ブラウザで dashboard を開き、未開封メッセージの行をクリック → 本文展開 → 「開封する」ボタン → SSR 結果 (hit: 送信者カード / miss: ★ miss テキスト) が段階的に表示されることを確認する"
    expected: "D-25 段階的開示 UX が視覚的に機能する。avatar は onerror fallback で /img/default-avatar.svg に切り替わる"
    why_human: "CSS アニメーション / <details> 展開 / avatar onerror はブラウザ上での視覚確認が必要"
gaps: []
deferred: []
---

# Phase 3: Inbox, Message & SSR Reveal — Verification Report

**Phase Goal:** 受け手が自分の inbox(SNS handle 由来 slug + SSR 確率カスタマイズ + welcome_message + is_accepting)を持ち、Bluesky OAuth 済みの送り手が同意 UI を経て送信、送信時に is_ssr / ssr_probability_at_send / ssr_seed / sender snapshot が確定し、受け手が /dashboard で受信一覧を見て段階的開示 UX(本文 → 開封 → SSR 結果)で SSR hit/miss を確認できる、というコア体験 E2E が成立しているか。

**Verified:** 2026-04-26T20:00:00Z
**Status:** human_needed (コードレベル 7/7 PASS; 3 件は live Bluesky AS / ブラウザ視覚確認が必要)
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths (Roadmap Success Criteria)

| # | Truth | Status | Evidence |
|---|-------|--------|---------|
| 1 | 受け手はサインアップ時に SNS handle 由来の slug が自動付与され `/<slug>` で inbox URL を持つ。衝突時は `-2`/`-3` suffix が自動付与。SNS 改名時は slug と display_name が自動追従 | VERIFIED | `SlugDeriver::deriveFromHandle` (src/Service/Inbox/SlugDeriver.php:54) 実装済。`InboxesTable::assignSlugForUser` (src/Model/Table/InboxesTable.php:190) 衝突 retry loop (n=0..100 + did_hash8 fallback)。`UserIdentitiesTable::upsertBlueskyIdentity` (grep:226-301) 両方のパス(新規/既存)で `SlugDeriver` + `assignSlugForUser` 呼び出し。`SlugCollisionSuffixApplied` イベント dispatch 確認。InboxesTableTest::testAssignSlugForUserCreatesNewInbox / testAssignSlugForUserAppliesSuffix / testAssignSlugForUserRenames の 3 テストで動作確認。SlugDeriverTest 10 tests / 10 assertions OK |
| 2 | 受け手は自分の inbox の SSR 確率を 0〜100% のスライダ/数値で設定・保存でき、デフォルト 10% が適用される | VERIFIED | `InboxesController::settings` (src/Controller/InboxesController.php:27) POST で `ssr_probability_pct` を 0..100 整数として受け取り `DECIMAL(4,3)` 文字列に変換して patchEntity。`InboxesControllerTest` に確率変更 / 範囲外 / 保存成功の統合テスト。ssa_probability デフォルト `'0.100'` が `assignSlugForUser` で明示 set |
| 3 | 未認証の訪問者が送信フォームにアクセスすると、Bluesky OAuth 同意を経なければ送信ボタンが押せない(AUTH-03) | VERIFIED | `MessagesController::send` POST 分岐: 未認証 → `stashAndRedirectToLogin` (src/Controller/MessagesController.php:100)。`AuthController::startBluesky` が `pending_message_body` + `pending_message_inbox_id` をセッションに書き込み(grep:57-63)。`OauthController::callback` が `pending_message_inbox_id` を消費して `/<slug>?restored=1` にリダイレクト(grep:202-213)。`MessagesControllerTest::testSendPostUnauthenticatedStashesBodyAndRedirects` で検証 |
| 4 | 送信フォームは「確率で名前がバレる可能性がある」旨を明示し、同意チェック/同意ボタンなしでは送信 submit できない | VERIFIED | `templates/Messages/send.php:58-61` に `<input type="checkbox" name="consent" value="1" required>` と固定文言「このメッセージは抽選で…(現在の確率: X%)」。`processSend` server-side: `$consent === ''` のとき 422 相当の Flash + redirect (src/Controller/MessagesController.php:218-221)。`MessagesControllerTest::testSendPostNoConsentRedirectsBack` で検証 |
| 5 | 送信成功時、`messages` 行には `is_ssr` と `ssr_seed = sha256(server_secret + message_id + created_at)` が刻まれ、送信者の handle / avatar / profile_url のスナップショットが保存される | VERIFIED | `MessagesTable::sendMessage` (src/Model/Table/MessagesTable.php:183): `SsrJudge::judge` で seed + is_ssr 算出、entity に `ssr_seed`, `is_ssr`, `ssr_probability_at_send`, `sender_handle_snapshot`, `sender_avatar_url_snapshot`, `sender_profile_url_snapshot` をすべて bake して saveOrFail。`MessagesTableTest::testSendMessagePersistsSsrAndSnapshotFields` で DB 挿入後のフィールドを assert。F2 監査性テスト `testSendMessageSsrSeedIsDeterministic` で seed の再現性を確認 |
| 6 | 受け手のダッシュボードで自分の inbox の受信一覧が見え、未開封/開封済みが視覚的に区別される | VERIFIED | `UsersController::dashboard` (src/Controller/UsersController.php:42) でページネーション (limit 20, DESC) + inbox 取得 + collisionFlash 消費。`templates/Users/dashboard.php:60-123` で `<details>` + `data-state` `unread`/`opened` + ● / ✓ アイコン。`UsersControllerTest` に受信一覧表示 / 未開封太字 / 開封済みチェック / ページネーション / out-of-range の統合テスト |
| 7 | 未開封メッセージを「開封」操作すると `opened_at` が記録され、`is_ssr=true` のときだけ送信者 identity(handle / avatar / profile_url)が露出表示される | VERIFIED | `MessagesTable::markOpened` (src/Model/Table/MessagesTable.php:283): ownership check → idempotent `opened_at` set。dashboard.php の SSR reveal 分岐: `$isHit` true → sender-card (avatar / handle / profile_url / 通報・ブロックボタン), false → `.ssr-reveal__miss` テキスト。`MessagesControllerTest` に open 5 tests (ownership / idempotent / forbidden)。`UsersControllerTest::testDashboardShowsOpenedSsrHitReveal` / `testDashboardShowsOpenedSsrMissText` |

**Score:** 7/7 truths verified at code level

---

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|---------|--------|---------|
| `src/Service/Inbox/SlugDeriver.php` | Handle→slug 正規化 pure service | VERIFIED | 77 行、D-01/D-02/fallback 実装済 |
| `src/Service/Message/SsrJudge.php` | 決定的 SSR 判定 | VERIFIED | 65 行、sha256(serverSecret+id+createdAt_micro)、Configure::read 経由 |
| `config/Migrations/20260427120000_AddSlugPreviousToInboxes.php` | slug_previous 列追加 migration | VERIFIED | ファイル存在確認済 |
| `src/Model/Table/InboxesTable.php` | findBySlugOrPrevious / assignSlugForUser | VERIFIED | 300 行、両メソッド実装済、contain Users |
| `src/Model/Table/MessagesTable.php` | sendMessage + markOpened | VERIFIED | 311 行、SSR bake + snapshot freeze + ownership check |
| `src/Controller/MessagesController.php` | send GET/POST + open + report 501 stub | VERIFIED | 275 行、auth gate + D-13 + D-19 実装済 |
| `src/Controller/UsersController.php` | dashboard paginated + collision flash | VERIFIED | 123 行、paginator limit 20 DESC + consume-once flash |
| `src/Controller/InboxesController.php` | settings GET redirect / POST patch | VERIFIED | 116 行、pct→decimal 変換 + IDOR 防止 |
| `src/Controller/BlocksController.php` | POST /block 501 stub (D-35) | VERIFIED | 52 行、501 stub + allowUnauthenticated |
| `templates/Messages/send.php` | 送信フォーム + 同意 UI + D-13 restored | VERIFIED | consent checkbox + probabilityPct 表示 + restoredBody |
| `templates/Messages/send_done.php` | 送信完了画面 (D-19: SSR 非開示) | VERIFIED | ファイル存在確認済 |
| `templates/Users/dashboard.php` | 受信一覧 + SSR reveal + settings embed | VERIFIED | 137 行、D-25 段階開示、sender-card、pagination |
| `templates/element/inbox_settings_form.php` | 設定フォーム (ssr_probability + slider + welcome_message + is_accepting) | VERIFIED | ファイル存在確認済 |
| `webroot/img/default-avatar.svg` | D-31 avatar fallback asset | VERIFIED | 286 bytes SVG存在確認済 |
| `webroot/css/tamabox.css` | Phase 3 UI スタイル | VERIFIED | 641 行 (Phase 2: 218 + Phase 3: ~423) |

---

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `UserIdentitiesTable::upsertBlueskyIdentity` | `InboxesTable::assignSlugForUser` | `TableRegistry::getTableLocator()->get('Inboxes')` | WIRED | grep:226-234, 290-298 で両パス(新規/既存)確認 |
| `Application::bootstrap` | `SlugCollisionSuffixApplied` event listener | `EventManager::instance()->on(...)` | WIRED | src/Application.php:86-105、Router::getRequest() でセッション書き込み |
| `UsersController::dashboard` | `Flash.slug_collision_suffix` 消費 | session read + delete | WIRED | src/Controller/UsersController.php:102-110 consume-once |
| `MessagesController::send (POST unauth)` | `AuthController::startBluesky` | `/login/bluesky` redirect | WIRED | MessagesController:100 → AuthController:57-63 |
| `OauthController::callback` | `/<slug>?restored=1` redirect | `pending_message_inbox_id` session consume | WIRED | src/Controller/OauthController.php:202-213 |
| `MessagesController::processSend` | `MessagesTable::sendMessage` | `fetchTable('Messages')` | WIRED | MessagesController:237 |
| `MessagesTable::sendMessage` | `SsrJudge::judge` | `new SsrJudge()` | WIRED | MessagesTable.php:226-227 |
| `MessagesController::open` | `MessagesTable::markOpened` | `fetchTable('Messages')` | WIRED | MessagesController:128 |
| `dashboard.php` (unread) | `POST /dashboard/messages/{id}/open` | `<form>` submit | WIRED | dashboard.php:71-77 |
| `config/routes.php` | `/{slug}` catch-all | AFTER 全特殊ルート | WIRED | routes.php:125-131 — /dashboard/messages, /dashboard/settings, /report, /block が先に定義。slug は最後 |

---

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
|----------|---------------|--------|-------------------|--------|
| `templates/Users/dashboard.php` | `$messages` | `UsersController::dashboard` → `$messagesTable->find()->where(['inbox_id' => $inbox->id])` paginate | DB query (inbox_id WHERE + ORDER DESC) | FLOWING |
| `templates/Users/dashboard.php` | `$inbox` | `InboxesTable::find()->where(['user_id' => $userId])` | DB query | FLOWING |
| `templates/Messages/send.php` | `$inbox` (ssr_probability 表示) | `InboxesTable::findBySlugOrPrevious` | DB query (slug + slug_previous) | FLOWING |
| `MessagesTable::sendMessage` | `ssr_seed`, `is_ssr`, `ssr_probability_at_send` | `SsrJudge::judge` → `Configure::read('Security.serverSecret')` + sha256 | env-driven deterministic hash | FLOWING |
| `MessagesTable::sendMessage` | `sender_handle_snapshot` / `sender_avatar_url_snapshot` | `$usersTable->find()->contain(['UserIdentities'])` → `user_identity->handle_cached` | DB query (user_identities table) | FLOWING |

---

### Behavioral Spot-Checks

| Behavior | Verification | Result | Status |
|----------|-------------|--------|--------|
| SlugDeriver: satie.bsky.social → satie | SlugDeriverTest (10 tests, 10 assertions) | All pass | PASS |
| SsrJudge: 決定的 seed + 確率判定 | SsrJudgeTest (9 tests, 49 assertions) | All pass | PASS |
| InboxesTable 衝突 suffix (-2, -3) | InboxesTableTest::testAssignSlugForUserAppliesSuffix | Pass | PASS |
| MessagesTable sendMessage SSR bake | MessagesTableTest 5 tests (seed hex64, snapshot, determinism) | Pass | PASS |
| MessagesController send POST full suite | MessagesControllerTest 16 tests | All pass | PASS |
| UsersController dashboard full suite | UsersControllerTest 10 tests | All pass | PASS |
| InboxesController settings full suite | InboxesControllerTest 13 tests | All pass | PASS |
| BlocksController 501 stub | BlocksControllerTest 2 tests | All pass | PASS |
| Full test suite | 163 tests / 439 assertions / 0 failures | PASS | PASS |
| phpstan level 8 | `vendor/bin/phpstan analyse --level=8 src/` | [OK] No errors | PASS |
| phpcs PSR2 | 0 errors (warning のみ — 既存 line-length、Phase 3 新規コードに ERROR なし) | PASS (warnings only) | PASS |

---

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|---------|
| AUTH-03 | 03-02 | 送り手は OAuth 完了しないと送信できない | SATISFIED | MessagesController::send POST 未認証 → stashAndRedirectToLogin; テスト確認済 |
| INBOX-01 | 03-01 | サインアップ時に SNS handle 由来 slug 自動付与、衝突で -2/-3 suffix | SATISFIED (実装済み、REQ チェックボックス未更新は追記漏れ) | SlugDeriver + assignSlugForUser + UserIdentitiesTable hook 実装確認済 |
| INBOX-02 | 03-03a | SSR 確率 0-100% 設定 | SATISFIED | InboxesController::settings POST + テスト |
| INBOX-03 | 03-03a | 受信一覧ダッシュボード | SATISFIED | UsersController::dashboard + dashboard.php |
| INBOX-06 | 03-01 | SNS handle 改名時 slug 追従 | SATISFIED (実装済み、REQ チェックボックス未更新は追記漏れ) | UserIdentitiesTable existing-user path で slug 再計算 + slug_previous 保存 |
| MSG-01 | 03-02 | 送信フォーム (絵文字対応 utf8mb4) | SATISFIED | MessagesController + MessagesTable + utf8mb4_0900_ai_ci schema |
| MSG-02 | 03-01/02 | is_ssr 送信時確定 | SATISFIED | SsrJudge + sendMessage で bake-in |
| MSG-03 | 03-01/02 | ssr_seed = sha256(server_secret+id+created_at) | SATISFIED | SsrJudge.php:55 確認済 |
| MSG-04 | 03-02 | 送信者 snapshot 送信時点で確定 | SATISFIED | sendMessage で handle/avatar/profile_url を user_identity から freeze |
| MSG-05 | 03-02 | 同意 UI + 同意なし送信不可 | SATISFIED | send.php consent checkbox required + processSend server-side check |
| MSG-06 | 03-03a | 開封操作で opened_at 記録 | SATISFIED | markOpened + MessagesController::open |
| MSG-07 | 03-03a | 開封前/開封済みの視覚区別 | SATISFIED | dashboard.php ● vs ✓, unread/opened CSS state |

**注意:** REQUIREMENTS.md の INBOX-01 と INBOX-06 のチェックボックスが `[ ]` のままになっている（追記漏れ）。実装は完成しているが、REQUIREMENTS.md の更新が必要。

---

### Anti-Patterns Found

| File | Pattern | Severity | Impact |
|------|---------|---------|--------|
| `src/Controller/MessagesController.php:57` | `allowUnauthenticated(['send', 'report'])` — `report` を 501 stub のために unauthenticated 許可 | Info | D-35 手法として意図的。Phase 4 で report 本実装時に remove が必要。SUMMARY に明記済み |
| `src/Controller/BlocksController.php:35` | `allowUnauthenticated(['create'])` — 501 stub のため | Info | D-35 同上。意図的 |
| `webroot/css/tamabox.css` | 641 行 — Phase 3 extension は shell heredoc で追記 | Info | 機能的影響なし |

**スキップ済テスト (6件) の内訳確認:**
- `InboxesTableTest::testValidationDefault` — incomplete (pre-existing、Phase 1 bake から)
- `UserIdentitiesTableTest::testValidationDefault` — incomplete (pre-existing)
- 残り4件 — pre-existing incomplete テスト

Phase 3 新規コードに skipped / incomplete テストはなし。

---

### Human Verification Required

#### 1. Bluesky handle 由来 slug 実 AS 接続確認

**Test:** 実際の Bluesky アカウント (例: satie.bsky.social) で tamabox にサインアップし、slug が `satie` として付与されることを確認。次に Bluesky でハンドルを変更し、tamabox に再ログインしたとき slug が新ハンドル由来の値に更新され、旧 slug が `slug_previous` に退避されることを確認。コリジョンが発生した場合は dashboard に一度だけ flash 通知が表示されることを確認。

**Expected:** slug = `<handle の ドット前部分>` が自動付与される。改名後の再ログインで slug が追従し、旧 slug URL からは 301 リダイレクト。dashboard 最初の表示でコリジョン flash が表示され、2 回目以降は非表示。

**Why human:** Bluesky AS への実接続が必要。統合テストは session + DB fixture で代替しているが、実 OAuth callback でのセッション書き込み → 次 request での消費という 2-request 流れは実ブラウザが必要。

#### 2. D-13 pending body 復元の E2E ブラウザ確認

**Test:** ブラウザで `/alice` にアクセスし(未認証)、textarea に本文を入力 → 「Bluesky でログインして送信」ボタン → Bluesky OAuth → callback → `/<slug>?restored=1` にリダイレクト → textarea に入力した本文が復元されていることを確認。

**Expected:** OAuth 完了後に send フォームに戻り、入力した本文が textarea に表示される。2 回目のリロードでは textarea が空になる (consume-once)。

**Why human:** 実 OAuth redirect loop + ブラウザセッション cookie が必要。

#### 3. 段階的開示 UX の視覚確認

**Test:** dashboard で未開封メッセージの `<details>` 行をクリックして本文展開 → 「開封する」ボタン押下 → `opened_at` UPDATE → SSR hit の場合は sender-card (avatar + handle + profile_url リンク) が、miss の場合は「★ 抽選 miss」テキストが表示されることを確認。avatar の onerror fallback も確認 (`/img/default-avatar.svg` に切り替わる)。

**Expected:** D-25 仕様どおりの段階的開示 UX。avatar fallback が機能し、外部 CSS スタイルが適用されて sender-card が読みやすく表示される。

**Why human:** `<details>` 展開動作 / CSS 視覚確認 / `onerror` イベント発火はブラウザ上での確認が必要。

---

### Gaps Summary

コードレベルのギャップなし。

唯一の注意事項: **REQUIREMENTS.md の INBOX-01 と INBOX-06 のチェックボックスが `[ ]` のままになっている**。実装は Phase 3 で完了しているが、03-03a または 03-03b の SUMMARY 更新時に REQUIREMENTS.md の更新が漏れたと思われる。このチェックボックスのみ更新が必要 (機能上の問題はない)。

Phase 4 への hand-off 項目:
- `MessagesController::report` の 501 stub 解除 + `allowUnauthenticated` から `report` を削除
- `BlocksController::create` の 501 stub 実装 + `allowUnauthenticated` から `create` を削除
- REQUIREMENTS.md INBOX-01 / INBOX-06 チェックボックス更新 (`[x]` に変更)

---

## Recommendation

**Phase 4 に PROCEED してよい。**

コードレベルの全 7 Observable Truth が検証済み (7/7)。テストスイート 163 tests / 439 assertions / 0 failures、phpstan level 8 [OK]、phpcs 0 errors。

3 件の human_needed 項目 (Bluesky AS 実接続 / D-13 ブラウザ E2E / 段階的開示視覚確認) は Phase 4 デプロイ後 (`tamabox.emomie.com`) に smoke test として実施する — Phase 2 verify-phase と同じ「コードレベル PASS → Phase 4 デプロイ後に human gate」の前例踏襲。

---

_Verified: 2026-04-26T20:00:00Z_
_Verifier: Claude (claude-sonnet-4-6) — gsd-verifier pattern_
