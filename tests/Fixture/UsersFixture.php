<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * UsersFixture
 */
class UsersFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => '11111111-1111-1111-1111-111111111111',
                'display_name' => 'Alice Example',
                'created_at' => '2026-04-22 12:00:00',
                'updated_at' => '2026-04-22 12:00:00',
                'deleted_at' => null,
            ],
            [
                'id' => '22222222-2222-2222-2222-222222222222',
                'display_name' => 'Bob Example',
                'created_at' => '2026-04-22 12:00:00',
                'updated_at' => '2026-04-22 12:00:00',
                'deleted_at' => null,
            ],
            [
                'id' => '33333333-3333-3333-3333-333333333333',
                'display_name' => 'Charlie Example',
                'created_at' => '2026-04-22 12:00:00',
                'updated_at' => '2026-04-22 12:00:00',
                'deleted_at' => null,
            ],
            // Dave — Phase 4 04-01: non-blocked sender used by send-flow happy/validation tests
            // (alice→bob and alice→charlie blocks make those users unsuitable as senders to alice).
            [
                'id' => '44444444-4444-4444-4444-444444444444',
                'display_name' => 'Dave Example',
                'created_at' => '2026-04-22 12:00:00',
                'updated_at' => '2026-04-22 12:00:00',
                'deleted_at' => null,
            ],
        ];
        parent::init();
    }
}
