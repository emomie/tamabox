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

namespace App\Test\TestCase\Service\Message;

use App\Service\Message\SsrJudge;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

/**
 * SsrJudge Test Case
 */
class SsrJudgeTest extends TestCase
{
    /**
     * @var \App\Service\Message\SsrJudge
     */
    private SsrJudge $svc;

    /**
     * @var string
     */
    private string $secret = 'test-secret-deterministic-32-chars!';

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('Security.serverSecret', $this->secret);
        $this->svc = new SsrJudge();
    }

    /**
     * Test same inputs always produce identical seed and is_ssr (D-09 F2 invariant).
     *
     * @return void
     */
    public function testDeterminism(): void
    {
        $a = $this->svc->judge('msg1', '2026-04-27 12:00:00.000001', '0.500');
        $b = $this->svc->judge('msg1', '2026-04-27 12:00:00.000001', '0.500');
        $this->assertSame($a['ssr_seed'], $b['ssr_seed']);
        $this->assertSame($a['is_ssr'], $b['is_ssr']);
    }

    /**
     * Test seed is 64 lowercase hex characters.
     *
     * @return void
     */
    public function testSeedIs64HexChars(): void
    {
        $r = $this->svc->judge('m', 't', '0.500');
        $this->assertSame(64, strlen($r['ssr_seed']));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $r['ssr_seed']);
    }

    /**
     * Test probability 0.000 always misses (rand01 ∈ [0,1); rand01 < 0 impossible).
     *
     * @return void
     */
    public function testProbabilityZeroAlwaysMisses(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $r = $this->svc->judge('msg-' . $i, 'now-' . $i, '0.000');
            $this->assertFalse($r['is_ssr'], "iter $i should miss at 0%");
        }
    }

    /**
     * Test probability 1.000 always hits (rand01 ∈ [0,1); rand01 < 1 always true).
     *
     * @return void
     */
    public function testProbabilityOneAlwaysHits(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $r = $this->svc->judge('msg-' . $i, 'now-' . $i, '1.000');
            $this->assertTrue($r['is_ssr'], "iter $i should hit at 100%");
        }
    }

    /**
     * Test seed matches manual sha256 computation.
     *
     * @return void
     */
    public function testKnownSeedMatchesManualHash(): void
    {
        $expected = hash('sha256', $this->secret . 'fixed-id' . 'fixed-time');
        $r = $this->svc->judge('fixed-id', 'fixed-time', '0.500');
        $this->assertSame($expected, $r['ssr_seed']);
    }

    /**
     * Test ssr_probability_at_send echoes back the input string.
     *
     * @return void
     */
    public function testProbabilityAtSendEchoed(): void
    {
        $r = $this->svc->judge('m', 't', '0.250');
        $this->assertSame('0.250', $r['ssr_probability_at_send']);
    }

    /**
     * Test empty server secret throws RuntimeException.
     *
     * @return void
     */
    public function testEmptyServerSecretThrows(): void
    {
        Configure::write('Security.serverSecret', '');
        $this->expectException(\RuntimeException::class);
        $this->svc->judge('m', 't', '0.500');
    }

    /**
     * Test is_ssr is consistent with manual computation of rand01.
     *
     * @return void
     */
    public function testProbabilityBoundaryHit(): void
    {
        $msg = 'audit-replay-msg';
        $time = '2026-04-27 12:34:56.789012';
        $r = $this->svc->judge($msg, $time, '0.500');
        $manualRand = hexdec(substr($r['ssr_seed'], 0, 8)) / 0xFFFFFFFF;
        $this->assertSame($manualRand < 0.5, $r['is_ssr']);
    }

    /**
     * Test different message IDs produce different seeds.
     *
     * @return void
     */
    public function testDifferentInputsProduceDifferentSeeds(): void
    {
        $r1 = $this->svc->judge('msg-alpha', 'ts-1', '0.500');
        $r2 = $this->svc->judge('msg-beta', 'ts-1', '0.500');
        $this->assertNotSame($r1['ssr_seed'], $r2['ssr_seed']);
    }
}
