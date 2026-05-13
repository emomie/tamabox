<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * ReportsController integration tests — Phase 4 MOD-01 / MOD-02 / D-09..D-13.
 *
 * Phase 2 sticky #1: $fixtures must be UNTYPED.
 */
class ReportsControllerTest extends TestCase
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
        $this->session(['Auth' => ['id' => '11111111-1111-1111-1111-111111111111']]);
    }

    /**
     * @return void
     */
    private function loginAsBob(): void
    {
        $this->session(['Auth' => ['id' => '22222222-2222-2222-2222-222222222222']]);
    }

    // === GET tests ===

    /**
     * @return void
     */
    public function testCreateGetUnauthenticatedRedirects(): void
    {
        $this->get('/report/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $this->assertResponseCode(302);
    }

    /**
     * @return void
     */
    public function testCreateGetRendersForm(): void
    {
        $this->loginAsAlice();
        $this->get('/report/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $this->assertResponseCode(200);
        // Phase 6 UI-05 shortens the heading from "このメッセージを通報する" to hi-fi "メッセージを通報".
        $this->assertResponseContains('メッセージを通報');
        $this->assertResponseContains('class="report-form');
        $this->assertResponseContains('value="harassment"');
        $this->assertResponseContains('value="spam"');
        $this->assertResponseContains('value="illegal"');
        $this->assertResponseContains('value="other"');
    }

    /**
     * @return void
     */
    public function testCreateGetAlreadyReportedRedirectsWithFlash(): void
    {
        // Fixture (Plan 04-02 Task 1) has alice already reported aaaa2222.
        $this->loginAsAlice();
        $this->get('/report/aaaa2222-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $this->assertResponseCode(302);
        $this->assertRedirectContains('/dashboard');
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertIsArray($flash);
        $this->assertMatchesRegularExpression('/既に通報済み/', (string)$flash[0]['message']);
    }

    /**
     * @return void
     */
    public function testCreateGetForeignMessageReturns404(): void
    {
        // bob trying to report alice's message (which is NOT bob's inbox's message).
        $this->loginAsBob();
        $this->get('/report/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $this->assertResponseCode(404);
    }

    /**
     * @return void
     */
    public function testCreateGetUnknownMessageReturns404(): void
    {
        $this->loginAsAlice();
        $this->get('/report/00000000-0000-0000-0000-000000000000');
        $this->assertResponseCode(404);
    }

    // === POST tests ===

    /**
     * @return void
     */
    public function testCreatePostHappyPathInsertsRow(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/report/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa', [
            'reason' => 'harassment',
            'detail' => '',
        ]);
        $this->assertResponseCode(302);
        $this->assertRedirectContains('/dashboard');
        $exists = $this->fetchTable('Reports')->exists([
            'reporter_user_id' => '11111111-1111-1111-1111-111111111111',
            'message_id' => 'aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        ]);
        $this->assertTrue($exists);
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertIsArray($flash);
        $this->assertMatchesRegularExpression('/通報を送信しました/', (string)$flash[0]['message']);
    }

    /**
     * @return void
     */
    public function testCreatePostReasonOtherWithoutDetailRejected(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/report/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa', [
            'reason' => 'other',
            'detail' => '',
        ]);
        $this->assertResponseCode(302);
        $this->assertRedirectContains('/report/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $exists = $this->fetchTable('Reports')->exists([
            'reporter_user_id' => '11111111-1111-1111-1111-111111111111',
            'message_id' => 'aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        ]);
        $this->assertFalse($exists, 'Report must NOT be inserted on validation fail');
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertIsArray($flash);
        $this->assertMatchesRegularExpression('/その他.*詳細/', (string)$flash[0]['message']);
    }

    /**
     * @return void
     */
    public function testCreatePostInvalidReasonRejected(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/report/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa', [
            'reason' => 'BOGUS',
        ]);
        $this->assertResponseCode(302);
        $exists = $this->fetchTable('Reports')->exists([
            'reporter_user_id' => '11111111-1111-1111-1111-111111111111',
            'message_id' => 'aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        ]);
        $this->assertFalse($exists);
    }

    /**
     * @return void
     */
    public function testCreatePostDetailOver1000CharsRejected(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $longDetail = str_repeat('あ', 1001); // 1001 chars > 1000 limit
        $this->post('/report/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa', [
            'reason' => 'spam',
            'detail' => $longDetail,
        ]);
        $this->assertResponseCode(302);
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertIsArray($flash);
        $this->assertMatchesRegularExpression('/1000 文字/', (string)$flash[0]['message']);
    }

    /**
     * @return void
     */
    public function testCreatePostDuplicateRejectedByUniqueConstraint(): void
    {
        // Fixture has alice already reported aaaa2222. POST again must be rejected by uk_reports_reporter_message.
        // The GET path early-redirects, but a direct POST exercises the catch.
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/report/aaaa2222-aaaa-aaaa-aaaa-aaaaaaaaaaaa', [
            'reason' => 'harassment',
            'detail' => '',
        ]);
        $this->assertResponseCode(302);
        $this->assertRedirectContains('/dashboard');
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertIsArray($flash);
        $this->assertMatchesRegularExpression('/既に通報済み/', (string)$flash[0]['message']);
        // Still exactly 1 row for that pair.
        $count = $this->fetchTable('Reports')->find()
            ->where([
                'reporter_user_id' => '11111111-1111-1111-1111-111111111111',
                'message_id' => 'aaaa2222-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ])
            ->count();
        $this->assertSame(1, $count);
    }

    /**
     * @return void
     */
    public function testCreatePostForeignMessageReturns404(): void
    {
        $this->enableCsrfToken();
        $this->loginAsBob();
        $this->post('/report/aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa', [
            'reason' => 'spam',
        ]);
        $this->assertResponseCode(404);
    }
}
