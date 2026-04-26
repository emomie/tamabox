---
phase: 03-inbox-message-ssr-reveal
plan: 03b
type: execute
wave: 3
depends_on:
  - 03-02
files_modified:
  - src/Controller/BlocksController.php
  - templates/Pages/home.php
  - templates/element/flash/info.php
  - webroot/css/tamabox.css
  - webroot/img/default-avatar.svg
  - tests/TestCase/Controller/BlocksControllerTest.php
  - tests/TestCase/Controller/PagesControllerTest.php
autonomous: true
requirements:
  - INBOX-03
  - MSG-07
tags:
  - stub
  - block
  - css
  - asset
  - svg
  - landing
  - phase-4-handoff

must_haves:
  truths:
    - "BlocksController::create($senderUserId) returns withStatus(501)->withStringBody('Not Implemented') (D-35 Phase 4 hand-off contract); test asserts 501 — the assertion is the locked contract Phase 4 will replace"
    - "webroot/img/default-avatar.svg exists as a 64x64 viewBox SVG with a circular silhouette (UI-SPEC §7 spec): outer circle fill #E5E7EB, head circle fill #9CA3AF, shoulder path fill #9CA3AF; rendered when sender_avatar_url snapshot is empty OR loads with onerror fallback"
    - "webroot/css/tamabox.css gains Phase 3 ruleset (~200 lines appended after Phase 2 line 218): .send-form-page / .send-form / .consent-label / .char-counter / .inbox-self-notice / .welcome-message / .empty-state / .send-done-page / .send-done__lead / .send-done__actions / .dashboard-page (extended) / .dashboard-header / .receive-list / .receive-list-empty / .message-row / .message-row__head / .message-row__icon / .message-row__time / .message-row__preview / .message-row__body / .open-form / .ssr-reveal / .ssr-reveal__banner / .ssr-reveal__miss / .sender-card / .sender-card__avatar / .sender-card__handle / .button-destructive / .settings-form / .probability-control / .probability-suffix / .pagination / .message.info (flash variant) / .visually-hidden (verify still present from Phase 2) / 768px breakpoint media query"
    - ":root in tamabox.css gains 2 new tokens — --avatar-sm: 24px and --avatar-lg: 64px — inserted within the existing :root block (UI-SPEC §2 Spacing Scale Exceptions)"
    - "templates/Pages/home.php (Phase 2 'Bluesky でログイン' CTA) gains a brief explainer paragraph above the CTA per Phase 3 LP minor copy update; existing CTA + form structure preserved (no test regression in PagesControllerTest)"
    - "templates/element/flash/info.php (new) — info variant flash markup with .message.info class wrapper (UI-SPEC §11 — used for slug collision flash)"
    - "Integration test: BlocksControllerTest::testCreateReturns501Stub asserts POST /block/<senderUserId> returns 501 (regardless of authentication; the route is wired in Plan 03-02 routes.php)"
    - "PagesControllerTest::testHomePageDisplaysCTA still passes (no regression); IF the existing test asserts exact copy, the Phase 3 minor edit must keep that assertion green OR the test gets updated to reflect the new explainer (planner choice — minimum viable change is to keep the CTA wording invariant)"
  artifacts:
    - path: "src/Controller/BlocksController.php"
      provides: "501 stub class with create() method per D-35 hand-off contract"
      min_lines: 30
      contains: "withStatus(501)"
    - path: "webroot/img/default-avatar.svg"
      provides: "64x64 SVG fallback avatar"
      contains: "viewBox=\"0 0 64 64\""
    - path: "webroot/css/tamabox.css"
      provides: "Phase 3 component CSS appended; --avatar-sm + --avatar-lg in :root"
      contains: "--avatar-lg"
    - path: "templates/element/flash/info.php"
      provides: "Info flash element with .message.info class for slug collision banner"
      contains: "message info"
    - path: "templates/Pages/home.php"
      provides: "Existing Bluesky CTA preserved + brief Phase 3 explainer addition"
      contains: "Bluesky でログイン"
  key_links:
    - from: "templates/Users/dashboard.php (Plan 03-03a)"
      to: "/img/default-avatar.svg"
      via: "<img onerror=\"this.src='/img/default-avatar.svg'\"> avatar fallback"
      pattern: "default-avatar.svg"
    - from: "templates/Users/dashboard.php (Plan 03-03a) sender card"
      to: "POST /block/<sender_user_id> → BlocksController::create (501)"
      via: "Form submission to wired route from Plan 03-02 routes.php; 501 returned by this plan's controller"
      pattern: "/block/"
    - from: "tamabox.css :root tokens"
      to: "Phase 2 baseline + new --avatar-sm + --avatar-lg"
      via: "Variable declarations consumed by .sender-card__avatar (64px) and avatar chip (24px)"
      pattern: "--avatar-(sm|lg)"
---

<objective>
Phase 3 の **静的アセット + Phase 4 への 501 stub + LP 小修正 + CSS 拡張** を担当する。Plan 03-03a と並行実行可能(files_modified 完全分離)。

具体的には:
1. **`src/Controller/BlocksController.php`** (新規) — `POST /block/<senderUserId>` の 501 stub。Plan 03-02 で route は wired 済み(`routes.php` connect)、本プランで controller class を作成。Phase 4 hand-off contract (D-35)。
2. **`webroot/img/default-avatar.svg`** (新規) — UI-SPEC §7 spec を満たす 64x64 viewBox SVG。3 shape (background circle / head circle / shoulder path)、neutral grey、ジェンダーレス。
3. **`webroot/css/tamabox.css`** 拡張 — Phase 2 baseline (218 行) に Phase 3 component rules を append (~200 行)、`:root` に `--avatar-sm: 24px;` + `--avatar-lg: 64px;` 追加。UI-SPEC §1〜§13 に出る class 全部を埋める。
4. **`templates/element/flash/info.php`** (新規) — UI-SPEC §11 で要求される `.message.info` 用 flash element。スラッグ衝突通知 (D-06) は dashboard.php が直接 div 出力する形 (Plan 03-03a 参照) なのでこの element は必須ではないが、CakePHP 標準パターン上で `Flash->set('msg', ['element' => 'info'])` を呼べるよう用意。
5. **`templates/Pages/home.php`** に Phase 3 の小修正 — 現状 'Bluesky でログイン' CTA のみ。LP に「自分の受信箱を作ろう / 他の受信箱に送信しよう」の短い説明を 1 段落で追加。Phase 2 PagesControllerTest::testDisplay 等の既存 assertion が壊れないように既存文言は触らない (CTA ボタン文字は invariant)。
6. **`tests/TestCase/Controller/BlocksControllerTest.php`** (新規) — 501 stub assertion + Phase 4 contract lock。
7. **`tests/TestCase/Controller/PagesControllerTest.php`** (拡張) — Phase 2 baseline test を Phase 3 LP 小修正後も壊さないこと確認 + 新しい説明段落が表示されることの assertion。

Purpose:
- **D-35 Phase 4 hand-off contract** — 通報/ブロックボタンを Phase 4 で本実装する際の置き換え対象を locked-in (501 stub のテストが Phase 4 で 302/Flash 確認テストに置き換わる契約)
- **D-31 avatar fallback asset** — Plan 03-03a の dashboard で `<img onerror="this.src='/img/default-avatar.svg'">` が機能するためのファイル実体
- **UI-SPEC §1〜§13 visual contract** — Plan 03-03a が出力する全 DOM クラスに対応する CSS を提供 (CSS が無いと dashboard の見た目が壊れるが機能は保たれる、test は文字列 grep ベースなので CSS 不在でもテストは pass する)
- **Phase 2 home.php の Phase 3 採用** — LP に Phase 3 の inbox 機能のヒントを追加 (D-CONTEXT 範囲: home.php の minor copy update)

Output:
- 1 controller (新規 BlocksController, 501 stub のみ)
- 1 SVG asset (新規)
- 1 CSS file (拡張、~200 行 append)
- 1 flash element (新規)
- 1 template 拡張 (Pages/home.php 小修正)
- 2 test ファイル (BlocksControllerTest 新規、PagesControllerTest 拡張)
- composer test green、Phase 2 baseline assertions 全部維持

注意 (parallel safety): Plan 03-03a と本プランは完全に file overlap が無い。Plan 03-03a の dashboard.php は本プランの SVG / CSS / Phase 4 stub を参照するが、参照先が一時的に 501 を返したり SVG が一時的に 404 を返しても、Plan 03-03a の integration test は文字列 grep + DOM 構造のみ確認するため pass する。両プラン完了後の手動 visual check で初めて完成形が見える。
</objective>

<execution_context>
@/home/claude/.claude/get-shit-done/workflows/execute-plan.md
@/home/claude/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@/home/claude/projects/tamabox/.planning/STATE.md
@/home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-CONTEXT.md
@/home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-UI-SPEC.md
@/home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-PATTERNS.md
@/home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-02-send-flow-PLAN.md
@/home/claude/projects/tamabox/src/Controller/AppController.php
@/home/claude/projects/tamabox/src/Controller/AuthController.php
@/home/claude/projects/tamabox/src/Controller/MessagesController.php
@/home/claude/projects/tamabox/templates/Pages/home.php
@/home/claude/projects/tamabox/webroot/css/tamabox.css
@/home/claude/projects/tamabox/config/routes.php
@/home/claude/projects/tamabox/tests/TestCase/Controller/AuthControllerTest.php
@/home/claude/projects/tamabox/tests/TestCase/Controller/PagesControllerTest.php

<interfaces>
<!-- Plan 03-02 already wired the routes -->

config/routes.php (Plan 03-02 Task 1):
  - POST /block/{senderUserId} → BlocksController::create
  - The class did NOT exist when route was added; this plan creates it.

UI-SPEC §7 default-avatar.svg spec (verbatim):
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
    <circle cx="32" cy="32" r="32" fill="#E5E7EB"/>
    <circle cx="32" cy="24" r="11" fill="#9CA3AF"/>
    <path d="M14,56 a18,18 0 0 1 36,0 z" fill="#9CA3AF"/>
  </svg>

Phase 2 tamabox.css :root (existing, do NOT remove or change values, ONLY append new):
  --color-bg, --color-surface, --color-accent, --color-text-primary, --color-text-secondary,
  --color-error, --color-success, --color-warning, --color-border,
  --space-1..--space-12 (8-point scale), --font-family, --radius-sm, --radius-md, etc.

Phase 2 tamabox.css existing classes (DO NOT modify, only extend):
  body, .header-bar, .header-bar-title, .home-page, .display-heading, .home-lead,
  .login-form, .button.primary-button, .dashboard-page (Phase 2 minimal),
  .callback-page, .avatar-handle-chip, .visually-hidden, .message.error/.warning/.success,
  :focus-visible, .text-secondary, .spinner

UI-SPEC §11 Flash variants (existing + new):
  - existing: error / warning / success
  - new in this plan: info — `.message.info { border-left: 4px solid var(--color-text-secondary); background: var(--color-bg); }`

UI-SPEC class roster Plan 03-03a emits (CSS targets for this plan to provide):
  - .send-form-page, .send-form, .send-form__body, .consent-label, .char-counter, .inbox-header, .inbox-self-notice, .welcome-message, .empty-state, .text-secondary
  - .send-done-page, .send-done__lead, .send-done__actions
  - .dashboard-page (extended), .dashboard-header
  - .receive-list, .receive-list-empty
  - .message-row, .message-row[data-state="unread"], .message-row[data-state="opened"], .message-row__head, .message-row__icon, .message-row__time, .message-row__preview, .message-row__body
  - .open-form
  - .ssr-reveal, .ssr-reveal__banner, .ssr-reveal__miss, .ssr-reveal[data-outcome="hit"], .ssr-reveal[data-outcome="miss"]
  - .sender-card, .sender-card__avatar, .sender-card__handle
  - .button.button-clear, .button.button-destructive (extending Phase 2 .button.primary-button base)
  - .settings-form, .probability-control, .probability-suffix
  - .pagination, .pagination li, .pagination li.active
  - .message.info (flash info)
  - inline class: .inline (for nested forms in sender-card)
  - .dashboard-settings (sidebar/below)

Mobile breakpoint (UI-SPEC §Layouts):
  Single 768px breakpoint.
  Below 768px: single column, dashboard receive-list above settings.
  768px+: max-width 960px centered, dashboard 2-column grid (3fr / 2fr), gap var(--space-8).

Existing PagesControllerTest assertions (READ FIRST before editing home.php):
  - testDisplay or testHomePageDisplaysCTA — likely contains assertResponseContains('Bluesky でログイン')
  - DO NOT break these — keep the CTA button label exactly the same.
</interfaces>
</context>

<threat_model>
## Trust Boundaries

| Boundary | Description |
|----------|-------------|
| browser → POST /block/{senderUserId} | CSRF-protected; 501 means no DB write occurs (defense by inaction) |
| webroot/img/default-avatar.svg | Static asset served by Apache; no dynamic content |
| tamabox.css | Static asset; no input |
| home.php | Server-rendered Phase 2 + minor Phase 3 copy; no user input |

## STRIDE Threat Register

| Threat ID | Category | Component | Disposition | Mitigation Plan |
|-----------|----------|-----------|-------------|-----------------|
| T-03-03b-01 | Tampering (premature block save) | BlocksController::create | mitigate | 501 hard-coded; no patchEntity / save call exists in the file. Test asserts response code 501 — Phase 4 implementer must replace BOTH the body AND the test (mirror of OauthController callback 501 contract from Phase 2). |
| T-03-03b-02 | Information Disclosure (SVG XSS) | default-avatar.svg | mitigate | Static `.svg` file served as `image/svg+xml` by Apache; no inline `<script>` or `<foreignObject>` tags; only `<svg>`/`<circle>`/`<path>` shapes per UI-SPEC §7 spec. |
| T-03-03b-03 | DoS (oversized CSS) | tamabox.css | accept | Phase 3 adds ~200 lines to existing 218 lines = ~436 lines / ~15-20 KB total; Apache serves with mod_expires caching; non-issue on Lolipop shared hosting. |
| T-03-03b-04 | Tampering (unauthenticated block POST) | BlocksController::create | accept | 501 returned regardless of identity (initialize() calls `allowUnauthenticated(['create'])` so AuthenticationMiddleware doesn't 302-redirect before the action runs). No DB writes, no observable diff between authed/unauthed. Phase 4 plan-phase MUST drop `'create'` from `allowUnauthenticated` and add proper auth gate when replacing the stub body. |
| T-03-03b-05 | Open redirect (LP edit) | home.php | mitigate | LP additions are static text + the existing form's hardcoded action="/login/bluesky"; no URL-from-query rendering. |
</threat_model>

<tasks>

<task type="auto">
  <name>Task 1: BlocksController 501 stub + integration test</name>
  <files>src/Controller/BlocksController.php, tests/TestCase/Controller/BlocksControllerTest.php</files>

  <read_first>
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-CONTEXT.md D-35 (Phase 4 stub pattern: 501 + corresponding test asserting 501 forces Phase 4 to replace both)
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-PATTERNS.md §3 BlocksController/OauthController 501 stub pattern
    - /home/claude/projects/tamabox/src/Controller/MessagesController.php (analog: report() method 501 stub from Plan 03-02 Task 1)
    - /home/claude/projects/tamabox/src/Controller/OauthController.php (Phase 2 example of replaced 501 stub — Plan 02-04 fully replaced the body, the test was updated alongside; this is the pattern Phase 4 will replicate)
    - /home/claude/projects/tamabox/tests/TestCase/Controller/AuthControllerTest.php (analog test: protected $fixtures + setUp + enableCsrfToken)
    - /home/claude/projects/tamabox/config/routes.php (confirm POST /block/{senderUserId} route exists from Plan 03-02 Task 1)
  </read_first>

  <action>
**A. Create `src/Controller/BlocksController.php`**:

```php
<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * BlocksController — sender-block lifecycle. Phase 4 (INBOX-04 / INBOX-05).
 *
 * Phase 3 status: this controller exists ONLY to provide a 501 stub for
 * `POST /block/<senderUserId>` referenced by the SSR-reveal sender card
 * (D-35 hand-off contract). Phase 4 plan-phase replaces this body with the
 * real INSERT-into-blocks logic.
 *
 * The corresponding integration test (BlocksControllerTest::testCreateReturns501Stub)
 * locks the contract: when Phase 4 ships, that test must be UPDATED to assert
 * the real behavior (302 redirect + Flash). If the 501 test still passes after
 * Phase 4 deploy, the implementation never happened — the same protocol Plan
 * 02-04 used to replace OauthController::callback's 501 stub.
 */
class BlocksController extends AppController
{
    /**
     * @inheritDoc
     *
     * Phase 3 ONLY: `create` is a 501 stub (D-35 hand-off contract).
     * AuthenticationMiddleware (Application.php:145 unauthenticatedRedirect=>'/')
     * would otherwise return 302 BEFORE the action runs, breaking the strict
     * `assertResponseCode(501)` test. Since the body is a content-negotiation
     * early-return with no DB/auth context, allowing unauthenticated access is
     * safe. Phase 4 plan-phase MUST remove `'create'` from this list when
     * replacing the body with real INSERT-into-blocks logic (the listener-only
     * mirror of how Plan 02-04 finalized OauthController::callback).
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->allowUnauthenticated(['create']);
    }

    /**
     * POST /block/{senderUserId} — Phase 4 stub.
     *
     * @param string $senderUserId UUID of the sender user being blocked.
     * @return \Cake\Http\Response
     */
    public function create(string $senderUserId): Response
    {
        $this->request->allowMethod(['post']);
        return $this->response
            ->withStatus(501)
            ->withStringBody('Not Implemented');
    }
}
```

**B. Create `tests/TestCase/Controller/BlocksControllerTest.php`**:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * BlocksController integration tests — Phase 3 contains only the 501 stub assertion
 * for the Phase 4 hand-off (D-35). Phase 4 plan-phase MUST update this test alongside
 * replacing BlocksController::create's body.
 *
 * Phase 2 sticky note 1: $fixtures must be UNTYPED.
 */
class BlocksControllerTest extends TestCase
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

    public function testCreateReturns501Stub(): void
    {
        $this->enableCsrfToken();
        $this->post('/block/22222222-2222-2222-2222-222222222222');
        $this->assertResponseCode(501);
        // Body assertion is intentionally minimal — Phase 4 will not return a body string.
        $body = (string)$this->_response->getBody();
        $this->assertStringContainsString('Not Implemented', $body);
    }

    public function testCreateOnlyAllowsPost(): void
    {
        $this->get('/block/22222222-2222-2222-2222-222222222222');
        // CakePHP route restricted to POST → 405 OR 404 (depending on version)
        $code = $this->_response->getStatusCode();
        $this->assertContains($code, [404, 405], "Expected 404/405 for non-POST, got $code");
    }
}
```

**C. Verify**:
```bash
composer test -- --filter BlocksControllerTest
composer test
vendor/bin/phpstan analyse src/Controller/BlocksController.php
composer cs-check
```
  </action>

  <acceptance_criteria>
    - `grep -c 'class BlocksController extends AppController' src/Controller/BlocksController.php` = 1
    - `grep -c 'public function create(string \$senderUserId)' src/Controller/BlocksController.php` = 1
    - `grep -c "withStatus(501)" src/Controller/BlocksController.php` = 1
    - `grep -c "Not Implemented" src/Controller/BlocksController.php` = 1
    - `grep -c "allowMethod\(\['post'\]\)" src/Controller/BlocksController.php` = 1
    - `grep -cE "allowUnauthenticated\(\[.*['\"]create['\"]" src/Controller/BlocksController.php` = 1   # Blocker fix: 501 stub must not be auth-redirected before reaching the action
    - `grep -c 'public function initialize' src/Controller/BlocksController.php` = 1
    - `grep -c 'testCreateReturns501Stub' tests/TestCase/Controller/BlocksControllerTest.php` = 1
    - `composer test -- --filter BlocksControllerTest 2>&1 | grep -E 'OK \(|FAILURES'` shows OK
    - `vendor/bin/phpstan analyse src/Controller/BlocksController.php` exit 0
  </acceptance_criteria>

  <verify>
    <automated>composer test -- --filter BlocksControllerTest && vendor/bin/phpstan analyse src/Controller/BlocksController.php</automated>
  </verify>

  <done>BlocksController class exists with create() returning 501. Test asserts 501. Phase 4 hand-off contract locked.</done>
</task>

<task type="auto">
  <name>Task 2: default-avatar.svg + flash/info element + home.php Phase 3 minor copy</name>
  <files>webroot/img/default-avatar.svg, templates/element/flash/info.php, templates/Pages/home.php, tests/TestCase/Controller/PagesControllerTest.php</files>

  <read_first>
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-UI-SPEC.md §7 Default Avatar SVG (verbatim spec) / §11 Flash Message info variant
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-CONTEXT.md `<code_context>` Reusable Assets templates/Pages/home.php section (Phase 2 で書き換え済 → Phase 3 minor copy update)
    - /home/claude/projects/tamabox/templates/Pages/home.php FULL (current Phase 2 'Bluesky でログイン' CTA — must preserve exact CTA wording so Phase 2 PagesControllerTest does NOT regress)
    - /home/claude/projects/tamabox/tests/TestCase/Controller/PagesControllerTest.php (READ FIRST — note current assertions; the planner edit must keep all of them green)
    - /home/claude/projects/tamabox/templates/element/ (existing CakePHP element directory; the flash subdirectory may not exist yet — `templates/element/flash/` is the convention for `Flash->set('msg', ['element' => 'X'])` to resolve to `templates/element/flash/X.php`)
  </read_first>

  <action>
**A. Create `webroot/img/default-avatar.svg`** verbatim per UI-SPEC §7:

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64" role="img" aria-label="default avatar">
  <circle cx="32" cy="32" r="32" fill="#E5E7EB"/>
  <circle cx="32" cy="24" r="11" fill="#9CA3AF"/>
  <path d="M14,56 a18,18 0 0 1 36,0 z" fill="#9CA3AF"/>
</svg>
```

(`role="img"` + `aria-label` added per UI-SPEC §A11y for screen-reader compatibility. The hex colors are literals — CSS variables don't propagate into SVG `fill`.)

**B. Create `templates/element/flash/info.php`**:

```php
<?php
/**
 * @var \App\View\AppView $this
 * @var string $message
 *
 * Info variant flash element — UI-SPEC §11.
 * Used by Flash->set($msg, ['element' => 'info']) — accent color is muted (border-left
 * --color-text-secondary, NOT --color-accent) to keep accent reserved for primary CTAs.
 */
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="message info" role="status" onclick="this.classList.add('hidden')">
    <?= $message ?>
</div>
```

**Note**: existing Phase 2 flash elements (error / success / warning) follow the same pattern. Read `templates/element/flash/error.php` (or wherever Phase 2 placed them) and mirror the structure. If Phase 2 used `templates/Element/flash/` (capital E from older CakePHP), use the same case. Modern CakePHP 4.5 uses lowercase `templates/element/`.

**C. Update `templates/Pages/home.php`** — keep the existing 'Bluesky でログイン' button + form intact, ADD a brief explainer paragraph above OR below the existing structure. **Read the current file first** to identify the exact insertion point:

Current structure (Phase 2):
```php
<div class="home-page">
    <h1 class="display-heading">tamabox</h1>
    <p class="text-secondary home-lead">
        Bluesky アカウントでログインして、あなたの受信箱をはじめましょう。
    </p>

    <?= $this->Form->create(null, [
        'url' => ['controller' => 'Auth', 'action' => 'startBluesky'],
        'type' => 'post',
        'class' => 'login-form',
    ]) ?>
        <button type="submit" class="button primary-button">Bluesky でログイン</button>
    <?= $this->Form->end() ?>
</div>
```

Phase 3 addition — insert AFTER the `Form->end()` and BEFORE the closing `</div>` of `.home-page`:

```php
    <section class="home-explainer text-secondary">
        <p>tamabox は確率で送信者の名前がバレる、匿名メッセージ箱です。</p>
        <p>
            ログイン後、あなた専用の受信箱 URL を Bluesky でシェアして、
            メッセージを集めることができます。
            送信者は同意のうえで送信し、受信箱の確率設定に応じて送信者の Bluesky アカウントが開示されることがあります。
        </p>
    </section>
```

(Decorative additions only; CTA button + form structure + 'Bluesky でログイン' label preserved EXACTLY.)

**D. Update `tests/TestCase/Controller/PagesControllerTest.php`** — add a new test for the explainer; do NOT modify existing tests:

```php
public function testHomePageContainsPhase3Explainer(): void
{
    $this->get('/');
    $this->assertResponseOk();
    $this->assertResponseContains('確率で送信者の名前がバレる');
    $this->assertResponseContains('Bluesky でログイン');  // CTA preserved
}
```

(This test verifies BOTH the new copy AND the unchanged CTA in one assertion. If Phase 2 had a `testDisplay` or `testHomePageDisplaysCTA` test that already asserts 'Bluesky でログイン', leave it intact; both will pass.)

**E. Verify**:
```bash
composer test -- --filter PagesControllerTest
composer test
ls -la webroot/img/default-avatar.svg
file webroot/img/default-avatar.svg     # confirm svg
```
  </action>

  <acceptance_criteria>
    - `ls webroot/img/default-avatar.svg` exists
    - `grep -c 'viewBox="0 0 64 64"' webroot/img/default-avatar.svg` = 1
    - `grep -c '<circle cx="32" cy="32" r="32" fill="#E5E7EB"/>' webroot/img/default-avatar.svg` = 1
    - `grep -c '<circle cx="32" cy="24" r="11" fill="#9CA3AF"/>' webroot/img/default-avatar.svg` = 1
    - `grep -c '<path d="M14,56 a18,18 0 0 1 36,0 z"' webroot/img/default-avatar.svg` = 1
    - `grep -c '<script' webroot/img/default-avatar.svg` = 0   # security: no inline JS
    - `grep -c '<foreignObject' webroot/img/default-avatar.svg` = 0
    - `ls templates/element/flash/info.php` exists
    - `grep -c 'class="message info"' templates/element/flash/info.php` = 1
    - `grep -c '確率で送信者の名前がバレる' templates/Pages/home.php` = 1
    - `grep -c 'Bluesky でログイン' templates/Pages/home.php` = 1   # CTA preserved
    - `grep -c 'Bluesky アカウントでログインして、あなたの受信箱をはじめましょう' templates/Pages/home.php` = 1   # Phase 2 home-lead preserved
    - `composer test -- --filter PagesControllerTest 2>&1 | grep -E 'OK \(|FAILURES'` shows OK   # NO regression of Phase 2 baseline
    - `composer test 2>&1 | tail -3 | grep -E 'OK|FAILURES'` shows OK overall
  </acceptance_criteria>

  <verify>
    <automated>composer test -- --filter PagesControllerTest && test -f webroot/img/default-avatar.svg && grep -q 'viewBox="0 0 64 64"' webroot/img/default-avatar.svg</automated>
  </verify>

  <done>default-avatar.svg created per UI-SPEC §7 with no script tags. flash/info.php element created. home.php gains Phase 3 explainer paragraph WITHOUT regressing the Phase 2 CTA assertions. PagesControllerTest expanded with explainer assertion. composer test green.</done>
</task>

<task type="auto">
  <name>Task 3: tamabox.css Phase 3 extension</name>
  <files>webroot/css/tamabox.css</files>

  <read_first>
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-UI-SPEC.md §1 (send form) / §2 (receive list) / §3 (settings) / §4 (SSR reveal) / §5 (empty states) / §6 (sender card) / §7 (avatar SVG sizing) / §10 (pagination) / §11 (flash info) / §13 (probability control) / Layouts (mobile-first 768px breakpoint)
    - /home/claude/projects/tamabox/webroot/css/tamabox.css FULL (Phase 2 baseline 218 lines — DO NOT modify existing rules; only `:root` block gets `--avatar-sm` / `--avatar-lg` insertion + new rules appended at end)
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-PATTERNS.md "CSS — webroot/css/tamabox.css extension" section
  </read_first>

  <action>
**A. Modify `webroot/css/tamabox.css`** — TWO changes:

**Change 1**: Inside the existing `:root { ... }` block (Phase 2 lines ~4-26), add 2 lines per UI-SPEC §2 Spacing Scale Exceptions, adjacent to other `--space-*` declarations:

```css
:root {
    /* ... existing Phase 2 tokens preserved ... */
    --avatar-sm: 24px;   /* Phase 3 — avatar chip in header (was hardcoded 24px in Phase 2) */
    --avatar-lg: 64px;   /* Phase 3 — avatar in SSR reveal sender card */
}
```

(Place the two lines logically next to existing space/sizing tokens; do NOT remove or alter any existing line.)

**Change 2**: APPEND the following CSS block at the END of `tamabox.css` (after Phase 2's last rule):

```css
/* ========================================================================
   Phase 3 — Inbox / Send / Receive / SSR Reveal (UI-SPEC §1-§13)
   ======================================================================== */

/* ---------- Send form page (UI-SPEC §1) ---------- */

.send-form-page {
    max-width: 600px;
    margin: 0 auto;
    padding: var(--space-6) var(--space-4);
}

.inbox-header {
    margin-bottom: var(--space-6);
}

.inbox-header h1 {
    font-size: 24px;
    font-weight: 600;
    line-height: 1.25;
    margin: 0;
}

.inbox-self-notice {
    margin: var(--space-2) 0 0 0;
    padding: var(--space-2) var(--space-4);
    background: var(--color-bg);
    border-radius: var(--radius-sm, 4px);
    font-size: 14px;
    color: var(--color-text-secondary);
}

.welcome-message {
    margin: var(--space-4) 0 var(--space-6) 0;
    padding: var(--space-4);
    background: var(--color-surface);
    border-left: 3px solid var(--color-border);
}

.welcome-message h2 {
    font-size: 14px;
    font-weight: 400;
    color: var(--color-text-secondary);
    margin: 0 0 var(--space-2) 0;
}

.welcome-message p {
    margin: 0;
    line-height: 1.5;
}

.send-form .send-form__body {
    width: 100%;
    min-height: 9em;
    padding: var(--space-3);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm, 4px);
    font-family: inherit;
    font-size: 16px;
    line-height: 1.5;
    resize: vertical;
}

.consent-label {
    display: block;
    margin: var(--space-4) 0;
    padding: var(--space-3);
    background: var(--color-bg);
    border-radius: var(--radius-sm, 4px);
    font-size: 14px;
    line-height: 1.5;
    cursor: pointer;
}

.consent-label input[type="checkbox"] {
    margin-right: var(--space-2);
}

.char-counter {
    margin: var(--space-1) 0;
    font-size: 14px;
    color: var(--color-text-secondary);
    text-align: right;
}

.empty-state {
    margin: var(--space-6) 0 var(--space-2) 0;
    font-size: 16px;
    color: var(--color-text-primary);
}

/* ---------- Send done (UI-SPEC §9) ---------- */

.send-done-page {
    max-width: 600px;
    margin: 0 auto;
    padding: var(--space-12) var(--space-4);
    text-align: center;
}

.send-done__lead {
    font-size: 16px;
    line-height: 1.5;
    margin-bottom: var(--space-6);
}

.send-done__actions {
    display: flex;
    gap: var(--space-3);
    justify-content: center;
    flex-wrap: wrap;
}

@media (max-width: 767px) {
    .send-done__actions {
        flex-direction: column;
    }
}

/* ---------- Dashboard (UI-SPEC §2 / §3 / §10) ---------- */

.dashboard-page {
    max-width: 960px;
    margin: 0 auto;
    padding: var(--space-6) var(--space-4);
}

.dashboard-header {
    margin-bottom: var(--space-6);
}

.dashboard-header h1 {
    font-size: 24px;
    font-weight: 600;
    line-height: 1.25;
    margin: 0 0 var(--space-2) 0;
}

@media (min-width: 768px) {
    .dashboard-page {
        display: grid;
        grid-template-columns: 3fr 2fr;
        gap: var(--space-8);
    }
    .dashboard-header,
    .receive-list,
    .receive-list-empty,
    .pagination,
    .message.info {
        grid-column: 1 / 2;
    }
    .dashboard-settings {
        grid-column: 2 / 3;
        grid-row: 2 / span 4;
    }
}

/* ---------- Receive list (UI-SPEC §2 / §22) ---------- */

.receive-list {
    margin-top: var(--space-4);
}

.receive-list h2 {
    font-size: 14px;
    font-weight: 400;
    color: var(--color-text-secondary);
    margin: 0 0 var(--space-3) 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.receive-list-empty {
    margin: var(--space-6) 0;
    padding: var(--space-6);
    background: var(--color-surface);
    border: 1px dashed var(--color-border);
    border-radius: var(--radius-sm, 4px);
}

.receive-list-empty h2 {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 var(--space-2) 0;
}

.receive-list-empty code {
    background: var(--color-bg);
    padding: 2px var(--space-1);
    border-radius: 2px;
    font-size: 14px;
}

.message-row {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm, 4px);
    margin-bottom: var(--space-2);
    padding: 0;
}

.message-row__head {
    display: flex;
    align-items: baseline;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-4);
    cursor: pointer;
    list-style: none;
}

.message-row__head::-webkit-details-marker {
    display: none;
}

.message-row__icon {
    font-size: 16px;
    width: 1.5em;
    text-align: center;
}

.message-row[data-state="unread"] .message-row__icon {
    color: var(--color-text-primary);
}

.message-row[data-state="opened"] .message-row__icon {
    color: var(--color-success);
}

.message-row[data-state="unread"] .message-row__preview {
    font-weight: 600;
    color: var(--color-text-primary);
}

.message-row[data-state="opened"] .message-row__preview {
    font-weight: 400;
    color: var(--color-text-secondary);
}

.message-row__time {
    font-size: 14px;
    color: var(--color-text-secondary);
    flex-shrink: 0;
}

.message-row__preview {
    font-size: 16px;
    line-height: 1.5;
    flex-grow: 1;
    overflow: hidden;
    text-overflow: ellipsis;
}

.message-row__body {
    padding: var(--space-4);
    border-top: 1px solid var(--color-border);
}

.message-row__body p {
    margin: 0 0 var(--space-4) 0;
    line-height: 1.5;
    white-space: pre-wrap;
}

.open-form {
    margin-top: var(--space-3);
}

/* ---------- SSR reveal (UI-SPEC §4 / §6) ---------- */

.ssr-reveal {
    margin-top: var(--space-4);
}

.ssr-reveal__banner {
    padding: var(--space-3) var(--space-4);
    background: var(--color-surface);
    border-left: 4px solid var(--color-accent);
    font-weight: 600;
    color: var(--color-text-primary);
    margin-bottom: var(--space-4);
}

.ssr-reveal__miss {
    font-size: 14px;
    color: var(--color-text-secondary);
    margin: 0;
}

.sender-card {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    flex-wrap: wrap;
    padding: var(--space-3);
    background: var(--color-bg);
    border-radius: var(--radius-sm, 4px);
}

.sender-card__avatar {
    width: var(--avatar-lg);
    height: var(--avatar-lg);
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}

.sender-card__handle {
    color: var(--color-accent);
    text-decoration: underline;
    font-size: 16px;
    font-weight: 600;
}

.button.button-clear {
    background: transparent;
    color: var(--color-accent);
    border: 1px solid transparent;
    padding: var(--space-2) var(--space-3);
}

.button.button-clear:hover {
    background: var(--color-bg);
}

.button.button-destructive {
    color: var(--color-error);
}

.inline {
    display: inline-block;
    margin: 0;
}

/* ---------- Settings form (UI-SPEC §3 / §13) ---------- */

.dashboard-settings {
    background: var(--color-surface);
    padding: var(--space-4);
    border-radius: var(--radius-sm, 4px);
    border: 1px solid var(--color-border);
}

.dashboard-settings h2 {
    font-size: 14px;
    font-weight: 400;
    color: var(--color-text-secondary);
    margin: 0 0 var(--space-3) 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.settings-form fieldset {
    border: 0;
    padding: 0;
    margin: 0 0 var(--space-4) 0;
}

.settings-form legend {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: var(--space-2);
}

.probability-control {
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.probability-control input[type="range"] {
    flex-grow: 1;
}

.probability-control input[type="number"] {
    width: 4em;
    padding: var(--space-2);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm, 4px);
}

.probability-suffix {
    font-size: 16px;
    color: var(--color-text-secondary);
}

.settings-form textarea {
    width: 100%;
    padding: var(--space-3);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm, 4px);
    font-family: inherit;
    font-size: 16px;
    resize: vertical;
}

/* ---------- Pagination (UI-SPEC §10) ---------- */

.pagination {
    margin: var(--space-6) 0;
    text-align: center;
}

.pagination ul,
.pagination li {
    display: inline-block;
    list-style: none;
    padding: 0;
    margin: 0 var(--space-1);
}

.pagination a {
    color: var(--color-accent);
    text-decoration: none;
    padding: var(--space-1) var(--space-2);
}

.pagination .active a,
.pagination .active span {
    color: var(--color-text-primary);
    font-weight: 600;
}

/* ---------- Flash variant: info (UI-SPEC §11) ---------- */

.message.info {
    border-left: 4px solid var(--color-text-secondary);
    background: var(--color-bg);
    padding: var(--space-3) var(--space-4);
    margin: var(--space-3) 0;
    color: var(--color-text-primary);
}
```

**Critical rules**:
- Do NOT modify any existing Phase 2 rule.
- Do NOT remove `:focus-visible`, `.visually-hidden`, `.button.primary-button` Phase 2 rules.
- All new rules use `--space-*` tokens from Phase 2 (NO new spacing tokens introduced beyond `--avatar-sm` / `--avatar-lg`).
- The `--radius-sm` token referenced may not exist in Phase 2 — if so, use a literal `4px` everywhere `var(--radius-sm)` appears OR add `--radius-sm: 4px;` to `:root`. **Inspect Phase 2 :root first**: if `--radius-sm` exists, keep `var(--radius-sm, 4px)` (which uses fallback when missing); if not, the fallback in the `var()` call (the second arg) applies. This is safe in either case.

**B. Smoke test** — open `/dashboard` in a browser (not part of automated verify, but Lolipop devs commonly visual-check):
```bash
# After login + visiting /dashboard, the receive list should show:
# - distinct unread (●, bold) vs opened (✓, normal) rows
# - SSR hit row with avatar 64px circle + handle link + profile button + Phase 4 stub buttons
# - SSR miss row with text-only line
# - Settings sidebar (desktop) or below-list (mobile)
```

(This is a manual smoke check; not enforced by automated test.)

**C. Verify (automated parts)**:
```bash
# CSS file syntactically loadable (apache will serve it; check for unbalanced braces)
php -r 'echo file_get_contents("webroot/css/tamabox.css");' | grep -c '{' | head -1
php -r 'echo file_get_contents("webroot/css/tamabox.css");' | grep -c '}' | head -1
# Approximate counts should match (same number of opening and closing braces)

composer test                  # baseline
composer cs-check              # PHP only — CSS isn't linted by phpcs

# Optional: stylelint if installed (not in this project's stack)
```
  </action>

  <acceptance_criteria>
    - `grep -c '\-\-avatar-sm: 24px' webroot/css/tamabox.css` = 1
    - `grep -c '\-\-avatar-lg: 64px' webroot/css/tamabox.css` = 1
    - `grep -c '.send-form-page' webroot/css/tamabox.css` ≥ 1
    - `grep -c '.send-form__body' webroot/css/tamabox.css` ≥ 1
    - `grep -c '.consent-label' webroot/css/tamabox.css` ≥ 1
    - `grep -c '.char-counter' webroot/css/tamabox.css` ≥ 1
    - `grep -c '.send-done-page' webroot/css/tamabox.css` ≥ 1
    - `grep -c '.dashboard-page' webroot/css/tamabox.css` ≥ 1
    - `grep -c '.dashboard-settings' webroot/css/tamabox.css` ≥ 1
    - `grep -c '.receive-list' webroot/css/tamabox.css` ≥ 1
    - `grep -c '.message-row' webroot/css/tamabox.css` ≥ 4
    - `grep -c 'data-state="unread"' webroot/css/tamabox.css` ≥ 1
    - `grep -c 'data-state="opened"' webroot/css/tamabox.css` ≥ 1
    - `grep -c '.ssr-reveal' webroot/css/tamabox.css` ≥ 2
    - `grep -c '.sender-card' webroot/css/tamabox.css` ≥ 2
    - `grep -c '.button-destructive' webroot/css/tamabox.css` ≥ 1
    - `grep -c '.settings-form' webroot/css/tamabox.css` ≥ 1
    - `grep -c '.probability-control' webroot/css/tamabox.css` ≥ 1
    - `grep -c '.pagination' webroot/css/tamabox.css` ≥ 1
    - `grep -c '.message.info' webroot/css/tamabox.css` ≥ 1
    - `grep -c '@media (min-width: 768px)' webroot/css/tamabox.css` ≥ 1
    - `grep -c '\-\-color-accent' webroot/css/tamabox.css` ≥ 4   # (Phase 2 baseline + Phase 3 references)
    - Brace count balanced: `tr -cd '{' < webroot/css/tamabox.css | wc -c` equals `tr -cd '}' < webroot/css/tamabox.css | wc -c`
    - Phase 2 :root tokens preserved: `grep -c '\-\-color-bg' webroot/css/tamabox.css` ≥ 1
    - Phase 2 .button.primary-button preserved: `grep -c '\.button\.primary-button' webroot/css/tamabox.css` ≥ 1
    - Phase 2 .visually-hidden preserved: `grep -c '\.visually-hidden' webroot/css/tamabox.css` ≥ 1
    - File size reasonable: `wc -l webroot/css/tamabox.css` returns 350-500 (Phase 2 218 + ~200 Phase 3 = ~420)
    - composer test still green (no PHP regression)
  </acceptance_criteria>

  <verify>
    <automated>composer test && [ "$(tr -cd '{' < webroot/css/tamabox.css | wc -c)" = "$(tr -cd '}' < webroot/css/tamabox.css | wc -c)" ]</automated>
  </verify>

  <done>tamabox.css gets ~200 lines of Phase 3 CSS appended. :root has --avatar-sm + --avatar-lg. All UI-SPEC §1-§13 class targets covered. Phase 2 baseline rules untouched. 768px breakpoint media query for desktop dashboard 2-column grid. composer test still green.</done>
</task>

</tasks>

<verification>

After all tasks complete:

```bash
# Per-test-class
composer test -- --filter BlocksControllerTest
composer test -- --filter PagesControllerTest

# Full suite — must remain green
composer test

# Static analysis
vendor/bin/phpstan analyse src/Controller/BlocksController.php

# Coding standards (PHP only)
composer cs-check

# Static asset checks
test -f webroot/img/default-avatar.svg && \
  grep -q 'viewBox="0 0 64 64"' webroot/img/default-avatar.svg && \
  ! grep -q '<script' webroot/img/default-avatar.svg

# CSS sanity
test "$(tr -cd '{' < webroot/css/tamabox.css | wc -c)" = "$(tr -cd '}' < webroot/css/tamabox.css | wc -c)"

# Manual smoke (combined with Plan 03-03a output):
# 1. Login as alice → /dashboard renders styled dashboard, SSR reveal sections show 64px avatars
# 2. POST /block/<sender> → 501 Not Implemented
# 3. View / → home.php shows CTA + new explainer
```

All MUST pass.

</verification>

<success_criteria>

This plan succeeds when:

1. **D-35 hand-off contract**: BlocksController exists with 501 stub for `create`. Test asserts 501. Phase 4 plan-phase will replace BOTH the body and the test (mirroring the Plan 02-04 OauthController callback replacement pattern).
2. **D-31 avatar fallback**: webroot/img/default-avatar.svg renders the UI-SPEC §7 silhouette without script tags. Plan 03-03a's `<img onerror>` finally has a real fallback to load.
3. **UI-SPEC §11 info flash**: templates/element/flash/info.php exists with `.message.info` class wrapper. Plan 03-03a's slug-collision banner uses the same class via direct div output (the element is available for future `Flash->set('msg', ['element' => 'info'])` calls).
4. **UI-SPEC §1-§13 visual coverage**: tamabox.css gains all classes Plan 03-03a's templates reference (.message-row, .ssr-reveal, .sender-card, .settings-form, .pagination, .send-form-page, .send-done-page, etc.) without modifying Phase 2 baseline.
5. **No Phase 2 regression**: PagesControllerTest still green; home.php's 'Bluesky でログイン' CTA preserved EXACTLY; Phase 2 home-lead text preserved.
6. **Mobile-first responsive**: 768px breakpoint media query splits dashboard into 2-column grid on desktop, single-column on mobile.
7. composer test fully green; phpstan level 8 OK; phpcs clean.

</success_criteria>

<output>
After completion, create `.planning/phases/03-inbox-message-ssr-reveal/03-03b-SUMMARY.md` documenting:

- Files created (BlocksController, default-avatar.svg, flash/info.php, BlocksControllerTest) + modified (home.php, tamabox.css, PagesControllerTest)
- Test counts (BlocksControllerTest 2, PagesControllerTest +1 explainer)
- Phase 4 hand-off note: BlocksController::create body + BlocksControllerTest::testCreateReturns501Stub MUST be replaced together when Phase 4 implements INBOX-04 / INBOX-05. The 501 contract is the canonical "did the implementation actually happen" gate (mirror of Plan 02-04 callback stub replacement).
- CSS line count Phase 2 → Phase 3 (218 → ~420 expected)
- Confirmation: PagesControllerTest baseline ≤Phase 2 assertions all still pass after home.php edit (NO regression)
- Sticky note for Phase 3 verifier: Plans 03-03a + 03-03b together complete the visual + functional contract. Live-AS Bluesky session smoke is `human_needed` deferred to Phase 4 deploy (Phase 2 verify-phase precedent: 7/7 code-level truths VERIFIED, 3 inherent-human-only items).
</output>
