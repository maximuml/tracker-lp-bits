<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Per-request batch of user column updates.
 *
 * Replaces SupportContext::addUserUpdate()/getUserUpdateSet() with a
 * container singleton. Internally delegates to NexusContext which holds
 * the actual array storage (and mirrors it into globals['USERUPDATESET']).
 */
final class UserUpdateBatch
{
    /**
     * Add a key/value pair to the batch.
     */
    public function add(string $key, mixed $value): void
    {
        SupportContext::addUserUpdate($key, $value);
    }

    /**
     * Get the current batch (by reference, for legacy compatibility).
     *
     * @return array<string, mixed>
     */
    public function &all(): array
    {
        $set = &SupportContext::getUserUpdateSet();

        return $set;
    }
}
