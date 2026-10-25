<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\SettingsValidationException;
use App\Models\Setting;
use App\Support\Cache;
use App\Support\Logger;
use App\Support\SettingsSchemaValidator;
use Illuminate\Support\Arr;

class SettingRepository extends BaseRepository
{
    public function __construct(
        private readonly SettingsSchemaValidator $schemaValidator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return Setting::getFromDb();
    }

    public function getByName(string $name, mixed $default = null): mixed
    {
        return Setting::getByName($name, $default);
    }

    /**
     * Dotted setting names whose values never leave the API response,
     * combined with the leaf-name rule in {@see SENSITIVE_LEAF_PATTERN}.
     */
    private const SENSITIVE_SETTING_NAMES = [
        'security.iv' => true,
    ];

    /**
     * Leaf fields whose values are credentials and are always redacted —
     * catches password/secret/key columns in any prefix, including settings
     * that exist in the table without a schema entry. Suffix anchored so
     * config metadata like `login_secret_lifetime` stays visible.
     */
    private const SENSITIVE_LEAF_PATTERN = '/(?:password|secret|credential|private_key|access_key|api_key|secret_key)$/i';

    /**
     * @param  array<int|string, mixed>  $params
     * @return array<int|string, mixed>
     */
    public function getList(array $params)
    {
        $results = self::redactSensitive(Setting::getFromDb());
        $prefix = $params['prefix'] ?? null;
        if ($prefix) {
            return [$prefix => Arr::get($results, $prefix, [])];
        }

        return $results;
    }

    /**
     * Store a batch of settings grouped by prefix. Each prefix must have a
     * JSON schema in config/settings-schema/ — this is the allowlist that
     * keeps arbitrary `settings` rows (and secrets) from being written
     * through the API. Values are validated via {@see saveBatch()}.
     *
     * @param  array<int|string, mixed>  $params
     */
    public function store(array $params): bool
    {
        if ($params === []) {
            Logger::writeWithContext('no values', 'info', false);

            return true;
        }

        foreach ($params as $prefix => $nameValues) {
            if (! is_array($nameValues)) {
                throw new \InvalidArgumentException('Unsupported parameter format.');
            }
            $prefix = strtolower((string) $prefix);
            if (! $this->schemaValidator->hasSchema($prefix)) {
                throw new \InvalidArgumentException("Unsupported settings prefix: $prefix");
            }
            $this->saveBatch($prefix, $nameValues);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $nameAndValue
     */
    public function saveBatch(string $prefix, array $nameAndValue, bool $autoload = true): void
    {
        $prefix = strtolower($prefix);

        // Validate against JSON schema before persisting.
        $errors = $this->schemaValidator->validatePrefix($prefix, $nameAndValue);
        if (! empty($errors)) {
            throw new SettingsValidationException($prefix, $errors);
        }

        $datetimeNow = date('Y-m-d H:i:s');
        $records = [];

        foreach ($nameAndValue as $name => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $records[] = [
                'name' => "$prefix.$name",
                'value' => $value,
                'created_at' => $datetimeNow,
                'updated_at' => $datetimeNow,
                'autoload' => $autoload,
            ];
        }

        if (! empty($records)) {
            Setting::query()->upsert($records, ['name'], ['value', 'updated_at']);
        }

        Cache::clearSettings();
    }

    /**
     * @param  array<int|string, mixed>  $settings
     * @return array<int|string, mixed>
     */
    private static function redactSensitive(array $settings, string $prefix = ''): array
    {
        $redacted = [];
        foreach ($settings as $name => $value) {
            $dotted = $prefix === '' ? (string) $name : $prefix.'.'.$name;
            if (is_array($value)) {
                $redacted[$name] = self::redactSensitive($value, $dotted);

                continue;
            }
            if (isset(self::SENSITIVE_SETTING_NAMES[$dotted])
                || preg_match(self::SENSITIVE_LEAF_PATTERN, (string) $name) === 1) {
                $redacted[$name] = null;

                continue;
            }
            $redacted[$name] = $value;
        }

        return $redacted;
    }
}
