<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * BlocksController integration tests — Phase 3 contains only the 501 stub assertion
 * for the Phase 4 hand-off (D-35). Phase 4 plan-phase MUST update this test alongside
 * replacing BlocksController::create's body.
 *
 * Phase 2 sticky note 1: $fixtures must be UNTYPED.
 */
class BlocksControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @var array<int, string>
     */
    protected $fixtures = [
        'app.Users',
        'app.UserIdentities',
        'app.Inboxes',
        'app.Messages',
        'app.Blocks',
        'app.Reports',
    ];

    public function testCreateReturns501Stub(): void
    {
        $this->enableCsrfToken();
        $this->post('/block/22222222-2222-2222-2222-222222222222');
        $this->assertResponseCode(501);
        // Body assertion is intentionally minimal — Phase 4 will not return a body string.
        $body = (string)$this->_response->getBody();
        $this->assertStringContainsString('Not Implemented', $body);
    }

    public function testCreateOnlyAllowsPost(): void
    {
        $this->get('/block/22222222-2222-2222-2222-222222222222');
        // CakePHP route restricted to POST → 405 OR 404 (depending on version)
        $code = $this->_response->getStatusCode();
        $this->assertContains($code, [404, 405], "Expected 404/405 for non-POST, got $code");
    }
}
