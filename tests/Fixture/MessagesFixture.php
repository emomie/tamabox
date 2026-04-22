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
        $body = 'Hello from Bob!';
        $this->records = [
            [
                'id' => '644a57ef-ca39-42b5-aa7c-c7774ec39f80',
                'inbox_id' => 'ef8c90df-5fd3-4e2c-be65-744fa440be90',
                'sender_user_id' => '22222222-2222-2222-2222-222222222222',
                'body' => $body,
                'body_length' => 15,
                'is_ssr' => 0,
                'ssr_probability_at_send' => 0.100,
                'ssr_seed' => 'a' . str_repeat('0', 63),
                'sender_provider' => 'bluesky',
                'sender_handle_snapshot' => 'bob.bsky.social',
                'sender_avatar_url_snapshot' => null,
                'sender_profile_url_snapshot' => null,
                'opened_at' => null,
                'deleted_at' => null,
                'deleted_reason' => null,
                'created_at' => '2026-04-22 12:00:00',
            ],
        ];
        parent::init();
    }
}
