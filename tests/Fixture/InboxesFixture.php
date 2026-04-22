<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * InboxesFixture
 */
class InboxesFixture extends TestFixture
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
                'id' => 'ef8c90df-5fd3-4e2c-be65-744fa440be90',
                'user_id' => '11111111-1111-1111-1111-111111111111',
                'slug' => 'alice-box',
                'ssr_probability' => 0.100,
                'is_accepting' => 1,
                'welcome_message' => 'Welcome to my tamabox!',
                'created_at' => '2026-04-22 12:00:00',
                'updated_at' => '2026-04-22 12:00:00',
            ],
        ];
        parent::init();
    }
}
