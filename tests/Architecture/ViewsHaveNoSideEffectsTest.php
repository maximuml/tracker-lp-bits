<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Enforces that Blade views do not contain side-effect calls.
 *
 * Views must be pure presentation: no auth checks, no POST reads,
 * no email sending, no LegacyResponse::abort(), no die/exit.
 * All such logic belongs in controllers or middleware.
 */
final class ViewsHaveNoSideEffectsTest extends TestCase
{
    /**
     * Patterns that indicate a side-effect in a Blade view.
     * Each entry is [pattern, description].
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const FORBIDDEN_PATTERNS = [
        // Auth / permission checks — belong in controllers/middleware
        ['UserDisplay::currentClass\\(\\)', 'auth check (UserDisplay::currentClass) — move to controller/middleware'],
        ['LegacyResponse::permissionDenied', 'permission denied call — move to controller/middleware'],
        ['LegacyResponse::abort\\(', 'LegacyResponse::abort() — move to controller'],

        // POST/request reads — data should be passed from controller
        ['request\\(\\)->post\\(', 'request()->post() — pass data from controller'],
        ['request\\(\\)->input\\(', 'request()->input() — pass data from controller'],
        ['request\\(\\)->query\\(', 'request()->query() — pass data from controller'],

        // Mail/DB side-effects — belong in services
        ['Mail::sent', 'Mail::sent — move to controller/service'],
        ['Mail::send', 'Mail::send — move to controller/service'],

        // Die/exit — never in views
        ['\\bdie\\(', 'die() — never in views'],
        ['\\bexit\\(', 'exit() — never in views'],
    ];

    /**
     * Views that are exempt from the side-effect rules.
     * These are legacy views that still need refactoring.
     *
     * @var array<int, string>
     */
    private const EXEMPT_VIEWS = [
        // Still uses UserDisplay::currentClass() for conditional display
        'resources/views/warned/index.blade.php',
        // Pre-existing side-effects — to be refactored in future sprints
        'resources/views/auth/login.blade.php',
        'resources/views/bitbucket/_bitbucket.blade.php',
        'resources/views/bitbucketlog/index.blade.php',
        'resources/views/comments/_comments.blade.php',
        'resources/views/forums/_viewforum.blade.php',
        'resources/views/forums/_viewthread.blade.php',
        'resources/views/image/_image.blade.php',
        'resources/views/increment-bulk/_increment-bulk.blade.php',
        'resources/views/invite/index.blade.php',
        'resources/views/moresmilies/index.blade.php',
        'resources/views/my/_hr.blade.php',
        'resources/views/page/_page.blade.php',
        'resources/views/polloverview/index.blade.php',
        'resources/views/reports/_reports.blade.php',
        'resources/views/shoutbox/index.blade.php',
        'resources/views/staffmess/_staffmess.blade.php',
        'resources/views/torrent/_edit.blade.php',
        'resources/views/torrents/_search_form.blade.php',
        'resources/views/user-ban-log/index.blade.php',
        'resources/views/user/_details.blade.php',
        'resources/views/userhistory/_userhistory.blade.php',
    ];

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function viewProvider(): array
    {
        $viewsDir = __DIR__.'/../../resources/views';
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php' || $file->getExtension() === 'blade.php') {
                $relativePath = 'resources/views/'.ltrim(str_replace($viewsDir, '', $file->getPathname()), '/');
                $files[$relativePath] = [$relativePath, $file->getPathname(), (string) file_get_contents($file->getPathname())];
            }
        }

        return $files;
    }

    /**
     * @param  string  $relativePath  Relative path from project root.
     * @param  string  $absolutePath  Absolute filesystem path.
     * @param  string  $content  File contents.
     */
    #[DataProvider('viewProvider')]
    public function test_view_has_no_side_effects(string $relativePath, string $absolutePath, string $content): void
    {
        if (in_array($relativePath, self::EXEMPT_VIEWS, true)) {
            $this->markTestSkipped("Exempt: {$relativePath}");
        }

        foreach (self::FORBIDDEN_PATTERNS as [$pattern, $description]) {
            // Skip if the pattern is inside a comment line
            $lines = explode("\n", $content);
            foreach ($lines as $lineNum => $line) {
                $trimmed = trim($line);
                // Skip comment lines (// or # or *)
                if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '*')) {
                    continue;
                }

                if (preg_match('/'.$pattern.'/i', $line)) {
                    $this->fail(
                        sprintf(
                            "%s contains forbidden side-effect: %s (line %d).\n  %s",
                            $relativePath,
                            $description,
                            $lineNum + 1,
                            trim($line)
                        )
                    );
                }
            }
        }

        $this->assertTrue(true);
    }
}
