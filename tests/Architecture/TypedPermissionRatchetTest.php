<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Ratchet on raw permission-string calls.
 *
 * All permission checks in domain code must go through the typed
 * PermissionEnum facade — `Permission::can(PermissionEnum::X)` /
 * `ActorContext::can()` — or pass `PermissionEnum::X->value` to
 * PermissionChecker::userCan(). A bare string literal ('staffmem',
 * 'commanage', …) compiles happily against any typo and silently fails
 * closed or, worse, matches nothing the permission table knows.
 *
 * The engine itself (Permissions + its injectable front PermissionChecker
 * + the enum facade Permission) is exempt — it legitimately receives the
 * string at the boundary.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class TypedPermissionRatchetTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    /**
     * Files that form the permission engine boundary — the only places a
     * permission may still be named by a raw string. Paths relative to
     * app/; may only shrink.
     */
    private const ENGINE_ALLOWLIST = [
        'Support/Permissions.php',
        'Services/PermissionChecker.php',
        'Auth/Permission.php',
    ];

    public function test_no_raw_permission_strings_outside_engine(): void
    {
        $violations = [];
        $pattern = '/userCan\(\s*[\'"]/';

        foreach ($this->phpFiles() as $file) {
            $relative = str_replace(self::APP_DIR.'/', '', $file);
            if (in_array($relative, self::ENGINE_ALLOWLIST, true)) {
                continue;
            }

            foreach (explode("\n", (string) file_get_contents($file)) as $lineno => $line) {
                if (preg_match($pattern, $line)) {
                    $violations[] = "$relative:".($lineno + 1);
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Domain code passes a raw permission string to userCan() — use '
           .'PermissionEnum::X->value (or Permission::can()/ActorContext::can() '
           .'with the enum) so the name is checked at compile time. '
           .'Violations: '.implode(', ', $violations),
        );
    }

    /** @return list<string> */
    private function phpFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::APP_DIR, \RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
