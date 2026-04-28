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
            // Unread message in alice's inbox (no opened_at).
            [
                'id' => 'aaaa1111-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'inbox_id' => '11111111-1111-1111-1111-111111111111',
                'sender_user_id' => '22222222-2222-2222-2222-222222222222',
                'body' => '未開封テストメッセージ',
                'body_length' => 11,
                'is_ssr' => 1,
                'ssr_probability_at_send' => '0.100',
                'ssr_seed' => str_repeat('a', 64),
                'sender_provider' => 'bluesky',
                'sender_handle_snapshot' => 'bob-2.bsky.social',
                'sender_avatar_url_snapshot' => 'https://example.com/bob.jpg',
                'sender_profile_url_snapshot' => 'https://bsky.app/profile/bob-2.bsky.social',
                'opened_at' => null,
                'deleted_at' => null,
                'deleted_reason' => null,
                'created_at' => '2026-04-23 10:00:00.000000',
            ],
            // Opened SSR-hit message in alice's inbox (opened_at set).
            [
                'id' => 'aaaa2222-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'inbox_id' => '11111111-1111-1111-1111-111111111111',
                'sender_user_id' => '22222222-2222-2222-2222-222222222222',
                'body' => '開封済 SSR hit メッセージ',
                'body_length' => 17,
                'is_ssr' => 1,
                'ssr_probability_at_send' => '0.500',
                'ssr_seed' => str_repeat('b', 64),
                'sender_provider' => 'bluesky',
                'sender_handle_snapshot' => 'bob-2.bsky.social',
                'sender_avatar_url_snapshot' => 'https://example.com/bob.jpg',
                'sender_profile_url_snapshot' => 'https://bsky.app/profile/bob-2.bsky.social',
                'opened_at' => '2026-04-23 11:00:00.000000',
                'deleted_at' => null,
                'deleted_reason' => null,
                'created_at' => '2026-04-23 10:30:00.000000',
            ],
            // Opened SSR-miss message in alice's inbox.
            [
                'id' => 'aaaa3333-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'inbox_id' => '11111111-1111-1111-1111-111111111111',
                'sender_user_id' => '22222222-2222-2222-2222-222222222222',
                'body' => '開封済 SSR miss メッセージ',
                'body_length' => 18,
                'is_ssr' => 0,
                'ssr_probability_at_send' => '0.100',
                'ssr_seed' => str_repeat('c', 64),
                'sender_provider' => 'bluesky',
                'sender_handle_snapshot' => 'bob-2.bsky.social',
                'sender_avatar_url_snapshot' => 'https://example.com/bob.jpg',
                'sender_profile_url_snapshot' => 'https://bsky.app/profile/bob-2.bsky.social',
                'opened_at' => '2026-04-23 11:30:00.000000',
                'deleted_at' => null,
                'deleted_reason' => null,
                'created_at' => '2026-04-23 10:45:00.000000',
            ],
            // Phase 4 Plan 04-01: soft-deleted message (filter sentinel).
            [
                'id' => 'aaaa4444-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'inbox_id' => '11111111-1111-1111-1111-111111111111',
                'sender_user_id' => '22222222-2222-2222-2222-222222222222',
                'body' => '削除済テストメッセージ',
                'body_length' => 11,
                'is_ssr' => 0,
                'ssr_probability_at_send' => '0.100',
                'ssr_seed' => str_repeat('d', 64),
                'sender_provider' => 'bluesky',
                'sender_handle_snapshot' => 'bob-2.bsky.social',
                'sender_avatar_url_snapshot' => 'https://example.com/bob.jpg',
                'sender_profile_url_snapshot' => 'https://bsky.app/profile/bob-2.bsky.social',
                'opened_at' => '2026-04-23 12:00:00.000000',
                'deleted_at' => '2026-04-23 12:30:00.000000',
                'deleted_reason' => 'user_deleted',
                'created_at' => '2026-04-23 11:00:00.000000',
            ],
        ];
        parent::init();
    }
}
