<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Ratchet on reads of the mutable CurrentUser singleton.
 *
 * CurrentUser is the write-side bridge: the guard, middleware and a few
 * out-of-band auth flows promote()/set() the legacy user array into it so
 * downstream legacy readers still work. Reading it in domain code bypasses
 * the immutable per-request snapshot: a promote() mid-request silently
 * changes what a CurrentUser reader sees, while the already-resolved
 * ActorContext keeps the old user — the "parallel worlds" split.
 *
 * Domain code must read the actor through ActorContext (immutable,
 * per-request, flushed by ResetNexus) instead. WRITE calls (promote,
 * promoteIfAbsent, set, reset) are the bridge's job and stay allowed.
 *
 * To fix a failure: inject ActorContext (or call ActorContext::current())
 * and use ->id / ->value() / ->yes() / ->no() / ->toLegacyArray().
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class CurrentUserReadRatchetTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    /**
     * Read methods on CurrentUser that domain code must not call.
     */
    private const READ_METHODS = 'get|id|value|yes|no|isLoggedIn|username|classId|enabled|passkey|seedbonus';

    /**
     * Files allowed to read CurrentUser — the singleton itself and the
     * ActorContext hydration bridge. Paths relative to app/; may only shrink.
     */
    private const ALLOWLIST = [
        'Support/CurrentUser.php', // the class itself
        'DTOs/Auth/ActorContext.php', // builds the immutable snapshot from it
    ];

    /**
     * Domain layers where reading the ambient guard directly bypasses the
     * ActorContext snapshot. Controllers/middleware legitimately resolve the
     * guard (that is how the promote bridge authenticates), so the ban is
     * scoped to Repositories/Services/Livewire.
     */
    private const DOMAIN_LAYER_PREFIXES = [
        'Repositories/',
        'Services/',
        'Livewire/',
    ];

    public function test_no_currentuser_reads_outside_bridge(): void
    {
        $violations = [];
        $pattern = '/(?:currentUser->|CurrentUser::instance\(\)->)(?:'.self::READ_METHODS.')\\s*\(/';

        foreach ($this->phpFiles() as $file) {
            $relative = $this->relativePath($file);
            if (in_array($relative, self::ALLOWLIST, true)) {
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
            'Domain code reads the mutable CurrentUser singleton — use the '
           .'immutable ActorContext snapshot instead (->id / ->value() / ->yes() '
           .'/ ->toLegacyArray()). Violations: '.implode(', ', $violations),
        );
    }

    public function test_no_ambient_guard_reads_in_domain_layers(): void
    {
        $violations = [];
        $pattern = '/Auth::(?:user|id)\s*\(\s*\)/';

        foreach ($this->phpFiles() as $file) {
            $relative = $this->relativePath($file);
            if (! $this->isDomainLayer($relative)) {
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
            'Repositories/Services/Livewire read the ambient guard via '
           .'Auth::user()/Auth::id() — use ActorContext::current()->user or '
           .'inject ActorContext instead. Violations: '.implode(', ', $violations),
        );
    }

    private function isDomainLayer(string $relative): bool
    {
        foreach (self::DOMAIN_LAYER_PREFIXES as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
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

    private function relativePath(string $file): string
    {
        return str_replace(self::APP_DIR.'/', '', $file);
    }
}
