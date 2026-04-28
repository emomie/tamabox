<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * BlocksFixture
 */
class BlocksFixture extends TestFixture
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
                'id' => '2c6c3fe8-b629-4c91-8143-abf7995de6ea',
                'blocker_user_id' => '11111111-1111-1111-1111-111111111111',
                'blocked_user_id' => '22222222-2222-2222-2222-222222222222',
                'reason' => 'spam',
                'created_at' => '2026-04-22 12:00:00',
            ],
            [
                'id' => 'b10c1c1c-aaaa-bbbb-cccc-111122223333',
                'blocker_user_id' => '11111111-1111-1111-1111-111111111111',
                'blocked_user_id' => '33333333-3333-3333-3333-333333333333',
                'reason' => null,
                'created_at' => '2026-04-22 12:30:00',
            ],
        ];
        parent::init();
    }
}
