# Phase 4: Moderation & Production Launch — Pattern Map

**Mapped:** 2026-04-28
**Files analyzed:** 24 (creates + modifies + tests + fixtures + migration + runbook)
**Analogs found:** 22 / 24 (2 files have no exact analog — `LAUNCH-RUNBOOK.md` is documentation, `templates/element/block_list.php` partial is a structural extension of dashboard sections)

> **Authority order honored:** CONTEXT `<revisions>` REV-01..REV-03 override D-XX where they conflict. **REV-03 descopes token refresh** — no token refresh files appear in this map. **REV-01 redirects** the 退会 slug-404 path through `InboxesTable::findBySlugOrPrevious()` (`users.deleted_at IS NULL` JOIN), not via a non-existent `inboxes.deleted_at`. **REV-02** is moot because no token refresh code is touched.

---

## File Classification

### New files (Phase 4 creates)

| New File | Role | Data Flow | Closest Analog | Match Quality |
|----------|------|-----------|----------------|---------------|
| `src/Controller/ReportsController.php` | controller | request-response (form) | `src/Controller/InboxesController.php` (settings GET-redirect+POST-save) AND `src/Controller/MessagesController.php::send` (GET-render/POST-process) | role+flow exact (mash of two) |
| `src/Controller/AccountController.php` | controller | request-response (form) | `src/Controller/InboxesController.php::settings` (GET form / POST single-row UPDATE on owned record) | role+flow exact |
| `templates/Reports/create.php` | template | form render | `templates/Messages/send.php` (`<form>` create + textarea + button + welcome lead) | role exact |
| `templates/Account/delete.php` | template | form render | `templates/Inboxes/settings.php` (lightweight wrapper around an element) AND `templates/element/inbox_settings_form.php` (consent-style form) | role exact |
| `templates/element/block_list.php` | template (element/partial) | list render | `templates/element/inbox_settings_form.php` (element called via `$this->element(...)` from dashboard with single context var) | structural exact |
| `config/Migrations/20260428XXXXXX_AddReporterMessageUniqueToReports.php` | migration | DDL idempotent | `config/Migrations/20260427120000_AddSlugPreviousToInboxes.php` (Phase 3 alter migration: `addColumn` + `addIndex` + reversible up/down) | role exact |
| `tests/TestCase/Controller/ReportsControllerTest.php` | test (integration) | request-response | `tests/TestCase/Controller/InboxesControllerTest.php` (POST happy path + flash assertions + auth gate) | role+flow exact |
| `tests/TestCase/Controller/AccountControllerTest.php` | test (integration) | request-response | `tests/TestCase/Controller/InboxesControllerTest.php` (single-row UPDATE pattern + flash) | role+flow exact |
| `LAUNCH-RUNBOOK.md` | runbook (markdown ops doc) | n/a | **NONE in repo** — only `.planning/PROJECT.md`/`.planning/ROADMAP.md` are markdown narratives, but they are project-management docs, not deploy runbooks. Use `04-RESEARCH.md §6 Code Example 3` (post-receive hook) + RESEARCH §Pitfalls 6/7/11/12 as primary source. | no-analog |
| `MANUAL-SMOKE-CHECKLIST.md` | runbook (markdown checklist) | n/a | **NONE** — Phase 2/3 verify-phase produced human_needed lists inline in STATE.md but no dedicated checklist file. Researcher recommends D-35 walkthrough checkboxes (RESEARCH §Recommended Plan Breakdown Plan 04-04 step 4). | no-analog |

### Modified files (Phase 4 edits in place)

| Modified File | Role | Data Flow | Existing Analog Section | Match Quality |
|---------------|------|-----------|--------------------------|---------------|
| `src/Controller/BlocksController.php` | controller | CRUD (replace 501 stub → real INSERT/DELETE) | self (existing 501 stub structure) + `src/Model/Table/UserIdentitiesTable.php` lines 312-316 (DatabaseException catch for UNIQUE) | role+flow exact (replace) |
| `src/Controller/MessagesController.php` | controller | request-response (extend) | self — `send()` action at lines 66-104 (add block check D-05/D-06); add new `delete()` action mirroring existing `open()` action at lines 112-132 (POST-only auth-gated UPDATE on owned message via shared MessagesTable helper) | role+flow exact (in-place extend) |
| `src/Controller/UsersController.php` | controller | CRUD (extend dashboard query + view vars) | self — `dashboard()` lines 42-122 (paginate query at L80-85 gets `'Messages.deleted_at IS' => null` AND/JOIN; new `$blocks` view var fetched via `BlocksTable::find()->where(blocker_user_id=me)->contain('BlockedUsers.UserIdentities')`) | role+flow exact (in-place extend) |
| `src/Model/Table/BlocksTable.php` | model/table | CRUD finder | self — Phase 1 baseline; add `isBlocked(string $blockerId, string $blockedId): bool` finder (RESEARCH Code Example 1) | role exact (extend) |
| `src/Model/Table/MessagesTable.php` | model/table | CRUD soft-delete method | self — `markOpened($messageId, $ownerUserId)` at lines 283-310 (NotFoundException + ForbiddenException + idempotent guard + patchEntity with `accessibleFields` + saveOrFail) is the exact template for `softDeleteByReceiver($messageId, $ownerUserId, string $reason='user_deleted')` | role+flow exact (twin method) |
| `src/Model/Table/InboxesTable.php` | model/table | finder filter (extend) | self — `findBySlugOrPrevious()` at lines 147-168 (REV-01: add `'Users.deleted_at IS' => null` to BOTH where-clauses, since `contain(['Users'])` already JOINs; matches Pitfall 4 / Pattern 5 IS-NULL syntax) | role+flow exact (in-place extend) |
| `templates/Users/dashboard.php` | template | list render (extend) | self — receive list at lines 41-130 (replace inline 通報 form L102-108 with `<a href>` link per UI-SPEC §10; append `.message-row__footer` with 削除 form per UI-SPEC §6; add `<?= $this->element('block_list', ['blocks' => $blocks]) ?>` after settings aside L132-135 per UI-SPEC §3) | role exact (in-place extend) |
| `templates/Messages/send.php` | template | form render (extend) | self — entire file (add error-banner rendering at top + `is-disabled` class + `disabled` attributes when `$isBlocked === true`; existing `is_accepting=false` short-circuit at L36-38 is the structural twin) | role exact (in-place extend) |
| `templates/element/inbox_settings_form.php` | template (element) | form render (append section) | self — append `<fieldset class="settings-form__danger-zone">` with link to `/account/delete` per UI-SPEC §Layouts | role exact (append) |
| `webroot/css/tamabox.css` | stylesheet | append | self — Phase 2 + Phase 3 cumulative (currently 641 lines); append `~150-200 lines` for §1-§9 components (UI-SPEC explicit CSS blocks are copy-paste targets) | role exact (append-only) |
| `tests/TestCase/Controller/BlocksControllerTest.php` | test (integration) | request-response (replace stub assertion) | self — replace `testCreateReturns501Stub` with happy-path tests; new tests follow `InboxesControllerTest::testSettingsPostHappyPathSaves` pattern | role+flow exact (replace) |
| `tests/TestCase/Controller/MessagesControllerTest.php` | test (integration) | request-response (extend) | self — replace `testReportReturns501Stub` (L92-97) when route still passes through `MessagesController::report` (or remove if route moves to `ReportsController`); add `testSendShowsBlockedBanner` / `testDeleteSoftDeletes` / `testSendReturnsNotFoundWhenOwnerRetired` modeled on existing `testSendGetIsAcceptingFalseHidesForm` (L80-87) | role+flow exact (extend) |
| `tests/TestCase/Controller/UsersControllerTest.php` | test (integration) | request-response (extend) | self — add `testDashboardExcludesDeleted` modeled on `testDashboardRendersUnreadAndOpenedMessages` (L72-84); add `testDashboardRendersBlockList` modeled on `testDashboardRendersSettingsForm` (L142-151) | role+flow exact (extend) |
| `tests/Fixture/BlocksFixture.php` | test fixture | data-only | self — single-row baseline (alice→bob block at line 22-27); Phase 4 may add more rows for multi-block list test | role exact (extend) |
| `tests/Fixture/ReportsFixture.php` | test fixture | data-only | self — single-row baseline (alice→msg report at line 22-32); Phase 4 adds row(s) for duplicate-detection test | role exact (extend) |
| `config/routes.php` | config (routes) | DSL append | self — Phase 3 routes block at L99-132 (specific routes BEFORE catch-all `/{slug}`); Phase 4 appends `POST /dashboard/blocks/{id}/delete`, `GET\|POST /report/{id}` (extend method list to include GET, currently POST-only at L114-118), `POST /dashboard/messages/{id}/delete`, `GET\|POST /account/delete` | role+flow exact (extend) |
| `config/.env.example` | config (env template) | doc-only | self — Phase 1/2 wiring; **add** `# DEBUG=false in production` comment + verify `SERVER_SECRET` / `OAUTH_KID` / `TOKEN_ENC_KEY` / `BLUESKY_*` / `DATABASE_URL` already documented (they are) | role exact (doc tweak) |

---

## Pattern Assignments

### `src/Controller/BlocksController.php` (controller, CRUD — replace 501 stub)

**Analog:** `src/Controller/InboxesController.php` (CRUD twin) + `src/Model/Table/UserIdentitiesTable.php:312-316` (DatabaseException catch)

**Imports pattern** — copy from `MessagesController.php` lines 18-22:
```php
namespace App\Controller;

use Cake\Database\Exception\DatabaseException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Utility\Text;
```

**Auth/identity gate pattern** — copy from `InboxesController.php:29-37`:
```php
$identity = $this->Authentication->getIdentity();
if ($identity === null) {
    return $this->redirect('/');
}
$identifier = $identity->getIdentifier();
$userId = is_scalar($identifier) ? (string)$identifier : '';
if ($userId === '') {
    return $this->redirect('/');
}
```

**Method-allow + ownership check** — copy from `MessagesController.php::open` lines 114-123:
```php
$this->request->allowMethod(['post']);
// ownership: blocker_user_id MUST equal current identity (no trust on URL params for delete)
```

**INSERT with UNIQUE-collision idempotency (D-03 ワンクリック + race-safe)** — adapt from `UserIdentitiesTable.php:312-316`:
```php
try {
    $entity = $blocksTable->newEntity([
        'id' => Text::uuid(),
        'blocker_user_id' => $myId,
        'blocked_user_id' => $senderUserId,
    ], ['accessibleFields' => [
        'id' => true,
        'blocker_user_id' => true,
        'blocked_user_id' => true,
    ]]);
    $blocksTable->saveOrFail($entity);
} catch (DatabaseException $e) {
    // uk_blocks_pair already exists — idempotent success per D-03
} catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
    $this->Flash->error(__('ブロックに失敗しました。'));
    return $this->redirect('/dashboard');
}
```

**Flash + redirect pattern** — copy from `InboxesController.php:111-114`:
```php
$this->Flash->success(__('@%s をブロックしました', $handle));  // see UI-SPEC §2 for undo link rendering
return $this->redirect('/dashboard');
```

**Stub-removal protocol** (CRITICAL):
- Remove `'create'` from `allowUnauthenticated()` (BlocksController.php line 35) — Phase 4 INSERTs DB rows, auth required.
- The existing test `BlocksControllerTest::testCreateReturns501Stub` MUST be replaced with happy-path assertions (analog: `OauthControllerCallbackTest` per Plan 02-04 precedent referenced in BlocksController.php docblock lines 17-21).

---

### `src/Controller/ReportsController.php` (controller, CRUD — NEW)

**Analog:** `src/Controller/InboxesController.php` (GET-redirect-or-render + POST-save) + `src/Controller/MessagesController.php::send` lines 66-104 (GET render branch / POST process branch in single action)

**Class skeleton** — copy from `InboxesController.php` lines 1-25 (declare strict_types + namespace + use Response + extend AppController + class docblock listing routes):
```php
<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Database\Exception\DatabaseException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * ReportsController — receiver-side abuse reports (MOD-01 / MOD-02).
 *
 * Routes (config/routes.php Phase 4):
 *   GET|POST /report/{messageId} → create($messageId)
 *
 * Auth: required (default — no allowUnauthenticated calls).
 * CSRF: middleware automatic on POST.
 */
class ReportsController extends AppController
{
```

**GET/POST branch in single action** — copy structure from `MessagesController.php::send` lines 66-104:
```php
public function create(string $messageId): ?Response
{
    // identity gate (analog: InboxesController.php:29-37)
    // ownership verification: $msg->inbox->user_id === $myId (analog: MessagesController.php::open L113-128)

    if ($this->request->is('get')) {
        // pre-check: already reported? early-redirect with flash (D-16)
        $this->set(['message' => $msg]);
        return null; // renders templates/Reports/create.php
    }

    $this->request->allowMethod(['post']);
    // ... validate reason ENUM in [harassment,spam,illegal,other] (RESEARCH Code Example 2)
    // ... if reason==='other' && empty(detail) → flash + redirect /report/{id} (D-11)
    // ... try INSERT; catch DatabaseException for uk_reports_reporter_message UNIQUE (D-12)
    // analog: BlocksController INSERT pattern above
}
```

**postString helper** — copy verbatim from `MessagesController.php:264-273`:
```php
private function postString(string $key): string
{
    $v = $this->request->getData($key);
    return is_string($v) ? $v : '';
}
```

**Validation flash-and-redirect pattern** — copy from `MessagesController.php:217-232`:
```php
$body = $this->postString('body');
$consent = $this->postString('consent');
if ($consent === '') {
    $this->Flash->error(__('送信前に同意チェックボックスにチェックしてください。'));
    return $this->redirect('/' . $inbox->slug);
}
if (mb_strlen($body) > 2000) {
    $this->Flash->error(__('本文は 2000 文字以内で入力してください。'));
    return $this->redirect('/' . $inbox->slug);
}
```
→ adapt for `reason` ENUM check + `detail` length 1000 + `reason==='other'` required (per UI-SPEC §4 validation copy).

**Full canonical body** is RESEARCH §Code Example 2 (lines 783-865 in 04-RESEARCH.md). Reference that example as ground truth.

---

### `src/Controller/AccountController.php` (controller, CRUD — NEW, single-row UPDATE)

**Analog:** `src/Controller/InboxesController.php::settings` (single-row UPDATE on owner-scoped record + GET branch redirects to dashboard, but here GET renders form per D-23 separate page)

**Class skeleton** — copy `InboxesController.php` header + class docblock structure:
```php
<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\BadRequestException;
use Cake\Http\Response;
use Cake\I18n\FrozenTime;

/**
 * AccountController — 退会 (account deletion) flow (MOD-03).
 *
 * Routes (config/routes.php Phase 4):
 *   GET|POST /account/delete → delete()
 *
 * GET → render templates/Account/delete.php (consent + checkbox + submit).
 * POST → verify confirm_delete checkbox → UPDATE users.deleted_at → logout → redirect /.
 *
 * D-24 / REV-01: only `users.deleted_at` is set. `inboxes.deleted_at` does NOT exist.
 * Slug 404 is enforced by InboxesTable::findBySlugOrPrevious WHERE users.deleted_at IS NULL.
 */
class AccountController extends AppController
{
```

**Identity gate + GET render** — copy from `InboxesController.php:29-42`:
```php
public function delete(): ?Response
{
    $identity = $this->Authentication->getIdentity();
    if ($identity === null) {
        return $this->redirect('/');
    }
    $identifier = $identity->getIdentifier();
    $userId = is_scalar($identifier) ? (string)$identifier : '';
    if ($userId === '') {
        return $this->redirect('/');
    }

    if ($this->request->is('get')) {
        return null; // render templates/Account/delete.php
    }
```

**POST checkbox-required guard (D-27)** — pattern is CONTEXT specifics §retired-form lines 212:
```php
$this->request->allowMethod(['post']);
$confirmed = $this->request->getData('confirm_delete');
if (!$confirmed) {
    throw new BadRequestException();
}
```

**UPDATE single field via patchEntity + saveOrFail** — copy structure from `InboxesController.php:86-110`:
```php
/** @var \App\Model\Table\UsersTable $usersTable */
$usersTable = $this->fetchTable('Users');
$user = $usersTable->get($userId);
$patched = $usersTable->patchEntity($user, [
    'deleted_at' => FrozenTime::now(),
], ['accessibleFields' => ['deleted_at' => true]]);
$usersTable->saveOrFail($patched);

$this->Authentication->logout();
$this->Flash->info(__('退会が完了しました。ご利用ありがとうございました。'));
return $this->redirect('/');
```

(`Authentication->logout()` analog: `AuthController.php:95-102`.)

**Full canonical body** is RESEARCH §Pattern 6 lines 472-505. Reference as ground truth.

---

### `src/Controller/MessagesController.php` (controller, modify — extend send + add delete + remove report stub)

**Analog:** self — existing actions in same file are the structural template.

**Block check on send (D-05/D-06 dual-gate)** — RESEARCH Pattern 3 lines 386-413; insertion point is right after `findBySlugOrPrevious` returns at L72-80, before the GET-handling branch at L92-96. Pseudocode:
```php
// AFTER existing findBySlugOrPrevious resolution + slug_previous redirect handling:
$identity = $this->Authentication->getIdentity();
$isBlocked = false;
if ($identity !== null) {
    $myId = (string)$identity->getIdentifier();
    /** @var \App\Model\Table\BlocksTable $blocksTable */
    $blocksTable = $this->fetchTable('Blocks');
    $isBlocked = $blocksTable->isBlocked((string)$inbox->user_id, $myId);
}

// GET branch — pass $isBlocked to template (UI-SPEC §1 banner)
if ($this->request->is('get')) {
    $this->set('isBlocked', $isBlocked);  // template renders banner + disabled form when true
    $this->renderSendForm($inbox, $isAuthenticated, $isOwnInbox);
    return null;
}

// POST branch — defense-in-depth re-check
if ($isBlocked) {
    $this->Flash->error(__('この受信箱には送信できません。'));
    return $this->redirect('/' . $inbox->slug);
}
```

**New `delete($id)` action — EXACT TWIN of existing `open($id)` at lines 112-132**, swap `markOpened` for `softDeleteByReceiver`. Copy `open` verbatim and swap method name + Flash message + redirect:
```php
public function delete(string $id): ?Response
{
    $this->request->allowMethod(['post']);
    $identity = $this->Authentication->getIdentity();
    if ($identity === null) {
        return $this->redirect('/');
    }
    $identifier = $identity->getIdentifier();
    $userId = is_scalar($identifier) ? (string)$identifier : '';
    if ($userId === '') {
        return $this->redirect('/');
    }

    /** @var \App\Model\Table\MessagesTable $messagesTable */
    $messagesTable = $this->fetchTable('Messages');
    // softDeleteByReceiver throws NotFoundException / ForbiddenException — let CakePHP handle.
    $messagesTable->softDeleteByReceiver($id, $userId);

    $this->Flash->success(__('メッセージを削除しました'));
    return $this->redirect('/dashboard');
}
```

**Stub removal: `report` action**
- Phase 4 ROUTES `GET|POST /report/{id}` to a new `ReportsController::create($id)` (RESEARCH Recommendation in §Open Questions; UI-SPEC §4 page).
- Either: (a) DELETE the entire `report` action body (lines 134-145) AND remove `'report'` from `allowUnauthenticated()` (line 57); OR (b) keep it but make it 410 Gone if the routes file is updated to point at ReportsController. **(a) is cleaner — researcher recommendation.**
- Update `MessagesControllerTest::testReportReturns501Stub` (lines 92-97) accordingly: either delete the test or rewrite to assert 404 / route-not-matched.

---

### `src/Controller/UsersController.php` (controller, modify dashboard query)

**Analog:** self — `dashboard()` lines 42-122.

**Pattern 5 (D-20 soft-delete filter)** — extend the paginate query at lines 80-85:
```php
$messages = $this->paginate(
    $messagesTable
        ->find()
        ->where([
            'Messages.inbox_id' => $inbox->id,
            'Messages.deleted_at IS' => null,  // D-20 — note `'Foo IS' => null` syntax (RESEARCH Test Pattern Reference)
        ])
        ->order(['Messages.created_at' => 'DESC'])
);
```

**New view-vars: blocks list + per-message report-status map (D-04 + D-16)** — append before the `$this->set([...])` call at L113:
```php
/** @var \App\Model\Table\BlocksTable $blocksTable */
$blocksTable = $this->fetchTable('Blocks');
$blocks = $blocksTable->find()
    ->where(['Blocks.blocker_user_id' => $userId])
    ->contain(['BlockedUsers' => ['UserIdentities']])  // analog: UsersController.php:60 contain pattern
    ->order(['Blocks.created_at' => 'DESC'])
    ->toArray();

// 通報済 badge map: messageId => bool (D-16)
/** @var \App\Model\Table\ReportsTable $reportsTable */
$reportsTable = $this->fetchTable('Reports');
$messageIds = array_map(fn($m) => (string)$m->id, iterator_to_array($messages));
$reportedSet = [];
if ($messageIds !== []) {
    $reportedSet = $reportsTable->find()
        ->where([
            'Reports.reporter_user_id' => $userId,
            'Reports.message_id IN' => $messageIds,
        ])
        ->all()
        ->reduce(fn($acc, $r) => $acc + [(string)$r->message_id => true], []);
}

$this->set('blocks', $blocks);
$this->set('reportedSet', $reportedSet);
```

(NOTE: planner judgment may instead push `reportedSet` into a `MessagesTable::loadReportedFlagFor()` method, mirroring `markOpened` placement. Researcher recommendation: keep in controller for now, refactor if list rendering grows.)

---

### `src/Model/Table/BlocksTable.php` (model/table, add finder)

**Analog:** self — Phase 1 baseline at lines 28-93. The Table currently has zero custom methods; Phase 4 adds 1.

**New `isBlocked()` finder** — RESEARCH Code Example 1 (lines 758-776). Use `Cake\ORM\Table::exists()` (short-circuit count, no entity hydration):
```php
/**
 * Check whether $blockerId has blocked $blockedId.
 *
 * @param string $blockerId UUID of the receiver who may have created the block.
 * @param string $blockedId UUID of the sender who may be blocked.
 * @return bool true if a blocks row exists for this directional pair.
 */
public function isBlocked(string $blockerId, string $blockedId): bool
{
    return $this->exists([
        'blocker_user_id' => $blockerId,
        'blocked_user_id' => $blockedId,
    ]);
}
```

(`exists()` analog within tamabox: `InboxesTable::findBySlugOrPrevious` uses `->first()` instead. `exists` is the right tool for boolean checks per RESEARCH Pattern 1.)

---

### `src/Model/Table/MessagesTable.php` (model/table, add soft-delete method)

**Analog:** self — `markOpened($messageId, $ownerUserId)` at lines 283-310 is the EXACT TEMPLATE.

**New `softDeleteByReceiver()` method** — copy `markOpened` lines 283-310 verbatim, change:
- Method name + docblock
- Idempotent guard: if `deleted_at !== null` return entity unchanged (mirrors `opened_at !== null` guard L299-301)
- patchEntity payload: `['deleted_at' => FrozenTime::now(), 'deleted_reason' => $reason]`
- accessibleFields: `['deleted_at' => true, 'deleted_reason' => true]` (RESEARCH Pitfall 5 — DO NOT touch `body` / `body_length`)

```php
/**
 * Soft-delete a message by its receiver (MSG-08, D-18).
 *
 * Mirrors markOpened() shape: ownership-checked, idempotent, single-field UPDATE.
 *
 * @param string $messageId UUID of the message.
 * @param string $ownerUserId UUID of the receiver (current authenticated user).
 * @param string $reason Free-form reason literal — D-22 'user_deleted' for receiver delete.
 * @return \App\Model\Entity\Message
 * @throws \Cake\Http\Exception\NotFoundException If message not found.
 * @throws \Cake\Http\Exception\ForbiddenException If message's inbox is not owned by $ownerUserId.
 */
public function softDeleteByReceiver(string $messageId, string $ownerUserId, string $reason = 'user_deleted'): \App\Model\Entity\Message
{
    /** @var \App\Model\Entity\Message|null $msg */
    $msg = $this->find()
        ->where([$this->aliasField('id') => $messageId])
        ->contain(['Inboxes'])
        ->first();
    if ($msg === null) {
        throw new \Cake\Http\Exception\NotFoundException(__('メッセージが見つかりませんでした。'));
    }
    $inbox = $msg->inbox ?? null;
    if ($inbox === null || (string)$inbox->user_id !== $ownerUserId) {
        throw new \Cake\Http\Exception\ForbiddenException(__('このメッセージを削除する権限がありません。'));
    }
    if ($msg->deleted_at !== null) {
        return $msg; // idempotent
    }
    $patched = $this->patchEntity($msg, [
        'deleted_at' => FrozenTime::now(),
        'deleted_reason' => $reason,
    ], ['accessibleFields' => [
        'deleted_at' => true,
        'deleted_reason' => true,
    ]]);
    /** @var \App\Model\Entity\Message $saved */
    $saved = $this->saveOrFail($patched);
    return $saved;
}
```

**Optional: const class for reason literals (RESEARCH Open Question 4)**:
```php
public const DELETED_REASON_USER = 'user_deleted';
public const DELETED_REASON_ADMIN = 'admin_action';
```
Place near top of class body, document in docblock.

---

### `src/Model/Table/InboxesTable.php` (model/table, REV-01 retired-user filter)

**Analog:** self — `findBySlugOrPrevious()` at lines 147-168.

**REV-01 modification** — both `->where()` calls get `'Users.deleted_at IS' => null` added. The existing `contain(['Users'])` at L151/L160 already pulls in the JOIN target, so the WHERE just filters it:
```php
public function findBySlugOrPrevious(string $slug): array
{
    /** @var \App\Model\Entity\Inbox|null $inbox */
    $inbox = $this->find()
        ->contain(['Users'])
        ->where([
            $this->aliasField('slug') => $slug,
            'Users.deleted_at IS' => null,  // REV-01 / D-25
        ])
        ->first();
    if ($inbox !== null) {
        return ['inbox' => $inbox, 'redirect' => false];
    }

    /** @var \App\Model\Entity\Inbox|null $prev */
    $prev = $this->find()
        ->contain(['Users'])
        ->where([
            $this->aliasField('slug_previous') => $slug,
            'Users.deleted_at IS' => null,  // REV-01 / D-25
        ])
        ->first();
    if ($prev !== null) {
        return ['inbox' => $prev, 'redirect' => true];
    }

    throw new NotFoundException(__('受信箱が見つかりませんでした。'));
}
```

**`'Foo IS' => null` syntax confirmed** as the project ORM convention (RESEARCH Test Pattern Reference; matches existing fixtures-baked predicates in tests).

---

### `templates/Reports/create.php` (template, NEW form page)

**Analog:** `templates/Messages/send.php` (form page wrapper + submit button + lead text + h1).

**Page-level skeleton** — adapt `send.php` lines 1-19 (PHP block with @var hints + `$this->assign('title', ...)`):
```php
<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Message $message  passed by ReportsController::create
 *
 * UI-SPEC §4 — Report form page.
 */
$this->assign('title', 'メッセージを通報する');
?>
<div class="report-page">
    <header class="report-page__header">
        <h1>このメッセージを通報する</h1>
        <p class="text-secondary">通報内容は運営が確認します。重複通報はできません。</p>
    </header>
    ...
```

**Form via `$this->Form->create(null, [...])`** — copy from `send.php` lines 40-44:
```php
<?= $this->Form->create(null, [
    'url' => '/report/' . h((string)$message->id),
    'type' => 'post',
    'class' => 'report-form',
]) ?>
    <fieldset class="report-form__reasons">
        <legend>通報の理由を 1 つ選んでください</legend>
        <!-- 4 radios per UI-SPEC §4 -->
    </fieldset>
    <fieldset class="report-form__detail">
        <legend>詳細(その他選択時は必須・最大 1000 文字)</legend>
        <textarea name="detail" maxlength="1000" rows="5"></textarea>
    </fieldset>
    <div class="report-form__actions">
        <button type="submit" class="primary-button">通報を送信する</button>
        <a href="/dashboard" class="button button-clear">キャンセル</a>
    </div>
<?= $this->Form->end() ?>
</div>
```

**XSS safety pattern** — every interpolation uses `h(...)` (analog: `dashboard.php:48` `mb_substr` + `h()` for body preview). Reference UI-SPEC §4 for canonical full DOM.

---

### `templates/Account/delete.php` (template, NEW form page)

**Analog:** `templates/Inboxes/settings.php` (lightweight wrapper around form) + `templates/Messages/send.php` (`<form>` with `consent` checkbox, lines 58-61).

**Page-level skeleton** — copy `settings.php` (full file is the template; just expand body):
```php
<?php
/**
 * @var \App\View\AppView $this
 *
 * UI-SPEC §7 — Account deletion page.
 */
$this->assign('title', '退会の手続き');
?>
<div class="account-delete-page">
    <header class="account-delete-page__header">
        <h1>退会の手続き</h1>
    </header>

    <section class="account-delete-page__notice">
        <p>退会するとあなたの受信箱は使えなくなります。</p>
        <p>あなたが過去に送信したメッセージは、受け手側の画面ではあなたの当時の handle・avatar が記録されたまま残ります(MOD-03)。</p>
        <p>退会後、あなたの slug は他の人に再割り当てされません。</p>
    </section>

    <?= $this->Form->create(null, [
        'url' => '/account/delete',
        'type' => 'post',
        'class' => 'account-delete-form',
    ]) ?>
        <label class="account-delete-form__consent">
            <input type="checkbox" name="confirm_delete" required>
            <span>上記の内容を理解した上で、退会します</span>
        </label>
        <div class="account-delete-form__actions">
            <button type="submit" class="primary-button button-destructive-bg">退会する</button>
            <a href="/dashboard" class="button button-clear">ダッシュボードに戻る</a>
        </div>
    <?= $this->Form->end() ?>
</div>
```

**Required-checkbox pattern** — analog `send.php` line 59: `<input type="checkbox" name="consent" value="1" required>` is the established pattern. UI-SPEC §7 specifies HTML5 `required` + server-side double-check.

---

### `templates/element/block_list.php` (template element, NEW)

**Analog:** `templates/element/inbox_settings_form.php` — same call-site pattern (`$this->element('block_list', ['blocks' => $blocks])` from dashboard).

**Skeleton** (UI-SPEC §3 canonical DOM block):
```php
<?php
/**
 * @var \App\View\AppView $this
 * @var array<int, \App\Model\Entity\Block> $blocks  Each block contains BlockedUser->user_identity
 *
 * UI-SPEC §3 — Dashboard block list section.
 * Consumed by templates/Users/dashboard.php.
 */
?>
<section class="block-list">
    <h2>ブロック中ユーザー</h2>
    <?php if (count($blocks) === 0): ?>
        <p class="text-secondary block-list__empty">ブロックしているユーザーはいません</p>
    <?php else: ?>
        <ul class="block-list__items">
            <?php foreach ($blocks as $block): ?>
                <li class="block-list__row">
                    <?php
                    $blocked = $block->blocked_user ?? null;
                    $identity = ($blocked !== null && isset($blocked->user_identity)) ? $blocked->user_identity : null;
                    $handle = $identity !== null ? (string)$identity->handle_cached : '';
                    $avatar = ($identity !== null && $identity->avatar_url_cached !== null)
                        ? (string)$identity->avatar_url_cached
                        : '/img/default-avatar.svg';
                    ?>
                    <img class="block-list__avatar"
                         src="<?= h($avatar) ?>"
                         alt=""
                         width="24" height="24"
                         onerror="this.src='/img/default-avatar.svg'">
                    <span class="block-list__handle">@<?= h($handle) ?></span>
                    <?= $this->Form->create(null, [
                        'url' => '/dashboard/blocks/' . h((string)$block->id) . '/delete',
                        'type' => 'post',
                        'class' => 'inline block-list__unblock-form',
                    ]) ?>
                        <button type="submit" class="button button-clear">解除</button>
                    <?= $this->Form->end() ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
```

**Avatar fallback pattern** — copy verbatim from `dashboard.php:84-94` (`onerror="this.src='/img/default-avatar.svg'"` + width/height + alt).

---

### `templates/Users/dashboard.php` (template, in-place modifications)

**Per UI-SPEC §10 + §6 + §3** — three surgical edits:

1. **Replace the inline 通報 form** at lines 102-108 with a link to the report page:
   ```php
   <a href="/report/<?= h((string)$msg->id) ?>" class="button button-clear button-destructive">通報する</a>
   ```

2. **Append `.message-row__footer`** after the SSR reveal block (after line 121 closing `<?php endif; ?>`, before `</div>` at L122):
   ```php
   <div class="message-row__footer">
       <?= $this->Form->create(null, [
           'url' => '/dashboard/messages/' . h((string)$msg->id) . '/delete',
           'type' => 'post',
           'class' => 'inline',
           'onsubmit' => "return confirm('このメッセージを削除しますか?(削除後は元に戻せません)');",
       ]) ?>
           <button type="submit" class="button button-clear button-destructive">削除</button>
       <?= $this->Form->end() ?>
       <?php if (isset($reportedSet[(string)$msg->id])): ?>
           <span class="report-badge" aria-label="このメッセージは通報済みです">通報済</span>
       <?php else: ?>
           <a href="/report/<?= h((string)$msg->id) ?>" class="button button-clear button-destructive">通報する</a>
       <?php endif; ?>
   </div>
   ```
   (NOTE: this absorbs the inline-form replacement from edit #1 — UI-SPEC §6 places these together. Pick one structural placement.)

3. **Render the block list element** after the settings aside at lines 132-135:
   ```php
   <?= $this->element('block_list', ['blocks' => $blocks]) ?>
   ```

---

### `templates/Messages/send.php` (template, in-place block-banner extension)

**Per UI-SPEC §1 + §9** — add error banner above form when `$isBlocked === true`, mark form `is-disabled`:

Insert after `<header class="inbox-header">` (line 23) and before `<?php if ($isOwnInbox): ?>` (L25):
```php
<?php if (isset($isBlocked) && $isBlocked === true): ?>
    <div class="error-banner" role="status">この受信箱には送信できません</div>
<?php endif; ?>
```

In the form, add `is-disabled` class + native `disabled` attributes when `$isBlocked === true`:
- `class="send-form<?= (isset($isBlocked) && $isBlocked) ? ' is-disabled' : '' ?>"` (line 43)
- `<textarea name="body" <?= ... ? 'disabled' : '' ?> ...>` (line 46)
- `<input type="checkbox" name="consent" value="1" required <?= ... ? 'disabled' : '' ?>>` (line 59)
- `<button type="submit" class="..." <?= ... ? 'disabled' : '' ?>>` (line 64/66)

(Analog for conditional disabled-attribute rendering: `dashboard.php:60` uses `<?= $isUnread ? '' : 'open' ?>` — same conditional-attr pattern.)

---

### `templates/element/inbox_settings_form.php` (template element, append danger-zone)

**Per UI-SPEC §Layouts** — append at end of file before `</form>`:
```php
<fieldset class="settings-form__danger-zone">
    <legend>退会</legend>
    <p class="text-secondary">アカウントを削除すると元に戻せません。</p>
    <a href="/account/delete" class="button button-clear button-destructive">退会の手続きへ</a>
</fieldset>
```

---

### `webroot/css/tamabox.css` (stylesheet, append-only)

**Analog:** Phase 2/3 cumulative (currently 641 lines). Phase 4 appends `~150-200 lines` per UI-SPEC §1-§9, plus `.settings-form__danger-zone` block.

**Specific block targets** — copy each CSS rule verbatim from UI-SPEC:
- §1 Error Banner: `.error-banner { ... }` + `.send-form.is-disabled, .consent-label.is-disabled { ... }`
- §2 Undo Link: `.undo-link { ... }`
- §3 Block List: `.block-list*` (~10 rules + media query)
- §4 Report Form: `.report-page*` + `.report-form*` (~9 rules)
- §5 Report Badge: `.report-badge { ... }`
- §6 Soft-Delete Footer: `.message-row__footer { ... }`
- §7 Account Delete Page: `.account-delete-page*` + `.account-delete-form*` + `.button-destructive-bg { ... }`
- §Layouts danger-zone: `.settings-form__danger-zone { ... }`

**Token reuse only** — no new `--space-*` / `--color-*` introduced (UI-SPEC explicit constraint).

---

### `config/Migrations/20260428XXXXXX_AddReporterMessageUniqueToReports.php` (migration, NEW)

**Analog:** `config/Migrations/20260427120000_AddSlugPreviousToInboxes.php` (Phase 3 alter migration).

**Skeleton** — copy structure of `AddSlugPreviousToInboxes` lines 1-56 (extends `AbstractMigration`, up/down pair, addIndex with named index):
```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * AddReporterMessageUniqueToReports — Phase 4 D-12 (duplicate-report prevention).
 *
 * Adds a composite UNIQUE index uk_reports_reporter_message on (reporter_user_id, message_id)
 * to enforce 1 report per (reporter, message). MySQL 8.0 NULL-in-UNIQUE allows multiple
 * NULLs (RESEARCH Pitfall 3 — intentional for retired-reporter SET NULL semantics).
 *
 * No CHECK constraint, no raw SQL — Phinx 0.13 addIndex API suffices.
 */
class AddReporterMessageUniqueToReports extends AbstractMigration
{
    public function up(): void
    {
        $this->table('reports')
            ->addIndex(
                ['reporter_user_id', 'message_id'],
                [
                    'unique' => true,
                    'name'   => 'uk_reports_reporter_message',
                ]
            )
            ->update();
    }

    public function down(): void
    {
        $this->table('reports')
            ->removeIndexByName('uk_reports_reporter_message')
            ->update();
    }
}
```

**Naming convention** — `uk_*` prefix matches existing `uk_blocks_pair` (CreateBlocks migration L72) and `idx_reports_*` (CreateReports migration L119, L125). Confirmed via DB-SCHEMA.md v0.2 convention.

---

### `tests/TestCase/Controller/BlocksControllerTest.php` (test, replace stub)

**Analog:** existing self-file (lines 1-49) for fixtures + structure; `InboxesControllerTest.php` for happy-path/auth-gate/flash assertions.

**Fixture loadout — copy verbatim from existing self-file lines 22-30** (untyped `$fixtures` per Phase 2 sticky #1):
```php
/**
 * @var array<int, string>
 */
protected $fixtures = [
    'app.Users',
    'app.UserIdentities',
    'app.Inboxes',
    'app.Messages',
    'app.Blocks',
    'app.Reports',
];
```

**setUp + login helper** — copy from `InboxesControllerTest.php:33-47`:
```php
protected function setUp(): void
{
    parent::setUp();
    $this->enableRetainFlashMessages();
}

private function loginAsAlice(): void
{
    $alice = $this->fetchTable('Users')->get('11111111-1111-1111-1111-111111111111', ['contain' => ['UserIdentities']]);
    $this->session(['Auth' => $alice]);
}
```

**Happy-path POST + redirect + DB-state assertion** — copy from `InboxesControllerTest::testSettingsPostHappyPathSaves` lines 76-92:
```php
public function testCreateBlockHappyPath(): void
{
    $this->enableCsrfToken();
    $this->loginAsAlice();
    $this->post('/block/22222222-2222-2222-2222-222222222222');
    $this->assertResponseCode(302);
    $this->assertRedirectContains('/dashboard');
    $exists = $this->fetchTable('Blocks')->exists([
        'blocker_user_id' => '11111111-1111-1111-1111-111111111111',
        'blocked_user_id' => '22222222-2222-2222-2222-222222222222',
    ]);
    $this->assertTrue($exists);
    // (BlocksFixture pre-loads this exact pair, so the test data setup must vary —
    //  e.g. block charlie 33333333-... instead, OR start from clean blocks fixture.)
}
```

**Idempotency test** — adapt `testCreateReturns501Stub` shape to assert second-block-of-same-pair returns 302 (not 5xx):
```php
public function testCreateBlockIdempotentOnDuplicate(): void
{
    // BlocksFixture pre-loads alice→bob block; second POST must succeed silently.
    $this->enableCsrfToken();
    $this->loginAsAlice();
    $this->post('/block/22222222-2222-2222-2222-222222222222');
    $this->assertResponseCode(302); // not 500 — DatabaseException is caught
}
```

**REPLACE the existing `testCreateReturns501Stub` method entirely** (it asserts the 501 stub which Phase 4 removes).

---

### `tests/TestCase/Controller/ReportsControllerTest.php` (test, NEW)

**Analog:** `tests/TestCase/Controller/InboxesControllerTest.php` (full file structure).

**File-level skeleton** — copy `InboxesControllerTest.php` lines 1-47 verbatim, swap class name + add `loginAsBob` if needed (analog: `MessagesControllerTest.php:393-396`):
```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class ReportsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @var array<int, string>
     */
    protected $fixtures = [
        'app.Users',
        'app.UserIdentities',
        'app.Inboxes',
        'app.Messages',
        'app.Blocks',
        'app.Reports',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableRetainFlashMessages();
    }
    // ... loginAsAlice helper
```

**Test cases (RESEARCH Phase Requirements → Test Map)**:
- `testCreateGetRendersForm` — analog: `InboxesControllerTest::testSettingsGetRedirectsToDashboard` (but inverted — GET renders, doesn't redirect)
- `testCreateGetUnauthenticatedRedirects` — analog: `InboxesControllerTest::testSettingsGetUnauthenticatedRedirects` (line 65-69)
- `testCreatePostHappyPath` — analog: `InboxesControllerTest::testSettingsPostHappyPathSaves` (L76-92), assert `Reports->exists([reporter, message])`
- `testCreatePostReasonOtherWithoutDetailRejected` — analog: `InboxesControllerTest::testSettingsPostOver100Rejected` (L132-147), assert flash + no INSERT
- `testCreatePostDuplicateRejected` — pre-load fixture row matching (alice, message), assert 302 + flash error
- `testCreatePostInvalidReasonRejected` — analog: `testSettingsPostNonIntegerRejected` (L166-175)

---

### `tests/TestCase/Controller/AccountControllerTest.php` (test, NEW)

**Analog:** `tests/TestCase/Controller/InboxesControllerTest.php` (full file structure) + `tests/TestCase/Controller/AuthControllerTest.php` (logout-related assertions like `testLogoutWithGetDoesNotDestroySession` lines 56-67).

**File-level skeleton + login helper** — same as ReportsControllerTest above (copy InboxesControllerTest scaffolding).

**Test cases**:
- `testDeleteGetRendersForm` — assert `assertResponseContains('退会の手続き')`
- `testDeleteGetUnauthenticatedRedirects` — analog: `InboxesControllerTest::testSettingsGetUnauthenticatedRedirects`
- `testDeletePostHappyPathSetsDeletedAt` — analog: `InboxesControllerTest::testSettingsPostHappyPathSaves`, assert `users.deleted_at !== null` + flash + redirect `/`
- `testDeletePostWithoutCheckboxRejected` — assert 400 BadRequestException response
- `testDeletePostDoesNotTouchInboxesDeletedAt` — REV-01 sentinel: confirm no `inboxes.deleted_at` field exists / no UPDATE attempted (this is mostly a code-review gate, but a test asserting the inbox row is unchanged after delete is valuable)
- `testDeletePostPreservesMessages` — MOD-03 sentinel: `messages` rows authored by retiring user remain in DB with sender_*_snapshot intact

---

### Test fixture extensions

**`tests/Fixture/BlocksFixture.php`** — current single record (alice→bob block, line 22-27). Phase 4 may need to:
- Remove the existing block in cases where the test wants to create from scratch (or use `charlie` as the target).
- Add a second blocker (bob blocks alice) to test that block is receiver-scoped (MOD-04).

**`tests/Fixture/ReportsFixture.php`** — current single record (alice reports msg, line 22-32). Phase 4 needs:
- A duplicate-fixture row for the duplicate-detection test, OR start from clean.
- A row where `reporter_user_id = NULL` (D-12 NULL-in-UNIQUE semantics; RESEARCH Pitfall 3).

**Conventions**: untyped `$fixtures` (Phase 2 sticky #1), schema-valid records only (Phase 1 D-15), no use of CakePHP bake (RESEARCH Anti-Patterns).

---

### `config/routes.php` (config, append routes)

**Analog:** Phase 3 routes block at lines 99-132.

**Phase 4 additions** — preserve "specific routes BEFORE catch-all `/{slug}`" rule (line 126 comment confirms ordering criticality). Insert AFTER line 119 (existing `/report/{id}` POST stub) and BEFORE line 125 (existing `/block/{senderUserId}` POST stub):

```php
// Phase 4 — moderation lifecycle routes.

// GET|POST /report/{id} — Phase 4 ReportsController::create (replaces Phase 3 501 POST stub).
// REPLACE the existing connect at L114-118 (which routes to Messages::report) with:
$builder->connect(
    '/report/{id}',
    ['controller' => 'Reports', 'action' => 'create'],
    ['pass' => ['id'], 'id' => '[0-9a-f-]{36}']
)->setMethods(['GET', 'POST']);

// POST /dashboard/messages/{id}/delete — soft-delete (D-18 / MSG-08).
$builder->connect(
    '/dashboard/messages/{id}/delete',
    ['controller' => 'Messages', 'action' => 'delete'],
    ['pass' => ['id'], 'id' => '[0-9a-f-]{36}']
)->setMethods(['POST']);

// POST /dashboard/blocks/{id}/delete — unblock by block-row id (D-04).
$builder->connect(
    '/dashboard/blocks/{id}/delete',
    ['controller' => 'Blocks', 'action' => 'delete'],
    ['pass' => ['id'], 'id' => '[0-9a-f-]{36}']
)->setMethods(['POST']);

// GET|POST /account/delete — 退会 (D-23 / MOD-03).
$builder->connect(
    '/account/delete',
    ['controller' => 'Account', 'action' => 'delete']
)->setMethods(['GET', 'POST']);
```

**Pattern for UUID `pass` constraint** — copy from `/dashboard/messages/{id}/open` definition at lines 100-104:
```php
['pass' => ['id'], 'id' => '[0-9a-f-]{36}']
```

---

### `LAUNCH-RUNBOOK.md` (NEW, no analog in repo)

**No analog file exists** — write fresh based on:
- RESEARCH §6 Code Example 3 (post-receive hook) — drop verbatim into runbook.
- RESEARCH §Pitfalls 6 (PHP path), 7 (cache:clear after migrate), 11 (DebugKit auto-exclude verification), 12 (`$HOME` paths) — each becomes a "Verify" checkbox.
- CONTEXT D-37 ordered 6-step deploy — becomes the runbook's main outline.
- CONTEXT D-32, D-33, D-34, D-35, D-36 — referenced as inline rationale.

**Suggested structure** (planner judgment):
```markdown
# tamabox Production Launch Runbook

Target: tamabox.emomie.com (Lolipop high-speed plan)

## Prerequisites
- [ ] Lolipop SSH credentials in 1Password
- [ ] ES256 keypair generated locally (RESEARCH Pitfall 12 paths)
- [ ] config/.env populated for production (DEBUG=false, DATABASE_URL, ...)

## Step 1 — Initial Lolipop bare-repo setup (one-time)
... (D-37 step 1)

## Step 2 — SSH-place secrets (one-time)
... (D-33)

## Step 3 — Push main + observe post-receive hook
... (D-32)

## Step 4 — Manual migration + cache clear
... (D-34, RESEARCH Pitfall 7)

## Step 5 — Bluesky AS client-metadata.json registration
... (D-37 step 5)

## Step 6 — Manual smoke test (see MANUAL-SMOKE-CHECKLIST.md)
... (D-35, D-39)

## Rollback procedure
... (planner judgment — git checkout previous commit + cache clear)
```

**Stylistic match** — markdown headings + checklists, follow `.planning/PROJECT.md` / `.planning/ROADMAP.md` style (h1/h2/h3 hierarchy, Japanese OK).

---

### `MANUAL-SMOKE-CHECKLIST.md` (NEW, no analog in repo)

**No analog file exists**. Skeleton based on CONTEXT D-35 walkthrough order (CONTEXT specifics §D-35 lines 210):
```markdown
# Phase 4 Manual Smoke Test — Production Walkthrough

Date: ____________
Operator: ____________

## Phase 4 new flows
- [ ] (1) Sign up via real Bluesky account A → see /<a-slug> → confirm dashboard renders
- [ ] (2) From device 2, login as B → send message to /<a-slug>
- [ ] (3) Switch to A → /dashboard → open message → verify SSR hit/miss演出
- [ ] (4) On hit, click ブロック → confirm Flash with (取り消し) link
- [ ] (5) From B, retry POST /<a-slug> → verify error banner (D-06)
- [ ] (6) From A, click 通報する on the message → fill report form → submit → verify reports row via SSH SQL
- [ ] (7) From A, click 削除 in expanded message → confirm() → verify message disappears
- [ ] (8) From A, settings → 退会 → confirm checkbox + submit → verify /<a-slug> returns 404 (REV-01)
- [ ] (9) From B's perspective, verify A's past sent-messages still display sender snapshot (MOD-03 strict, D-26)

## Phase 2/3 carried-over human items
- [ ] (10) Live Bluesky AS handshake (Phase 2 verifier deferred)
- [ ] (11) Browser cookie destroy on logout (Phase 2 verifier deferred)
- [ ] (12) Handle-change sync via second login (Phase 3 D-39)
```

---

## Shared Patterns

### Authentication / Identity Resolution

**Source:** `src/Controller/InboxesController.php:29-37` (canonical 4-line gate; identical pattern in `MessagesController::open` L114-123 and `UsersController::dashboard` L43-54).

**Apply to:** `BlocksController::create`, `BlocksController::delete`, `MessagesController::delete` (NEW), `ReportsController::create`, `AccountController::delete`.

```php
$identity = $this->Authentication->getIdentity();
if ($identity === null) {
    return $this->redirect('/');
}
$identifier = $identity->getIdentifier();
$userId = is_scalar($identifier) ? (string)$identifier : '';
if ($userId === '') {
    return $this->redirect('/');
}
```

**Phase 4 NEW class — `AccountController` is the FIRST instance of a dedicated user-account-modification controller** (Phase 2 `AuthController` does identity-creation; Phase 3 `InboxesController` does inbox-state mutation; `AccountController` is novel). The pattern itself is copy-paste from existing controllers.

---

### CSRF Protection

**Source:** Auto-applied via `CsrfProtectionMiddleware` (Phase 2 wired in `src/Application.php`). All POST routes are CSRF-protected by default — no per-controller code needed.

**Apply to:** All Phase 4 POST routes (`/block/{id}`, `/dashboard/blocks/{id}/delete`, `/report/{id}`, `/dashboard/messages/{id}/delete`, `/account/delete`).

**Test enable pattern** — `$this->enableCsrfToken()` in test method (analog: `InboxesControllerTest::testSettingsPostHappyPathSaves` line 78; same in every POST integration test).

---

### Flash Messaging

**Source:** `src/Controller/InboxesController.php:53, 64, 70, 105, 112` (Flash->error / Flash->success usage).

**Apply to:** All Phase 4 controllers — mapping to UI-SPEC §Copywriting Contract:
- `Flash->success(__('@%s をブロックしました', $handle))` — block create (D-03; UI-SPEC requires `(取り消し)` link rendered as inline anchor in flash body — see UI-SPEC §2 for custom flash element variant)
- `Flash->success(__('@%s のブロックを解除しました', $handle))` — unblock
- `Flash->success(__('通報を送信しました。確認まで時間がかかる場合があります。'))` — report success
- `Flash->error(__('このメッセージは既に通報済みです。'))` — duplicate report
- `Flash->success(__('メッセージを削除しました'))` — soft-delete
- `Flash->info(__('退会が完了しました。ご利用ありがとうございました。'))` — 退会 (NOTE: `info` not `success` per UI-SPEC §Copywriting tone)
- `Flash->error(__('セッションが切れました。再度ログインしてください'))` — N/A in Phase 4 (REV-03 descopes token refresh; UI-SPEC §8 still documents this for future)

**Custom Flash element for block-create undo link** — UI-SPEC §2 specifies the success message body contains an inline `<a class="undo-link">`. Phase 2/3 use the default `templates/element/flash/success.php` element which `h()`-escapes the body. To render unescaped HTML safely, planner judgment: either (a) create a new Flash element (`templates/element/flash/block_success.php`) called via `Flash->success(..., ['element' => 'block_success'])`, or (b) emit the link as a separate `Flash->info()` after redirect. Option (a) is closer to UI-SPEC intent.

---

### `'Foo IS' => null` ORM Predicate Syntax

**Source:** RESEARCH Test Pattern Reference + project convention. Existing usage: not yet present in codebase (Phase 4 introduces it).

**Apply to:**
- `UsersController::dashboard` paginate query: `'Messages.deleted_at IS' => null`
- `InboxesTable::findBySlugOrPrevious` (REV-01): `'Users.deleted_at IS' => null`
- Test pre-conditions: `Reports->exists(['Reports.reporter_user_id IS' => null, 'Reports.message_id' => $msgId])` for the multi-NULL-allowed sentinel.

---

### `DatabaseException` Catch for UNIQUE Race-Safety

**Source:** `src/Model/Table/UserIdentitiesTable.php:312-316` (Phase 2 production-pattern):
```php
} catch (DatabaseException $e) {
    // T-02-04-10: UNIQUE violation (provider+did race) or other integrity error.
    throw new RuntimeException('Identity upsert failed: database constraint violation.', 0, $e);
}
```

**Apply to:**
- `BlocksController::create` — catch on `uk_blocks_pair`; **idempotent silent success** (per D-03; RESEARCH Pattern 1).
- `ReportsController::create` — catch on `uk_reports_reporter_message`; **flash error + redirect** (per D-12; RESEARCH Pattern 2 / Code Example 2).

**Difference from `PersistenceFailedException`:** `PersistenceFailedException` is for validation/save errors at the ORM level (caught separately); `DatabaseException` surfaces DB-level constraint violations directly. Both should be caught with distinct branches per the analog at `InboxesTable.php:221-231`.

---

### `accessibleFields` Whitelist on patchEntity / newEntity

**Source:** Universal across `MessagesTable.php:248-262`, `InboxesTable.php:211-217`, `InboxesController.php:90-94`, `MessagesTable.php:303-305`. Every save in this codebase uses an explicit whitelist.

**Apply to:** All Phase 4 INSERT/UPDATE operations:
- BlocksController::create → `['id' => true, 'blocker_user_id' => true, 'blocked_user_id' => true]`
- ReportsController::create → `['id', 'message_id', 'reporter_user_id', 'reason', 'detail', 'status']`
- MessagesTable::softDeleteByReceiver → `['deleted_at' => true, 'deleted_reason' => true]` (RESEARCH Pitfall 5 — DO NOT include `body` / `body_length`)
- AccountController::delete → `['deleted_at' => true]`

---

### UUID Primary Key Generation

**Source:** `src/Model/Table/InboxesTable.php:206` (`'id' => Text::uuid()`), `MessagesTable.php:221` (`Text::uuid()` returned then assigned to entity), `UserIdentitiesTable.php:283` similar.

**Apply to:** `BlocksController::create` and `ReportsController::create` (both INSERT into UUID-PK tables).

```php
use Cake\Utility\Text;
// ...
$entity = $blocksTable->newEntity([
    'id' => Text::uuid(),
    // ...
]);
```

---

### `fetchTable` (Controller-Only Helper)

**Source:** Used universally in Phase 2/3 controllers — `MessagesController.php:70, 126, 236`, `InboxesController.php:47, 49`, `UsersController.php:57, 64, 78`.

**Apply to:** All Phase 4 controllers. **Avoid** `TableRegistry::getTableLocator()->get(...)` — that's the service-layer pattern (`MessagesTable.php:199`) and should not appear in controllers (RESEARCH Pitfall 8).

---

### Redirect Pattern + Anchor Hashes

**Source:** `MessagesController::open` L131 redirects with `#msg-` anchor. `OauthController::callback` L213-217 redirects with `?restored=1` query param. `InboxesController::settings` L113 redirects to `/dashboard`.

**Apply to:** Block create — redirect to `/dashboard` (success). Soft-delete — redirect to `/dashboard` (no anchor; row is gone). Account delete — redirect to `/` (post-logout). Report submit — redirect to `/dashboard` (success), or back to `/report/{id}` on inline-validation failure.

---

### `Cake\ORM\Table::exists()` (Boolean Lookup)

**Source:** RESEARCH Pattern 3 establishes this as the idiomatic predicate (no entity hydration overhead). Not yet used in tamabox codebase.

**Apply to:**
- `BlocksTable::isBlocked()` (NEW finder).
- `ReportsTable` pre-check inside `ReportsController::create` GET branch (D-16: redirect early if already reported).
- Tests asserting DB state (`$this->fetchTable('Blocks')->exists([...])`).

---

### Test Authentication Session Pattern

**Source:** `MessagesControllerTest.php:393-396` — minimal `['Auth' => ['id' => uuid]]`:
```php
private function loginAsBob(): void
{
    $this->session(['Auth' => ['id' => '22222222-2222-2222-2222-222222222222']]);
}
```

**OR** `InboxesControllerTest.php:42-47` / `UsersControllerTest.php:42-47` — full entity:
```php
private function loginAsAlice(): void
{
    $alice = $this->fetchTable('Users')->get('11111111-1111-1111-1111-111111111111', ['contain' => ['UserIdentities']]);
    $this->session(['Auth' => $alice]);
}
```

**Apply to:** All Phase 4 integration tests. The full-entity form is needed when the controller reads `$identity->getOriginalData()->user_identity` (e.g., dashboard.php block-list rendering); the minimal form suffices when only the user_id is needed.

---

### `enableRetainFlashMessages()` + Flash assertion

**Source:** Universal in test setUp — `InboxesControllerTest.php:36`, `MessagesControllerTest.php:40`, `UsersControllerTest.php:36`, `AuthControllerTest.php:40`.

**Pattern** — `InboxesControllerTest.php:140-143`:
```php
$flash = $this->_requestSession->read('Flash.flash');
$this->assertIsArray($flash);
$this->assertMatchesRegularExpression('/0〜100/', (string)$flash[0]['message']);
```

**Apply to:** All Phase 4 integration tests asserting flash after redirect.

---

### Untyped `$fixtures` Property

**Source:** Phase 2 sticky #1 (referenced in `BlocksControllerTest.php:14-15`). Every test file has:
```php
/**
 * @var array<int, string>
 */
protected $fixtures = [
    'app.Users',
    'app.UserIdentities',
    'app.Inboxes',
    'app.Messages',
    'app.Blocks',
    'app.Reports',
];
```

**Apply to:** New `ReportsControllerTest.php`, `AccountControllerTest.php`. NEVER use `protected array $fixtures` (PHP fatal — collides with parent class property type).

---

## No Analog Found

Files where Phase 4 introduces a structurally novel pattern. Planner should rely on RESEARCH.md / UI-SPEC.md as ground truth for these:

| File | Role | Data Flow | Reason / Source |
|------|------|-----------|-----------------|
| `LAUNCH-RUNBOOK.md` | runbook (md) | n/a | No deploy runbook exists in repo. Use RESEARCH §6 Code Example 3 + Pitfalls 6/7/11/12 + CONTEXT D-37 step ordering. Style after `.planning/PROJECT.md` markdown heading conventions. |
| `MANUAL-SMOKE-CHECKLIST.md` | checklist (md) | n/a | No checklist file exists. Skeleton from CONTEXT specifics §D-35 walkthrough order + RESEARCH §Recommended Plan Breakdown Plan 04-04. |

**Phase 4 firsts (existing pattern but first concrete instance):**

- `AccountController` is the first dedicated user-account-management controller (vs Phase 2 `AuthController` which is identity-creation, Phase 3 `InboxesController` which is inbox-mutation). Pattern itself is identical to `InboxesController::settings` — first instance of "destructive single-row UPDATE on owner-scoped record + logout + redirect to public page".
- `templates/element/block_list.php` is the second `templates/element/*.php` partial (first was `inbox_settings_form.php`). Element call-site convention is established.
- The `<form onsubmit="return confirm(...)">` inline-JS confirm pattern (UI-SPEC §6) is novel for Phase 4 — analog is `inbox_settings_form.php:62-77` which uses event-listener-based `confirm()` (more JS, but server-side gate is in both cases canonical).
- The `.button-destructive-bg` CSS class (`background-color: var(--color-error)`) is novel for Phase 4 — UI-SPEC §7 explicitly creates this for 退会 button only. Phase 2/3 destructive buttons used `--color-error` text only.
- `'Foo IS' => null` ORM predicate is first-time use in tamabox source code (only documented in research notes prior).
- Composite UNIQUE migration with `addIndex(unique=true)` + Phinx `down()` reversibility for an alter-table (`uk_reports_reporter_message`) — Phase 1 had unique indexes inside `create()` calls; Phase 3's `AddSlugPreviousToInboxes` is the closest alter-pattern but adds a column not just an index.

---

## Metadata

**Analog search scope:**
- `src/Controller/` (10 files)
- `src/Model/Table/` (6 files)
- `templates/` (6 subdirectories incl. element/)
- `config/Migrations/` (7 files)
- `config/routes.php`
- `tests/TestCase/Controller/` (8 test files)
- `tests/Fixture/` (6 fixture files + keys/)
- `webroot/css/tamabox.css`
- `composer.json` / `config/.env.example`

**Files scanned:** ~50 source files (Read calls), full file content for the 22 analogs cited.

**Pattern extraction date:** 2026-04-28

**Authority order applied throughout:** CONTEXT `<revisions>` REV-01..REV-03 > CONTEXT `<decisions>` D-XX > RESEARCH.md patterns > UI-SPEC.md DOM/CSS contracts. Where REV-03 descopes token refresh, all D-28..D-31 references are deliberately omitted. Where REV-01 redirects 退会 slug-404 to `users.deleted_at IS NULL` JOIN, all `inboxes.deleted_at` references are deliberately omitted. REV-02 has no Phase 4 effect because no token-encrypted column is touched.
