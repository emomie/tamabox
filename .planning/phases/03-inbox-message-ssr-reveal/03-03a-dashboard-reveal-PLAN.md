---
phase: 03-inbox-message-ssr-reveal
plan: 03a
type: execute
wave: 3
depends_on:
  - 03-01
  - 03-02
files_modified:
  - src/Controller/UsersController.php
  - src/Controller/InboxesController.php
  - src/Controller/MessagesController.php
  - src/Model/Table/MessagesTable.php
  - templates/Users/dashboard.php
  - templates/Inboxes/settings.php
  - tests/TestCase/Controller/UsersControllerTest.php
  - tests/TestCase/Controller/InboxesControllerTest.php
  - tests/TestCase/Controller/MessagesControllerTest.php
autonomous: true
requirements:
  - INBOX-02
  - INBOX-03
  - MSG-06
  - MSG-07
tags:
  - dashboard
  - settings
  - open
  - reveal
  - ssr
  - paginator
  - controller
  - integration-test

must_haves:
  truths:
    - "GET /dashboard requires authentication; renders templates/Users/dashboard.php with: (a) handle header (existing Phase 2 behavior preserved), (b) slug-collision flash (D-06) consumed once from session 'Flash.slug_collision_suffix' if set, (c) paginated 20-per-page receive list ordered by created_at DESC (D-23 / D-24), (d) settings sidebar (or stacked on mobile) with current ssr_probability/welcome_message/is_accepting values"
    - "Receive list renders <details> rows per UI-SPEC §2 — unread = font-weight 600 + ● icon + summary preview (mb_substr 80 chars), opened = normal weight + ✓ icon; <summary> click expands body; if unread, shows 'open form' POST button; if opened, shows SSR reveal section embedded inline (D-25 / D-27)"
    - "POST /dashboard/messages/{id}/open (replacing Plan 03-02 Task 1 placeholder 501): authenticates owner, calls MessagesTable::markOpened($id, $ownerUserId), 302 redirects /dashboard; idempotent — re-open does NOT update opened_at again (D-27)"
    - "MessagesTable::markOpened($messageId, $ownerUserId) verifies inbox.user_id == ownerUserId, sets messages.opened_at to FrozenTime::now() ONLY if currently NULL, returns the entity"
    - "GET /dashboard/settings returns 302 to /dashboard (settings rendered inline in dashboard); POST /dashboard/settings authenticates owner, validates ssr_probability (0..100 int → 0..1 decimal), welcome_message (≤1000 chars), is_accepting (boolean), patches Inbox entity, 302 redirects /dashboard with Flash.success '保存しました'"
    - "ssr_probability INPUT comes as integer percent 0..100 from <input type='number'>; controller divides by 100 → DECIMAL(4,3) string ('0.000' to '1.000', step 0.010); D-12 contract: existing messages.ssr_probability_at_send NEVER changes when inbox.ssr_probability is updated"
    - "SSR reveal markup (UI-SPEC §4): hit = banner '★ 抽選 hit — 送信者が開示されました' + sender card with avatar 64px (onerror→/img/default-avatar.svg) + handle linked https://bsky.app/profile/<handle> + 'Bluesky プロフィールを見る' external link + 'このユーザーをブロック' (501 stub form) + '通報する' (501 stub form); miss = single text line '★ 抽選 miss(送信者は匿名のまま)'"
    - "Empty state copy (UI-SPEC §5): zero messages → 'まだ受信したメッセージはありません' + 'あなたの inbox URL を Bluesky でシェアしてみましょう: <code>https://tamabox.emomie.com/<slug></code>'"
    - "Pagination (UI-SPEC §10 / D-24): 20 messages/page; ?page=N query; CakePHP Paginator->numbers() rendered; out-of-range page → 'そのページはありません。' + back link to /dashboard"
    - "Self-flash (D-06): if Flash.slug_collision_suffix present in session, render flash 'あなたの slug: {slug} になりました({base} は他のユーザーに使われていたため)', then session->delete the key (consume-once)"
    - "Integration tests cover: dashboard unauth (302→/), dashboard auth empty inbox (renders empty state), dashboard auth with messages (renders rows + paginator), dashboard with collision flash session (renders flash + clears session), dashboard out-of-range page (catches Cake\\Datasource\\Paging\\Exception\\PageOutOfBoundsException for CakePHP 4.5 — UI-SPEC §5 fallback copy), dashboard messages.body XSS escape (T-03-02-04 — Warning 2 fix), settings POST happy (saves + redirect), settings POST ssr_probability over 100 (422), open POST happy (302 + opened_at set), open POST already opened (302 + opened_at unchanged), open POST other-user's inbox (403)"
  artifacts:
    - path: "src/Controller/UsersController.php"
      provides: "dashboard expanded — slug header + collision flash + paginated message list + inbox + settings view vars"
      contains: "Paginator|fetchTable\\('Messages'\\)"
    - path: "src/Controller/InboxesController.php"
      provides: "settings (POST handler — patchEntity + validation + save + flash)"
      min_lines: 60
      contains: "class InboxesController"
    - path: "src/Controller/MessagesController.php"
      provides: "open() body filled in (replaces Plan 03-02 501 placeholder)"
      contains: "markOpened"
    - path: "src/Model/Table/MessagesTable.php"
      provides: "markOpened method — idempotent UPDATE with ownership check"
      contains: "markOpened"
    - path: "templates/Users/dashboard.php"
      provides: "Full dashboard per UI-SPEC §2 + §3 + §10 — header + flash + receive list with <details> + settings inline + paginator"
      min_lines: 100
      contains: "details class=\"message-row\""
    - path: "templates/Inboxes/settings.php"
      provides: "Settings form (rendered as element OR inline in dashboard.php — planner picks element for cleanliness)"
      contains: "ssr_probability"
  key_links:
    - from: "UsersController::dashboard"
      to: "MessagesTable->find()->where(['inbox_id' => $inbox->id])->order(['created_at' => 'DESC']) + Paginator"
      via: "Paginator->paginate with limit=20, ?page=N from query"
      pattern: "Paginator|paginate"
    - from: "MessagesController::open"
      to: "MessagesTable::markOpened"
      via: "POST /dashboard/messages/{id}/open → ownership-checked UPDATE messages.opened_at"
      pattern: "markOpened"
    - from: "InboxesController::settings (POST)"
      to: "InboxesTable->patchEntity + saveOrFail"
      via: "ssr_probability_pct (0..100) → /100 → DECIMAL string; welcome_message null-or-string; is_accepting boolean from checkbox"
      pattern: "ssr_probability"
    - from: "templates/Users/dashboard.php SSR reveal"
      to: "/img/default-avatar.svg + https://bsky.app/profile/<handle>"
      via: "<img onerror=\"this.src='/img/default-avatar.svg'\"> + handle linked external"
      pattern: "default-avatar.svg|bsky.app/profile"
---

<objective>
Phase 3 の **受信ダッシュボード + 開封 UX + 設定 UI** を完成させ、INBOX-02 / INBOX-03 / MSG-06 / MSG-07 を closes する。Phase 3 の最後の機能ピース。

具体的には:
1. **`UsersController::dashboard`** を Phase 2 の最小実装から拡張: 認証ユーザの inbox を取得 → ページング 20 件のメッセージ一覧 + collision flash 消費 (D-06) + settings 用の view vars 渡し。
2. **`InboxesController::settings`** (新規): GET は 302 redirect /dashboard (UI 統合先)、POST は ssr_probability_pct (0..100 int) を 0..1 DECIMAL に変換 + welcome_message + is_accepting で patchEntity + 保存 + Flash.success。
3. **`MessagesController::open`** の本体実装 (Plan 03-02 で予約した 501 placeholder を置換): `MessagesTable::markOpened` 呼出 + 302 redirect /dashboard。
4. **`MessagesTable::markOpened($messageId, $ownerUserId)`** (新規): 受信箱 owner かを確認 (Inbox→User join)、`opened_at IS NULL` なら `FrozenTime::now()` で UPDATE、既に開封済なら no-op (D-27 idempotent)、他人の inbox の message なら ForbiddenException。
5. **`templates/Users/dashboard.php`** を全面書き換え (UI-SPEC §2 / §3 / §10): 
   - header (display_name + slug + welcome_message プレビュー)
   - collision flash (D-06)
   - 受信一覧: `<details>` ベースの段階開示 (D-25)、未開封 ● + 太字 / 開封済 ✓ + 通常、80 文字 preview、SSR hit 時の sender card (default-avatar fallback、bsky.app profile link、501 stub の通報/ブロックボタン)、SSR miss 時の miss テキスト
   - 空ステート (zero messages)
   - 設定フォーム (UI-SPEC §3、後段)
   - paginator (UI-SPEC §10、20/page)
6. **`templates/Inboxes/settings.php`** (新規 element として `templates/element/settings_form.php` に置くか、dashboard.php に直接インライン展開するかは executor 判断。本プランでは UI-SPEC §3 に沿った独立 partial template `templates/Inboxes/settings.php` を作成し、dashboard.php から `$this->element` で読む形を推奨)。
7. Integration tests: UsersControllerTest 新規、InboxesControllerTest 新規、MessagesControllerTest::testOpen* (3+ cases)。

Purpose:
- ROADMAP Phase 3 success criteria #2 (SSR 0-100% 設定、デフォルト 10%) → InboxesController::settings + UI
- ROADMAP Phase 3 success criteria #6 (受信一覧 + 未開封/開封済の視覚区分) → dashboard receive list + UI-SPEC §2 styling
- ROADMAP Phase 3 success criteria #7 (開封 → opened_at + SSR 露出) → MessagesController::open + UI-SPEC §4 reveal section
- D-06 衝突 suffix flash の 1 回限り表示 → session consume パターン
- D-25 段階的開示 UX → `<details>` + 開封ボタン + SSR reveal の DOM 構造
- D-27 既開封の再閲覧時の冪等性 → markOpened の `opened_at IS NULL` ガード

Output:
- 1 controller 拡張 (UsersController) + 1 controller 新規 (InboxesController) + 1 controller 拡張 (MessagesController::open)
- 1 table 拡張 (MessagesTable::markOpened)
- 1 template 拡張 (Users/dashboard.php) + 1 template 新規 (Inboxes/settings.php)
- 3 test ファイル
- composer test green

注意 (parallel safety): このプランは 03-03b と並行実行される。両者の files_modified は重ならない (03-03b: BlocksController, default-avatar.svg, tamabox.css, templates/Pages/home.php, routes は **既に Plan 03-02 で完成**)。タイミングが微妙な依存: 本プランの dashboard が `<img onerror="this.src='/img/default-avatar.svg'">` を吐くため、03-03b で SVG が作られていないと開発環境で broken-image フォールバックが見える。**本番影響なし**(SVG 不在は executor が両方完成後に検証する)、test も DOM 文字列 grep で確認するため SVG ファイル存在に依存しない。
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
@/home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-02-send-flow-PLAN.md
@/home/claude/projects/tamabox/src/Controller/UsersController.php
@/home/claude/projects/tamabox/src/Controller/MessagesController.php
@/home/claude/projects/tamabox/src/Controller/AppController.php
@/home/claude/projects/tamabox/src/Model/Table/MessagesTable.php
@/home/claude/projects/tamabox/src/Model/Table/InboxesTable.php
@/home/claude/projects/tamabox/templates/Users/dashboard.php
@/home/claude/projects/tamabox/templates/Pages/home.php
@/home/claude/projects/tamabox/tests/TestCase/Controller/AuthControllerTest.php
@/home/claude/projects/tamabox/tests/TestCase/Controller/MessagesControllerTest.php
@/home/claude/projects/tamabox/config/routes.php

<interfaces>
<!-- From Plan 03-01 / 03-02 -->

App\Controller\MessagesController:
  - public function open(string $id): Response  // currently 501 placeholder (Plan 03-02 Task 1)
  - This plan REPLACES the body with the markOpened call.

App\Model\Table\MessagesTable (Plan 03-02):
  - public function sendMessage(...) -- already implemented
  - This plan ADDS: public function markOpened(string $messageId, string $ownerUserId): \App\Model\Entity\Message

App\Model\Table\InboxesTable (Plan 03-01):
  - validation rules now include ssr_probability range, welcome_message length, is_accepting boolean (Plan 03-01 Task 4)
  - This plan CONSUMES the validation when patching from settings form.

Session keys (Plan 03-02 Task 3):
  - Flash.slug_collision_suffix: ['slug' => string, 'base' => string] | null
  - This plan CONSUMES this in dashboard rendering (D-06 — show once + delete).

User identity in test (Plan 03-02 Task 4 helpers):
  - $this->session(['Auth' => $userEntity])
  - Session key 'Auth' confirmed by reading src/Application.php::getAuthenticationService — read first.

UI-SPEC §2 receive list DOM (verbatim required):
  <details class="message-row" data-msg-id="{uuid}" data-state="{unread|opened}">
    <summary class="message-row__head">
      <span class="message-row__icon" aria-hidden="true">{● or ✓}</span>
      <time class="message-row__time" datetime="{iso}">{YYYY/MM/DD HH:MM}</time>
      <span class="message-row__preview">{mb_substr(body, 0, 80)}…</span>
    </summary>
    <div class="message-row__body">
      <p>{nl2br(h(body))}</p>
      <!-- if unread -->
      <form method="post" action="/dashboard/messages/{id}/open" class="open-form">
        <button type="submit" class="primary-button">開封する</button>
      </form>
      <!-- if opened, embed reveal directly -->
      <div class="ssr-reveal" data-outcome="{hit|miss}">
        <!-- hit -->
        <div class="ssr-reveal__banner">★ 抽選 hit — 送信者が開示されました</div>
        <div class="sender-card">
          <img class="sender-card__avatar" src="{avatar_url}" alt="{handle}" width="64" height="64"
               onerror="this.src='/img/default-avatar.svg'">
          <a class="sender-card__handle" href="https://bsky.app/profile/{handle}">@{handle}</a>
          <a class="button button-clear" href="{profile_url}" target="_blank" rel="noopener">Bluesky プロフィールを見る</a>
          <form method="post" action="/report/{id}" class="inline">
            <button type="submit" class="button-clear button-destructive">通報する</button>
          </form>
          <form method="post" action="/block/{sender_user_id}" class="inline">
            <button type="submit" class="button-clear button-destructive">このユーザーをブロック</button>
          </form>
        </div>
        <!-- miss -->
        <p class="ssr-reveal__miss text-secondary">★ 抽選 miss(送信者は匿名のまま)</p>
      </div>
    </div>
  </details>

UI-SPEC §3 settings form (verbatim):
  <form method="post" action="/dashboard/settings" class="settings-form">
    <fieldset>
      <legend>SSR 確率(送信者が開示される確率)</legend>
      <div class="probability-control">
        <input type="range" name="ssr_probability_pct_range" min="0" max="100" step="1" value="{X}" aria-label="確率スライダ" id="prob-range">
        <input type="number" name="ssr_probability_pct" min="0" max="100" step="1" value="{X}" aria-label="確率値" id="prob-number">
        <span class="probability-suffix">%</span>
      </div>
      <p class="text-secondary">デフォルト 10%、0% / 100% 設定時は確認ダイアログが表示されます</p>
    </fieldset>
    <fieldset>
      <legend>welcome message(送信フォーム上部に表示される歓迎文、任意)</legend>
      <textarea name="welcome_message" maxlength="1000" rows="4">{welcome}</textarea>
    </fieldset>
    <fieldset>
      <legend>受信を受け付ける</legend>
      <label><input type="checkbox" name="is_accepting" value="1" {checked}> 現在この受信箱でメッセージを受け付ける</label>
      <p class="text-secondary">OFF にすると /<slug> で送信フォームが非表示になります</p>
    </fieldset>
    <button type="submit" class="primary-button">保存する</button>
  </form>

  IMPLEMENTATION DECISION: Use SAME `name="ssr_probability_pct"` on both <input>s OR use range with `_range` suffix and rely on JS to sync them into the number input. Per UI-SPEC §3 implementation note: planner judgment. CHOICE: use TWO different names (`ssr_probability_pct_range` for range, `ssr_probability_pct` for number — server reads `ssr_probability_pct` only). JS syncs range→number on input. JS-off fallback: number is the source of truth, range is decorative. This avoids browser duplicate-name behavior (last-wins or first-wins varies).

UI-SPEC §5 empty state copy (verbatim):
  - 'まだ受信したメッセージはありません'
  - 'あなたの inbox URL を Bluesky でシェアしてみましょう: <code>https://tamabox.emomie.com/<slug></code>'
  - 'そのページはありません。' (paginator out-of-range)
  - 'これはあなたの受信箱です。' (D-38 self notice — already in Plan 03-02 send.php; also reused in dashboard for slug header)

UI-SPEC §11 flash variants — Plan 03-03b adds the `info` variant CSS rule. This plan emits the markup using CakePHP Flash component which automatically picks up the variant via `Flash->set(..., ['element' => 'info'])` or `Flash->info()` if available; otherwise use `Flash->set('msg', ['element' => 'default', 'params' => ['class' => 'info']])`. Read CakePHP 4.5 Flash docs / existing flash usage in Phase 2 for canonical pattern.

UI-SPEC §1 (D-38 self-notice strip — also surface on dashboard slug header):
  '<p class="inbox-self-notice">これはあなたの受信箱です。<a href="/dashboard">/dashboard で受信一覧</a></p>'

D-06 collision flash text (verbatim):
  'あなたの slug: {slug} になりました({base} は他のユーザーに使われていたため)'

Phase 1 schema reminder:
  - inboxes.ssr_probability is DECIMAL(4,3); CHECK constraint 0 <= x <= 1
  - inboxes.welcome_message VARCHAR(1000) NULL
  - inboxes.is_accepting TINYINT(1) NOT NULL DEFAULT 1
  - messages.opened_at DATETIME(6) NULL — set ONLY by markOpened

Pagination:
  - CakePHP 4.5 Paginator helper / PaginatorComponent
  - $this->paginate($table->find()->where([...])) returns ResultSetInterface
  - $this->Paginator->numbers() in template renders <nav> with page links
  - Paginator config: ['limit' => 20, 'order' => ['Messages.created_at' => 'DESC']]
</interfaces>
</context>

<threat_model>
## Trust Boundaries

| Boundary | Description |
|----------|-------------|
| browser → POST /dashboard/messages/{id}/open | CSRF-protected; ownership-validated server-side via inbox.user_id == identity |
| browser → POST /dashboard/settings | CSRF-protected; ownership-validated; ssr_probability_pct sanitized to int 0..100 |
| session Flash.slug_collision_suffix | Per-user session; consume-once pattern |
| message body display | Rendered through h() + nl2br escape; no HTML/Markdown allowed (D-17) |
| sender snapshot display | Rendered through h() escape; bsky.app profile URL constructed server-side |
| profile_url external link | target="_blank" rel="noopener" mandatory to prevent reverse-tabnabbing |

## STRIDE Threat Register

| Threat ID | Category | Component | Disposition | Mitigation Plan |
|-----------|----------|-----------|-------------|-----------------|
| T-03-03a-01 | Tampering (open arbitrary message) | MessagesController::open | mitigate | Server-side ownership check: load message → message.inbox.user_id MUST == identity user_id; ForbiddenException otherwise. Test `testOpenOtherUsersMessageReturns403` |
| T-03-03a-02 | Spoofing (settings update from non-owner) | InboxesController::settings POST | mitigate | Identity → fetchTable Inboxes where user_id = identity → patchEntity on THAT entity only; never trust an `id` from POST body. Test `testSettingsPostUpdatesOwnInboxOnly` |
| T-03-03a-03 | Tampering (probability range bypass) | settings POST | mitigate | Validator + controller-side `max(0, min(100, (int)$pct))` clamp before /100; integer-only step; bcdiv to construct DECIMAL string with 3 fractional digits; integration test posts 150 + -10 + 'foo' and asserts 422 OR clamped 100/0/0 (decision: REJECT 422 with Flash.error per UI-SPEC §5 validation copy) |
| T-03-03a-04 | XSS (welcome_message HTML injection) | settings POST + dashboard / send page render | mitigate | All output through h() / nl2br(h()); welcome_message stored raw but rendered escaped; D-17 plaintext-only; test `testWelcomeMessageScriptEscapedOnRender` posts <script> tag and asserts escaped output |
| T-03-03a-05 | XSS (sender snapshot handle injection) | dashboard receive list | mitigate | sender_handle_snapshot is from cached field which originated from Bluesky AS — but AS could in theory return weird handles; dashboard renders via h() escape; integration test asserts <script> in handle is escaped |
| T-03-03a-06 | Reverse tabnabbing | external profile_url link | mitigate | target="_blank" + rel="noopener" mandatory in <a>; template-level invariant; test asserts substring 'rel="noopener"' present in dashboard output for SSR-hit row |
| T-03-03a-07 | Information Disclosure (collision flash to wrong user) | session-based flash | mitigate | Session is per-user (PHP session_id); Flash.slug_collision_suffix written by OauthController during identity establishment, consumed once by dashboard; cannot leak across users by design |
| T-03-03a-08 | DoS (huge welcome_message storage) | settings POST | mitigate | maxLength('welcome_message', 1000) validator (Plan 03-01) + HTML maxlength="1000" in form; server rejects ≥1001 chars with Flash.error |
| T-03-03a-09 | Tampering (open opened_at timestamp via repeated requests) | markOpened | mitigate | `if ($message->opened_at !== null) return $message;` idempotent guard; test `testOpenAlreadyOpenedDoesNotUpdateTimestamp` |
| T-03-03a-10 | Repudiation (no audit on open) | markOpened | accept | Phase 3 MVP — no audit log yet; opened_at timestamp itself is the audit record. Future Phase 4 may add log entries |
| T-03-03a-11 | Pagination overflow | Paginator out-of-range | mitigate | CakePHP Paginator throws NotFoundException on out-of-range; controller catches → renders 'そのページはありません。' message OR default to page 1 (decision: render the message per UI-SPEC §5; rely on `Paginator->paginate` `not found` config) |
| T-03-03a-12 | Information Disclosure (deleted user's snapshot leak) | sender_*_snapshot in dashboard | accept | Phase 3 does not yet wire user deletion (MOD-03 Phase 4); snapshots remain visible to receiver, which is the intended behavior per V1 hypothesis ('逃げ得防止') |
</threat_model>

<tasks>

<task type="auto">
  <name>Task 1: MessagesTable::markOpened + MessagesController::open body fill-in + open integration tests</name>
  <files>src/Model/Table/MessagesTable.php, src/Controller/MessagesController.php, tests/TestCase/Controller/MessagesControllerTest.php</files>

  <read_first>
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-CONTEXT.md D-25 (段階開示 — server flow only updates opened_at on explicit POST) / D-27 (再閲覧時 idempotent) / `<specifics>` "段階的開示の DOM 構造"
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-PATTERNS.md §7 MessagesTable::markOpened pattern + Shared "Auth gate (controllers)"
    - /home/claude/projects/tamabox/src/Model/Table/MessagesTable.php (current state — Plan 03-02 sendMessage is in)
    - /home/claude/projects/tamabox/src/Controller/MessagesController.php (current — open is 501 placeholder from Plan 03-02 Task 1)
    - /home/claude/projects/tamabox/tests/TestCase/Controller/MessagesControllerTest.php (current — has testOpenReturns501Placeholder which this task UPDATES to expect 302)
  </read_first>

  <action>
**A. Add `markOpened` to `src/Model/Table/MessagesTable.php`**:

```php
/**
 * Mark a message opened (D-25 / D-27 / MSG-06).
 *
 * - Verifies ownership: message.inbox.user_id MUST equal $ownerUserId.
 * - If opened_at is already set, returns the entity without modification (idempotent — D-27).
 * - Else sets opened_at = FrozenTime::now() and saves.
 *
 * @param string $messageId UUID of the message.
 * @param string $ownerUserId UUID of the receiver (current authenticated user).
 * @return \App\Model\Entity\Message
 * @throws \Cake\Http\Exception\NotFoundException If message not found.
 * @throws \Cake\Http\Exception\ForbiddenException If message's inbox is not owned by $ownerUserId.
 */
public function markOpened(string $messageId, string $ownerUserId): \App\Model\Entity\Message
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
        throw new \Cake\Http\Exception\ForbiddenException(__('このメッセージを開く権限がありません。'));
    }

    if ($msg->opened_at !== null) {
        return $msg;  // D-27 idempotent — re-open does NOT update timestamp
    }

    $patched = $this->patchEntity($msg, [
        'opened_at' => \Cake\I18n\FrozenTime::now(),
    ], ['accessibleFields' => ['opened_at' => true]]);
    /** @var \App\Model\Entity\Message $saved */
    $saved = $this->saveOrFail($patched);
    return $saved;
}
```

**B. Replace `MessagesController::open` body** in `src/Controller/MessagesController.php` (currently a 501 placeholder from Plan 03-02 Task 1):

```php
/**
 * POST /dashboard/messages/{id}/open — receiver opens a message (D-25 / MSG-06).
 *
 * @param string $id Message UUID.
 * @return \Cake\Http\Response|null
 */
public function open(string $id): ?Response
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
    // markOpened throws NotFoundException / ForbiddenException — let CakePHP error handler render.
    $messagesTable->markOpened($id, $userId);

    // Anchor the redirect so the browser scrolls to the just-opened row.
    return $this->redirect('/dashboard#msg-' . $id);
}
```

Remove the `?>` `withStatus(501)->withStringBody('Not Implemented');` block from Task 1 (the placeholder body).

**C. Replace `testOpenReturns501Placeholder` in MessagesControllerTest** (the placeholder check from Plan 03-02 Task 1) with proper open tests:

Delete the existing `testOpenReturns501Placeholder` method.

Add:

```php
public function testOpenAuthenticatedSetsOpenedAt(): void
{
    $this->enableCsrfToken();
    $this->loginAsAlice();  // alice owns inbox 11111111-1111-...; aaaa1111-... message belongs to it (unread)
    $this->post('/dashboard/messages/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa/open');
    $this->assertResponseCode(302);
    /** @var \App\Model\Entity\Message $msg */
    $msg = $this->fetchTable('Messages')->get('aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
    $this->assertNotNull($msg->opened_at);
}

public function testOpenAlreadyOpenedDoesNotUpdateTimestamp(): void
{
    $this->enableCsrfToken();
    $this->loginAsAlice();
    /** @var \App\Model\Entity\Message $before */
    $before = $this->fetchTable('Messages')->get('aaaa2222-aaaa-aaaa-aaaa-aaaaaaaaaaaa');  // already opened in fixture
    $beforeTs = $before->opened_at !== null ? $before->opened_at->format('Y-m-d H:i:s.u') : null;
    $this->post('/dashboard/messages/aaaa2222-aaaa-aaaa-aaaa-aaaaaaaaaaaa/open');
    $this->assertResponseCode(302);
    /** @var \App\Model\Entity\Message $after */
    $after = $this->fetchTable('Messages')->get('aaaa2222-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
    $afterTs = $after->opened_at !== null ? $after->opened_at->format('Y-m-d H:i:s.u') : null;
    $this->assertSame($beforeTs, $afterTs);
}

public function testOpenOtherUsersMessageReturns403(): void
{
    $this->enableCsrfToken();
    $this->loginAsBob();  // bob does NOT own alice's inbox
    $this->post('/dashboard/messages/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa/open');
    $this->assertResponseCode(403);
}

public function testOpenUnknownMessageReturns404(): void
{
    $this->enableCsrfToken();
    $this->loginAsAlice();
    $this->post('/dashboard/messages/00000000-0000-0000-0000-000000000000/open');
    $this->assertResponseCode(404);
}

public function testOpenUnauthenticatedRedirects(): void
{
    $this->enableCsrfToken();
    $this->post('/dashboard/messages/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa/open');
    // AuthenticationMiddleware redirects unauth → /
    $this->assertResponseCode(302);
}
```

(`loginAsAlice` / `loginAsBob` helpers are already in MessagesControllerTest from Plan 03-02 Task 4. Reuse them.)

**D. Verify**:
```bash
composer test -- --filter MessagesControllerTest
composer test -- --filter MessagesTableTest
composer test
vendor/bin/phpstan analyse src/Controller/MessagesController.php src/Model/Table/MessagesTable.php
composer cs-check
```
  </action>

  <acceptance_criteria>
    - `grep -c 'public function markOpened' src/Model/Table/MessagesTable.php` = 1
    - `grep -c "FrozenTime::now()" src/Model/Table/MessagesTable.php` ≥ 1
    - `grep -c "if (\$msg->opened_at !== null)" src/Model/Table/MessagesTable.php` = 1
    - `grep -c "ForbiddenException" src/Model/Table/MessagesTable.php` ≥ 1
    - `grep -c "NotFoundException" src/Model/Table/MessagesTable.php` ≥ 1
    - `grep -c "withStatus(501)" src/Controller/MessagesController.php` = 1   # only `report` keeps 501; `open` no longer 501
    - `grep -c "markOpened" src/Controller/MessagesController.php` = 1
    - `grep -c "redirect.*/dashboard#msg-" src/Controller/MessagesController.php` = 1
    - `grep -E 'public function testOpen[A-Z]' tests/TestCase/Controller/MessagesControllerTest.php | wc -l` ≥ 5
    - `grep -c 'testOpenReturns501Placeholder' tests/TestCase/Controller/MessagesControllerTest.php` = 0   # placeholder test removed
    - `composer test -- --filter MessagesControllerTest 2>&1 | grep -E 'OK \(|FAILURES'` shows OK
  </acceptance_criteria>

  <verify>
    <automated>composer test -- --filter MessagesControllerTest && vendor/bin/phpstan analyse src/Controller/MessagesController.php src/Model/Table/MessagesTable.php</automated>
  </verify>

  <done>MessagesTable::markOpened performs ownership-checked idempotent UPDATE. MessagesController::open replaces the 501 placeholder with markOpened call + 302 redirect. 5 open tests pass (auth happy / re-open no-op / 403 forbidden / 404 unknown / 302 unauth). Plan 03-02's testOpenReturns501Placeholder removed.</done>
</task>

<task type="auto">
  <name>Task 2: InboxesController::settings + integration tests + InboxesTable rule patches</name>
  <files>src/Controller/InboxesController.php, src/Model/Table/InboxesTable.php, tests/TestCase/Controller/InboxesControllerTest.php</files>

  <read_first>
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-CONTEXT.md D-07 (range + number) / D-08 (DECIMAL(4,3) 1% 刻み) / D-10 (0% confirm) / D-11 (100% confirm) / D-12 (no retroactive) / D-28 (3 fields combined form)
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-UI-SPEC.md §3 Settings Form / §5 Validation エラー文言
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-PATTERNS.md §2 InboxesController + Shared "Auth gate"
    - /home/claude/projects/tamabox/src/Controller/UsersController.php (analog: identity → fetchTable → firstOrFail)
    - /home/claude/projects/tamabox/src/Controller/AuthController.php logout method (analog: allowMethod POST + Flash success + redirect)
    - /home/claude/projects/tamabox/src/Model/Table/InboxesTable.php (Plan 03-01 — already has ssr_probability + welcome_message + is_accepting validation)
    - /home/claude/projects/tamabox/tests/TestCase/Controller/AuthControllerTest.php (analog: $fixtures + setUp + enableCsrfToken)
    - /home/claude/projects/tamabox/tests/TestCase/Controller/MessagesControllerTest.php (Plan 03-02 — loginAsAlice / loginAsBob helpers — copy them or extract to a trait)
  </read_first>

  <action>
**A. Create `src/Controller/InboxesController.php`**:

```php
<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * InboxesController — receiver-side inbox settings.
 *
 * Routes (config/routes.php Plan 03-02):
 *   GET|POST /dashboard/settings → settings()
 *
 * GET → 302 to /dashboard (settings is rendered inline in the dashboard view).
 * POST → patches the authenticated user's inbox; 302 redirect /dashboard with Flash.success.
 *
 * Ownership: settings target is determined by `inbox.user_id == identity.user_id`,
 * NEVER by an inbox id in the POST body (no IDOR surface).
 */
class InboxesController extends AppController
{
    /**
     * GET|POST /dashboard/settings.
     *
     * @return \Cake\Http\Response|null
     */
    public function settings(): ?Response
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
            // Settings UI is rendered inline in /dashboard.
            return $this->redirect('/dashboard');
        }

        $this->request->allowMethod(['post']);

        /** @var \App\Model\Table\InboxesTable $inboxesTable */
        $inboxesTable = $this->fetchTable('Inboxes');
        /** @var \App\Model\Entity\Inbox|null $inbox */
        $inbox = $inboxesTable->find()
            ->where([$inboxesTable->aliasField('user_id') => $userId])
            ->first();
        if ($inbox === null) {
            $this->Flash->error(__('受信箱が見つかりませんでした。再度ログインしてください。'));
            return $this->redirect('/');
        }

        // === ssr_probability_pct (D-08): integer 0..100 → DECIMAL(4,3) string '0.000'..'1.000' ===
        $pctRaw = $this->request->getData('ssr_probability_pct');
        if (!is_scalar($pctRaw) || !ctype_digit((string)(int)$pctRaw) && (string)(int)$pctRaw !== (string)$pctRaw) {
            // Reject non-integer (also handles strings like 'foo' which (int) coerces to 0; explicit check):
            $pctTest = (string)$pctRaw;
            if (!preg_match('/^-?\d+$/', $pctTest)) {
                $this->Flash->error(__('確率は 0〜100 の整数で入力してください。'));
                return $this->redirect('/dashboard');
            }
        }
        $pct = (int)$pctRaw;
        if ($pct < 0 || $pct > 100) {
            $this->Flash->error(__('確率は 0〜100 の整数で入力してください。'));
            return $this->redirect('/dashboard');
        }
        // Build DECIMAL string. e.g. 10 → '0.100', 100 → '1.000', 0 → '0.000'.
        $probabilityDecimal = sprintf('%d.%03d', intdiv($pct, 100), ($pct % 100) * 10);
        // (alternative: number_format($pct / 100, 3, '.', '') — same result)

        // === welcome_message (D-28, ≤1000 chars) — null when empty ===
        $welcomeRaw = $this->request->getData('welcome_message');
        $welcome = is_string($welcomeRaw) && trim($welcomeRaw) !== '' ? $welcomeRaw : null;

        // === is_accepting (D-28, checkbox) ===
        $isAcceptingRaw = $this->request->getData('is_accepting');
        $isAccepting = $isAcceptingRaw !== null && $isAcceptingRaw !== '' && $isAcceptingRaw !== '0' && $isAcceptingRaw !== false;

        $patched = $inboxesTable->patchEntity($inbox, [
            'ssr_probability' => $probabilityDecimal,
            'welcome_message' => $welcome,
            'is_accepting' => $isAccepting,
        ], ['accessibleFields' => [
            'ssr_probability' => true,
            'welcome_message' => true,
            'is_accepting' => true,
        ]]);

        if ($patched->getErrors() !== []) {
            // Surface validator's first message.
            $errors = $patched->getErrors();
            $first = '';
            foreach ($errors as $field => $messages) {
                foreach ($messages as $msg) {
                    $first = (string)$msg;
                    break 2;
                }
            }
            $this->Flash->error($first !== '' ? $first : __('保存に失敗しました。'));
            return $this->redirect('/dashboard');
        }

        try {
            $inboxesTable->saveOrFail($patched);
        } catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
            $this->Flash->error(__('保存に失敗しました。'));
            return $this->redirect('/dashboard');
        }

        $this->Flash->success(__('保存しました'));
        return $this->redirect('/dashboard');
    }
}
```

**B. Add `tests/TestCase/Controller/InboxesControllerTest.php`**:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class InboxesControllerTest extends TestCase
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

    private function loginAsAlice(): void
    {
        $alice = $this->fetchTable('Users')->get('11111111-1111-1111-1111-111111111111', ['contain' => ['UserIdentities']]);
        $this->session(['Auth' => $alice]);
    }

    public function testSettingsGetRedirectsToDashboard(): void
    {
        $this->loginAsAlice();
        $this->get('/dashboard/settings');
        $this->assertResponseCode(302);
        $this->assertHeader('Location', '/dashboard');
    }

    public function testSettingsPostHappyPathSaves(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/dashboard/settings', [
            'ssr_probability_pct' => '50',
            'welcome_message' => 'よろしくお願いします',
            'is_accepting' => '1',
        ]);
        $this->assertResponseCode(302);
        $this->assertHeader('Location', '/dashboard');
        $inbox = $this->fetchTable('Inboxes')->get('11111111-1111-1111-1111-111111111111');
        $this->assertSame('0.500', (string)$inbox->ssr_probability);
        $this->assertSame('よろしくお願いします', (string)$inbox->welcome_message);
        $this->assertTrue((bool)$inbox->is_accepting);
    }

    public function testSettingsPostZeroPercent(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/dashboard/settings', [
            'ssr_probability_pct' => '0',
            'welcome_message' => '',
            'is_accepting' => '1',
        ]);
        $this->assertResponseCode(302);
        $inbox = $this->fetchTable('Inboxes')->get('11111111-1111-1111-1111-111111111111');
        $this->assertSame('0.000', (string)$inbox->ssr_probability);
    }

    public function testSettingsPostHundredPercent(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/dashboard/settings', [
            'ssr_probability_pct' => '100',
            'is_accepting' => '1',
        ]);
        $inbox = $this->fetchTable('Inboxes')->get('11111111-1111-1111-1111-111111111111');
        $this->assertSame('1.000', (string)$inbox->ssr_probability);
    }

    public function testSettingsPostOver100Rejected(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/dashboard/settings', [
            'ssr_probability_pct' => '150',
            'is_accepting' => '1',
        ]);
        $this->assertResponseCode(302);
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertIsArray($flash);
        $this->assertMatchesRegularExpression('/0〜100/', (string)$flash[0]['message']);
        $inbox = $this->fetchTable('Inboxes')->get('11111111-1111-1111-1111-111111111111');
        $this->assertSame('0.100', (string)$inbox->ssr_probability);  // unchanged
    }

    public function testSettingsPostNegativeRejected(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/dashboard/settings', ['ssr_probability_pct' => '-5']);
        $this->assertResponseCode(302);
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertMatchesRegularExpression('/0〜100/', (string)$flash[0]['message']);
    }

    public function testSettingsPostNonIntegerRejected(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/dashboard/settings', ['ssr_probability_pct' => 'foo']);
        $this->assertResponseCode(302);
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertMatchesRegularExpression('/0〜100/', (string)$flash[0]['message']);
    }

    public function testSettingsPostIsAcceptingUnchecked(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        // Unchecked checkbox — browser omits the field.
        $this->post('/dashboard/settings', [
            'ssr_probability_pct' => '10',
            // no is_accepting key
        ]);
        $this->assertResponseCode(302);
        $inbox = $this->fetchTable('Inboxes')->get('11111111-1111-1111-1111-111111111111');
        $this->assertFalse((bool)$inbox->is_accepting);
    }

    public function testSettingsPostWelcomeMessageOver1000Rejected(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/dashboard/settings', [
            'ssr_probability_pct' => '10',
            'welcome_message' => str_repeat('a', 1001),
            'is_accepting' => '1',
        ]);
        $this->assertResponseCode(302);
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertMatchesRegularExpression('/1000 文字/', (string)$flash[0]['message']);
        $inbox = $this->fetchTable('Inboxes')->get('11111111-1111-1111-1111-111111111111');
        $this->assertNull($inbox->welcome_message);  // unchanged
    }

    public function testSettingsPostUnauthenticatedRedirects(): void
    {
        $this->enableCsrfToken();
        $this->post('/dashboard/settings', ['ssr_probability_pct' => '10']);
        $this->assertResponseCode(302);
    }

    public function testSettingsPostDoesNotAffectExistingMessages(): void
    {
        // D-12: changing inbox.ssr_probability does NOT change existing messages.ssr_probability_at_send.
        $this->enableCsrfToken();
        $this->loginAsAlice();
        /** @var \App\Model\Entity\Message $before */
        $before = $this->fetchTable('Messages')->get('aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $beforeProb = (string)$before->ssr_probability_at_send;

        $this->post('/dashboard/settings', ['ssr_probability_pct' => '90', 'is_accepting' => '1']);

        /** @var \App\Model\Entity\Message $after */
        $after = $this->fetchTable('Messages')->get('aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $this->assertSame($beforeProb, (string)$after->ssr_probability_at_send);
    }
}
```

**C. Verify**:
```bash
composer test -- --filter InboxesControllerTest
composer test
vendor/bin/phpstan analyse src/Controller/InboxesController.php
composer cs-check
```
  </action>

  <acceptance_criteria>
    - `grep -c 'class InboxesController extends AppController' src/Controller/InboxesController.php` = 1
    - `grep -c 'public function settings' src/Controller/InboxesController.php` = 1
    - `grep -c "ssr_probability_pct" src/Controller/InboxesController.php` ≥ 1
    - `grep -c "0〜100 の整数" src/Controller/InboxesController.php` ≥ 1
    - `grep -c "保存しました" src/Controller/InboxesController.php` = 1
    - `grep -c "1000 文字" src/Controller/InboxesController.php` = 0   # validation message comes from validator, not controller; absence here is OK as long as validator surfaces it
    - `grep -E 'public function test[A-Z]' tests/TestCase/Controller/InboxesControllerTest.php | wc -l` ≥ 10
    - `composer test -- --filter InboxesControllerTest 2>&1 | grep -E 'OK \(|FAILURES'` shows OK
    - `composer test 2>&1 | tail -3 | grep -E 'OK|FAILURES'` shows OK overall
    - `vendor/bin/phpstan analyse src/Controller/InboxesController.php` exit 0
  </acceptance_criteria>

  <verify>
    <automated>composer test -- --filter InboxesControllerTest && vendor/bin/phpstan analyse src/Controller/InboxesController.php</automated>
  </verify>

  <done>InboxesController::settings handles GET/POST per D-28. POST sanitizes pct (0..100 int → '0.NNN'..'1.000'), patches inbox, saves on validate-pass. ≥10 integration tests cover happy / boundary 0 / boundary 100 / over 100 / negative / non-integer / is_accepting unchecked / welcome over 1000 / unauth / D-12 (existing messages unaffected). composer test green.</done>
</task>

<task type="auto">
  <name>Task 3: UsersController::dashboard expansion + dashboard.php template + UsersControllerTest</name>
  <files>src/Controller/UsersController.php, templates/Users/dashboard.php, templates/Inboxes/settings.php, tests/TestCase/Controller/UsersControllerTest.php</files>

  <read_first>
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-UI-SPEC.md §2 Receive List Item / §3 Settings Form / §4 SSR Reveal Section / §5 Empty State / §6 Sender Card / §10 Pagination / §11 Flash Message / §12 Char Counter NOT (this is for send.php) / §13 Probability Control
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-CONTEXT.md D-06 (collision flash consume-once) / D-20 (single-page dashboard) / D-21 / D-22 / D-23 / D-24 / D-25 / D-26 / D-27 / D-28 / D-31 (avatar fallback) / D-32 (bsky.app/profile URL) / D-35 (Phase 4 stub buttons in reveal section) / D-38 (self notice — already in send.php, also in dashboard for slug header)
    - /home/claude/projects/tamabox/.planning/phases/03-inbox-message-ssr-reveal/03-PATTERNS.md §11 dashboard.php extension + §13 controller test setup
    - /home/claude/projects/tamabox/src/Controller/UsersController.php FULL (current Phase 2 minimal)
    - /home/claude/projects/tamabox/templates/Users/dashboard.php FULL (current Phase 2 minimal — analog for header docblock + h() escape pattern)
    - /home/claude/projects/tamabox/src/Model/Table/MessagesTable.php (markOpened added in Task 1; sendMessage from Plan 03-02)
    - /home/claude/projects/tamabox/tests/TestCase/Controller/MessagesControllerTest.php (Plan 03-02 — loginAsAlice / loginAsBob helpers)
  </read_first>

  <action>
**A. Replace `src/Controller/UsersController.php::dashboard`** (preserve existing skeleton, expand body):

```php
<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\Datasource\Paging\Exception\PageOutOfBoundsException;

/**
 * Users — authenticated landing pages (dashboard).
 *
 * Phase 3 expanded: receive list (paginated 20/page) + settings sidebar + slug header
 * + collision-suffix flash (consume-once from session).
 */
class UsersController extends AppController
{
    /**
     * @inheritDoc
     */
    public $paginate = [
        'Messages' => [
            'limit' => 20,
            'order' => ['Messages.created_at' => 'DESC'],
        ],
    ];

    /**
     * GET /dashboard — receive list + settings + slug header.
     *
     * @return \Cake\Http\Response|null
     */
    public function dashboard(): ?Response
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

        /** @var \App\Model\Entity\User $user */
        $user = $this->fetchTable('Users')
            ->find()
            ->where(['Users.id' => $userId])
            ->contain(['UserIdentities'])
            ->firstOrFail();

        /** @var \App\Model\Table\InboxesTable $inboxesTable */
        $inboxesTable = $this->fetchTable('Inboxes');
        /** @var \App\Model\Entity\Inbox|null $inbox */
        $inbox = $inboxesTable->find()
            ->where([$inboxesTable->aliasField('user_id') => $userId])
            ->first();
        if ($inbox === null) {
            // Should never happen post-Phase 3 (Plan 03-01 creates inbox at first login).
            // Defensive: render the dashboard with a soft notice.
            $this->Flash->error(__('受信箱が見つかりませんでした。再度ログインしてください。'));
            return $this->redirect('/');
        }

        // === Paginate messages ===
        /** @var \App\Model\Table\MessagesTable $messagesTable */
        $messagesTable = $this->fetchTable('Messages');
        try {
            $messages = $this->paginate(
                $messagesTable
                    ->find()
                    ->where(['Messages.inbox_id' => $inbox->id])
                    ->order(['Messages.created_at' => 'DESC'])
            );
        } catch (PageOutOfBoundsException $e) {
            // Warning 1 fix: CakePHP 4.5 Paginator throws
            // Cake\Datasource\Paging\Exception\PageOutOfBoundsException for
            // out-of-range pages (NOT Cake\Http\Exception\NotFoundException — wrong
            // class hierarchy; the catch would never fire and users would see a 500
            // instead of the UI-SPEC §5 fallback copy). Verified against
            // vendor/cakephp/cakephp/src/Datasource/Paging/Exception/PageOutOfBoundsException.php
            // (deprecated alias Cake\Datasource\Exception\PageOutOfBoundsException
            // also forwards to this class).
            $this->set([
                'user' => $user,
                'inbox' => $inbox,
                'messages' => [],
                'pageOutOfRange' => true,
                'collisionFlash' => null,
            ]);
            return null;
        }

        // === Consume slug-collision flash if present (D-06) ===
        $session = $this->request->getSession();
        $collisionRaw = $session->read('Flash.slug_collision_suffix');
        $collisionFlash = null;
        if (is_array($collisionRaw) && isset($collisionRaw['slug'], $collisionRaw['base'])) {
            $collisionFlash = [
                'slug' => (string)$collisionRaw['slug'],
                'base' => (string)$collisionRaw['base'],
            ];
            $session->delete('Flash.slug_collision_suffix');
        }

        $this->set([
            'user' => $user,
            'inbox' => $inbox,
            'messages' => $messages,
            'pageOutOfRange' => false,
            'collisionFlash' => $collisionFlash,
        ]);

        return null;
    }
}
```

**B. Create `templates/Inboxes/settings.php`** (renderable as element OR include via `$this->element('Inboxes/settings')` — simpler: create as element file `templates/element/Inboxes/settings_form.php`. For minimal complexity: place at `templates/Inboxes/settings.php` and consume as a partial via `$this->element('Inboxes/settings_form')` — CakePHP elements live in `templates/element/`. **Decision**: create the file at `templates/element/inbox_settings_form.php` and use `$this->element('inbox_settings_form', ['inbox' => $inbox])` from dashboard.php.

Actually `templates/Inboxes/settings.php` is the more PSR-cleaning route since it mirrors the controller name (`InboxesController`). It can be rendered via `<?= $this->fetch('content') ?>` in layout if invoked through `$this->render('Inboxes/settings')` from a controller — but our flow renders dashboard.php and includes settings inline. **Simpler: include settings markup directly in dashboard.php** to keep the plan flat. **Use the Inboxes/settings.php as a render element via** `$this->element('settings_form', ['inbox' => $inbox])` from `templates/element/settings_form.php`.

Final decision: Create `templates/element/inbox_settings_form.php` (CakePHP element convention). Dashboard calls `$this->element('inbox_settings_form', ['inbox' => $inbox])`.

ALSO create `templates/Inboxes/settings.php` AS A FALLBACK for direct rendering (it just renders the element):

`templates/Inboxes/settings.php`:
```php
<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Inbox $inbox
 *
 * Settings page (currently routed only via /dashboard inline; this template exists
 * for direct render compatibility per CakePHP convention).
 */
$this->assign('title', '受信箱設定');
?>
<div class="settings-page">
    <h1>受信箱設定</h1>
    <?= $this->element('inbox_settings_form', ['inbox' => $inbox]) ?>
</div>
```

`templates/element/inbox_settings_form.php` (UI-SPEC §3 verbatim):
```php
<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Inbox $inbox
 *
 * Settings form element — UI-SPEC §3 / D-28 / D-07 / D-08 / D-10 / D-11.
 * Consumed by templates/Users/dashboard.php and templates/Inboxes/settings.php.
 */
$probabilityPct = (int)round((float)$inbox->ssr_probability * 100);
$welcomeMessage = $inbox->welcome_message !== null ? (string)$inbox->welcome_message : '';
$isAccepting = (bool)$inbox->is_accepting;
?>
<?= $this->Form->create(null, [
    'url' => '/dashboard/settings',
    'type' => 'post',
    'class' => 'settings-form',
]) ?>
    <fieldset>
        <legend>SSR 確率(送信者が開示される確率)</legend>
        <div class="probability-control">
            <input type="range"
                   name="ssr_probability_pct_range"
                   min="0" max="100" step="1"
                   value="<?= $probabilityPct ?>"
                   aria-label="確率スライダ"
                   id="prob-range">
            <input type="number"
                   name="ssr_probability_pct"
                   min="0" max="100" step="1"
                   value="<?= $probabilityPct ?>"
                   aria-label="確率値"
                   id="prob-number">
            <span class="probability-suffix">%</span>
        </div>
        <p class="text-secondary">デフォルト 10%、0% / 100% 設定時は確認ダイアログが表示されます</p>
    </fieldset>

    <fieldset>
        <legend>welcome message(送信フォーム上部に表示される歓迎文、任意)</legend>
        <textarea name="welcome_message" maxlength="1000" rows="4"><?= h($welcomeMessage) ?></textarea>
    </fieldset>

    <fieldset>
        <legend>受信を受け付ける</legend>
        <label>
            <input type="checkbox" name="is_accepting" value="1" <?= $isAccepting ? 'checked' : '' ?>>
            現在この受信箱でメッセージを受け付ける
        </label>
        <p class="text-secondary">OFF にすると <code>/&lt;slug&gt;</code> で送信フォームが非表示になります</p>
    </fieldset>

    <button type="submit" class="button primary-button">保存する</button>
<?= $this->Form->end() ?>

<?php
$this->Html->scriptBlock(<<<JS
(function () {
    var range = document.getElementById('prob-range');
    var number = document.getElementById('prob-number');
    if (!range || !number) return;
    range.addEventListener('input', function () { number.value = range.value; });
    number.addEventListener('input', function () { range.value = number.value; });
    document.querySelector('.settings-form').addEventListener('submit', function (e) {
        var v = parseInt(number.value, 10);
        if (v === 0) {
            if (!confirm('0% にするとコア体験(送信者開示の楽しみ)が失われますが、それでも設定しますか?')) {
                e.preventDefault();
            }
        } else if (v === 100) {
            if (!confirm('全てのメッセージで送信者が開示されます — 本当によろしいですか?')) {
                e.preventDefault();
            }
        }
    });
})();
JS, ['block' => false]);
?>
```

**C. Replace `templates/Users/dashboard.php`** with the full UI-SPEC §2 + §3 + §10 + §11 + §5 implementation:

```php
<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 * @var \App\Model\Entity\Inbox $inbox
 * @var \Cake\Datasource\ResultSetInterface|array $messages
 * @var bool $pageOutOfRange
 * @var array{slug: string, base: string}|null $collisionFlash
 *
 * UI-SPEC §2 receive list + §3 settings + §5 empty + §10 paginator + §11 flash + D-06 collision.
 */
$this->assign('title', 'ダッシュボード');

$handle = '';
if (isset($user->user_identity) && $user->user_identity !== null) {
    $handle = (string)$user->user_identity->handle_cached;
}
$slug = (string)$inbox->slug;
?>
<div class="dashboard-page">
    <header class="dashboard-header">
        <h1>ようこそ、<?= h($handle) ?> さん</h1>
        <p class="text-secondary">あなたの受信箱: <code><?= h('/' . $slug) ?></code></p>
    </header>

    <?php if ($collisionFlash !== null): ?>
        <div class="message info">
            あなたの slug: <strong><?= h($collisionFlash['slug']) ?></strong> になりました(<?= h($collisionFlash['base']) ?> は他のユーザーに使われていたため)
        </div>
    <?php endif; ?>

    <?php if ($pageOutOfRange === true): ?>
        <p>そのページはありません。</p>
        <p><?= $this->Html->link('最初のページに戻る', '/dashboard') ?></p>
    <?php elseif (count($messages) === 0): ?>
        <section class="receive-list-empty">
            <h2>まだ受信したメッセージはありません</h2>
            <p>あなたの inbox URL を Bluesky でシェアしてみましょう: <code>https://tamabox.emomie.com<?= h('/' . $slug) ?></code></p>
        </section>
    <?php else: ?>
        <section class="receive-list">
            <h2>受信メッセージ</h2>
            <?php foreach ($messages as $msg): ?>
                <?php
                $isUnread = $msg->opened_at === null;
                $state = $isUnread ? 'unread' : 'opened';
                $icon = $isUnread ? '●' : '✓';
                $bodyPreview = mb_substr((string)$msg->body, 0, 80);
                if (mb_strlen((string)$msg->body) > 80) {
                    $bodyPreview .= '…';
                }
                $createdIso = $msg->created_at !== null ? $msg->created_at->format(DATE_ATOM) : '';
                $createdDisplay = $msg->created_at !== null ? $msg->created_at->format('Y/m/d H:i') : '';
                $isHit = (bool)$msg->is_ssr;
                $senderHandle = (string)$msg->sender_handle_snapshot;
                $senderAvatar = $msg->sender_avatar_url_snapshot !== null ? (string)$msg->sender_avatar_url_snapshot : '';
                $senderProfileUrl = $msg->sender_profile_url_snapshot !== null ? (string)$msg->sender_profile_url_snapshot : '';
                $senderUserId = (string)$msg->sender_user_id;
                ?>
                <details class="message-row" data-msg-id="<?= h((string)$msg->id) ?>" data-state="<?= h($state) ?>" id="msg-<?= h((string)$msg->id) ?>" <?= $isUnread ? '' : 'open' ?>>
                    <summary class="message-row__head">
                        <span class="message-row__icon" aria-hidden="true"><?= $icon ?></span>
                        <span class="visually-hidden"><?= $isUnread ? '未開封' : '開封済' ?></span>
                        <time class="message-row__time" datetime="<?= h($createdIso) ?>"><?= h($createdDisplay) ?></time>
                        <span class="message-row__preview"><?= h($bodyPreview) ?></span>
                    </summary>
                    <div class="message-row__body">
                        <p><?= nl2br(h((string)$msg->body)) ?></p>

                        <?php if ($isUnread): ?>
                            <?= $this->Form->create(null, [
                                'url' => '/dashboard/messages/' . h((string)$msg->id) . '/open',
                                'type' => 'post',
                                'class' => 'open-form',
                            ]) ?>
                                <button type="submit" class="button primary-button">開封する</button>
                            <?= $this->Form->end() ?>
                        <?php else: ?>
                            <?php if ($isHit): ?>
                                <div class="ssr-reveal" data-outcome="hit">
                                    <div class="ssr-reveal__banner">★ 抽選 hit — 送信者が開示されました</div>
                                    <div class="sender-card">
                                        <?php if ($senderAvatar !== ''): ?>
                                            <img class="sender-card__avatar"
                                                 src="<?= h($senderAvatar) ?>"
                                                 alt="<?= h($senderHandle) ?>"
                                                 width="64" height="64"
                                                 onerror="this.src='/img/default-avatar.svg'">
                                        <?php else: ?>
                                            <img class="sender-card__avatar"
                                                 src="/img/default-avatar.svg"
                                                 alt="<?= h($senderHandle) ?>"
                                                 width="64" height="64">
                                        <?php endif; ?>
                                        <a class="sender-card__handle" href="<?= 'https://bsky.app/profile/' . h($senderHandle) ?>">@<?= h($senderHandle) ?></a>
                                        <?php if ($senderProfileUrl !== ''): ?>
                                            <a class="button button-clear"
                                               href="<?= h($senderProfileUrl) ?>"
                                               target="_blank"
                                               rel="noopener">Bluesky プロフィールを見る</a>
                                        <?php endif; ?>
                                        <?= $this->Form->create(null, [
                                            'url' => '/report/' . h((string)$msg->id),
                                            'type' => 'post',
                                            'class' => 'inline',
                                        ]) ?>
                                            <button type="submit" class="button button-clear button-destructive">通報する</button>
                                        <?= $this->Form->end() ?>
                                        <?= $this->Form->create(null, [
                                            'url' => '/block/' . h($senderUserId),
                                            'type' => 'post',
                                            'class' => 'inline',
                                        ]) ?>
                                            <button type="submit" class="button button-clear button-destructive">このユーザーをブロック</button>
                                        <?= $this->Form->end() ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="ssr-reveal__miss text-secondary">★ 抽選 miss(送信者は匿名のまま)</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </section>

        <nav class="pagination" aria-label="受信一覧ページ送り">
            <?= $this->Paginator->numbers() ?>
        </nav>
    <?php endif; ?>

    <aside class="dashboard-settings">
        <h2>受信箱設定</h2>
        <?= $this->element('inbox_settings_form', ['inbox' => $inbox]) ?>
    </aside>
</div>
```

**D. Add `tests/TestCase/Controller/UsersControllerTest.php`**:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class UsersControllerTest extends TestCase
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

    private function loginAsAlice(): void
    {
        $alice = $this->fetchTable('Users')->get('11111111-1111-1111-1111-111111111111', ['contain' => ['UserIdentities']]);
        $this->session(['Auth' => $alice]);
    }

    public function testDashboardUnauthRedirectsHome(): void
    {
        $this->get('/dashboard');
        $this->assertResponseCode(302);
    }

    public function testDashboardAuthRendersHandle(): void
    {
        $this->loginAsAlice();
        $this->get('/dashboard');
        $this->assertResponseOk();
        $this->assertResponseContains('ようこそ');
    }

    public function testDashboardRendersUnreadAndOpenedMessages(): void
    {
        $this->loginAsAlice();
        $this->get('/dashboard');
        $this->assertResponseOk();
        // Unread row carries data-state="unread"
        $this->assertResponseContains('data-state="unread"');
        // Opened SSR-hit row exposes the reveal banner (per fixture aaaa2222 is_ssr=1, opened)
        $this->assertResponseContains('★ 抽選 hit');
        $this->assertResponseContains('https://bsky.app/profile/');
        // Opened SSR-miss row (aaaa3333 is_ssr=0, opened)
        $this->assertResponseContains('★ 抽選 miss');
    }

    public function testDashboardOpenFormPresentForUnread(): void
    {
        $this->loginAsAlice();
        $this->get('/dashboard');
        $this->assertResponseContains('action="/dashboard/messages/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa/open"');
        $this->assertResponseContains('開封する');
    }

    public function testDashboardSenderCardHasNoOpenerRel(): void
    {
        $this->loginAsAlice();
        $this->get('/dashboard');
        $this->assertResponseContains('rel="noopener"');
    }

    public function testDashboardCollisionFlashShownOnceThenCleared(): void
    {
        $this->loginAsAlice();
        $this->session([
            'Auth' => $this->fetchTable('Users')->get('11111111-1111-1111-1111-111111111111', ['contain' => ['UserIdentities']]),
            'Flash.slug_collision_suffix' => ['slug' => 'alice-2', 'base' => 'alice'],
        ]);
        $this->get('/dashboard');
        $this->assertResponseContains('alice-2 になりました');
        $this->assertResponseContains('alice は他のユーザーに使われていたため');
        // Re-fetch — the flash should be consumed.
        $this->get('/dashboard');
        $this->assertResponseNotContains('alice-2 になりました');
    }

    public function testDashboardRendersSettingsForm(): void
    {
        $this->loginAsAlice();
        $this->get('/dashboard');
        $this->assertResponseContains('SSR 確率');
        $this->assertResponseContains('name="ssr_probability_pct"');
        $this->assertResponseContains('name="welcome_message"');
        $this->assertResponseContains('name="is_accepting"');
        $this->assertResponseContains('保存する');
    }

    public function testDashboardEmptyInboxShowsEmptyState(): void
    {
        // charlie has no messages in fixture (all messages are alice's).
        $charlie = $this->fetchTable('Users')->get('33333333-3333-3333-3333-333333333333', ['contain' => ['UserIdentities']]);
        $this->session(['Auth' => $charlie]);
        $this->get('/dashboard');
        $this->assertResponseOk();
        $this->assertResponseContains('まだ受信したメッセージはありません');
    }

    public function testDashboardOutOfRangePageShowsCopy(): void
    {
        $this->loginAsAlice();
        $this->get('/dashboard?page=999');
        $this->assertResponseOk();
        $this->assertResponseContains('そのページはありません');
    }

    public function testDashboardBodyScriptEscaped(): void
    {
        // Warning 2 fix (T-03-02-04 XSS test): messages.body is sender-controlled and
        // displayed on /dashboard via nl2br(h(...)). Insert a Message row with a
        // script tag in body, GET /dashboard, assert escaped output.
        //
        // Uses Messages fixture data — alice owns inbox 11111111-...; aaaa1111-... is
        // already opened/unread per fixture. We patch its body in-place with a script
        // tag and re-render the dashboard.
        /** @var \App\Model\Table\MessagesTable $messagesTable */
        $messagesTable = $this->fetchTable('Messages');
        $msg = $messagesTable->get('aaaa2222-aaaa-aaaa-aaaa-aaaaaaaaaaaa');  // already opened → body section visible
        $msg = $messagesTable->patchEntity(
            $msg,
            ['body' => '<script>alert(1)</script>'],
            ['accessibleFields' => ['body' => true]]
        );
        $messagesTable->saveOrFail($msg);

        $this->loginAsAlice();
        $this->get('/dashboard');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body, 'message body must be HTML-escaped on dashboard');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body, 'raw <script> must NOT appear in rendered dashboard HTML');
    }
}
```

**E. Verify**:
```bash
composer test -- --filter UsersControllerTest
composer test
vendor/bin/phpstan analyse src/Controller/UsersController.php
composer cs-check
```
  </action>

  <acceptance_criteria>
    - `grep -c 'public \$paginate' src/Controller/UsersController.php` = 1
    - `grep -c "'limit' => 20" src/Controller/UsersController.php` = 1
    - `grep -c "Flash.slug_collision_suffix" src/Controller/UsersController.php` ≥ 1
    - `grep -c "session->delete('Flash.slug_collision_suffix')" src/Controller/UsersController.php` = 1
    - `grep -c '\$this->paginate' src/Controller/UsersController.php` = 1
    - `grep -c 'pageOutOfRange' src/Controller/UsersController.php` ≥ 2
    - `grep -c 'use Cake\\Datasource\\Paging\\Exception\\PageOutOfBoundsException' src/Controller/UsersController.php` = 1   # Warning 1 fix: correct exception class for CakePHP 4.5 Paginator
    - `grep -c 'catch (PageOutOfBoundsException' src/Controller/UsersController.php` = 1   # Warning 1 fix: must catch the right type or 500 leaks instead of UI-SPEC §5 fallback
    - `grep -c 'catch (NotFoundException' src/Controller/UsersController.php` = 0   # Warning 1 fix: NotFoundException is the WRONG type for paginator out-of-range
    - `grep -c '<details class="message-row"' templates/Users/dashboard.php` = 1
    - `grep -c 'data-state="\<?= h(\$state)' templates/Users/dashboard.php` = 1
    - `grep -c '★ 抽選 hit' templates/Users/dashboard.php` = 1
    - `grep -c '★ 抽選 miss' templates/Users/dashboard.php` = 1
    - `grep -c '通報する' templates/Users/dashboard.php` = 1
    - `grep -c 'このユーザーをブロック' templates/Users/dashboard.php` = 1
    - `grep -c 'まだ受信したメッセージはありません' templates/Users/dashboard.php` = 1
    - `grep -c 'そのページはありません' templates/Users/dashboard.php` = 1
    - `grep -c "onerror=\"this.src='/img/default-avatar.svg'\"" templates/Users/dashboard.php` = 1
    - `grep -c "https://bsky.app/profile/" templates/Users/dashboard.php` = 1
    - `grep -c 'rel="noopener"' templates/Users/dashboard.php` = 1
    - `grep -c "Paginator->numbers" templates/Users/dashboard.php` = 1
    - `grep -c "になりました" templates/Users/dashboard.php` = 1
    - `grep -c "<?= \$this->element('inbox_settings_form'" templates/Users/dashboard.php` = 1
    - `grep -c "name=\"ssr_probability_pct\"" templates/element/inbox_settings_form.php` = 1
    - `grep -c "name=\"welcome_message\"" templates/element/inbox_settings_form.php` = 1
    - `grep -c "name=\"is_accepting\"" templates/element/inbox_settings_form.php` = 1
    - `grep -c "0% にするとコア体験" templates/element/inbox_settings_form.php` = 1
    - `grep -c "全てのメッセージで送信者が開示されます" templates/element/inbox_settings_form.php` = 1
    - `grep -E 'public function test[A-Z]' tests/TestCase/Controller/UsersControllerTest.php | wc -l` ≥ 9   # Warning 2 fix bumped by +1 (testDashboardBodyScriptEscaped replaces the markTestSkipped placeholder)
    - `grep -c 'testDashboardBodyScriptEscaped' tests/TestCase/Controller/UsersControllerTest.php` = 1   # Warning 2: T-03-02-04 XSS test for messages.body on dashboard
    - `grep -c 'testDashboardWelcomeMessageScriptEscaped' tests/TestCase/Controller/UsersControllerTest.php` = 0   # Warning 2: placeholder markTestSkipped removed; welcome_message XSS test moved to MessagesControllerTest (Plan 03-02 Task 4)
    - `grep -c '&lt;script&gt;alert(1)&lt;/script&gt;' tests/TestCase/Controller/UsersControllerTest.php` = 1   # Warning 2: escaped expectation
    - `grep -c 'markTestSkipped' tests/TestCase/Controller/UsersControllerTest.php` = 0   # Warning 2: no skipped tests; XSS coverage is now fully implemented
    - `composer test -- --filter UsersControllerTest 2>&1 | grep -E 'OK \(|FAILURES'` shows OK
    - `composer test 2>&1 | tail -3 | grep -E 'OK|FAILURES'` shows OK overall
    - `vendor/bin/phpstan analyse src/Controller/UsersController.php` exit 0
    - `composer cs-check` zero errors
  </acceptance_criteria>

  <verify>
    <automated>composer test -- --filter UsersControllerTest && vendor/bin/phpstan analyse src/Controller/UsersController.php && composer cs-check</automated>
  </verify>

  <done>UsersController::dashboard renders header (handle + slug) + collision flash (consume-once) + paginated 20/page receive list + settings element + paginator out-of-range copy (catches Cake\Datasource\Paging\Exception\PageOutOfBoundsException — Warning 1 fix). dashboard.php uses `<details>` per UI-SPEC §2 with hit/miss reveal sections per §4. inbox_settings_form element renders UI-SPEC §3 verbatim including 0/100 confirm JS. ≥9 UsersControllerTest cases pass (Warning 2 fix replaced markTestSkipped placeholder with real testDashboardBodyScriptEscaped XSS test). composer test fully green, phpstan level 8 OK, phpcs clean.</done>
</task>

</tasks>

<verification>

After all tasks complete:

```bash
# Per-test-class
composer test -- --filter MessagesControllerTest    # 12 (Plan 03-02) + 5 open + adjustments = ~16
composer test -- --filter MessagesTableTest         # 5 from Plan 03-02 + Phase 1 baseline + markOpened scenarios via integration
composer test -- --filter UsersControllerTest       # 8+
composer test -- --filter InboxesControllerTest     # 10+

# Full suite — must remain green
composer test

# Static analysis
vendor/bin/phpstan analyse src/Controller/UsersController.php src/Controller/InboxesController.php src/Controller/MessagesController.php src/Model/Table/MessagesTable.php

# Coding standards
composer cs-check

# 7-truth E2E manual smoke (browser + DB):
# 1. Login as alice → /dashboard renders 3 message rows (1 unread bold, 2 opened with hit/miss reveals)
# 2. POST /dashboard/messages/aaaa1111.../open → row state flips to opened, ★ hit/miss appears
# 3. POST /dashboard/settings ssr_probability_pct=50, welcome_message='hi', is_accepting=1 → /dashboard re-renders with values
# 4. Browser cookie deleted → GET /dashboard → 302 to /
```

All MUST pass.

</verification>

<success_criteria>

This plan succeeds when:

1. **INBOX-02**: GET /dashboard shows the settings form with current ssr_probability as integer percent; POST /dashboard/settings with valid pct (0..100) updates inboxes.ssr_probability to '0.NNN' DECIMAL string. Verified by `testSettingsPostHappyPathSaves` + `testSettingsPostZeroPercent` + `testSettingsPostHundredPercent`.
2. **INBOX-03**: GET /dashboard renders paginated 20-per-page receive list filtered by inbox_id, ordered by created_at DESC. Verified by `testDashboardRendersUnreadAndOpenedMessages`.
3. **MSG-06**: POST /dashboard/messages/{id}/open sets messages.opened_at to FrozenTime::now() when currently NULL; idempotent (no double-update). Verified by `testOpenAuthenticatedSetsOpenedAt` + `testOpenAlreadyOpenedDoesNotUpdateTimestamp`.
4. **MSG-07**: dashboard.php differentiates unread (data-state="unread", ●, font-weight 600 — CSS in Plan 03-03b) from opened (data-state="opened", ✓). Verified by `testDashboardRendersUnreadAndOpenedMessages` + grep for `data-state="unread"` + `data-state="opened"`.
5. **D-06**: collision flash shown once then cleared from session. Verified by `testDashboardCollisionFlashShownOnceThenCleared`.
6. **D-12**: settings UPDATE never modifies existing messages.ssr_probability_at_send. Verified by `testSettingsPostDoesNotAffectExistingMessages`.
7. **D-25 / D-27**: opened messages render SSR reveal section inline (D-26 hit / miss copy) — initial open requires explicit POST; re-render shows reveal directly without extra request. Verified by template grep + `testOpenAlreadyOpenedDoesNotUpdateTimestamp`.
8. **D-31 / D-32**: avatar `onerror` falls back to /img/default-avatar.svg; profile link points to https://bsky.app/profile/<handle>. Verified by `testDashboardSenderCardHasNoOpenerRel` + grep for substrings.
9. **Phase 4 stub markup**: dashboard.php contains `<form action="/report/...">` and `<form action="/block/...">` for hit reveals; both POST → 501 (Plan 03-02 report stub + Plan 03-03b BlocksController stub).
10. **D-23 / D-24**: pagination 20/page, sorted by created_at DESC, ?page=N query, out-of-range → 'そのページはありません' copy. Verified by `testDashboardOutOfRangePageShowsCopy` AND the controller catches the correct CakePHP 4.5 exception (`Cake\Datasource\Paging\Exception\PageOutOfBoundsException`) — Warning 1 fix.
11. **T-03-02-04 / T-03-03a-04 XSS**: messages.body rendered through nl2br(h(...)) on dashboard escapes script tags. Verified by `testDashboardBodyScriptEscaped` (Warning 2 fix). welcome_message XSS coverage is in `testSendDisplaysWelcomeMessageScriptEscaped` (Plan 03-02 Task 4).

phpstan level 8 [OK]. phpcs zero errors. composer test fully green (cumulative).

</success_criteria>

<output>
After completion, create `.planning/phases/03-inbox-message-ssr-reveal/03-03a-SUMMARY.md` documenting:

- Files created/modified (3 controllers, 1 table, 2 templates, 3 test files)
- Test counts per controller
- Sticky note for Phase 3 verifier: 7 ROADMAP success criteria observable end-to-end via integration tests; live-AS Bluesky session is human_needed deferred to Phase 4 (Phase 2 verify-phase precedent).
- Decision recorded: settings rendered inline in dashboard via element `templates/element/inbox_settings_form.php`; standalone `templates/Inboxes/settings.php` exists as a thin wrapper for direct render compatibility.
- Decision recorded: range/number twin inputs use distinct names (`ssr_probability_pct_range` decorative, `ssr_probability_pct` server-canonical) with JS-sync.
- Decision recorded: out-of-range page → 200 + UI-SPEC §5 'そのページはありません' copy (NOT 404, NOT auto-redirect to page 1).
- Confirmation that Plan 03-03b (parallel execution) was completed before/after — if Plan 03-03b finished first, the dashboard CSS already exists and the visual is correct on first hit; if 03-03a finished first, the CSS will be added by 03-03b and HTML degrades gracefully (no styles, but functionally correct).
</output>
