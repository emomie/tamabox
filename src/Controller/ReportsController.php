<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Database\Exception\DatabaseException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Utility\Text;
use PDOException;

/**
 * ReportsController — receiver-side abuse reports (Phase 4 MOD-01 / MOD-02 / D-09..D-13).
 *
 * Routes (config/routes.php Phase 4):
 *   GET|POST /report/{messageId} → create($messageId)
 *
 * Auth: required (default — no allowUnauthenticated calls).
 * CSRF: middleware automatic on POST.
 *
 * MOD-02: NO AI/NG-word filter on submit. Server-side action is INSERT-only.
 * D-12: uk_reports_reporter_message UNIQUE 制約で重複通報を弾く。DatabaseException catch
 * で race-safe に冪等吸収する。
 */
class ReportsController extends AppController
{
    /**
     * GET → render form (or redirect with flash if already reported).
     * POST → validate + INSERT reports row.
     *
     * @param string $messageId UUID of the message being reported.
     * @return \Cake\Http\Response|null
     */
    public function create(string $messageId): ?Response
    {
        $identity = $this->Authentication->getIdentity();
        if ($identity === null) {
            return $this->redirect('/');
        }
        $identifier = $identity->getIdentifier();
        $myId = is_scalar($identifier) ? (string)$identifier : '';
        if ($myId === '') {
            return $this->redirect('/');
        }

        /** @var \App\Model\Table\MessagesTable $messagesTable */
        $messagesTable = $this->fetchTable('Messages');
        /** @var \App\Model\Entity\Message|null $msg */
        $msg = $messagesTable->find()
            ->where([$messagesTable->aliasField('id') => $messageId])
            ->contain(['Inboxes'])
            ->first();
        if ($msg === null || $msg->inbox === null || (string)$msg->inbox->user_id !== $myId) {
            // Not found OR not own inbox's message → 404 (do not leak existence).
            throw new NotFoundException(__('通報できないメッセージです。'));
        }

        /** @var \App\Model\Table\ReportsTable $reportsTable */
        $reportsTable = $this->fetchTable('Reports');

        if ($this->request->is('get')) {
            // Pre-check: already reported? early-redirect with flash (D-16: 取り消し不可)
            $alreadyReported = $reportsTable->exists([
                'reporter_user_id' => $myId,
                'message_id' => $messageId,
            ]);
            if ($alreadyReported) {
                $this->Flash->error(__('このメッセージは既に通報済みです。'));

                return $this->redirect('/dashboard');
            }
            $this->set('message', $msg);

            return null;
        }

        // POST
        $this->request->allowMethod(['post']);
        $reason = $this->postString('reason');
        $detail = $this->postString('detail');

        $allowedReasons = ['harassment', 'spam', 'illegal', 'other'];
        if (!in_array($reason, $allowedReasons, true)) {
            $this->Flash->error(__('通報理由を選んでください。'));

            return $this->redirect('/report/' . $messageId);
        }
        $detailTrimmed = trim($detail);
        if ($reason === 'other' && $detailTrimmed === '') {
            $this->Flash->error(__('「その他」選択時は詳細の記入が必須です。'));

            return $this->redirect('/report/' . $messageId);
        }
        if (mb_strlen($detail) > 1000) {
            $this->Flash->error(__('詳細は 1000 文字以内で入力してください。'));

            return $this->redirect('/report/' . $messageId);
        }

        try {
            $entity = $reportsTable->newEntity([
                'id' => Text::uuid(),
                'message_id' => $messageId,
                'reporter_user_id' => $myId,
                'reason' => $reason,
                'detail' => $detailTrimmed === '' ? null : $detail,
                'status' => 'pending',
            ], ['accessibleFields' => [
                'id' => true,
                'message_id' => true,
                'reporter_user_id' => true,
                'reason' => true,
                'detail' => true,
                'status' => true,
            ]]);
            $reportsTable->saveOrFail($entity);
        } catch (DatabaseException | PDOException $e) {
            // uk_reports_reporter_message UNIQUE collision (D-12) — race-safe.
            // Catches both Cake\Database\Exception\DatabaseException AND raw PDOException
            // (CakePHP 5 may pass through PDO's SQLSTATE 23000 without wrapping).
            $code = method_exists($e, 'getCode') ? (string)$e->getCode() : '';
            $msg = $e->getMessage();
            $isDup = $code === '23000'
                || str_contains($msg, 'uk_reports_reporter_message')
                || str_contains($msg, 'Duplicate entry');
            if ($isDup) {
                $this->Flash->error(__('このメッセージは既に通報済みです。'));

                return $this->redirect('/dashboard');
            }
            // Other DB error — re-flash as generic failure.
            $this->Flash->error(__('通報の送信に失敗しました。'));

            return $this->redirect('/report/' . $messageId);
        } catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
            $this->Flash->error(__('通報の送信に失敗しました。'));

            return $this->redirect('/report/' . $messageId);
        }

        $this->Flash->success(__('通報を送信しました。確認まで時間がかかる場合があります。'));

        return $this->redirect('/dashboard');
    }

    /**
     * Safely read a POST field as a string. Non-string values become ''.
     *
     * @param string $key POST field name.
     * @return string
     */
    private function postString(string $key): string
    {
        $v = $this->request->getData($key);

        return is_string($v) ? $v : '';
    }
}
