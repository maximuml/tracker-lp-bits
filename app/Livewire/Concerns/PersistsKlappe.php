<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * Klappe-collapse state persistence — same '1'|'0' contract the legacy
 * stats-details.js used on nx-klappe:* localStorage keys, stored in a
 * cookie so the server component reads it on mount (no post-load flip).
 */
trait PersistsKlappe
{
    private function klappeInitial(string $id, bool $default): bool
    {
        $stored = request()->cookie('nx-klappe-'.$id);

        return $stored === null ? $default : $stored === '1';
    }

    private function klappePersist(string $id, bool $open): void
    {
        cookie()->queue('nx-klappe-'.$id, $open ? '1' : '0', 525600);
    }
}
