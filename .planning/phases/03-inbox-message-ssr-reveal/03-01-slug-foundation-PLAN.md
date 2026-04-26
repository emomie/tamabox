---
phase: 03-inbox-message-ssr-reveal
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/Service/Inbox/SlugDeriver.php
  - src/Service/Message/SsrJudge.php
  - src/Model/Table/InboxesTable.php
  - src/Model/Table/UserIdentitiesTable.php
  - src/Model/Entity/Inbox.php
  - config/Migrations/20260427120000_AddSlugPreviousToInboxes.php
  - tests/Fixture/InboxesFixture.php
  - tests/Fixture/MessagesFixture.php
  - tests/TestCase/Service/Inbox/SlugDeriverTest.php
  - tests/TestCase/Service/Message/SsrJudgeTest.php
  - tests/TestCase/Model/Table/InboxesTableTest.php
autonomous: true
requirements:
  - INBOX-01
  - INBOX-06
  - MSG-03
tags:
  - slug
  - inbox
  - ssr
  - migration
  - service
  - foundation

must_haves:
  truths:
    - "SlugDeriver::deriveFromHandle('satie.bsky.social', 'did:plc:abc...') returns 'satie' (normalize: lowercase domain prefix, ASCII [a-z0-9_-] only)"
    - "SlugDeriver::deriveFromHandle('', 'did:plc:abc1234567890123456789012') returns 'user-' . substr(sha256(did), 0, 8) fallback when handle empty or non-slug-safe"
    - "InboxesTable::findBySlug($slug) returns Inbox entity if slug matches inboxes.slug; if not found, falls back to inboxes.slug_previous match (D-04 single-generation rename redirect); throws NotFoundException if neither matches"
    - "InboxesTable::assignUniqueSlug($baseSlug, $userId) wraps INSERT-or-UPDATE in Connection::transactional, retries up to 100 times on uk_inboxes_slug DatabaseException with -2/-3/...-N suffix, falls back to '<base>-<did_hash8>' on N=100 (D-02)"
    - "UserIdentitiesTable::upsertBlueskyIdentity (modified) — after INSERT/UPDATE of identity, derives slug via SlugDeriver from handle_cached + did, calls InboxesTable::assignSlugForUser(userId, derivedSlug, $isNewUser): on new user creates inbox row with default ssr_probability=0.100 / is_accepting=1; on existing user with handle change, UPDATEs inbox.slug + records old slug to inbox.slug_previous; flash 'collision' message (D-06) if suffix was applied (signal via session key)"
    - "SsrJudge::judge($messageId, $createdAtMicro, $probability) computes ssr_seed = sha256(server_secret . message_id . created_at_micro) (64 hex chars), is_ssr = hexdec(substr(seed, 0, 8)) / 0xFFFFFFFF < (float)probability; deterministic: same inputs → same output (D-09 / F2 audit invariant)"
    - "Migration AddSlugPreviousToInboxes adds VARCHAR(32) NULL column inboxes.slug_previous after slug, with CHECK regex matching slug rule, NULL allowed; down() removes it"
    - "InboxesFixture has 2+ records: alice (slug='alice', ssr_probability=0.100, is_accepting=1) + bob (slug='bob-2', slug_previous='bob', ssr_probability=0.500, is_accepting=1); MessagesFixture has unread + opened + ssr-hit + ssr-miss variants tied to alice's inbox"
    - "composer test green: SlugDeriverTest (≥6 cases: domain prefix / fallback / case normalize / empty handle / underscore handle / collision suffix delegated to caller), SsrJudgeTest (≥5 cases: determinism / boundary 0.0 / boundary 1.0 / mid threshold hit / mid threshold miss with fixed inputs), InboxesTableTest (findBySlug + slug_previous fallback + assignUniqueSlug retry)"
  artifacts:
    - path: "src/Service/Inbox/SlugDeriver.php"
      provides: "Pure handle→slug normalization + DID-hash fallback"
      min_lines: 60
      contains: "final class SlugDeriver"
    - path: "src/Service/Message/SsrJudge.php"
      provides: "Deterministic SSR seed + judgement (sha256 + hexdec / 0xFFFFFFFF)"
      min_lines: 40
      contains: "final class SsrJudge"
    - path: "src/Model/Table/InboxesTable.php"
      provides: "findBySlug (with slug_previous fallback) + assignUniqueSlug retry + assignSlugForUser hook + slug validation"
      contains: "findBySlug"
    - path: "src/Model/Table/UserIdentitiesTable.php"
      provides: "upsertBlueskyIdentity extended to wire SlugDeriver + InboxesTable on identity create/update (D-03 rename trigger)"
      contains: "SlugDeriver"
    - path: "config/Migrations/20260427120000_AddSlugPreviousToInboxes.php"
      provides: "ALTER inboxes ADD slug_previous VARCHAR(32) NULL with CHECK"
      contains: "slug_previous"
  key_links:
    - from: "UserIdentitiesTable::upsertBlueskyIdentity"
      to: "SlugDeriver::deriveFromHandle + InboxesTable::assignSlugForUser"
      via: "After identity save, derive slug from handle_cached + did, then assignSlugForUser inside same transaction"
      pattern: "SlugDeriver|assignSlugForUser"
    - from: "InboxesTable::assignUniqueSlug"
      to: "DatabaseException catch + suffix retry"
      via: "Connection::transactional + try save / catch DatabaseException / increment suffix"
      pattern: "DatabaseException"
    - from: "InboxesTable::findBySlug"
      to: "inboxes.slug_previous column"
      via: "Primary lookup on slug; if null, secondary lookup on slug_previous (D-04 single-generation redirect)"
      pattern: "slug_previous"
---

<objective>
Phase 3 の foundation を敷く。Wave 2 (送信フロー) と Wave 3 (ダッシュボード) が依存する純粋サービス + DB 拡張 + フィクスチャを揃える。

具体的には:
1. **`src/Service/Inbox/SlugDeriver.php`** (新規) — Bluesky handle → slug 自動導出 (D-01 ドメイン前部分採用 + 異常系の `user-<did_hash8>` フォールバック)。pure function only。
2. **`src/Service/Message/SsrJudge.php`** (新規) — D-09 deterministic algorithm (`sha256` + `hexdec / 0xFFFFFFFF`)。Wave 2 で MessagesController が呼ぶ。F2 監査性を担保。
3. **`config/Migrations/20260427120000_AddSlugPreviousToInboxes.php`** (新規) — `inboxes.slug_previous VARCHAR(32) NULL` 1 列追加 (D-04 ハンドラ単独で planner 判断 — 1 列で 1 世代分救う方針)。
4. **`InboxesTable`** 拡張 — `findBySlug`(slug → slug_previous フォールバック)、`assignUniqueSlug`(衝突 suffix 自動付与、最大 100 retry → did_hash8 fallback)、`assignSlugForUser`(receiver 識別から inbox 作成/更新ハブ)、validation rules 追加 (slug regex `[a-zA-Z0-9_-]{3,32}`)。
5. **`UserIdentitiesTable::upsertBlueskyIdentity`** 拡張 — handle 同期成功後に SlugDeriver + InboxesTable::assignSlugForUser を発火 (D-03)、衝突 suffix が付与されたら session に collision flag を立てる (D-06 dashboard で 1 度だけ flash)。
6. Fixtures 拡張 (Inboxes + Messages) — 後段プランの integration test で alice / bob の 2 inbox + 開封済 / 未開封 / SSR hit / miss 4 variants の Messages を提供。
7. Unit tests: SlugDeriverTest, SsrJudgeTest, InboxesTableTest (slug 関連の追加部分のみ)。

Purpose:
- ROADMAP Phase 3 success criteria #1 (slug 自動付与 + 衝突 suffix)、success criteria #5 (`ssr_seed` の数式) の foundation を確定
- INBOX-01 (slug 自動付与) と INBOX-06 (handle 改名追従) の **データ層** を完成 — 残る UI 層は Wave 3 で追加
- MSG-03 (`ssr_seed = sha256(server_secret + message_id + created_at)`) の **アルゴリズム層** を確定 — 呼び出し側は Wave 2

Output:
- 2 service クラス + 1 migration + 2 table 拡張 + 1 entity (Inbox accessibleFields 微修正) + 2 fixture + 3 test ファイル
- composer test green、phpstan level 8 [OK]、phpcs PASS
- Wave 2 と Wave 3 が以下に依存:
  - Wave 2 (送信): SsrJudge::judge を MessagesController::send で call
  - Wave 3a (dashboard): InboxesTable::findBySlug + assignSlugForUser、collision flash session key
  - Wave 3a (settings): InboxesTable validation rules (`ssr_probability`, `welcome_message`, `is_accepting`)

注意: SlugDeriver は **pure**(I/O ゼロ)。collision retry は InboxesTable 側に閉じる。test fixture 値は後段 integration test で固定 UUID を再利用するため、**ここで決めた UUID/値が後段の oracle になる**。
</objective>

<execution_context>
@/home/claude/.claude/get-shit-done/workflows/execute-plan.md
@/home/claude/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@/home/claude/projects/tamabox/.planning/STATE.md
@/home/claude/projects/tamabox/.planning/ROADMAP.md
@/home/claude/projects/tamabox/.planning/REQUIREMENTS.md
@/home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-CONTEXT.md
@/home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-UI-SPEC.md
@/home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-PATTERNS.md
@/home/claude/projects/tamabox/src/Service/OAuth/KeyManager.php
@/home/claude/projects/tamabox/src/Service/OAuth/Bluesky/DpopService.php
@/home/claude/projects/tamabox/src/Model/Table/UserIdentitiesTable.php
@/home/claude/projects/tamabox/src/Model/Table/InboxesTable.php
@/home/claude/projects/tamabox/src/Model/Entity/Inbox.php
@/home/claude/projects/tamabox/config/Migrations/20260422120003_CreateInboxes.php
@/home/claude/projects/tamabox/tests/Fixture/InboxesFixture.php
@/home/claude/projects/tamabox/tests/Fixture/MessagesFixture.php
@/home/claude/projects/tamabox/tests/TestCase/Service/OAuth/KeyManagerTest.php
@/home/claude/projects/tamabox/tests/TestCase/Service/OAuth/Bluesky/DpopServiceTest.php

<interfaces>
<!-- Phase 2 interfaces this plan consumes -->

UserIdentitiesTable::upsertBlueskyIdentity (existing, Plan 02-04):
  signature: upsertBlueskyIdentity(array $profile, array $tokens): User
  - profile keys: did (string), handle (string), avatar (?string), profile_url (string)
  - returns: \App\Model\Entity\User (with user_identity contained)
  - existing behavior: UPSERT users + user_identities in transactional()
  - this plan EXTENDS: after identity save, call SlugDeriver + InboxesTable::assignSlugForUser inside the SAME transaction

KeyManager (Plan 02-02 — pattern donor):
  Pure constructor with Configure-defaulted args: optional + falls back to Configure key

DpopService (Plan 02-02 — pattern donor for SsrJudge):
  - final class
  - private readonly KeyManager $keyManager (constructor promotion)
  - hash('sha256', ...) usage
  - Configure::read for client_id

Existing inboxes table schema (Phase 1 Plan 01-02a):
  id CHAR(36) UUID PK,
  user_id CHAR(36) UNIQUE FK→users CASCADE,
  slug VARCHAR(32) NOT NULL UNIQUE (CHECK [a-zA-Z0-9_-]{3,32}),
  display_name VARCHAR(64) NOT NULL,
  ssr_probability DECIMAL(4,3) NOT NULL DEFAULT 0.100 (CHECK 0..1),
  welcome_message VARCHAR(1000) NULL,
  is_accepting TINYINT(1) NOT NULL DEFAULT 1,
  created_at/updated_at DATETIME(6).

After this plan, schema gains:
  slug_previous VARCHAR(32) NULL (CHECK same regex OR NULL).

Existing user_identities columns (Phase 2):
  handle_cached VARCHAR(255) NOT NULL — basis for slug derivation
  provider_account_id VARCHAR(255) — DID for fallback hash
  last_synced_at DATETIME(6) — fresh per Phase 2 D-29

Phase 2 sticky note 1: $this->Authentication available in any AppController subclass.
Phase 2 sticky note 3: queryString/sessionString helpers for phpstan-safe getQuery/session reads.

Server secret reading:
  Configure::read('Security.serverSecret')  // from Phase 1 INFRA-02 .env loader; SERVER_SECRET env var

Slug regex (canonical, from CreateInboxes migration line 117-122):
  ^[a-zA-Z0-9_-]{3,32}$

Inbox default values (Phase 1 schema defaults — must NOT regress):
  ssr_probability = 0.100 (= 10% per ROADMAP Phase 3 #2)
  is_accepting = 1
  welcome_message = NULL
</interfaces>
</context>

<threat_model>
## Trust Boundaries

| Boundary | Description |
|----------|-------------|
| handle_cached → slug | Handle data is from Bluesky AS (validated upstream) but contains arbitrary unicode; slug derivation must NOT pass non-ASCII through to URL space |
| slug rename UPDATE | Concurrent identity-upserts could race on same slug; UNIQUE constraint + retry resolves |
| Configure.Security.serverSecret | Pre-existing secret (Phase 1 INFRA-02); SsrJudge consumes read-only |
| Migration DDL | Adds nullable column; no data migration; rollback safe |

## STRIDE Threat Register

| Threat ID | Category | Component | Disposition | Mitigation Plan |
|-----------|----------|-----------|-------------|-----------------|
| T-03-01-01 | Tampering (slug injection) | SlugDeriver output | mitigate | Output regex-locked to `[a-z0-9_-]{3,32}`; non-matching characters rejected → forces fallback `user-<did_hash8>`; SlugDeriverTest covers unicode/punctuation injection |
| T-03-01-02 | Spoofing (slug squatting via handle change) | InboxesTable::assignSlugForUser | mitigate | UNIQUE constraint on `inboxes.slug` + retry suffix; first-come-first-served documented in D-02; `slug_previous` retains old slug to prevent old URL hijack within same session |
| T-03-01-03 | Information Disclosure (server_secret leak via SSR judge) | SsrJudge | mitigate | server_secret never returned, only consumed inside hash(); ssr_seed (the hash output) is non-reversible; Configure-or-throw guard rejects empty secret at boot |
| T-03-01-04 | Denial-of-service (suffix retry loop) | InboxesTable::assignUniqueSlug | mitigate | Hard cap N=100 retries; on exhaustion, deterministic `<base>-<did_hash8>` fallback (collision rate negligible); InboxesTableTest covers fallback path |
| T-03-01-05 | Repudiation (SSR result manipulation) | SsrJudge | mitigate | Determinism: same (server_secret, message_id, created_at_micro) always produces same seed and is_ssr; F2 audit-replayable. Test asserts byte-equality across two judge() calls with identical inputs |
| T-03-01-06 | Tampering (migration data corruption) | AddSlugPreviousToInboxes | mitigate | Pure DDL ADD COLUMN with NULL default; no data backfill; CHECK constraint prevents bad values; down() reversible |
| T-03-01-07 | Information Disclosure (slug history leak) | findBySlug | accept | slug_previous lookup exposes that a renamed account once owned a URL; this is intentional UX (D-04 redirect grace), not a privacy leak — handles are public on Bluesky |

</threat_model>

<tasks>

<task type="auto">
  <name>Task 1: SlugDeriver service + unit tests</name>
  <files>src/Service/Inbox/SlugDeriver.php, tests/TestCase/Service/Inbox/SlugDeriverTest.php</files>

  <read_first>
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-CONTEXT.md sections "decisions" D-01 / D-02 / D-05 + "specifics" "Bluesky handle 正規化規則の境界ケース"
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-PATTERNS.md §4 SlugDeriver Pattern Assignments
    - /home/claude/projects/tamabox/src/Service/OAuth/KeyManager.php (analog: final class + Configure default + RuntimeException + private readonly)
    - /home/claude/projects/tamabox/tests/TestCase/Service/OAuth/KeyManagerTest.php (analog: typed private property + setUp + assertSame deterministic)
    - /home/claude/projects/tamabox/config/Migrations/20260422120003_CreateInboxes.php lines 117-122 (slug regex `^[a-zA-Z0-9_-]{3,32}$` — single source of truth)
  </read_first>

  <action>
**A. Create `src/Service/Inbox/SlugDeriver.php`** (final class, pure functions, no I/O).

```php
<?php
declare(strict_types=1);

namespace App\Service\Inbox;

/**
 * Bluesky handle → URL slug normalizer (Phase 3 INBOX-01 / INBOX-06 / D-01 / D-02).
 *
 * Pure deterministic transform. NO I/O — collision retry is the caller's
 * responsibility (InboxesTable::assignUniqueSlug).
 *
 * Algorithm (D-01):
 *   1. Take portion of handle BEFORE the first '.', e.g. 'satie.bsky.social' → 'satie'.
 *   2. Lowercase.
 *   3. If result matches /^[a-z0-9_-]{3,32}$/, use it.
 *   4. Else fall back to 'user-' . substr(sha256(did), 0, 8).
 *
 * Boundary cases:
 *   - empty handle → fallback
 *   - handle starting with '.' or '_' or non-ASCII → fallback
 *   - did = '' AND handle empty → throw RuntimeException (impossible input)
 */
final class SlugDeriver
{
    private const SLUG_REGEX = '/^[a-z0-9_-]{3,32}$/';

    /**
     * @param string $handle Bluesky handle, e.g. 'satie.bsky.social', 'you.example.com', or '' on AS error.
     * @param string $did Bluesky DID, e.g. 'did:plc:abcdefg...'. Required for fallback.
     * @return string Slug guaranteed to match the inboxes_slug_format CHECK regex (3-32 chars).
     * @throws \RuntimeException If both $handle and $did are empty.
     */
    public function deriveFromHandle(string $handle, string $did): string
    {
        if ($did === '' && $handle === '') {
            throw new \RuntimeException('SlugDeriver: both handle and did are empty.');
        }

        $candidate = strtolower(strtok($handle, '.') ?: '');
        if ($candidate !== '' && preg_match(self::SLUG_REGEX, $candidate) === 1) {
            return $candidate;
        }

        // Fallback — deterministic per-DID hash. did='' protected above.
        $hash = substr(hash('sha256', $did), 0, 8);
        return 'user-' . $hash;
    }
}
```

**B. Create `tests/TestCase/Service/Inbox/SlugDeriverTest.php`** with ≥7 cases:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\Inbox;

use App\Service\Inbox\SlugDeriver;
use Cake\TestSuite\TestCase;

class SlugDeriverTest extends TestCase
{
    private SlugDeriver $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new SlugDeriver();
    }

    public function testDomainPrefixExtraction(): void
    {
        $this->assertSame('satie', $this->svc->deriveFromHandle('satie.bsky.social', 'did:plc:abc'));
    }

    public function testCustomDomainHandle(): void
    {
        $this->assertSame('you', $this->svc->deriveFromHandle('you.example.com', 'did:plc:xyz'));
    }

    public function testCaseLowered(): void
    {
        $this->assertSame('satie', $this->svc->deriveFromHandle('SATIE.bsky.social', 'did:plc:abc'));
    }

    public function testEmptyHandleFallsBackToDidHash(): void
    {
        $did = 'did:plc:abc1234567890123456789012';
        $expected = 'user-' . substr(hash('sha256', $did), 0, 8);
        $this->assertSame($expected, $this->svc->deriveFromHandle('', $did));
    }

    public function testHandleStartingWithDotFallsBack(): void
    {
        $did = 'did:plc:bcd';
        $expected = 'user-' . substr(hash('sha256', $did), 0, 8);
        $this->assertSame($expected, $this->svc->deriveFromHandle('.atproto.example.com', $did));
    }

    public function testTwoCharHandleFallsBack(): void
    {
        // 'a' is too short (< 3 chars) — must fall back.
        $did = 'did:plc:short';
        $expected = 'user-' . substr(hash('sha256', $did), 0, 8);
        $this->assertSame($expected, $this->svc->deriveFromHandle('a.bsky.social', $did));
    }

    public function testNonAsciiHandleFallsBack(): void
    {
        $did = 'did:plc:utf';
        $expected = 'user-' . substr(hash('sha256', $did), 0, 8);
        $this->assertSame($expected, $this->svc->deriveFromHandle('たまばこ.bsky.social', $did));
    }

    public function testBothEmptyThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->svc->deriveFromHandle('', '');
    }

    public function testDeterminism(): void
    {
        $r1 = $this->svc->deriveFromHandle('', 'did:plc:abc');
        $r2 = $this->svc->deriveFromHandle('', 'did:plc:abc');
        $this->assertSame($r1, $r2);
    }

    public function testUnderscoresAndDashesAccepted(): void
    {
        // Domain part containing _ or - and within 3..32 chars stays as-is.
        $this->assertSame('foo_bar-baz', $this->svc->deriveFromHandle('foo_bar-baz.example.com', 'did:plc:zzz'));
    }
}
```

**C. Verify**: `composer test -- --filter SlugDeriverTest` PASS, `vendor/bin/phpstan analyse src/Service/Inbox/SlugDeriver.php` exit 0, `composer cs-check` PASS.
  </action>

  <acceptance_criteria>
    - `grep -c 'final class SlugDeriver' src/Service/Inbox/SlugDeriver.php` = 1
    - `grep -c 'preg_match(self::SLUG_REGEX' src/Service/Inbox/SlugDeriver.php` ≥ 1
    - `grep -c "'/^[a-z0-9_-]{3,32}\$/'" src/Service/Inbox/SlugDeriver.php` = 1
    - `grep -c 'public function deriveFromHandle' src/Service/Inbox/SlugDeriver.php` = 1
    - `grep -c 'hash(.sha256.,' src/Service/Inbox/SlugDeriver.php` ≥ 1
    - `grep -c 'RuntimeException' src/Service/Inbox/SlugDeriver.php` ≥ 1
    - `grep -E 'public function test[A-Z]' tests/TestCase/Service/Inbox/SlugDeriverTest.php | wc -l` ≥ 7
    - `composer test -- --filter SlugDeriverTest 2>&1 | grep -E 'OK \(|FAILURES'` shows OK
    - SlugDeriver has NO `use Cake\Http\Client` (pure, no I/O)
    - SlugDeriver has NO `Configure::read` calls (pure transform)
  </acceptance_criteria>

  <verify>
    <automated>composer test -- --filter SlugDeriverTest && vendor/bin/phpstan analyse src/Service/Inbox/SlugDeriver.php</automated>
  </verify>

  <done>SlugDeriver final class with deriveFromHandle returning slug per D-01 + did_hash8 fallback. 8+ unit tests pass. phpstan level 8 clean.</done>
</task>

<task type="auto">
  <name>Task 2: SsrJudge service + unit tests</name>
  <files>src/Service/Message/SsrJudge.php, tests/TestCase/Service/Message/SsrJudgeTest.php</files>

  <read_first>
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-CONTEXT.md D-09 (algorithm) / D-12 (no retroactive change) + Phase 1 STATE.md note "messages.ssr_seed VARCHAR(64) NOT NULL"
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-PATTERNS.md §5 SsrJudge Pattern Assignments
    - /home/claude/projects/tamabox/src/Service/OAuth/Bluesky/DpopService.php (analog: final class + private readonly DI + hash('sha256') + RuntimeException on missing config)
    - /home/claude/projects/tamabox/tests/TestCase/Service/OAuth/Bluesky/DpopServiceTest.php (analog: deterministic table-driven assertions + Configure::write in setUp)
  </read_first>

  <action>
**A. Create `src/Service/Message/SsrJudge.php`** (final class, deterministic, server-secret consumer).

```php
<?php
declare(strict_types=1);

namespace App\Service\Message;

use Cake\Core\Configure;
use RuntimeException;

/**
 * SSR (Super Rare reveal) judgement — D-09.
 *
 * Computes the deterministic seed and is_ssr flag at SEND time (MSG-02 contract).
 * The judgement is auditable via F2 invariant: given (server_secret, message_id,
 * created_at_micro), the seed and is_ssr are reproducible.
 *
 *   seed = sha256(server_secret . message_id . created_at_micro)  -- 64 hex chars
 *   rand01 = hexdec(substr(seed, 0, 8)) / 0xFFFFFFFF              -- in [0, 1)
 *   is_ssr = rand01 < probability                                  -- probability ∈ [0, 1]
 *
 * server_secret comes from Configure::read('Security.serverSecret') (Phase 1 INFRA-02
 * SERVER_SECRET env var). Empty secret → RuntimeException at call time.
 */
final class SsrJudge
{
    /**
     * @param string $messageId UUID of the message being sent (caller pre-generates via Text::uuid()).
     * @param string $createdAtMicro Microsecond-precision timestamp string, e.g. result of (new \DateTimeImmutable())->format('Y-m-d H:i:s.u').
     * @param string $probability Decimal string from inboxes.ssr_probability column, e.g. '0.100'.
     * @return array{ssr_seed: string, is_ssr: bool, ssr_probability_at_send: string}
     * @throws \RuntimeException If Security.serverSecret is empty.
     */
    public function judge(string $messageId, string $createdAtMicro, string $probability): array
    {
        $serverSecret = (string)Configure::read('Security.serverSecret', '');
        if ($serverSecret === '') {
            throw new RuntimeException('SsrJudge: Security.serverSecret is not configured.');
        }

        $seed = hash('sha256', $serverSecret . $messageId . $createdAtMicro);
        $rand01 = hexdec(substr($seed, 0, 8)) / 0xFFFFFFFF;
        $isSsr = $rand01 < (float)$probability;

        return [
            'ssr_seed' => $seed,
            'is_ssr' => $isSsr,
            'ssr_probability_at_send' => $probability,
        ];
    }
}
```

**B. Create `tests/TestCase/Service/Message/SsrJudgeTest.php`** with ≥6 cases:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\Message;

use App\Service\Message\SsrJudge;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

class SsrJudgeTest extends TestCase
{
    private SsrJudge $svc;
    private string $secret = 'test-secret-deterministic-32-chars!';

    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('Security.serverSecret', $this->secret);
        $this->svc = new SsrJudge();
    }

    public function testDeterminism(): void
    {
        $a = $this->svc->judge('msg1', '2026-04-27 12:00:00.000001', '0.500');
        $b = $this->svc->judge('msg1', '2026-04-27 12:00:00.000001', '0.500');
        $this->assertSame($a['ssr_seed'], $b['ssr_seed']);
        $this->assertSame($a['is_ssr'], $b['is_ssr']);
    }

    public function testSeedIs64HexChars(): void
    {
        $r = $this->svc->judge('m', 't', '0.500');
        $this->assertSame(64, strlen($r['ssr_seed']));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $r['ssr_seed']);
    }

    public function testProbabilityZeroAlwaysMisses(): void
    {
        // rand01 ∈ [0, 1); probability = 0 means rand01 < 0 is impossible.
        for ($i = 0; $i < 20; $i++) {
            $r = $this->svc->judge('msg-' . $i, 'now-' . $i, '0.000');
            $this->assertFalse($r['is_ssr'], "iter $i should miss at 0%");
        }
    }

    public function testProbabilityOneAlwaysHits(): void
    {
        // rand01 ∈ [0, 1); probability = 1 means rand01 < 1 is always true.
        for ($i = 0; $i < 20; $i++) {
            $r = $this->svc->judge('msg-' . $i, 'now-' . $i, '1.000');
            $this->assertTrue($r['is_ssr'], "iter $i should hit at 100%");
        }
    }

    public function testKnownSeedMatchesManualHash(): void
    {
        $expected = hash('sha256', $this->secret . 'fixed-id' . 'fixed-time');
        $r = $this->svc->judge('fixed-id', 'fixed-time', '0.500');
        $this->assertSame($expected, $r['ssr_seed']);
    }

    public function testProbabilityAtSendEchoed(): void
    {
        $r = $this->svc->judge('m', 't', '0.250');
        $this->assertSame('0.250', $r['ssr_probability_at_send']);
    }

    public function testEmptyServerSecretThrows(): void
    {
        Configure::write('Security.serverSecret', '');
        $this->expectException(\RuntimeException::class);
        $this->svc->judge('m', 't', '0.500');
    }

    public function testProbabilityBoundaryHit(): void
    {
        // Choose inputs where hexdec(substr(seed, 0, 8))/0xFFFFFFFF lands in a known half.
        // Construction: try a few message IDs; assert each result is consistent with manual computation.
        $msg = 'audit-replay-msg';
        $time = '2026-04-27 12:34:56.789012';
        $r = $this->svc->judge($msg, $time, '0.500');
        $manualRand = hexdec(substr($r['ssr_seed'], 0, 8)) / 0xFFFFFFFF;
        $this->assertSame($manualRand < 0.5, $r['is_ssr']);
    }
}
```

**C. Verify**: `composer test -- --filter SsrJudgeTest` PASS, `vendor/bin/phpstan analyse src/Service/Message/SsrJudge.php` exit 0.
  </action>

  <acceptance_criteria>
    - `grep -c 'final class SsrJudge' src/Service/Message/SsrJudge.php` = 1
    - `grep -c "hash('sha256', \$serverSecret \. \$messageId \. \$createdAtMicro)" src/Service/Message/SsrJudge.php` = 1
    - `grep -c 'hexdec(substr(\$seed, 0, 8)) / 0xFFFFFFFF' src/Service/Message/SsrJudge.php` = 1
    - `grep -c "Configure::read('Security.serverSecret'" src/Service/Message/SsrJudge.php` ≥ 1
    - `grep -c 'ssr_probability_at_send' src/Service/Message/SsrJudge.php` ≥ 1
    - `grep -c 'public function judge' src/Service/Message/SsrJudge.php` = 1
    - `grep -E 'public function test[A-Z]' tests/TestCase/Service/Message/SsrJudgeTest.php | wc -l` ≥ 7
    - `composer test -- --filter SsrJudgeTest 2>&1 | grep -E 'OK \(|FAILURES'` shows OK
  </acceptance_criteria>

  <verify>
    <automated>composer test -- --filter SsrJudgeTest && vendor/bin/phpstan analyse src/Service/Message/SsrJudge.php</automated>
  </verify>

  <done>SsrJudge final class with judge() returning {ssr_seed, is_ssr, ssr_probability_at_send} per D-09. ≥7 unit tests pass including determinism + boundary 0/1. phpstan level 8 clean.</done>
</task>

<task type="auto">
  <name>Task 3: Migration AddSlugPreviousToInboxes + Inbox entity accessibleFields update</name>
  <files>config/Migrations/20260427120000_AddSlugPreviousToInboxes.php, src/Model/Entity/Inbox.php</files>

  <read_first>
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-CONTEXT.md D-04 (slug history fallback) — planner picked single column over thin table
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-PATTERNS.md §12 Migration Pattern Assignments
    - /home/claude/projects/tamabox/config/Migrations/20260422120003_CreateInboxes.php (analog: addColumn pattern + raw SQL CHECK with `\$` escape, line 117-122)
    - /home/claude/projects/tamabox/src/Model/Entity/Inbox.php (current state — accessibleFields list)
  </read_first>

  <action>
**Decision rationale (recorded for D-04 selection)**: Choosing **`inboxes.slug_previous VARCHAR(32) NULL` single column** over `inbox_slug_history` table. Rationale: Phase 3 requirement is "1 generation grace period" (CONTEXT.md `<deferred>`: "複数世代追跡 → MVP 範囲外"). A single column models exactly that — no schema overhead, no JOIN cost on the lookup hot path, no orphaned-history rows on cascade delete. If multi-generation is ever needed (v2+), migration to a history table is forward-compatible.

**A. Create `config/Migrations/20260427120000_AddSlugPreviousToInboxes.php`**:

```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * AddSlugPreviousToInboxes — Phase 3 D-04 (single-generation slug rename redirect).
 *
 * Adds a nullable `slug_previous` column to `inboxes` to support 301-redirect from
 * the old slug after a Bluesky handle rename. CHECK constraint mirrors the existing
 * inboxes_slug_format regex (3..32 chars, [a-zA-Z0-9_-]) but allows NULL.
 *
 * Source of truth for the regex: Phase 1 CreateInboxes migration L117-122.
 * Constraint name uses snake_case without _check suffix (DB-SCHEMA.md v0.2 convention).
 */
class AddSlugPreviousToInboxes extends AbstractMigration
{
    public function up(): void
    {
        $this->table('inboxes')
            ->addColumn('slug_previous', 'string', [
                'limit' => 32,
                'null' => true,
                'default' => null,
                'after' => 'slug',
                'comment' => 'Previous slug retained for 1-generation 301 redirect after Bluesky handle rename (Phase 3 D-04).',
            ])
            ->addIndex(['slug_previous'], ['name' => 'idx_inboxes_slug_previous'])
            ->update();

        $this->execute(<<<SQL
ALTER TABLE inboxes
  ADD CONSTRAINT inboxes_slug_previous_format
  CHECK (slug_previous IS NULL OR slug_previous REGEXP '^[a-zA-Z0-9_-]{3,32}\$')
SQL);
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE inboxes DROP CONSTRAINT inboxes_slug_previous_format');
        $this->table('inboxes')
            ->removeIndexByName('idx_inboxes_slug_previous')
            ->removeColumn('slug_previous')
            ->update();
    }
}
```

**B. Update `src/Model/Entity/Inbox.php`** to include `slug_previous` in `_accessible`:

Find the `_accessible` array (Phase 1 baked) and add `'slug_previous' => true` next to `'slug' => true`. If the array already contains `'*' => true`, no change needed — but most baked entities list fields explicitly. Read first, confirm current shape, then add the line.

**C. Run migration**:
```bash
bin/cake migrations migrate
bin/cake migrations status   # confirm 20260427120000_AddSlugPreviousToInboxes is up
```

If running on test DB, also run `bin/cake migrations migrate -c test` (per Phase 1 convention, see `tests/bootstrap.php`).

**D. Verify schema via INFORMATION_SCHEMA**:
```bash
bin/cake schema | grep slug_previous   # OR: query directly
mysql -u <user> -p<pw> <db> -e "SELECT COLUMN_NAME, IS_NULLABLE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='inboxes' AND COLUMN_NAME='slug_previous';"
mysql -u <user> -p<pw> <db> -e "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS WHERE CONSTRAINT_NAME='inboxes_slug_previous_format';"
```

If `bin/cake migrations` fails because `config/app_local.php` is missing, re-create from `config/app_local.example.php` (Phase 1 deviation #12 — STATE.md Resolved Blockers).
  </action>

  <acceptance_criteria>
    - `ls config/Migrations/20260427120000_AddSlugPreviousToInboxes.php` exists
    - `grep -c 'class AddSlugPreviousToInboxes extends AbstractMigration' config/Migrations/20260427120000_AddSlugPreviousToInboxes.php` = 1
    - `grep -c "addColumn('slug_previous', 'string'" config/Migrations/20260427120000_AddSlugPreviousToInboxes.php` = 1
    - `grep -c 'inboxes_slug_previous_format' config/Migrations/20260427120000_AddSlugPreviousToInboxes.php` = 2  # CREATE + DROP
    - `grep -c "REGEXP '\^\[a-zA-Z0-9_-\]{3,32}" config/Migrations/20260427120000_AddSlugPreviousToInboxes.php` = 1
    - `grep -c 'idx_inboxes_slug_previous' config/Migrations/20260427120000_AddSlugPreviousToInboxes.php` = 2  # add + remove
    - `bin/cake migrations status 2>&1 | grep -c 'AddSlugPreviousToInboxes.*up'` = 1
    - `grep -c "'slug_previous' =>" src/Model/Entity/Inbox.php` ≥ 1   # _accessible entry
  </acceptance_criteria>

  <verify>
    <automated>bin/cake migrations status | grep AddSlugPreviousToInboxes && bin/cake migrations status -c test 2>&1 | grep AddSlugPreviousToInboxes || true</automated>
  </verify>

  <done>Migration applied to dev + test DBs. inboxes.slug_previous column exists with CHECK constraint and NULL default. Inbox entity _accessible includes slug_previous. composer test runs (Phase 1 + Phase 2 baseline still green; new tests will be added in later tasks).</done>
</task>

<task type="auto">
  <name>Task 4: InboxesTable extension (findBySlug + assignUniqueSlug + assignSlugForUser + validation) + UserIdentitiesTable hook + InboxesTableTest extension</name>
  <files>src/Model/Table/InboxesTable.php, src/Model/Table/UserIdentitiesTable.php, tests/Fixture/InboxesFixture.php, tests/Fixture/MessagesFixture.php, tests/TestCase/Model/Table/InboxesTableTest.php</files>

  <read_first>
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-CONTEXT.md D-02 (collision suffix retry up to 100, then `<base>-<did_hash8>`) / D-03 (handle change triggers slug recompute in upsertBlueskyIdentity) / D-04 (slug_previous fallback) / D-06 (collision flash session signal) / D-08 (DECIMAL(4,3)) / D-28 (welcome_message + is_accepting validation)
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-PATTERNS.md §6 InboxesTable + Shared Patterns "Validation (table layer)"
    - /home/claude/projects/tamabox/src/Model/Table/UserIdentitiesTable.php FULL FILE — analog for transactional + DatabaseException catch pattern (lines 154-275 referenced in PATTERNS.md §6)
    - /home/claude/projects/tamabox/src/Model/Table/InboxesTable.php FULL FILE — current validation rules (Phase 1 baked, lines 68-103)
    - /home/claude/projects/tamabox/tests/Fixture/InboxesFixture.php FULL FILE — current 1-record fixture
    - /home/claude/projects/tamabox/tests/Fixture/MessagesFixture.php FULL FILE — current shape
  </read_first>

  <action>
**A. Extend `src/Model/Table/InboxesTable.php`** with the following additions (preserve existing `initialize()`, associations, `Timestamp` behavior — DO NOT modify them):

1. **Add imports** (after existing `use` lines):
```php
use App\Service\Inbox\SlugDeriver;
use Cake\Database\Exception\DatabaseException;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\Query;
use Cake\Utility\Text;
use RuntimeException;
```

2. **Extend `validationDefault`** — add slug regex + ssr_probability range + welcome_message length + is_accepting + slug_previous (per D-08 / D-28):

```php
$validator
    ->scalar('slug')
    ->maxLength('slug', 32)
    ->minLength('slug', 3)
    ->add('slug', 'format', [
        'rule' => ['custom', '/^[a-zA-Z0-9_-]{3,32}$/'],
        'message' => 'スラッグは英数字とハイフン・アンダースコアのみ、3〜32文字で指定してください。',
    ]);

$validator
    ->scalar('slug_previous')
    ->allowEmptyString('slug_previous')
    ->add('slug_previous', 'format', [
        'rule' => ['custom', '/^[a-zA-Z0-9_-]{3,32}$/'],
        'message' => 'previous slug の形式が不正です。',
    ]);

$validator
    ->decimal('ssr_probability')
    ->greaterThanOrEqual('ssr_probability', 0.0, '確率は 0〜100 の整数で入力してください。')
    ->lessThanOrEqual('ssr_probability', 1.0, '確率は 0〜100 の整数で入力してください。');

$validator
    ->scalar('welcome_message')
    ->maxLength('welcome_message', 1000, 'welcome message は 1000 文字以内で入力してください。')
    ->allowEmptyString('welcome_message');

$validator
    ->boolean('is_accepting');

return $validator;
```

(Keep the existing `uuid('user_id')`, `uuid('id')`, etc. rules. Replace ONLY the slug + ssr_probability + welcome_message + is_accepting blocks; add slug_previous.)

3. **Add `findBySlug` finder method** (slug → slug_previous fallback per D-04):

```php
/**
 * Find an inbox by current slug, falling back to slug_previous (D-04 single-generation
 * grace period). Caller decides whether to 301-redirect when fallback hits.
 *
 * @param string $slug The slug from the URL.
 * @return array{inbox: \App\Model\Entity\Inbox, redirect: bool} redirect=true means slug_previous matched.
 * @throws \Cake\Http\Exception\NotFoundException If neither slug nor slug_previous matches.
 */
public function findBySlugOrPrevious(string $slug): array
{
    /** @var \App\Model\Entity\Inbox|null $inbox */
    $inbox = $this->find()
        ->where([$this->aliasField('slug') => $slug])
        ->contain(['Users' => ['UserIdentities']])
        ->first();
    if ($inbox !== null) {
        return ['inbox' => $inbox, 'redirect' => false];
    }

    /** @var \App\Model\Entity\Inbox|null $prev */
    $prev = $this->find()
        ->where([$this->aliasField('slug_previous') => $slug])
        ->contain(['Users' => ['UserIdentities']])
        ->first();
    if ($prev !== null) {
        return ['inbox' => $prev, 'redirect' => true];
    }

    throw new NotFoundException(__('受信箱が見つかりませんでした。'));
}
```

4. **Add `assignSlugForUser` method** (called by UserIdentitiesTable on identity upsert):

```php
/**
 * Create or update the inbox for a user, assigning a unique slug derived from the user's
 * Bluesky handle. Phase 3 D-03 (rename trigger) + D-02 (collision suffix retry) + D-06
 * (collision flash signal).
 *
 * On new user (no existing inbox row): INSERT with derived slug; if UNIQUE collision,
 * suffix `-2`, `-3`... up to N=100, then `<base>-<did_hash8>` fallback.
 *
 * On existing user with handle change (existing inbox.slug != newDerivedBase): UPDATE
 * inbox.slug to a new unique value, copy old slug → inbox.slug_previous.
 *
 * @param string $userId Owner user UUID.
 * @param string $derivedBaseSlug SlugDeriver::deriveFromHandle output for the user's handle.
 * @param string $did User's DID (for fallback hash if N=100 exhausted).
 * @param string $displayName Display name (slug derivation rule applied per D-05 — caller passes lowercased domain prefix or derived value).
 * @return array{slug: string, slug_changed: bool, suffix_applied: bool}
 *   slug: final slug stored.
 *   slug_changed: true if existing inbox got a new slug (rename).
 *   suffix_applied: true if base collided and a -N suffix was used.
 *
 * @throws \RuntimeException If retry exhausted AND fallback also collides (vanishingly rare).
 */
public function assignSlugForUser(string $userId, string $derivedBaseSlug, string $did, string $displayName): array
{
    /** @var \App\Model\Entity\Inbox|null $existing */
    $existing = $this->find()
        ->where([$this->aliasField('user_id') => $userId])
        ->first();

    $candidate = $derivedBaseSlug;
    $suffixApplied = false;

    if ($existing === null) {
        // New inbox path.
        for ($n = 0; $n <= 100; $n++) {
            $tryslug = $n === 0 ? $candidate : ($candidate . '-' . ($n + 1));
            try {
                $entity = $this->newEntity([
                    'id' => Text::uuid(),
                    'user_id' => $userId,
                    'slug' => $tryslug,
                    'display_name' => $displayName,
                    // schema defaults (DB) — set explicitly for clarity:
                    'ssr_probability' => '0.100',
                    'is_accepting' => true,
                ], ['accessibleFields' => [
                    'id' => true, 'user_id' => true, 'slug' => true,
                    'display_name' => true, 'ssr_probability' => true, 'is_accepting' => true,
                ]]);
                $this->saveOrFail($entity);
                return ['slug' => $tryslug, 'slug_changed' => false, 'suffix_applied' => $n > 0];
            } catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
                $cause = $e->getPrevious();
                if (!$cause instanceof DatabaseException) {
                    throw $e;
                }
                $suffixApplied = true;
                continue;
            } catch (DatabaseException $e) {
                $suffixApplied = true;
                continue;
            }
        }
        // Fallback to did_hash8 if N=100 exhausted (D-02).
        $fallback = $candidate . '-' . substr(hash('sha256', $did), 0, 8);
        $entity = $this->newEntity([
            'id' => Text::uuid(),
            'user_id' => $userId,
            'slug' => $fallback,
            'display_name' => $displayName,
            'ssr_probability' => '0.100',
            'is_accepting' => true,
        ], ['accessibleFields' => [
            'id' => true, 'user_id' => true, 'slug' => true,
            'display_name' => true, 'ssr_probability' => true, 'is_accepting' => true,
        ]]);
        $this->saveOrFail($entity);
        return ['slug' => $fallback, 'slug_changed' => false, 'suffix_applied' => true];
    }

    // Existing inbox path. Only rename if base differs.
    // Strip any existing -N suffix from existing slug for compare.
    $currentBase = preg_replace('/-\d+$/', '', (string)$existing->slug) ?? (string)$existing->slug;
    if ($currentBase === $derivedBaseSlug) {
        // Handle didn't actually change the base — keep current slug.
        return ['slug' => (string)$existing->slug, 'slug_changed' => false, 'suffix_applied' => false];
    }

    // Rename. Save current slug → slug_previous (D-04), then retry-update.
    $oldSlug = (string)$existing->slug;
    for ($n = 0; $n <= 100; $n++) {
        $tryslug = $n === 0 ? $candidate : ($candidate . '-' . ($n + 1));
        try {
            $patched = $this->patchEntity($existing, [
                'slug' => $tryslug,
                'slug_previous' => $oldSlug,
                'display_name' => $displayName,
            ], ['accessibleFields' => [
                'slug' => true, 'slug_previous' => true, 'display_name' => true,
            ]]);
            $this->saveOrFail($patched);
            return ['slug' => $tryslug, 'slug_changed' => true, 'suffix_applied' => $n > 0];
        } catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
            $cause = $e->getPrevious();
            if (!$cause instanceof DatabaseException) {
                throw $e;
            }
            $suffixApplied = true;
            continue;
        } catch (DatabaseException $e) {
            $suffixApplied = true;
            continue;
        }
    }
    $fallback = $candidate . '-' . substr(hash('sha256', $did), 0, 8);
    $patched = $this->patchEntity($existing, [
        'slug' => $fallback,
        'slug_previous' => $oldSlug,
        'display_name' => $displayName,
    ], ['accessibleFields' => [
        'slug' => true, 'slug_previous' => true, 'display_name' => true,
    ]]);
    $this->saveOrFail($patched);
    return ['slug' => $fallback, 'slug_changed' => true, 'suffix_applied' => true];
}
```

**B. Modify `src/Model/Table/UserIdentitiesTable.php::upsertBlueskyIdentity`** — after the existing inside-transactional save block (where `return $user;` happens at the end of the success branch), add a call to `assignSlugForUser` BEFORE the final return. Use the existing transaction (do NOT open a new one — InboxesTable::assignSlugForUser uses the same connection by default).

Pseudo-diff (locate the inner closure body):

```php
return $connection->transactional(
    function () use (...) : User {
        // ... existing UPSERT logic of users + user_identities ...

        // === Phase 3 addition: slug assignment hook ===
        $slugDeriver = new \App\Service\Inbox\SlugDeriver();
        $derivedBase = $slugDeriver->deriveFromHandle((string)$handle, (string)$did);
        $displayName = $derivedBase;  // D-05: display_name === slug base, lowercased domain prefix
        /** @var \App\Model\Table\InboxesTable $inboxesTable */
        $inboxesTable = $this->getTableLocator()->get('Inboxes');
        $slugResult = $inboxesTable->assignSlugForUser(
            (string)$user->id,
            $derivedBase,
            (string)$did,
            $displayName
        );
        // D-06: signal flash on collision suffix or rename-induced collision
        if ($slugResult['suffix_applied']) {
            $this->getEventManager()->dispatch(
                new \Cake\Event\Event('SlugCollisionSuffixApplied', $this, [
                    'user_id' => (string)$user->id,
                    'slug' => $slugResult['slug'],
                    'base' => $derivedBase,
                ])
            );
        }

        return $user;
    }
);
```

Then, in `OauthController::callback` (already implemented in Phase 2 Plan 02-04) — Wave 3a will add the listener that consumes `SlugCollisionSuffixApplied` event and writes a session flag (deferred to Wave 3a Task 1, NOT this task). For this task, just emit the event.

3. **Inboxes/Messages Fixtures** — extend with deterministic records used by all later integration tests:

`tests/Fixture/InboxesFixture.php`:
```php
public function init(): void
{
    $this->records = [
        // Existing record (preserve as-is from Phase 1 fixture).
        // alice — receiver with default 10% probability.
        [
            'id' => '11111111-1111-1111-1111-111111111111',
            'user_id' => '11111111-1111-1111-1111-111111111111',
            'slug' => 'alice',
            'slug_previous' => null,
            'display_name' => 'alice',
            'ssr_probability' => '0.100',
            'welcome_message' => null,
            'is_accepting' => 1,
            'created_at' => '2026-04-22 12:00:00.000000',
            'updated_at' => '2026-04-22 12:00:00.000000',
        ],
        // bob — receiver who renamed handle (slug_previous='bob' supports D-04 fallback).
        [
            'id' => '22222222-2222-2222-2222-222222222222',
            'user_id' => '22222222-2222-2222-2222-222222222222',
            'slug' => 'bob-2',
            'slug_previous' => 'bob',
            'display_name' => 'bob',
            'ssr_probability' => '0.500',
            'welcome_message' => 'メッセージありがとう!',
            'is_accepting' => 1,
            'created_at' => '2026-04-22 12:00:00.000000',
            'updated_at' => '2026-04-23 12:00:00.000000',
        ],
        // charlie — receiver with is_accepting=false for "受け付けていない" UI.
        [
            'id' => '33333333-3333-3333-3333-333333333333',
            'user_id' => '33333333-3333-3333-3333-333333333333',
            'slug' => 'charlie',
            'slug_previous' => null,
            'display_name' => 'charlie',
            'ssr_probability' => '1.000',
            'welcome_message' => null,
            'is_accepting' => 0,
            'created_at' => '2026-04-22 12:00:00.000000',
            'updated_at' => '2026-04-22 12:00:00.000000',
        ],
    ];
    parent::init();
}
```

**Required precursor fixtures:** `tests/Fixture/UsersFixture.php` must contain user IDs `11111111-...`, `22222222-...`, `33333333-...`. Read `tests/Fixture/UsersFixture.php` first; if those rows don't exist, ADD them. Same for `tests/Fixture/UserIdentitiesFixture.php` — needs an identity row tied to user `11111111-...` (handle_cached='alice.bsky.social', avatar_url_cached='https://example.com/alice.jpg', profile_url_cached='https://bsky.app/profile/alice.bsky.social').

`tests/Fixture/MessagesFixture.php`:
```php
public function init(): void
{
    $this->records = [
        // Unread message in alice's inbox (no opened_at).
        [
            'id' => 'aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'inbox_id' => '11111111-1111-1111-1111-111111111111',
            'sender_user_id' => '22222222-2222-2222-2222-222222222222',
            'body' => '未開封テストメッセージ',
            'body_length' => 11,
            'is_ssr' => 1,
            'ssr_probability_at_send' => '0.100',
            'ssr_seed' => str_repeat('a', 64),
            'sender_provider' => 'bluesky',
            'sender_handle_snapshot' => 'bob-2.bsky.social',
            'sender_avatar_url_snapshot' => 'https://example.com/bob.jpg',
            'sender_profile_url_snapshot' => 'https://bsky.app/profile/bob-2.bsky.social',
            'opened_at' => null,
            'deleted_at' => null,
            'deleted_reason' => null,
            'created_at' => '2026-04-23 10:00:00.000000',
        ],
        // Opened SSR-hit message in alice's inbox (opened_at set).
        [
            'id' => 'aaaa2222-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'inbox_id' => '11111111-1111-1111-1111-111111111111',
            'sender_user_id' => '22222222-2222-2222-2222-222222222222',
            'body' => '開封済 SSR hit メッセージ',
            'body_length' => 14,
            'is_ssr' => 1,
            'ssr_probability_at_send' => '0.500',
            'ssr_seed' => str_repeat('b', 64),
            'sender_provider' => 'bluesky',
            'sender_handle_snapshot' => 'bob-2.bsky.social',
            'sender_avatar_url_snapshot' => 'https://example.com/bob.jpg',
            'sender_profile_url_snapshot' => 'https://bsky.app/profile/bob-2.bsky.social',
            'opened_at' => '2026-04-23 11:00:00.000000',
            'deleted_at' => null,
            'deleted_reason' => null,
            'created_at' => '2026-04-23 10:30:00.000000',
        ],
        // Opened SSR-miss message in alice's inbox.
        [
            'id' => 'aaaa3333-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'inbox_id' => '11111111-1111-1111-1111-111111111111',
            'sender_user_id' => '22222222-2222-2222-2222-222222222222',
            'body' => '開封済 SSR miss メッセージ',
            'body_length' => 15,
            'is_ssr' => 0,
            'ssr_probability_at_send' => '0.100',
            'ssr_seed' => str_repeat('c', 64),
            'sender_provider' => 'bluesky',
            'sender_handle_snapshot' => 'bob-2.bsky.social',
            'sender_avatar_url_snapshot' => 'https://example.com/bob.jpg',
            'sender_profile_url_snapshot' => 'https://bsky.app/profile/bob-2.bsky.social',
            'opened_at' => '2026-04-23 11:30:00.000000',
            'deleted_at' => null,
            'deleted_reason' => null,
            'created_at' => '2026-04-23 10:45:00.000000',
        ],
    ];
    parent::init();
}
```

(These are baseline records; later plans may add more for specific paths.)

**C. Add InboxesTable tests** in `tests/TestCase/Model/Table/InboxesTableTest.php`:

```php
public function testFindBySlugOrPreviousFindsByCurrentSlug(): void
{
    $r = $this->Inboxes->findBySlugOrPrevious('alice');
    $this->assertSame('11111111-1111-1111-1111-111111111111', $r['inbox']->id);
    $this->assertFalse($r['redirect']);
}

public function testFindBySlugOrPreviousFallsBackToSlugPrevious(): void
{
    $r = $this->Inboxes->findBySlugOrPrevious('bob');  // bob renamed → bob-2; lookup of 'bob' hits slug_previous.
    $this->assertSame('22222222-2222-2222-2222-222222222222', $r['inbox']->id);
    $this->assertTrue($r['redirect']);
    $this->assertSame('bob-2', $r['inbox']->slug);
}

public function testFindBySlugOrPreviousNotFoundThrows(): void
{
    $this->expectException(\Cake\Http\Exception\NotFoundException::class);
    $this->Inboxes->findBySlugOrPrevious('does-not-exist');
}

public function testAssignSlugForUserNewInboxNoCollision(): void
{
    $newUserId = $this->Inboxes->Users->newEntity([...])  // OR fixture-add a 4th user, or use TableLocator to insert directly.
    // For brevity: Insert user via Users table, then call assignSlugForUser.
    // Assert: row created with slug='dave' for handle='dave.bsky.social'.
}

public function testAssignSlugForUserCollisionAppliesSuffix(): void
{
    // Pre-condition: alice already has slug='alice'. Call assignSlugForUser(newUserId, 'alice', didX, 'alice').
    // Assert: returned slug is 'alice-2', suffix_applied=true.
}

public function testAssignSlugForUserHandleRenameMovesSlugPrevious(): void
{
    // Pre-condition: alice has slug='alice'. Call with derivedBase='satie', didX, 'satie'.
    // Assert: alice's row now has slug='satie', slug_previous='alice', slug_changed=true.
}
```

(Fill in concrete UUIDs / arrange-act-assert per Phase 1 InboxesTableTest pattern.)

**D. Run**:
```bash
composer test -- --filter InboxesTableTest
composer test -- --filter SsrJudgeTest
composer test -- --filter SlugDeriverTest
composer test                   # full suite stays green
vendor/bin/phpstan analyse src/Model/Table/InboxesTable.php src/Model/Table/UserIdentitiesTable.php src/Service/Inbox/SlugDeriver.php src/Service/Message/SsrJudge.php
composer cs-check
```
  </action>

  <acceptance_criteria>
    - `grep -c 'public function findBySlugOrPrevious' src/Model/Table/InboxesTable.php` = 1
    - `grep -c 'public function assignSlugForUser' src/Model/Table/InboxesTable.php` = 1
    - `grep -c 'use App\\\\Service\\\\Inbox\\\\SlugDeriver' src/Model/Table/InboxesTable.php` = 1
    - `grep -c 'DatabaseException' src/Model/Table/InboxesTable.php` ≥ 2
    - `grep -c 'slug_previous' src/Model/Table/InboxesTable.php` ≥ 2
    - `grep -c 'use App\\\\Service\\\\Inbox\\\\SlugDeriver' src/Model/Table/UserIdentitiesTable.php` = 1   # in extended upsertBlueskyIdentity
    - `grep -c 'assignSlugForUser' src/Model/Table/UserIdentitiesTable.php` = 1
    - `grep -c 'SlugCollisionSuffixApplied' src/Model/Table/UserIdentitiesTable.php` = 1
    - `grep -c "'slug' => 'alice'" tests/Fixture/InboxesFixture.php` = 1
    - `grep -c "'slug_previous' => 'bob'" tests/Fixture/InboxesFixture.php` = 1
    - `grep -c "'slug' => 'charlie'" tests/Fixture/InboxesFixture.php` = 1
    - `grep -c "'is_accepting' => 0" tests/Fixture/InboxesFixture.php` = 1
    - `grep -c 'aaaa1111-aaaa' tests/Fixture/MessagesFixture.php` = 1
    - `grep -E 'public function test[A-Z]' tests/TestCase/Model/Table/InboxesTableTest.php | wc -l` ≥ 4 NEW (in addition to whatever Phase 1 baked)
    - `composer test 2>&1 | grep -E 'OK \(|FAILURES'` shows OK
    - `vendor/bin/phpstan analyse src/Model/Table/InboxesTable.php src/Model/Table/UserIdentitiesTable.php src/Service/Inbox/SlugDeriver.php src/Service/Message/SsrJudge.php 2>&1 | grep -c 'No errors'` = 1   OR exit code 0
    - `composer cs-check 2>&1 | grep -E 'FOUND.*ERROR'` shows zero errors
  </acceptance_criteria>

  <verify>
    <automated>composer test && vendor/bin/phpstan analyse src/Model/Table/InboxesTable.php src/Model/Table/UserIdentitiesTable.php src/Service/Inbox/SlugDeriver.php src/Service/Message/SsrJudge.php</automated>
  </verify>

  <done>InboxesTable has findBySlugOrPrevious + assignSlugForUser + extended validation. UserIdentitiesTable::upsertBlueskyIdentity dispatches SlugCollisionSuffixApplied event after slug assign. Fixtures expanded to 3 inboxes (alice / bob (renamed) / charlie (closed)) + 3 messages (unread / SSR hit / SSR miss). InboxesTableTest covers find / collision / rename. composer test green. phpstan level 8 [OK] across all 4 modified files.</done>
</task>

</tasks>

<verification>

After all tasks complete:

```bash
# Service unit tests
composer test -- --filter SlugDeriverTest
composer test -- --filter SsrJudgeTest
composer test -- --filter InboxesTableTest

# Full suite (Phase 1 + Phase 2 baseline must remain green)
composer test

# Static analysis
vendor/bin/phpstan analyse src/Service/Inbox/SlugDeriver.php src/Service/Message/SsrJudge.php src/Model/Table/InboxesTable.php src/Model/Table/UserIdentitiesTable.php

# Coding standards
composer cs-check

# Migration applied
bin/cake migrations status | grep AddSlugPreviousToInboxes
```

All MUST pass.

</verification>

<success_criteria>

This plan succeeds when:

1. SlugDeriver returns `'satie'` for `'satie.bsky.social'` and `'user-<hash8>'` for empty/non-conformant handles. Pure function — no I/O. (D-01 / D-02 fallback)
2. SsrJudge produces deterministic seed + is_ssr from (server_secret, message_id, created_at_micro). Same inputs → byte-equal seed. (D-09 / F2 audit invariant)
3. inboxes table has `slug_previous` column (NULL allowed, regex CHECK) deployed to dev + test DBs.
4. InboxesTable::findBySlugOrPrevious resolves both current slug and 1-generation-old slug → entity. NotFoundException on miss.
5. InboxesTable::assignSlugForUser handles new-user / existing-user-rename paths inside transaction with collision suffix retry up to 100 + did_hash8 fallback.
6. UserIdentitiesTable::upsertBlueskyIdentity now creates / renames inbox slug atomically with identity upsert. Emits `SlugCollisionSuffixApplied` event.
7. Fixtures: 3 inboxes (alice / bob with slug_previous='bob' / charlie is_accepting=0) + 3 messages (unread / SSR-hit-opened / SSR-miss-opened). All Wave 2/3 integration tests can rely on these fixed UUIDs.
8. composer test green (Phase 1 + Phase 2 + new Phase 3 tests). phpstan level 8 OK. phpcs PASS.

</success_criteria>

<output>
After completion, create `.planning/phases/03-inbox-message-ssr-reveal/03-01-SUMMARY.md` documenting:

- Files created (SlugDeriver, SsrJudge, migration) + modified (InboxesTable, UserIdentitiesTable, fixtures)
- Test counts (SlugDeriverTest N cases, SsrJudgeTest N cases, InboxesTableTest +N cases)
- Decision recorded: chose `inboxes.slug_previous` single column over `inbox_slug_history` table for D-04 (rationale: 1-generation grace period only)
- Sticky note for Wave 2: SsrJudge::judge expects `created_at_micro` as `(new \DateTimeImmutable())->format('Y-m-d H:i:s.u')` string — caller MUST format consistently (Wave 2 MessagesController::send uses this)
- Sticky note for Wave 3a: `SlugCollisionSuffixApplied` event is dispatched from UserIdentitiesTable; Wave 3a Task 1 adds the listener (in OauthController OR an event handler) that writes session key `Flash.slug_collision_suffix` for dashboard to consume per D-06
</output>
