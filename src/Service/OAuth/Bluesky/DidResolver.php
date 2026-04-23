<?php
declare(strict_types=1);

namespace App\Service\OAuth\Bluesky;

use Cake\Http\Client;
use RuntimeException;

/**
 * DID -> PDS URL resolver via plc.directory.
 *
 * Phase 2 scope: did:plc: only. did:web / third-party PDS is Deferred (CONTEXT.md).
 * CONTEXT D-07 precludes handle->DID resolution (AS handles that UI); only token.sub->PDS
 * mapping is needed here, which is a single plc.directory GET.
 *
 * Threat mitigations:
 *  - T-02-03-05 Tampering: DID format validated via `^did:plc:[a-z2-7]{24}$` BEFORE any HTTP call
 *  - T-02-03-06 DoS: Cake\Http\Client is constructed with a 10 s timeout
 *  - T-02-03-07 Info Disclosure: RuntimeException messages are generic (no echo of response body)
 */
final class DidResolver
{
    /**
     * plc.directory base URL (always trailing slash — concatenated with DID).
     *
     * @var string
     */
    private const PLC_DIRECTORY_BASE = 'https://plc.directory/';

    /**
     * DID format regex: `did:plc:` followed by exactly 24 base32-hex chars [a-z2-7].
     * RESEARCH "Handle / DID Resolution" section.
     *
     * @var string
     */
    private const DID_FORMAT_REGEX = '/^did:plc:[a-z2-7]{24}$/';

    /**
     * HTTP timeout in seconds — guards against plc.directory hangs (T-02-03-06).
     *
     * @var int
     */
    private const HTTP_TIMEOUT_SEC = 10;

    private Client $http;

    /**
     * @param \Cake\Http\Client|null $http Optional injected client. Tests use the global
     *   Client::addMockResponse() static mock adapter, which intercepts every Client instance,
     *   so the default construction is fine for tests too.
     */
    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client([
            'timeout' => self::HTTP_TIMEOUT_SEC,
            'redirect' => 3,
        ]);
    }

    /**
     * Resolve a did:plc: identifier to the PDS endpoint URL declared in its DID document.
     *
     * @param string $did Must match `did:plc:[a-z2-7]{24}` (24 base32-hex chars after did:plc:).
     * @return string PDS URL (e.g. 'https://bsky.social'). Trailing slash is stripped.
     * @throws \RuntimeException Invalid DID, plc.directory non-200, missing PDS service entry.
     */
    public function resolveDidToPds(string $did): string
    {
        // T-02-03-05 mitigation: validate DID syntax BEFORE outbound HTTP.
        if (!preg_match(self::DID_FORMAT_REGEX, $did)) {
            throw new RuntimeException('Invalid DID format.');
        }

        try {
            $response = $this->http->get(
                self::PLC_DIRECTORY_BASE . $did,
                [],
                ['headers' => ['Accept' => 'application/json']]
            );
        } catch (\Throwable $e) {
            throw new RuntimeException('DID resolution failed (network error).');
        }

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('DID resolution failed.');
        }

        $doc = $response->getJson();
        if (!is_array($doc) || !isset($doc['service']) || !is_array($doc['service'])) {
            throw new RuntimeException('DID document missing service array.');
        }

        foreach ($doc['service'] as $entry) {
            if (
                is_array($entry)
                && isset($entry['type'], $entry['serviceEndpoint'])
                && $entry['type'] === 'AtprotoPersonalDataServer'
                && is_string($entry['serviceEndpoint'])
                && $entry['serviceEndpoint'] !== ''
            ) {
                return rtrim($entry['serviceEndpoint'], '/');
            }
        }

        throw new RuntimeException('DID document has no AtprotoPersonalDataServer service.');
    }
}
