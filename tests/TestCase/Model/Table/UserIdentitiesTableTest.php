<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\UserIdentitiesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\UserIdentitiesTable Test Case
 */
class UserIdentitiesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\UserIdentitiesTable
     */
    protected $UserIdentities;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.UserIdentities',
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
        $config = $this->getTableLocator()->exists('UserIdentities') ? [] : ['className' => UserIdentitiesTable::class];
        $this->UserIdentities = $this->getTableLocator()->get('UserIdentities', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->UserIdentities);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\UserIdentitiesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
