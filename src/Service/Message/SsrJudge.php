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

namespace App\Service\Message;

use Cake\Core\Configure;
use RuntimeException;

/**
 * SSR (Super Rare reveal) judgement — D-09.
 *
 * Computes the deterministic seed and is_ssr flag at SEND time (MSG-02 contract).
 * The judgement is auditable via F2 invariant: given (server_secret, message_id,
 * created_at_micro), the seed and is_ssr are reproducible.
 *
 *   seed = sha256(server_secret . message_id . created_at_micro)  -- 64 hex chars
 *   rand01 = hexdec(substr(seed, 0, 8)) / 0xFFFFFFFF              -- in [0, 1)
 *   is_ssr = rand01 < probability                                  -- probability ∈ [0, 1]
 *
 * server_secret comes from Configure::read('Security.serverSecret') (Phase 1 INFRA-02
 * SERVER_SECRET env var). Empty secret → RuntimeException at call time.
 */
final class SsrJudge
{
    /**
     * Compute the SSR seed and judgement for a message.
     *
     * @param string $messageId UUID of the message being sent (caller pre-generates via Text::uuid()).
     * @param string $createdAtMicro Microsecond-precision timestamp string, e.g. result of (new \DateTimeImmutable())->format('Y-m-d H:i:s.u').
     * @param string $probability Decimal string from inboxes.ssr_probability column, e.g. '0.100'.
     * @return array{ssr_seed: string, is_ssr: bool, ssr_probability_at_send: string}
     * @throws \RuntimeException If Security.serverSecret is empty.
     */
    public function judge(string $messageId, string $createdAtMicro, string $probability): array
    {
        $serverSecret = (string)Configure::read('Security.serverSecret', '');
        if ($serverSecret === '') {
            throw new RuntimeException('SsrJudge: Security.serverSecret is not configured.');
        }

        $seed = hash('sha256', $serverSecret . $messageId . $createdAtMicro);
        $rand01 = hexdec(substr($seed, 0, 8)) / 0xFFFFFFFF;
        $isSsr = $rand01 < (float)$probability;

        return [
            'ssr_seed' => $seed,
            'is_ssr' => $isSsr,
            'ssr_probability_at_send' => $probability,
        ];
    }
}
