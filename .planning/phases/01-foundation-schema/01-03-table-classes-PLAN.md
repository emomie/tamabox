---
phase: 01-foundation-schema
plan: 03
type: execute
wave: 4
depends_on:
  - 01-02b
files_modified:
  - src/Model/Table/UsersTable.php
  - src/Model/Table/UserIdentitiesTable.php
  - src/Model/Table/InboxesTable.php
  - src/Model/Table/MessagesTable.php
  - src/Model/Table/BlocksTable.php
  - src/Model/Table/ReportsTable.php
  - src/Model/Entity/User.php
  - src/Model/Entity/UserIdentity.php
  - src/Model/Entity/Inbox.php
  - src/Model/Entity/Message.php
  - src/Model/Entity/Block.php
  - src/Model/Entity/Report.php
  - tests/Fixture/UsersFixture.php
  - tests/Fixture/UserIdentitiesFixture.php
  - tests/Fixture/InboxesFixture.php
  - tests/Fixture/MessagesFixture.php
  - tests/Fixture/BlocksFixture.php
  - tests/Fixture/ReportsFixture.php
  - tests/TestCase/Model/Table/UsersTableTest.php
  - tests/TestCase/Model/Table/UserIdentitiesTableTest.php
  - tests/TestCase/Model/Table/InboxesTableTest.php
  - tests/TestCase/Model/Table/MessagesTableTest.php
  - tests/TestCase/Model/Table/BlocksTableTest.php
  - tests/TestCase/Model/Table/ReportsTableTest.php
autonomous: true
requirements:
  - INFRA-07
tags:
  - cakephp
  - orm
  - bake
  - table-classes
  - phpstan

must_haves:
  truths:
    - "All 6 Table classes exist at src/Model/Table/<Name>Table.php under namespace App\\Model\\Table"
    - "All 6 Entity classes exist at src/Model/Entity/<Name>.php under namespace App\\Model\\Entity"
    - "TableRegistry::getTableLocator()->get('<Name>') resolves to the explicit class (not fallback) under allowFallbackClass(false)"
    - "Every Table class has Timestamp Behavior mapping created_at=new, updated_at=always (D-09)"
    - "Bake-emitted @property docblocks for UUID/FK columns are corrected to string (not int) per Pitfall 6"
    - "composer phpstan (level 8 on src/) exits 0"
    - "composer phpcs (CakePHP sniff) exits 0"
    - "composer test (PHPUnit skeleton + bake-generated stubs) exits 0"
  artifacts:
    - path: "src/Model/Table/UsersTable.php"
      provides: "UsersTable with Timestamp behavior and associations (hasOne UserIdentities, hasMany Inboxes, hasMany Messages sender_user_id, hasMany Blocks blocker/blocked)"
      contains: "class UsersTable extends Table"
    - path: "src/Model/Table/UserIdentitiesTable.php"
      provides: "UserIdentitiesTable with belongsTo Users"
      contains: "class UserIdentitiesTable extends Table"
    - path: "src/Model/Table/InboxesTable.php"
      provides: "InboxesTable with belongsTo Users, hasMany Messages"
      contains: "class InboxesTable extends Table"
    - path: "src/Model/Table/MessagesTable.php"
      provides: "MessagesTable with belongsTo Inboxes, belongsTo Users (sender), hasMany Reports"
      contains: "class MessagesTable extends Table"
    - path: "src/Model/Table/BlocksTable.php"
      provides: "BlocksTable with dual belongsTo Users (blocker + blocked, distinct aliases)"
      contains: "class BlocksTable extends Table"
    - path: "src/Model/Table/ReportsTable.php"
      provides: "ReportsTable with belongsTo Messages, belongsTo Users (reporter, nullable)"
      contains: "class ReportsTable extends Table"
    - path: "src/Model/Entity/User.php"
      provides: "User entity with @property string \\$id (UUID-corrected)"
      contains: "class User extends Entity"
  key_links:
    - from: "src/Application.php::bootstrap (allowFallbackClass(false))"
      to: "src/Model/Table/<Name>Table.php"
      via: "PSR-4 autoload (App\\Model\\Table\\<Name>Table → src/Model/Table/<Name>Table.php)"
      pattern: "namespace App.Model.Table"
    - from: "<Name>Table::initialize()"
      to: "CakePHP Timestamp Behavior"
      via: "addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created_at'=>'new','updated_at'=>'always']]])"
      pattern: "addBehavior..Timestamp"
    - from: "MessagesTable::initialize()"
      to: "UsersTable via sender_user_id"
      via: "belongsTo alias 'SenderUsers' foreignKey 'sender_user_id' className 'Users'"
      pattern: "belongsTo..SenderUsers|sender_user_id"
---

<objective>
migrations 適用済みの 6 テーブルに対して、明示的な `*Table` / `*Entity` クラスを `src/Model/Table/` / `src/Model/Entity/` に生成する。`TableLocator::allowFallbackClass(false)` 下でも `TableRegistry::getTableLocator()->get('<Name>')` が fallback なしで解決できる状態にする。

Purpose:
- INFRA-07 を閉じる ── 全 6 テーブル分の Table クラスが揃い、Phase 2 以降で Controller/Service からの ORM 利用が fatal なく動く (RESEARCH §Anti-Patterns "Forgetting TableLocator::allowFallbackClass(false) implications")。
- Timestamp Behavior (D-09) で created_at/updated_at を CakePHP ORM 経由でも自動設定 (DB 側 CURRENT_TIMESTAMP(6) との belt-and-suspenders)。
- Phase 2-4 実装者が bake 出力の誤った `@property int $id` に足を取られないよう、UUID 列を `string` に修正して PHPStan level 8 を通す。

Output:
- 6 Table + 6 Entity クラス (bake 生成 + UUID 型補正)
- 6 Fixture + 6 TableTest (bake デフォルト、後続 phase で拡張)
- `composer phpcs && composer phpstan && composer test` 全通過

**Non-goals (Phase 1 外):**
- UUID 生成 behavior (`Text::uuid()` in initialize hook) ← Phase 2 でユーザ作成時に必要になる
- `validationDefault()` での CHECK 予期書き (ORM 層検証) ← Phase 2-3 でコントローラが入力を受け始めた段階
- カスタム finder / association option 追加 ← 実装が必要になった時点 (Phase 3 dashboard など)
- snapshot 保持 behavior ← Phase 3 (MSG-04) MessagesTable に beforeSave hook として追加
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/01-foundation-schema/01-CONTEXT.md
@.planning/phases/01-foundation-schema/01-RESEARCH.md
@.planning/phases/01-foundation-schema/01-PATTERNS.md
@.planning/phases/01-foundation-schema/01-02b-SUMMARY.md
@.planning/codebase/CONVENTIONS.md
@.planning/codebase/ARCHITECTURE.md
@src/Application.php
@phpstan.neon
@phpcs.xml

<interfaces>
<!-- Key existing code the executor must align with -->

Application.php already sets allowFallbackClass(false) (around line 52-56):

    use Cake\ORM\Locator\TableLocator;
    use Cake\ORM\Locator\LocatorInterface;
    use Cake\Datasource\FactoryLocator;
    ...
    FactoryLocator::add('Table', (new TableLocator())->allowFallbackClass(false));

PagesController.php file header pattern (src/Controller/PagesController.php:1-17) — use this exact structure for new Table/Entity docblocks:

    <?php
    declare(strict_types=1);

    /**
     * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
     * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
     *
     * Licensed under The MIT License
     * For full copyright and license information, please see the LICENSE.txt
     * Redistributable under the terms of the MIT License.
     *
     * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
     * @link          https://cakephp.org CakePHP(tm) Project
     * @since         0.2.9
     * @license       https://opensource.org/licenses/mit-license.php MIT License
     */
    namespace App\...;

Canonical Timestamp Behavior block (D-09 + PATTERNS.md):

    $this->addBehavior('Timestamp', [
        'events' => [
            'Model.beforeSave' => [
                'created_at' => 'new',
                'updated_at' => 'always',
            ],
        ],
    ]);

PHPStan level 8 scope: src/ only (phpstan.neon), excludes src/Console/Installer.php. Tests and config/Migrations are NOT scanned by PHPStan.
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Bake all 6 Table/Entity/Fixture/Test class sets via bin/cake bake model</name>
  <read_first>
    - State of src/Model/Table/ and src/Model/Entity/ (must be empty except .gitkeep — Plan 02 did not touch these)
    - src/Application.php (confirm allowFallbackClass(false) + Bake plugin loaded in bootstrapCli — DO NOT edit)
    - .planning/phases/01-foundation-schema/01-RESEARCH.md: Pitfall 3 (bake is not merge-safe → bake first, commit, then edit), Architecture Patterns (Recommended Directory Changes)
    - .planning/phases/01-foundation-schema/01-PATTERNS.md: Table classes + Entity classes sections (post-bake checklist)
  </read_first>
  <files>Generated by bake — full list in files_modified frontmatter (24 files total: 6 Tables + 6 Entities + 6 Fixtures + 6 TableTests)</files>
  <action>
  Run `bin/cake bake model` for each of the 6 tables in FK-dependency order. Bake is NOT merge-safe (RESEARCH Pitfall 3) — if it needs to re-run, files must be deleted first. So we run it ONCE cleanly for all 6.

  Prerequisites (sanity check first):
  - `bin/cake --version` succeeds (Plan 01-01 made bin/cake executable via vendor install)
  - `bin/cake migrations status` shows 6 up (Plan 01-02b ran migrate)
  - `ls src/Model/Table/` shows only `.gitkeep` (virgin state)
  - `ls src/Model/Entity/` shows only `.gitkeep`

  Step 1: Bake each model with full default set (Table + Entity + Fixture + Test). The bake introspects the migrated schema to produce accurate associations and @property docblocks.

      bin/cake bake model Users --no-rules --no-actions 2>&1 | tee /tmp/bake_users.log
      bin/cake bake model UserIdentities --no-rules --no-actions 2>&1 | tee /tmp/bake_useridentities.log
      bin/cake bake model Inboxes --no-rules --no-actions 2>&1 | tee /tmp/bake_inboxes.log
      bin/cake bake model Messages --no-rules --no-actions 2>&1 | tee /tmp/bake_messages.log
      bin/cake bake model Blocks --no-rules --no-actions 2>&1 | tee /tmp/bake_blocks.log
      bin/cake bake model Reports --no-rules --no-actions 2>&1 | tee /tmp/bake_reports.log

     Flag explanations:
     - `--no-rules`: skip buildRules() method (application-level referential checks — we rely on DB FK for Phase 1; can add in Phase 2-3)
     - `--no-actions`: (if supported by this bake version) skip controller actions. If the flag is unrecognized, drop it — bake model shouldn't produce a controller anyway.
     - If bake prompts interactively despite CLI flags, pipe `yes ""` or `echo -e "\n\n\n"` as stdin and re-run (bake in recent versions is fully non-interactive when all tables are discoverable).

  Step 2: Confirm all 6 sets were generated:

      ls -1 src/Model/Table/*.php | grep -c Table   # must be 6
      ls -1 src/Model/Entity/*.php | grep -v gitkeep | wc -l   # must be 6
      ls -1 tests/Fixture/*Fixture.php | wc -l   # must be 6
      ls -1 tests/TestCase/Model/Table/*TableTest.php | wc -l   # must be 6

  Step 3: Verify each Table file has the correct associations based on DB FKs. Spot-check critical ones (don't edit — just verify bake introspected correctly):

      # MessagesTable should have BOTH belongsTo Inboxes AND belongsTo Users (sender alias)
      grep -E "belongsTo\('Inboxes'|belongsTo\('SenderUsers'|sender_user_id" src/Model/Table/MessagesTable.php

      # BlocksTable should have TWO belongsTo Users (blocker + blocked with distinct aliases)
      grep -cE "belongsTo\('\w+Users'" src/Model/Table/BlocksTable.php   # expected: 2

     If bake produced the wrong alias shape (e.g. two `belongsTo('Users')` rather than distinct aliases `BlockerUsers` + `BlockedUsers`), note the discrepancy — Task 3 fixes it.

  CRITICAL: Commit the raw bake output to git BEFORE editing (so Task 2 / Task 3 edits are reviewable as a separate diff). If running outside of git, just move forward; file_state is tracked by GSD.

  If bake fails with "table 'users' could not be found": confirm `bin/cake migrations status` shows up; confirm the dotenv-loaded DATABASE_URL matches the DB bake is introspecting (the same `tamabox` DB migrations ran against).
  </action>
  <verify>
    <automated>test -f src/Model/Table/UsersTable.php && test -f src/Model/Table/UserIdentitiesTable.php && test -f src/Model/Table/InboxesTable.php && test -f src/Model/Table/MessagesTable.php && test -f src/Model/Table/BlocksTable.php && test -f src/Model/Table/ReportsTable.php && test -f src/Model/Entity/User.php && test -f src/Model/Entity/UserIdentity.php && test -f src/Model/Entity/Inbox.php && test -f src/Model/Entity/Message.php && test -f src/Model/Entity/Block.php && test -f src/Model/Entity/Report.php && for f in src/Model/Table/*Table.php src/Model/Entity/*.php; do php -l "$f" | grep -q 'No syntax errors' || { echo "SYNTAX FAIL: $f"; exit 1; }; done && grep -q 'namespace App\\Model\\Table' src/Model/Table/UsersTable.php && grep -q 'namespace App\\Model\\Entity' src/Model/Entity/User.php</automated>
  </verify>
  <acceptance_criteria>
    - 6 Table class files exist: UsersTable, UserIdentitiesTable, InboxesTable, MessagesTable, BlocksTable, ReportsTable
    - 6 Entity class files exist: User, UserIdentity, Inbox, Message, Block, Report
    - 6 Fixture files exist in tests/Fixture/
    - 6 TableTest files exist in tests/TestCase/Model/Table/
    - `php -l` passes on all 12 Table+Entity files
    - Table files declare `namespace App\Model\Table`
    - Entity files declare `namespace App\Model\Entity`
    - MessagesTable contains association to Inboxes AND an FK-aware association to Users (sender alias)
  </acceptance_criteria>
  <done>All 6 Table/Entity/Fixture/Test sets baked from the migrated schema; files under src/Model/ syntactically valid; association introspection worked.</done>
</task>

<task type="auto">
  <name>Task 2: Post-bake Timestamp Behavior verification + patch (D-09)</name>
  <read_first>
    - All 6 src/Model/Table/*Table.php files (as just baked)
    - .planning/phases/01-foundation-schema/01-CONTEXT.md D-09
    - .planning/phases/01-foundation-schema/01-PATTERNS.md "Timestamp Behavior Mapping" section (canonical form)
  </read_first>
  <files>
    src/Model/Table/UsersTable.php,
    src/Model/Table/UserIdentitiesTable.php,
    src/Model/Table/InboxesTable.php,
    src/Model/Table/MessagesTable.php,
    src/Model/Table/BlocksTable.php,
    src/Model/Table/ReportsTable.php
  </files>
  <action>
  Ensure every Table class's `initialize()` method loads the CakePHP Timestamp Behavior with the tamabox-specific fields mapping (D-09). Bake MAY emit `$this->addBehavior('Timestamp')` by default (using the conventional 'created' / 'modified' field names), but tamabox uses 'created_at' / 'updated_at', so we must override.

  For EACH of the 6 Table files, inspect the initialize() method and apply ONE of these transforms:

  Case A — bake emitted `$this->addBehavior('Timestamp');` (no options):
    Replace the line with the canonical block:

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'updated_at' => 'always',
                ],
            ],
        ]);

  Case B — bake emitted no Timestamp Behavior at all:
    Add the canonical block immediately after `$this->setPrimaryKey('id');` and before any `belongsTo` / `hasMany` calls.

  Case C — bake emitted the canonical block already:
    No change.

  Case D — bake emitted something else (e.g. `'created' => 'created_at'`-style mapping):
    Normalize to the canonical form above. The normalization MUST use field names `created_at` and `updated_at` (snake_case with _at suffix) exactly matching the DB columns.

  After editing each file: `php -l <file>` must pass.

  Constraints:
  - Do NOT touch association methods (`belongsTo` / `hasMany` / `hasOne`) in this task — associations are handled in Task 3.
  - Do NOT add UUID-generation behavior or `beforeSave` hooks — those belong to Phase 2 (D-08, RESEARCH §Don't Hand-Roll).
  - Indentation is 4 spaces (phpcs / PSR-12).

  After all 6 Tables are patched:

      php -l src/Model/Table/*.php | grep -c 'No syntax errors'   # expect 6
      grep -c "'created_at' => 'new'" src/Model/Table/*.php | awk -F: '{ s += $2 } END { if (s == 6) print "OK"; else print "FAIL("s")" }'
  </action>
  <verify>
    <automated>php -l src/Model/Table/UsersTable.php | grep -q 'No syntax errors' && php -l src/Model/Table/UserIdentitiesTable.php | grep -q 'No syntax errors' && php -l src/Model/Table/InboxesTable.php | grep -q 'No syntax errors' && php -l src/Model/Table/MessagesTable.php | grep -q 'No syntax errors' && php -l src/Model/Table/BlocksTable.php | grep -q 'No syntax errors' && php -l src/Model/Table/ReportsTable.php | grep -q 'No syntax errors' && grep -lE "addBehavior\('Timestamp'" src/Model/Table/*Table.php | wc -l | grep -q '^6$' && grep -c "'created_at' => 'new'" src/Model/Table/*Table.php | awk -F: 'BEGIN{s=0} {s+=$2} END { exit (s==6?0:1) }' && grep -c "'updated_at' => 'always'" src/Model/Table/*Table.php | awk -F: 'BEGIN{s=0} {s+=$2} END { exit (s==6?0:1) }'</automated>
  </verify>
  <acceptance_criteria>
    - All 6 Table files pass `php -l`
    - All 6 Table files contain `addBehavior('Timestamp'`
    - Combined grep count of `'created_at' => 'new'` across the 6 Table files equals 6 (one per Table)
    - Combined grep count of `'updated_at' => 'always'` across the 6 Table files equals 6
    - No Table file contains `'created' => 'created_at'` (normalized away from any alt form)
  </acceptance_criteria>
  <done>Every Table class uses the canonical Timestamp Behavior mapping to created_at/updated_at. D-09 satisfied across all 6 tables.</done>
</task>

<task type="auto">
  <name>Task 3: Fix bake @property int→string for UUID/FK columns + verify associations + lint clean</name>
  <read_first>
    - All 6 src/Model/Entity/*.php files
    - All 6 src/Model/Table/*Table.php files
    - .planning/phases/01-foundation-schema/01-RESEARCH.md: Pitfall 6 (bake @property int $id for UUID column is wrong)
    - .planning/phases/01-foundation-schema/01-PATTERNS.md: Entity classes post-bake checklist
    - phpstan.neon (level 8 enforced on src/)
  </read_first>
  <files>
    src/Model/Entity/User.php,
    src/Model/Entity/UserIdentity.php,
    src/Model/Entity/Inbox.php,
    src/Model/Entity/Message.php,
    src/Model/Entity/Block.php,
    src/Model/Entity/Report.php,
    src/Model/Table/BlocksTable.php (if dual-User alias fix needed),
    src/Model/Table/MessagesTable.php (if sender alias fix needed),
    src/Model/Table/ReportsTable.php (if reporter alias fix needed)
  </files>
  <action>
  Three sub-fixes + one final validation, all required for PHPStan level 8 to pass.

  SUB-FIX A — Entity UUID property types (Pitfall 6):

  Bake generates `@property int $id` / `@property int $user_id` for CHAR(36) UUID columns because its template assumes BIGINT auto_increment. Every UUID column in tamabox must be typed `string` in PHPDoc.

  For each of the 6 Entity files, find and replace occurrences of `@property int` that refer to UUID columns and rewrite as `@property string`. UUID columns across the 6 entities:

  | Entity | UUID columns to fix |
  |--------|---------------------|
  | User.php | `id` |
  | UserIdentity.php | `id`, `user_id` |
  | Inbox.php | `id`, `user_id` |
  | Message.php | `id`, `inbox_id`, `sender_user_id` |
  | Block.php | `id`, `blocker_user_id`, `blocked_user_id` |
  | Report.php | `id`, `message_id`, `reporter_user_id` (note: reporter_user_id is nullable → `@property string|null $reporter_user_id`) |

  Careful — `@property int $ssr_probability` would be wrong too (it's DECIMAL), but bake usually gets decimals right as `float|string`. Scan for any other obviously-wrong PHPDoc types but limit changes to UUID+nullable-UUID to stay minimal.

  Example edit (Message.php):

  BEFORE (bake output):
      * @property int $id
      * @property int $inbox_id
      * @property int $sender_user_id
      * @property string $body

  AFTER:
      * @property string $id
      * @property string $inbox_id
      * @property string $sender_user_id
      * @property string $body

  SUB-FIX B — Table association aliases (verify + fix if bake was confused):

  Bake uses FK introspection to generate `belongsTo` / `hasMany`. For tables with MULTIPLE FKs to the same target, bake may produce two ambiguous same-named associations. Review these three Table files:

  (B1) `src/Model/Table/BlocksTable.php` — two FKs to users. Expected result:

      $this->belongsTo('BlockerUsers', [
          'className'  => 'Users',
          'foreignKey' => 'blocker_user_id',
          'joinType'   => 'INNER',
      ]);
      $this->belongsTo('BlockedUsers', [
          'className'  => 'Users',
          'foreignKey' => 'blocked_user_id',
          'joinType'   => 'INNER',
      ]);

     If bake produced two aliases both named 'Users' (invalid — CakePHP errors at boot) or used the wrong alias names, replace with the block above.

  (B2) `src/Model/Table/MessagesTable.php` — `inbox_id` FK + `sender_user_id` FK. Expected:

      $this->belongsTo('Inboxes', [
          'foreignKey' => 'inbox_id',
          'joinType'   => 'INNER',
      ]);
      $this->belongsTo('SenderUsers', [
          'className'  => 'Users',
          'foreignKey' => 'sender_user_id',
          'joinType'   => 'INNER',
      ]);

     Verify the alias is `SenderUsers` (NOT `Users`) and the className points to `Users`.

  (B3) `src/Model/Table/ReportsTable.php` — `message_id` FK (NOT NULL) + `reporter_user_id` FK (NULLABLE). Expected:

      $this->belongsTo('Messages', [
          'foreignKey' => 'message_id',
          'joinType'   => 'INNER',
      ]);
      $this->belongsTo('ReporterUsers', [
          'className'  => 'Users',
          'foreignKey' => 'reporter_user_id',
          'joinType'   => 'LEFT',
      ]);

     CRITICAL: The reporter association joinType MUST be 'LEFT' (not 'INNER') because `reporter_user_id` is nullable. Bake infers this from schema NULL allowability — if it produced 'INNER' anyway, change it.

  Corresponding inverse associations on UsersTable:

     $this->hasMany('SentMessages', ['className' => 'Messages', 'foreignKey' => 'sender_user_id']);
     $this->hasMany('BlockerBlocks', ['className' => 'Blocks', 'foreignKey' => 'blocker_user_id']);
     $this->hasMany('BlockedByBlocks', ['className' => 'Blocks', 'foreignKey' => 'blocked_user_id']);
     $this->hasMany('ReportsMade', ['className' => 'Reports', 'foreignKey' => 'reporter_user_id']);

     Apply only if bake produced duplicate-alias `hasMany('Messages')` / `hasMany('Blocks')` / `hasMany('Reports')` which CakePHP rejects at boot. Otherwise leave bake output as-is.

  SUB-FIX C — Quick lint + static analysis pass:

      composer phpcs 2>&1 | tee /tmp/phpcs.log
      echo "phpcs exit=$?"
      composer phpstan 2>&1 | tee /tmp/phpstan.log
      echo "phpstan exit=$?"
      composer test 2>&1 | tee /tmp/test.log
      echo "phpunit exit=$?"

  If phpcs fails (style violations in bake output): run `composer phpcs-fix` to auto-fix whitespace/brace issues, then re-run phpcs. Bake output is usually phpcs-clean but tamabox phpcs config is strict.

  If phpstan fails on `@property int $id` that Sub-Fix A missed: find the entity, correct it, rerun.

  If phpstan fails on CakePHP magic (e.g. `$this->Users` properties on Table classes): verify `cakedc/cakephp-phpstan` was installed in Plan 01-01 and that phpstan.neon includes its extension. If phpstan.neon does NOT include the extension, add (one-time edit, small):

      includes:
          - vendor/cakedc/cakephp-phpstan/extension.neon

     at the top of phpstan.neon. Commit that change.

  If phpunit fails on fixture loading: the baked fixtures reference the new migrated schema; Migrations\TestSuite\Migrator (in tests/bootstrap.php) should auto-apply migrations to test_tamabox DB. If fixtures fail with "table does not exist", Plan 01-02b migrations may have applied only to tamabox (not test_tamabox). Run: `DATABASE_URL="$DATABASE_TEST_URL" bin/cake migrations migrate` against test DB, then rerun composer test.
  </action>
  <verify>
    <automated>grep -E '@property (int|integer) \$(id|user_id|inbox_id|sender_user_id|blocker_user_id|blocked_user_id|message_id|reporter_user_id)' src/Model/Entity/*.php | wc -l | grep -q '^0$' && grep -cE '@property string \$id' src/Model/Entity/*.php | awk -F: 'BEGIN{s=0} {s+=$2} END { exit (s==6?0:1) }' && grep -q "belongsTo.'BlockerUsers'" src/Model/Table/BlocksTable.php && grep -q "belongsTo.'BlockedUsers'" src/Model/Table/BlocksTable.php && grep -qE "belongsTo.'SenderUsers'" src/Model/Table/MessagesTable.php && grep -qE "belongsTo.'ReporterUsers'" src/Model/Table/ReportsTable.php && grep -A 5 "ReporterUsers" src/Model/Table/ReportsTable.php | grep -q "'joinType' => 'LEFT'" && composer phpcs 2>&1 | tail -5 | grep -qE '(0 ERRORS|No fixable errors|passed)' && composer phpstan 2>&1 | tail -3 | grep -qE '(No errors|passed|\[OK\])' && composer test 2>&1 | tail -5 | grep -qE '(OK \(|Tests:.*Failures: 0)'</automated>
  </verify>
  <acceptance_criteria>
    - Zero occurrences of `@property int $<uuid_column>` in any src/Model/Entity/*.php file
    - Every src/Model/Entity/*.php file has exactly 1 line matching `@property string $id`
    - BlocksTable has `belongsTo('BlockerUsers')` AND `belongsTo('BlockedUsers')` (both explicit aliases, className 'Users')
    - MessagesTable has `belongsTo('SenderUsers')` with className 'Users'
    - ReportsTable has `belongsTo('ReporterUsers')` with `'joinType' => 'LEFT'` (nullable FK)
    - `composer phpcs` exits 0
    - `composer phpstan` exits 0 (level 8 on src/, with cakedc/cakephp-phpstan extension loaded)
    - `composer test` exits 0 (baked TableTest stubs pass; includes fixture-load smoke test)
  </acceptance_criteria>
  <done>Entity PHPDoc types corrected to string for UUIDs, ambiguous associations renamed to explicit aliases, PHPStan level 8 clean, phpcs clean, PHPUnit green.</done>
</task>

<task type="auto">
  <name>Task 4: Verify TableLocator resolves all 6 Tables (allowFallbackClass(false) compliance)</name>
  <read_first>
    - src/Application.php (allowFallbackClass(false) line — no edit)
    - All 6 src/Model/Table/*Table.php files
    - .planning/phases/01-foundation-schema/01-RESEARCH.md: Anti-Patterns "Forgetting TableLocator::allowFallbackClass(false) implications"
  </read_first>
  <files>(no file writes — this is a runtime verification task)</files>
  <action>
  Prove at runtime that every one of the 6 domain tables can be loaded via `TableRegistry::getTableLocator()->get(...)` WITHOUT falling back to a generic Cake\ORM\Table instance — because fallback is disabled and would fatal.

  Create a short CLI script as stdin to `bin/cake` REPL-style check. Simplest approach: a one-shot PHP script via the Cake app bootstrap.

  Option 1 — inline script (preferred, no files to clean up):

      php -r '
        require "vendor/autoload.php";
        $_SERVER["DOCUMENT_ROOT"] = __DIR__ . "/webroot";
        define("ROOT", __DIR__);
        define("APP_DIR", "src");
        require_once "config/requirements.php";
        require "config/bootstrap.php";
        foreach (["Users","UserIdentities","Inboxes","Messages","Blocks","Reports"] as $name) {
            $t = \Cake\ORM\Locator\LocatorAwareTrait::class; // force autoload
            $table = \Cake\ORM\TableRegistry::getTableLocator()->get($name);
            $class = get_class($table);
            $ok = $class === "App\\\\Model\\\\Table\\\\{$name}Table";
            echo sprintf("%-20s -> %s %s\n", $name, $class, $ok ? "[OK]" : "[FAIL]");
            if (!$ok) exit(1);
        }
        echo "ALL_OK\n";
      ' 2>&1 | tee /tmp/locator_check.log

     If the one-liner is fragile in this environment, fall back to Option 2:

  Option 2 — throwaway test file:

  Create `tests/TestCase/Model/LocatorSmokeTest.php` (temporary — can be deleted at end of this task or kept as ongoing regression guard):

      <?php
      declare(strict_types=1);

      namespace App\Test\TestCase\Model;

      use Cake\ORM\TableRegistry;
      use Cake\TestSuite\TestCase;

      /**
       * @internal Phase 1 locator sanity check — guards against allowFallbackClass(false) regressions.
       */
      class LocatorSmokeTest extends TestCase
      {
          /**
           * @return void
           */
          public function testAllPhaseOneTablesResolveToExplicitClasses(): void
          {
              $expected = [
                  'Users'          => \App\Model\Table\UsersTable::class,
                  'UserIdentities' => \App\Model\Table\UserIdentitiesTable::class,
                  'Inboxes'        => \App\Model\Table\InboxesTable::class,
                  'Messages'       => \App\Model\Table\MessagesTable::class,
                  'Blocks'         => \App\Model\Table\BlocksTable::class,
                  'Reports'        => \App\Model\Table\ReportsTable::class,
              ];
              foreach ($expected as $alias => $fqcn) {
                  $table = TableRegistry::getTableLocator()->get($alias);
                  $this->assertInstanceOf($fqcn, $table, "Alias '$alias' did not resolve to $fqcn (got " . get_class($table) . ")");
              }
          }
      }

  Then run only this test:

      vendor/bin/phpunit --filter testAllPhaseOneTablesResolveToExplicitClasses 2>&1 | tee /tmp/locator_test.log

     Must pass. This is the definitive proof for ROADMAP Phase 1 success criterion #5.

  Keep LocatorSmokeTest.php committed (serves as ongoing regression guard — if a future phase deletes a Table class, this test catches it immediately).

  Optional association smoke check (only if time permits — not gating):

      # Confirm association aliases are all resolvable (tests Task 3 Sub-Fix B correctness at runtime)
      php -r '
        require "vendor/autoload.php";
        require "config/bootstrap.php";
        $u = \Cake\ORM\TableRegistry::getTableLocator()->get("Users");
        foreach ($u->associations() as $assoc) {
            echo $assoc->getName(), " -> ", $assoc->getTarget()::class, "\n";
        }
      '

     (This is diagnostic; not a gate.)
  </action>
  <verify>
    <automated>test -f tests/TestCase/Model/LocatorSmokeTest.php && vendor/bin/phpunit --filter testAllPhaseOneTablesResolveToExplicitClasses 2>&1 | tee /tmp/locator_test.log | tail -3 | grep -qE '(OK \(|Tests: 1.*Failures: 0)'</automated>
  </verify>
  <acceptance_criteria>
    - `tests/TestCase/Model/LocatorSmokeTest.php` exists
    - `vendor/bin/phpunit --filter testAllPhaseOneTablesResolveToExplicitClasses` exits 0
    - Test log contains "OK (1 test" OR "Tests: 1, Failures: 0"
    - Each of the 6 aliases (Users, UserIdentities, Inboxes, Messages, Blocks, Reports) resolves to its corresponding `App\Model\Table\<Name>Table` class (no fallback instance)
  </acceptance_criteria>
  <done>All 6 Table classes proven resolvable via TableLocator under allowFallbackClass(false). INFRA-07 fully satisfied; ROADMAP Phase 1 success criterion #5 closed. LocatorSmokeTest committed as ongoing regression guard.</done>
</task>

</tasks>

<threat_model>
## Trust Boundaries

| Boundary | Description |
|----------|-------------|
| Phase 2+ controller code → ORM | Phase 2+ controllers call `TableRegistry::getTableLocator()->get('...')`; a missing Table class would fatal under allowFallbackClass(false). |
| bake-generated Entity $_accessible → mass-assignment | Entities default to `'*' => true` OR bake may emit explicit accessible map; if mis-set, mass-assignment attacks are possible in Phase 3. |

## STRIDE Threat Register

| Threat ID | Category | Component | Disposition | Mitigation Plan |
|-----------|----------|-----------|-------------|-----------------|
| T-01-15 | Denial of Service | Missing Table class at runtime (Phase 2+) | mitigate | Task 4 LocatorSmokeTest gates that all 6 Tables resolve; any regression (deleted file, renamed namespace) surfaces in CI. |
| T-01-16 | Tampering | Bake @property int $id misleads future devs + breaks PHPStan | mitigate | Task 3 Sub-Fix A explicitly corrects all UUID @property types to `string`. |
| T-01-17 | Elevation of Privilege | Entity mass-assignment of $id or $user_id via unsafe bake defaults | accept (Phase 3 will harden) | Phase 1 accepts bake defaults; Phase 3 will set `$_accessible = ['id' => false, 'user_id' => false, ...]` when controllers start accepting user input. Flag for Phase 3 review. |
| T-01-18 | Information Disclosure | LEFT join on ReporterUsers exposes report rows linked to deleted reporter (expected behavior, not a bug, but flagged for moderation UX) | accept | `fk_reports_reporter SET NULL` is by design (MOD-03). Phase 4 moderation UI will render "account removed" placeholder when reporter_user_id IS NULL. |
</threat_model>

<verification>
Phase-level verification (all tasks complete):

1. `ls src/Model/Table/ | grep -c Table.php` → 6
2. `ls src/Model/Entity/ | grep -v gitkeep | wc -l` → 6
3. `composer phpcs` → exit 0
4. `composer phpstan` → exit 0
5. `composer test` → exit 0 (includes LocatorSmokeTest)
6. `vendor/bin/phpunit --filter testAllPhaseOneTablesResolveToExplicitClasses` → exit 0

ROADMAP Phase 1 success criteria cross-check (all 5):
- [x] #1 composer.json ^8.0 + composer install OK (Plan 01-01 Task 1-2)
- [x] #2 .env loader active, env() resolves secrets (Plan 01-01 Task 3-4)
- [x] #3 httpoxy block active (Plan 01-01 Task 5)
- [x] #4 migrations migrate succeeds on MySQL 8.0 with UUID PK + utf8mb4_0900_ai_ci (Plan 01-02b Task 4)
- [x] #5 all *Table classes resolvable under allowFallbackClass(false) (Plan 01-03 Task 4)
</verification>

<success_criteria>
- [ ] 6 src/Model/Table/*Table.php files written (bake + Timestamp Behavior patch + alias fixes)
- [ ] 6 src/Model/Entity/*.php files written (bake + @property UUID type fix)
- [ ] 6 tests/Fixture/*Fixture.php + 6 tests/TestCase/Model/Table/*TableTest.php files exist (bake defaults, extended in later phases)
- [ ] tests/TestCase/Model/LocatorSmokeTest.php committed as ongoing regression guard
- [ ] Every Table class loads Timestamp Behavior with `'created_at' => 'new'` + `'updated_at' => 'always'`
- [ ] BlocksTable uses BlockerUsers/BlockedUsers aliases, MessagesTable uses SenderUsers alias, ReportsTable uses ReporterUsers alias with LEFT joinType
- [ ] composer phpcs exits 0
- [ ] composer phpstan exits 0 (level 8 on src/)
- [ ] composer test exits 0
- [ ] LocatorSmokeTest passes
</success_criteria>

<output>
After completion, create `.planning/phases/01-foundation-schema/01-03-SUMMARY.md` containing:
- List of 12 Table/Entity files with final byte-size or line-count
- Final associations map (what belongsTo/hasMany is wired where) — copy-paste from each Table's initialize() method
- composer phpcs + phpstan + test exit codes and abbreviated output
- LocatorSmokeTest pass confirmation
- Phase 1 ROADMAP success criteria checklist — all 5 marked ✓
- Handoff note to Phase 2: "All 6 Tables resolvable via TableLocator. Phase 2 OAuth work will add: (a) UUID-generation behavior via Text::uuid() in UsersTable/UserIdentitiesTable initialize(), (b) AES-GCM encrypt/decrypt for access_token_enc / refresh_token_enc columns on UserIdentitiesTable, (c) handle sync logic in UserIdentitiesTable from Bluesky AS response. Entity $_accessible hardening deferred to when controllers start accepting user input."
</output>
