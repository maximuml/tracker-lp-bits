<?php

namespace App\Support;

/**
 * Runtime environment helpers extracted from `include/globalfunctions.php`.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 */
final class Environment
{
    public static function isConsole(): bool
    {
        return (!defined('RUNNING_IN_OCTANE') || !RUNNING_IN_OCTANE) && PHP_SAPI === 'cli';
    }

    public static function isWindows(): bool
    {
        return (!defined('RUNNING_IN_OCTANE') || !RUNNING_IN_OCTANE) && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    public static function isFpm(): bool
    {
        return php_sapi_name() === 'fpm-fcgi';
    }

    public static function commandExists(string $command): bool
    {
        return trim((string) exec("command -v $command")) !== '';
    }

    /**
     * Execute a shell command and return its output.
     *
     * Mirrors `executeCommand()`. When `$artisan` is true, the command is
     * prefixed with the configured PHP binary and `artisan`.
     */
    public static function run(string $command, string $format = 'string', bool $artisan = false, bool $exception = true): string|array
    {
        $append = ' 2>&1';
        if (!str_ends_with($command, $append)) {
            $command .= $append;
        }

        if ($artisan) {
            $phpPath = \nexus_env('PHP_PATH') ?: 'php';
            $webRoot = rtrim(ROOT_PATH, '/');
            $command = "$phpPath $webRoot/artisan $command";
        }

        \do_log("command: $command");
        $result = exec($command, $output, $resultCode);
        $outputString = implode("\n", $output);
        $log = sprintf('result_code: %s, result: %s, output: %s', $resultCode, $result, $outputString);

        if ($resultCode != 0) {
            \do_log($log, 'error');
            if ($exception) {
                throw new \RuntimeException($outputString);
            }
        } else {
            \do_log($log);
        }

        return $format === 'string' ? $outputString : $output;
    }
}
