# Phase 4: Moderation & Production Launch — Research

**Researched:** 2026-04-28
**Domain:** CakePHP 4.5 moderation CRUD + Bluesky AT Protocol token refresh + Lolipop shared-hosting deploy
**Confidence:** HIGH (Phase 1-3 codebase + Phase 2 OAuth foundation already verified; refresh spec confirmed against atproto.com/specs/oauth)

---

## Summary

Phase 4 is structurally a **fill-in-the-blanks plan** rather than a research-heavy plan. Three of the four research areas — moderation CRUD, DB integrity, and validation strategy — are direct extensions of Phase 2/3 patterns already locked in the codebase. The fourth area — **Bluesky token refresh** — is a defer from Phase 2 sticky-note #5, but the actual `BlueskyOAuthClient::refreshToken()` method body is **already implemented** (`src/Service/OAuth/Bluesky/BlueskyOAuthClient.php:156-187`); what's missing is **(a) the call-site wiring** in `UserIdentitiesTable::upsertBlueskyIdentity()` and **(b) integration tests** with `Client::addMockResponse()`. Lolipop deploy is operational, not engineering — it's a sequence of SSH commands captured as a runbook.

**Two CONTEXT.md inaccuracies surfaced during research that the planner MUST address:**

1. **D-24 mentions `inboxes.deleted_at`** — this column does NOT exist in the schema (`config/Migrations/20260422120003_CreateInboxes.php` + `/home/claude/projects/ssr-box-discovery/DB-SCHEMA.md` §3 confirm). Slug 404 on retired user (D-25) MUST be implemented via `JOIN users WHERE users.deleted_at IS NULL` in `InboxesTable::findBySlugOrPrevious()`, NOT via an inbox-level deleted column.
2. **D-28 mentions `expires_at_enc`** — the actual schema column is **`token_expires_at` (DATETIME, plaintext)**. Token *values* are encrypted; the *expiry timestamp* is plaintext (no `*_enc` suffix). The planner must edit D-28 wording in plan task descriptions.

**Primary recommendation:** Plan 4 should be **3 sequential plans** mirroring Phase 3's structure: (a) **04-01 moderation-crud** (BlocksController + ReportsController + soft-delete + 退会 — all CakePHP CRUD), (b) **04-02 token-refresh-and-cleanups** (refresh wiring + sender-handle deleted_at filter + dashboard block-list section + 通報済 badge), (c) **04-03 launch-runbook** (deploy steps as a checklist + Manual smoke walkthrough). Splitting (a) and (b) prevents merge conflicts since (a) is pure new code and (b) edits Phase 2/3 files.

---

## User Constraints (from CONTEXT.md)

### Locked Decisions

**Block UX (D-01–D-04)**
- D-01: Block button only on SSR-hit sender card (Phase 3 D-35 contract). Miss state: no button.
- D-02: Block unit = `users.id` (not identity). 1:1 user-identity mapping per AUTH-04 makes this operationally equivalent.
- D-03: One-click block + Flash with `(取り消し)` undo link. No confirm dialog.
- D-04: Unblock UI = `/dashboard` block list section. No separate page.

**Block Error Display (D-05–D-08)**
- D-05: Block check on BOTH GET (UI control) and POST (race-defence redundant check).
- D-06: Send form visible-but-disabled, persistent banner above. Form NOT hidden.
- D-07: Unauthenticated path uses Phase 3 D-13 `pending_message_body` session pattern.
- D-08: Error copy: 主語ぼかし「この受信箱には送信できません」. NO blocker disclosure.

**Report UX (D-09–D-13)**
- D-09: Separate page `GET/POST /report/{message_id}`.
- D-10: Radio-required, 1 reason from ENUM 4 values (`harassment`/`spam`/`illegal`/`other`).
- D-11: `detail` required ONLY when `reason='other'`, max 1000 chars.
- D-12: Duplicate-report prevention via UNIQUE migration (`uk_reports_reporter_message`) + app-layer SELECT-then-INSERT + DatabaseException catch.
- D-13: NO AI/NG-word filter on report submit (MOD-02).

**Report Review (D-14–D-17)**
- D-14: NO admin web UI. SQL-direct review.
- D-15: NO notification.
- D-16: 通報済 badge (text only, non-removable).
- D-17: Admin actions = SQL UPDATE + optional separate `messages.deleted_at`. NO CLI.

**Soft Delete (D-18–D-22)**
- D-18: 削除 button in expanded message footer (after SSR reveal).
- D-19: Native `confirm()` dialog. No custom modal.
- D-20: Filter `WHERE messages.deleted_at IS NULL` in list query. NO tombstone badge.
- D-21: NO restore UI.
- D-22: `deleted_reason='user_deleted'` (planner-judged value; `'admin_action'` for ops).

**Account Deletion 退会 (D-23–D-27)**
- D-23: Separate page `/account/delete` (NOT inside settings).
- D-24: UPDATE `users.deleted_at` ONLY. Messages/blocks/reports/snapshots/identities all preserved (MOD-03).
- D-25: Slug 404 forever (no re-issue). Add `WHERE users.deleted_at IS NULL` to slug lookup.
- D-26: Dead-link sender snapshots OK (no anonymize, no badge, MOD-03 strict).
- D-27: Checkbox-required form on page (HTML5 `required` + server-side check). NO `confirm()` dialog.

**Token Refresh (D-28–D-31)**
- D-28: Refresh fires inside `upsertBlueskyIdentity()` BEFORE `resolveProfile()` call. Login-time only (lazy).
- D-29: On failure: `Authentication->logout()` + Flash error + redirect `/`.
- D-30: Token rotation ON. Update all 3 fields: `access_token_enc`, `refresh_token_enc`, `token_expires_at`.
- D-31: Full implementation + integration test using `Client::addMockResponse()` pattern (Phase 2 verifier).

**Production Launch (D-32–D-37)**
- D-32: Lolipop git deploy via `post-receive` hook → `composer install --no-dev` + `bin/cake cache:clear_all`. Migration NOT in hook.
- D-33: `.env` + `config/keys/*.pem` placed once via SSH, never re-deployed.
- D-34: `bin/cake migrations migrate` SSH-manual.
- D-35: Manual smoke test only. No `bin/cake smoke`.
- D-36: `debug=false` via env-driven `Configure::write('debug', filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN))`. DebugKit auto-excluded by `--no-dev`.
- D-37: Initial deploy ordering documented.

**Boundary (D-38–D-40)**
- D-38: New schema columns NOT needed except possibly D-12 UNIQUE.
- D-39: human_needed walkthrough = launch.
- D-40: NO PROJECT/REQUIREMENTS/ROADMAP rewrites.

### Claude's Discretion

- D-12 UNIQUE migration vs app-layer dedupe (planner picks)
- `deleted_reason` literal values
- Flash message detailed wording (Phase 2/3 patterns)
- Controller class layout (BlocksController vs new ReportsController vs MessagesController::delete)
- `BlocksController::create()` body details (template, redirect target)
- 退会 form HTML structure
- Token refresh HTTP mock fixture details
- Lolipop SSH hook script body (specific shell commands)
- 退会 transaction boundary (whether `inboxes` row is also touched — note: schema has no inbox `deleted_at`, see Pitfall §1)
- Test fixtures for moderation flows
- Manual smoke checklist format
- Lolipop quirks discovered during deploy

### Deferred Ideas (OUT OF SCOPE)

- Admin web UI for reports
- `bin/cake reports:resolve` CLI
- Email / Bluesky DM notifications
- Soft-delete restore UI
- Sender-snapshot anonymization for retired users
- Slug quarantine + re-issue
- Eager-middleware token refresh on every request
- `bin/cake smoke` automation
- GitHub Actions → Lolipop pipeline
- Synthetic monitoring
- Multi-退会 / 復活 flow
- Pre-send NG word warning UI
- Report statistics
- 退会 reason hearing textarea

---

## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| **INBOX-04** | Receiver can block a sender (identity unit) | §1 Block CRUD — `BlocksTable` already exists with `BlockerUsers`/`BlockedUsers` aliases. Schema has `uk_blocks_pair` UNIQUE + `blocks_no_self` CHECK. |
| **INBOX-05** | Send form shows error when blocked | §1 Block check pattern — `BlocksTable::isBlocked($receiver, $sender)` finder + dual GET/POST gate in `MessagesController::send()`. |
| **MSG-08** | Soft delete via `deleted_at` | §2 Soft-delete — patch `messages.deleted_at = NOW()` + `deleted_reason = 'user_deleted'`. Filter `WHERE deleted_at IS NULL` in `UsersController::dashboard()` paginate query. |
| **MOD-01** | Reporting via 4-category ENUM | §3 Report CRUD — `ReportsTable` exists. New `ReportsController::create($id)` (or `MessagesController::report` re-implementation) for GET/POST. |
| **MOD-02** | Post-hoc only, no AI filter | §3 — DON'T add any moderation pipeline; just INSERT row. |
| **MOD-03** | Retired user snapshot retention | §4 退会 — `users.deleted_at` UPDATE only, snapshots untouched. |
| **MOD-04** | No global BAN | implicit — block table is receiver-scoped, no cross-inbox propagation. |
| **INFRA-01** | Production at `tamabox.emomie.com` | §6 Lolipop deploy — git bare repo + post-receive hook + manual migrations. |
| **INFRA-06** | `debug=false` in production | §6 — `config/app.php` already has `filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN)` (Phase 1 INFRA-02 verified). Production `.env` sets `DEBUG=false`. DebugKit auto-excluded by `composer install --no-dev`. |

---

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Block create / list / delete | API (CakePHP Controller) + ORM (BlocksTable) | View (templates) | CakePHP CRUD pattern, server-rendered. CSRF middleware applies. |
| Block check on send | API (MessagesController) + ORM (BlocksTable.isBlocked finder) | — | Dual gate (GET + POST), race-condition tolerant. |
| Report create | API (ReportsController) + ORM (ReportsTable) | View | Page-level form, server-side validation as canonical gate (MOD-02 = no pipeline). |
| 通報済 badge render | View (dashboard.php) | API (UsersController::dashboard contains call) | Pure render based on existing reports row check. |
| Soft-delete message | API (MessagesController::delete or new action) + ORM (MessagesTable) | View (button + confirm) | UPDATE-only. Filter applied at list query time. |
| 退会 page | API (new AccountController) + ORM (UsersTable) | View | Separate page, transaction = users UPDATE (single row, no related cascades because soft delete). |
| Slug 404 for retired user | ORM (InboxesTable.findBySlugOrPrevious) | API (MessagesController::send) | Add `users.deleted_at IS NULL` JOIN filter. |
| Token refresh on login | Service (BlueskyOAuthClient.refreshToken — already exists) + ORM (UserIdentitiesTable) | API (OauthController::callback flows through unchanged) | Lazy refresh inside upsertBlueskyIdentity, before resolveProfile call. |
| Silent logout on refresh fail | API (catch in upsertBlueskyIdentity caller) + Authentication component | View (Flash message) | Phase 2 pattern: throw RuntimeException → callback catches → logout+Flash+redirect. |
| Production deploy | Operational (SSH commands + git push) | — | Not engineering — runbook in 04-03 plan. |

---

## Standard Stack

### Core (no new dependencies)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| CakePHP | 4.5.7 (existing) | Controller / ORM / Validation / Flash / CSRF | Already wired through Phase 2-3 |
| cakephp/authentication | 2.11 (existing) | `$this->Authentication->getIdentity()`, `logout()` | Phase 2 D-02 |
| cakephp/migrations | 3.7 (existing) | D-12 UNIQUE migration | Phase 1 |
| `Cake\Http\Client` + `Client::addMockResponse()` | core | Token refresh integration testing | Phase 2 verifier pattern (STATE.md Plan 02-03 deviation #1) |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `App\Service\OAuth\TokenEncryptionService` | existing (Phase 2) | Encrypt/decrypt new access+refresh tokens after refresh | D-30 token rotation re-encrypt step |
| `App\Service\OAuth\Bluesky\BlueskyOAuthClient::refreshToken()` | existing (lines 156-187) | Already implements POST to `Bluesky.token_endpoint` with `grant_type=refresh_token` + DPoP + private_key_jwt | D-31 — call site wiring is the only missing piece |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| App-layer SELECT-then-INSERT (D-12 dedupe) | DB UNIQUE migration only + DatabaseException catch | Migration is cleaner (one source of truth) but the `reporter_user_id` SET NULL on user delete creates the NULL-tolerant UNIQUE behavior in MySQL where multiple NULLs ARE allowed (intentional, see Pitfall §3) |
| Custom soft-delete plugin (`muffin/trash`) | Manual `deleted_at = NOW()` + `WHERE deleted_at IS NULL` | Plain UPDATE is simpler. Phase 1 `messages.deleted_at` schema is intentional explicit datetime, not behavior-driven. CONTEXT D-22 specifies `deleted_reason` literal values — Trash plugin doesn't model that column. |
| Eager middleware refresh on every request | Lazy refresh on login (D-28) | MVP simplicity. CakePHP `expires_in` is typically 5-30 min; Bluesky session is long-lived. User would have to actively use the app to need refresh, and the only call-site we have for the access token is `getProfile` during login. NOT relevant after login (no PDS calls). |

**Installation:** none — all dependencies already in `composer.json`.

**Version verification:** All dependencies pinned and locked in Phase 1-3.

---

## Architecture Patterns

### System Data Flow (Phase 4 additions)

```
                                    ┌─────────────────────────────────────┐
   Browser (Phase 3 dashboard) ────▶│ POST /block/{senderUserId}          │
                                    │ → BlocksController::create($sid)    │
                                    │   1. Auth->getIdentity()            │
                                    │   2. INSERT blocks (try) /          │
                                    │      catch DatabaseException        │
                                    │      (uk_blocks_pair already exists)│
                                    │   3. Flash success + (取り消し) link │
                                    │   4. redirect /dashboard            │
                                    └─────────────────────────────────────┘

                                    ┌─────────────────────────────────────┐
   Browser (dashboard or msg row) ▶│ POST /dashboard/blocks/{id}/delete  │
                                    │ → BlocksController::delete($id)     │
                                    │   1. Auth->getIdentity()            │
                                    │   2. Verify blocker_user_id == me   │
                                    │   3. DELETE blocks WHERE id         │
                                    │   4. Flash + redirect /dashboard    │
                                    └─────────────────────────────────────┘

                                    ┌─────────────────────────────────────┐
   Browser (sender, on /<slug>) ───▶│ GET /<slug> (existing, modified)    │
                                    │ → MessagesController::send($slug)   │
                                    │   1. findBySlugOrPrevious           │
                                    │      + WHERE users.deleted_at IS    │
                                    │      NULL (D-25)                    │
                                    │   2. NEW: BlocksTable::isBlocked    │
                                    │      ($inbox.user_id, $me.id)       │
                                    │   3. If blocked: render with        │
                                    │      $isBlocked=true → banner +     │
                                    │      disabled form (D-06)           │
                                    └─────────────────────────────────────┘

                                    ┌─────────────────────────────────────┐
   Browser (msg row 通報する link)  ▶│ GET /report/{message_id}            │
                                    │ → ReportsController::create($mid)   │
                                    │   1. Auth->getIdentity()            │
                                    │   2. Load message, verify           │
                                    │      message.inbox.user_id == me    │
                                    │   3. Check existing report → if     │
                                    │      exists, redirect with flash    │
                                    │   4. Render form template           │
                                    └─────────────────────────────────────┘
                                                  │
                                                  ▼
                                    ┌─────────────────────────────────────┐
                                    │ POST /report/{message_id}           │
                                    │ → ReportsController::create($mid)   │
                                    │   1. Validate reason ENUM           │
                                    │   2. If reason==='other' && !detail │
                                    │      → 422 inline error             │
                                    │   3. Try INSERT reports →           │
                                    │      catch DatabaseException        │
                                    │      (uk_reports_reporter_message)  │
                                    │   4. Flash success + redirect       │
                                    │      /dashboard                     │
                                    └─────────────────────────────────────┘

                                    ┌─────────────────────────────────────┐
   Browser (msg row 削除 button) ──▶│ POST /dashboard/messages/{id}/delete│
                                    │ → MessagesController::delete($id)   │
                                    │   1. Auth->getIdentity()            │
                                    │   2. Load message, verify           │
                                    │      msg.inbox.user_id == me        │
                                    │   3. UPDATE deleted_at=NOW(),       │
                                    │      deleted_reason='user_deleted'  │
                                    │   4. Flash + redirect /dashboard    │
                                    └─────────────────────────────────────┘

                                    ┌─────────────────────────────────────┐
   Browser (settings danger-zone)  ▶│ GET /account/delete                 │
                                    │ → AccountController::delete()       │
                                    │   1. Render warning + checkbox form │
                                    └─────────────────────────────────────┘
                                                  │
                                                  ▼
                                    ┌─────────────────────────────────────┐
                                    │ POST /account/delete                │
                                    │ → AccountController::delete()       │
                                    │   1. Auth->getIdentity()            │
                                    │   2. Verify confirm_delete present  │
                                    │   3. UPDATE users SET deleted_at=NOW│
                                    │   4. Authentication->logout()       │
                                    │   5. Flash info + redirect /        │
                                    └─────────────────────────────────────┘

   ─── Token Refresh (lazy, login-time) ───
                                    ┌─────────────────────────────────────┐
   Browser → Bluesky → /oauth/   ──▶│ OauthController::callback() unchanged│
   callback?code=...&state=...      │ → exchangeCodeForToken (PHASE 2)    │
                                    │ → resolveProfile (PHASE 2)          │
                                    │ → upsertBlueskyIdentity (Phase 4    │
                                    │   modifies):                        │
                                    │   IF existing identity:             │
                                    │     1. Decrypt refresh_token_enc    │
                                    │     2. Check token_expires_at vs    │
                                    │        now() + 60s safety margin    │
                                    │     3. If expired/near:             │
                                    │        a. Call $client->refreshToken│
                                    │        b. Re-encrypt 3 fields       │
                                    │        c. Use new access_token in   │
                                    │           subsequent ops            │
                                    │     4. On RuntimeException from     │
                                    │        refreshToken: throw —        │
                                    │        callback catches, logs out + │
                                    │        flashes + redirects /        │
                                    └─────────────────────────────────────┘
```

### Pattern 1: Block CRUD with `uk_blocks_pair` race-safe INSERT

**What:** INSERT blocks row, catch `DatabaseException` for UNIQUE collision.
**When to use:** Block create action (D-03 one-click).
**Example:**
```php
// src/Controller/BlocksController.php (Phase 4 replacement of 501 stub)
public function create(string $senderUserId): Response
{
    $this->request->allowMethod(['post']);
    $identity = $this->Authentication->getIdentity();
    if ($identity === null) {
        return $this->redirect('/');
    }
    $myId = (string)$identity->getIdentifier();
    if ($myId === $senderUserId) {
        // blocks_no_self CHECK would also catch this, but defend in app layer first.
        $this->Flash->error(__('自分自身はブロックできません。'));
        return $this->redirect('/dashboard');
    }

    /** @var \App\Model\Table\BlocksTable $blocksTable */
    $blocksTable = $this->fetchTable('Blocks');
    try {
        $entity = $blocksTable->newEntity([
            'id' => \Cake\Utility\Text::uuid(),
            'blocker_user_id' => $myId,
            'blocked_user_id' => $senderUserId,
        ], ['accessibleFields' => [
            'id' => true,
            'blocker_user_id' => true,
            'blocked_user_id' => true,
        ]]);
        $blocksTable->saveOrFail($entity);
    } catch (\Cake\Database\Exception\DatabaseException $e) {
        // uk_blocks_pair already exists — idempotent success.
        // Flash with same message; user expects to see "blocked".
    } catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
        $this->Flash->error(__('ブロックに失敗しました。'));
        return $this->redirect('/dashboard');
    }
    // resolve handle for flash copy via UserIdentities
    $handle = $this->resolveHandle($senderUserId);
    $this->Flash->success(...);  // see UI-SPEC §2 for exact format
    return $this->redirect('/dashboard');
}
```
*Source: `BlocksTable.php` lines 28-65 + Phase 2 `UserIdentitiesTable.php:312` `DatabaseException` catch pattern.*

### Pattern 2: Lazy token refresh inside upsertBlueskyIdentity

**What:** Detect expiry, call existing `BlueskyOAuthClient::refreshToken()`, re-encrypt, UPDATE 3 columns, then continue with new access_token.
**When to use:** Inside `UserIdentitiesTable::upsertBlueskyIdentity()`, only on the **existing user** branch (lines 199-246), BEFORE the patchEntity call. New-user branch never has tokens to refresh.
**Wait** — actually D-28 says "BlueskyOAuthClient::resolveProfile() 呼び出し直前で expires_at_enc を復号して expiry チェック". This means the refresh happens in `OauthController::callback()` BEFORE `resolveProfile()`, not inside upsert. The diagram above reflects this. Re-read:

> D-28: Refresh 発火ポイント = `UserIdentitiesTable::upsertBlueskyIdentity()` 内の `BlueskyOAuthClient::resolveProfile()` (= getProfile) 呼び出し直前で expires_at_enc を復号 → … → 新 access token を取得してから getProfile

OauthController::callback DOES call `resolveProfile()` BEFORE `upsertBlueskyIdentity()` (`OauthController.php:175-180`). So the refresh logic doesn't naturally fit inside `upsertBlueskyIdentity()` — by the time we're inside upsert, `resolveProfile()` has ALREADY succeeded with the brand-new access_token from `exchangeCodeForToken()`.

**This is a CONTEXT slip:** D-28 describes a flow that doesn't actually occur on the **fresh login** path. On a fresh login, the access_token is brand-new (just received from `exchangeCodeForToken`) — there's nothing expired to refresh.

**The correct interpretation:** the refresh path fires on **subsequent logins** (i.e., login flow runs again, OauthController::callback returns a new access_token). Wait — but the OAuth flow always re-runs `exchangeCodeForToken` when a user clicks "Bluesky でログイン" (which goes through PAR + redirect + callback again, getting a brand-new code → fresh access_token).

**So when does refresh actually fire?** Only when:
- (a) User has an active session (already logged in)
- (b) Application makes a server-to-server call to Bluesky PDS using the stored access_token
- (c) That call returns 401 (token expired)

Tamabox MVP makes **zero server-to-server calls to Bluesky after login**. There are no PDS write operations, no profile re-fetches between logins. **The only access_token use is `resolveProfile()` during the OAuth callback itself, which uses the brand-new just-issued access_token.**

So **refresh has no live call-site in the MVP** — the existing `BlueskyOAuthClient::refreshToken()` is dead code unless we add a use-case.

**Two valid resolutions for the planner to bring back to the user:**

**Resolution A (recommended):** Delete the refresh integration from Phase 4. The defer was for a use-case that never materialized. Mark Phase 2 sticky #5 as resolved-as-not-needed. The dead method body stays for AUTH-06 future X provider, but no call site is wired. Update ROADMAP/STATE accordingly.

**Resolution B (the CONTEXT D-28 spirit):** Add a non-OAuth-callback use-case that NEEDS a live access_token, e.g., periodic handle/avatar resync via PDS getProfile. But this contradicts AUTH-FLOW.md handle-sync-policy-B (resync on each login only) and adds operational complexity (cron jobs forbidden on Lolipop).

**Resolution C (D-28 literal interpretation):** Modify upsertBlueskyIdentity so that on the existing-user branch, the freshly-issued access_token is ignored if `token_expires_at` from the previous session is still valid (>60s remaining), and instead the stored refresh_token is decrypted and a refresh call is made to validate it. This is **operationally pointless** — we just got a brand-new access_token, why discard it? — but it would exercise the refresh path.

**Recommendation to the planner:** Bring this back to the user via discuss-phase or by writing an open question. Phase 4 should NOT block on this. **Default plan: Resolution A** (delete refresh integration, document why), unless the user explicitly wants Resolution C for "exercise the path before launch" rationale.

This finding **does not block planning** — the moderation and deploy areas are independent.

### Pattern 3: Block-check on send (dual gate, D-05)

**What:** GET-time check renders disabled form + banner; POST-time check rejects with same banner.
**When to use:** `MessagesController::send()` modification.
**Example:**
```php
// src/Controller/MessagesController.php (Phase 4 modification of existing send())
// AFTER findBySlugOrPrevious returns $inbox:

$identity = $this->Authentication->getIdentity();
$isBlocked = false;
if ($identity !== null) {
    $myId = (string)$identity->getIdentifier();
    /** @var \App\Model\Table\BlocksTable $blocksTable */
    $blocksTable = $this->fetchTable('Blocks');
    $isBlocked = $blocksTable->exists([
        'blocker_user_id' => (string)$inbox->user_id,
        'blocked_user_id' => $myId,
    ]);
}

if ($this->request->is('get')) {
    $this->set('isBlocked', $isBlocked);  // template renders banner + disabled form when true
    $this->renderSendForm($inbox, $isAuthenticated, $isOwnInbox);
    return null;
}

// POST path — defense-in-depth re-check (D-05)
if ($isBlocked) {
    $this->Flash->error(__('この受信箱には送信できません。'));
    return $this->redirect('/' . $inbox->slug);
}
```
*Note: `Cake\ORM\Table::exists()` is the idiomatic exists-check, returns bool, doesn't hydrate.*

### Pattern 4: Phinx UNIQUE migration with NULL-tolerant column (D-12)

**What:** Add `uk_reports_reporter_message` UNIQUE on `(reporter_user_id, message_id)`.
**When to use:** New migration in `config/Migrations/`.
**MySQL 8.0 NULL-in-UNIQUE behavior:** **Multiple NULLs ARE allowed** in a UNIQUE index column when the column is nullable. This is documented and intentional — anonymized reports (after reporter退会 → SET NULL) won't block other reports on the same message. CONTEXT specifics §1 already notes this.
**Example:**
```php
// config/Migrations/20260428000001_AddReporterMessageUniqueToReports.php
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

### Pattern 5: Soft-delete with paginate filter

**What:** Filter `WHERE messages.deleted_at IS NULL` in the dashboard listing.
**When to use:** Modify `UsersController::dashboard()` (line 80).
**Example:**
```php
// src/Controller/UsersController.php Phase 4 mod (line 80-85):
$messages = $this->paginate(
    $messagesTable
        ->find()
        ->where([
            'Messages.inbox_id' => $inbox->id,
            'Messages.deleted_at IS' => null,  // D-20
        ])
        ->order(['Messages.created_at' => 'DESC'])
);
```
*Source: `UsersController.php:80-85`. The CakePHP ORM `'Foo IS' => null` syntax generates `Foo IS NULL`.*

### Pattern 6: 退会 endpoint — single-row UPDATE, no cascade triggers

**What:** UPDATE `users.deleted_at = NOW()`, that's literally it. No cascade actions because all FKs from-users are CASCADE/RESTRICT/SET NULL — we don't physically delete the row, just set the timestamp.
**When to use:** New `AccountController::delete()`.
**Example:**
```php
// src/Controller/AccountController.php (NEW)
public function delete(): ?Response
{
    $identity = $this->Authentication->getIdentity();
    if ($identity === null) {
        return $this->redirect('/');
    }
    $myId = (string)$identity->getIdentifier();

    if ($this->request->is('get')) {
        return null;  // render templates/Account/delete.php (UI-SPEC §7)
    }

    // POST — D-27 checkbox required
    $this->request->allowMethod(['post']);
    $confirmed = $this->request->getData('confirm_delete');
    if (!$confirmed) {
        throw new \Cake\Http\Exception\BadRequestException();
    }

    /** @var \App\Model\Table\UsersTable $usersTable */
    $usersTable = $this->fetchTable('Users');
    $user = $usersTable->get($myId);
    $patched = $usersTable->patchEntity($user, [
        'deleted_at' => \Cake\I18n\FrozenTime::now(),
    ], ['accessibleFields' => ['deleted_at' => true]]);
    $usersTable->saveOrFail($patched);

    $this->Authentication->logout();
    $this->Flash->info(__('退会が完了しました。ご利用ありがとうございました。'));
    return $this->redirect('/');
}
```

### Anti-Patterns to Avoid

- **DON'T add a custom soft-delete behavior plugin** for messages. Phase 1 schema is plain `deleted_at` + manual filter. Adding `muffin/trash` mid-project is a refactor risk for zero MVP gain.
- **DON'T model retired users as a separate table** or add a `is_active` boolean. `users.deleted_at IS NULL` is the canonical predicate (Phase 1 D-10).
- **DON'T add unblock confirmation dialogs** (D-04 says low-stakes).
- **DON'T expose blocker handle in error banner** (D-08 主語ぼかし).
- **DON'T re-bake `BlocksTable.php` / `ReportsTable.php`** — bake re-introduces broken default fixtures (Phase 1 deviation #2).
- **DON'T put `bin/cake migrations migrate` in post-receive hook** (D-34 — migration failure breaks the deploy mid-checkout).
- **DON'T deploy `config/.env` or `config/keys/*.pem` via git** — the `.gitignore` excludes them; production placement is one-shot SSH (D-33).
- **DON'T re-implement `BlueskyOAuthClient::refreshToken()` body** — it's already done (Phase 2 verified).

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| AES-GCM token re-encryption (refresh) | Custom crypto | `App\Service\OAuth\TokenEncryptionService::encrypt/decrypt` | Already exists from Phase 2 (Plan 02-02), AUTH-07 verified. |
| OAuth refresh HTTP request | Custom curl + signing | `BlueskyOAuthClient::refreshToken()` | Already exists at `src/Service/OAuth/Bluesky/BlueskyOAuthClient.php:156-187`. Tested? Verifier didn't run integration test for refresh path — `BlueskyOAuthClientTest.php` covers PAR + token-exchange + getProfile but NOT refresh. The method body is verified by reading; full test coverage is a Phase 4 deliverable IF refresh integration is kept (see Pattern 2 discussion). |
| Slug 404 logic | Custom not-found check | `Cake\Http\Exception\NotFoundException` thrown from `InboxesTable::findBySlugOrPrevious` | Existing pattern, Phase 3 verified. Just add `users.deleted_at IS NULL` to the JOIN. |
| Block UNIQUE collision handling | App-level pre-INSERT lookup | Catch `Cake\Database\Exception\DatabaseException` | Pattern from `UserIdentitiesTable.php:312` — race-safe. |
| Report duplicate prevention | Pure app-layer SELECT-then-INSERT (no DB constraint) | UNIQUE migration + DatabaseException catch | DB constraint is a single source of truth, prevents race conditions. CONTEXT D-12 already settles this. |
| 退会 cascade logic | Manual related-table cleanup | Just UPDATE `users.deleted_at` | All from-users FKs are CASCADE/RESTRICT/SET NULL but we DON'T physically delete the row — soft-delete means cascades NEVER fire. Other tables' WHERE clauses do the filtering. |
| Lolipop file sync | Custom rsync / FTP scripts | git bare repo + `post-receive` hook running `git checkout -f` | Industry standard for shared hosting. Documented since 2010s in Lolipop community. |
| `debug=false` toggle | Manual config commits per-environment | `filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN)` in `config/app.php` + `.env` per environment | Phase 1 INFRA-02 already wired this. Phase 4 only sets `DEBUG=false` in production `.env`. |

**Key insight:** Phase 4 is **almost zero new code architecture** — every component pattern already exists in Phase 2/3. The plan is "wire up CRUD from existing tables + 1 migration + 1 deploy runbook." Researcher confidence is HIGH because nothing requires invention.

---

## Common Pitfalls

### Pitfall 1: `inboxes.deleted_at` does NOT exist (CONTEXT D-24 inaccuracy)

**What goes wrong:** D-24 says `inboxes.deleted_at` is also UPDATEd on退会. But `config/Migrations/20260422120003_CreateInboxes.php` (lines 35-107) and `/home/claude/projects/ssr-box-discovery/DB-SCHEMA.md` §3 both confirm: **`inboxes` has NO `deleted_at` column.** Only `users.deleted_at`, `messages.deleted_at`.
**Why it happens:** CONTEXT.md author confused inbox-level visibility hiding (which D-25 wants) with a per-table soft-delete column (which doesn't exist).
**How to avoid:** Implement D-25 slug 404 by JOINing `users` and filtering `users.deleted_at IS NULL` in `InboxesTable::findBySlugOrPrevious()`, not by checking inbox row state. Update plan task language to remove `inboxes.deleted_at` mentions.
**Code example:**
```php
// src/Model/Table/InboxesTable.php Phase 4 mod (lines 147-168):
public function findBySlugOrPrevious(string $slug): array
{
    $inbox = $this->find()
        ->contain(['Users'])
        ->where([
            $this->aliasField('slug') => $slug,
            'Users.deleted_at IS' => null,  // D-25 retired user → 404
        ])
        ->first();
    // … rest unchanged
}
```
**Warning signs:** If the plan task mentions `inboxes.deleted_at` UPDATE in 退会 flow, that's broken. Remove.

### Pitfall 2: `token_expires_at` is plaintext, NOT `expires_at_enc`

**What goes wrong:** D-28 says "expires_at_enc を復号". But `user_identities.token_expires_at` is a plain DATETIME column (`config/Migrations/20260422120002_CreateUserIdentities.php:83`). The encrypted columns are `access_token_enc` and `refresh_token_enc` only.
**Why it happens:** D-28 author mentally extended the `*_enc` pattern to all token-related columns.
**How to avoid:** Read `token_expires_at` directly via the entity. No decryption needed.
**Code example:**
```php
// in upsertBlueskyIdentity or wherever expiry check happens:
$expiresAt = $existing->token_expires_at;  // FrozenTime|null, plaintext
if ($expiresAt === null || $expiresAt->lessThanOrEquals(FrozenTime::now()->addSeconds(60))) {
    // expired or near-expiry — refresh
}
```

### Pitfall 3: MySQL 8.0 UNIQUE allows multiple NULLs

**What goes wrong:** D-12 UNIQUE on `(reporter_user_id, message_id)` won't block `(NULL, msg-A)` + `(NULL, msg-A)` rows. Some implementers expect Postgres-like NULL-NULL=duplicate behavior; MySQL treats NULL as "unknown" and allows duplicates.
**Why it happens:** Default MySQL 8.0 behavior. Documented at https://dev.mysql.com/doc/refman/8.0/en/create-index.html — "A UNIQUE index permits multiple NULL values for columns that can contain NULL."
**How to avoid:** This is **intentional** per CONTEXT specifics §1 —退会 reporters get SET NULL'd, and the original reporter's report row should not block other reporters. No fix needed; just understand the semantics.
**Warning signs:** If the planner adds an `IFNULL(reporter_user_id, '00000000-...')` workaround, that's wrong — it would break `fk_reports_reporter` SET NULL semantics.

### Pitfall 4: `BlocksTable.exists()` direction matters

**What goes wrong:** Block check uses wrong direction (`blocked → blocker` instead of `blocker → blocked`).
**Why it happens:** Schema is `blocker_user_id` (the receiver) blocks `blocked_user_id` (the sender). Someone reading "is X blocked by Y" can mentally swap.
**How to avoid:** Always pair the names: `blocker_user_id = $inbox->user_id` (receiver who created the block), `blocked_user_id = $sender->id` (sender being blocked).
**Code:** see Pattern 3.
**Warning signs:** If the test fixture INSERTs a block with `blocker_user_id = sender` and the test passes, the direction is reversed.

### Pitfall 5: `messages_body_length_check` CHECK constraint when patching

**What goes wrong:** Soft-delete UPDATE patches `deleted_at`, but if the test or fixture also touches `body`, `body_length` must stay consistent (Phase 3 deviation #4).
**Why it happens:** `CHECK (body_length = CHAR_LENGTH(body))` is enforced.
**How to avoid:** Soft-delete should ONLY patch `deleted_at` + `deleted_reason`. Don't touch `body` or `body_length`.
**Code:**
```php
$patched = $messagesTable->patchEntity($msg, [
    'deleted_at' => FrozenTime::now(),
    'deleted_reason' => 'user_deleted',  // D-22
], ['accessibleFields' => [
    'deleted_at' => true,
    'deleted_reason' => true,
]]);
```

### Pitfall 6: Lolipop SSH PHP path is version-specific

**What goes wrong:** Default SSH `php` may point to PHP 5.6 / 7.4 — not the configured-via-control-panel version.
**Why it happens:** Lolipop SSH shells default to `/usr/local/bin/php` which can be the older PHP. The web (Apache module) PHP is configured separately via control panel.
**How to avoid:** Use the explicit version path. For PHP 8.1: `/usr/local/php/8.1/bin/php`. For PHP 8.3: `/usr/local/php/8.3/bin/php`. Add to `~/.bashrc`:
```bash
alias php='/usr/local/php/8.1/bin/php'
alias composer='/usr/local/php/8.1/bin/php /home/users/.../composer.phar'
```
Or in the post-receive hook, invoke composer via explicit path:
```bash
/usr/local/php/8.1/bin/php /home/users/.../composer.phar install --no-dev --optimize-autoloader
```
**Warning signs:** If `bin/cake migrations migrate` errors out with "PHP 7.x required ^8.0", the shell is using the wrong PHP.

### Pitfall 7: ORM cache stale after migration

**What goes wrong:** After `bin/cake migrations migrate` adds the UNIQUE index, ORM may have cached column metadata, causing "column not found" errors until cache cleared.
**Why it happens:** CakePHP ORM caches table introspection. Per CakePHP docs (book.cakephp.org/4/en/deployment.html): "After running migrations, remember to clear the ORM cache."
**How to avoid:** Always run `bin/cake cache:clear_all` after `bin/cake migrations migrate`. Bake into deploy step (D-37 step 4 should pair these).
**Code:** D-37 step 4 actually:
```bash
bin/cake migrations migrate
bin/cake cache clear_all  # mandatory follow-up
```

### Pitfall 8: AppController fetchTable forwards work, but service injection doesn't

**What goes wrong:** `$this->fetchTable('Reports')` works in controllers (CakePHP 4.5 helper). But if a service class needs `ReportsTable`, must use `TableRegistry::getTableLocator()->get('Reports')`.
**Why it happens:** `fetchTable` is a controller-only convenience.
**How to avoid:** Stick with `fetchTable` in controllers. For one-off needs, the controller is the right layer for these CRUD operations — don't introduce a service layer for moderation if not needed.

### Pitfall 9: `inboxes.fk_inboxes_user` is CASCADE — relevant for hard-delete only

**What goes wrong:** A planner might worry that the `fk_inboxes_user` CASCADE will wipe the inbox when 退会 fires. It won't, because 退会 sets `users.deleted_at` (UPDATE), it doesn't DELETE the user row. CASCADE only fires on physical DELETE.
**Why it happens:** Reading the FK action without context.
**How to avoid:** Document this in the退会 plan task explicitly: "no cascade is triggered because users row is not physically deleted; only `deleted_at` is set."

### Pitfall 10: `BlueskyOAuthClient::refreshToken()` lacks integration test coverage

**What goes wrong:** Phase 2 verifier tests cover PAR / token-exchange / profile fetch, but **not refresh** (per `tests/TestCase/Service/OAuth/Bluesky/BlueskyOAuthClientTest.php` table of contents and STATE.md verifier output).
**Why it happens:** Phase 2 sticky #5 deferred the call-site, so the test for the un-called method also got deferred.
**How to avoid:** IF the planner keeps the refresh integration (Pattern 2 discussion), add 3 mock-based tests:
- testRefreshTokenSuccess201 (happy path: returns new access+refresh+expires_in)
- testRefreshTokenInvalidGrant401 (revoked refresh token → throws RuntimeException with 'REFRESH' phase label)
- testRefreshTokenWithDpopNonceRetry (400 + use_dpop_nonce → retries, succeeds 200)
**Code:** See `tests/TestCase/Service/OAuth/Bluesky/BlueskyOAuthClientTest.php` lines 80-95 for the existing pattern.

### Pitfall 11: `DEBUG=false` in production .env may not auto-disable DebugKit

**What goes wrong:** Even with `DEBUG=false`, if `cakephp/debug_kit` is in the autoloaded packages (somehow installed despite `--no-dev`), `Application::bootstrap()` still adds the plugin conditionally on `Configure::read('debug')`. If config evaluates `debug=false`, DebugKit doesn't load — good. But if `composer install --no-dev` accidentally still includes DebugKit (e.g., if it was moved to `require`), it could load.
**Why it happens:** `composer.json` has DebugKit in `require-dev` — confirmed. So `--no-dev` excludes it from `vendor/`. The `addPlugin('DebugKit')` line in `Application::bootstrap()` will fail to find the plugin and throw — UNLESS we use `addOptionalPlugin`.
**How to avoid:** Phase 4 plan should verify that `Application::bootstrap()` line 70-72 uses `addPlugin` not `addOptionalPlugin` and document that the production deploy runs `composer install --no-dev` so DebugKit isn't installed AND `Configure::read('debug')` evaluates false. The conditional `if (Configure::read('debug'))` short-circuits before `addPlugin` runs, so even if the package were missing it wouldn't error. **Verify this manually after first deploy** as a smoke gate.
**Code:** `src/Application.php:70-72`:
```php
if (Configure::read('debug')) {
    $this->addPlugin('DebugKit');  // never executed in production with debug=false
}
```
This is correct as-is.

### Pitfall 12: Lolipop's git bare repo path conventions

**What goes wrong:** Newer Lolipop accounts have user home at `/home/users/0/<account>/` for legacy or `/home/users/2/<account>/` for newer. Path-hardcoding in deploy script breaks if account migrates plans.
**Why it happens:** Lolipop's storage backend evolved.
**How to avoid:** Use `$HOME` instead of hardcoded paths in the post-receive hook:
```bash
#!/bin/sh
TARGET="$HOME/web/tamabox.emomie.com"
GIT_DIR="$HOME/repo/tamabox.git"
git --git-dir="$GIT_DIR" --work-tree="$TARGET" checkout -f main
cd "$TARGET" && /usr/local/php/8.1/bin/php /usr/local/composer/composer.phar install --no-dev --optimize-autoloader
```
**Warning signs:** Hook fails on first push because `cd` target doesn't exist. Verify paths via `pwd` SSH session before scripting.

---

## Runtime State Inventory

> Phase 4 includes both new state-creating actions (block/report/delete) and state-modifying actions (退会). Inventory mostly reflects design state for plans, not pre-existing migration data.

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | New rows: blocks, reports, messages.deleted_at UPDATE, users.deleted_at UPDATE. NO migration of existing data needed (no production data exists yet). | Code edits only. |
| Live service config | None — Lolipop deploy is fresh, no existing service to update. | None. |
| OS-registered state | Lolipop SSH account, git bare repo placement (initial setup, D-37 step 1). cron jobs forbidden on Lolipop standard plan. | Manual SSH session per D-37. |
| Secrets/env vars | `config/.env` + `config/keys/es256-private.pem` + `config/keys/es256-public.pem` + `config/app_local.php` (for DB connection). All gitignored. Phase 4 places these once via SSH (D-33). New env values: `DEBUG=false`, plus all Phase 2 vars (`OAUTH_KID`, `TOKEN_ENC_KEY`, `BLUESKY_*`, `SERVER_SECRET`, `DATABASE_URL`). | Initial SSH placement (D-37 step 2), never touched again. |
| Build artifacts | `vendor/` directory regenerated by `composer install --no-dev --optimize-autoloader` in post-receive hook. `tmp/cache/` and `logs/` writable by web user. | Permissions via SSH on first deploy. |

**Nothing found in category:** None — all 5 categories have content for Phase 4.

---

## Validation Architecture

> Project config has `nyquist_validation_enabled` not explicitly set, so include section per default-enabled rule. Project tests with `composer test`.

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 9.6 (existing, Phase 1 wired) + CakePHP TestSuite |
| Config file | `phpunit.xml.dist` (existing) |
| Quick run command | `vendor/bin/phpunit --filter <TestName>` |
| Full suite command | `composer test` (currently 163 tests / 439 assertions / 0 failures from Phase 3) |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| INBOX-04 | Block create succeeds + UNIQUE collision idempotent | integration | `vendor/bin/phpunit tests/TestCase/Controller/BlocksControllerTest.php` | Stub exists; needs body |
| INBOX-04 | Block delete (unblock) succeeds | integration | (same file) | New test |
| INBOX-05 | Send GET shows banner when blocked | integration | `vendor/bin/phpunit tests/TestCase/Controller/MessagesControllerTest.php::testSendShowsBlockedBanner` | New test in existing file |
| INBOX-05 | Send POST rejected when blocked | integration | (same file) | New test |
| MSG-08 | Soft-delete patches deleted_at + filters out of list | integration | `MessagesControllerTest::testDeleteSoftDeletes` + `UsersControllerTest::testDashboardExcludesDeleted` | New |
| MOD-01 | Report create with valid reason | integration | `tests/TestCase/Controller/ReportsControllerTest.php` (NEW) | New file |
| MOD-01 | Report create with `reason=other` requires detail | integration | (same) | New |
| MOD-01 | Duplicate report → 422 / flash error | integration | (same) | New |
| MOD-03 | 退会 sets users.deleted_at, snapshots preserved | integration | `tests/TestCase/Controller/AccountControllerTest.php` (NEW) | New file |
| MOD-03 | After 退会, slug returns 404 | integration | `MessagesControllerTest::testSendReturnsNotFoundWhenOwnerRetired` | New |
| MOD-04 | Block is receiver-scoped (other inbox unaffected) | integration | `MessagesControllerTest::testSendOtherInboxIgnoresUnrelatedBlocks` | New |
| INFRA-01 | Production deploy + smoke test | manual | n/a (D-35 manual walkthrough) | n/a |
| INFRA-06 | `debug=false` in production | manual | inspect via `bin/cake configuration` over SSH after deploy | n/a |
| (D-31 refresh) | If kept: refresh on expiry, retry on nonce, fail on invalid_grant | unit | `BlueskyOAuthClientTest::testRefreshToken*` | New 3 tests if Pattern 2 Resolution C kept |

### Sampling Rate

- **Per task commit:** `vendor/bin/phpunit --filter <TestName>` for the file under change
- **Per wave merge:** `composer test` full suite
- **Phase gate:** Full suite green + `phpcs` 54/54 + `phpstan level 8 [OK]` before `/gsd-verify-work`

### Wave 0 Gaps

- [ ] `tests/TestCase/Controller/ReportsControllerTest.php` — covers MOD-01 (or `MessagesControllerTest::testReport*` if reuse)
- [ ] `tests/TestCase/Controller/AccountControllerTest.php` — covers MOD-03
- [ ] Test fixtures: `BlocksFixture.php` + `ReportsFixture.php` need additional records for moderation flows. Existing fixtures cover Phase 1 schema-validity only.
- [ ] If refresh kept: 3 new `BlueskyOAuthClientTest::testRefreshToken*` tests + mock fixtures for 200/400-nonce-retry/401-invalid_grant.

*(No framework install needed — PHPUnit and CakePHP TestSuite already configured.)*

### Test Pattern Reference (from Phase 2/3 verifier-discovered)

- **`Client::clearMockResponses()`** in BOTH `setUp()` AND `tearDown()` (Plan 02-03 deviation #1) — `BlueskyOAuthClientTest.php:36,60`.
- **`@var array<int, string>` + `protected $fixtures` (untyped)** — never `protected array $fixtures` (Plan 02-04 Rule 1, PHP fatal).
- **`session(['Flash' => []])` between requests in a single test** — to clear consume-once flash data (Plan 03-03a Rule 1).
- **`->scalar()` not `->uuid()`** for FK validation when fixtures use sequential IDs (Plan 03-02 Rule 1).
- **`'Foo IS' => null`** ORM syntax for `IS NULL` in WHERE clauses.

---

## Code Examples

### Example 1: Block check finder on `BlocksTable`

```php
// src/Model/Table/BlocksTable.php — add method (NEW)
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
*Source pattern: ORM `Table::exists()` is short-circuit count-based; no entity hydration overhead.*

### Example 2: Report duplicate-aware INSERT

```php
// src/Controller/ReportsController.php (NEW)
public function create(string $messageId): ?Response
{
    $identity = $this->Authentication->getIdentity();
    if ($identity === null) {
        return $this->redirect('/');
    }
    $myId = (string)$identity->getIdentifier();

    /** @var \App\Model\Table\MessagesTable $messagesTable */
    $messagesTable = $this->fetchTable('Messages');
    $msg = $messagesTable->find()
        ->where(['Messages.id' => $messageId])
        ->contain(['Inboxes'])
        ->first();
    if ($msg === null || (string)$msg->inbox->user_id !== $myId) {
        throw new \Cake\Http\Exception\NotFoundException();
    }

    /** @var \App\Model\Table\ReportsTable $reportsTable */
    $reportsTable = $this->fetchTable('Reports');

    if ($this->request->is('get')) {
        // Pre-check: already reported? redirect with flash (D-16 implies)
        if ($reportsTable->exists([
            'reporter_user_id' => $myId,
            'message_id' => $messageId,
        ])) {
            $this->Flash->error(__('このメッセージは既に通報済みです。'));
            return $this->redirect('/dashboard');
        }
        $this->set('message', $msg);
        return null;  // render templates/Reports/create.php
    }

    // POST
    $this->request->allowMethod(['post']);
    $reason = $this->postString('reason');
    $detail = $this->postString('detail');

    $allowedReasons = ['harassment', 'spam', 'illegal', 'other'];
    if (!in_array($reason, $allowedReasons, true)) {
        $this->Flash->error(__('通報理由を選んでください。'));
        return $this->redirect('/report/' . $messageId);
    }
    if ($reason === 'other' && trim($detail) === '') {
        $this->Flash->error(__('「その他」選択時は詳細の記入が必須です。'));
        return $this->redirect('/report/' . $messageId);
    }
    if (mb_strlen($detail) > 1000) {
        $this->Flash->error(__('詳細は 1000 文字以内で入力してください。'));
        return $this->redirect('/report/' . $messageId);
    }

    try {
        $entity = $reportsTable->newEntity([
            'id' => \Cake\Utility\Text::uuid(),
            'message_id' => $messageId,
            'reporter_user_id' => $myId,
            'reason' => $reason,
            'detail' => $reason === 'other' ? $detail : ($detail !== '' ? $detail : null),
            'status' => 'pending',
        ], ['accessibleFields' => [
            'id' => true,
            'message_id' => true,
            'reporter_user_id' => true,
            'reason' => true,
            'detail' => true,
            'status' => true,
        ]]);
        $reportsTable->saveOrFail($entity);
    } catch (\Cake\Database\Exception\DatabaseException $e) {
        // uk_reports_reporter_message UNIQUE collision (D-12) — race-safe.
        $this->Flash->error(__('このメッセージは既に通報済みです。'));
        return $this->redirect('/dashboard');
    } catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
        $this->Flash->error(__('通報の送信に失敗しました。'));
        return $this->redirect('/report/' . $messageId);
    }

    $this->Flash->success(__('通報を送信しました。確認まで時間がかかる場合があります。'));
    return $this->redirect('/dashboard');
}
```

### Example 3: Lolipop post-receive hook

```bash
#!/bin/sh
# ~/repo/tamabox.git/hooks/post-receive (Lolipop SSH placement, chmod +x)
# Triggered automatically on `git push lolipop main` from VPS.

set -e

WORKING_DIR="$HOME/web/tamabox.emomie.com"
GIT_DIR="$HOME/repo/tamabox.git"
PHP_BIN="/usr/local/php/8.1/bin/php"            # Lolipop's PHP 8.1+ path
COMPOSER_PHAR="$HOME/composer.phar"

# Ensure target dir exists.
mkdir -p "$WORKING_DIR"

# Checkout the pushed main branch into web tree.
git --git-dir="$GIT_DIR" --work-tree="$WORKING_DIR" checkout -f main

# Install PHP dependencies excluding dev (D-32, D-36 — DebugKit excluded here).
cd "$WORKING_DIR"
"$PHP_BIN" "$COMPOSER_PHAR" install --no-dev --optimize-autoloader --no-interaction

# Clear ORM/route caches (CakePHP deployment best practice).
"$PHP_BIN" bin/cake.php cache clear_all || true

echo "[tamabox] post-receive deploy complete: $(date)"
```

### Example 4: Token-refresh tests (if Pattern 2 Resolution C kept)

```php
// tests/TestCase/Service/OAuth/Bluesky/BlueskyOAuthClientTest.php — append

public function testRefreshTokenSuccess(): void
{
    Client::addMockResponse(
        'POST',
        'https://bsky.social/oauth/token',
        new Response(
            ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
            (string)json_encode([
                'access_token' => 'new-access-xyz',
                'refresh_token' => 'new-refresh-xyz',
                'expires_in' => 900,
                'token_type' => 'DPoP',
            ])
        )
    );

    $out = $this->newClient()->refreshToken('old-refresh-abc');
    $this->assertSame('new-access-xyz', $out['access_token']);
    $this->assertSame('new-refresh-xyz', $out['refresh_token']);  // rotated!
    $this->assertSame(900, $out['expires_in']);
}

public function testRefreshTokenFailsOnInvalidGrant(): void
{
    Client::addMockResponse(
        'POST',
        'https://bsky.social/oauth/token',
        new Response(
            ['HTTP/1.1 401 Unauthorized', 'Content-Type: application/json'],
            (string)json_encode(['error' => 'invalid_grant'])
        )
    );

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('REFRESH request failed (HTTP 401)');
    $this->newClient()->refreshToken('revoked-refresh');
}

public function testRefreshTokenRetriesOnDpopNonce(): void
{
    // First response: 400 + use_dpop_nonce + DPoP-Nonce header.
    Client::addMockResponse(
        'POST',
        'https://bsky.social/oauth/token',
        new Response(
            ['HTTP/1.1 400 Bad Request', 'Content-Type: application/json', 'DPoP-Nonce: abc-nonce'],
            (string)json_encode(['error' => 'use_dpop_nonce'])
        )
    );
    Client::addMockResponse(
        'POST',
        'https://bsky.social/oauth/token',
        new Response(
            ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
            (string)json_encode([
                'access_token' => 'new-access',
                'refresh_token' => 'new-refresh',
                'expires_in' => 900,
            ])
        )
    );

    $client = $this->newClient('initial-nonce');  // seed nonce so retry path engages
    $out = $client->refreshToken('valid-refresh');
    $this->assertSame('new-access', $out['access_token']);
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Phase 3 D-35 stubs (`BlocksController`/`MessagesController::report` 501) | Phase 4 real implementation + integration tests | this phase | Replace stub bodies, update test files (per OauthController callback precedent in Plan 02-04). |
| Phase 3 dashboard with `WHERE inbox_id = ?` only | + `AND deleted_at IS NULL` filter | this phase | Soft-deleted messages disappear from list (D-20). |
| `InboxesTable::findBySlugOrPrevious` joins Users for slug match | Same query + `Users.deleted_at IS NULL` filter | this phase | Retired users' slugs return 404 (D-25). |
| `composer install` (default with dev deps) | `composer install --no-dev --optimize-autoloader` in production | this phase | DebugKit not deployed (INFRA-06 / D-36). |
| `debug=true` (CakePHP skeleton default) | `filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN)` with `.env` `DEBUG=false` in production | already wired Phase 1 INFRA-02 | Confirmed via Plan 01-01 SUMMARY. |
| OAuth token expires_at unchecked | Optional refresh on login (D-28 - resolution pending, see Pattern 2) | this phase OR descope | Conditional. |
| No production deployment | Lolipop git deploy + `tamabox.emomie.com` live | this phase | INFRA-01. |

**Deprecated/outdated:** None. Phase 4 introduces new patterns; nothing rotates out.

---

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Lolipop high-speed plan supports PHP 8.1+ | §6 Pitfall 6 | If only 8.0 available, `composer.json` `^8.0` still works but PHP path changes (`/usr/local/php/8.0/bin/php`). |
| A2 | MySQL 8.0 NULL-in-UNIQUE behavior allows multiple NULLs | §3 D-12 Pitfall 3 | If wrong, anonymized reports would fail INSERT. Verified via MySQL official docs (https://dev.mysql.com/doc/refman/8.0/en/create-index.html). |
| A3 | `bin/cake.php` works in Lolipop SSH with explicit PHP binary | §6 deploy runbook | Verified pattern in altotoo (live in production). |
| A4 | `mod_rewrite` enabled on Lolipop standard plan | implicit (Phase 1) | Verified by Phase 1 INFRA-05 — `.htaccess` rewrite to `webroot/` is documented standard. |
| A5 | Bluesky AS error response format on revoked refresh: `{"error": "invalid_grant"}` with HTTP 401 | §Code Example 4 | Per atproto.com/specs/oauth: rotation is mandatory, replay → revoke. The body format is OAuth 2.0 standard (RFC 6749). |
| A6 | `BlueskyOAuthClient::refreshToken()` already correctly implements the DPoP + private_key_jwt + nonce-retry flow | §Pattern 2 | Verified by reading `src/Service/OAuth/Bluesky/BlueskyOAuthClient.php:156-187` — it reuses the same `postWithNonceRetry()` helper as PAR/token-exchange. |
| A7 | Resolution A (skip refresh integration) is acceptable to user given no live PDS calls in MVP | §Pattern 2 discussion | If user wants Resolution C (refresh-on-relogin to exercise the path), planner adds the wiring. NOT a blocker — this is a discuss-phase question to surface. |
| A8 | Deploy-time `bin/cake cache:clear_all` is required after migrations | §Pitfall 7 | Per CakePHP 4 deployment docs. Verified. |
| A9 | The 9 Phase 4 requirements can ship in 3 plans without scope creep | §Recommended Plan Breakdown | If a 4th plan emerges (e.g., refresh integration as standalone), still consistent with Phase 3's 4-plan precedent. |

---

## Open Questions

1. **Token refresh integration: keep or skip?**
   - What we know: `BlueskyOAuthClient::refreshToken()` is implemented but never called. CONTEXT D-28's call-site description (in upsertBlueskyIdentity, before resolveProfile) doesn't fit the actual login flow (resolveProfile uses the brand-new access_token from token exchange, not a stored one).
   - What's unclear: Whether the user wants the refresh path **exercised** at login time (Resolution C) for "ready when needed" rationale, or **descoped** (Resolution A) since no live use-case exists.
   - **Recommendation:** Surface this as an open question in the plan, default to Resolution A (descope, document) unless user explicitly requests Resolution C. Either way, the Phase 4 deliverables that DO matter (moderation + deploy) are not blocked.

2. **`uk_reports_reporter_message` migration vs pure app-layer dedupe (D-12)?**
   - What we know: CONTEXT defers to planner. Both work.
   - What's unclear: Whether to add 1 migration file vs not. Migration is cleaner.
   - **Recommendation:** Add the migration. Cost is 1 file (~30 lines), benefit is single-source-of-truth on the constraint and race-safety via DatabaseException catch.

3. **Block dashboard list section placement (D-04)?**
   - What we know: UI-SPEC §3 recommends right column below settings.
   - What's unclear: Whether it's a single dashboard.php template extension or a partial element.
   - **Recommendation:** Add as `templates/element/block_list.php` partial called from `dashboard.php`. Keeps `dashboard.php` reasoning simple and follows Phase 3 `inbox_settings_form` pattern.

4. **Final `deleted_reason` literals in code (D-22)?**
   - What we know: `'user_deleted'` for receiver delete, `'admin_action'` (or similar) for ops.
   - **Recommendation:** Define both as const class on `MessagesTable`:
   ```php
   public const DELETED_REASON_USER = 'user_deleted';
   public const DELETED_REASON_ADMIN = 'admin_action';
   ```
   So the ops SQL UPDATE can reference the same literal. Documented in MessagesTable docblock.

---

## Environment Availability

> Phase 4 production launch makes external dependencies critical. Local dev environment (this VPS) is also relevant for tests + smoke prep.

| Dependency | Required By | Available (VPS dev) | Available (Lolipop prod, projected) | Version | Fallback |
|------------|------------|-----------|---------|---------|----------|
| PHP 8.1+ | composer.json `^8.0`; CakePHP 4.5 ORM | ✓ (PHP 8.3.6 per project memory) | ✓ (Lolipop high-speed plan PHP 8.1/8.2/8.3 confirmed) | 8.3 | 8.0 acceptable per `composer.json ^8.0` |
| MySQL 8.0 | Schema CHECK constraints | ✓ (per project memory `MySQL 8.0.45`) | ✓ (Lolipop default) | 8.0 | none |
| Composer | dev `composer test`, prod `composer install --no-dev` | ✓ (Composer 2.7.1 per memory) | ✓ (downloadable to home dir) | 2.x | none |
| Apache `mod_rewrite` | `.htaccess` rewrite to `webroot/` | ✓ | ✓ (Lolipop standard) | n/a | none |
| OpenSSL | ES256 sign + AES-GCM | ✓ (libopenssl bundled with PHP) | ✓ | n/a | none |
| Git (server-side) | Lolipop `post-receive` hook | ✓ | ✓ (Lolipop SSH gateway) | 2.x | none |
| SSH | Manual deploy ops + initial setup | ✓ | ✓ (Lolipop SSH gateway, port 2222 typically) | n/a | none |
| Bluesky AT Protocol | Live OAuth handshake (D-35 manual smoke) | n/a (mocked in tests) | n/a (production reaches via HTTPS to bsky.social) | live | none — manual test only |

**Missing dependencies with no fallback:** None — all dependencies are verified available or sourced from already-shipping components.

**Missing dependencies with fallback:** None.

---

## Recommended Plan Breakdown

(For the planner — based on this research, not a locked plan structure):

### Plan 04-01: moderation-crud (Wave 1, ~6 tasks)
1. `BlocksTable::isBlocked()` finder + integration test
2. `BlocksController::create()` real body + test (replaces 501 stub)
3. `BlocksController::delete()` new action + test
4. `MessagesController::send()` modify for block check (D-05/06) + test
5. `MessagesController::delete()` (or new `Dashboard::deleteMessage`) + test (MSG-08)
6. `UsersController::dashboard()` modify for `deleted_at IS NULL` filter + block list (`templates/element/block_list.php`) + 通報済 badge

### Plan 04-02: report-and-account (Wave 2, ~5 tasks)
1. Migration `AddReporterMessageUniqueToReports`
2. `ReportsController::create($messageId)` GET+POST + test
3. New `templates/Reports/create.php` (UI-SPEC §4)
4. `AccountController::delete()` GET+POST + test
5. `templates/Account/delete.php` + settings danger-zone link
6. `InboxesTable::findBySlugOrPrevious` modify for `users.deleted_at IS NULL` + test
7. CSS append (UI-SPEC §1-§9, ~150-200 lines)

### Plan 04-03: token-refresh-or-descope (Wave 2, conditional)
- **If Resolution A (descope):** 1 task to update STATE.md + ROADMAP.md sticky-note resolution. ~10 min.
- **If Resolution C (keep):** 3-4 tasks for upsertBlueskyIdentity refresh wiring + 3 BlueskyOAuthClientTest tests + integration test for silent logout.

### Plan 04-04: production-launch-runbook (Wave 3, manual)
1. `config/app.php` confirm `debug` env-driven (verify Phase 1 already does this)
2. Production `.env.example` template with all keys (`DEBUG=false`, `BLUESKY_*`, `OAUTH_KID`, `TOKEN_ENC_KEY`, `SERVER_SECRET`, `DATABASE_URL`)
3. `LAUNCH-RUNBOOK.md` with D-37 ordered steps + Lolipop-specific commands
4. `MANUAL-SMOKE-CHECKLIST.md` with D-35 walkthrough (10 numbered checkboxes covering signup → block → report → delete → 退会 + Phase 2/3 carried-over 3 human items)
5. Manual smoke execution by user, results captured in VERIFICATION.md

---

## Sources

### Primary (HIGH confidence — direct code/spec verification)

- `/home/claude/projects/tamabox/src/Service/OAuth/Bluesky/BlueskyOAuthClient.php` — already-implemented refreshToken (lines 156-187)
- `/home/claude/projects/tamabox/src/Model/Table/UserIdentitiesTable.php` — upsertBlueskyIdentity (lines 157-318)
- `/home/claude/projects/tamabox/src/Model/Table/InboxesTable.php` — findBySlugOrPrevious (lines 147-168), assignSlugForUser (lines 190-300)
- `/home/claude/projects/tamabox/src/Model/Table/BlocksTable.php` — schema, BlockerUsers/BlockedUsers aliases
- `/home/claude/projects/tamabox/src/Model/Table/ReportsTable.php` — schema, validators
- `/home/claude/projects/tamabox/src/Model/Table/MessagesTable.php` — markOpened pattern (model for delete)
- `/home/claude/projects/tamabox/src/Controller/MessagesController.php` — send/open/report 501 stub
- `/home/claude/projects/tamabox/src/Controller/BlocksController.php` — 501 stub
- `/home/claude/projects/tamabox/src/Controller/OauthController.php` — callback flow + setIdentity
- `/home/claude/projects/tamabox/src/Application.php` — middleware queue, AuthenticationService
- `/home/claude/projects/tamabox/src/Service/OAuth/TokenEncryptionService.php` — AES-GCM
- `/home/claude/projects/tamabox/config/Migrations/20260422120001_CreateUsers.php` — `users.deleted_at` column
- `/home/claude/projects/tamabox/config/Migrations/20260422120002_CreateUserIdentities.php` — `token_expires_at` plaintext (Pitfall 2)
- `/home/claude/projects/tamabox/config/Migrations/20260422120003_CreateInboxes.php` — NO `deleted_at` column (Pitfall 1)
- `/home/claude/projects/tamabox/config/Migrations/20260422120004_CreateMessages.php` — `deleted_at`, `deleted_reason`, body CHECK
- `/home/claude/projects/tamabox/config/Migrations/20260422120005_CreateBlocks.php` — UNIQUE pair, no_self CHECK, CASCADE FKs
- `/home/claude/projects/tamabox/config/Migrations/20260422120006_CreateReports.php` — ENUMs, SET NULL FK, NO existing UNIQUE on (reporter_user_id, message_id)
- `/home/claude/projects/ssr-box-discovery/AUTH-FLOW.md` §8 (Refresh) — DPoP-bound, single-use refresh tokens, force logout on invalidation
- `/home/claude/projects/ssr-box-discovery/DB-SCHEMA.md` v0.2 §3-§6 — DDL truth source (confirmed inboxes has no deleted_at)
- `.planning/phases/04-moderation-production-launch/04-CONTEXT.md` — D-01..D-40 locked decisions
- `.planning/phases/04-moderation-production-launch/04-UI-SPEC.md` — visual contract for §1-§9
- `.planning/STATE.md` — Phase 2 verifier discoveries (mock pattern, fixtures, ORM caveats)

### Secondary (MEDIUM confidence — official spec / docs verified)

- [AT Protocol OAuth specification](https://atproto.com/specs/oauth) — refresh_token grant, DPoP rebind required, mandatory rotation, replay → revoke
- [Bluesky OAuth Improvements proposal](https://github.com/bluesky-social/proposals/blob/main/0004-oauth/README.md) — refresh form fields, error codes, DPoP nonce binding
- [Bluesky OAuth Client Guide](https://docs.bsky.app/docs/advanced-guides/oauth-client) — DPoP nonce 5-minute rotation, persist per session
- [CakePHP 4.x Deployment Guide](https://book.cakephp.org/4/en/deployment.html) — `composer install --no-dev`, post-migration cache clear
- [MySQL 8.0 CREATE INDEX](https://dev.mysql.com/doc/refman/8.0/en/create-index.html) — UNIQUE NULL semantics

### Tertiary (LOW confidence — community guides for Lolipop quirks, plan language verification)

- [Lolipop マニュアル PHP設定](https://lolipop.jp/manual/user/php-setting/) — PHP version per-domain
- [Lolipop ハイスピードプラン PHP 8.3 提供開始](https://lolipop.jp/info/news/8067/) — confirms PHP 8.3 available 2024-04+, ongoing patches into 2026
- [ロリポップでgitサーバ構築 SSH](https://hasethblog.com/it/programming/git/5465/) — bare repo + post-receive hook setup pattern
- [Lolipop Composer install pattern](https://here2.click/archives/13) — explicit PHP path for Composer

---

## Metadata

**Confidence breakdown:**

- **Standard stack:** HIGH — every component already exists and is verified through Phase 2-3 integration tests (163 tests / 439 assertions / 0 failures, phpcs 54/54, phpstan level 8 [OK]).
- **Architecture (moderation CRUD):** HIGH — direct extension of Phase 3 patterns; no novel design. The 9 phase requirements decompose cleanly into 6-7 controller actions.
- **Architecture (token refresh):** MEDIUM — code exists and is correct, but the **call-site** is questionable (Pattern 2 discussion). Recommend surfacing this as a discuss-phase question with a default of Resolution A.
- **Architecture (退会):** HIGH — single-row UPDATE, all ON-DELETE-* FKs are dormant under soft-delete.
- **Pitfalls:** HIGH — 12 pitfalls all derive from concrete file reads or verified docs.
- **Production deploy:** MEDIUM — runbook patterns are documented externally and cross-verified (Lolipop docs + community guides + altotoo precedent), but Lolipop-specific quirks (file ownership, exact PHP binary path on the assigned plan) need first-deploy confirmation. D-39 manual walkthrough is the validation step.

**Research date:** 2026-04-28
**Valid until:** 2026-05-28 (30 days — stable phase boundary, but Bluesky OAuth spec is technically "Developer Preview" so monitor for spec changes if launch slips beyond a month).

---

*Phase 4 research complete. Plan-phase can begin without further research blockers, with two CONTEXT slips flagged for planner attention (Pitfall 1 + Pitfall 2) and one open question (refresh keep-or-skip) to surface or default-resolve.*
