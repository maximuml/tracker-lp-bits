<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * W2-12: SupportContext was dissolved into NexusContext::instance() /
 * NexusContext::reset(); this ratchet fails if the facade name reappears
 * anywhere under app/ (a reintroduced static context facade).
 *
 * Callers use the wrapper classes (CurrentUser, PageState, UserUpdateBatch)
 * or inject NexusContext directly — never a SupportContext facade.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class SupportContextUsageTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    public function test_support_context_is_not_used_anywhere(): void
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

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            foreach (explode("\n", $content) as $line) {
                $trimmed = ltrim($line);
                if (str_starts_with($trimmed, '*')
                    || str_starts_with($trimmed, '//')
                    || str_starts_with($trimmed, '#')
                    || str_starts_with($trimmed, '/*')
                ) {
                    continue;
                }

                if (str_contains($line, 'SupportContext::') || str_contains($line, 'use App\Support\SupportContext')) {
                    $violations[] = $relativePath;
                    break;
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "SupportContext was dissolved into NexusContext — the facade must not reappear:\n".
            implode("\n", $violations)."\n\n".
            'Use CurrentUser, PageState, UserUpdateBatch, or NexusContext::instance() instead.',
        );
    }
}
