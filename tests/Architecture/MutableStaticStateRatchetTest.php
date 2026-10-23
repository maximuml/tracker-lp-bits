<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Ratchet on mutable static state that survives between requests in
 * long-lived workers (Octane / RoadRunner / queue consumers).
 *
 * Under FPM every request gets a fresh process, so `static $x` inside a
 * function or a `private static` class property behaved like a request
 * memo. On a persistent worker the same code leaks data — or stale
 * permissions/settings — from one request into the next.
 *
 * Two rules are enforced on app/:
 *
 * 1. Function-level `static $var` declarations cannot be reset from the
 *    outside and are therefore forbidden; the FUNCTION_STATIC_ALLOWLIST
 *    holds the remaining immutable-memoization sites (lookup tables,
 *    sentinels, service memos keyed by immutable input).
 *
 * 2. A file declaring `private|protected static` mutable properties must
 *    expose a `public static function reset*()`/`flush()` that ResetNexus
 *    calls per request, unless it is in WORKER_SAFE_ALLOWLIST (service
 *    singletons, env/config-derived values, deterministic memoization,
 *    worker-scoped circuit breakers).
 *
 * To fix a failure: convert the function static into a private static
 * class property, add `resetState()`, and register it in
 * App\Listeners\ResetNexus.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class MutableStaticStateRatchetTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    /**
     * Function-level statics that are worker-safe immutable memoizations.
     * File path relative to app/; the list may only shrink.
     */
    private const FUNCTION_STATIC_ALLOWLIST = [
        'Support/Torrent/FileBadge.php', // immutable badge map
        'Support/RedisGuard.php', // stdClass sentinel for cache-miss detection
        'Support/Shoutbox.php', // immutable class-badge map
        'Support/Network.php', // geo-location lookup memo
        'Support/Strings.php', // immutable emoji table
        'Support/Smilies.php', // smiley path list
        'Support/AuthCookie.php', // encrypter service memo
    ];

    /**
     * Files whose class-level statics are worker-safe without a per-request
     * reset. All paths relative to app/.
     */
    private const WORKER_SAFE_ALLOWLIST = [
        'Repositories/TorrentRepository.php', // const-like include/filter tables
        'Repositories/UserRepository.php', // const-like include tables
        'Repositories/MysqlStatsRepository.php', // const unit/date tables
        'Repositories/MeiliSearchRepository.php', // shared client + const maps
        'Repositories/TorrentDownloadRepository.php', // HKDF memo keyed by passkey
        'Services/MeiliSearchFilterService.php', // const query maps
        'Services/MeiliSearchService.php', // const query maps
        'Utils/ThirdPartyJob.php', // const queue key/size
        'Http/Resources/TorrentResource.php', // stateless repository reference
        'Console/Commands/TranslateLang.php', // const env-to-path map
        'Support/AttachmentStorage.php', // driver service instances
        'Support/Config.php', // legacy config files, immutable per deploy
        'Support/Logger.php', // env-derived level/env + path memoization
        'Support/RedisGuard.php', // worker-shared circuit breaker, by design
        'Support/Network.php', // env-derived trusted proxies
        'Support/Env.php', // env values
        'Support/Captcha.php', // manager service singleton
        'Support/TwoFactorAuthHelper.php', // service singleton
        'Logging/SensitiveDataRedactor.php', // service singleton
    ];

    /**
     * Directories whose static properties are framework-managed metadata
     * (Filament navigation props) or Eloquent lookup tables.
     */
    private const FRAMEWORK_MANAGED_PREFIXES = [
        'Filament/',
    ];

    public function test_no_new_function_level_statics(): void
    {
        $violations = [];
        foreach ($this->phpFiles() as $file) {
            $relative = $this->relativePath($file);
            foreach (explode("\n", (string) file_get_contents($file)) as $line) {
                if (preg_match('/^\s*static\s+\$\w+/', $line)) {
                    $violations[$relative][] = trim($line);
                }
            }
        }

        $unexpected = array_diff(array_keys($violations), self::FUNCTION_STATIC_ALLOWLIST);
        $this->assertSame(
            [],
            array_values($unexpected),
            'New function-level `static $` declarations appeared. They cannot be '
           .'reset between requests on long-lived workers — convert to a class '
           .'property and reset it in ResetNexus. Found in: '
           .implode(', ', $unexpected),
        );

        // The allowlist may only shrink: stale entries must be removed.
        $stale = array_diff(self::FUNCTION_STATIC_ALLOWLIST, array_keys($violations));
        $this->assertSame(
            [],
            array_values($stale),
            'FUNCTION_STATIC_ALLOWLIST entries no longer contain a function static — '
           .'remove the stale entries: '.implode(', ', $stale),
        );
    }

    public function test_mutable_class_statics_are_resettable(): void
    {
        $violations = [];
        foreach ($this->phpFiles() as $file) {
            $relative = $this->relativePath($file);
            if ($this->isFrameworkManaged($relative)) {
                continue;
            }

            $content = (string) file_get_contents($file);
            if (! preg_match('/^\s*(?:private|protected)\s+static\s+(?:[\w\\\\|?]+\s+)?\$/m', $content)) {
                continue;
            }

            $hasReset = (bool) preg_match(
                '/public\s+static\s+function\s+(reset\w*|flush)\s*\(/',
                $content,
            );

            if (! $hasReset && ! in_array($relative, self::WORKER_SAFE_ALLOWLIST, true)) {
                $violations[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Files declare mutable private/protected static properties without a '
           .'public static reset*()/flush() and are not allowlisted: '
           .implode(', ', $violations),
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

    private function relativePath(string $file): string
    {
        return str_replace(self::APP_DIR.'/', '', $file);
    }

    private function isFrameworkManaged(string $relative): bool
    {
        foreach (self::FRAMEWORK_MANAGED_PREFIXES as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
