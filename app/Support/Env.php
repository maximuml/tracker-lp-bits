<?php

namespace App\Support;

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
        if (self::$env === null) {
            self::$env = self::load(dirname(__DIR__, 2) . '/.env');
        }

        if ($key === null) {
            return self::$env;
        }

        if (array_key_exists($key, self::$env)) {
            return self::$env[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }

    /**
     * @return array<string, string>
     */
    public static function load(string $envFile): array
    {
        if (!file_exists($envFile)) {
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
