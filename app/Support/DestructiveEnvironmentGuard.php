<?php

namespace App\Support;

use RuntimeException;

/**
 * Prevents tests and seeders from running against a non-test database.
 *
 * The guard is enforced whenever {@see assertTestingEnvironment()} is called
 * (typically from the test suite's setUp() and the migrate:fresh /
 * seed commands). When APP_ENV is "testing" the configured database name must
 * contain one of the markers "test", "testing" or "e2e" and the Redis prefix
 * must contain "test". Any other value is treated as a production/dev database
 * and rejected before a single query runs.
 */
final class DestructiveEnvironmentGuard
{
    /**
     * Markers that identify a database name as safe for destructive test use.
     *
     * @var list<string>
     */
    private const DB_MARKERS = ['test', 'testing', 'e2e'];

    /**
     * Marker that must appear in the Redis prefix when running tests.
     */
    private const REDIS_MARKER = 'test';

    /**
     * Assert that the current environment is safe for destructive operations.
     *
     * @param  array<string, mixed>|null  $config  Override the resolved config
     *                                             (used by the guard's own
     *                                             tests to avoid booting the
     *                                             full framework).
     */
    public static function assertTestingEnvironment(?array $config = null): void
    {
        $env = (string) ($config['app_env'] ?? self::resolveEnv('APP_ENV', 'app.env', ''));

        // Only enforce when the application believes it is in the testing
        // environment. Production and local dev are not gated here — they have
        // their own protections (migrate:fresh --force prompts, etc.).
        if ($env !== 'testing') {
            return;
        }

        $database = (string) ($config['db_database'] ?? self::resolveEnv('DB_DATABASE', 'database.connections.mysql.database', ''));
        $redisPrefix = (string) ($config['redis_prefix'] ?? self::resolveEnv('REDIS_PREFIX', 'database.redis.options.prefix', ''));

        if (! self::matchesAnyMarker($database, self::DB_MARKERS)) {
            throw new RuntimeException(sprintf(
                'Refusing to run tests against database "%s": the name must contain one of %s. '
                .'Set DB_DATABASE to a *_testing / *_test / *_e2e database to prevent data loss.',
                $database,
                implode(', ', array_map(fn (string $m): string => "\"{$m}\"", self::DB_MARKERS)),
            ));
        }

        if (stripos($redisPrefix, self::REDIS_MARKER) === false) {
            throw new RuntimeException(sprintf(
                'Refusing to run tests with Redis prefix "%s": the prefix must contain "%s". '
                .'Set REDIS_PREFIX to a test-specific value to prevent clobbering dev data.',
                $redisPrefix,
                self::REDIS_MARKER,
            ));
        }
    }

    /**
     * True if $haystack contains any of the markers (case-insensitive).
     *
     * @param  list<string>  $markers
     */
    private static function matchesAnyMarker(string $haystack, array $markers): bool
    {
        foreach ($markers as $marker) {
            if (stripos($haystack, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a configuration value from $_SERVER (set by phpunit.xml),
     * falling back to the Laravel config repository (which reads from .env
     * or the cached config). This ensures the guard works both when the
     * config is cached (Docker) and when it is not (CI).
     *
     * @param  non-empty-string  $envKey  The $_SERVER key (e.g. 'DB_DATABASE').
     * @param  non-empty-string  $configKey  The Laravel config key (e.g. 'database.connections.mysql.database').
     * @param  string  $default  Fallback when neither source has a value.
     */
    private static function resolveEnv(string $envKey, string $configKey, string $default): string
    {
        // PHPUnit's <server> directive populates $_SERVER, which survives
        // config caching. Check it first so the guard sees the test-specific
        // values even when the config cache holds the dev/production values.
        if (isset($_SERVER[$envKey]) && is_string($_SERVER[$envKey]) && $_SERVER[$envKey] !== '') {
            return $_SERVER[$envKey];
        }

        // Fall back to the Laravel config repository for Artisan commands
        // (where $_SERVER is not populated by phpunit.xml).
        $value = config($configKey);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $default;
    }
}
