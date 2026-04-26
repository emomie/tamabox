<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

namespace App\Test\TestCase\Service\Inbox;

use App\Service\Inbox\SlugDeriver;
use Cake\TestSuite\TestCase;

/**
 * SlugDeriver Test Case
 */
class SlugDeriverTest extends TestCase
{
    /**
     * @var \App\Service\Inbox\SlugDeriver
     */
    private SlugDeriver $svc;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new SlugDeriver();
    }

    /**
     * Test domain prefix extraction from 'satie.bsky.social' → 'satie'.
     *
     * @return void
     */
    public function testDomainPrefixExtraction(): void
    {
        $this->assertSame('satie', $this->svc->deriveFromHandle('satie.bsky.social', 'did:plc:abc'));
    }

    /**
     * Test custom domain handle extracts correct prefix.
     *
     * @return void
     */
    public function testCustomDomainHandle(): void
    {
        $this->assertSame('you', $this->svc->deriveFromHandle('you.example.com', 'did:plc:xyz'));
    }

    /**
     * Test uppercase handle is lowercased.
     *
     * @return void
     */
    public function testCaseLowered(): void
    {
        $this->assertSame('satie', $this->svc->deriveFromHandle('SATIE.bsky.social', 'did:plc:abc'));
    }

    /**
     * Test empty handle falls back to DID hash.
     *
     * @return void
     */
    public function testEmptyHandleFallsBackToDidHash(): void
    {
        $did = 'did:plc:abc1234567890123456789012';
        $expected = 'user-' . substr(hash('sha256', $did), 0, 8);
        $this->assertSame($expected, $this->svc->deriveFromHandle('', $did));
    }

    /**
     * Test handle starting with '.' falls back to DID hash.
     *
     * @return void
     */
    public function testHandleStartingWithDotFallsBack(): void
    {
        $did = 'did:plc:bcd';
        $expected = 'user-' . substr(hash('sha256', $did), 0, 8);
        $this->assertSame($expected, $this->svc->deriveFromHandle('.atproto.example.com', $did));
    }

    /**
     * Test handle with too-short prefix (< 3 chars) falls back.
     *
     * @return void
     */
    public function testTwoCharHandleFallsBack(): void
    {
        // 'a' is too short (< 3 chars) — must fall back.
        $did = 'did:plc:short';
        $expected = 'user-' . substr(hash('sha256', $did), 0, 8);
        $this->assertSame($expected, $this->svc->deriveFromHandle('a.bsky.social', $did));
    }

    /**
     * Test non-ASCII handle falls back to DID hash.
     *
     * @return void
     */
    public function testNonAsciiHandleFallsBack(): void
    {
        $did = 'did:plc:utf';
        $expected = 'user-' . substr(hash('sha256', $did), 0, 8);
        $this->assertSame($expected, $this->svc->deriveFromHandle('たまばこ.bsky.social', $did));
    }

    /**
     * Test both empty handle and DID throws RuntimeException.
     *
     * @return void
     */
    public function testBothEmptyThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->svc->deriveFromHandle('', '');
    }

    /**
     * Test determinism: same inputs always produce same output.
     *
     * @return void
     */
    public function testDeterminism(): void
    {
        $r1 = $this->svc->deriveFromHandle('', 'did:plc:abc');
        $r2 = $this->svc->deriveFromHandle('', 'did:plc:abc');
        $this->assertSame($r1, $r2);
    }

    /**
     * Test underscores and dashes in handle prefix are preserved.
     *
     * @return void
     */
    public function testUnderscoresAndDashesAccepted(): void
    {
        // Domain part containing _ or - and within 3..32 chars stays as-is.
        $this->assertSame('foo_bar-baz', $this->svc->deriveFromHandle('foo_bar-baz.example.com', 'did:plc:zzz'));
    }
}
