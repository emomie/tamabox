<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\BadRequestException;
use Cake\Http\Response;
use Cake\I18n\FrozenTime;

/**
 * AccountController — 退会 (account deletion) flow (Phase 4 MOD-03 / D-23..D-27).
 *
 * Routes (config/routes.php Phase 4):
 *   GET|POST /account/delete → delete()
 *
 * GET → render templates/Account/delete.php (consent + checkbox + submit).
 * POST → verify confirm_delete checkbox → UPDATE users.deleted_at → logout → redirect /.
 *
 * D-24 / REV-01: ONLY users.deleted_at is set. inboxes.deleted_at column does NOT exist —
 * slug 404 enforcement happens via InboxesTable::findBySlugOrPrevious WHERE Users.deleted_at IS NULL.
 *
 * MOD-03: messages.sender_*_snapshot rows are PRESERVED (D-26 — receivers see the dead-link
 * snapshot indefinitely; this is the逃げ得-prevention mechanism).
 *
 * Auth: required (default).
 * CSRF: middleware automatic on POST.
 */
class AccountController extends AppController
{
    /**
     * GET|POST /account/delete.
     *
     * @return \Cake\Http\Response|null
     */
    public function delete(): ?Response
    {
        $identity = $this->Authentication->getIdentity();
        if ($identity === null) {
            return $this->redirect('/');
        }
        $identifier = $identity->getIdentifier();
        $userId = is_scalar($identifier) ? (string)$identifier : '';
        if ($userId === '') {
            return $this->redirect('/');
        }

        if ($this->request->is('get')) {
            return null; // render templates/Account/delete.php
        }

        // POST
        $this->request->allowMethod(['post']);
        $confirmed = $this->request->getData('confirm_delete');
        // HTML5 required attribute is the canonical front-end gate; server-side check is
        // defense-in-depth (D-27).
        if ($confirmed === null || $confirmed === '' || $confirmed === false || $confirmed === '0') {
            throw new BadRequestException(__('退会の確認チェックが必要です。'));
        }

        /** @var \App\Model\Table\UsersTable $usersTable */
        $usersTable = $this->fetchTable('Users');
        /** @var \App\Model\Entity\User $user */
        $user = $usersTable->get($userId);
        $patched = $usersTable->patchEntity($user, [
            'deleted_at' => FrozenTime::now(),
        ], ['accessibleFields' => ['deleted_at' => true]]);
        $usersTable->saveOrFail($patched);

        // Phase 2 D-18 logout pattern.
        $this->Authentication->logout();
        $this->Flash->info(__('退会が完了しました。ご利用ありがとうございました。'));

        return $this->redirect('/');
    }
}
