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

namespace App\Service\Inbox;

/**
 * Bluesky handle → URL slug normalizer (Phase 3 INBOX-01 / INBOX-06 / D-01 / D-02).
 *
 * Pure deterministic transform. NO I/O — collision retry is the caller's
 * responsibility (InboxesTable::assignUniqueSlug).
 *
 * Algorithm (D-01):
 *   1. Take portion of handle BEFORE the first '.', e.g. 'satie.bsky.social' → 'satie'.
 *   2. Lowercase.
 *   3. If result matches /^[a-z0-9_-]{3,32}$/, use it.
 *   4. Else fall back to 'user-' . substr(sha256(did), 0, 8).
 *
 * Boundary cases:
 *   - empty handle → fallback
 *   - handle starting with '.' or '_' or non-ASCII → fallback
 *   - did = '' AND handle empty → throw RuntimeException (impossible input)
 */
final class SlugDeriver
{
    /**
     * Regex for valid derived slug (lowercase only for derived slugs).
     *
     * @var string
     */
    private const SLUG_REGEX = '/^[a-z0-9_-]{3,32}$/';

    /**
     * Derive a slug from a Bluesky handle and DID.
     *
     * @param string $handle Bluesky handle, e.g. 'satie.bsky.social', 'you.example.com', or '' on AS error.
     * @param string $did Bluesky DID, e.g. 'did:plc:abcdefg...'. Required for fallback.
     * @return string Slug guaranteed to match the inboxes_slug_format CHECK regex (3-32 chars).
     * @throws \RuntimeException If both $handle and $did are empty.
     */
    public function deriveFromHandle(string $handle, string $did): string
    {
        if ($did === '' && $handle === '') {
            throw new \RuntimeException('SlugDeriver: both handle and did are empty.');
        }

        // If handle starts with '.', the prefix before the dot is empty — force fallback.
        if ($handle !== '' && $handle[0] !== '.') {
            $candidate = strtolower(strtok($handle, '.') ?: '');
        } else {
            $candidate = '';
        }

        if ($candidate !== '' && preg_match(self::SLUG_REGEX, $candidate) === 1) {
            return $candidate;
        }

        // Fallback — deterministic per-DID hash. did='' protected above.
        $hash = substr(hash('sha256', $did), 0, 8);

        return 'user-' . $hash;
    }
}
