<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * W2-12: Ratchet on SupportContext usage.
 *
 * SupportContext is a static facade for the per-request NexusContext.
 * It should only be used in wrapper classes (CurrentUser, Globals,
 * UserUpdateBatch, LegacyBootstrap, Bootstrap, ResetNexus) that
 * provide DI-friendly access to the same data.
 *
 * Controllers, services, and repositories should use the wrapper
 * classes or inject NexusContext directly — never SupportContext.
 *
 * Baseline: 11 calls in 7 wrapper files (captured 2026-09-07).
 */
final class SupportContextUsageTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    /**
     * Files allowed to use SupportContext directly.
     *
     * These are wrapper/adapter classes that bridge the static
     * facade to DI-friendly services.
     */
    private const ALLOWED_FILES = [
        'Support/CurrentUser.php',
        'Support/Globals.php',
        'Support/UserUpdateBatch.php',
        'Support/LegacyBootstrap.php',
        'Support/Bootstrap.php',
        'Listeners/ResetNexus.php',
        'Services/Announce/AnnounceRequestFactory.php',
    ];

    public function test_support_context_only_used_in_wrappers(): void
    {
        $violations = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::APP_DIR, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace(self::APP_DIR.'/', '', $file->getPathname());

            // Skip the SupportContext class itself
            if ($relativePath === 'Support/SupportContext.php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false || ! str_contains($content, 'SupportContext')) {
                continue;
            }

            if (! in_array($relativePath, self::ALLOWED_FILES, true)) {
                // Count actual usage lines (not comments)
                $hasUsage = false;
                foreach (explode("\n", $content) as $line) {
                    $trimmed = ltrim($line);
                    if (str_starts_with($trimmed, '*')
                        || str_starts_with($trimmed, '//')
                        || str_starts_with($trimmed, '#')
                        || str_starts_with($trimmed, '/*')
                        || str_contains($trimmed, 'Replaces SupportContext')
                    ) {
                        continue;
                    }

                    if (str_contains($line, 'SupportContext::')) {
                        $hasUsage = true;
                        break;
                    }
                }

                if ($hasUsage) {
                    $violations[] = $relativePath;
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "SupportContext:: is used outside wrapper classes:\n".
            implode("\n", $violations)."\n\n".
            'Use CurrentUser, Globals, UserUpdateBatch, or inject NexusContext instead.',
        );
    }
}
