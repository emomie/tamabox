<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * BlocksController integration tests — Phase 4 (real INSERT/DELETE).
 *
 * Phase 2 sticky #1: $fixtures must be UNTYPED.
 * Replaces Phase 3 testCreateReturns501Stub per D-35 hand-off protocol.
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableRetainFlashMessages();
    }

    private function loginAsAlice(): void
    {
        /** @var \App\Model\Entity\User $alice */
        $alice = $this->fetchTable('Users')->get(
            '11111111-1111-1111-1111-111111111111',
            ['contain' => ['UserIdentities']]
        );
        $this->session(['Auth' => $alice]);
    }

    // === create ===

    public function testCreateUnauthenticatedRedirects(): void
    {
        $this->enableCsrfToken();
        $this->post('/block/22222222-2222-2222-2222-222222222222');
        $this->assertResponseCode(302);
    }

    public function testCreateOnlyAllowsPost(): void
    {
        $this->loginAsAlice();
        $this->get('/block/22222222-2222-2222-2222-222222222222');
        $code = $this->_response->getStatusCode();
        $this->assertContains($code, [404, 405], "Expected 404/405 for non-POST, got $code");
    }

    public function testCreateInsertsBlocksRow(): void
    {
        // Block charlie (alice→charlie already in fixture, so this exercises idempotency
        // path — D-03 silent success).
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/block/33333333-3333-3333-3333-333333333333');
        $this->assertResponseCode(302);
        $this->assertRedirectContains('/dashboard');
        $exists = $this->fetchTable('Blocks')->exists([
            'blocker_user_id' => '11111111-1111-1111-1111-111111111111',
            'blocked_user_id' => '33333333-3333-3333-3333-333333333333',
        ]);
        $this->assertTrue($exists);
    }

    public function testCreateIdempotentOnDuplicate(): void
    {
        // Fixture has alice→bob block. Re-block must succeed silently (D-03).
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/block/22222222-2222-2222-2222-222222222222');
        $this->assertResponseCode(302); // not 500 — DatabaseException is caught
        $this->assertRedirectContains('/dashboard');
        // Still exactly 1 block for that pair (idempotent).
        $count = $this->fetchTable('Blocks')->find()
            ->where([
                'blocker_user_id' => '11111111-1111-1111-1111-111111111111',
                'blocked_user_id' => '22222222-2222-2222-2222-222222222222',
            ])
            ->count();
        $this->assertSame(1, $count);
    }

    public function testCreateRejectsSelfBlock(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/block/11111111-1111-1111-1111-111111111111'); // blocking self
        $this->assertResponseCode(302);
        $this->assertRedirectContains('/dashboard');
        $flash = $this->_requestSession->read('Flash.flash');
        $this->assertIsArray($flash);
        $this->assertMatchesRegularExpression('/自分自身/', (string)$flash[0]['message']);
    }

    // === delete ===

    public function testDeleteUnauthenticatedRedirects(): void
    {
        $this->enableCsrfToken();
        $this->post('/dashboard/blocks/2c6c3fe8-b629-4c91-8143-abf7995de6ea/delete');
        $this->assertResponseCode(302);
    }

    public function testDeleteOnlyAllowsPost(): void
    {
        $this->loginAsAlice();
        $this->get('/dashboard/blocks/2c6c3fe8-b629-4c91-8143-abf7995de6ea/delete');
        $code = $this->_response->getStatusCode();
        $this->assertContains($code, [404, 405]);
    }

    public function testDeleteRemovesBlocksRow(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/dashboard/blocks/2c6c3fe8-b629-4c91-8143-abf7995de6ea/delete');
        $this->assertResponseCode(302);
        $this->assertRedirectContains('/dashboard');
        $exists = $this->fetchTable('Blocks')->exists(['id' => '2c6c3fe8-b629-4c91-8143-abf7995de6ea']);
        $this->assertFalse($exists);
    }

    public function testDeleteForbiddenForNonOwner(): void
    {
        // Fixture alice→bob block, but we login as bob trying to delete alice's block row.
        $this->enableCsrfToken();
        /** @var \App\Model\Entity\User $bob */
        $bob = $this->fetchTable('Users')->get(
            '22222222-2222-2222-2222-222222222222',
            ['contain' => ['UserIdentities']]
        );
        $this->session(['Auth' => $bob]);
        $this->post('/dashboard/blocks/2c6c3fe8-b629-4c91-8143-abf7995de6ea/delete');
        $this->assertResponseCode(403);
    }

    public function testDeleteNotFoundForUnknownId(): void
    {
        $this->enableCsrfToken();
        $this->loginAsAlice();
        $this->post('/dashboard/blocks/00000000-0000-0000-0000-000000000000/delete');
        $this->assertResponseCode(404);
    }
}
