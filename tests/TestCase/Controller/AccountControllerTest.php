<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * AccountController integration tests — Phase 4 MOD-03 / D-23..D-27.
 *
 * Phase 2 sticky #1: $fixtures must be UNTYPED.
 */
class AccountControllerTest extends TestCase
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

    // === GET tests ===

    /**
     * @return void
     */
    public function testDeleteGetUnauthenticatedRedirects(): void
    {
        $this->get('/account/delete');
        $this->assertResponseCode(302);
    }

    /**
     * @return void
     */
    public function testDeleteGetRendersForm(): void
    {
        $this->loginAsAlice();
        $this->get('/account/delete');
        $this->assertResponseCode(200);
        $this->assertResponseContains('退会の手続き');
        $this->assertResponseContains('confirm_delete');
        $this->assertResponseContains('退会する');
    }

    // === POST tests ===

    /**
     * @return void
     */
    public function testDeletePostHappyPathSetsDeletedAt(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();

        // Snapshot count of alice's messages before退会 (MOD-03 sentinel).
        $aliceInbox = $this->fetchTable('Inboxes')->find()
            ->where(['user_id' => '11111111-1111-1111-1111-111111111111'])
            ->firstOrFail();
        $messageCountBefore = $this->fetchTable('Messages')->find()
            ->where(['inbox_id' => $aliceInbox->id])
            ->count();

        $this->post('/account/delete', ['confirm_delete' => '1']);
        $this->assertResponseCode(302);
        $this->assertRedirectContains('/');

        /** @var \App\Model\Entity\User $alice */
        $alice = $this->fetchTable('Users')->get('11111111-1111-1111-1111-111111111111');
        $this->assertNotNull($alice->deleted_at, 'users.deleted_at must be set after 退会');

        // MOD-03 sentinel: messages preserved.
        $messageCountAfter = $this->fetchTable('Messages')->find()
            ->where(['inbox_id' => $aliceInbox->id])
            ->count();
        $this->assertSame($messageCountBefore, $messageCountAfter, 'messages must NOT be deleted on 退会 (MOD-03)');

        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertIsArray($flash);
        $this->assertMatchesRegularExpression('/退会が完了/', (string)$flash[0]['message']);
    }

    /**
     * @return void
     */
    public function testDeletePostWithoutCheckboxRejected(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/account/delete', []); // no confirm_delete
        $this->assertResponseCode(400);

        /** @var \App\Model\Entity\User $alice */
        $alice = $this->fetchTable('Users')->get('11111111-1111-1111-1111-111111111111');
        $this->assertNull($alice->deleted_at, 'users.deleted_at must NOT be set when checkbox missing');
    }

    /**
     * @return void
     */
    public function testDeletePostPreservesSenderSnapshots(): void
    {
        // Bob has sent messages in alice's inbox (Fixture aaaa1111 etc., sender_*_snapshot fields populated).
        // After bob 退会s, those snapshots must remain unchanged on the messages rows (MOD-03).
        $this->enableCsrfToken();
        $this->session(['Auth' => ['id' => '22222222-2222-2222-2222-222222222222']]);

        $msgBefore = $this->fetchTable('Messages')->get('aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $handleBefore = (string)$msgBefore->sender_handle_snapshot;
        $avatarBefore = (string)$msgBefore->sender_avatar_url_snapshot;
        $profileBefore = (string)$msgBefore->sender_profile_url_snapshot;

        $this->post('/account/delete', ['confirm_delete' => '1']);
        $this->assertResponseCode(302);

        $msgAfter = $this->fetchTable('Messages')->get('aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $this->assertSame($handleBefore, (string)$msgAfter->sender_handle_snapshot);
        $this->assertSame($avatarBefore, (string)$msgAfter->sender_avatar_url_snapshot);
        $this->assertSame($profileBefore, (string)$msgAfter->sender_profile_url_snapshot);
    }
}
