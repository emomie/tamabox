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
                'user_id' => 'ea0fd149-435c-4aed-bd30-12abfcfd27fd',
                'provider' => 'Lorem ipsum dolor sit amet',
                'provider_account_id' => 'Lorem ipsum dolor sit amet',
                'handle_cached' => 'Lorem ipsum dolor sit amet',
                'avatar_url_cached' => 'Lorem ipsum dolor sit amet',
                'profile_url_cached' => 'Lorem ipsum dolor sit amet',
                'access_token_enc' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'refresh_token_enc' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'token_expires_at' => '',
                'last_synced_at' => '',
                'is_primary' => 1,
                'created_at' => '',
                'updated_at' => 1776867777,
            ],
        ];
        parent::init();
    }
}
