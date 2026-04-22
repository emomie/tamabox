<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\BlocksTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\BlocksTable Test Case
 */
class BlocksTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\BlocksTable
     */
    protected $Blocks;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.Blocks',
        'app.Users',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Blocks') ? [] : ['className' => BlocksTable::class];
        $this->Blocks = $this->getTableLocator()->get('Blocks', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Blocks);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\BlocksTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
