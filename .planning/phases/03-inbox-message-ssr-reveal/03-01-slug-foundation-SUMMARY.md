---
phase: 03-inbox-message-ssr-reveal
plan: "01"
subsystem: slug-foundation
tags:
  - slug
  - inbox
  - ssr
  - migration
  - service
  - foundation
dependency_graph:
  requires: []
  provides:
    - SlugDeriver::deriveFromHandle (pure handle→slug normalization)
    - SsrJudge::judge (deterministic SSR seed + judgement)
    - InboxesTable::findBySlugOrPrevious (slug + slug_previous fallback lookup)
    - InboxesTable::assignSlugForUser (collision-retry slug assignment)
    - inboxes.slug_previous column (migration applied)
    - Fixtures: alice / bob(renamed) / charlie inboxes + 3 messages
  affects:
    - Wave 2: MessagesController::send will call SsrJudge::judge
    - Wave 3a: InboxesController will call findBySlugOrPrevious for 301 redirect
    - Wave 3a: OauthController will listen to SlugCollisionSuffixApplied event
tech_stack:
  added:
    - src/Service/Inbox/SlugDeriver.php (pure service)
    - src/Service/Message/SsrJudge.php (pure service)
    - config/Migrations/20260427120000_AddSlugPreviousToInboxes.php
    - tests/TestCase/Service/Inbox/SlugDeriverTest.php
    - tests/TestCase/Service/Message/SsrJudgeTest.php
  patterns:
    - Collision-retry loop with PersistenceFailedException + DatabaseException catch
    - CakePHP Event dispatch (SlugCollisionSuffixApplied) for session flag signal
    - TableRegistry::getTableLocator()->get() for cross-table access in Table classes
key_files:
  created:
    - src/Service/Inbox/SlugDeriver.php
    - src/Service/Message/SsrJudge.php
    - config/Migrations/20260427120000_AddSlugPreviousToInboxes.php
    - tests/TestCase/Service/Inbox/SlugDeriverTest.php
    - tests/TestCase/Service/Message/SsrJudgeTest.php
  modified:
    - src/Model/Table/InboxesTable.php
    - src/Model/Table/UserIdentitiesTable.php
    - src/Model/Entity/Inbox.php
    - tests/Fixture/InboxesFixture.php
    - tests/Fixture/MessagesFixture.php
    - tests/Fixture/UsersFixture.php
    - tests/TestCase/Model/Table/InboxesTableTest.php
    - phpstan.neon
decisions:
  - "D-04: Chose inboxes.slug_previous single column over inbox_slug_history table — 1-generation grace period only; multi-generation is MVP外 (CONTEXT.md deferred)"
  - "PersistenceFailedException retry: check entity errors['slug']['unique'] not just getPrevious() instanceof DatabaseException — CakePHP validation-layer unique check also throws PersistenceFailedException without a DatabaseException cause"
  - "TableRegistry::getTableLocator()->get() used in UserIdentitiesTable instead of fetchTable()/getTableLocator() — phpstan level 8 cannot resolve these methods from LocatorAwareTrait on Table subclasses"
  - "phpstan.neon ignoreErrors added for deadCode.unreachable + catch.neverThrown on InboxesTable — saveOrFail() throws PersistenceFailedException at runtime but phpstan cannot infer this from @method docblock"
metrics:
  duration: "~35 minutes"
  completed_date: "2026-04-26"
  tasks_completed: 4
  files_changed: 13
---

# Phase 03 Plan 01: Slug Foundation Summary

**One-liner:** Bluesky handle→slug normalization (D-01/D-02) + deterministic SSR judgement (D-09) + inboxes.slug_previous migration + collision-retry inbox assignment wired into identity upsert.

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | SlugDeriver service + unit tests | b888382 | src/Service/Inbox/SlugDeriver.php |
| 2 | SsrJudge service + unit tests | 3bb0598 | src/Service/Message/SsrJudge.php |
| 3 | Migration AddSlugPreviousToInboxes + Inbox entity | 24b2153 | config/Migrations/20260427120000_AddSlugPreviousToInboxes.php |
| 4 | InboxesTable extension + UserIdentitiesTable hook + fixtures + InboxesTableTest | f6e8af6 | InboxesTable.php, UserIdentitiesTable.php, fixtures |

## Test Results

- SlugDeriverTest: 10 tests, 10 assertions — OK
- SsrJudgeTest: 9 tests, 49 assertions — OK
- InboxesTableTest: 8 tests (1 incomplete pre-existing), 21 assertions — OK
- Full suite: 111 tests, 301 assertions, 6 incomplete (all pre-existing) — OK

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] SlugDeriver: handle starting with '.' not falling back**
- **Found during:** Task 1 test run
- **Issue:** `strtok('.atproto.example.com', '.')` returns `'atproto'` (PHP skips leading delimiter), not `''`. Plan expected fallback for handles starting with `.`
- **Fix:** Added explicit check `if ($handle !== '' && $handle[0] !== '.')` before calling `strtok`
- **Files modified:** src/Service/Inbox/SlugDeriver.php
- **Commit:** b888382

**2. [Rule 1 - Bug] MessagesFixture body_length mismatch**
- **Found during:** Task 4 fixture load
- **Issue:** MySQL CHECK constraint `body_length = char_length(body)` violated. Japanese strings `'開封済 SSR hit メッセージ'` has `char_length=17` (not 14) and `'開封済 SSR miss メッセージ'` has `char_length=18` (not 15)
- **Fix:** Corrected body_length values to match actual char_length
- **Files modified:** tests/Fixture/MessagesFixture.php
- **Commit:** f6e8af6

**3. [Rule 1 - Bug] PersistenceFailedException retry logic missing validation-layer unique check**
- **Found during:** Task 4 InboxesTableTest collision test
- **Issue:** CakePHP validation-layer unique check throws `PersistenceFailedException` with `getPrevious() === null` (no DatabaseException cause). Original plan's catch block only retried when `getPrevious() instanceof DatabaseException`, causing the collision suffix test to fail
- **Fix:** Changed retry condition to `isset($errors['slug']['unique']) || $cause instanceof DatabaseException`
- **Files modified:** src/Model/Table/InboxesTable.php
- **Commit:** f6e8af6

**4. [Rule 2 - Missing functionality] display_name column absent from inboxes schema**
- **Found during:** Task 4 implementation review
- **Issue:** Plan's `assignSlugForUser` passed `$displayName` to entity, but `display_name` column does not exist in the `inboxes` table schema (Phase 1 CreateInboxes migration)
- **Fix:** Removed `display_name` parameter and field from `assignSlugForUser`; slug is sufficient as display context
- **Files modified:** src/Model/Table/InboxesTable.php
- **Commit:** f6e8af6

**5. [Rule 3 - Blocking] phpstan level 8 errors on UserIdentitiesTable**
- **Found during:** phpstan verification
- **Issue:** `fetchTable()` and `getTableLocator()` are undefined methods per phpstan — `LocatorAwareTrait` not resolved from `Table` base class
- **Fix:** Used `TableRegistry::getTableLocator()->get('Inboxes')` with explicit `use Cake\ORM\TableRegistry` import
- **Files modified:** src/Model/Table/UserIdentitiesTable.php
- **Commit:** f6e8af6

**6. [Rule 3 - Blocking] phpstan dead-catch false positives on InboxesTable**
- **Found during:** phpstan verification
- **Issue:** phpstan cannot infer that `saveOrFail()` throws `PersistenceFailedException` from the `@method` docblock return type, reporting dead catch
- **Fix:** Added `ignoreErrors` entries for `deadCode.unreachable` and `catch.neverThrown` identifiers scoped to `InboxesTable.php` in `phpstan.neon`
- **Files modified:** phpstan.neon
- **Commit:** f6e8af6

## Sticky Notes for Wave 2

- `SsrJudge::judge` expects `$createdAtMicro` as `(new \DateTimeImmutable())->format('Y-m-d H:i:s.u')` string — Wave 2 `MessagesController::send` MUST format consistently using this exact pattern
- `SsrJudge` reads `Configure::read('Security.serverSecret')` — ensure `SERVER_SECRET` env var is set in all environments

## Sticky Notes for Wave 3a

- `SlugCollisionSuffixApplied` event is dispatched from `UserIdentitiesTable::upsertBlueskyIdentity` (both new-user and existing-user paths). Wave 3a Task 1 adds the listener (in `OauthController` or an event handler) that writes session key `Flash.slug_collision_suffix` for dashboard to consume per D-06
- `findBySlugOrPrevious` returns `['inbox' => $entity, 'redirect' => bool]` — controller should 301 redirect when `redirect === true`

## Fixture Oracle Values (fixed UUIDs for all Wave 2/3 integration tests)

| User | ID | Inbox slug | slug_previous | ssr_probability |
|------|----|------------|---------------|-----------------|
| alice | 11111111-1111-1111-1111-111111111111 | alice | null | 0.100 |
| bob (renamed) | 22222222-2222-2222-2222-222222222222 | bob-2 | bob | 0.500 |
| charlie (closed) | 33333333-3333-3333-3333-333333333333 | charlie | null | 1.000 |

Messages tied to alice's inbox (inbox_id=11111111-...):
- aaaa1111-...: unread, is_ssr=1
- aaaa2222-...: opened, is_ssr=1 (SSR hit)
- aaaa3333-...: opened, is_ssr=0 (SSR miss)

## Self-Check: PASSED

- src/Service/Inbox/SlugDeriver.php: FOUND
- src/Service/Message/SsrJudge.php: FOUND
- config/Migrations/20260427120000_AddSlugPreviousToInboxes.php: FOUND
- Commits b888382, 3bb0598, 24b2153, f6e8af6: all present (4/4)
