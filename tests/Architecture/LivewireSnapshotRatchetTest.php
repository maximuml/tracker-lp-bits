<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Stage-D ratchet: hot list surfaces must not embed Livewire components.
 *
 * Per-row/per-section Livewire snapshots bloated payloads (one snapshot per
 * torrent row, five per user profile). They were replaced by plain Blade +
 * fetch endpoints (`data-nx-bookmark`, `data-nx-torrentlist`). Interactive
 * components that are NOT per-row — comments, shoutbox, poll, bbcode editor,
 * upload helpers — legitimately remain Livewire and are out of scope here.
 *
 * To check a surface for stray usage:
 *   grep -rn '<livewire:' <path>
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class LivewireSnapshotRatchetTest extends TestCase
{
    private const BASE_DIR = __DIR__.'/../..';

    /** @var list<string> Blade paths (relative to repo root) that must stay Livewire-free. */
    private const MIGRATED_SURFACES = [
        'resources/views/components/torrents/table.blade.php',
        'resources/views/components/torrent/bookmark.blade.php',
        'resources/views/user/details.blade.php',
    ];

    /** @var list<string> Directories whose every Blade view must stay Livewire-free. */
    private const MIGRATED_DIRS = [
        'resources/views/components/getusertorrentlistajax',
    ];

    public function test_migrated_surfaces_have_no_livewire_components(): void
    {
        $violations = [];

        foreach (self::MIGRATED_SURFACES as $relative) {
            $path = self::BASE_DIR.'/'.$relative;
            if (is_file($path) && str_contains((string) file_get_contents($path), '<livewire:')) {
                $violations[] = $relative;
            }
        }

        foreach (self::MIGRATED_DIRS as $dir) {
            $absDir = self::BASE_DIR.'/'.$dir;
            if (! is_dir($absDir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getPathname(), '.blade.php')) {
                    continue;
                }
                if (str_contains((string) file_get_contents($file->getPathname()), '<livewire:')) {
                    $violations[] = substr($file->getPathname(), strlen(self::BASE_DIR) + 1);
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Livewire components re-appeared on migrated list surfaces — per-row snapshots were removed for payload size (stage D). Use plain Blade + fetch endpoints instead:\n"
            .implode("\n", $violations),
        );
    }
}
