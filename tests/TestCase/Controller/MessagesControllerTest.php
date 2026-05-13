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
 *
 * @uses \App\Controller\MessagesController
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

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableRetainFlashMessages();
        Configure::write('Security.serverSecret', 'test-server-secret-32-chars-fixed!');
    }

    // === GET smoke tests ===

    /**
     * @return void
     */
    public function testSendGetUnknownSlugReturns404(): void
    {
        $this->get('/no-such-inbox');
        $this->assertResponseCode(404);
    }

    /**
     * EDGE-01 — Phase 8 D-01: 404 renders the hi-fi SendNotFound layout.
     *
     * @return void
     */
    public function testSendNotFoundRendersHiFiTemplate(): void
    {
        $this->get('/no-such-inbox');
        $this->assertResponseCode(404);
        $this->assertResponseContains('この箱は見つかりません');
        $this->assertResponseContains('tb-error-screen');
    }

    /**
     * EDGE-03 — Phase 8: Send form ships the overflow chip + JS hooks markup.
     *
     * @return void
     */
    public function testSendFormIncludesOverflowChipMarkup(): void
    {
        $this->get('/alice');
        $this->assertResponseOk();
        $this->assertResponseContains('data-send-overflow-chip');
        $this->assertResponseContains('data-send-textarea');
        $this->assertResponseContains('data-send-submit');
        $this->assertResponseContains('長すぎます');
    }

    /**
     * @return void
     */
    public function testSendGetSlugPreviousRedirectsToCurrent(): void
    {
        // bob has slug='bob-2' and slug_previous='bob' per InboxesFixture.
        $this->get('/bob');
        $this->assertResponseCode(301);
        $this->assertRedirectContains('/bob-2');
    }

    /**
     * @return void
     */
    public function testSendGetUnauthenticatedRendersForm(): void
    {
        $this->get('/alice');
        $this->assertResponseOk();
        $this->assertResponseContains('Bluesky でログインして送信');
        $this->assertResponseContains('Alice Example の受信箱');
    }

    /**
     * @return void
     */
    public function testSendGetIsAcceptingFalseHidesForm(): void
    {
        // charlie has is_accepting=0.
        // EDGE-02: copy upgraded from v1 "現在この受信箱は受け付けていません" to
        // hi-fi SendInboxClosed "この箱はいま受信を停止しています" + status kicker.
        $this->get('/charlie');
        $this->assertResponseOk();
        $this->assertResponseContains('この箱はいま受信を停止しています');
        $this->assertResponseContains('inbox · paused');
        $this->assertResponseNotContains('<textarea name="body"');
    }

    /**
     * @return void
     */
    public function testOpenAuthenticatedSetsOpenedAt(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice(); // alice owns inbox 11111111-...; aaaa1111-... message is unread
        $this->post('/dashboard/messages/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa/open');
        $this->assertResponseCode(302);
        /** @var \App\Model\Entity\Message $msg */
        $msg = $this->fetchTable('Messages')->get('aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $this->assertNotNull($msg->opened_at);
    }

    /**
     * @return void
     */
    public function testOpenAlreadyOpenedDoesNotUpdateTimestamp(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        /** @var \App\Model\Entity\Message $before */
        $before = $this->fetchTable('Messages')->get('aaaa2222-aaaa-aaaa-aaaa-aaaaaaaaaaaa'); // already opened in fixture
        $beforeTs = $before->opened_at !== null ? $before->opened_at->format('Y-m-d H:i:s.u') : null;
        $this->post('/dashboard/messages/aaaa2222-aaaa-aaaa-aaaa-aaaaaaaaaaaa/open');
        $this->assertResponseCode(302);
        /** @var \App\Model\Entity\Message $after */
        $after = $this->fetchTable('Messages')->get('aaaa2222-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $afterTs = $after->opened_at !== null ? $after->opened_at->format('Y-m-d H:i:s.u') : null;
        $this->assertSame($beforeTs, $afterTs);
    }

    /**
     * @return void
     */
    public function testOpenOtherUsersMessageReturns403(): void
    {
        $this->enableCsrfToken();
        $this->loginAsBob(); // bob does NOT own alice's inbox
        $this->post('/dashboard/messages/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa/open');
        $this->assertResponseCode(403);
    }

    /**
     * @return void
     */
    public function testOpenUnknownMessageReturns404(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/dashboard/messages/00000000-0000-0000-0000-000000000000/open');
        $this->assertResponseCode(404);
    }

    /**
     * @return void
     */
    public function testOpenUnauthenticatedRedirects(): void
    {
        $this->enableCsrfToken();
        $this->post('/dashboard/messages/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa/open');
        // AuthenticationMiddleware redirects unauthenticated → /
        $this->assertResponseCode(302);
    }

    // === POST tests (added in Task 4) ===

    /**
     * @return void
     */
    public function testSendPostUnauthenticatedStashesBodyAndRedirectsToLogin(): void
    {
        $this->enableCsrfToken();
        $this->post('/alice', [
            'body' => 'unauth body',
            'consent' => '1',
        ]);
        $this->assertResponseCode(302);
        $this->assertRedirectContains('/login/bluesky');
        $this->assertSession('unauth body', 'pending_message_body');
        $this->assertSession('11111111-1111-1111-1111-111111111111', 'pending_message_inbox_id');
    }

    /**
     * @return void
     */
    public function testSendPostAuthenticatedHappyPathInsertsMessage(): void
    {
        $this->enableCsrfToken();
        // Phase 4 04-01: bob is blocked by alice (fixture); use dave (non-blocked sender) instead.
        $this->loginAsDave();
        $countBefore = $this->fetchTable('Messages')
            ->find()
            ->where(['inbox_id' => '11111111-1111-1111-1111-111111111111'])
            ->count();
        $this->post('/alice', [
            'body' => 'こんにちは alice',
            'consent' => '1',
        ]);
        $this->assertResponseOk(); // send_done renders directly
        // Phase 6 UI-03: copy split into heading "送信しました" + body "受け手が開封したとき…"
        // Assert both fragments to keep send_done page identity assertion.
        $this->assertResponseContains('送信しました');
        $this->assertResponseContains('受け手が開封したとき');
        $countAfter = $this->fetchTable('Messages')
            ->find()
            ->where(['inbox_id' => '11111111-1111-1111-1111-111111111111'])
            ->count();
        $this->assertSame($countBefore + 1, $countAfter);
        /** @var \App\Model\Entity\Message $msg */
        $msg = $this->fetchTable('Messages')->find()
            ->where(['inbox_id' => '11111111-1111-1111-1111-111111111111'])
            ->order(['created_at' => 'DESC'])
            ->first();
        $this->assertSame(64, strlen((string)$msg->ssr_seed));
        $this->assertSame('bluesky', $msg->sender_provider);
        $this->assertNotEmpty($msg->sender_handle_snapshot);
    }

    /**
     * @return void
     */
    public function testSendPostConsentMissingRedirectsWithError(): void
    {
        $this->enableCsrfToken();
        // Phase 4 04-01: bob is blocked by alice (fixture); use dave (non-blocked sender) instead.
        $this->loginAsDave();
        $this->post('/alice', ['body' => 'no consent']);
        $this->assertResponseCode(302);
        $this->assertRedirectContains('/alice');
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertIsArray($flash);
        $this->assertMatchesRegularExpression('/同意/', (string)$flash[0]['message']);
    }

    /**
     * @return void
     */
    public function testSendPostBodyTooLongRedirectsWithError(): void
    {
        $this->enableCsrfToken();
        // Phase 4 04-01: bob is blocked by alice (fixture); use dave (non-blocked sender) instead.
        $this->loginAsDave();
        $this->post('/alice', [
            'body' => str_repeat('a', 2001),
            'consent' => '1',
        ]);
        $this->assertResponseCode(302);
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertMatchesRegularExpression('/2000 文字/', (string)$flash[0]['message']);
    }

    /**
     * @return void
     */
    public function testSendPostBodyEmptyRedirectsWithError(): void
    {
        $this->enableCsrfToken();
        // Phase 4 04-01: bob is blocked by alice (fixture); use dave (non-blocked sender) instead.
        $this->loginAsDave();
        $this->post('/alice', [
            'body' => '',
            'consent' => '1',
        ]);
        $this->assertResponseCode(302);
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertMatchesRegularExpression('/本文/', (string)$flash[0]['message']);
    }

    /**
     * @return void
     */
    public function testSendPostToClosedInboxRedirectsWithError(): void
    {
        $this->enableCsrfToken();
        $this->loginAsBob();
        $this->post('/charlie', [ // is_accepting=0
            'body' => 'try',
            'consent' => '1',
        ]);
        $this->assertResponseCode(302);
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertMatchesRegularExpression('/受け付けていません/', (string)$flash[0]['message']);
    }

    /**
     * @return void
     */
    public function testSendDoneOmitsSsrResult(): void
    {
        $this->enableCsrfToken();
        // Phase 4 04-01: bob is blocked by alice (fixture); use dave (non-blocked sender) instead.
        $this->loginAsDave();
        $this->post('/alice', [
            'body' => 'check no ssr leak',
            'consent' => '1',
        ]);
        $this->assertResponseOk();
        $this->assertResponseNotContains('is_ssr');
        $this->assertResponseNotContains('ssr_seed');
        // D-19: send_done must NOT show SSR outcome (hit/miss).
        // Note: the fixed-copy sentence contains '抽選' (lottery) but no is_ssr/hit/miss fields.
        $this->assertResponseNotContains('"hit"');
        $this->assertResponseNotContains('"miss"');
        $this->assertResponseNotContains('ssr-result');
    }

    /**
     * @return void
     */
    public function testSendGetIsOwnInboxShowsSelfNotice(): void
    {
        $this->loginAsAlice();
        $this->get('/alice');
        $this->assertResponseOk();
        $this->assertResponseContains('これはあなたの受信箱です');
    }

    /**
     * D-33: self-send must insert a row, not divert to special case.
     *
     * @return void
     */
    public function testSendPostToOwnInboxStillInserts(): void
    {
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
        $this->assertResponseOk(); // send_done renders directly
        // Phase 6 UI-03: copy split — assert both fragments individually.
        $this->assertResponseContains('送信しました');
        $this->assertResponseContains('受け手が開封したとき');
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
        $this->assertSame(
            (string)$inbox->user_id,
            (string)$msg->sender_user_id,
            'D-33: self-send goes through normal flow with sender_user_id == inbox.user_id'
        );
    }

    /**
     * T-03-02-04 XSS: welcome_message must be HTML-escaped via nl2br(h(...)).
     *
     * @return void
     */
    public function testSendDisplaysWelcomeMessageScriptEscaped(): void
    {
        /** @var \App\Model\Table\InboxesTable $inboxesTable */
        $inboxesTable = $this->fetchTable('Inboxes');
        /** @var \App\Model\Entity\Inbox $alice */
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
        $this->assertStringContainsString(
            '&lt;script&gt;alert(2)&lt;/script&gt;',
            $body,
            'welcome_message must be HTML-escaped'
        );
        $this->assertStringNotContainsString(
            '<script>alert(2)</script>',
            $body,
            'raw <script> must NOT appear in rendered HTML'
        );
    }

    // === Phase 4 04-01: block check (D-05/D-06) + soft-delete (MSG-08) ===

    /**
     * @return void
     */
    public function testSendGetShowsBlockedBannerWhenBlocked(): void
    {
        // Fixture has alice→bob block. Login as bob, GET alice's slug → see error banner + disabled form.
        /** @var \App\Model\Entity\Inbox $aliceInbox */
        $aliceInbox = $this->fetchTable('Inboxes')->find()
            ->where(['user_id' => '11111111-1111-1111-1111-111111111111'])
            ->firstOrFail();
        /** @var \App\Model\Entity\User $bob */
        $bob = $this->fetchTable('Users')->get(
            '22222222-2222-2222-2222-222222222222',
            ['contain' => ['UserIdentities']]
        );
        $this->session(['Auth' => $bob]);

        $this->get('/' . $aliceInbox->slug);
        $this->assertResponseCode(200);
        $this->assertResponseContains('この受信箱には送信できません');
        $this->assertResponseContains('error-banner');
        $this->assertResponseContains('is-disabled');
    }

    /**
     * @return void
     */
    public function testSendPostBlockedRejectsMessage(): void
    {
        // Fixture has alice→bob block. POST send as bob → flash error + redirect, no INSERT.
        /** @var \App\Model\Entity\Inbox $aliceInbox */
        $aliceInbox = $this->fetchTable('Inboxes')->find()
            ->where(['user_id' => '11111111-1111-1111-1111-111111111111'])
            ->firstOrFail();
        $beforeCount = $this->fetchTable('Messages')->find()
            ->where(['inbox_id' => $aliceInbox->id])
            ->count();
        /** @var \App\Model\Entity\User $bob */
        $bob = $this->fetchTable('Users')->get(
            '22222222-2222-2222-2222-222222222222',
            ['contain' => ['UserIdentities']]
        );
        $this->session(['Auth' => $bob]);
        $this->enableRetainFlashMessages();
        $this->enableCsrfToken();

        $this->post('/' . $aliceInbox->slug, [
            'body' => 'blocked send attempt',
            'consent' => '1',
        ]);
        $this->assertResponseCode(302);
        $this->assertRedirectContains('/' . $aliceInbox->slug);
        $afterCount = $this->fetchTable('Messages')->find()
            ->where(['inbox_id' => $aliceInbox->id])
            ->count();
        $this->assertSame($beforeCount, $afterCount, 'Blocked POST must not INSERT message');
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertIsArray($flash);
        $this->assertMatchesRegularExpression('/送信できません/', (string)$flash[0]['message']);
    }

    /**
     * MOD-04 sentinel: alice→bob block must NOT prevent bob sending to charlie's inbox.
     *
     * @return void
     */
    public function testSendPostUnrelatedInboxIgnoresUnrelatedBlocks(): void
    {
        $charlieInbox = $this->fetchTable('Inboxes')->find()
            ->where(['user_id' => '33333333-3333-3333-3333-333333333333'])
            ->first();
        if ($charlieInbox === null) {
            $this->markTestSkipped(
                'Charlie inbox not in fixture; skipping MOD-04 sentinel.'
            );

            return;
        }
        /** @var \App\Model\Entity\User $bob */
        $bob = $this->fetchTable('Users')->get(
            '22222222-2222-2222-2222-222222222222',
            ['contain' => ['UserIdentities']]
        );
        $this->session(['Auth' => $bob]);
        $this->enableCsrfToken();
        $this->get('/' . $charlieInbox->slug);
        $this->assertResponseCode(200);
        $this->assertResponseNotContains('error-banner');
    }

    /**
     * @return void
     */
    public function testDeleteSoftDeletesMessage(): void
    {
        // Fixture aaaa1111... is alice's unread message. Login as alice, POST /dashboard/messages/{id}/delete.
        $this->enableCsrfToken();
        $this->enableRetainFlashMessages();
        /** @var \App\Model\Entity\User $alice */
        $alice = $this->fetchTable('Users')->get(
            '11111111-1111-1111-1111-111111111111',
            ['contain' => ['UserIdentities']]
        );
        $this->session(['Auth' => $alice]);

        $this->post('/dashboard/messages/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa/delete');
        $this->assertResponseCode(302);
        $this->assertRedirectContains('/dashboard');
        $msg = $this->fetchTable('Messages')->get('aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $this->assertNotNull($msg->deleted_at, 'deleted_at must be set');
        $this->assertSame('user_deleted', (string)$msg->deleted_reason);
        // body / body_length must NOT change (RESEARCH Pitfall 5)
        $this->assertSame('未開封テストメッセージ', (string)$msg->body);
        $this->assertSame(11, (int)$msg->body_length);
    }

    /**
     * @return void
     */
    public function testDeleteForbiddenForNonOwner(): void
    {
        // bob tries to delete alice's message.
        $this->enableCsrfToken();
        /** @var \App\Model\Entity\User $bob */
        $bob = $this->fetchTable('Users')->get(
            '22222222-2222-2222-2222-222222222222',
            ['contain' => ['UserIdentities']]
        );
        $this->session(['Auth' => $bob]);
        $this->post('/dashboard/messages/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa/delete');
        $this->assertResponseCode(403);
    }

    // === helpers ===

    /**
     * Log in as Bob (22222222-...) — a sender who has a UserIdentity.
     *
     * SessionAuthenticator with identify=true reads session['Auth']['id'] and
     * re-fetches the user via ORM. Storing the array form ['id' => uuid] is
     * sufficient and avoids Entity serialization issues in the test session.
     *
     * @return void
     */
    private function loginAsBob(): void
    {
        $this->session(['Auth' => ['id' => '22222222-2222-2222-2222-222222222222']]);
    }

    /**
     * Log in as Alice (11111111-...) — the owner of the 'alice' inbox.
     *
     * @return void
     */
    private function loginAsAlice(): void
    {
        $this->session(['Auth' => ['id' => '11111111-1111-1111-1111-111111111111']]);
    }

    /**
     * Log in as Dave (44444444-...) — Phase 4 04-01: a sender NOT blocked by alice.
     * Required because Phase 4 added dual-gate block check; bob is in the alice→bob block fixture
     * and would be intercepted by the new block check before reaching consent/body validators.
     *
     * @return void
     */
    private function loginAsDave(): void
    {
        $this->session(['Auth' => ['id' => '44444444-4444-4444-4444-444444444444']]);
    }

    /**
     * REV-01 sentinel: after the inbox owner's deleted_at is set, /<their-slug> must return 404.
     *
     * @return void
     */
    public function testSendReturns404WhenInboxOwnerRetired(): void
    {
        /** @var \App\Model\Entity\Inbox $aliceInbox */
        $aliceInbox = $this->fetchTable('Inboxes')->find()
            ->where(['user_id' => '11111111-1111-1111-1111-111111111111'])
            ->firstOrFail();

        // Set alice's deleted_at directly (simulating post-退会 state).
        $usersTable = $this->fetchTable('Users');
        $alice = $usersTable->get('11111111-1111-1111-1111-111111111111');
        $alice->set('deleted_at', \Cake\I18n\FrozenTime::now());
        $usersTable->saveOrFail($alice, ['accessibleFields' => ['deleted_at' => true]]);

        // Anonymous GET /<alice-slug> must 404.
        $this->get('/' . $aliceInbox->slug);
        $this->assertResponseCode(404);
    }
}
