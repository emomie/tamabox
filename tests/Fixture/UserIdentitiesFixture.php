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
        ];
        parent::init();
    }
}
