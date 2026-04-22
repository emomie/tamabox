<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Message Entity
 *
 * @property string $id
 * @property string $inbox_id
 * @property string $sender_user_id
 * @property string $body
 * @property int $body_length
 * @property bool $is_ssr
 * @property string $ssr_probability_at_send
 * @property string $ssr_seed
 * @property string $sender_provider
 * @property string $sender_handle_snapshot
 * @property string|null $sender_avatar_url_snapshot
 * @property string|null $sender_profile_url_snapshot
 * @property \Cake\I18n\FrozenTime|null $opened_at
 * @property \Cake\I18n\FrozenTime|null $deleted_at
 * @property string|null $deleted_reason
 * @property \Cake\I18n\FrozenTime $created_at
 *
 * @property \App\Model\Entity\Inbox $inbox
 * @property \App\Model\Entity\User $user
 * @property \App\Model\Entity\Report[] $reports
 */
class Message extends Entity
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
        'inbox_id' => true,
        'sender_user_id' => true,
        'body' => true,
        'body_length' => true,
        'is_ssr' => true,
        'ssr_probability_at_send' => true,
        'ssr_seed' => true,
        'sender_provider' => true,
        'sender_handle_snapshot' => true,
        'sender_avatar_url_snapshot' => true,
        'sender_profile_url_snapshot' => true,
        'opened_at' => true,
        'deleted_at' => true,
        'deleted_reason' => true,
        'created_at' => true,
        'inbox' => true,
        'user' => true,
        'reports' => true,
    ];
}
