<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Ratchet on `->toArray()` degradations inside the domain layers.
 *
 * Repositories and Services converting Eloquent models to plain arrays
 * erase the typed shape PHPStan level 8 can check: `$row['usename']` typos
 * pass silently, IDE completion dies, and every consumer re-validates
 * keys with `(int) ($row['id'] ?? 0)`. Arrays remain legitimate at the
 * render boundary (controllers/view factories assembling legacy `$data`)
 * and for `toLegacyArray()` — this gate counts only bare `->toArray()`
 * calls in Repositories/ and Services/.
 *
 * The sibling pair `OfferCommentRepository::getComments()` /
 * `TorrentDetailRepository::getComments()` shows the preferred shape:
 * return `Collection<int, Model>` and let the consumer serialize at the
 * boundary.
 *
 * Only bare `->toArray()` calls count: Eloquent's toArray() takes no
 * arguments, while `$this->toArray($collection)` in the Topten
 * repositories invokes a local row-shaping helper — a different (and
 * intentional) boundary mechanism.
 *
 * Baseline captured after typing the torrent-comment flow and
 * findById: 45 repository + 26 service call sites. May only be lowered.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class ModelToArrayRatchetTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    /** @var array<string, int> subdirectory → baseline call-site count */
    private const BASELINES = [
        'Repositories' => 43,
        'Services' => 22,
    ];

    public function test_to_array_calls_do_not_grow_past_baseline(): void
    {
        $violations = [];

        foreach (self::BASELINES as $layer => $baseline) {
            $count = $this->countToArrayCalls(self::APP_DIR.'/'.$layer);
            if ($count > $baseline) {
                $violations[] = "{$layer}: {$count} > {$baseline}";
            }
        }

        $this->assertSame(
            [],
            $violations,
            'New ->toArray() call sites in domain layers: '.implode('; ', $violations)
            ."\nReturn typed models/Collections and serialize at the render "
            .'boundary instead — see OfferCommentRepository::getComments(). '
            .'To lower a baseline, remove a site and update self::BASELINES.',
        );
    }

    private function countToArrayCalls(string $dir): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            foreach (explode("\n", $content) as $line) {
                $trimmed = ltrim($line);
                if (str_starts_with($trimmed, '*')
                    || str_starts_with($trimmed, '//')
                    || str_starts_with($trimmed, '/*')
                ) {
                    continue;
                }

                // Bare ->toArray() only: Eloquent's serializer takes no
                // args; $this->toArray($collection) is a local helper call.
                if (preg_match('/->\s*toArray\s*\(\s*\)/', $line)) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
