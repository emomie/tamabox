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
                    ->where([
                        'Messages.inbox_id' => $inbox->id,
                        'Messages.deleted_at IS' => null,
                    ])
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
                'blocks' => [],
                'reportedSet' => [],
                'unreadCount' => 0,
                'activeTab' => 'inbox',
            ]);

            return null;
        }

        // Phase 4 D-04 — block list for "ブロック中ユーザー" section.
        /** @var \App\Model\Table\BlocksTable $blocksTable */
        $blocksTable = $this->fetchTable('Blocks');
        $blocks = $blocksTable->find()
            ->where(['Blocks.blocker_user_id' => $userId])
            ->contain(['BlockedUsers' => ['UserIdentities']])
            ->order(['Blocks.created_at' => 'DESC'])
            ->toArray();

        // 通報済 badge map (Phase 4 D-16) — keys=message_id, vals=true. Used by 04-02 once /report exists,
        // but the data is fetched here so 04-01 dashboard re-render exposes the structure.
        /** @var \App\Model\Table\ReportsTable $reportsTable */
        $reportsTable = $this->fetchTable('Reports');
        $messageIds = [];
        foreach ($messages as $m) {
            $messageIds[] = (string)$m->id;
        }
        $reportedSet = [];
        if ($messageIds !== []) {
            $rows = $reportsTable->find()
                ->where([
                    'Reports.reporter_user_id' => $userId,
                    'Reports.message_id IN' => $messageIds,
                ])
                ->all();
            foreach ($rows as $r) {
                $reportedSet[(string)$r->message_id] = true;
            }
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

        // Phase 7 NAV-02 — unread count for inbox tab dot + Counts pill.
        $unreadCount = (int)$messagesTable->find()
            ->where([
                'Messages.inbox_id' => $inbox->id,
                'Messages.opened_at IS' => null,
                'Messages.deleted_at IS' => null,
            ])
            ->count();

        $this->set([
            'user' => $user,
            'inbox' => $inbox,
            'messages' => $messages,
            'pageOutOfRange' => false,
            'collisionFlash' => $collisionFlash,
            'blocks' => $blocks,
            'reportedSet' => $reportedSet,
            'unreadCount' => $unreadCount,
            'activeTab' => 'inbox',
        ]);

        return null;
    }

    /**
     * GET /dashboard/discover — 発見タブの Empty-state スタブ (NAV-04).
     *
     * Phase 7 stub. Body has no DB query (DISC-01 deferred to v3); only the
     * cross-tab unread count is computed so the TabBar inbox dot stays
     * accurate while the user is on Discover.
     *
     * @return \Cake\Http\Response|null
     */
    public function discover(): ?Response
    {
        $identity = $this->Authentication->getIdentity();
        if ($identity === null) {
            return $this->redirect('/');
        }
        $identifier = $identity->getIdentifier();
        $userId = is_scalar($identifier) ? (string)$identifier : '';
        $this->set([
            'activeTab' => 'discover',
            'unreadCount' => $this->computeUnreadCount($userId),
        ]);

        return null;
    }

    /**
     * GET /dashboard/notifications — 通知タブの Empty-state スタブ (NAV-05).
     *
     * Phase 7 stub. Body has no DB query (NOTIF-01 deferred to v3); only the
     * cross-tab unread count is computed so the TabBar inbox dot stays
     * accurate while the user is on Notifications.
     *
     * @return \Cake\Http\Response|null
     */
    public function notifications(): ?Response
    {
        $identity = $this->Authentication->getIdentity();
        if ($identity === null) {
            return $this->redirect('/');
        }
        $identifier = $identity->getIdentifier();
        $userId = is_scalar($identifier) ? (string)$identifier : '';
        $this->set([
            'activeTab' => 'notifications',
            'unreadCount' => $this->computeUnreadCount($userId),
        ]);

        return null;
    }

    /**
     * Compute unread message count for the given user, for the TabBar
     * inbox-tab dot. Single COUNT query on (inbox_id, opened_at, deleted_at).
     *
     * Shared by dashboard / discover / notifications / settings so the dot
     * persists across tab navigation (REVIEW L-04 fix).
     *
     * @param string $userId Authenticated user id (empty string → 0).
     * @return int Non-negative unread count; 0 when user has no inbox.
     */
    public function computeUnreadCount(string $userId): int
    {
        if ($userId === '') {
            return 0;
        }
        /** @var \App\Model\Table\InboxesTable $inboxesTable */
        $inboxesTable = $this->fetchTable('Inboxes');
        /** @var \App\Model\Entity\Inbox|null $inbox */
        $inbox = $inboxesTable->find()
            ->where([$inboxesTable->aliasField('user_id') => $userId])
            ->first();
        if ($inbox === null) {
            return 0;
        }
        /** @var \App\Model\Table\MessagesTable $messagesTable */
        $messagesTable = $this->fetchTable('Messages');

        return (int)$messagesTable->find()
            ->where([
                'Messages.inbox_id' => $inbox->id,
                'Messages.opened_at IS' => null,
                'Messages.deleted_at IS' => null,
            ])
            ->count();
    }
}
