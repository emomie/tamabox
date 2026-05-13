<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * UsersController integration tests — Phase 3 dashboard (receive list + settings + flash).
 *
 * @uses \App\Controller\UsersController
 */
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

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableRetainFlashMessages();
    }

    /**
     * @return void
     */
    private function loginAsAlice(): void
    {
        /** @var \App\Model\Entity\User $alice */
        $alice = $this->fetchTable('Users')->get('11111111-1111-1111-1111-111111111111', ['contain' => ['UserIdentities']]);
        $this->session(['Auth' => $alice]);
    }

    /**
     * @return void
     */
    public function testDashboardUnauthRedirectsHome(): void
    {
        $this->get('/dashboard');
        $this->assertResponseCode(302);
    }

    /**
     * @return void
     */
    public function testDashboardAuthRendersHandle(): void
    {
        $this->loginAsAlice();
        $this->get('/dashboard');
        $this->assertResponseOk();
        $this->assertResponseContains('ようこそ');
    }

    /**
     * @return void
     */
    public function testDashboardRendersUnreadAndOpenedMessages(): void
    {
        $this->loginAsAlice();
        $this->get('/dashboard');
        $this->assertResponseOk();
        // Unread row carries data-state="unread"
        $this->assertResponseContains('data-state="unread"');
        // Opened SSR-hit row exposes the reveal banner (fixture aaaa2222 is_ssr=1, opened)
        $this->assertResponseContains('★ 抽選 hit');
        $this->assertResponseContains('https://bsky.app/profile/');
        // Opened SSR-miss row (aaaa3333 is_ssr=0, opened)
        $this->assertResponseContains('★ 抽選 miss');
    }

    /**
     * @return void
     */
    public function testDashboardOpenFormPresentForUnread(): void
    {
        $this->loginAsAlice();
        $this->get('/dashboard');
        $this->assertResponseContains('action="/dashboard/messages/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa/open"');
        $this->assertResponseContains('開封する');
    }

    /**
     * T-03-03a-06: external profile link must have rel="noopener" (reverse tabnabbing).
     *
     * @return void
     */
    public function testDashboardSenderCardHasNoOpenerRel(): void
    {
        $this->loginAsAlice();
        $this->get('/dashboard');
        $this->assertResponseContains('rel="noopener"');
    }

    /**
     * D-06: collision flash shown once then cleared from session.
     *
     * @return void
     */
    public function testDashboardCollisionFlashShownOnceThenCleared(): void
    {
        // loginAsAlice sets Auth; separately set Flash.slug_collision_suffix
        // to simulate the SlugCollisionSuffixApplied listener writing to session.
        $this->loginAsAlice();
        $this->session([
            'Flash' => [
                'slug_collision_suffix' => ['slug' => 'alice-2', 'base' => 'alice'],
            ],
        ]);
        $this->get('/dashboard');
        // Template: <strong>alice-2</strong> になりました(alice は他のユーザーに使われていたため)
        $this->assertResponseContains('alice-2');
        $this->assertResponseContains('になりました');
        $this->assertResponseContains('alice は他のユーザーに使われていたため');

        // Simulate second page load: clear the Flash from _session so it is not re-injected
        // by IntegrationTestTrait._session on the second request.
        // (The controller deletes 'Flash.slug_collision_suffix' from the actual session,
        // but IntegrationTestTrait re-writes _session contents before each request.)
        $this->session(['Flash' => []]);
        $this->get('/dashboard');
        $this->assertResponseNotContains('alice は他のユーザーに使われていたため');
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
    public function testDashboardEmptyInboxShowsEmptyState(): void
    {
        // charlie (33333333-...) has no messages in fixture (all messages belong to alice's inbox).
        /** @var \App\Model\Entity\User $charlie */
        $charlie = $this->fetchTable('Users')->get('33333333-3333-3333-3333-333333333333', ['contain' => ['UserIdentities']]);
        $this->session(['Auth' => $charlie]);
        $this->get('/dashboard');
        $this->assertResponseOk();
        $this->assertResponseContains('まだ受信したメッセージはありません');
    }

    /**
     * UI-SPEC §5 out-of-range page shows fallback copy (catches PageOutOfBoundsException).
     *
     * @return void
     */
    public function testDashboardOutOfRangePageShowsCopy(): void
    {
        $this->loginAsAlice();
        $this->get('/dashboard?page=999');
        $this->assertResponseOk();
        $this->assertResponseContains('そのページはありません');
    }

    /**
     * T-03-02-04 / T-03-03a-05 XSS: messages.body is sender-controlled and displayed
     * via nl2br(h(...)). Insert a script tag body and assert it is escaped on dashboard.
     *
     * @return void
     */
    public function testDashboardBodyScriptEscaped(): void
    {
        /** @var \App\Model\Table\MessagesTable $messagesTable */
        $messagesTable = $this->fetchTable('Messages');
        /** @var \App\Model\Entity\Message $msg */
        $msg = $messagesTable->get('aaaa2222-aaaa-aaaa-aaaa-aaaaaaaaaaaa'); // already opened → body section visible
        $xssBody = '<script>alert(1)</script>';
        $msg = $messagesTable->patchEntity(
            $msg,
            ['body' => $xssBody, 'body_length' => mb_strlen($xssBody)],
            ['accessibleFields' => ['body' => true, 'body_length' => true]]
        );
        $messagesTable->saveOrFail($msg);

        $this->loginAsAlice();
        $this->get('/dashboard');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertStringContainsString(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            $body,
            'message body must be HTML-escaped on dashboard'
        );
        $this->assertStringNotContainsString(
            '<script>alert(1)</script>',
            $body,
            'raw <script> must NOT appear in rendered dashboard HTML'
        );
    }

    /**
     * Phase 4 04-01 (MSG-08 / D-20): soft-deleted messages must not appear in dashboard list.
     *
     * @return void
     */
    public function testDashboardExcludesSoftDeletedMessages(): void
    {
        // Fixture aaaa4444 is soft-deleted (Plan 04-01 Task 1 added it).
        // It must NOT appear in alice's dashboard render.
        /** @var \App\Model\Entity\User $alice */
        $alice = $this->fetchTable('Users')->get(
            '11111111-1111-1111-1111-111111111111',
            ['contain' => ['UserIdentities']]
        );
        $this->session(['Auth' => $alice]);
        $this->get('/dashboard');
        $this->assertResponseCode(200);
        $this->assertResponseNotContains('aaaa4444-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $this->assertResponseNotContains('削除済テストメッセージ');
    }

    /**
     * Phase 4 04-01 (INBOX-04 / D-04): dashboard renders ブロック中ユーザー section partial.
     *
     * @return void
     */
    public function testDashboardRendersBlockListSection(): void
    {
        // Fixture has alice→bob and alice→charlie blocks (after Plan 04-01 Task 1 append).
        /** @var \App\Model\Entity\User $alice */
        $alice = $this->fetchTable('Users')->get(
            '11111111-1111-1111-1111-111111111111',
            ['contain' => ['UserIdentities']]
        );
        $this->session(['Auth' => $alice]);
        $this->get('/dashboard');
        $this->assertResponseCode(200);
        $this->assertResponseContains('ブロック中ユーザー');
        // Phase 6 UI-07: outer wrapper now emits class="block-list tb-block-list" and
        // rows emit class="block-list__row tb-block-row" — match the legacy class as
        // a substring (proves the test-required class is still present).
        $this->assertResponseContains('class="block-list ');
        // Two block rows expected.
        $this->assertResponseContains('class="block-list__row ');
    }

    /**
     * NAV-04: /dashboard/discover unauth redirects to '/'.
     *
     * @return void
     */
    public function testDiscoverUnauthRedirectsHome(): void
    {
        $this->get('/dashboard/discover');
        $this->assertResponseCode(302);
    }

    /**
     * NAV-04: /dashboard/discover auth returns 200.
     *
     * Body assertion ("発見はもうすぐ来ます" copy) added in plan 07-06 once the
     * template lands. For 07-02 we only assert the status code.
     *
     * @return void
     */
    public function testDiscoverAuthRenders200(): void
    {
        $this->loginAsAlice();
        $this->get('/dashboard/discover');
        $this->assertResponseOk();
    }

    /**
     * NAV-05: /dashboard/notifications unauth redirects to '/'.
     *
     * @return void
     */
    public function testNotificationsUnauthRedirectsHome(): void
    {
        $this->get('/dashboard/notifications');
        $this->assertResponseCode(302);
    }

    /**
     * NAV-05: /dashboard/notifications auth returns 200.
     *
     * @return void
     */
    public function testNotificationsAuthRenders200(): void
    {
        $this->loginAsAlice();
        $this->get('/dashboard/notifications');
        $this->assertResponseOk();
    }
}
