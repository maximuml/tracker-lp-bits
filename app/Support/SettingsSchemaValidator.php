<?php

declare(strict_types=1);

namespace App\Support;

use JsonSchema\Validator as JsonSchemaValidator;

/**
 * Validates settings values against JSON schemas defined in
 * {@code config/settings-schema/*.json}.
 *
 * Each schema file corresponds to a settings prefix (e.g. {@code main.json}
 * for the {@code main.*} settings). Schemas follow JSON Schema draft-07.
 */
final class SettingsSchemaValidator
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $schemas = null;

    /** @var array<string, object>|null */
    private ?array $schemaObjects = null;

    /**
     * Load and cache all schema files from {@code config/settings-schema/}.
     *
     * @return array<string, array<string, mixed>>
     */
    public function schemas(): array
    {
        if ($this->schemas !== null) {
            return $this->schemas;
        }

        $this->schemas = [];
        $this->schemaObjects = [];
        $dir = config_path('settings-schema');
        if (! is_dir($dir)) {
            return $this->schemas;
        }

        foreach (new \DirectoryIterator($dir) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'json') {
                continue;
            }
            $prefix = $file->getBasename('.json');
            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }
            $decoded = json_decode($contents, true);
            if (is_array($decoded)) {
                $this->schemas[$prefix] = $decoded;
                // Also keep the stdClass version for the JsonSchema validator,
                // which requires nested objects to be stdClass, not arrays.
                $obj = json_decode($contents);
                if ($obj instanceof \stdClass) {
                    $this->schemaObjects[$prefix] = $obj;
                }
            }
        }

        return $this->schemas;
    }

    /**
     * Validate a batch of settings for a single prefix.
     *
     * @param  array<string, mixed>  $nameAndValue
     * @return list<string> List of validation error messages (empty if valid).
     */
    public function validatePrefix(string $prefix, array $nameAndValue): array
    {
        $this->schemas();
        $schemaObj = $this->schemaObjects[$prefix] ?? null;
        if ($schemaObj === null) {
            return [];
        }

        // Build a data object that only includes the keys being written,
        // with values coerced to the types the schema expects.
        $data = new \stdClass;
        foreach ($nameAndValue as $key => $value) {
            $data->$key = $this->coerceValue($value);
        }

        $validator = new JsonSchemaValidator;
        $validator->validate($data, $schemaObj);

        $errors = [];
        if (! $validator->isValid()) {
            foreach ($validator->getErrors() as $error) {
                $errors[] = sprintf(
                    '%s.%s: %s',
                    $prefix,
                    $error['property'],
                    $error['message'],
                );
            }
        }

        return $errors;
    }

    /**
     * Validate all settings in the database against their schemas.
     *
     * @return array<string, list<string>> Map of prefix → error messages.
     */
    public function validateAll(): array
    {
        $allSettings = Settings::fromDb();
        $results = [];

        foreach ($allSettings as $prefix => $values) {
            if (! is_array($values)) {
                continue;
            }
            $errors = $this->validatePrefix((string) $prefix, $values);
            if (! empty($errors)) {
                $results[(string) $prefix] = $errors;
            }
        }

        return $results;
    }

    /**
     * Check whether a schema exists for the given prefix.
     */
    public function hasSchema(string $prefix): bool
    {
        return isset($this->schemas()[$prefix]);
    }

    /**
     * Coerce a settings value to a type compatible with JSON Schema validation.
     *
     * Settings are stored as strings in the database, but schemas may expect
     * integers, numbers, arrays, or booleans. This method attempts to coerce
     * string values to their natural types for validation.
     */
    private function coerceValue(mixed $value): mixed
    {
        if (is_string($value)) {
            // Try to decode JSON arrays/objects
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            // Keep as string — the schema allows both integer/string
            return $value;
        }

        return $value;
    }
}
