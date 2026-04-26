<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

/**
 * Users — authenticated landing pages (dashboard).
 *
 * Phase 3 expanded: receive list (paginated 20/page) + settings sidebar + slug header
 * + collision-suffix flash (consume-once from session D-06).
 *
 * AuthenticationMiddleware redirects unauthenticated hits to '/' via
 * Application::getAuthenticationService's unauthenticatedRedirect setting.
 *
 * NOTE (Rule 1 deviation): CakePHP Controller::paginate() internally catches
 * Cake\Datasource\Paging\Exception\PageOutOfBoundsException and re-throws it as
 * Cake\Http\Exception\NotFoundException. The plan sticky note said to catch
 * PageOutOfBoundsException directly, but the parent class transformation means we
 * must catch NotFoundException here instead. Verified against
 * vendor/cakephp/cakephp/src/Controller/Controller.php line ~1005.
 */
class UsersController extends AppController
{
    /**
     * Paginator configuration.
     *
     * @var array<string, mixed>
     */
    public $paginate = [
        'limit' => 20,
        'order' => ['Messages.created_at' => 'DESC'],
    ];

    /**
     * GET /dashboard — receive list + settings + slug header.
     *
     * @return \Cake\Http\Response|null Null renders the dashboard template.
     */
    public function dashboard(): ?Response
    {
        $identity = $this->Authentication->getIdentity();
        if ($identity === null) {
            // AuthenticationMiddleware usually catches this, but defend in depth.
            return $this->redirect('/');
        }

        $identifier = $identity->getIdentifier();
        $userId = is_scalar($identifier) ? (string)$identifier : '';
        if ($userId === '') {
            return $this->redirect('/');
        }

        /** @var \App\Model\Entity\User $user */
        $user = $this->fetchTable('Users')
            ->find()
            ->where(['Users.id' => $userId])
            ->contain(['UserIdentities'])
            ->firstOrFail();

        /** @var \App\Model\Table\InboxesTable $inboxesTable */
        $inboxesTable = $this->fetchTable('Inboxes');
        /** @var \App\Model\Entity\Inbox|null $inbox */
        $inbox = $inboxesTable->find()
            ->where([$inboxesTable->aliasField('user_id') => $userId])
            ->first();
        if ($inbox === null) {
            // Defensive: should never happen post-Phase 3 (inbox created at first login).
            $this->Flash->error(__('受信箱が見つかりませんでした。再度ログインしてください。'));

            return $this->redirect('/');
        }

        // === Paginate messages ===
        /** @var \App\Model\Table\MessagesTable $messagesTable */
        $messagesTable = $this->fetchTable('Messages');
        try {
            $messages = $this->paginate(
                $messagesTable
                    ->find()
                    ->where(['Messages.inbox_id' => $inbox->id])
                    ->order(['Messages.created_at' => 'DESC'])
            );
        } catch (NotFoundException $e) {
            // Rule 1 deviation: Controller::paginate() catches PageOutOfBoundsException
            // internally and re-throws as NotFoundException. We catch NotFoundException here
            // to surface the UI-SPEC §5 fallback copy ('そのページはありません。').
            $this->set([
                'user' => $user,
                'inbox' => $inbox,
                'messages' => [],
                'pageOutOfRange' => true,
                'collisionFlash' => null,
            ]);

            return null;
        }

        // === Consume slug-collision flash if present (D-06) ===
        $session = $this->request->getSession();
        $collisionRaw = $session->read('Flash.slug_collision_suffix');
        $collisionFlash = null;
        if (is_array($collisionRaw) && isset($collisionRaw['slug'], $collisionRaw['base'])) {
            $collisionFlash = [
                'slug' => (string)$collisionRaw['slug'],
                'base' => (string)$collisionRaw['base'],
            ];
            $session->delete('Flash.slug_collision_suffix');
        }

        $this->set([
            'user' => $user,
            'inbox' => $inbox,
            'messages' => $messages,
            'pageOutOfRange' => false,
            'collisionFlash' => $collisionFlash,
        ]);

        return null;
    }
}
