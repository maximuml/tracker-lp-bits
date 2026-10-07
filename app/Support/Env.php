<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Env as LaravelEnv;

/**
 * `.env` access helpers extracted from `include/globalfunctions.php`.
 *
 * Phase 5 of the legacy migration. Mirrors the legacy `nexus_env()`,
 * `readEnvFile()` and `normalize_env()` helpers while keeping the
 * in-request static cache.
 */
final class Env
{
    /** @var array<string, string>|null */
    private static ?array $env = null;

    public static function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key !== null) {
            // Real environment wins over the .env file — LaravelEnv reads
            // the same $_ENV/$_SERVER adapters as env(), so phpunit <server>
            // overrides (e.g. REDIS_DB=15) and docker -e variables actually
            // reach nexus.* config. Without this, NexusCache kept
            // writing test data into the dev Redis keyspace. `has()` first:
            // Env normalizes 'null' to real null, which must not fall through.
            if (LaravelEnv::getRepository()->has($key)) {
                return LaravelEnv::get($key);
            }
            $value = getenv($key);
            if ($value !== false) {
                return $value;
            }
        }

        if (self::$env === null) {
            self::$env = self::load(dirname(__DIR__, 2).'/.env');
        }

        if ($key === null) {
            return self::$env;
        }

        return self::$env[$key] ?? $default;
    }

    /**
     * @return array<string, string>
     */
    public static function load(string $envFile): array
    {
        if (! file_exists($envFile)) {
            if (\PHP_SAPI === 'cli') {
                return [];
            }
            throw new \RuntimeException("env file : $envFile is not exists in the root path.");
        }

        $fp = fopen($envFile, 'r');
        if ($fp === false) {
            throw new \RuntimeException(".env file: $envFile is not readable.");
        }

        $env = [];
        while (($line = fgets($fp)) !== false) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos <= 0) {
                continue;
            }
            if (mb_substr($line, 0, 1, 'utf-8') == '#') {
                continue;
            }
            $lineKey = self::normalize(mb_substr($line, 0, $pos, 'utf-8'));
            $lineValue = self::normalize(mb_substr($line, $pos + 1, null, 'utf-8'));
            $env[$lineKey] = $lineValue;
        }
        fclose($fp);

        return $env;
    }

    public static function normalize(string $value): string
    {
        $value = trim($value);
        $toStrip = ["'", '"'];
        if (in_array(mb_substr($value, 0, 1, 'utf-8'), $toStrip)) {
            $value = mb_substr($value, 1, null, 'utf-8');
        }
        if (in_array(mb_substr($value, -1, null, 'utf-8'), $toStrip)) {
            $value = mb_substr($value, 0, -1, 'utf-8');
        }

        return $value;
    }

    /**
     * Normalize a raw .env value and cast common boolean/null strings.
     *
     * Backs the `normalize_env()` helper.
     */
    public static function cast(mixed $value): mixed
    {
        $normalized = self::normalize((string) $value);

        return match (strtolower($normalized)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $normalized,
        };
    }
}
