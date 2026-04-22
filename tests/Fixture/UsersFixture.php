<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * UsersFixture
 */
class UsersFixture extends TestFixture
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
                'id' => '8a37b43b-7522-4006-a7a4-e0d91975ec38',
                'display_name' => 'Lorem ipsum dolor sit amet',
                'created_at' => '',
                'updated_at' => 1776867777,
                'deleted_at' => '',
            ],
        ];
        parent::init();
    }
}
