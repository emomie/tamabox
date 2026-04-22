<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * ReportsFixture
 */
class ReportsFixture extends TestFixture
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
                'id' => '4995bda6-1c41-4ece-950a-091115adc818',
                'message_id' => '644a57ef-ca39-42b5-aa7c-c7774ec39f80',
                'reporter_user_id' => '11111111-1111-1111-1111-111111111111',
                'reason' => 'harassment',
                'detail' => 'Report detail text for fixture.',
                'status' => 'pending',
                'reviewed_at' => null,
                'reviewed_by_admin' => null,
                'resolution_note' => null,
                'created_at' => '2026-04-22 12:00:00',
            ],
        ];
        parent::init();
    }
}
