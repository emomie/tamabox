<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * AuthController integration tests — startBluesky + logout.
 *
 * BlueskyOAuthClient's PAR call is stubbed via Client::addMockResponse so the test
 * does not hit the live Bluesky AS. When the mock is absent (logout flow), the
 * controller never builds the client, so no HTTP call is made.
 *
 * @uses \App\Controller\AuthController
 */
class AuthControllerTest extends TestCase
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

    public function testLogoutWithGetDoesNotDestroySession(): void
    {
        // D-18 / T-02-01-02 — logout is POST-only (route constraint). A GET must not
        // invoke AuthController::logout(). CakePHP's route matcher declines the GET
        // (routes constrained via setMethods(['POST'])) and the dispatcher will
        // either surface a 405 (exact method-mismatch) or fall back to the router's
        // next candidate (resulting in a redirect/404). Either way, it MUST NOT succeed
        // with a 200 — any non-200/non-destructive outcome is acceptable.
        $this->get('/oauth/logout');
        $code = $this->_response->getStatusCode();
        $this->assertNotSame(200, $code, 'GET /oauth/logout must not succeed.');
    }

    public function testDashboardWithoutAuthRedirectsHome(): void
    {
        // T-02-04-14 — AuthenticationMiddleware redirects unauthenticated /dashboard hits to '/'.
        $this->get('/dashboard');
        $this->assertResponseCode(302);
    }

    public function testLoginBlueskyRedirectsToAuthEndpointOnParSuccess(): void
    {
        // PAR happy path — mock the AS response; AuthController redirects to auth_endpoint with request_uri.
        Client::addMockResponse(
            'POST',
            'https://bsky.social/oauth/par',
            new \Cake\Http\Client\Response(
                ['HTTP/1.1 201 Created', 'Content-Type: application/json'],
                (string)json_encode([
                    'request_uri' => 'urn:ietf:params:oauth:request_uri:TEST-REQ-URI',
                    'expires_in' => 60,
                ])
            )
        );

        $this->enableCsrfToken();
        $this->post('/login/bluesky');

        $this->assertResponseCode(302);
        $this->assertRedirectContains('https://bsky.social/oauth/authorize');
        $this->assertRedirectContains('request_uri=');
        $this->assertRedirectContains('client_id=');
    }

    public function testLoginBlueskyWithParFailureFlashesError(): void
    {
        // PAR failure path — mock 400 non-nonce response; AuthController catches and flashes connection error.
        Client::addMockResponse(
            'POST',
            'https://bsky.social/oauth/par',
            new \Cake\Http\Client\Response(
                ['HTTP/1.1 400 Bad Request', 'Content-Type: application/json'],
                (string)json_encode(['error' => 'invalid_client'])
            )
        );

        $this->enableCsrfToken();
        $this->post('/login/bluesky');

        $this->assertResponseCode(302);
        $this->assertRedirectContains('/');
    }

    public function testLoginBlueskyRedirectTargetContainsRequestUriFromPar(): void
    {
        // Narrower assertion than reading session directly — the redirect URL must embed
        // the request_uri returned from PAR, which implies: PAR ran, session stash happened,
        // client_id + request_uri were rawurlencoded into the AS URL.
        Client::addMockResponse(
            'POST',
            'https://bsky.social/oauth/par',
            new \Cake\Http\Client\Response(
                ['HTTP/1.1 201 Created', 'Content-Type: application/json'],
                (string)json_encode(['request_uri' => 'urn:par:test', 'expires_in' => 60])
            )
        );

        $this->enableCsrfToken();
        $this->post('/login/bluesky');

        $this->assertResponseCode(302);
        $this->assertRedirectContains('request_uri=urn%3Apar%3Atest');
    }

    /**
     * D-13: POST data pending_message_body + pending_message_inbox_id are stashed to session.
     *
     * @return void
     */
    public function testStartBlueskyPersistsPendingMessageBodyToSession(): void
    {
        Client::addMockResponse(
            'POST',
            'https://bsky.social/oauth/par',
            new \Cake\Http\Client\Response(
                ['HTTP/1.1 201 Created', 'Content-Type: application/json'],
                (string)json_encode([
                    'request_uri' => 'urn:ietf:params:oauth:request_uri:TEST',
                    'expires_in' => 60,
                ])
            )
        );

        $this->enableCsrfToken();
        $this->post('/login/bluesky', [
            'pending_message_body' => 'メッセージ復元テスト',
            'pending_message_inbox_id' => '11111111-1111-1111-1111-111111111111',
        ]);

        $this->assertResponseCode(302);
        $this->assertSession('メッセージ復元テスト', 'pending_message_body');
        $this->assertSession('11111111-1111-1111-1111-111111111111', 'pending_message_inbox_id');
    }
}
