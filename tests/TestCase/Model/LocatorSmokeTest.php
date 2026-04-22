<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * Locator Smoke Test
 *
 * Phase 1 (INFRA-07) regression guard: proves every one of the 6 domain
 * Table aliases resolves to its explicit App\Model\Table\<Name>Table class
 * via TableRegistry::getTableLocator(). Application.php enables
 * allowFallbackClass(false) in the HTTP SAPI path, which means a missing
 * Table class would fatal at runtime in Phase 2+ controller code. This
 * test catches any regression (deleted file, renamed namespace, bake wipe)
 * before the fatal reaches production.
 *
 * @internal
 */
class LocatorSmokeTest extends TestCase
{
    /**
     * Every Phase 1 alias must resolve to its concrete Table subclass,
     * never the generic Cake\ORM\Table fallback.
     *
     * @return void
     */
    public function testAllPhaseOneTablesResolveToExplicitClasses(): void
    {
        $expected = [
            'Users' => \App\Model\Table\UsersTable::class,
            'UserIdentities' => \App\Model\Table\UserIdentitiesTable::class,
            'Inboxes' => \App\Model\Table\InboxesTable::class,
            'Messages' => \App\Model\Table\MessagesTable::class,
            'Blocks' => \App\Model\Table\BlocksTable::class,
            'Reports' => \App\Model\Table\ReportsTable::class,
        ];

        // Ensure a clean locator state so no other test left an aliased
        // stub in place.
        TableRegistry::getTableLocator()->clear();

        foreach ($expected as $alias => $fqcn) {
            $table = TableRegistry::getTableLocator()->get($alias);
            $this->assertInstanceOf(
                $fqcn,
                $table,
                sprintf(
                    "Alias '%s' did not resolve to %s (got %s)",
                    $alias,
                    $fqcn,
                    get_class($table)
                )
            );
        }
    }
}
