<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * BlocksController — sender-block lifecycle. Phase 4 (INBOX-04 / INBOX-05).
 *
 * Phase 3 status: this controller exists ONLY to provide a 501 stub for
 * `POST /block/<senderUserId>` referenced by the SSR-reveal sender card
 * (D-35 hand-off contract). Phase 4 plan-phase replaces this body with the
 * real INSERT-into-blocks logic.
 *
 * The corresponding integration test (BlocksControllerTest::testCreateReturns501Stub)
 * locks the contract: when Phase 4 ships, that test must be UPDATED to assert
 * the real behavior (302 redirect + Flash). If the 501 test still passes after
 * Phase 4 deploy, the implementation never happened — the same protocol Plan
 * 02-04 used to replace OauthController::callback's 501 stub.
 */
class BlocksController extends AppController
{
    /**
     * Phase 3 ONLY: `create` is a 501 stub (D-35 hand-off contract).
     * AuthenticationMiddleware would otherwise 302-redirect before the action
     * runs. Allowing unauthenticated is safe because no DB writes occur.
     * Phase 4 MUST remove 'create' from this list when replacing the stub body.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->allowUnauthenticated(['create']);
    }

    /**
     * POST /block/{senderUserId} — Phase 4 stub.
     *
     * @param string $senderUserId UUID of the sender user being blocked.
     * @return \Cake\Http\Response
     */
    public function create(string $senderUserId): Response
    {
        $this->request->allowMethod(['post']);

        return $this->response
            ->withStatus(501)
            ->withStringBody('Not Implemented');
    }
}
