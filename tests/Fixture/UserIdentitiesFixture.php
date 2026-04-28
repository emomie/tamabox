<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * UserIdentitiesFixture
 */
class UserIdentitiesFixture extends TestFixture
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
                'id' => '1c2636e6-2472-48d7-86fa-f19541829b93',
                'user_id' => '11111111-1111-1111-1111-111111111111',
                'provider' => 'bluesky',
                'provider_account_id' => 'did:plc:alice123',
                'handle_cached' => 'alice.bsky.social',
                'avatar_url_cached' => 'https://example.com/alice.jpg',
                'profile_url_cached' => 'https://bsky.app/profile/alice.bsky.social',
                'access_token_enc' => null,
                'refresh_token_enc' => null,
                'token_expires_at' => null,
                'last_synced_at' => null,
                'is_primary' => 1,
                'created_at' => '2026-04-22 12:00:00',
                'updated_at' => '2026-04-22 12:00:00',
            ],
            // Bob — sender who sends messages to Alice's inbox in integration tests.
            [
                'id' => '2c2636e6-2472-48d7-86fa-f19541829b93',
                'user_id' => '22222222-2222-2222-2222-222222222222',
                'provider' => 'bluesky',
                'provider_account_id' => 'did:plc:bob456',
                'handle_cached' => 'bob.bsky.social',
                'avatar_url_cached' => 'https://example.com/bob.jpg',
                'profile_url_cached' => 'https://bsky.app/profile/bob.bsky.social',
                'access_token_enc' => null,
                'refresh_token_enc' => null,
                'token_expires_at' => null,
                'last_synced_at' => null,
                'is_primary' => 1,
                'created_at' => '2026-04-22 12:00:00',
                'updated_at' => '2026-04-22 12:00:00',
            ],
            // Dave — Phase 4 04-01: non-blocked sender for send-flow tests where bob/charlie are blocked.
            [
                'id' => '4d2636e6-2472-48d7-86fa-f19541829b93',
                'user_id' => '44444444-4444-4444-4444-444444444444',
                'provider' => 'bluesky',
                'provider_account_id' => 'did:plc:dave789',
                'handle_cached' => 'dave.bsky.social',
                'avatar_url_cached' => 'https://example.com/dave.jpg',
                'profile_url_cached' => 'https://bsky.app/profile/dave.bsky.social',
                'access_token_enc' => null,
                'refresh_token_enc' => null,
                'token_expires_at' => null,
                'last_synced_at' => null,
                'is_primary' => 1,
                'created_at' => '2026-04-22 12:00:00',
                'updated_at' => '2026-04-22 12:00:00',
            ],
        ];
        parent::init();
    }
}
