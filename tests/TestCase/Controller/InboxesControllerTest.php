<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * InboxesController integration tests — Phase 3 settings (GET redirect + POST save).
 *
 * @uses \App\Controller\InboxesController
 */
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

    // === GET tests ===

    /**
     * Phase 7 NAV-06: GET /dashboard/settings now renders the settings tab
     * instead of 302-redirecting to /dashboard (was Phase 3 behavior; the
     * settings page is now a standalone tab per Phase 7 D-02).
     *
     * @return void
     */
    public function testSettingsGetRendersSettingsTab(): void
    {
        $this->loginAsAlice();
        $this->get('/dashboard/settings');
        $this->assertResponseOk();
        // Inbox settings form is rendered (asserts on existing Phase 6 form fields).
        $this->assertResponseContains('name="ssr_probability_pct"');
        $this->assertResponseContains('name="welcome_message"');
    }

    /**
     * @return void
     */
    public function testSettingsGetUnauthenticatedRedirects(): void
    {
        $this->get('/dashboard/settings');
        $this->assertResponseCode(302);
    }

    // === POST tests ===

    /**
     * @return void
     */
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
        $this->assertRedirectContains('/dashboard');
        /** @var \App\Model\Entity\Inbox $inbox */
        $inbox = $this->fetchTable('Inboxes')->get('11111111-1111-1111-1111-111111111111');
        $this->assertSame('0.500', (string)$inbox->ssr_probability);
        $this->assertSame('よろしくお願いします', (string)$inbox->welcome_message);
        $this->assertTrue((bool)$inbox->is_accepting);
    }

    /**
     * @return void
     */
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
        /** @var \App\Model\Entity\Inbox $inbox */
        $inbox = $this->fetchTable('Inboxes')->get('11111111-1111-1111-1111-111111111111');
        $this->assertSame('0.000', (string)$inbox->ssr_probability);
    }

    /**
     * @return void
     */
    public function testSettingsPostHundredPercent(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/dashboard/settings', [
            'ssr_probability_pct' => '100',
            'is_accepting' => '1',
        ]);
        $this->assertResponseCode(302);
        /** @var \App\Model\Entity\Inbox $inbox */
        $inbox = $this->fetchTable('Inboxes')->get('11111111-1111-1111-1111-111111111111');
        $this->assertSame('1.000', (string)$inbox->ssr_probability);
    }

    /**
     * @return void
     */
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
        /** @var \App\Model\Entity\Inbox $inbox */
        $inbox = $this->fetchTable('Inboxes')->get('11111111-1111-1111-1111-111111111111');
        $this->assertSame('0.100', (string)$inbox->ssr_probability); // unchanged
    }

    /**
     * @return void
     */
    public function testSettingsPostNegativeRejected(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/dashboard/settings', ['ssr_probability_pct' => '-5']);
        $this->assertResponseCode(302);
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertIsArray($flash);
        $this->assertMatchesRegularExpression('/0〜100/', (string)$flash[0]['message']);
    }

    /**
     * @return void
     */
    public function testSettingsPostNonIntegerRejected(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/dashboard/settings', ['ssr_probability_pct' => 'foo']);
        $this->assertResponseCode(302);
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertIsArray($flash);
        $this->assertMatchesRegularExpression('/0〜100/', (string)$flash[0]['message']);
    }

    /**
     * @return void
     */
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
        /** @var \App\Model\Entity\Inbox $inbox */
        $inbox = $this->fetchTable('Inboxes')->get('11111111-1111-1111-1111-111111111111');
        $this->assertFalse((bool)$inbox->is_accepting);
    }

    /**
     * @return void
     */
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
        $this->assertIsArray($flash);
        $this->assertMatchesRegularExpression('/1000 文字/', (string)$flash[0]['message']);
        /** @var \App\Model\Entity\Inbox $inbox */
        $inbox = $this->fetchTable('Inboxes')->get('11111111-1111-1111-1111-111111111111');
        $this->assertNull($inbox->welcome_message); // unchanged
    }

    /**
     * @return void
     */
    public function testSettingsPostUnauthenticatedRedirects(): void
    {
        $this->enableCsrfToken();
        $this->post('/dashboard/settings', ['ssr_probability_pct' => '10']);
        $this->assertResponseCode(302);
    }

    /**
     * D-12: changing inbox.ssr_probability does NOT change existing messages.ssr_probability_at_send.
     *
     * @return void
     */
    public function testSettingsPostDoesNotAffectExistingMessages(): void
    {
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

    /**
     * Flash.success message is shown on successful save.
     *
     * @return void
     */
    public function testSettingsPostSuccessFlash(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/dashboard/settings', [
            'ssr_probability_pct' => '20',
            'is_accepting' => '1',
        ]);
        $this->assertResponseCode(302);
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertIsArray($flash);
        $this->assertMatchesRegularExpression('/保存しました/', (string)$flash[0]['message']);
    }

    /**
     * Phase 4 REV-01 regression sentinel: retired-user JOIN filter must NOT break the active-user
     * happy path on /dashboard/settings. Phase 7 updated the GET behavior from 302→render
     * (NAV-06), so this regression sentinel now asserts 200 + that the inbox is loaded.
     *
     * @return void
     */
    public function testSettingsStillReachableForActiveUser(): void
    {
        $this->loginAsAlice();
        $this->get('/dashboard/settings');
        $this->assertResponseOk();
        // Inbox settings form fields are present (proves the controller loaded
        // the inbox entity and rendered the template).
        $this->assertResponseContains('name="ssr_probability_pct"');
    }
}
