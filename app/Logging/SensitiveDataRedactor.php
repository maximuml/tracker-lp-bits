<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Monolog processor that recursively redacts sensitive values from log records.
 *
 * Wired into every log channel via the ``tap`` key in ``config/logging.php``.
 * Scans ``message``, ``context``, and ``extra`` for keys (case-insensitive)
 * matching the deny-list and replaces their values with ``[REDACTED]``.
 *
 * The deny-list covers credentials, auth tokens, and identifiers that must
 * never appear in log pipelines (ELK, Loki, Datadog) or error responses.
 */
final class SensitiveDataRedactor
{
    /**
     * Keys (lowercased) whose values must be redacted.
     *
     * Matches against array keys, JSON object keys, and query-string
     * parameter names. Substring match is used so that ``password_hash``
     * and ``old_passkey`` are also caught.
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'passhash',
        'passhash_algo',
        'passkey',
        'auth_key',
        'secret',
        'editsecret',
        'token',
        'signature',
        'verity',
        'authorization',
        'cookie',
        'csrf',
        'csrf_token',
        '_token',
        'hash',
        'nonce',
        'api_key',
        'apikey',
        'private_key',
        'session',
        'access_token',
        'refresh_token',
        'bearer',
    ];

    /**
     * Redacted placeholder written in place of sensitive values.
     */
    private const PLACEHOLDER = '[REDACTED]';

    private static ?self $instance = null;

    /**
     * Singleton accessor — the processor is stateless so a single
     * instance can be shared across all channels.
     */
    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    /**
     * Tap callback invoked by Laravel for each log channel that lists
     * this class in its ``tap`` array.
     */
    public function __invoke(IlluminateLogger $logger): void
    {
        $monolog = $logger->getLogger();
        if (! $monolog instanceof Logger) {
            return;
        }

        $monolog->pushProcessor(function (LogRecord $record): LogRecord {
            return $this->redactRecord($record);
        });
    }

    /**
     * Redact a single Monolog record, returning a new immutable instance.
     */
    private function redactRecord(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->redactString($record->message),
            context: $this->redactArray($record->context),
            extra: $this->redactArray($record->extra),
        );
    }

    /**
     * Redact sensitive substrings from a plain string.
     *
     * Catches ``key=value`` and ``"key":"value"`` patterns that arise
     * when request parameters are stringified via ``Json::encode`` or
     * ``sprintf`` before being passed to the logger.
     */
    private function redactString(string $value): string
    {
        foreach (self::SENSITIVE_KEYS as $key) {
            // JSON: "password":"secret123"
            $value = preg_replace(
                '/"('.preg_quote($key, '/').')"\s*:\s*"[^"]*"/i',
                '"$1":"'.self::PLACEHOLDER.'"',
                $value,
            ) ?? $value;

            // query-string / sprintf: password=secret123
            $value = preg_replace(
                '/\b('.preg_quote($key, '/').')\s*=\s*[^&,\s\]"\']+/i',
                '$1='.self::PLACEHOLDER,
                $value,
            ) ?? $value;
        }

        return $value;
    }

    /**
     * Recursively redact sensitive keys from an array.
     *
     * @param  array<string|int, mixed>  $data
     * @return array<string|int, mixed>
     */
    private function redactArray(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $result[$key] = self::PLACEHOLDER;

                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->redactArray($value);
            } elseif (is_string($value)) {
                $result[$key] = $this->redactString($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Determine whether a key name matches a sensitive pattern.
     */
    private function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($lower === $sensitive) {
                return true;
            }
            // Substring match for compound keys: passhash_algo, old_passkey, etc.
            if (str_contains($lower, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
