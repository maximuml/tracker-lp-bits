<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Parses PHP's own error_log file (php-fpm / CLI "PHP <level>:" entries).
 * Entries are returned newest-first; continuation lines (stack frames,
 * "Stack trace:", "#0 ...", "thrown in ...") are attached to the entry
 * they belong to.
 */
final class PhpErrorLogParser
{
    private const string ENTRY_RE = '/^\[(?<time>\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2}(?: [A-Za-z+\/:\d_-]+)?)\] PHP (?<level>Fatal error|Parse error|Warning|Notice|Deprecated|Strict standards|Recoverable fatal error|Core Warning|Core error|Compile Warning|Compile error|User error|User warning|User notice|User deprecated|Uncaught exception|Exception|Stack trace):\s?(?<message>.*)$/';

    /** @var array<string, string> raw level => normalized key */
    private const array LEVEL_MAP = [
        'Fatal error' => 'fatal',
        'Recoverable fatal error' => 'fatal',
        'Core error' => 'fatal',
        'Parse error' => 'parse',
        'Compile error' => 'compile',
        'Compile Warning' => 'warning',
        'Core Warning' => 'warning',
        'Warning' => 'warning',
        'User warning' => 'warning',
        'User error' => 'user_error',
        'Notice' => 'notice',
        'User notice' => 'notice',
        'Deprecated' => 'deprecated',
        'User deprecated' => 'deprecated',
        'Strict standards' => 'strict',
        'Exception' => 'exception',
        'Uncaught exception' => 'exception',
        'Stack trace' => 'trace',
    ];

    /**
     * @return list<array{id: int, time: string, level: string, level_key: string, message: string, context: string, full: string}>
     */
    public static function entries(string $path, int $limit = 500, int $maxBytes = 2097152): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }
        $size = filesize($path);
        if ($size === false || $size === 0) {
            return [];
        }

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return [];
        }
        $offset = max(0, $size - $maxBytes);
        if ($offset > 0) {
            fseek($fh, $offset);
            fgets($fh); // drop the partial first line
        }
        $raw = stream_get_contents($fh);
        fclose($fh);
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $entries = [];
        $current = null;
        foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
            if (preg_match(self::ENTRY_RE, $line, $m) === 1) {
                $entries[] = [
                    'id' => 0,
                    'time' => self::parseTime($m['time']),
                    'level' => $m['level'],
                    'level_key' => self::LEVEL_MAP[$m['level']],
                    'message' => trim($m['message']),
                    'context' => '',
                    'full' => $line,
                ];
                $current = count($entries) - 1;

                continue;
            }
            if ($current !== null && $line !== '') {
                $entries[$current]['context'] .= ($entries[$current]['context'] === '' ? '' : "\n").$line;
                $entries[$current]['full'] .= "\n".$line;
            }
        }

        $entries = array_slice(array_reverse($entries), 0, $limit);
        $i = 0;
        foreach ($entries as &$entry) {
            $entry['id'] = $i++;
        }
        unset($entry);

        return array_values($entries);
    }

    /**
     * Distinct normalized levels present in the file, for filter options.
     *
     * @return array<string, string>
     */
    public static function levelOptions(): array
    {
        return [
            'fatal' => 'Fatal',
            'parse' => 'Parse',
            'compile' => 'Compile',
            'warning' => 'Warning',
            'user_error' => 'User error',
            'notice' => 'Notice',
            'deprecated' => 'Deprecated',
            'strict' => 'Strict',
            'exception' => 'Exception',
            'other' => 'Other',
        ];
    }

    private static function parseTime(string $raw): string
    {
        $dt = \DateTimeImmutable::createFromFormat('d-M-Y H:i:s T', $raw)
            ?: \DateTimeImmutable::createFromFormat('d-M-Y H:i:s', $raw);

        return $dt instanceof \DateTimeImmutable ? $dt->format('Y-m-d H:i:s') : $raw;
    }
}
