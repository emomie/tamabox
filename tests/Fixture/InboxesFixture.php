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
            // alice — receiver with default 10% probability.
            [
                'id' => '11111111-1111-1111-1111-111111111111',
                'user_id' => '11111111-1111-1111-1111-111111111111',
                'slug' => 'alice',
                'slug_previous' => null,
                'ssr_probability' => '0.100',
                'is_accepting' => 1,
                'welcome_message' => null,
                'created_at' => '2026-04-22 12:00:00.000000',
                'updated_at' => '2026-04-22 12:00:00.000000',
            ],
            // bob — receiver who renamed handle (slug_previous='bob' supports D-04 fallback).
            [
                'id' => '22222222-2222-2222-2222-222222222222',
                'user_id' => '22222222-2222-2222-2222-222222222222',
                'slug' => 'bob-2',
                'slug_previous' => 'bob',
                'ssr_probability' => '0.500',
                'is_accepting' => 1,
                'welcome_message' => 'メッセージありがとう!',
                'created_at' => '2026-04-22 12:00:00.000000',
                'updated_at' => '2026-04-23 12:00:00.000000',
            ],
            // charlie — receiver with is_accepting=0 for "受け付けていない" UI.
            [
                'id' => '33333333-3333-3333-3333-333333333333',
                'user_id' => '33333333-3333-3333-3333-333333333333',
                'slug' => 'charlie',
                'slug_previous' => null,
                'ssr_probability' => '1.000',
                'is_accepting' => 0,
                'welcome_message' => null,
                'created_at' => '2026-04-22 12:00:00.000000',
                'updated_at' => '2026-04-22 12:00:00.000000',
            ],
        ];
        parent::init();
    }
}
