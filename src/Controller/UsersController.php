<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * Users — authenticated landing pages.
 *
 * Phase 2 scope: /dashboard only (placeholder until Phase 3 wires inbox management).
 * AuthenticationMiddleware redirects unauthenticated hits to '/' via
 * Application::getAuthenticationService's unauthenticatedRedirect setting.
 */
class UsersController extends AppController
{
    /**
     * GET /dashboard — welcome + Bluesky handle + Phase 3 placeholder.
     *
     * @return \Cake\Http\Response|null Null renders the dashboard; Response when
     *   defense-in-depth identity-missing redirect triggers.
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

        $this->set('user', $user);

        return null;
    }
}
