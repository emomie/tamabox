---
phase: 03-inbox-message-ssr-reveal
plan: 02
type: execute
wave: 2
depends_on:
  - 03-01
files_modified:
  - src/Application.php
  - src/Controller/MessagesController.php
  - src/Controller/AuthController.php
  - src/Controller/OauthController.php
  - src/Model/Table/MessagesTable.php
  - src/Model/Entity/Message.php
  - templates/Messages/send.php
  - templates/Messages/send_done.php
  - config/routes.php
  - tests/TestCase/Controller/MessagesControllerTest.php
  - tests/TestCase/Controller/AuthControllerTest.php
  - tests/TestCase/Model/Table/MessagesTableTest.php
autonomous: true
requirements:
  - AUTH-03
  - MSG-01
  - MSG-02
  - MSG-03
  - MSG-04
  - MSG-05
tags:
  - send
  - consent
  - auth-gate
  - ssr-bake-in
  - sender-snapshot
  - controller
  - integration-test

must_haves:
  truths:
    - "GET /<slug> renders templates/Messages/send.php — shows display_name header, optional welcome_message section (NULL → omit), inbox-self-notice strip if identity matches inbox owner (D-38), is_accepting=false renders 'この受信箱は受け付けていません' empty state and NO form (D-28); 404 if slug not found AND slug_previous not found; 301 to /<currentSlug> if slug_previous matched (D-04)"
    - "POST /<slug> requires authenticated identity (AUTH-03) — unauthenticated POST → 302 to /login/bluesky after stashing pending_message_body + pending_message_inbox_id in session (D-13)"
    - "POST /<slug> validates body (required, mb_strlen <= 2000 D-16) + consent checkbox (required, D-14) — failures return 422 OR redirect back to /<slug> with Flash.error and inputs preserved"
    - "POST /<slug> success path: generates message UUID via Text::uuid; computes created_at_micro; calls SsrJudge::judge for ssr_seed + is_ssr + ssr_probability_at_send; bakes sender snapshot from user_identities cached fields (D-29); INSERTs messages row; redirects to /<slug>/sent (or renders send_done) — 302 with Flash.success"
    - "Sender snapshot bake: messages.sender_handle_snapshot = identity.handle_cached, sender_avatar_url_snapshot = identity.avatar_url_cached, sender_profile_url_snapshot = identity.profile_url_cached, sender_provider = 'bluesky' — frozen at SEND time, never updated (D-34 / MSG-04)"
    - "AuthController::startBluesky extended (D-13) — accepts pending_message_body + pending_message_inbox_id in POST body, persists to session before PAR redirect; OauthController::callback extended to consume them: on successful identity establish, if pending session keys present, redirect /<slug>?restored=1 instead of /dashboard"
    - "Application::bootstrap() registers the global SlugCollisionSuffixApplied listener once at boot (D-06 — Warning 4 fix). Listener writes Flash.slug_collision_suffix to the active request session via Router::getRequest(); UsersController::dashboard (Plan 03-03a) consumes-once. Bootstrap-time binding eliminates per-request ordering concerns vs binding inside OauthController::callback."
    - "Send-done: redirects target /<slug>?sent=1 OR renders templates/Messages/send_done.php directly per UI-SPEC §9 — fixed copy '送信しました。受け手が開封したとき、抽選次第であなたのアカウントが開示されます。' + 2 CTAs (再送 primary / 他の受信箱 button-clear). NO SSR result shown (D-19)"
    - "MessagesController::report 501 stub returns withStatus(501)->withStringBody('Not Implemented'); test asserts response code = 501 (locks Phase 4 hand-off contract per D-35)"
    - "config/routes.php gains 4 new routes: GET|POST /<slug> → MessagesController::send, POST /report/<id> → MessagesController::report (501 stub), POST /dashboard/messages/<id>/open → MessagesController::open (placeholder route — body in Wave 3a), POST /block/<senderUserId> → BlocksController::create (501 — class added in Wave 3b but route reserved here)"
    - "MessagesTable::sendMessage(...): uses newEntity + saveOrFail, computes body_length = mb_strlen, validates body ≤ 2000 + non-empty + sender_user_id present + inbox.is_accepting=true; on validation fail throws PersistenceFailedException; on DB error throws RuntimeException"
    - "Integration tests cover: send GET unauth (200, ボタンラベル 'Bluesky でログインして送信'), send GET auth + own inbox (inbox-self-notice visible), send GET 404 unknown slug, send GET 301 slug_previous, send GET is_accepting=false (form hidden, message visible), send POST unauth (302 → /login/bluesky + session has pending_message_body), send POST auth happy (302 + DB row + ssr_seed 64 hex + sender snapshot from cached), send POST consent missing (Flash error + 0 new rows), send POST body too long (Flash error + 0 new rows), send POST own-inbox self-send still inserts (D-33 normal flow, no special case), welcome_message XSS escape on send page (T-03-02-04), report stub strict 501 (allowUnauthenticated includes 'report' so AuthenticationMiddleware doesn't 302 before the stub runs)"
  artifacts:
    - path: "src/Controller/MessagesController.php"
      provides: "send (GET/POST), report (501 stub), open (placeholder for Wave 3a fill-in)"
      min_lines: 180
      contains: "class MessagesController"
    - path: "src/Model/Table/MessagesTable.php"
      provides: "sendMessage method + extended validation rules (mb_strlen body ≤ 2000)"
      contains: "sendMessage"
    - path: "templates/Messages/send.php"
      provides: "Send form per UI-SPEC §1 — header + welcome section + form (textarea + consent checkbox + button) OR is_accepting empty state OR self-notice strip"
      min_lines: 50
      contains: "consent"
    - path: "templates/Messages/send_done.php"
      provides: "Fixed-copy send_done per UI-SPEC §9 — '送信しました' lead + 2 CTAs"
      min_lines: 20
      contains: "送信しました"
    - path: "config/routes.php"
      provides: "4 new routes (send, open, report, block) + slug parameter regex"
      contains: "MessagesController"
  key_links:
    - from: "MessagesController::send (POST)"
      to: "SsrJudge::judge + MessagesTable::sendMessage"
      via: "Generate UUID + microtime → judge produces seed/is_ssr → snapshot from identity.*_cached → INSERT message"
      pattern: "SsrJudge|sendMessage"
    - from: "MessagesController::send (GET, unauthenticated POST)"
      to: "session pending_message_body / pending_message_inbox_id + AuthController::startBluesky"
      via: "Stash body in session → POST /login/bluesky → on callback consume → restore form"
      pattern: "pending_message_body"
    - from: "OauthController::callback (existing)"
      to: "pending_message_inbox_id session → /<slug>?restored=1"
      via: "After setIdentity, if pending_message_inbox_id present in session, redirect to that inbox's send form"
      pattern: "pending_message_inbox_id|restored"
    - from: "MessagesController::send"
      to: "InboxesTable::findBySlugOrPrevious"
      via: "Look up inbox by URL slug; on slug_previous match → 301 redirect to current slug"
      pattern: "findBySlugOrPrevious"
---

<objective>
Phase 3 の **送信フロー本体** を完成させる。slug 解決から消費 (welcome 表示 + 同意 UI + body validation + SSR 確定 + sender snapshot bake + DB INSERT) までを E2E で組み立て、AUTH-03 / MSG-01..MSG-05 を closes する。

具体的には:
1. **`config/routes.php`** に 4 routes を追加: `GET|POST /<slug>` → `MessagesController::send`、`POST /report/<id>` → `MessagesController::report` (501 stub)、`POST /dashboard/messages/<id>/open` → `MessagesController::open` (Wave 3a で本体)、`POST /block/<senderUserId>` → `BlocksController::create` (Wave 3b)。`fallbacks()` の上に置いて衝突回避。slug は `[a-zA-Z0-9_-]{3,32}` regex で route param 拘束。
2. **`MessagesController::send`** (新規 GET/POST): 
   - GET — InboxesTable::findBySlugOrPrevious で slug 解決 → slug_previous hit → 301 redirect to current slug → render send.php (auth state + is_accepting + welcome を view vars に渡す)。
   - POST 未認証 (D-13): pending_message_body + pending_message_inbox_id を session 保存 → `/login/bluesky` POST へ redirect (CSRF token + form 経由)。
   - POST 認証済 (AUTH-03): consent + body validation → SsrJudge::judge call → MessagesTable::sendMessage call → redirect `/<slug>?sent=1` で send_done 表示 OR 直接 send_done.php render。
3. **`MessagesController::report`** (501 stub, D-35): allowMethod POST + 501 + 'Not Implemented' で Phase 4 contract を予約。
4. **`MessagesController::open`** (placeholder): 空の `?Response` メソッドで route 解決のみ可能にする (501 でも可、Wave 3a で実装される)。
5. **`MessagesTable::sendMessage`**: D-29 sender snapshot bake + body_length 計算 + validation 強化 (mb_strlen ≤ 2000) + saveOrFail を transaction-safe に。
6. **`AuthController::startBluesky`** 拡張 (D-13): POST body の `pending_message_body` / `pending_message_inbox_id` を session へ転記。 
7. **`OauthController::callback`** 拡張 (D-13 復帰): callback 成功で setIdentity 後、session に pending_message_inbox_id があれば dashboard ではなく当該 slug の send.php へ redirect (`?restored=1`)。
8. **Templates**: `Messages/send.php` (UI-SPEC §1 forme + welcome + self-notice + is_accepting empty state) と `Messages/send_done.php` (UI-SPEC §9 fixed copy + 2 CTAs)。固定文言は CONTEXT.md / UI-SPEC.md verbatim。
9. **Integration tests**: MessagesControllerTest (10+ cases), AuthControllerTest 拡張 (pending_message_body persist), MessagesTableTest 拡張 (sendMessage)。

Purpose:
- ROADMAP Phase 3 success criteria #3 (未認証は同意送信不可 = AUTH-03), #4 (consent UI 必須), #5 (送信時 SSR + ssr_seed + snapshot)
- D-13 同意済み消費 UX: 本文を打って→Bluesky ログイン→callback→送信フォームに本文復元→送信。consume パターンで session 増殖防止。
- D-19 送信完了画面で SSR hit/miss を絶対表示しない (送り手非対称設計)。
- D-35 Phase 4 への 501 stub hand-off (`testReportReturns501`).

Output:
- 1 controller (新規 MessagesController) + 2 controller 拡張 (AuthController, OauthController)
- 1 table 拡張 (MessagesTable) + 1 entity 微修正 (Message accessibleFields)
- 2 templates 新規 (send / send_done)
- routes.php に 4 routes 追加
- 3 test ファイル (MessagesControllerTest 新規、AuthControllerTest 拡張、MessagesTableTest 拡張) — composer test green

注意: Wave 3a (`MessagesController::open` 本体) と Wave 3b (`BlocksController` 本体 + tamabox.css 拡張) はこのプランの後段。本プランの 501 stub を Wave 3a/3b が置き換える。Wave 3 plans は本プランの routes.php 追加に依存する。
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
@/home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-01-slug-foundation-PLAN.md
@/home/claude/projects/tamabox/src/Controller/AuthController.php
@/home/claude/projects/tamabox/src/Controller/OauthController.php
@/home/claude/projects/tamabox/src/Controller/UsersController.php
@/home/claude/projects/tamabox/src/Controller/AppController.php
@/home/claude/projects/tamabox/src/Service/Inbox/SlugDeriver.php
@/home/claude/projects/tamabox/src/Service/Message/SsrJudge.php
@/home/claude/projects/tamabox/src/Model/Table/InboxesTable.php
@/home/claude/projects/tamabox/src/Model/Table/MessagesTable.php
@/home/claude/projects/tamabox/src/Model/Table/UserIdentitiesTable.php
@/home/claude/projects/tamabox/src/Model/Entity/Message.php
@/home/claude/projects/tamabox/templates/Pages/home.php
@/home/claude/projects/tamabox/templates/layout/default.php
@/home/claude/projects/tamabox/config/routes.php
@/home/claude/projects/tamabox/tests/TestCase/Controller/AuthControllerTest.php
@/home/claude/projects/tamabox/tests/TestCase/Controller/OauthControllerCallbackTest.php

<interfaces>
<!-- From Plan 03-01 (Wave 1 — already shipped before this plan executes) -->

App\Service\Inbox\SlugDeriver:
  - public function deriveFromHandle(string $handle, string $did): string

App\Service\Message\SsrJudge:
  - public function judge(string $messageId, string $createdAtMicro, string $probability): array
    returns: ['ssr_seed' => string(64), 'is_ssr' => bool, 'ssr_probability_at_send' => string]

App\Model\Table\InboxesTable:
  - public function findBySlugOrPrevious(string $slug): array
    returns: ['inbox' => Inbox (with Users + UserIdentities contained), 'redirect' => bool]
    throws: \Cake\Http\Exception\NotFoundException
  - public function assignSlugForUser(string $userId, string $derivedBaseSlug, string $did, string $displayName): array

<!-- From Phase 1 (already shipped) -->

App\Model\Entity\User:
  - id (UUID), display_name, deleted_at, created_at, updated_at
  - related: user_identity (singular, hasOne→UserIdentities effective via UserIdentities ownership)

App\Model\Entity\UserIdentity (Phase 2 hydrated by upsertBlueskyIdentity):
  - id (UUID), user_id, provider='bluesky', provider_account_id (DID),
  - handle_cached, avatar_url_cached, profile_url_cached, last_synced_at

App\Model\Entity\Message (Phase 1 baked):
  - id (UUID PK), inbox_id (UUID FK→inboxes), sender_user_id (UUID FK→users),
  - body (TEXT), body_length (INT), is_ssr (TINYINT), ssr_probability_at_send (DECIMAL(4,3)), ssr_seed (VARCHAR(64) NOT NULL),
  - sender_provider (ENUM 'bluesky','x'), sender_handle_snapshot, sender_avatar_url_snapshot, sender_profile_url_snapshot,
  - opened_at (DATETIME(6) NULL), deleted_at (DATETIME(6) NULL), deleted_reason (VARCHAR(64) NULL),
  - created_at (DATETIME(6))
  - NO updated_at (Phase 1 STATE.md note — immutable post-send)

App\Controller\AppController:
  - $this->Authentication available (Plan 02-04 sticky note 1)
  - $this->Flash available
  - $this->fetchTable('TableName')->find()->where()->firstOrFail() pattern
  - CSRF auto-applied to all POST routes (CsrfProtectionMiddleware globally)

queryString / sessionString helper pattern (Phase 2 sticky note 3) — copy verbatim into MessagesController:

```php
private function queryString(string $key): string
{
    $v = $this->request->getQuery($key);
    return is_string($v) ? $v : '';
}

private function sessionString(string $key): string
{
    $v = $this->request->getSession()->read($key);
    return is_string($v) ? $v : '';
}

private function postString(string $key): string
{
    $v = $this->request->getData($key);
    return is_string($v) ? $v : '';
}
```

Bluesky Authentication identity (Plan 02-04):
  - $this->Authentication->getIdentity() returns ?IdentityInterface
  - $identity->getIdentifier() returns scalar (user UUID)
  - $this->request->getAttribute('identity') in templates

Existing AuthController::startBluesky (Plan 02-04, lines 41-71):
  - public function startBluesky(): ?Response
  - reads/writes session keys: Oauth.pkce_verifier, Oauth.state, Oauth.as_nonce
  - on RuntimeException: Flash.error + redirect /
  - this plan EXTENDS to also persist pending_message_body + pending_message_inbox_id from POST data

Existing OauthController::callback (Plan 02-04):
  - reads session: Oauth.pkce_verifier, Oauth.state, Oauth.as_nonce
  - happy path ends with redirect /dashboard
  - this plan EXTENDS: if session.pending_message_inbox_id present after setIdentity, lookup that inbox slug → redirect /<slug>?restored=1 instead of /dashboard, then session->delete('pending_message_inbox_id') and KEEP pending_message_body for the form to consume

UI-SPEC §1 send.php DOM contract (verbatim from 03-UI-SPEC.md):
  outer: <div class="send-form-page">
  header: <header class="inbox-header"><h1>{display_name} の受信箱</h1></header>
  self-notice (D-38, only if identity owns this inbox):
    <p class="inbox-self-notice">これはあなたの受信箱です。<a href="/dashboard">/dashboard で受信一覧</a></p>
  welcome section (only if welcome_message NOT NULL/empty):
    <section class="welcome-message"><h2>{display_name} から:</h2><p>{nl2br(h(welcome_message))}</p></section>
  form (only if is_accepting=true):
    <form method="post" class="send-form">
      <textarea name="body" required maxlength="2000" rows="6" aria-describedby="body-counter body-help">{restored body or empty}</textarea>
      <p id="body-help" class="text-secondary">最大 2000 文字、改行可、絵文字対応</p>
      <p id="body-counter" class="char-counter" aria-live="polite">0 / 2000</p>
      <label class="consent-label">
        <input type="checkbox" name="consent" required>
        このメッセージは抽選で送信者の Bluesky アカウントが開示される可能性があります(現在の確率: <strong>{X}%</strong>)
      </label>
      <button type="submit" class="primary-button">{送信する OR Bluesky でログインして送信}</button>
    </form>
  is_accepting=false empty state (replaces form, NOT alongside):
    <p class="empty-state">現在この受信箱は受け付けていません</p>
    <p class="text-secondary">受け取り再開をお待ちください。</p>

UI-SPEC §9 send_done.php (verbatim):
  <div class="send-done-page">
    <p class="send-done__lead">送信しました。受け手が開封したとき、抽選次第であなたのアカウントが開示されます。</p>
    <div class="send-done__actions">
      <a href="/{slug}" class="button primary-button">同じ受信箱に再送する</a>
      <a href="/" class="button button-clear">他の受信箱を見る</a>
    </div>
  </div>

UI-SPEC error copy (verbatim, planner):
  consent unchecked → '送信前に同意チェックボックスにチェックしてください。'
  body empty → '本文を入力してください。'
  body > 2000 → '本文は 2000 文字以内で入力してください。'
  is_accepting=false POST → 422 + 'この受信箱は現在受け付けていません。'
  unauth POST starting flow flash → none (just redirect, body is preserved silently)
  callback restoring flash → none (form silently shows the body)

Phase 1 STATE.md note: messages table has NO updated_at column. Timestamp behavior on MessagesTable already configured (created_at => 'new' only).
</interfaces>
</context>

<threat_model>
## Trust Boundaries

| Boundary | Description |
|----------|-------------|
| browser → POST /<slug> body | Untrusted input crosses; CSRF-protected; mb_strlen capped; consent checkbox revalidated server-side |
| session pending_message_body | Per-user session storage; consume-once pattern; cleared on read |
| Authentication identity ↔ message sender | Identity retrieved from Authentication component; sender_user_id is the canonical identifier |
| inbox slug param | Validated by route regex `[a-zA-Z0-9_-]{3,32}`; further validated by InboxesTable::findBySlugOrPrevious which throws NotFound |

## STRIDE Threat Register

| Threat ID | Category | Component | Disposition | Mitigation Plan |
|-----------|----------|-----------|-------------|-----------------|
| T-03-02-01 | Spoofing (sender impersonation) | sender_user_id | mitigate | sender_user_id sourced from `$this->Authentication->getIdentity()->getIdentifier()`, never from request body; integration test asserts request body cannot override sender_user_id |
| T-03-02-02 | Tampering (consent bypass) | consent checkbox | mitigate | Server-side check: `postString('consent') !== '1'` (or equivalent truthy) → Flash.error + redirect, no DB write; integration test `testSendPostConsentMissingNoInsert` |
| T-03-02-03 | Tampering (body length bypass) | body validation | mitigate | mb_strlen check in MessagesTable validation rule (PATTERNS.md "Validation"); HTML maxlength is UX only, not security; integration test asserts 2001-char body rejected |
| T-03-02-04 | Tampering (XSS via body) | send.php / send_done.php | mitigate | All output through `h()` and `nl2br(h(...))`; templates do NOT use `<?= $body ?>` raw; integration test `testSendDisplaysScriptInBodyEscaped` asserts `<script>` tag is escaped on send-done preview if any |
| T-03-02-05 | Information Disclosure (SSR result to sender) | send_done.php | mitigate | send_done template explicitly contains NO is_ssr / ssr_seed / sender card markup; D-19 enforced; integration test asserts response body for `testSendDoneOmitsSsrResult` does NOT contain `is_ssr` or 'hit' / 'miss' / '抽選' |
| T-03-02-06 | Tampering (sender snapshot manipulation) | sender_*_snapshot columns | mitigate | Snapshot fields populated server-side from `$identity->user_identity->handle_cached` etc., never from request body; accessibleFields whitelist excludes them from external patchEntity; integration test posts forged sender_handle_snapshot in body and asserts DB row has cached value |
| T-03-02-07 | CSRF (forged POST) | POST /<slug>, /report, /open, /block | mitigate | CakePHP CsrfProtectionMiddleware enforces token globally (CSRF protection runs BEFORE Authentication, see Application.php middleware order T-02-01-06). `report` allows unauthenticated for the 501 stub but CSRF token is still required. Integration test `testSendPostWithoutCsrfRejected` asserts 403. |
| T-03-02-08 | Open redirect (slug param) | /<slug>?restored=1 redirect | mitigate | Slug param validated by route regex AND InboxesTable lookup; redirect target is constructed from canonical slug, not from query/header |
| T-03-02-09 | Information Disclosure (pending_message_body persisted across users) | session pending_message_body | mitigate | session is per-user (PHP session_id); consume-once via session->consume() in callback restoration; pending_message_body cleared on send POST regardless of outcome |
| T-03-02-10 | Spoofing (iss / handle drift) | sender snapshot freshness | accept | snapshot is taken from cached fields with last_synced_at fresh per Phase 2 D-29; Phase 4 token refresh will improve freshness; for Phase 3 cached snapshot is the documented contract (D-29) |
| T-03-02-11 | Replay (resubmit same form) | send POST | accept | Idempotency not required for MVP — duplicate sends produce distinct messages with distinct UUIDs and ssr_seeds; user can manually delete (Phase 4 MSG-08); ranking effort vs MVP scope |
| T-03-02-12 | DoS (huge body) | POST body parsing | mitigate | CakePHP BodyParserMiddleware applies PHP `post_max_size` / `upload_max_filesize`; mb_strlen check at validator layer rejects >2000 chars early without DB call |
| T-03-02-13 | Repudiation (lost ssr_seed audit) | SsrJudge call site | mitigate | ssr_seed persisted in DB on every send; verifiable via `bin/cake ssr:verify` future CLI (deferred per CONTEXT specifics); for now, manual recompute possible from message_id + created_at + Configure.serverSecret |
| T-03-02-14 | Information Disclosure (auth bypass to private inbox) | is_accepting=false | mitigate | If is_accepting=false on POST: server returns 422 with empty-state copy, no INSERT; integration test `testSendPostToClosedInboxReturns422` |
</threat_model>

<tasks>

<task type="auto">
  <name>Task 1: routes.php update + MessagesController skeleton + report 501 stub + open placeholder + send_done template</name>
  <files>config/routes.php, src/Controller/MessagesController.php, templates/Messages/send_done.php, tests/TestCase/Controller/MessagesControllerTest.php</files>

  <read_first>
    - /home/claude/projects/tamabox/config/routes.php (existing — see where Phase 2 Bluesky routes are placed; new routes go above `$builder->fallbacks()` and below the Phase 2 block)
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-CONTEXT.md `<code_context>` Integration Points (7 routes mapping)
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-PATTERNS.md §3 BlocksController/OauthController 501 stub pattern + §9 send_done template + §13 controller test setup
    - /home/claude/projects/tamabox/src/Controller/AuthController.php FULL (analog: initialize + allowUnauthenticated + try/catch RuntimeException + Flash + redirect; private newOAuthChallenge / buildOAuthClient / queryString / sessionString helpers will be referenced)
    - /home/claude/projects/tamabox/src/Controller/OauthController.php (referenced in PATTERNS for queryString / sessionString helper verbatim copy)
    - /home/claude/projects/tamabox/tests/TestCase/Controller/AuthControllerTest.php FULL (analog: protected $fixtures untyped, setUp Configure setup, enableCsrfToken)
    - /home/claude/projects/tamabox/.planning/phases/02-bluesky-oauth-identity/02-04-oauth-flow-PLAN.md (already in canonical references — for callback expectations of pending_message_inbox_id)
  </read_first>

  <action>
**A. Extend `config/routes.php`** — add 4 new routes inside the existing `$routes->scope('/', function (RouteBuilder $builder): void { ... })` block, AFTER the Phase 2 routes but BEFORE `$builder->fallbacks()`. Order matters: more-specific routes (`/dashboard/*`, `/report/*`, `/block/*`) MUST appear before the catch-all `/<slug>` to avoid being shadowed.

```php
        // === Phase 3 routes (03-02 / 03-03) ===

        // POST /dashboard/messages/{id}/open — receiver opens a message (Wave 3a body).
        $builder->connect(
            '/dashboard/messages/{id}/open',
            ['controller' => 'Messages', 'action' => 'open'],
            ['pass' => ['id'], 'id' => '[0-9a-f-]{36}']
        )->setMethods(['POST']);

        // POST /dashboard/settings — receiver updates inbox settings (Wave 3a body).
        $builder->connect(
            '/dashboard/settings',
            ['controller' => 'Inboxes', 'action' => 'settings']
        )->setMethods(['GET', 'POST']);

        // POST /report/{messageId} — Phase 4 stub (501 in Phase 3).
        $builder->connect(
            '/report/{id}',
            ['controller' => 'Messages', 'action' => 'report'],
            ['pass' => ['id'], 'id' => '[0-9a-f-]{36}']
        )->setMethods(['POST']);

        // POST /block/{senderUserId} — Phase 4 stub (Wave 3b BlocksController, 501 stub).
        $builder->connect(
            '/block/{senderUserId}',
            ['controller' => 'Blocks', 'action' => 'create'],
            ['pass' => ['senderUserId'], 'senderUserId' => '[0-9a-f-]{36}']
        )->setMethods(['POST']);

        // GET|POST /{slug} — public inbox page / send form (D-13 unauth + D-38 self).
        // Slug regex matches inboxes.slug CHECK constraint — 3..32 chars, [a-zA-Z0-9_-].
        $builder->connect(
            '/{slug}',
            ['controller' => 'Messages', 'action' => 'send'],
            ['pass' => ['slug'], 'slug' => '[a-zA-Z0-9_-]{3,32}']
        )->setMethods(['GET', 'POST']);
```

**Critical**: `/{slug}` is a high-recall catch-all. Keep `$builder->fallbacks()` AFTER it but understand that fallbacks won't apply to anything that already matches the slug pattern. Reserved words like `dashboard`, `oauth`, `login`, `pages` are matched by their explicit routes BEFORE `{slug}` because more-specific routes are checked first.

If conflict observed (e.g., `/dashboard` clashing with `/{slug}` regex match for the literal word "dashboard"), debug via `bin/cake routes`.

**B. Create `src/Controller/MessagesController.php`** with skeleton + 3 actions:

```php
<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\Inbox\SlugDeriver;
use App\Service\Message\SsrJudge;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use RuntimeException;

/**
 * MessagesController — public inbox send page + receiver open + Phase 4 report stub.
 *
 * Routes (config/routes.php Phase 3):
 *   GET|POST /{slug}                              → send($slug)
 *   POST /dashboard/messages/{id}/open            → open($id)        (Wave 3a body)
 *   POST /report/{messageId}                      → report($id)      (501 stub, D-35)
 *
 * Auth gates:
 *   - send GET: open to all (D-13 — sender form must render for unauthenticated visitors).
 *   - send POST unauthenticated: 302 redirect → /login/bluesky after stashing pending body.
 *   - send POST authenticated: AUTH-03 satisfied; INSERTs message + redirects to send_done.
 *   - open / report: authentication required (default).
 *
 * CSRF: All POST routes auto-protected by global CsrfProtectionMiddleware.
 */
class MessagesController extends AppController
{
    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();
        // D-13: send page is reachable by unauthenticated users so they can compose
        // before logging in. POST is gated separately inside the action body.
        //
        // Blocker fix: 'report' is a 501 stub (D-35 Phase 4 hand-off contract). Without
        // allowUnauthenticated, AuthenticationMiddleware (Application.php:145
        // unauthenticatedRedirect=>'/') returns 302 BEFORE the action runs, breaking
        // the strict assertResponseCode(501) test. 501 is a content-negotiation
        // early-return with no DB/auth context — auth gate is irrelevant for the
        // stub. Phase 4 plan-phase MUST remove 'report' from this list when replacing
        // the body with real reporting logic (mirror of Plan 02-04 OauthController
        // callback stub replacement).
        $this->Authentication->allowUnauthenticated(['send', 'report']);
    }

    /**
     * GET|POST /{slug} — render send form / process send POST.
     *
     * @param string $slug Inbox slug from URL.
     * @return \Cake\Http\Response|null
     */
    public function send(string $slug): ?Response
    {
        // === slug resolution ===
        /** @var \App\Model\Table\InboxesTable $inboxesTable */
        $inboxesTable = $this->fetchTable('Inboxes');
        try {
            $resolved = $inboxesTable->findBySlugOrPrevious($slug);
        } catch (NotFoundException $e) {
            throw $e; // → CakePHP error400.php (D-36)
        }
        $inbox = $resolved['inbox'];
        if ($resolved['redirect'] === true) {
            // 301 redirect old-slug → current slug (D-04 single-generation grace).
            return $this->redirect('/' . $inbox->slug, 301);
        }

        $identity = $this->Authentication->getIdentity();
        $isAuthenticated = $identity !== null;
        $currentUserId = '';
        if ($isAuthenticated) {
            $identifier = $identity->getIdentifier();
            $currentUserId = is_scalar($identifier) ? (string)$identifier : '';
        }
        $isOwnInbox = $isAuthenticated && $currentUserId !== '' && $currentUserId === $inbox->user_id;

        // GET handling — render form.
        if ($this->request->is('get')) {
            return $this->renderSendForm($inbox, $isAuthenticated, $isOwnInbox);
        }

        // POST — branch on authentication.
        if (!$isAuthenticated) {
            return $this->stashAndRedirectToLogin($slug, $inbox->id);
        }

        return $this->processSend($inbox, $currentUserId);
    }

    /**
     * POST /dashboard/messages/{id}/open — receiver opens a message.
     * Wave 3a fills in the body. For Plan 03-02 this is a placeholder
     * that returns 501 so the route resolves. Wave 3a Task 1 replaces it.
     *
     * @param string $id Message UUID.
     * @return \Cake\Http\Response
     */
    public function open(string $id): Response
    {
        $this->request->allowMethod(['post']);
        // Plan 03-03a Task 1 replaces this body with the real implementation.
        // Until then, return 501 so the route is testable and the contract is locked.
        return $this->response->withStatus(501)->withStringBody('Not Implemented');
    }

    /**
     * POST /report/{messageId} — Phase 4 stub (D-35).
     *
     * @param string $id Message UUID.
     * @return \Cake\Http\Response
     */
    public function report(string $id): Response
    {
        $this->request->allowMethod(['post']);
        return $this->response->withStatus(501)->withStringBody('Not Implemented');
    }

    // === private helpers (Task 2 fills these in) ===

    private function renderSendForm(\App\Model\Entity\Inbox $inbox, bool $isAuthenticated, bool $isOwnInbox): ?Response
    {
        $session = $this->request->getSession();
        // D-13: if redirected back from OAuth callback (?restored=1), restore body once.
        $restoredBody = '';
        if ($this->queryString('restored') === '1') {
            $stash = $session->read('pending_message_body');
            if (is_string($stash)) {
                $restoredBody = $stash;
            }
            // consume — clear session entries so resubmits don't re-restore.
            $session->delete('pending_message_body');
            $session->delete('pending_message_inbox_id');
        }

        $this->set([
            'inbox' => $inbox,
            'isAuthenticated' => $isAuthenticated,
            'isOwnInbox' => $isOwnInbox,
            'restoredBody' => $restoredBody,
            'sentFlash' => $this->queryString('sent') === '1',
        ]);
        return $this->render('Messages/send');
    }

    private function stashAndRedirectToLogin(string $slug, string $inboxId): ?Response
    {
        $body = $this->postString('body');
        $session = $this->request->getSession();
        $session->write('pending_message_body', $body);
        $session->write('pending_message_inbox_id', $inboxId);

        // Forward to /login/bluesky POST. AuthController::startBluesky reads back
        // pending_message_inbox_id during PAR, OauthController::callback redirects
        // /<slug>?restored=1 after setIdentity (Task 3).
        return $this->redirect(['controller' => 'Auth', 'action' => 'startBluesky']);
    }

    private function processSend(\App\Model\Entity\Inbox $inbox, string $senderUserId): ?Response
    {
        // is_accepting=false short-circuit (D-28).
        if (!(bool)$inbox->is_accepting) {
            $this->Flash->error(__('この受信箱は現在受け付けていません。'));
            return $this->redirect('/' . $inbox->slug);
        }

        $body = $this->postString('body');
        $consent = $this->postString('consent');
        if ($consent === '') {
            $this->Flash->error(__('送信前に同意チェックボックスにチェックしてください。'));
            return $this->redirect('/' . $inbox->slug);
        }
        if (trim($body) === '') {
            $this->Flash->error(__('本文を入力してください。'));
            return $this->redirect('/' . $inbox->slug);
        }
        if (mb_strlen($body) > 2000) {
            $this->Flash->error(__('本文は 2000 文字以内で入力してください。(現在 :n 文字)', [':n' => mb_strlen($body)]));
            return $this->redirect('/' . $inbox->slug);
        }

        // Hand off to Task 2 implementation.
        try {
            /** @var \App\Model\Table\MessagesTable $messagesTable */
            $messagesTable = $this->fetchTable('Messages');
            $messagesTable->sendMessage($inbox, $senderUserId, $body);
        } catch (RuntimeException $e) {
            $this->Flash->error(__('送信に失敗しました。しばらくしてから再度お試しください。'));
            return $this->redirect('/' . $inbox->slug);
        }

        // D-19: send_done shows fixed copy + 2 CTAs, NO ssr result.
        $this->Flash->success(__('送信しました。'));
        return $this->redirect('/' . $inbox->slug . '?sent=1');
    }

    private function queryString(string $key): string
    {
        $v = $this->request->getQuery($key);
        return is_string($v) ? $v : '';
    }

    private function postString(string $key): string
    {
        $v = $this->request->getData($key);
        return is_string($v) ? $v : '';
    }
}
```

**Decision note**: For `?sent=1` flow we redirect to /<slug>?sent=1 (so `send_done` is rendered as a flash + the form is also visible for re-send) OR render `Messages/send_done` directly. Per UI-SPEC §9 the intent is a dedicated page. Implementation choice: render `Messages/send_done` directly via `return $this->render('Messages/send_done')` and pass `inbox` for the "再送" link. **Update `processSend` final 2 lines to**:

```php
        $this->set('inbox', $inbox);
        return $this->render('Messages/send_done');
```

(Remove the `?sent=1` redirect and the `Flash->success` — fixed-copy page replaces the flash, per UI-SPEC.)

**C. Create `templates/Messages/send_done.php`** verbatim per UI-SPEC §9:

```php
<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Inbox $inbox
 *
 * Send-done page — fixed copy per UI-SPEC §9.
 * D-19: NO SSR result is shown to the sender.
 */
$this->assign('title', '送信しました');
?>
<div class="send-done-page">
    <p class="send-done__lead">送信しました。受け手が開封したとき、抽選次第であなたのアカウントが開示されます。</p>
    <div class="send-done__actions">
        <?= $this->Html->link(
            '同じ受信箱に再送する',
            '/' . h($inbox->slug),
            ['class' => 'button primary-button']
        ) ?>
        <?= $this->Html->link(
            '他の受信箱を見る',
            '/',
            ['class' => 'button button-clear']
        ) ?>
    </div>
</div>
```

**D. Create `tests/TestCase/Controller/MessagesControllerTest.php`** — initial test scaffold + the 501 stub assertions for `report` and `open` placeholder, plus `send GET` smoke (full coverage in Task 4):

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * MessagesController integration tests — Phase 3 send / open / report.
 *
 * Phase 2 Executor sticky note 1: $fixtures must be UNTYPED (typed-property collision
 * with Cake\TestSuite\Fixture parent). Use phpdoc @var instead.
 */
class MessagesControllerTest extends TestCase
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
        Configure::write('Security.serverSecret', 'test-server-secret-32-chars-fixed!');
    }

    public function testSendGetUnknownSlugReturns404(): void
    {
        $this->get('/no-such-inbox');
        $this->assertResponseCode(404);
    }

    public function testSendGetSlugPreviousRedirectsToCurrent(): void
    {
        // bob has slug='bob-2' and slug_previous='bob' per InboxesFixture.
        $this->get('/bob');
        $this->assertResponseCode(301);
        $this->assertHeader('Location', '/bob-2');
    }

    public function testSendGetUnauthenticatedRendersForm(): void
    {
        $this->get('/alice');
        $this->assertResponseOk();
        $this->assertResponseContains('Bluesky でログインして送信');
        $this->assertResponseContains('alice の受信箱');
    }

    public function testSendGetIsAcceptingFalseHidesForm(): void
    {
        // charlie has is_accepting=0.
        $this->get('/charlie');
        $this->assertResponseOk();
        $this->assertResponseContains('現在この受信箱は受け付けていません');
        $this->assertResponseNotContains('<textarea name="body"');
    }

    public function testReportReturns501Stub(): void
    {
        $this->enableCsrfToken();
        $this->post('/report/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $this->assertResponseCode(501);
    }

    public function testOpenReturns501Placeholder(): void
    {
        $this->enableCsrfToken();
        $this->post('/dashboard/messages/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa/open');
        // Wave 3a Task 1 replaces this body. Until then, 501 OR 302 (auth redirect)
        // depending on identity. Without identity, AuthenticationMiddleware redirects → 302.
        $code = $this->_response->getStatusCode();
        $this->assertContains($code, [302, 501], "Expected 302 (auth redirect) or 501 (stub), got $code");
    }
}
```

(Task 4 expands the test file with send POST cases; Task 1 establishes the scaffold + smoke.)

**E. Run**:
```bash
bin/cake routes 2>&1 | grep -E '(report|block|messages|inboxes|/\{slug\}|/dashboard/messages)' | head -20  # confirm 5 new routes appear
composer test -- --filter MessagesControllerTest
composer test                              # baseline still green
vendor/bin/phpstan analyse src/Controller/MessagesController.php
composer cs-check
```
  </action>

  <acceptance_criteria>
    - `grep -c "'/{slug}'" config/routes.php` = 1
    - `grep -c "'slug' => '\[a-zA-Z0-9_-\]{3,32}'" config/routes.php` = 1
    - `grep -c "/report/{id}" config/routes.php` = 1
    - `grep -c "/block/{senderUserId}" config/routes.php` = 1
    - `grep -c '/dashboard/messages/{id}/open' config/routes.php` = 1
    - `grep -c '/dashboard/settings' config/routes.php` = 1
    - `grep -c 'class MessagesController extends AppController' src/Controller/MessagesController.php` = 1
    - `grep -cE "allowUnauthenticated\(\[.*['\"]send['\"].*['\"]report['\"]" src/Controller/MessagesController.php` = 1   # Blocker fix: report stub must bypass auth-redirect to satisfy strict assertResponseCode(501)
    - `grep -c 'public function send(string \$slug)' src/Controller/MessagesController.php` = 1
    - `grep -c 'public function report(string \$id)' src/Controller/MessagesController.php` = 1
    - `grep -c "withStatus(501)->withStringBody('Not Implemented')" src/Controller/MessagesController.php` ≥ 2
    - `grep -c '送信しました。受け手が開封したとき、抽選次第であなたのアカウントが開示されます' templates/Messages/send_done.php` = 1
    - `grep -c '同じ受信箱に再送する' templates/Messages/send_done.php` = 1
    - `grep -c '他の受信箱を見る' templates/Messages/send_done.php` = 1
    - `bin/cake routes 2>&1 | grep -c '/{slug}'` ≥ 1
    - `composer test -- --filter MessagesControllerTest 2>&1 | grep -E 'OK \(|FAILURES'` shows OK
  </acceptance_criteria>

  <verify>
    <automated>composer test -- --filter MessagesControllerTest && bin/cake routes 2>&1 | grep -c MessagesController</automated>
  </verify>

  <done>routes.php has 5 new Phase 3 routes (slug + 4 specific). MessagesController has send/open/report actions. send_done template renders fixed UI-SPEC §9 copy. report returns 501. Initial integration tests cover 404 / 301 redirect / unauth GET / is_accepting=false / report 501 stub. composer test green.</done>
</task>

<task type="auto">
  <name>Task 2: MessagesTable::sendMessage + sender snapshot bake + extended validation + entity accessibleFields</name>
  <files>src/Model/Table/MessagesTable.php, src/Model/Entity/Message.php, tests/TestCase/Model/Table/MessagesTableTest.php</files>

  <read_first>
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-CONTEXT.md D-09 (SSR algorithm) / D-12 (no retroactive change) / D-16 (2000 chars) / D-17 (plain text only) / D-29 (sender snapshot from cached) / D-32 (profile_url is https://bsky.app/profile/<handle> for Bluesky) / D-34 (snapshot frozen)
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-PATTERNS.md §7 MessagesTable + Shared "Validation (table layer)" pattern
    - /home/claude/projects/tamabox/src/Model/Table/MessagesTable.php FULL FILE — Phase 1 baked validators + Timestamp behavior (no updated_at)
    - /home/claude/projects/tamabox/src/Model/Entity/Message.php (current accessibleFields)
    - /home/claude/projects/tamabox/src/Model/Table/UserIdentitiesTable.php — analog for newEntity + accessibleFields + saveOrFail
    - /home/claude/projects/tamabox/src/Service/Message/SsrJudge.php — judge() signature
    - STATE.md note: messages table has NO updated_at column; Timestamp behavior uses created_at only.
  </read_first>

  <action>
**A. Extend `src/Model/Table/MessagesTable.php`**:

1. **Add imports**:
```php
use App\Service\Message\SsrJudge;
use Cake\I18n\FrozenTime;
use Cake\Utility\Text;
use RuntimeException;
```

2. **Extend `validationDefault`** — keep all existing validators (Phase 1 baked); add the body length rule with mb_strlen:

```php
$validator
    ->scalar('body')
    ->requirePresence('body', 'create')
    ->notEmptyString('body', '本文を入力してください。')
    ->add('body', 'mbLength', [
        'rule' => function ($value) {
            return is_string($value) && mb_strlen($value) <= 2000;
        },
        'message' => '本文は 2000 文字以内で入力してください。',
    ]);

$validator
    ->integer('body_length')
    ->greaterThanOrEqual('body_length', 1)
    ->lessThanOrEqual('body_length', 2000);

$validator
    ->scalar('ssr_seed')
    ->add('ssr_seed', 'hex64', [
        'rule' => ['custom', '/^[0-9a-f]{64}$/'],
        'message' => 'ssr_seed must be a 64-char lowercase hex string.',
    ]);
```

(If Phase 1 already provides `body` / `ssr_seed` rules, REPLACE them with the above. Inspect the file before editing — the existing validators may use different rule names.)

3. **Add `sendMessage` method**:

```php
/**
 * Insert a message with SSR judgement + sender snapshot bake (D-09 + D-29 + MSG-04).
 *
 * The judgement is computed from (Configure.serverSecret, message_id, created_at_micro)
 * via SsrJudge; the snapshot is copied from $senderUser->user_identity->*_cached fields.
 *
 * @param \App\Model\Entity\Inbox $inbox The receiver's inbox (already loaded).
 * @param string $senderUserId UUID of the authenticated sender.
 * @param string $body Message text (validated upstream + here for defense in depth).
 * @return \App\Model\Entity\Message The persisted message entity (with ssr_seed + is_ssr).
 * @throws \RuntimeException If sender's identity lacks cached handle (data inconsistency)
 *   OR if Configure.serverSecret missing (SsrJudge rejects).
 * @throws \Cake\ORM\Exception\PersistenceFailedException On validation/save failure.
 */
public function sendMessage(\App\Model\Entity\Inbox $inbox, string $senderUserId, string $body): \App\Model\Entity\Message
{
    if ($body === '') {
        throw new RuntimeException('sendMessage: body is empty.');
    }
    if (mb_strlen($body) > 2000) {
        throw new RuntimeException('sendMessage: body exceeds 2000 chars.');
    }
    if (!(bool)$inbox->is_accepting) {
        throw new RuntimeException('sendMessage: inbox is not accepting.');
    }

    /** @var \App\Model\Table\UsersTable $usersTable */
    $usersTable = $this->getTableLocator()->get('Users');
    /** @var \App\Model\Entity\User|null $senderUser */
    $senderUser = $usersTable->find()
        ->where(['Users.id' => $senderUserId])
        ->contain(['UserIdentities'])
        ->first();
    if ($senderUser === null) {
        throw new RuntimeException('sendMessage: sender user not found.');
    }

    /** @var \App\Model\Entity\UserIdentity|null $identity */
    $identity = null;
    if (isset($senderUser->user_identities) && is_array($senderUser->user_identities) && count($senderUser->user_identities) > 0) {
        $identity = $senderUser->user_identities[0];
    } elseif (isset($senderUser->user_identity) && $senderUser->user_identity !== null) {
        $identity = $senderUser->user_identity;
    }
    if ($identity === null) {
        throw new RuntimeException('sendMessage: sender has no user_identity.');
    }

    // === Compute deterministic SSR seed BEFORE INSERT (MSG-02 + MSG-03 + D-12 contract) ===
    $messageId = Text::uuid();
    $createdAt = FrozenTime::now();
    $createdAtMicro = $createdAt->format('Y-m-d H:i:s.u');
    $probability = (string)$inbox->ssr_probability;  // DECIMAL(4,3) → '0.100' etc.

    $judge = new SsrJudge();
    $verdict = $judge->judge($messageId, $createdAtMicro, $probability);

    // === Sender snapshot (D-29 / D-32 / D-34 — frozen at SEND time) ===
    $entity = $this->newEntity([
        'id' => $messageId,
        'inbox_id' => (string)$inbox->id,
        'sender_user_id' => $senderUserId,
        'body' => $body,
        'body_length' => mb_strlen($body),
        'is_ssr' => $verdict['is_ssr'],
        'ssr_probability_at_send' => $verdict['ssr_probability_at_send'],
        'ssr_seed' => $verdict['ssr_seed'],
        'sender_provider' => 'bluesky',
        'sender_handle_snapshot' => (string)$identity->handle_cached,
        'sender_avatar_url_snapshot' => $identity->avatar_url_cached !== null ? (string)$identity->avatar_url_cached : null,
        'sender_profile_url_snapshot' => $identity->profile_url_cached !== null ? (string)$identity->profile_url_cached : null,
        'created_at' => $createdAt,
    ], ['accessibleFields' => [
        'id' => true, 'inbox_id' => true, 'sender_user_id' => true,
        'body' => true, 'body_length' => true,
        'is_ssr' => true, 'ssr_probability_at_send' => true, 'ssr_seed' => true,
        'sender_provider' => true,
        'sender_handle_snapshot' => true, 'sender_avatar_url_snapshot' => true, 'sender_profile_url_snapshot' => true,
        'created_at' => true,
    ]]);

    /** @var \App\Model\Entity\Message $saved */
    $saved = $this->saveOrFail($entity);
    return $saved;
}
```

**B. Update `src/Model/Entity/Message.php`** — ensure `_accessible` includes the snapshot fields. Read first; if Phase 1 baked entity already has `'*' => true` (CakePHP default), no change. If not, add explicit entries for the fields above. Important: `opened_at` must NOT be marked patchable from external request (it's internal, set by `markOpened` in Wave 3a).

**C. Add `tests/TestCase/Model/Table/MessagesTableTest.php` cases** (extend Phase 1 baked tests):

```php
public function testSendMessagePersistsSsrFieldsAndSnapshot(): void
{
    Configure::write('Security.serverSecret', 'test-server-secret-32-chars-fixed!');
    /** @var \App\Model\Entity\Inbox $inbox */
    $inbox = $this->Messages->Inboxes->get('11111111-1111-1111-1111-111111111111');  // alice, 0.100
    $senderUserId = '22222222-2222-2222-2222-222222222222';  // bob

    $msg = $this->Messages->sendMessage($inbox, $senderUserId, 'こんにちは');
    $this->assertNotEmpty($msg->id);
    $this->assertSame(64, strlen((string)$msg->ssr_seed));
    $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string)$msg->ssr_seed);
    $this->assertSame('0.100', (string)$msg->ssr_probability_at_send);
    $this->assertSame(5, $msg->body_length);  // 'こんにちは' = 5 chars by mb_strlen
    $this->assertSame('bluesky', $msg->sender_provider);
    $this->assertNotEmpty($msg->sender_handle_snapshot);
}

public function testSendMessageRefusesEmptyBody(): void
{
    Configure::write('Security.serverSecret', 'test-server-secret-32-chars-fixed!');
    $inbox = $this->Messages->Inboxes->get('11111111-1111-1111-1111-111111111111');
    $this->expectException(\RuntimeException::class);
    $this->Messages->sendMessage($inbox, '22222222-2222-2222-2222-222222222222', '');
}

public function testSendMessageRefusesBodyOver2000(): void
{
    Configure::write('Security.serverSecret', 'test-server-secret-32-chars-fixed!');
    $inbox = $this->Messages->Inboxes->get('11111111-1111-1111-1111-111111111111');
    $this->expectException(\RuntimeException::class);
    $this->Messages->sendMessage($inbox, '22222222-2222-2222-2222-222222222222', str_repeat('a', 2001));
}

public function testSendMessageRefusesIfInboxNotAccepting(): void
{
    Configure::write('Security.serverSecret', 'test-server-secret-32-chars-fixed!');
    $inbox = $this->Messages->Inboxes->get('33333333-3333-3333-3333-333333333333');  // charlie, is_accepting=0
    $this->expectException(\RuntimeException::class);
    $this->Messages->sendMessage($inbox, '22222222-2222-2222-2222-222222222222', 'test');
}

public function testSendMessageDeterministicSeedFromSecret(): void
{
    // F2 audit: replay seed compute manually and compare.
    Configure::write('Security.serverSecret', 'test-server-secret-32-chars-fixed!');
    $inbox = $this->Messages->Inboxes->get('11111111-1111-1111-1111-111111111111');
    $msg = $this->Messages->sendMessage($inbox, '22222222-2222-2222-2222-222222222222', 'ok');
    // Recompute manually using id + created_at:
    $expectedSeed = hash('sha256', 'test-server-secret-32-chars-fixed!' . $msg->id . $msg->created_at->format('Y-m-d H:i:s.u'));
    $this->assertSame($expectedSeed, (string)$msg->ssr_seed);
}
```

**D. Verify**:
```bash
composer test -- --filter MessagesTableTest
composer test
vendor/bin/phpstan analyse src/Model/Table/MessagesTable.php src/Model/Entity/Message.php
composer cs-check
```
  </action>

  <acceptance_criteria>
    - `grep -c 'public function sendMessage' src/Model/Table/MessagesTable.php` = 1
    - `grep -c 'use App\\\\Service\\\\Message\\\\SsrJudge' src/Model/Table/MessagesTable.php` = 1
    - `grep -c "format('Y-m-d H:i:s.u')" src/Model/Table/MessagesTable.php` = 1
    - `grep -c 'sender_handle_snapshot' src/Model/Table/MessagesTable.php` ≥ 1
    - `grep -c "'sender_provider' => 'bluesky'" src/Model/Table/MessagesTable.php` = 1
    - `grep -c "'rule' => \['custom', '/\^\[0-9a-f\]{64}\$/'\]" src/Model/Table/MessagesTable.php` = 1
    - `grep -c "mb_strlen(\$value) <= 2000" src/Model/Table/MessagesTable.php` = 1
    - `grep -E 'public function test[A-Z]' tests/TestCase/Model/Table/MessagesTableTest.php | wc -l` ≥ 5 NEW (in addition to Phase 1 baked)
    - `composer test -- --filter MessagesTableTest 2>&1 | grep -E 'OK \(|FAILURES'` shows OK
    - `composer test 2>&1 | tail -3 | grep -E 'OK|FAILURES'` shows OK overall
    - `vendor/bin/phpstan analyse src/Model/Table/MessagesTable.php src/Model/Entity/Message.php 2>&1` exit code 0
  </acceptance_criteria>

  <verify>
    <automated>composer test -- --filter MessagesTableTest && vendor/bin/phpstan analyse src/Model/Table/MessagesTable.php src/Model/Entity/Message.php</automated>
  </verify>

  <done>MessagesTable::sendMessage builds Message entity with deterministic ssr_seed (SsrJudge), sender snapshot from user_identities cached fields, body_length = mb_strlen, validation strict. Entity accessibleFields permits the snapshot/SSR fields. ≥5 new MessagesTableTest cases pass. composer test green, phpstan level 8 [OK].</done>
</task>

<task type="auto">
  <name>Task 3: AuthController + OauthController extension for D-13 pending message body restoration</name>
  <files>src/Application.php, src/Controller/AuthController.php, src/Controller/OauthController.php, tests/TestCase/Controller/AuthControllerTest.php, tests/TestCase/Controller/OauthControllerCallbackTest.php</files>

  <read_first>
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-CONTEXT.md D-13 (未認証時の送信フォーム挙動 — pending_message_body / pending_message_inbox_id session, restored=1 query consume パターン)
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-CONTEXT.md `<specifics>` section "送信フォーム未認証時の本文 session 保持"
    - /home/claude/projects/tamabox/src/Application.php FULL FILE — bootstrap() target for global event listener registration (Warning 4 fix: listener moved here from OauthController::callback to eliminate per-request ordering concerns)
    - /home/claude/projects/tamabox/src/Controller/AuthController.php FULL FILE — current startBluesky implementation; the new code must NOT regress Phase 2 PAR / state / pkce_verifier / as_nonce flow
    - /home/claude/projects/tamabox/src/Controller/OauthController.php FULL FILE — current callback implementation (Plan 02-04); identify the line where the success-path redirect to /dashboard happens
    - /home/claude/projects/tamabox/tests/TestCase/Controller/AuthControllerTest.php — analog test pattern + setUp Configure setup
    - /home/claude/projects/tamabox/tests/TestCase/Controller/OauthControllerCallbackTest.php — Phase 2 callback test patterns; HTTP mock setup
  </read_first>

  <action>
**A. Extend `src/Controller/AuthController.php::startBluesky`**:

INSIDE the `try { ... }` block, AFTER `$this->request->getSession()->write('Oauth.state', $state);` and BEFORE the `$client = $this->buildOAuthClient();` line, ADD:

```php
            // Phase 3 D-13: if user came from a send form, persist body + inbox id so callback
            // can redirect back. If absent (user came directly from /), this is a no-op.
            $pendingBody = $this->request->getData('pending_message_body');
            $pendingInbox = $this->request->getData('pending_message_inbox_id');
            if (is_string($pendingBody) && $pendingBody !== '') {
                $this->request->getSession()->write('pending_message_body', $pendingBody);
            }
            if (is_string($pendingInbox) && $pendingInbox !== '') {
                $this->request->getSession()->write('pending_message_inbox_id', $pendingInbox);
            }
```

Do NOT modify the existing PAR / redirect logic — purely additive.

**B. Extend `src/Controller/OauthController.php::callback`**:

Locate the success path that ends with `return $this->redirect('/dashboard');` (or equivalent). REPLACE that single line with the conditional block:

```php
            // Phase 3 D-13: if a pending send was stashed before login, return to it.
            $session = $this->request->getSession();
            $pendingInboxId = $session->read('pending_message_inbox_id');
            if (is_string($pendingInboxId) && $pendingInboxId !== '') {
                /** @var \App\Model\Table\InboxesTable $inboxesTable */
                $inboxesTable = $this->fetchTable('Inboxes');
                /** @var \App\Model\Entity\Inbox|null $inbox */
                $inbox = $inboxesTable->find()
                    ->where(['Inboxes.id' => $pendingInboxId])
                    ->first();
                $session->delete('pending_message_inbox_id');  // consume — body kept until form reads it
                if ($inbox !== null) {
                    return $this->redirect('/' . $inbox->slug . '?restored=1');
                }
            }
            return $this->redirect('/dashboard');
```

**C. Listen to `SlugCollisionSuffixApplied` event** dispatched by Plan 03-01 (`UserIdentitiesTable::upsertBlueskyIdentity` fires the event on the global EventManager during the OAuth callback transactional path).

**Decision (Warning 4 fix)**: register the listener ONCE in `Application::bootstrap()` instead of per-request inside `OauthController::callback`. This eliminates per-request ordering concerns entirely — the listener exists for the entire process lifetime and is GUARANTEED to be bound before any controller request fires (and therefore before `upsertBlueskyIdentity` ever runs). Per-request binding is brittle: any future refactor that moves the upsert call earlier than the listener registration silently breaks D-06 collision flash.

The listener writes to the session that the request **happens to be processing at the moment the event fires**; CakePHP's session is request-bound, so this is safe — events fire synchronously from inside the request lifecycle, so `$_SESSION` super-global / `Cake\Http\Session` of the active request is guaranteed to be the same per-user session. The listener captures a closure over the EventManager singleton, NOT over a request-bound session.

**Implementation (verbatim — add to `src/Application.php::bootstrap()` after the existing `$this->addPlugin('Authentication');` line):**

```php
        $this->addPlugin('Authentication');

        // Phase 3 D-06: bind slug-collision listener globally — fires from
        // UserIdentitiesTable::upsertBlueskyIdentity (Plan 03-01 Task 4) inside the
        // OAuth callback transactional path. Writing to Flash.slug_collision_suffix
        // here surfaces a one-shot info banner on the next /dashboard render
        // (consumed-and-cleared by UsersController::dashboard, Plan 03-03a Task 3).
        //
        // Why bootstrap() instead of OauthController::callback(): per-request binding
        // is order-sensitive (listener must register BEFORE upsert dispatches the
        // event in the same request). Bootstrap-time binding is hands-off — the
        // listener exists for the lifetime of the PHP process, so timing is moot.
        \Cake\Event\EventManager::instance()->on(
            'SlugCollisionSuffixApplied',
            function (\Cake\Event\Event $e): void {
                $data = $e->getData();
                if (!is_array($data) || !isset($data['slug'], $data['base'])) {
                    return;
                }
                // Pull the active request session lazily (the listener fires from inside
                // a controller action, so a request is in flight). Router::getRequest()
                // returns the active ServerRequest.
                $request = \Cake\Routing\Router::getRequest();
                if ($request === null) {
                    return;
                }
                $request->getSession()->write('Flash.slug_collision_suffix', [
                    'slug' => (string)$data['slug'],
                    'base' => (string)$data['base'],
                ]);
            }
        );

        // Load more plugins here
```

**Note**: `src/Application.php` is now a Plan 03-02 modified file — add it to this plan's `files_modified` mental list (the frontmatter already covers controllers; the `Application.php` edit is small and tightly coupled to the OauthController extension, so it stays in this task). The acceptance criteria below grep for both the listener registration and the import.

**OauthController::callback note**: do NOT register the listener inside `callback()`. The Application::bootstrap() registration is the SOLE binding point. Keep `callback()` focused on PAR/identity/redirect logic only.

**D. Add tests** to `tests/TestCase/Controller/AuthControllerTest.php`:

```php
public function testStartBlueskyPersistsPendingMessageBodyToSession(): void
{
    // Setup OAuth mocks like Phase 2 testStartBlueskyRedirectsToAs.
    $this->mockParEndpointSuccess();  // helper from Phase 2 (or inline addMockResponse)
    $this->enableCsrfToken();
    $this->post('/login/bluesky', [
        'pending_message_body' => 'メッセージ復元テスト',
        'pending_message_inbox_id' => '11111111-1111-1111-1111-111111111111',
    ]);
    $this->assertResponseCode(302);
    $this->assertSession('メッセージ復元テスト', 'pending_message_body');
    $this->assertSession('11111111-1111-1111-1111-111111111111', 'pending_message_inbox_id');
}
```

(If `mockParEndpointSuccess` doesn't exist, inline the `Client::addMockResponse` call per Phase 2 patterns — see existing AuthControllerTest setUp.)

**E. Add tests** to `tests/TestCase/Controller/OauthControllerCallbackTest.php`:

```php
public function testCallbackWithPendingInboxRedirectsToSlugWithRestored(): void
{
    // Re-use Phase 2 happy-path callback fixture (mocked token + profile responses).
    $this->primePhase2HappyPathMocks();  // or inline as in Phase 2 test
    $this->session([
        'Oauth' => ['state' => 'real_state_abc', 'pkce_verifier' => 'verifier'],
        'pending_message_inbox_id' => '11111111-1111-1111-1111-111111111111',
        'pending_message_body' => 'restored body',
    ]);
    $this->get('/oauth/callback?code=valid&state=real_state_abc&iss=https://bsky.social');
    $this->assertResponseCode(302);
    $this->assertHeader('Location', '/alice?restored=1');
    $this->assertSession('restored body', 'pending_message_body');  // body retained for form to consume
}

public function testCallbackWithoutPendingInboxRedirectsToDashboard(): void
{
    $this->primePhase2HappyPathMocks();
    $this->session(['Oauth' => ['state' => 'real_state_abc', 'pkce_verifier' => 'verifier']]);
    $this->get('/oauth/callback?code=valid&state=real_state_abc&iss=https://bsky.social');
    $this->assertResponseCode(302);
    $this->assertHeader('Location', '/dashboard');
}
```

**F. Verify**:
```bash
composer test -- --filter AuthControllerTest
composer test -- --filter OauthControllerCallbackTest
composer test
vendor/bin/phpstan analyse src/Controller/AuthController.php src/Controller/OauthController.php
```
  </action>

  <acceptance_criteria>
    - `grep -c 'pending_message_body' src/Controller/AuthController.php` ≥ 1
    - `grep -c 'pending_message_inbox_id' src/Controller/AuthController.php` ≥ 1
    - `grep -c 'pending_message_inbox_id' src/Controller/OauthController.php` ≥ 1
    - `grep -c "?restored=1" src/Controller/OauthController.php` = 1
    - `grep -c "EventManager::instance().*on.*SlugCollisionSuffixApplied" src/Application.php` = 1   # Warning 4 fix: listener registered globally in bootstrap(), NOT per-request in OauthController::callback
    - `grep -c 'Flash.slug_collision_suffix' src/Application.php` = 1
    - `grep -c "EventManager::instance().*on.*SlugCollisionSuffixApplied" src/Controller/OauthController.php` = 0   # Warning 4 fix: listener moved out of callback() — no per-request ordering concern
    - `grep -c 'use Cake.Routing.Router' src/Application.php` = 1   # Router::getRequest() needed inside the listener
    - `composer test -- --filter AuthControllerTest 2>&1 | grep -E 'OK \(|FAILURES'` shows OK
    - `composer test -- --filter OauthControllerCallbackTest 2>&1 | grep -E 'OK \(|FAILURES'` shows OK
    - Phase 2 Plan 02-04 baseline tests still green: `composer test -- --filter OauthControllerCallbackTest 2>&1 | grep -E '\.\.\.\..*OK'` — NO regressions vs Phase 2 verification baseline
  </acceptance_criteria>

  <verify>
    <automated>composer test -- --filter AuthControllerTest && composer test -- --filter OauthControllerCallbackTest && vendor/bin/phpstan analyse src/Application.php src/Controller/AuthController.php src/Controller/OauthController.php</automated>
  </verify>

  <done>AuthController::startBluesky persists pending_message_body / pending_message_inbox_id from POST data into session before PAR. OauthController::callback redirects to /<slug>?restored=1 when pending_message_inbox_id present (consuming inbox_id, keeping body). Global event listener registered in Application::bootstrap() captures SlugCollisionSuffixApplied → session Flash.slug_collision_suffix (Warning 4 fix: bootstrap-time binding eliminates per-request ordering concerns). Phase 2 callback tests still pass. New tests for both branches (with-pending / without-pending) pass.</done>
</task>

<task type="auto">
  <name>Task 4: send.php template + full MessagesControllerTest send POST coverage</name>
  <files>templates/Messages/send.php, tests/TestCase/Controller/MessagesControllerTest.php</files>

  <read_first>
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-UI-SPEC.md §1 Send Form (DOM contract verbatim) + §5 Empty/状態系コピー (is_accepting=false / 自分の inbox 訪問時) + §3 Char Counter
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-CONTEXT.md D-13 / D-14 / D-15 / D-16 / D-17 / D-38 (verbatim copy)
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-PATTERNS.md §8 send.php template + Shared "Template shape"
    - /home/claude/projects/tamabox/templates/Pages/home.php (analog: Form->create(null, [...]) + primary-button + display heading; CSRF auto)
    - /home/claude/projects/tamabox/templates/Users/dashboard.php (analog: @var docblock + h() escape + dashboard-page wrapper)
    - /home/claude/projects/tamabox/src/Controller/MessagesController.php (Task 1 output — view vars set: inbox, isAuthenticated, isOwnInbox, restoredBody, sentFlash)
    - /home/claude/projects/tamabox/tests/TestCase/Controller/AuthControllerTest.php (analog: enableCsrfToken + Configure + Client mock for Phase 2; this task adds non-OAuth tests so HTTP mocks are NOT needed)
  </read_first>

  <action>
**A. Create `templates/Messages/send.php`** following UI-SPEC §1 verbatim. Variables set by controller: `$inbox` (Entity), `$isAuthenticated` (bool), `$isOwnInbox` (bool), `$restoredBody` (string).

```php
<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Inbox $inbox
 * @var bool $isAuthenticated
 * @var bool $isOwnInbox
 * @var string $restoredBody
 *
 * Send page — UI-SPEC §1 (Send Form contract).
 * D-13 unauthenticated POST flow + D-14/D-15 consent + D-16/D-17 body validation + D-38 self-inbox notice.
 */
$this->assign('title', h((string)$inbox->display_name) . ' の受信箱');

$displayName = (string)$inbox->display_name;
$slug = (string)$inbox->slug;
$probabilityPct = (int)round((float)$inbox->ssr_probability * 100);
$welcomeMessage = $inbox->welcome_message;
$isAccepting = (bool)$inbox->is_accepting;
?>
<div class="send-form-page">
    <header class="inbox-header">
        <h1><?= h($displayName) ?> の受信箱</h1>
        <?php if ($isOwnInbox): ?>
            <p class="inbox-self-notice">これはあなたの受信箱です。<a href="/dashboard">/dashboard で受信一覧</a></p>
        <?php endif; ?>
    </header>

    <?php if ($welcomeMessage !== null && $welcomeMessage !== ''): ?>
        <section class="welcome-message">
            <h2><?= h($displayName) ?> から:</h2>
            <p><?= nl2br(h((string)$welcomeMessage)) ?></p>
        </section>
    <?php endif; ?>

    <?php if (!$isAccepting): ?>
        <p class="empty-state">現在この受信箱は受け付けていません</p>
        <p class="text-secondary">受け取り再開をお待ちください。</p>
    <?php else: ?>
        <?= $this->Form->create(null, [
            'url' => '/' . h($slug),
            'type' => 'post',
            'class' => 'send-form',
        ]) ?>
            <textarea
                name="body"
                required
                maxlength="2000"
                rows="6"
                aria-describedby="body-counter body-help"
                class="send-form__body"
            ><?= h($restoredBody) ?></textarea>
            <p id="body-help" class="text-secondary">最大 2000 文字、改行可、絵文字対応</p>
            <p id="body-counter" class="char-counter" aria-live="polite">
                <span data-counter><?= mb_strlen($restoredBody) ?></span> / 2000
            </p>

            <label class="consent-label">
                <input type="checkbox" name="consent" value="1" required>
                このメッセージは抽選で送信者の Bluesky アカウントが開示される可能性があります(現在の確率: <strong><?= $probabilityPct ?>%</strong>)
            </label>

            <?php if ($isAuthenticated): ?>
                <button type="submit" class="button primary-button">送信する</button>
            <?php else: ?>
                <input type="hidden" name="pending_message_inbox_id" value="<?= h((string)$inbox->id) ?>">
                <input type="hidden" name="pending_message_body_carrier" value="1">
                <button
                    type="submit"
                    class="button primary-button"
                    formaction="/login/bluesky"
                    formmethod="post"
                    formnovalidate
                >Bluesky でログインして送信</button>
            <?php endif; ?>
        <?= $this->Form->end() ?>
    <?php endif; ?>
</div>

<?php
// Char counter — vanilla JS, progressive enhancement (works without JS via maxlength).
$this->Html->scriptBlock(<<<JS
(function () {
    var ta = document.querySelector('.send-form__body');
    var counter = document.querySelector('[data-counter]');
    if (!ta || !counter) return;
    function update() {
        counter.textContent = String([...ta.value].length);
    }
    ta.addEventListener('input', update);
    update();
})();
JS, ['block' => false]);
?>
```

**Critical UX detail**: For unauthenticated users, the button MUST submit to `/login/bluesky` POST while carrying the textarea body as `pending_message_body`. Easiest: use `formaction="/login/bluesky"` + `formmethod="post"` so the textarea's `name="body"` value is sent with the same form. AuthController::startBluesky reads `body` field NAME from POST data — but we want it stashed as `pending_message_body`. **Two options**:

(a) Rename the textarea's name dynamically OR change AuthController to read `body` and copy to `pending_message_body` session key.

(b) Submit the form to MessagesController::send POST (the existing `action="/<slug>"`), let `stashAndRedirectToLogin` (Task 1 helper) read `body` and stash + redirect to /login/bluesky GET. The POST-then-redirect-then-form-resubmit dance from Phase 2 doesn't apply here because we're inside the same browser flow.

**Decision (option b — cleaner)**: Remove the `formaction` override. The unauth button submits to `/<slug>` POST. MessagesController::send detects unauth, calls `stashAndRedirectToLogin` which writes session keys + redirects to `/login/bluesky` (GET). AuthController::startBluesky for GET shows nothing currently — extend it to read session keys (already added in Task 3 from POST data), but here body comes via the redirect chain. **Simpler**: in `stashAndRedirectToLogin`, redirect to `/login/bluesky` via POST is not possible from a controller redirect. Instead, redirect to GET `/login/bluesky` and have AuthController::startBluesky read session-stashed values directly (NOT POST body). Update Task 3 logic accordingly:

In `AuthController::startBluesky`, change the read pattern to ALSO read session-pending values:

```php
// Already-stashed by MessagesController::stashAndRedirectToLogin (D-13 GET path).
$session = $this->request->getSession();
$existingBody = $session->read('pending_message_body');
$existingInbox = $session->read('pending_message_inbox_id');
// Then the new POST-data-from-form-direct path:
$pendingBody = $this->request->getData('pending_message_body');
$pendingInbox = $this->request->getData('pending_message_inbox_id');
if (is_string($pendingBody) && $pendingBody !== '') {
    $session->write('pending_message_body', $pendingBody);
}
if (is_string($pendingInbox) && $pendingInbox !== '') {
    $session->write('pending_message_inbox_id', $pendingInbox);
}
```

If session values were already set by a prior MessagesController POST→302→GET /login/bluesky chain, they remain. If the form POST'd directly to /login/bluesky with the form body (option a), they get written from POST data. Both paths converge.

**Confirmed approach**: Use option (b) — form `action="/<slug>"` POST. Remove `formaction` from the unauth button. The button label changes but submit destination is the same. MessagesController stashes + redirects to /login/bluesky (GET). Update `send.php` template:

```php
<?php if ($isAuthenticated): ?>
    <button type="submit" class="button primary-button">送信する</button>
<?php else: ?>
    <button type="submit" class="button primary-button">Bluesky でログインして送信</button>
<?php endif; ?>
```

Drop the hidden inputs.

**B. Extend `tests/TestCase/Controller/MessagesControllerTest.php`** (add to file from Task 1) with full POST coverage:

```php
public function testSendPostUnauthenticatedStashesBodyAndRedirectsToLogin(): void
{
    $this->enableCsrfToken();
    $this->post('/alice', [
        'body' => 'unauth body',
        'consent' => '1',
    ]);
    $this->assertResponseCode(302);
    $this->assertHeader('Location', '/login/bluesky');
    $this->assertSession('unauth body', 'pending_message_body');
    $this->assertSession('11111111-1111-1111-1111-111111111111', 'pending_message_inbox_id');
}

public function testSendPostAuthenticatedHappyPathInsertsMessage(): void
{
    $this->enableCsrfToken();
    $this->loginAsBob();  // helper — see below
    $countBefore = $this->fetchTable('Messages')
        ->find()
        ->where(['inbox_id' => '11111111-1111-1111-1111-111111111111'])
        ->count();
    $this->post('/alice', [
        'body' => 'こんにちは alice',
        'consent' => '1',
    ]);
    $this->assertResponseOk();  // send_done renders directly
    $this->assertResponseContains('送信しました。受け手が開封したとき');
    $countAfter = $this->fetchTable('Messages')
        ->find()
        ->where(['inbox_id' => '11111111-1111-1111-1111-111111111111'])
        ->count();
    $this->assertSame($countBefore + 1, $countAfter);
    $msg = $this->fetchTable('Messages')->find()
        ->where(['inbox_id' => '11111111-1111-1111-1111-111111111111'])
        ->order(['created_at' => 'DESC'])
        ->first();
    $this->assertSame(64, strlen((string)$msg->ssr_seed));
    $this->assertSame('bluesky', $msg->sender_provider);
    $this->assertNotEmpty($msg->sender_handle_snapshot);
}

public function testSendPostConsentMissingRedirectsWithError(): void
{
    $this->enableCsrfToken();
    $this->loginAsBob();
    $this->post('/alice', ['body' => 'no consent']);
    $this->assertResponseCode(302);
    $this->assertHeader('Location', '/alice');
    $flash = $this->_requestSession->read('Flash.flash');
    $this->assertIsArray($flash);
    $this->assertMatchesRegularExpression('/同意/', (string)$flash[0]['message']);
}

public function testSendPostBodyTooLongRedirectsWithError(): void
{
    $this->enableCsrfToken();
    $this->loginAsBob();
    $this->post('/alice', [
        'body' => str_repeat('a', 2001),
        'consent' => '1',
    ]);
    $this->assertResponseCode(302);
    $flash = $this->_requestSession->read('Flash.flash');
    $this->assertMatchesRegularExpression('/2000 文字/', (string)$flash[0]['message']);
}

public function testSendPostBodyEmptyRedirectsWithError(): void
{
    $this->enableCsrfToken();
    $this->loginAsBob();
    $this->post('/alice', [
        'body' => '',
        'consent' => '1',
    ]);
    $this->assertResponseCode(302);
    $flash = $this->_requestSession->read('Flash.flash');
    $this->assertMatchesRegularExpression('/本文/', (string)$flash[0]['message']);
}

public function testSendPostToClosedInboxRedirectsWithError(): void
{
    $this->enableCsrfToken();
    $this->loginAsBob();
    $this->post('/charlie', [  // is_accepting=0
        'body' => 'try',
        'consent' => '1',
    ]);
    $this->assertResponseCode(302);
    $flash = $this->_requestSession->read('Flash.flash');
    $this->assertMatchesRegularExpression('/受け付けていません/', (string)$flash[0]['message']);
}

public function testSendDoneOmitsSsrResult(): void
{
    $this->enableCsrfToken();
    $this->loginAsBob();
    $this->post('/alice', [
        'body' => 'check no ssr leak',
        'consent' => '1',
    ]);
    $this->assertResponseOk();
    $this->assertResponseNotContains('is_ssr');
    $this->assertResponseNotContains('ssr_seed');
    $this->assertResponseNotContains('hit');
    $this->assertResponseNotContains('miss');
    $this->assertResponseNotContains('抽選');
}

public function testSendGetIsOwnInboxShowsSelfNotice(): void
{
    $this->loginAsAlice();
    $this->get('/alice');
    $this->assertResponseOk();
    $this->assertResponseContains('これはあなたの受信箱です');
}

public function testSendPostToOwnInboxStillInserts(): void
{
    // Warning 3 fix (D-33: 自己送信許可、特別扱いせず通常 send flow を通す).
    // Authenticated alice POSTing to her own /alice inbox MUST insert a row, NOT be
    // diverted to a self-send special case. testSendGetIsOwnInboxShowsSelfNotice covers
    // the GET-side notice copy; this test locks the POST-side happy path.
    $this->enableCsrfToken();
    $this->loginAsAlice();
    $countBefore = $this->fetchTable('Messages')
        ->find()
        ->where(['inbox_id' => '11111111-1111-1111-1111-111111111111'])
        ->count();
    $this->post('/alice', [
        'body' => '自分の受信箱に自分で送るテスト',
        'consent' => '1',
    ]);
    $this->assertResponseOk();  // send_done renders directly
    $this->assertResponseContains('送信しました。受け手が開封したとき');
    $countAfter = $this->fetchTable('Messages')
        ->find()
        ->where(['inbox_id' => '11111111-1111-1111-1111-111111111111'])
        ->count();
    $this->assertSame($countBefore + 1, $countAfter, 'Self-send must insert a row (D-33)');
    /** @var \App\Model\Entity\Message $msg */
    $msg = $this->fetchTable('Messages')->find()
        ->where(['inbox_id' => '11111111-1111-1111-1111-111111111111'])
        ->order(['created_at' => 'DESC'])
        ->first();
    // sender_user_id matches the inbox owner (alice's user UUID = inbox.user_id).
    $this->assertSame('11111111-1111-1111-1111-111111111111', (string)$msg->sender_user_id);
    /** @var \App\Model\Entity\Inbox $inbox */
    $inbox = $this->fetchTable('Inboxes')->get('11111111-1111-1111-1111-111111111111');
    $this->assertSame((string)$inbox->user_id, (string)$msg->sender_user_id, 'D-33: self-send goes through normal flow with sender_user_id == inbox.user_id');
}

public function testSendDisplaysWelcomeMessageScriptEscaped(): void
{
    // Warning 2 fix (T-03-02-04 XSS test): welcome_message is receiver-controlled and
    // displayed on /<slug> via nl2br(h(...)). Insert a script tag into the inbox's
    // welcome_message and assert it renders escaped, not raw.
    /** @var \App\Model\Table\InboxesTable $inboxesTable */
    $inboxesTable = $this->fetchTable('Inboxes');
    $alice = $inboxesTable->get('11111111-1111-1111-1111-111111111111');
    $alice = $inboxesTable->patchEntity(
        $alice,
        ['welcome_message' => '<script>alert(2)</script>'],
        ['accessibleFields' => ['welcome_message' => true]]
    );
    $inboxesTable->saveOrFail($alice);

    $this->get('/alice');
    $this->assertResponseOk();
    $body = (string)$this->_response->getBody();
    $this->assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $body, 'welcome_message must be HTML-escaped');
    $this->assertStringNotContainsString('<script>alert(2)</script>', $body, 'raw <script> must NOT appear in rendered HTML');
}

// === helpers ===
private function loginAsBob(): void
{
    /** @var \App\Model\Entity\User $bob */
    $bob = $this->fetchTable('Users')->get('22222222-2222-2222-2222-222222222222', ['contain' => ['UserIdentities']]);
    $this->session(['Auth' => $bob]);
}

private function loginAsAlice(): void
{
    /** @var \App\Model\Entity\User $alice */
    $alice = $this->fetchTable('Users')->get('11111111-1111-1111-1111-111111111111', ['contain' => ['UserIdentities']]);
    $this->session(['Auth' => $alice]);
}
```

**Critical session key check**: The `$this->session(['Auth' => $user])` priming key MUST match what `cakephp/authentication` SessionAuthenticator reads. Phase 2 Plan 02-04 Application configuration determines this. **Inspect `src/Application.php::getAuthenticationService` for the `sessionKey` setting** before writing tests; default is `'Auth'`. If it's something else (e.g. `'Authentication'` or `'Auth.User'`), update the helpers accordingly. To avoid guesswork, refactor login helpers to use `$this->_setupRequest` + Authentication->setIdentity ON the test request:

Actually the simplest reliable pattern is the one Phase 2 used in OauthControllerCallbackTest — drive setIdentity through a real login flow. Since that requires HTTP mocks, the alternative is direct session priming. **Read `src/Application.php` first** to confirm the session key, then commit accordingly.

**C. Verify**:
```bash
composer test -- --filter MessagesControllerTest
composer test
vendor/bin/phpstan analyse src/Controller/MessagesController.php
composer cs-check
```
  </action>

  <acceptance_criteria>
    - `grep -c "@var \\\\App\\\\Model\\\\Entity\\\\Inbox \$inbox" templates/Messages/send.php` = 1
    - `grep -c "現在この受信箱は受け付けていません" templates/Messages/send.php` = 1
    - `grep -c "Bluesky でログインして送信" templates/Messages/send.php` = 1
    - `grep -c "送信する" templates/Messages/send.php` ≥ 1   # at least the auth button
    - `grep -c "このメッセージは抽選で送信者の Bluesky アカウントが開示される可能性があります" templates/Messages/send.php` = 1
    - `grep -c "これはあなたの受信箱です" templates/Messages/send.php` = 1
    - `grep -c 'maxlength="2000"' templates/Messages/send.php` = 1
    - `grep -c "name=\"consent\"" templates/Messages/send.php` = 1
    - `grep -c 'nl2br(h(' templates/Messages/send.php` ≥ 1   # welcome_message escape
    - `grep -c '<?= h(' templates/Messages/send.php` ≥ 3
    - `grep -E 'public function test[A-Z]' tests/TestCase/Controller/MessagesControllerTest.php | wc -l` ≥ 14   # Warning 2 + Warning 3 fixes added 2 tests
    - `grep -c 'testSendPostToOwnInboxStillInserts' tests/TestCase/Controller/MessagesControllerTest.php` = 1   # Warning 3: D-33 self-send POST happy path
    - `grep -c 'testSendDisplaysWelcomeMessageScriptEscaped' tests/TestCase/Controller/MessagesControllerTest.php` = 1   # Warning 2: T-03-02-04 XSS test
    - `grep -c '&lt;script&gt;alert(2)&lt;/script&gt;' tests/TestCase/Controller/MessagesControllerTest.php` = 1   # Warning 2: escaped expectation
    - `composer test 2>&1 | tail -5 | grep -E 'OK|FAILURES'` shows OK
    - `vendor/bin/phpstan analyse src/Controller/MessagesController.php 2>&1` exit code 0
    - `composer cs-check 2>&1 | grep -E 'FOUND.*ERROR'` shows zero errors OR no output (=0 errors)
  </acceptance_criteria>

  <verify>
    <automated>composer test && vendor/bin/phpstan analyse src/Controller/MessagesController.php templates/Messages/send.php 2>/dev/null; composer cs-check</automated>
  </verify>

  <done>send.php template renders form per UI-SPEC §1 verbatim including all fixed copy strings. unauth button label switches to "Bluesky でログインして送信". is_accepting=false hides form. self-inbox notice (D-38) shown when own inbox. ≥14 MessagesControllerTest cases cover all paths (404 / 301 / unauth GET / unauth POST stash / auth happy / consent missing / body too long / body empty / closed inbox / send-done no SSR leak / self-notice GET / D-33 self-send POST happy / welcome_message XSS escape / report 501). Full composer test green, phpstan level 8 OK, phpcs PASS.</done>
</task>

</tasks>

<verification>

After all tasks complete:

```bash
# Per-test-class
composer test -- --filter MessagesControllerTest
composer test -- --filter MessagesTableTest
composer test -- --filter AuthControllerTest
composer test -- --filter OauthControllerCallbackTest

# Full suite — must remain green (Phase 1 + Phase 2 + Phase 3 plans 01 + 02)
composer test

# Static analysis
vendor/bin/phpstan analyse src/Controller/MessagesController.php src/Controller/AuthController.php src/Controller/OauthController.php src/Model/Table/MessagesTable.php

# Coding standards
composer cs-check

# Routes inspection
bin/cake routes | grep -E '(send|report|open|block|settings)' | head -10

# 7-truth E2E manual check (smoke):
# 1. GET /alice as anonymous → 200 + 'Bluesky でログインして送信'
# 2. GET /alice with browser session as bob → 200 + '送信する'
# 3. POST /alice with body + consent as bob → 200 send_done
# 4. SELECT * FROM messages ORDER BY created_at DESC LIMIT 1 → ssr_seed 64-hex, sender_handle_snapshot fresh
```

All MUST pass.

</verification>

<success_criteria>

This plan succeeds when:

1. **AUTH-03**: Anonymous POST to `/<slug>` does NOT INSERT a message; instead, body is stashed in session and user is redirected to `/login/bluesky`. Verified by `testSendPostUnauthenticatedStashesBodyAndRedirectsToLogin`.
2. **MSG-01**: Authenticated POST with valid body + consent INSERTs a row in `messages` with the correct body + body_length (mb_strlen accurate including emoji). Verified by `testSendPostAuthenticatedHappyPathInsertsMessage`.
3. **MSG-02**: `messages.is_ssr` is computed at INSERT time (never updated later). Verified by SsrJudge unit tests + table test asserting the column is set.
4. **MSG-03**: `messages.ssr_seed` = `sha256(server_secret . message_id . created_at_micro)` — 64 hex chars. Verified by `testSendMessageDeterministicSeedFromSecret`.
5. **MSG-04**: `messages.sender_handle_snapshot` / `sender_avatar_url_snapshot` / `sender_profile_url_snapshot` populated from `user_identities.*_cached` at SEND time. Verified by `testSendMessagePersistsSsrFieldsAndSnapshot`.
6. **MSG-05**: Send form contains the consent checkbox with the verbatim D-15 copy; missing consent → 302 + Flash error + 0 INSERTs. Verified by `testSendPostConsentMissingRedirectsWithError`.
7. **D-13**: After OAuth login from send-form unauth flow, callback redirects to `/<slug>?restored=1` (not `/dashboard`); send.php restores body from session and consumes the inbox_id key. Verified by callback test + send GET test with `?restored=1`.
8. **D-19**: send_done.php contains NO SSR result (no 'is_ssr', 'hit', 'miss', '抽選'). Verified by `testSendDoneOmitsSsrResult`.
9. **D-35**: `report` returns 501 (locked Phase 4 hand-off contract); MessagesController::initialize allowUnauthenticated includes 'report' so AuthenticationMiddleware does not 302-redirect before the action runs (Blocker fix). Verified by `testReportReturns501Stub` strict 501 assertion.
10. **D-04**: GET `/<old_slug>` returns 301 to `/<current_slug>` when slug_previous matches. Verified by `testSendGetSlugPreviousRedirectsToCurrent`.
11. **D-33**: Authenticated user POSTing to their own inbox slug inserts a row through the normal send flow (no self-send special case). Verified by `testSendPostToOwnInboxStillInserts` (Warning 3 fix).
12. **T-03-02-04 XSS**: `welcome_message` (receiver-controlled, rendered via nl2br(h(...)) on /<slug>) is HTML-escaped. Verified by `testSendDisplaysWelcomeMessageScriptEscaped` (Warning 2 fix).

phpstan level 8 [OK]. phpcs zero errors. composer test fully green (Phase 1 + Phase 2 + Phase 3 plans 01-02 cumulative).

</success_criteria>

<output>
After completion, create `.planning/phases/03-inbox-message-ssr-reveal/03-02-SUMMARY.md` documenting:

- All files created / modified (controller / table / templates / routes / tests)
- Test counts (MessagesControllerTest ≥14 — bumped by +2 for Warning 2/3 fixes (testSendDisplaysWelcomeMessageScriptEscaped + testSendPostToOwnInboxStillInserts), MessagesTableTest ≥5 new, AuthControllerTest +1, OauthControllerCallbackTest +2)
- Sticky note for Wave 3a: `MessagesController::open` is currently a 501 placeholder — Wave 3a Task 1 must replace it with `markOpened` logic and update `testOpenReturns501Placeholder` to assert 302 (success redirect) instead. The integration test gives Wave 3a a clear "did the body actually get implemented?" signal.
- Sticky note for Wave 3a: `Flash.slug_collision_suffix` session key is now written by the global SlugCollisionSuffixApplied listener registered in `Application::bootstrap()` (Warning 4 fix — bootstrap-time binding instead of per-request inside OauthController::callback). Dashboard rendering (UsersController::dashboard, Plan 03-03a Task 3) must check + flash + delete this key once. Format: `['slug' => string, 'base' => string]`.
- Sticky note for Wave 3b: `BlocksController::create` route is wired (`POST /block/<senderUserId>`); class doesn't exist yet → Wave 3b Task 1 creates it + implements 501 stub.
- Decision recorded: `processSend` renders `Messages/send_done.php` directly (NOT redirect-with-?sent=1) per UI-SPEC §9 (dedicated page intent).
- Phase 2 callback test baseline: confirm `composer test -- --filter OauthControllerCallbackTest` still passes with NO regressions vs Phase 2 verifier baseline (count + names).
</output>
