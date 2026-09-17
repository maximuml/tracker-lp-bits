<?php

declare(strict_types=1);

namespace App\Services\Installer;

use App\Support\Env;
use Illuminate\Support\Facades\DB;

/**
 * Writes/merges `.env` for install and upgrade.
 *
 * Ported from `Install::createEnvFile()` / `Update::updateEnvFile()`.
 * The generated `APP_KEY` (base64 of 32 CSPRNG bytes) and the 0640
 * permission are pinned by `AppKeyTest`.
 */
final class EnvFileWriter
{
    public const APP_KEY_PLACEHOLDER = 'ChangeMeToYourGeneratedAppKeyNow';

    /**
     * Create or merge `.env` from `.env.example` plus $overrides.
     * On a fresh write (or when $freshInstall) forces production-shaped
     * defaults and generates a fresh APP_KEY when the placeholder is set.
     *
     * @param  array<string, string>  $overrides
     * @param  callable(string):void|null  $log
     */
    public function write(array $overrides = [], bool $freshInstall = true, ?callable $log = null): void
    {
        $rootPath = base_path();
        $envExampleFile = $rootPath.'/.env.example';
        $envExampleData = Env::load($envExampleFile);
        $envFile = $rootPath.'/.env';
        $newData = [];
        if (file_exists($envFile) && is_readable($envFile)) {
            $newData = Env::load($envFile);
            $this->log($log, '[CREATE ENV] .env exists, loaded '.count($newData).' keys');
        }

        foreach ($envExampleData as $key => $value) {
            if (isset($overrides[$key])) {
                $newData[$key] = trim($overrides[$key]);
            } elseif (! isset($newData[$key])) {
                $newData[$key] = $value;
            }
            if ($key === 'CACHE_DRIVER') {
                $newData[$key] = 'redis';
            }
            if ($key === 'QUEUE_CONNECTION') {
                $newData[$key] = 'redis';
            }
            if ($freshInstall || ! file_exists($envFile)) {
                if ($key === 'APP_ENV') {
                    $newData[$key] = 'production';
                }
                if ($key === 'APP_DEBUG') {
                    $newData[$key] = 'false';
                }
                if ($key === 'LOG_LEVEL') {
                    $newData[$key] = 'info';
                }
                if ($key === 'APP_KEY') {
                    $current = (string) ($newData['APP_KEY'] ?? '');
                    if ($current === '' || $current === self::APP_KEY_PLACEHOLDER) {
                        $newData[$key] = 'base64:'.base64_encode(random_bytes(32));
                        $this->log($log, '[CREATE ENV] generated fresh APP_KEY');
                    }
                }
            }
        }

        // Fail fast before writing if the configured DB/Redis are unreachable.
        DB::connection()->getPdo();
        $redis = new \Redis;
        $redis->connect($newData['REDIS_HOST'], (int) ($newData['REDIS_PORT'] ?: 6379));
        if (! empty($overrides['REDIS_PASSWORD'])) {
            $redis->auth($overrides['REDIS_PASSWORD']);
        }
        if (isset($newData['REDIS_DB'])) {
            if (! ctype_digit((string) $newData['REDIS_DB']) || $newData['REDIS_DB'] < 0 || $newData['REDIS_DB'] > 15) {
                throw new \InvalidArgumentException('invalid redis database: '.$newData['REDIS_DB']);
            }
            $redis->select((int) $newData['REDIS_DB']);
        }

        $this->writeFile($envFile, $newData);
        $this->log($log, "[CREATE ENV] wrote {$envFile} with ".count($newData).' keys');
        $this->warnOnInsecureDefaults($newData, $log);
    }

    /**
     * Merge any keys present in `.env.example` but missing from `.env`
     * (upgrade path — never overwrites operator values).
     *
     * @param  callable(string):void|null  $log
     */
    public function mergeNewKeys(?callable $log = null): void
    {
        $envFile = base_path('.env');
        $envExample = base_path('.env.example');
        $envData = Env::load($envFile);
        $envExampleData = Env::load($envExample);
        $added = 0;
        foreach ($envExampleData as $key => $value) {
            if (! isset($envData[$key])) {
                $envData[$key] = $value;
                $added++;
            }
        }
        $this->writeFile($envFile, $envData);
        $this->log($log, "[UPDATE ENV] merged {$added} new key(s)");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeFile(string $envFile, array $data): void
    {
        $isNew = ! file_exists($envFile);
        $content = '';
        foreach ($data as $key => $value) {
            $content .= "{$key}={$value}\n";
        }
        $fp = @fopen($envFile, 'w');
        if ($fp === false) {
            throw new \RuntimeException("can't create env file, make sure php has permission to create file at: ".base_path());
        }
        fwrite($fp, $content);
        fclose($fp);
        if ($isNew) {
            // Fresh .env gets restrictive permissions; an existing file keeps
            // the operator-managed mode (fopen('w') preserves the inode, so a
            // chmod here would silently cut the PHP-FPM user out of a file it
            // could previously read).
            if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
                @chgrp($envFile, 'www-data');
            }
            @chmod($envFile, 0640);
        }
    }

    /**
     * Log warnings when well-known insecure default secrets are detected.
     * Does not block installation — the values may be intentional for
     * local development — but alerts the operator to change them.
     *
     * @param  array<string, mixed>  $envData
     * @param  callable(string):void|null  $log
     */
    private function warnOnInsecureDefaults(array $envData, ?callable $log): void
    {
        $insecureDefaults = [
            'DB_PASSWORD' => ['nexusphp', 'ChangeMeToYourDBPassword', 'root', 'password', ''],
            'REDIS_PASSWORD' => ['changeme_redis_password', '', 'redis'],
            'MEILISEARCH_MASTER_KEY' => ['nexusphp_default_key', ''],
        ];
        foreach ($insecureDefaults as $key => $badValues) {
            $current = (string) ($envData[$key] ?? '');
            if (in_array($current, $badValues, true)) {
                $this->log($log, "[SECURITY WARNING] {$key} is set to an insecure default ('{$current}'). Change it before exposing to the internet.");
            }
        }
    }

    /**
     * @param  callable(string):void|null  $log
     */
    private function log(?callable $log, string $message): void
    {
        if ($log !== null) {
            $log($message);
        }
    }
}
