<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Database\Exception\DatabaseException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * BlocksController — sender-block lifecycle (Phase 4 INBOX-04 / D-01..D-04).
 *
 * Routes (config/routes.php Phase 4):
 *   POST /block/{senderUserId}              → create($senderUserId)
 *   POST /dashboard/blocks/{id}/delete      → delete($id)
 *
 * Auth: required for both. CSRF: middleware automatic.
 */
class BlocksController extends AppController
{
    /**
     * POST /block/{senderUserId} — receiver blocks a sender (D-01..D-03).
     *
     * @param string $senderUserId UUID of the sender user being blocked.
     * @return \Cake\Http\Response|null
     */
    public function create(string $senderUserId): ?Response
    {
        $this->request->allowMethod(['post']);

        $identity = $this->Authentication->getIdentity();
        if ($identity === null) {
            return $this->redirect('/');
        }
        $identifier = $identity->getIdentifier();
        $myId = is_scalar($identifier) ? (string)$identifier : '';
        if ($myId === '') {
            return $this->redirect('/');
        }

        // blocks_no_self CHECK would catch this server-side; defend in app layer first.
        if ($myId === $senderUserId) {
            $this->Flash->error(__('自分自身はブロックできません。'));

            return $this->redirect('/dashboard');
        }

        /** @var \App\Model\Table\BlocksTable $blocksTable */
        $blocksTable = $this->fetchTable('Blocks');
        try {
            $entity = $blocksTable->newEntity([
                'id' => Text::uuid(),
                'blocker_user_id' => $myId,
                'blocked_user_id' => $senderUserId,
            ], ['accessibleFields' => [
                'id' => true,
                'blocker_user_id' => true,
                'blocked_user_id' => true,
            ]]);
            $blocksTable->saveOrFail($entity);
        } catch (DatabaseException $e) {
            // uk_blocks_pair UNIQUE collision — D-03 idempotent silent success.
            // RESEARCH Pattern 1: race-safe re-block on existing pair returns same UX.
        } catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
            $this->Flash->error(__('ブロックに失敗しました。'));

            return $this->redirect('/dashboard');
        }

        $this->Flash->success(__('ユーザーをブロックしました'));

        return $this->redirect('/dashboard');
    }

    /**
     * POST /dashboard/blocks/{id}/delete — receiver removes a block (D-04 解除).
     *
     * Ownership: the blocks row's blocker_user_id MUST equal the current identity
     * (no IDOR — block id alone is not sufficient).
     *
     * @param string $id UUID of the blocks row.
     * @return \Cake\Http\Response|null
     */
    public function delete(string $id): ?Response
    {
        $this->request->allowMethod(['post']);

        $identity = $this->Authentication->getIdentity();
        if ($identity === null) {
            return $this->redirect('/');
        }
        $identifier = $identity->getIdentifier();
        $myId = is_scalar($identifier) ? (string)$identifier : '';
        if ($myId === '') {
            return $this->redirect('/');
        }

        /** @var \App\Model\Table\BlocksTable $blocksTable */
        $blocksTable = $this->fetchTable('Blocks');
        /** @var \App\Model\Entity\Block|null $block */
        $block = $blocksTable->find()
            ->where([$blocksTable->aliasField('id') => $id])
            ->first();
        if ($block === null) {
            throw new NotFoundException(__('ブロック行が見つかりませんでした。'));
        }
        if ((string)$block->blocker_user_id !== $myId) {
            throw new ForbiddenException(__('このブロックを解除する権限がありません。'));
        }

        $blocksTable->deleteOrFail($block);

        $this->Flash->success(__('ブロックを解除しました'));

        return $this->redirect('/dashboard');
    }
}
