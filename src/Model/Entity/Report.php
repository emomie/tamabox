<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Report Entity
 *
 * @property string $id
 * @property string $message_id
 * @property string|null $reporter_user_id
 * @property string $reason
 * @property string|null $detail
 * @property string $status
 * @property \Cake\I18n\FrozenTime|null $reviewed_at
 * @property string|null $reviewed_by_admin
 * @property string|null $resolution_note
 * @property \Cake\I18n\FrozenTime $created_at
 *
 * @property \App\Model\Entity\Message $message
 * @property \App\Model\Entity\User $user
 */
class Report extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected $_accessible = [
        'message_id' => true,
        'reporter_user_id' => true,
        'reason' => true,
        'detail' => true,
        'status' => true,
        'reviewed_at' => true,
        'reviewed_by_admin' => true,
        'resolution_note' => true,
        'created_at' => true,
        'message' => true,
        'user' => true,
    ];
}
