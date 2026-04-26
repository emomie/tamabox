<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\MessagesTable;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\MessagesTable Test Case
 */
class MessagesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\MessagesTable
     */
    protected $Messages;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.Messages',
        'app.Inboxes',
        'app.Users',
        'app.UserIdentities',
        'app.Reports',
        'app.Blocks',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Messages') ? [] : ['className' => MessagesTable::class];
        $this->Messages = $this->getTableLocator()->get('Messages', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Messages);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\MessagesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * sendMessage persists ssr_seed (64 hex chars), snapshot fields, body_length.
     *
     * @return void
     */
    public function testSendMessagePersistsSsrFieldsAndSnapshot(): void
    {
        Configure::write('Security.serverSecret', 'test-server-secret-32-chars-fixed!');
        /** @var \App\Model\Entity\Inbox $inbox */
        $inbox = $this->Messages->Inboxes->get('11111111-1111-1111-1111-111111111111');
        $senderUserId = '22222222-2222-2222-2222-222222222222'; // bob

        $msg = $this->Messages->sendMessage($inbox, $senderUserId, 'こんにちは');
        $this->assertNotEmpty($msg->id);
        $this->assertSame(64, strlen((string)$msg->ssr_seed));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string)$msg->ssr_seed);
        $this->assertSame('0.100', (string)$msg->ssr_probability_at_send);
        $this->assertSame(5, $msg->body_length); // 'こんにちは' = 5 chars by mb_strlen
        $this->assertSame('bluesky', $msg->sender_provider);
        $this->assertNotEmpty($msg->sender_handle_snapshot);
    }

    /**
     * sendMessage refuses empty body.
     *
     * @return void
     */
    public function testSendMessageRefusesEmptyBody(): void
    {
        Configure::write('Security.serverSecret', 'test-server-secret-32-chars-fixed!');
        /** @var \App\Model\Entity\Inbox $inbox */
        $inbox = $this->Messages->Inboxes->get('11111111-1111-1111-1111-111111111111');
        $this->expectException(\RuntimeException::class);
        $this->Messages->sendMessage($inbox, '22222222-2222-2222-2222-222222222222', '');
    }

    /**
     * sendMessage refuses body over 2000 chars.
     *
     * @return void
     */
    public function testSendMessageRefusesBodyOver2000(): void
    {
        Configure::write('Security.serverSecret', 'test-server-secret-32-chars-fixed!');
        /** @var \App\Model\Entity\Inbox $inbox */
        $inbox = $this->Messages->Inboxes->get('11111111-1111-1111-1111-111111111111');
        $this->expectException(\RuntimeException::class);
        $this->Messages->sendMessage($inbox, '22222222-2222-2222-2222-222222222222', str_repeat('a', 2001));
    }

    /**
     * sendMessage refuses if inbox is not accepting.
     *
     * @return void
     */
    public function testSendMessageRefusesIfInboxNotAccepting(): void
    {
        Configure::write('Security.serverSecret', 'test-server-secret-32-chars-fixed!');
        /** @var \App\Model\Entity\Inbox $inbox */
        $inbox = $this->Messages->Inboxes->get('33333333-3333-3333-3333-333333333333'); // charlie, is_accepting=0
        $this->expectException(\RuntimeException::class);
        $this->Messages->sendMessage($inbox, '22222222-2222-2222-2222-222222222222', 'test');
    }

    /**
     * F2 audit: ssr_seed is deterministic from (serverSecret, messageId, createdAt).
     *
     * @return void
     */
    public function testSendMessageDeterministicSeedFromSecret(): void
    {
        Configure::write('Security.serverSecret', 'test-server-secret-32-chars-fixed!');
        /** @var \App\Model\Entity\Inbox $inbox */
        $inbox = $this->Messages->Inboxes->get('11111111-1111-1111-1111-111111111111');
        $msg = $this->Messages->sendMessage($inbox, '22222222-2222-2222-2222-222222222222', 'ok');
        // Recompute seed manually using id + created_at
        $expectedSeed = hash('sha256', 'test-server-secret-32-chars-fixed!' . $msg->id . $msg->created_at->format('Y-m-d H:i:s.u'));
        $this->assertSame($expectedSeed, (string)$msg->ssr_seed);
    }
}
