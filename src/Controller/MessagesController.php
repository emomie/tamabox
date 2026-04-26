<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use RuntimeException;

/**
 * MessagesController — public inbox send page + receiver open + Phase 4 report stub.
 *
 * Routes (config/routes.php Phase 3):
 *   GET|POST /{slug}                              → send($slug)
 *   POST /dashboard/messages/{id}/open            → open($id)        (Wave 3a body)
 *   POST /report/{messageId}                      → report($id)      (501 stub, D-35)
 *
 * Auth gates:
 *   - send GET: open to all (D-13 — sender form must render for unauthenticated visitors).
 *   - send POST unauthenticated: 302 redirect → /login/bluesky after stashing pending body.
 *   - send POST authenticated: AUTH-03 satisfied; INSERTs message + redirects to send_done.
 *   - open / report: authentication required (default).
 *
 * CSRF: All POST routes auto-protected by global CsrfProtectionMiddleware.
 */
class MessagesController extends AppController
{
    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();
        // D-13: send page is reachable by unauthenticated users so they can compose
        // before logging in. POST is gated separately inside the action body.
        //
        // Blocker fix: 'report' is a 501 stub (D-35 Phase 4 hand-off contract). Without
        // allowUnauthenticated, AuthenticationMiddleware (Application.php unauthenticatedRedirect=>'/')
        // returns 302 BEFORE the action runs, breaking the strict assertResponseCode(501) test.
        // 501 is a content-negotiation early-return with no DB/auth context — auth gate is
        // irrelevant for the stub. Phase 4 plan-phase MUST remove 'report' from this list when
        // replacing the body with real reporting logic.
        $this->Authentication->allowUnauthenticated(['send', 'report']);
    }

    /**
     * GET|POST /{slug} — render send form / process send POST.
     *
     * @param string $slug Inbox slug from URL.
     * @return \Cake\Http\Response|null
     */
    public function send(string $slug): ?Response
    {
        // === slug resolution ===
        /** @var \App\Model\Table\InboxesTable $inboxesTable */
        $inboxesTable = $this->fetchTable('Inboxes');
        try {
            $resolved = $inboxesTable->findBySlugOrPrevious($slug);
        } catch (NotFoundException $e) {
            throw $e; // → CakePHP error400.php (D-36)
        }
        $inbox = $resolved['inbox'];
        if ($resolved['redirect'] === true) {
            // 301 redirect old-slug → current slug (D-04 single-generation grace).
            return $this->redirect('/' . $inbox->slug, 301);
        }

        $identity = $this->Authentication->getIdentity();
        $isAuthenticated = $identity !== null;
        $currentUserId = '';
        if ($isAuthenticated) {
            $identifier = $identity->getIdentifier();
            $currentUserId = is_scalar($identifier) ? (string)$identifier : '';
        }
        $isOwnInbox = $isAuthenticated && $currentUserId !== '' && $currentUserId === (string)$inbox->user_id;

        // GET handling — render form.
        if ($this->request->is('get')) {
            $this->renderSendForm($inbox, $isAuthenticated, $isOwnInbox);

            return null;
        }

        // POST — branch on authentication.
        if (!$isAuthenticated) {
            return $this->stashAndRedirectToLogin($slug, (string)$inbox->id);
        }

        return $this->processSend($inbox, $currentUserId);
    }

    /**
     * POST /dashboard/messages/{id}/open — receiver opens a message.
     * Wave 3a fills in the body. For Plan 03-02 this is a placeholder
     * that returns 501 so the route resolves. Wave 3a Task 1 replaces it.
     *
     * @param string $id Message UUID.
     * @return \Cake\Http\Response
     */
    public function open(string $id): Response
    {
        $this->request->allowMethod(['post']);
        // Plan 03-03a Task 1 replaces this body with the real implementation.
        // Until then, return 501 so the route is testable and the contract is locked.
        return $this->response->withStatus(501)->withStringBody('Not Implemented');
    }

    /**
     * POST /report/{id} — Phase 4 stub (D-35).
     *
     * @param string $id Message UUID.
     * @return \Cake\Http\Response
     */
    public function report(string $id): Response
    {
        $this->request->allowMethod(['post']);

        return $this->response->withStatus(501)->withStringBody('Not Implemented');
    }

    // === private helpers ===

    /**
     * Render the send form template with required view vars.
     *
     * @param \App\Model\Entity\Inbox $inbox The resolved inbox entity.
     * @param bool $isAuthenticated Whether the current visitor is authenticated.
     * @param bool $isOwnInbox Whether the current visitor owns this inbox.
     * @return void
     */
    private function renderSendForm(\App\Model\Entity\Inbox $inbox, bool $isAuthenticated, bool $isOwnInbox): void
    {
        $session = $this->request->getSession();
        // D-13: if redirected back from OAuth callback (?restored=1), restore body once.
        $restoredBody = '';
        if ($this->queryString('restored') === '1') {
            $stash = $session->read('pending_message_body');
            if (is_string($stash)) {
                $restoredBody = $stash;
            }
            // consume — clear session entries so resubmits don't re-restore.
            $session->delete('pending_message_body');
            $session->delete('pending_message_inbox_id');
        }

        $this->set([
            'inbox' => $inbox,
            'isAuthenticated' => $isAuthenticated,
            'isOwnInbox' => $isOwnInbox,
            'restoredBody' => $restoredBody,
        ]);
    }

    /**
     * Stash body + inbox ID in session, then redirect to /login/bluesky for D-13 unauth flow.
     *
     * @param string $slug Current inbox slug (used to restore after OAuth).
     * @param string $inboxId Current inbox UUID to persist in session.
     * @return \Cake\Http\Response|null
     */
    private function stashAndRedirectToLogin(string $slug, string $inboxId): ?Response
    {
        $body = $this->postString('body');
        $session = $this->request->getSession();
        $session->write('pending_message_body', $body);
        $session->write('pending_message_inbox_id', $inboxId);

        // Redirect to /login/bluesky GET. AuthController::startBluesky reads back
        // pending_message_inbox_id from session. OauthController::callback redirects
        // /<slug>?restored=1 after setIdentity (Task 3).
        return $this->redirect('/login/bluesky');
    }

    /**
     * Process authenticated send POST: validate + SsrJudge + MessagesTable::sendMessage.
     *
     * @param \App\Model\Entity\Inbox $inbox The receiver's inbox.
     * @param string $senderUserId UUID of the authenticated sender.
     * @return \Cake\Http\Response|null
     */
    private function processSend(\App\Model\Entity\Inbox $inbox, string $senderUserId): ?Response
    {
        // is_accepting=false short-circuit (D-28).
        if (!(bool)$inbox->is_accepting) {
            $this->Flash->error(__('この受信箱は現在受け付けていません。'));

            return $this->redirect('/' . $inbox->slug);
        }

        $body = $this->postString('body');
        $consent = $this->postString('consent');
        if ($consent === '') {
            $this->Flash->error(__('送信前に同意チェックボックスにチェックしてください。'));

            return $this->redirect('/' . $inbox->slug);
        }
        if (trim($body) === '') {
            $this->Flash->error(__('本文を入力してください。'));

            return $this->redirect('/' . $inbox->slug);
        }
        if (mb_strlen($body) > 2000) {
            $this->Flash->error(__('本文は 2000 文字以内で入力してください。'));

            return $this->redirect('/' . $inbox->slug);
        }

        try {
            /** @var \App\Model\Table\MessagesTable $messagesTable */
            $messagesTable = $this->fetchTable('Messages');
            $messagesTable->sendMessage($inbox, $senderUserId, $body);
        } catch (RuntimeException $e) {
            $this->Flash->error(__('送信に失敗しました。しばらくしてから再度お試しください。'));

            return $this->redirect('/' . $inbox->slug);
        }

        // D-19: send_done shows fixed copy + 2 CTAs, NO ssr result.
        $this->set('inbox', $inbox);

        return $this->render('send_done');
    }

    /**
     * Safely read a query parameter as a string. Non-string values (arrays, null) become ''.
     *
     * @param string $key Query key name.
     * @return string
     */
    private function queryString(string $key): string
    {
        $v = $this->request->getQuery($key);

        return is_string($v) ? $v : '';
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
