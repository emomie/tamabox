---
phase: 04-moderation-production-launch
plan: 01
subsystem: moderation-block-soft-delete
tags: [moderation, block, soft-delete, inbox, dashboard]
requires:
  - Phase 3 BlocksController 501 stub (D-35 hand-off contract)
  - Phase 3 MessagesController::send (Phase 3 D-13 base)
  - Phase 3 UsersController::dashboard (Phase 3 D-20)
provides:
  - INBOX-04 ブロック CRUD UI (block / unblock)
  - INBOX-05 ブロック済送信時のエラー表示 + form disable
  - MSG-08 受信側の論理削除 UI + dashboard filter
  - MOD-04 受信者単位ブロック (グローバル BAN なし sentinel)
affects:
  - templates/Users/dashboard.php (footer + block_list element 追加)
  - templates/Messages/send.php (error-banner + disabled form)
  - tests/Fixture/UsersFixture.php (+1 dave)
  - tests/Fixture/UserIdentitiesFixture.php (+1 dave identity)
  - tests/Fixture/BlocksFixture.php (+1 alice→charlie)
  - tests/Fixture/MessagesFixture.php (+1 soft-deleted aaaa4444)
tech-stack:
  added: []
  patterns:
    - "blocks_table.isBlocked(blockerId, blockedId): exists() truth check (no entity hydration)"
    - "messages_table.softDeleteByReceiver: markOpened-shape (ownership re-check + idempotent + accessibleFields whitelist)"
    - "DELETED_REASON_USER / DELETED_REASON_ADMIN const literal pair (D-22)"
    - "BlocksController::create: DatabaseException catch で UNIQUE 衝突を冪等吸収 (D-03)"
    - "MessagesController::send: dual-gate block check (GET = banner / POST = redirect)"
    - "dashboard.php: opened messages の footer に削除フォーム + 通報済 badge slot (UI-SPEC §6)"
key-files:
  created:
    - templates/element/block_list.php
  modified:
    - src/Controller/BlocksController.php
    - src/Controller/MessagesController.php
    - src/Controller/UsersController.php
    - src/Model/Table/BlocksTable.php
    - src/Model/Table/MessagesTable.php
    - templates/Users/dashboard.php
    - templates/Messages/send.php
    - webroot/css/tamabox.css
    - config/routes.php
    - tests/Fixture/BlocksFixture.php
    - tests/Fixture/MessagesFixture.php
    - tests/Fixture/UsersFixture.php
    - tests/Fixture/UserIdentitiesFixture.php
    - tests/TestCase/Controller/BlocksControllerTest.php
    - tests/TestCase/Controller/MessagesControllerTest.php
    - tests/TestCase/Controller/UsersControllerTest.php
decisions:
  - "Rule 1: Phase 3 既存 send-flow tests (consent / body validation / happy path / send_done) の loginAsBob を loginAsDave に切替 — alice→bob ブロック fixture と新 dual-gate の整合のため"
  - "Rule 2: dave (44444444-...) を UsersFixture + UserIdentitiesFixture に追加 — alice にブロックされていない sender が必要"
  - "Rule 3: Task 4 (a) の BlocksControllerTest 全置換を Task 2 commit に前倒し — 各 commit を独立に green に保つため (501 stub テストが Task 2 の controller body 入替で必ず壊れる構造)"
  - "Rule 1: BlocksController::create / delete の return type を `Response` → `?Response` に変更 — `$this->redirect()` の戻り値が `Response|null` で plan-provided コードが phpstan level 8 を通らなかった"
  - "MessagesController::initialize の allowUnauthenticated コメントを書き換え (空コメント phpcs エラー fix + 'report' 削除メモ追記)"
  - "MessagesController::renderSendForm シグネチャを multiline 化 — line length warning 解消 + readability"
  - "MessagesTable::softDeleteByReceiver シグネチャを multiline 化 — line length warning 解消"
metrics:
  duration: "~14m 30s"
  completed: "2026-04-28"
  tasks: 4
  files_created: 1
  files_modified: 16
  commits: 4
  test_delta: "+14 (170 → 177 tests, hard-counted: 8 BlocksControllerTest replaced + 5 MessagesControllerTest added + 2 UsersControllerTest added − 1 testReportReturns501Stub removed)"
---

# Phase 4 Plan 01: Moderation — block CRUD + 送信側エラー表示 + 論理削除 Summary

受信者側モデレーション機能のうち**ブロック CRUD + 送信フォームのブロックエラー表示 + メッセージ論理削除**を実装。Phase 3 D-35 で予約された `BlocksController::create()` 501 stub を本実装に差し替え、`MessagesController::send()` に dual-gate ブロック判定 (D-05/D-06) を追加し、`MessagesController::delete()` を新設して MSG-08 を満たす。dashboard には『ブロック中ユーザー』セクションと message-row footer (削除ボタン + 通報済 badge slot) を追加した。要件 INBOX-04 / INBOX-05 / MSG-08 / MOD-04 を closed.

## Tasks 完了状況

| Task | 内容 | Commit |
|------|------|--------|
| 1 | BlocksTable::isBlocked + MessagesTable::softDeleteByReceiver + fixtures | `32b8da6` |
| 2 | BlocksController real impl + Messages send block-check + delete + dashboard filter + routes (+ Task 4(a) 前倒し: BlocksControllerTest 全置換) | `c95ff1d` |
| 3 | block_list partial + dashboard footer + tamabox.css §1/§3/§5/§6/§9 | `f8f77f7` |
| 4 | block check + soft-delete + dashboard filter integration tests (Messages +5 / Users +2) | `51e0d53` |

## Verification 結果

- `composer phpcs` → 65/65 files clean
- `composer phpstan` → level 8 [OK] No errors
- `composer test` → **177 tests / 485 assertions / 0 failures** (Phase 3 baseline 163 → +14)
- `bin/cake routes` で `messages:delete` / `blocks:delete` / `blocks:create` の 3 ルート確認済

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] BlocksController::create / delete の戻り値型不一致**
- **Found during:** Task 2 phpstan 実行
- **Issue:** plan-provided code は `: Response` 宣言だが `$this->redirect()` は `Response|null` を返す → phpstan level 8 で 8 件 error。
- **Fix:** 両 method の return type を `: ?Response` に変更し docblock も `@return \Cake\Http\Response|null` に揃えた。
- **Files modified:** src/Controller/BlocksController.php
- **Commit:** `c95ff1d`

**2. [Rule 1 - Bug] Phase 3 既存 send-flow テスト群が新 dual-gate block check で破綻**
- **Found during:** Task 2 phpunit 実行
- **Issue:** alice→bob block fixture は元から存在していたが Phase 3 の send POST 経路は誰もブロック判定を呼んでいなかったため inert だった。Phase 4 04-01 で `MessagesController::send` に block-check を入れた瞬間、既存の `loginAsBob → /alice POST` 系テスト 5 件 (testSendPostAuthenticatedHappyPathInsertsMessage / testSendPostConsentMissingRedirectsWithError / testSendPostBodyTooLongRedirectsWithError / testSendPostBodyEmptyRedirectsWithError / testSendDoneOmitsSsrResult) が「同意/2000 文字/本文/送信しました」マッチに来る前にブロックされ Flash error 'この受信箱には送信できません。' が出るため失敗。
- **Fix:** これら 5 件を `loginAsDave()` に切替 + 各テストにコメント追記。dave (44444444-...) を UsersFixture / UserIdentitiesFixture に新規追加して alice にブロックされていない sender として用意した (Rule 2 と兼任)。bob は `testOpenOtherUsersMessageReturns403` (alice の message を bob が削除/開封できないことの確認) で引き続き必要なため `loginAsBob()` ヘルパーは温存。
- **Files modified:** tests/Fixture/UsersFixture.php / tests/Fixture/UserIdentitiesFixture.php / tests/TestCase/Controller/MessagesControllerTest.php
- **Commit:** `c95ff1d`

**3. [Rule 2 - Missing critical functionality] Test fixture に dave (非ブロック sender) が必要**
- **Found during:** Task 2 phpunit 実行
- **Issue:** Phase 3 fixture には alice / bob / charlie の 3 user しかいないが、charlie は UserIdentities fixture に行がないため `MessagesTable::sendMessage` が "sender has no user_identity" で失敗、bob は alice→bob ブロック行がある → "alice にブロックされていない / UserIdentity を持つ sender" が居ない。Phase 4 04-01 でブロック検査が有効化されると、send-flow のテストの validation 検査自体に到達できない。
- **Fix:** dave (id=44444444-..., handle=`dave.bsky.social`) を UsersFixture + UserIdentitiesFixture に追加。
- **Files modified:** tests/Fixture/UsersFixture.php / tests/Fixture/UserIdentitiesFixture.php
- **Commit:** `c95ff1d`

**4. [Rule 3 - Blocking issue] Task 4 (a) BlocksControllerTest 全置換を Task 2 commit に前倒し**
- **Found during:** Task 2 phpunit 実行
- **Issue:** plan は Task 2 で BlocksController body を 501 stub から本実装に差し替え、Task 4 (a) で対応する `testCreateReturns501Stub` を含む BlocksControllerTest 全体を置換する構造。順番通り実行すると Task 2 commit 直後 ~ Task 4 commit 直前の間 BlocksControllerTest が必ず failing 状態になり、各 commit の atomic green 不変条件を破る。
- **Fix:** Task 4 (a) の BlocksControllerTest 全置換 (501 stub テスト 2 件削除 → 10 件の本実装テスト) を Task 2 commit に統合。Task 4 では Task 4 (b) (Messages +5) と Task 4 (c) (Users +2) のみ実装。
- **Files modified:** tests/TestCase/Controller/BlocksControllerTest.php
- **Commit:** `c95ff1d`

**5. [Rule 1 - Style] phpcs scope 内 (src/+tests/) のスタイル違反 fix**
- **Found during:** Task 2 phpcs 実行
- **Issue:** 元の plan-provided MessagesController initialize 修正で空コメント `//` が残った (空コメント phpcs エラー)。MessagesTable::softDeleteByReceiver / MessagesController::renderSendForm がそれぞれ 120 文字を超える line length warning を出した。
- **Fix:** 空コメントを意味のあるコメント (Phase 4 04-01: 'report' removed メモ) で置換。両 method シグネチャを multiline 化。
- **Files modified:** src/Controller/MessagesController.php / src/Model/Table/MessagesTable.php
- **Commit:** `c95ff1d`

### Out-of-scope deferrals

なし。Plan 04-01 の対象範囲は完全に履行。

## Known Stubs (intentional, resolved by Plan 04-02)

| Stub | File | Reason | Resolution |
|------|------|--------|------------|
| `/report/{id}` route still points to `Messages::report` (action deleted) | config/routes.php:113-117 | Plan 04-02 で ReportsController を作成して route を `Reports::create` に re-point する設計。04-01 ではアクション削除と route 残しが意図的整合 (本 plan の `template/Users/dashboard.php` 既存 inline 通報 form は触らず、04-02 で `<a href>` リンク化と同時に解決)。 | Plan 04-02 |
| `templates/Users/dashboard.php` の SSR-hit セクション内 inline 通報フォーム (Phase 3 既存) | templates/Users/dashboard.php:102-108 | 上記と同じ。04-02 でリンク化される。 | Plan 04-02 |

これらは hit user が SSR-hit 開封後に「通報する」ボタンを押すと `Missing Action` エラーになる軽度な経路だが、04-01 の MVP launch までに 04-02 が同 wave 内で続くため許容。テスト suite はこの経路を踏まないため green を維持。

## Threat Surface Coverage

| STRIDE Threat ID | Disposition | Implementation Evidence |
|------------------|-------------|------------------------|
| T-04-01-01 IDOR on /dashboard/blocks/{id}/delete | mitigate | `BlocksController::delete` で `$block->blocker_user_id !== $myId` ガード → ForbiddenException (`testDeleteForbiddenForNonOwner` で確認) |
| T-04-01-02 IDOR on /dashboard/messages/{id}/delete | mitigate | `MessagesTable::softDeleteByReceiver` が `contain('Inboxes')` で再ロード → `$inbox->user_id !== $ownerUserId` ガード (`testDeleteForbiddenForNonOwner` で確認) |
| T-04-01-04 Information disclosure (banner copy) | mitigate | UI-SPEC §1 fixed text "この受信箱には送信できません" — blocker handle 露出なし |
| T-04-01-05 DoS / spam re-block | accept | DatabaseException catch で 200 → 302 redirect 返却、UNIQUE 制約により行重複なし (`testCreateIdempotentOnDuplicate` で確認) |
| T-04-01-06 Self-block CHECK bypass | mitigate | App-layer self-block 拒否 + `blocks_no_self` CHECK 二重防御 (`testCreateRejectsSelfBlock` で確認) |
| T-04-01-07 URL UUID 注入 | mitigate | route regex `[0-9a-f-]{36}` 既存、識別は session のみ |

## Decisions Made

- **D-22 reason literals を const として MessagesTable に置く** — `MessagesTable::DELETED_REASON_USER = 'user_deleted'` / `DELETED_REASON_ADMIN = 'admin_action'`。型安全 + 検索容易性 + 04-02 で運営側削除 path が同じ const を再利用できる。
- **DatabaseException catch は完全 silent で OK** — D-03 の冪等仕様。Flash success のみ出して `/dashboard` redirect (Plan 04-02 で undo リンク追加が予定されているが 04-01 では plain success)。
- **`renderSendForm` の `$isBlocked = false` default param** — Phase 3 既存 callers が 4 引数で呼んでも壊れないように後方互換。
- **dashboard `<aside>` 後 + `</div>` 前に `block_list` element** — UI-SPEC §3 のレイアウト指定 (mobile single-column, desktop grid-column 2)。CSS の `@media (min-width: 768px) { .block-list { grid-column: 2 / 3 } }` で 2 列レイアウト時に右カラムに収まる。

## Self-Check: PASSED

- src/Controller/BlocksController.php: FOUND (real impl, 0 stubs)
- src/Controller/MessagesController.php: FOUND (delete action present, report action removed)
- src/Controller/UsersController.php: FOUND (deleted_at filter + blocks/$reportedSet view-vars)
- src/Model/Table/BlocksTable.php: FOUND (isBlocked finder)
- src/Model/Table/MessagesTable.php: FOUND (softDeleteByReceiver + 2 const)
- templates/element/block_list.php: FOUND (new file, 43 lines)
- templates/Users/dashboard.php: FOUND (footer + element call)
- templates/Messages/send.php: FOUND (error-banner + disabled flags)
- webroot/css/tamabox.css: FOUND (749 lines, +108)
- config/routes.php: FOUND (3 Phase 4 routes)
- tests/Fixture/{Blocks,Messages,Users,UserIdentities}Fixture.php: FOUND (rows appended)
- tests/TestCase/Controller/{Blocks,Messages,Users}ControllerTest.php: FOUND
- Commit 32b8da6: FOUND
- Commit c95ff1d: FOUND
- Commit f8f77f7: FOUND
- Commit 51e0d53: FOUND

---

**Next:** Plan 04-02 (Wave 2) — `/report/{id}` ReportsController + 退会 + slug-404 retired-user filter (REV-01 / D-23..D-27)
