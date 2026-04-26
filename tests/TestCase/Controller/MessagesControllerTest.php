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
        $this->get('/charlie');
        $this->assertResponseOk();
        $this->assertResponseContains('現在この受信箱は受け付けていません');
        $this->assertResponseNotContains('<textarea name="body"');
    }

    /**
     * @return void
     */
    public function testReportReturns501Stub(): void
    {
        $this->enableCsrfToken();
        $this->post('/report/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $this->assertResponseCode(501);
    }

    /**
     * @return void
     */
    public function testOpenReturns501Placeholder(): void
    {
        $this->enableCsrfToken();
        $this->post('/dashboard/messages/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa/open');
        // Wave 3a Task 1 replaces this body. Until then, 501 OR 302 (auth redirect)
        // depending on identity. Without identity, AuthenticationMiddleware redirects → 302.
        $code = $this->_response->getStatusCode();
        $this->assertContains($code, [302, 501], "Expected 302 (auth redirect) or 501 (stub), got $code");
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
        $this->loginAsBob();
        $countBefore = $this->fetchTable('Messages')
            ->find()
            ->where(['inbox_id' => '11111111-1111-1111-1111-111111111111'])
            ->count();
        $this->post('/alice', [
            'body' => 'こんにちは alice',
            'consent' => '1',
        ]);
        $this->assertResponseOk(); // send_done renders directly
        $this->assertResponseContains('送信しました。受け手が開封したとき');
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
        $this->loginAsBob();
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
        $this->loginAsBob();
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
        $this->loginAsBob();
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
        $this->loginAsBob();
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
}
