<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * UserIdentity Entity
 *
 * @property string $id
 * @property string $user_id
 * @property string $provider
 * @property string $provider_account_id
 * @property string $handle_cached
 * @property string|null $avatar_url_cached
 * @property string|null $profile_url_cached
 * @property string|null $access_token_enc
 * @property string|null $refresh_token_enc
 * @property \Cake\I18n\FrozenTime|null $token_expires_at
 * @property \Cake\I18n\FrozenTime|null $last_synced_at
 * @property bool $is_primary
 * @property \Cake\I18n\FrozenTime $created_at
 * @property \Cake\I18n\FrozenTime $updated_at
 *
 * @property \App\Model\Entity\User $user
 */
class UserIdentity extends Entity
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
        'provider' => true,
        'provider_account_id' => true,
        'handle_cached' => true,
        'avatar_url_cached' => true,
        'profile_url_cached' => true,
        'access_token_enc' => true,
        'refresh_token_enc' => true,
        'token_expires_at' => true,
        'last_synced_at' => true,
        'is_primary' => true,
        'created_at' => true,
        'updated_at' => true,
        'user' => true,
    ];
}
