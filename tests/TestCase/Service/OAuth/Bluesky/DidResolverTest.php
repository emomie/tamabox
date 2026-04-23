<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\OAuth\Bluesky;

use App\Service\OAuth\Bluesky\DidResolver;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Cake\TestSuite\TestCase;

/**
 * DidResolver unit tests.
 *
 * HTTP traffic is stubbed via Cake\Http\Client::addMockResponse() — a static
 * mock adapter that intercepts any Client instance's requests (the adapter is
 * global, not per-instance). We clear mocks in tearDown() so tests are isolated.
 *
 * @uses \App\Service\OAuth\Bluesky\DidResolver
 */
class DidResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Client::clearMockResponses();
    }

    protected function tearDown(): void
    {
        Client::clearMockResponses();
        parent::tearDown();
    }

    public function testResolveDidToPdsReturnsPdsUrl(): void
    {
        $did = 'did:plc:abcdefghij234567klmnopqr';
        Client::addMockResponse(
            'GET',
            'https://plc.directory/' . $did,
            new Response(
                ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
                (string)json_encode([
                    'id' => $did,
                    'service' => [
                        ['type' => 'AtprotoPersonalDataServer', 'serviceEndpoint' => 'https://bsky.social'],
                    ],
                ])
            )
        );

        $resolver = new DidResolver();
        $this->assertSame('https://bsky.social', $resolver->resolveDidToPds($did));
    }

    public function testResolveStripsTrailingSlash(): void
    {
        $did = 'did:plc:abcdefghij234567klmnopqr';
        Client::addMockResponse(
            'GET',
            'https://plc.directory/' . $did,
            new Response(
                ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
                (string)json_encode([
                    'service' => [
                        ['type' => 'AtprotoPersonalDataServer', 'serviceEndpoint' => 'https://bsky.social/'],
                    ],
                ])
            )
        );

        $this->assertSame('https://bsky.social', (new DidResolver())->resolveDidToPds($did));
    }

    /**
     * @dataProvider invalidDidProvider
     */
    public function testInvalidDidFormatThrows(string $bad): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid DID format');
        (new DidResolver())->resolveDidToPds($bad);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDidProvider(): array
    {
        return [
            'empty' => [''],
            'wrong-scheme' => ['did:evil:abcdefghij234567klmnopqr'],
            'too-short' => ['did:plc:abcdefghij234567klmnopq'],     // 23 chars
            'too-long' => ['did:plc:abcdefghij234567klmnopqrs'],    // 25 chars
            'uppercase' => ['did:plc:ABCDEFGHIJ234567KLMNOPQR'],    // A-Z not allowed
            'with-1' => ['did:plc:1bcdefghij234567klmnopqr'],       // '1' not in base32-hex [a-z2-7]
            'sql-inject' => ["did:plc:' OR 1=1 --                 "],
        ];
    }

    public function testPlcDirectoryNon200Throws(): void
    {
        $did = 'did:plc:abcdefghij234567klmnopqr';
        Client::addMockResponse(
            'GET',
            'https://plc.directory/' . $did,
            new Response(['HTTP/1.1 404 Not Found'], '{"error":"not found"}')
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DID resolution failed');
        (new DidResolver())->resolveDidToPds($did);
    }

    public function testMissingServiceArrayThrows(): void
    {
        $did = 'did:plc:abcdefghij234567klmnopqr';
        Client::addMockResponse(
            'GET',
            'https://plc.directory/' . $did,
            new Response(
                ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
                (string)json_encode(['id' => $did])
            )
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('service array');
        (new DidResolver())->resolveDidToPds($did);
    }

    public function testNoAtprotoServiceEntryThrows(): void
    {
        $did = 'did:plc:abcdefghij234567klmnopqr';
        Client::addMockResponse(
            'GET',
            'https://plc.directory/' . $did,
            new Response(
                ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
                (string)json_encode([
                    'service' => [
                        ['type' => 'OtherService', 'serviceEndpoint' => 'https://not-pds.example'],
                    ],
                ])
            )
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AtprotoPersonalDataServer');
        (new DidResolver())->resolveDidToPds($did);
    }

    public function testDefaultConstructorBuildsClient(): void
    {
        // Smoke test: no arg passed in — constructor still succeeds, just builds a default Client.
        // Does NOT make an HTTP call (resolveDidToPds is not invoked here).
        $r = new DidResolver();
        $this->assertInstanceOf(DidResolver::class, $r);
    }
}
