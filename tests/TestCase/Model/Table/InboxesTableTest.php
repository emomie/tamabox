<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\InboxesTable;
use Cake\TestSuite\TestCase;

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
}
