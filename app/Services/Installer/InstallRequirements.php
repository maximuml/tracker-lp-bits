<?php

declare(strict_types=1);

namespace App\Services\Installer;

/**
 * Environment requirements check for `app:install`/`app:upgrade`.
 *
 * Ported from the legacy web installer's `listRequirementTableRows()`,
 * minus the exec-probing that existed only because the wizard ran under
 * FPM while the work happened on CLI. These commands always run on the
 * same CLI the app runs on, so direct checks are accurate.
 */
final class InstallRequirements
{
    public const MINIMUM_PHP = '8.4.0';

    /** @var list<string> */
    private const REQUIRED_EXTENSIONS = [
        'ctype', 'curl', 'fileinfo', 'json', 'mbstring', 'openssl', 'pdo_mysql', 'tokenizer', 'xml',
        'bcmath', 'redis', 'gd', 'gmp', 'Zend OPcache', 'pcntl', 'posix', 'sockets', 'zip', 'intl',
        'sqlite3', 'pdo_sqlite',
    ];

    /** @var list<string> */
    private const CONFLICT_EXTENSIONS = ['mysql'];

    /** @var list<string> */
    private const REQUIRED_FUNCTIONS = [
        'symlink', 'putenv', 'proc_open', 'proc_get_status', 'exec',
        'pcntl_signal', 'pcntl_alarm', 'pcntl_async_signals',
    ];

    /**
     * @return array{rows: list<array{label: string, required: string, current: string|int, result: string}>, fails: list<array{label: string, required: string, current: string|int, result: string}>, pass: bool}
     */
    public function check(): array
    {
        $rows = [];
        $rows[] = [
            'label' => 'PHP version',
            'required' => '>= '.self::MINIMUM_PHP,
            'current' => PHP_VERSION,
            'result' => version_compare(PHP_VERSION, self::MINIMUM_PHP, '>=') ? 'YES' : 'NO',
        ];

        $disabledFunctions = array_values(array_filter(
            self::REQUIRED_FUNCTIONS,
            fn (string $fn): bool => ! function_exists($fn),
        ));
        $rows[] = [
            'label' => 'Required functions',
            'required' => 'true',
            'current' => $disabledFunctions === []
                ? '1'
                : 'These functions are disabled: '.implode(',', $disabledFunctions),
            'result' => $disabledFunctions === [] ? 'YES' : 'NO',
        ];

        foreach (self::CONFLICT_EXTENSIONS as $extension) {
            $loaded = extension_loaded($extension);
            $rows[] = [
                'label' => "PHP extension $extension",
                'required' => 'disabled',
                'current' => (int) $loaded,
                'result' => $loaded ? 'NO' : 'YES',
            ];
        }

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $loaded = extension_loaded($extension);
            $rows[] = [
                'label' => "PHP extension $extension",
                'required' => 'enabled',
                'current' => (int) $loaded,
                'result' => $loaded ? 'YES' : 'NO',
            ];
        }

        $gdInfo = function_exists('gd_info') ? gd_info() : [];
        foreach (['JPEG Support', 'PNG Support', 'GIF Read Support'] as $capability) {
            $rows[] = [
                'label' => "PHP extension gd $capability",
                'required' => 'true',
                'current' => (string) ($gdInfo[$capability] ?? ''),
                'result' => ($gdInfo[$capability] ?? false) ? 'YES' : 'NO',
            ];
        }

        $phpVersionRequire = '>= '.self::MINIMUM_PHP;
        $fails = array_values(array_filter(
            $rows,
            fn (array $row): bool => in_array($row['required'], ['true', 'enabled', $phpVersionRequire], true)
                && $row['result'] === 'NO',
        ));

        return [
            'rows' => $rows,
            'fails' => $fails,
            'pass' => $fails === [],
        ];
    }
}
