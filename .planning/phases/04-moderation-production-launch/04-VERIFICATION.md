---
phase: 04-moderation-production-launch
verified: 2026-04-28T13:50:00Z
re_verified: 2026-05-13T08:30:30Z
status: verified
score: 9/9 code-level requirements verified; 12/12 manual smoke items passed (see 04-UAT.md)
overrides_applied: 0
roadmap_success_criteria_passed: 6/6 (code-level)
test_suite: 195 tests / 546 assertions / 0 failures (6 incomplete pre-existing skips)
phpstan_level: 8 [OK]
phpcs: 69/69 clean
migrations: 8/8 up (incl. AddReporterMessageUniqueToReports)

requirements:
  - id: INBOX-04
    status: verified
    evidence: "src/Controller/BlocksController.php:29-75 (create) + 86-118 (delete); src/Model/Table/BlocksTable.php:104-110 (isBlocked); 10 BlocksControllerTest passing"
  - id: INBOX-05
    status: verified
    evidence: "src/Controller/MessagesController.php:86-91 (dual-gate isBlocked call) + 105-109 (POST reject); templates/Messages/send.php:31-33 (banner) + 56-77 (disabled attr)"
  - id: MSG-08
    status: verified
    evidence: "src/Model/Table/MessagesTable.php:333-364 (softDeleteByReceiver); src/Controller/UsersController.php:85 (Messages.deleted_at IS NULL filter, sole list query)"
  - id: MOD-01
    status: verified
    evidence: "src/Controller/ReportsController.php:34-144 (4-reason ENUM validation, 1000 char detail, INSERT only); config/Migrations/20260428120001 uk_reports_reporter_message UNIQUE; templates/Reports/create.php:29-58 (4 radio buttons)"
  - id: MOD-02
    status: verified
    evidence: "ReportsController has zero AI/NG-word filter calls — pure validation + INSERT (verified by grep: no AI/filter/checkContent calls). PROJECT.md Out-of-Scope explicit."
  - id: MOD-03
    status: verified
    evidence: "src/Controller/AccountController.php:60-67 (only users.deleted_at UPDATE, no messages touched); testDeletePostPreservesSenderSnapshots passing; src/Model/Table/InboxesTable.php:154,166 (REV-01 retired-user 404 filter); testSendReturns404WhenInboxOwnerRetired passing"
  - id: MOD-04
    status: verified
    evidence: "BlocksTable::isBlocked queries (blocker_user_id, blocked_user_id) PAIR — receiver-scoped, not global. testSendPostUnrelatedInboxIgnoresUnrelatedBlocks passing"
  - id: INFRA-01
    status: verified_doc_level
    evidence: "LAUNCH-RUNBOOK.md (272 lines, Steps 1-6 + rollback + Lolipop quirks); deploy is human-execution gate"
  - id: INFRA-06
    status: verified
    evidence: "config/app.php:19 'debug' => filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN); src/Application.php:70-72 Configure::read('debug') guard around addPlugin('DebugKit'); composer.json:20 cakephp/debug_kit in require-dev (excluded by composer install --no-dev)"

human_verification:
  - test: "Live Bluesky AS handshake — sign up via real Bluesky account A through OAuth flow"
    expected: "After OAuth callback, redirect to /dashboard with /<a-slug> visible; users + user_identities + inboxes rows created"
    why_human: "External AS (bsky.social) handshake requires real identity; cannot be exercised by HTTP-mocked tests"
    smoke_step: "MANUAL-SMOKE-CHECKLIST item (1) + (10)"
  - test: "Send + open + SSR reveal end-to-end against production"
    expected: "Account B sends to /<a-slug>, message lands in dashboard, open reveals SSR hit/miss correctly"
    why_human: "Browser session continuity, two-account dual-device flow, visual SSR reveal animation"
    smoke_step: "MANUAL-SMOKE-CHECKLIST items (2) + (3)"
  - test: "Block button on SSR-hit sender card → block enforcement"
    expected: "Click block, B's resend to /<a-slug> shows 'この受信箱には送信できません' banner + disabled form"
    why_human: "SSR hit/miss is stochastic; depends on a real RNG outcome. Setting prob=100 is allowed."
    smoke_step: "MANUAL-SMOKE-CHECKLIST items (4) + (5)"
  - test: "Report flow"
    expected: "通報する link → /report/<id> → submit → Flash success + 通報済 badge"
    why_human: "Browser-side <a href> nav + form submission UX"
    smoke_step: "MANUAL-SMOKE-CHECKLIST item (6)"
  - test: "Soft-delete with native confirm dialog"
    expected: "削除 button → confirm() OK → message disappears from list"
    why_human: "JavaScript confirm() dialog can only be exercised in a real browser"
    smoke_step: "MANUAL-SMOKE-CHECKLIST item (7)"
  - test: "Account deletion flow + retired-user 404"
    expected: "/account/delete + checkbox → /<a-slug> → 404; session cookie destroyed"
    why_human: "Browser cookie destroy + session continuity check; live URL 404"
    smoke_step: "MANUAL-SMOKE-CHECKLIST item (8)"
  - test: "MOD-03 sender_snapshot retention via SSH SQL"
    expected: "After A退会, SELECT ... FROM messages WHERE sender_user_id=B shows sender_handle_snapshot etc. unchanged"
    why_human: "Requires Lolipop SSH access + live MySQL query post-deploy"
    smoke_step: "MANUAL-SMOKE-CHECKLIST item (9)"
  - test: "Logout cookie destroy"
    expected: "/oauth/logout POST → CakePHP session cookie removed in DevTools; /dashboard redirects to /"
    why_human: "Browser cookie inspection (Phase 2 carry-over)"
    smoke_step: "MANUAL-SMOKE-CHECKLIST item (11)"
  - test: "Handle-change sync via second login"
    expected: "Bluesky handle rename → relogin → new slug auto-derived; old slug → 301 redirect (1 generation grace)"
    why_human: "Requires real Bluesky handle change which is rate-limited at the AS (Phase 3 carry-over)"
    smoke_step: "MANUAL-SMOKE-CHECKLIST item (12)"
  - test: "Production DEBUG=false verification"
    expected: "Hit /somethinginvalid404 on tamabox.emomie.com → CakePHP production error page (no stack trace)"
    why_human: "Requires live deploy; DebugKit absence + error handler can only be confirmed against tamabox.emomie.com"
    smoke_step: "LAUNCH-RUNBOOK Verification gates"
  - test: "DebugKit absent verification (composer install --no-dev result)"
    expected: "ls vendor/cakephp/debug_kit on Lolipop → no such directory"
    why_human: "Requires SSH access to Lolipop after deploy hook fires"
    smoke_step: "LAUNCH-RUNBOOK Verification gates"
  - test: "TLS certificate + jwks endpoint live"
    expected: "curl https://tamabox.emomie.com/oauth/jwks.json + /oauth/client-metadata.json return valid JSON over TLS"
    why_human: "Requires live deploy + DNS resolution"
    smoke_step: "LAUNCH-RUNBOOK Verification gates"
---

# Phase 4: Moderation & Production Launch — Verification Report

**Phase Goal**: 通報・ブロック・論理削除・退会時 snapshot 保持の運用レーンを整え、`tamabox.emomie.com` 上で `debug=false` 固定の本番運用に乗せる。

**Verified**: 2026-04-28 (code-level), 2026-05-13 (manual smoke walkthrough)
**Status**: `verified` — code-level 9/9 requirements verified, 12/12 manual smoke items passed on tamabox.emomie.com
**Re-verification**: Yes (manual smoke pass appended 2026-05-13 via `/gsd-verify-phase 4` — see `04-UAT.md`)
**Verdict**: **PASS — Phase 4 fully verified, ready for v1 milestone close.**

---

## Goal Achievement

### Observable Truths (ROADMAP Success Criteria 6/6)

| #   | Truth                                                                                            | Status                | Evidence                                                                                                                                                                                                       |
| --- | ------------------------------------------------------------------------------------------------ | --------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | 受け手は任意の送信者 identity をブロックでき、ブロックされた送信者が同じ inbox に送信しようとするとフォーム上でエラー表示される | ✓ VERIFIED            | `src/Controller/BlocksController.php:29-75` (create); `src/Model/Table/BlocksTable.php:104-110` (isBlocked exists() check); `src/Controller/MessagesController.php:86-91` (dual-gate); `templates/Messages/send.php:31-33` (banner copy "この受信箱には送信できません") |
| 2   | 受け手は任意の受信メッセージを 4 カテゴリで通報でき、reports 行に記録される(送信時に AI/NG フィルタはかからない)         | ✓ VERIFIED            | `src/Controller/ReportsController.php:82-98` (4-value ENUM allowlist); `src/Controller/MessagesController.php` send/processSend has zero filter calls (pure validate + INSERT); migration `20260428120001` adds `uk_reports_reporter_message` |
| 3   | 受け手は任意の受信メッセージを論理削除でき、deleted_at がセットされて一覧から外れる(物理行は残る)                     | ✓ VERIFIED            | `src/Model/Table/MessagesTable.php:333-364` (softDeleteByReceiver — only 2 fields patched: deleted_at + deleted_reason); `src/Controller/UsersController.php:85` ("Messages.deleted_at IS" => null) — sole list query in app |
| 4   | 受け手ユーザーが退会しても、過去に送った message の送信者 snapshot は DB 上に残る                              | ✓ VERIFIED            | `src/Controller/AccountController.php:60-67` (UPDATE users.deleted_at ONLY, no messages touched); `testDeletePostPreservesSenderSnapshots` passes; `src/Model/Table/InboxesTable.php:154,166` (REV-01 retired-user 404 filter both branches) |
| 5   | 通報された送信者でも、受け手側のブロックがない限り、別 inbox への送信は拒否されない                                   | ✓ VERIFIED            | `BlocksTable::isBlocked(blockerId, blockedId)` line 104 queries the EXACT pair via `blocker_user_id` + `blocked_user_id`. `MessagesController::send:90` passes `$inbox->user_id` as blocker. Different inbox = different blocker = no cross-inbox block. `testSendPostUnrelatedInboxIgnoresUnrelatedBlocks` passes |
| 6   | tamabox.emomie.com で実サイトが稼働し、debug=false 固定 / DebugKit 無効化 / webroot 外 config / ES256 鍵が config/keys/ で OAuth・送信・開封が本番から通る | ⚠️ HUMAN NEEDED       | Code-level: VERIFIED (config/app.php:19 env-driven debug, src/Application.php:70-72 Configure::read('debug') guard, composer.json:20 debug_kit in require-dev, LAUNCH-RUNBOOK.md Steps 1-6 + rollback). Live deploy + smoke walkthrough still pending — see human_verification block. |

**Score**: 5/6 truths fully verified at code level; truth #6 has full code-level wiring but requires live tamabox.emomie.com deploy + manual smoke (12 checklist items) for end-to-end confirmation.

---

## Required Artifacts

### Phase 4 Plan 04-01 (Block + Soft-delete + Dashboard footer)

| Artifact                                | Expected                                            | Status     | Details                                                                                                            |
| --------------------------------------- | --------------------------------------------------- | ---------- | ------------------------------------------------------------------------------------------------------------------ |
| `src/Controller/BlocksController.php`   | create($senderUserId) + delete($id), real impl      | ✓ VERIFIED | 119 lines, `Response\|null` return type, DatabaseException catch idempotent, ForbiddenException ownership check    |
| `src/Controller/MessagesController.php` | send block-check + delete($id) + report removed     | ✓ VERIFIED | 305 lines, MessagesController::report action removed (no withStatus(501) hits in src/); send dual-gate at lines 86-91, 105-109; delete at 148-169 |
| `src/Controller/UsersController.php`    | dashboard messages.deleted_at IS NULL filter        | ✓ VERIFIED | line 85, sole list paginate; line 100/123 reportedSet view-var; line 109 blocks query                              |
| `src/Model/Table/BlocksTable.php`       | isBlocked(blockerId, blockedId): bool               | ✓ VERIFIED | line 104-110 — exists() truth check on (blocker_user_id, blocked_user_id) PAIR. Receiver-scoped per MOD-04         |
| `src/Model/Table/MessagesTable.php`     | softDeleteByReceiver + DELETED_REASON_USER          | ✓ VERIFIED | lines 36-37 (consts), 333-364 (method); ownership check + idempotent + accessibleFields whitelist                  |
| `templates/element/block_list.php`      | dashboard ブロック中ユーザー partial                  | ✓ VERIFIED | 45 lines, exceeds min 25                                                                                          |
| `templates/Users/dashboard.php`         | footer 削除 form + 通報済 badge slot + element call  | ✓ VERIFIED | line 118-123 delete form with `onsubmit="return confirm(...)"`, line 126-128 badge, line 148 `element('block_list')` |
| `templates/Messages/send.php`           | error-banner + disabled form when isBlocked         | ✓ VERIFIED | line 31-33 banner, lines 56-77 `disabled` attr on textarea/checkbox/button                                       |
| `webroot/css/tamabox.css`               | Phase 4 §1/§3/§5/§6/§9 styles appended (>=690 lines) | ✓ VERIFIED | 911 lines after 04-02                                                                                              |

### Phase 4 Plan 04-02 (Report + Account Deletion + REV-01)

| Artifact                                                                       | Expected                                              | Status     | Details                                                                                                              |
| ------------------------------------------------------------------------------ | ----------------------------------------------------- | ---------- | -------------------------------------------------------------------------------------------------------------------- |
| `config/Migrations/20260428120001_AddReporterMessageUniqueToReports.php`       | uk_reports_reporter_message UNIQUE                    | ✓ VERIFIED | 49 lines, addIndex with unique=true, name set, down() reverses; migrations status shows "up"                         |
| `src/Controller/ReportsController.php`                                         | create($messageId) GET+POST                           | ✓ VERIFIED | 158 lines; DatabaseException \| PDOException union catch (line 117); SQLSTATE 23000 / 'Duplicate entry' / index name match; 404 on cross-inbox |
| `src/Controller/AccountController.php`                                         | delete() GET+POST                                     | ✓ VERIFIED | 75 lines; FrozenTime UPDATE users.deleted_at; logout(); BadRequestException on missing checkbox                      |
| `src/Model/Table/InboxesTable.php`                                             | findBySlugOrPrevious with Users.deleted_at IS NULL    | ✓ VERIFIED | line 154 (current slug branch) + line 166 (slug_previous branch) BOTH have `'Users.deleted_at IS' => null` (REV-01) |
| `templates/Reports/create.php`                                                 | 4-radio reasons + detail textarea (>=30 lines)        | ✓ VERIFIED | 59 lines; 4 radio inputs (harassment/spam/illegal/other); textarea maxlength=1000                                    |
| `templates/Account/delete.php`                                                 | confirm_delete checkbox + 退会する button (>=30 lines)| ✓ VERIFIED | 34 lines; `<input type="checkbox" name="confirm_delete" required>` + post action /account/delete                     |
| `templates/element/inbox_settings_form.php`                                    | danger-zone link to /account/delete                   | ✓ VERIFIED | 04-02 SUMMARY confirms; routes registered                                                                            |
| `templates/Users/dashboard.php`                                                | inline 通報 form replaced by <a href>                 | ✓ VERIFIED | line 129 `<a href="/report/<?= h(...) ?>" ...>通報する</a>`, no inline POST form for /report any more                 |

### Phase 4 Plan 04-03 (Production Launch Runbook + Smoke Checklist)

| Artifact                            | Expected                                                | Status     | Details                                                                                                          |
| ----------------------------------- | ------------------------------------------------------- | ---------- | ---------------------------------------------------------------------------------------------------------------- |
| `LAUNCH-RUNBOOK.md`                 | D-37 ordered Steps 1-6 + rollback + Lolipop quirks      | ✓ VERIFIED | 272 lines; Step 1..Step 6 each section present; Rollback procedure section present; DebugKit absent verification gate present |
| `MANUAL-SMOKE-CHECKLIST.md`         | 12 checkbox items (9 Phase 4 + 3 carry-over)            | ✓ VERIFIED | 73 lines; items (1)..(12) numbered; REV-01 / MOD-03 references; failure logging template                          |
| `config/.env.example`               | Phase 4 production guidance + DEBUG=false comment       | ✓ VERIFIED | line 17 DEBUG="true" (dev), line 73 # `export DEBUG="false"` (prod guidance comment); checklist appended          |
| `.planning/STATE.md`                | REV-03 propagation (Phase 2 sticky #5 = resolved)        | ✓ VERIFIED | line 164: "Phase 2 sticky #5 ... = `resolved-as-not-needed-for-MVP`"                                              |

---

## Key Link Verification

| From                                           | To                                          | Via                                                                              | Status     | Details                                                                                |
| ---------------------------------------------- | ------------------------------------------- | -------------------------------------------------------------------------------- | ---------- | -------------------------------------------------------------------------------------- |
| `MessagesController::send`                     | `BlocksTable::isBlocked`                    | `fetchTable('Blocks')->isBlocked((string)$inbox->user_id, $currentUserId)`       | ✓ WIRED    | line 88-91; uses `$inbox->user_id` as blocker (per-inbox scoping)                      |
| `UsersController::dashboard`                   | `messages.deleted_at IS NULL` filter        | paginate where clause `'Messages.deleted_at IS' => null`                         | ✓ WIRED    | line 85 — only message-list query in entire app                                        |
| `templates/Users/dashboard.php`                | `templates/element/block_list.php`          | `<?= $this->element('block_list', ['blocks' => $blocks]) ?>`                     | ✓ WIRED    | dashboard.php line 148                                                                 |
| `ReportsController::create`                    | `uk_reports_reporter_message` UNIQUE        | `try { saveOrFail() } catch (DatabaseException \| PDOException $e)`              | ✓ WIRED    | line 117 union catch; line 121-126 SQLSTATE/index/Duplicate matching (race-safe)       |
| `AccountController::delete`                    | `users.deleted_at = NOW()`                  | `patchEntity([deleted_at => FrozenTime::now()], accessibleFields=[deleted_at])`  | ✓ WIRED    | line 64-67; ONLY users row touched (REV-01 — no inboxes.deleted_at)                    |
| `InboxesTable::findBySlugOrPrevious`           | `Users.deleted_at IS NULL` (REV-01)         | WHERE clause on contain(['Users']) JOIN, BOTH branches                           | ✓ WIRED    | lines 154 + 166 — current slug AND slug_previous branches both filter retired user     |
| `templates/element/inbox_settings_form.php`    | `/account/delete`                           | `<a href='/account/delete' class='button button-clear button-destructive'>`      | ✓ WIRED    | per 04-02 SUMMARY; routes:account:delete registered                                    |
| `templates/Users/dashboard.php`                | `/report/<id>` link                         | `<a href="/report/<?= h(...) ?>"...>通報する</a>` (replacing inline POST)         | ✓ WIRED    | line 129; no inline `Form->create` for /report any more                                |
| `LAUNCH-RUNBOOK.md`                            | D-37 ordered 6 steps                        | `## Step 1..6` sections                                                          | ✓ WIRED    | grep confirms 6 ## Step sections                                                       |
| `MANUAL-SMOKE-CHECKLIST.md`                    | 12 checkbox items                           | `- [ ]` items (1)..(12)                                                          | ✓ WIRED    | 12 items total (9 Phase 4 + 3 carry-over)                                              |

---

## Data-Flow Trace (Level 4) — Critical Paths

| Artifact / Path                              | Data Source                                        | Produces Real Data | Status     |
| -------------------------------------------- | -------------------------------------------------- | ------------------ | ---------- |
| Dashboard message list                        | paginate(MessagesTable->find()->where(deleted_at IS null) ORDER BY created_at) | YES (real ORM)   | ✓ FLOWING  |
| Dashboard `$blocks` (ブロック中ユーザー)      | BlocksTable->find()->where(blocker_user_id=$me)->contain(BlockedUsers->UserIdentities) | YES         | ✓ FLOWING  |
| Dashboard `$reportedSet` (通報済 badge keys) | ReportsTable->find()->where(reporter_user_id=$me, message_id IN $messageIds) | YES (real ORM) | ✓ FLOWING  |
| Send form `$isBlocked` flag                  | BlocksTable::isBlocked exists() query                                            | YES (DB exists)  | ✓ FLOWING  |
| Report form `$message` view-var              | MessagesTable->find()->where(id=$messageId)->contain(Inboxes)->first() with ownership re-check | YES | ✓ FLOWING  |
| Account-delete UPDATE                        | UsersTable patchEntity(deleted_at=FrozenTime::now()) saveOrFail                  | YES (real save)  | ✓ FLOWING  |
| Send-page slug resolution                    | InboxesTable::findBySlugOrPrevious with REV-01 deleted_at filter                 | YES (real query) | ✓ FLOWING  |

No hollow props or static fallbacks detected on Phase 4 surfaces.

---

## Behavioral Spot-Checks

| Behavior                                                  | Command                                                                                            | Result                                | Status |
| --------------------------------------------------------- | -------------------------------------------------------------------------------------------------- | ------------------------------------- | ------ |
| Test suite green                                          | `vendor/bin/phpunit`                                                                               | 195 tests / 546 assertions / 0 failures | ✓ PASS |
| Migrations all up                                         | `bin/cake migrations status`                                                                       | 8/8 up incl. AddReporterMessageUnique  | ✓ PASS |
| Phase 4 sentinel tests targeted                           | `vendor/bin/phpunit --filter "testSendReturns404WhenInboxOwnerRetired\|testDeletePostPreservesSenderSnapshots\|testSendPostUnrelatedInboxIgnoresUnrelatedBlocks\|testCreatePostDuplicateRejectedByUniqueConstraint\|testCreateRejectsSelfBlock\|testCreateIdempotentOnDuplicate\|testDeletePostWithoutCheckboxRejected\|testCreatePostInvalidReasonRejected"` | 8/8 pass / 26 assertions               | ✓ PASS |
| All Phase 4 routes registered                             | `bin/cake routes \| grep -E "report\|account\|block\|delete"`                                       | reports:create, messages:open, messages:delete, blocks:delete, blocks:create, account:delete, messages:send | ✓ PASS |
| `messages.deleted_at IS NULL` filter is sole list query   | `grep "Messages.deleted_at IS" src/`                                                               | 1 hit at UsersController.php:85 (no other list query exists) | ✓ PASS |
| `Users.deleted_at IS NULL` REV-01 filter on both branches | `grep "Users.deleted_at IS" src/`                                                                  | 2 hits at InboxesTable.php:154 + :166                | ✓ PASS |
| No `withStatus(501)` stubs in src/                        | `grep -nE "withStatus\(501\)" src/`                                                                | 0 hits                                | ✓ PASS |
| No TODO/FIXME in src/Controller                           | `grep -nE "TODO\|FIXME\|XXX\|HACK" src/Controller src/Model src/Service`                          | 0 hits in production code paths       | ✓ PASS |

---

## Requirements Coverage (9/9 Phase 4 reqs)

| Requirement | Source Plan | Description | Status | Evidence |
| ----------- | ---------- | ----------- | ------ | -------- |
| INBOX-04   | 04-01     | 受け手は送信者(identity)単位でブロックできる | ✓ SATISFIED | BlocksController create/delete + isBlocked + dashboard block-list section + test sentinels |
| INBOX-05   | 04-01     | ブロックされた送信者が同じ inbox に送信しようとするとフォーム上でエラー表示される | ✓ SATISFIED | MessagesController dual-gate (lines 86-91, 105-109) + templates/Messages/send.php banner+disabled |
| MSG-08     | 04-01     | 受け手はメッセージを論理削除できる | ✓ SATISFIED | softDeleteByReceiver + UsersController dashboard filter (sole list query) |
| MOD-01     | 04-02     | 4 カテゴリ通報 + reports 行記録 | ✓ SATISFIED | ReportsController + uk_reports_reporter_message UNIQUE migration + 4-reason ENUM allowlist |
| MOD-02     | 04-02     | 事後レビュー方式、AI 検閲なし | ✓ SATISFIED | ReportsController has zero AI/NG filter calls (pure validate + INSERT); PROJECT.md Out-of-Scope |
| MOD-03     | 04-02     | 退会時も sender snapshot 保持 | ✓ SATISFIED | AccountController only UPDATEs users.deleted_at; testDeletePostPreservesSenderSnapshots passes; InboxesTable REV-01 retired-user 404 filter |
| MOD-04     | 04-01     | グローバル BAN 不在(receiver-scoped block のみ) | ✓ SATISFIED | BlocksTable::isBlocked queries (blocker, blocked) PAIR; testSendPostUnrelatedInboxIgnoresUnrelatedBlocks |
| INFRA-01   | 04-03     | tamabox.emomie.com 稼働 | ⚠️ DOC-VERIFIED, awaits live deploy | LAUNCH-RUNBOOK.md fully drafted (272 lines, Steps 1-6 + rollback); deploy is a human gate |
| INFRA-06   | 04-03     | debug=false 固定 + DebugKit 無効化 | ✓ SATISFIED (code-level) | config/app.php:19 env-driven debug default=false; src/Application.php:70-72 conditional addPlugin; composer.json require-dev placement; LAUNCH-RUNBOOK Step 6 verification gates |

No orphaned requirements.

---

## Anti-Patterns Found

| File                                            | Line     | Pattern                                  | Severity | Impact                                                     |
| ----------------------------------------------- | -------- | ---------------------------------------- | -------- | ---------------------------------------------------------- |
| `src/Console/Installer.php`                     | 203, 233 | "placeholder" string in log message      | ℹ️ Info  | Vendored CakePHP installer console output (not Phase 4 code) |

**Phase 4 production code paths**: zero TODO / FIXME / placeholder / 501-stub patterns. Test fixtures + service classes clean. CakePHP idiomatic `return null` from controller actions is not a stub.

---

## REV Resolution Status

| REV    | Subject                                       | Status     | Code Evidence                                                                                                                            |
| ------ | --------------------------------------------- | ---------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| REV-01 | inboxes.deleted_at column does not exist      | ✓ APPLIED  | InboxesTable::findBySlugOrPrevious uses `Users.deleted_at IS NULL` JOIN filter (lines 154 + 166); AccountController only UPDATEs users.deleted_at, never inboxes |
| REV-02 | token_expires_at is plaintext (not encrypted) | ✓ APPLIED  | Phase 4 does not implement token refresh (REV-03 descope), so REV-02's expires_at_enc → token_expires_at correction is a documentation-level note for future phases |
| REV-03 | Token refresh descoped (no live PDS calls in MVP) | ✓ APPLIED  | STATE.md line 164: Phase 2 sticky #5 = `resolved-as-not-needed-for-MVP`; BlueskyOAuthClient::refreshToken() preserved at src:156-187 for future use; no Phase 4 controller invokes refreshToken |

---

## Human Verification Required

Phase 4 is the **launch milestone**, so its goal achievement explicitly requires:
1. A live tamabox.emomie.com deploy following LAUNCH-RUNBOOK.md (Steps 1-6).
2. Manual smoke walkthrough of all 12 items in MANUAL-SMOKE-CHECKLIST.md.
3. Phase 2 / Phase 3 carry-over human items (3 items: live-AS happy path, browser cookie destroy, handle-change sync).

Until those are exercised by a human against the production URL, **the launch is verified at the code level only**. The decision tree in Step 9 (gates.md) requires `status: human_needed` whenever any human verification items remain — therefore this phase's status is **`human_needed`**, even though all 9 requirements pass code-level verification and the test suite is green.

See the `human_verification:` block in the YAML frontmatter for the full 12-item list with expected outcomes.

---

## Soft-Delete Re-Fetch Path Note (informational, not a gap)

`ReportsController::create` and `MessagesTable::markOpened` look up messages by id without an explicit `Messages.deleted_at IS NULL` filter. This is **NOT** flagged as a gap because:

1. The dashboard list (the only place users see message ids) already filters out soft-deleted rows.
2. To reach `/dashboard/messages/<id>/open` or `/report/<id>` for a soft-deleted message, an attacker would need the message UUID by some other means (history, leaked URL).
3. Even if so reached, ownership re-check still applies (NotFound / Forbidden if not own inbox), and re-opening a soft-deleted message has no user-visible side effect (the dashboard still won't list it). Re-reporting one is harmless (UNIQUE on reporter+message id still applies).

This is documented for future hardening (e.g., a `Messages.deleted_at IS NULL` filter could be added to markOpened/findById in a defense-in-depth pass) but does not block Phase 4 goal achievement.

---

## Verdict

**PASS at code level for all 9 Phase 4 requirements.** All 6 ROADMAP Success Criteria for Phase 4 have corresponding code-level evidence. All key links wired. All artifacts substantive. Test suite is green (195 / 546 / 0). Migration applied. No anti-patterns in production code paths. REV-01 / REV-02 / REV-03 all properly applied or explicitly descoped per CONTEXT.md.

**Status remains `human_needed`** because Phase 4 is the launch milestone — its core goal includes "tamabox.emomie.com で実サイトが稼働" which can only be confirmed by a live deploy + 12-item manual smoke walkthrough (LAUNCH-RUNBOOK + MANUAL-SMOKE-CHECKLIST). Code is ready to deploy; no gap-closure plan needed.

**Recommended next action**: Execute LAUNCH-RUNBOOK.md Steps 1-6 against tamabox.emomie.com, then walk through MANUAL-SMOKE-CHECKLIST.md and record results back into this file (or a follow-up VERIFICATION-LIVE.md). After the smoke walkthrough completes successfully, this phase can be marked **VERIFIED** in STATE.md.

---

*Verified: 2026-04-28T13:50:00Z*
*Verifier: Claude (gsd-verifier)*
