<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\OAuth\Bluesky;

use App\Service\OAuth\Bluesky\BlueskyOAuthClient;
use App\Service\OAuth\Bluesky\ClientJwtService;
use App\Service\OAuth\Bluesky\DidResolver;
use App\Service\OAuth\Bluesky\DpopService;
use App\Service\OAuth\KeyManager;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Cake\TestSuite\TestCase;

/**
 * BlueskyOAuthClient unit tests.
 *
 * HTTP traffic is stubbed via Cake\Http\Client::addMockResponse() — the global
 * static mock adapter that intercepts every Client instance. Client::clearMockResponses()
 * is called in both setUp() and tearDown() to prevent cross-test bleed
 * (see STATE.md Phase 2 Plan 02-03 deviation #1 for rationale).
 *
 * @uses \App\Service\OAuth\Bluesky\BlueskyOAuthClient
 */
class BlueskyOAuthClientTest extends TestCase
{
    private KeyManager $km;
    private DpopService $dpop;
    private ClientJwtService $clientJwt;
    private DidResolver $didResolver;

    protected function setUp(): void
    {
        parent::setUp();
        Client::clearMockResponses();
        putenv('OAUTH_KID=test-kid-1');
        $_ENV['OAUTH_KID'] = 'test-kid-1';
        Configure::write('Bluesky', [
            'issuer'         => 'https://bsky.social',
            'par_endpoint'   => 'https://bsky.social/oauth/par',
            'token_endpoint' => 'https://bsky.social/oauth/token',
            'auth_endpoint'  => 'https://bsky.social/oauth/authorize',
            'client_id'      => 'https://tamabox.emomie.com/oauth/client-metadata.json',
            'redirect_uri'   => 'https://tamabox.emomie.com/oauth/callback',
            'client_metadata' => ['scope' => 'atproto transition:generic'],
        ]);

        $this->km = new KeyManager(
            TESTS . 'Fixture' . DS . 'keys' . DS . 'private.key',
            TESTS . 'Fixture' . DS . 'keys' . DS . 'public.key'
        );
        $this->dpop = new DpopService($this->km);
        $this->clientJwt = new ClientJwtService($this->km);
        $this->didResolver = new DidResolver();
    }

    protected function tearDown(): void
    {
        Client::clearMockResponses();
        parent::tearDown();
    }

    private function newClient(?string $initialNonce = null): BlueskyOAuthClient
    {
        return new BlueskyOAuthClient(
            $this->dpop,
            $this->clientJwt,
            $this->didResolver,
            null,
            $initialNonce
        );
    }

    public function testGetProviderKeyReturnsBluesky(): void
    {
        $this->assertSame('bluesky', $this->newClient()->getProviderKey());
    }

    public function testExecuteParReturnsRequestUriOn201(): void
    {
        Client::addMockResponse(
            'POST',
            'https://bsky.social/oauth/par',
            new Response(
                ['HTTP/1.1 201 Created', 'Content-Type: application/json'],
                (string)json_encode(['request_uri' => 'urn:ietf:params:oauth:request_uri:ABC', 'expires_in' => 60])
            )
        );

        $out = $this->newClient()->executeParRequest('challenge-xyz', 'state-abc');
        $this->assertSame('urn:ietf:params:oauth:request_uri:ABC', $out['request_uri']);
        $this->assertSame(60, $out['expires_in']);
    }

    public function testExecuteParRetriesWithNonceOnUseDpopNonce(): void
    {
        // First response: 400 + use_dpop_nonce + DPoP-Nonce header.
        Client::addMockResponse(
            'POST',
            'https://bsky.social/oauth/par',
            new Response(
                ['HTTP/1.1 400 Bad Request', 'Content-Type: application/json', 'DPoP-Nonce: nonce-abc-123'],
                (string)json_encode(['error' => 'use_dpop_nonce'])
            )
        );
        // Second response: 201 success on retry.
        Client::addMockResponse(
            'POST',
            'https://bsky.social/oauth/par',
            new Response(
                ['HTTP/1.1 201 Created', 'Content-Type: application/json'],
                (string)json_encode(['request_uri' => 'urn:ok', 'expires_in' => 60])
            )
        );

        $client = $this->newClient();
        $out = $client->executeParRequest('ch', 'st');
        $this->assertSame('urn:ok', $out['request_uri']);
        $this->assertSame('nonce-abc-123', $client->getLastAsNonce());
    }

    public function testExecuteParThrowsWhenNon201AndNotNonceError(): void
    {
        Client::addMockResponse(
            'POST',
            'https://bsky.social/oauth/par',
            new Response(
                ['HTTP/1.1 400 Bad Request', 'Content-Type: application/json'],
                (string)json_encode(['error' => 'invalid_client'])
            )
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PAR');
        $this->newClient()->executeParRequest('ch', 'st');
    }

    public function testExchangeCodeForTokenHappyPath(): void
    {
        Client::addMockResponse(
            'POST',
            'https://bsky.social/oauth/token',
            new Response(
                ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
                (string)json_encode([
                    'access_token'  => 'at_test_123',
                    'refresh_token' => 'rt_test_456',
                    'token_type'    => 'DPoP',
                    'expires_in'    => 3600,
                    'sub'           => 'did:plc:abcdefghij234567klmnopqr',
                ])
            )
        );

        $out = $this->newClient()->exchangeCodeForToken('code-abc', 'verifier-xyz');
        $this->assertSame('at_test_123', $out['access_token']);
        $this->assertSame('rt_test_456', $out['refresh_token']);
        $this->assertSame('did:plc:abcdefghij234567klmnopqr', $out['sub']);
        $this->assertSame(3600, $out['expires_in']);
        $this->assertSame('DPoP', $out['token_type']);
    }

    public function testExchangeCodeForTokenThrowsOnMissingField(): void
    {
        Client::addMockResponse(
            'POST',
            'https://bsky.social/oauth/token',
            new Response(
                ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
                (string)json_encode([
                    'access_token' => 'x',
                    'token_type'   => 'DPoP',
                    'expires_in'   => 1,
                    'sub'          => 'did:plc:x',
                    // refresh_token missing
                ])
            )
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('refresh_token');
        $this->newClient()->exchangeCodeForToken('c', 'v');
    }

    public function testRefreshTokenHappyPath(): void
    {
        Client::addMockResponse(
            'POST',
            'https://bsky.social/oauth/token',
            new Response(
                ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
                (string)json_encode([
                    'access_token'  => 'at_new',
                    'refresh_token' => 'rt_new',
                    'expires_in'    => 3600,
                ])
            )
        );

        $out = $this->newClient()->refreshToken('old_rt');
        $this->assertSame('at_new', $out['access_token']);
        $this->assertSame('rt_new', $out['refresh_token']);
        $this->assertSame(3600, $out['expires_in']);
    }

    public function testResolveProfileHappyPath(): void
    {
        $did = 'did:plc:abcdefghij234567klmnopqr';
        // DID resolution (plc.directory lookup).
        Client::addMockResponse(
            'GET',
            'https://plc.directory/' . $did,
            new Response(
                ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
                (string)json_encode([
                    'service' => [
                        ['type' => 'AtprotoPersonalDataServer', 'serviceEndpoint' => 'https://bsky.social'],
                    ],
                ])
            )
        );
        // getProfile.
        Client::addMockResponse(
            'GET',
            'https://bsky.social/xrpc/app.bsky.actor.getProfile?actor=' . rawurlencode($did),
            new Response(
                ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
                (string)json_encode([
                    'handle'      => 'alice.bsky.social',
                    'avatar'      => 'https://cdn/av.png',
                    'displayName' => 'Alice',
                ])
            )
        );

        $out = $this->newClient()->resolveProfile($did, 'at_xyz');
        $this->assertSame('alice.bsky.social', $out['handle']);
        $this->assertSame('https://cdn/av.png', $out['avatar']);
        $this->assertSame('Alice', $out['displayName']);
        $this->assertSame('https://bsky.app/profile/alice.bsky.social', $out['profile_url']);
    }

    public function testResolveProfileAvatarOptional(): void
    {
        $did = 'did:plc:abcdefghij234567klmnopqr';
        Client::addMockResponse(
            'GET',
            'https://plc.directory/' . $did,
            new Response(
                ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
                (string)json_encode([
                    'service' => [
                        ['type' => 'AtprotoPersonalDataServer', 'serviceEndpoint' => 'https://bsky.social'],
                    ],
                ])
            )
        );
        Client::addMockResponse(
            'GET',
            'https://bsky.social/xrpc/app.bsky.actor.getProfile?actor=' . rawurlencode($did),
            new Response(
                ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
                (string)json_encode(['handle' => 'bob.bsky.social'])
            )
        );

        $out = $this->newClient()->resolveProfile($did, 'at');
        $this->assertSame('bob.bsky.social', $out['handle']);
        $this->assertNull($out['avatar']);
        $this->assertNull($out['displayName']);
    }

    public function testResolveProfileMissingHandleThrows(): void
    {
        $did = 'did:plc:abcdefghij234567klmnopqr';
        Client::addMockResponse(
            'GET',
            'https://plc.directory/' . $did,
            new Response(
                ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
                (string)json_encode([
                    'service' => [
                        ['type' => 'AtprotoPersonalDataServer', 'serviceEndpoint' => 'https://bsky.social'],
                    ],
                ])
            )
        );
        Client::addMockResponse(
            'GET',
            'https://bsky.social/xrpc/app.bsky.actor.getProfile?actor=' . rawurlencode($did),
            new Response(
                ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
                (string)json_encode(['avatar' => 'x'])
            )
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('handle');
        $this->newClient()->resolveProfile($did, 'at');
    }

    public function testResolveProfile401Throws(): void
    {
        $did = 'did:plc:abcdefghij234567klmnopqr';
        Client::addMockResponse(
            'GET',
            'https://plc.directory/' . $did,
            new Response(
                ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
                (string)json_encode([
                    'service' => [
                        ['type' => 'AtprotoPersonalDataServer', 'serviceEndpoint' => 'https://bsky.social'],
                    ],
                ])
            )
        );
        Client::addMockResponse(
            'GET',
            'https://bsky.social/xrpc/app.bsky.actor.getProfile?actor=' . rawurlencode($did),
            new Response(['HTTP/1.1 401 Unauthorized'], '{"error":"invalid_token"}')
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Profile fetch failed');
        $this->newClient()->resolveProfile($did, 'at_bad');
    }

    public function testErrorMessagesContainNoSecrets(): void
    {
        // T-02-04-08: a token-exchange failure must not leak debug payload contents in the
        // exception message. The exception message comes from assertStatus(), which only
        // references the HTTP phase label (e.g. 'TOKEN_EXCHANGE'), never the response body.
        Client::addMockResponse(
            'POST',
            'https://bsky.social/oauth/token',
            new Response(
                ['HTTP/1.1 500 Internal Server Error', 'Content-Type: application/json'],
                '{"error":"server_error","debug_access_token":"should-not-leak-this"}'
            )
        );

        try {
            $this->newClient()->exchangeCodeForToken('c', 'v');
            $this->fail('Expected RuntimeException from 500 response.');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('should-not-leak-this', $e->getMessage());
            $this->assertStringContainsString('TOKEN_EXCHANGE', $e->getMessage());
        }
    }

    public function testInitialNonceIsUsedAndUpdatedAfterRequest(): void
    {
        // Construct with an initial nonce (from prior request's DPoP-Nonce header).
        // PAR succeeds on first try (nonce already cached), new DPoP-Nonce in response updates state.
        Client::addMockResponse(
            'POST',
            'https://bsky.social/oauth/par',
            new Response(
                ['HTTP/1.1 201 Created', 'Content-Type: application/json', 'DPoP-Nonce: nonce-fresh'],
                (string)json_encode(['request_uri' => 'urn:ok', 'expires_in' => 60])
            )
        );

        $client = $this->newClient('nonce-previous');
        $this->assertSame('nonce-previous', $client->getLastAsNonce());

        $client->executeParRequest('ch', 'st');
        $this->assertSame('nonce-fresh', $client->getLastAsNonce());
    }
}
