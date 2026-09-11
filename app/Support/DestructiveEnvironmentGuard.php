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
        // APP_ENV is the *claim* ("this run intends to be a test") — read the
        // superglobal first because phpunit.xml sets it via <server>.
        $env = (string) ($config['app_env'] ?? self::resolveClaim('APP_ENV', 'app.env', ''));

        // Only enforce when the application believes it is in the testing
        // environment. Production and local dev are not gated here — they have
        // their own protections (migrate:fresh --force prompts, etc.).
        if ($env !== 'testing') {
            return;
        }

        // DB_DATABASE / REDIS_PREFIX describe *reality* — what the connection
        // will actually use. The resolved config repository wins over
        // $_SERVER: when bootstrap/cache/config.php exists, env overrides are
        // inert, so trusting them would let a "testing" claim pass while
        // queries still hit the dev database.
        $database = (string) ($config['db_database'] ?? self::resolveReality('DB_DATABASE', 'database.connections.mysql.database', ''));
        $redisPrefix = (string) ($config['redis_prefix'] ?? self::resolveReality('REDIS_PREFIX', 'database.redis.options.prefix', ''));

        if (! self::matchesAnyMarker($database, self::DB_MARKERS)) {
            throw new RuntimeException(sprintf(
                'Refusing to run tests against database "%s": the name must contain one of %s. '
                .'Set DB_DATABASE to a *_testing / *_test / *_e2e database to prevent data loss.%s',
                $database,
                implode(', ', array_map(fn (string $m): string => "\"{$m}\"", self::DB_MARKERS)),
                self::staleConfigHint('DB_DATABASE', $database),
            ));
        }

        if (stripos($redisPrefix, self::REDIS_MARKER) === false) {
            throw new RuntimeException(sprintf(
                'Refusing to run tests with Redis prefix "%s": the prefix must contain "%s". '
                .'Set REDIS_PREFIX to a test-specific value to prevent clobbering dev data.%s',
                $redisPrefix,
                self::REDIS_MARKER,
                self::staleConfigHint('REDIS_PREFIX', $redisPrefix),
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
     * Resolve the *claimed* environment name: $_SERVER first (phpunit.xml
     * <server> directive), then the config repository.
     *
     * @param  non-empty-string  $envKey  The $_SERVER key (e.g. 'APP_ENV').
     * @param  non-empty-string  $configKey  The Laravel config key.
     * @param  string  $default  Fallback when neither source has a value.
     */
    private static function resolveClaim(string $envKey, string $configKey, string $default): string
    {
        $value = self::serverValue($envKey);
        if ($value !== null) {
            return $value;
        }

        $value = config($configKey);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * Resolve the value the app will *actually* use: the config repository
     * first (authoritative — when the config is cached, env vars are inert),
     * then $_SERVER for pre-boot contexts.
     *
     * @param  non-empty-string  $envKey  The $_SERVER key (e.g. 'DB_DATABASE').
     * @param  non-empty-string  $configKey  The Laravel config key.
     * @param  string  $default  Fallback when neither source has a value.
     */
    private static function resolveReality(string $envKey, string $configKey, string $default): string
    {
        $value = config($configKey);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return self::serverValue($envKey) ?? $default;
    }

    /**
     * Explain the common failure mode: env claims a test value while the
     * resolved (possibly cached) config still points at the dev database.
     *
     * @param  non-empty-string  $envKey
     */
    private static function staleConfigHint(string $envKey, string $resolved): string
    {
        $claimed = self::serverValue($envKey);
        if ($claimed === null || $claimed === $resolved) {
            return '';
        }

        return sprintf(
            ' Note: %s="%s" is set but the resolved config uses "%s" — a stale '
            .'config cache makes env overrides inert; run `php artisan config:clear`.',
            $envKey,
            $claimed,
            $resolved,
        );
    }

    /**
     * Read a single $_SERVER entry. The guard is the one place in app/ where
     * superglobal access is intentional: it exists precisely to compare the
     * claimed env (phpunit.xml <server>, docker -e) against the resolved
     * config before the request ever boots.
     *
     * @param  non-empty-string  $key
     */
    private static function serverValue(string $key): ?string
    {
        $value = $_SERVER[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
