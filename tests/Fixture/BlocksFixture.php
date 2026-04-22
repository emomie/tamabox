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
                'blocker_user_id' => 'fb1b9e85-33af-4d70-8c75-77bc9c88d7a7',
                'blocked_user_id' => '539350fd-5259-44cb-84e2-2b2095fc1534',
                'reason' => 'Lorem ipsum dolor sit amet',
                'created_at' => '',
            ],
        ];
        parent::init();
    }
}
