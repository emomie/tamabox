<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * InboxesController — receiver-side inbox settings.
 *
 * Routes (config/routes.php Plan 03-02):
 *   GET|POST /dashboard/settings → settings()
 *
 * GET → 302 to /dashboard (settings is rendered inline in the dashboard view).
 * POST → patches the authenticated user's inbox; 302 redirect /dashboard with Flash.success.
 *
 * Ownership: settings target is determined by `inbox.user_id == identity.user_id`,
 * NEVER by an inbox id in the POST body (no IDOR surface).
 */
class InboxesController extends AppController
{
    /**
     * GET|POST /dashboard/settings.
     *
     * @return \Cake\Http\Response|null
     */
    public function settings(): ?Response
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
            // Settings UI is rendered inline in /dashboard.
            return $this->redirect('/dashboard');
        }

        $this->request->allowMethod(['post']);

        /** @var \App\Model\Table\InboxesTable $inboxesTable */
        $inboxesTable = $this->fetchTable('Inboxes');
        /** @var \App\Model\Entity\Inbox|null $inbox */
        $inbox = $inboxesTable->find()
            ->where([$inboxesTable->aliasField('user_id') => $userId])
            ->first();
        if ($inbox === null) {
            $this->Flash->error(__('受信箱が見つかりませんでした。再度ログインしてください。'));

            return $this->redirect('/');
        }

        // === ssr_probability_pct (D-08): integer 0..100 → DECIMAL(4,3) string '0.000'..'1.000' ===
        $pctRaw = $this->request->getData('ssr_probability_pct');
        $pctStr = is_scalar($pctRaw) ? (string)$pctRaw : '';
        if (!preg_match('/^-?\d+$/', $pctStr)) {
            $this->Flash->error(__('確率は 0〜100 の整数で入力してください。'));

            return $this->redirect('/dashboard');
        }
        $pct = (int)$pctStr;
        if ($pct < 0 || $pct > 100) {
            $this->Flash->error(__('確率は 0〜100 の整数で入力してください。'));

            return $this->redirect('/dashboard');
        }
        // Build DECIMAL string. e.g. 10 → '0.100', 100 → '1.000', 0 → '0.000'.
        $probabilityDecimal = number_format($pct / 100, 3, '.', '');

        // === welcome_message (D-28, ≤1000 chars) — null when empty ===
        $welcomeRaw = $this->request->getData('welcome_message');
        $welcome = is_string($welcomeRaw) && trim($welcomeRaw) !== '' ? $welcomeRaw : null;

        // === is_accepting (D-28, checkbox) ===
        $isAcceptingRaw = $this->request->getData('is_accepting');
        $isAccepting = $isAcceptingRaw !== null && $isAcceptingRaw !== '' && $isAcceptingRaw !== '0' && $isAcceptingRaw !== false;

        $patched = $inboxesTable->patchEntity($inbox, [
            'ssr_probability' => $probabilityDecimal,
            'welcome_message' => $welcome,
            'is_accepting' => $isAccepting,
        ], ['accessibleFields' => [
            'ssr_probability' => true,
            'welcome_message' => true,
            'is_accepting' => true,
        ]]);

        if ($patched->getErrors() !== []) {
            $errors = $patched->getErrors();
            $first = '';
            foreach ($errors as $messages) {
                foreach ($messages as $msg) {
                    $first = (string)$msg;
                    break 2;
                }
            }
            $this->Flash->error($first !== '' ? $first : __('保存に失敗しました。'));

            return $this->redirect('/dashboard');
        }

        $inboxesTable->saveOrFail($patched);

        $this->Flash->success(__('保存しました'));

        return $this->redirect('/dashboard');
    }
}
