<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\InboxesTable;
use Cake\Http\Exception\NotFoundException;
use Cake\TestSuite\TestCase;
use Cake\Utility\Text;

/**
 * App\Model\Table\InboxesTable Test Case
 */
class InboxesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\InboxesTable
     */
    protected $Inboxes;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.Inboxes',
        'app.Users',
        'app.Messages',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Inboxes') ? [] : ['className' => InboxesTable::class];
        $this->Inboxes = $this->getTableLocator()->get('Inboxes', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Inboxes);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\InboxesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test findBySlugOrPrevious resolves by current slug.
     *
     * @return void
     */
    public function testFindBySlugOrPreviousFindsByCurrentSlug(): void
    {
        $r = $this->Inboxes->findBySlugOrPrevious('alice');
        $this->assertSame('11111111-1111-1111-1111-111111111111', $r['inbox']->id);
        $this->assertFalse($r['redirect']);
    }

    /**
     * Test findBySlugOrPrevious falls back to slug_previous (D-04).
     *
     * bob renamed to bob-2; looking up 'bob' should hit slug_previous.
     *
     * @return void
     */
    public function testFindBySlugOrPreviousFallsBackToSlugPrevious(): void
    {
        $r = $this->Inboxes->findBySlugOrPrevious('bob');
        $this->assertSame('22222222-2222-2222-2222-222222222222', $r['inbox']->id);
        $this->assertTrue($r['redirect']);
        $this->assertSame('bob-2', $r['inbox']->slug);
    }

    /**
     * Test findBySlugOrPrevious throws NotFoundException when neither slug matches.
     *
     * @return void
     */
    public function testFindBySlugOrPreviousNotFoundThrows(): void
    {
        $this->expectException(NotFoundException::class);
        $this->Inboxes->findBySlugOrPrevious('does-not-exist');
    }

    /**
     * Test assignSlugForUser creates new inbox without collision.
     *
     * @return void
     */
    public function testAssignSlugForUserNewInboxNoCollision(): void
    {
        // Insert a 4th user via the Users table.
        /** @var \App\Model\Table\UsersTable $usersTable */
        $usersTable = $this->getTableLocator()->get('Users');
        $newUserId = Text::uuid();
        $newUser = $usersTable->newEntity([
            'id' => $newUserId,
            'display_name' => 'Dave',
        ], ['accessibleFields' => ['id' => true, 'display_name' => true]]);
        $usersTable->saveOrFail($newUser);

        $result = $this->Inboxes->assignSlugForUser($newUserId, 'dave', 'did:plc:dave123');

        $this->assertSame('dave', $result['slug']);
        $this->assertFalse($result['slug_changed']);
        $this->assertFalse($result['suffix_applied']);

        // Verify row was actually created.
        /** @var \App\Model\Entity\Inbox $inbox */
        $inbox = $this->Inboxes->find()->where(['slug' => 'dave'])->firstOrFail();
        $this->assertSame($newUserId, $inbox->user_id);
    }

    /**
     * Test assignSlugForUser applies -2 suffix when base slug collides.
     *
     * alice already owns slug='alice'; a new user requesting the same base gets 'alice-2'.
     *
     * @return void
     */
    public function testAssignSlugForUserCollisionAppliesSuffix(): void
    {
        /** @var \App\Model\Table\UsersTable $usersTable */
        $usersTable = $this->getTableLocator()->get('Users');
        $newUserId = Text::uuid();
        $newUser = $usersTable->newEntity([
            'id' => $newUserId,
            'display_name' => 'Alice2',
        ], ['accessibleFields' => ['id' => true, 'display_name' => true]]);
        $usersTable->saveOrFail($newUser);

        $result = $this->Inboxes->assignSlugForUser($newUserId, 'alice', 'did:plc:alice2did');

        $this->assertSame('alice-2', $result['slug']);
        $this->assertTrue($result['suffix_applied']);
        $this->assertFalse($result['slug_changed']);
    }

    /**
     * Test assignSlugForUser moves old slug to slug_previous on handle rename.
     *
     * alice has slug='alice'; after rename to 'satie' base, slug becomes 'satie'
     * and slug_previous becomes 'alice'.
     *
     * @return void
     */
    public function testAssignSlugForUserHandleRenameMovesSlugPrevious(): void
    {
        $aliceUserId = '11111111-1111-1111-1111-111111111111';

        $result = $this->Inboxes->assignSlugForUser($aliceUserId, 'satie', 'did:plc:alice123');

        $this->assertSame('satie', $result['slug']);
        $this->assertTrue($result['slug_changed']);
        $this->assertFalse($result['suffix_applied']);

        // Verify slug_previous was recorded.
        /** @var \App\Model\Entity\Inbox $inbox */
        $inbox = $this->Inboxes->find()->where(['user_id' => $aliceUserId])->firstOrFail();
        $this->assertSame('satie', $inbox->slug);
        $this->assertSame('alice', $inbox->slug_previous);
    }

    /**
     * Test assignSlugForUser returns existing slug unchanged when base matches current.
     *
     * @return void
     */
    public function testAssignSlugForUserNoChangeWhenBaseMatches(): void
    {
        // alice already has slug='alice'; re-deriving 'alice' base should be a no-op.
        $aliceUserId = '11111111-1111-1111-1111-111111111111';

        $result = $this->Inboxes->assignSlugForUser($aliceUserId, 'alice', 'did:plc:alice123');

        $this->assertSame('alice', $result['slug']);
        $this->assertFalse($result['slug_changed']);
        $this->assertFalse($result['suffix_applied']);
    }
}
