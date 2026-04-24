<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * OauthController::callback integration tests (Plan 02-04).
 *
 * Covers the error paths that do not require the live Bluesky AS:
 *   (a) ?error=... user cancelled
 *   (b) state missing entirely
 *   (b') state mismatch between session and query
 *   — iss mismatch when iss is present but wrong
 *   — the 501 stub from Plan 02-03 has been replaced (302 now, never 501)
 *
 * Happy-path (token exchange + UPSERT + setIdentity + /dashboard redirect) is
 * covered by BlueskyOAuthClient unit tests and the DB-layer upsert unit tests;
 * full integration requires live AS and is out of scope.
 *
 * @uses \App\Controller\OauthController
 */
class OauthControllerCallbackTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        Client::clearMockResponses();
        $this->enableRetainFlashMessages();
        putenv('OAUTH_KID=test-kid-1');
        $_ENV['OAUTH_KID'] = 'test-kid-1';
        $hexKey = str_repeat('ab', 32);
        putenv('TOKEN_ENC_KEY=' . $hexKey);
        $_ENV['TOKEN_ENC_KEY'] = $hexKey;
        Configure::write('Bluesky.private_key_path', TESTS . 'Fixture' . DS . 'keys' . DS . 'private.key');
        Configure::write('Bluesky.public_key_path', TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key');
    }

    protected function tearDown(): void
    {
        Client::clearMockResponses();
        parent::tearDown();
    }

    public function testCallbackWithErrorParamFlashesCancelAndRedirects(): void
    {
        // Error path (a): user cancelled on Bluesky's consent screen.
        $this->get('/oauth/callback?error=access_denied&state=whatever');
        $this->assertResponseCode(302);
        $this->assertRedirectContains('/');
        $this->assertFlashMessageMatches('/ログインをキャンセル/');
    }

    public function testCallbackWithoutStateFlashesMismatch(): void
    {
        // Error path (b): state is absent from session AND query.
        $this->get('/oauth/callback?code=x&state=missing_state');
        $this->assertResponseCode(302);
        $this->assertRedirectContains('/');
        $this->assertFlashMessageMatches('/STATE_MISMATCH/');
    }

    public function testCallbackWithStateMismatchFlashesError(): void
    {
        // Prime session with a different state than the query param.
        $this->session([
            'Oauth' => ['state' => 'real_state_abc', 'pkce_verifier' => 'verifier'],
        ]);
        $this->get('/oauth/callback?code=x&state=WRONG');
        $this->assertResponseCode(302);
        $this->assertRedirectContains('/');
        $this->assertFlashMessageMatches('/STATE_MISMATCH/');
    }

    public function testCallbackWithIssMismatchFlashesError(): void
    {
        // Plan threat model T-02-04-13: when iss is present, it must match Bluesky.issuer.
        $this->session([
            'Oauth' => ['state' => 'matching', 'pkce_verifier' => 'verifier'],
        ]);
        $this->get('/oauth/callback?code=x&state=matching&iss=https://evil.example');
        $this->assertResponseCode(302);
        $this->assertRedirectContains('/');
        $this->assertFlashMessageMatches('/ISS_MISMATCH/');
    }

    public function testCallbackNoLongerReturns501Stub(): void
    {
        // Plan 02-03 reserved a 501 stub; Plan 02-04 MUST replace the body. Any query
        // shape should now produce 302 redirects on the error paths, never 501.
        $this->get('/oauth/callback?error=access_denied&state=x');
        $this->assertNotSame(501, $this->_response->getStatusCode());
    }

    /**
     * Helper — partial-regex assertion over the latest Flash message.
     *
     * @param string $pattern PCRE pattern to match against the Flash message.
     * @return void
     */
    private function assertFlashMessageMatches(string $pattern): void
    {
        $session = $this->_requestSession;
        $this->assertNotNull($session, 'Session not available — request not yet dispatched.');
        $flash = $session->read('Flash.flash');
        $this->assertIsArray($flash, 'No Flash.flash array in session.');
        $this->assertNotEmpty($flash, 'No Flash message recorded.');
        $this->assertMatchesRegularExpression($pattern, (string)$flash[0]['message']);
    }
}
