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
                'user_id' => 'd3c853ba-72f1-453e-ad7b-89716e9a7c96',
                'slug' => 'Lorem ipsum dolor sit amet',
                'ssr_probability' => 1.5,
                'is_accepting' => 1,
                'welcome_message' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'created_at' => '',
                'updated_at' => 1776867778,
            ],
        ];
        parent::init();
    }
}
