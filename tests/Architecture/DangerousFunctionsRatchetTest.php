<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Ratchet on dangerous PHP primitives in app/: eval, dynamic-function
 * creation, unserialize, variable extraction, and shell execution.
 *
 * Each is a code/command-injection or object-injection primitive when fed
 * request-controlled data. A small set of files has audited, justified
 * uses; those are pinned by exact per-file call counts so growth inside
 * an allowlisted file also fails.
 *
 * Allowlist rationale:
 *   - Support/Cache/NexusCache.php — unserialize() wrapper forces
 *     `allowed_classes: false` (Sprint 19 hardening); object injection
 *     is impossible.
 *   - Support/View.php — extract($data, EXTR_SKIP) on the template data
 *     array; EXTR_SKIP cannot clobber $view/$data/$rootPath.
 *   - Support/Environment.php — exec() for `command -v` and the artisan
 *     sub-shell; every argument passes escapeshellarg/escapeshellcmd.
 *   - Services/BackupService.php — exec() for tar/zip/gpg/mysqldump;
 *     every interpolated argument is escapeshellarg'd and paths are
 *     app-derived, never request-derived.
 *   - Console/Commands/BackupRestoreDrill.php — same pattern inside a
 *     CLI-only restore drill.
 *
 * Method calls (`->eval()`, `->exec()`, `::system()`) are excluded —
 * e.g. Redis Lua via $redis->eval() takes no shell/PHP path.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class DangerousFunctionsRatchetTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    /**
     * @var array<string, string> function name → regex fragment matching a
     *                            direct (non-method, non-static-method) call of the primitive.
     */
    private const PATTERNS = [
        'eval' => 'eval',
        'create_function' => 'create_function',
        'unserialize' => 'unserialize',
        'extract' => 'extract',
        'exec' => 'exec',
        'shell_exec' => 'shell_exec',
        'system' => 'system',
        'passthru' => 'passthru',
        'proc_open' => 'proc_open',
        'popen' => 'popen',
        'pcntl_exec' => 'pcntl_exec',
        'assert' => 'assert',
    ];

    /**
     * Audited usages: relative path → function → allowed call count.
     * Any call outside these files, or beyond these counts, fails.
     *
     * @var array<string, array<string, int>>
     */
    private const ALLOWED = [
        'Support/Cache/NexusCache.php' => ['unserialize' => 2],
        'Support/View.php' => ['extract' => 1],
        'Support/Environment.php' => ['exec' => 2],
        'Services/BackupService.php' => ['exec' => 4],
        'Console/Commands/BackupRestoreDrill.php' => ['exec' => 4],
    ];

    public function test_dangerous_functions_do_not_appear_outside_allowlist(): void
    {
        $violations = [];
        $allowlistCounts = [];

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

            foreach (explode("\n", $content) as $lineNumber => $line) {
                $trimmed = ltrim($line);
                if (str_starts_with($trimmed, '*')
                    || str_starts_with($trimmed, '//')
                    || str_starts_with($trimmed, '/*')
                    || str_starts_with($trimmed, '#')
                ) {
                    continue;
                }

                // Blank out string literals so SQL fragments (EXTRACT(EPOCH…),
                // log messages ("deleted by system (…)")) don't trip the scan.
                $code = (string) preg_replace(
                    ['/"(?:[^"\\\\]|\\\\.)*"/', "/'(?:[^'\\\\]|\\\\.)*'/"],
                    "''",
                    $line,
                );

                foreach (self::PATTERNS as $function => $fragment) {
                    // Direct call only: `(?<![:>$\w])` excludes ->fn(), ::fn(),
                    // $fn() — method calls and variable functions take a
                    // different (and safe) path.
                    if (! preg_match('/(?<![:>$\w])'.$fragment.'\s*\(/i', $code)) {
                        continue;
                    }

                    $allowed = self::ALLOWED[$relativePath][$function] ?? 0;
                    if ($allowed > 0) {
                        $allowlistCounts[$relativePath][$function]
                            = ($allowlistCounts[$relativePath][$function] ?? 0) + 1;

                        continue;
                    }

                    $violations[] = "{$relativePath}:".($lineNumber + 1)." calls {$function}()";
                }
            }
        }

        // Allowlisted calls must match the pinned counts exactly — a new
        // exec() inside BackupService is as suspicious as one elsewhere.
        foreach (self::ALLOWED as $path => $functions) {
            foreach ($functions as $function => $expected) {
                $actual = $allowlistCounts[$path][$function] ?? 0;
                if ($actual !== $expected) {
                    $violations[] = "{$path} has {$actual} {$function}() calls, expected {$expected} — update the pin or justify the new call";
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Dangerous function calls outside the audited allowlist:\n".implode("\n", $violations)
            ."\n\nEach primitive is injection-prone on request data. If a use is genuinely "
            .'safe (escaped args, allowed_classes: false, EXTR_SKIP, …), justify it in the '
            .'class docblock and pin it in self::ALLOWED.',
        );
    }
}
