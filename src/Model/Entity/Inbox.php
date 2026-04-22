<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Inbox Entity
 *
 * @property string $id
 * @property string $user_id
 * @property string $slug
 * @property string $ssr_probability
 * @property bool $is_accepting
 * @property string|null $welcome_message
 * @property \Cake\I18n\FrozenTime $created_at
 * @property \Cake\I18n\FrozenTime $updated_at
 *
 * @property \App\Model\Entity\User $user
 * @property \App\Model\Entity\Message[] $messages
 */
class Inbox extends Entity
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
        'user_id' => true,
        'slug' => true,
        'ssr_probability' => true,
        'is_accepting' => true,
        'welcome_message' => true,
        'created_at' => true,
        'updated_at' => true,
        'user' => true,
        'messages' => true,
    ];
}
