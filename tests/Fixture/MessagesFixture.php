<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * MessagesFixture
 */
class MessagesFixture extends TestFixture
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
                'id' => '644a57ef-ca39-42b5-aa7c-c7774ec39f80',
                'inbox_id' => '5a13c00a-7a15-4120-9f84-2961911e8ebb',
                'sender_user_id' => '3d42fbce-c51e-4e01-8414-ba557001d6a3',
                'body' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'body_length' => 1,
                'is_ssr' => 1,
                'ssr_probability_at_send' => 1.5,
                'ssr_seed' => 'Lorem ipsum dolor sit amet',
                'sender_provider' => 'Lorem ipsum dolor sit amet',
                'sender_handle_snapshot' => 'Lorem ipsum dolor sit amet',
                'sender_avatar_url_snapshot' => 'Lorem ipsum dolor sit amet',
                'sender_profile_url_snapshot' => 'Lorem ipsum dolor sit amet',
                'opened_at' => '',
                'deleted_at' => '',
                'deleted_reason' => 'Lorem ipsum dolor sit amet',
                'created_at' => '',
            ],
        ];
        parent::init();
    }
}
